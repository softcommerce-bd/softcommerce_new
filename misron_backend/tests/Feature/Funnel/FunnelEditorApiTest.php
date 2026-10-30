<?php

namespace Tests\Feature\Funnel;

use App\Models\Funnel;
use App\Models\User;
use App\Services\Funnel\EditorSession;
use App\Services\Funnel\FunnelService;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Funnel\FunnelTestCase;

class FunnelEditorApiTest extends FunnelTestCase
{
    private const BASE = '/api/v2/funnel-editor';

    private function editorToken(User $user, ?Funnel $funnel = null): string
    {
        $funnel = $funnel ?: app(FunnelService::class)->create(['title' => 'Token funnel'], $this->makeAdmin());
        $code = app(EditorSession::class)->issueHandoff($user, $funnel);

        $response = $this->postJson(self::BASE . '/session', ['code' => $code]);
        $response->assertOk();
        $this->forgetAuth();

        return $response->json('token');
    }

    private function api(string $token)
    {
        $this->forgetAuth();

        return $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);
    }

    public function test_handoff_code_works_once_and_returns_permissions()
    {
        $admin = $this->makeAdmin();
        $funnel = app(FunnelService::class)->create(['title' => 'Offer'], $admin);
        $code = app(EditorSession::class)->issueHandoff($admin, $funnel);

        $first = $this->postJson(self::BASE . '/session', ['code' => $code]);
        $first->assertOk()
            ->assertJsonPath('funnel_id', $funnel->id)
            ->assertJsonPath('user.permissions.publish_funnel', true);

        $this->forgetAuth();
        $this->postJson(self::BASE . '/session', ['code' => $code])->assertStatus(401)->assertJsonPath('code', 'invalid_handoff');
        $this->postJson(self::BASE . '/session', ['code' => 'made-up'])->assertStatus(401);

        $this->api($first->json('token'))->getJson(self::BASE . '/funnels/' . $funnel->id)
            ->assertOk()
            ->assertJsonPath('data.slug', $funnel->slug)
            ->assertJsonPath('data.draft.revision', 1);
    }

    public function test_requests_without_an_editor_token_are_refused()
    {
        $this->getJson(self::BASE . '/funnels')->assertStatus(401);

        // A storefront login token carries "*" but is not an editor token.
        $staff = $this->makeUser('staff', ['view_funnels', 'edit_funnel']);
        $storefront = $staff->createToken('API Token')->plainTextToken;
        $this->api($storefront)->getJson(self::BASE . '/funnels')->assertStatus(403);

        // Customers never get in, even with a correctly named token.
        $customer = $this->makeUser('customer');
        $forged = $customer->createToken(EditorSession::TOKEN_NAME, ['funnel:edit'])->plainTextToken;
        $this->api($forged)->getJson(self::BASE . '/funnels')->assertStatus(403);
    }

    public function test_expired_editor_tokens_are_rejected_and_deleted()
    {
        $admin = $this->makeAdmin();
        $token = $this->editorToken($admin);

        $model = PersonalAccessToken::findToken($token);
        $model->forceFill(['created_at' => now()->subMinutes(config('funnel.editor_session.token_ttl_minutes') + 1)])->save();

        $this->api($token)->getJson(self::BASE . '/funnels')->assertStatus(401)->assertJsonPath('code', 'session_expired');
        $this->assertNull(PersonalAccessToken::find($model->id));
    }

    /** UC-17: missing permissions hold even when the API is called directly. */
    public function test_staff_without_publish_or_delete_permission_is_blocked()
    {
        $admin = $this->makeAdmin();
        $service = app(FunnelService::class);
        $funnel = $service->create(['title' => 'Offer'], $admin);
        $service->saveDraft($funnel, $this->sellingPage(), 1, $admin);

        $editor = $this->makeUser('staff', ['view_funnels', 'edit_funnel']);
        $token = $this->editorToken($editor, $funnel);

        $this->api($token)->postJson(self::BASE . "/funnels/{$funnel->id}/publish")->assertStatus(403);
        $this->api($token)->deleteJson(self::BASE . "/funnels/{$funnel->id}")->assertStatus(403);
        $this->api($token)->postJson(self::BASE . "/funnels/{$funnel->id}/archive")->assertStatus(403);
        $this->api($token)->postJson(self::BASE . '/funnels', ['title' => 'New'])->assertStatus(403);

        $fresh = Funnel::find($funnel->id);
        $this->assertSame(Funnel::STATUS_DRAFT, $fresh->status);
        $this->assertNull($fresh->published_version_id);

        // Editing is allowed for this role.
        $this->api($token)->putJson(self::BASE . "/funnels/{$funnel->id}/draft", [
            'content' => $this->sellingPage(3),
            'revision' => 2,
        ])->assertOk()->assertJsonPath('revision', 3);
    }

    public function test_rollback_with_publish_needs_publish_permission()
    {
        $admin = $this->makeAdmin();
        $service = app(FunnelService::class);
        $funnel = $service->create(['title' => 'Offer'], $admin);
        [$funnel] = $service->saveDraft($funnel, $this->sellingPage(), 1, $admin);
        [$funnel, $version] = $service->publish($funnel, $admin);

        $editor = $this->makeUser('staff', ['view_funnels', 'edit_funnel']);
        $token = $this->editorToken($editor, $funnel);

        $this->api($token)->postJson(self::BASE . "/funnels/{$funnel->id}/versions/{$version->id}/rollback", ['publish' => true])
            ->assertStatus(403);
        $this->api($token)->postJson(self::BASE . "/funnels/{$funnel->id}/versions/{$version->id}/rollback")
            ->assertOk();
    }

    public function test_full_editor_flow_for_an_admin()
    {
        $admin = $this->makeAdmin();
        $token = $this->editorToken($admin);

        $created = $this->api($token)->postJson(self::BASE . '/funnels', ['title' => 'Eid Blender Offer'])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'eid-blender-offer')
            ->assertJsonPath('data.status', 'draft');
        $id = $created->json('data.id');

        // Empty strings survive the save: an emptied field stays empty.
        $page = $this->sellingPage(21);
        $page['page']['sections'][0]['children'][] = ['id' => 'text_001', 'type' => 'text', 'props' => ['text' => '']];

        $this->api($token)->putJson(self::BASE . "/funnels/{$id}/draft", ['content' => $page, 'revision' => 1])
            ->assertOk()
            ->assertJsonPath('revision', 2);
        $this->assertSame('', Funnel::find($id)->draft_content['page']['sections'][0]['children'][1]['props']['text']);

        $this->api($token)->putJson(self::BASE . "/funnels/{$id}/draft", ['content' => $page, 'revision' => 1])
            ->assertStatus(409)
            ->assertJsonPath('code', 'revision_conflict')
            ->assertJsonPath('revision', 2);

        $this->api($token)->putJson(self::BASE . "/funnels/{$id}/draft", [
            'content' => ['schemaVersion' => 1, 'page' => ['sections' => [['id' => 'h_001', 'type' => 'heading']]]],
            'revision' => 2,
        ])->assertStatus(422)
            ->assertJsonPath('code', 'invalid_page')
            ->assertJsonPath('errors.0.code', 'invalid_nesting')
            ->assertJsonPath('errors.0.path', 'page.sections[0]');

        $this->api($token)->postJson(self::BASE . "/funnels/{$id}/validate")
            ->assertOk()
            ->assertJsonPath('valid', true);

        $this->api($token)->postJson(self::BASE . "/funnels/{$id}/publish", ['label' => 'Launch'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.is_live', true)
            ->assertJsonPath('version.version_number', 1)
            ->assertJsonPath('version.label', 'Launch')
            ->assertJsonPath('version.is_published', true);

        $this->api($token)->putJson(self::BASE . "/funnels/{$id}", ['title' => 'Eid Blender Offer 2026', 'slug' => 'eid-blender-2026'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Eid Blender Offer 2026')
            ->assertJsonPath('data.public_url', 'http://shop.test/funnel/eid-blender-2026');

        $this->api($token)->getJson(self::BASE . '/funnels?status=published&q=eid')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.product_ids', [21]);

        $this->api($token)->getJson(self::BASE . "/funnels/{$id}/versions")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->api($token)->postJson(self::BASE . "/funnels/{$id}/duplicate")
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');

        $this->api($token)->deleteJson(self::BASE . "/funnels/{$id}")->assertOk();
        $this->api($token)->getJson(self::BASE . "/funnels/{$id}")->assertStatus(404)->assertJsonPath('code', 'not_found');
    }

    public function test_registry_is_served_to_the_editor()
    {
        $token = $this->editorToken($this->makeAdmin());

        $response = $this->api($token)->getJson(self::BASE . '/registry')->assertOk();

        $this->assertSame(1, $response->json('schema_version'));
        $this->assertArrayHasKey('checkout_form', $response->json('registry.blocks'));
        $this->assertSame(['root'], $response->json('registry.blocks.section.parents'));
    }
    public function test_opening_the_editor_is_limited_per_address()
    {
        \Illuminate\Support\Facades\RateLimiter::clear(sha1('funnel-editor-session' . '127.0.0.1'));
        config(['funnel.editor_session.exchanges_per_minute' => 3]);

        foreach (range(1, 3) as $attempt) {
            $this->postJson(self::BASE . '/session', ['code' => 'not-a-real-code-' . $attempt])->assertStatus(401);
        }
        $this->postJson(self::BASE . '/session', ['code' => 'not-a-real-code-4'])->assertStatus(429);
    }
}
