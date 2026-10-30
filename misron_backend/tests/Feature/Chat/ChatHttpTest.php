<?php

namespace Tests\Feature\Chat;

use App\Jobs\Chat\SendChatMessage;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Bus;

class ChatHttpTest extends ChatTestCase
{
    private const API = '/admin/unified-chat/api/';

    public function test_guest_and_unprivileged_users_are_blocked()
    {
        $this->getJson(self::API . 'conversations')->assertStatus(401);
        $this->actingAs($this->staff([]))->getJson(self::API . 'conversations')->assertStatus(403);
        $this->actingAs($this->staff([], 'customer'))->getJson(self::API . 'conversations')->assertStatus(404);
    }

    public function test_conversation_ids_of_other_accounts_return_404_for_every_endpoint()
    {
        $me = $this->staff(['view_unified_chat', 'view_all_chats', 'reply_unified_chat', 'assign_unified_chat', 'manage_chat_notes', 'create_order_from_chat']);
        $mine = $this->account('facebook', null, [$me]);
        $theirs = $this->account('whatsapp');
        $c = $this->conversation($theirs);
        $this->actingAs($me);
        $this->getJson(self::API . "conversations/{$c->id}")->assertStatus(404);
        $this->getJson(self::API . "conversations/{$c->id}/messages")->assertStatus(404);
        $this->postJson(self::API . "conversations/{$c->id}/messages", ['body' => 'x', 'client_ref' => 'abcdefgh'])->assertStatus(404);
        $this->postJson(self::API . "conversations/{$c->id}/assign", ['user_id' => $me->id])->assertStatus(404);
        $this->postJson(self::API . "conversations/{$c->id}/notes", ['body' => 'x'])->assertStatus(404);
        $this->postJson(self::API . "conversations/{$c->id}/orders", [])->assertStatus(404);
        $this->postJson(self::API . "conversations/{$c->id}/read")->assertStatus(404);
        $m = ChatMessage::create(['chat_conversation_id' => $c->id, 'chat_account_id' => $theirs->id, 'direction' => 'in', 'attachments' => [['type' => 'image', 'url' => 'https://x.fbcdn.net/a.jpg']], 'external_id' => 'm9']);
        $this->get('/admin/unified-chat/attachments/' . $m->id . '/0', ['Accept' => 'application/json'])->assertStatus(404);
        $this->assertSame([], $this->getJson(self::API . 'conversations')->json('data'));
    }

    public function test_list_search_filters_and_cursor_pagination()
    {
        $me = $this->staff(['view_unified_chat', 'view_all_chats']);
        $a = $this->account('facebook', null, [$me]);
        for ($i = 0; $i < 35; $i++) {
            $this->conversation($a, ['customer_name' => $i === 3 ? 'Rahim Uddin' : "Cust $i", 'last_message_at' => now()->subMinutes($i)]);
        }
        $this->actingAs($me);
        $p1 = $this->getJson(self::API . 'conversations')->json();
        $this->assertCount(30, $p1['data']);
        $this->assertTrue($p1['has_more']);
        $last = end($p1['data']);
        $p2 = $this->getJson(self::API . 'conversations?' . http_build_query(['before_at' => $last['cursor'], 'before_id' => $last['id']]))->json();
        $this->assertCount(5, $p2['data']);
        $this->assertEmpty(array_intersect(array_column($p1['data'], 'id'), array_column($p2['data'], 'id')));
        $this->assertSame('Rahim Uddin', $this->getJson(self::API . 'conversations?q=Rahim')->json('data.0.name'));
        $this->assertSame([], $this->getJson(self::API . 'conversations?q=' . urlencode('%'))->json('data'), 'LIKE wildcards are escaped');
    }

    public function test_reply_is_queued_once_per_client_ref_and_needs_reply_permission()
    {
        Bus::fake();
        $viewer = $this->staff(['view_unified_chat', 'view_all_chats']);
        $agent = $this->staff(['view_unified_chat', 'view_all_chats', 'reply_unified_chat']);
        $a = $this->account('facebook', null, [$viewer, $agent]);
        $c = $this->conversation($a);

        $this->actingAs($viewer)->postJson(self::API . "conversations/{$c->id}/messages", ['body' => 'hi', 'client_ref' => 'ref-12345'])->assertStatus(404);
        $this->actingAs($agent);
        $r1 = $this->postJson(self::API . "conversations/{$c->id}/messages", ['body' => 'hi', 'client_ref' => 'ref-12345'])->assertStatus(201);
        $r2 = $this->postJson(self::API . "conversations/{$c->id}/messages", ['body' => 'hi', 'client_ref' => 'ref-12345'])->assertOk();
        $this->assertSame($r1->json('id'), $r2->json('id'));
        $this->assertSame(1, ChatMessage::where('direction', 'out')->count());
        Bus::assertDispatchedTimes(SendChatMessage::class, 1);
    }

    public function test_reply_refused_when_window_closed_or_account_not_connected()
    {
        Bus::fake();
        $u = $this->staff(['view_unified_chat', 'view_all_chats', 'reply_unified_chat']);
        $a = $this->account('whatsapp', null, [$u]);
        $c = $this->conversation($a, ['last_inbound_at' => now()->subHours(30)]);
        $this->actingAs($u)->postJson(self::API . "conversations/{$c->id}/messages", ['body' => 'hi', 'client_ref' => 'ref-aaaaaa'])->assertStatus(422)->assertJsonPath('code', 'window_closed');
        $a->update(['status' => 'expired']);
        $c2 = $this->conversation($a);
        $this->postJson(self::API . "conversations/{$c2->id}/messages", ['body' => 'hi', 'client_ref' => 'ref-bbbbbb'])->assertStatus(422);
        Bus::assertNothingDispatched();
    }

    public function test_assignment_respects_account_access_and_permission()
    {
        $boss = $this->staff(['view_unified_chat', 'view_all_chats', 'assign_unified_chat']);
        $ok = $this->staff(['view_unified_chat', 'view_all_chats']);
        $outsider = $this->staff(['view_unified_chat', 'view_all_chats']);
        $noPerm = $this->staff(['view_unified_chat', 'view_all_chats']);
        $a = $this->account('facebook', null, [$boss, $ok, $noPerm]);
        $c = $this->conversation($a);

        $this->actingAs($boss)->postJson(self::API . "conversations/{$c->id}/assign", ['user_id' => $outsider->id])->assertStatus(422);
        $this->postJson(self::API . "conversations/{$c->id}/assign", ['user_id' => $ok->id])->assertOk();
        $this->assertSame($ok->id, $c->fresh()->assigned_to);
        $this->actingAs($noPerm)->postJson(self::API . "conversations/{$c->id}/assign", ['user_id' => $noPerm->id])->assertStatus(404);
    }

    public function test_removing_staff_from_account_unassigns_their_conversations_and_blocks_access()
    {
        $admin = $this->staff(['view_unified_chat', 'manage_chat_accounts']);
        $agent = $this->staff(['view_unified_chat', 'view_all_chats']);
        $a = $this->account('facebook', null, [$admin, $agent]);
        $c = $this->conversation($a, ['assigned_to' => $agent->id]);
        $this->actingAs($admin)->postJson(self::API . "accounts/{$a->id}", ['staff_ids' => [$admin->id]])->assertOk();
        $this->assertNull($c->fresh()->assigned_to);
        $this->actingAs($agent)->getJson(self::API . "conversations/{$c->id}")->assertStatus(404);
    }

    public function test_notes_need_their_own_permission()
    {
        $a1 = $this->staff(['view_unified_chat', 'view_all_chats', 'manage_chat_notes']);
        $a2 = $this->staff(['view_unified_chat', 'view_all_chats']);
        $acc = $this->account('facebook', null, [$a1, $a2]);
        $c = $this->conversation($acc);
        $this->actingAs($a2)->postJson(self::API . "conversations/{$c->id}/notes", ['body' => 'secret'])->assertStatus(404);
        $this->actingAs($a1)->postJson(self::API . "conversations/{$c->id}/notes", ['body' => 'secret'])->assertStatus(201);
        $this->actingAs($a2)->getJson(self::API . "conversations/{$c->id}")->assertJsonPath('notes', []);
        $this->actingAs($a1)->getJson(self::API . "conversations/{$c->id}")->assertJsonPath('notes.0.body', 'secret');
    }

    public function test_account_management_requires_permission_and_never_exposes_credentials()
    {
        $viewer = $this->staff(['view_unified_chat', 'view_all_chats']);
        $mgr = $this->staff(['view_unified_chat', 'manage_chat_accounts']);
        $a = $this->account('facebook', null, [$viewer, $mgr]);
        $this->actingAs($viewer)->getJson(self::API . 'accounts')->assertStatus(403);
        $this->actingAs($viewer)->deleteJson(self::API . "accounts/{$a->id}")->assertStatus(403);
        $json = $this->actingAs($mgr)->getJson(self::API . 'accounts')->assertOk()->getContent();
        $this->assertStringNotContainsString('tok-facebook', $json);
        $this->assertStringNotContainsString('credentials', $json);
        $boot = $this->actingAs($viewer)->getJson(self::API . 'bootstrap')->getContent();
        $this->assertStringNotContainsString('tok-facebook', $boot);
        $this->assertSame('tok-facebook', $a->fresh()->secrets()['access_token'], 'stored encrypted but decryptable server-side');
        $this->assertStringNotContainsString('tok-facebook', (string) \DB::table('chat_accounts')->value('credentials'), 'credentials are encrypted at rest');
    }

    public function test_disconnect_wipes_credentials_but_keeps_history()
    {
        $mgr = $this->staff(['view_unified_chat', 'manage_chat_accounts']);
        $a = $this->account('facebook', null, [$mgr]);
        $c = $this->conversation($a);
        $this->actingAs($mgr)->deleteJson(self::API . "accounts/{$a->id}")->assertOk();
        $this->assertNull(\DB::table('chat_accounts')->where('id', $a->id)->value('credentials'));
        $this->assertSame('disabled', $a->fresh()->status);
        $this->assertDatabaseHas('chat_conversations', ['id' => $c->id]);
    }

    public function test_connect_state_is_bound_to_user_and_single_use()
    {
        config(['unified_chat.mode' => 'gateway']);
        $mgr = $this->staff(['view_unified_chat', 'manage_chat_accounts']);
        $other = $this->staff(['view_unified_chat', 'manage_chat_accounts']);
        $r = $this->actingAs($mgr)->postJson(self::API . 'accounts/connect/facebook')->assertOk();
        parse_str(parse_url($r->json('redirect'), PHP_URL_QUERY), $q);
        $this->assertSame('inst_a', $q['installation_id']);
        $this->actingAs($other)->get('/admin/unified-chat/connect/callback?state=' . $q['state'] . '&grant=g')->assertRedirect();
        $this->assertNull(\App\Models\ChatAccount::first(), 'a state minted for another admin must not connect anything');
    }

    public function test_tiktok_connect_is_refused_until_approved()
    {
        $mgr = $this->staff(['view_unified_chat', 'manage_chat_accounts']);
        $this->actingAs($mgr)->postJson(self::API . 'accounts/connect/tiktok')->assertStatus(422)->assertJsonFragment(['message' => 'TikTok Business Messaging approval required. Connection is disabled until TikTok grants API access to the developer app.']);
    }

    public function test_analytics_and_export_are_permission_gated_and_scoped()
    {
        $plain = $this->staff(['view_unified_chat', 'view_all_chats']);
        $an = $this->staff(['view_unified_chat', 'view_all_chats', 'view_chat_analytics', 'export_chat_data']);
        $mine = $this->account('facebook', null, [$an, $plain]);
        $theirs = $this->account('instagram');
        $this->conversation($mine);
        $this->conversation($mine);
        $this->conversation($theirs);
        $this->actingAs($plain)->getJson(self::API . 'analytics')->assertStatus(403);
        $this->actingAs($plain)->get('/admin/unified-chat/export', ['Accept' => 'application/json'])->assertStatus(403);
        $this->actingAs($an)->getJson(self::API . 'analytics?range=30d')->assertOk()->assertJsonPath('totals.conversations', 2);
        $csv = $this->actingAs($an)->get('/admin/unified-chat/export', ['Accept' => 'application/json'])->assertOk()->streamedContent();
        $this->assertSame(3, substr_count(trim($csv), "\n") + 1, 'header + 2 rows, not the other account');
    }

    public function test_updates_poll_only_returns_authorized_ids()
    {
        $me = $this->staff(['view_unified_chat', 'view_all_chats']);
        $mine = $this->account('facebook', null, [$me]);
        $theirs = $this->account('instagram');
        $c1 = $this->conversation($mine);
        $this->conversation($theirs);
        $r = $this->actingAs($me)->getJson(self::API . 'updates?since=' . urlencode(now()->subHour()->toIso8601String()))->assertOk();
        $this->assertSame([$c1->id], $r->json('conversation_ids'));
    }

    public function test_super_admin_reaches_everything_through_the_existing_gate()
    {
        $su = $this->superAdmin();
        $a = $this->account('instagram');
        $c = $this->conversation($a);
        $this->actingAs($su);
        $boot = $this->getJson(self::API . 'bootstrap')->assertOk();
        $this->assertTrue($boot->json('permissions.manage_chat_accounts'));
        $this->assertCount(1, $boot->json('accounts'));
        $this->getJson(self::API . "conversations/{$c->id}")->assertOk();
        $this->getJson(self::API . 'accounts')->assertOk();
        $this->getJson(self::API . 'analytics')->assertOk();
    }
}
