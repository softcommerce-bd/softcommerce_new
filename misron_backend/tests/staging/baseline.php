<?php
// Staging baseline snapshot (read-only). Usage: DB_DATABASE=<staging copy> php artisan tinker --execute="echo json_encode(include 'tests/staging/baseline.php');"
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$has = fn ($t) => Schema::hasTable($t);
$one = fn ($q) => DB::selectOne($q);
$out = ['taken_at' => now()->toDateTimeString(), 'database' => DB::getDatabaseName()];

$tables = ['products', 'product_stocks', 'purchase_products', 'purchases', 'purchase_returns', 'suppliers', 'orders', 'order_details', 'combined_orders', 'users', 'chart_of_accounts', 'account_statement_logs', 'supplier_payments', 'journal_entries', 'journal_lines', 'goods_receipts', 'supplier_invoices'];
foreach ($tables as $t) { $out['rows'][$t] = $has($t) ? (int) DB::table($t)->count() : null; }

$out['inventory'] = [
    'stock_rows_qty_total' => (float) $one('SELECT COALESCE(SUM(qty),0) v FROM product_stocks')->v,
    'products_current_stock_total' => (float) $one('SELECT COALESCE(SUM(current_stock),0) v FROM products')->v,
    'fifo_layers_remaining_qty' => (float) $one('SELECT COALESCE(SUM(remaining_qty),0) v FROM purchase_products')->v,
    'fifo_layers_value' => round((float) $one('SELECT COALESCE(SUM(remaining_qty*CAST(buying_unit_cost AS DECIMAL(20,4))),0) v FROM purchase_products')->v, 2),
    'stock_rows_with_stock_but_no_layer' => (int) $one("SELECT COUNT(*) v FROM product_stocks s WHERE s.qty>0 AND NOT EXISTS (SELECT 1 FROM purchase_products l WHERE l.product_id=s.product_id AND COALESCE(l.variant,'')=s.variant AND l.remaining_qty>0)")->v,
    'units_without_cost_layer' => (int) $one("SELECT COALESCE(SUM(GREATEST(s.qty - COALESCE((SELECT SUM(l.remaining_qty) FROM purchase_products l WHERE l.product_id=s.product_id AND COALESCE(l.variant,'')=s.variant),0),0)),0) v FROM product_stocks s WHERE s.qty>0")->v,
    'inventory_account_balance' => round((float) ($one("SELECT current_balance v FROM chart_of_accounts WHERE name='Inventory' AND type='asset' LIMIT 1")->v ?? 0), 2),
];
$out['suppliers'] = [
    'due_amount_total' => round((float) $one('SELECT COALESCE(SUM(due_amount),0) v FROM suppliers')->v, 2),
    'advance_held_total' => round((float) $one('SELECT COALESCE(SUM(CAST(NULLIF(opening_balance,"") AS DECIMAL(20,2))),0) v FROM suppliers')->v, 2),
    'legacy_purchase_dues' => round((float) $one('SELECT COALESCE(SUM(CAST(total_payment_due AS DECIMAL(20,2))),0) v FROM purchases')->v, 2),
    'accounts_payable_balance' => round((float) ($one("SELECT current_balance v FROM chart_of_accounts WHERE name='Accounts Payable' AND type='liability' LIMIT 1")->v ?? 0), 2),
];
$j = $has('journal_lines') ? $one('SELECT COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c FROM journal_lines') : null;
$out['journal'] = $j ? ['debit' => (float) $j->d, 'credit' => (float) $j->c, 'difference' => round($j->d - $j->c, 2)] : 'no journal tables yet';

// Reports: the application's own calculations (private methods, called read-only), as at today / all time.
$ctl = app(\App\Http\Controllers\FinanceReportController::class);
$call = function (string $m, ...$args) use ($ctl) { $r = new ReflectionMethod($ctl, $m); $r->setAccessible(true); return $r->invoke($ctl, ...$args); };
$end = Carbon::now()->endOfDay(); $start = Carbon::parse('2000-01-01')->startOfDay();
$pl = $call('calculateProfitLossData', $start, $end);
$out['profit_and_loss_all_time'] = ['income' => round($pl['totalIncome'], 2), 'cogs' => round($pl['totalCOGS'], 2), 'gross_profit' => round($pl['grossProfit'], 2), 'expenses' => round($pl['totalExpenses'], 2), 'net_profit' => round($pl['netProfit'], 2),
    'lines' => ['income' => $pl['incomeData'], 'cogs' => $pl['cogsData'], 'expenses' => $pl['expenseData']]];
$bs = $call('calculateBalanceSheetData', $start, $end);
$out['balance_sheet_today'] = ['total_assets' => round($bs['totalAssets'], 2), 'total_liabilities' => round($bs['totalLiabilities'], 2), 'total_equity' => round($bs['totalEquity'], 2),
    'groups_assets' => array_map(fn ($g) => ['label' => $g['label'], 'total' => round($g['total'], 2), 'items' => $g['items']], $bs['assetGroups']),
    'groups_liabilities_equity' => array_map(fn ($g) => ['label' => $g['label'], 'total' => round($g['total'], 2), 'items' => $g['items']], $bs['liabilityGroups'])];
$tb = $call('calculateTrialBalanceData', $start, $end);
$out['trial_balance_today'] = array_intersect_key($tb, array_flip(['totalDebit', 'totalCredit'])) + ['rows' => count($tb['trialData'] ?? [])];
$out['trial_balance_rows'] = array_map(fn ($r) => array_intersect_key($r, array_flip(['code', 'name', 'type', 'net_debit', 'net_credit', 'debit', 'credit'])), $tb['trialData'] ?? []);
return $out;
