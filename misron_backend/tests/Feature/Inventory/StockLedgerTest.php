<?php

namespace Tests\Feature\Inventory;

use App\Models\OrderDetail;
use App\Models\PurchaseProduct;
use App\Services\Inventory\Exceptions\CostBatchesMissing;
use App\Services\Inventory\Exceptions\InvalidStockRequest;
use App\Services\Inventory\Exceptions\OutOfStock;
use App\Services\Inventory\Exceptions\VariantUnavailable;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Inventory\InventoryTestCase;

class StockLedgerTest extends InventoryTestCase
{
    private function ledger(): StockLedger
    {
        return app(StockLedger::class);
    }

    private function line($product, string $variant, int $quantity, $key = null, bool $digital = false): array
    {
        return ['key' => $key ?? "{$product->id}|{$variant}", 'product_id' => $product->id, 'variant' => $variant, 'quantity' => $quantity, 'digital' => $digital];
    }

    // ---- the sale itself ------------------------------------------------

    public function test_take_subtracts_stock_and_walks_batches_oldest_first(): void
    {
        $product = $this->makeProduct(['unit_price' => 900], [['variant' => 'M', 'qty' => 8, 'price' => 900]]);
        $first = $this->makeBatch($product, 'M', 3, 100);
        $second = $this->makeBatch($product, 'M', 5, 120);

        $take = $this->ledger()->take([$this->line($product, 'M', 5)]);

        $this->assertSame(3, $this->stockQty($product, 'M'));
        $this->assertSame(0, (int) $first->fresh()->remaining_qty);
        $this->assertSame(3, (int) $second->fresh()->remaining_qty);

        $line = $take->line("{$product->id}|M");
        $this->assertEquals(3 * 100 + 2 * 120, $line['cost']);
        $this->assertEquals(108.0, $line['avg_cost']);
        $this->assertCount(2, $line['batches']);
        $this->assertSame($first->id, $line['batches'][0]['purchase_product_id']);
        $this->assertSame(3, $line['batches'][0]['qty']);
        $this->assertSame($second->id, $line['batches'][1]['purchase_product_id']);
        $this->assertSame(1, $line['batches'][0]['supplier_id']);
    }

    public function test_result_matches_the_routine_it_replaces(): void
    {
        // The routine the storefront used before, reproduced as it was.
        $legacy = function ($productId, $variant, $qtyToConsume) {
            $remaining = $qtyToConsume;
            $totalCost = 0.0;
            $batches = [];
            $rows = PurchaseProduct::with('purchase')->where('product_id', $productId)->where('remaining_qty', '>', 0)
                ->when(!empty($variant), fn ($q) => $q->where('variant', $variant))
                ->when(empty($variant), fn ($q) => $q->whereNull('variant'))
                ->orderBy('id', 'asc')->get();
            foreach ($rows as $batch) {
                if ($remaining <= 0) {
                    break;
                }
                $take = min($batch->remaining_qty, $remaining);
                $batch->remaining_qty -= $take;
                $batch->save();
                $cost = $take * $batch->buying_unit_cost;
                $totalCost += $cost;
                $batches[] = ['purchase_product_id' => $batch->id, 'purchase_id' => $batch->purchase_id, 'supplier_id' => optional($batch->purchase)->supplier_id,
                    'qty' => $take, 'unit_price' => $batch->buying_unit_cost, 'total_price' => $cost];
                $remaining -= $take;
            }

            return ['total_cost' => $totalCost, 'batches' => $batches];
        };

        foreach ([[7, 3, 2.5], [1, 1, 99.99], [20, 20, 0.1], [4, 4, 1500]] as [$buy, $stockFor, $cost]) {
            $a = $this->makeProduct([], [['variant' => 'S', 'qty' => 50]]);
            $b = $this->makeProduct([], [['variant' => 'S', 'qty' => 50]]);
            foreach ([$a, $b] as $p) {
                $this->makeBatch($p, 'S', 2, $cost);
                $this->makeBatch($p, 'S', 5, $cost + 10);
                $this->makeBatch($p, 'S', 30, $cost + 20);
            }
            $qty = min($buy, 37);

            $old = $legacy($a->id, 'S', $qty);
            $new = $this->ledger()->take([$this->line($b, 'S', $qty)])->line("{$b->id}|S");

            $this->assertEquals($old['total_cost'], $new['cost'], "cost for qty {$qty} at {$cost}");
            $this->assertSame(
                array_map(fn ($x) => [$x['qty'], (string) $x['unit_price'], $x['supplier_id']], $old['batches']),
                array_map(fn ($x) => [$x['qty'], (string) $x['unit_price'], $x['supplier_id']], $new['batches'])
            );
            $this->assertSame($this->batchRemaining($a, 'S'), $this->batchRemaining($b, 'S'));
        }
    }

    public function test_plain_products_use_an_empty_variant_and_match_null_batches(): void
    {
        $product = $this->makeProduct([], [['variant' => '', 'qty' => 4]]);
        $batch = $this->makeBatch($product, null, 4, 50);

        $take = $this->ledger()->take([['key' => 'a', 'product_id' => $product->id, 'variant' => null, 'quantity' => 3]]);

        $this->assertSame(1, $this->stockQty($product, ''));
        $this->assertSame(1, (int) $batch->fresh()->remaining_qty);
        $this->assertEquals(150.0, $take->line('a')['cost']);
    }

    // ---- refusals ----------------------------------------------------------

    public function test_asking_for_more_than_the_stock_row_holds_changes_nothing(): void
    {
        $product = $this->makeStockedProduct(['M' => 3]);

        try {
            $this->ledger()->take([$this->line($product, 'M', 4)]);
            $this->fail('Expected OutOfStock');
        } catch (OutOfStock $e) {
            $this->assertSame('out_of_stock', $e->errorCode());
            $this->assertSame(3, $e->available());
            $this->assertSame($product->id, $e->context()['product_id']);
        }

        $this->assertSame(3, $this->stockQty($product, 'M'));
        $this->assertSame(3, $this->batchRemaining($product, 'M'));
    }

    public function test_last_unit_goes_to_the_first_buyer_only(): void
    {
        $product = $this->makeStockedProduct(['M' => 1]);

        $this->ledger()->take([$this->line($product, 'M', 1)]);
        $this->expectException(OutOfStock::class);
        $this->ledger()->take([$this->line($product, 'M', 1)]);
    }

    public function test_an_unknown_variation_is_reported_not_crashed_on(): void
    {
        $product = $this->makeStockedProduct(['M' => 3]);

        $this->expectException(VariantUnavailable::class);
        $this->ledger()->take([$this->line($product, 'XXL', 1)]);
    }

    public function test_stock_without_cost_batches_is_refused_and_leaves_no_trace(): void
    {
        $product = $this->makeProduct([], [['variant' => 'M', 'qty' => 10]]);
        $this->makeBatch($product, 'M', 2, 100);

        try {
            $this->ledger()->take([$this->line($product, 'M', 5)]);
            $this->fail('Expected CostBatchesMissing');
        } catch (CostBatchesMissing $e) {
            $this->assertSame('inventory_unverified', $e->errorCode());
            $this->assertSame(3, $e->context()['missing']);
        }

        // Neither the stock row nor the two batch units that were read first.
        $this->assertSame(10, $this->stockQty($product, 'M'));
        $this->assertSame(2, $this->batchRemaining($product, 'M'));
    }

    public function test_a_refused_line_undoes_the_lines_taken_before_it(): void
    {
        $a = $this->makeStockedProduct(['M' => 5]);
        $b = $this->makeStockedProduct(['M' => 1]);

        try {
            // Same transaction stays open, as a caller that catches the exception would leave it.
            $this->ledger()->take([$this->line($a, 'M', 2), $this->line($b, 'M', 2)]);
            $this->fail('Expected OutOfStock');
        } catch (OutOfStock $e) {
            $this->assertSame($b->id, $e->context()['product_id']);
        }

        $this->assertSame(5, $this->stockQty($a, 'M'));
        $this->assertSame(5, $this->batchRemaining($a, 'M'));
        $this->assertSame(1, $this->stockQty($b, 'M'));
    }

    public function test_the_same_variation_twice_in_one_order_is_counted_twice(): void
    {
        $product = $this->makeStockedProduct(['M' => 5]);

        $this->ledger()->take([$this->line($product, 'M', 3, 'first'), $this->line($product, 'M', 2, 'second')]);
        $this->assertSame(0, $this->stockQty($product, 'M'));

        $this->expectException(OutOfStock::class);
        $this->ledger()->take([$this->line($product, 'M', 3, 'a'), $this->line($product, 'M', 3, 'b')]);
    }

    public function test_digital_lines_move_nothing(): void
    {
        $product = $this->makeProduct(['digital' => 1], [['variant' => '', 'qty' => 0]]);

        $take = $this->ledger()->take([$this->line($product, '', 3, 'ebook', true)]);

        $this->assertSame(0, $this->stockQty($product, ''));
        $this->assertSame(0.0, $take->line('ebook')['cost']);
        $this->assertSame([], $take->line('ebook')['batches']);
    }

    /** @dataProvider badLines */
    public function test_malformed_lines_are_rejected_before_anything_moves($line): void
    {
        $product = $this->makeStockedProduct(['M' => 5]);
        $line = array_merge(['product_id' => $product->id, 'variant' => 'M', 'quantity' => 1], $line);

        try {
            $this->ledger()->take([$line]);
            $this->fail('Expected InvalidStockRequest');
        } catch (InvalidStockRequest $e) {
            $this->assertSame('invalid_request', $e->errorCode());
        }
        $this->assertSame(5, $this->stockQty($product, 'M'));
    }

    public function badLines(): array
    {
        return [
            'zero' => [['quantity' => 0]],
            'negative' => [['quantity' => -2]],
            'fraction' => [['quantity' => 1.5]],
            'text' => [['quantity' => 'many']],
            'huge' => [['quantity' => 1000000]],
            'no product' => [['product_id' => 0]],
        ];
    }

    public function test_it_will_not_run_outside_a_transaction(): void
    {
        $product = $this->makeStockedProduct(['M' => 5]);

        DB::rollBack();
        try {
            $this->expectException(LogicException::class);
            $this->ledger()->take([$this->line($product, 'M', 1)]);
        } finally {
            DB::beginTransaction(); // hand the framework its transaction back
        }
    }

    // ---- locking order -----------------------------------------------------

    public function test_rows_are_always_touched_in_product_and_variation_order(): void
    {
        $low = $this->makeStockedProduct(['A' => 5, 'B' => 5]);
        $high = $this->makeStockedProduct(['A' => 5]);

        $seen = [];
        DB::listen(function ($query) use (&$seen) {
            if (stripos($query->sql, 'update product_stocks') === 0) {
                $seen[] = [$query->bindings[2], $query->bindings[3]];
            }
        });

        // Deliberately the opposite of the sorted order.
        $this->ledger()->take([$this->line($high, 'A', 1), $this->line($low, 'B', 1), $this->line($low, 'A', 1)]);

        $this->assertSame([[$low->id, 'A'], [$low->id, 'B'], [$high->id, 'A']], $seen);
    }

    // ---- not turning a duplicated row into a double sale ---------------------

    public function test_a_duplicated_stock_row_is_only_charged_once(): void
    {
        // Only the original schema allows this: the unique key forbids a second row.
        if ($this->stockKeyIsUnique()) {
            $this->markTestSkipped('The unique key on product_stocks makes duplicate rows impossible here.');
        }
        $product = $this->makeProduct([], [['variant' => 'M', 'qty' => 5], ['variant' => 'M', 'qty' => 5]]);
        $this->makeBatch($product, 'M', 10, 100);

        $this->ledger()->take([$this->line($product, 'M', 2)]);

        $this->assertSame(8, (int) DB::table('product_stocks')->where('product_id', $product->id)->sum('qty'));
    }

    // ---- if the order fails later and batches do not roll back ---------------

    public function test_release_gives_batch_units_back_when_they_do_not_roll_back(): void
    {
        config(['inventory.fifo_transactional' => false]); // as on the original MyISAM schema
        $product = $this->makeStockedProduct(['M' => 6]);

        $take = $this->ledger()->take([$this->line($product, 'M', 4)]);
        $this->assertSame(2, $this->batchRemaining($product, 'M'));

        $this->ledger()->release($take);
        $this->assertSame(6, $this->batchRemaining($product, 'M'));

        // Twice is harmless.
        $this->ledger()->release($take);
        $this->assertSame(6, $this->batchRemaining($product, 'M'));
    }

    public function test_release_does_nothing_when_batches_roll_back_with_the_transaction(): void
    {
        config(['inventory.fifo_transactional' => true]);
        $product = $this->makeStockedProduct(['M' => 6]);

        $take = $this->ledger()->take([$this->line($product, 'M', 4)]);
        $this->ledger()->release($take);

        $this->assertSame(2, $this->batchRemaining($product, 'M'));
    }

    // ---- cancelling ----------------------------------------------------------

    private function soldLine($product, string $variant, int $quantity, array $extra = []): OrderDetail
    {
        $take = $this->ledger()->take([$this->line($product, $variant, $quantity, 'x')]);
        $order = DB::table('orders')->insertGetId(['user_id' => 1, 'code' => 'T' . random_int(1000, 9999), 'date' => time(), 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('order_details')->insertGetId(array_merge([
            'order_id' => $order, 'product_id' => $product->id, 'variation' => $variant, 'quantity' => $quantity, 'price' => 1000 * $quantity,
            'delivery_status' => 'pending', 'fifo_batch_info' => $take->batchInfo('x'), 'created_at' => now(), 'updated_at' => now(),
        ], $extra));
        DB::table('products')->where('id', $product->id)->update(['num_of_sale' => $quantity]);

        return OrderDetail::find($id);
    }

    public function test_cancelling_puts_stock_and_the_same_batches_back_exactly_once(): void
    {
        $product = $this->makeProduct([], [['variant' => 'M', 'qty' => 8]]);
        $old = $this->makeBatch($product, 'M', 3, 100);
        $new = $this->makeBatch($product, 'M', 5, 120);
        $line = $this->soldLine($product, 'M', 5);
        $this->assertSame(3, $this->stockQty($product, 'M'));

        $this->assertTrue($this->ledger()->give($line));
        $this->assertSame(8, $this->stockQty($product, 'M'));
        $this->assertSame(3, (int) $old->fresh()->remaining_qty);
        $this->assertSame(5, (int) $new->fresh()->remaining_qty);
        $this->assertSame('cancelled', $line->fresh()->delivery_status);
        $this->assertSame(0, (int) DB::table('products')->where('id', $product->id)->value('num_of_sale'));

        // A second cancel, from anywhere, changes nothing.
        $this->assertFalse($this->ledger()->give(OrderDetail::find($line->id)));
        $this->assertFalse($this->ledger()->give($line));
        $this->assertSame(8, $this->stockQty($product, 'M'));
        $this->assertSame(8, $this->batchRemaining($product, 'M'));
    }

    public function test_delivered_lines_are_not_restocked(): void
    {
        $product = $this->makeStockedProduct(['M' => 5]);
        $line = $this->soldLine($product, 'M', 2, ['delivery_status' => 'delivered']);

        $this->assertFalse($this->ledger()->give($line));
        $this->assertSame(3, $this->stockQty($product, 'M'));
    }

    public function test_a_line_already_cancelled_elsewhere_is_not_restocked_again(): void
    {
        $product = $this->makeStockedProduct(['M' => 5]);
        $line = $this->soldLine($product, 'M', 2, ['delivery_status' => 'Cancelled ']);

        $this->assertFalse($this->ledger()->give($line));
        $this->assertSame(3, $this->stockQty($product, 'M'));
    }

    public function test_lines_without_a_batch_record_fall_back_to_the_latest_batches_with_room(): void
    {
        $product = $this->makeProduct([], [['variant' => 'M', 'qty' => 6]]);
        // Both batches are fully used up; the sale took the only unit left, in the newer one.
        $old = $this->makeBatch($product, 'M', 4, 100, 0);
        $new = $this->makeBatch($product, 'M', 4, 120, 1);
        $line = $this->soldLine($product, 'M', 1, ['fifo_batch_info' => null]);
        $this->assertSame(0, $this->batchRemaining($product, 'M'));

        $this->assertTrue($this->ledger()->give($line));

        $this->assertSame(6, $this->stockQty($product, 'M'));
        $this->assertSame(1, (int) $new->fresh()->remaining_qty, 'the newest batch with room gets the unit');
        $this->assertSame(0, (int) $old->fresh()->remaining_qty);
    }

    public function test_a_batch_never_holds_more_than_was_bought_into_it(): void
    {
        $product = $this->makeProduct([], [['variant' => 'M', 'qty' => 4]]);
        $batch = $this->makeBatch($product, 'M', 4, 100);
        $line = $this->soldLine($product, 'M', 2);
        // Somebody already topped the batch back up by hand.
        DB::table('purchase_products')->where('id', $batch->id)->update(['remaining_qty' => 4]);

        $this->ledger()->give($line);

        $this->assertSame(4, (int) $batch->fresh()->remaining_qty);
    }

    public function test_digital_lines_are_cancelled_without_stock_movement(): void
    {
        $product = $this->makeProduct(['digital' => 1], [['variant' => '', 'qty' => 0]]);
        $id = DB::table('order_details')->insertGetId(['order_id' => 1, 'product_id' => $product->id, 'variation' => '', 'quantity' => 2, 'delivery_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertTrue($this->ledger()->give(OrderDetail::find($id)));
        $this->assertSame(0, $this->stockQty($product, ''));
    }

    // ---- other writers ----------------------------------------------------

    public function test_adjust_moves_a_stock_row_atomically_and_never_below_zero(): void
    {
        $product = $this->makeProduct([], [['variant' => 'M', 'qty' => 4]]);

        $this->assertTrue($this->ledger()->adjust($product->id, 'M', 6));
        $this->assertSame(10, $this->stockQty($product, 'M'));

        $this->assertTrue($this->ledger()->adjust($product->id, 'M', -10));
        $this->assertSame(0, $this->stockQty($product, 'M'));

        $this->assertFalse($this->ledger()->adjust($product->id, 'M', -1), 'would go negative');
        $this->assertFalse($this->ledger()->adjust($product->id, 'XXL', 1), 'no such row');
        $this->assertTrue($this->ledger()->adjust($product->id, 'M', 0));
        $this->assertSame(0, $this->stockQty($product, 'M'));
    }

    // ---- reading ---------------------------------------------------------

    public function test_sellable_is_the_smaller_of_stock_and_batches(): void
    {
        $product = $this->makeProduct([], [['variant' => 'M', 'qty' => 10], ['variant' => 'L', 'qty' => 2], ['variant' => 'S', 'qty' => 5]]);
        $this->makeBatch($product, 'M', 4, 100);
        $this->makeBatch($product, 'L', 9, 100);

        $this->assertSame(['stock' => 10, 'batches' => 4, 'sellable' => 4, 'has_stock_row' => true], $this->ledger()->sellable($product->id, 'M'));
        $this->assertSame(['stock' => 2, 'batches' => 9, 'sellable' => 2, 'has_stock_row' => true], $this->ledger()->sellable($product->id, 'L'));
        $this->assertSame(['stock' => 5, 'batches' => 0, 'sellable' => 0, 'has_stock_row' => true], $this->ledger()->sellable($product->id, 'S'));
        $this->assertSame(['stock' => 0, 'batches' => 0, 'sellable' => 0, 'has_stock_row' => false], $this->ledger()->sellable($product->id, 'XXL'));
    }
}
