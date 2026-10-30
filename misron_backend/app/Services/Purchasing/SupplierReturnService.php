<?php

namespace App\Services\Purchasing;

use App\Services\Accounting\JournalService;
use App\Services\Accounting\Money;
use App\Services\Accounting\SystemAccounts;
use App\Services\Inventory\Idempotency;
use App\Services\Inventory\StockBalance;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Goods go back to a supplier (for goods received through the new receiving flow).
 * Allowed only for units that are still on the shelf, in their own FIFO layer and not earmarked for a confirmed order:
 * a unit that was sold or dispatched cannot be "returned to the supplier" here (that needs an explicit reconciliation).
 *   Cr Inventory (the layer's cost of those units)
 *   Dr Goods-Received-Not-Invoiced  (receipt not invoiced yet)
 *   Dr Accounts Payable             (invoiced and still owed: recorded as a credit note on the invoice)
 *   Dr Supplier Advances            (invoice already paid: the supplier now owes us; held as an advance)
 *   +/- Purchase Price Variance     (difference between the layer cost and what the supplier credits)
 */
class SupplierReturnService
{
    public function __construct(private StockLedger $ledger, private JournalService $journal)
    {
    }

    public function returnGoods(string $key, int $receiptLineId, int $qty, ?int $by = null, ?string $note = null): array
    {
        return Idempotency::once('supplier_return', $key, function () use ($key, $receiptLineId, $qty, $by, $note) {
            if ($qty < 1) {
                throw new InvalidArgumentException('The return quantity must be at least 1.');
            }
            $line = DB::table('goods_receipt_lines as l')->join('goods_receipts as g', 'g.id', '=', 'l.goods_receipt_id')->where('l.id', $receiptLineId)->lockForUpdate()
                ->first(['l.*', 'g.supplier_id', 'g.invoice_status', 'g.id as receipt_id', 'g.receipt_no', 'g.goods_total']) ?: throw new InvalidArgumentException('Unknown receipt line.');
            $returned = (int) DB::table('stock_movements')->where('type', 'supplier_return')->where('source_type', 'goods_receipt_line')->where('source_id', $line->id)->sum(DB::raw('-on_hand_delta'));
            if ($qty > (int) $line->qty - $returned) {
                throw new InvalidArgumentException('Only ' . ((int) $line->qty - $returned) . ' unit(s) of this line can still be returned.');
            }

            $productId = (int) $line->product_id;
            $variant = StockBalance::key($line->variant);
            $bal = StockBalance::lock($productId, $variant);
            $layer = DB::table('purchase_products')->where('id', $line->purchase_product_id)->lockForUpdate()->first();
            $free = StockBalance::onHandLocked($productId, $variant) - (int) $bal->allocated;
            if (!$layer || (int) $layer->remaining_qty < $qty || $free < $qty) {
                throw new InvalidArgumentException('Those units are no longer available to return: some were sold, dispatched or are held for a confirmed order (' . max(0, min((int) ($layer->remaining_qty ?? 0), $free)) . ' can go back).');
            }

            // physical stock, layer, current_stock total
            if (!$this->ledger->adjust($productId, $variant, -$qty) || !$this->ledger->resizeBatch((int) $layer->id, -$qty)) {
                throw new InvalidArgumentException('The stock changed while returning: nothing was returned. Try again.');
            }
            DB::table('products')->where('id', $productId)->where('current_stock', '>=', $qty)->decrement('current_stock', $qty);
            StockBalance::movement(['product_id' => $productId, 'variant' => $variant, 'type' => 'supplier_return', 'on_hand_delta' => -$qty, 'source_type' => 'goods_receipt_line', 'source_id' => $line->id, 'created_by' => $by, 'note' => $note]);

            $inventoryCents = Money::cents($qty * Money::micro($layer->buying_unit_cost));
            $ratio = [1, 1]; // supplier credit = goods price x (invoiced goods / received goods)
            $invoice = null;
            if ($line->invoice_status === 'invoiced') {
                $invoice = DB::table('supplier_invoices')->where('goods_receipt_id', $line->receipt_id)->lockForUpdate()->first();
                $ratio = [Money::cents(Money::micro($invoice->subtotal) - Money::micro($invoice->discount)), max(1, Money::cents(Money::micro($line->goods_total)))];
            }
            $creditCents = intdiv(Money::cents($qty * Money::micro($line->unit_cost)) * $ratio[0] * 2 + $ratio[1], 2 * $ratio[1]);

            $lines = [['account' => SystemAccounts::id('inventory'), 'credit' => $inventoryCents, 'memo' => "Return to supplier ({$line->receipt_no})"]];
            $debits = [];
            if (!$invoice) {
                $debits[] = ['account' => SystemAccounts::id('grni'), 'debit' => $creditCents, 'supplier_id' => $line->supplier_id, 'memo' => "Return of uninvoiced goods ({$line->receipt_no})"];
            } else {
                $owed = (int) round(((float) $invoice->total - (float) $invoice->paid) * 100);
                $toPayable = min($creditCents, max(0, $owed));
                $toAdvance = $creditCents - $toPayable;
                if ($toPayable > 0) {
                    $debits[] = ['account' => SystemAccounts::id('payable'), 'debit' => $toPayable, 'supplier_id' => $line->supplier_id, 'memo' => "Credit note on {$invoice->invoice_no}"];
                    DB::table('supplier_invoices')->where('id', $invoice->id)->update(['paid' => ((int) round((float) $invoice->paid * 100) + $toPayable) / 100, 'status' => ((int) round((float) $invoice->paid * 100) + $toPayable) >= (int) round((float) $invoice->total * 100) ? 'paid' : 'partial', 'updated_at' => now()]);
                    DB::table('supplier_invoice_payments')->insert(['supplier_id' => $line->supplier_id, 'supplier_invoice_id' => $invoice->id, 'account_id' => SystemAccounts::id('payable'), 'kind' => 'credit_note', 'amount' => $toPayable / 100, 'paid_on' => now()->toDateString(), 'idempotency_key' => 'cn:' . $key, 'created_by' => $by, 'created_at' => now(), 'updated_at' => now()]);
                    DB::update('UPDATE suppliers SET due_amount = due_amount - ?, updated_at = NOW() WHERE id = ?', [$toPayable / 100, $line->supplier_id]);
                }
                if ($toAdvance > 0) {
                    $debits[] = ['account' => SystemAccounts::id('advance'), 'debit' => $toAdvance, 'supplier_id' => $line->supplier_id, 'memo' => "Supplier owes us (invoice {$invoice->invoice_no} already paid)"];
                    DB::update('UPDATE suppliers SET opening_balance = CAST(CAST(COALESCE(NULLIF(opening_balance, ""), "0") AS DECIMAL(15,2)) + ? AS CHAR), updated_at = NOW() WHERE id = ?', [$toAdvance / 100, $line->supplier_id]);
                }
            }
            $diff = $inventoryCents - $creditCents; // layer cost (landed, revalued) vs what the supplier credits
            if ($diff > 0) {
                $debits[] = ['account' => SystemAccounts::id('variance'), 'debit' => $diff, 'memo' => 'Return: cost not recovered from supplier'];
            } elseif ($diff < 0) {
                $lines[] = ['account' => SystemAccounts::id('variance'), 'credit' => -$diff, 'memo' => 'Return: credit above carrying cost'];
            }
            $j = $this->journal->post('sret:' . $key, now()->toDateString(), array_merge($lines, $debits), 'supplier_return', $line->id, "Return to supplier ({$line->receipt_no})", $by);

            return ['returned' => $qty, 'inventory_value' => $inventoryCents / 100, 'supplier_credit' => $creditCents / 100, 'journal_entry_id' => $j['journal_entry_id']];
        });
    }
}
