<?php

namespace Tests\Feature\Inventory;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Tests\Inventory\InventoryTestCase;

/**
 * Editing an order after it was placed moves stock and purchase batches by exactly the
 * difference, with the same atomic statements as a sale: more units come off the shelf and
 * out of the oldest batches, fewer go back, a removed line goes back whole, and an edit
 * that cannot be served changes nothing.
 */
class OrderEditTest extends InventoryTestCase
{
    /** @return array{0: Product, 1: Order, 2: OrderDetail} a product of 10 with an order for 3 of it */
    private function orderOfThree(string $phone, array $variants = ['M' => 10]): array
    {
        $product = $this->makeStockedProduct($variants, 1000, 400);
        $this->placeGuestOrder([[$product, array_key_first($variants), 3]], [], $phone)->assertJson(['result' => true]);
        $order = Order::where('guest_id', $phone)->firstOrFail();

        return [$product, $order, OrderDetail::where('order_id', $order->id)->firstOrFail()];
    }

    private function edit(Order $order, array $lines, array $extra = [])
    {
        $body = array_merge([
            'id' => $order->id,
            'grand_total' => '9,999',
            'shipping_cost' => '0',
            'name' => 'Buyer',
            'email' => 'buyer@example.com',
            'address' => 'House 1',
            'phone' => '01712345678',
            'order_detail_id' => array_column($lines, 'detail'),
            'product_id' => array_column($lines, 'product'),
            'variation' => array_column($lines, 'variant'),
            'qty' => array_column($lines, 'qty'),
            'total' => array_column($lines, 'total'),
        ], $extra);

        return $this->actingAs($this->makeAdmin())->post(route('order.edit.update'), $body);
    }

    public function test_adding_units_takes_them_from_the_shelf_and_the_oldest_batch(): void
    {
        [$product, $order, $line] = $this->orderOfThree('720001');

        $this->edit($order, [['detail' => $line->id, 'product' => $product->id, 'variant' => 'M', 'qty' => 5, 'total' => '5,000']]);

        $this->assertSame(5, $this->stockQty($product, 'M'));
        $this->assertSame(5, $this->batchRemaining($product, 'M'));
        $this->assertSame(5, (int) $line->fresh()->quantity);
    }

    public function test_reducing_units_returns_exactly_the_difference(): void
    {
        [$product, $order, $line] = $this->orderOfThree('720002');

        $this->edit($order, [['detail' => $line->id, 'product' => $product->id, 'variant' => 'M', 'qty' => 1, 'total' => '1,000']]);

        $this->assertSame(9, $this->stockQty($product, 'M'));
        $this->assertSame(9, $this->batchRemaining($product, 'M'));
    }

    public function test_an_edit_that_asks_for_more_than_exists_changes_nothing_and_says_so(): void
    {
        [$product, $order, $line] = $this->orderOfThree('720003');

        $response = $this->edit($order, [['detail' => $line->id, 'product' => $product->id, 'variant' => 'M', 'qty' => 50, 'total' => '50,000']]);

        $response->assertRedirect();
        $this->assertNotEmpty(session('flash_notification'));
        $this->assertSame(7, $this->stockQty($product, 'M'), 'the shelf is as the sale left it');
        $this->assertSame(7, $this->batchRemaining($product, 'M'), 'and so are the batches');
        $this->assertSame(3, (int) $line->fresh()->quantity);
    }

    public function test_stock_that_is_on_the_shelf_but_not_in_the_batches_is_refused_without_a_partial_change(): void
    {
        [$product, $order, $line] = $this->orderOfThree('720004');
        // Records disagree: the shelf says 7, the batches only hold 1.
        \DB::table('purchase_products')->where('product_id', $product->id)->update(['remaining_qty' => 1]);

        $this->edit($order, [['detail' => $line->id, 'product' => $product->id, 'variant' => 'M', 'qty' => 6, 'total' => '6,000']]);

        $this->assertSame(7, $this->stockQty($product, 'M'));
        $this->assertSame(1, $this->batchRemaining($product, 'M'));
    }

    public function test_a_line_taken_out_of_the_order_goes_back_whole(): void
    {
        [$product, $order, $line] = $this->orderOfThree('720005');
        $other = $this->makeStockedProduct(['L' => 10], 500, 200);

        // The edit keeps only a different product (the first line is not in the request any more).
        $this->edit($order, [['detail' => '', 'product' => $other->id, 'variant' => 'L', 'qty' => 2, 'total' => '1,000']]);

        $this->assertSame(10, $this->stockQty($product, 'M'));
        $this->assertSame(10, $this->batchRemaining($product, 'M'));
        $this->assertSame(8, $this->stockQty($other, 'L'));
        $this->assertSame(8, $this->batchRemaining($other, 'L'));
        $this->assertNull(OrderDetail::find($line->id));
    }

    public function test_changing_the_variation_moves_the_units_between_variations(): void
    {
        [$product, $order, $line] = $this->orderOfThree('720006', ['M' => 10, 'L' => 10]);

        $this->edit($order, [['detail' => $line->id, 'product' => $product->id, 'variant' => 'L', 'qty' => 3, 'total' => '3,000']]);

        $this->assertSame(10, $this->stockQty($product, 'M'));
        $this->assertSame(10, $this->batchRemaining($product, 'M'));
        $this->assertSame(7, $this->stockQty($product, 'L'));
        $this->assertSame(7, $this->batchRemaining($product, 'L'));
    }

    public function test_cancelling_after_an_edit_puts_every_unit_back(): void
    {
        [$product, $order, $line] = $this->orderOfThree('720007');
        $this->edit($order, [['detail' => $line->id, 'product' => $product->id, 'variant' => 'M', 'qty' => 6, 'total' => '6,000']]);
        $this->assertSame(4, $this->stockQty($product, 'M'));

        $this->post('/admin/orders/update_delivery_status', ['order_id' => $order->id, 'status' => 'cancelled'])->assertOk();

        // The line now holds 6; its record held 3. The 3 the record cannot account for are not lost.
        $this->assertSame(10, $this->stockQty($product, 'M'));
        $this->assertSame(10, $this->batchRemaining($product, 'M'));
    }

    public function test_deleting_a_line_returns_its_units_once_and_never_to_another_variation(): void
    {
        [$product, $order, $line] = $this->orderOfThree('720008', ['M' => 10, 'L' => 10]);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('order.edit.delete', ['id' => $line->id, 'variation' => 'L']))->assertOk();

        $this->assertSame(10, $this->stockQty($product, 'M'));
        $this->assertSame(10, $this->batchRemaining($product, 'M'));
        $this->assertSame(10, $this->stockQty($product, 'L'), 'the other variation is untouched');
        $this->assertSame(10, $this->batchRemaining($product, 'L'));
        $this->assertNull(OrderDetail::find($line->id));
    }

    public function test_deleting_a_line_that_was_cancelled_adds_nothing_again(): void
    {
        [$product, $order, $line] = $this->orderOfThree('720009');
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post('/admin/orders/update_delivery_status', ['order_id' => $order->id, 'status' => 'cancelled'])->assertOk();
        $this->assertSame(10, $this->stockQty($product, 'M'));

        $this->get(route('order.edit.delete', ['id' => $line->id]))->assertOk();

        $this->assertSame(10, $this->stockQty($product, 'M'));
        $this->assertSame(10, $this->batchRemaining($product, 'M'));
    }
}
