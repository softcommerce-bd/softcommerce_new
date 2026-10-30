<?php

namespace Tests\Feature\Inventory;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Inventory\InventoryTestCase;

/**
 * The storefront checkout (Api\V2\OrderController::store) and the customer's
 * cancel, as seen from outside: what a shopper receives and what happens to
 * stock. Written against the original implementation first, then kept green
 * through the move to the shared stock service.
 */
class StorefrontCheckoutTest extends InventoryTestCase
{
    private function detailOf(string $tempUserId): ?OrderDetail
    {
        $order = Order::where('guest_id', $tempUserId)->first();

        return $order ? OrderDetail::where('order_id', $order->id)->first() : null;
    }

    // ---- an ordinary order -------------------------------------------------

    public function test_a_guest_order_moves_stock_batches_and_clears_the_cart(): void
    {
        $product = $this->makeStockedProduct(['M' => 10, 'L' => 4], 1000, 400);
        $batch = \App\Models\PurchaseProduct::where('product_id', $product->id)->where('variant', 'M')->first();

        $response = $this->placeGuestOrder([[$product, 'M', 3]], [], '700001');

        $response->assertOk()->assertJson(['result' => true, 'is_guest' => true]);
        $this->assertNotEmpty($response->json('combined_order_id'));

        $this->assertSame(7, $this->stockQty($product, 'M'));
        $this->assertSame(7, $this->batchRemaining($product, 'M'));
        $this->assertSame(4, $this->stockQty($product, 'L'), 'other variations are untouched');

        $order = Order::where('guest_id', '700001')->firstOrFail();
        $this->assertEquals(3000, $order->grand_total);
        $this->assertSame(0, Cart::where('temp_user_id', '700001')->count());

        $line = OrderDetail::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(3, (int) $line->quantity);
        $this->assertEquals(1000, $line->selling_price);
        $this->assertEquals(3000, $line->price);
        $this->assertEquals(400, $line->buying_price);
        $this->assertEquals(1800, $line->profit);
        $this->assertSame($batch->purchase_id, (int) $line->purchase_id);
        $this->assertSame('M', $line->variation);
    }

    public function test_an_order_spanning_two_batches_is_costed_first_in_first_out(): void
    {
        $product = $this->makeProduct(['unit_price' => 900], [['variant' => 'M', 'qty' => 8, 'price' => 900]]);
        $this->makeBatch($product, 'M', 3, 100);
        $this->makeBatch($product, 'M', 5, 120);

        $this->placeGuestOrder([[$product, 'M', 5]], [], '700002')->assertJson(['result' => true]);

        $line = $this->detailOf('700002');
        $this->assertEquals(108, $line->buying_price);          // (3*100 + 2*120) / 5
        $this->assertEquals((900 - 108) * 5, $line->profit);
        $this->assertSame(3, $this->stockQty($product, 'M'));
        $this->assertSame(3, $this->batchRemaining($product, 'M'));
    }

    // ---- refusals keep the shape the storefront reads ------------------------------

    public function test_asking_for_more_than_is_in_stock_is_a_plain_refusal(): void
    {
        $product = $this->makeStockedProduct(['M' => 3], 1000, 400);

        $response = $this->placeGuestOrder([[$product, 'M', 5]], [], '700003');

        $response->assertOk()->assertJson(['result' => false, 'combined_order_id' => 0]);
        $this->assertStringContainsString($product->name, $response->json('message'));
        $this->assertSame(0, Order::where('guest_id', '700003')->count());
        $this->assertSame(3, $this->stockQty($product, 'M'));
        $this->assertSame(3, $this->batchRemaining($product, 'M'));
    }

    public function test_stock_without_cost_batches_is_refused_and_nothing_is_consumed(): void
    {
        $product = $this->makeProduct(['unit_price' => 1000], [['variant' => 'M', 'qty' => 10, 'price' => 1000]]);
        $this->makeBatch($product, 'M', 2, 100);

        $response = $this->placeGuestOrder([[$product, 'M', 5]], [], '700004');

        $response->assertJson(['result' => false, 'combined_order_id' => 0]);
        $this->assertSame(0, Order::where('guest_id', '700004')->count());
        $this->assertSame(10, $this->stockQty($product, 'M'));
        // The two units the cost lookup read before it gave up must still be there.
        $this->assertSame(2, $this->batchRemaining($product, 'M'));
    }

    public function test_a_failure_on_the_second_line_leaves_the_first_line_untouched(): void
    {
        $fine = $this->makeStockedProduct(['M' => 6], 1000, 400);
        $short = $this->makeStockedProduct(['M' => 1], 1000, 400);

        $response = $this->placeGuestOrder([[$fine, 'M', 4], [$short, 'M', 2]], [], '700005');

        $response->assertJson(['result' => false]);
        $this->assertSame(0, Order::where('guest_id', '700005')->count());
        $this->assertSame(6, $this->stockQty($fine, 'M'));
        $this->assertSame(6, $this->batchRemaining($fine, 'M'), 'batches of the line that was fine are back');
        $this->assertSame(1, $this->stockQty($short, 'M'));
    }

    public function test_a_variation_with_no_stock_row_is_a_refusal_not_a_crash(): void
    {
        $product = $this->makeStockedProduct(['M' => 5]);

        $response = $this->placeGuestOrder([[$product, 'XXL', 1]], [], '700006');

        $response->assertJson(['result' => false, 'combined_order_id' => 0]);
        $this->assertSame(0, Order::where('guest_id', '700006')->count());
        $this->assertSame(5, $this->stockQty($product, 'M'));
    }

    public function test_a_deadlock_is_reported_as_try_again_without_leaking_sql(): void
    {
        $product = $this->makeStockedProduct(['M' => 5]);

        $pdo = new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; secret_table');
        $pdo->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];
        $this->mock(\App\Services\Inventory\StockLedger::class, function ($mock) use ($pdo) {
            $mock->shouldReceive('take')->once()->andThrow(new \Illuminate\Database\QueryException('update product_stocks set qty = 1', [], $pdo));
            $mock->shouldReceive('release')->never();
        });

        $response = $this->placeGuestOrder([[$product, 'M', 1]], [], '700009');

        $response->assertStatus(503)->assertJson(['result' => false, 'code' => 'inventory_busy']);
        $this->assertStringNotContainsString('secret_table', $response->getContent());
        $this->assertSame(0, Order::where('guest_id', '700009')->count());
    }

    // ---- the customer cancels -----------------------------------------------------

    public function test_cancelling_an_order_returns_stock_and_batches_once(): void
    {
        $product = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $this->placeGuestOrder([[$product, 'M', 3]], [], '700007')->assertJson(['result' => true]);
        $order = Order::where('guest_id', '700007')->firstOrFail();
        $this->assertSame(7, $this->stockQty($product, 'M'));

        Sanctum::actingAs(User::findOrFail($order->user_id));
        $this->getJson("/api/v2/order/cancel/{$order->id}")->assertOk()->assertJson(['result' => true]);

        $this->assertSame(10, $this->stockQty($product, 'M'));
        $this->assertSame(10, $this->batchRemaining($product, 'M'));
        $this->assertSame('cancelled', $order->fresh()->delivery_status);
        $this->assertSame('cancelled', OrderDetail::where('order_id', $order->id)->first()->delivery_status);

        // Pressing it again, or a retry from a slow connection, must not add the units twice.
        $this->getJson("/api/v2/order/cancel/{$order->id}")->assertJson(['result' => false]);
        $this->assertSame(10, $this->stockQty($product, 'M'));
        $this->assertSame(10, $this->batchRemaining($product, 'M'));
    }

    public function test_a_line_cancelled_by_someone_else_is_not_restocked_by_the_customer_cancel(): void
    {
        $product = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $this->placeGuestOrder([[$product, 'M', 3]], [], '700008')->assertJson(['result' => true]);
        $order = Order::where('guest_id', '700008')->firstOrFail();

        // An admin cancelled the line and restocked it by hand already.
        OrderDetail::where('order_id', $order->id)->update(['delivery_status' => 'cancelled']);
        DB::table('product_stocks')->where('product_id', $product->id)->where('variant', 'M')->increment('qty', 3);

        Sanctum::actingAs(User::findOrFail($order->user_id));
        $this->getJson("/api/v2/order/cancel/{$order->id}");

        $this->assertSame(10, $this->stockQty($product, 'M'), 'no double restock');
    }
}
