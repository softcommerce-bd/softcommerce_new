<?php

namespace Tests\Feature\Funnel;

use App\Models\FunnelOrderAttribution;
use App\Models\Order;
use App\Services\Funnel\Analytics\EventRecorder;
use App\Services\Funnel\Analytics\FunnelAnalytics;
use App\Services\Funnel\Analytics\TestOrderToken;
use App\Services\Funnel\Analytics\TrafficSource;
use App\Services\Funnel\FunnelService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Funnel\BuildsLiveFunnel;
use Tests\Inventory\InventoryTestCase;

/**
 * Visitor events, campaign attribution, test orders, the Purchase tracking data
 * and the analytics report. Orders and money must come from the order records.
 */
class FunnelAnalyticsTest extends InventoryTestCase
{
    use BuildsLiveFunnel;

    private const MOBILE = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLiveFunnel();
        EventRecorder::forgetReadiness();
        config(['funnel.analytics.test_phones' => [], 'funnel.analytics.share_customer_data' => false]);
    }

    protected function tearDown(): void
    {
        $this->disablePaystation();
        parent::tearDown();
    }

    private function sendEvents(array $events, array $extra = [], string $userAgent = self::MOBILE, ?string $slug = null)
    {
        return $this->withHeaders(['User-Agent' => $userAgent])->postJson('/api/v2/funnels/' . ($slug ?? $this->slug) . '/events', array_replace([
            'visitor_id' => 'visitor-' . Str::random(12),
            'session_id' => 'session-' . Str::random(12),
            'context' => ['utm_source' => 'facebook', 'utm_medium' => 'paid_social', 'utm_campaign' => 'eid-sale', 'referrer_host' => 'm.facebook.com'],
            'events' => $events,
        ], $extra));
    }

    private function event(string $type, array $extra = []): array
    {
        return array_replace(['id' => 'evt-' . Str::random(20), 'type' => $type], $extra);
    }

    // ── visitor events ─────────────────────────────────────────────────────

    public function test_events_are_stored_once_without_personal_data_and_only_for_live_pages(): void
    {
        [$funnel] = $this->liveFunnel();
        $view = $this->event('page_view');

        $this->sendEvents([$view, $this->event('product_select', ['offer_key' => 'offer_one']), $this->event('made_up'), ['type' => 'page_view']])
            ->assertStatus(202)->assertJsonPath('stored', 2);
        // A retried beacon with the same event id adds nothing.
        $this->sendEvents([$view])->assertStatus(202)->assertJsonPath('stored', 0);

        $row = DB::table('funnel_events')->where('event_id', $view['id'])->first();
        $this->assertSame($funnel->id, (int) $row->funnel_id);
        $this->assertSame('mobile', $row->device);
        $this->assertSame('facebook', $row->source);
        $this->assertSame('eid-sale', $row->campaign);
        // No address, user agent or IP is kept.
        $this->assertSame(['id', 'event_id', 'funnel_id', 'funnel_version_id', 'type', 'visitor_id', 'session_id', 'offer_key', 'device', 'source', 'medium', 'campaign', 'created_at'], array_keys((array) $row));

        // Crawlers are not visitors.
        $this->sendEvents([$this->event('page_view')], [], 'facebookexternalhit/1.1')->assertStatus(202)->assertJsonPath('stored', 0);

        // A page that is not live takes no events.
        app(FunnelService::class)->unpublish($funnel->fresh(), $this->makeAdmin());
        $this->sendEvents([$this->event('page_view')])->assertStatus(404);
        $this->sendEvents([$this->event('page_view')], [], self::MOBILE, 'no-such-page')->assertStatus(404);
    }

    public function test_traffic_sources_and_devices_are_classified_from_what_the_browser_sends(): void
    {
        $this->assertSame('facebook', TrafficSource::source('fb', null));
        $this->assertSame('facebook', TrafficSource::source('Instagram', null));
        $this->assertSame('google', TrafficSource::source(null, 'www.google.com'));
        $this->assertSame('facebook', TrafficSource::source(null, null, 'fbclid'));
        $this->assertSame('google', TrafficSource::source(null, null, 'gclid'));
        $this->assertSame('direct', TrafficSource::source(null, null));
        $this->assertSame('referral', TrafficSource::source(null, 'blog.example.com'));
        $this->assertSame('other', TrafficSource::source('partner-x', null));

        $this->assertSame('mobile', TrafficSource::device(self::MOBILE));
        $this->assertSame('tablet', TrafficSource::device('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)'));
        $this->assertSame('desktop', TrafficSource::device('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0'));
        $this->assertSame('unknown', TrafficSource::device(null));
    }

    // ── attribution and test orders ────────────────────────────────────────

    public function test_an_order_keeps_its_campaign_and_click_ids_but_only_with_consent(): void
    {
        [$funnel] = $this->liveFunnel();
        $attribution = ['utm_source' => 'facebook', 'utm_medium' => 'cpc', 'utm_campaign' => 'eid-sale', 'utm_content' => 'video-a', 'utm_term' => 'blender',
            'fbclid' => 'IwAR0abc_DEF-123', 'gclid' => null, 'referrer_host' => 'l.facebook.com', 'visitor_id' => 'visitor-1234567890', 'session_id' => 'session-1234567890', 'consent' => 'granted'];

        $this->withHeaders(['User-Agent' => self::MOBILE])->placeOrder($this->checkoutBody(['attribution' => $attribution]))->assertStatus(201);
        $order = Order::latest('id')->firstOrFail();
        $row = FunnelOrderAttribution::where('combined_order_id', $order->combined_order_id)->firstOrFail();
        $this->assertSame($funnel->id, (int) $row->funnel_id);
        $this->assertSame(['facebook', 'cpc', 'eid-sale', 'video-a', 'blender', 'IwAR0abc_DEF-123', 'mobile', 'facebook', false],
            [$row->utm_source, $row->utm_medium, $row->utm_campaign, $row->utm_content, $row->utm_term, $row->fbclid, $row->device, $row->source, $row->is_test]);

        // Consent refused: the campaign is kept, the ad click id is not.
        $denied = array_replace($attribution, ['consent' => 'denied']);
        $this->placeOrder($this->checkoutBody(['attribution' => $denied], ['phone' => '01898765432']))->assertStatus(201);
        $row = FunnelOrderAttribution::latest('id')->firstOrFail();
        $this->assertNull($row->fbclid);
        $this->assertSame('eid-sale', $row->utm_campaign);

        // Malformed attribution never blocks an order.
        $this->placeOrder($this->checkoutBody(['attribution' => ['utm_source' => ['x'], 'fbclid' => '<script>', 'referrer_host' => 'javascript:alert(1)']], ['phone' => '01611111111']))->assertStatus(201);
        $row = FunnelOrderAttribution::latest('id')->firstOrFail();
        $this->assertNull($row->utm_source);
        $this->assertNull($row->fbclid);
        $this->assertNull($row->referrer_host);
        $this->assertSame('direct', $row->source);
    }

    public function test_a_test_order_is_a_real_order_marked_as_a_test_and_never_reported(): void
    {
        [$funnel] = $this->liveFunnel();

        // A forged or foreign token does nothing.
        $this->placeOrder($this->checkoutBody(['test_token' => '9999999999.' . str_repeat('a', 64)]))->assertStatus(201)->assertJsonPath('order.tracking.test', false);
        $this->assertFalse(TestOrderToken::valid(TestOrderToken::make($funnel->id + 1), $funnel->id));
        $this->assertFalse(TestOrderToken::valid(TestOrderToken::make($funnel->id, -1), $funnel->id));

        $response = $this->placeOrder($this->checkoutBody(['test_token' => TestOrderToken::make($funnel->id)], ['phone' => '01898765432']))->assertStatus(201);
        $response->assertJsonPath('order.tracking.test', true)->assertJsonPath('order.tracking.eligible', false);
        $order = Order::latest('id')->firstOrFail();
        $this->assertStringContainsString('TEST ORDER', $order->additional_info);
        $this->assertSame('M', $order->orderDetails->first()->variation); // a real order with real stock

        // Phone numbers listed as test numbers count as tests too.
        config(['funnel.analytics.test_phones' => ['01611111111']]);
        $this->placeOrder($this->checkoutBody([], ['phone' => '01611111111']))->assertStatus(201)->assertJsonPath('order.tracking.test', true);

        $report = app(FunnelAnalytics::class)->report($funnel->id, now()->subDay(), now());
        $this->assertSame(1, $report['totals']['orders_placed']);
    }

    public function test_the_editor_hands_out_a_test_order_link_for_live_pages_only(): void
    {
        [$funnel] = $this->liveFunnel();
        $admin = $this->makeAdmin();
        $token = $admin->createToken('funnel-editor', [config('funnel.editor_session.token_ability')])->plainTextToken;
        $api = fn () => $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);

        $url = $api()->postJson("/api/v2/funnel-editor/funnels/{$funnel->id}/test-order-link")->assertOk()->json('url');
        $this->assertStringContainsString("/funnel/{$this->slug}?fn_test=", $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertTrue(TestOrderToken::valid($query['fn_test'], $funnel->id));

        app(FunnelService::class)->unpublish($funnel->fresh(), $admin);
        $api()->postJson("/api/v2/funnel-editor/funnels/{$funnel->id}/test-order-link")->assertStatus(409)->assertJsonPath('code', 'not_live');

        $viewer = $this->makeUser('staff', ['view_funnels']);
        $viewerToken = $viewer->createToken('funnel-editor', [config('funnel.editor_session.token_ability')])->plainTextToken;
        $this->forgetAuth();
        $this->withHeaders(['Authorization' => 'Bearer ' . $viewerToken, 'Accept' => 'application/json'])
            ->postJson("/api/v2/funnel-editor/funnels/{$funnel->id}/test-order-link")->assertStatus(403);
    }

    // ── what may be reported as a Purchase ─────────────────────────────────

    public function test_purchase_tracking_comes_from_the_order_and_respects_payment_and_privacy(): void
    {
        $this->enablePaystation();
        $this->liveFunnel(['M' => 10], ['cash_on_delivery', 'paystation']);

        $cash = $this->placeOrder($this->checkoutBody(['selections' => [['key' => 'offer_one', 'quantity' => 2]]]))->assertStatus(201)->json('order');
        $this->assertTrue($cash['tracking']['eligible']);
        $this->assertSame('BDT', $cash['tracking']['currency']);
        $this->assertEquals($cash['totals']['total']['amount'], $cash['tracking']['value']);
        $this->assertSame(2, $cash['tracking']['contents'][0]['quantity']);
        $this->assertMatchesRegularExpression('/^purchase_[a-f0-9]{32}$/', $cash['tracking']['event_id']);
        $this->assertNull($cash['tracking']['user_data']);

        // The same order always names the same event, so a reload is not a second purchase.
        $again = $this->getJson("/api/v2/funnels/{$this->slug}/orders/" . urlencode($cash['receipt']))->json('order.tracking');
        $this->assertSame($cash['tracking']['event_id'], $again['event_id']);

        // An online order is a purchase only once paid.
        $online = $this->placeOrder($this->checkoutBody(['payment_type' => 'paystation'], ['phone' => '01898765432']))->assertStatus(201)->json('order');
        $this->assertFalse($online['tracking']['eligible']);
        $order = Order::where('payment_type', 'paystation')->firstOrFail();
        Order::where('id', $order->id)->update(['payment_status' => 'paid']);
        $paid = $this->getJson("/api/v2/funnels/{$this->slug}/orders/" . urlencode($online['receipt']))->json('order.tracking');
        $this->assertTrue($paid['eligible']);

        // Cancelled orders are never purchases.
        Order::where('id', $order->id)->update(['delivery_status' => 'cancelled']);
        $this->assertFalse($this->getJson("/api/v2/funnels/{$this->slug}/orders/" . urlencode($online['receipt']))->json('order.tracking.eligible'));

        // Customer data only hashed, and only when the store allows it.
        config(['funnel.analytics.share_customer_data' => true]);
        $shared = $this->getJson("/api/v2/funnels/{$this->slug}/orders/" . urlencode($cash['receipt']))->json('order.tracking.user_data');
        $this->assertSame(hash('sha256', '8801712345678'), $shared['ph']);
        $this->assertStringNotContainsString('01712345678', json_encode($shared));
    }

    // ── the report ─────────────────────────────────────────────────────────

    public function test_the_report_counts_orders_and_money_from_the_orders_only(): void
    {
        $this->enablePaystation();
        [$funnel] = $this->liveFunnel(['M' => 20], ['cash_on_delivery', 'paystation']);

        // Two visitors, one with three views; a selection and a checkout start.
        $visitor = 'visitor-aaaaaaaaaa';
        $this->sendEvents([$this->event('page_view'), $this->event('page_view'), $this->event('product_view'), $this->event('product_select'), $this->event('checkout_start')], ['visitor_id' => $visitor]);
        $this->sendEvents([$this->event('page_view')], ['visitor_id' => $visitor]);
        $this->sendEvents([$this->event('page_view')], ['context' => ['referrer_host' => 'www.google.com']], 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0');

        $phones = ['01712345678', '01898765432', '01611111111', '01922222222'];
        $codes = [];
        foreach ($phones as $i => $phone) {
            $type = $i === 3 ? 'paystation' : 'cash_on_delivery';
            $this->placeOrder($this->checkoutBody(['payment_type' => $type, 'attribution' => ['utm_source' => 'facebook', 'utm_campaign' => 'eid-sale'] + ($i === 0 ? ['visitor_id' => $visitor] : [])], ['phone' => $phone]))->assertStatus(201);
            $codes[$i] = Order::latest('id')->value('id');
        }
        // 0: delivered (cash collected). 1: cancelled. 2: still open, unpaid cash. 3: online, paid.
        Order::where('id', $codes[0])->update(['delivery_status' => 'delivered']);
        Order::where('id', $codes[1])->update(['delivery_status' => 'cancelled']);
        Order::where('id', $codes[3])->update(['payment_status' => 'paid']);
        // A browser event claiming a purchase changes nothing: there is no such event type.
        $this->sendEvents([$this->event('purchase'), $this->event('order_paid')])->assertJsonPath('stored', 0);

        $report = app(FunnelAnalytics::class)->report($funnel->id, now()->subDays(2), now());
        $t = $report['totals'];
        $each = (float) Order::where('id', $codes[0])->value('grand_total');

        $this->assertSame(4, $t['page_views']);
        $this->assertSame(2, $t['unique_visitors']);
        $this->assertSame(1, $t['product_views']);
        $this->assertSame(1, $t['product_selections']);
        $this->assertSame(1, $t['checkout_started']);
        $this->assertSame(4, $t['orders_placed']);
        $this->assertSame(1, $t['orders_paid']);
        $this->assertSame(1, $t['orders_delivered']);
        $this->assertSame(1, $t['orders_cancelled']);
        // One of the two visitors ordered; the other three orders came without a seen visit.
        $this->assertSame(1, $t['converted_visitors']);
        $this->assertEquals(50.0, $t['conversion_rate']);
        $this->assertSame(['visitors' => 2, 'product_view' => 1, 'product_select' => 1, 'checkout_start' => 1, 'ordered' => 1], $report['steps']);
        $this->assertEqualsWithDelta(3 * $each, $t['order_value'], 0.01);
        $this->assertEqualsWithDelta(2 * $each, $t['confirmed_revenue'], 0.01);
        $this->assertEqualsWithDelta($each, $t['average_order_value'], 0.01);
        $this->assertSame(['orders' => 3, 'orders_online' => 1], ['orders' => $report['payment_methods']['cod']['orders'], 'orders_online' => $report['payment_methods']['online']['orders']]);
        $this->assertSame('eid-sale', $report['campaigns'][0]['campaign']);
        $this->assertSame(4, $report['campaigns'][0]['orders']);

        $sources = collect($report['sources'])->keyBy('key');
        $this->assertSame(1, $sources['facebook']['visitors']);
        $this->assertSame(1, $sources['google']['visitors']);
        $this->assertSame(4, $sources['facebook']['orders']);
        $devices = collect($report['devices'])->keyBy('key');
        $this->assertSame(1, $devices['mobile']['visitors']);
        $this->assertSame(1, $devices['desktop']['visitors']);

        $this->assertSame(4, array_sum(array_column($report['trend'], 'orders')));
        $all = app(FunnelAnalytics::class)->report(null, now()->subDays(2), now(), 'week');
        $this->assertSame(4, collect($all['funnels'])->firstWhere('id', $funnel->id)['orders']);
        $this->assertSame([], $all['attention']);

        // A payment that arrives after cancellation is flagged.
        Order::where('id', $codes[1])->update(['payment_status' => 'paid']);
        $this->assertCount(1, app(FunnelAnalytics::class)->report($funnel->id, now()->subDays(2), now())['attention']);
    }

    public function test_the_dashboard_needs_its_own_permission(): void
    {
        [$funnel] = $this->liveFunnel();
        $this->placeOrder($this->checkoutBody())->assertStatus(201);

        $this->actingAs($this->makeUser('staff', ['view_funnels']))->get('/admin/funnels/analytics')->assertStatus(403);

        $analyst = $this->makeUser('staff', ['view_funnel_analytics']);
        $this->actingAs($analyst)->get('/admin/funnels/analytics')->assertOk()->assertSee('Funnel Analytics')->assertSee('Confirmed revenue');
        $this->actingAs($analyst)->get('/admin/funnels/analytics?funnel=' . $funnel->id . '&from=2020-01-01&to=bad')->assertOk()->assertSee($funnel->title);
        $this->actingAs($analyst)->getJson('/admin/funnels/analytics?funnel=' . $funnel->id)->assertOk()->assertJsonPath('report.totals.orders_placed', 1);
    }
}
