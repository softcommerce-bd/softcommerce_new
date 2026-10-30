<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

/**
 * READ-ONLY reconciliation. Never writes. Two layers:
 *  - stockInvariantViolations(): the v2 model's own rules (empty means the counters, demand rows and ledger agree)
 *  - report(): the legacy discrepancies found by the audit, as counts and a few sample ids, for review BEFORE any repair.
 * Nothing here repairs anything: unresolved cost or quantity differences need a human decision.
 */
class InventoryReconciliation
{
    /** @return string[] */
    public function stockInvariantViolations(): array
    {
        $v = [];
        if (!InventoryMode::installed()) {
            return $v;
        }
        $demand = DB::table('order_demands')->where('status', 'open')->selectRaw("product_id, variant, SUM(qty_confirmed - qty_dispatched - qty_cancelled) outstanding, SUM(qty_allocated) allocated")->groupBy('product_id', 'variant')->get()->keyBy(fn ($r) => $r->product_id . '|' . $r->variant);
        $bal = DB::table('stock_balances')->get()->keyBy(fn ($r) => $r->product_id . '|' . $r->variant);
        $ledger = DB::table('stock_movements')->selectRaw('product_id, variant, SUM(demand_delta) d, SUM(allocated_delta) a')->groupBy('product_id', 'variant')->get()->keyBy(fn ($r) => $r->product_id . '|' . $r->variant);

        foreach ($bal->keys()->merge($demand->keys())->unique() as $k) {
            [$pid, $var] = explode('|', $k, 2) + [1 => ''];
            $outstanding = (int) ($demand[$k]->outstanding ?? 0);
            $allocated = (int) ($demand[$k]->allocated ?? 0);
            $onHand = StockBalance::onHand((int) $pid, $var);
            if ((int) ($bal[$k]->demand ?? 0) !== $outstanding) {
                $v[] = "{$k}: demand counter " . ($bal[$k]->demand ?? 0) . " != sum of outstanding {$outstanding}";
            }
            if ((int) ($bal[$k]->allocated ?? 0) !== $allocated) {
                $v[] = "{$k}: allocated counter " . ($bal[$k]->allocated ?? 0) . " != sum of allocations {$allocated}";
            }
            if ($allocated > $onHand) {
                $v[] = "{$k}: allocated {$allocated} exceeds physical on-hand {$onHand}";
            }
            if ($onHand < 0) {
                $v[] = "{$k}: physical on-hand is negative ({$onHand})";
            }
            if ((int) ($ledger[$k]->d ?? 0) !== $outstanding) {
                $v[] = "{$k}: movement ledger demand " . ($ledger[$k]->d ?? 0) . " != outstanding {$outstanding}";
            }
            if ((int) ($ledger[$k]->a ?? 0) !== $allocated) {
                $v[] = "{$k}: movement ledger allocation " . ($ledger[$k]->a ?? 0) . " != allocated {$allocated}";
            }
        }
        foreach (DB::table('order_demands')->whereRaw('qty_confirmed - qty_dispatched - qty_cancelled < 0 OR qty_allocated > qty_confirmed - qty_dispatched - qty_cancelled')->get(['id']) as $bad) {
            $v[] = "order_demands #{$bad->id}: outstanding or allocation out of range";
        }

        return $v;
    }

    /** @return array<string, array{label:string, count:int|string, samples?:array, severity:string}> */
    public function report(): array
    {
        $r = [];
        $add = function (string $key, string $label, $count, array $samples = [], string $severity = 'info') use (&$r) {
            $r[$key] = ['label' => $label, 'count' => $count, 'samples' => array_slice($samples, 0, 5), 'severity' => $severity];
        };

        $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::raw('DATABASE()'))->whereIn('TABLE_NAME', ['purchases', 'purchase_products', 'purchase_returns', 'purchase_return_products', 'suppliers', 'product_stocks', 'orders', 'order_details'])->pluck('ENGINE', 'TABLE_NAME');
        $myisam = $engines->filter(fn ($e) => strtoupper((string) $e) !== 'INNODB')->keys()->all();
        $add('myisam', 'Money/stock tables that are not InnoDB (cannot roll back)', count($myisam), $myisam, $myisam ? 'critical' : 'ok');

        $dup = DB::select('SELECT product_id, variant, COUNT(*) c FROM product_stocks GROUP BY product_id, variant HAVING c > 1');
        $add('duplicate_stock_rows', 'Duplicate stock rows (same product and variation)', count($dup), array_map(fn ($d) => "{$d->product_id}/{$d->variant}", $dup), $dup ? 'critical' : 'ok');

        $neg = DB::table('product_stocks')->where('qty', '<', 0)->pluck('id')->all();
        $add('negative_physical', 'Negative physical stock rows', count($neg), $neg, $neg ? 'critical' : 'ok');

        $mism = DB::select("SELECT s.product_id, s.variant, s.q, COALESCE(b.r,0) r FROM (SELECT product_id, variant, SUM(qty) q FROM product_stocks GROUP BY 1,2) s
            LEFT JOIN (SELECT product_id, COALESCE(variant,'') variant, SUM(remaining_qty) r FROM purchase_products GROUP BY 1,2) b ON b.product_id = s.product_id AND b.variant = s.variant WHERE s.q <> COALESCE(b.r,0)");
        $add('stock_vs_layers', 'Stock rows whose quantity differs from their FIFO cost layers', count($mism), array_map(fn ($m) => "{$m->product_id}/{$m->variant}: stock {$m->q} vs layers {$m->r}", $mism), $mism ? 'high' : 'ok');

        $nocost = DB::select("SELECT s.product_id, s.variant FROM product_stocks s WHERE s.qty > 0 AND NOT EXISTS (SELECT 1 FROM purchase_products b WHERE b.product_id = s.product_id AND COALESCE(b.variant,'') = s.variant)");
        $add('stock_without_cost', 'Positive stock with no cost layer at all (value cannot be determined: needs a decision, nothing is invented)', count($nocost), array_map(fn ($m) => "{$m->product_id}/{$m->variant}", $nocost), $nocost ? 'high' : 'ok');

        $cur = DB::select('SELECT p.id, p.current_stock, COALESCE(s.q,0) q FROM products p LEFT JOIN (SELECT product_id, SUM(qty) q FROM product_stocks GROUP BY 1) s ON s.product_id = p.id WHERE p.current_stock <> COALESCE(s.q,0)');
        $add('current_stock_drift', 'Products whose current_stock differs from the sum of their stock rows', count($cur), array_map(fn ($m) => "#{$m->id}: {$m->current_stock} vs {$m->q}", $cur), $cur ? 'medium' : 'ok');

        $orphans = DB::table('purchase_products as pp')->leftJoin('products as p', 'p.id', '=', 'pp.product_id')->whereNull('p.id')->pluck('pp.id')->all();
        $add('orphan_layers', 'Cost layers pointing at deleted products (history to investigate, not to delete)', count($orphans), $orphans, $orphans ? 'medium' : 'ok');

        $nosr = DB::select("SELECT DISTINCT b.product_id, COALESCE(b.variant,'') variant FROM purchase_products b LEFT JOIN product_stocks s ON s.product_id = b.product_id AND s.variant = COALESCE(b.variant,'') WHERE s.id IS NULL AND EXISTS (SELECT 1 FROM products p WHERE p.id = b.product_id)");
        $add('layers_without_stock_row', 'Cost layers for a product/variation that has no stock row', count($nosr), array_map(fn ($m) => "{$m->product_id}/{$m->variant}", $nosr), $nosr ? 'medium' : 'ok');

        $blank = (int) DB::table('account_statement_logs')->where(fn ($q) => $q->whereNull('transaction_type')->orWhere('transaction_type', ''))->count();
        $add('blank_transaction_types', 'Statement log rows with a blank transaction type (the old ENUM discarded the value; the original type cannot be reconstructed)', $blank, [], $blank ? 'medium' : 'ok');

        $payable = (float) (DB::table('chart_of_accounts')->where('name', 'Accounts Payable')->value('current_balance') ?? 0);
        $dues = (float) DB::table('suppliers')->sum('due_amount');
        $add('supplier_dues_vs_payable', "Supplier dues (" . number_format($dues, 2, '.', '') . ') vs Accounts Payable account (' . number_format($payable, 2, '.', '') . ')', number_format($dues - $payable, 2, '.', ''), [], abs($dues - $payable) > 0.009 ? 'high' : 'ok');

        $inv = (float) (DB::table('chart_of_accounts')->where('name', 'Inventory')->value('current_balance') ?? 0);
        $val = (float) (DB::table('purchase_products')->selectRaw('COALESCE(SUM(remaining_qty * CAST(buying_unit_cost AS DECIMAL(20,4))),0) v')->value('v'));
        $add('inventory_vs_layers', 'Inventory account (' . number_format($inv, 2, '.', '') . ') vs FIFO layer valuation (' . number_format($val, 2, '.', '') . ')', number_format($inv - $val, 2, '.', ''), [], abs($inv - $val) > 0.009 ? 'high' : 'ok');

        if (InventoryMode::installed()) {
            $violations = $this->stockInvariantViolations();
            $add('v2_invariants', 'Confirmed-order stock model invariants violated', count($violations), $violations, $violations ? 'critical' : 'ok');
            $j = DB::table('journal_lines')->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')->first();
            $add('journal_balance', 'Journal: total debits minus total credits (must be 0.00)', number_format((float) $j->d - (float) $j->c, 2, '.', ''), [], round((float) $j->d - (float) $j->c, 2) === 0.0 ? 'ok' : 'critical');
            $unbal = DB::select('SELECT journal_entry_id FROM journal_lines GROUP BY journal_entry_id HAVING ROUND(SUM(debit) - SUM(credit), 2) <> 0');
            $add('unbalanced_entries', 'Unbalanced journal entries', count($unbal), array_map(fn ($u) => $u->journal_entry_id, $unbal), $unbal ? 'critical' : 'ok');
            $unc = DB::table('order_details')->where('cost_status', 'uncosted')->pluck('id')->all();
            $add('uncosted_dispatches', 'Dispatched order lines with units that had no FIFO cost layer (cost unknown; excluded from profit until resolved)', count($unc), $unc, $unc ? 'high' : 'ok');
            foreach ((new AccountingReconciliation())->checks() as $c) {
                $add('acct_' . $c['key'], $c['label'], $c['count'], $c['samples'], $c['severity']);
            }
            $add('open_demand', 'Confirmed outstanding demand (units) / of which unallocated', (int) DB::table('stock_balances')->sum('demand') . ' / ' . ((int) DB::table('stock_balances')->sum('demand') - (int) DB::table('stock_balances')->sum('allocated')), [], 'info');
        }

        return $r;
    }
}
