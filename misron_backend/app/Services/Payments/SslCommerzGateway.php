<?php

namespace App\Services\Payments;

use App\Models\BusinessSetting;
use App\Models\CombinedOrder;
use App\Models\PaymentGatewayAttempt;
use Illuminate\Support\Facades\Log;

/**
 * SSLCommerz hosted checkout for storefront orders.
 *
 * The same rules as Paystation: an order is marked paid only after SSLCommerz's own validation
 * API confirms the transaction for the full amount, and an order is cancelled only when the
 * gateway definitely reports a failure for a payment this store started. Marking paid and
 * cancelling are shared with PaystationGateway, so both gateways settle orders the same way.
 */
class SslCommerzGateway
{
    public const PAID = PaystationGateway::PAID;
    public const FAILED = PaystationGateway::FAILED;
    public const UNKNOWN = PaystationGateway::UNKNOWN;

    private string $mode;
    private ?string $storeId;
    private ?string $storePass;

    public function __construct(private PaystationGateway $settle)
    {
        $sandbox = optional(BusinessSetting::where('type', 'sslcommerz_sandbox')->first())->value == 1;
        $this->mode = $sandbox ? 'sandbox' : 'securepay';
        $this->storeId = env('SSLCZ_STORE_ID');
        $this->storePass = env('SSLCZ_STORE_PASSWD');
    }

    public function configured(): bool
    {
        return !empty($this->storeId) && !empty($this->storePass);
    }

    public function refusal(CombinedOrder $combined): ?string
    {
        return $this->settle->refusal($combined);
    }

    public function transactionId(int $combinedOrderId): string
    {
        return 'AIZ-' . $combinedOrderId . '-' . date('YmdHis') . random_int(10, 99);
    }

    public static function combinedOrderId(?string $tranId): int
    {
        return (int) (explode('-', (string) $tranId)[1] ?? 0);
    }

    /** @return array|false the decoded session answer */
    public function initiate(array $postData)
    {
        $postData['store_id'] = $this->storeId;
        $postData['store_passwd'] = $this->storePass;

        return $this->call('https://' . $this->mode . '.sslcommerz.com/gwprocess/v4/api.php', $postData, true);
    }

    public function recordAttempt(CombinedOrder $combined, string $tranId): void
    {
        if (!PaymentGatewayAttempt::ready()) {
            return;
        }
        PaymentGatewayAttempt::create([
            'combined_order_id' => $combined->id,
            'gateway' => 'sslcommerz',
            'invoice_number' => $tranId,
            'amount' => $combined->grand_total,
            'status' => PaymentGatewayAttempt::STARTED,
        ]);
    }

    /** Whether this store sent the shopper to the gateway with this transaction id. */
    public function startedHere(string $tranId, CombinedOrder $combined): bool
    {
        if (!PaymentGatewayAttempt::ready()) {
            return true;
        }

        return PaymentGatewayAttempt::where('invoice_number', $tranId)->where('combined_order_id', $combined->id)->exists();
    }

    /**
     * Asks SSLCommerz about a payment (val_id comes with the return and the notification) and
     * applies the answer. Safe to call any number of times.
     *
     * @return string PAID, FAILED or UNKNOWN
     */
    public function confirm(?string $valId, ?string $tranId, bool $failureCancels = true): string
    {
        $combined = CombinedOrder::find(self::combinedOrderId($tranId));
        if ($combined === null || !$this->startedHere((string) $tranId, $combined)) {
            return self::UNKNOWN;
        }
        if (optional($combined->orders->first())->payment_status === 'paid') {
            return self::PAID;
        }
        if (empty($valId)) {
            return self::UNKNOWN;
        }

        $answer = $this->call(
            'https://' . $this->mode . '.sslcommerz.com/validator/api/validationserverAPI.php?' . http_build_query([
                'val_id' => $valId,
                'store_id' => $this->storeId,
                'store_passwd' => $this->storePass,
                'v' => 1,
                'format' => 'json',
            ]),
            null,
            false
        );
        Log::info('SSLCommerz validation:', ['tran_id' => $tranId, 'answer' => is_array($answer) ? array_diff_key($answer, ['store_passwd' => 1]) : $answer]);

        if (!is_array($answer)) {
            return self::UNKNOWN;
        }

        $status = strtoupper((string) ($answer['status'] ?? ''));
        if (in_array($status, ['VALID', 'VALIDATED'], true)) {
            // The transaction must be this one, in this currency, for the whole order.
            $sameTransaction = (string) ($answer['tran_id'] ?? '') === (string) $tranId;
            $fullAmount = abs((float) $combined->grand_total - (float) ($answer['amount'] ?? 0)) < 1;
            $currency = strtoupper((string) ($answer['currency_type'] ?? 'BDT')) === 'BDT';
            if (!$sameTransaction || !$fullAmount || !$currency) {
                Log::error('SSLCommerz mismatch:', ['tran_id' => $tranId, 'order_total' => $combined->grand_total, 'answer_amount' => $answer['amount'] ?? null]);
                $this->note((string) $tranId, PaymentGatewayAttempt::UNKNOWN, 'mismatch');

                return self::UNKNOWN;
            }
            $this->note((string) $tranId, PaymentGatewayAttempt::PAID, $status);
            $this->settle->markPaid($combined, $answer);

            return self::PAID;
        }

        if (in_array($status, ['FAILED', 'CANCELLED', 'EXPIRED', 'UNATTEMPTED'], true)) {
            $this->note((string) $tranId, PaymentGatewayAttempt::FAILED, $status);
            if ($failureCancels) {
                $this->settle->cancelUnpaid($combined, 'Online payment failed or was cancelled at the gateway.');
            }

            return self::FAILED;
        }

        return self::UNKNOWN;
    }

    /** The shopper came back from a failed or cancelled payment (no val_id is sent). */
    public function failedReturn(?string $tranId, string $reason): string
    {
        $combined = CombinedOrder::find(self::combinedOrderId($tranId));
        if ($combined !== null && $this->startedHere((string) $tranId, $combined) && optional($combined->orders->first())->payment_status !== 'paid') {
            $this->note((string) $tranId, PaymentGatewayAttempt::FAILED, $reason);
            $this->settle->cancelUnpaid($combined, 'Online payment ' . $reason . ' at the gateway.');
        }

        return self::FAILED;
    }

    private function note(string $tranId, string $status, ?string $gatewayStatus): void
    {
        if (!PaymentGatewayAttempt::ready()) {
            return;
        }
        PaymentGatewayAttempt::where('invoice_number', $tranId)
            ->where('status', '!=', PaymentGatewayAttempt::PAID)
            ->update(['status' => $status, 'gateway_status' => $gatewayStatus, 'checked_at' => now(), 'updated_at' => now()]);
    }

    /** @return array|false */
    protected function call(string $url, ?array $post, bool $isPost)
    {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        if ($isPost) {
            curl_setopt($handle, CURLOPT_POST, 1);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $post);
        }
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
        $verify = $this->mode === 'securepay';
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, $verify);

        $content = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_errno($handle);
        curl_close($handle);

        if ($code == 200 && !$error) {
            return json_decode($content, true) ?: false;
        }
        // Never log the request body: it carries the store password.
        Log::error('SSLCommerz request failed:', ['code' => $code, 'curl_error' => $error]);

        return false;
    }
}
