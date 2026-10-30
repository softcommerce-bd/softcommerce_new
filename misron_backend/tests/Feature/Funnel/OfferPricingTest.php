<?php

namespace Tests\Feature\Funnel;

use App\Models\Funnel;
use App\Models\Product;
use App\Models\ProductStock;
use App\Services\Funnel\FunnelException;
use App\Services\Funnel\FunnelService;
use App\Services\Funnel\Offers\OfferEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Funnel\FunnelTestCase;

/**
 * Offers priced against the live catalogue: lookup, variations, availability,
 * stock shared between offers, dates, limits and the server's refusal to
 * trust anything the browser says about money.
 */
class OfferPricingTest extends FunnelTestCase
{
    private function engine(): OfferEngine
    {
        return app(OfferEngine::class);
    }

    private function binding(string $key, array $items, array $offer = ['type' => 'none'], array $display = []): array
    {
        $normalized = array_map(function ($item) {
            return $item + ['variant' => null, 'quantity' => 1, 'lockVariant' => false];
        }, $items);

        return array_filter(['key' => $key, 'items' => $normalized, 'offer' => $offer, 'display' => $display ?: null]);
    }

    private function item(Product $product, ?string $variant = null, int $quantity = 1, bool $lock = false): array
    {
        return ['productId' => $product->id, 'variant' => $variant, 'quantity' => $quantity, 'lockVariant' => $lock];
    }

    private function quote(array $bindings, $selections, ?Carbon $now = null): array
    {
        return $this->engine()->quote($bindings, $selections, $now);
    }

    private function codes(array $quote): array
    {
        return array_column($quote['errors'], 'code');
    }

    /** A product listed at 1,000 with a 10% website discount, i.e. 900 today. */
    private function blender(array $attributes = [], array $stocks = []): Product
    {
        return $this->makeProduct($attributes + ['name' => 'Super Blender', 'unit_price' => 1000, 'discount' => 10, 'discount_type' => 'percent'], $stocks);
    }

    // ── Lookup and binding ───────────────────────────────────────────────

    public function test_resolve_reads_live_product_data_without_copying_it()
    {
        $product = $this->blender([], [['sku' => 'BLD-1', 'price' => 1000, 'qty' => 7]]);
        $offers = $this->engine()->resolve([$this->binding('offer_a', [$this->item($product)], ['type' => 'percent', 'value' => 10])]);

        $card = $offers['offer_a'];
        $this->assertTrue($card['available']);
        $this->assertSame('Super Blender', $card['title']);
        $this->assertSame($product->id, $card['items'][0]['product_id']);
        $this->assertEquals(1000, $card['items'][0]['regular']);
        $this->assertEquals(900, $card['items'][0]['current']);
        $this->assertSame(7, $card['items'][0]['stock_quantity']);
        // Offer applies on the website price: 10% off 900.
        $this->assertEquals(810, $card['price']['offer']['amount']);
        $this->assertEquals(90, $card['price']['savings']['amount']);
        $this->assertSame(1, DB::table('products')->where('name', 'Super Blender')->count(), 'no second product record');

        // A website change shows up on the next read: the funnel stored only the id.
        $product->update(['name' => 'Super Blender X', 'unit_price' => 2000]);
        ProductStock::where('product_id', $product->id)->update(['price' => 2000]);
        $again = $this->engine()->resolve([$this->binding('offer_a', [$this->item($product)], ['type' => 'percent', 'value' => 10])]);
        $this->assertSame('Super Blender X', $again['offer_a']['title']);
        $this->assertEquals(1800 * 0.9, $again['offer_a']['price']['offer']['amount']);
    }

    public function test_funnel_titles_override_without_renaming_the_product()
    {
        $product = $this->blender();
        $offers = $this->engine()->resolve([$this->binding('offer_a', [$this->item($product)], ['type' => 'none'], ['title' => 'ঈদ স্পেশাল ব্লেন্ডার'])]);

        $this->assertSame('ঈদ স্পেশাল ব্লেন্ডার', $offers['offer_a']['title']);
        $this->assertSame('Super Blender', $offers['offer_a']['items'][0]['name']);
        $this->assertSame('Super Blender', Product::find($product->id)->name);
    }

    public function test_totals_separate_product_discount_tax_and_delivery()
    {
        $product = $this->blender();
        DB::table('product_taxes')->insert(['product_id' => $product->id, 'tax_id' => 1, 'tax' => 5, 'tax_type' => 'percent']);

        $quote = $this->quote(
            [$this->binding('offer_a', [$this->item($product)], ['type' => 'percent', 'value' => 10, 'freeDelivery' => true])],
            [['key' => 'offer_a', 'quantity' => 2]]
        );

        $this->assertTrue($quote['valid'], json_encode($quote['errors']));
        $totals = $quote['totals'];
        $this->assertEquals(2000, $totals['regular_subtotal']['amount']);   // list price
        $this->assertEquals(1800, $totals['website_subtotal']['amount']);   // after the product's own 10%
        $this->assertEquals(180, $totals['funnel_discount']['amount']);     // funnel's extra 10%
        $this->assertEquals(1620, $totals['product_subtotal']['amount']);
        $this->assertEquals(81, $totals['tax']['amount']);                  // 5% of what is charged
        $this->assertEquals(1701, $totals['total_before_delivery']['amount']);
        $this->assertEquals(380, $totals['total_savings']['amount']);
        $this->assertSame('calculated_at_checkout', $totals['delivery']['status']);
        $this->assertTrue($totals['delivery']['free']);
        $this->assertNull($totals['delivery']['amount']);
        $this->assertStringContainsString('1,701', $totals['total_before_delivery']['text']);
        $this->assertArrayNotHasKey('_cents', array_diff_key($quote, ['_cents' => 1]));
    }

    // ── Variations ───────────────────────────────────────────────────────

    public function test_variation_selection_prices_the_chosen_variant()
    {
        $shirt = $this->makeProduct(['name' => 'Shirt', 'unit_price' => 800], [
            ['variant' => 'M', 'price' => 800, 'qty' => 5, 'sku' => 'S-M'],
            ['variant' => 'L', 'price' => 900, 'qty' => 5, 'sku' => 'S-L'],
            ['variant' => 'XL', 'price' => 950, 'qty' => 0, 'sku' => 'S-XL'],
        ]);
        $bindings = [$this->binding('shirt', [$this->item($shirt)], ['type' => 'fixed_amount', 'value' => 100])];

        $card = $this->engine()->resolve($bindings)['shirt'];
        $this->assertSame('M', $card['items'][0]['variant'], 'first in-stock variant is the default');
        $this->assertEquals(700, $card['price']['offer']['amount']);
        $this->assertEquals(800, $card['variant_prices']->L['offer']['amount']);
        $this->assertFalse($card['variant_prices']->XL['available']);

        $quote = $this->quote($bindings, [['key' => 'shirt', 'variant' => 'L', 'quantity' => 2]]);
        $this->assertTrue($quote['valid']);
        $this->assertSame('L', $quote['lines'][0]['items'][0]['variant']);
        $this->assertEquals(1600, $quote['totals']['product_subtotal']['amount']);

        $this->assertContains('variant_required', $this->codes($this->quote($bindings, [['key' => 'shirt', 'quantity' => 1]])));
        $this->assertContains('invalid_variant', $this->codes($this->quote($bindings, [['key' => 'shirt', 'variant' => 'XXL', 'quantity' => 1]])));
        $this->assertContains('out_of_stock', $this->codes($this->quote($bindings, [['key' => 'shirt', 'variant' => 'XL', 'quantity' => 1]])));
    }

    public function test_a_locked_variant_cannot_be_changed_by_the_customer()
    {
        $shirt = $this->makeProduct(['name' => 'Shirt'], [
            ['variant' => 'M', 'price' => 800, 'qty' => 5],
            ['variant' => 'L', 'price' => 900, 'qty' => 5],
        ]);
        $bindings = [$this->binding('shirt', [$this->item($shirt, 'M', 1, true)])];

        $quote = $this->quote($bindings, [['key' => 'shirt', 'variant' => 'L', 'quantity' => 1]]);
        $this->assertTrue($quote['valid']);
        $this->assertSame('M', $quote['lines'][0]['items'][0]['variant']);
        $this->assertEquals(800, $quote['totals']['product_subtotal']['amount']);
    }

    // ── Availability and stock ───────────────────────────────────────────

    public function test_unavailable_products_are_refused_and_disabled()
    {
        $hidden = $this->blender(['published' => 0]);
        $empty = $this->blender([], [['qty' => 0]]);
        $digital = $this->blender(['digital' => 1]);
        $bindings = [
            $this->binding('hidden', [$this->item($hidden)]),
            $this->binding('empty', [$this->item($empty)]),
            $this->binding('digital', [$this->item($digital)]),
        ];

        $offers = $this->engine()->resolve($bindings);
        $this->assertSame('product_unpublished', $offers['hidden']['unavailable_reason']);
        $this->assertSame('out_of_stock', $offers['empty']['unavailable_reason']);
        $this->assertSame('product_unsupported', $offers['digital']['unavailable_reason']);
        $this->assertFalse($offers['hidden']['available']);

        foreach (['hidden' => 'product_unpublished', 'empty' => 'out_of_stock', 'digital' => 'product_unsupported'] as $key => $code) {
            $this->assertContains($code, $this->codes($this->quote($bindings, [['key' => $key, 'quantity' => 1]])), $key);
        }

        $product = $this->blender();
        $missing = [$this->binding('gone', [['productId' => 987654, 'variant' => null, 'quantity' => 1]])];
        $this->assertSame('product_missing', $this->engine()->resolve($missing)['gone']['unavailable_reason']);
        $this->assertContains('product_missing', $this->codes($this->quote($missing, [['key' => 'gone', 'quantity' => 1]])));
        $this->assertNotNull($product);
    }

    public function test_stock_shared_by_several_offers_is_checked_in_total()
    {
        $product = $this->blender([], [['qty' => 5]]);
        $bindings = [
            $this->binding('one', [$this->item($product)]),
            $this->binding('pair', [$this->item($product, null, 2)], ['type' => 'percent', 'value' => 10]),
        ];

        // 3 singles + 1 pair (2 pieces) = 5 pieces: exactly what is in stock.
        $ok = $this->quote($bindings, [['key' => 'one', 'quantity' => 3], ['key' => 'pair', 'quantity' => 1]]);
        $this->assertTrue($ok['valid'], json_encode($ok['errors']));

        // 3 singles + 2 pairs = 7 pieces, though each line alone fits.
        $over = $this->quote($bindings, [['key' => 'one', 'quantity' => 3], ['key' => 'pair', 'quantity' => 2]]);
        $this->assertFalse($over['valid']);
        $this->assertContains('out_of_stock', $this->codes($over));
        $this->assertSame(5, $over['errors'][0]['available']);
    }

    /** UC-06 / UC-07: a sale elsewhere changes what the funnel offers, with no funnel edit. */
    public function test_stock_changes_on_the_website_reach_the_funnel_immediately()
    {
        $product = $this->blender([], [['qty' => 2]]);
        $bindings = [$this->binding('one', [$this->item($product)])];

        $this->assertTrue($this->quote($bindings, [['key' => 'one', 'quantity' => 2]])['valid']);
        $this->assertTrue($this->engine()->resolve($bindings)['one']['available']);

        ProductStock::where('product_id', $product->id)->update(['qty' => 1]);
        $this->assertFalse($this->quote($bindings, [['key' => 'one', 'quantity' => 2]])['valid']);
        $this->assertTrue($this->quote($bindings, [['key' => 'one', 'quantity' => 1]])['valid']);

        ProductStock::where('product_id', $product->id)->update(['qty' => 0]);
        $card = $this->engine()->resolve($bindings)['one'];
        $this->assertFalse($card['available']);
        $this->assertSame('out_of_stock', $card['unavailable_reason']);
    }

    // ── Offer kinds ──────────────────────────────────────────────────────

    public function test_quantity_discount_bxgy_and_fixed_price_through_the_engine()
    {
        $product = $this->makeProduct(['unit_price' => 500], [['qty' => 100, 'price' => 500]]);
        $bindings = [
            $this->binding('tier', [$this->item($product)], ['type' => 'quantity_discount', 'tiers' => [
                ['minQty' => 3, 'type' => 'percent', 'value' => 10], ['minQty' => 6, 'type' => 'fixed_amount', 'value' => 100],
            ]]),
            $this->binding('bxgy', [$this->item($product)], ['type' => 'bxgy', 'buyQty' => 2, 'getQty' => 1]),
            $this->binding('fixed', [$this->item($product)], ['type' => 'fixed_price', 'value' => 400]),
        ];

        $this->assertEquals(1000, $this->quote($bindings, [['key' => 'tier', 'quantity' => 2]])['totals']['product_subtotal']['amount']);
        $this->assertEquals(1350, $this->quote($bindings, [['key' => 'tier', 'quantity' => 3]])['totals']['product_subtotal']['amount']);
        $this->assertEquals(6 * 400, $this->quote($bindings, [['key' => 'tier', 'quantity' => 6]])['totals']['product_subtotal']['amount']);

        $bxgy = $this->quote($bindings, [['key' => 'bxgy', 'quantity' => 6]]);
        $this->assertEquals(4 * 500, $bxgy['totals']['product_subtotal']['amount']);
        $this->assertSame(2, $bxgy['lines'][0]['free_units']);

        $this->assertEquals(2 * 400, $this->quote($bindings, [['key' => 'fixed', 'quantity' => 2]])['totals']['product_subtotal']['amount']);
    }

    public function test_free_gift_adds_a_gift_line_and_needs_gift_stock()
    {
        $main = $this->makeProduct(['unit_price' => 1000], [['qty' => 10]]);
        $gift = $this->makeProduct(['name' => 'Travel Mug', 'unit_price' => 200], [['qty' => 3]]);
        $bindings = [$this->binding('with_gift', [$this->item($main)], ['type' => 'free_gift', 'giftProductId' => $gift->id, 'giftQuantity' => 1])];

        $quote = $this->quote($bindings, [['key' => 'with_gift', 'quantity' => 2]]);
        $this->assertTrue($quote['valid']);
        $this->assertEquals(2000, $quote['totals']['product_subtotal']['amount'], 'the gift is free');
        $this->assertSame('Travel Mug', $quote['lines'][0]['gift']['name']);
        $this->assertSame(2, $quote['lines'][0]['gift']['pieces']);

        // Only 3 gifts exist: a fourth order unit cannot honour the offer.
        $this->assertContains('out_of_stock', $this->codes($this->quote($bindings, [['key' => 'with_gift', 'quantity' => 4]])));

        ProductStock::where('product_id', $gift->id)->update(['qty' => 0]);
        $this->assertSame('gift_unavailable', $this->engine()->resolve($bindings)['with_gift']['unavailable_reason']);
        $this->assertContains('gift_unavailable', $this->codes($this->quote($bindings, [['key' => 'with_gift', 'quantity' => 1]])));
    }

    public function test_bundle_prices_split_and_sum_exactly()
    {
        $a = $this->makeProduct(['name' => 'A', 'unit_price' => 333.33], [['price' => 333.33, 'qty' => 9]]);
        $b = $this->makeProduct(['name' => 'B', 'unit_price' => 333.33], [['price' => 333.33, 'qty' => 9]]);
        $c = $this->makeProduct(['name' => 'C', 'unit_price' => 333.34], [['price' => 333.34, 'qty' => 9]]);
        $bindings = [$this->binding('set', [$this->item($a), $this->item($b), $this->item($c, null, 2)], ['type' => 'fixed_price', 'value' => 1199.99])];

        $quote = $this->quote($bindings, [['key' => 'set', 'quantity' => 3]]);
        $this->assertTrue($quote['valid'], json_encode($quote['errors']));
        $this->assertEquals(3599.97, $quote['totals']['product_subtotal']['amount']);
        $lineSum = array_sum(array_map(function ($item) {
            return $item['total']['amount'];
        }, $quote['lines'][0]['items']));
        $this->assertEqualsWithDelta(3599.97, $lineSum, 0.0001);
        $this->assertSame([3, 3, 6], array_column($quote['lines'][0]['items'], 'pieces'));
    }

    public function test_multiple_offers_in_one_selection_add_up()
    {
        $one = $this->makeProduct(['unit_price' => 100], [['qty' => 9, 'price' => 100]]);
        $two = $this->makeProduct(['unit_price' => 250], [['qty' => 9, 'price' => 250]]);
        $bindings = [
            $this->binding('first', [$this->item($one)], ['type' => 'percent', 'value' => 50]),
            $this->binding('second', [$this->item($two)]),
        ];
        $quote = $this->quote($bindings, [['key' => 'first', 'quantity' => 2], ['key' => 'second', 'quantity' => 1]]);

        $this->assertTrue($quote['valid']);
        $this->assertCount(2, $quote['lines']);
        $this->assertEquals(350, $quote['totals']['product_subtotal']['amount']);
        $this->assertEquals(100, $quote['totals']['funnel_discount']['amount']);
    }

    // ── Dates and limits ─────────────────────────────────────────────────

    public function test_offers_outside_their_dates_are_refused()
    {
        $product = $this->blender();
        $offer = ['type' => 'percent', 'value' => 10, 'startsAt' => '2026-12-01T00:00:00+06:00', 'endsAt' => '2026-12-31T23:59:00+06:00'];
        $bindings = [$this->binding('winter', [$this->item($product)], $offer)];
        $select = [['key' => 'winter', 'quantity' => 1]];

        $this->assertContains('offer_not_started', $this->codes($this->quote($bindings, $select, Carbon::parse('2026-11-30 12:00', 'Asia/Dhaka'))));
        $this->assertTrue($this->quote($bindings, $select, Carbon::parse('2026-12-15 12:00', 'Asia/Dhaka'))['valid']);
        $this->assertContains('offer_expired', $this->codes($this->quote($bindings, $select, Carbon::parse('2027-01-01 00:01', 'Asia/Dhaka'))));

        $this->assertSame('scheduled', $this->engine()->resolve($bindings, Carbon::parse('2026-11-30', 'Asia/Dhaka'))['winter']['status']);
        $this->assertSame('expired', $this->engine()->resolve($bindings, Carbon::parse('2027-02-01', 'Asia/Dhaka'))['winter']['status']);
        $this->assertSame('active', $this->engine()->resolve($bindings, Carbon::parse('2026-12-10', 'Asia/Dhaka'))['winter']['status']);
    }

    public function test_per_order_limits()
    {
        $product = $this->makeProduct(['unit_price' => 100], [['qty' => 500, 'price' => 100]]);
        $bindings = [
            $this->binding('limited', [$this->item($product)], ['type' => 'percent', 'value' => 10, 'maxPerOrder' => 3]),
            $this->binding('open', [$this->item($product)]),
        ];

        $this->assertTrue($this->quote($bindings, [['key' => 'limited', 'quantity' => 3]])['valid']);
        $over = $this->quote($bindings, [['key' => 'limited', 'quantity' => 4]]);
        $this->assertContains('limit_exceeded', $this->codes($over));
        $this->assertSame(3, $over['errors'][0]['max']);
        // The platform ceiling applies even without an offer limit.
        $this->assertContains('limit_exceeded', $this->codes($this->quote($bindings, [['key' => 'open', 'quantity' => 100]])));
    }

    // ── Tampering ────────────────────────────────────────────────────────

    public function test_malformed_selections_are_rejected()
    {
        $product = $this->blender();
        $bindings = [$this->binding('a', [$this->item($product)])];

        foreach ([
            [['key' => 'a', 'quantity' => 0]],
            [['key' => 'a', 'quantity' => -3]],
            [['key' => 'a', 'quantity' => 1.5]],
            [['key' => 'a', 'quantity' => '2; DROP TABLE orders']],
            [['key' => 'a', 'quantity' => null]],
            [['key' => ['a'], 'quantity' => 1]],
            [['quantity' => 1]],
            'a',
            ['a' => 1],
        ] as $bad) {
            $quote = $this->quote($bindings, $bad);
            $this->assertFalse($quote['valid'], json_encode($bad));
            $this->assertNull($quote['totals']);
        }

        $dup = $this->quote($bindings, [['key' => 'a', 'quantity' => 1], ['key' => 'a', 'quantity' => 1]]);
        $this->assertContains('duplicate_selection', $this->codes($dup));
        $this->assertContains('empty_selection', $this->codes($this->quote($bindings, [])));
        $this->assertContains('unknown_offer', $this->codes($this->quote($bindings, [['key' => 'not_on_the_page', 'quantity' => 1]])));

        $many = array_map(function ($i) {
            return ['key' => 'a', 'quantity' => 1];
        }, range(1, 60));
        $this->assertContains('too_many_lines', $this->codes($this->quote($bindings, $many)));
    }

    /** UC-11: a request that carries its own prices does not get them. */
    public function test_client_amounts_are_never_used_and_drafts_cannot_leak()
    {
        $admin = $this->makeAdmin();
        $product = $this->blender([], [['qty' => 10]]);
        $service = app(FunnelService::class);
        $funnel = $service->create(['title' => 'Tamper', 'slug' => 'tamper-test'], $admin);

        $page = $this->sellingPage($product->id);
        $page['page']['sections'][1]['productBindings'][0]['offer'] = ['type' => 'percent', 'value' => 10];
        [$funnel] = $service->saveDraft($funnel, $page, 1, $admin);
        $service->publish($funnel, $admin);

        $honest = $this->postJson('/api/v2/funnels/tamper-test/quote', ['selections' => [['key' => 'offer_one', 'quantity' => 2]]]);
        $honest->assertOk()->assertJsonPath('totals.product_subtotal.amount', 1620);

        $forged = $this->postJson('/api/v2/funnels/tamper-test/quote', [
            'selections' => [[
                'key' => 'offer_one', 'quantity' => 2,
                'price' => 1, 'unit_price' => 1, 'total' => 1, 'discount' => 99999, 'amount' => 0, 'offer' => ['type' => 'percent', 'value' => 99],
            ]],
            'total' => 1, 'discount' => 99999, 'bindings' => [['key' => 'offer_one', 'offer' => ['type' => 'percent', 'value' => 99]]],
        ]);
        $forged->assertOk()->assertJsonPath('totals.product_subtotal.amount', 1620)->assertJsonPath('fingerprint', $honest->json('fingerprint'));

        $this->postJson('/api/v2/funnels/tamper-test/quote', ['selections' => [['key' => 'offer_nine', 'quantity' => 1]]])
            ->assertStatus(422)->assertJsonPath('errors.0.code', 'unknown_offer');
        $this->postJson('/api/v2/funnels/tamper-test/quote', ['selections' => [['key' => 'offer_one', 'quantity' => -1]]])
            ->assertStatus(422);

        // The draft now has a 90% offer and a new key; customers still get the published rules.
        $page['page']['sections'][1]['productBindings'][0]['offer'] = ['type' => 'percent', 'value' => 90];
        $page['page']['sections'][1]['productBindings'][] = ['key' => 'offer_two', 'items' => [['productId' => $product->id, 'quantity' => 1]], 'offer' => ['type' => 'percent', 'value' => 90]];
        $service->saveDraft($funnel->fresh(), $page, $funnel->fresh()->draft_revision, $admin);

        $this->postJson('/api/v2/funnels/tamper-test/quote', ['selections' => [['key' => 'offer_one', 'quantity' => 2]]])
            ->assertOk()->assertJsonPath('totals.product_subtotal.amount', 1620);
        $this->postJson('/api/v2/funnels/tamper-test/quote', ['selections' => [['key' => 'offer_two', 'quantity' => 1]]])
            ->assertStatus(422)->assertJsonPath('errors.0.code', 'unknown_offer');
        $this->getJson('/api/v2/funnels/tamper-test/offers')->assertOk()->assertJsonCount(1, 'offers');
    }

    public function test_only_live_funnels_are_priced()
    {
        $admin = $this->makeAdmin();
        $service = app(FunnelService::class);
        $draft = $service->create(['title' => 'Draft only', 'slug' => 'draft-only'], $admin);
        $this->postJson('/api/v2/funnels/draft-only/quote', ['selections' => []])->assertStatus(404);
        $this->getJson('/api/v2/funnels/draft-only/offers')->assertStatus(404);
        $this->getJson('/api/v2/funnels/Not_A_Slug!/offers')->assertStatus(404);

        $product = $this->blender();
        [$funnel] = $service->saveDraft($draft, $this->sellingPage($product->id), 1, $admin);
        $funnel = $service->publish($funnel, $admin)[0];
        $this->getJson('/api/v2/funnels/draft-only/offers')->assertOk();

        $service->unpublish($funnel, $admin);
        $this->getJson('/api/v2/funnels/draft-only/offers')->assertStatus(404);
    }

    // ── Published-version consistency ────────────────────────────────────

    public function test_website_price_updates_move_the_payable_amount_but_never_the_rule()
    {
        $admin = $this->makeAdmin();
        $product = $this->makeProduct(['unit_price' => 1000], [['price' => 1000, 'qty' => 10]]);
        $service = app(FunnelService::class);
        $funnel = $service->create(['title' => 'Price move', 'slug' => 'price-move'], $admin);
        $page = $this->sellingPage($product->id);
        $page['page']['sections'][1]['productBindings'][0]['offer'] = ['type' => 'percent', 'value' => 20];
        [$funnel] = $service->saveDraft($funnel, $page, 1, $admin);
        [$funnel, $version] = $service->publish($funnel, $admin);

        $ask = function () {
            return $this->postJson('/api/v2/funnels/price-move/quote', ['selections' => [['key' => 'offer_one', 'quantity' => 1]]]);
        };
        $ask()->assertOk()->assertJsonPath('totals.product_subtotal.amount', 800);

        // The shop raises the price: 20% off the new price, rule untouched.
        $product->update(['unit_price' => 1500]);
        ProductStock::where('product_id', $product->id)->update(['price' => 1500]);
        $ask()->assertOk()->assertJsonPath('totals.product_subtotal.amount', 1200);

        // The shop starts a 500 website discount: the funnel offer applies on top of it.
        $product->update(['discount' => 500, 'discount_type' => 'amount']);
        $ask()->assertOk()->assertJsonPath('totals.product_subtotal.amount', 800)->assertJsonPath('totals.website_subtotal.amount', 1000);

        $stored = $version->fresh()->content['page']['sections'][1]['productBindings'][0]['offer'];
        $this->assertSame(['type' => 'percent', 'value' => 20], $stored);
    }

    /** Spec scenario: published version keeps the offer configuration. */
    public function test_published_version_preserves_offers_while_the_draft_changes()
    {
        $admin = $this->makeAdmin();
        $products = [];
        foreach (range(1, 8) as $i) {
            $products[] = $this->makeProduct(['name' => "Blender {$i}", 'unit_price' => 1000 + $i * 100], [['price' => 1000 + $i * 100, 'qty' => 20, 'sku' => "BLD-{$i}"]]);
        }
        $bindings = [];
        foreach ($products as $i => $product) {
            $bindings[] = [
                'key' => 'offer_b' . ($i + 1),
                'items' => [['productId' => $product->id, 'variant' => null, 'quantity' => 1]],
                'offer' => $i % 2 === 0 ? ['type' => 'percent', 'value' => 10 + $i] : ['type' => 'fixed_amount', 'value' => 100 * $i, 'freeDelivery' => true],
                'display' => ['title' => 'অফার ' . ($i + 1)],
            ];
        }
        $page = ['schemaVersion' => 1, 'page' => ['sections' => [
            ['id' => 'sel_001', 'type' => 'product_offer_selector', 'props' => ['selectionMode' => 'multiple', 'cardLayout' => 'compact'], 'style' => ['mobile' => ['columns' => 2]], 'productBindings' => $bindings],
            ['id' => 'checkout_001', 'type' => 'checkout_form', 'children' => [['id' => 'buy_001', 'type' => 'purchase_button', 'props' => ['text' => 'Order now']]]],
        ]]];

        $service = app(FunnelService::class);
        $funnel = $service->create(['title' => 'Eight blenders'], $admin);
        [$funnel] = $service->saveDraft($funnel, $page, 1, $admin);
        [$funnel, $version] = $service->publish($funnel, $admin);

        $published = $version->fresh()->content['page']['sections'][0];
        $this->assertCount(8, $published['productBindings']);
        $this->assertSame(['mobile' => ['columns' => 2]], $published['style']);
        $this->assertSame('multiple', $published['props']['selectionMode']);
        $this->assertEqualsCanonicalizing(array_column($products, 'id'), $funnel->fresh()->product_ids);

        // Edit the draft: all offers 50% off.
        foreach ($page['page']['sections'][0]['productBindings'] as &$binding) {
            $binding['offer'] = ['type' => 'percent', 'value' => 50];
        }
        unset($binding);
        $service->saveDraft($funnel->fresh(), $page, $funnel->fresh()->draft_revision, $admin);

        $after = Funnel::find($funnel->id)->publishedVersion->content['page']['sections'][0]['productBindings'];
        $this->assertSame($published['productBindings'], $after, 'published offers are immutable');
        $this->getJson("/api/v2/funnels/{$funnel->fresh()->slug}/offers")
            ->assertOk()
            ->assertJsonPath('offers.offer_b1.type', 'percent')
            ->assertJsonPath('offers.offer_b1.items.0.name', 'Blender 1')
            ->assertJsonPath('offers.offer_b2.type', 'fixed_amount')
            ->assertJsonPath('offers.offer_b2.free_delivery', true);
    }

    // ── Publish-time audit ───────────────────────────────────────────────

    public function test_publishing_checks_offers_against_the_catalogue()
    {
        $admin = $this->makeAdmin();
        $service = app(FunnelService::class);
        $real = $this->blender([], [['variant' => 'M', 'qty' => 3, 'price' => 1000], ['variant' => 'L', 'qty' => 3, 'price' => 1000]]);
        $other = $this->blender();
        $hidden = $this->blender(['published' => 0]);
        $digital = $this->blender(['digital' => 1]);

        $page = function (array $bindings) {
            return ['schemaVersion' => 1, 'page' => ['sections' => [
                ['id' => 'sel_001', 'type' => 'product_offer_selector', 'productBindings' => $bindings],
                ['id' => 'checkout_001', 'type' => 'checkout_form', 'children' => [['id' => 'buy_001', 'type' => 'purchase_button', 'props' => ['text' => 'Order now']]]],
            ]]];
        };
        $try = function (array $bindings) use ($service, $admin, $page) {
            $funnel = $service->create(['title' => 'Audit ' . uniqid()], $admin);
            [$funnel] = $service->saveDraft($funnel, $page($bindings), 1, $admin);
            try {
                [, , $result] = $service->publish($funnel, $admin);

                return ['ok' => true, 'warnings' => array_column($result->warnings(), 'code')];
            } catch (FunnelException $e) {
                return ['ok' => false, 'errors' => array_column($e->details()['errors'], 'code')];
            }
        };

        $gone = $try([['key' => 'offer_a', 'items' => [['productId' => 999999, 'quantity' => 1]], 'offer' => ['type' => 'none']]]);
        $this->assertSame(['product_missing'], $gone['errors']);

        $badVariant = $try([['key' => 'offer_a', 'items' => [['productId' => $real->id, 'variant' => 'XXL', 'quantity' => 1]], 'offer' => ['type' => 'none']]]);
        $this->assertSame(['invalid_variant'], $badVariant['errors']);

        $openBundle = $try([['key' => 'offer_a', 'items' => [['productId' => $real->id, 'quantity' => 1], ['productId' => $other->id, 'quantity' => 1]], 'offer' => ['type' => 'percent', 'value' => 10]]]);
        $this->assertSame(['variant_required'], $openBundle['errors']);

        $unsupported = $try([['key' => 'offer_a', 'items' => [['productId' => $digital->id, 'quantity' => 1]], 'offer' => ['type' => 'none']]]);
        $this->assertSame(['product_unsupported'], $unsupported['errors']);

        $free = $try([['key' => 'offer_a', 'items' => [['productId' => $other->id, 'quantity' => 1]], 'offer' => ['type' => 'fixed_amount', 'value' => 5000]]]);
        $this->assertSame(['invalid_offer_price'], $free['errors']);

        $warn = $try([
            ['key' => 'offer_a', 'items' => [['productId' => $hidden->id, 'quantity' => 1]], 'offer' => ['type' => 'none']],
            ['key' => 'offer_b', 'items' => [['productId' => $other->id, 'quantity' => 1]], 'offer' => ['type' => 'fixed_price', 'value' => 5000]],
            ['key' => 'offer_c', 'items' => [['productId' => $other->id, 'quantity' => 1]], 'offer' => ['type' => 'percent', 'value' => 5]],
        ]);
        $this->assertTrue($warn['ok']);
        $this->assertContains('product_unpublished', $warn['warnings']);
        $this->assertContains('offer_not_beneficial', $warn['warnings']);
        $this->assertContains('overlapping_offers', $warn['warnings']);

        // A page that sells products but has no order form warns about it (an error only when the store requires one).
        $noForm = $service->create(['title' => 'No form ' . uniqid()], $admin);
        [$noForm] = $service->saveDraft($noForm, ['schemaVersion' => 1, 'page' => ['sections' => [
            ['id' => 'sel_001', 'type' => 'product_offer_selector', 'productBindings' => [['key' => 'offer_a', 'items' => [['productId' => $other->id, 'quantity' => 1]], 'offer' => ['type' => 'none']]]],
        ]]], 1, $admin);
        [, , $noFormResult] = $service->publish($noForm, $admin);
        $this->assertContains('missing_checkout', array_column($noFormResult->warnings(), 'code'));

        $fine = $try([['key' => 'offer_a', 'items' => [['productId' => $real->id, 'variant' => 'M', 'quantity' => 1, 'lockVariant' => true], ['productId' => $other->id, 'quantity' => 2]], 'offer' => ['type' => 'percent', 'value' => 10]]]);
        $this->assertTrue($fine['ok'], json_encode($fine));
    }

    // ── Editor preview endpoints ─────────────────────────────────────────

    public function test_editor_endpoints_resolve_and_quote_draft_offers_with_permissions()
    {
        $product = $this->blender([], [['qty' => 4]]);
        $bindings = [['key' => 'offer_a', 'items' => [['productId' => $product->id, 'quantity' => 1]], 'offer' => ['type' => 'percent', 'value' => 25]]];

        $service = app(\App\Services\Funnel\EditorSession::class);
        $admin = $this->makeAdmin();
        $funnel = app(FunnelService::class)->create(['title' => 'Preview'], $admin);
        $tokenFor = function ($user) use ($service, $funnel) {
            $code = $service->issueHandoff($user, $funnel);
            $token = $this->postJson('/api/v2/funnel-editor/session', ['code' => $code])->json('token');
            $this->forgetAuth();

            return $token;
        };
        $call = function (string $token, string $path, array $body) {
            $this->forgetAuth();

            return $this->withHeaders(['Authorization' => 'Bearer ' . $token])->postJson('/api/v2/funnel-editor/' . $path, $body);
        };

        $token = $tokenFor($admin);
        $call($token, 'offers/resolve', ['bindings' => $bindings])
            ->assertOk()->assertJsonPath('offers.offer_a.price.offer.amount', 675)->assertJsonPath('offers.offer_a.items.0.stock_quantity', 4);
        $call($token, 'offers/quote', ['bindings' => $bindings, 'selections' => [['key' => 'offer_a', 'quantity' => 2]]])
            ->assertOk()->assertJsonPath('totals.product_subtotal.amount', 1350);
        $call($token, 'offers/quote', ['bindings' => $bindings, 'selections' => [['key' => 'offer_a', 'quantity' => 9]]])
            ->assertStatus(422)->assertJsonPath('errors.0.code', 'out_of_stock');

        // Invalid offers are described, not priced.
        $bad = [['key' => 'offer_a', 'items' => [['productId' => $product->id]], 'offer' => ['type' => 'percent', 'value' => 400]]];
        $call($token, 'offers/resolve', ['bindings' => $bad])->assertStatus(422)->assertJsonPath('errors.0.code', 'invalid_discount');

        $viewer = $this->makeUser('staff', ['view_funnels']);
        $call($tokenFor($viewer), 'offers/resolve', ['bindings' => $bindings])->assertStatus(403);
        $call($tokenFor($viewer), 'offers/quote', ['bindings' => $bindings, 'selections' => []])->assertStatus(403);
    }
}
