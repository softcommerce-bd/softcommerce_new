<?php

namespace App\Services\Inventory;

use App\Services\Accounting\JournalService;
use App\Services\Accounting\Money;
use App\Services\Accounting\SystemAccounts;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Opening cost for legacy stock that has no FIFO cost layer.
 *
 * Nothing is invented: the units that have no cost are listed (uncostedUnits), a person fills in the cost they have evidence for,
 * and only rows with an explicit unit_cost are applied. Applying NEVER changes physical stock, orders or any existing layer; it adds
 * one new layer per approved row (a "cost" for units already on the shelf) and one balanced journal entry:
 *   Dr Inventory / Cr Inventory Opening Adjustment (equity)
 * The whole file is idempotent by its content hash, so re-running the same approved file posts nothing twice.
 * Dispatched order lines already marked "uncosted" are not rewritten; they stay excluded from finalized profit.
 */
class OpeningBalanceService
{
    public function __construct(private JournalService $journal)
    {
    }

    /** @return array<int, array{product_id:int,variant:string,sku:string,name:string,on_hand:int,layered:int,uncosted:int}> */
    public function uncostedUnits(): array
    {
        $rows = DB::select("SELECT s.product_id, s.variant, COALESCE(s.sku,'') sku, p.name, s.qty AS on_hand,
                COALESCE((SELECT SUM(l.remaining_qty) FROM purchase_products l WHERE l.product_id = s.product_id AND COALESCE(l.variant,'') = s.variant), 0) AS layered
            FROM product_stocks s JOIN products p ON p.id = s.product_id WHERE s.qty > 0 ORDER BY s.product_id, s.variant");
        $out = [];
        foreach ($rows as $r) {
            $gap = (int) $r->on_hand - (int) $r->layered;
            if ($gap > 0) {
                $out[] = ['product_id' => (int) $r->product_id, 'variant' => (string) $r->variant, 'sku' => $r->sku, 'name' => $r->name, 'on_hand' => (int) $r->on_hand, 'layered' => (int) $r->layered, 'uncosted' => $gap];
            }
        }

        return $out;
    }

    /** CSV text: one line per uncosted product/variation, unit_cost blank for a person to fill in. */
    public function template(): string
    {
        $fh = fopen('php://temp', 'w+');
        fputcsv($fh, ['product_id', 'variant', 'sku', 'name', 'uncosted_qty', 'unit_cost', 'qty_to_cost', 'evidence']);
        foreach ($this->uncostedUnits() as $u) {
            fputcsv($fh, [$u['product_id'], $u['variant'], $u['sku'], $u['name'], $u['uncosted'], '', '', '']);
        }
        rewind($fh);

        return stream_get_contents($fh);
    }

    /** @return array{rows: array, errors: array<int,string>, total: string} rows that would be applied; nothing is written */
    public function plan(string $csv): array
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\n|\r/', trim($csv)), 'strlen'));
        $header = array_map('trim', str_getcsv(array_shift($lines) ?? ''));
        foreach (['product_id', 'variant', 'unit_cost'] as $need) {
            if (!in_array($need, $header, true)) {
                throw new InvalidArgumentException("The file must have a '{$need}' column.");
            }
        }
        $gaps = [];
        foreach ($this->uncostedUnits() as $u) {
            $gaps[$u['product_id'] . '|' . $u['variant']] = $u['uncosted'];
        }
        $rows = $errors = $seen = [];
        $totalCents = 0;
        foreach ($lines as $i => $line) {
            $n = $i + 2;
            $c = array_combine($header, array_pad(str_getcsv($line), count($header), ''));
            if (trim((string) $c['unit_cost']) === '') {
                continue; // no evidence supplied: stays uncosted, nothing invented
            }
            $key = (int) $c['product_id'] . '|' . (string) $c['variant'];
            if (!isset($gaps[$key])) {
                $errors[] = "Line {$n}: product {$c['product_id']} / '{$c['variant']}' has no uncosted units.";
                continue;
            }
            if (isset($seen[$key])) {
                $errors[] = "Line {$n}: product {$c['product_id']} / '{$c['variant']}' appears twice.";
                continue;
            }
            $seen[$key] = true;
            if (!preg_match('/^\d+(\.\d{1,4})?$/', trim($c['unit_cost'])) || (float) $c['unit_cost'] <= 0) {
                $errors[] = "Line {$n}: unit_cost must be a positive number.";
                continue;
            }
            $qty = trim((string) ($c['qty_to_cost'] ?? '')) === '' ? $gaps[$key] : (int) $c['qty_to_cost'];
            if ($qty < 1 || $qty > $gaps[$key]) {
                $errors[] = "Line {$n}: qty_to_cost must be between 1 and {$gaps[$key]}.";
                continue;
            }
            $cents = Money::cents($qty * Money::micro(trim($c['unit_cost'])));
            $totalCents += $cents;
            $rows[] = ['product_id' => (int) $c['product_id'], 'variant' => (string) $c['variant'], 'qty' => $qty, 'unit_cost' => trim($c['unit_cost']), 'value_cents' => $cents, 'evidence' => trim((string) ($c['evidence'] ?? ''))];
        }

        return ['rows' => $rows, 'errors' => $errors, 'total' => number_format($totalCents / 100, 2, '.', '')];
    }

    /** Applies an approved file. Refuses when any row is invalid. Idempotent by file content. */
    public function apply(string $csv, int|string $approvedBy): array
    {
        $hash = hash('sha256', $csv);
        $done = DB::table('idempotency_keys')->where('scope', 'opening_balance')->where('key', substr($hash, 0, 64))->whereNotNull('result')->value('result');
        if ($done) { // the very same approved file: report the stored result, validate nothing against today's (already reduced) gaps
            return ['replayed' => true] + (json_decode($done, true) ?: []);
        }
        $plan = $this->plan($csv);
        if ($plan['errors']) {
            throw new InvalidArgumentException("The file has errors, nothing was applied:\n" . implode("\n", $plan['errors']));
        }
        if (!$plan['rows']) {
            throw new InvalidArgumentException('No row has a unit_cost, so there is nothing to apply.');
        }

        return Idempotency::once('opening_balance', substr($hash, 0, 64), function () use ($plan, $approvedBy, $hash) {
            $purchaseId = DB::table('purchases')->insertGetId(['supplier_id' => 0, 'reference_no' => 'OPEN-' . substr($hash, 0, 10), 'purchase_date' => now()->toDateString(), 'business_location' => '',
                'total_item' => array_sum(array_column($plan['rows'], 'qty')), 'net_total_amount' => $plan['total'], 'total_paid' => 0, 'total_payment_due' => '0', 'opening_balance_used' => 0,
                'payment_note' => 'Opening cost for existing stock, approved by ' . $approvedBy . ' (no stock change, no payable)', 'created_at' => now(), 'updated_at' => now()]);
            $total = 0;
            foreach ($plan['rows'] as $r) {
                $name = (string) DB::table('products')->where('id', $r['product_id'])->value('name');
                StockBalance::lock($r['product_id'], StockBalance::key($r['variant']));
                // re-check inside the lock: the gap may have closed since the plan
                $gap = collect($this->uncostedUnits())->first(fn ($u) => $u['product_id'] === $r['product_id'] && $u['variant'] === $r['variant'])['uncosted'] ?? 0;
                if ($r['qty'] > $gap) {
                    throw new InvalidArgumentException("Product {$r['product_id']} now has only {$gap} uncosted unit(s).");
                }
                DB::table('purchase_products')->insert(['purchase_id' => $purchaseId, 'product_id' => $r['product_id'], 'product_name' => $name, 'variant' => $r['variant'] === '' ? null : $r['variant'],
                    'purchase_quantity' => (string) $r['qty'], 'remaining_qty' => $r['qty'], 'buying_unit_cost' => $r['unit_cost'], 'total_cost' => number_format($r['value_cents'] / 100, 2, '.', ''), 'product_tax' => '0', 'created_at' => now(), 'updated_at' => now()]);
                StockBalance::movement(['product_id' => $r['product_id'], 'variant' => $r['variant'], 'type' => 'opening_cost', 'on_hand_delta' => 0, 'source_type' => 'purchase', 'source_id' => $purchaseId, 'note' => 'Opening cost approved by ' . $approvedBy . ($r['evidence'] !== '' ? ': ' . $r['evidence'] : '')]);
                $total += $r['value_cents'];
            }
            $j = $this->journal->post('open:' . $hash, now()->toDateString(), [
                ['account' => SystemAccounts::id('inventory'), 'debit' => $total, 'memo' => 'Opening cost for existing stock'],
                ['account' => SystemAccounts::id('opening'), 'credit' => $total, 'memo' => 'Approved by ' . $approvedBy],
            ], 'opening_balance', $purchaseId, 'Opening inventory cost (approved by ' . $approvedBy . ')');

            return ['purchase_id' => $purchaseId, 'rows' => count($plan['rows']), 'value' => number_format($total / 100, 2, '.', ''), 'journal_entry_id' => $j['journal_entry_id']];
        });
    }
}
