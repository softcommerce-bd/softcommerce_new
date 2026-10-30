<?php

namespace App\Services\Purchasing;

use App\Services\Accounting\JournalService;
use App\Services\Accounting\Money;
use App\Services\Accounting\SystemAccounts;
use App\Services\Inventory\DemandService;
use App\Services\Inventory\Idempotency;
use App\Services\Inventory\StockBalance;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Receiving goods: the only operation that adds physical stock from a supplier.
 * One call, one transaction, one idempotency key. It
 *   - checks the quantities against the purchase order (partial receiving),
 *   - adds physical stock and writes the movement,
 *   - creates the FIFO cost layer (a purchase_products row under a purchases header, so every existing screen and
 *     StockLedger::consumeBatches keep working) at the LANDED unit cost (price + allocated freight/other costs),
 *   - posts  Dr Inventory / Cr Goods-Received-Not-Invoiced  (+ Cr the account that bore the landed cost),
 *   - allocates the new stock to waiting confirmed orders, oldest first.
 * It does not create a supplier payable (that is the invoice) and it never pays anything.
 */
class GoodsReceiptService
{
    public function __construct(private StockLedger $ledger, private DemandService $demand, private JournalService $journal)
    {
    }

    /**
     * @param array{supplier_id:int, purchase_order_id?:int, received_at?:string, note?:string, landed_costs?:array{amount:string|int|float, account_id:int}, lines:array<int, array{purchase_order_detail_id?:int, product_id:int, variant?:string, qty:int, unit_cost:string|int|float}>} $data
     */
    public function receive(string $key, array $data, ?int $by = null): array
    {
        return Idempotency::once('goods_receipt', $key, function () use ($key, $data, $by) {
            $lines = $data['lines'] ?? [];
            if ($lines === []) {
                throw new InvalidArgumentException('Nothing to receive.');
            }
            $supplierId = (int) $data['supplier_id'];
            if (!DB::table('suppliers')->where('id', $supplierId)->exists()) {
                throw new InvalidArgumentException('Unknown supplier.');
            }

            $po = null;
            if (!empty($data['purchase_order_id'])) {
                $po = DB::table('purchase_orders')->where('id', $data['purchase_order_id'])->lockForUpdate()->first();
                if (!$po || (int) $po->supplier_id !== $supplierId || !in_array($po->status, ['ordered', 'partially_received'], true)) {
                    throw new InvalidArgumentException('That purchase order cannot receive goods (not found, another supplier, or not open).');
                }
            }

            // Validate every line before anything is written.
            $prepared = [];
            $seenDetails = [];
            foreach ($lines as $i => $l) {
                $qty = (int) ($l['qty'] ?? 0);
                if ($qty < 1 || $qty > 1000000 || (string) $qty !== (string) ($l['qty'] ?? '')) {
                    throw new InvalidArgumentException('Line ' . ($i + 1) . ': received quantity must be a whole number of at least 1.');
                }
                $cost = Money::micro($l['unit_cost'] ?? null);
                if ($cost < 0) {
                    throw new InvalidArgumentException('Line ' . ($i + 1) . ': unit cost cannot be negative.');
                }
                $productId = (int) $l['product_id'];
                $variant = StockBalance::key($l['variant'] ?? '');
                $product = DB::table('products')->where('id', $productId)->first(['id', 'name', 'digital']);
                if (!$product) {
                    throw new InvalidArgumentException('Line ' . ($i + 1) . ': unknown product.');
                }
                $detail = null;
                if ($po) {
                    $detailId = (int) ($l['purchase_order_detail_id'] ?? 0);
                    if (isset($seenDetails[$detailId])) {
                        throw new InvalidArgumentException('Line ' . ($i + 1) . ': the same purchase order line appears twice.');
                    }
                    $seenDetails[$detailId] = true;
                    $detail = DB::table('purchase_order_details')->where('id', $detailId)->where('purchase_order_id', $po->id)->lockForUpdate()->first();
                    if (!$detail || (int) $detail->product_id !== $productId || StockBalance::key($detail->variant) !== $variant) {
                        throw new InvalidArgumentException('Line ' . ($i + 1) . ': not a line of that purchase order.');
                    }
                    if ($qty > (int) $detail->pending_quantity) {
                        throw new InvalidArgumentException('Line ' . ($i + 1) . ": only {$detail->pending_quantity} still expected on that purchase order line.");
                    }
                }
                $prepared[] = compact('qty', 'cost', 'productId', 'variant', 'product', 'detail');
            }

            // Landed cost (freight etc.) is capitalised into the units, in proportion to value.
            $landed = ['amount' => 0, 'account' => null];
            if (!empty($data['landed_costs']['amount'])) {
                $landed = ['amount' => Money::micro($data['landed_costs']['amount']), 'account' => (int) ($data['landed_costs']['account_id'] ?? 0)];
                if ($landed['amount'] < 0 || !$landed['account'] || !DB::table('chart_of_accounts')->where('id', $landed['account'])->exists()) {
                    throw new InvalidArgumentException('Landed costs need a positive amount and the account that bears them (cash, bank or payable).');
                }
            }
            // Lock order: every caller walks lines by (product, variation), so concurrent receipts and orders cannot deadlock.
            usort($prepared, fn ($a, $b) => [$a['productId'], $a['variant']] <=> [$b['productId'], $b['variant']]);
            $weights = array_map(fn ($p) => $p['qty'] * $p['cost'] ?: 1, $prepared);
            $shares = Money::allocate($landed['amount'], $weights);

            $goodsMicro = 0;
            $totalMicro = 0;
            foreach ($prepared as $k => &$p) {
                $unitLanded = $p['qty'] > 0 ? intdiv(($p['qty'] * $p['cost'] + $shares[$k]) * 2 + $p['qty'], 2 * $p['qty']) : 0; // rounded to 4 decimals
                $p['landed_unit'] = $unitLanded;
                $p['line_total'] = $unitLanded * $p['qty'];
                $p['goods'] = $p['qty'] * $p['cost'];
                $goodsMicro += $p['goods'];
                $totalMicro += $p['line_total'];
            }
            unset($p);
            $landedBooked = $totalMicro - $goodsMicro; // what actually went into the units (rounding stays inside inventory)

            $receiptNo = 'GRN-' . now()->format('ymd') . '-' . strtoupper(substr(sha1($key), 0, 6));
            $receivedAt = $data['received_at'] ?? now()->toDateString();

            // The purchases header exists so the FIFO layers have a purchase to belong to; it carries no payable.
            $purchaseId = DB::table('purchases')->insertGetId(['supplier_id' => $supplierId, 'reference_no' => $receiptNo, 'purchase_date' => $receivedAt, 'business_location' => '', 'total_item' => array_sum(array_column($prepared, 'qty')),
                'net_total_amount' => Money::cents($totalMicro) / 100, 'total_paid' => 0, 'total_payment_due' => '0', 'opening_balance_used' => 0, 'payment_note' => 'Goods receipt ' . $receiptNo . ': payables are recorded on the supplier invoice', 'created_at' => now(), 'updated_at' => now()]);

            $receiptId = DB::table('goods_receipts')->insertGetId(['receipt_no' => $receiptNo, 'purchase_order_id' => $po->id ?? null, 'supplier_id' => $supplierId, 'purchase_id' => $purchaseId, 'received_at' => $receivedAt,
                'goods_total' => Money::decimal($goodsMicro), 'landed_cost' => Money::decimal($landedBooked), 'total' => Money::decimal($totalMicro), 'invoice_status' => 'not_invoiced', 'status' => 'posted',
                'idempotency_key' => $key, 'created_by' => $by, 'note' => $data['note'] ?? null, 'created_at' => now(), 'updated_at' => now()]);

            $items = [];
            foreach ($prepared as $p) {
                $this->ensureStockRow($p['productId'], $p['variant']);
                StockBalance::lock($p['productId'], $p['variant']);
                // 1. physical stock
                $this->ledger->adjust($p['productId'], $p['variant'], $p['qty']);
                DB::table('products')->where('id', $p['productId'])->increment('current_stock', $p['qty']);
                // 2. FIFO layer at the landed cost
                $layerId = DB::table('purchase_products')->insertGetId(['purchase_id' => $purchaseId, 'product_id' => $p['productId'], 'product_name' => $p['product']->name, 'variant' => $p['variant'] === '' ? null : $p['variant'],
                    'purchase_quantity' => (string) $p['qty'], 'remaining_qty' => $p['qty'], 'buying_unit_cost' => Money::decimal($p['landed_unit']), 'total_cost' => Money::decimal($p['line_total'], 2), 'product_tax' => '0', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('goods_receipt_lines')->insert(['goods_receipt_id' => $receiptId, 'purchase_order_detail_id' => $p['detail']->id ?? null, 'product_id' => $p['productId'], 'variant' => $p['variant'], 'qty' => $p['qty'],
                    'unit_cost' => Money::decimal($p['cost']), 'landed_unit_cost' => Money::decimal($p['landed_unit']), 'line_total' => Money::decimal($p['line_total']), 'purchase_product_id' => $layerId, 'created_at' => now(), 'updated_at' => now()]);
                StockBalance::movement(['product_id' => $p['productId'], 'variant' => $p['variant'], 'type' => 'goods_received', 'on_hand_delta' => $p['qty'], 'source_type' => 'goods_receipt', 'source_id' => $receiptId, 'created_by' => $by]);
                // 3. purchase order line and requirement links
                if ($p['detail']) {
                    $pending = (int) $p['detail']->pending_quantity - $p['qty'];
                    DB::table('purchase_order_details')->where('id', $p['detail']->id)->update(['recieved_quantity' => (int) $p['detail']->recieved_quantity + $p['qty'], 'pending_quantity' => $pending, 'remaining_quantity' => $pending, 'updated_at' => now()]);
                    $this->advanceLinks((int) $p['detail']->id, $p['qty']);
                }
                $items[$p['productId'] . '|' . $p['variant']] = [$p['productId'], $p['variant']];
            }
            if ($po) {
                $open = DB::table('purchase_order_details')->where('purchase_order_id', $po->id)->where('pending_quantity', '>', 0)->exists();
                DB::table('purchase_orders')->where('id', $po->id)->update(['status' => $open ? 'partially_received' : 'received', 'updated_at' => now()]);
            }

            // 4. accounting: Dr Inventory (goods + landed) / Cr GRNI (goods) / Cr landed-cost account
            $lines = [
                ['account' => SystemAccounts::id('inventory'), 'debit' => Money::cents($totalMicro), 'memo' => $receiptNo],
                ['account' => SystemAccounts::id('grni'), 'credit' => Money::cents($goodsMicro), 'supplier_id' => $supplierId, 'memo' => $receiptNo],
            ];
            $landedCents = Money::cents($totalMicro) - Money::cents($goodsMicro);
            if ($landedCents > 0) {
                $lines[] = ['account' => $landed['account'], 'credit' => $landedCents, 'memo' => 'Landed cost ' . $receiptNo];
            } elseif ($landedCents < 0) { // cent-level rounding between goods and total: keep the entry balanced inside GRNI
                $lines[1]['credit'] -= $landedCents;
            }
            $j = $this->journal->post('grn:' . $key, $receivedAt, $lines, 'goods_receipt', $receiptId, 'Goods received ' . $receiptNo, $by);
            DB::table('goods_receipts')->where('id', $receiptId)->update(['journal_entry_id' => $j['journal_entry_id']]);

            // 5. new physical units go to the oldest waiting confirmed orders
            foreach ($items as [$pid, $var]) {
                $this->demand->allocate($pid, $var);
            }

            return ['goods_receipt_id' => $receiptId, 'receipt_no' => $receiptNo, 'total' => Money::decimal($totalMicro), 'journal_entry_id' => $j['journal_entry_id']];
        });
    }

    private function ensureStockRow(int $productId, string $variant): void
    {
        if (!DB::table('product_stocks')->where('product_id', $productId)->where('variant', $variant)->exists()) {
            // The same thing the legacy purchase screen does: a purchase may introduce the product's first stock row.
            DB::table('product_stocks')->insert(['product_id' => $productId, 'variant' => $variant, 'sku' => '', 'price' => (float) DB::table('products')->where('id', $productId)->value('unit_price'), 'qty' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Received units fill requirement links of that PO line, oldest demand first. */
    private function advanceLinks(int $poDetailId, int $qty): void
    {
        $links = DB::table('purchase_requirement_links as l')->join('order_demands as d', 'd.id', '=', 'l.order_demand_id')->where('l.purchase_order_detail_id', $poDetailId)->where('l.status', 'open')
            ->orderBy('d.confirmed_at')->orderBy('l.id')->lockForUpdate()->get(['l.id', 'l.qty_linked', 'l.qty_received']);
        foreach ($links as $l) {
            if ($qty < 1) {
                break;
            }
            $room = (int) $l->qty_linked - (int) $l->qty_received;
            $take = min($room, $qty);
            DB::table('purchase_requirement_links')->where('id', $l->id)->update(['qty_received' => (int) $l->qty_received + $take, 'status' => $take === $room ? 'received' : 'open', 'updated_at' => now()]);
            $qty -= $take;
        }
    }
}
