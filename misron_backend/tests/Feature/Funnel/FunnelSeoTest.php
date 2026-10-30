<?php

namespace Tests\Feature\Funnel;

use App\Models\Funnel;
use App\Services\Funnel\FunnelService;
use Illuminate\Support\Str;
use Tests\Funnel\FunnelTestCase;

/**
 * Search and sharing details: what the page head gets, what stays private, and
 * the list the sitemap is built from.
 */
class FunnelSeoTest extends FunnelTestCase
{
    private FunnelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FunnelService::class);
        config(['funnel.public_url' => 'https://shop.test']);
    }

    private function live(string $slug = null, array $details = []): Funnel
    {
        $admin = $this->makeAdmin();
        $funnel = $this->service->create(['title' => 'Blender Mega Offer', 'slug' => $slug ?: 'seo-' . strtolower(Str::random(6))], $admin);
        [$funnel] = $this->service->saveDraft($funnel, $this->sellingPage(), 1, $admin);
        if ($details) {
            $this->service->updateDetails($funnel->fresh(), $details, $admin);
        }
        [$funnel] = $this->service->publish($funnel->fresh(), $admin);

        return $funnel->fresh();
    }

    public function test_the_page_gets_title_description_canonical_and_share_details(): void
    {
        $funnel = $this->live(null, ['meta_title' => 'Best blender in town', 'meta_description' => 'Order today.']);

        $seo = $this->getJson("/api/v2/funnels/{$funnel->slug}")->assertOk()->json('seo');

        $this->assertSame('Best blender in town', $seo['title']);
        $this->assertSame('Order today.', $seo['description']);
        $this->assertSame("https://shop.test/funnel/{$funnel->slug}", $seo['canonical']);
        $this->assertFalse($seo['noindex']);
        $this->assertSame('bn', $seo['language']);
        $this->assertSame('Best blender in town', $seo['og']['title']);
        $this->assertSame('website', $seo['og']['type']);
    }

    public function test_the_title_falls_back_to_the_page_title_and_the_share_texts_to_the_search_texts(): void
    {
        $funnel = $this->live();

        $seo = $this->getJson("/api/v2/funnels/{$funnel->slug}")->json('seo');

        $this->assertSame('Blender Mega Offer', $seo['title']);
        $this->assertNull($seo['description']);
        $this->assertSame('Blender Mega Offer', $seo['og']['title']);
        $this->assertNull($seo['og']['image']);
    }

    public function test_share_title_description_noindex_and_canonical_are_saved_and_used(): void
    {
        $admin = $this->makeAdmin();
        $token = $admin->createToken('funnel-editor', ['funnel:edit'])->plainTextToken;
        $funnel = $this->live();

        $this->withToken($token)->putJson("/api/v2/funnel-editor/funnels/{$funnel->id}", [
            'meta_description' => 'Search text',
            'og_title' => 'Share title',
            'og_description' => 'Share text',
            'noindex' => true,
            'canonical_url' => 'https://brand.example/blender',
        ])->assertOk()
            ->assertJsonPath('data.seo.noindex', true)
            ->assertJsonPath('data.seo.og_title', 'Share title');

        $seo = $this->getJson("/api/v2/funnels/{$funnel->slug}")->json('seo');
        $this->assertTrue($seo['noindex']);
        $this->assertSame('https://brand.example/blender', $seo['canonical']);
        $this->assertSame('Share title', $seo['og']['title']);
        $this->assertSame('Share text', $seo['og']['description']);
        $this->assertSame('Search text', $seo['description']);
    }

    public function test_seo_changes_apply_without_publishing_again(): void
    {
        $funnel = $this->live();
        $versionBefore = $funnel->published_version_id;

        $this->service->updateDetails($funnel, ['meta_title' => 'New title'], $this->makeAdmin());

        $this->assertSame($versionBefore, $funnel->fresh()->published_version_id);
        $this->assertSame('New title', $this->getJson("/api/v2/funnels/{$funnel->slug}")->json('seo.title'));
    }

    public function test_a_canonical_address_must_be_a_full_https_address(): void
    {
        $admin = $this->makeAdmin();
        $token = $admin->createToken('funnel-editor', ['funnel:edit'])->plainTextToken;
        $funnel = $this->live();

        foreach (['http://brand.example/x', 'javascript:alert(1)', '/relative', 'https://', 'https://a b.example'] as $bad) {
            $this->forgetAuth();
            $this->withToken($token)->putJson("/api/v2/funnel-editor/funnels/{$funnel->id}", ['canonical_url' => $bad])
                ->assertStatus(422)->assertJsonPath('code', 'invalid_canonical_url');
        }
    }

    public function test_the_share_picture_is_the_chosen_upload_and_never_the_placeholder(): void
    {
        $funnel = $this->live();
        $this->assertNull($this->getJson("/api/v2/funnels/{$funnel->slug}")->json('seo.og.image'));

        $uploadId = \DB::table('uploads')->insertGetId(['file_original_name' => 'share', 'file_name' => 'uploads/all/share.jpg', 'user_id' => 1, 'file_size' => 1000, 'extension' => 'jpg', 'type' => 'image']);
        $this->service->updateDetails($funnel->fresh(), ['og_image_upload_id' => $uploadId], $this->makeAdmin());
        $this->assertStringContainsString('uploads/all/share.jpg', $this->getJson("/api/v2/funnels/{$funnel->slug}")->json('seo.og.image'));

        // A deleted upload falls back to no picture, not to the placeholder.
        \DB::table('uploads')->where('id', $uploadId)->delete();
        $this->assertNull($this->getJson("/api/v2/funnels/{$funnel->slug}")->json('seo.og.image'));
    }

    public function test_structured_data_describes_the_first_single_piece_offer_with_the_live_price(): void
    {
        $funnel = $this->live();
        $ld = $this->getJson("/api/v2/funnels/{$funnel->slug}")->json('seo.json_ld');

        $this->assertSame('Product', $ld['@type']);
        $this->assertSame('Offer', $ld['offers']['@type']);
        $this->assertSame('https://schema.org/InStock', $ld['offers']['availability']);
        // 1000 list price, 10% off.
        $this->assertSame('900.00', $ld['offers']['price']);
        $this->assertNotEmpty($ld['offers']['priceCurrency']);
        $this->assertSame("https://shop.test/funnel/{$funnel->slug}", $ld['url']);
    }

    public function test_the_page_language_comes_from_the_document(): void
    {
        $admin = $this->makeAdmin();
        $funnel = $this->service->create(['title' => 'English page', 'slug' => 'en-' . strtolower(Str::random(5))], $admin);
        $page = $this->sellingPage();
        $page['page']['settings'] = ['language' => 'en'];
        [$funnel] = $this->service->saveDraft($funnel, $page, 1, $admin);
        $this->service->publish($funnel->fresh(), $admin);

        $this->assertSame('en', $this->getJson("/api/v2/funnels/{$funnel->slug}")->json('seo.language'));
    }

    // ── the list behind the sitemap ────────────────────────────────────────

    public function test_the_public_list_has_live_pages_only_with_their_noindex_flag(): void
    {
        $live = $this->live();
        $hidden = $this->live(null, []);
        $this->service->updateDetails($hidden, ['noindex' => true], $this->makeAdmin());
        $unpublished = $this->live();
        $this->service->unpublish($unpublished, $this->makeAdmin());
        $draft = $this->service->create(['title' => 'Just a draft', 'slug' => 'draft-' . strtolower(Str::random(5))], $this->makeAdmin());

        $rows = collect($this->getJson('/api/v2/funnels')->assertOk()->json('data'))->keyBy('slug');

        $this->assertTrue($rows->has($live->slug));
        $this->assertFalse($rows[$live->slug]['noindex']);
        $this->assertTrue($rows[$hidden->slug]['noindex']);
        $this->assertFalse($rows->has($unpublished->slug));
        $this->assertFalse($rows->has($draft->slug));
        $this->assertArrayNotHasKey('draft_content', $rows[$live->slug]);
    }

    public function test_the_public_page_exposes_nothing_internal(): void
    {
        $funnel = $this->live();

        $body = $this->getJson("/api/v2/funnels/{$funnel->slug}")->getContent();

        foreach (['internal_name', 'created_by', 'updated_by', 'draft_hash', 'draft_revision', 'token_hash'] as $private) {
            $this->assertStringNotContainsString($private, $body);
        }
    }
}
