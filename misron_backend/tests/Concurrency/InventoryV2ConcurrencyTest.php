<?php

namespace Tests\Concurrency;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Services\Inventory\InventoryMode;
use App\Services\Inventory\InventoryReconciliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Real parallel processes against committed rows (the other suites roll back, which cannot show races).
 * Everything this class commits is removed again in tearDown, including chart-of-accounts balances.
 */
class InventoryV2ConcurrencyTest extends ConcurrencyTestCase
{
    private const EXTRA = ['suppliers', 'goods_receipts', 'goods_receipt_lines', 'journal_entries', 'journal_lines', 'idempotency_keys', 'order_demands', 'stock_movements', 'stock_allocations', 'stock_balances',
        'supplier_invoices', 'supplier_invoice_payments', 'account_statement_logs', 'chart_of_accounts'];

    private array $extraHigh = [];
    private array $balances = [];
    private int $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        if (!\Illuminate\Support\Facades\Schema::hasTable('order_demands')) {
            $this->markTestSkipped('inventory_v2 migrations are not applied.');
        }
        putenv('INVENTORY_CONFIRMED_ONLY=true');
        $_ENV['INVENTORY_CONFIRMED_ONLY'] = $_SERVER['INVENTORY_CONFIRMED_ONLY'] = 'true';
        config(['inventory.confirmed_only' => true]);
        InventoryMode::reset();
        foreach (self::EXTRA as $t) {
            $this->extraHigh[$t] = (int) DB::table($t)->max('id');
        }
        $this->balances = DB::table('chart_of_accounts')->pluck('current_balance', 'id')->all();
        $this->supplier = DB::table('suppliers')->insertGetId(['slug' => 'c-' . Str::random(6), 'business_name' => 'Conc', 'contact_person_name' => 'C', 'mobile' => '017' . random_int(10000000, 99999999), 'opening_balance' => '0', 'due_amount' => 0, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        putenv('INVENTORY_CONFIRMED_ONLY');
        unset($_ENV['INVENTORY_CONFIRMED_ONLY'], $_SERVER['INVENTORY_CONFIRMED_ONLY']);
        config(['inventory.confirmed_only' => false]);
        InventoryMode::reset();
        foreach (array_reverse(self::EXTRA) as $t) {
            DB::table($t)->where('id', '>', $this->extraHigh[$t] ?? PHP_INT_MAX)->delete();
        }
        foreach ($this->balances as $id => $bal) {
            DB::table('chart_of_accounts')->where('id', $id)->update(['current_balance' => $bal]);
        }
        DB::table('stock_balances')->where('id', '>', $this->extraHigh['stock_balances'] ?? PHP_INT_MAX)->delete();
        parent::tearDown();
    }

    /** product at zero stock with a backorder policy, one stock row */
    private function zeroProduct(): int
    {
        $id = $this->seedProduct(['M' => 0], 1000, 400, []); // helper creates a row; no layers when qty 0
        DB::table('products')->where('id', $id)->update(['stock_policy' => 'backorder']);
        DB::table('purchase_products')->where('product_id', $id)->delete();
        DB::table('product_stocks')->where('product_id', $id)->update(['qty' => 0]);

        return $id;
    }

    private function order(int $productId, int $qty, string $status = 'pending'): array
    {
        $u = DB::table('users')->insertGetId(['name' => 'c', 'email' => Str::random(8) . '@c.test', 'password' => 'x', 'user_type' => 'customer', 'created_at' => now(), 'updated_at' => now()]);
        $o = new Order();
        $o->forceFill(['user_id' => $u, 'seller_id' => 1, 'code' => Order::generateUniqueCode(), 'delivery_status' => 'pending', 'payment_status' => 'unpaid', 'payment_type' => 'cash_on_delivery', 'shipping_address' => '{}', 'grand_total' => 0, 'date' => time(), 'order_from' => 'app'])->save();
        $d = new OrderDetail();
        $d->forceFill(['order_id' => $o->id, 'seller_id' => 1, 'product_id' => $productId, 'variation' => 'M', 'quantity' => $qty, 'price' => 1000 * $qty, 'selling_price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'buying_price' => 0, 'profit' => 0, 'delivery_status' => 'pending'])->save();
        if ($status !== 'pending') {
            $o->delivery_status = $status;
            $o->save();
        }

        return [$o->id, $d->id];
    }

    private function snap(int $productId): array
    {
        return \App\Services\Inventory\StockBalance::snapshot($productId, 'M');
    }

    public function test_eight_admins_confirming_the_same_order_create_its_demand_once()
    {
        $p = $this->zeroProduct();
        [$oid] = $this->order($p, 5);
        $r = $this->race(array_fill(0, 8, ['action' => 'v2_status', 'order_id' => $oid, 'status' => 'confirmed']));
        $this->assertSame(8, $this->tally($r)['ok'], json_encode($r));
        $this->assertSame(1, DB::table('order_demands')->where('order_id', $oid)->count());
        $this->assertSame(5, $this->snap($p)['demand']);
        $this->assertSame(1, DB::table('stock_movements')->where('type', 'demand_confirmed')->where('product_id', $p)->count());
        $this->assertSame([], app(InventoryReconciliation::class)->stockInvariantViolations());
    }

    public function test_confirm_and_cancel_racing_end_in_a_consistent_state()
    {
        $p = $this->zeroProduct();
        [$oid] = $this->order($p, 4);
        $r = $this->race([['action' => 'v2_status', 'order_id' => $oid, 'status' => 'confirmed'], ['action' => 'v2_status', 'order_id' => $oid, 'status' => 'cancelled'],
            ['action' => 'v2_status', 'order_id' => $oid, 'status' => 'confirmed'], ['action' => 'v2_status', 'order_id' => $oid, 'status' => 'cancelled']]);
        $this->assertSame([], app(InventoryReconciliation::class)->stockInvariantViolations(), json_encode($r));
        $s = $this->snap($p);
        $this->assertContains($s['demand'], [0, 4], 'demand is either fully held or fully released, never half');
        $this->assertSame(0, $s['on_hand']);
        $this->assertSame(DB::table('stock_balances')->where('product_id', $p)->value('demand'), (int) DB::table('order_demands')->where('product_id', $p)->where('status', 'open')->sum(DB::raw('qty_confirmed - qty_dispatched - qty_cancelled')));
    }

    public function test_the_same_goods_receipt_submitted_six_times_adds_stock_once()
    {
        $p = $this->zeroProduct();
        $data = ['supplier_id' => $this->supplier, 'lines' => [['product_id' => $p, 'variant' => 'M', 'qty' => 5, 'unit_cost' => '90']]];
        $r = $this->race(array_fill(0, 6, ['action' => 'v2_receive', 'key' => 'conc-grn-' . $p, 'data' => $data]));
        $this->assertSame(6, $this->tally($r)['ok'], json_encode($r));
        $this->assertSame(1, DB::table('goods_receipts')->where('idempotency_key', 'conc-grn-' . $p)->count());
        $this->assertSame(5, $this->stock($p, 'M'));
        $this->assertSame(5, $this->batchSum($p, 'M'));
        $this->assertSame(1, DB::table('journal_entries')->where('idempotency_key', 'grn:conc-grn-' . $p)->count());
    }

    public function test_receipts_and_confirmations_racing_never_double_allocate_or_break_the_invariants()
    {
        $p = $this->zeroProduct();
        $orders = [];
        for ($i = 0; $i < 4; $i++) {
            $orders[] = $this->order($p, 2)[0];
        }
        $jobs = [];
        foreach ($orders as $oid) {
            $jobs[] = ['action' => 'v2_status', 'order_id' => $oid, 'status' => 'confirmed'];
        }
        foreach ([3, 3] as $i => $qty) {
            $jobs[] = ['action' => 'v2_receive', 'key' => "conc-mix-{$p}-{$i}", 'data' => ['supplier_id' => $this->supplier, 'lines' => [['product_id' => $p, 'variant' => 'M', 'qty' => $qty, 'unit_cost' => '50']]]];
        }
        $r = $this->race($jobs);
        $this->assertSame(6, $this->tally($r)['ok'], json_encode($r));
        $s = $this->snap($p);
        $this->assertSame([6, 8, 6, -2], [$s['on_hand'], $s['demand'], $s['allocated'], $s['available']], 'physical 6, demand 8, all 6 physical units allocated exactly once');
        $this->assertSame([], app(InventoryReconciliation::class)->stockInvariantViolations());
    }

    public function test_two_orders_dispatching_the_last_unit_at_once_only_one_ships()
    {
        $p = $this->zeroProduct();
        DB::table('product_stocks')->where('product_id', $p)->update(['qty' => 1]);
        DB::table('purchase_products')->insert(['purchase_id' => 0, 'product_id' => $p, 'product_name' => 'x', 'variant' => 'M', 'purchase_quantity' => '1', 'remaining_qty' => 1, 'buying_unit_cost' => '10', 'total_cost' => '10', 'product_tax' => '0', 'created_at' => now(), 'updated_at' => now()]);
        [$a] = $this->order($p, 1, 'confirmed');
        [$b] = $this->order($p, 1, 'confirmed');
        $r = $this->race([['action' => 'v2_status', 'order_id' => $a, 'status' => 'picked_up'], ['action' => 'v2_status', 'order_id' => $b, 'status' => 'picked_up']]);
        $t = $this->tally($r);
        $this->assertSame([1, 1], [$t['ok'], $t['refused']], json_encode($r));
        $this->assertSame(0, $this->stock($p, 'M'), 'physical stock never negative');
        $this->assertSame(0, $this->batchSum($p, 'M'), 'the cost layer was consumed once');
        $this->assertSame([], app(InventoryReconciliation::class)->stockInvariantViolations());
    }

    public function test_the_same_supplier_payment_submitted_four_times_pays_once()
    {
        $p = $this->zeroProduct();
        $cash = DB::table('chart_of_accounts')->insertGetId(['code' => 'CC-' . Str::random(6), 'name' => 'Conc cash ' . Str::random(4), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 5000, 'status' => 'active', 'is_deleted' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->balances[$cash] = 5000;
        $rec = app(\App\Services\Purchasing\GoodsReceiptService::class)->receive('conc-pay-grn-' . $p, ['supplier_id' => $this->supplier, 'lines' => [['product_id' => $p, 'variant' => 'M', 'qty' => 2, 'unit_cost' => '100']]]);
        $inv = app(\App\Services\Purchasing\SupplierLedgerService::class)->recordInvoice('conc-inv-' . $p, ['supplier_id' => $this->supplier, 'goods_receipt_id' => $rec['goods_receipt_id'], 'invoice_no' => 'CI-' . $p, 'subtotal' => '200'])['supplier_invoice_id'];
        $r = $this->race(array_fill(0, 4, ['action' => 'v2_pay', 'key' => 'conc-pay-' . $p, 'invoice_id' => $inv, 'account_id' => $cash, 'amount' => '120']));
        $this->assertSame(4, $this->tally($r)['ok'], json_encode($r));
        $this->assertEquals(4880.0, (float) DB::table('chart_of_accounts')->where('id', $cash)->value('current_balance'), 'paid once');
        $this->assertEquals(80.0, (float) DB::table('suppliers')->where('id', $this->supplier)->value('due_amount'));
        $this->assertSame(1, DB::table('supplier_invoice_payments')->where('supplier_invoice_id', $inv)->count());
    }

    public function test_different_payments_racing_cannot_overpay_an_invoice()
    {
        $p = $this->zeroProduct();
        $cash = DB::table('chart_of_accounts')->insertGetId(['code' => 'CD-' . Str::random(6), 'name' => 'Conc cash ' . Str::random(4), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 5000, 'status' => 'active', 'is_deleted' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $rec = app(\App\Services\Purchasing\GoodsReceiptService::class)->receive('conc-op-grn-' . $p, ['supplier_id' => $this->supplier, 'lines' => [['product_id' => $p, 'variant' => 'M', 'qty' => 1, 'unit_cost' => '100']]]);
        $inv = app(\App\Services\Purchasing\SupplierLedgerService::class)->recordInvoice('conc-op-inv-' . $p, ['supplier_id' => $this->supplier, 'goods_receipt_id' => $rec['goods_receipt_id'], 'invoice_no' => 'OP-' . $p, 'subtotal' => '100'])['supplier_invoice_id'];
        $jobs = [];
        for ($i = 0; $i < 5; $i++) {
            $jobs[] = ['action' => 'v2_pay', 'key' => "conc-op-{$p}-{$i}", 'invoice_id' => $inv, 'account_id' => $cash, 'amount' => '60'];
        }
        $r = $this->race($jobs);
        $this->assertSame(1, $this->tally($r)['ok'], 'only one 60 fits into the 100 owed: ' . json_encode($this->tally($r)));
        $this->assertEquals(60.0, (float) DB::table('supplier_invoices')->where('id', $inv)->value('paid'));
        $this->assertEquals(4940.0, (float) DB::table('chart_of_accounts')->where('id', $cash)->value('current_balance'));
    }

    public function test_parallel_journal_postings_keep_every_balance_exact()
    {
        $a = DB::table('chart_of_accounts')->insertGetId(['code' => 'JA-' . Str::random(6), 'name' => 'JA ' . Str::random(4), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $b = DB::table('chart_of_accounts')->insertGetId(['code' => 'JB-' . Str::random(6), 'name' => 'JB ' . Str::random(4), 'type' => 'liability', 'sub_type' => 'payable', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $jobs = [];
        for ($i = 0; $i < 10; $i++) {
            $jobs[] = ['action' => 'v2_journal', 'key' => 'conc-j-' . $a . '-' . $i, 'lines' => $i % 2 ? [['account' => $a, 'debit' => 1000], ['account' => $b, 'credit' => 1000]] : [['account' => $b, 'credit' => 1000], ['account' => $a, 'debit' => 1000]]];
        }
        $r = $this->race($jobs);
        $this->assertSame(10, $this->tally($r)['ok'], json_encode($r));
        $this->assertEquals(100.0, (float) DB::table('chart_of_accounts')->where('id', $a)->value('current_balance'));
        $this->assertEquals(100.0, (float) DB::table('chart_of_accounts')->where('id', $b)->value('current_balance'));
    }
}
