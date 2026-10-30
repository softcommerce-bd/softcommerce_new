<?php

namespace App\Services\Funnel\Checkout;

use App\Models\City;
use App\Services\Funnel\Offers\Money;

/**
 * The delivery charge for a funnel order, computed by the store's own shipping
 * rule (`getShippingCost`, driven by the admin's shipping settings) so a funnel
 * order is charged exactly what the same basket costs on the website. No rate
 * is invented here.
 *
 * `getShippingCost` works on cart rows. A funnel has no cart, so the order's
 * lines are presented to it as in-memory rows that are never saved.
 *
 * Carrier-wise shipping needs a carrier chosen by the customer, which a funnel
 * does not offer; with that setting the funnel refuses to take orders rather
 * than guess a price.
 */
class DeliveryCharge
{
    /** The shipping setting the store runs on, e.g. area_wise_shipping. */
    public function mode(): string
    {
        return (string) get_setting('shipping_type');
    }

    public function supported(): bool
    {
        return $this->mode() !== 'carrier_wise_shipping';
    }

    /** A district the store delivers to (the same list the website offers). */
    public function city(int $id): ?City
    {
        return City::where('id', $id)->where('status', 1)->first();
    }

    /**
     * @param  array<int, array{product_id: int, variant: string, pieces: int}>  $lines  physical lines of the order
     * @return array{cents: int, per_line: int[], free: bool}
     */
    public function quote(array $lines, int $cityId, bool $free): array
    {
        if ($lines === []) {
            return ['cents' => 0, 'per_line' => [], 'free' => $free];
        }
        if ($free) {
            return ['cents' => 0, 'per_line' => array_fill(0, count($lines), 0), 'free' => true];
        }

        // The rule reads product_id, quantity and the shipping_info it is given.
        $carts = array_values(array_map(fn ($line) => [
            'product_id' => $line['product_id'],
            'quantity' => $line['pieces'],
            'variation' => $line['variant'],
        ], $lines));
        $info = ['country_id' => 18, 'city_id' => $cityId]; // Bangladesh, as the website's guest checkout sends

        $total = 0.0;
        foreach (array_keys($carts) as $index) {
            $total += (float) getShippingCost($carts, $index, $info);
        }
        $cents = max(0, Money::cents($total));

        return [
            'cents' => $cents,
            // Exact split, so the lines add up to the charge shown to the customer.
            'per_line' => Money::allocate($cents, array_fill(0, count($lines), 1)),
            'free' => false,
        ];
    }
}
