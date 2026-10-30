<?php

namespace Tests\Feature\Funnel;

use App\Http\Controllers\Api\V2\PaystationController;
use App\Models\Funnel;
use App\Models\FunnelCheckoutAttempt;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\User;
use App\Services\Funnel\Checkout\FunnelOrderService;
use App\Services\Funnel\FunnelService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Inventory\InventoryTestCase;

/**
 * Ordering from a published funnel page, end to end through the HTTP API: prices
 * from the server, stock through the shared ledger, the order as an ordinary order
 * of the store, and safe repeats.
 */
class FunnelCheckoutTest extends InventoryTestCase
{
    private int $cityId;
    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();
        config(['funnel.checkout.enabled' => true]);
        FunnelOrderService::forgetReadiness();
        RateLimiter::clear('funnel-checkout:phone:01712345678');
        Cache::flush();

        $this->cityId = DB::table('cities')->insertGetId(['name' => 'Dhaka', 'state_id' => 1, 'cost' => 60, 'status' => 1]);
        $this->slug = 'checkout-' . Str::lower(Str::random(6));
    }

    protected function tearDown(): void
    {
        putenv('PAYSTATION_MERCHANT_ID');
        unset($_ENV['PAYSTATION_MERCHANT_ID'], $_SERVER['PAYSTATION_MERCHANT_ID']);
        parent::tearDown();
    }

    // ── fixtures ───────────────────────────────────────────────────────────

    private function enablePaystation(): void
    {
        putenv('PAYSTATION_MERCHANT_ID=TESTMERCHANT');
        $_ENV['PAYSTATION_MERCHANT_ID'] = $_SERVER['PAYSTATION_MERCHANT_ID'] = 'TESTMERCHANT';
        DB::table('payment_methods')->where('name', 'paystation')->delete();
        DB::table('payment_methods')->insert(['name' => 'paystation', 'active' => 1]);
    }

    /** Publishes a one-offer funnel with a checkout form and returns [funnel, product]. */
    private function liveFunnel(array $offer = ['type' => 'percent', 'value' => 10], array $form = [], array $stock = ['M' => 10], array $paymentAllowed = []): array
    {
        $admin = $this->makeAdmin();
        $product = $this->makeStockedProduct($stock, 1000, 400, ['name' => 'Super Blender']);
        $service = app(FunnelService::class);
        $funnel = $service->create(['title' => 'Blender Mega Offer', 'slug' => $this->slug], $admin);

        $page = [
            'schemaVersion' => 1,
            'page' => ['sections' => [
                ['id' => 'hero_001', 'type' => 'section', 'children' => [['id' => 'heading_001', 'type' => 'heading', 'props' => ['text' => 'Offer', 'level' => 'h1']]]],
                [
                    'id' => 'offers_001', 'type' => 'product_offer_selector', 'props' => ['selectionMode' => 'multiple'],
                    'productBindings' => [
                        ['key' => 'offer_one', 'items' => [['productId' => $product->id, 'variant' => array_key_first($stock), 'quantity' => 1, 'lockVariant' => true]], 'offer' => $offer],
                    ],
                ],
                ['id' => 'checkout_001', 'type' => 'checkout_form', 'props' => $form, 'children' => [
                    ['id' => 'pay_001', 'type' => 'payment_method_selector', 'props' => ['allowed' => $paymentAllowed]],
                    ['id' => 'buy_001', 'type' => 'purchase_button', 'props' => ['text' => 'Order now']],
                ]],
            ]],
        ];
        [$funnel] = $service->saveDraft($funnel, $page, 1, $admin);
        [$funnel] = $service->publish($funnel, $admin);

        return [$funnel->fresh(), $product];
    }

    private function body(array $override = [], array $customer = []): array
    {
        return array_replace([
            'idempotency_key' => 'key-' . Str::random(24),
            'selections' => [['key' => 'offer_one', 'quantity' => 2]],
            'customer' => array_replace(['name' => 'রহিম উদ্দিন', 'phone' => '01712345678', 'address' => 'House 5, Road 7, Dhanmondi', 'district_id' => $this->cityId], $customer),
            'payment_type' => 'cash_on_delivery',
            'delivery_method' => 'home_delivery',
        ], $override);
    }

    private function place(array $body, ?string $slug = null)
    {
        return $this->postJson('/api/v2/funnels/' . ($slug ?? $this->slug) . '/checkout', $body);
    }

    // ── what the merchant is told before publishing ────────────────────────

    public function test_publishing_warns_about_ordering_payment_and_stock_records(): void
    {
        [$funnel, $product] = $this->liveFunnel(['type' => 'none'], [], ['M' => 6, 'L' => 4], ['paystation', 'cash_on_delivery']);
        $service = app(FunnelService::class);
        $codes = fn () => array_column($service->check($funnel->fresh())->warnings(), 'code');

        // Ordering on, cash works, online payment is not set up in the store, all stock has cost records.
        $this->assertNotContains('checkout_disabled', $codes());
        $this->assertContains('payment_unavailable', $codes());
        $this->assertNotContains('inventory_unverified', $codes());

        config(['funnel.checkout.enabled' => false]);
        $this->assertContains('checkout_disabled', $codes());

        // Units on the shelf that no purchase record covers.
        DB::table('purchase_products')->where('product_id', $product->id)->where('variant', 'L')->update(['remaining_qty' => 1]);
        $warnings = $service->check($funnel->fresh())->warnings();
        $stock = array_values(array_filter($warnings, fn ($w) => $w['code'] === 'inventory_unverified'));
        // The offer is for variation M, which is fine: only what customers can actually pick is reported.
        $this->assertSame([], $stock);

        DB::table('purchase_products')->where('product_id', $product->id)->where('variant', 'M')->update(['remaining_qty' => 2]);
        $stock = array_values(array_filter($service->check($funnel->fresh())->warnings(), fn ($w) => $w['code'] === 'inventory_unverified'));
        $this->assertCount(1, $stock);
        $this->assertStringContainsString('shows 6 in stock', $stock[0]['message']);
        $this->assertStringContainsString('at most 2', $stock[0]['message']);

        DB::table('purchase_products')->where('product_id', $product->id)->where('variant', 'M')->update(['remaining_qty' => 0]);
        $none = array_values(array_filter($service->check($funnel->fresh())->warnings(), fn ($w) => $w['code'] === 'inventory_unverified'));
        $this->assertStringContainsString('refused until its inventory is checked', $none[0]['message']);
    }

    public function test_the_public_page_endpoint_serves_the_published_document_and_nothing_else(): void
    {
        [$funnel] = $this->liveFunnel(['type' => 'none']);
        $admin = $this->makeAdmin();

        $page = $this->getJson("/api/v2/funnels/{$this->slug}")->assertOk();
        $page->assertJsonPath('funnel.title', 'Blender Mega Offer')->assertJsonPath('funnel.version', 1)->assertJsonPath('document.page.sections.0.id', 'hero_001');
        $this->assertArrayNotHasKey('internal_name', $page->json('funnel'));
        $this->assertArrayNotHasKey('created_by', $page->json('funnel'));

        // A later draft edit is invisible until it is published.
        $service = app(FunnelService::class);
        $draft = $funnel->fresh()->draft_content;
        $draft['page']['sections'][0]['id'] = 'edited_001';
        $service->saveDraft($funnel->fresh(), $draft, $funnel->fresh()->draft_revision, $admin);
        $this->getJson("/api/v2/funnels/{$this->slug}")->assertJsonPath('document.page.sections.0.id', 'hero_001');

        $service->unpublish($funnel->fresh(), $admin);
        $this->getJson("/api/v2/funnels/{$this->slug}")->assertStatus(404);
        $this->getJson('/api/v2/funnels/not-a-page')->assertStatus(404);
    }

    public function test_browsing_traffic_does_not_use_up_the_allowance_for_placing_the_order(): void
    {
        $this->liveFunnel(['type' => 'none']);
        $one = ['selections' => [['key' => 'offer_one', 'quantity' => 1]]];

        // A busy page re-prices on every change; its counter is separate from the order button's.
        for ($i = 0; $i < 30; $i++) {
            $this->postJson("/api/v2/funnels/{$this->slug}/quote", $one + ['district_id' => $this->cityId])->assertOk();
            $this->getJson("/api/v2/funnels/{$this->slug}/offers")->assertOk();
        }

        $this->place($this->body($one))->assertStatus(201);
    }

    // ── switched off, not set up ───────────────────────────────────────────

    public function test_checkout_is_refused_while_the_feature_flag_is_off(): void
    {
        [, $product] = $this->liveFunnel();
        config(['funnel.checkout.enabled' => false]);

        $this->getJson("/api/v2/funnels/{$this->slug}/checkout/config")->assertOk()
            ->assertJsonPath('enabled', false)->assertJsonPath('reason', 'checkout_disabled');
        $this->place($this->body())->assertStatus(503)->assertJsonPath('code', 'checkout_disabled');

        $this->assertSame(0, Order::where('funnel_id', '>', 0)->count());
        $this->assertSame(10, $this->stockQty($product, 'M'));
    }

    public function test_unpublished_pages_take_no_orders_and_a_page_without_a_form_says_so(): void
    {
        $this->place($this->body(), 'no-such-page')->assertStatus(404)->assertJsonPath('code', 'not_found');

        [$funnel] = $this->liveFunnel();
        $document = $funnel->publishedVersion->content;
        $document['page']['sections'] = array_values(array_filter($document['page']['sections'], fn ($s) => $s['type'] !== 'checkout_form'));
        $funnel->publishedVersion->forceFill(['content' => $document])->save();
        Cache::flush();

        $this->getJson("/api/v2/funnels/{$this->slug}/checkout/config")->assertJsonPath('enabled', false)->assertJsonPath('reason', 'no_checkout');
        $this->place($this->body())->assertStatus(404)->assertJsonPath('code', 'no_checkout');
    }

    // ── the order ──────────────────────────────────────────────────────────

    public function test_config_describes_the_form_and_the_payment_methods_in_force(): void
    {
        $this->enablePaystation();
        $this->liveFunnel(['type' => 'none'], ['requireConsent' => true, 'consentText' => 'I agree to the terms']);

        $config = $this->getJson("/api/v2/funnels/{$this->slug}/checkout/config")->assertOk();

        $config->assertJsonPath('enabled', true)->assertJsonPath('checkout.require_consent', true)->assertJsonPath('otp_required', false);
        $config->assertJsonPath('districts.0.name', 'Dhaka')->assertJsonPath('districts.0.charge.amount', 60);
        $keys = array_column($config->json('checkout.fields'), 'key');
        $this->assertEqualsCanonicalizing(['name', 'phone', 'address', 'district', 'area', 'notes'], $keys);
        $this->assertEqualsCanonicalizing(['cash_on_delivery', 'paystation'], array_column($config->json('payment_methods'), 'key'));
        $required = array_column(array_filter($config->json('checkout.fields'), fn ($f) => $f['required']), 'key');
        $this->assertEqualsCanonicalizing(['name', 'phone', 'address', 'district'], $required);
    }

    public function test_a_cash_on_delivery_order_becomes_an_ordinary_order_of_the_store(): void
    {
        [$funnel, $product] = $this->liveFunnel();
        $batch = \App\Models\PurchaseProduct::where('product_id', $product->id)->first();

        $response = $this->place($this->body());

        $response->assertStatus(201)->assertJsonPath('replayed', false)->assertJsonPath('order.payment.method', 'cash_on_delivery')
            ->assertJsonPath('order.payment.status', 'unpaid')->assertJsonPath('order.payment.action', 'none');
        // 2 x 1,000 less the funnel's 10% = 1,800, plus the district's delivery of 60.
        $response->assertJsonPath('order.totals.products.amount', 1800)->assertJsonPath('order.totals.delivery.amount', 60)->assertJsonPath('order.totals.total.amount', 1860);

        $order = Order::where('funnel_id', $funnel->id)->firstOrFail();
        $this->assertSame($funnel->published_version_id, (int) $order->funnel_version_id);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $order->code);
        $this->assertSame('cash_on_delivery', $order->payment_type);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('pending', $order->delivery_status);
        $this->assertSame('home_delivery', $order->shipping_type);
        $this->assertSame('app', $order->order_from);
        $this->assertEquals(1860, $order->grand_total);
        $this->assertEquals(1860, $order->due_amount);
        $this->assertStringContainsString('Blender Mega Offer', $order->additional_info);
        $this->assertGreaterThan(0, (int) $order->guest_id);

        $address = json_decode($order->shipping_address, true);
        $this->assertSame(['name' => 'রহিম উদ্দিন', 'phone' => '01712345678', 'city' => 'Dhaka', 'country' => 'Bangladesh'], array_intersect_key($address, array_flip(['name', 'phone', 'city', 'country'])));
        $this->assertSame('home_delivery', $address['delivery_type']);

        $line = OrderDetail::where('order_id', $order->id)->firstOrFail();
        $this->assertSame($product->id, (int) $line->product_id);
        $this->assertSame('M', $line->variation);
        $this->assertSame(2, (int) $line->quantity);
        $this->assertEquals(1800, $line->price);
        $this->assertEquals(900, $line->selling_price);
        $this->assertEquals(400, $line->buying_price);
        $this->assertEquals(1000, $line->profit);
        $this->assertEquals(60, $line->shipping_cost);
        $this->assertSame($batch->purchase_id, (int) $line->purchase_id);
        $this->assertSame([['id' => $batch->id, 'qty' => 2, 'cost' => '400']], json_decode($line->fifo_batch_info, true));

        // Stock moved through the shared ledger; the customer record exists; the attempt keeps the agreed prices.
        $this->assertSame(8, $this->stockQty($product, 'M'));
        $this->assertSame(8, $this->batchRemaining($product, 'M'));
        $this->assertSame(1, User::where('phone', '01712345678')->count());
        $attempt = FunnelCheckoutAttempt::firstOrFail();
        $this->assertSame('placed', $attempt->status);
        $this->assertSame(1800, $attempt->snapshot()['quote']['totals']['product_subtotal']['amount']);
        $this->assertSame(60, (int) ($attempt->snapshot()['delivery']['cents'] / 100));

        // The confirmation carries no address or phone.
        $this->assertStringNotContainsString('Dhanmondi', $response->getContent());
        $this->assertStringNotContainsString('01712345678', $response->getContent());
    }

    public function test_the_browser_can_only_choose_never_price(): void
    {
        [, $product] = $this->liveFunnel();

        $response = $this->place($this->body([
            'selections' => [['key' => 'offer_one', 'quantity' => 2, 'price' => 1, 'total' => 1, 'discount' => 99999]],
            'total' => 1, 'grand_total' => 1, 'delivery' => 0, 'delivery_charge' => 0, 'discount' => 99999,
            'bindings' => [['key' => 'offer_one', 'offer' => ['type' => 'percent', 'value' => 99]]],
        ]));

        $response->assertStatus(201)->assertJsonPath('order.totals.total.amount', 1860);
        $this->assertEquals(1860, Order::firstOrFail()->grand_total);
        $this->assertSame(8, $this->stockQty($product, 'M'));
    }

    public function test_free_delivery_offers_waive_the_charge(): void
    {
        $this->liveFunnel(['type' => 'percent', 'value' => 10, 'freeDelivery' => true]);

        $this->place($this->body())->assertStatus(201)->assertJsonPath('order.totals.delivery.amount', 0)->assertJsonPath('order.totals.total.amount', 1800);
        $this->assertEquals(0, OrderDetail::firstOrFail()->shipping_cost);
    }

    public function test_delivery_uses_the_stores_own_rule_per_district(): void
    {
        $this->liveFunnel(['type' => 'none']);
        $far = DB::table('cities')->insertGetId(['name' => 'Sylhet', 'state_id' => 1, 'cost' => 120, 'status' => 1]);
        $off = DB::table('cities')->insertGetId(['name' => 'Closed', 'state_id' => 1, 'cost' => 10, 'status' => 0]);

        $this->place($this->body(['selections' => [['key' => 'offer_one', 'quantity' => 1]]], ['district_id' => $far]))
            ->assertStatus(201)->assertJsonPath('order.totals.delivery.amount', 120);

        $this->place($this->body(['selections' => [['key' => 'offer_one', 'quantity' => 1]]], ['district_id' => $off]))
            ->assertStatus(422)->assertJsonPath('errors.district.code', 'invalid_district');
        $this->place($this->body([], ['district_id' => 99999999]))->assertStatus(422)->assertJsonPath('errors.district.code', 'invalid_district');
    }

    // ── what the customer was shown ────────────────────────────────────────

    public function test_the_live_quote_includes_delivery_and_its_fingerprint_is_checked_at_checkout(): void
    {
        [, $product] = $this->liveFunnel(['type' => 'none']);

        $quote = $this->postJson("/api/v2/funnels/{$this->slug}/quote", ['selections' => [['key' => 'offer_one', 'quantity' => 1]], 'district_id' => $this->cityId])->assertOk();
        $quote->assertJsonPath('totals.delivery.status', 'calculated')->assertJsonPath('totals.delivery.amount.amount', 60)->assertJsonPath('totals.payable.amount', 1060);
        $fingerprint = $quote->json('fingerprint');

        // Without a district the quote is unchanged from before: delivery is "calculated at checkout".
        $plain = $this->postJson("/api/v2/funnels/{$this->slug}/quote", ['selections' => [['key' => 'offer_one', 'quantity' => 1]]])->assertOk();
        $plain->assertJsonPath('totals.delivery.status', 'calculated_at_checkout');
        $this->assertNotSame($fingerprint, $plain->json('fingerprint'));

        // The price moves on the website after the customer saw 1,060.
        Product::where('id', $product->id)->update(['unit_price' => 1200]);
        DB::table('product_stocks')->where('product_id', $product->id)->update(['price' => 1200]);

        $stale = $this->place($this->body(['selections' => [['key' => 'offer_one', 'quantity' => 1]], 'quote_fingerprint' => $fingerprint]));
        $stale->assertStatus(409)->assertJsonPath('code', 'price_changed')->assertJsonPath('quote.totals.product_subtotal.amount', 1200);
        $this->assertSame(0, Order::count());
        $this->assertSame(10, $this->stockQty($product, 'M'));

        // The customer reviews the new total and confirms it, with a fresh submission.
        $fresh = $this->postJson("/api/v2/funnels/{$this->slug}/quote", ['selections' => [['key' => 'offer_one', 'quantity' => 1]], 'district_id' => $this->cityId])->json('fingerprint');
        $this->place($this->body(['selections' => [['key' => 'offer_one', 'quantity' => 1]], 'quote_fingerprint' => $fresh]))
            ->assertStatus(201)->assertJsonPath('order.totals.total.amount', 1260);
    }

    /** UC-13: a placed order keeps its prices when the product's price changes afterwards. */
    public function test_an_order_keeps_its_prices_when_the_product_price_changes_later(): void
    {
        [, $product] = $this->liveFunnel();
        $placed = $this->place($this->body())->assertStatus(201);
        $order = Order::latest('id')->firstOrFail();
        $total = (float) $order->grand_total;
        $line = (float) $order->orderDetails()->firstOrFail()->price;
        $this->assertSame($total, (float) $placed->json('order.totals.total.amount'));

        Product::where('id', $product->id)->update(['unit_price' => 5000, 'discount' => 0]);
        DB::table('product_stocks')->where('product_id', $product->id)->update(['price' => 5000]);

        $this->assertSame($total, (float) $order->fresh()->grand_total);
        $this->assertSame($line, (float) $order->orderDetails()->firstOrFail()->price);
        // The customer's receipt shows what was charged, not today's price.
        $receipt = $placed->json('order.receipt');
        if ($receipt) {
            $this->getJson("/api/v2/funnels/{$this->slug}/orders/" . rawurlencode($receipt))->assertOk();
            $this->assertSame($total, (float) $this->getJson("/api/v2/funnels/{$this->slug}/orders/" . rawurlencode($receipt))->json('order.totals.total.amount'));
        }
    }

    public function test_a_delivery_rate_change_after_the_quote_is_caught_too(): void
    {
        $this->liveFunnel(['type' => 'none']);
        $fingerprint = $this->postJson("/api/v2/funnels/{$this->slug}/quote", ['selections' => [['key' => 'offer_one', 'quantity' => 1]], 'district_id' => $this->cityId])->json('fingerprint');

        DB::table('cities')->where('id', $this->cityId)->update(['cost' => 90]);

        $this->place($this->body(['selections' => [['key' => 'offer_one', 'quantity' => 1]], 'quote_fingerprint' => $fingerprint]))
            ->assertStatus(409)->assertJsonPath('code', 'price_changed')->assertJsonPath('delivery.amount', 90);
    }

    // ── stock ──────────────────────────────────────────────────────────────

    public function test_the_last_unit_goes_to_one_customer_and_the_other_is_told_plainly(): void
    {
        [, $product] = $this->liveFunnel(['type' => 'none'], [], ['M' => 1]);
        $one = ['selections' => [['key' => 'offer_one', 'quantity' => 1]]];

        $this->place($this->body($one))->assertStatus(201);
        // A second customer: the quote itself already says it is gone, and checkout agrees.
        $second = $this->place($this->body($one, ['phone' => '01898765432']));

        $second->assertStatus(409)->assertJsonPath('code', 'offer_unavailable');
        $this->assertSame(1, Order::count());
        $this->assertSame(0, $this->stockQty($product, 'M'));
        $this->assertSame(0, $this->batchRemaining($product, 'M'));
    }

    public function test_a_stock_refusal_at_the_ledger_leaves_nothing_behind(): void
    {
        [, $product] = $this->liveFunnel(['type' => 'none'], [], ['M' => 5]);
        // The quote sees stock; by the time the ledger runs, the units are gone (another channel took them).
        $this->mock(\App\Services\Inventory\StockLedger::class, function ($mock) use ($product) {
            $mock->shouldReceive('take')->once()->andThrow(new \App\Services\Inventory\Exceptions\OutOfStock('gone', ['product_id' => $product->id, 'available' => 0]));
            $mock->shouldReceive('release')->never();
        });
        $body = $this->body();

        $this->place($body)->assertStatus(409)->assertJsonPath('code', 'out_of_stock')->assertJsonPath('product', 'Super Blender');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, User::where('phone', '01712345678')->count(), 'the customer row rolled back with the order');
        $this->assertSame('rejected', FunnelCheckoutAttempt::firstOrFail()->status);

        // The same submission id replays the refusal; it is never retried behind the customer's back.
        $this->place($body)->assertStatus(409)->assertJsonPath('code', 'out_of_stock');
    }

    public function test_stock_without_cost_batches_is_refused_as_unavailable(): void
    {
        $this->liveFunnel(['type' => 'none'], [], ['M' => 5]);
        DB::table('purchase_products')->update(['remaining_qty' => 0]); // units on the shelf, no cost batch left (only this test's rows exist in the transaction)

        $quote = $this->postJson("/api/v2/funnels/{$this->slug}/quote", ['selections' => [['key' => 'offer_one', 'quantity' => 1]]]);
        // The offer engine prices from the stock row, as the website shows it; the ledger decides.
        $quote->assertOk();

        $this->place($this->body(['selections' => [['key' => 'offer_one', 'quantity' => 1]]]))
            ->assertStatus(409)->assertJsonPath('code', 'item_unavailable');
        $this->assertSame(0, Order::count());
        $this->assertSame(5, DB::table('product_stocks')->where('variant', 'M')->orderByDesc('id')->value('qty'));
    }

    // ── repeats ────────────────────────────────────────────────────────────

    public function test_the_same_submission_twice_places_one_order_and_deducts_once(): void
    {
        [, $product] = $this->liveFunnel();
        $body = $this->body();

        $first = $this->place($body)->assertStatus(201);
        $second = $this->place($body)->assertStatus(200)->assertJsonPath('replayed', true);

        $this->assertSame($first->json('order.code'), $second->json('order.code'));
        $this->assertSame(1, Order::count());
        $this->assertSame(1, OrderDetail::count());
        $this->assertSame(8, $this->stockQty($product, 'M'));
        $this->assertSame(1, FunnelCheckoutAttempt::count());
    }

    public function test_a_reused_submission_id_with_different_content_is_refused(): void
    {
        $this->liveFunnel();
        $body = $this->body();
        $this->place($body)->assertStatus(201);

        $this->place(array_replace_recursive($body, ['selections' => [['key' => 'offer_one', 'quantity' => 3]]]))
            ->assertStatus(422)->assertJsonPath('code', 'idempotency_key_reused');
        $this->place(array_replace_recursive($body, ['customer' => ['phone' => '01898765432']]))
            ->assertStatus(422)->assertJsonPath('code', 'idempotency_key_reused');
        $this->assertSame(1, Order::count());
    }

    public function test_an_attempt_that_never_finished_is_taken_over_only_after_a_while(): void
    {
        [, $product] = $this->liveFunnel();
        $body = $this->body();
        $funnel = Funnel::where('slug', $this->slug)->first();
        $input = \App\Services\Funnel\Checkout\CheckoutInput::fromBody($body, \App\Services\Funnel\Checkout\CheckoutConfig::fromDocument($funnel->publishedVersion->content), ['cash_on_delivery']);

        // A request that started and then died: the row says "started".
        FunnelCheckoutAttempt::create([
            'funnel_id' => $funnel->id, 'funnel_version_id' => $funnel->published_version_id,
            'key_hash' => hash('sha256', $funnel->id . '|' . $body['idempotency_key']), 'request_hash' => $input->requestHash(), 'status' => 'started',
        ]);

        $this->place($body)->assertStatus(409)->assertJsonPath('code', 'in_progress')->assertHeader('Retry-After', '2');
        $this->assertSame(0, Order::count());

        FunnelCheckoutAttempt::query()->update(['updated_at' => now()->subMinutes(5)]);
        $this->place($body)->assertStatus(201);
        $this->assertSame(1, Order::count());
        $this->assertSame(8, $this->stockQty($product, 'M'));
    }

    // ── what the form requires ─────────────────────────────────────────────

    public function test_required_details_and_bengali_digits(): void
    {
        $this->liveFunnel();

        $bad = $this->place(array_replace($this->body(), ['customer' => ['name' => '', 'phone' => '12345', 'address' => 'x', 'district_id' => 0]]));
        $bad->assertStatus(422)->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('errors.name.code', 'required')->assertJsonPath('errors.phone.code', 'invalid_phone')
            ->assertJsonPath('errors.address.code', 'required')->assertJsonPath('errors.district.code', 'required');

        // Bengali digits and +88 are accepted and stored in one form.
        $this->place($this->body([], ['phone' => '+৮৮০১৭১২৩৪৫৬৭৮']))->assertStatus(201);
        $this->assertSame('01712345678', json_decode(Order::firstOrFail()->shipping_address, true)['phone']);
    }

    public function test_optional_fields_follow_what_the_page_asks_for(): void
    {
        $this->liveFunnel(['type' => 'none'], [
            'requireConsent' => true, 'consentText' => 'I agree',
            'fields' => [
                ['key' => 'name', 'enabled' => true, 'required' => true, 'label' => 'Your name'],
                ['key' => 'phone', 'enabled' => true, 'required' => true],
                ['key' => 'address', 'enabled' => true, 'required' => true],
                ['key' => 'district', 'enabled' => true, 'required' => true],
                ['key' => 'email', 'enabled' => true, 'required' => true],
                ['key' => 'custom_1', 'enabled' => true, 'required' => true, 'label' => 'Gift message', 'type' => 'text'],
                ['key' => 'custom_2', 'enabled' => true, 'required' => false, 'label' => 'Colour', 'type' => 'select', 'options' => ['Red', 'Blue']],
            ],
        ]);
        $one = ['selections' => [['key' => 'offer_one', 'quantity' => 1]]];

        $this->place($this->body($one))->assertStatus(422)
            ->assertJsonPath('errors.email.code', 'required')->assertJsonPath('errors.custom_1.code', 'required')->assertJsonPath('errors.consent.code', 'consent_required');
        $this->place($this->body($one + ['consent' => true], ['email' => 'not-an-email', 'custom_1' => 'Happy birthday', 'custom_2' => 'Green']))->assertStatus(422)
            ->assertJsonPath('errors.email.code', 'invalid_email')->assertJsonPath('errors.custom_2.code', 'invalid_choice');

        $this->place($this->body($one + ['consent' => true], ['email' => 'rahim@example.com', 'custom_1' => 'Happy birthday', 'custom_2' => 'Blue']))->assertStatus(201);
        $order = Order::firstOrFail();
        $this->assertStringContainsString('Gift message: Happy birthday', $order->additional_info);
        $this->assertStringContainsString('Colour: Blue', $order->additional_info);
        $this->assertStringContainsString('Consent given: I agree', $order->additional_info);
        $this->assertSame('rahim@example.com', json_decode($order->shipping_address, true)['email']);
    }

    public function test_a_field_the_page_does_not_show_is_not_stored_even_if_sent(): void
    {
        $this->liveFunnel(['type' => 'none']);

        $this->place($this->body(['selections' => [['key' => 'offer_one', 'quantity' => 1]]], ['email' => 'hidden@example.com', 'custom_3' => 'sneaky']))->assertStatus(201);

        $order = Order::firstOrFail();
        $this->assertSame('', json_decode($order->shipping_address, true)['email']);
        $this->assertStringNotContainsString('sneaky', (string) $order->additional_info);
    }

    public function test_bots_and_malformed_requests_are_turned_away(): void
    {
        $this->liveFunnel();

        $this->place($this->body(['website' => 'http://spam.example']))->assertStatus(422)->assertJsonPath('code', 'rejected');
        $this->place($this->body(['idempotency_key' => 'short']))->assertStatus(422)->assertJsonPath('errors.idempotency_key.code', 'invalid_idempotency_key');
        $this->place($this->body(['selections' => []]))->assertStatus(422)->assertJsonPath('errors.selections.code', 'empty_selection');
        $this->place($this->body(['payment_type' => 'bitcoin']))->assertStatus(422)->assertJsonPath('errors.payment_type.code', 'invalid_payment');
        $this->place($this->body(['delivery_method' => 'pickup_point']))->assertStatus(422)->assertJsonPath('errors.delivery_method.code', 'invalid_delivery');
        $this->call('POST', "/api/v2/funnels/{$this->slug}/checkout", [], [], [], ['CONTENT_TYPE' => 'application/json'], '{not json')->assertStatus(422);
        $this->call('POST', "/api/v2/funnels/{$this->slug}/checkout", [], [], [], ['CONTENT_TYPE' => 'application/json'], str_repeat('a', 70000))->assertStatus(413);
        $this->assertSame(0, Order::count());
    }

    public function test_a_blocked_connection_and_too_many_attempts_from_one_number(): void
    {
        $this->liveFunnel(['type' => 'none'], [], ['M' => 50]);

        DB::table('blocked_ips')->insert(['ip_address' => '127.0.0.1', 'status' => 'blocked', 'created_at' => now(), 'updated_at' => now()]);
        $this->place($this->body())->assertStatus(403)->assertJsonPath('code', 'blocked');
        DB::table('blocked_ips')->where('ip_address', '127.0.0.1')->delete();

        RateLimiter::clear('funnel-checkout:phone:01712345678');
        $one = ['selections' => [['key' => 'offer_one', 'quantity' => 1]]];
        for ($i = 0; $i < 6; $i++) {
            $this->place($this->body($one))->assertStatus(201);
        }
        $this->place($this->body($one))->assertStatus(429)->assertJsonPath('code', 'too_many_attempts');
    }

    public function test_phone_verification_is_enforced_when_the_store_turns_it_on(): void
    {
        $this->liveFunnel(['type' => 'none']);
        // checkout_otps is MyISAM, so a row written here would outlive the test's rollback.
        DB::table('checkout_otps')->where('phone', '01712345678')->delete();
        DB::table('business_settings')->where('type', 'checkout_otp_activation')->update(['value' => '1']);
        Cache::flush();
        $one = ['selections' => [['key' => 'offer_one', 'quantity' => 1]]];

        $this->getJson("/api/v2/funnels/{$this->slug}/checkout/config")->assertJsonPath('otp_required', true);
        $this->place($this->body($one))->assertStatus(403)->assertJsonPath('code', 'otp_required');

        DB::table('checkout_otps')->insert(['phone' => '01712345678', 'otp' => '123456', 'is_verified' => 1, 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now()]);
        $this->place($this->body($one))->assertStatus(201);
        DB::table('checkout_otps')->where('phone', '01712345678')->delete();
    }

    // ── payment ────────────────────────────────────────────────────────────

    public function test_online_payment_is_offered_only_when_the_store_has_it_and_the_page_allows_it(): void
    {
        $this->liveFunnel(['type' => 'none'], [], ['M' => 10], ['paystation']);
        $one = ['selections' => [['key' => 'offer_one', 'quantity' => 1]]];

        // Not set up in the store: only what works is offered, and the page's choice cannot widen that.
        $this->getJson("/api/v2/funnels/{$this->slug}/checkout/config")->assertJsonPath('payment_methods', []);
        $this->place($this->body($one + ['payment_type' => 'paystation']))->assertStatus(422)->assertJsonPath('errors.payment_type.code', 'invalid_payment');
        $this->place($this->body($one))->assertStatus(422)->assertJsonPath('errors.payment_type.code', 'invalid_payment');

        $this->enablePaystation();
        $this->getJson("/api/v2/funnels/{$this->slug}/checkout/config")->assertJsonCount(1, 'payment_methods')->assertJsonPath('payment_methods.0.key', 'paystation');
        // The merchant allowed only online payment on this page: cash is not accepted here.
        $this->place($this->body($one))->assertStatus(422);
        $this->place($this->body($one + ['payment_type' => 'paystation']))->assertStatus(201)
            ->assertJsonPath('order.payment.method', 'paystation')->assertJsonPath('order.payment.action', 'pay');
    }

    public function test_the_payment_link_is_given_only_for_unpaid_online_orders_that_are_still_open(): void
    {
        $this->enablePaystation();
        [$funnel] = $this->liveFunnel(['type' => 'none']);
        $one = ['selections' => [['key' => 'offer_one', 'quantity' => 1]]];
        $this->mock(PaystationController::class, function ($mock) {
            $mock->shouldReceive('begin')->twice()->andReturn(response()->json(['result' => true, 'url' => 'https://pay.example/session']));
        });

        $cash = $this->place($this->body($one))->assertStatus(201)->json('order.receipt');
        $this->postJson("/api/v2/funnels/{$this->slug}/orders/{$cash}/pay")->assertStatus(409)->assertJsonPath('code', 'not_online');

        $online = $this->place($this->body($one + ['payment_type' => 'paystation'], ['phone' => '01898765432']))->assertStatus(201)->json('order.receipt');
        $this->postJson("/api/v2/funnels/{$this->slug}/orders/{$online}/pay")->assertOk()->assertJsonPath('url', 'https://pay.example/session');
        // Asking again before paying is fine.
        $this->postJson("/api/v2/funnels/{$this->slug}/orders/{$online}/pay")->assertOk();

        $order = Order::where('payment_type', 'paystation')->firstOrFail();
        Order::where('id', $order->id)->update(['payment_status' => 'paid']);
        $this->postJson("/api/v2/funnels/{$this->slug}/orders/{$online}/pay")->assertStatus(409)->assertJsonPath('code', 'already_paid');

        Order::where('id', $order->id)->update(['payment_status' => 'unpaid', 'delivery_status' => 'cancelled']);
        $this->postJson("/api/v2/funnels/{$this->slug}/orders/{$online}/pay")->assertStatus(409)->assertJsonPath('code', 'order_cancelled');
        $this->getJson("/api/v2/funnels/{$this->slug}/orders/{$online}")->assertOk()->assertJsonPath('order.payment.status', 'cancelled')->assertJsonPath('order.payment.action', 'none');
    }

    public function test_the_gateway_sends_funnel_customers_back_to_the_funnel_page_with_their_receipt(): void
    {
        $previous = getenv('FRONTEND_URL');
        putenv('FRONTEND_URL=https://shop.test');
        $_ENV['FRONTEND_URL'] = $_SERVER['FRONTEND_URL'] = 'https://shop.test';

        try {
            [$funnel] = $this->liveFunnel(['type' => 'none']);
            $this->place($this->body(['selections' => [['key' => 'offer_one', 'quantity' => 1]]]))->assertStatus(201);
            $order = Order::firstOrFail();

            $paid = \App\Services\Funnel\Checkout\PaymentReturn::urlFor($order, true);
            $failed = \App\Services\Funnel\Checkout\PaymentReturn::urlFor($order, false);

            $this->assertStringStartsWith("https://shop.test/funnel/{$this->slug}?receipt=", $paid);
            $this->assertStringEndsWith('&payment=success', $paid);
            $this->assertStringEndsWith('&payment=failed', $failed);
            parse_str((string) parse_url($paid, PHP_URL_QUERY), $query);
            $this->assertSame(
                ['combined_order_id' => (int) $order->combined_order_id, 'funnel_id' => $funnel->id],
                \App\Services\Funnel\Checkout\Receipt::read($query['receipt'], $funnel->id)
            );

            // Orders that did not come from a funnel are left to the website's own pages.
            Order::where('id', $order->id)->update(['funnel_id' => null]);
            $this->assertNull(\App\Services\Funnel\Checkout\PaymentReturn::urlFor($order->fresh(), true));
            $this->assertNull(\App\Services\Funnel\Checkout\PaymentReturn::urlFor(null, true));
        } finally {
            $previous === false ? putenv('FRONTEND_URL') : putenv('FRONTEND_URL=' . $previous);
            unset($_ENV['FRONTEND_URL'], $_SERVER['FRONTEND_URL']);
        }
    }

    // ── looking the order up again ─────────────────────────────────────────

    public function test_a_receipt_opens_only_its_own_order(): void
    {
        [$funnel] = $this->liveFunnel(['type' => 'none']);
        $receipt = $this->place($this->body(['selections' => [['key' => 'offer_one', 'quantity' => 1]]]))->assertStatus(201)->json('order.receipt');

        $order = $this->getJson("/api/v2/funnels/{$this->slug}/orders/" . rawurlencode($receipt))->assertOk();
        $order->assertJsonPath('order.items.0.name', 'Super Blender')->assertJsonPath('order.totals.total.amount', 1060);
        $this->assertStringNotContainsString('Dhanmondi', $order->getContent());

        $this->getJson("/api/v2/funnels/{$this->slug}/orders/" . strrev($receipt))->assertStatus(404);
        $this->getJson("/api/v2/funnels/{$this->slug}/orders/not-a-receipt")->assertStatus(404);

        // Another funnel's page cannot open it, even with a valid token.
        $other = app(FunnelService::class);
        $second = $other->create(['title' => 'Other', 'slug' => 'other-page'], $this->makeAdmin());
        $document = $funnel->publishedVersion->content;
        [$second] = $other->saveDraft($second, $document, 1, $this->makeAdmin());
        $other->publish($second, $this->makeAdmin());
        $this->getJson("/api/v2/funnels/other-page/orders/{$receipt}")->assertStatus(404);

        // Expired tokens stop working.
        $expired = \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['c' => Order::firstOrFail()->combined_order_id, 'f' => $funnel->id, 'x' => time() - 10]));
        $this->getJson("/api/v2/funnels/{$this->slug}/orders/{$expired}")->assertStatus(404);
    }

    // ── cancelling returns what was taken ──────────────────────────────────

    public function test_cancelling_a_funnel_order_puts_stock_and_batches_back_once(): void
    {
        [, $product] = $this->liveFunnel();
        $this->place($this->body())->assertStatus(201);
        $this->assertSame(8, $this->stockQty($product, 'M'));

        foreach (OrderDetail::all() as $line) {
            $this->assertTrue(app(\App\Services\Inventory\StockLedger::class)->give($line));
        }
        foreach (OrderDetail::all() as $line) {
            $this->assertFalse(app(\App\Services\Inventory\StockLedger::class)->give($line));
        }

        $this->assertSame(10, $this->stockQty($product, 'M'));
        $this->assertSame(10, $this->batchRemaining($product, 'M'));
    }

    // ── gifts and bundles are ordinary order lines ─────────────────────────

    public function test_a_free_gift_becomes_a_zero_priced_line_that_still_takes_stock(): void
    {
        $admin = $this->makeAdmin();
        $blender = $this->makeStockedProduct(['M' => 5], 1000, 400, ['name' => 'Blender']);
        $bag = $this->makeStockedProduct(['' => 5], 200, 50, ['name' => 'Gift Bag']);
        $service = app(FunnelService::class);
        $funnel = $service->create(['title' => 'Gift', 'slug' => $this->slug], $admin);
        $page = [
            'schemaVersion' => 1,
            'page' => ['sections' => [
                ['id' => 'offers_001', 'type' => 'product_offer_selector', 'props' => ['selectionMode' => 'multiple'], 'productBindings' => [[
                    'key' => 'offer_gift', 'items' => [['productId' => $blender->id, 'variant' => 'M', 'quantity' => 1, 'lockVariant' => true]],
                    'offer' => ['type' => 'free_gift', 'giftProductId' => $bag->id, 'giftQuantity' => 1],
                ]]],
                ['id' => 'checkout_001', 'type' => 'checkout_form', 'props' => [], 'children' => [['id' => 'buy_001', 'type' => 'purchase_button', 'props' => ['text' => 'Order now']]]],
            ]],
        ];
        [$funnel] = $service->saveDraft($funnel, $page, 1, $admin);
        $service->publish($funnel, $admin);

        $this->place($this->body(['selections' => [['key' => 'offer_gift', 'quantity' => 2]]]))->assertStatus(201)->assertJsonPath('order.totals.products.amount', 2000);

        $lines = OrderDetail::orderBy('id')->get();
        $this->assertCount(2, $lines);
        $this->assertEquals([2000, 0], $lines->pluck('price')->map(fn ($p) => (float) $p)->all());
        $this->assertSame([2, 2], $lines->pluck('quantity')->map(fn ($q) => (int) $q)->all());
        $this->assertSame(3, $this->stockQty($blender, 'M'));
        $this->assertSame(3, $this->stockQty($bag, ''), 'the gift leaves the shelf too');
        $this->assertEquals(-100, (float) $lines[1]->profit, 'its cost is a cost of the order');
    }
    public function test_funnel_orders_appear_in_the_orders_dashboard_with_a_funnel_badge_and_filter(): void
    {
        [$funnel] = $this->liveFunnel();
        $this->place($this->body())->assertStatus(201);
        $order = Order::orderByDesc('id')->firstOrFail();
        $this->assertSame($funnel->id, (int) $order->funnel_id);

        // The list shows the shop owner's orders; fixture products belong to user 1, who need not be the first admin here.
        $order->forceFill(['seller_id' => User::where('user_type', 'admin')->orderBy('id')->value('id')])->save();

        $admin = $this->makeAdmin();
        $page = $this->actingAs($admin)->get(route('all_orders.index'));
        $page->assertOk()->assertSee($order->code)->assertSee('Funnel');

        $filtered = $this->get(route('all_orders.index', ['funnel_id' => $funnel->id]));
        $filtered->assertOk()->assertSee($order->code);

        // A funnel with no orders shows none of them.
        $other = $this->get(route('all_orders.index', ['funnel_id' => $funnel->id + 9999]));
        $other->assertOk()->assertDontSee($order->code);
    }
}
