<?php

namespace App\Services\Purchasing;

use App\Services\Accounting\Money;
use App\Services\Inventory\Idempotency;
use App\Services\Inventory\StockBalance;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * CSV bulk purchasing. Two steps, so nothing financially important is guessed:
 *   preview(): reads and validates EVERY row, returns the resolved lines, the total and a list of problems. Writes nothing.
 *   commit():  refuses to run while any problem remains; creates ONE purchase order (draft or ordered) for the supplier.
 * Retrying the same file for the same supplier returns the first result (the key is a hash of supplier + file content).
 * Columns: sku | barcode | product_id (any one), variant (optional), qty, unit_cost, discount (optional, amount for the line).
 */
class BulkPurchaseImporter
{
    public const TEMPLATE = "sku,barcode,product_id,variant,qty,unit_cost,discount\nSKU-001,,,M,10,650.00,0\n,8901234567890,,,5,120.50,10\n,,123,,2,300,0\n";

    public function __construct(private PurchaseOrderService $orders)
    {
    }

    /** @return array{lines:array, problems:array<int,string>, total:string, rows:int, key:string} */
    public function preview(int $supplierId, string $csv): array
    {
        $rows = $this->parse($csv);
        $problems = [];
        $lines = [];
        $seen = [];
        $total = 0;
        if (!DB::table('suppliers')->where('id', $supplierId)->exists()) {
            $problems[] = 'Unknown supplier.';
        }
        if (count($rows) > 2000) {
            $problems[] = 'More than 2000 rows: split the file.';
        }
        foreach ($rows as $n => $r) {
            $line = $n + 2; // header is line 1
            try {
                $qtyRaw = trim($r['qty'] ?? '');
                if (!preg_match('/^\d+$/', $qtyRaw) || (int) $qtyRaw < 1) {
                    throw new InvalidArgumentException('quantity must be a whole number of at least 1');
                }
                $cost = Money::micro(($r['unit_cost'] ?? '') === '' ? 'x' : $r['unit_cost']);
                $discount = Money::micro(($r['discount'] ?? '') === '' ? 0 : $r['discount']);
                if ($cost < 0 || $discount < 0 || $discount > (int) $qtyRaw * $cost) {
                    throw new InvalidArgumentException('unit cost or discount is not valid');
                }
                [$productId, $variant, $name] = $this->resolve($r);
                $k = $productId . '|' . $variant;
                if (isset($seen[$k])) {
                    throw new InvalidArgumentException("duplicate of line {$seen[$k]} (same product and variation): merge them");
                }
                $seen[$k] = $line;
                $qty = (int) $qtyRaw;
                $effective = intdiv(($qty * $cost - $discount) * 2 + $qty, 2 * $qty); // net unit cost, 4 decimals
                $lines[] = ['product_id' => $productId, 'variant' => $variant, 'name' => $name, 'qty' => $qty, 'unit_cost' => Money::decimal($effective), 'row' => $line];
                $total += $qty * $effective;
            } catch (InvalidArgumentException $e) {
                $problems[] = "Line {$line}: " . $e->getMessage();
            }
        }
        if ($rows === []) {
            $problems[] = 'The file has no data rows.';
        }

        return ['lines' => $lines, 'problems' => $problems, 'total' => Money::decimal($total, 2), 'rows' => count($rows), 'key' => 'bulk:' . $supplierId . ':' . hash('sha256', $csv)];
    }

    /** @return array{purchase_order_id:int, reference_no:string, status:string, replayed:bool} */
    public function commit(int $supplierId, string $csv, string $status = 'draft', ?int $by = null): array
    {
        $p = $this->preview($supplierId, $csv);
        if ($p['problems'] !== []) {
            throw new InvalidArgumentException('Fix these first: ' . implode(' | ', array_slice($p['problems'], 0, 5)) . (count($p['problems']) > 5 ? ' ...' : ''));
        }
        $po = $this->orders->create($p['key'], ['supplier_id' => $supplierId, 'status' => $status, 'note' => 'Bulk import (' . $p['rows'] . ' rows)',
            'lines' => array_map(fn ($l) => ['product_id' => $l['product_id'], 'variant' => $l['variant'], 'qty' => $l['qty'], 'unit_cost' => $l['unit_cost']], $p['lines'])], $by);

        return $po + ['replayed' => $po['replayed'] ?? false];
    }

    private function resolve(array $r): array
    {
        $sku = trim($r['sku'] ?? '');
        $barcode = trim($r['barcode'] ?? '');
        $pid = trim($r['product_id'] ?? '');
        $variant = trim($r['variant'] ?? '');
        if ($sku !== '') {
            $rows = DB::table('product_stocks')->where('sku', $sku)->get(['product_id', 'variant']);
            if ($rows->count() !== 1) {
                throw new InvalidArgumentException($rows->isEmpty() ? "SKU '{$sku}' not found" : "SKU '{$sku}' matches {$rows->count()} products: use product_id");
            }
            $pid = (string) $rows[0]->product_id;
            $variant = $variant !== '' ? $variant : (string) $rows[0]->variant;
        } elseif ($barcode !== '') {
            $found = DB::table('products')->where('barcode', $barcode)->pluck('id');
            if ($found->count() !== 1) {
                throw new InvalidArgumentException($found->isEmpty() ? "barcode '{$barcode}' not found" : "barcode '{$barcode}' matches several products");
            }
            $pid = (string) $found[0];
        }
        if (!preg_match('/^\d+$/', $pid)) {
            throw new InvalidArgumentException('give a sku, a barcode or a product_id');
        }
        $product = DB::table('products')->where('id', $pid)->first(['id', 'name']);
        if (!$product) {
            throw new InvalidArgumentException("product {$pid} not found");
        }
        $exists = DB::table('product_stocks')->where('product_id', $product->id)->where('variant', StockBalance::key($variant))->exists();
        if (!$exists && DB::table('product_stocks')->where('product_id', $product->id)->exists()) {
            throw new InvalidArgumentException("'{$product->name}' has no variation '{$variant}'");
        }

        return [(int) $product->id, StockBalance::key($variant), $product->name . ($variant !== '' ? " ({$variant})" : '')];
    }

    private function parse(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv); // Excel's byte-order mark
        $lines = array_values(array_filter(preg_split('/\r\n|\n|\r/', $csv), fn ($l) => trim($l) !== ''));
        if ($lines === []) {
            return [];
        }
        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(array_shift($lines)));
        if (!in_array('qty', $header, true) || !in_array('unit_cost', $header, true)) {
            throw new InvalidArgumentException('The header row must contain qty and unit_cost (download the template).');
        }
        $out = [];
        foreach ($lines as $l) {
            $cells = str_getcsv($l);
            $row = [];
            foreach ($header as $i => $h) {
                // A cell that starts like a formula is data, never an instruction.
                $row[$h] = isset($cells[$i]) ? trim(ltrim($cells[$i], "=+@\t\r")) : '';
            }
            $out[] = $row;
        }

        return $out;
    }
}
