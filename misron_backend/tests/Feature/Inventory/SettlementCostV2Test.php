<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use App\Services\Accounting\SystemAccounts;
use Illuminate\Support\Facades\DB;

/** Courier settlement (where non-POS sales, COGS and the inventory credit are recognised) with a confirmed-order line. */
class SettlementCostV2Test extends InventoryV2TestCase
{
    public function test_settlement_books_cogs_at_the_exact_fifo_cost_recorded_at_dispatch_across_layers()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder']);
        $order = $this->makeOrder([[$p, '', 5]], 'confirmed');
        $this->receive($p, '', 3, '100');
        $this->receive($p, '', 2, '120');
        $this->setStatus($order, 'picked_up');
        $line = $this->detail($order);
        $this->assertEquals(108.0, (float) $line->buying_price, '(3 x 100 + 2 x 120) / 5');

        $inv = SystemAccounts::id('inventory');
        $before = (float) DB::table('chart_of_accounts')->where('id', $inv)->value('current_balance'); // 540 from the two receipts
        $this->assertEquals(540.0, $before);
        $cash = ChartOfAccount::create(['code' => 'S-CASH', 'name' => 'Settle cash', 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0])->id;
        foreach ([['Sales', 'income', 'sales'], ['Accounts Receivable', 'asset', 'receivable'], ['Courier Income', 'income', 'income'], ['Cost of Goods Sold', 'expense', 'cost_of_sales']] as [$n, $t, $s]) {
            ChartOfAccount::firstOrCreate(['name' => $n], ['code' => 'S-' . strtoupper(substr($n, 0, 4)), 'type' => $t, 'sub_type' => $s, 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0]);
        }
        DB::table('orders')->where('id', $order->id)->update(['courier_status' => 'delivered', 'courier_name' => 'steadfast', 'due_amount' => 5000, 'grand_total' => 5000]);

        $this->forgetAuth();
        $res = $this->actingAs($this->makeAdmin())->postJson(route('courier-settlement.settle'), ['order_id' => $order->id, 'courier_fee' => 60, 'account_id' => $cash, 'courier_name' => 'steadfast', 'settlement_fee' => 0, 'due_amount' => 5000, 'settlement_charge' => 0]);
        $this->assertTrue($res->json('success') ?? false, json_encode($res->json()));

        $this->assertEquals(540.0 - 540.0, (float) DB::table('chart_of_accounts')->where('id', $inv)->value('current_balance'), 'inventory credited by exactly the 540 of FIFO cost that left');
        $cogs = (float) DB::table('chart_of_accounts')->where('sub_type', 'cost_of_sales')->sum('current_balance');
        $this->assertEquals(540.0, $cogs, 'COGS = the actual layer cost, not first-layer-price x quantity (which would be 500)');

        // the same settlement, seen from the journal: one source of truth, nothing posted twice
        $entries = DB::table('journal_entries')->where('source_type', 'like', 'legacy:%')->get();
        $this->assertGreaterThanOrEqual(1, $entries->count(), 'the legacy settlement was journalled by the bridge');
        $lines = DB::table('journal_lines')->whereIn('journal_entry_id', $entries->pluck('id'))->get();
        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.001, 'every settlement entry balances');
        $this->assertEquals(540.0, (float) $lines->where('account_id', $inv)->sum('credit'), 'Inventory credited 540 in the journal');
        $this->assertEquals(540.0, (float) $lines->whereIn('account_id', DB::table('chart_of_accounts')->where('sub_type', 'cost_of_sales')->pluck('id'))->sum('debit'), 'COGS debited 540 in the journal');
        $this->assertSame(0, DB::table('account_statement_logs')->where('transaction_type', 'journal')->whereIn('source_id', $entries->pluck('id'))->count(), 'bridged lines add no second statement row');
        $this->assertTrue(\App\Services\Accounting\JournalService::trialBalance(now()->toDateString())['balanced']);
    }

    public function test_an_uncosted_dispatch_is_not_given_an_invented_cost()
    {
        $p = $this->makeStockedProduct(['M' => 2], 1000, 100);
        DB::table('purchase_products')->where('product_id', $p->id)->update(['remaining_qty' => 0]); // units on the shelf, no cost layer left
        $order = $this->makeOrder([[$p, 'M', 2]], 'picked_up');
        $line = $this->detail($order);
        $this->assertSame('uncosted', $line->cost_status);
        $this->assertEquals(0.0, (float) $line->profit, 'no profit is claimed for units without a real cost');
        $report = app(\App\Services\Inventory\InventoryReconciliation::class)->report();
        $this->assertSame('high', $report['uncosted_dispatches']['severity']);
        $this->assertSame(1, $report['uncosted_dispatches']['count']);
    }
}
