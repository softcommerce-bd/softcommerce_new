<?php

namespace Tests\Feature\Funnel;

use App\Models\FunnelSavedSection;
use App\Models\User;
use Tests\Funnel\FunnelTestCase;

/**
 * Saved sections in the editor library: save, list and search, rename,
 * duplicate, delete, with the page validator applied and permissions enforced.
 */
class SavedSectionsTest extends FunnelTestCase
{
    private const BASE = '/api/v2/funnel-editor/saved-sections';

    private function api(User $user)
    {
        $this->forgetAuth();
        $token = $user->createToken('funnel-editor', [config('funnel.editor_session.token_ability')])->plainTextToken;

        return $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);
    }

    private function heroSection(string $text = 'নতুন অফার'): array
    {
        return ['id' => 'hero_001', 'type' => 'section', 'props' => [], 'children' => [
            ['id' => 'heading_001', 'type' => 'heading', 'props' => ['text' => $text, 'level' => 'h2']],
            ['id' => 'text_001', 'type' => 'rich_text', 'props' => ['html' => '<p>Hello <script>alert(1)</script><a href="javascript:alert(1)">x</a></p>']],
        ]];
    }

    public function test_a_section_is_saved_cleaned_listed_searched_renamed_duplicated_and_deleted(): void
    {
        $admin = $this->makeAdmin();
        $before = FunnelSavedSection::count();

        $saved = $this->api($admin)->postJson(self::BASE, ['name' => '  Eid hero  ', 'node' => $this->heroSection()])
            ->assertStatus(201)->assertJsonPath('data.name', 'Eid hero')->assertJsonPath('data.type', 'section')->json('data');
        // Stored as the validator cleaned it: no script, no javascript: link.
        $html = json_encode($saved['node']);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('নতুন অফার', json_encode($saved['node'], JSON_UNESCAPED_UNICODE));

        $this->api($admin)->postJson(self::BASE, ['name' => 'Footer note', 'node' => $this->heroSection('Footer')])->assertStatus(201);
        $this->api($admin)->getJson(self::BASE)->assertOk()->assertJsonCount($before + 2, 'data');
        $this->api($admin)->getJson(self::BASE . '?search=eid')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $saved['id']);
        $this->api($admin)->getJson(self::BASE . '?search=%25')->assertOk()->assertJsonCount(0, 'data');

        $this->api($admin)->putJson(self::BASE . "/{$saved['id']}", ['name' => 'Eid hero v2'])->assertOk()->assertJsonPath('data.name', 'Eid hero v2');
        $this->api($admin)->putJson(self::BASE . "/{$saved['id']}", ['name' => '   '])->assertStatus(422)->assertJsonPath('code', 'invalid_name');

        $copy = $this->api($admin)->postJson(self::BASE . "/{$saved['id']}/duplicate")->assertStatus(201)->json('data');
        $this->assertSame('Eid hero v2 (copy)', $copy['name']);
        $this->assertEquals($saved['node'], $copy['node']);

        $this->api($admin)->deleteJson(self::BASE . "/{$copy['id']}")->assertOk();
        $this->assertSoftDeleted('funnel_saved_sections', ['id' => $copy['id']]);
        $this->api($admin)->deleteJson(self::BASE . "/{$copy['id']}")->assertStatus(404);
    }

    public function test_only_valid_top_level_blocks_can_be_saved(): void
    {
        $admin = $this->makeAdmin();

        $this->api($admin)->postJson(self::BASE, ['name' => 'Nothing'])->assertStatus(422)->assertJsonPath('code', 'invalid_section');
        $this->api($admin)->postJson(self::BASE, ['name' => 'Bad', 'node' => ['id' => 'x_001', 'type' => 'no_such_block']])->assertStatus(422)->assertJsonPath('code', 'invalid_section');
        // A heading cannot stand at the top of a page, so it cannot be saved as a section.
        $this->api($admin)->postJson(self::BASE, ['name' => 'Heading', 'node' => ['id' => 'heading_009', 'type' => 'heading', 'props' => ['text' => 'Hi', 'level' => 'h2']]])
            ->assertStatus(422);
        $this->api($admin)->postJson(self::BASE, ['name' => '', 'node' => $this->heroSection()])->assertStatus(422)->assertJsonPath('code', 'invalid_name');
    }

    public function test_saved_sections_need_the_edit_permission(): void
    {
        $viewer = $this->makeUser('staff', ['view_funnels']);
        $this->api($viewer)->getJson(self::BASE)->assertStatus(403);
        $this->api($viewer)->postJson(self::BASE, ['name' => 'x', 'node' => $this->heroSection()])->assertStatus(403);

        $editor = $this->makeUser('staff', ['view_funnels', 'edit_funnel']);
        $this->api($editor)->postJson(self::BASE, ['name' => 'Mine', 'node' => $this->heroSection()])->assertStatus(201);

        // A storefront login token is not an editor token.
        $customer = $this->makeUser('customer');
        $this->forgetAuth();
        $token = $customer->createToken('login')->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'])->getJson(self::BASE)->assertStatus(403);
    }
}
