<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;

/** T-5: real parallel courier settlements as authenticated admins. */
class CourierSettlementParallelTest extends HttpWorkflowCase
{
    /** a delivered, unsettled order whose single line was sold from a legacy FIFO batch */
    private function deliveredOrder(int $product, int $due = 5000): int
    {
        $this->systemAccounts();
        $purchaseId = (int) DB::table('purchase_products')->where('product_id', $product)->value('purchase_id');
        $u = DB::table('users')->insertGetId(['name' => 'c', 'email' => uniqid() . '@c.test', 'password' => 'x', 'user_type' => 'customer', 'created_at' => now(), 'updated_at' => now()]);
        $o = new \App\Models\Order();
        $o->forceFill(['user_id' => $u, 'seller_id' => 1, 'code' => \App\Models\Order::generateUniqueCode(), 'delivery_status' => 'picked_up', 'payment_status' => 'unpaid', 'payment_type' => 'cash_on_delivery', 'shipping_address' => '{}', 'grand_total' => $due, 'due_amount' => $due,
            'courier_status' => 'delivered', 'courier_name' => 'steadfast', 'date' => time(), 'order_from' => 'app'])->save();
        (new \App\Models\OrderDetail())->forceFill(['order_id' => $o->id, 'seller_id' => 1, 'product_id' => $product, 'variation' => 'M', 'quantity' => 5, 'price' => $due, 'selling_price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'buying_price' => 100, 'profit' => 0,
            'delivery_status' => 'picked_up', 'purchase_id' => $purchaseId, 'stock_mode' => 'legacy'])->save();

        return $o->id;
    }

    private function body(int $order, int $account, int $due = 5000): array
    {
        return ['order_id' => $order, 'courier_fee' => 60, 'account_id' => $account, 'courier_name' => 'steadfast', 'settlement_fee' => 0, 'due_amount' => $due, 'settlement_charge' => 0];
    }

    public function test_the_same_order_settled_by_four_admins_at_once_is_settled_exactly_once()
    {
        $this->flag(true);
        $p = $this->seedProduct(['M' => 5], 1000, 100);
        $order = $this->deliveredOrder($p);
        $cash = $this->cashAccount(0);
        $cogs = (int) (\App\Models\ChartOfAccount::where('type', 'expense')->where('name', 'Cost of Goods Sold')->first() ?? \App\Models\ChartOfAccount::where('sub_type', 'cost_of_sales')->first())->id;
        $inv = (int) \App\Models\ChartOfAccount::getInventoryAccount()->id;
        $cogsBefore = $this->bal($cogs);
        $invBefore = $this->bal($inv);
        $admins = [$this->admin(), $this->admin(), $this->admin(), $this->admin()];

        $r = $this->race(array_map(fn ($a) => $this->req($a, '/courier-settlement/settle', $this->body($order, $cash)), $admins), 120);

        $ok = array_values(array_filter($r, fn ($x) => ($x['body']['success'] ?? false) === true));
        $this->assertCount(1, $ok, 'exactly one request settled it: ' . json_encode($r));
        $this->assertSame(1, DB::table('courier_settlements')->where('order_id', $order)->count(), 'one settlement record');
        $this->assertSame(1, DB::table('courier_settlement_details')->whereIn('courier_settlement_id', DB::table('courier_settlements')->where('order_id', $order)->pluck('id'))->count(), 'one detail line');
        $this->assertEquals(5000 - 60, $this->bal($cash), 'the account was credited once');
        $this->assertEquals(500.0, $this->bal($cogs) - $cogsBefore, 'COGS booked once (5 x 100)');
        $this->assertEquals(-500.0, $this->bal($inv) - $invBefore, 'inventory relieved once');
        $this->assertSame(1, DB::table('account_statement_logs')->where('order_id', $order)->where('transaction_type', 'courier_settlement')->where('account_id', $cash)->count());
        $this->assertSame(0, DB::table('ledger_outbox')->whereNull('processed_at')->count(), 'no posting left waiting');
    }

    public function test_different_orders_settled_at_once_into_one_account_add_up_exactly()
    {
        $this->flag(true);
        $p = $this->seedProduct(['M' => 20], 1000, 100);
        $cash = $this->cashAccount(0);
        $orders = [$this->deliveredOrder($p), $this->deliveredOrder($p), $this->deliveredOrder($p), $this->deliveredOrder($p)];
        $cogs = (int) (\App\Models\ChartOfAccount::where('type', 'expense')->where('name', 'Cost of Goods Sold')->first() ?? \App\Models\ChartOfAccount::where('sub_type', 'cost_of_sales')->first())->id;
        $cogsBefore = $this->bal($cogs);

        $r = $this->race(array_map(fn ($o) => $this->req($this->admin(), '/courier-settlement/settle', $this->body($o, $cash)), $orders), 120);

        $this->assertSame(4, count(array_filter($r, fn ($x) => ($x['body']['success'] ?? false) === true)), json_encode($r));
        $this->assertSame(4, DB::table('courier_settlements')->whereIn('order_id', $orders)->count());
        $this->assertEquals(4 * (5000 - 60), $this->bal($cash), 'no lost update on the shared account');
        $this->assertEquals(4 * 500.0, $this->bal($cogs) - $cogsBefore, 'no lost update on COGS');
        $this->assertSame(0, DB::table('ledger_outbox')->whereNull('processed_at')->count());
        $this->assertTrue(\App\Services\Accounting\JournalService::trialBalance(now()->toDateString())['balanced']);
    }
}
