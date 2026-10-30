<?php

namespace Tests\Feature\Funnel;

use App\Models\Order;
use App\Models\PaymentGatewayAttempt;
use App\Services\Payments\PaystationGateway;
use Illuminate\Support\Facades\Crypt;
use Tests\Funnel\BuildsLiveFunnel;
use Tests\Inventory\InventoryTestCase;

/**
 * Online payment through Paystation: an order is marked paid once, cancelled
 * only when the gateway confirms the payment failed, never cancelled when the
 * gateway gives no clear answer, and stale unpaid orders give their stock back
 * safely. The gateway itself is replaced by a scripted fake.
 */
class PaystationReliabilityTest extends InventoryTestCase
{
    use BuildsLiveFunnel;

    private FakePaystation $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLiveFunnel();
        $this->enablePaystation();
        PaymentGatewayAttempt::forgetReadiness();
        $this->gateway = new FakePaystation();
        $this->app->instance(PaystationGateway::class, $this->gateway);

        $_ENV['FRONTEND_URL'] = $_SERVER['FRONTEND_URL'] = 'https://shop.test';
        putenv('FRONTEND_URL=https://shop.test');
    }

    protected function tearDown(): void
    {
        $this->disablePaystation();
        putenv('FRONTEND_URL');
        unset($_ENV['FRONTEND_URL'], $_SERVER['FRONTEND_URL']);
        parent::tearDown();
    }

    /** An online order for one unit; returns [order, product]. */
    private function onlineOrder(string $phone = '01712345678'): array
    {
        [, $product] = $this->product ?? ($this->product = $this->liveFunnel(['M' => 10], ['cash_on_delivery', 'paystation']));
        $this->placeOrder($this->checkoutBody(['payment_type' => 'paystation'], ['phone' => $phone]))->assertStatus(201);

        return [Order::where('payment_type', 'paystation')->latest('id')->firstOrFail(), $product];
    }

    private ?array $product = null;

    private function startPayment(Order $order): string
    {
        $this->gateway->queue(['status_code' => 200, 'payment_url' => 'https://sandbox.paystation.test/pay/abc']);
        $this->postJson('/api/v2/paystation/begin', ['payment_type' => 'cart_payment', 'combined_order_id' => Crypt::encrypt($order->combined_order_id)])
            ->assertOk()->assertJsonPath('url', 'https://sandbox.paystation.test/pay/abc');

        return PaymentGatewayAttempt::where('combined_order_id', $order->combined_order_id)->latest('id')->value('invoice_number');
    }

    private function status(string $state, ?float $amount = null): array
    {
        return ['status_code' => 200, 'data' => ['trx_status' => $state, 'payment_amount' => $amount ?? 0, 'trx_id' => 'TRX' . random_int(1000, 9999)]];
    }

    public function test_a_confirmed_payment_marks_the_order_paid_once_and_returns_to_the_funnel(): void
    {
        [$order, $product] = $this->onlineOrder();
        $invoice = $this->startPayment($order);
        $this->assertSame(9, $this->stockQty($product, 'M'));

        $this->gateway->queue($this->status('Successful', (float) $order->grand_total));
        $response = $this->get('/api/v2/paystation/callback?status=Successful&invoice_number=' . $invoice . '&trx_id=T1');
        $response->assertRedirect();
        $this->assertStringContainsString("/funnel/{$this->slug}?receipt=", $response->headers->get('Location'));
        $this->assertStringEndsWith('&payment=success', $response->headers->get('Location'));

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $details = $order->payment_details;
        $this->assertSame(PaymentGatewayAttempt::PAID, PaymentGatewayAttempt::where('invoice_number', $invoice)->value('status'));

        // The notification arriving afterwards does not ask the gateway or settle again.
        $calls = $this->gateway->calls;
        $this->postJson('/api/v2/paystation/ipn', ['invoice_number' => $invoice, 'trx_id' => 'T1'])->assertOk();
        $this->assertSame($calls, $this->gateway->calls);
        $this->assertSame($details, $order->fresh()->payment_details);
        $this->assertSame(9, $this->stockQty($product, 'M'));
    }

    public function test_a_confirmed_failure_cancels_and_returns_stock_exactly_once(): void
    {
        [$order, $product] = $this->onlineOrder();
        $invoice = $this->startPayment($order);

        $this->gateway->queue($this->status('Failed'), $this->status('Failed'));
        $first = $this->get('/api/v2/paystation/callback?status=Failed&invoice_number=' . $invoice);
        $this->assertStringEndsWith('&payment=failed', $first->headers->get('Location'));
        $this->assertSame('cancelled', $order->fresh()->delivery_status);
        $this->assertSame(10, $this->stockQty($product, 'M'));

        // A repeated return does not give the stock back twice.
        $this->get('/api/v2/paystation/callback?status=Failed&invoice_number=' . $invoice);
        $this->assertSame(10, $this->stockQty($product, 'M'));

        // A cancelled order can never be sent to the gateway again.
        $this->postJson('/api/v2/paystation/begin', ['payment_type' => 'cart_payment', 'combined_order_id' => Crypt::encrypt($order->combined_order_id)])
            ->assertJsonPath('result', false);
    }

    public function test_a_failed_answer_for_an_invoice_the_store_never_started_cancels_nothing(): void
    {
        [$order, $product] = $this->onlineOrder();
        $this->startPayment($order);

        // Anyone can call the return address with a made-up invoice number for this order.
        $forged = 'AIZ-' . $order->combined_order_id . '-20260101000000' . '42';
        $this->gateway->queue($this->status('Failed'));
        $this->get('/api/v2/paystation/callback?status=Failed&invoice_number=' . $forged);

        $this->assertNotSame('cancelled', $order->fresh()->delivery_status);
        $this->assertSame(9, $this->stockQty($product, 'M'));
    }

    public function test_no_clear_answer_from_the_gateway_never_cancels_the_order(): void
    {
        [$order, $product] = $this->onlineOrder();
        $invoice = $this->startPayment($order);

        // Gateway unreachable, then a pending answer, then an unexpected value.
        $this->gateway->queue(false, $this->status('Pending'), $this->status('Processing'));
        foreach (range(1, 3) as $ignored) {
            $response = $this->get('/api/v2/paystation/callback?status=Failed&invoice_number=' . $invoice);
            $this->assertStringEndsWith('&payment=pending', $response->headers->get('Location'));
        }
        $order->refresh();
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertNotSame('cancelled', $order->delivery_status);
        $this->assertSame(9, $this->stockQty($product, 'M'));

        // A "successful" answer for the wrong amount is not a payment of this order.
        $this->gateway->queue($this->status('Successful', 1.0));
        $this->get('/api/v2/paystation/callback?invoice_number=' . $invoice);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertNotSame('cancelled', $order->fresh()->delivery_status);

        // The notification never cancels, even on a failed answer.
        $this->gateway->queue($this->status('Failed'));
        $this->postJson('/api/v2/paystation/ipn', ['invoice_number' => $invoice])->assertOk();
        $this->assertNotSame('cancelled', $order->fresh()->delivery_status);
    }

    public function test_a_payment_after_cancellation_is_recorded_and_flagged_not_revived(): void
    {
        [$order, $product] = $this->onlineOrder();
        $invoice = $this->startPayment($order);
        app(PaystationGateway::class)->cancelUnpaid($order->combinedOrder ?? \App\Models\CombinedOrder::find($order->combined_order_id), 'test');
        $this->assertSame(10, $this->stockQty($product, 'M'));

        $this->gateway->queue($this->status('Successful', (float) $order->grand_total));
        $this->postJson('/api/v2/paystation/ipn', ['invoice_number' => $invoice])->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('cancelled', $order->delivery_status);
        $this->assertStringContainsString('payment received after this order was cancelled', $order->additional_info);
        $this->assertSame(10, $this->stockQty($product, 'M'));
    }

    public function test_paid_orders_are_never_sent_to_the_gateway_again(): void
    {
        [$order] = $this->onlineOrder();
        Order::where('id', $order->id)->update(['payment_status' => 'paid']);
        $this->postJson('/api/v2/paystation/begin', ['payment_type' => 'cart_payment', 'combined_order_id' => Crypt::encrypt($order->combined_order_id)])
            ->assertJsonPath('result', false);
        $this->assertSame(0, $this->gateway->calls);
    }

    public function test_the_expiry_job_settles_stale_unpaid_orders_safely(): void
    {
        $old = now()->subMinutes(90);
        [$neverStarted, $product] = $this->onlineOrder('01712345678');
        [$failed] = $this->onlineOrder('01898765432');
        [$paidLate] = $this->onlineOrder('01611111111');
        [$unknown] = $this->onlineOrder('01922222222');
        $failedInvoice = $this->startPayment($failed);
        $paidInvoice = $this->startPayment($paidLate);
        $unknownInvoice = $this->startPayment($unknown);
        $this->placeOrder($this->checkoutBody([], ['phone' => '01555555555']))->assertStatus(201);
        $cash = Order::where('payment_type', 'cash_on_delivery')->latest('id')->firstOrFail();
        Order::query()->update(['created_at' => $old]);
        $this->assertSame(5, $this->stockQty($product, 'M'));

        // Switched off by default: nothing happens.
        $before = $this->gateway->calls;
        $this->artisan('orders:expire-unpaid')->expectsOutputToContain('switched off')->assertExitCode(0);
        $this->assertSame($before, $this->gateway->calls);

        config(['funnel.unpaid_online_orders.expire' => true]);
        // A dry run reports and changes nothing.
        $this->gateway->queue($this->status('Failed'), $this->status('Successful', (float) $paidLate->grand_total), false);
        $this->artisan('orders:expire-unpaid', ['--dry-run' => true])->expectsOutputToContain('2 cancelled')->assertExitCode(0);
        $this->assertSame(5, $this->stockQty($product, 'M'));

        $this->gateway->queue($this->status('Failed'), $this->status('Successful', (float) $paidLate->grand_total), false);
        $this->artisan('orders:expire-unpaid')->expectsOutputToContain('1 paid, 2 cancelled, 1 still waiting')->assertExitCode(0);

        $this->assertSame('cancelled', $neverStarted->fresh()->delivery_status);
        $this->assertSame('cancelled', $failed->fresh()->delivery_status);
        $this->assertSame('paid', $paidLate->fresh()->payment_status);
        $this->assertNotSame('cancelled', $paidLate->fresh()->delivery_status);
        $this->assertNotSame('cancelled', $unknown->fresh()->delivery_status);
        $this->assertNotSame('cancelled', $cash->fresh()->delivery_status);
        $this->assertSame(7, $this->stockQty($product, 'M'));

        // Still no answer two days later: left for staff with a note, and not retried.
        Order::where('id', $unknown->id)->update(['created_at' => now()->subHours(50)]);
        \App\Models\CombinedOrder::where('id', $unknown->combined_order_id)->update(['created_at' => now()->subHours(50)]);
        $this->gateway->queue(false);
        $this->artisan('orders:expire-unpaid')->expectsOutputToContain('1 left for staff')->assertExitCode(0);
        $this->assertStringContainsString('did not confirm whether this order was paid', $unknown->fresh()->additional_info);
        $this->assertNotSame('cancelled', $unknown->fresh()->delivery_status);
        $calls = $this->gateway->calls;
        $this->artisan('orders:expire-unpaid')->expectsOutputToContain('0 paid, 0 cancelled')->assertExitCode(0);
        $this->assertSame($calls, $this->gateway->calls);
        $this->assertSame(7, $this->stockQty($product, 'M'));
    }
}

/** Paystation with scripted answers instead of HTTP calls. */
class FakePaystation extends PaystationGateway
{
    /** @var array<int, array|false> */
    private array $answers = [];
    public int $calls = 0;

    public function queue(...$answers): void
    {
        array_push($this->answers, ...$answers);
    }

    protected function request(string $url, array $data)
    {
        $this->calls++;
        if ($this->answers === []) {
            throw new \RuntimeException('Unexpected gateway call to ' . $url);
        }

        return array_shift($this->answers);
    }
}
