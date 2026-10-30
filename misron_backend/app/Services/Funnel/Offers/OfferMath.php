<?php

namespace App\Services\Funnel\Offers;

/**
 * The arithmetic of one offer, with no database and no clock.
 *
 * An "offer unit" is one purchase of the offer: for a single product that is
 * one piece, for a bundle it is the whole set. A customer buys $n offer units.
 * Prices are the website's current unit prices (already net of the product's
 * own discount, before tax) in minor units.
 *
 * Offer types
 *   none               website price
 *   percent            value % off each offer unit
 *   fixed_amount       value taka off each offer unit
 *   fixed_price        each offer unit costs value taka (never more than the
 *                      website price: a higher number simply gives no discount)
 *   quantity_discount  the best matching tier by units bought, as percent or
 *                      taka off each offer unit
 *   bxgy               buy `buyQty`, get `getQty` of the same offer unit free
 *   free_gift          full price, plus a free gift line (added by the caller)
 */
class OfferMath
{
    /**
     * @param array $items  each: ['current' => int minor units per piece, 'quantity' => int pieces per offer unit]
     * @param array $offer  the stored offer rule
     * @return array{
     *   error: ?string, unit_base: int, unit_price: int, total_base: int, total: int, discount: int,
     *   free_units: int, tier: ?array, beneficial: bool, allocation: int[]
     * }
     */
    public static function price(array $items, array $offer, int $n): array
    {
        $unitBase = 0;
        $weights = [];
        foreach ($items as $i => $item) {
            $line = $item['current'] * $item['quantity'];
            $unitBase += $line;
            $weights[$i] = $line * $n;
        }
        $totalBase = $unitBase * $n;

        $type = $offer['type'] ?? 'none';
        $unitPrice = $unitBase;
        $total = null;
        $freeUnits = 0;
        $tier = null;
        $error = null;

        switch ($type) {
            case 'percent':
                $unitPrice = $unitBase - Money::percentOf($unitBase, (float) ($offer['value'] ?? 0));
                break;

            case 'fixed_amount':
                $unitPrice = $unitBase - Money::cents($offer['value'] ?? 0);
                break;

            case 'fixed_price':
                $target = Money::cents($offer['value'] ?? 0);
                if ($target <= 0) {
                    $error = 'invalid_offer_price';
                }
                $unitPrice = min($unitBase, $target);
                break;

            case 'quantity_discount':
                foreach ($offer['tiers'] ?? [] as $candidate) {
                    if ($n >= $candidate['minQty'] && ($tier === null || $candidate['minQty'] > $tier['minQty'])) {
                        $tier = $candidate;
                    }
                }
                if ($tier !== null) {
                    $unitPrice = $tier['type'] === 'percent'
                        ? $unitBase - Money::percentOf($unitBase, (float) $tier['value'])
                        : $unitBase - Money::cents($tier['value']);
                }
                break;

            case 'bxgy':
                $buy = max(1, (int) ($offer['buyQty'] ?? 1));
                $get = max(1, (int) ($offer['getQty'] ?? 1));
                $freeUnits = intdiv($n, $buy + $get) * $get;
                $total = $unitBase * ($n - $freeUnits);
                break;
        }

        if ($error === null && $unitBase > 0 && $unitPrice <= 0) {
            // A discount that makes the product free (or negative) is a
            // configuration mistake, not a promotion.
            $error = 'invalid_offer_price';
            $unitPrice = $unitBase;
        }

        if ($total === null) {
            $total = $unitPrice * $n;
        }

        return [
            'error' => $error,
            'unit_base' => $unitBase,
            'unit_price' => $unitPrice,
            'total_base' => $totalBase,
            'total' => $total,
            'discount' => $totalBase - $total,
            'free_units' => $freeUnits,
            'tier' => $tier,
            'beneficial' => $totalBase - $total > 0,
            'allocation' => Money::allocate($total, $weights),
        ];
    }
}
