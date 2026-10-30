<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

/**
 * Per product-and-variation counters for the v2 model, and the one place that answers "how much is there".
 *
 *   on_hand     = product_stocks.qty                         physical units in the warehouse
 *   demand      = stock_balances.demand                      outstanding CONFIRMED order quantity
 *   allocated   = stock_balances.allocated                   part of that demand with physical units earmarked
 *   free        = on_hand - allocated                        physical units not yet earmarked for any order
 *   available   = on_hand - demand                           what may still be sold; negative = confirmed shortage
 *   incoming    = ordered - received on open purchase orders (not physical until received)
 *
 * Allocated units are a SUBSET of demand, so `available` subtracts demand only; subtracting allocation as well
 * would count the same units twice.
 */
class StockBalance
{
    public static function key($variant): string
    {
        return $variant === null ? '' : (string) $variant;
    }

    /** Creates the counter row if missing and locks it for the rest of the transaction. */
    public static function lock(int $productId, $variant): object
    {
        $variant = self::key($variant);
        // ON DUPLICATE KEY takes the row's EXCLUSIVE lock straight away. (INSERT IGNORE takes a shared lock on a duplicate and the
        // SELECT ... FOR UPDATE then tries to upgrade it: two concurrent callers deadlock each other.)
        DB::insert('INSERT INTO stock_balances (product_id, variant, demand, allocated, damaged, created_at, updated_at) VALUES (?, ?, 0, 0, 0, NOW(), NOW()) ON DUPLICATE KEY UPDATE updated_at = updated_at', [$productId, $variant]);

        return DB::selectOne('SELECT * FROM stock_balances WHERE product_id = ? AND variant = ? FOR UPDATE', [$productId, $variant]);
    }

    public static function change(int $productId, $variant, int $demand = 0, int $allocated = 0, int $damaged = 0): void
    {
        DB::update('UPDATE stock_balances SET demand = demand + ?, allocated = allocated + ?, damaged = damaged + ?, updated_at = NOW() WHERE product_id = ? AND variant = ?',
            [$demand, $allocated, $damaged, $productId, self::key($variant)]);
    }

    public static function onHand(int $productId, $variant): int
    {
        return (int) (DB::table('product_stocks')->where('product_id', $productId)->where('variant', self::key($variant))->orderBy('id')->value('qty') ?? 0);
    }

    /**
     * The CURRENT on-hand quantity, locking the row. Inside a transaction a plain SELECT returns the snapshot taken at the
     * transaction's first read, which can predate a receipt that has since committed; allocation decisions must never use that.
     */
    public static function onHandLocked(int $productId, $variant): int
    {
        return (int) (DB::table('product_stocks')->where('product_id', $productId)->where('variant', self::key($variant))->orderBy('id')->lockForUpdate()->value('qty') ?? 0);
    }

    /** Units left in the FIFO cost layers of one item, as a locking (current) read. */
    public static function layersLocked(int $productId, $variant): int
    {
        $v = self::key($variant);
        $clause = $v === '' ? "(variant IS NULL OR variant = '')" : 'variant = ?';

        return (int) (DB::selectOne("SELECT COALESCE(SUM(remaining_qty), 0) AS q FROM purchase_products WHERE product_id = ? AND {$clause} AND remaining_qty > 0 FOR UPDATE", $v === '' ? [$productId] : [$productId, $v])->q ?? 0);
    }

    public static function incoming(int $productId, $variant): int
    {
        $v = self::key($variant);

        return (int) DB::table('purchase_order_details as d')->join('purchase_orders as o', 'o.id', '=', 'd.purchase_order_id')
            ->whereIn('o.status', ['ordered', 'partially_received'])->where('d.product_id', $productId)
            ->where(fn ($q) => $q->where('d.variant', $v)->orWhere(fn ($w) => $v === '' ? $w->whereNull('d.variant') : $w->whereRaw('1 = 0')))
            ->sum('d.pending_quantity');
    }

    /** @return array{on_hand:int, demand:int, allocated:int, free:int, available:int, incoming:int, damaged:int} */
    public static function snapshot(int $productId, $variant): array
    {
        $b = DB::table('stock_balances')->where('product_id', $productId)->where('variant', self::key($variant))->first();
        $onHand = self::onHand($productId, $variant);
        $demand = (int) ($b->demand ?? 0);
        $allocated = (int) ($b->allocated ?? 0);

        return ['on_hand' => $onHand, 'demand' => $demand, 'allocated' => $allocated, 'free' => $onHand - $allocated, 'available' => $onHand - $demand,
            'incoming' => self::incoming($productId, $variant), 'damaged' => (int) ($b->damaged ?? 0)];
    }

    public static function movement(array $row): int
    {
        return (int) DB::table('stock_movements')->insertGetId($row + ['variant' => '', 'on_hand_delta' => 0, 'demand_delta' => 0, 'allocated_delta' => 0, 'created_at' => now()]);
    }
}
