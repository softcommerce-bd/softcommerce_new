<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

/** T-5 F-03 (behaviour): a refused request leaves the connection at the transaction level it started at, so its locks are released. */
class TransactionExitTest extends InventoryV2TestCase
{
    public function test_a_refused_courier_settlement_leaves_no_transaction_open()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder']);
        $order = $this->makeOrder([[$p, '', 5]], 'confirmed');
        $this->receive($p, '', 5, '100');
        $this->setStatus($order, 'picked_up');
        $cash = ChartOfAccount::create(['code' => 'TX-' . uniqid(), 'name' => 'TX cash ' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0])->id;
        foreach ([['Sales', 'income', 'sales'], ['Accounts Receivable', 'asset', 'receivable'], ['Courier Income', 'income', 'income'], ['Cost of Goods Sold', 'expense', 'cost_of_sales']] as [$n, $t, $s]) {
            ChartOfAccount::firstOrCreate(['name' => $n], ['code' => 'TX-' . strtoupper(substr($n, 0, 4)) . uniqid(), 'type' => $t, 'sub_type' => $s, 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0]);
        }
        DB::table('orders')->where('id', $order->id)->update(['courier_status' => 'delivered', 'courier_name' => 'steadfast', 'due_amount' => 5000, 'grand_total' => 5000]);
        $this->forgetAuth();
        $this->actingAs($this->makeAdmin());
        $body = ['order_id' => $order->id, 'courier_fee' => 60, 'account_id' => $cash, 'courier_name' => 'steadfast', 'settlement_fee' => 0, 'due_amount' => 5000, 'settlement_charge' => 0];

        $this->postJson(route('courier-settlement.settle'), $body)->assertJson(['success' => true]);
        $level = DB::transactionLevel();
        $second = $this->postJson(route('courier-settlement.settle'), $body);
        $second->assertJson(['success' => false]);
        $this->assertSame($level, DB::transactionLevel(), 'the refusal ("already settled") rolled back its transaction instead of leaving it open');
    }

    public function test_a_refused_voucher_leaves_no_transaction_open()
    {
        $this->actingAs($this->makeAdmin());
        $a = ChartOfAccount::create(['code' => 'TV-' . uniqid(), 'name' => 'TV a ' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0])->id;
        $b = ChartOfAccount::create(['code' => 'TV-' . uniqid(), 'name' => 'TV b ' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0])->id;
        $level = DB::transactionLevel();
        $res = $this->post(route('accounts.vouchers.store'), ['date' => now()->toDateString(), 'type' => 'JV', 'account_ids' => [$a, $b], 'debits' => [100, 0], 'credits' => [0, 90]]);
        $res->assertRedirect();
        $this->assertStringContainsString('Total debit must equal total credit', (string) session('error'), "today's message is unchanged");
        $this->assertSame($level, DB::transactionLevel());
    }
}
