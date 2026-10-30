<?php

namespace App\Services\Inventory;

use App\Models\OrderDetail;
use App\Services\Inventory\Exceptions\InsufficientPhysicalStock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns confirmed demand for v2 order lines: confirm -> allocate -> dispatch, release, quantity change, returns.
 * Every public method is one transaction, takes row locks in a fixed order (stock_balances row, then demand rows),
 * and is safe to repeat.
 *
 * Invariants (checked by `inventory:reconcile` and the test suite):
 *   outstanding(d)  = qty_confirmed - qty_dispatched - qty_cancelled              >= 0
 *   qty_allocated(d) <= outstanding(d)
 *   stock_balances.demand    = sum(outstanding) over the item's open demands
 *   stock_balances.allocated = sum(qty_allocated) over the item's open demands    <= product_stocks.qty (on-hand)
 *   product_stocks.qty never goes below zero through this service.
 */
class DemandService
{
    public function __construct(private StockLedger $ledger)
    {
    }

    private function eligible(OrderDetail $d): bool
    {
        return $d->id && InventoryMode::lineIsV2((int) $d->id) && (int) $d->quantity > 0 && !$this->isDigital((int) $d->product_id);
    }

    private function isDigital(int $productId): bool
    {
        return (int) DB::table('products')->where('id', $productId)->value('digital') === 1;
    }

    private function demandRow(int $detailId, bool $lock = true): ?object
    {
        $q = DB::table('order_demands')->where('order_detail_id', $detailId);

        return $lock ? $q->lockForUpdate()->first() : $q->first();
    }

    public static function outstanding(object $d): int
    {
        return (int) $d->qty_confirmed - (int) $d->qty_dispatched - (int) $d->qty_cancelled;
    }

    /** Creates confirmed demand for the line, once. Repeating it, or confirming a line that is already confirmed, changes nothing. */
    public function confirm(OrderDetail $detail, ?int $by = null): ?object
    {
        if (!$this->eligible($detail)) {
            return null;
        }

        return DB::transaction(function () use ($detail, $by) {
            $variant = StockBalance::key($detail->variation);
            $productId = (int) $detail->product_id;
            StockBalance::lock($productId, $variant);

            $existing = $this->demandRow((int) $detail->id);
            if ($existing) {
                // Un-confirmed (back to pending) and confirmed again before anything shipped: reopen the same row.
                if ($existing->status === 'cancelled' && (int) $existing->qty_dispatched === 0) {
                    $qty = (int) $detail->quantity;
                    DB::table('order_demands')->where('id', $existing->id)->update(['qty_confirmed' => $qty, 'qty_cancelled' => 0, 'qty_allocated' => 0, 'status' => 'open', 'confirmed_at' => now(), 'updated_at' => now()]);
                    StockBalance::change($productId, $variant, demand: $qty);
                    StockBalance::movement(['product_id' => $productId, 'variant' => $variant, 'type' => 'demand_confirmed', 'demand_delta' => $qty, 'source_type' => 'order', 'source_id' => $detail->order_id, 'order_detail_id' => $detail->id, 'created_by' => $by, 'note' => 'reopened']);
                    $this->allocate($productId, $variant);
                }

                return $this->demandRow((int) $detail->id, false);
            }

            $qty = (int) $detail->quantity;
            $id = DB::table('order_demands')->insertGetId(['order_id' => $detail->order_id, 'order_detail_id' => $detail->id, 'product_id' => $productId, 'variant' => $variant,
                'qty_confirmed' => $qty, 'status' => 'open', 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            StockBalance::change($productId, $variant, demand: $qty);
            StockBalance::movement(['product_id' => $productId, 'variant' => $variant, 'type' => 'demand_confirmed', 'demand_delta' => $qty, 'source_type' => 'order', 'source_id' => $detail->order_id, 'order_detail_id' => $detail->id, 'created_by' => $by]);
            $this->allocate($productId, $variant);

            return DB::table('order_demands')->where('id', $id)->first();
        });
    }

    /**
     * Earmarks free physical units for open demand, oldest confirmation first. Called after every event that
     * can free units (receipt, release, return) and after every confirmation. Idempotent.
     *
     * @return int units newly allocated
     */
    public function allocate(int $productId, $variant): int
    {
        return DB::transaction(function () use ($productId, $variant) {
            $variant = StockBalance::key($variant);
            $bal = StockBalance::lock($productId, $variant);
            $free = StockBalance::onHandLocked($productId, $variant) - (int) $bal->allocated;
            if ($free <= 0) {
                return 0;
            }

            $total = 0;
            $demands = DB::table('order_demands')->where('product_id', $productId)->where('variant', $variant)->where('status', 'open')
                ->orderBy('confirmed_at')->orderBy('id')->lockForUpdate()->get();
            foreach ($demands as $d) {
                if ($free < 1) {
                    break;
                }
                $need = self::outstanding($d) - (int) $d->qty_allocated;
                if ($need < 1) {
                    continue;
                }
                $take = min($need, $free);
                DB::table('order_demands')->where('id', $d->id)->update(['qty_allocated' => (int) $d->qty_allocated + $take, 'updated_at' => now()]);
                $mid = StockBalance::movement(['product_id' => $productId, 'variant' => $variant, 'type' => 'allocated', 'allocated_delta' => $take, 'source_type' => 'order', 'source_id' => $d->order_id, 'order_detail_id' => $d->order_detail_id]);
                DB::table('stock_allocations')->insert(['order_demand_id' => $d->id, 'stock_movement_id' => $mid, 'action' => 'allocate', 'qty' => $take, 'created_at' => now()]);
                $free -= $take;
                $total += $take;
            }
            if ($total > 0) {
                StockBalance::change($productId, $variant, allocated: $total);
            }

            return $total;
        });
    }

    /**
     * The goods leave the warehouse: physical stock, outstanding demand and allocation fall together, FIFO layers are
     * consumed once, and the cost lands on the order line. Dispatching more than is allocated is refused.
     *
     * @return array{dispatched:int, cost:float, uncosted:int}
     * @throws InsufficientPhysicalStock
     */
    public function dispatch(OrderDetail $detail, ?int $qty = null, ?int $by = null): array
    {
        if (!$this->eligible($detail)) {
            return ['dispatched' => 0, 'cost' => 0.0, 'uncosted' => 0];
        }
        $this->confirm($detail, $by);

        return DB::transaction(function () use ($detail, $qty, $by) {
            $productId = (int) $detail->product_id;
            $variant = StockBalance::key($detail->variation);
            StockBalance::lock($productId, $variant);
            $d = $this->demandRow((int) $detail->id);
            $outstanding = self::outstanding($d);
            $qty = $qty ?? $outstanding;
            if ($qty < 1) {
                return ['dispatched' => 0, 'cost' => 0.0, 'uncosted' => 0];
            }
            if ($qty > $outstanding) {
                throw new InvalidArgumentException("Cannot dispatch {$qty}: only {$outstanding} outstanding on this line.");
            }
            if ((int) $d->qty_allocated < $qty) {
                $this->allocate($productId, $variant);
                $d = $this->demandRow((int) $detail->id);
            }
            if ((int) $d->qty_allocated < $qty) {
                $name = DB::table('products')->where('id', $productId)->value('name');
                throw new InsufficientPhysicalStock(trim("{$name} {$variant}") . ': ' . (int) $d->qty_allocated . ' unit(s) in stock for this order, ' . $qty . ' needed. Receive the purchase first.',
                    ['product_id' => $productId, 'variant' => $variant, 'needed' => $qty, 'allocated' => (int) $d->qty_allocated]);
            }

            // 1. physical stock leaves (never below zero)
            if (!$this->ledger->adjust($productId, $variant, -$qty, true)) { // dispatch is the one operation allowed to consume allocated units
                throw new InsufficientPhysicalStock('Physical stock is lower than the allocation: run inventory:reconcile.', ['product_id' => $productId, 'variant' => $variant]);
            }
            DB::table('products')->where('id', $productId)->where('current_stock', '>=', $qty)->decrement('current_stock', $qty);

            // 2. FIFO layers: consume what exists exactly once; units without a layer stay explicitly uncosted
            $layers = StockBalance::layersLocked($productId, $variant);
            $costed = min($qty, max(0, $layers));
            $consumed = ['total_cost' => 0.0, 'batches' => []];
            if ($costed > 0) {
                $consumed = $this->ledger->consumeBatches($productId, $variant, $costed);
            }
            $uncosted = $qty - $costed;

            // 3. order line cost (weighted average over everything dispatched so far)
            $this->recordLineCost($detail, $consumed, $uncosted);

            // 4. demand, allocation, counters, ledger
            $newDispatched = (int) $d->qty_dispatched + $qty;
            $left = $outstanding - $qty;
            DB::table('order_demands')->where('id', $d->id)->update(['qty_dispatched' => $newDispatched, 'qty_allocated' => (int) $d->qty_allocated - $qty, 'status' => $left === 0 ? 'fulfilled' : 'open', 'updated_at' => now()]);
            StockBalance::change($productId, $variant, demand: -$qty, allocated: -$qty);
            $mid = StockBalance::movement(['product_id' => $productId, 'variant' => $variant, 'type' => 'dispatched', 'on_hand_delta' => -$qty, 'demand_delta' => -$qty, 'allocated_delta' => -$qty, 'source_type' => 'order', 'source_id' => $detail->order_id, 'order_detail_id' => $detail->id, 'created_by' => $by]);
            DB::table('stock_allocations')->insert(['order_demand_id' => $d->id, 'stock_movement_id' => $mid, 'action' => 'dispatch', 'qty' => $qty,
                'fifo_batch_info' => $consumed['batches'] ? json_encode(array_map(fn ($b) => ['id' => $b['purchase_product_id'], 'qty' => $b['qty'], 'cost' => $b['unit_price']], $consumed['batches'])) : null, 'created_at' => now()]);

            return ['dispatched' => $qty, 'cost' => (float) $consumed['total_cost'], 'uncosted' => $uncosted];
        });
    }

    private function recordLineCost(OrderDetail $detail, array $consumed, int $uncosted): void
    {
        $row = DB::table('order_details')->where('id', $detail->id)->lockForUpdate()->first(['fifo_batch_info', 'price', 'quantity', 'purchase_id', 'supplier_id', 'cost_status']);
        $info = $row->fifo_batch_info ? (json_decode($row->fifo_batch_info, true) ?: []) : [];
        foreach ($consumed['batches'] as $b) {
            $info[] = ['id' => $b['purchase_product_id'], 'qty' => $b['qty'], 'cost' => $b['unit_price']];
        }
        $units = array_sum(array_column($info, 'qty'));
        $total = array_sum(array_map(fn ($e) => $e['qty'] * (float) $e['cost'], $info));
        $dispatched = (int) DB::table('order_demands')->where('order_detail_id', $detail->id)->value('qty_dispatched') + ($consumed['batches'] ? array_sum(array_column($consumed['batches'], 'qty')) + $uncosted : $uncosted);
        $everUncosted = $uncosted > 0 || $row->cost_status === 'uncosted';
        $update = ['fifo_batch_info' => $info ? json_encode($info) : null, 'buying_price' => $units ? round($total / $units, 4) : 0,
            'cost_status' => $everUncosted ? 'uncosted' : 'final', 'updated_at' => now()];
        if (!$everUncosted && $dispatched >= (int) $row->quantity) {
            $update['profit'] = round((float) $row->price - $total, 2); // only when every unit has a real cost layer
        }
        if (!$row->purchase_id && $consumed['batches']) {
            $update['purchase_id'] = $consumed['batches'][0]['purchase_id'];
            $update['supplier_id'] = $consumed['batches'][0]['supplier_id'];
        }
        DB::table('order_details')->where('id', $detail->id)->update($update);
    }

    /**
     * Releases outstanding (undispatched) demand: a cancelled order, a cancelled quantity, or an order sent back to pending.
     * Dispatched units are never touched: goods that left need a return receipt, not a fake restock.
     *
     * @return int units released
     */
    public function release(OrderDetail $detail, ?int $qty = null, ?int $by = null): int
    {
        if (!$detail->id || !InventoryMode::lineIsV2((int) $detail->id)) {
            return 0;
        }

        return DB::transaction(function () use ($detail, $qty, $by) {
            $productId = (int) $detail->product_id;
            $variant = StockBalance::key($detail->variation);
            StockBalance::lock($productId, $variant);
            $d = $this->demandRow((int) $detail->id);
            if (!$d) {
                return 0; // never confirmed: nothing was held
            }
            $outstanding = self::outstanding($d);
            $qty = min($qty ?? $outstanding, $outstanding);
            if ($qty < 1) {
                return 0;
            }
            $after = $outstanding - $qty;
            $excess = max(0, (int) $d->qty_allocated - $after);
            DB::table('order_demands')->where('id', $d->id)->update(['qty_cancelled' => (int) $d->qty_cancelled + $qty, 'qty_allocated' => (int) $d->qty_allocated - $excess,
                'status' => $after > 0 ? 'open' : ((int) $d->qty_dispatched > 0 ? 'fulfilled' : 'cancelled'), 'updated_at' => now()]);
            StockBalance::change($productId, $variant, demand: -$qty, allocated: -$excess);
            $mid = StockBalance::movement(['product_id' => $productId, 'variant' => $variant, 'type' => 'demand_released', 'demand_delta' => -$qty, 'allocated_delta' => -$excess, 'source_type' => 'order', 'source_id' => $detail->order_id, 'order_detail_id' => $detail->id, 'created_by' => $by]);
            if ($excess > 0) {
                DB::table('stock_allocations')->insert(['order_demand_id' => $d->id, 'stock_movement_id' => $mid, 'action' => 'deallocate', 'qty' => $excess, 'created_at' => now()]);
            }
            if ($after === 0) {
                DB::table('purchase_requirement_links')->where('order_demand_id', $d->id)->where('status', 'open')->update(['status' => 'cancelled', 'updated_at' => now()]);
            }
            $this->allocate($productId, $variant); // freed physical units go to the next waiting order

            return $qty;
        });
    }

    /** The order line's quantity changed after confirmation: move demand by the difference, never below what already shipped. */
    public function setQuantity(OrderDetail $detail, int $newQty, ?int $by = null): void
    {
        if (!$detail->id || !InventoryMode::lineIsV2((int) $detail->id)) {
            return;
        }
        DB::transaction(function () use ($detail, $newQty, $by) {
            $productId = (int) $detail->product_id;
            $variant = StockBalance::key($detail->variation);
            StockBalance::lock($productId, $variant);
            $d = $this->demandRow((int) $detail->id);
            if (!$d) {
                return; // pending line: nothing held yet
            }
            $effective = (int) $d->qty_confirmed - (int) $d->qty_cancelled;
            $diff = $newQty - $effective;
            if ($diff === 0) {
                return;
            }
            if ($newQty < (int) $d->qty_dispatched) {
                throw new InvalidArgumentException("{$d->qty_dispatched} unit(s) already shipped; the quantity cannot go below that.");
            }
            if ($diff < 0) {
                $this->release($detail, -$diff, $by);

                return;
            }
            DB::table('order_demands')->where('id', $d->id)->update(['qty_confirmed' => (int) $d->qty_confirmed + $diff, 'status' => 'open', 'updated_at' => now()]);
            StockBalance::change($productId, $variant, demand: $diff);
            StockBalance::movement(['product_id' => $productId, 'variant' => $variant, 'type' => 'demand_adjusted', 'demand_delta' => $diff, 'source_type' => 'order', 'source_id' => $detail->order_id, 'order_detail_id' => $detail->id, 'created_by' => $by]);
            $this->allocate($productId, $variant);
        });
    }

    /**
     * Goods came back and were inspected. Only 'resellable' units become on-hand stock (and return to their FIFO layers);
     * 'damaged' and 'rejected' are recorded but never become sellable.
     */
    public function receiveReturn(OrderDetail $detail, int $qty, string $condition, string $key, ?int $by = null, ?string $note = null): array
    {
        if (!in_array($condition, ['resellable', 'damaged', 'rejected'], true)) {
            throw new InvalidArgumentException('Condition must be resellable, damaged or rejected.');
        }
        if ($qty < 1) {
            throw new InvalidArgumentException('Return quantity must be at least 1.');
        }

        return Idempotency::once('return_receipt', $key, function () use ($detail, $qty, $condition, $key, $by, $note) {
            $productId = (int) $detail->product_id;
            $variant = StockBalance::key($detail->variation);
            StockBalance::lock($productId, $variant);
            $d = $this->demandRow((int) $detail->id);
            $returned = (int) DB::table('stock_return_receipts')->where('order_detail_id', $detail->id)->sum('qty');
            if (!$d || $qty > (int) $d->qty_dispatched - $returned) {
                throw new InvalidArgumentException('Only goods that were actually dispatched can be received back (' . max(0, (int) ($d->qty_dispatched ?? 0) - $returned) . ' left to receive).');
            }

            $mid = null;
            if ($condition === 'resellable') {
                $this->ledger->adjust($productId, $variant, $qty);
                DB::table('products')->where('id', $productId)->increment('current_stock', $qty);
                $this->ledger->returnBatchUnits($productId, $variant, $qty, $detail instanceof OrderDetail ? OrderDetail::find($detail->id) : null);
                $mid = StockBalance::movement(['product_id' => $productId, 'variant' => $variant, 'type' => 'return_received', 'on_hand_delta' => $qty, 'source_type' => 'order', 'source_id' => $detail->order_id, 'order_detail_id' => $detail->id, 'created_by' => $by, 'note' => $note]);
            } else {
                StockBalance::change($productId, $variant, damaged: $qty);
                $mid = StockBalance::movement(['product_id' => $productId, 'variant' => $variant, 'type' => 'return_damaged', 'source_type' => 'order', 'source_id' => $detail->order_id, 'order_detail_id' => $detail->id, 'created_by' => $by, 'note' => $condition . ($note ? ": {$note}" : '')]);
            }
            DB::table('stock_return_receipts')->insert(['order_detail_id' => $detail->id, 'qty' => $qty, 'condition' => $condition, 'stock_movement_id' => $mid, 'note' => $note, 'idempotency_key' => $key, 'received_by' => $by, 'created_at' => now(), 'updated_at' => now()]);
            if ($condition === 'resellable') {
                $this->allocate($productId, $variant);
            }

            return ['received' => $qty, 'condition' => $condition];
        });
    }
}
