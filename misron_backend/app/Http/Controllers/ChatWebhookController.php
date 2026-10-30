<?php

namespace App\Http\Controllers;

use App\Services\Chat\GatewaySignature;
use App\Services\Chat\ProviderRegistry;
use App\Services\Chat\WebhookIngest;
use Illuminate\Http\Request;

/**
 * Public endpoints. No session, no CSRF: every request is authenticated by a
 * signature (provider app secret in direct mode, installation secret from the
 * gateway), and nothing is processed inline — events are stored and queued.
 */
class ChatWebhookController extends Controller
{
    public function __construct(private ProviderRegistry $providers, private WebhookIngest $ingest)
    {
    }

    /** Meta's subscription handshake (direct mode only). */
    public function verify(Request $request, string $platform)
    {
        if (config('unified_chat.mode') !== 'direct' || !in_array($platform, ['facebook', 'instagram', 'whatsapp'], true)) {
            return response('Not found', 404);
        }
        $token = (string) config('unified_chat.meta.verify_token');
        if ($token !== '' && $request->query('hub_mode') === 'subscribe' && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function direct(Request $request, string $platform)
    {
        if (config('unified_chat.mode') !== 'direct' || !in_array($platform, $this->providers->platforms(), true)) {
            return response()->json(['ok' => false], 404);
        }
        $raw = $request->getContent();
        $headers = array_change_key_case(array_map(fn ($v) => is_array($v) ? $v[0] : $v, $request->headers->all()), CASE_LOWER);

        if (!$this->providers->get($platform)->verifyWebhook($raw, $headers)) {
            return response()->json(['ok' => false], 401);
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return response()->json(['ok' => false], 400);
        }
        $this->ingest->accept('direct', $platform, 'sha1:' . sha1($raw), $payload);

        return response()->json(['ok' => true]);
    }

    /** Signed delivery from the Softtech Messaging Integration Gateway. */
    public function gateway(Request $request)
    {
        $cfg = config('unified_chat.gateway');
        $raw = $request->getContent();
        $installation = (string) $request->header('X-Softtech-Installation');

        $valid = $cfg['installation_id'] && hash_equals((string) $cfg['installation_id'], $installation)
            && GatewaySignature::verify((string) $cfg['secret'], $installation, (int) $request->header('X-Softtech-Timestamp'), $raw,
                (string) $request->header('X-Softtech-Signature'), (int) $cfg['tolerance']);
        if (!$valid) {
            return response()->json(['ok' => false], 401);
        }

        $body = json_decode($raw, true);
        $platform = (string) ($body['platform'] ?? '');
        if (!is_array($body) || empty($body['event_id']) || !is_array($body['payload'] ?? null) || !in_array($platform, $this->providers->platforms(), true)) {
            return response()->json(['ok' => false], 422);
        }
        $new = $this->ingest->accept('gateway', $platform, 'gw:' . $body['event_id'], $body['payload']);

        // 200 for a duplicate too: the gateway must stop retrying an event we already hold.
        return response()->json(['ok' => true, 'duplicate' => !$new]);
    }

    public function media($message, $index)
    {
        $m = \App\Models\ChatMessage::where('direction', 'out')->findOrFail($message);
        $a = ($m->attachments ?: [])[(int) $index] ?? abort(404);
        $disk = \Illuminate\Support\Facades\Storage::disk(config('unified_chat.attachment_disk'));
        abort_unless(!empty($a['path']) && $disk->exists($a['path']), 404);

        return response($disk->get($a['path']), 200, ['Content-Type' => $a['mime'] ?? 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }
}
