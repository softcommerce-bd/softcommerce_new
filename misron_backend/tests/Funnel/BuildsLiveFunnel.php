<?php

namespace Tests\Funnel;

use App\Models\Funnel;
use App\Models\Product;
use App\Services\Funnel\Checkout\FunnelOrderService;
use App\Services\Funnel\FunnelService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * A published funnel that takes orders, for tests built on InventoryTestCase:
 * one offer, a checkout form and a delivery district.
 */
trait BuildsLiveFunnel
{
    protected int $cityId;
    protected string $slug;

    protected function setUpLiveFunnel(): void
    {
        config(['funnel.checkout.enabled' => true]);
        FunnelOrderService::forgetReadiness();
        foreach (['01712345678', '01898765432', '01611111111', '01922222222'] as $phone) {
            RateLimiter::clear('funnel-checkout:phone:' . $phone);
        }
        Cache::flush();

        $this->cityId = DB::table('cities')->insertGetId(['name' => 'Dhaka', 'state_id' => 1, 'cost' => 60, 'status' => 1]);
        $this->slug = 'live-' . Str::lower(Str::random(8));
    }

    protected function enablePaystation(): void
    {
        putenv('PAYSTATION_MERCHANT_ID=TESTMERCHANT');
        $_ENV['PAYSTATION_MERCHANT_ID'] = $_SERVER['PAYSTATION_MERCHANT_ID'] = 'TESTMERCHANT';
        DB::table('payment_methods')->where('name', 'paystation')->delete();
        DB::table('payment_methods')->insert(['name' => 'paystation', 'active' => 1]);
    }

    protected function disablePaystation(): void
    {
        putenv('PAYSTATION_MERCHANT_ID');
        unset($_ENV['PAYSTATION_MERCHANT_ID'], $_SERVER['PAYSTATION_MERCHANT_ID']);
    }

    /** @return array{0: Funnel, 1: Product} */
    protected function liveFunnel(array $stock = ['M' => 10], array $paymentAllowed = [], ?string $slug = null): array
    {
        $admin = $this->makeAdmin();
        $product = $this->makeStockedProduct($stock, 1000, 400, ['name' => 'Super Blender']);
        $service = app(FunnelService::class);
        $funnel = $service->create(['title' => 'Blender Mega Offer', 'slug' => $slug ?? $this->slug], $admin);

        $page = [
            'schemaVersion' => 1,
            'page' => ['sections' => [
                ['id' => 'hero_001', 'type' => 'section', 'children' => [['id' => 'heading_001', 'type' => 'heading', 'props' => ['text' => 'Offer', 'level' => 'h1']]]],
                [
                    'id' => 'offers_001', 'type' => 'product_offer_selector', 'props' => ['selectionMode' => 'multiple'],
                    'productBindings' => [
                        ['key' => 'offer_one', 'items' => [['productId' => $product->id, 'variant' => array_key_first($stock), 'quantity' => 1, 'lockVariant' => true]], 'offer' => ['type' => 'none']],
                    ],
                ],
                ['id' => 'checkout_001', 'type' => 'checkout_form', 'props' => [], 'children' => [
                    ['id' => 'pay_001', 'type' => 'payment_method_selector', 'props' => ['allowed' => $paymentAllowed]],
                    ['id' => 'buy_001', 'type' => 'purchase_button', 'props' => ['text' => 'Order now']],
                ]],
            ]],
        ];
        [$funnel] = $service->saveDraft($funnel, $page, 1, $admin);
        [$funnel] = $service->publish($funnel, $admin);

        return [$funnel->fresh(), $product];
    }

    protected function checkoutBody(array $override = [], array $customer = []): array
    {
        return array_replace([
            'idempotency_key' => 'key-' . Str::random(24),
            'selections' => [['key' => 'offer_one', 'quantity' => 1]],
            'customer' => array_replace(['name' => 'রহিম উদ্দিন', 'phone' => '01712345678', 'address' => 'House 5, Road 7, Dhanmondi', 'district_id' => $this->cityId], $customer),
            'payment_type' => 'cash_on_delivery',
            'delivery_method' => 'home_delivery',
        ], $override);
    }

    protected function placeOrder(array $body, ?string $slug = null)
    {
        return $this->postJson('/api/v2/funnels/' . ($slug ?? $this->slug) . '/checkout', $body);
    }
}
