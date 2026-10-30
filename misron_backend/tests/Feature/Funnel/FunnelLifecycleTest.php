<?php

namespace Tests\Feature\Funnel;

use App\Models\Funnel;
use App\Models\FunnelAuditLog;
use App\Models\FunnelVersion;
use App\Services\Funnel\FunnelException;
use App\Services\Funnel\FunnelService;
use Tests\Funnel\FunnelTestCase;

class FunnelLifecycleTest extends FunnelTestCase
{
    private function service(): FunnelService
    {
        return app(FunnelService::class);
    }

    private function expectFunnelError(string $code, callable $action): FunnelException
    {
        try {
            $action();
        } catch (FunnelException $e) {
            $this->assertSame($code, $e->errorCode(), $e->getMessage());
            return $e;
        }
        $this->fail("Expected FunnelException {$code}.");
    }

    public function test_create_derives_unique_slugs_including_bengali_titles()
    {
        $user = $this->makeAdmin();

        $first = $this->service()->create(['title' => 'Blender Mega Offer'], $user);
        $second = $this->service()->create(['title' => 'Blender Mega Offer'], $user);
        $bengali = $this->service()->create(['title' => 'ব্লেন্ডার মেগা অফার'], $user);

        $this->assertSame('blender-mega-offer', $first->slug);
        $this->assertSame('blender-mega-offer-2', $second->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $bengali->slug);
        $this->assertSame(Funnel::STATUS_DRAFT, $first->status);
        $this->assertSame(1, $first->draft_revision);
        $this->assertSame([], $first->draft_content['page']['sections']);
        $this->assertTrue(FunnelAuditLog::where('funnel_id', $first->id)->where('action', 'created')->exists());
    }

    public function test_explicit_slugs_are_validated()
    {
        $user = $this->makeAdmin();
        $this->service()->create(['title' => 'One', 'slug' => 'eid-offer'], $user);

        $this->expectFunnelError('invalid_slug', function () use ($user) {
            $this->service()->create(['title' => 'Two', 'slug' => 'eid-offer'], $user);
        });
        $this->expectFunnelError('invalid_slug', function () use ($user) {
            $this->service()->create(['title' => 'Three', 'slug' => 'Eid Offer!'], $user);
        });
        $this->expectFunnelError('invalid_slug', function () use ($user) {
            $this->service()->create(['title' => 'Four', 'slug' => 'preview'], $user);
        });
    }

    public function test_draft_saves_are_revisioned_and_detect_conflicts()
    {
        $user = $this->makeAdmin();
        $funnel = $this->service()->create(['title' => 'Offer'], $user);

        [$saved] = $this->service()->saveDraft($funnel, $this->sellingPage(11), 1, $user);
        $this->assertSame(2, $saved->draft_revision);
        $this->assertSame([11], $saved->product_ids);

        // Same content again: nothing to write, revision unchanged.
        [$same] = $this->service()->saveDraft($saved, $this->sellingPage(11), 2, $user);
        $this->assertSame(2, $same->draft_revision);

        // A second tab still holding revision 1 must not overwrite revision 2.
        $e = $this->expectFunnelError('revision_conflict', function () use ($saved, $user) {
            $this->service()->saveDraft($saved, $this->sellingPage(12), 1, $user);
        });
        $this->assertSame(409, $e->status());
        $this->assertSame(2, $e->details()['revision']);
        $this->assertSame([11], Funnel::find($funnel->id)->product_ids);
    }

    public function test_invalid_draft_is_rejected_without_changes()
    {
        $user = $this->makeAdmin();
        $funnel = $this->service()->create(['title' => 'Offer'], $user);

        $e = $this->expectFunnelError('invalid_page', function () use ($funnel, $user) {
            $this->service()->saveDraft($funnel, ['schemaVersion' => 1, 'page' => ['sections' => [
                ['id' => 'x_001', 'type' => 'raw_html', 'props' => ['html' => '<script>']],
            ]]], 1, $user);
        });

        $this->assertSame('unknown_block', $e->details()['errors'][0]['code']);
        $this->assertSame(1, Funnel::find($funnel->id)->draft_revision);
    }

    /** UC-10: customers keep the published version while the draft changes. */
    public function test_published_version_is_isolated_from_later_draft_edits()
    {
        $user = $this->makeAdmin();
        $funnel = $this->service()->create(['title' => 'Offer'], $user);
        [$funnel] = $this->service()->saveDraft($funnel, $this->sellingPage(11, 'Version one'), 1, $user);

        [$funnel, $v1] = $this->service()->publish($funnel, $user);
        $this->assertSame(Funnel::STATUS_PUBLISHED, $funnel->status);
        $this->assertTrue($funnel->isLive());
        $this->assertSame(1, $v1->version_number);
        $this->assertFalse($funnel->fresh()->hasUnpublishedChanges());

        [$funnel] = $this->service()->saveDraft($funnel, $this->sellingPage(11, 'Version two'), $funnel->draft_revision, $user);
        $funnel = $funnel->fresh();
        $this->assertTrue($funnel->hasUnpublishedChanges());
        $this->assertSame('Version one', $funnel->publishedVersion->content['page']['sections'][0]['children'][0]['props']['text']);

        // Publishing the same draft twice does not create an empty version.
        [$funnel, $v2] = $this->service()->publish($funnel, $user);
        [, $again] = $this->service()->publish($funnel, $user);
        $this->assertSame(2, $v2->version_number);
        $this->assertSame($v2->id, $again->id);
        $this->assertSame('Version two', $funnel->fresh()->publishedVersion->content['page']['sections'][0]['children'][0]['props']['text']);
        $this->assertSame(2, FunnelVersion::where('funnel_id', $funnel->id)->count());
    }

    public function test_incomplete_page_cannot_be_published()
    {
        $user = $this->makeAdmin();
        $funnel = $this->service()->create(['title' => 'Offer'], $user);

        $e = $this->expectFunnelError('invalid_page', function () use ($funnel, $user) {
            $this->service()->publish($funnel, $user);
        });

        $this->assertContains('empty_page', array_column($e->details()['errors'], 'code'));
        $funnel = $funnel->fresh();
        $this->assertSame(Funnel::STATUS_DRAFT, $funnel->status);
        $this->assertNull($funnel->published_version_id);
    }

    public function test_state_transitions()
    {
        $user = $this->makeAdmin();
        $funnel = $this->service()->create(['title' => 'Offer'], $user);
        [$funnel] = $this->service()->saveDraft($funnel, $this->sellingPage(), 1, $user);
        [$funnel] = $this->service()->publish($funnel, $user);

        $funnel = $this->service()->unpublish($funnel, $user);
        $this->assertSame(Funnel::STATUS_UNPUBLISHED, $funnel->status);
        $this->assertFalse($funnel->isLive());

        $funnel = $this->service()->archive($funnel, $user);
        $this->assertSame(Funnel::STATUS_ARCHIVED, $funnel->status);

        $this->expectFunnelError('invalid_state', function () use ($funnel, $user) {
            $this->service()->saveDraft($funnel, $this->sellingPage(2), $funnel->draft_revision, $user);
        });
        $this->expectFunnelError('invalid_state', function () use ($funnel, $user) {
            $this->service()->publish($funnel, $user);
        });

        // Restoring never puts a page back online by itself.
        $funnel = $this->service()->restore($funnel, $user);
        $this->assertSame(Funnel::STATUS_UNPUBLISHED, $funnel->status);
        $this->assertFalse($funnel->isLive());

        $this->expectFunnelError('invalid_state', function () use ($funnel, $user) {
            $this->service()->unpublish($funnel, $user);
        });

        $actions = FunnelAuditLog::where('funnel_id', $funnel->id)->orderBy('id')->pluck('action')->all();
        $this->assertSame(['created', 'published', 'unpublished', 'archived', 'restored'], $actions);
    }

    /** UC-09: a duplicate is an independent draft with its own URL. */
    public function test_duplicate_is_independent()
    {
        $user = $this->makeAdmin();
        $funnel = $this->service()->create(['title' => 'Offer', 'slug' => 'blender-offer'], $user);
        [$funnel] = $this->service()->saveDraft($funnel, $this->sellingPage(7), 1, $user);
        [$funnel] = $this->service()->publish($funnel, $user);

        $copy = $this->service()->duplicate($funnel, $user);
        $this->assertSame('blender-offer-copy', $copy->slug);
        $this->assertSame(Funnel::STATUS_DRAFT, $copy->status);
        $this->assertNull($copy->published_version_id);
        $this->assertSame($funnel->draft_content, $copy->draft_content);

        $this->service()->saveDraft($copy, $this->sellingPage(8, 'Other campaign'), 1, $user);
        $this->assertSame([7], $funnel->fresh()->product_ids);
        $this->assertSame([8], $copy->fresh()->product_ids);
    }

    public function test_delete_is_soft_and_releases_the_slug()
    {
        $user = $this->makeAdmin();
        $funnel = $this->service()->create(['title' => 'Offer', 'slug' => 'flash-sale'], $user);
        [$funnel] = $this->service()->saveDraft($funnel, $this->sellingPage(), 1, $user);
        $this->service()->publish($funnel, $user);

        $this->service()->delete($funnel, $user);

        $this->assertNull(Funnel::find($funnel->id));
        $trashed = Funnel::withTrashed()->find($funnel->id);
        $this->assertNotNull($trashed);
        $this->assertSame('flash-sale--deleted-' . $funnel->id, $trashed->slug);
        $this->assertSame(1, FunnelVersion::where('funnel_id', $funnel->id)->count());

        $reuse = $this->service()->create(['title' => 'New', 'slug' => 'flash-sale'], $user);
        $this->assertSame('flash-sale', $reuse->slug);
    }

    public function test_rollback_restores_content_and_keeps_a_restore_point()
    {
        $user = $this->makeAdmin();
        $funnel = $this->service()->create(['title' => 'Offer'], $user);
        [$funnel] = $this->service()->saveDraft($funnel, $this->sellingPage(1, 'First'), 1, $user);
        [$funnel, $v1] = $this->service()->publish($funnel, $user);
        [$funnel] = $this->service()->saveDraft($funnel, $this->sellingPage(1, 'Unsaved idea'), $funnel->draft_revision, $user);

        $funnel = $this->service()->rollback($funnel, $v1, $user);

        $this->assertSame('First', $funnel->draft_content['page']['sections'][0]['children'][0]['props']['text']);
        $point = FunnelVersion::where('funnel_id', $funnel->id)->where('kind', FunnelVersion::KIND_RESTORE_POINT)->first();
        $this->assertNotNull($point);
        $this->assertSame('Unsaved idea', $point->content['page']['sections'][0]['children'][0]['props']['text']);
        // Rolling back the draft does not change what is published.
        $this->assertSame($v1->id, (int) $funnel->published_version_id);

        $other = $this->service()->create(['title' => 'Other'], $user);
        $this->expectFunnelError('version_mismatch', function () use ($other, $v1, $user) {
            $this->service()->rollback($other, $v1, $user);
        });
    }
}
