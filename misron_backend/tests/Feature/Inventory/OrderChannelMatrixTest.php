<?php

namespace Tests\Feature\Inventory;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\PaystationGateway;
use App\Models\PaymentGatewayAttempt;
use App\Utility\PosUtility;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Funnel\BuildsLiveFunnel;

/**
 * Independent end-to-end runs per order channel with INVENTORY_CONFIRMED_ONLY on (isolated test DB):
 * pending takes nothing, confirmed reserves, processing changes nothing, shipped moves physical + demand together once,
 * delivered adds nothing, cancelled releases once. Each channel creates its own order through its own entry point.
 */
class OrderChannelMatrixTest extends InventoryV2TestCase
{
    use BuildsLiveFunnel;

    private function stocked(int $qty = 10): Product
    {
        $p = $this->productAtZero(['unit_price' => 1000]);
        $this->receive($p, '', $qty, '400');

        return $p;
    }

    /** pending -> confirmed -> processing -> shipped -> delivered for an order of $q units out of $start. */
    private function walk(Order $order, Product $p, int $q, int $start): void
    {
        $order = Order::find($order->id);
        $this->assertSame('pending', $order->delivery_status);
        $this->assertSame([$start, 0], [$this->snap($p)['on_hand'], $this->snap($p)['demand']], 'pending takes nothing');
        $this->setStatus($order, 'confirmed');
        $this->assertSame([$start, $q], [$this->snap($p)['on_hand'], $this->snap($p)['demand']], 'confirmed reserves the demand only');
        $this->setStatus($order, 'confirmed');
        $this->assertSame($q, $this->snap($p)['demand'], 'a repeated confirmation does not reserve twice');
        $this->setStatus($order, 'processing');
        $this->assertSame([$start, $q], [$this->snap($p)['on_hand'], $this->snap($p)['demand']], 'processing changes nothing');
        $this->setStatus($order, 'picked_up');
        $this->assertSame([$start - $q, 0], [$this->snap($p)['on_hand'], $this->snap($p)['demand']], 'shipped: physical and demand fall together');
        $this->setStatus($order, 'on_the_way');
        $this->setStatus($order, 'delivered');
        $this->assertSame([$start - $q, 0], [$this->snap($p)['on_hand'], $this->snap($p)['demand']], 'delivered adds no second deduction');
        $this->assertSame('final', $this->detail($order)->cost_status);
        $this->assertEquals(400.0, (float) $this->detail($order)->buying_price, 'actual FIFO cost was recorded at dispatch');
    }

    private function cancelWalk(Order $order, Product $p, int $q, int $start): void
    {
        $this->setStatus($order, 'confirmed');
        $this->assertSame($q, $this->snap($p)['demand']);
        $this->setStatus($order, 'cancelled');
        $this->assertSame([$start, 0], [$this->snap($p)['on_hand'], $this->snap($p)['demand']], 'cancel releases the demand, no stock moves');
        $this->setStatus($order, 'cancelled');
        $this->assertSame([$start, 0], [$this->snap($p)['on_hand'], $this->snap($p)['demand']], 'cancelling twice releases nothing more');
    }

    // 1 + 2: storefront checkout (guest) -------------------------------------------------------
    public function test_01_02_storefront_guest_checkout_full_lifecycle_and_cancel()
    {
        $p = $this->stocked();
        $this->placeGuestOrder([[$p, '', 3]], [], '710001')->assertOk()->assertJson(['result' => true]);
        $order = Order::where('guest_id', '710001')->firstOrFail();
        $this->assertSame('v2', $this->detail($order)->stock_mode);
        $this->walk($order, $p, 3, 10);

        $q = $this->stocked();
        $this->placeGuestOrder([[$q, '', 2]], [], '710002');
        $this->cancelWalk(Order::where('guest_id', '710002')->firstOrFail(), $q, 2, 10);
    }

    // 3 + 4: registered customer / API order with a bearer token ---------------------------------
    public function test_03_04_registered_customer_api_order_and_customer_cancel_endpoint()
    {
        $p = $this->stocked();
        $user = $this->makeUser('customer');
        Cart::create(['user_id' => $user->id, 'product_id' => $p->id, 'variation' => '', 'quantity' => 4, 'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v2/order/store', ['payment_type' => 'cash_on_delivery', 'delivery_type' => 'home_delivery',
            'shipping_address' => ['name' => 'Reg', 'phone' => '01712345679', 'address' => 'House 1, Road 2', 'city_name' => 'Dhaka']])->assertOk()->assertJson(['result' => true]);
        $order = Order::where('user_id', $user->id)->firstOrFail();
        $this->assertSame([10, 0], [$this->snap($p)['on_hand'], $this->snap($p)['demand']], 'placing it took nothing');
        $this->postJson("/api/v2/order/cancel/{$order->id}")->assertOk()->assertJson(['result' => true]);
        $this->assertSame([10, 0], [$this->snap($p)['on_hand'], $this->snap($p)['demand']], 'customer cancel of a pending order changes nothing');
        $this->assertSame('cancelled', $order->fresh()->delivery_status);
        $this->postJson("/api/v2/order/cancel/{$order->id}")->assertJson(['result' => false]);

        $q = $this->stocked();
        Cart::create(['user_id' => $user->id, 'product_id' => $q->id, 'variation' => '', 'quantity' => 3, 'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0]);
        $this->postJson('/api/v2/order/store', ['payment_type' => 'cash_on_delivery', 'delivery_type' => 'home_delivery',
            'shipping_address' => ['name' => 'Reg', 'phone' => '01712345679', 'address' => 'House 1, Road 2', 'city_name' => 'Dhaka']])->assertOk();
        $this->walk(Order::where('user_id', $user->id)->latest('id')->firstOrFail(), $q, 3, 10);
    }

    // 5: sales funnel -----------------------------------------------------------------------
    public function test_05_funnel_checkout_lifecycle()
    {
        $this->setUpLiveFunnel();
        [, $product] = $this->liveFunnel(['M' => 10]);
        $this->placeOrder($this->checkoutBody())->assertStatus(201);
        $order = Order::latest('id')->firstOrFail();
        $line = $this->detail($order);
        $this->assertSame('v2', $line->stock_mode);
        $q = (int) $line->quantity;
        $this->assertSame([10, 0], [$this->snap($product, 'M')['on_hand'], $this->snap($product, 'M')['demand']], 'funnel order pending takes nothing');
        $this->setStatus($order, 'confirmed');
        $this->assertSame($q, $this->snap($product, 'M')['demand']);
        $this->setStatus($order, 'picked_up');
        $this->assertSame([10 - $q, 0], [$this->snap($product, 'M')['on_hand'], $this->snap($product, 'M')['demand']]);
        $this->setStatus($order, 'delivered');
        $this->assertSame(10 - $q, $this->snap($product, 'M')['on_hand']);
    }

    // 6: POS ---------------------------------------------------------------------------------
    public function test_06_pos_sale_takes_stock_immediately_and_cannot_oversell_units_held_for_confirmed_orders()
    {
        $p = $this->stocked(5);
        $held = $this->makeOrder([[$p, '', 4]], 'confirmed'); // 4 of the 5 units are earmarked
        $this->actingAs($this->makeAdmin());
        $temp = (string) random_int(810000000, 819999999);
        $cart = fn (int $q) => Cart::create(['owner_id' => User::where('user_type', 'admin')->orderBy('id')->value('id'), 'user_id' => null, 'temp_user_id' => $temp, 'product_id' => $p->id, 'variation' => '', 'quantity' => $q, 'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0]);
        $cart(2);
        $res = PosUtility::orderStore(['user_id' => null, 'temp_usder_id' => $temp, 'payment_type' => 'cash_on_delivery', 'shippingInfo' => ['name' => 'W', 'phone' => '01700000000', 'address' => 'c']]);
        $this->assertSame(5, $this->snap($p)['on_hand'], 'the 2-unit POS sale was refused: only 1 unit is free');
        $this->assertSame(4, $this->snap($p)['demand']);
        Cart::where('temp_user_id', $temp)->delete();
        $cart(1);
        PosUtility::orderStore(['user_id' => null, 'temp_usder_id' => $temp, 'payment_type' => 'cash_on_delivery', 'shippingInfo' => ['name' => 'W', 'phone' => '01700000000', 'address' => 'c']]);
        $this->assertSame([4, 4], [$this->snap($p)['on_hand'], $this->snap($p)['demand']], 'the free unit sold at once; the earmarked ones are untouched');
    }

    // 7: chat --------------------------------------------------------------------------------
    public function test_07_chat_order_lifecycle()
    {
        $p = $this->stocked();
        $a = \App\Models\ChatAccount::create(['platform' => 'facebook', 'external_id' => 'P' . uniqid(), 'name' => 'Page', 'status' => 'connected']);
        $conv = \App\Models\ChatConversation::create(['chat_account_id' => $a->id, 'platform' => 'facebook', 'external_id' => 'U' . uniqid(), 'customer_name' => 'Buyer']);
        $city = DB::table('cities')->insertGetId(['name' => 'Dhaka', 'state_id' => 1, 'cost' => 60, 'status' => 1]);
        $combined = app(\App\Services\Chat\ChatOrderService::class)->place($conv, $this->makeAdmin(), ['idempotency_key' => 'k-' . uniqid(), 'items' => [['product_id' => $p->id, 'variant' => '', 'quantity' => 3]], 'discount' => 0,
            'customer' => ['name' => 'Rahim', 'phone' => '01712345678', 'address' => 'House 1, Road 2', 'city_id' => $city]], '127.0.0.1');
        $this->walk(Order::where('combined_order_id', $combined->id)->firstOrFail(), $p, 3, 10);
    }

    // 8: payment gateway callback ------------------------------------------------------------
    public function test_08_gateway_failure_callback_cancels_without_inventing_stock_and_success_does_not_confirm()
    {
        $this->setUpLiveFunnel();
        $this->enablePaystation();
        PaymentGatewayAttempt::forgetReadiness();
        $gateway = new FakePaystation();
        $this->app->instance(PaystationGateway::class, $gateway);
        $_ENV['FRONTEND_URL'] = $_SERVER['FRONTEND_URL'] = 'https://shop.test';
        putenv('FRONTEND_URL=https://shop.test');
        [, $product] = $this->liveFunnel(['M' => 10], ['cash_on_delivery', 'paystation']);
        $begin = function (Order $o) use ($gateway) {
            $gateway->queue(['status_code' => 200, 'payment_url' => 'https://sandbox.paystation.test/pay/abc']);
            $this->postJson('/api/v2/paystation/begin', ['payment_type' => 'cart_payment', 'combined_order_id' => Crypt::encrypt($o->combined_order_id)])->assertOk();

            return PaymentGatewayAttempt::where('combined_order_id', $o->combined_order_id)->latest('id')->value('invoice_number');
        };
        $this->placeOrder($this->checkoutBody(['payment_type' => 'paystation']))->assertStatus(201);
        $failed = Order::where('payment_type', 'paystation')->latest('id')->firstOrFail();
        $this->assertSame([10, 0], [$this->snap($product, 'M')['on_hand'], $this->snap($product, 'M')['demand']]);
        $inv = $begin($failed);
        $gateway->queue(['status_code' => 200, 'data' => ['trx_status' => 'Failed', 'payment_amount' => 0, 'trx_id' => 'T1']], ['status_code' => 200, 'data' => ['trx_status' => 'Failed', 'payment_amount' => 0, 'trx_id' => 'T1']]);
        $this->get('/api/v2/paystation/callback?status=Failed&invoice_number=' . $inv);
        $this->assertSame('cancelled', $failed->fresh()->delivery_status);
        $this->assertSame([10, 0], [$this->snap($product, 'M')['on_hand'], $this->snap($product, 'M')['demand']], 'nothing was taken, so a failed payment gives nothing back');
        $this->get('/api/v2/paystation/callback?status=Failed&invoice_number=' . $inv);
        $this->assertSame(10, $this->snap($product, 'M')['on_hand'], 'a repeated callback never adds stock');

        $this->placeOrder($this->checkoutBody(['payment_type' => 'paystation'], ['phone' => '01898765432']))->assertStatus(201);
        $paid = Order::where('payment_type', 'paystation')->latest('id')->firstOrFail();
        $inv2 = $begin($paid);
        $gateway->queue(['status_code' => 200, 'data' => ['trx_status' => 'Successful', 'payment_amount' => (float) $paid->grand_total, 'trx_id' => 'T2']]);
        $this->get('/api/v2/paystation/callback?status=Successful&invoice_number=' . $inv2);
        $this->assertSame('paid', $paid->fresh()->payment_status);
        $this->assertSame('pending', $paid->fresh()->delivery_status, 'payment does not confirm the order or reserve stock by itself');
        $this->assertSame(0, $this->snap($product, 'M')['demand']);
        $this->disablePaystation();
    }

    // 9-12: line cancellation (partial), courier dispatch and courier return -----------------------
    public function test_09_10_12_line_partial_cancel_dispatch_courier_return_and_receipt()
    {
        $p = $this->stocked();
        $q = $this->stocked();
        $order = $this->makeOrder([[$p, '', 3], [$q, '', 2]], 'confirmed');
        $this->assertSame([3, 2], [$this->snap($p)['demand'], $this->snap($q)['demand']]);
        $lines = OrderDetail::where('order_id', $order->id)->orderBy('id')->get();
        $this->actingAs($this->makeAdmin())->postJson(route('orders.update-items-status'), ['order_id' => $order->id, 'updates' => [['order_detail_id' => $lines[1]->id, 'delivery_status' => 'cancelled']]]);
        $this->assertSame([3, 0], [$this->snap($p)['demand'], $this->snap($q)['demand']], 'cancelling one line releases only that line');
        $this->assertSame([10, 10], [$this->snap($p)['on_hand'], $this->snap($q)['on_hand']]);

        $this->setStatus($order, 'picked_up');
        $this->assertSame([7, 0], [$this->snap($p)['on_hand'], $this->snap($p)['demand']], 'courier dispatch of the remaining line');
        $this->assertSame(10, $this->snap($q)['on_hand'], 'the cancelled line was never dispatched');
        $this->setStatus($order, 'returned');
        $this->assertSame(7, $this->snap($p)['on_hand'], 'a courier return does not restock by itself');
        $this->actingAs($this->makeAdmin())->post(route('purchasing.returns.receive'), ['op_key' => 'r-' . uniqid(), 'order_detail_id' => $lines[0]->id, 'qty' => 3, 'condition' => 'resellable']);
        $this->assertSame(10, $this->snap($p)['on_hand'], 'after the goods are received and inspected the units are back');
        $this->actingAs($this->makeAdmin())->post(route('purchasing.returns.receive'), ['op_key' => 'r-' . uniqid(), 'order_detail_id' => $lines[0]->id, 'qty' => 3, 'condition' => 'resellable']);
        $this->assertSame(10, $this->snap($p)['on_hand'], 'receiving the same return again cannot restock twice');
    }
}

/** Scripted gateway: answers are queued by the test, any unexpected call fails it. */
class FakePaystation extends PaystationGateway
{
    private array $answers = [];

    public function queue(...$answers): void
    {
        array_push($this->answers, ...$answers);
    }

    protected function request(string $url, array $data)
    {
        if ($this->answers === []) {
            throw new \RuntimeException('Unexpected gateway call to ' . $url);
        }

        return array_shift($this->answers);
    }
}
