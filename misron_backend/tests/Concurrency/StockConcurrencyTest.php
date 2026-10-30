<?php

namespace Tests\Concurrency;

use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Real overlapping connections: each "shopper" is its own PHP process with its
 * own database session, released together. Assertions are about the end state of
 * the database, which is what must stay true however the statements interleave.
 */
class StockConcurrencyTest extends ConcurrencyTestCase
{
    public function test_the_last_unit_goes_to_exactly_one_of_eight_buyers(): void
    {
        $product = $this->seedProduct(['M' => 1]);

        $results = $this->race(array_fill(0, 8, $this->takeJob([$this->line($product, 'M', 1)], ['hold_ms' => 40])));
        $tally = $this->tally($results);

        $this->assertSame(1, $tally['ok'], json_encode($results));
        $this->assertSame(['out_of_stock' => 7], $tally['codes']);
        $this->assertSame(0, $this->stock($product, 'M'));
        $this->assertSame(0, $this->batchSum($product, 'M'));
    }

    public function test_variations_are_independent_under_contention(): void
    {
        $product = $this->seedProduct(['L' => 1, 'M' => 20]);

        $jobs = [];
        for ($i = 0; $i < 4; $i++) {
            $jobs[] = $this->takeJob([$this->line($product, 'L', 1)], ['hold_ms' => 20]);
            $jobs[] = $this->takeJob([$this->line($product, 'M', 1)], ['hold_ms' => 20]);
        }
        $results = $this->race($jobs);

        // Even-numbered jobs wanted L, odd-numbered ones M.
        $lWins = count(array_filter($results, fn ($r, $i) => $i % 2 === 0 && !empty($r['ok']), ARRAY_FILTER_USE_BOTH));
        $this->assertSame(1, $lWins, json_encode($results));
        $this->assertSame(0, $this->stock($product, 'L'));
        $this->assertSame(16, $this->stock($product, 'M'), 'every M buyer succeeded');
        $this->assertSame(16, $this->batchSum($product, 'M'));
    }

    public function test_orders_with_the_same_products_in_opposite_order_never_deadlock(): void
    {
        $a = $this->seedProduct(['M' => 3]);
        $b = $this->seedProduct(['M' => 3]);

        $jobs = [];
        for ($i = 0; $i < 10; $i++) {
            $lines = [$this->line($a, 'M', 1), $this->line($b, 'M', 1)];
            $jobs[] = $this->takeJob($i % 2 ? array_reverse($lines) : $lines, ['hold_ms' => 15]);
        }
        $results = $this->race($jobs);
        $tally = $this->tally($results);

        $this->assertSame(3, $tally['ok'], json_encode($results));
        $this->assertSame(['out_of_stock' => 7], $tally['codes'], 'refusals are plain stock refusals, never a deadlock');
        $this->assertSame(0, $this->stock($a, 'M'));
        $this->assertSame(0, $this->stock($b, 'M'));
    }

    public function test_fifo_batches_are_consumed_exactly_by_what_was_sold(): void
    {
        $product = $this->seedProduct(['S' => 10], 1000, 400, ['S' => [2, 3, 5]]); // costs 400, 410, 420

        $results = $this->race(array_fill(0, 12, $this->takeJob([$this->line($product, 'S', 1)], ['hold_ms' => 10])));
        $tally = $this->tally($results);

        $this->assertSame(10, $tally['ok'], json_encode($tally));
        $this->assertSame(['out_of_stock' => 2], $tally['codes']);
        $this->assertSame(0, $this->stock($product, 'S'));
        $this->assertSame(0, $this->batchSum($product, 'S'));
        $this->assertSame(0, DB::table('purchase_products')->where('product_id', $product)->where('remaining_qty', '<', 0)->count());

        $sold = array_sum(array_map(fn ($r) => $r['cost'] ?? 0, $results));
        $this->assertEquals(2 * 400 + 3 * 410 + 5 * 420, $sold, 'costs of all orders add up to the cost of the batches');
    }

    public function test_a_failure_after_the_stock_is_taken_puts_everything_back(): void
    {
        $product = $this->seedProduct(['M' => 5]);

        $jobs = [];
        for ($i = 0; $i < 3; $i++) {
            $jobs[] = $this->takeJob([$this->line($product, 'M', 2)], ['fail_after' => true, 'hold_ms' => 10]);
            $jobs[] = $this->takeJob([$this->line($product, 'M', 1)], ['hold_ms' => 10]);
        }
        $results = $this->race($jobs);

        $bought = count(array_filter($results, fn ($r) => !empty($r['ok'])));
        $this->assertSame(3, $bought, json_encode($results));
        $this->assertSame(5 - $bought, $this->stock($product, 'M'), 'only the orders that completed took stock');
        $this->assertSame(5 - $bought, $this->batchSum($product, 'M'), 'batches of the failed orders were given back');
    }

    public function test_cancelling_the_same_line_from_many_processes_restocks_once(): void
    {
        $product = $this->seedProduct(['M' => 10]);
        $ledger = app(StockLedger::class);

        DB::beginTransaction();
        $take = $ledger->take([$this->line($product, 'M', 3, 'x')]);
        $orderId = DB::table('orders')->insertGetId(['user_id' => 1, 'code' => 'RACE' . random_int(100, 999), 'date' => time(), 'created_at' => now(), 'updated_at' => now()]);
        $lineId = DB::table('order_details')->insertGetId([
            'order_id' => $orderId, 'product_id' => $product, 'variation' => 'M', 'quantity' => 3, 'price' => 3000,
            'delivery_status' => 'pending', 'fifo_batch_info' => $take->batchInfo('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::commit();
        $this->assertSame(7, $this->stock($product, 'M'));

        $results = $this->race(array_fill(0, 6, ['action' => 'give', 'order_detail_id' => $lineId]));

        $this->assertSame(1, count(array_filter($results, fn ($r) => !empty($r['gave']))), json_encode($results));
        $this->assertSame(10, $this->stock($product, 'M'));
        $this->assertSame(10, $this->batchSum($product, 'M'));
    }

    public function test_five_taps_on_place_order_create_one_order(): void
    {
        $product = $this->seedProduct(['M' => 10]);
        DB::table('carts')->insert([
            'temp_user_id' => '800001', 'product_id' => $product, 'variation' => 'M', 'quantity' => 2,
            'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $body = [
            'temp_user_id' => '800001', 'payment_type' => 'cash_on_delivery', 'delivery_type' => 'home_delivery',
            'guest_info' => ['name' => 'Tap Tester', 'phone' => '01712345678', 'address' => 'Road 1', 'city_name' => 'Dhaka'],
        ];
        $results = $this->race(array_fill(0, 5, ['action' => 'http', 'uri' => '/api/v2/guest/order/store', 'body' => $body]));

        $placed = array_filter($results, fn ($r) => !empty($r['body']['result']));
        $this->assertCount(1, $placed, json_encode($results));
        $this->assertSame(1, DB::table('orders')->where('guest_id', '800001')->count());
        $this->assertSame(8, $this->stock($product, 'M'), 'two units, once');
        $this->assertSame(8, $this->batchSum($product, 'M'));
        $this->assertSame(0, DB::table('carts')->where('temp_user_id', '800001')->count());
    }

    public function test_the_storefront_and_a_funnel_style_checkout_race_for_the_last_unit(): void
    {
        $product = $this->seedProduct(['M' => 1]);
        DB::table('carts')->insert([
            'temp_user_id' => '800002', 'product_id' => $product, 'variation' => 'M', 'quantity' => 1,
            'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $results = $this->race([
            ['action' => 'http', 'uri' => '/api/v2/guest/order/store', 'body' => [
                'temp_user_id' => '800002', 'payment_type' => 'cash_on_delivery', 'delivery_type' => 'home_delivery',
                'guest_info' => ['name' => 'Mix', 'phone' => '01787654321', 'address' => 'Road 2', 'city_name' => 'Dhaka'],
            ]],
            $this->takeJob([$this->line($product, 'M', 1)], ['hold_ms' => 20]),
        ]);

        $storefrontWon = !empty($results[0]['body']['result']);
        $funnelWon = !empty($results[1]['ok']);
        $this->assertTrue($storefrontWon xor $funnelWon, 'exactly one channel gets the unit: ' . json_encode($results));
        $this->assertSame(0, $this->stock($product, 'M'));
        $this->assertSame(0, $this->batchSum($product, 'M'));
    }

    public function test_five_taps_on_the_funnel_order_button_create_one_order(): void
    {
        [$slug, $product, $cityId] = $this->seedFunnel(['M' => 10]);
        $job = $this->checkoutJob($slug, $cityId, ['selections' => [['key' => 'offer_one', 'quantity' => 2]]]);

        $results = $this->race(array_fill(0, 5, $job));

        $statuses = array_map(fn ($r) => $r['status'] ?? null, $results);
        $placed = array_filter($results, fn ($r) => ($r['status'] ?? null) === 201);
        $this->assertCount(1, $placed, json_encode($results));
        // Everyone else got the same order back, or was asked to wait a moment: never a second order.
        foreach ($results as $r) {
            $this->assertContains($r['status'] ?? null, [200, 201, 409], json_encode($r));
            if (($r['status'] ?? null) === 409) {
                $this->assertSame('in_progress', $r['body']['code']);
            }
        }
        $this->assertSame(1, $this->created('orders'), json_encode($statuses));
        $this->assertSame(1, $this->created('funnel_checkout_attempts'));
        $this->assertSame(8, $this->stock($product, 'M'), 'two units, once');
        $this->assertSame(8, $this->batchSum($product, 'M'));
    }

    public function test_eight_customers_race_for_the_last_unit_through_the_funnel(): void
    {
        [$slug, $product, $cityId] = $this->seedFunnel(['M' => 1]);

        $jobs = [];
        for ($i = 0; $i < 8; $i++) {
            $jobs[] = $this->checkoutJob($slug, $cityId, [], ['phone' => '01712' . str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT), 'name' => "Buyer {$i}"]);
        }
        $results = $this->race($jobs);

        $this->assertCount(1, array_filter($results, fn ($r) => ($r['status'] ?? null) === 201), json_encode($results));
        foreach ($results as $r) {
            if (($r['status'] ?? null) !== 201) {
                $this->assertSame(409, $r['status'], json_encode($r));
                $this->assertContains($r['body']['code'], ['out_of_stock', 'offer_unavailable'], json_encode($r));
            }
        }
        $this->assertSame(1, $this->created('orders'));
        $this->assertSame(0, $this->stock($product, 'M'));
        $this->assertSame(0, $this->batchSum($product, 'M'));
    }

    public function test_the_funnel_and_the_storefront_race_for_the_last_unit(): void
    {
        [$slug, $product, $cityId] = $this->seedFunnel(['M' => 1]);
        DB::table('carts')->insert([
            'temp_user_id' => '800003', 'product_id' => $product, 'variation' => 'M', 'quantity' => 1,
            'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $results = $this->race([
            $this->checkoutJob($slug, $cityId),
            ['action' => 'http', 'uri' => '/api/v2/guest/order/store', 'body' => [
                'temp_user_id' => '800003', 'payment_type' => 'cash_on_delivery', 'delivery_type' => 'home_delivery',
                'guest_info' => ['name' => 'Web', 'phone' => '01787654321', 'address' => 'Road 2', 'city_name' => 'Dhaka'],
            ]],
        ]);

        $funnelWon = ($results[0]['status'] ?? null) === 201;
        $webWon = !empty($results[1]['body']['result']);
        $this->assertTrue($funnelWon xor $webWon, 'exactly one channel gets the unit: ' . json_encode($results));
        $this->assertSame(0, $this->stock($product, 'M'));
        $this->assertSame(0, $this->batchSum($product, 'M'));
        $this->assertSame(1, $this->created('orders'));
    }

    public function test_many_shoppers_many_products_keep_every_number_consistent(): void
    {
        mt_srand(20261023);
        $products = [];
        foreach (range(1, 4) as $n) {
            $products[] = $this->seedProduct(['M' => 6, 'L' => 6], 1000, 300, ['M' => [2, 4], 'L' => [6]]);
        }
        $before = DB::table('product_stocks')->whereIn('product_id', $products)->sum('qty');

        $jobs = [];
        $asked = [];
        foreach (range(1, 24) as $n) {
            $lines = [];
            foreach ((array) array_rand(array_flip($products), mt_rand(1, 3)) as $productId) {
                $variant = mt_rand(0, 1) ? 'M' : 'L';
                $lines[] = $this->line((int) $productId, $variant, mt_rand(1, 2));
            }
            shuffle($lines);
            $asked[] = $lines;
            $jobs[] = $this->takeJob($lines, ['hold_ms' => mt_rand(0, 25)]);
        }
        $results = $this->race($jobs, 120);

        $units = 0;
        foreach ($results as $i => $r) {
            if (!empty($r['ok'])) {
                $units += array_sum(array_column($asked[$i], 'quantity'));
            } else {
                $this->assertSame('out_of_stock', $r['code'] ?? null, 'only stock refusals are expected: ' . json_encode($r));
            }
        }

        $after = DB::table('product_stocks')->whereIn('product_id', $products)->sum('qty');
        $batches = DB::table('purchase_products')->whereIn('product_id', $products)->sum('remaining_qty');
        $this->assertSame((int) $before - $units, (int) $after, 'stock fell by exactly the units sold');
        $this->assertSame((int) $before - $units, (int) $batches, 'batches fell by exactly the units sold');
        $this->assertSame(0, DB::table('product_stocks')->whereIn('product_id', $products)->where('qty', '<', 0)->count());
        $this->assertSame(0, DB::table('purchase_products')->whereIn('product_id', $products)->where('remaining_qty', '<', 0)->count());
    }

    /**
     * The conflict the writers audit documents. A purchase receipt (and POS, returns, the
     * product form) loads the stock row, adds in PHP and saves the absolute number, so a sale
     * that lands in between is silently erased. Not a requirement: it shows what those
     * writers do to the ledger's work until they use adjust().
     */
    public function test_a_read_modify_write_writer_erases_a_concurrent_sale(): void
    {
        $product = $this->seedProduct(['M' => 10]);

        $this->race([
            ['action' => 'rmw_add', 'product_id' => $product, 'variant' => 'M', 'delta' => 5, 'pause_ms' => 300],
            $this->takeJob([$this->line($product, 'M', 1)]),
        ]);

        $this->assertSame(15, $this->stock($product, 'M'), 'the sale of one unit vanished from product_stocks');
        $this->assertSame(9, $this->batchSum($product, 'M'), 'while the batch really lost it: the two records now disagree');
    }

    public function test_adjust_keeps_both_a_concurrent_sale_and_a_receipt(): void
    {
        $product = $this->seedProduct(['M' => 10]);

        $results = $this->race([
            ['action' => 'adjust', 'product_id' => $product, 'variant' => 'M', 'delta' => 5],
            $this->takeJob([$this->line($product, 'M', 1)], ['hold_ms' => 50]),
        ]);

        $this->assertSame(14, $this->stock($product, 'M'), json_encode($results));
    }

    // ---- the other writers, now on the same statements ------------------------------------------

    public function test_a_purchase_receipt_and_a_sale_at_the_same_moment_both_count(): void
    {
        $product = $this->seedProduct(['M' => 10]);

        $results = $this->race([
            ['action' => 'receipt', 'product_id' => $product, 'variant' => 'M', 'delta' => 5, 'hold_ms' => 100],
            $this->takeJob([$this->line($product, 'M', 1)], ['hold_ms' => 100]),
        ]);

        // The old receipt wrote back 15 and erased the sale (see the read-modify-write test above).
        $this->assertSame(14, $this->stock($product, 'M'), json_encode($results));
    }

    public function test_six_receipts_at_once_add_up_exactly(): void
    {
        $product = $this->seedProduct(['M' => 10]);

        $this->race(array_fill(0, 6, ['action' => 'receipt', 'product_id' => $product, 'variant' => 'M', 'delta' => 1]));

        $this->assertSame(16, $this->stock($product, 'M'));
    }

    public function test_an_order_edit_changing_the_shelf_keeps_a_concurrent_sale(): void
    {
        $product = $this->seedProduct(['M' => 10]);

        $this->race([
            ['action' => 'edit_shelf', 'product_id' => $product, 'variant' => 'M', 'quantity' => 2],
            $this->takeJob([$this->line($product, 'M', 3)], ['hold_ms' => 80]),
        ]);

        $this->assertSame(5, $this->stock($product, 'M'), 'the edit took 2 and the sale took 3');
    }

    public function test_resizing_a_batch_for_a_purchase_edit_keeps_a_concurrent_sale(): void
    {
        $product = $this->seedProduct(['M' => 10]);
        $batchId = (int) DB::table('purchase_products')->where('product_id', $product)->value('id');

        $results = $this->race([
            ['action' => 'resize', 'batch_id' => $batchId, 'delta' => 5],
            $this->takeJob([$this->line($product, 'M', 2)], ['hold_ms' => 80]),
        ]);

        $this->assertSame(13, $this->batchSum($product, 'M'), json_encode($results));
        $this->assertSame('15', (string) DB::table('purchase_products')->where('id', $batchId)->value('purchase_quantity'));
    }

    public function test_a_batch_is_never_resized_below_zero_whatever_lands_in_between(): void
    {
        $product = $this->seedProduct(['M' => 10]);
        $batchId = (int) DB::table('purchase_products')->where('product_id', $product)->value('id');

        $results = $this->race([
            ['action' => 'resize', 'batch_id' => $batchId, 'delta' => -8],
            $this->takeJob([$this->line($product, 'M', 5)], ['hold_ms' => 80]),
        ]);

        $this->assertGreaterThanOrEqual(0, $this->batchSum($product, 'M'), json_encode($results));
        $this->assertGreaterThanOrEqual(0, $this->stock($product, 'M'));
    }

    public function test_cancelling_a_line_from_six_admin_screens_at_once_restocks_once(): void
    {
        $product = $this->seedProduct(['M' => 10]);
        $ledger = app(StockLedger::class);

        DB::beginTransaction();
        $take = $ledger->take([$this->line($product, 'M', 4, 'x')]);
        $orderId = DB::table('orders')->insertGetId(['user_id' => 1, 'code' => 'RACE' . random_int(100, 999), 'date' => time(), 'created_at' => now(), 'updated_at' => now()]);
        $lineId = DB::table('order_details')->insertGetId([
            'order_id' => $orderId, 'product_id' => $product, 'variation' => 'M', 'quantity' => 4, 'price' => 4000,
            'delivery_status' => 'pending', 'fifo_batch_info' => $take->batchInfo('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::commit();
        $this->assertSame(6, $this->stock($product, 'M'));

        $this->race(array_fill(0, 6, ['action' => 'cancel_line', 'order_detail_id' => $lineId]));

        $this->assertSame(10, $this->stock($product, 'M'), 'only one of the six restocked');
        $this->assertSame(10, $this->batchSum($product, 'M'));
    }

    public function test_adjust_will_not_take_stock_below_zero(): void
    {
        $product = $this->seedProduct(['M' => 3]);

        $results = $this->race(array_fill(0, 5, ['action' => 'adjust', 'product_id' => $product, 'variant' => 'M', 'delta' => -1]));

        $this->assertSame(3, count(array_filter($results, fn ($r) => !empty($r['ok']))));
        $this->assertSame(0, $this->stock($product, 'M'));
    }

    /**
     * Characterisation, not a requirement: the logic the storefront used before the
     * stock service, run the same way. It is kept to show what the service replaces.
     */
    public function test_the_previous_read_then_write_logic_sold_the_last_unit_twice(): void
    {
        $product = $this->seedProduct(['M' => 1]);

        $results = $this->race(array_fill(0, 2, ['action' => 'legacy_take', 'product_id' => $product, 'variant' => 'M', 'quantity' => 1, 'pause_ms' => 150]));

        $this->assertSame(2, $this->tally($results)['ok'], 'both buyers were told yes for a single unit');
        $this->assertSame(0, $this->stock($product, 'M'), 'and the second write simply overwrote the first');
    }
}
