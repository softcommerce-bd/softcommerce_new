<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Inventory\OrderStockTransitions;
use Illuminate\Support\Facades\Auth;

/**
 * Runs the stock rules for every save that changes an order's delivery status, wherever it came from.
 * It runs before the row is written and inside the caller's transaction: if the stock rules refuse (for example the goods
 * are not in the warehouse yet), the status is not saved and nothing changes.
 */
class OrderStockObserver
{
    public function updating(Order $order): void
    {
        if (!$order->isDirty('delivery_status')) {
            return;
        }
        $new = OrderStockTransitions::normalise($order->delivery_status);
        $old = OrderStockTransitions::normalise($order->getOriginal('delivery_status'));
        if ($new === $old) {
            return;
        }
        app(OrderStockTransitions::class)->apply($order, $new, Auth::id());
    }
}
