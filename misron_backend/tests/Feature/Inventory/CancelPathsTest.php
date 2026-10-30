<?php

namespace Tests\Feature\Inventory;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Inventory\InventoryTestCase;

/**
 * Every way an order can be cancelled or deleted returns its stock exactly once, to the
 * shelf and to the purchase batches it came from: the admin order screen, bulk cancel, order
 * deletion, the seller and web customer screens. Before the shared stock service each of these
 * either added the units to the shelf only, added them to the wrong batches, or added them twice.
 */
class CancelPathsTest extends InventoryTestCase
{
    /** @return array{0: \App\Models\Product, 1: Order} a product of 10 and an order for 3 of it */
    private function orderOfThree(string $phone): array
    {
        $product = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $this->placeGuestOrder([[$product, 'M', 3]], [], $phone)->assertJson(['result' => true]);

        return [$product, Order::where('guest_id', $phone)->firstOrFail()];
    }

    private function assertShelfAndBatches($product, int $expected, string $message = ''): void
    {
        $this->assertSame($expected, $this->stockQty($product, 'M'), "shelf {$message}");
        $this->assertSame($expected, $this->batchRemaining($product, 'M'), "batches {$message}");
    }

    private function adminCancels(Order $order)
    {
        return $this->actingAs($this->makeAdmin())->post('/admin/orders/update_delivery_status', ['order_id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_the_helper_cancels_a_line_once_and_returns_the_batches_it_came_from(): void
    {
        [$product, $order] = $this->orderOfThree('710001');
        $line = OrderDetail::where('order_id', $order->id)->firstOrFail();
        $this->assertShelfAndBatches($product, 7, 'after the sale');

        set_order_line_status($line, 'cancelled');
        $this->assertShelfAndBatches($product, 10, 'after the first cancel');
        $this->assertSame('cancelled', $line->fresh()->delivery_status);

        set_order_line_status($line->fresh(), 'cancelled');
        $this->assertShelfAndBatches($product, 10, 'cancelling twice adds nothing');
    }

    public function test_other_statuses_do_not_touch_stock(): void
    {
        [$product, $order] = $this->orderOfThree('710002');
        $line = OrderDetail::where('order_id', $order->id)->firstOrFail();

        set_order_line_status($line, 'confirmed');
        set_order_line_status($line->fresh(), 'on_the_way');

        $this->assertShelfAndBatches($product, 7);
        $this->assertSame('on_the_way', $line->fresh()->delivery_status);
    }

    public function test_the_admin_order_screen_restocks_shelf_and_batches_once_even_if_pressed_twice(): void
    {
        [$product, $order] = $this->orderOfThree('710003');

        $this->adminCancels($order)->assertOk();
        $this->assertShelfAndBatches($product, 10, 'after the admin cancels');
        $this->assertSame('cancelled', $order->fresh()->delivery_status);

        $this->adminCancels($order);
        $this->assertShelfAndBatches($product, 10, 'after the admin presses it again');
    }

    public function test_a_delivered_order_the_admin_moves_to_cancelled_comes_back_to_stock_once(): void
    {
        [$product, $order] = $this->orderOfThree('710004');
        $this->actingAs($this->makeAdmin())->post('/admin/orders/update_delivery_status', ['order_id' => $order->id, 'status' => 'delivered'])->assertOk();
        $this->assertShelfAndBatches($product, 7, 'delivered goods stay out');

        $this->adminCancels($order)->assertOk();
        $this->assertShelfAndBatches($product, 10, 'returned goods are back');

        $this->adminCancels($order);
        $this->assertShelfAndBatches($product, 10, 'and only once');
    }

    public function test_cancelling_an_order_that_was_not_placed_through_the_service_still_returns_to_the_newest_batches(): void
    {
        $product = $this->makeStockedProduct(['M' => 10], 1000, 400);
        // An older order: it has no record of its batches (fifo_batch_info is empty).
        $this->placeGuestOrder([[$product, 'M', 4]], [], '710005')->assertJson(['result' => true]);
        $order = Order::where('guest_id', '710005')->firstOrFail();
        OrderDetail::where('order_id', $order->id)->update(['fifo_batch_info' => null]);

        $this->adminCancels($order)->assertOk();

        $this->assertShelfAndBatches($product, 10);
    }

    public function test_bulk_cancel_from_the_order_list_restocks_every_order_it_cancels(): void
    {
        [$productA, $orderA] = $this->orderOfThree('710006');
        [$productB, $orderB] = $this->orderOfThree('710007');

        $this->actingAs($this->makeAdmin())->post(route('bulk-order-status'), ['bulk_delivery_status' => 'cancelled', 'id' => [$orderA->id, $orderB->id]]);

        $this->assertSame('cancelled', $orderA->fresh()->delivery_status);
        $this->assertSame('cancelled', $orderB->fresh()->delivery_status);
        $this->assertShelfAndBatches($productA, 10, 'order A');
        $this->assertShelfAndBatches($productB, 10, 'order B');
        $this->assertSame(['cancelled'], OrderDetail::whereIn('order_id', [$orderA->id, $orderB->id])->pluck('delivery_status')->unique()->all());

        // Running the same bulk action again changes nothing.
        $this->post(route('bulk-order-status'), ['bulk_delivery_status' => 'cancelled', 'id' => [$orderA->id, $orderB->id]]);
        $this->assertShelfAndBatches($productA, 10, 'order A again');
    }

    public function test_deleting_an_open_order_returns_its_stock_and_deleting_a_cancelled_one_does_not_return_it_again(): void
    {
        [$product, $open] = $this->orderOfThree('710008');
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get(route('orders.destroy', $open->id));
        $this->assertShelfAndBatches($product, 10, 'after deleting an open order');
        $this->assertNull(Order::find($open->id));

        [$product2, $cancelled] = $this->orderOfThree('710009');
        $this->adminCancels($cancelled);
        $this->assertShelfAndBatches($product2, 10, 'after cancelling');
        $this->get(route('orders.destroy', $cancelled->id));
        $this->assertShelfAndBatches($product2, 10, 'deleting after cancelling must not add the units again');
    }

    public function test_deleting_a_delivered_order_keeps_the_goods_out_of_stock(): void
    {
        [$product, $order] = $this->orderOfThree('710010');
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post('/admin/orders/update_delivery_status', ['order_id' => $order->id, 'status' => 'delivered'])->assertOk();

        $this->get(route('orders.destroy', $order->id));

        $this->assertShelfAndBatches($product, 7, 'delivered goods are gone, deleting the record does not bring them back');
    }

    public function test_a_failing_restock_never_blocks_deleting_the_order(): void
    {
        [$product, $order] = $this->orderOfThree('710011');
        // The line points at a product that no longer exists.
        OrderDetail::where('order_id', $order->id)->update(['product_id' => 99999999]);

        $this->actingAs($this->makeAdmin())->get(route('orders.destroy', $order->id));

        $this->assertNull(Order::find($order->id));
        $this->assertSame(7, $this->stockQty($product, 'M'));
    }

    public function test_the_web_customer_cancel_restocks_once(): void
    {
        [$product, $order] = $this->orderOfThree('710012');
        $user = User::findOrFail($order->user_id);
        // The web screen is for signed-in, verified customers.
        $user->forceFill(['user_type' => 'customer', 'email_verified_at' => now()])->save();

        $this->actingAs($user->fresh())->post(route('purchase_history.destroy', $order->id));
        $this->assertShelfAndBatches($product, 10, 'after the web cancel');

        $this->post(route('purchase_history.destroy', $order->id));
        $this->assertShelfAndBatches($product, 10, 'after a second press');
    }

    public function test_the_old_restock_function_is_now_the_same_safe_operation(): void
    {
        [$product, $order] = $this->orderOfThree('710013');
        $line = OrderDetail::where('order_id', $order->id)->firstOrFail();

        $this->assertTrue(product_restock($line));
        $this->assertFalse(product_restock($line->fresh()));
        $this->assertShelfAndBatches($product, 10);
        $this->assertGreaterThanOrEqual(0, (int) DB::table('products')->where('id', $product->id)->value('num_of_sale'), 'the sales counter never goes below zero');
    }
}
