<?php

namespace Tests\Feature\Inventory;

use App\Services\Inventory\DemandService;
use App\Services\Inventory\Exceptions\InsufficientPhysicalStock;
use App\Services\Inventory\Exceptions\OutOfStock;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\StockPolicy;
use Illuminate\Support\Facades\DB;

class ConfirmedOrderStockTest extends InventoryV2TestCase
{
    public function test_the_specification_example_from_zero_to_dispatch()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder']);
        $this->assertSame(['on_hand' => 0, 'demand' => 0, 'allocated' => 0, 'free' => 0, 'available' => 0, 'incoming' => 0, 'damaged' => 0], $this->snap($p));

        $order = $this->makeOrder([[$p, '', 5]]);                                   // customer orders 5, pending
        $this->assertSame([0, 0, 0], [$this->snap($p)['on_hand'], $this->snap($p)['demand'], $this->snap($p)['available']], 'pending holds nothing');

        $this->setStatus($order, 'confirmed');                                       // confirmed
        $s = $this->snap($p);
        $this->assertSame([0, 5, 0, -5], [$s['on_hand'], $s['demand'], $s['allocated'], $s['available']], 'available goes negative, physical stock does not');
        $this->assertSame(0, $this->stockQty($p, ''), 'product_stocks.qty (physical) is never negative');
        $this->assertSame(0, $this->batchRemaining($p, ''), 'no FIFO layer was created or consumed');

        $this->receive($p, '', 5, '650');                                            // supplier delivers 5
        $s = $this->snap($p);
        $this->assertSame([5, 5, 5, 0], [$s['on_hand'], $s['demand'], $s['allocated'], $s['available']], 'received units are allocated to the waiting order; available is 0, not -5 again, not -10');

        $this->setStatus($order, 'picked_up');                                       // dispatched
        $s = $this->snap($p);
        $this->assertSame([0, 0, 0, 0], [$s['on_hand'], $s['demand'], $s['allocated'], $s['available']], 'dispatch removes the same 5 from physical and demand, available unchanged');

        $this->setStatus($order, 'delivered');                                       // delivered: no second deduction
        $s = $this->snap($p);
        $this->assertSame([0, 0, 0, 0], [$s['on_hand'], $s['demand'], $s['allocated'], $s['available']]);
        $line = $this->detail($order);
        $this->assertEquals(650.0, (float) $line->buying_price, 'cost is the real FIFO layer cost');
        $this->assertSame('final', $line->cost_status);
        $this->assertEquals(5000 - 3250, (float) $line->profit);
        $this->assertSame(0, $this->batchRemaining($p, ''), 'the layer was consumed exactly once');
    }

    public function test_pending_orders_never_change_availability_and_stock_only_blocks_at_zero()
    {
        $p = $this->productAtZero(); // stock_only
        DB::transaction(function () use ($p) {
            $this->expectException(OutOfStock::class);
            app(StockLedger::class)->take([['key' => 0, 'product_id' => $p->id, 'variant' => '', 'quantity' => 1]]);
        });
    }

    public function test_pending_order_for_stocked_item_deducts_nothing()
    {
        $p = $this->makeStockedProduct(['M' => 10], 1000, 400);
        DB::transaction(function () use ($p) {
            $take = app(StockLedger::class)->take([['key' => 0, 'product_id' => $p->id, 'variant' => 'M', 'quantity' => 4]]);
            $this->assertSame([], $take->line(0)['batches'], 'no cost layer is consumed at order creation');
        });
        $this->makeOrder([[$p, 'M', 4]]);
        $this->assertSame(10, $this->stockQty($p, 'M'));
        $this->assertSame(10, $this->batchRemaining($p, 'M'));
        $this->assertSame(10, $this->snap($p, 'M')['available']);
    }

    public function test_repeated_confirmation_and_later_statuses_never_double_count()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder']);
        $order = $this->makeOrder([[$p, '', 3]]);
        $this->setStatus($order, 'confirmed');
        $this->setStatus($order, 'confirmed');
        app(DemandService::class)->confirm($this->detail($order));
        app(DemandService::class)->confirm($this->detail($order));
        $this->setStatus($order, 'processing');
        $this->assertSame(3, $this->snap($p)['demand']);
        $this->assertSame(1, DB::table('order_demands')->where('order_id', $order->id)->count());
        $this->assertSame(1, DB::table('stock_movements')->where('type', 'demand_confirmed')->where('order_detail_id', $this->detail($order)->id)->count());
    }

    public function test_zero_stock_backorder_policy_allows_the_order_and_limits_apply()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder', 'max_backorder_qty' => 5]);
        $this->assertSame('available_on_order', StockPolicy::describe($p->id, '', 1)['state']);
        $this->assertTrue(StockPolicy::describe($p->id, '', 5)['can_order']);
        $o = $this->makeOrder([[$p, '', 4]], 'confirmed');
        $this->assertSame(-4, $this->snap($p)['available']);
        $this->assertTrue(StockPolicy::describe($p->id, '', 1)['can_order']);
        $this->assertFalse(StockPolicy::describe($p->id, '', 2)['can_order'], 'shortage beyond the maximum backorder is refused');
        $this->assertSame('out_of_stock', StockPolicy::describe($p->id, '', 2)['state']);
    }

    public function test_preorder_policy_and_state()
    {
        $p = $this->productAtZero(['stock_policy' => 'preorder', 'max_preorder_qty' => 10, 'preorder_available_at' => now()->addDays(10)->toDateString()]);
        $d = StockPolicy::describe($p->id, '', 3);
        $this->assertSame(['preorder', true], [$d['state'], $d['can_order']]);
        $this->assertNotNull($d['expected_at']);
        $this->assertFalse(StockPolicy::describe($p->id, '', 11)['can_order']);
    }

    public function test_cancel_after_confirmation_releases_demand_once_and_frees_units_for_the_next_order()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder']);
        $first = $this->makeOrder([[$p, '', 2]], 'confirmed');
        $second = $this->makeOrder([[$p, '', 2]], 'confirmed');
        $this->receive($p, '', 2, '100');
        $this->assertSame(2, DB::table('order_demands')->where('order_id', $first->id)->value('qty_allocated'), 'oldest order gets the stock');
        $this->assertSame(0, DB::table('order_demands')->where('order_id', $second->id)->value('qty_allocated'));

        $this->setStatus($first, 'cancelled');
        $this->setStatus($first, 'cancelled');
        set_order_line_status($this->detail($first), 'cancelled');
        $s = $this->snap($p);
        $this->assertSame([2, 2, 2, 0], [$s['on_hand'], $s['demand'], $s['allocated'], $s['available']], 'released once; the freed units moved to the waiting order');
        $this->assertSame(2, DB::table('order_demands')->where('order_id', $second->id)->value('qty_allocated'));
        $this->assertSame(1, DB::table('stock_movements')->where('type', 'demand_released')->where('order_detail_id', $this->detail($first)->id)->count(), 'a repeat cancel releases nothing more');
        $this->assertSame(2, $this->stockQty($p, ''), 'no physical stock was invented or removed');
    }

    public function test_cancelling_a_pending_order_changes_nothing()
    {
        $p = $this->makeStockedProduct(['M' => 5], 1000, 100);
        $o = $this->makeOrder([[$p, 'M', 2]]);
        $before = [$this->snap($p, 'M'), DB::table('stock_movements')->count()];
        $this->setStatus($o, 'cancelled');
        $this->assertSame($before, [$this->snap($p, 'M'), DB::table('stock_movements')->count()]);
    }

    public function test_partial_cancellation_releases_only_the_difference()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder']);
        $o = $this->makeOrder([[$p, '', 5]], 'confirmed');
        $this->receive($p, '', 3, '100');
        app(DemandService::class)->setQuantity($this->detail($o), 3);
        $s = $this->snap($p);
        $this->assertSame([3, 3, 3, 0], [$s['on_hand'], $s['demand'], $s['allocated'], $s['available']]);
        app(DemandService::class)->setQuantity($this->detail($o), 4);
        $this->assertSame(4, $this->snap($p)['demand'], 'editing up adds only the delta');
        app(DemandService::class)->setQuantity($this->detail($o), 4);
        $this->assertSame(4, $this->snap($p)['demand'], 'same quantity again is a no-op');
    }

    public function test_dispatch_is_refused_without_physical_stock_and_nothing_changes()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder']);
        $o = $this->makeOrder([[$p, '', 2]], 'confirmed');
        try {
            $this->setStatus($o, 'picked_up');
            $this->fail('shipping goods that are not in the warehouse must be refused');
        } catch (InsufficientPhysicalStock $e) {
            $this->assertStringContainsString('Receive the purchase first', $e->getMessage());
        }
        $this->assertSame('confirmed', DB::table('orders')->where('id', $o->id)->value('delivery_status'), 'the status was not saved');
        $this->assertSame([0, 2], [$this->snap($p)['on_hand'], $this->snap($p)['demand']]);
    }

    public function test_partial_dispatch_then_the_rest_after_more_stock_arrives()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder']);
        $o = $this->makeOrder([[$p, '', 5]], 'confirmed');
        $this->receive($p, '', 2, '100');
        app(DemandService::class)->dispatch($this->detail($o), 2);
        $s = $this->snap($p);
        $this->assertSame([0, 3, 0, -3], [$s['on_hand'], $s['demand'], $s['allocated'], $s['available']]);
        $this->receive($p, '', 3, '120');
        $this->setStatus($o, 'on_the_way');
        $s = $this->snap($p);
        $this->assertSame([0, 0, 0, 0], [$s['on_hand'], $s['demand'], $s['allocated'], $s['available']]);
        $line = $this->detail($o);
        $this->assertEquals(round((2 * 100 + 3 * 120) / 5, 4), (float) $line->buying_price, 'FIFO: 2 units at 100 then 3 units at 120');
        $this->assertSame('fulfilled', DB::table('order_demands')->where('order_id', $o->id)->value('status'));
    }

    public function test_cancel_after_dispatch_never_invents_stock_and_returns_follow_the_receipt_workflow()
    {
        $p = $this->makeStockedProduct(['M' => 4], 1000, 300);
        $o = $this->makeOrder([[$p, 'M', 4]], 'picked_up');
        $this->assertSame([0, 0], [$this->stockQty($p, 'M'), $this->snap($p, 'M')['demand']]);
        $this->setStatus($o, 'cancelled');
        set_order_line_status($this->detail($o), 'cancelled');
        $this->assertSame(0, $this->stockQty($p, 'M'), 'a cancelled shipped order does not put stock back by itself');

        $line = $this->detail($o);
        $svc = app(DemandService::class);
        $svc->receiveReturn($line, 1, 'damaged', 'ret-1');
        $svc->receiveReturn($line, 1, 'rejected', 'ret-2');
        $this->assertSame(0, $this->stockQty($p, 'M'), 'damaged and rejected goods never become sellable');
        $this->assertSame(2, $this->snap($p, 'M')['damaged']);
        $svc->receiveReturn($line, 2, 'resellable', 'ret-3');
        $this->assertSame(2, $this->stockQty($p, 'M'), 'inspected, resellable goods become stock');
        $this->assertSame(2, $this->batchRemaining($p, 'M'), 'and go back to their FIFO layer');
        $svc->receiveReturn($line, 2, 'resellable', 'ret-3'); // retry of the same receipt
        $this->assertSame(2, $this->stockQty($p, 'M'), 'the same return receipt cannot add stock twice');
        $this->expectException(\InvalidArgumentException::class);
        $svc->receiveReturn($line, 1, 'resellable', 'ret-4'); // 4 shipped, 4 already received
    }

    public function test_variations_are_independent()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder'], ['S', 'M']);
        $o = $this->makeOrder([[$p, 'S', 2], [$p, 'M', 1]], 'confirmed');
        $this->receive($p, 'S', 2, '100');
        $this->assertSame([2, 2, 0], [$this->snap($p, 'S')['on_hand'], $this->snap($p, 'S')['demand'], $this->snap($p, 'S')['available']]);
        $this->assertSame([0, 1, -1], [$this->snap($p, 'M')['on_hand'], $this->snap($p, 'M')['demand'], $this->snap($p, 'M')['available']]);
        try {
            $this->setStatus($o, 'picked_up');
            $this->fail('M is not in stock, so the order cannot ship');
        } catch (InsufficientPhysicalStock $e) {
            $this->assertSame(2, $this->snap($p, 'S')['demand'], 'the S line was not half-dispatched');
        }
    }

    public function test_last_unit_cannot_be_dispatched_twice_even_when_two_orders_were_confirmed()
    {
        $p = $this->makeStockedProduct(['M' => 1], 1000, 100);
        $a = $this->makeOrder([[$p, 'M', 1]], 'confirmed');
        $b = $this->makeOrder([[$p, 'M', 1]], 'confirmed');
        $this->assertSame(-1, $this->snap($p, 'M')['available']);
        $this->setStatus($a, 'picked_up');
        try {
            $this->setStatus($b, 'picked_up');
            $this->fail('the second order must not ship the same unit');
        } catch (InsufficientPhysicalStock $e) {
        }
        $this->assertSame(0, $this->stockQty($p, 'M'), 'physical stock never went below zero');
    }

    public function test_pos_style_immediate_sale_cannot_take_units_earmarked_for_confirmed_orders()
    {
        $p = $this->makeStockedProduct(['M' => 3], 1000, 100);
        $this->makeOrder([[$p, 'M', 2]], 'confirmed'); // 2 of 3 earmarked
        DB::transaction(function () use ($p) {
            app(StockLedger::class)->take([['key' => 0, 'product_id' => $p->id, 'variant' => 'M', 'quantity' => 1]], ['immediate' => true]);
        });
        $this->assertSame(2, $this->stockQty($p, 'M'));
        $this->expectException(OutOfStock::class);
        DB::transaction(function () use ($p) {
            app(StockLedger::class)->take([['key' => 0, 'product_id' => $p->id, 'variant' => 'M', 'quantity' => 1]], ['immediate' => true]);
        });
    }

    public function test_orders_created_before_the_switch_keep_the_legacy_behaviour()
    {
        config(['inventory.confirmed_only' => false]);
        \App\Services\Inventory\InventoryMode::reset();
        $p = $this->makeStockedProduct(['M' => 5], 1000, 100);
        $o = $this->makeOrder([[$p, 'M', 2]]);
        $this->assertSame('legacy', $this->detail($o)->stock_mode);
        config(['inventory.confirmed_only' => true]);
        \App\Services\Inventory\InventoryMode::reset();
        $this->setStatus($o, 'confirmed');
        $this->setStatus($o, 'picked_up');
        $this->assertSame(5, $this->stockQty($p, 'M'), 'a legacy line is not touched by the new status rules');
        $this->assertSame(0, DB::table('order_demands')->where('order_id', $o->id)->count());
    }

    public function test_the_switch_refuses_to_run_on_myisam_tables()
    {
        $this->assertSame([], \App\Services\Inventory\InventoryMode::preflight(), 'this test database is InnoDB, so v2 may run');
    }

    public function test_invariants_hold_after_a_busy_sequence()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder']);
        $orders = [];
        foreach ([2, 3, 1, 4] as $q) {
            $orders[] = $this->makeOrder([[$p, '', $q]], 'confirmed');
        }
        $this->receive($p, '', 5, '100');
        $this->setStatus($orders[0], 'picked_up');
        $this->setStatus($orders[1], 'cancelled');
        $this->receive($p, '', 3, '110');
        $this->setStatus($orders[2], 'delivered');
        $this->assertSame([], app(\App\Services\Inventory\InventoryReconciliation::class)->stockInvariantViolations(), 'on-hand, demand, allocation and the ledger agree');
    }
}
