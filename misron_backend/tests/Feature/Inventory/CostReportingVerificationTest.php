<?php

namespace Tests\Feature\Inventory;

use App\Services\Accounting\JournalService;
use App\Services\Accounting\RefundService;
use Illuminate\Support\Facades\DB;

/** C. Historical / uncosted reporting: classification, refunds shown but not netted, estimates never booked, originals untouched. */
class CostReportingVerificationTest extends InventoryV2TestCase
{
    private function ledgerFingerprint(): string
    {
        return json_encode([DB::table('chart_of_accounts')->orderBy('id')->pluck('current_balance', 'id')->all(), DB::table('journal_entries')->count(), DB::table('journal_lines')->count(), DB::table('account_statement_logs')->count()]);
    }

    public function test_every_kind_of_line_lands_in_its_own_bucket_and_nothing_is_mixed_into_final_profit()
    {
        $p = $this->productAtZero(['unit_price' => 1000, 'estimated_purchase_cost' => 300]);
        // 1. confirmed order, real cost, shipped -> FINAL
        $this->receive($p, '', 3, '400');
        $final = $this->makeOrder([[$p, '', 2]], 'picked_up'); $lf = $this->detail($final);
        // 2. cancelled order -> not counted at all
        $cancelled = $this->makeOrder([[$p, '', 1]], 'pending'); DB::table('orders')->where('id', $cancelled->id)->update(['delivery_status' => 'cancelled']);
        // 3. shipped with units that have no cost layer -> UNCOSTED with an ESTIMATE
        $q = $this->makeStockedProduct(['M' => 2], 1000, 100, ['estimated_purchase_cost' => 300]);
        DB::table('purchase_products')->where('product_id', $q->id)->update(['remaining_qty' => 0]);
        $unc = $this->makeOrder([[$q, 'M', 2]], 'picked_up'); $lu = $this->detail($unc);
        // 4. historical line with recorded cost, partially refunded; 5. historical with no cost
        $h = $this->makeOrder([[$p, '', 1]], 'pending'); $lh = $this->detail($h); DB::table('orders')->where('id', $h->id)->update(['delivery_status' => 'delivered']);
        DB::table('order_details')->where('id', $lh->id)->update(['stock_mode' => 'legacy', 'price' => 1000, 'buying_price' => '350', 'profit' => 650, 'cost_status' => null]);
        $n = $this->makeOrder([[$p, '', 1]], 'pending'); $ln = $this->detail($n); DB::table('orders')->where('id', $n->id)->update(['delivery_status' => 'delivered']);
        DB::table('order_details')->where('id', $ln->id)->update(['stock_mode' => 'legacy', 'price' => 800, 'buying_price' => '0', 'profit' => 0, 'cost_status' => null]);
        app(RefundService::class)->record('hist-1', 'wallet', 250, $h->id, $lh->id);   // partial refund of the historical line
        app(RefundService::class)->record('fin-1', 'wallet', 100, $final->id, $lf->id); // partial refund of the FINAL line

        $originals = DB::table('order_details')->whereIn('id', [$lf->id, $lu->id, $lh->id, $ln->id])->orderBy('id')->get()->toJson();
        $ledger = $this->ledgerFingerprint();
        $html = $this->actingAs($this->makeAdmin())->get(route('purchasing.report', ['name' => 'actual-order-profit']))->assertOk()->getContent();

        $this->assertStringContainsString('FINAL (actual FIFO cost)', $html);
        $this->assertStringContainsString('UNCOSTED - excluded from profit', $html);
        $this->assertStringContainsString('ESTIMATE 1400.00 (not final)', $html, '2 units sold for 2000 with a planning cost of 300 each: 2000 - 2 x 300');
        $this->assertStringContainsString('HISTORICAL - cost as recorded by the old system, not verified', $html);
        $this->assertStringContainsString('HISTORICAL - cost unknown', $html);
        $this->assertStringContainsString('FINAL profit (actual FIFO cost recorded at dispatch): ' . number_format((float) $lf->fresh()->profit, 2), $html, 'FINAL equals the dispatched line only');
        $this->assertStringContainsString('Refunds posted on these lines: 350.00', $html, 'both partial refunds are shown');
        $this->assertStringContainsString('NOT deducted from any profit figure', $html);
        $this->assertStringContainsString('Cancelled orders are not counted', $html);
        // the cancelled order's line is not listed
        $cancelledCode = $cancelled->code;
        $this->assertStringNotContainsString($cancelledCode, $html);
        $this->assertSame($originals, DB::table('order_details')->whereIn('id', [$lf->id, $lu->id, $lh->id, $ln->id])->orderBy('id')->get()->toJson(), 'no original order or cost record was touched by reading the report');
        $this->assertSame($ledger, $this->ledgerFingerprint(), 'reading the report posted nothing');
    }

    public function test_estimates_never_enter_actual_accounting_totals()
    {
        $q = $this->makeStockedProduct(['M' => 4], 1000, 100, ['estimated_purchase_cost' => 300]);
        DB::table('purchase_products')->where('product_id', $q->id)->update(['remaining_qty' => 0]);
        $before = $this->ledgerFingerprint();
        $o = $this->makeOrder([[$q, 'M', 4]], 'picked_up'); $line = $this->detail($o);
        $this->assertEquals(300.0, (float) $line->estimated_unit_cost, 'the estimate exists');
        $this->assertSame('uncosted', $line->cost_status);
        $this->assertEquals(0.0, (float) $line->profit, 'no profit claimed');
        $this->assertEquals(0.0, (float) $line->buying_price, 'actual cost stays unknown');
        $this->assertSame($before, $this->ledgerFingerprint(), 'dispatching with an estimate posted no entry and moved no account (Inventory, COGS and every other balance are unchanged)');
        // and no report total contains it
        $this->actingAs($this->makeAdmin());
        $this->get(route('accounts.reports.profit-loss', ['date_range' => 'this_year']))->assertOk()->assertDontSee('1,200.00', false);
        $rec = collect((new \App\Services\Inventory\AccountingReconciliation())->checks())->firstWhere('key', 'profit_excluded_lines');
        $this->assertGreaterThanOrEqual(1, (int) $rec['count'], 'reconciliation lists the line as excluded from profit');
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
    }
}
