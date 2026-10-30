<?php

namespace App\Services\Funnel\Checkout;

use Illuminate\Support\Facades\DB;

/**
 * Payment methods a funnel order can use right now. Only methods the store has
 * switched on AND that already work for a customer who is not logged in:
 *
 *   cash_on_delivery   the store's "cash payment" setting
 *   paystation         the hosted checkout the website already uses for guests
 *
 * The other gateways in the admin panel (bKash, Nagad, SSLCommerz …) need a
 * signed-in customer on the website today, so they are not offered here either.
 * The merchant can narrow the list further on the page; they can never widen it.
 */
class PaymentOptions
{
    public const COD = 'cash_on_delivery';
    public const PAYSTATION = 'paystation';

    /** @return array<int, array{key: string, label: string, online: bool}> */
    public function available(): array
    {
        $options = [];

        if ((int) get_setting('cash_payment') === 1) {
            $options[] = ['key' => self::COD, 'label' => 'Cash on delivery', 'online' => false];
        }
        if ($this->paystationReady()) {
            $options[] = ['key' => self::PAYSTATION, 'label' => 'Pay online', 'online' => true];
        }

        return $options;
    }

    /**
     * What a given page may offer: what is available, narrowed to the merchant's choice
     * (an empty choice means everything available).
     *
     * @return array<int, array{key: string, label: string, online: bool}>
     */
    public function forPage(CheckoutConfig $config): array
    {
        $allowed = $config->paymentsAllowed();

        return array_values(array_filter($this->available(), fn ($option) => $allowed === [] || in_array($option['key'], $allowed, true)));
    }

    public function isOnline(string $key): bool
    {
        return $key === self::PAYSTATION;
    }

    private function paystationReady(): bool
    {
        if (trim((string) env('PAYSTATION_MERCHANT_ID')) === '') {
            return false;
        }

        return DB::table('payment_methods')->where('name', 'paystation')->where('active', 1)->exists();
    }
}
