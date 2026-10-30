<?php

namespace App\Services\Inventory;

use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\DB;

/**
 * The single mapping from an order's delivery status to stock actions for v2 lines. It runs from the Order model
 * observer, so every code path that saves a new status (single, bulk, API, courier sync, seller, gateway) gets the same
 * rules without each controller carrying its own stock logic.
 *
 *   pending / new ................ nothing held (a confirmed order sent back to pending releases its undispatched demand)
 *   confirmed, processing ........ confirmed demand exists, exactly once
 *   courier booking states ....... same as processing (a booking is not a pickup)
 *   picked_up, on_the_way,
 *   shipped, delivered ........... demand exists and the outstanding quantity is dispatched (physical stock and FIFO cost)
 *   cancelled .................... undispatched demand is released; dispatched units are untouched (needs a return receipt)
 * Legacy lines (stock_mode = 'legacy') are never touched here.
 */
class OrderStockTransitions
{
    public const DEMAND_STATUSES = ['confirmed', 'processing', 'courier_assigned', 'steadfast', 'pathao'];
    public const DISPATCH_STATUSES = ['picked_up', 'on_the_way', 'shipped', 'delivered'];
    public const PENDING_STATUSES = ['pending', 'new', 'draft'];

    public function __construct(private DemandService $demand)
    {
    }

    public static function normalise($status): string
    {
        return strtolower(trim((string) $status));
    }

    public function apply(Order $order, $newStatus, ?int $by = null): void
    {
        $status = self::normalise($newStatus);
        $lines = $this->v2Lines($order);
        if ($lines->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($lines, $status, $by) {
            foreach ($lines as $line) {
                $lineStatus = self::normalise($line->delivery_status);
                if ($lineStatus === 'cancelled' && $status !== 'cancelled') {
                    continue; // a cancelled line stays out of every later status change
                }
                if ($status === 'cancelled') {
                    $this->demand->release($line, null, $by);
                } elseif (in_array($status, self::PENDING_STATUSES, true)) {
                    $this->demand->release($line, null, $by);
                } elseif (in_array($status, self::DISPATCH_STATUSES, true)) {
                    $this->demand->dispatch($line, null, $by);
                } elseif (in_array($status, self::DEMAND_STATUSES, true)) {
                    $this->demand->confirm($line, $by);
                }
            }
        });
    }

    private function v2Lines(Order $order)
    {
        // Lines created under v2 keep being processed even if the switch is later turned off: only the tables must exist.
        if (!$order->id || !InventoryMode::installed()) {
            return collect();
        }

        // Always in (product, variation) order: two orders with the same items in a different order cannot lock each other in a circle.
        return OrderDetail::where('order_id', $order->id)->where('stock_mode', 'v2')->orderBy('product_id')->orderBy('variation')->orderBy('id')->get();
    }
}
