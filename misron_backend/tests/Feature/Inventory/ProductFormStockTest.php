<?php

namespace Tests\Feature\Inventory;

use App\Models\ProductStock;
use App\Services\ProductService;
use Illuminate\Support\Facades\DB;
use Tests\Inventory\InventoryTestCase;

/**
 * Saving the product form no longer deletes and recreates the stock rows with the quantities typed
 * into it. Rows keep their id and their quantity, so a sale, purchase or adjustment made since the
 * form was opened is never overwritten.
 */
class ProductFormStockTest extends InventoryTestCase
{
    private function sync($product, ?array $variants, array $plain = ['sku' => null, 'price' => 1000, 'qty' => 0]): bool
    {
        return app(ProductService::class)->syncStockRows($product, $variants, $plain);
    }

    private function variant(int $qty, float $price = 1000, ?string $sku = null): array
    {
        return ['sku' => $sku, 'price' => $price, 'qty' => $qty, 'image' => null];
    }

    public function test_existing_variations_keep_their_row_and_their_quantity(): void
    {
        $product = $this->makeStockedProduct(['M' => 10, 'L' => 5], 1000, 400);
        $idsBefore = ProductStock::where('product_id', $product->id)->orderBy('id')->pluck('id')->all();
        // A shopper buys 4 M while the form is open; the form still says 10.
        DB::table('product_stocks')->where('product_id', $product->id)->where('variant', 'M')->decrement('qty', 4);

        $ignored = $this->sync($product, ['M' => $this->variant(10, 1200, 'SKU-M'), 'L' => $this->variant(5, 1200, 'SKU-L')]);

        $this->assertTrue($ignored, 'the caller is told the typed quantity was not applied');
        $this->assertSame(6, $this->stockQty($product, 'M'), 'the sale is not erased');
        $this->assertSame(5, $this->stockQty($product, 'L'));
        $this->assertSame($idsBefore, ProductStock::where('product_id', $product->id)->orderBy('id')->pluck('id')->all(), 'same rows, same ids');
        $row = ProductStock::where('product_id', $product->id)->where('variant', 'M')->first();
        $this->assertSame('SKU-M', $row->sku, 'price and SKU edits are applied');
        $this->assertEquals(1200, (float) $row->price);
        $this->assertSame(11, (int) $product->fresh()->current_stock, 'the total is the real total');
    }

    public function test_a_new_variation_gets_its_opening_quantity(): void
    {
        $product = $this->makeStockedProduct(['M' => 10], 1000, 400);

        $this->sync($product, ['M' => $this->variant(10), 'XL' => $this->variant(7)]);

        $this->assertSame(7, $this->stockQty($product, 'XL'));
        $this->assertSame(17, (int) $product->fresh()->current_stock);
    }

    public function test_a_variation_removed_from_the_form_goes_only_when_its_shelf_is_empty(): void
    {
        $product = $this->makeStockedProduct(['M' => 10, 'L' => 0, 'S' => 3], 1000, 400);

        $this->sync($product, ['M' => $this->variant(10)]);

        $remaining = ProductStock::where('product_id', $product->id)->pluck('qty', 'variant')->all();
        $this->assertSame(['M' => 10, 'S' => 3], $remaining, 'the empty L row is gone; S still holds 3 units and stays');
    }

    public function test_a_plain_product_keeps_its_quantity_and_gets_its_price_and_sku(): void
    {
        $product = $this->makeProduct(['variant_product' => 0], [['variant' => '', 'qty' => 9, 'price' => 1000]]);

        $ignored = $this->sync($product, null, ['sku' => 'PLAIN-1', 'price' => 1500, 'qty' => 50]);

        $this->assertTrue($ignored);
        $row = ProductStock::where('product_id', $product->id)->firstOrFail();
        $this->assertSame(9, (int) $row->qty);
        $this->assertSame('PLAIN-1', $row->sku);
        $this->assertEquals(1500, (float) $row->price);
        $this->assertSame(9, (int) $product->fresh()->current_stock);
    }

    public function test_a_plain_product_with_no_row_gets_one_with_the_typed_quantity(): void
    {
        $product = $this->makeProduct(['variant_product' => 0], []);
        DB::table('product_stocks')->where('product_id', $product->id)->delete();

        $ignored = $this->sync($product, null, ['sku' => null, 'price' => 700, 'qty' => 12]);

        $this->assertFalse($ignored);
        $this->assertSame(12, (int) ProductStock::where('product_id', $product->id)->value('qty'));
    }

    public function test_saving_without_changes_asks_the_caller_for_no_warning(): void
    {
        $product = $this->makeStockedProduct(['M' => 10], 1000, 400);

        $this->assertFalse($this->sync($product, ['M' => $this->variant(10)]));
    }
}
