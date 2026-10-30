<?php

namespace Tests\Feature\Funnel;

use App\Models\Funnel;
use App\Models\FunnelPreviewLink;
use App\Services\Funnel\FunnelPreviewService;
use App\Services\Funnel\FunnelService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\Funnel\FunnelTestCase;

/**
 * Secret links to a draft: who can make them, what they show, and when they stop working.
 */
class FunnelPreviewLinkTest extends FunnelTestCase
{
    private FunnelService $service;
    private FunnelPreviewService $previews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FunnelService::class);
        $this->previews = app(FunnelPreviewService::class);
        Carbon::setTestNow();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function funnel(): Funnel
    {
        $admin = $this->makeAdmin();
        $funnel = $this->service->create(['title' => 'Draft only', 'slug' => 'prev-' . strtolower(Str::random(6))], $admin);
        [$funnel] = $this->service->saveDraft($funnel, $this->sellingPage(1, 'Work in progress'), 1, $admin);

        return $funnel->fresh();
    }

    /** @return array{0: Funnel, 1: string} funnel and the secret in its link */
    private function linked(int $hours = 24): array
    {
        $funnel = $this->funnel();
        [, $secret] = $this->previews->create($funnel, $this->makeAdmin(), $hours);

        return [$funnel, $secret];
    }

    public function test_a_link_shows_the_draft_of_an_unpublished_funnel_and_is_never_cached_or_indexed(): void
    {
        [$funnel, $secret] = $this->linked();

        // The public page for the same slug does not exist yet.
        $this->getJson("/api/v2/funnels/{$funnel->slug}")->assertStatus(404);

        $response = $this->getJson("/api/v2/funnels/{$funnel->slug}/preview/{$secret}");
        $response->assertOk()
            ->assertJsonPath('preview', true)
            ->assertJsonPath('funnel.status', 'draft')
            ->assertJsonPath('seo.noindex', true)
            ->assertJsonPath('document.page.sections.0.children.0.props.text', 'Work in progress');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    }

    public function test_the_preview_shows_edits_made_after_the_link_was_created(): void
    {
        [$funnel, $secret] = $this->linked();
        $this->service->saveDraft($funnel->fresh(), $this->sellingPage(1, 'Edited later'), $funnel->fresh()->draft_revision, $this->makeAdmin());

        $this->getJson("/api/v2/funnels/{$funnel->slug}/preview/{$secret}")
            ->assertJsonPath('document.page.sections.0.children.0.props.text', 'Edited later');
    }

    public function test_only_a_hash_of_the_secret_is_stored(): void
    {
        [$funnel, $secret] = $this->linked();

        $row = FunnelPreviewLink::where('funnel_id', $funnel->id)->firstOrFail();
        $this->assertNotSame($secret, $row->token_hash);
        $this->assertSame(hash('sha256', $secret), $row->token_hash);
        $this->assertSame(0, \DB::table('funnel_preview_links')->where('token_hash', $secret)->count());
        $this->assertSame(40, strlen($secret));
    }

    public function test_a_link_stops_working_when_it_expires(): void
    {
        [$funnel, $secret] = $this->linked(2);

        $this->getJson("/api/v2/funnels/{$funnel->slug}/preview/{$secret}")->assertOk();

        Carbon::setTestNow(now()->addHours(2)->addMinute());
        $this->getJson("/api/v2/funnels/{$funnel->slug}/preview/{$secret}")->assertStatus(404);
    }

    public function test_a_revoked_link_stops_working_at_once(): void
    {
        $funnel = $this->funnel();
        $admin = $this->makeAdmin();
        [$link, $secret] = $this->previews->create($funnel, $admin, 24);
        $this->getJson("/api/v2/funnels/{$funnel->slug}/preview/{$secret}")->assertOk();

        $this->previews->revoke($funnel, $link->id, $admin);

        $this->getJson("/api/v2/funnels/{$funnel->slug}/preview/{$secret}")->assertStatus(404);
    }

    public function test_a_link_works_only_for_its_own_funnel(): void
    {
        [$funnel, $secret] = $this->linked();
        $other = $this->funnel();

        $this->getJson("/api/v2/funnels/{$other->slug}/preview/{$secret}")->assertStatus(404);
    }

    public function test_wrong_or_malformed_secrets_all_get_the_same_answer(): void
    {
        [$funnel] = $this->linked();

        foreach ([Str::random(40), 'short', str_repeat('a', 41), 'has space in it ' . Str::random(24), "'; DROP TABLE funnels;--"] as $bad) {
            $this->getJson("/api/v2/funnels/{$funnel->slug}/preview/" . rawurlencode($bad))
                ->assertStatus(404)->assertJsonPath('code', 'not_found');
        }
    }

    public function test_an_archived_funnel_cannot_be_previewed(): void
    {
        [$funnel, $secret] = $this->linked();
        $this->service->archive($funnel->fresh(), $this->makeAdmin());

        $this->getJson("/api/v2/funnels/{$funnel->slug}/preview/{$secret}")->assertStatus(404);
    }

    public function test_a_deleted_funnel_cannot_be_previewed(): void
    {
        [$funnel, $secret] = $this->linked();
        $slug = $funnel->slug;
        $this->service->delete($funnel->fresh(), $this->makeAdmin());

        $this->getJson("/api/v2/funnels/{$slug}/preview/{$secret}")->assertStatus(404);
    }

    public function test_preview_offers_and_quotes_work_for_a_draft_and_cannot_place_orders(): void
    {
        [$funnel, $secret] = $this->linked();

        $this->getJson("/api/v2/funnels/{$funnel->slug}/preview/{$secret}/offers")
            ->assertOk()->assertJsonPath('offers.offer_one.available', true);

        $this->postJson("/api/v2/funnels/{$funnel->slug}/preview/{$secret}/quote", ['selections' => [['key' => 'offer_one', 'quantity' => 2]]])
            ->assertOk()->assertJsonPath('result', true);

        // There is no ordering route under a preview link.
        $this->postJson("/api/v2/funnels/{$funnel->slug}/preview/{$secret}/checkout", [])->assertStatus(404);
        $this->postJson("/api/v2/funnels/{$funnel->slug}/checkout", ['idempotency_key' => 'k-' . Str::random(30)])->assertStatus(404);
    }

    public function test_a_wrong_secret_gets_no_offers_or_quotes(): void
    {
        [$funnel] = $this->linked();
        $bad = Str::random(40);

        $this->getJson("/api/v2/funnels/{$funnel->slug}/preview/{$bad}/offers")->assertStatus(404);
        $this->postJson("/api/v2/funnels/{$funnel->slug}/preview/{$bad}/quote", ['selections' => []])->assertStatus(404);
    }

    // ── making and managing links through the editor API ───────────────────

    public function test_the_editor_api_creates_lists_and_revokes_links_and_shows_the_secret_only_once(): void
    {
        $funnel = $this->funnel();
        $token = $this->makeAdmin()->createToken('funnel-editor', ['funnel:edit'])->plainTextToken;
        config(['funnel.public_url' => 'https://shop.test']);

        $created = $this->withToken($token)->postJson("/api/v2/funnel-editor/funnels/{$funnel->id}/preview-links", ['hours' => 48, 'label' => 'For the client']);
        $created->assertStatus(201)->assertJsonPath('data.label', 'For the client')->assertJsonPath('data.is_active', true);
        $url = $created->json('url');
        $this->assertMatchesRegularExpression('#^https://shop\.test/funnel/' . preg_quote($funnel->slug, '#') . '/preview/[A-Za-z0-9]{40}$#', $url);

        $secret = substr($url, -40);
        $this->getJson("/api/v2/funnels/{$funnel->slug}/preview/{$secret}")->assertOk();

        $this->forgetAuth();
        $list = $this->withToken($token)->getJson("/api/v2/funnel-editor/funnels/{$funnel->id}/preview-links");
        $list->assertOk()->assertJsonCount(1, 'data');
        $this->assertStringNotContainsString($secret, $list->getContent());
        $linkId = $list->json('data.0.id');

        $this->forgetAuth();
        $this->withToken($token)->deleteJson("/api/v2/funnel-editor/funnels/{$funnel->id}/preview-links/{$linkId}")
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson("/api/v2/funnels/{$funnel->slug}/preview/{$secret}")->assertStatus(404);
    }

    public function test_link_lifetime_is_limited(): void
    {
        $funnel = $this->funnel();
        $token = $this->makeAdmin()->createToken('funnel-editor', ['funnel:edit'])->plainTextToken;

        foreach ([0, -5, 24 * 31] as $hours) {
            $this->forgetAuth();
            $this->withToken($token)->postJson("/api/v2/funnel-editor/funnels/{$funnel->id}/preview-links", ['hours' => $hours])
                ->assertStatus(422)->assertJsonPath('code', 'invalid_expiry');
        }
    }

    public function test_a_funnel_keeps_at_most_the_configured_number_of_active_links(): void
    {
        config(['funnel.preview.max_active_links' => 2]);
        $funnel = $this->funnel();
        $admin = $this->makeAdmin();
        $this->previews->create($funnel, $admin, 1);
        $this->previews->create($funnel, $admin, 1);

        $this->expectExceptionMessage('maximum number of active preview links');
        $this->previews->create($funnel, $admin, 1);
    }

    public function test_viewing_without_view_permission_or_a_token_is_refused(): void
    {
        $funnel = $this->funnel();
        $this->getJson("/api/v2/funnel-editor/funnels/{$funnel->id}/preview-links")->assertStatus(401);

        $viewer = $this->makeUser('staff', ['view_funnels']);
        $token = $viewer->createToken('funnel-editor', ['funnel:edit'])->plainTextToken;
        $this->withToken($token)->postJson("/api/v2/funnel-editor/funnels/{$funnel->id}/preview-links", [])->assertStatus(403);
    }

    public function test_opening_a_link_is_counted_without_a_write_per_view(): void
    {
        [$funnel, $secret] = $this->linked();
        for ($i = 0; $i < 5; $i++) {
            $this->getJson("/api/v2/funnels/{$funnel->slug}/preview/{$secret}")->assertOk();
        }

        $link = FunnelPreviewLink::where('funnel_id', $funnel->id)->firstOrFail();
        $this->assertSame(1, $link->use_count);
        $this->assertNotNull($link->last_used_at);
    }
}
