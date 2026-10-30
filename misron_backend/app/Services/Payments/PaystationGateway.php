<?php

namespace App\Services\Payments;

use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\PaymentGatewayAttempt;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Everything the store does with Paystation after the customer has been sent to
 * it: asking what happened to a payment, marking an order paid exactly once,
 * and cancelling an order only when the gateway says no payment went through.
 *
 * Used by the gateway's return and notification endpoints (PaystationController)
 * and by the expiry of stale unpaid orders (orders:expire-unpaid), so the rules
 * are the same whichever of them gets there first.
 *
 * The three answers the gateway can give are kept apart on purpose:
 *  - PAID: the gateway confirmed a successful transaction for the full amount;
 *  - FAILED: the gateway confirmed the transaction failed or was cancelled;
 *  - UNKNOWN: anything else (no answer, an error, still pending, an unexpected
 *    value). An UNKNOWN never cancels an order, because the money may have been
 *    taken; the order waits for a later answer or for staff.
 */
class PaystationGateway
{
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const UNKNOWN = 'unknown';

    /** Transaction states the gateway uses for a payment that will not complete. */
    private const FAILED_STATES = ['failed', 'failure', 'fail', 'cancel', 'canceled', 'cancelled', 'rejected', 'declined', 'expired'];
    private const SUCCESS_STATES = ['success', 'successful'];

    private string $baseUrl;
    private ?string $merchantId;
    private ?string $password;

    public function __construct()
    {
        $this->baseUrl = get_setting('paystation_sandbox', 1)
            ? 'https://sandbox.paystation.com.bd/'
            : 'https://api.paystation.com.bd/';
        $this->merchantId = env('PAYSTATION_MERCHANT_ID');
        $this->password = env('PAYSTATION_PASSWORD');
    }

    // ── Starting a payment ─────────────────────────────────────────────────

    /**
     * Why this order must not be sent to the gateway, or null when it may.
     * A paid order would be charged twice; a cancelled one has given its stock back.
     */
    public function refusal(CombinedOrder $combined): ?string
    {
        $orders = $combined->orders;
        if ($orders->isEmpty()) {
            return 'Order not found';
        }
        if ($orders->contains(fn ($order) => $order->payment_status === 'paid')) {
            return 'This order is already paid';
        }
        if ($orders->contains(fn ($order) => $order->delivery_status === 'cancelled')) {
            return 'This order was cancelled. Please place it again.';
        }

        return null;
    }

    public function invoiceNumber(int $combinedOrderId): string
    {
        // The gateway refuses an invoice number it has seen, so the time is part of it and an
        // abandoned payment can be retried. Random digits keep two clicks in one second apart.
        return 'AIZ-' . $combinedOrderId . '-' . date('YmdHis') . random_int(10, 99);
    }

    public static function combinedOrderId(?string $invoiceNumber): int
    {
        return (int) (explode('-', (string) $invoiceNumber)[1] ?? 0);
    }

    /** Remembers the session so its outcome can be asked for later. */
    public function recordAttempt(CombinedOrder $combined, string $invoiceNumber): void
    {
        if (!PaymentGatewayAttempt::ready()) {
            return;
        }
        PaymentGatewayAttempt::create([
            'combined_order_id' => $combined->id,
            'gateway' => 'paystation',
            'invoice_number' => $invoiceNumber,
            'amount' => $combined->grand_total,
            'status' => PaymentGatewayAttempt::STARTED,
        ]);
    }

    /** @return array|false the decoded answer of initiate-payment */
    public function initiate(array $postData)
    {
        $postData['merchantId'] = $this->merchantId;
        $postData['password'] = $this->password;

        return $this->request($this->baseUrl . 'initiate-payment', $postData);
    }

    // ── Asking what happened ───────────────────────────────────────────────

    /**
     * The gateway's answer for one invoice, checked against the order's amount.
     *
     * @return array{state: string, data: ?array, gateway_status: ?string}
     */
    public function status(string $invoiceNumber, CombinedOrder $combined): array
    {
        $response = $this->request($this->baseUrl . 'transaction-status', ['invoice_number' => $invoiceNumber]);
        Log::info('Paystation status lookup:', ['invoice_number' => $invoiceNumber, 'response' => $response]);

        if (!is_array($response) || !isset($response['data']) || !is_array($response['data'])) {
            return ['state' => self::UNKNOWN, 'data' => null, 'gateway_status' => null];
        }

        $data = $response['data'];
        $gatewayStatus = strtolower(trim((string) ($data['trx_status'] ?? '')));

        if (in_array($gatewayStatus, self::SUCCESS_STATES, true)) {
            // A successful payment for less than the order is never accepted as payment of it.
            if (abs((float) $combined->grand_total - (float) ($data['payment_amount'] ?? 0)) >= 1) {
                Log::error('Paystation amount mismatch:', [
                    'invoice_number' => $invoiceNumber,
                    'order_total' => $combined->grand_total,
                    'paid' => $data['payment_amount'] ?? 0,
                ]);

                return ['state' => self::UNKNOWN, 'data' => $data, 'gateway_status' => 'amount_mismatch'];
            }

            return ['state' => self::PAID, 'data' => $data, 'gateway_status' => $gatewayStatus];
        }

        if (in_array($gatewayStatus, self::FAILED_STATES, true)) {
            return ['state' => self::FAILED, 'data' => $data, 'gateway_status' => $gatewayStatus];
        }

        return ['state' => self::UNKNOWN, 'data' => $data, 'gateway_status' => $gatewayStatus ?: null];
    }

    /**
     * Confirms one invoice with the gateway and applies the answer: marks the order
     * paid, or cancels it when the payment definitely failed. Safe to call any number
     * of times, from the return page, the notification and the expiry job at once.
     *
     * @return string PAID, FAILED or UNKNOWN
     */
    public function confirm(?string $invoiceNumber, bool $cancelOnFailure = true): string
    {
        $combined = CombinedOrder::find(self::combinedOrderId($invoiceNumber));
        if ($combined === null) {
            return self::UNKNOWN;
        }
        if (optional($combined->orders->first())->payment_status === 'paid') {
            return self::PAID;
        }

        $answer = $this->status((string) $invoiceNumber, $combined);
        $this->noteAttempt((string) $invoiceNumber, $answer);

        if ($answer['state'] === self::PAID) {
            $this->markPaid($combined, $answer['data']);
        } elseif ($answer['state'] === self::FAILED && $cancelOnFailure && $this->startedHere((string) $invoiceNumber, $combined)) {
            $this->cancelUnpaid($combined, 'Online payment failed or was cancelled at the gateway.');
        }

        return $answer['state'];
    }

    /**
     * Whether this store sent the shopper to the gateway with this invoice number. The return
     * address is public and the invoice number is easy to guess, so a failed answer for an
     * invoice the store never started must not cancel somebody else's order. Before the
     * sessions table exists there is nothing to check against.
     */
    private function startedHere(string $invoiceNumber, CombinedOrder $combined): bool
    {
        if (!PaymentGatewayAttempt::ready()) {
            return true;
        }

        return PaymentGatewayAttempt::where('invoice_number', $invoiceNumber)
            ->where('combined_order_id', $combined->id)
            ->exists();
    }

    // ── Applying the answer ────────────────────────────────────────────────

    /**
     * Marks every order of the combined order paid, once. The lock keeps the return page
     * and the notification, which often arrive together, from both running the paid
     * steps (customer notification, commissions). A payment that arrives for an order
     * that was already cancelled is recorded and flagged for staff instead: its stock
     * was given back, so it is neither silently revived nor left looking unpaid.
     */
    public function markPaid(CombinedOrder $combined, ?array $data): void
    {
        $lock = Cache::lock('paystation-settle:' . $combined->id, 30);
        $lock->block(10, function () use ($combined, $data) {
            $orders = Order::where('combined_order_id', $combined->id)->get();
            if ($orders->isEmpty() || $orders->every(fn ($order) => $order->payment_status === 'paid')) {
                return;
            }

            if ($orders->contains(fn ($order) => $order->delivery_status === 'cancelled')) {
                foreach ($orders as $order) {
                    $order->payment_status = 'paid';
                    $order->payment_details = json_encode($data);
                    $order->additional_info = trim($order->additional_info . "\n" . 'ATTENTION: payment received after this order was cancelled. Refund the customer or restore the order by hand.');
                    $order->save();
                }
                Log::warning('Paystation payment received for a cancelled order.', ['combined_order_id' => $combined->id]);

                return;
            }

            checkout_done($combined->id, json_encode($data));
        });
    }

    /**
     * Cancels the unpaid orders of a combined order and gives their stock back, each line
     * exactly once. Only ever called after the gateway said the payment failed, or when
     * no payment was ever started; a paid or already cancelled order is left alone.
     */
    public function cancelUnpaid(CombinedOrder $combined, string $reason): void
    {
        $lock = Cache::lock('paystation-settle:' . $combined->id, 30);
        $lock->block(10, function () use ($combined, $reason) {
            foreach (Order::where('combined_order_id', $combined->id)->get() as $order) {
                if ($order->payment_status === 'paid' || $order->delivery_status === 'cancelled') {
                    continue;
                }

                $order->delivery_status = 'cancelled';
                $order->additional_info = trim($order->additional_info . "\n" . 'Cancelled automatically: ' . $reason);
                $order->save();

                foreach ($order->orderDetails as $orderDetail) {
                    app(StockLedger::class)->give($orderDetail);
                }
            }
        });
    }

    private function noteAttempt(string $invoiceNumber, array $answer): void
    {
        if (!PaymentGatewayAttempt::ready()) {
            return;
        }
        $status = [self::PAID => PaymentGatewayAttempt::PAID, self::FAILED => PaymentGatewayAttempt::FAILED][$answer['state']] ?? PaymentGatewayAttempt::UNKNOWN;
        PaymentGatewayAttempt::where('invoice_number', $invoiceNumber)
            ->where('status', '!=', PaymentGatewayAttempt::PAID)
            ->update(['status' => $status, 'gateway_status' => $answer['gateway_status'], 'checked_at' => now(), 'updated_at' => now()]);
    }

    /**
     * One HTTP call to the gateway. The initiate call carries the credentials in its
     * body, the status call expects the merchant id in the headers.
     *
     * @return array|false
     */
    protected function request(string $url, array $data)
    {
        $headers = ['Accept: application/json', 'merchantId: ' . $this->merchantId];

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_POST, 1);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);

        if (get_setting('paystation_sandbox', 1)) {
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        } else {
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        }

        $content = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        if ($code == 200 && !curl_errno($handle)) {
            curl_close($handle);

            return json_decode($content, true) ?: false;
        }

        // Never log the request body: the initiate call carries the merchant password.
        Log::error('Paystation request failed:', ['url' => $url, 'code' => $code, 'response' => $content]);
        curl_close($handle);

        return false;
    }
}
