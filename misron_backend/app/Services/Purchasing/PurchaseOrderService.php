<?php

namespace App\Services\Purchasing;

use App\Services\Accounting\Money;
use App\Services\Inventory\DemandService;
use App\Services\Inventory\Idempotency;
use App\Services\Inventory\StockBalance;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Purchase orders: an intention to buy. Creating one never changes physical stock, FIFO layers, payables or the books.
 * 'draft' is saved work; 'ordered' counts as incoming stock for the requirement screen.
 * Lines may be linked to confirmed order demand (the requirement), which keeps two orders from buying the same units twice.
 */
class PurchaseOrderService
{
    /**
     * @param array{supplier_id:int, status?:string, expected_delivery_date?:string, note?:string, lines:array<int, array{product_id:int, variant?:string, qty:int, unit_cost:string|int|float, demand_ids?:int[]}>} $data
     */
    public function create(string $key, array $data, ?int $by = null): array
    {
        return Idempotency::once('purchase_order', $key, function () use ($key, $data, $by) {
            $status = $data['status'] ?? 'draft';
            if (!in_array($status, ['draft', 'ordered'], true)) {
                throw new InvalidArgumentException('A new purchase order is a draft or ordered.');
            }
            $supplierId = (int) $data['supplier_id'];
            if (!DB::table('suppliers')->where('id', $supplierId)->exists() || empty($data['lines'])) {
                throw new InvalidArgumentException('A supplier and at least one line are required.');
            }
            $rows = [];
            $total = 0;
            foreach ($data['lines'] as $i => $l) {
                $qty = (int) ($l['qty'] ?? 0);
                $cost = Money::micro($l['unit_cost'] ?? 0);
                if ($qty < 1 || $cost < 0 || (string) $qty !== (string) ($l['qty'] ?? '')) {
                    throw new InvalidArgumentException('Line ' . ($i + 1) . ': quantity must be a whole number of at least 1 and the price cannot be negative.');
                }
                $product = DB::table('products')->where('id', $l['product_id'])->first(['id', 'name']);
                if (!$product) {
                    throw new InvalidArgumentException('Line ' . ($i + 1) . ': unknown product.');
                }
                $rows[] = ['product' => $product, 'variant' => StockBalance::key($l['variant'] ?? ''), 'qty' => $qty, 'cost' => $cost, 'demand_ids' => $l['demand_ids'] ?? []];
                $total += (int) round(Money::cents($qty * $cost));
            }
            $ref = 'PO-' . now()->format('ymd') . '-' . strtoupper(substr(sha1($key), 0, 6));
            $poId = DB::table('purchase_orders')->insertGetId(['supplier_id' => $supplierId, 'reference_no' => $ref, 'purchase_date' => now()->toDateString(), 'date' => now()->toDateString(), 'business_location' => '', 'total_item' => array_sum(array_column($rows, 'qty')),
                'net_total_amount' => $total / 100, 'total_advance_amount' => 0, 'total_cash_amount' => 0, 'total_cheque_amount' => 0, 'total_bank_transfer_amount' => 0, 'advanced_amount' => 0, 'total_payment_due' => 0,
                'payment_note' => $data['note'] ?? null, 'expected_delivery_date' => $data['expected_delivery_date'] ?? null, 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);

            foreach ($rows as $r) {
                $detailId = DB::table('purchase_order_details')->insertGetId(['purchase_order_id' => $poId, 'product_id' => $r['product']->id, 'product_name' => $r['product']->name, 'variant' => $r['variant'],
                    'purchase_quantity' => $r['qty'], 'remaining_quantity' => $r['qty'], 'recieved_quantity' => 0, 'pending_quantity' => $r['qty'], 'buying_unit_cost' => round($r['cost'] / Money::SCALE, 2),
                    'total_cost' => Money::cents($r['qty'] * $r['cost']) / 100, 'product_tax' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $this->link($detailId, (int) $r['product']->id, $r['variant'], $r['qty'], $r['demand_ids']);
            }

            return ['purchase_order_id' => $poId, 'reference_no' => $ref, 'status' => $status];
        });
    }

    /** Draft -> ordered. Only now does it count as incoming. */
    public function markOrdered(int $poId): void
    {
        $changed = DB::table('purchase_orders')->where('id', $poId)->where('status', 'draft')->update(['status' => 'ordered', 'updated_at' => now()]);
        if (!$changed) {
            throw new InvalidArgumentException('Only a draft purchase order can be sent.');
        }
    }

    /** Cancels what has not arrived; received goods stay received. Linked requirements become open for purchase again. */
    public function cancel(int $poId): void
    {
        DB::transaction(function () use ($poId) {
            $po = DB::table('purchase_orders')->where('id', $poId)->lockForUpdate()->first();
            if (!$po || !in_array($po->status, ['draft', 'ordered', 'partially_received'], true)) {
                throw new InvalidArgumentException('This purchase order cannot be cancelled.');
            }
            $details = DB::table('purchase_order_details')->where('purchase_order_id', $poId)->pluck('id');
            DB::table('purchase_requirement_links')->whereIn('purchase_order_detail_id', $details)->where('status', 'open')->update(['status' => 'cancelled', 'updated_at' => now()]);
            DB::table('purchase_order_details')->where('purchase_order_id', $poId)->update(['pending_quantity' => 0, 'remaining_quantity' => 0, 'updated_at' => now()]);
            DB::table('purchase_orders')->where('id', $poId)->update(['status' => $po->status === 'partially_received' ? 'received' : 'cancelled', 'updated_at' => now()]);
        });
    }

    /** Links PO quantity to confirmed demand without exceeding what is still uncovered (no double purchase). */
    private function link(int $detailId, int $productId, string $variant, int $qty, array $demandIds): void
    {
        foreach ($demandIds as $demandId) {
            if ($qty < 1) {
                break;
            }
            $d = DB::table('order_demands')->where('id', $demandId)->where('product_id', $productId)->where('variant', $variant)->where('status', 'open')->lockForUpdate()->first();
            if (!$d) {
                throw new InvalidArgumentException("Requirement {$demandId} does not match this product or is no longer open.");
            }
            $uncovered = PurchaseRequirementsService::uncoveredForDemand($d);
            $take = min($uncovered, $qty);
            if ($take < 1) {
                throw new InvalidArgumentException("Requirement {$demandId} is already covered by physical stock or by another purchase order; buying it again would double count.");
            }
            DB::table('purchase_requirement_links')->insert(['order_demand_id' => $d->id, 'purchase_order_detail_id' => $detailId, 'qty_linked' => $take, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
            $qty -= $take;
        }
    }
}
