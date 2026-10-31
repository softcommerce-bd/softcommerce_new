<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;

/**
 * T-5: REAL deadlocks (not injected) on the admin screens. A helper process holds a row and then wants another that the request already holds,
 * while the request, paused on purpose after taking its first lock, wants the helper's row: the database kills one of them. When the request is the
 * victim, the middleware must retry it (the response carries X-Deadlock-Retries) and everything must exist exactly once.
 */
class RealDeadlockHttpTest extends HttpWorkflowCase
{
    private function deliveredOrder(int $product): int
    {
        $this->systemAccounts();
        $purchaseId = (int) DB::table('purchase_products')->where('product_id', $product)->value('purchase_id');
        $u = DB::table('users')->insertGetId(['name' => 'c', 'email' => uniqid() . '@c.test', 'password' => 'x', 'user_type' => 'customer', 'created_at' => now(), 'updated_at' => now()]);
        $o = new \App\Models\Order();
        $o->forceFill(['user_id' => $u, 'seller_id' => 1, 'code' => \App\Models\Order::generateUniqueCode(), 'delivery_status' => 'picked_up', 'payment_status' => 'unpaid', 'payment_type' => 'cash_on_delivery', 'shipping_address' => '{}', 'grand_total' => 5000, 'due_amount' => 5000,
            'courier_status' => 'delivered', 'courier_name' => 'steadfast', 'date' => time(), 'order_from' => 'app'])->save();
        (new \App\Models\OrderDetail())->forceFill(['order_id' => $o->id, 'seller_id' => 1, 'product_id' => $product, 'variation' => 'M', 'quantity' => 5, 'price' => 5000, 'selling_price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'buying_price' => 100, 'profit' => 0,
            'delivery_status' => 'picked_up', 'purchase_id' => $purchaseId, 'stock_mode' => 'legacy'])->save();

        return $o->id;
    }

    public function test_courier_settlement_that_is_a_real_deadlock_victim_is_retried_and_settles_once()
    {
        $this->flag(true);
        $p = $this->seedProduct(['M' => 5], 1000, 100);
        $order = $this->deliveredOrder($p);
        $cash = $this->cashAccount(0);
        $cogs = (int) (\App\Models\ChartOfAccount::where('type', 'expense')->where('name', 'Cost of Goods Sold')->first() ?? \App\Models\ChartOfAccount::where('sub_type', 'cost_of_sales')->first())->id;
        $cogsBefore = $this->bal($cogs);

        $settle = $this->req($this->admin(), '/courier-settlement/settle', ['order_id' => $order, 'courier_fee' => 60, 'account_id' => $cash, 'courier_name' => 'steadfast', 'settlement_fee' => 0, 'due_amount' => 5000, 'settlement_charge' => 0]);
        $settle['pause_after'] = ['regex' => '/from `orders` where `orders`\.`id` = \? .*for update/i', 'ms' => 1500];
        $helper = ['action' => 'lock_then_lock', 'first' => ['table' => 'chart_of_accounts', 'id' => $cash], 'second' => ['table' => 'orders', 'id' => $order], 'hold_ms' => 700, 'touch' => $this->heavy()];

        $r = $this->race([$settle, $helper], 120);

        $this->assertTrue($r[0]['body']['success'] ?? false, 'the settlement went through in the end: ' . json_encode($r));
        $deadlocked = !empty($r[0]['headers']['X-Deadlock-Retries']);
        $this->assertTrue($deadlocked, 'the request was the real deadlock victim and was retried by the middleware: ' . json_encode($r));
        $this->assertTrue($r[1]['ok'] ?? false, 'the helper completed once the request had rolled back: ' . json_encode($r[1]));
        $this->assertSame(1, DB::table('courier_settlements')->where('order_id', $order)->count(), 'one settlement');
        $this->assertEquals(5000 - 60, $this->bal($cash), 'credited once');
        $this->assertEquals(500.0, $this->bal($cogs) - $cogsBefore, 'COGS booked once');
        $this->assertSame(1, DB::table('account_statement_logs')->where('order_id', $order)->where('transaction_type', 'courier_settlement')->where('account_id', $cash)->count());
        $this->assertJournalSettles();
        fwrite(STDERR, "\nREAL-DEADLOCK courier: request retried=" . ($deadlocked ? json_encode($r[0]['headers']) : 'no (helper was the victim)') . "\n");
    }

    private function heavy(): array
    {
        return array_map(fn () => $this->cashAccount(0), range(1, 80));
    }

    public function test_legacy_purchase_that_is_a_real_deadlock_victim_is_retried_and_exists_once()
    {
        $this->flag(true);
        $supplier = (int) DB::table('suppliers')->insertGetId(['slug' => 'rd-' . uniqid(), 'business_name' => 'RD', 'contact_person_name' => 'C', 'mobile' => '017' . random_int(10000000, 99999999), 'opening_balance' => '0', 'due_amount' => 0, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $p = $this->seedProduct(['M' => 0], 1000, 100);
        DB::table('purchase_products')->where('product_id', $p)->delete();
        $cash = $this->cashAccount(1000);
        $req = $this->req($this->admin(), '/purchase/store', ['op_key' => 'rd-' . uniqid(), 'supplier_id' => $supplier, 'purchase_date' => now()->toDateString(), 'product_id' => [$p], 'name' => ['x'], 'variant' => ['M'],
            'purchase_quantity' => [5], 'buying_unit_cost' => [20], 'total_cost' => [100], 'pay_by' => 'cash', 'payment_account_id' => $cash, 'paid_amount' => 40]);
        $req['pause_after'] = ['regex' => '/from `suppliers` where `suppliers`\.`id` = \? .*for update/i', 'ms' => 1500];
        $helper = ['action' => 'lock_then_lock', 'first' => ['table' => 'chart_of_accounts', 'id' => $cash], 'second' => ['table' => 'suppliers', 'id' => $supplier], 'hold_ms' => 700, 'touch' => $this->heavy()];

        $r = $this->race([$req, $helper], 120);

        $this->assertTrue($r[0]['body']['success'] ?? false, json_encode($r));
        $this->assertNotEmpty($r[0]['headers']['X-Deadlock-Retries'] ?? null, 'the request was the real deadlock victim and was retried: ' . json_encode($r));
        $this->assertTrue($r[1]['ok'] ?? false, json_encode($r[1]));
        $this->assertSame(1, DB::table('purchases')->where('supplier_id', $supplier)->count(), 'one purchase');
        $this->assertSame(1, DB::table('purchase_products')->where('product_id', $p)->count(), 'one FIFO layer');
        $this->assertSame(5, (int) DB::table('product_stocks')->where('product_id', $p)->sum('qty'));
        $this->assertEquals(60.0, (float) DB::table('suppliers')->where('id', $supplier)->value('due_amount'), 'supplier due once');
        $this->assertEquals(960.0, $this->bal($cash), 'cash paid once');
        $this->assertSame(1, DB::table('idempotency_keys')->where('scope', 'legacy_purchase')->count(), 'one double-submit key');
        $this->assertJournalSettles();
        fwrite(STDERR, "\nREAL-DEADLOCK purchase: " . json_encode($r[0]['headers']) . "\n");
    }

    public function test_admin_create_order_that_is_a_real_deadlock_victim_is_retried_and_takes_stock_once()
    {
        $this->flag(false);
        $p = $this->seedProduct(['M' => 10], 1000, 400);
        $admin = $this->admin();
        DB::table('carts')->insert(['user_id' => $admin, 'product_id' => $p, 'variation' => 'M', 'quantity' => 2, 'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0, 'shipping_type' => 'home_delivery', 'created_at' => now(), 'updated_at' => now()]);
        $cartId = (int) DB::table('carts')->where('user_id', $admin)->value('id');
        $stockId = (int) DB::table('product_stocks')->where('product_id', $p)->value('id');
        $address = (int) DB::table('addresses')->insertGetId(['user_id' => $admin, 'address' => 'H', 'phone' => '01700000001', 'postal_code' => '1200', 'country_id' => 18, 'state_id' => 0, 'city_id' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $req = $this->req($admin, '/admin/orders', ['address_id' => $address, 'payment_option' => 'cash_on_delivery', 'shipping_cost_amount' => 0]);
        $req['pause_after'] = ['regex' => '/from `carts` where `user_id` = \? .*for update/i', 'ms' => 1500];
        $helper = ['action' => 'lock_then_lock', 'first' => ['table' => 'product_stocks', 'id' => $stockId], 'second' => ['table' => 'carts', 'id' => $cartId], 'hold_ms' => 700, 'touch' => $this->heavy()];

        $r = $this->race([$req, $helper], 120);

        $this->assertNotEmpty($r[0]['headers']['X-Deadlock-Retries'] ?? null, 'the request was the real deadlock victim and was retried: ' . json_encode($r));
        $this->assertTrue($r[1]['ok'] ?? false, json_encode($r[1]));
        $this->assertSame(1, DB::table('orders')->where('user_id', $admin)->count(), 'one order');
        $this->assertSame(8, (int) DB::table('product_stocks')->where('product_id', $p)->value('qty'), 'two units taken once');
        $this->assertSame(8, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'), 'FIFO layers fell by the same two');
        $this->assertSame(1, DB::table('order_details')->where('product_id', $p)->count());
        fwrite(STDERR, "\nREAL-DEADLOCK admin order: " . json_encode($r[0]['headers']) . "\n");
    }
}
