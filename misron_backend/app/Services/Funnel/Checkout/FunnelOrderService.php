<?php

namespace App\Services\Funnel\Checkout;

use App\Models\BlockedIp;
use App\Models\CheckoutOtp;
use App\Models\CombinedOrder;
use App\Models\Funnel;
use App\Models\FunnelCheckoutAttempt;
use App\Models\FunnelOrderAttribution;
use App\Services\Funnel\Analytics\TestOrderToken;
use App\Services\Funnel\Analytics\TrafficSource;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Services\Funnel\Offers\Money;
use App\Services\Funnel\Offers\OfferEngine;
use App\Services\Funnel\Offers\PublishedOffers;
use App\Services\Inventory\Exceptions\InventoryException;
use App\Services\Inventory\StockLedger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Turns a funnel checkout into an ordinary order of the store.
 *
 * What it reuses rather than reinvents:
 *  - prices: the offer engine, on the published version only (the browser never
 *    sends an amount);
 *  - stock: StockLedger, the same service the website checkout uses;
 *  - delivery charge: the store's own shipping rule;
 *  - records: CombinedOrder, Order, OrderDetail, the customer's User row, the
 *    same columns and the same statuses, so the order shows up in the existing
 *    admin list and moves through the normal fulfilment steps.
 *
 * What it adds: an idempotency record per submission (so a double tap or a retry
 * cannot place two orders) and the funnel attribution on each order.
 */
class FunnelOrderService
{
    /** Seconds after which an attempt that never finished may be taken over by a retry. */
    private const STALE_AFTER = 120;

    private OfferEngine $engine;
    private PublishedOffers $published;
    private DeliveryCharge $delivery;
    private PaymentOptions $payments;
    private CustomerResolver $customers;
    private StockLedger $ledger;

    private static ?bool $attributionReady = null;

    public function __construct(
        OfferEngine $engine,
        PublishedOffers $published,
        DeliveryCharge $delivery,
        PaymentOptions $payments,
        CustomerResolver $customers,
        StockLedger $ledger
    ) {
        $this->engine = $engine;
        $this->published = $published;
        $this->delivery = $delivery;
        $this->payments = $payments;
        $this->customers = $customers;
        $this->ledger = $ledger;
    }

    // ── Readiness ───────────────────────────────────────────────────────────

    /**
     * Why orders cannot be taken right now, or null when they can. Checked on every
     * submit and reported on the page, so a half-installed feature refuses cleanly.
     */
    public function notReady(): ?CheckoutException
    {
        if (!config('funnel.checkout.enabled')) {
            return new CheckoutException('checkout_disabled', 'Ordering through this page is not open yet.', 503);
        }
        if (!$this->attributionReady()) {
            return new CheckoutException('checkout_not_ready', 'Ordering through this page is not set up yet.', 503);
        }
        if (!$this->delivery->supported()) {
            return new CheckoutException('delivery_unsupported', 'Delivery pricing for this store cannot be used from a sales page yet.', 503);
        }
        if ($this->payments->available() === []) {
            return new CheckoutException('no_payment_method', 'No payment method is available right now.', 503);
        }

        return null;
    }

    public static function forgetReadiness(): void
    {
        self::$attributionReady = null;
        self::$attributionTable = null;
    }

    private static ?bool $attributionTable = null;

    /** The campaign attribution table is optional (sales_funnel_v5); orders are taken without it. */
    public static function attributionTableReady(): bool
    {
        return self::$attributionTable ??= Schema::hasTable('funnel_order_attributions');
    }

    private function attributionReady(): bool
    {
        if (self::$attributionReady === null) {
            self::$attributionReady = Schema::hasColumn('orders', 'funnel_id')
                && Schema::hasColumn('orders', 'funnel_version_id')
                && Schema::hasTable('funnel_checkout_attempts');
        }

        return self::$attributionReady;
    }

    // ── Placing an order ────────────────────────────────────────────────────

    /**
     * @return array{replayed: bool, order: array}
     * @throws CheckoutException
     */
    public function place(Funnel $funnel, CheckoutConfig $config, CheckoutInput $input, string $ip): array
    {
        if ($refusal = $this->notReady()) {
            throw $refusal;
        }
        if (!$config->present()) {
            throw new CheckoutException('no_checkout', 'This page has no order form.', 404);
        }

        $this->guardCustomer($input, $ip);

        [$attempt, $replay] = $this->openAttempt($funnel, $input);
        if ($replay) {
            return ['replayed' => true, 'order' => $this->summary(CombinedOrder::findOrFail($attempt->combined_order_id), $funnel)];
        }

        $take = null;
        try {
            [$quote, $rows, $delivery, $city] = $this->price($funnel, $input);

            DB::beginTransaction();
            try {
                $combined = $this->createOrders($funnel, $config, $input, $quote, $rows, $delivery, $city, $ip, $take, $attempt);
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                if ($take) {
                    // Batches that do not roll back (MyISAM) are given back by hand.
                    $this->ledger->release($take);
                }
                throw $e;
            }
        } catch (CheckoutException $e) {
            $this->settle($attempt, FunnelCheckoutAttempt::REJECTED, $e->errorCode());
            throw $e;
        } catch (InventoryException $e) {
            $refusal = $this->stockRefusal($e);
            $final = !in_array($e->errorCode(), ['inventory_busy'], true);
            $this->settle($attempt, $final ? FunnelCheckoutAttempt::REJECTED : FunnelCheckoutAttempt::ERRORED, $refusal->errorCode());
            throw $refusal;
        } catch (Throwable $e) {
            Log::error('Funnel checkout failed: ' . $e->getMessage(), ['funnel_id' => $funnel->id, 'exception' => $e]);
            $this->settle($attempt, FunnelCheckoutAttempt::ERRORED, 'checkout_failed');

            if ($e instanceof QueryException && in_array($e->errorInfo[1] ?? null, [1213, 1205], true)) {
                throw new CheckoutException('inventory_busy', 'Too many orders at once. Please try again.', 503);
            }
            throw new CheckoutException('checkout_failed', 'We could not place your order. Nothing was charged. Please try again.', 500);
        }

        return ['replayed' => false, 'order' => $this->summary($combined, $funnel)];
    }

    /**
     * Prices the selection and the delivery.
     *
     * @return array{0: array, 1: array, 2: array, 3: \App\Models\City}
     */
    private function price(Funnel $funnel, CheckoutInput $input): array
    {
        $quote = $this->engine->quote($this->published->bindings($funnel), $input->selections);

        if (!$quote['valid']) {
            throw new CheckoutException('offer_unavailable', 'One of the offers can no longer be ordered.', 409, [], [
                'quote_errors' => $quote['errors'],
                'product' => $quote['errors'][0]['product'] ?? null,
            ]);
        }

        $city = $this->delivery->city($input->cityId);
        if ($city === null) {
            throw CheckoutException::invalid(['district' => ['code' => 'invalid_district', 'message' => 'We do not deliver to that district.']]);
        }

        $rows = $this->detailRows($quote['_cents']['lines']);
        $delivery = $this->delivery->quote(
            array_map(fn ($row) => ['product_id' => $row['product_id'], 'variant' => $row['variant'], 'pieces' => $row['pieces']], $rows),
            $input->cityId,
            $quote['_cents']['totals']['free_delivery']
        );

        // The customer confirmed a total on screen (products, tax and delivery); if it moved
        // since, they confirm the new one.
        $fingerprint = $this->fingerprintWithDelivery((string) $quote['fingerprint'], $delivery['cents'], $input->cityId);
        if ($input->quoteFingerprint !== null && !hash_equals($fingerprint, $input->quoteFingerprint)) {
            // The new total as the page will show it: products, tax and this district's delivery.
            $public = $this->withDelivery($quote, $input->cityId);
            unset($public['_cents']);
            throw new CheckoutException('price_changed', 'The price changed while you were ordering. Please review the new total.', 409, [], [
                'quote' => $public,
                'delivery' => $this->money($delivery['cents']),
            ]);
        }

        return [$quote, $rows, $delivery, $city];
    }

    /**
     * One row per physical product line: every item of every chosen offer, then free gifts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function detailRows(array $lines): array
    {
        $rows = [];
        foreach ($lines as $line) {
            foreach ($line['items'] as $item) {
                $rows[] = [
                    'offer_key' => $line['key'], 'offer_type' => $line['type'], 'product_id' => (int) $item['product_id'],
                    'variant' => (string) ($item['variant'] ?? ''), 'pieces' => (int) $item['pieces'],
                    'total' => (int) $item['total'], 'tax' => (int) $item['tax'], 'gift' => false,
                ];
            }
            if (!empty($line['gift'])) {
                $rows[] = [
                    'offer_key' => $line['key'], 'offer_type' => $line['type'], 'product_id' => (int) $line['gift']['product_id'],
                    'variant' => (string) ($line['gift']['variant'] ?? ''), 'pieces' => (int) $line['gift']['pieces'],
                    'total' => 0, 'tax' => 0, 'gift' => true,
                ];
            }
        }

        return $rows;
    }

    // ── Pricing with delivery (also used by the page's live quote) ───────────

    /**
     * Adds the delivery charge for a district to a valid quote: the delivery line, the
     * payable total, and a fingerprint that covers the delivery as well, so a changed
     * delivery rate is caught at checkout like a changed product price.
     */
    public function withDelivery(array $quote, int $cityId): array
    {
        $city = $this->delivery->city($cityId);
        if ($city === null || !$this->delivery->supported()) {
            $quote['totals']['delivery'] = ['status' => 'unavailable', 'free' => false, 'amount' => null];

            return $quote;
        }

        $rows = $this->detailRows($quote['_cents']['lines']);
        $delivery = $this->delivery->quote(
            array_map(fn ($row) => ['product_id' => $row['product_id'], 'variant' => $row['variant'], 'pieces' => $row['pieces']], $rows),
            $cityId,
            $quote['_cents']['totals']['free_delivery']
        );
        $payable = $quote['_cents']['totals']['total_before_delivery'] + $delivery['cents'];

        $quote['totals']['delivery'] = ['status' => 'calculated', 'free' => $delivery['free'], 'amount' => $this->money($delivery['cents']), 'district' => $city->name];
        $quote['totals']['payable'] = $this->money($payable);
        $quote['fingerprint'] = $this->fingerprintWithDelivery((string) $quote['fingerprint'], $delivery['cents'], $cityId);

        return $quote;
    }

    private function fingerprintWithDelivery(string $fingerprint, int $deliveryCents, int $cityId): string
    {
        return sha1($fingerprint . '|' . $deliveryCents . '|' . $cityId);
    }

    // ── Writing the order ───────────────────────────────────────────────────

    private function createOrders(
        Funnel $funnel, CheckoutConfig $config, CheckoutInput $input, array $quote, array $rows, array $delivery,
        $city, string $ip, ?\App\Services\Inventory\StockTake &$take, FunnelCheckoutAttempt $attempt
    ): CombinedOrder {
        $version = $funnel->publishedVersion;
        $products = Product::whereIn('id', array_unique(array_column($rows, 'product_id')))->get()->keyBy('id');

        // Stock for the whole basket first; a refused line stops everything with nothing half-taken.
        $take = $this->ledger->take(array_map(fn ($row, $i) => [
            'key' => $i, 'product_id' => $row['product_id'], 'variant' => $row['variant'],
            'quantity' => $row['pieces'], 'digital' => (int) ($products[$row['product_id']]->digital ?? 0) === 1,
        ], $rows, array_keys($rows)));

        $user = $this->customers->resolve($input, $ip);
        $guestId = random_int(100000000, 999999999); // orders.guest_id is an integer

        $address = [
            'name' => $input->name, 'email' => $input->email ?? '', 'phone' => $input->phone, 'address' => $input->address,
            'city' => $city->name, 'country' => 'Bangladesh', 'postal_code' => '', 'order_note' => $input->notes ?? '',
            'delivery_type' => 'home_delivery',
        ];
        if ($input->area !== null) {
            $address['area'] = $input->area;
        }

        $combined = new CombinedOrder();
        $combined->user_id = $user->id;
        $combined->temp_user_id = (string) $guestId;
        $combined->shipping_address = json_encode($address, JSON_UNESCAPED_UNICODE);
        $combined->save();

        $isTest = TestOrderToken::isTestOrder($input->testToken, (int) $funnel->id, $input->phone);

        // Plain text on purpose: the admin screen and the courier note show it as written.
        $info = ['Sales funnel: ' . $funnel->title . ' (v' . $version->version_number . ')'];
        if ($isTest) {
            $info[] = 'TEST ORDER placed by staff: not counted in funnel analytics or ad tracking. Cancel it when done.';
        }
        foreach ($input->custom as $label => $answer) {
            $info[] = $label . ': ' . $answer;
        }
        if ($config->requireConsent() && $input->consent) {
            $info[] = 'Consent given: ' . ($config->consentText() ?: 'yes');
        }

        $bySeller = [];
        foreach ($rows as $i => $row) {
            $bySeller[$products[$row['product_id']]->user_id][] = $i;
        }

        $grand = 0;
        foreach ($bySeller as $sellerId => $indexes) {
            $order = new Order();
            $order->combined_order_id = $combined->id;
            $order->user_id = $user->id;
            $order->guest_id = $guestId;
            $order->seller_id = $sellerId;
            $order->shipping_address = $combined->shipping_address;
            $order->additional_info = implode("\n", $info);
            $order->order_note = $input->notes;
            $order->payment_type = $input->paymentType;
            $order->shipping_type = 'home_delivery';
            $order->pickup_point_id = 0;
            $order->carrier_id = 0;
            $order->order_from = 'app';
            $order->funnel_id = $funnel->id;
            $order->funnel_version_id = $version->id;
            $order->delivery_viewed = '0';
            $order->payment_status_viewed = '0';
            $order->code = Order::generateUniqueCode();
            $order->date = strtotime('now');
            $order->payment_status = 'unpaid';
            $order->customer_ip = $ip;
            $order->save();

            $orderCents = 0;
            foreach ($indexes as $i) {
                $row = $rows[$i];
                $product = $products[$row['product_id']];
                $line = $take->line($i);
                $shipping = $delivery['per_line'][$i];

                $detail = new OrderDetail();
                $detail->order_id = $order->id;
                $detail->seller_id = $product->user_id;
                $detail->product_id = $product->id;
                $detail->variation = $row['variant'];
                $detail->selling_price = Money::amount((int) round($row['total'] / max(1, $row['pieces'])));
                $detail->price = Money::amount($row['total']);
                $detail->buying_price = $line['avg_cost'];
                $detail->profit = Money::amount($row['total']) - $line['cost'];
                if ($line['batches']) {
                    $detail->purchase_id = $line['batches'][0]['purchase_id'];
                    $detail->supplier_id = $line['batches'][0]['supplier_id'];
                }
                $detail->fifo_batch_info = $take->batchInfo($i);
                $detail->tax = Money::amount($row['tax']);
                $detail->shipping_type = 'home_delivery';
                $detail->shipping_cost = Money::amount($shipping);
                if (function_exists('addon_is_activated') && addon_is_activated('club_point')) {
                    $detail->earn_point = $product->earn_point;
                }
                $detail->quantity = $row['pieces'];
                $detail->save();

                Product::where('id', $product->id)->increment('num_of_sale', $row['pieces']);
                $orderCents += $row['total'] + $row['tax'] + $shipping;
            }

            $order->grand_total = Money::amount($orderCents);
            $order->due_amount = $order->grand_total;
            $order->save();
            $grand += $orderCents;
        }

        // The pieces must add up to the total the customer was shown.
        $expected = $quote['_cents']['totals']['total_before_delivery'] + $delivery['cents'];
        if ($grand !== $expected) {
            throw new \RuntimeException("Order total {$grand} does not match the quote {$expected}.");
        }

        $combined->grand_total = Money::amount($grand);
        $combined->save();

        if (self::attributionTableReady()) {
            FunnelOrderAttribution::create(array_merge($input->attribution ?: CheckoutInput::attribution(null), [
                'combined_order_id' => $combined->id,
                'funnel_id' => $funnel->id,
                'device' => TrafficSource::device($input->userAgent),
                'is_test' => $isTest,
            ]));
        }

        // Same tracking the website checkout does, so the admin's IP review covers funnel orders.
        BlockedIp::updateOrCreate(['ip_address' => $ip], [
            'user_id' => $user->id, 'customer_name' => $input->name, 'customer_phone' => $input->phone, 'customer_email' => $input->email,
        ]);

        $public = $quote;
        unset($public['_cents']);
        $attempt->forceFill([
            'status' => FunnelCheckoutAttempt::PLACED,
            'error_code' => null,
            'quote_fingerprint' => $quote['fingerprint'],
            'combined_order_id' => $combined->id,
            'payment_type' => $input->paymentType,
            // What was agreed, as it was: later catalogue changes cannot rewrite it.
            'quote_snapshot' => json_encode([
                'quote' => $public, 'delivery' => ['cents' => $delivery['cents'], 'free' => $delivery['free']],
                'district' => ['id' => $city->id, 'name' => $city->name], 'funnel_version' => $version->version_number,
            ], JSON_UNESCAPED_UNICODE),
        ])->save();

        return $combined;
    }

    private function stockRefusal(InventoryException $e): CheckoutException
    {
        $product = isset($e->context()['product_id']) ? Product::find($e->context()['product_id']) : null;
        $name = $product ? $product->name : 'An item';
        $context = ['product' => $name, 'available' => $e->context()['available'] ?? null];

        switch ($e->errorCode()) {
            case 'out_of_stock':
            case 'variant_unavailable':
                return new CheckoutException('out_of_stock', "{$name} is no longer available in that quantity.", 409, [], $context);
            case 'inventory_unverified':
                return new CheckoutException('item_unavailable', "{$name} is temporarily unavailable.", 409, [], $context);
            case 'inventory_busy':
                return new CheckoutException('inventory_busy', 'Too many orders at once. Please try again.', 503);
            default:
                return new CheckoutException('checkout_failed', 'We could not place your order.', 422);
        }
    }

    // ── Guards ──────────────────────────────────────────────────────────────

    private function guardCustomer(CheckoutInput $input, string $ip): void
    {
        if (BlockedIp::where('ip_address', $ip)->where('status', 'blocked')->exists()) {
            throw new CheckoutException('blocked', 'Orders from this connection are not being accepted. Please contact us.', 403);
        }

        if ((int) get_setting('checkout_otp_activation') === 1) {
            $verified = CheckoutOtp::where('phone', $input->phone)->where('is_verified', true)->where('expires_at', '>', now())->latest()->first();
            if (!$verified) {
                throw new CheckoutException('otp_required', 'Please verify your phone number to place the order.', 403);
            }
        }
    }

    // ── Idempotency ─────────────────────────────────────────────────────────

    /**
     * Registers this submission, or finds the one that is already registered.
     *
     * @return array{0: FunnelCheckoutAttempt, 1: bool} the attempt, and whether it is a replay
     */
    private function openAttempt(Funnel $funnel, CheckoutInput $input): array
    {
        $keyHash = hash('sha256', $funnel->id . '|' . $input->idempotencyKey);
        $requestHash = $input->requestHash();

        $limiter = 'funnel-checkout:phone:' . $input->phone;

        try {
            if (RateLimiter::tooManyAttempts($limiter, 6)) {
                throw new CheckoutException('too_many_attempts', 'Too many orders from this number. Please wait a few minutes.', 429);
            }
            $attempt = FunnelCheckoutAttempt::create([
                'funnel_id' => $funnel->id, 'funnel_version_id' => $funnel->publishedVersion->id,
                'key_hash' => $keyHash, 'request_hash' => $requestHash, 'status' => FunnelCheckoutAttempt::STARTED,
            ]);
            RateLimiter::hit($limiter, 600);

            return [$attempt, false];
        } catch (QueryException $e) {
            if (!$this->isDuplicateKey($e)) {
                throw $e;
            }
        }

        $attempt = FunnelCheckoutAttempt::where('key_hash', $keyHash)->firstOrFail();

        if (!hash_equals($attempt->request_hash, $requestHash)) {
            throw new CheckoutException('idempotency_key_reused', 'This submission id was already used for a different order. Reload the page and try again.', 422);
        }

        switch ($attempt->status) {
            case FunnelCheckoutAttempt::PLACED:
                return [$attempt, true];

            case FunnelCheckoutAttempt::REJECTED:
                throw new CheckoutException((string) $attempt->error_code ?: 'rejected', 'This order was refused. Review your order and submit it again.', 409, [], ['final' => true]);

            case FunnelCheckoutAttempt::ERRORED:
                $reopened = FunnelCheckoutAttempt::where('id', $attempt->id)->where('status', FunnelCheckoutAttempt::ERRORED)
                    ->update(['status' => FunnelCheckoutAttempt::STARTED, 'updated_at' => now()]);
                if ($reopened) {
                    return [$attempt->fresh(), false];
                }
                break;

            default: // started
                $cutoff = now()->subSeconds(self::STALE_AFTER);
                $took = FunnelCheckoutAttempt::where('id', $attempt->id)->where('status', FunnelCheckoutAttempt::STARTED)
                    ->where('updated_at', '<', $cutoff)->update(['updated_at' => now()]);
                if ($took) {
                    return [$attempt->fresh(), false];
                }
        }

        throw new CheckoutException('in_progress', 'Your order is being placed. One moment.', 409, [], ['retry_after' => 2]);
    }

    private function settle(FunnelCheckoutAttempt $attempt, string $status, string $code): void
    {
        FunnelCheckoutAttempt::where('id', $attempt->id)->where('status', FunnelCheckoutAttempt::STARTED)
            ->update(['status' => $status, 'error_code' => $code, 'updated_at' => now()]);
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062 || ($e->errorInfo[0] ?? null) === '23000';
    }

    // ── What the customer sees afterwards ───────────────────────────────────

    /** Summary of a placed order, for the confirmation area. No address or phone. */
    public function summary(CombinedOrder $combined, Funnel $funnel): array
    {
        $orders = Order::where('combined_order_id', $combined->id)->get();
        $details = [];
        $products = $tax = $shipping = $total = 0;

        foreach ($orders as $order) {
            foreach ($order->orderDetails as $detail) {
                $details[] = [
                    'name' => optional($detail->product)->name,
                    'variant' => $detail->variation !== '' ? $detail->variation : null,
                    'quantity' => (int) $detail->quantity,
                    'total' => $this->money(Money::cents($detail->price)),
                ];
                $products += Money::cents($detail->price);
                $tax += Money::cents($detail->tax);
                $shipping += Money::cents($detail->shipping_cost);
            }
            $total += Money::cents($order->grand_total);
        }

        $first = $orders->first();
        $online = $first && $this->payments->isOnline((string) $first->payment_type);
        $paid = $first && $first->payment_status === 'paid';
        $cancelled = $first && $first->delivery_status === 'cancelled';

        return [
            'code' => $orders->pluck('code')->implode(', '),
            'receipt' => Receipt::make($combined->id, $funnel->id),
            'status' => $first ? $first->delivery_status : null,
            'payment' => [
                'method' => $first ? $first->payment_type : null,
                'status' => $paid ? 'paid' : ($cancelled ? 'cancelled' : 'unpaid'),
                // An online order is not finished until it is paid; the page then asks for the payment link.
                'action' => $online && !$paid && !$cancelled ? 'pay' : 'none',
            ],
            'items' => $details,
            'totals' => [
                'products' => $this->money($products),
                'tax' => $this->money($tax),
                'delivery' => $this->money($shipping),
                'total' => $this->money($total),
            ],
            'tracking' => $this->tracking($combined, $orders, $online, $paid, $cancelled, $total),
        ];
    }

    /**
     * What the page may report to Meta and Google as a Purchase, decided here from
     * the order itself, never from the browser:
     *  - a cash-on-delivery order counts once it is placed, an online order only
     *    once the gateway confirmed the payment; a cancelled or test order never;
     *  - the event id is derived from the order, so the browser Pixel, the server
     *    copy and a reloaded confirmation page all name the same event and Meta
     *    counts it once;
     *  - the buyer's phone and email are included only hashed, and only when the
     *    store has switched sharing on (funnel.analytics.share_customer_data).
     */
    private function tracking(CombinedOrder $combined, $orders, bool $online, bool $paid, bool $cancelled, int $totalCents): array
    {
        $test = self::attributionTableReady()
            && FunnelOrderAttribution::where('combined_order_id', $combined->id)->where('is_test', true)->exists();
        $eligible = !$test && !$cancelled && (!$online || $paid);

        $contents = [];
        foreach ($orders as $order) {
            foreach ($order->orderDetails as $detail) {
                // The catalogue feed publishes product_stocks row ids, the storefront's Pixel too.
                $stockId = \App\Models\ProductStock::where('product_id', $detail->product_id)
                    ->where('variant', (string) $detail->variation)->value('id');
                $contents[] = [
                    'id' => (string) ($stockId ?: $detail->product_id),
                    'quantity' => (int) $detail->quantity,
                    'item_price' => $detail->quantity > 0 ? round(Money::amount(Money::cents($detail->price)) / $detail->quantity, 2) : 0,
                    'name' => optional($detail->product)->name,
                ];
            }
        }

        $userData = null;
        if ($eligible && config('funnel.analytics.share_customer_data')) {
            $address = json_decode((string) $combined->shipping_address, true) ?: [];
            $phone = preg_replace('/\D+/', '', (string) ($address['phone'] ?? ''));
            $email = strtolower(trim((string) ($address['email'] ?? '')));
            $userData = array_filter([
                // Meta expects the number with its country code, digits only, then hashed.
                'ph' => $phone !== '' ? hash('sha256', (strlen($phone) === 11 ? '88' : '') . $phone) : null,
                'em' => $email !== '' ? hash('sha256', $email) : null,
            ]);
        }

        return [
            'eligible' => $eligible,
            'test' => $test,
            'event_id' => 'purchase_' . substr(hash_hmac('sha256', 'funnel-purchase|' . $combined->id, (string) config('app.key')), 0, 32),
            'transaction_id' => (string) $orders->pluck('code')->implode('-'),
            'value' => Money::amount($totalCents),
            'currency' => 'BDT',
            'contents' => $contents,
            'user_data' => $userData ?: null,
        ];
    }

    /** @return array{amount: float, text: string} */
    public function money(int $cents): array
    {
        return ['amount' => Money::amount($cents), 'text' => Money::format($cents)];
    }

    /** The encrypted id the website's own order pages and the payment gateway use. */
    public function publicOrderId(CombinedOrder $combined): string
    {
        return Crypt::encrypt($combined->id);
    }
}
