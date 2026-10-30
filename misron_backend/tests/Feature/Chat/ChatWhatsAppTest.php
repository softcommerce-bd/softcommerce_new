<?php

namespace Tests\Feature\Chat;

use App\Jobs\Chat\SendChatMessage;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

class ChatWhatsAppTest extends ChatTestCase
{
    private const API = '/admin/unified-chat/api/';

    private function wa(array $staffPerms = ['view_unified_chat', 'view_all_chats', 'reply_unified_chat'], array $conv = [])
    {
        $u = $this->staff($staffPerms);
        $a = $this->account('whatsapp', '105500123', [$u]);
        $a->update(['meta' => ['waba_id' => '900100123']]);
        $c = $this->conversation($a, $conv + ['external_id' => '8801700000000', 'last_inbound_at' => now()->subHours(30)]);

        return [$u, $a, $c];
    }

    private function templatesResponse(): array
    {
        return ['data' => [
            ['name' => 'order_update', 'language' => 'bn', 'status' => 'APPROVED', 'category' => 'UTILITY', 'components' => [['type' => 'BODY', 'text' => 'আপনার অর্ডার {{1}} এখন {{2}}']]],
            ['name' => 'promo_sale', 'language' => 'en', 'status' => 'APPROVED', 'category' => 'MARKETING', 'components' => [['type' => 'BODY', 'text' => 'Sale!']]],
            ['name' => 'pending_one', 'language' => 'en', 'status' => 'PENDING', 'category' => 'UTILITY', 'components' => [['type' => 'BODY', 'text' => 'x']]],
            ['name' => 'with_image', 'language' => 'en', 'status' => 'APPROVED', 'category' => 'UTILITY', 'components' => [['type' => 'HEADER', 'format' => 'IMAGE'], ['type' => 'BODY', 'text' => 'Hi']]],
        ]];
    }

    public function test_only_approved_non_marketing_supported_templates_are_offered()
    {
        Http::fake(['*/900100123/message_templates*' => Http::response($this->templatesResponse())]);
        [$u, , $c] = $this->wa();
        $r = $this->actingAs($u)->getJson(self::API . "conversations/{$c->id}/templates")->assertOk();
        $names = array_column($r->json('data'), 'name');
        $this->assertContains('order_update', $names);
        $this->assertNotContains('promo_sale', $names, 'marketing templates are hidden: this product has no marketing sending');
        $this->assertNotContains('pending_one', $names);
        $this->assertFalse(collect($r->json('data'))->firstWhere('name', 'with_image')['supported']);
        $this->assertStringContainsString('charged', $r->json('billing_note'));
        config(['unified_chat.whatsapp.allow_marketing_templates' => true]);
        $this->assertContains('promo_sale', array_column($this->getJson(self::API . "conversations/{$c->id}/templates")->json('data'), 'name'));
    }

    public function test_template_outside_the_window_is_sent_as_a_template_and_free_text_still_is_not()
    {
        Http::fake(['*/message_templates*' => Http::response($this->templatesResponse()), '*/105500123/messages' => Http::response(['messages' => [['id' => 'wamid.T1']]])]);
        [$u, , $c] = $this->wa();
        $this->actingAs($u)->postJson(self::API . "conversations/{$c->id}/messages", ['body' => 'hi', 'client_ref' => 'free-text-1'])->assertStatus(422)->assertJsonPath('code', 'window_closed');

        $r = $this->postJson(self::API . "conversations/{$c->id}/template-messages", ['name' => 'order_update', 'language' => 'bn', 'params' => ['#1001', 'পাঠানো হয়েছে'], 'client_ref' => 'tpl-ref-1'])->assertStatus(201);
        $m = ChatMessage::find($r->json('id'));
        $this->assertSame('আপনার অর্ডার #1001 এখন পাঠানো হয়েছে', $m->body, 'the stored text is the rendered template');
        $this->assertSame('wamid.T1', $m->external_id);
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/105500123/messages') && $req['type'] === 'template' && $req['template']['name'] === 'order_update'
            && $req['template']['language']['code'] === 'bn' && $req['template']['components'][0]['parameters'][1]['text'] === 'পাঠানো হয়েছে' && $req['to'] === '8801700000000');
        $this->assertDatabaseHas('chat_audit_logs', ['action' => 'template.sent', 'subject_id' => $c->id]);
        $this->assertSame('template', $m->type);
        $this->assertSame([], $r->json('attachments'), 'the template payload is never exposed as an attachment');
    }

    public function test_template_validation_blocks_wrong_param_count_unapproved_and_unsupported()
    {
        Http::fake(['*/message_templates*' => Http::response($this->templatesResponse())]);
        Bus::fake();
        [$u, , $c] = $this->wa();
        $post = fn ($body) => $this->actingAs($u)->postJson(self::API . "conversations/{$c->id}/template-messages", $body + ['client_ref' => 'r' . uniqid()]);
        $post(['name' => 'order_update', 'language' => 'bn', 'params' => ['only-one']])->assertStatus(422)->assertJsonFragment(['message' => 'This template needs exactly 2 value(s).']);
        $post(['name' => 'pending_one', 'language' => 'en'])->assertStatus(422);
        $post(['name' => 'with_image', 'language' => 'en'])->assertStatus(422);
        $post(['name' => 'promo_sale', 'language' => 'en'])->assertStatus(422);
        $post(['name' => 'does_not_exist', 'language' => 'en'])->assertStatus(422);
        Bus::assertNothingDispatched();
    }

    public function test_template_endpoints_follow_account_access_and_reply_permission_and_are_whatsapp_only()
    {
        Http::fake(['*' => Http::response($this->templatesResponse())]);
        [$viewer, , $c] = $this->wa(['view_unified_chat', 'view_all_chats']);
        $this->actingAs($viewer)->getJson(self::API . "conversations/{$c->id}/templates")->assertStatus(404);
        $this->actingAs($viewer)->postJson(self::API . "conversations/{$c->id}/template-messages", ['name' => 'order_update', 'language' => 'bn', 'params' => ['a', 'b'], 'client_ref' => 'abcdefgh'])->assertStatus(404);
        $outsider = $this->staff(['view_unified_chat', 'view_all_chats', 'reply_unified_chat']);
        $this->actingAs($outsider)->getJson(self::API . "conversations/{$c->id}/templates")->assertStatus(404);
        $fb = $this->account('facebook', null, [$outsider]);
        $fbc = $this->conversation($fb);
        $this->actingAs($outsider)->getJson(self::API . "conversations/{$fbc->id}/templates")->assertStatus(404);
    }

    public function test_a_template_cannot_start_a_conversation_with_someone_who_never_wrote()
    {
        Http::fake(['*' => Http::response($this->templatesResponse())]);
        [$u, , $c] = $this->wa(['view_unified_chat', 'view_all_chats', 'reply_unified_chat'], ['last_inbound_at' => null]);
        $this->actingAs($u)->postJson(self::API . "conversations/{$c->id}/template-messages", ['name' => 'order_update', 'language' => 'bn', 'params' => ['a', 'b'], 'client_ref' => 'abcdefgh'])->assertStatus(422);
    }

    public function test_template_double_submit_is_idempotent()
    {
        Bus::fake();
        Http::fake(['*/message_templates*' => Http::response($this->templatesResponse())]);
        [$u, , $c] = $this->wa();
        $b = ['name' => 'order_update', 'language' => 'bn', 'params' => ['1', '2'], 'client_ref' => 'same-ref-1'];
        $a = $this->actingAs($u)->postJson(self::API . "conversations/{$c->id}/template-messages", $b)->assertStatus(201);
        $z = $this->postJson(self::API . "conversations/{$c->id}/template-messages", $b)->assertOk();
        $this->assertSame($a->json('id'), $z->json('id'));
        Bus::assertDispatchedTimes(SendChatMessage::class, 1);
    }

    public function test_username_only_contacts_use_their_user_id_and_cannot_be_messaged_by_phone()
    {
        $a = $this->account('whatsapp', '105500123');
        $payload = ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'W', 'changes' => [['field' => 'messages', 'value' => [
            'metadata' => ['phone_number_id' => '105500123'], 'contacts' => [['profile' => ['name' => 'Anon', 'username' => '@anon'], 'user_id' => 'US.1349120865']],
            'messages' => [['user_id' => 'US.1349120865', 'id' => 'wamid.U1', 'timestamp' => '1700000000', 'type' => 'text', 'text' => ['body' => 'hello']]],
        ]]]]]];
        $raw = json_encode($payload);
        $this->call('POST', '/api/chat/webhook/whatsapp', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $this->sign($raw), 'CONTENT_TYPE' => 'application/json'], $raw)->assertOk();
        $c = ChatConversation::first();
        $this->assertSame(['US.1349120865', null], [$c->external_id, $c->customer_phone], 'no phone number is invented');
        $m = ChatMessage::create(['chat_conversation_id' => $c->id, 'chat_account_id' => $a->id, 'direction' => 'out', 'body' => 'x', 'status' => 'queued']);
        $c->update(['last_inbound_at' => now()]); // the fixture's provider timestamp is old; put the chat inside the window
        Http::fake();
        (new SendChatMessage($m->id))->handle(app(\App\Services\Chat\ProviderRegistry::class), app(\App\Services\Chat\InboundProcessor::class));
        Http::assertNothingSent();
        $this->assertStringContainsString('username', $m->fresh()->error);
    }

    public function test_coexistence_echo_from_the_business_app_is_stored_as_outgoing_and_not_unread()
    {
        $a = $this->account('whatsapp', '105500123');
        $c = $this->conversation($a, ['external_id' => '8801700000000']);
        $payload = ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'W', 'changes' => [['field' => 'smb_message_echoes', 'value' => [
            'metadata' => ['phone_number_id' => '105500123'], 'message_echoes' => [['from' => '8801999999999', 'to' => '8801700000000', 'id' => 'wamid.ECHO1', 'timestamp' => '1700000000', 'type' => 'text', 'text' => ['body' => 'typed on the phone']]],
        ]]]]]];
        // The echo field is delivered under value.* of a "messages"-style change; the processor reads the value regardless of field label.
        $payload['entry'][0]['changes'][0]['field'] = 'messages';
        $raw = json_encode($payload);
        $this->call('POST', '/api/chat/webhook/whatsapp', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $this->sign($raw), 'CONTENT_TYPE' => 'application/json'], $raw)->assertOk();
        $m = ChatMessage::where('external_id', 'wamid.ECHO1')->first();
        $this->assertSame(['out', 'typed on the phone'], [$m->direction, $m->body]);
        $this->assertSame(0, $c->fresh()->unread_count);
    }

    public function test_health_verify_reports_each_check_without_leaking_the_token()
    {
        Http::fake([
            '*/105500123?*' => Http::response(['id' => '105500123', 'display_phone_number' => '+880 1700-000000', 'quality_rating' => 'GREEN']),
            '*/900100123/subscribed_apps' => Http::response(['data' => []]),
        ]);
        $mgr = $this->staff(['view_unified_chat', 'manage_chat_accounts']);
        $a = $this->account('whatsapp', '105500123', [$mgr]);
        $a->update(['meta' => ['waba_id' => '900100123']]);
        $r = $this->actingAs($mgr)->postJson(self::API . "accounts/{$a->id}/verify")->assertOk();
        $byName = collect($r->json('checks'))->keyBy('name');
        $this->assertTrue($byName['Access token and phone number']['ok']);
        $this->assertFalse($byName['Webhook subscription']['ok'], 'an empty subscription list is reported, not assumed fine');
        $this->assertStringNotContainsString('tok-whatsapp', $r->getContent());
        $this->assertNotNull($a->fresh()->last_error);
    }

    public function test_health_verify_marks_an_expired_token_for_reconnect_and_is_permission_gated()
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 190, 'message' => 'Error validating access token']], 400)]);
        $viewer = $this->staff(['view_unified_chat', 'view_all_chats']);
        $mgr = $this->staff(['view_unified_chat', 'manage_chat_accounts']);
        $a = $this->account('facebook', 'PAGE1', [$viewer, $mgr]);
        $this->actingAs($viewer)->postJson(self::API . "accounts/{$a->id}/verify")->assertStatus(403);
        $this->actingAs($mgr)->postJson(self::API . "accounts/{$a->id}/verify")->assertOk()->assertJsonPath('checks.0.ok', false);
        $this->assertSame('expired', $a->fresh()->status);
    }

    public function test_messenger_health_checks_the_page_message_subscription()
    {
        Http::fake(['*/PAGE1?*' => Http::response(['id' => 'PAGE1', 'name' => 'Shop']), '*/PAGE1/subscribed_apps' => Http::response(['data' => [['subscribed_fields' => ['messages', 'message_echoes']]]])]);
        $mgr = $this->staff(['view_unified_chat', 'manage_chat_accounts']);
        $a = $this->account('facebook', 'PAGE1', [$mgr]);
        $r = $this->actingAs($mgr)->postJson(self::API . "accounts/{$a->id}/verify")->assertOk();
        $this->assertSame([true, true], array_column($r->json('checks'), 'ok'));
        $this->assertNull($a->fresh()->last_error);
    }

    public function test_tiktok_health_never_claims_success()
    {
        $mgr = $this->staff(['view_unified_chat', 'manage_chat_accounts']);
        $a = $this->account('tiktok', 'TT1', [$mgr]);
        Http::fake();
        $r = $this->actingAs($mgr)->postJson(self::API . "accounts/{$a->id}/verify")->assertOk();
        $this->assertFalse($r->json('checks.0.ok'));
        Http::assertNothingSent();
    }

    public function test_manual_whatsapp_connect_records_the_waba_and_never_duplicates_a_number()
    {
        Http::fake(['*/105500123*' => Http::response(['id' => '105500123', 'display_phone_number' => '+880 1700', 'verified_name' => 'Shop BD'])]);
        $mgr = $this->staff(['view_unified_chat', 'manage_chat_accounts']);
        $this->actingAs($mgr)->postJson(self::API . 'accounts/connect/whatsapp-manual', ['phone_number_id' => '105500123', 'access_token' => 'EAAB', 'waba_id' => '900100123'])->assertStatus(201);
        $this->postJson(self::API . 'accounts/connect/whatsapp-manual', ['phone_number_id' => '105500123', 'access_token' => 'EAAC', 'waba_id' => '900100123'])->assertStatus(201);
        $this->assertSame(1, \App\Models\ChatAccount::where('platform', 'whatsapp')->count());
        $a = \App\Models\ChatAccount::first();
        $this->assertSame('900100123', $a->meta['waba_id']);
        $this->assertSame('EAAC', $a->secrets()['access_token'], 'reconnecting replaces the token');
    }

    public function test_embedded_signup_grant_stores_waba_coexistence_and_pin_encrypted()
    {
        $mgr = $this->staff(['view_unified_chat', 'manage_chat_accounts']);
        $ctl = app(\App\Http\Controllers\ChatAccountController::class);
        $a = $ctl->upsert($mgr, ['platform' => 'whatsapp', 'external_id' => '105500123', 'name' => 'Shop BD', 'access_token' => 'BIZTOKEN', 'pin' => '123456',
            'meta' => ['waba_id' => '900100123', 'coexistence' => true, 'junk' => 'dropped']]);
        $this->assertSame(['waba_id' => '900100123', 'coexistence' => true], $a->fresh()->meta);
        $raw = (string) \DB::table('chat_accounts')->value('credentials');
        $this->assertStringNotContainsString('BIZTOKEN', $raw);
        $this->assertStringNotContainsString('123456', $raw);
        $this->assertSame('123456', $a->secrets()['pin']);
        $this->assertStringNotContainsString('123456', json_encode($a->toPublic()));
    }
}
