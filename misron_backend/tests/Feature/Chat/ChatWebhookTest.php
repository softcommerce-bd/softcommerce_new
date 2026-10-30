<?php

namespace Tests\Feature\Chat;

use App\Jobs\Chat\ProcessChatWebhookEvent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatWebhookEvent;
use App\Services\Chat\GatewaySignature;
use Illuminate\Support\Facades\Bus;

class ChatWebhookTest extends ChatTestCase
{
    private function hook(string $platform, array $payload, ?string $sig = null)
    {
        $raw = json_encode($payload);

        return $this->call('POST', "/api/chat/webhook/$platform", [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $sig ?? $this->sign($raw), 'CONTENT_TYPE' => 'application/json'], $raw);
    }

    private function gw(array $body, array $o = []): \Illuminate\Testing\TestResponse
    {
        $raw = json_encode($body);
        $ts = $o['ts'] ?? time();
        $sig = $o['sig'] ?? GatewaySignature::sign($o['secret'] ?? 'secret-a', $o['id'] ?? 'inst_a', $ts, $raw);

        return $this->call('POST', '/api/chat/gateway/ingest', [], [], [], ['HTTP_X_SOFTTECH_INSTALLATION' => $o['id'] ?? 'inst_a', 'HTTP_X_SOFTTECH_TIMESTAMP' => $ts, 'HTTP_X_SOFTTECH_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $raw);
    }

    public function test_meta_handshake_only_in_direct_mode_with_the_right_token()
    {
        $this->get('/api/chat/webhook/facebook?hub_mode=subscribe&hub_verify_token=vtoken&hub_challenge=123')->assertOk()->assertSee('123');
        $this->get('/api/chat/webhook/facebook?hub_mode=subscribe&hub_verify_token=bad&hub_challenge=123')->assertStatus(403);
        config(['unified_chat.mode' => 'gateway']);
        $this->get('/api/chat/webhook/facebook?hub_mode=subscribe&hub_verify_token=vtoken&hub_challenge=123')->assertStatus(404);
    }

    public function test_direct_webhook_rejects_bad_or_missing_signature()
    {
        Bus::fake();
        $p = $this->messengerPayload('PAGE1', 'mid.1');
        $this->hook('facebook', $p, 'sha256=deadbeef')->assertStatus(401);
        $this->hook('facebook', $p, '')->assertStatus(401);
        $this->assertSame(0, ChatWebhookEvent::count());
        Bus::assertNothingDispatched();
    }

    public function test_direct_webhook_is_disabled_in_gateway_mode()
    {
        config(['unified_chat.mode' => 'gateway']);
        $this->hook('facebook', $this->messengerPayload('P', 'm'))->assertStatus(404);
    }

    public function test_valid_event_is_stored_then_processed_into_a_conversation()
    {
        $a = $this->account('facebook', 'PAGE1');
        $this->hook('facebook', $this->messengerPayload('PAGE1', 'mid.1', 'দাম কত?', 'PSID9'))->assertOk();
        $c = ChatConversation::first();
        $this->assertSame('PSID9', $c->external_id);
        $this->assertSame(1, $c->unread_count);
        $this->assertSame('দাম কত?', $c->last_message_preview);
        $m = ChatMessage::first();
        $this->assertSame(['in', 'received', 'mid.1'], [$m->direction, $m->status, $m->external_id]);
        $this->assertNotNull($a->fresh()->last_webhook_at);
    }

    public function test_duplicate_delivery_creates_one_message_and_one_event()
    {
        $this->account('facebook', 'PAGE1');
        $p = $this->messengerPayload('PAGE1', 'mid.dup');
        $this->hook('facebook', $p)->assertOk();
        $this->hook('facebook', $p)->assertOk();
        // Same message re-sent inside a differently shaped envelope: still one message.
        $p2 = $p;
        $p2['entry'][0]['time'] = 999;
        $this->hook('facebook', $p2)->assertOk();
        $this->assertSame(1, ChatMessage::count());
        $this->assertSame(1, ChatConversation::first()->unread_count);
    }

    public function test_event_for_an_account_this_installation_does_not_own_is_dropped()
    {
        $this->account('facebook', 'OURS');
        $this->hook('facebook', $this->messengerPayload('SOMEONE_ELSES_PAGE', 'mid.x'))->assertOk();
        $this->assertSame(0, ChatMessage::count());
        $this->assertSame(0, ChatConversation::count());
    }

    public function test_disabled_account_and_hidden_inbox_do_not_store()
    {
        $a = $this->account('facebook', 'PAGE1');
        $a->update(['inbox_enabled' => false]);
        $this->hook('facebook', $this->messengerPayload('PAGE1', 'mid.1'))->assertOk();
        $this->assertSame(0, ChatMessage::count());
        $a->update(['inbox_enabled' => true, 'status' => 'disabled']);
        $this->hook('facebook', $this->messengerPayload('PAGE1', 'mid.2'))->assertOk();
        $this->assertSame(0, ChatMessage::count());
    }

    public function test_out_of_order_events_never_move_the_list_backwards_and_resolved_chats_reopen()
    {
        $this->account('facebook', 'PAGE1');
        $new = $this->messengerPayload('PAGE1', 'mid.new', 'second');
        $new['entry'][0]['messaging'][0]['timestamp'] = 1700000100000;
        $old = $this->messengerPayload('PAGE1', 'mid.old', 'first');
        $old['entry'][0]['messaging'][0]['timestamp'] = 1700000000000;
        $this->hook('facebook', $new);
        ChatConversation::first()->update(['status' => 'resolved']);
        $this->hook('facebook', $old);
        $c = ChatConversation::first();
        $this->assertSame('second', $c->last_message_preview, 'older message arriving late must not become the preview');
        $this->assertSame('open', $c->status);
        $this->assertSame(2, ChatMessage::count());
    }

    public function test_echo_of_our_own_send_does_not_duplicate_and_native_page_replies_are_recorded()
    {
        $a = $this->account('facebook', 'PAGE1');
        $c = $this->conversation($a, ['external_id' => 'PSID1']);
        ChatMessage::create(['chat_conversation_id' => $c->id, 'chat_account_id' => $a->id, 'direction' => 'out', 'external_id' => 'mid.ours', 'status' => 'sent', 'body' => 'hi']);
        $this->hook('facebook', $this->messengerPayload('PAGE1', 'mid.ours', 'hi', 'PSID1', true));
        $this->assertSame(1, ChatMessage::count(), 'our own echo is deduped by message id');
        $this->hook('facebook', $this->messengerPayload('PAGE1', 'mid.native', 'sent from phone', 'PSID1', true));
        $this->assertSame('out', ChatMessage::where('external_id', 'mid.native')->value('direction'));
        $this->assertSame(0, $c->fresh()->unread_count, 'outgoing echo is not unread');
    }

    public function test_status_updates_only_move_forward_and_early_statuses_are_applied_later()
    {
        $a = $this->account('whatsapp', '1055');
        $c = $this->conversation($a, ['external_id' => '8801700000000']);
        $mkStatus = fn (string $status, string $id = 'wamid.OUT') => ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'W', 'changes' => [['field' => 'messages', 'value' => ['metadata' => ['phone_number_id' => '1055'], 'statuses' => [['id' => $id, 'status' => $status, 'timestamp' => (string) time()]]]]]]]];
        $m = ChatMessage::create(['chat_conversation_id' => $c->id, 'chat_account_id' => $a->id, 'direction' => 'out', 'external_id' => 'wamid.OUT', 'status' => 'sent']);
        $this->hook('whatsapp', $mkStatus('read'));
        $this->hook('whatsapp', $mkStatus('delivered')); // arrives late
        $this->assertSame('read', $m->fresh()->status);

        // Status for a message whose id we have not recorded yet (webhook beat the send response).
        $this->hook('whatsapp', $mkStatus('delivered', 'wamid.EARLY'));
        $m2 = ChatMessage::create(['chat_conversation_id' => $c->id, 'chat_account_id' => $a->id, 'direction' => 'out', 'external_id' => 'wamid.EARLY', 'status' => 'sent']);
        app(\App\Services\Chat\InboundProcessor::class)->applyEarly($m2);
        $this->assertSame('delivered', $m2->fresh()->status);
    }

    public function test_whatsapp_payload_normalizes_name_phone_and_media()
    {
        $this->account('whatsapp', '1055');
        $p = ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'W', 'changes' => [['field' => 'messages', 'value' => [
            'metadata' => ['phone_number_id' => '1055'], 'contacts' => [['profile' => ['name' => 'Karim'], 'wa_id' => '8801711111111']],
            'messages' => [['from' => '8801711111111', 'id' => 'wamid.1', 'timestamp' => '1700000000', 'type' => 'image', 'image' => ['id' => 'MEDIA1', 'mime_type' => 'image/jpeg', 'caption' => 'এটা চাই']]],
        ]]]]]];
        $this->hook('whatsapp', $p)->assertOk();
        $c = ChatConversation::first();
        $this->assertSame(['Karim', '8801711111111'], [$c->customer_name, $c->customer_phone]);
        $m = ChatMessage::first();
        $this->assertSame('এটা চাই', $m->body);
        $this->assertSame('MEDIA1', $m->attachments[0]['media_id']);
    }

    public function test_instagram_events_use_the_instagram_account_mapping()
    {
        $this->account('instagram', 'IG1');
        $p = $this->messengerPayload('IG1', 'ig.mid.1');
        $p['object'] = 'instagram';
        $this->hook('instagram', $p)->assertOk();
        $this->assertSame('instagram', ChatConversation::first()->platform);
        // A page-shaped payload posted to the instagram endpoint is ignored.
        $this->hook('instagram', $this->messengerPayload('IG1', 'ig.mid.2'))->assertOk();
        $this->assertSame(1, ChatMessage::count());
    }

    // ── gateway delivery ──

    public function test_gateway_delivery_requires_valid_installation_signature()
    {
        $this->account('facebook', 'PAGE1');
        $body = ['event_id' => 'ev_1', 'platform' => 'facebook', 'payload' => $this->messengerPayload('PAGE1', 'mid.g1')];
        $this->gw($body, ['sig' => 'bad'])->assertStatus(401);
        $this->gw($body, ['secret' => 'other-installation-secret'])->assertStatus(401);
        $this->gw($body, ['id' => 'inst_b', 'secret' => 'secret-a'])->assertStatus(401);
        $this->gw($body, ['ts' => time() - 3600])->assertStatus(401);
        $this->assertSame(0, ChatWebhookEvent::count());
        $this->gw($body)->assertOk()->assertJsonPath('duplicate', false);
        $this->assertSame(1, ChatMessage::count());
    }

    public function test_gateway_redelivery_is_idempotent_and_acknowledged()
    {
        $this->account('facebook', 'PAGE1');
        $body = ['event_id' => 'ev_same', 'platform' => 'facebook', 'payload' => $this->messengerPayload('PAGE1', 'mid.g2')];
        $this->gw($body)->assertOk();
        $this->gw($body)->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(1, ChatWebhookEvent::count());
        $this->assertSame(1, ChatMessage::count());
    }

    public function test_gateway_cannot_deliver_another_installations_account()
    {
        $this->account('facebook', 'OUR_PAGE');
        $body = ['event_id' => 'ev_x', 'platform' => 'facebook', 'payload' => $this->messengerPayload('CUSTOMER_B_PAGE', 'mid.leak', 'private to B')];
        $this->gw($body)->assertOk();
        $this->assertSame(0, ChatMessage::count(), 'strict account mapping: foreign account events never land in this inbox');
        $this->assertSame(0, ChatConversation::count());
    }

    public function test_failed_processing_is_kept_for_retry_and_recovered_by_maintenance()
    {
        $a = $this->account('facebook', 'PAGE1');
        Bus::fake();
        $this->gw(['event_id' => 'ev_r', 'platform' => 'facebook', 'payload' => $this->messengerPayload('PAGE1', 'mid.r')])->assertOk();
        Bus::assertDispatched(ProcessChatWebhookEvent::class);
        $e = ChatWebhookEvent::first();
        $this->assertSame('pending', $e->status);
        $e->forceFill(['updated_at' => now()->subMinutes(10)])->saveQuietly();
        $this->artisan('chat:maintain')->assertExitCode(0);
        Bus::assertDispatchedTimes(ProcessChatWebhookEvent::class, 2);
    }

    public function test_invalid_gateway_body_is_rejected_after_signature_check()
    {
        $this->gw(['event_id' => 'e', 'platform' => 'myspace', 'payload' => []])->assertStatus(422);
        $this->gw(['platform' => 'facebook', 'payload' => []])->assertStatus(422);
    }
}
