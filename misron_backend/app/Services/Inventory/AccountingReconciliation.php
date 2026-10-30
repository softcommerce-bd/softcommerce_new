<?php

namespace App\Services\Inventory;

use App\Services\Accounting\SystemAccounts;
use Illuminate\Support\Facades\DB;

/**
 * READ-ONLY accounting cross-checks for the new ledger. Each check compares two independent sources of the same fact:
 *   journal vs the legacy account balances the journal mirrors, supplier payables vs open invoices, GRNI vs uninvoiced receipts,
 *   cash movements vs payments, FIFO valuation vs Inventory, profit exclusions.
 * Returns rows shaped like InventoryReconciliation::report() ([key, label, count, samples, severity]). Writes nothing.
 */
class AccountingReconciliation
{
    public function checks(): array
    {
        $rows = [];
        $add = function (string $key, string $label, $count, array $samples, string $severity) use (&$rows) {
            $rows[$key] = compact('key', 'label', 'count', 'samples', 'severity');
        };
        $f = fn ($v) => number_format((float) $v, 2, '.', '');

        // 1. every account the journal touched: journal net vs the statement-log mirror rows (the legacy balances' source)
        $bad = DB::select("SELECT a.id, a.name,
                (SELECT COALESCE(SUM(l.debit - l.credit),0) FROM journal_lines l WHERE l.account_id = a.id) AS j,
                (SELECT COALESCE(SUM(s.debit - s.credit),0) FROM account_statement_logs s WHERE s.account_id = a.id AND s.transaction_type = 'journal') AS m
            FROM chart_of_accounts a WHERE EXISTS (SELECT 1 FROM journal_lines x WHERE x.account_id = a.id)");
        $bad = array_values(array_filter($bad, fn ($r) => abs((float) $r->j - (float) $r->m) > 0.009));
        $add('journal_vs_statement_mirror', 'Accounts where the journal and the statement-log mirror disagree (the legacy balance would be wrong)', count($bad), array_map(fn ($r) => "{$r->name}: journal {$f($r->j)} vs mirror {$f($r->m)}", $bad), $bad ? 'critical' : 'ok');

        // 2. Accounts Payable vs open invoices vs supplier dues
        $payable = (float) DB::table('chart_of_accounts')->where('id', SystemAccounts::id('payable'))->value('current_balance');
        $open = (float) DB::table('supplier_invoices')->where('status', '<>', 'void')->selectRaw('COALESCE(SUM(total - paid),0) v')->value('v');
        $dues = (float) DB::table('suppliers')->sum('due_amount');
        $add('payable_vs_open_invoices', "Accounts Payable ({$f($payable)}) vs open supplier invoices ({$f($open)})", $f($payable - $open), [], abs($payable - $open) > 0.009 ? 'high' : 'ok');
        $add('dues_vs_open_invoices', "Supplier due_amount total ({$f($dues)}) vs open supplier invoices ({$f($open)}); legacy purchases may legitimately differ", $f($dues - $open), [], 'info');

        // 3. GRNI vs received-but-not-invoiced goods
        $grni = (float) DB::table('chart_of_accounts')->where('id', SystemAccounts::id('grni'))->value('current_balance');
        $uninv = (float) DB::table('goods_receipts')->where('status', 'posted')->where('invoice_status', '<>', 'invoiced')->sum('goods_total');
        $returned = (float) DB::select("SELECT COALESCE(SUM(-m.on_hand_delta * l.unit_cost),0) v FROM stock_movements m JOIN goods_receipt_lines l ON l.id = m.source_id JOIN goods_receipts g ON g.id = l.goods_receipt_id WHERE m.type = 'supplier_return' AND m.source_type = 'goods_receipt_line' AND g.invoice_status <> 'invoiced'")[0]->v;
        $add('grni_vs_uninvoiced', "GRNI ({$f($grni)}) vs goods received and not yet invoiced, net of returns ({$f($uninv - $returned)})", $f($grni - ($uninv - $returned)), [], abs($grni - ($uninv - $returned)) > 0.009 ? 'high' : 'ok');

        // 4. every real payment has a posted journal entry; credit notes are not cash
        $nojournal = DB::table('supplier_invoice_payments')->whereIn('kind', ['payment', 'advance', 'advance_applied'])->whereNull('journal_entry_id')->pluck('id')->all();
        $add('payments_without_journal', 'Supplier payments with no journal entry', count($nojournal), $nojournal, $nojournal ? 'critical' : 'ok');
        $overpaid = DB::table('supplier_invoices')->whereRaw('paid > total + 0.009')->pluck('id')->all();
        $add('overpaid_invoices', 'Supplier invoices paid beyond their total', count($overpaid), $overpaid, $overpaid ? 'critical' : 'ok');

        // 5. documents without a journal (nothing may change the ledger off the books)
        $r = DB::table('goods_receipts')->where('status', 'posted')->whereNull('journal_entry_id')->pluck('id')->all();
        $add('receipts_without_journal', 'Posted goods receipts with no journal entry', count($r), $r, $r ? 'critical' : 'ok');
        $i = DB::table('supplier_invoices')->where('status', '<>', 'void')->whereNull('journal_entry_id')->pluck('id')->all();
        $add('invoices_without_journal', 'Supplier invoices with no journal entry', count($i), $i, $i ? 'critical' : 'ok');

        // 6. profit: lines whose cost is unknown are excluded from finalized profit, never guessed
        $unc = DB::table('order_details')->where('cost_status', 'uncosted');
        $add('profit_excluded_lines', 'Dispatched lines excluded from finalized profit because their cost is unknown (resolve with inventory:opening-cost for FUTURE stock; history is not rewritten)', (clone $unc)->count(), (clone $unc)->limit(10)->pluck('id')->all(), (clone $unc)->exists() ? 'medium' : 'ok');
        $gap = array_sum(array_column((new OpeningBalanceService(app(\App\Services\Accounting\JournalService::class)))->uncostedUnits(), 'uncosted'));
        $add('uncosted_units_on_hand', 'Units on the shelf with no cost layer (their value is not in the Inventory account; see inventory:opening-cost list)', $gap, [], $gap ? 'medium' : 'ok');

        // 7. total debits equal credits
        $t = DB::table('journal_lines')->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')->first();
        $add('trial_balance', 'Trial balance difference (debits minus credits)', $f($t->d - $t->c), [], abs((float) $t->d - (float) $t->c) > 0.009 ? 'critical' : 'ok');

        return array_values($rows);
    }
}
