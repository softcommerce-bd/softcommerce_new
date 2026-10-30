<?php

namespace Tests\Feature\Inventory;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Tests\Inventory\InventoryTestCase;

/**
 * The order screen that creates an order from a cart (OrderController::store) takes stock
 * and purchase batches through the shared service: the last unit goes to one buyer, a refusal
 * names the product and leaves everything as it was, and a failure part-way gives the units back.
 */
class AdminCreateOrderTest extends InventoryTestCase
{
    private function cartLine(Product $product, string $variant, int $quantity): Cart
    {
        $admin = auth()->user();

        return Cart::create([
            'user_id' => $admin->id,
            'product_id' => $product->id,
            'variation' => $variant,
            'quantity' => $quantity,
            'price' => $product->unit_price,
            'tax' => 0,
            'shipping_cost' => 0,
            'discount' => 0,
            'shipping_type' => 'home_delivery',
        ]);
    }

    private function placeOrder()
    {
        $address = Address::create(['user_id' => auth()->id(), 'address' => 'House 1, Road 2', 'phone' => '01700000001', 'postal_code' => '1200', 'country_id' => 18, 'state_id' => 1, 'city_id' => 1]);

        return $this->post(route('orders.store'), ['address_id' => $address->id, 'payment_option' => 'cash_on_delivery', 'shipping_cost_amount' => 0]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->makeAdmin());
    }

    public function test_an_order_takes_stock_and_batches_and_records_where_they_came_from(): void
    {
        $product = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $this->cartLine($product, 'M', 3);

        $this->placeOrder();

        $this->assertSame(7, $this->stockQty($product, 'M'));
        $this->assertSame(7, $this->batchRemaining($product, 'M'));
        $line = OrderDetail::where('product_id', $product->id)->firstOrFail();
        $this->assertEquals(400, (float) $line->buying_price);
        $this->assertSame(3, json_decode($line->fifo_batch_info, true)[0]['qty']);
    }

    public function test_an_order_for_more_than_is_in_stock_names_the_product_and_changes_nothing(): void
    {
        $plenty = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $scarce = $this->makeStockedProduct(['L' => 1], 500, 200, ['name' => 'Last Mixer']);
        $this->cartLine($plenty, 'M', 4);
        $this->cartLine($scarce, 'L', 2);

        $response = $this->placeOrder();

        $response->assertRedirect(route('cart'));
        $this->assertStringContainsString('Last Mixer', json_encode(session('flash_notification')));
        $this->assertSame(10, $this->stockQty($plenty, 'M'));
        $this->assertSame(10, $this->batchRemaining($plenty, 'M'));
        $this->assertSame(1, $this->stockQty($scarce, 'L'));
        $this->assertSame(0, Order::where('user_id', auth()->id())->count(), 'no order is left behind');
        $this->assertSame(0, OrderDetail::whereIn('product_id', [$plenty->id, $scarce->id])->count());
    }

    public function test_a_failure_after_the_stock_was_taken_gives_it_all_back(): void
    {
        $product = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $this->cartLine($product, 'M', 4);
        OrderDetail::creating(function () {
            throw new \RuntimeException('disk full');
        });

        $response = $this->placeOrder();

        $response->assertStatus(500);
        $this->assertSame('disk full', optional($response->exception)->getMessage());

        $this->assertSame(10, $this->stockQty($product, 'M'));
        $this->assertSame(10, $this->batchRemaining($product, 'M'));
    }

    public function test_stock_with_no_cost_records_is_refused_not_sold_for_nothing(): void
    {
        $product = $this->makeProduct(['name' => 'Uncosted'], [['variant' => 'M', 'qty' => 5, 'price' => 1000]]);
        $this->cartLine($product, 'M', 1);

        $this->placeOrder()->assertRedirect(route('cart'));

        $this->assertSame(5, $this->stockQty($product, 'M'));
    }
}
