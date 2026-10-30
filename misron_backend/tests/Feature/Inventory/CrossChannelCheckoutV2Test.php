<?php

namespace Tests\Feature\Inventory;

use App\Models\Order;
use App\Services\Inventory\StockPolicy;
use Illuminate\Support\Facades\DB;

/** Real storefront HTTP checkout with the flag on: availability, no deduction while pending, once on confirmation. */
class CrossChannelCheckoutV2Test extends InventoryV2TestCase
{
    private function stocked(int $qty, array $attrs = [])
    {
        $p = $this->productAtZero($attrs);
        $this->receive($p, '', $qty, '60');

        return $p;
    }

    public function test_storefront_checkout_takes_nothing_until_confirmed_then_once()
    {
        $p = $this->stocked(10);
        $res = $this->placeGuestOrder([[$p, '', 3]]);
        $res->assertOk()->assertJson(['result' => true]);
        $this->assertSame(10, $this->stockQty($p, ''), 'pending order deducts nothing');
        $this->assertSame(0, $this->snap($p)['demand']);

        $order = Order::latest('id')->first();
        $order->delivery_status = 'confirmed';
        $order->save();
        $this->assertSame(3, $this->snap($p)['demand']);
        $this->assertSame(10, $this->stockQty($p, ''), 'confirmed reserves demand, physical stock is unchanged');
        $order->delivery_status = 'confirmed';
        $order->save();
        $this->assertSame(3, $this->snap($p)['demand'], 'repeating the status does not double count');

        $order->delivery_status = 'cancelled';
        $order->save();
        $this->assertSame(0, $this->snap($p)['demand'], 'cancel releases demand once');
        $this->assertSame(10, $this->stockQty($p, ''));
    }

    public function test_stock_only_product_at_zero_is_refused_and_backorder_product_is_accepted_without_deduction()
    {
        $stockOnly = $this->productAtZero(['stock_policy' => 'stock_only']);
        $this->placeGuestOrder([[$stockOnly, '', 1]])->assertJson(['result' => false]);
        $this->assertFalse(StockPolicy::describe($stockOnly->id, '', 1)['can_order']);

        $back = $this->productAtZero(['stock_policy' => 'backorder']);
        $this->placeGuestOrder([[$back, '', 2]])->assertOk()->assertJson(['result' => true]);
        $this->assertSame(0, $this->stockQty($back, ''));
        $order = Order::latest('id')->first();
        $order->delivery_status = 'confirmed';
        $order->save();
        $this->assertSame(2, $this->snap($back)['demand']);
        $this->assertSame(-2, $this->snap($back)['available'], 'available goes negative: it is what must be bought');
    }
}
