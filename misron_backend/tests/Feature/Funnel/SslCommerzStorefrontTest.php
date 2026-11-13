<?php

namespace Tests\Feature\Funnel;

use App\Models\Order;
use App\Models\PaymentGatewayAttempt;
use App\Services\Payments\PaystationGateway;
use App\Services\Payments\SslCommerzGateway;
use Illuminate\Support\Facades\Crypt;
use Tests\Funnel\BuildsLiveFunnel;
use Tests\Inventory\InventoryTestCase;

/**
 * SSLCommerz online payment: an order is marked paid only after the validation API confirms the
 * full amount for this store's own transaction, a made-up transaction cancels nothing, and a
 * failed payment cancels the order once. The gateway is replaced by a scripted fake.
 */
class SslCommerzStorefrontTest extends InventoryTestCase
{
    use BuildsLiveFunnel;

    private FakeSsl $gateway;
    private ?array $product = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLiveFunnel();
        $this->enablePaystation();
        PaymentGatewayAttempt::forgetReadiness();
        foreach (['SSLCZ_STORE_ID' => 'teststore', 'SSLCZ_STORE_PASSWD' => 'testpass', 'FRONTEND_URL' => 'https://shop.test'] as $k => $v) {
            $_ENV[$k] = $_SERVER[$k] = $v;
            putenv("$k=$v");
        }
        $this->gateway = new FakeSsl(app(PaystationGateway::class));
        $this->app->instance(SslCommerzGateway::class, $this->gateway);
    }

    protected function tearDown(): void
    {
        $this->disablePaystation();
        foreach (['SSLCZ_STORE_ID', 'SSLCZ_STORE_PASSWD', 'FRONTEND_URL'] as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
        parent::tearDown();
    }

    private function onlineOrder(): array
    {
        [, $product] = $this->product ?? ($this->product = $this->liveFunnel(['M' => 10], ['cash_on_delivery', 'paystation']));
        $this->placeOrder($this->checkoutBody(['payment_type' => 'paystation'], ['phone' => '01712345678']))->assertStatus(201);

        return [Order::where('payment_type', 'paystation')->latest('id')->firstOrFail(), $product];
    }

    private function begin(Order $order): string
    {
        $this->gateway->queue(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.test/gw/abc']);
        $this->postJson('/api/v2/sslcommerz/storefront/begin', ['combined_order_id' => Crypt::encrypt($order->combined_order_id)])
            ->assertOk()->assertJsonPath('result', true)->assertJsonPath('url', 'https://sandbox.sslcommerz.test/gw/abc');

        return PaymentGatewayAttempt::where('combined_order_id', $order->combined_order_id)->where('gateway', 'sslcommerz')->latest('id')->value('invoice_number');
    }

    private function valid(string $tran, float $amount): array
    {
        return ['status' => 'VALID', 'tran_id' => $tran, 'amount' => (string) $amount, 'currency_type' => 'BDT', 'val_id' => 'V1'];
    }

    public function test_a_validated_payment_marks_the_order_paid_once(): void
    {
        [$order] = $this->onlineOrder();
        $tran = $this->begin($order);

        $this->gateway->queue($this->valid($tran, (float) $order->grand_total));
        $this->post('/api/v2/sslcommerz/storefront/return/success', ['tran_id' => $tran, 'val_id' => 'V1', 'status' => 'VALID'])->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);

        // The notification arriving afterwards does not ask the gateway again.
        $calls = $this->gateway->calls;
        $this->postJson('/api/v2/sslcommerz/storefront/ipn', ['tran_id' => $tran, 'val_id' => 'V1'])->assertOk();
        $this->assertSame($calls, $this->gateway->calls);
    }

    public function test_a_payment_for_less_than_the_order_is_not_accepted(): void
    {
        [$order] = $this->onlineOrder();
        $tran = $this->begin($order);

        $this->gateway->queue($this->valid($tran, (float) $order->grand_total - 50));
        $this->post('/api/v2/sslcommerz/storefront/return/success', ['tran_id' => $tran, 'val_id' => 'V1'])->assertRedirect();
        $this->assertNotSame('paid', $order->fresh()->payment_status);
        $this->assertNotSame('cancelled', $order->fresh()->delivery_status);
    }

    public function test_a_transaction_the_store_never_started_changes_nothing(): void
    {
        [$order] = $this->onlineOrder();
        $this->begin($order);

        $forged = 'AIZ-' . $order->combined_order_id . '-20260101000000' . '42';
        $this->post('/api/v2/sslcommerz/storefront/return/fail', ['tran_id' => $forged])->assertRedirect();
        $this->assertNotSame('cancelled', $order->fresh()->delivery_status);
        $this->assertNotSame('paid', $order->fresh()->payment_status);
    }

    public function test_a_failed_payment_cancels_the_order_and_returns_stock_once(): void
    {
        [$order, $product] = $this->onlineOrder();
        $tran = $this->begin($order);
        $this->assertSame(9, $this->stockQty($product, 'M'));

        $this->post('/api/v2/sslcommerz/storefront/return/fail', ['tran_id' => $tran])->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->delivery_status);
        $this->assertSame(10, $this->stockQty($product, 'M'));

        $this->post('/api/v2/sslcommerz/storefront/return/cancel', ['tran_id' => $tran])->assertRedirect();
        $this->assertSame(10, $this->stockQty($product, 'M'));
    }
}

class FakeSsl extends SslCommerzGateway
{
    /** @var array<int, array|false> */
    private array $answers = [];
    public int $calls = 0;

    public function queue(...$answers): void
    {
        array_push($this->answers, ...$answers);
    }

    protected function call(string $url, ?array $post, bool $isPost)
    {
        $this->calls++;
        if ($this->answers === []) {
            throw new \RuntimeException('Unexpected gateway call to ' . $url);
        }

        return array_shift($this->answers);
    }
}
