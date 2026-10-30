<?php

namespace App\Services\Funnel\Checkout;

use App\Services\Funnel\ValidationResult;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Publish-time warnings about taking orders from a page. None of these blocks
 * publishing: a page can be built and published ahead of ordering being switched
 * on, and the live page tells customers plainly when it cannot take an order. They
 * are here so the merchant is not surprised later.
 */
class CheckoutAudit
{
    private FunnelOrderService $orders;
    private PaymentOptions $payments;
    private StockLedger $ledger;

    public function __construct(FunnelOrderService $orders, PaymentOptions $payments, StockLedger $ledger)
    {
        $this->orders = $orders;
        $this->payments = $payments;
        $this->ledger = $ledger;
    }

    public function audit(ValidationResult $result): void
    {
        $config = CheckoutConfig::fromDocument($result->document);

        if ($config->present()) {
            $this->checkout($result, $config);
        }

        $this->inventory($result);
    }

    private function checkout(ValidationResult $result, CheckoutConfig $config): void
    {
        if ($refusal = $this->orders->notReady()) {
            $messages = [
                'checkout_disabled' => 'Ordering from sales pages is not switched on for the store yet. The page will publish, and show that ordering is not open, until it is.',
                'checkout_not_ready' => 'The store database is missing the funnel order tables, so orders cannot be taken from this page yet.',
                'delivery_unsupported' => 'The store prices delivery by carrier, which a sales page cannot ask the customer to choose. Orders cannot be taken from this page.',
                'no_payment_method' => 'No payment method is switched on for the store, so orders cannot be taken from this page.',
            ];
            $result->warning('page', $refusal->errorCode(), $messages[$refusal->errorCode()] ?? $refusal->getMessage());
        }

        $offered = array_column($this->payments->available(), 'key');
        $unavailable = array_diff($config->paymentsAllowed(), $offered);
        if ($config->paymentsAllowed() !== [] && $unavailable !== []) {
            $result->warning('page', 'payment_unavailable', 'This page allows payment methods the store does not offer right now: ' . implode(', ', $unavailable) . '. Customers will not see them.');
        }
        if ($config->paymentsAllowed() !== [] && array_intersect($config->paymentsAllowed(), $offered) === [] && $offered !== []) {
            $result->warning('page', 'no_payment_method', 'None of the payment methods this page allows are available, so customers cannot pay.');
        }

        if ((int) get_setting('checkout_otp_activation') === 1) {
            $result->warning('page', 'otp_required', 'The store asks customers to verify their phone number with a code before ordering. The order form will ask for it.');
        }
    }

    /**
     * Products whose stock rows hold units that the purchase records do not
     * cover: checkout refuses (or limits) these until their inventory is checked.
     */
    private function inventory(ValidationResult $result): void
    {
        $seen = [];
        foreach ($result->bindings as $entry) {
            $ids = array_column($entry['binding']['items'], 'productId');
            if (!empty($entry['binding']['offer']['giftProductId'])) {
                $ids[] = (int) $entry['binding']['offer']['giftProductId'];
            }
            foreach ($entry['binding']['items'] as $item) {
                foreach ($this->variantsOf($item['productId'], $item['variant']) as $variant) {
                    $seen[$item['productId'] . '|' . $variant] = [$item['productId'], $variant, $entry['path']];
                }
            }
        }

        foreach ($seen as [$productId, $variant, $path]) {
            $levels = $this->ledger->sellable($productId, $variant);
            if ($levels['stock'] <= 0 || $levels['sellable'] >= $levels['stock']) {
                continue;
            }
            $name = DB::table('products')->where('id', $productId)->value('name') ?: "Product {$productId}";
            $label = $variant !== '' ? "{$name} ({$variant})" : $name;
            $result->warning(
                $path,
                'inventory_unverified',
                $levels['sellable'] === 0
                    ? "{$label} shows {$levels['stock']} in stock, but its purchase records hold none, so orders for it are refused until its inventory is checked."
                    : "{$label} shows {$levels['stock']} in stock, but its purchase records cover only {$levels['sellable']}, so customers can order at most {$levels['sellable']} until its inventory is checked."
            );
        }
    }

    /** @return string[] the one chosen variation, or every variation the product has */
    private function variantsOf(int $productId, ?string $variant): array
    {
        if ($variant !== null) {
            return [$variant];
        }

        $variants = DB::table('product_stocks')->where('product_id', $productId)->orderBy('id')->pluck('variant')->all();

        return $variants ?: [''];
    }
}
