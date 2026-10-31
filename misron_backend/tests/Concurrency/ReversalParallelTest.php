<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;

/**
 * T-5 F-01: a reversal/delete that is submitted several times at once (double click, two tabs, a retried request) must change the books ONCE.
 * Real processes; each document is locked first and re-read under the lock, so a repeat finds it gone and gets the refusal a sequential repeat gets today.
 */
class ReversalParallelTest extends HttpWorkflowCase
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

    private function supplier(): int
    {
        return (int) DB::table('suppliers')->insertGetId(['slug' => 'rv-' . uniqid(), 'business_name' => 'RV', 'contact_person_name' => 'C', 'mobile' => '017' . random_int(10000000, 99999999), 'opening_balance' => '0', 'due_amount' => 0, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function purchaseOn(int $supplier, int $admin, int $cash, int $paid = 40): array
    {
        $p = $this->seedProduct(['M' => 0], 1000, 100);
        DB::table('purchase_products')->where('product_id', $p)->delete();
        $r = $this->runOne($this->req($admin, '/purchase/store', ['op_key' => 'rv-' . uniqid(), 'supplier_id' => $supplier, 'purchase_date' => now()->toDateString(), 'product_id' => [$p], 'name' => ['x'], 'variant' => ['M'],
            'purchase_quantity' => [5], 'buying_unit_cost' => [20], 'total_cost' => [100], 'pay_by' => 'cash', 'payment_account_id' => $cash, 'paid_amount' => $paid]));
        $this->assertTrue($r['body']['success'] ?? false, json_encode($r));

        return [(int) DB::table('purchases')->where('supplier_id', $supplier)->value('id'), $p];
    }

    private function ok(array $results): int
    {
        return count(array_filter($results, fn ($r) => ($r['body']['success'] ?? null) === true || (($r['status'] ?? 0) === 302 && !str_contains(json_encode($r['body'] ?? ''), 'rror'))));
    }

    public function test_unsettle_sent_three_times_at_once_reverses_the_settlement_once()
    {
        $this->flag(true);
        $p = $this->seedProduct(['M' => 5], 1000, 100);
        $order = $this->deliveredOrder($p);
        $cash = $this->cashAccount(0);
        $admin = $this->admin();
        $r = $this->runOne($this->req($admin, '/courier-settlement/settle', ['order_id' => $order, 'courier_fee' => 60, 'account_id' => $cash, 'courier_name' => 'steadfast', 'settlement_fee' => 0, 'due_amount' => 5000, 'settlement_charge' => 0]));
        $this->assertTrue($r['body']['success'] ?? false, json_encode($r));
        $settlement = (int) DB::table('courier_settlements')->where('order_id', $order)->value('id');
        $this->assertEquals(4940.0, $this->bal($cash));
        $cogs = (int) \App\Models\ChartOfAccount::where('type', 'expense')->where('name', 'Cost of Goods Sold')->first()->id;
        $cogsBefore = $this->bal($cogs);

        $res = $this->race(array_map(fn () => $this->req($this->admin(), '/courier-settlement/unsettle', ['settlement_id' => $settlement]), range(1, 3)), 120);

        $this->assertEquals(0.0, $this->bal($cash), 'one reversal: the 4940 settled is taken back once, not three times: ' . json_encode(array_column($res, 'body')));
        $this->assertEquals(-500.0, $this->bal($cogs) - $cogsBefore, 'COGS reversed once');
        $this->assertSame(0, DB::table('courier_settlements')->where('order_id', $order)->count());
        $this->assertSame(1, count(array_filter($res, fn ($x) => ($x['body']['success'] ?? false) === true)), 'exactly one request reversed it');
        $this->assertJournalSettles();
    }

    public function test_supplier_payment_deleted_three_times_at_once_is_reversed_once()
    {
        $this->flag(true);
        $s = $this->supplier();
        $admin = $this->admin();
        $cash = $this->cashAccount(1000);
        [$purchase] = $this->purchaseOn($s, $admin, $cash);
        $r = $this->runOne($this->req($admin, '/admin/accounts/supplier-payments/store', ['supplier_id' => $s, 'payment_date' => now()->toDateString(), 'amount' => 30, 'payment_method' => 'CASH', 'account_head_id' => $cash, 'allocations' => json_encode([['purchase_id' => $purchase, 'paid_amount' => 30]])]));
        $this->assertTrue($r['body']['success'] ?? false, json_encode($r));
        $pay = (int) DB::table('supplier_payments')->where('supplier_id', $s)->value('id');
        $this->assertEquals(930.0, $this->bal($cash));

        $res = $this->race(array_map(fn () => $this->req($this->admin(), '/admin/accounts/supplier-payments/delete/' . $pay, [], 'DELETE'), range(1, 3)), 120);

        $this->assertEquals(960.0, $this->bal($cash), 'cash +30 once (probe before the fix: 1020)');
        $this->assertEquals(60.0, (float) DB::table('suppliers')->where('id', $s)->value('due_amount'), 'supplier due +30 once (probe before the fix: 120)');
        $this->assertEquals(60.0, (float) DB::table('purchases')->where('id', $purchase)->value('total_payment_due'));
        $this->assertSame(0, DB::table('supplier_payments')->where('supplier_id', $s)->count());
        $this->assertSame(1, count(array_filter($res, fn ($x) => ($x['body']['success'] ?? false) === true)), json_encode(array_column($res, 'body')));
        $this->assertJournalSettles();
    }

    public function test_purchase_deleted_three_times_at_once_is_reversed_once()
    {
        $this->flag(true);
        $s = $this->supplier();
        $admin = $this->admin();
        $cash = $this->cashAccount(1000);
        [$purchase, $p] = $this->purchaseOn($s, $admin, $cash);
        $this->assertEquals(960.0, $this->bal($cash));
        $payable = (int) \App\Models\ChartOfAccount::getAccountsPayable()->id;
        $payableBefore = $this->bal($payable);

        $this->race(array_map(fn () => $this->req($this->admin(), '/purchase/delete/' . $purchase, [], 'DELETE'), range(1, 3)), 120);

        $this->assertSame(0, DB::table('purchases')->where('id', $purchase)->count());
        $this->assertEquals(1000.0, $this->bal($cash), 'the 40 paid comes back once');
        $this->assertEquals(-60.0, $this->bal($payable) - $payableBefore, 'Accounts Payable reduced once by the open 60');
        $this->assertSame(0, (int) DB::table('product_stocks')->where('product_id', $p)->sum('qty'));
        $this->assertSame(0, DB::table('purchase_products')->where('product_id', $p)->count());
        $this->assertJournalSettles();
    }

    public function test_income_and_balance_transfer_deleted_three_times_at_once_are_reversed_once()
    {
        $this->flag(true);
        $admin = $this->admin();
        $cash = $this->cashAccount(0);
        $incomeAcc = $this->account('income', 'income', 0);
        $this->runOne($this->req($admin, route('accounts.incomes.store', [], false), ['date' => now()->toDateString(), 'received_account_id' => $cash, 'income_account_ids' => [$incomeAcc], 'amounts' => [250]]));
        $incomeId = (int) DB::table('incomes')->where('received_account_id', $cash)->value('id');
        $this->assertEquals(250.0, $this->bal($cash));
        $this->race(array_map(fn () => $this->req($this->admin(), route('accounts.incomes.delete', $incomeId, false), [], 'DELETE'), range(1, 3)), 120);
        $this->assertEquals(0.0, $this->bal($cash), 'income reversed once');
        $this->assertEquals(0.0, $this->bal($incomeAcc));

        $from = $this->cashAccount(500);
        $to = $this->cashAccount(0);
        $this->runOne($this->req($admin, route('accounts.balance-transfers.store', [], false), ['transaction_type' => 'transfer', 'date' => now()->toDateString(), 'from_account_id' => $from, 'to_account_id' => $to, 'amount' => 100, 'charge' => 0]));
        $tid = (int) DB::table('balance_transfers')->where('from_account_id', $from)->value('id');
        $this->assertEquals(400.0, $this->bal($from));
        $this->race(array_map(fn () => $this->req($this->admin(), route('accounts.balance-transfers.delete', $tid, false), [], 'DELETE'), range(1, 3)), 120);
        $this->assertEquals(500.0, $this->bal($from), 'transfer reversed once');
        $this->assertEquals(0.0, $this->bal($to));
        $this->assertJournalSettles();
    }
}
