<?php

namespace App\Services\Purchasing;

use App\Services\Accounting\JournalService;
use App\Services\Accounting\Money;
use App\Services\Accounting\SystemAccounts;
use App\Services\Inventory\Idempotency;
use App\Services\Inventory\StockBalance;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Supplier liabilities, in three separate operations (each idempotent, each posts one balanced journal entry):
 *   recordInvoice : Dr GRNI (goods received) [+/- Dr/Cr Purchase Price Variance] / Cr Accounts Payable
 *   pay           : Dr Accounts Payable / Cr cash|bank|mobile account            (a payment is never an expense)
 *   advance       : Dr Supplier Advances / Cr cash|bank|mobile account           (money paid before any invoice)
 *   applyAdvance  : Dr Accounts Payable / Cr Supplier Advances                   (advance used against an invoice)
 * suppliers.due_amount and suppliers.opening_balance (the legacy "advance paid" figure) are kept in step so the
 * existing supplier screens stay right; the journal is the source of truth and `inventory:reconcile` compares them.
 */
class SupplierLedgerService
{
    public function __construct(private JournalService $journal)
    {
    }

    /**
     * @param array{supplier_id:int, goods_receipt_id?:int, invoice_no:string, invoice_date?:string, subtotal:string|int|float, discount?:string|int|float, tax?:string|int|float, freight?:string|int|float} $d
     */
    public function recordInvoice(string $key, array $d, ?int $by = null): array
    {
        return Idempotency::once('supplier_invoice', $key, function () use ($key, $d, $by) {
            $supplierId = (int) $d['supplier_id'];
            DB::table('suppliers')->where('id', $supplierId)->lockForUpdate()->first() ?: throw new InvalidArgumentException('Unknown supplier.');
            $no = trim((string) ($d['invoice_no'] ?? ''));
            if ($no === '') {
                throw new InvalidArgumentException('The supplier invoice number is required.');
            }
            if (DB::table('supplier_invoices')->where('supplier_id', $supplierId)->where('invoice_no', $no)->exists()) {
                throw new InvalidArgumentException("Invoice {$no} was already recorded for this supplier.");
            }
            $sub = Money::micro($d['subtotal']);
            $disc = Money::micro($d['discount'] ?? 0);
            $tax = Money::micro($d['tax'] ?? 0);
            $freight = Money::micro($d['freight'] ?? 0);
            $total = $sub - $disc + $tax + $freight;
            if ($sub <= 0 || $disc < 0 || $tax < 0 || $freight < 0 || $total <= 0) {
                throw new InvalidArgumentException('Invoice amounts are not valid.');
            }

            $receipt = null;
            $goods = 0;
            if (!empty($d['goods_receipt_id'])) {
                $receipt = DB::table('goods_receipts')->where('id', $d['goods_receipt_id'])->lockForUpdate()->first();
                if (!$receipt || (int) $receipt->supplier_id !== $supplierId || $receipt->status !== 'posted') {
                    throw new InvalidArgumentException('That goods receipt is not available for this supplier.');
                }
                if ($receipt->invoice_status === 'invoiced') {
                    throw new InvalidArgumentException('That goods receipt already has an invoice.');
                }
                $goods = Money::micro($receipt->goods_total);
            }

            $totalCents = Money::cents($total);
            $goodsCents = Money::cents($goods);
            // Price difference on the goods themselves (after discount) vs what was received at. Tax and freight on the invoice are not
            // part of that: they are a period cost (Purchase Price Variance). The goods difference is allocated to the receipt's units:
            // units still on the shelf are REVALUED (inventory), units already sold go to the variance account; sold history is not rewritten.
            $priceDiff = $receipt ? Money::cents($sub - $disc) - $goodsCents : 0;
            $extra = $totalCents - $goodsCents - $priceDiff; // tax + freight (+ everything when there is no receipt)
            $reval = $receipt ? $this->revalue($receipt->id, $priceDiff, $no) : ['inventory' => 0, 'variance' => 0, 'units' => 0];
            $variance = $reval['variance'] + $extra;
            $lines = [['account' => SystemAccounts::id('payable'), 'credit' => $totalCents, 'supplier_id' => $supplierId, 'memo' => "Invoice {$no}"]];
            if ($goodsCents > 0) {
                $lines[] = ['account' => SystemAccounts::id('grni'), 'debit' => $goodsCents, 'supplier_id' => $supplierId, 'memo' => "Invoice {$no}"];
            }
            if ($reval['inventory'] > 0) {
                $lines[] = ['account' => SystemAccounts::id('inventory'), 'debit' => $reval['inventory'], 'memo' => "Invoice {$no}: revalued {$reval['units']} unsold unit(s)"];
            } elseif ($reval['inventory'] < 0) {
                $lines[] = ['account' => SystemAccounts::id('inventory'), 'credit' => -$reval['inventory'], 'memo' => "Invoice {$no}: revalued {$reval['units']} unsold unit(s)"];
            }
            if ($variance > 0) {
                $lines[] = ['account' => SystemAccounts::id('variance'), 'debit' => $variance, 'memo' => "Invoice {$no}: price difference on sold units, tax, freight"];
            } elseif ($variance < 0) {
                $lines[] = ['account' => SystemAccounts::id('variance'), 'credit' => -$variance, 'memo' => "Invoice {$no}: price difference on sold units, tax, freight"];
            }
            // Without a receipt there is nothing to clear: the whole invoice goes to the variance account until it is matched.
            $date = $d['invoice_date'] ?? now()->toDateString();
            $j = $this->journal->post('inv:' . $key, $date, $lines, 'supplier_invoice', null, "Supplier invoice {$no}", $by);

            $id = DB::table('supplier_invoices')->insertGetId(['supplier_id' => $supplierId, 'goods_receipt_id' => $receipt->id ?? null, 'invoice_no' => $no, 'invoice_date' => $date, 'subtotal' => Money::decimal($sub),
                'discount' => Money::decimal($disc), 'tax' => Money::decimal($tax), 'freight' => Money::decimal($freight), 'total' => Money::decimal($total), 'price_variance' => Money::decimal(($priceDiff + $extra) * 100), 'journal_entry_id' => $j['journal_entry_id'],
                'status' => 'open', 'idempotency_key' => $key, 'created_by' => $by, 'created_at' => now(), 'updated_at' => now()]);
            if ($receipt) {
                DB::table('goods_receipts')->where('id', $receipt->id)->update(['invoice_status' => 'invoiced', 'updated_at' => now()]);
            }
            DB::update('UPDATE suppliers SET due_amount = due_amount + ?, updated_at = NOW() WHERE id = ?', [$totalCents / 100, $supplierId]);

            return ['supplier_invoice_id' => $id, 'total' => Money::decimal($total), 'price_variance' => Money::decimal(($priceDiff + $extra) * 100, 2), 'revalued_inventory' => Money::decimal($reval['inventory'] * 100, 2), 'variance_expensed' => Money::decimal($variance * 100, 2), 'journal_entry_id' => $j['journal_entry_id']];
        });
    }

    /**
     * Spreads a goods price difference (cents) over the receipt's lines by goods value and splits each share between the units still in
     * the line's FIFO layer (inventory revaluation: the layer's unit cost moves) and the units already gone (variance account).
     *
     * @return array{inventory:int, variance:int, units:int} cents / units
     */
    private function revalue(int $receiptId, int $diffCents, string $invoiceNo): array
    {
        $out = ['inventory' => 0, 'variance' => 0, 'units' => 0];
        if ($diffCents === 0) {
            return $out;
        }
        $lines = DB::table('goods_receipt_lines')->where('goods_receipt_id', $receiptId)->orderBy('id')->get();
        $weights = $lines->mapWithKeys(fn ($l) => [$l->id => max(1, (int) ($l->qty * Money::micro($l->unit_cost) / 100))])->all();
        $shares = Money::allocate(abs($diffCents), $weights);
        $sign = $diffCents < 0 ? -1 : 1;
        foreach ($lines as $l) {
            $share = $sign * $shares[$l->id];
            $layer = $l->purchase_product_id ? DB::table('purchase_products')->where('id', $l->purchase_product_id)->lockForUpdate()->first() : null;
            $unsold = $layer ? max(0, min((int) $layer->remaining_qty, (int) $l->qty)) : 0;
            $toInventory = $l->qty > 0 ? $sign * intdiv(abs($share) * $unsold * 2 + (int) $l->qty, 2 * (int) $l->qty) : 0;
            if ($unsold > 0 && $toInventory !== 0) {
                // the unit cost of the unsold units moves; layers already consumed (sold) are untouched
                $newCost = Money::micro($layer->buying_unit_cost) + (int) round($toInventory * 100 / $unsold);
                DB::table('purchase_products')->where('id', $layer->id)->update(['buying_unit_cost' => Money::decimal(max(0, $newCost)), 'updated_at' => now()]);
                StockBalance::movement(['product_id' => $l->product_id, 'variant' => $l->variant, 'type' => 'revaluation', 'source_type' => 'goods_receipt_line', 'source_id' => $l->id,
                    'note' => "Invoice {$invoiceNo}: {$unsold} unsold unit(s) revalued by " . number_format($toInventory / 100, 2, '.', '')]);
            }
            $out['inventory'] += $toInventory;
            $out['variance'] += $share - $toInventory;
            $out['units'] += $unsold;
        }

        return $out;
    }

    /** Pays (part of) one invoice from a cash/bank/mobile account. */
    public function pay(string $key, int $invoiceId, int $accountId, $amount, ?string $paidOn = null, ?int $by = null): array
    {
        return Idempotency::once('supplier_payment', $key, function () use ($key, $invoiceId, $accountId, $amount, $paidOn, $by) {
            $inv = DB::table('supplier_invoices')->where('id', $invoiceId)->lockForUpdate()->first() ?: throw new InvalidArgumentException('Unknown invoice.');
            $cents = Money::cents(Money::micro($amount));
            $this->assertPayable($inv, $cents);
            $this->assertFunds($accountId, $cents);
            $date = $paidOn ?? now()->toDateString();
            $j = $this->journal->post('pay:' . $key, $date, [
                ['account' => SystemAccounts::id('payable'), 'debit' => $cents, 'supplier_id' => $inv->supplier_id, 'memo' => "Payment of {$inv->invoice_no}"],
                ['account' => $accountId, 'credit' => $cents, 'memo' => "Supplier payment {$inv->invoice_no}"],
            ], 'supplier_payment', $invoiceId, "Payment for invoice {$inv->invoice_no}", $by);
            $this->applyToInvoice($inv, $cents);
            $id = DB::table('supplier_invoice_payments')->insertGetId(['supplier_id' => $inv->supplier_id, 'supplier_invoice_id' => $invoiceId, 'account_id' => $accountId, 'kind' => 'payment', 'amount' => $cents / 100, 'paid_on' => $date,
                'journal_entry_id' => $j['journal_entry_id'], 'idempotency_key' => $key, 'created_by' => $by, 'created_at' => now(), 'updated_at' => now()]);

            return ['payment_id' => $id, 'paid' => $cents / 100, 'journal_entry_id' => $j['journal_entry_id']];
        });
    }

    /** One payment across several invoices of one supplier; each allocation is its own idempotent payment. */
    public function payMany(string $key, int $supplierId, int $accountId, array $allocations, ?string $paidOn = null, ?int $by = null): array
    {
        return Idempotency::once('supplier_payment_batch', $key, function () use ($key, $supplierId, $accountId, $allocations, $paidOn, $by) {
            $ids = [];
            foreach ($allocations as $i => $a) {
                $inv = DB::table('supplier_invoices')->where('id', $a['invoice_id'])->first();
                if (!$inv || (int) $inv->supplier_id !== $supplierId) {
                    throw new InvalidArgumentException('An allocation points to an invoice of another supplier.');
                }
                $ids[] = $this->pay("{$key}#{$i}", (int) $a['invoice_id'], $accountId, $a['amount'], $paidOn, $by)['payment_id'];
            }

            return ['payment_ids' => $ids];
        });
    }

    /** Money paid to a supplier before there is an invoice. */
    public function advance(string $key, int $supplierId, int $accountId, $amount, ?string $paidOn = null, ?int $by = null): array
    {
        return Idempotency::once('supplier_advance', $key, function () use ($key, $supplierId, $accountId, $amount, $paidOn, $by) {
            DB::table('suppliers')->where('id', $supplierId)->lockForUpdate()->first() ?: throw new InvalidArgumentException('Unknown supplier.');
            $cents = Money::cents(Money::micro($amount));
            if ($cents <= 0) {
                throw new InvalidArgumentException('The advance must be more than zero.');
            }
            $this->assertFunds($accountId, $cents);
            $date = $paidOn ?? now()->toDateString();
            $j = $this->journal->post('adv:' . $key, $date, [
                ['account' => SystemAccounts::id('advance'), 'debit' => $cents, 'supplier_id' => $supplierId, 'memo' => 'Advance to supplier'],
                ['account' => $accountId, 'credit' => $cents, 'memo' => 'Supplier advance'],
            ], 'supplier_advance', $supplierId, 'Supplier advance', $by);
            $this->moveAdvance($supplierId, $cents);
            $id = DB::table('supplier_invoice_payments')->insertGetId(['supplier_id' => $supplierId, 'supplier_invoice_id' => null, 'account_id' => $accountId, 'kind' => 'advance', 'amount' => $cents / 100, 'paid_on' => $date,
                'journal_entry_id' => $j['journal_entry_id'], 'idempotency_key' => $key, 'created_by' => $by, 'created_at' => now(), 'updated_at' => now()]);

            return ['payment_id' => $id, 'advance' => $cents / 100];
        });
    }

    /** Uses part of the supplier's advance against an invoice. */
    public function applyAdvance(string $key, int $invoiceId, $amount, ?int $by = null): array
    {
        return Idempotency::once('supplier_advance_apply', $key, function () use ($key, $invoiceId, $amount, $by) {
            $inv = DB::table('supplier_invoices')->where('id', $invoiceId)->lockForUpdate()->first() ?: throw new InvalidArgumentException('Unknown invoice.');
            $supplier = DB::table('suppliers')->where('id', $inv->supplier_id)->lockForUpdate()->first();
            $cents = Money::cents(Money::micro($amount));
            $this->assertPayable($inv, $cents);
            if ($cents > (int) round(((float) $supplier->opening_balance) * 100)) {
                throw new InvalidArgumentException('That is more than the advance this supplier holds.');
            }
            $j = $this->journal->post('advapp:' . $key, now()->toDateString(), [
                ['account' => SystemAccounts::id('payable'), 'debit' => $cents, 'supplier_id' => $inv->supplier_id, 'memo' => "Advance applied to {$inv->invoice_no}"],
                ['account' => SystemAccounts::id('advance'), 'credit' => $cents, 'supplier_id' => $inv->supplier_id, 'memo' => "Advance applied to {$inv->invoice_no}"],
            ], 'supplier_advance_applied', $invoiceId, "Advance applied to {$inv->invoice_no}", $by);
            $this->moveAdvance((int) $inv->supplier_id, -$cents);
            $this->applyToInvoice($inv, $cents);
            DB::table('supplier_invoice_payments')->insert(['supplier_id' => $inv->supplier_id, 'supplier_invoice_id' => $invoiceId, 'account_id' => SystemAccounts::id('advance'), 'kind' => 'advance_applied', 'amount' => $cents / 100,
                'paid_on' => now()->toDateString(), 'journal_entry_id' => $j['journal_entry_id'], 'idempotency_key' => $key, 'created_by' => $by, 'created_at' => now(), 'updated_at' => now()]);

            return ['applied' => $cents / 100];
        });
    }

    private function assertPayable(object $inv, int $cents): void
    {
        $owed = (int) round(((float) $inv->total - (float) $inv->paid) * 100);
        if ($inv->status === 'void' || $cents <= 0 || $cents > $owed) {
            throw new InvalidArgumentException('The amount must be more than zero and no more than what is still owed (' . number_format($owed / 100, 2, '.', '') . ').');
        }
    }

    private function assertFunds(int $accountId, int $cents): void
    {
        $a = DB::table('chart_of_accounts')->where('id', $accountId)->lockForUpdate()->first();
        if (!$a || $a->status !== 'active' || !in_array($a->sub_type, ['cash', 'bank', 'mobile_bank'], true)) {
            throw new InvalidArgumentException('Choose an active cash, bank or mobile banking account to pay from.');
        }
        if ((int) round(((float) $a->current_balance) * 100) < $cents) {
            throw new InvalidArgumentException('Insufficient balance in the selected account.');
        }
    }

    private function applyToInvoice(object $inv, int $cents): void
    {
        $paid = (int) round((float) $inv->paid * 100) + $cents;
        $total = (int) round((float) $inv->total * 100);
        DB::table('supplier_invoices')->where('id', $inv->id)->update(['paid' => $paid / 100, 'status' => $paid >= $total ? 'paid' : 'partial', 'updated_at' => now()]);
        DB::update('UPDATE suppliers SET due_amount = due_amount - ?, updated_at = NOW() WHERE id = ?', [$cents / 100, $inv->supplier_id]);
    }

    private function moveAdvance(int $supplierId, int $cents): void
    {
        DB::update('UPDATE suppliers SET opening_balance = CAST(CAST(COALESCE(NULLIF(opening_balance, ""), "0") AS DECIMAL(15,2)) + ? AS CHAR), updated_at = NOW() WHERE id = ?', [$cents / 100, $supplierId]);
    }

    /** Outstanding per supplier, from invoices (the new source of truth) */
    public static function outstanding(int $supplierId): string
    {
        return number_format((float) DB::table('supplier_invoices')->where('supplier_id', $supplierId)->where('status', '!=', 'void')->selectRaw('COALESCE(SUM(total - paid),0) o')->value('o'), 2, '.', '');
    }
}
