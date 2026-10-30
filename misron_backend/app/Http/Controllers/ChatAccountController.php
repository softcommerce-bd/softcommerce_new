<?php

namespace App\Http\Controllers;

use App\Models\ChatAccount;
use App\Models\User;
use App\Services\Chat\ChatAccess;
use App\Services\Chat\ChatAudit;
use App\Services\Chat\GatewayClient;
use App\Services\Chat\ProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Connected Accounts: official OAuth only. No passwords are ever collected. */
class ChatAccountController extends Controller
{
    public function __construct(private ChatAccess $access, private GatewayClient $gateway)
    {
        $this->middleware(['permission:view_unified_chat']);
        $this->middleware(['permission:manage_chat_accounts']);
    }

    public function index(Request $request)
    {
        $accounts = ChatAccount::with('staff:users.id,users.name')->orderBy('platform')->orderBy('name')->get()->map(function ($a) {
            return $a->toPublic() + [
                'staff_ids' => $a->staff->pluck('id'),
                'health' => $this->health($a),
            ];
        });

        return response()->json([
            'accounts' => $accounts, 'mode' => config('unified_chat.mode'),
            'gateway_ready' => $this->gateway->configured(),
            'platforms' => [
                'facebook' => ['available' => $this->canConnect('facebook')],
                'instagram' => ['available' => $this->canConnect('instagram')],
                'whatsapp' => ['available' => $this->canConnect('whatsapp')],
                'tiktok' => ['available' => (bool) config('unified_chat.tiktok.enabled'), 'note' => 'TikTok Business Messaging access must be approved by TikTok for the developer app.'],
            ],
            'staff' => User::whereIn('user_type', ['admin', 'staff'])->where('banned', 0)->orderBy('name')->get(['id', 'name'])->filter(fn ($u) => $u->can('view_unified_chat'))->values(),
        ]);
    }

    private function canConnect(string $platform): bool
    {
        return config('unified_chat.mode') === 'gateway' ? $this->gateway->configured() : (bool) config('unified_chat.meta.app_id') && $platform !== 'whatsapp';
    }

    /** Plain-language status for the diagnostics panel. */
    private function health(ChatAccount $a): array
    {
        $issues = [];
        if ($a->status === 'expired') $issues[] = ucfirst($a->platform) . ' authorization expired. Reconnect the account.';
        if ($a->status === 'pending_approval') $issues[] = 'Provider approval required before messaging works.';
        if ($a->token_expires_at && $a->token_expires_at->isPast()) $issues[] = 'The access token has expired.';
        if ($a->status === 'connected' && $a->last_webhook_at === null) $issues[] = 'No webhook received yet. Check the webhook subscription.';
        if ($a->last_error) $issues[] = $a->last_error;
        $failed = \App\Models\ChatMessage::where('chat_account_id', $a->id)->where('direction', 'out')->where('status', 'failed')->where('created_at', '>', now()->subDay())->count();
        if ($failed) $issues[] = "{$failed} message(s) failed in the last 24 hours.";

        return ['ok' => $issues === [] && $a->status === 'connected', 'issues' => $issues, 'failed_24h' => $failed, 'token_valid' => $a->status === 'connected' && !($a->token_expires_at && $a->token_expires_at->isPast())];
    }

    public function connect(Request $request, string $platform)
    {
        abort_unless(in_array($platform, ['facebook', 'instagram', 'whatsapp', 'tiktok'], true), 404);
        if ($platform === 'tiktok' && !config('unified_chat.tiktok.enabled')) {
            return response()->json(['message' => 'TikTok Business Messaging approval required. Connection is disabled until TikTok grants API access to the developer app.'], 422);
        }
        $state = Str::random(40);
        // State binds the callback to this browser session and this admin; single use, 10 minutes.
        Cache::put('chat:oauth:' . $state, ['user' => $request->user()->id, 'platform' => $platform, 'sid' => $request->session()->getId()], now()->addMinutes(10));
        $return = route('unified-chat.callback');

        if (config('unified_chat.mode') === 'gateway') {
            return response()->json(['redirect' => $this->gateway->startUrl($platform, $state, $return)]);
        }
        if ($platform === 'whatsapp' || !config('unified_chat.meta.app_id')) {
            return response()->json(['message' => 'Direct mode connects Facebook Pages (and their linked Instagram accounts). Use gateway mode for WhatsApp Embedded Signup.'], 422);
        }
        $scopes = 'pages_show_list,pages_messaging,pages_manage_metadata,instagram_basic,instagram_manage_messages,business_management';

        return response()->json(['redirect' => 'https://www.facebook.com/' . config('unified_chat.graph_version') . '/dialog/oauth?' . http_build_query([
            'client_id' => config('unified_chat.meta.app_id'), 'redirect_uri' => $return, 'state' => $state, 'scope' => $scopes, 'response_type' => 'code',
        ])]);
    }

    public function callback(Request $request)
    {
        $ctx = Cache::pull('chat:oauth:' . $request->query('state'));
        if (!$ctx || $ctx['user'] !== $request->user()->id || $ctx['sid'] !== $request->session()->getId()) {
            flash(translate('The connection request expired or was invalid. Please try again.'))->error();
            return redirect()->route('unified-chat.index');
        }
        if ($request->filled('error') || (!$request->filled('grant') && !$request->filled('code'))) {
            flash(translate('The provider did not authorize the connection.'))->error();
            return redirect()->route('unified-chat.index');
        }

        try {
            $found = config('unified_chat.mode') === 'gateway' ? $this->gateway->redeem((string) $request->query('grant')) : $this->directPages((string) $request->query('code'));
        } catch (\Throwable $e) {
            flash(translate('Could not complete the connection: ') . Str::limit($e->getMessage(), 160))->error();
            return redirect()->route('unified-chat.index');
        }

        $n = 0;
        foreach ($found as $a) {
            $this->upsert($request->user(), $a);
            $n++;
        }
        flash($n . ' ' . translate('account(s) connected.'))->success();

        return redirect()->route('unified-chat.index');
    }

    /** Direct mode: code → long-lived user token → Pages (+ linked Instagram) with their Page tokens. */
    private function directPages(string $code): array
    {
        $v = 'https://graph.facebook.com/' . config('unified_chat.graph_version');
        $app = config('unified_chat.meta');
        $short = Http::timeout(15)->get("$v/oauth/access_token", ['client_id' => $app['app_id'], 'client_secret' => $app['app_secret'], 'redirect_uri' => route('unified-chat.callback'), 'code' => $code])->throw()->json('access_token');
        $long = Http::timeout(15)->get("$v/oauth/access_token", ['grant_type' => 'fb_exchange_token', 'client_id' => $app['app_id'], 'client_secret' => $app['app_secret'], 'fb_exchange_token' => $short])->throw()->json('access_token');
        $pages = Http::timeout(15)->withToken($long)->get("$v/me/accounts", ['fields' => 'id,name,access_token,instagram_business_account{id,username,name}', 'limit' => 100])->throw()->json('data') ?? [];

        $out = [];
        foreach ($pages as $p) {
            $out[] = ['platform' => 'facebook', 'external_id' => $p['id'], 'name' => $p['name'], 'access_token' => $p['access_token'], 'expires_at' => null, 'flow' => 'facebook_login'];
            if (!empty($p['instagram_business_account'])) {
                $ig = $p['instagram_business_account'];
                $out[] = ['platform' => 'instagram', 'external_id' => $ig['id'], 'name' => $ig['name'] ?? $ig['username'] ?? $ig['id'], 'handle' => $ig['username'] ?? null, 'access_token' => $p['access_token'], 'expires_at' => null, 'flow' => 'facebook_login'];
            }
            // Subscribe the Page so Meta delivers its messages to this app's webhook.
            Http::timeout(15)->withToken($p['access_token'])->post("$v/{$p['id']}/subscribed_apps", ['subscribed_fields' => 'messages,message_echoes,message_deliveries,message_reads'])->throw();
        }

        return $out;
    }

    public function upsert(User $by, array $a): ChatAccount
    {
        abort_unless(in_array($a['platform'] ?? '', ['facebook', 'instagram', 'whatsapp', 'tiktok'], true), 422);
        // platform + external_id is unique: connecting the same account again refreshes it, never duplicates it.
        $account = ChatAccount::firstOrNew(['platform' => $a['platform'], 'external_id' => (string) $a['external_id']]);
        $isNew = !$account->exists;
        $account->fill(['name' => $a['name'] ?? $a['external_id'], 'handle' => $a['handle'] ?? null, 'status' => 'connected', 'last_error' => null,
            'token_expires_at' => $a['expires_at'] ?? null, 'connected_by' => $account->connected_by ?: $by->id]);
        $account->credentials = array_filter(['access_token' => $a['access_token'] ?? null, 'flow' => $a['flow'] ?? null]);
        $account->save();
        if ($isNew) {
            $account->staff()->syncWithoutDetaching([$by->id]);
        }
        ChatAudit::log($by->id, $isNew ? 'account.connected' : 'account.reconnected', 'account', $account->id, ['platform' => $account->platform]);

        return $account;
    }

    /** WhatsApp without the gateway: a phone number id plus a system-user token from the customer's own Meta business. */
    public function manualWhatsApp(Request $request)
    {
        $d = $request->validate(['phone_number_id' => 'required|digits_between:5,30', 'access_token' => 'required|string|max:1000']);
        $r = Http::timeout(15)->withToken($d['access_token'])->get('https://graph.facebook.com/' . config('unified_chat.graph_version') . '/' . $d['phone_number_id'], ['fields' => 'display_phone_number,verified_name']);
        if (!$r->successful()) {
            return response()->json(['message' => 'Meta did not accept that phone number id and token.'], 422);
        }
        if (config('unified_chat.mode') === 'gateway') {
            try {
                $this->gateway->register('whatsapp', $d['phone_number_id'], $d['access_token']); // routes this number's webhooks here
            } catch (\Throwable $e) {
                return response()->json(['message' => 'The gateway refused this number (already connected elsewhere, or not controlled by this token).'], 422);
            }
        }
        $a = $this->upsert($request->user(), ['platform' => 'whatsapp', 'external_id' => $d['phone_number_id'], 'name' => $r->json('verified_name') ?: $r->json('display_phone_number'), 'handle' => $r->json('display_phone_number'), 'access_token' => $d['access_token']]);

        return response()->json($a->toPublic(), 201);
    }

    public function update(Request $request, $id)
    {
        $a = ChatAccount::findOrFail($id);
        $d = $request->validate(['inbox_enabled' => 'nullable|boolean', 'staff_ids' => 'nullable|array', 'staff_ids.*' => 'integer']);
        if (array_key_exists('inbox_enabled', $d)) {
            $a->forceFill(['inbox_enabled' => $d['inbox_enabled']])->save();
        }
        if (array_key_exists('staff_ids', $d)) {
            // Only staff who hold the chat permission can be granted an account.
            $ids = User::whereIn('id', $d['staff_ids'])->whereIn('user_type', ['admin', 'staff'])->get()->filter(fn ($u) => $u->can('view_unified_chat'))->pluck('id');
            $a->staff()->sync($ids);
            // Conversations assigned to someone who just lost the account go back to the unassigned pool.
            \App\Models\ChatConversation::where('chat_account_id', $a->id)->whereNotNull('assigned_to')->whereNotIn('assigned_to', $ids)->update(['assigned_to' => null]);
            ChatAudit::log($request->user()->id, 'account.staff', 'account', $a->id, ['staff' => $ids->all()]);
        }

        return response()->json($a->toPublic());
    }

    public function destroy(Request $request, $id)
    {
        $a = ChatAccount::findOrFail($id);
        try {
            if (config('unified_chat.mode') === 'gateway' && $this->gateway->configured()) {
                $this->gateway->unlink($a->platform, $a->external_id);
            }
        } catch (\Throwable $e) {
            // Disconnect locally regardless; the gateway also drops events for disabled accounts.
        }
        // History stays; the credentials go and the account stops receiving.
        $a->credentials = null;
        $a->forceFill(['status' => 'disabled', 'inbox_enabled' => false])->save();
        $a->staff()->detach();
        ChatAudit::log($request->user()->id, 'account.disconnected', 'account', $a->id, ['platform' => $a->platform]);

        return response()->json(['ok' => true]);
    }
}
