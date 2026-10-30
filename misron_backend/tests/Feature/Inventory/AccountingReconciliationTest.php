<?php

namespace Tests\Feature\Inventory;

use App\Services\Accounting\SystemAccounts;
use App\Services\Inventory\AccountingReconciliation;
use App\Services\Purchasing\SupplierLedgerService;
use Illuminate\Support\Facades\DB;

class AccountingReconciliationTest extends InventoryV2TestCase
{
    private function sev(): array
    {
        return collect((new AccountingReconciliation())->checks())->pluck('severity', 'key')->all();
    }

    public function test_a_normal_receive_invoice_pay_cycle_reconciles_clean()
    {
        $p = $this->productAtZero();
        $r = $this->receive($p, '', 10, '100');
        $this->assertSame('ok', $this->sev()['grni_vs_uninvoiced']);
        $svc = app(SupplierLedgerService::class);
        $inv = $svc->recordInvoice('i', ['supplier_id' => $this->supplierId, 'goods_receipt_id' => $r['goods_receipt_id'], 'invoice_no' => 'Z1', 'subtotal' => '1000'])['supplier_invoice_id'];
        $acct = \App\Models\ChartOfAccount::create(['code' => 'T-C-' . uniqid(), 'name' => 'c' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 5000, 'status' => 'active', 'is_deleted' => 0])->id;
        $svc->pay('p', $inv, $acct, '400');
        foreach ($this->sev() as $k => $v) {
            if (in_array($k, ['uncosted_units_on_hand', 'profit_excluded_lines', 'dues_vs_open_invoices'], true)) {
                continue;
            }
            $this->assertContains($v, ['ok', 'info'], "check {$k} should be clean after a normal cycle");
        }
    }

    public function test_tampering_with_the_mirror_or_a_payable_is_caught()
    {
        $p = $this->productAtZero();
        $r = $this->receive($p, '', 2, '10');
        app(SupplierLedgerService::class)->recordInvoice('i', ['supplier_id' => $this->supplierId, 'goods_receipt_id' => $r['goods_receipt_id'], 'invoice_no' => 'Z2', 'subtotal' => '20']);
        DB::table('account_statement_logs')->where('transaction_type', 'journal')->where('account_id', SystemAccounts::id('payable'))->limit(1)->update(['credit' => 999]);
        DB::table('chart_of_accounts')->where('id', SystemAccounts::id('payable'))->increment('current_balance', 5);
        $s = $this->sev();
        $this->assertSame('critical', $s['journal_vs_statement_mirror']);
        $this->assertSame('high', $s['payable_vs_open_invoices']);
    }

    public function test_it_is_part_of_the_reconcile_report_and_changes_nothing()
    {
        $before = DB::table('chart_of_accounts')->sum('current_balance') . '|' . DB::table('journal_entries')->count();
        $keys = array_keys(app(\App\Services\Inventory\InventoryReconciliation::class)->report());
        $this->assertContains('acct_trial_balance', $keys);
        $this->assertSame($before, DB::table('chart_of_accounts')->sum('current_balance') . '|' . DB::table('journal_entries')->count());
    }
}
