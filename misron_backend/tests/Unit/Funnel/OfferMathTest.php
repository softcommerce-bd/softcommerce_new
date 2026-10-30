<?php

namespace Tests\Unit\Funnel;

use App\Services\Funnel\Offers\Money;
use App\Services\Funnel\Offers\OfferMath;
use PHPUnit\Framework\TestCase;

/**
 * The arithmetic of offers, in minor units, with no database.
 */
class OfferMathTest extends TestCase
{
    private function price(array $currents, array $offer, int $n, array $quantities = [])
    {
        $items = [];
        foreach ($currents as $i => $current) {
            $items[] = ['current' => $current, 'quantity' => $quantities[$i] ?? 1];
        }

        return OfferMath::price($items, $offer, $n);
    }

    public function test_no_offer_charges_the_website_price()
    {
        $math = $this->price([90000], ['type' => 'none'], 3);
        $this->assertSame(270000, $math['total']);
        $this->assertSame(0, $math['discount']);
        $this->assertFalse($math['beneficial']);
    }

    public function test_percent_and_fixed_amount_apply_per_offer_unit()
    {
        $percent = $this->price([90000], ['type' => 'percent', 'value' => 10], 3);
        $this->assertSame(81000, $percent['unit_price']);
        $this->assertSame(243000, $percent['total']);
        $this->assertSame(27000, $percent['discount']);

        $amount = $this->price([90000], ['type' => 'fixed_amount', 'value' => 150], 2);
        $this->assertSame(75000, $amount['unit_price']);
        $this->assertSame(150000, $amount['total']);
    }

    public function test_rounding_is_half_up_to_the_poisha()
    {
        // 33.33% of 10.01 taka = 3.336333 -> 3.34 taka off, never a fractional poisha.
        $math = $this->price([1001], ['type' => 'percent', 'value' => 33.33], 1);
        $this->assertSame(334, $math['discount']);
        $this->assertSame(667, $math['total']);
        $this->assertSame(1, Money::cents(0.005));
        $this->assertSame(1999, Money::cents(19.99));
        $this->assertSame(1999, Money::cents('19.990'));
    }

    public function test_fixed_price_never_charges_more_than_the_website()
    {
        $lower = $this->price([90000], ['type' => 'fixed_price', 'value' => 750], 2);
        $this->assertSame(150000, $lower['total']);
        $this->assertTrue($lower['beneficial']);

        // The website price dropped below the advertised offer price: the customer pays the lower one.
        $higher = $this->price([60000], ['type' => 'fixed_price', 'value' => 750], 2);
        $this->assertSame(120000, $higher['total']);
        $this->assertFalse($higher['beneficial']);
    }

    public function test_a_discount_that_makes_the_product_free_is_an_error()
    {
        $this->assertSame('invalid_offer_price', $this->price([90000], ['type' => 'fixed_amount', 'value' => 900], 1)['error']);
        $this->assertSame('invalid_offer_price', $this->price([90000], ['type' => 'fixed_amount', 'value' => 5000], 1)['error']);
        $this->assertSame('invalid_offer_price', $this->price([90000], ['type' => 'percent', 'value' => 100], 1)['error']);
        $this->assertSame('invalid_offer_price', $this->price([90000], ['type' => 'fixed_price', 'value' => 0], 1)['error']);

        // The failed calculation still reports the safe website price, never a negative total.
        $bad = $this->price([90000], ['type' => 'fixed_amount', 'value' => 5000], 2);
        $this->assertGreaterThan(0, $bad['total']);
    }

    public function test_quantity_tiers_pick_the_best_match()
    {
        $offer = ['type' => 'quantity_discount', 'tiers' => [
            ['minQty' => 3, 'type' => 'percent', 'value' => 10],
            ['minQty' => 5, 'type' => 'percent', 'value' => 15],
            ['minQty' => 10, 'type' => 'fixed_amount', 'value' => 200],
        ]];

        $this->assertSame(0, $this->price([100000], $offer, 2)['discount']);
        $this->assertSame(30000, $this->price([100000], $offer, 3)['discount']);
        $this->assertSame(40000, $this->price([100000], $offer, 4)["discount"]);
        $this->assertSame(75000, $this->price([100000], $offer, 5)['discount']);
        $this->assertSame(10 * 20000, $this->price([100000], $offer, 10)['discount']);
        $this->assertSame(5, $this->price([100000], $offer, 5)['tier']['minQty']);
    }

    public function test_buy_x_get_y_free()
    {
        $offer = ['type' => 'bxgy', 'buyQty' => 2, 'getQty' => 1];
        $this->assertSame(0, $this->price([50000], $offer, 2)['free_units']);
        $this->assertSame(1, $this->price([50000], $offer, 3)['free_units']);
        $seven = $this->price([50000], $offer, 7);
        $this->assertSame(2, $seven['free_units']);
        $this->assertSame(5 * 50000, $seven['total']);
        $this->assertSame(2 * 50000, $seven['discount']);
    }

    public function test_bundle_fixed_price_is_split_exactly_across_items()
    {
        // Three products worth 333.33 + 333.33 + 333.34 sold as one bundle for 900.
        $math = $this->price([33333, 33333, 33334], ['type' => 'fixed_price', 'value' => 9], 1);
        $this->assertSame(900, $math['total']);
        $this->assertSame(900, array_sum($math['allocation']));

        $big = $this->price([33333, 33333, 33334], ['type' => 'percent', 'value' => 7], 3);
        $this->assertSame($big['total'], array_sum($big['allocation']));
        $this->assertSame(array_sum([33333, 33333, 33334]) * 3 - $big['discount'], $big['total']);
    }

    public function test_bundle_pieces_per_offer_unit_multiply()
    {
        // 2 pieces of A (100) and 1 of B (50) per bundle, 3 bundles, 20% off.
        $math = $this->price([10000, 5000], ['type' => 'percent', 'value' => 20], 3, [2, 1]);
        $this->assertSame(25000, $math['unit_base']);
        $this->assertSame(20000, $math['unit_price']);
        $this->assertSame(60000, $math['total']);
        $this->assertSame(60000, array_sum($math['allocation']));
        // Allocation is proportional to value: A gets 4/5 of the total.
        $this->assertSame(48000, $math['allocation'][0]);
    }

    public function test_allocate_always_adds_up()
    {
        foreach ([[1, [1, 1, 1]], [100, [3, 3, 3]], [7, [5, 0, 5]], [0, [1, 2]], [99999, [1, 7, 13, 29]]] as [$total, $weights]) {
            $parts = Money::allocate($total, $weights);
            $this->assertSame($total, array_sum($parts), json_encode([$total, $weights]));
            $this->assertCount(count($weights), $parts);
            $this->assertSame(min($parts), min($parts));
        }
    }
}
