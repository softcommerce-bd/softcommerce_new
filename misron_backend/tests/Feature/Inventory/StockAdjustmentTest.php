<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Tests\Inventory\InventoryTestCase;

/**
 * Writing off stock (damage, loss, correction) takes the units off the shelf and out of the
 * purchase batches of ONE variation, refuses to take more than there is, and deleting the
 * adjustment puts them back where they came from.
 */
class StockAdjustmentTest extends InventoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->makeAdmin());
    }

    private function adjust(Product $product, string $variant, int $quantity, ?int $stockId = null)
    {
        return $this->postJson(route('stock-adjustments.store'), [
            'transaction_date' => now()->toDateString(),
            'adjustment_type' => 'normal',
            'products' => [[
                'product_id' => $product->id,
                'product_stock_id' => $stockId,
                'variant' => $variant,
                'quantity' => $quantity,
                'unit_price' => 400,
            ]],
        ]);
    }

    private function stockId(Product $product, string $variant): int
    {
        return (int) DB::table('product_stocks')->where('product_id', $product->id)->where('variant', $variant)->value('id');
    }

    public function test_a_write_off_takes_units_from_the_shelf_and_the_oldest_batch_of_that_variation(): void
    {
        $product = $this->makeStockedProduct(['M' => 10, 'L' => 10], 1000, 400);

        $this->adjust($product, 'M', 4, $this->stockId($product, 'M'))->assertJson(['success' => true]);

        $this->assertSame(6, $this->stockQty($product, 'M'));
        $this->assertSame(6, $this->batchRemaining($product, 'M'));
        $this->assertSame(10, $this->stockQty($product, 'L'), 'the other variation is untouched');
        $this->assertSame(10, $this->batchRemaining($product, 'L'), 'and so are its batches (they used to be drained too)');
    }

    public function test_the_variation_can_be_named_instead_of_the_row_id(): void
    {
        $product = $this->makeStockedProduct(['M' => 10, 'L' => 10], 1000, 400);

        $this->adjust($product, 'L', 3)->assertJson(['success' => true]);

        $this->assertSame(7, $this->stockQty($product, 'L'));
        $this->assertSame(7, $this->batchRemaining($product, 'L'));
        $this->assertSame(10, $this->stockQty($product, 'M'));
    }

    public function test_asking_for_more_than_is_on_the_shelf_is_refused_and_changes_nothing(): void
    {
        $product = $this->makeStockedProduct(['M' => 3], 1000, 400);

        $response = $this->adjust($product, 'M', 5, $this->stockId($product, 'M'));

        $response->assertStatus(500)->assertJson(['success' => false]);
        $this->assertStringContainsString('Not enough stock', $response->json('message'));
        $this->assertSame(3, $this->stockQty($product, 'M'), 'the shelf never goes below zero');
        $this->assertSame(3, $this->batchRemaining($product, 'M'));
        $this->assertSame(0, StockAdjustment::count());
    }

    public function test_shelf_stock_with_no_cost_records_is_refused_not_written_off_at_nothing(): void
    {
        $product = $this->makeProduct(['name' => 'Uncosted'], [['variant' => '', 'qty' => 5, 'price' => 1000]]);

        $response = $this->adjust($product, '', 2);

        $response->assertStatus(500);
        $this->assertSame(5, (int) DB::table('product_stocks')->where('product_id', $product->id)->value('qty'), 'the shelf is unchanged (the refusal rolled it back)');
    }

    public function test_a_product_with_several_variations_must_say_which_one(): void
    {
        $product = $this->makeStockedProduct(['M' => 10, 'L' => 10], 1000, 400);

        $this->postJson(route('stock-adjustments.store'), [
            'transaction_date' => now()->toDateString(),
            'adjustment_type' => 'normal',
            'products' => [['product_id' => $product->id, 'product_stock_id' => null, 'quantity' => 2, 'unit_price' => 400]],
        ])->assertStatus(500);

        $this->assertSame(10, $this->stockQty($product, 'M'));
        $this->assertSame(10, $this->stockQty($product, 'L'));
    }

    public function test_quantities_must_be_whole_numbers(): void
    {
        $product = $this->makeStockedProduct(['M' => 10], 1000, 400);

        $response = $this->postJson(route('stock-adjustments.store'), [
            'transaction_date' => now()->toDateString(),
            'adjustment_type' => 'normal',
            'products' => [['product_id' => $product->id, 'product_stock_id' => null, 'variant' => 'M', 'quantity' => 1.5, 'unit_price' => 400]],
        ]);

        $response->assertJson(['success' => false]);
        $this->assertSame(10, $this->stockQty($product, 'M'));
    }

    public function test_deleting_the_adjustment_puts_the_units_back_on_the_shelf_and_into_the_batches(): void
    {
        $product = $this->makeStockedProduct(['M' => 10, 'L' => 10], 1000, 400);
        $this->adjust($product, 'M', 4, $this->stockId($product, 'M'))->assertJson(['success' => true]);
        $adjustment = StockAdjustment::firstOrFail();

        $this->deleteJson(route('stock-adjustments.destroy', $adjustment->id))->assertJson(['success' => true]);

        $this->assertSame(10, $this->stockQty($product, 'M'), 'the shelf comes back too (it used to stay short)');
        $this->assertSame(10, $this->batchRemaining($product, 'M'));
        $this->assertSame(10, $this->batchRemaining($product, 'L'));
        $this->assertSame(0, StockAdjustment::count());
    }
}
