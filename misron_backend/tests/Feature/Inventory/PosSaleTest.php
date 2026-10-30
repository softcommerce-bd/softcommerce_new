<?php

namespace Tests\Feature\Inventory;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\User;
use App\Utility\PosUtility;
use Tests\Inventory\InventoryTestCase;

/**
 * Selling at the POS counter takes stock through the shared service, inside one transaction:
 * the sale is all or nothing, two sales for the last unit cannot both succeed, the batches
 * that supplied each line are recorded, and the sale can be cancelled back into stock.
 */
class PosSaleTest extends InventoryTestCase
{
    private string $tempUser;
    private User $admin;

    /** While true, saving an order line fails. */
    private static bool $explode = false;

    protected function setUp(): void
    {
        parent::setUp();
        // Each test boots its own application, so this listener never outlives the test.
        OrderDetail::creating(function () {
            if (self::$explode) {
                throw new \RuntimeException('disk full');
            }
        });
        $this->admin = $this->makeAdmin();
        $this->actingAs($this->admin);
        $this->tempUser = (string) random_int(800000000, 899999999);
    }

    /** The POS cart belongs to the shop owner (the first admin) and a temporary customer id. */
    private function addToPosCart(Product $product, string $variant, int $quantity): Cart
    {
        return Cart::create([
            'owner_id' => User::where('user_type', 'admin')->orderBy('id')->value('id'),
            'user_id' => null,
            'temp_user_id' => $this->tempUser,
            'product_id' => $product->id,
            'variation' => $variant,
            'quantity' => $quantity,
            'price' => $product->unit_price,
            'tax' => 0,
            'shipping_cost' => 0,
            'discount' => 0,
        ]);
    }

    private function checkout(array $override = []): array
    {
        return PosUtility::orderStore(array_merge([
            'user_id' => null,
            'temp_usder_id' => $this->tempUser,
            'payment_type' => 'cash_on_delivery',
            'shippingInfo' => ['name' => 'Walk-in', 'phone' => '01700000000', 'address' => 'Counter'],
        ], $override));
    }

    private function posOrders()
    {
        return Order::where('order_from', 'pos')->where('guest_id', $this->tempUser)->get();
    }

    public function test_a_sale_takes_stock_and_batches_records_where_the_units_came_from_and_clears_the_cart(): void
    {
        $product = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $this->addToPosCart($product, 'M', 3);

        $result = $this->checkout();

        $this->assertSame(1, $result['success'], $result['message'] ?? '');
        $this->assertSame(7, $this->stockQty($product, 'M'));
        $this->assertSame(7, $this->batchRemaining($product, 'M'));
        $line = OrderDetail::where('product_id', $product->id)->firstOrFail();
        $this->assertSame(3, (int) $line->quantity);
        $this->assertEquals(400, (float) $line->buying_price);
        $this->assertEquals(1800, (float) $line->profit);
        $batches = json_decode($line->fifo_batch_info, true);
        $this->assertSame(3, $batches[0]['qty'], 'the line remembers its batch for a later cancel');
        $this->assertSame(0, Cart::where('temp_user_id', $this->tempUser)->count());
        $this->assertSame(3, (int) Product::find($product->id)->num_of_sale);
    }

    public function test_a_line_that_is_out_of_stock_stops_the_whole_sale_and_names_the_product(): void
    {
        $plenty = $this->makeStockedProduct(['M' => 10], 1000, 400, ['name' => 'Plenty Blender']);
        $scarce = $this->makeStockedProduct(['L' => 2], 500, 200, ['name' => 'Scarce Mixer']);
        $this->addToPosCart($plenty, 'M', 4);
        $this->addToPosCart($scarce, 'L', 3);

        $result = $this->checkout();

        $this->assertSame(0, $result['success']);
        $this->assertStringContainsString('Scarce Mixer', $result['message']);
        $this->assertSame(10, $this->stockQty($plenty, 'M'), 'the line that could be served was not taken either');
        $this->assertSame(10, $this->batchRemaining($plenty, 'M'));
        $this->assertSame(2, $this->stockQty($scarce, 'L'));
        $this->assertCount(0, $this->posOrders(), 'no order row is left behind');
        $this->assertSame(2, Cart::where('temp_user_id', $this->tempUser)->count(), 'the cashier keeps the cart to adjust it');
    }

    public function test_stock_with_no_cost_records_is_refused_rather_than_sold_at_zero_cost(): void
    {
        $product = $this->makeProduct(['name' => 'Uncosted'], [['variant' => 'M', 'qty' => 5, 'price' => 1000]]);
        $this->addToPosCart($product, 'M', 1);

        $result = $this->checkout();

        $this->assertSame(0, $result['success']);
        $this->assertSame(5, $this->stockQty($product, 'M'));
        $this->assertCount(0, $this->posOrders());
    }

    public function test_a_failure_after_the_stock_was_taken_puts_everything_back(): void
    {
        $product = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $this->addToPosCart($product, 'M', 4);
        self::$explode = true;

        try {
            $result = $this->checkout();
        } finally {
            self::$explode = false;
        }

        $this->assertSame(0, $result['success']);
        $this->assertStringContainsString('disk full', $result['message']);
        $this->assertSame(10, $this->stockQty($product, 'M'), 'shelf restored');
        $this->assertSame(10, $this->batchRemaining($product, 'M'), 'batches restored, also on a database whose batches do not roll back');
        $this->assertCount(0, $this->posOrders());
    }

    public function test_the_last_unit_goes_to_one_sale_only(): void
    {
        $product = $this->makeStockedProduct(['M' => 1], 1000, 400);
        $this->addToPosCart($product, 'M', 1);
        $this->assertSame(1, $this->checkout()['success']);

        // A second cashier had the same item in a cart.
        $this->tempUser = (string) random_int(800000000, 899999999);
        $this->addToPosCart($product, 'M', 1);
        $second = $this->checkout();

        $this->assertSame(0, $second['success']);
        $this->assertSame(0, $this->stockQty($product, 'M'));
        $this->assertSame(0, $this->batchRemaining($product, 'M'));
    }

    public function test_a_pos_sale_can_be_cancelled_back_into_the_batches_it_came_from(): void
    {
        $product = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $this->addToPosCart($product, 'M', 3);
        $this->assertSame(1, $this->checkout()['success']);
        $order = $this->posOrders()->firstOrFail();

        $this->post('/admin/orders/update_delivery_status', ['order_id' => $order->id, 'status' => 'cancelled'])->assertOk();

        $this->assertSame(10, $this->stockQty($product, 'M'));
        $this->assertSame(10, $this->batchRemaining($product, 'M'));
        $this->post('/admin/orders/update_delivery_status', ['order_id' => $order->id, 'status' => 'cancelled']);
        $this->assertSame(10, $this->stockQty($product, 'M'), 'and only once');
    }

    public function test_digital_products_move_no_stock(): void
    {
        $digital = $this->makeProduct(['name' => 'Manual', 'digital' => 1], [['variant' => '', 'qty' => 0, 'price' => 100]]);
        $this->addToPosCart($digital, '', 2);

        $result = $this->checkout();

        $this->assertSame(1, $result['success'], $result['message'] ?? '');
    }
}
