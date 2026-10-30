<?php

namespace Tests\Feature\Chat;

use App\Jobs\Chat\SendChatMessage;
use App\Models\ChatMessage;
use App\Services\Chat\Automation;
use App\Services\Chat\SafeFetch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

class ChatSendTest extends ChatTestCase
{
    private function queued(string $platform = 'facebook', array $conv = [])
    {
        $a = $this->account($platform, $platform === 'whatsapp' ? '1055' : 'PAGE1');
        $c = $this->conversation($a, $conv + ['external_id' => 'RCPT']);
        $m = ChatMessage::create(['chat_conversation_id' => $c->id, 'chat_account_id' => $a->id, 'direction' => 'out', 'body' => 'hello', 'status' => 'queued', 'sent_by' => 1]);

        return [$a, $c, $m];
    }

    private function send(ChatMessage $m): void
    {
        (new SendChatMessage($m->id))->handle(app(\App\Services\Chat\ProviderRegistry::class), app(\App\Services\Chat\InboundProcessor::class));
    }

    public function test_messenger_send_posts_to_graph_with_token_and_records_the_id()
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['recipient_id' => 'RCPT', 'message_id' => 'mid.sent1'])]);
        [$a, $c, $m] = $this->queued();
        $this->send($m);
        $m->refresh();
        $this->assertSame(['sent', 'mid.sent1'], [$m->status, $m->external_id]);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/PAGE1/messages') && $r['recipient']['id'] === 'RCPT' && $r['message']['text'] === 'hello' && $r->hasHeader('Authorization', 'Bearer tok-facebook'));
        $this->assertNotNull($a->fresh()->last_api_ok_at);
    }

    public function test_instagram_uses_the_host_of_the_flow_that_issued_the_token()
    {
        Http::fake(['*' => Http::response(['message_id' => 'ig1'])]);
        $a = $this->account('instagram', 'IG1');
        $a->credentials = ['access_token' => 't', 'flow' => 'instagram_login'];
        $a->save();
        $c = $this->conversation($a, ['external_id' => 'R']);
        $m = ChatMessage::create(['chat_conversation_id' => $c->id, 'chat_account_id' => $a->id, 'direction' => 'out', 'body' => 'x', 'status' => 'queued']);
        $this->send($m);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://graph.instagram.com/') && str_contains($r->url(), '/IG1/messages'));
    }

    public function test_whatsapp_send_payload()
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.9']]])]);
        [, , $m] = $this->queued('whatsapp', ['external_id' => '8801700000000']);
        $this->send($m);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/1055/messages') && $r['messaging_product'] === 'whatsapp' && $r['to'] === '8801700000000' && $r['text']['body'] === 'hello');
        $this->assertSame('wamid.9', $m->fresh()->external_id);
    }

    public function test_send_is_idempotent_after_success()
    {
        Http::fake(['*' => Http::response(['message_id' => 'mid.1'])]);
        [, , $m] = $this->queued();
        $this->send($m);
        $this->send($m);
        Http::assertSentCount(1);
    }

    public function test_closed_window_fails_without_calling_the_provider()
    {
        Http::fake();
        [, , $m] = $this->queued('whatsapp', ['last_inbound_at' => now()->subHours(25)]);
        $this->send($m);
        Http::assertNothingSent();
        $m->refresh();
        $this->assertSame('failed', $m->status);
        $this->assertStringContainsString('template', $m->error);
    }

    public function test_expired_token_marks_account_for_reconnect_and_leaks_no_secret()
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 190, 'message' => 'Error validating access token tok-facebook']], 400)]);
        [$a, , $m] = $this->queued();
        $this->send($m);
        $this->assertSame('expired', $a->fresh()->status);
        $this->assertSame('failed', $m->fresh()->status);
        $this->assertStringNotContainsString('tok-facebook', (string) $m->fresh()->error . $a->fresh()->last_error);
    }

    public function test_rate_limit_is_released_for_backoff_not_failed()
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 4, 'message' => 'rate']], 429)]);
        [, , $m] = $this->queued();
        $job = new class($m->id) extends SendChatMessage {
            public $released = null;
            public function attempts() { return 1; }
            public function release($delay = 0) { $this->released = $delay; }
        };
        $job->handle(app(\App\Services\Chat\ProviderRegistry::class), app(\App\Services\Chat\InboundProcessor::class));
        $this->assertSame(10, $job->released);
        $this->assertSame('queued', $m->fresh()->status);
        $this->assertSame(1, $m->fresh()->retry_count);
    }

    public function test_tiktok_send_is_refused_as_unavailable_until_approved()
    {
        Http::fake();
        [, , $m] = $this->queued('tiktok');
        $this->send($m);
        Http::assertNothingSent();
        $this->assertStringContainsString('approval required', $m->fresh()->error);
    }

    public function test_echo_that_arrived_before_the_send_response_is_merged()
    {
        Http::fake(['*' => Http::response(['message_id' => 'mid.race'])]);
        [$a, $c, $m] = $this->queued();
        ChatMessage::create(['chat_conversation_id' => $c->id, 'chat_account_id' => $a->id, 'direction' => 'out', 'external_id' => 'mid.race', 'status' => 'sent', 'body' => 'hello']);
        $this->send($m);
        $this->assertSame(1, ChatMessage::where('external_id', 'mid.race')->count());
        $this->assertSame(1, ChatMessage::count());
    }

    public function test_greeting_is_sent_once_per_window_and_only_replies_never_opens_chats()
    {
        Bus::fake([SendChatMessage::class]);
        Automation::save(['greeting' => ['enabled' => true, 'text' => 'স্বাগতম'], 'away' => ['enabled' => true, 'text' => 'We are closed'],
            'hours' => ['start' => '00:00', 'end' => '23:59', 'days' => [0, 1, 2, 3, 4, 5, 6], 'tz' => 'UTC'], 'auto_close_days' => 0, 'unanswered_alert_minutes' => 0]);
        $this->account('facebook', 'PAGE1');
        $raw = fn ($mid) => json_encode($this->messengerPayload('PAGE1', $mid));
        foreach (['m1', 'm2'] as $mid) {
            $this->call('POST', '/api/chat/webhook/facebook', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $this->sign($raw($mid)), 'CONTENT_TYPE' => 'application/json'], $raw($mid));
        }
        $this->assertSame(1, ChatMessage::where('direction', 'out')->where('client_ref', 'like', 'auto:%')->count());
        Bus::assertDispatchedTimes(SendChatMessage::class, 1);
    }

    public function test_safe_fetch_only_allows_provider_cdn_https_hosts()
    {
        foreach (['https://scontent.xx.fbcdn.net/a.jpg', 'https://lookaside.fbsbx.com/x', 'https://graph.facebook.com/v21.0/1'] as $ok) {
            $this->assertTrue(SafeFetch::hostAllowed($ok), $ok);
        }
        foreach (['http://scontent.fbcdn.net/a.jpg', 'https://127.0.0.1/a', 'https://169.254.169.254/latest', 'https://evil.com/fbcdn.net', 'https://fbcdn.net.evil.com/a', 'https://user@fbcdn.net/a', 'https://fbcdn.net:8443/a', 'file:///etc/passwd', 'https://localhost/'] as $bad) {
            $this->assertFalse(SafeFetch::hostAllowed($bad), $bad);
        }
    }

    public function test_installation_side_signature_helpers()
    {
        $sig = \App\Services\Chat\GatewaySignature::sign('s', 'inst_a', 1000, 'body');
        $this->assertTrue(\App\Services\Chat\GatewaySignature::verify('s', 'inst_a', 1000, 'body', $sig, 300, 1100));
        $this->assertFalse(\App\Services\Chat\GatewaySignature::verify('s', 'inst_b', 1000, 'body', $sig, 300, 1100), 'bound to installation id');
        $this->assertFalse(\App\Services\Chat\GatewaySignature::verify('s', 'inst_a', 1000, 'body!', $sig, 300, 1100));
        $this->assertFalse(\App\Services\Chat\GatewaySignature::verify('s', 'inst_a', 1000, 'body', $sig, 300, 2000), 'stale');
        $this->assertFalse(\App\Services\Chat\GatewaySignature::verify('', 'inst_a', 1000, 'body', '', 300, 1000));
    }
}
