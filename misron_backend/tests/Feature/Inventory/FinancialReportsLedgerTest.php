<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\LedgerBalances;
use App\Services\Accounting\SystemAccounts;
use Illuminate\Support\Facades\DB;

/** The reports read one ledger: dated balances, trial balance, P&L, general ledger, supplier statement, order profit. */
class FinancialReportsLedgerTest extends InventoryV2TestCase
{
    private function acct(string $type, string $sub, string $name): ChartOfAccount
    {
        return ChartOfAccount::create(['code' => 'R-' . uniqid(), 'name' => $name . uniqid(), 'type' => $type, 'sub_type' => $sub, 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0]);
    }

    private function twoDayLedger(): array
    {
        $this->artisan('accounting:baseline')->assertExitCode(0);
        $cash = $this->acct('asset', 'cash', 'RptCash');
        $sales = $this->acct('income', 'sales', 'RptSales');
        $old = now()->subDays(10)->toDateString();
        $j = app(JournalService::class);
        $j->post('r1-' . uniqid(), $old, [['account' => $cash->id, 'debit' => 10000], ['account' => $sales->id, 'credit' => 10000]], 'test', 1, 'old sale');
        $j->post('r2-' . uniqid(), now()->toDateString(), [['account' => $cash->id, 'debit' => 2500], ['account' => $sales->id, 'credit' => 2500]], 'test', 2, 'new sale');

        return [$cash, $sales, $old];
    }

    public function test_balances_as_at_a_past_date_exclude_later_movements_and_match_the_journal()
    {
        [$cash, $sales, $old] = $this->twoDayLedger();
        $mid = now()->subDays(5)->toDateString();
        $bal = LedgerBalances::balancesAsOf(ChartOfAccount::whereIn('id', [$cash->id, $sales->id])->get(['id', 'type', 'current_balance']), $mid);
        $this->assertEquals(100.0, $bal[$cash->id], 'cash as at 5 days ago: only the older 100 had happened');
        $this->assertEquals(100.0, $bal[$sales->id]);
        $this->assertEquals(125.0, (float) $cash->fresh()->current_balance);
        $this->assertEquals((float) JournalService::balanceAsOf($cash->id, $mid), $bal[$cash->id], 'report balance equals the journal balance as of the same date');
    }

    public function test_balance_sheet_and_trial_balance_honour_the_date_filter_over_http()
    {
        [$cash, $sales] = $this->twoDayLedger();
        $this->actingAs($this->makeAdmin());
        $before = now()->subDays(20)->toDateString();
        $mid = now()->subDays(5)->toDateString();
        $names = fn ($html) => str_contains($html, $cash->name);
        $this->assertFalse($names($this->get(route('accounts.reports.balance-sheet', ['date_range' => 'custom', 'end_date' => $before]))->assertOk()->getContent()), 'before any movement the account has no balance');
        $this->assertTrue($names($this->get(route('accounts.reports.balance-sheet', ['date_range' => 'custom', 'end_date' => $mid]))->assertOk()->getContent()));
        $tb = $this->get(route('accounts.reports.trial-balance', ['date_range' => 'custom', 'end_date' => $before, 'start_date' => $before]))->assertOk()->getContent();
        $this->assertFalse(str_contains($tb, $cash->name), 'trial balance before any movement does not list the account');
        $tbMid = $this->get(route('accounts.reports.trial-balance', ['date_range' => 'custom', 'end_date' => $mid, 'start_date' => $mid]))->assertOk()->getContent();
        $this->assertTrue(str_contains($tbMid, $cash->name), 'and after the first movement it does');
        $this->get(route('accounts.reports.profit-loss', ['date_range' => 'custom', 'start_date' => $mid, 'end_date' => now()->toDateString()]))->assertOk()->assertSee('ledger-source-note', false);
    }

    public function test_profit_and_loss_period_movement_comes_from_the_journal_after_the_baseline()
    {
        [$cash, $sales] = $this->twoDayLedger();
        $this->assertEquals(25.0, LedgerBalances::periodMovement($sales->id, 'income', now()->subDays(2)->toDateString(), now()->toDateString()), 'only the recent sale is inside the period');
        $this->assertEquals(125.0, LedgerBalances::periodMovement($sales->id, 'income', null, null));
    }

    public function test_general_ledger_has_a_running_balance_that_ends_at_the_account_balance_and_supplier_statement_agrees_with_the_record()
    {
        [$cash] = $this->twoDayLedger();
        $this->actingAs($this->makeAdmin());
        $html = $this->get(route('purchasing.report', ['name' => 'general-ledger', 'account_id' => $cash->id]))->assertOk()->getContent();
        $this->assertStringContainsString('100.00', $html);
        $this->assertStringContainsString('125.00', $html, 'running balance reaches the current balance');

        $p = $this->productAtZero();
        $r = $this->receive($p, '', 4, '25');
        app(\App\Services\Purchasing\SupplierLedgerService::class)->recordInvoice('st-' . uniqid(), ['supplier_id' => $this->supplierId, 'goods_receipt_id' => $r['goods_receipt_id'], 'invoice_no' => 'ST-1', 'subtotal' => '100']);
        $html = $this->get(route('purchasing.report', ['name' => 'supplier-statement', 'supplier_id' => $this->supplierId]))->assertOk()->getContent();
        $this->assertStringContainsString('Invoice ST-1', $html);
        $this->assertStringContainsString('100.00 / 100.00', $html, 'statement balance equals the supplier record');
    }

    public function test_order_profit_separates_final_from_unfinalised_and_never_prices_uncosted_lines()
    {
        $p = $this->makeStockedProduct(['M' => 2], 1000, 100);
        DB::table('purchase_products')->where('product_id', $p->id)->update(['remaining_qty' => 0]);
        $this->makeOrder([[$p, 'M', 2]], 'picked_up');
        $this->actingAs($this->makeAdmin());
        $html = $this->get(route('purchasing.report', ['name' => 'actual-order-profit']))->assertOk()->getContent();
        $this->assertStringContainsString('NOT final', $html);
        $this->assertStringContainsString('excluded from profit', $html);
        $this->assertStringContainsString('with unknown cost', $html);
        $this->get(route('accounts.reports.profit-loss'))->assertOk()->assertSee('uncosted-note', false);
    }
}
