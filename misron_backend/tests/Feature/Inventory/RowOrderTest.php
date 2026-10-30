<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use Tests\Inventory\InventoryTestCase;

/**
 * Adding an index on (product_id, variant) changes the order in which the database
 * returns rows from a query that has no ORDER BY: alphabetical by variation instead
 * of insertion order. Pages and reports must not depend on that, so the Product
 * relations name their order. These tests hold on the original and the converted schema.
 */
class RowOrderTest extends InventoryTestCase
{
    public function test_variations_come_back_in_the_order_they_were_created(): void
    {
        // Deliberately not alphabetical, and not the order a (product_id, variant) index would give.
        $product = $this->makeProduct([], [
            ['variant' => 'S', 'qty' => 1], ['variant' => 'M', 'qty' => 1], ['variant' => 'XL', 'qty' => 1], ['variant' => 'L', 'qty' => 1],
        ]);

        $this->assertSame(['S', 'M', 'XL', 'L'], $product->fresh()->stocks->pluck('variant')->all());
        $this->assertSame(['S', 'M', 'XL', 'L'], Product::with('stocks')->find($product->id)->stocks->pluck('variant')->all());
        $this->assertSame('S', $product->fresh()->stocks->first()->variant, 'the default variation is the first one created');
    }

    public function test_batches_are_walked_oldest_first_whatever_their_variation(): void
    {
        $product = $this->makeProduct([], [['variant' => 'Z', 'qty' => 5], ['variant' => 'A', 'qty' => 5]]);
        $first = $this->makeBatch($product, 'Z', 5, 100);
        $second = $this->makeBatch($product, 'A', 5, 150);
        $third = $this->makeBatch($product, 'Z', 5, 200);

        $ids = Product::with('purchaseProducts')->find($product->id)->purchaseProducts->pluck('id')->all();

        $this->assertSame([$first->id, $second->id, $third->id], $ids);
        $this->assertSame($third->id, Product::find($product->id)->purchaseProducts->last()->id, 'report code takes last() as the latest purchase');
    }
}
