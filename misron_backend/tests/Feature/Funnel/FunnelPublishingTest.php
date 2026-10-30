<?php

namespace Tests\Feature\Funnel;

use App\Models\Funnel;
use App\Models\FunnelAuditLog;
use App\Models\FunnelVersion;
use App\Services\Funnel\FunnelException;
use App\Services\Funnel\FunnelService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Funnel\FunnelTestCase;

/**
 * Scheduled publishing and unpublishing, version restore, and the cache refresh
 * that tells the storefront a page changed.
 */
class FunnelPublishingTest extends FunnelTestCase
{
    private FunnelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FunnelService::class);
        Carbon::setTestNow();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function draftFunnel(string $slug = null): Funnel
    {
        $admin = $this->makeAdmin();
        $funnel = $this->service->create(['title' => 'Scheduled page', 'slug' => $slug ?: 'sched-' . strtolower(\Illuminate\Support\Str::random(6))], $admin);
        [$funnel] = $this->service->saveDraft($funnel, $this->sellingPage(), 1, $admin);

        return $funnel->fresh();
    }

    // ── scheduling a start ─────────────────────────────────────────────────

    public function test_a_scheduled_page_is_hidden_until_its_time_and_visible_after_without_any_scheduler(): void
    {
        $funnel = $this->draftFunnel();
        $at = now()->addHours(3);

        [$funnel, $version] = $this->service->schedule($funnel, $at, null, $this->makeAdmin());

        $this->assertSame(Funnel::STATUS_SCHEDULED, $funnel->status);
        $this->assertSame($version->id, (int) $funnel->published_version_id);
        $this->assertFalse($funnel->fresh()->isLive());
        $this->getJson("/api/v2/funnels/{$funnel->slug}")->assertStatus(404);

        // Time passes; nobody has run the scheduler.
        Carbon::setTestNow(now()->addHours(3)->addMinute());
        $this->assertTrue($funnel->fresh()->isLive());
        $this->getJson("/api/v2/funnels/{$funnel->slug}")->assertOk()->assertJsonPath('funnel.slug', $funnel->slug);
    }

    public function test_the_scheduler_makes_the_stored_status_match_and_keeps_an_audit_trail(): void
    {
        $funnel = $this->draftFunnel();
        $this->service->schedule($funnel, now()->addHour(), null, $this->makeAdmin());

        $this->assertSame([], $this->service->reconcile(), 'nothing is due yet');

        Carbon::setTestNow(now()->addHours(2));
        $this->assertSame([$funnel->slug], $this->service->reconcile());

        $fresh = $funnel->fresh();
        $this->assertSame(Funnel::STATUS_PUBLISHED, $fresh->status);
        $this->assertNull($fresh->scheduled_at);
        $this->assertNotNull($fresh->published_at);
        $this->assertTrue(FunnelAuditLog::where('funnel_id', $funnel->id)->where('action', 'went_live')->exists());

        $this->assertSame([], $this->service->reconcile(), 'running again changes nothing');
    }

    public function test_a_start_time_in_the_past_is_refused(): void
    {
        $funnel = $this->draftFunnel();

        try {
            $this->service->schedule($funnel, now()->subMinute(), null, $this->makeAdmin());
            $this->fail('A past start should be refused.');
        } catch (FunnelException $e) {
            $this->assertSame('invalid_schedule', $e->errorCode());
        }
        $this->assertSame(Funnel::STATUS_DRAFT, $funnel->fresh()->status);
    }

    public function test_the_end_must_come_after_the_start(): void
    {
        $funnel = $this->draftFunnel();

        $this->expectException(FunnelException::class);
        $this->service->schedule($funnel, now()->addDay(), now()->addHours(5), $this->makeAdmin());
    }

    public function test_an_invalid_page_cannot_be_scheduled(): void
    {
        $admin = $this->makeAdmin();
        $funnel = $this->service->create(['title' => 'Empty', 'slug' => 'empty-' . strtolower(\Illuminate\Support\Str::random(5))], $admin);

        try {
            $this->service->schedule($funnel, now()->addDay(), null, $admin);
            $this->fail('An empty page must not be schedulable.');
        } catch (FunnelException $e) {
            $this->assertSame('invalid_page', $e->errorCode());
        }
    }

    public function test_what_is_scheduled_is_what_was_checked_even_if_the_draft_changes_afterwards(): void
    {
        $admin = $this->makeAdmin();
        $funnel = $this->draftFunnel();
        [$funnel, $version] = $this->service->schedule($funnel, now()->addHour(), null, $admin);
        $frozen = $version->content_hash;

        [$funnel] = $this->service->saveDraft($funnel->fresh(), $this->sellingPage(1, 'A different headline'), $funnel->fresh()->draft_revision, $admin);

        $this->assertSame($frozen, FunnelVersion::find($funnel->published_version_id)->content_hash);
        $this->assertNotSame($frozen, $funnel->fresh()->draft_hash);
    }

    public function test_a_live_page_cannot_be_scheduled_again(): void
    {
        $admin = $this->makeAdmin();
        $funnel = $this->draftFunnel();
        [$funnel] = $this->service->publish($funnel, $admin);

        $this->expectException(FunnelException::class);
        $this->service->schedule($funnel, now()->addDay(), null, $admin);
    }

    public function test_cancelling_a_schedule_returns_a_new_page_to_draft_and_an_old_one_to_unpublished(): void
    {
        $admin = $this->makeAdmin();
        $funnel = $this->draftFunnel();
        $this->service->schedule($funnel, now()->addDay(), null, $admin);

        $cancelled = $this->service->cancelSchedule($funnel->fresh(), $admin);
        $this->assertSame(Funnel::STATUS_DRAFT, $cancelled->status);
        $this->assertNull($cancelled->published_version_id);
        $this->assertNull($cancelled->scheduled_at);

        // A page that was live before: back to unpublished, keeping its last version.
        $other = $this->draftFunnel();
        $this->service->publish($other, $admin);
        $this->service->unpublish($other->fresh(), $admin);
        $this->service->schedule($other->fresh(), now()->addDay(), null, $admin);
        $back = $this->service->cancelSchedule($other->fresh(), $admin);
        $this->assertSame(Funnel::STATUS_UNPUBLISHED, $back->status);
        $this->assertNotNull($back->published_version_id);
    }

    // ── scheduling an end ──────────────────────────────────────────────────

    public function test_a_live_page_disappears_at_its_end_time_without_any_scheduler(): void
    {
        $admin = $this->makeAdmin();
        $funnel = $this->draftFunnel();
        [$funnel] = $this->service->publish($funnel, $admin);
        $this->service->setEndTime($funnel->fresh(), now()->addHours(2), $admin);

        $this->getJson("/api/v2/funnels/{$funnel->slug}")->assertOk();

        Carbon::setTestNow(now()->addHours(2)->addSecond());
        $this->getJson("/api/v2/funnels/{$funnel->slug}")->assertStatus(404);
        $this->assertSame(Funnel::STATUS_PUBLISHED, $funnel->fresh()->status, 'status is only tidied by the scheduler');

        $this->assertSame([$funnel->slug], $this->service->reconcile());
        $fresh = $funnel->fresh();
        $this->assertSame(Funnel::STATUS_UNPUBLISHED, $fresh->status);
        $this->assertNull($fresh->unpublish_at);
        $this->assertTrue(FunnelAuditLog::where('funnel_id', $funnel->id)->where('action', 'went_offline')->exists());
    }

    public function test_a_window_can_be_scheduled_in_one_step(): void
    {
        $admin = $this->makeAdmin();
        $funnel = $this->draftFunnel();
        $this->service->schedule($funnel, now()->addHour(), now()->addHours(5), $admin);

        Carbon::setTestNow(now()->addHours(2));
        $this->assertTrue($funnel->fresh()->isLive());
        $this->service->reconcile();
        $this->assertSame(Funnel::STATUS_PUBLISHED, $funnel->fresh()->status);

        Carbon::setTestNow(now()->addHours(4));
        $this->assertFalse($funnel->fresh()->isLive());
        $this->service->reconcile();
        $this->assertSame(Funnel::STATUS_UNPUBLISHED, $funnel->fresh()->status);
    }

    public function test_a_window_that_was_missed_entirely_never_goes_live(): void
    {
        $admin = $this->makeAdmin();
        $funnel = $this->draftFunnel();
        $this->service->schedule($funnel, now()->addHour(), now()->addHours(2), $admin);

        // The scheduler was down for the whole window.
        Carbon::setTestNow(now()->addHours(3));
        $this->assertFalse($funnel->fresh()->isLive());
        $this->service->reconcile();

        $this->assertSame(Funnel::STATUS_UNPUBLISHED, $funnel->fresh()->status);
        $this->assertFalse(FunnelAuditLog::where('funnel_id', $funnel->id)->where('action', 'went_live')->exists());
    }

    public function test_publishing_again_keeps_a_future_end_time_and_drops_a_past_one(): void
    {
        $admin = $this->makeAdmin();
        $funnel = $this->draftFunnel();
        [$funnel] = $this->service->publish($funnel, $admin);
        $this->service->setEndTime($funnel->fresh(), now()->addDay(), $admin);

        [$again] = $this->service->publish($funnel->fresh(), $admin);
        $this->assertNotNull($again->fresh()->unpublish_at);

        Carbon::setTestNow(now()->addDays(2));
        $this->service->reconcile();
        [$republished] = $this->service->publish($funnel->fresh(), $admin);
        $this->assertNull($republished->fresh()->unpublish_at);
        $this->assertTrue($republished->fresh()->isLive());
    }

    public function test_unpublishing_clears_the_planned_end(): void
    {
        $admin = $this->makeAdmin();
        $funnel = $this->draftFunnel();
        [$funnel] = $this->service->publish($funnel, $admin);
        $this->service->setEndTime($funnel->fresh(), now()->addDay(), $admin);

        $funnel = $this->service->unpublish($funnel->fresh(), $admin);

        $this->assertNull($funnel->unpublish_at);
    }

    public function test_an_end_time_needs_a_live_or_scheduled_page(): void
    {
        $funnel = $this->draftFunnel();

        $this->expectException(FunnelException::class);
        $this->service->setEndTime($funnel, now()->addDay(), $this->makeAdmin());
    }

    // ── what the storefront is told ────────────────────────────────────────

    public function test_the_storefront_is_told_to_refresh_the_page_on_every_change_that_customers_can_see(): void
    {
        config(['funnel.revalidate.url' => 'https://shop.test/api/funnel/revalidate', 'funnel.revalidate.secret' => 's3cret']);
        Http::fake(['shop.test/*' => Http::response(['ok' => true])]);

        $admin = $this->makeAdmin();
        $funnel = $this->draftFunnel('refresh-me');
        $this->service->publish($funnel, $admin);
        $this->service->unpublish($funnel->fresh(), $admin);
        $this->service->updateDetails($funnel->fresh(), ['slug' => 'refresh-me-two'], $admin);

        Http::assertSentCount(3);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://shop.test/api/funnel/revalidate'
                && $request->hasHeader('Authorization', 'Bearer s3cret')
                && $request['slugs'] === ['refresh-me'];
        });
        // A new address refreshes both the old and the new page.
        Http::assertSent(function ($request) {
            return in_array('refresh-me-two', $request['slugs'], true) && in_array('refresh-me', $request['slugs'], true);
        });
    }

    public function test_an_unreachable_storefront_never_breaks_publishing(): void
    {
        config(['funnel.revalidate.url' => 'https://shop.test/api/funnel/revalidate', 'funnel.revalidate.secret' => 's3cret']);
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('connection refused');
        });

        $funnel = $this->draftFunnel();
        [$published] = $this->service->publish($funnel, $this->makeAdmin());

        $this->assertSame(Funnel::STATUS_PUBLISHED, $published->status);
    }

    public function test_nothing_is_sent_when_the_storefront_is_not_configured(): void
    {
        config(['funnel.revalidate.url' => '', 'funnel.revalidate.secret' => '']);
        Http::fake();

        $this->service->publish($this->draftFunnel(), $this->makeAdmin());

        Http::assertNothingSent();
    }

    // ── API surface ────────────────────────────────────────────────────────

    public function test_schedule_and_end_time_need_the_publish_permission(): void
    {
        $editor = $this->makeUser('staff', ['view_funnels', 'edit_funnel']);
        $funnel = $this->draftFunnel();
        $token = $editor->createToken('funnel-editor', ['funnel:edit'])->plainTextToken;

        $at = now()->addDay()->toIso8601String();
        $this->withToken($token)->postJson("/api/v2/funnel-editor/funnels/{$funnel->id}/schedule", ['at' => $at])->assertStatus(403);
        $this->forgetAuth();
        $this->withToken($token)->putJson("/api/v2/funnel-editor/funnels/{$funnel->id}/end-time", ['until' => $at])->assertStatus(403);
    }

    public function test_the_editor_api_schedules_cancels_and_reports_the_times(): void
    {
        $admin = $this->makeAdmin();
        $funnel = $this->draftFunnel();
        $token = $admin->createToken('funnel-editor', ['funnel:edit'])->plainTextToken;
        $at = now()->addDay()->startOfMinute();
        $until = now()->addDays(3)->startOfMinute();

        $response = $this->withToken($token)->postJson("/api/v2/funnel-editor/funnels/{$funnel->id}/schedule", [
            'at' => $at->toIso8601String(),
            'until' => $until->toIso8601String(),
        ]);
        $response->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.scheduled_at', $at->toIso8601String())
            ->assertJsonPath('data.unpublish_at', $until->toIso8601String());

        $this->forgetAuth();
        $this->withToken($token)->postJson("/api/v2/funnel-editor/funnels/{$funnel->id}/schedule/cancel")
            ->assertOk()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.scheduled_at', null);
    }

    public function test_the_api_refuses_badly_formed_dates(): void
    {
        $admin = $this->makeAdmin();
        $funnel = $this->draftFunnel();
        $token = $admin->createToken('funnel-editor', ['funnel:edit'])->plainTextToken;

        foreach (['tomorrow', '2030-01-01', 12345, ''] as $bad) {
            $this->forgetAuth();
            $this->withToken($token)->postJson("/api/v2/funnel-editor/funnels/{$funnel->id}/schedule", ['at' => $bad])
                ->assertStatus(422)->assertJsonPath('code', 'invalid_schedule');
        }
    }

    // ── version history ────────────────────────────────────────────────────

    public function test_a_stored_version_can_be_read_and_restored_as_the_draft(): void
    {
        $admin = $this->makeAdmin();
        $token = $admin->createToken('funnel-editor', ['funnel:edit'])->plainTextToken;
        $funnel = $this->draftFunnel();
        [$funnel, $first] = $this->service->publish($funnel, $admin);
        [$funnel] = $this->service->saveDraft($funnel->fresh(), $this->sellingPage(1, 'Second headline'), $funnel->fresh()->draft_revision, $admin);
        [$funnel, $second] = $this->service->publish($funnel->fresh(), $admin);

        $read = $this->withToken($token)->getJson("/api/v2/funnel-editor/funnels/{$funnel->id}/versions/{$first->id}");
        $read->assertOk()
            ->assertJsonPath('data.version_number', 1)
            ->assertJsonPath('content.page.sections.0.children.0.props.text', 'Blender Mega Offer');

        $this->forgetAuth();
        $restored = $this->withToken($token)->postJson("/api/v2/funnel-editor/funnels/{$funnel->id}/versions/{$first->id}/rollback");
        $restored->assertOk()->assertJsonPath('data.draft.content.page.sections.0.children.0.props.text', 'Blender Mega Offer');
        // The live page still shows the second version until the merchant publishes.
        $this->assertSame($second->id, (int) $funnel->fresh()->published_version_id);
        // The draft that was replaced is kept as a restore point.
        $this->assertTrue(FunnelVersion::where('funnel_id', $funnel->id)->where('kind', 'restore_point')->exists());
    }

    public function test_another_funnels_version_cannot_be_read(): void
    {
        $admin = $this->makeAdmin();
        $token = $admin->createToken('funnel-editor', ['funnel:edit'])->plainTextToken;
        $one = $this->draftFunnel();
        $two = $this->draftFunnel();
        [, $versionOfTwo] = $this->service->publish($two, $admin);

        $this->withToken($token)->getJson("/api/v2/funnel-editor/funnels/{$one->id}/versions/{$versionOfTwo->id}")->assertStatus(404);
    }
}
