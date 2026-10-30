<?php

namespace App\Http\Controllers;

use App\Jobs\Chat\SendChatMessage;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatNote;
use App\Models\ChatSavedReply;
use App\Models\Order;
use App\Models\User;
use App\Events\Chat\ChatUpdated;
use App\Services\Chat\Automation;
use App\Services\Chat\ChatAccess;
use App\Services\Chat\ChatAudit;
use App\Services\Chat\ChatOrderService;
use App\Services\Chat\ProviderRegistry;
use App\Services\Chat\SafeFetch;
use App\Services\Funnel\FunnelCatalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Unified Chat inbox. Every action goes through ChatAccess: the route middleware
 * only gates the page, the real checks are repeated per record here.
 */
class UnifiedChatController extends Controller
{
    public function __construct(private ChatAccess $access, private ProviderRegistry $providers)
    {
        $this->middleware(['permission:view_unified_chat']);
    }

    private function deny(string $why = 'Not allowed.')
    {
        return response()->json(['message' => $why], 403);
    }

    private function conversation(Request $request, $id, ?string $permission = null): ChatConversation
    {
        $c = $this->access->find($request->user(), $id, $permission);
        abort_if(!$c, 404); // same answer for "missing" and "not yours": ids reveal nothing
        return $c;
    }

    public function index()
    {
        return view('backend.unified_chat.index');
    }

    public function bootstrap(Request $request)
    {
        $u = $request->user();
        $perms = [];
        foreach (['view_all_chats', 'view_assigned_chats', 'reply_unified_chat', 'assign_unified_chat', 'manage_chat_accounts', 'manage_chat_settings',
                     'manage_chat_automation', 'view_chat_analytics', 'create_order_from_chat', 'manage_chat_notes', 'export_chat_data'] as $p) {
            $perms[$p] = $this->access->can($u, $p);
        }
        $accounts = $this->access->accounts($u)->orderBy('platform')->orderBy('name')->get()->map->toPublic();
        $base = $this->access->conversations($u);

        return response()->json([
            'me' => ['id' => $u->id, 'name' => $u->name],
            'permissions' => $perms,
            'accounts' => $accounts,
            'capabilities' => collect($this->providers->platforms())->mapWithKeys(fn ($p) => [$p => $this->providers->get($p)->capabilities() + ['window_hours' => $this->providers->get($p)->windowHours()]]),
            'agents' => $perms['assign_unified_chat'] ? $this->agents() : [],
            'counts' => [
                'all' => (clone $base)->count(),
                'unread' => (clone $base)->where('unread_count', '>', 0)->count(),
                'mine' => (clone $base)->where('assigned_to', $u->id)->count(),
                'unassigned' => (clone $base)->whereNull('assigned_to')->count(),
                'open' => (clone $base)->where('status', 'open')->count(),
                'pending' => (clone $base)->where('status', 'pending')->count(),
                'resolved' => (clone $base)->where('status', 'resolved')->count(),
                'archived' => (clone $base)->where('status', 'archived')->count(),
            ],
            'poll_seconds' => config('unified_chat.poll_seconds'),
            'realtime' => config('broadcasting.default') !== 'null' ? ['channels' => $accounts->pluck('id')] : null,
        ]);
    }

    /** Staff who could be assigned: have chat permissions (roles) — the per-account check happens on assign. */
    private function agents(): array
    {
        return User::whereIn('user_type', ['admin', 'staff'])->where('banned', 0)->orderBy('name')->get(['id', 'name'])
            ->filter(fn ($u) => $u->can('view_unified_chat'))->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->all();
    }

    public function conversations(Request $request)
    {
        $u = $request->user();
        $q = $this->access->conversations($u)->with(['account:id,name,platform', 'assignee:id,name']);

        match ($request->query('folder', 'all')) {
            'unread' => $q->where('unread_count', '>', 0),
            'mine' => $q->where('assigned_to', $u->id),
            'unassigned' => $q->whereNull('assigned_to'),
            'open', 'pending', 'resolved', 'archived' => $q->where('status', $request->query('folder')),
            default => $q->where('status', '!=', 'archived'),
        };
        if ($request->filled('platform')) $q->where('platform', $request->query('platform'));
        if ($request->filled('account_id')) $q->where('chat_account_id', (int) $request->query('account_id'));
        if ($request->filled('assignee')) $q->where('assigned_to', (int) $request->query('assignee'));
        if ($request->filled('priority')) $q->where('priority', $request->query('priority'));
        if ($request->filled('from')) $q->where('last_message_at', '>=', $request->query('from'));
        if ($request->filled('to')) $q->where('last_message_at', '<=', $request->query('to') . ' 23:59:59');
        if ($request->filled('customer_id')) $q->where('user_id', (int) $request->query('customer_id'));
        if ($term = trim((string) $request->query('q'))) {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $q->where(fn ($w) => $w->where('customer_name', 'like', $like)->orWhere('customer_handle', 'like', $like)
                ->orWhere('customer_phone', 'like', $like)->orWhere('last_message_preview', 'like', $like));
        }
        // Keyset pagination on (last_message_at, id): stable under new messages, no OFFSET scans.
        if ($request->filled('before_at') && $request->filled('before_id')) {
            $at = \Carbon\Carbon::createFromTimestamp((int) $request->query('before_at')); // numeric cursor: no timezone/format ambiguity
            $id = (int) $request->query('before_id');
            $q->where(fn ($w) => $w->where('last_message_at', '<', $at)->orWhere(fn ($x) => $x->where('last_message_at', $at)->where('id', '<', $id)));
        }
        $rows = $q->orderByDesc('last_message_at')->orderByDesc('id')->limit(31)->get();
        $more = $rows->count() > 30;

        return response()->json(['data' => $rows->take(30)->map(fn ($c) => $this->present($c))->values(), 'has_more' => $more]);
    }

    private function present(ChatConversation $c): array
    {
        return [
            'id' => $c->id, 'platform' => $c->platform, 'account' => ['id' => $c->account->id ?? null, 'name' => $c->account->name ?? ''],
            'name' => $c->customer_name ?: ($c->customer_handle ?: ('#' . $c->external_id)), 'avatar' => $c->customer_avatar,
            'preview' => $c->last_message_preview, 'last_message_at' => optional($c->last_message_at)->toIso8601String(), 'cursor' => optional($c->last_message_at)->timestamp,
            'unread' => $c->unread_count, 'status' => $c->status, 'priority' => $c->priority,
            'assignee' => $c->assignee ? ['id' => $c->assignee->id, 'name' => $c->assignee->name] : null,
            'user_id' => $c->user_id, 'tags' => $c->tags ?: [],
            'window_open' => $this->windowOpen($c),
        ];
    }

    private function windowOpen(ChatConversation $c): bool
    {
        $h = $this->providers->get($c->platform)->windowHours();

        return $h === null || ($c->last_inbound_at && $c->last_inbound_at->gt(now()->subHours($h)));
    }

    public function show(Request $request, $id)
    {
        $c = $this->conversation($request, $id)->load(['account:id,name,platform,status,last_error', 'assignee:id,name', 'notes.author:id,name']);
        $u = $request->user();
        $data = $this->present($c) + [
            'account_status' => $c->account->status, 'account_error' => $c->account->last_error,
            'window_hours' => $this->providers->get($c->platform)->windowHours(),
            'window_expires_at' => $c->last_inbound_at && $this->providers->get($c->platform)->windowHours() ? $c->last_inbound_at->copy()->addHours($this->providers->get($c->platform)->windowHours())->toIso8601String() : null,
            'customer_notes' => $c->customer_notes, 'phone' => $c->customer_phone,
            'notes' => $this->access->can($u, 'manage_chat_notes') ? $c->notes->map(fn ($n) => ['id' => $n->id, 'body' => $n->body, 'author' => $n->author->name ?? '', 'user_id' => $n->user_id, 'created_at' => $n->created_at->toIso8601String()]) : [],
            'customer' => $this->customerPanel($c, $u),
        ];

        return response()->json($data);
    }

    private function customerPanel(ChatConversation $c, User $u): array
    {
        $panel = ['linked' => null, 'orders' => null];
        if ($c->user_id && ($customer = User::find($c->user_id))) {
            $panel['linked'] = ['id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone, 'email' => $customer->email];
            if ($u->can('view_all_orders') || $u->can('view_order_details') || $u->can('create_order_from_chat')) {
                $orders = Order::where('user_id', $customer->id)->orderByDesc('id');
                $live = (clone $orders)->where('delivery_status', '!=', 'cancelled');
                $panel['orders'] = [
                    'count' => (clone $live)->count(), 'lifetime_value' => round((float) (clone $live)->sum('grand_total'), 2),
                    'pending' => (clone $orders)->whereIn('delivery_status', ['pending', 'confirmed', 'on_the_way', 'picked_up'])->count(),
                    'recent' => (clone $orders)->limit(5)->get(['id', 'code', 'grand_total', 'delivery_status', 'payment_status', 'date'])
                        ->map(fn ($o) => ['code' => $o->code, 'total' => $o->grand_total, 'delivery' => $o->delivery_status, 'payment' => $o->payment_status, 'date' => $o->date ? date('Y-m-d', $o->date) : null]),
                ];
            }
        }

        return $panel;
    }

    public function messages(Request $request, $id)
    {
        $c = $this->conversation($request, $id);
        $q = ChatMessage::where('chat_conversation_id', $c->id)->orderByDesc('id');
        if ($request->filled('before')) $q->where('id', '<', (int) $request->query('before'));
        if ($request->filled('after')) $q->where('id', '>', (int) $request->query('after'));
        $rows = $q->limit(41)->get();

        return response()->json([
            'data' => $rows->take(40)->reverse()->values()->map(fn ($m) => $this->presentMessage($m)),
            'has_more' => $rows->count() > 40,
        ]);
    }

    private function presentMessage(ChatMessage $m): array
    {
        return [
            'id' => $m->id, 'direction' => $m->direction, 'type' => $m->type, 'body' => $m->body, 'status' => $m->status, 'error' => $m->error,
            'at' => ($m->provider_at ?: $m->created_at)->toIso8601String(), 'sent_by' => $m->sent_by, 'auto' => str_starts_with((string) $m->client_ref, 'auto:'),
            // Attachments are only ever referenced by index: the browser never gets a provider URL or a token.
            'attachments' => collect($m->attachments ?: [])->map(fn ($a, $i) => ['type' => $a['type'] ?? 'file', 'name' => $a['name'] ?? null, 'index' => $i])->values(),
        ];
    }

    public function send(Request $request, $id)
    {
        $c = $this->conversation($request, $id, 'reply_unified_chat');
        $data = $request->validate([
            'body' => 'nullable|string|max:4000', 'client_ref' => 'required|string|max:64|regex:/^[A-Za-z0-9_-]+$/',
            'attachment' => 'nullable|file|max:8192|mimes:jpg,jpeg,png,gif,webp,pdf,mp4',
        ]);
        if (trim((string) ($data['body'] ?? '')) === '' && !$request->hasFile('attachment')) {
            return response()->json(['message' => 'Write a message or attach a file.'], 422);
        }
        if ($c->account->status !== 'connected') {
            return response()->json(['message' => 'This account is not connected. Reconnect it first.'], 422);
        }
        if (!$this->windowOpen($c)) {
            $h = $this->providers->get($c->platform)->windowHours();
            return response()->json(['message' => "More than {$h} hours have passed since the customer last wrote. The provider only allows an approved template message now.", 'code' => 'window_closed'], 422);
        }

        // Double-click / retry safety: same client_ref returns the message already created.
        if ($existing = ChatMessage::where('chat_conversation_id', $c->id)->where('client_ref', $data['client_ref'])->first()) {
            return response()->json($this->presentMessage($existing));
        }

        $attachments = null;
        $type = 'text';
        if ($file = $request->file('attachment')) {
            $mime = $file->getMimeType();
            $type = Str::startsWith($mime, 'image/') ? 'image' : (Str::startsWith($mime, 'video/') ? 'video' : 'file');
            if (!$this->providers->get($c->platform)->capabilities()[$type === 'image' ? 'image' : 'file']) {
                return response()->json(['message' => 'This platform does not support that attachment type.'], 422);
            }
            $path = $file->storeAs('chat/' . $c->id, Str::uuid() . '.' . $file->extension(), config('unified_chat.attachment_disk'));
            $attachments = [['type' => $type, 'path' => $path, 'mime' => $mime, 'name' => mb_substr($file->getClientOriginalName(), 0, 120)]];
        }

        $m = ChatMessage::create([
            'chat_conversation_id' => $c->id, 'chat_account_id' => $c->chat_account_id, 'direction' => 'out', 'type' => $type,
            'body' => $data['body'] ?? null, 'attachments' => $attachments, 'status' => 'queued', 'sent_by' => $request->user()->id, 'client_ref' => $data['client_ref'],
        ]);
        $c->forceFill(['last_message_preview' => mb_substr($data['body'] ?? "[{$type}]", 0, 200), 'last_message_at' => now()])->save();
        SendChatMessage::dispatch($m->id);
        event(new ChatUpdated($c->chat_account_id, 'message.out', $c->id, $m->id));

        return response()->json($this->presentMessage($m), 201);
    }

    public function retry(Request $request, $id, $messageId)
    {
        $c = $this->conversation($request, $id, 'reply_unified_chat');
        $m = ChatMessage::where('chat_conversation_id', $c->id)->where('direction', 'out')->where('status', 'failed')->findOrFail($messageId);
        $m->forceFill(['status' => 'queued', 'error' => null])->save();
        SendChatMessage::dispatch($m->id);

        return response()->json($this->presentMessage($m));
    }

    public function markRead(Request $request, $id)
    {
        $c = $this->conversation($request, $id);
        $c->forceFill(['unread_count' => 0])->save();

        return response()->json(['ok' => true]);
    }

    public function update(Request $request, $id)
    {
        $c = $this->conversation($request, $id, 'reply_unified_chat');
        $d = $request->validate(['status' => 'nullable|in:open,pending,resolved,archived', 'priority' => 'nullable|in:low,normal,high,urgent', 'tags' => 'nullable|array|max:10', 'tags.*' => 'string|max:30', 'customer_notes' => 'nullable|string|max:2000']);
        if (isset($d['status']) && $d['status'] !== $c->status) {
            $c->status = $d['status'];
            $c->resolved_at = $d['status'] === 'resolved' ? now() : null;
            ChatAudit::log($request->user()->id, 'conversation.status', 'conversation', $c->id, ['status' => $d['status']]);
        }
        foreach (['priority', 'tags', 'customer_notes'] as $f) {
            if (array_key_exists($f, $d)) $c->{$f} = $d[$f];
        }
        $c->save();
        event(new ChatUpdated($c->chat_account_id, 'conversation.updated', $c->id));

        return response()->json(['ok' => true]);
    }

    public function assign(Request $request, $id)
    {
        $c = $this->conversation($request, $id, 'assign_unified_chat');
        $d = $request->validate(['user_id' => 'nullable|integer']);
        if (!empty($d['user_id'])) {
            $target = User::whereIn('user_type', ['admin', 'staff'])->where('banned', 0)->find($d['user_id']);
            // The assignee must themselves be allowed to see this account.
            if (!$target || !$this->access->canBeAssigned($target, $c)) {
                return response()->json(['message' => 'That staff member has no access to this connected account.'], 422);
            }
        }
        $c->forceFill(['assigned_to' => $d['user_id'] ?? null])->save();
        ChatAudit::log($request->user()->id, 'conversation.assign', 'conversation', $c->id, ['to' => $d['user_id'] ?? null]);
        event(new ChatUpdated($c->chat_account_id, 'conversation.assigned', $c->id));

        return response()->json(['ok' => true]);
    }

    public function addNote(Request $request, $id)
    {
        $c = $this->conversation($request, $id, 'manage_chat_notes');
        $d = $request->validate(['body' => 'required|string|max:2000']);
        $n = ChatNote::create(['chat_conversation_id' => $c->id, 'user_id' => $request->user()->id, 'body' => $d['body']]);

        return response()->json(['id' => $n->id], 201);
    }

    public function editNote(Request $request, $id, $noteId)
    {
        $c = $this->conversation($request, $id, 'manage_chat_notes');
        $n = ChatNote::where('chat_conversation_id', $c->id)->findOrFail($noteId);
        abort_unless($n->user_id === $request->user()->id || $this->access->isSuper($request->user()), 403);
        if ($request->isMethod('delete')) {
            $n->delete();
        } else {
            $n->update($request->validate(['body' => 'required|string|max:2000']));
        }

        return response()->json(['ok' => true]);
    }

    // ── Customers ─────────────────────────────────────────────────────────

    public function searchCustomers(Request $request)
    {
        abort_unless($request->user()->can('reply_unified_chat') || $request->user()->can('create_order_from_chat'), 403);
        $term = trim((string) $request->query('q'));
        if (mb_strlen($term) < 3) {
            return response()->json([]);
        }
        $like = '%' . addcslashes($term, '%_\\') . '%';

        return response()->json(User::where('user_type', 'customer')->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like))
            ->limit(10)->get(['id', 'name', 'phone', 'email']));
    }

    public function linkCustomer(Request $request, $id)
    {
        $c = $this->conversation($request, $id, 'reply_unified_chat');
        $d = $request->validate(['user_id' => 'nullable|integer', 'create' => 'nullable|array', 'create.name' => 'required_with:create|string|max:120', 'create.phone' => 'required_with:create|string|max:30']);
        if (!empty($d['create'])) {
            $user = User::create(['name' => $d['create']['name'], 'phone' => $d['create']['phone'], 'password' => bcrypt(Str::random(40))]);
        } elseif (!empty($d['user_id'])) {
            $user = User::where('user_type', 'customer')->findOrFail($d['user_id']);
        } else {
            $user = null; // unlink
        }
        $c->forceFill(['user_id' => $user?->id])->save();
        ChatAudit::log($request->user()->id, $user ? 'customer.link' : 'customer.unlink', 'conversation', $c->id, ['user_id' => $user?->id]);

        return response()->json(['ok' => true]);
    }

    // ── Orders ────────────────────────────────────────────────────────────

    public function searchProducts(Request $request, FunnelCatalogue $catalogue)
    {
        abort_unless($request->user()->can('create_order_from_chat'), 403);
        $page = $catalogue->search(['q' => $request->query('q'), 'status' => 'published'], 10);

        return response()->json($catalogue->present($page->items()));
    }

    public function districts(Request $request)
    {
        abort_unless($request->user()->can('create_order_from_chat'), 403);

        return response()->json(\App\Models\City::where('status', 1)->orderBy('name')->get(['id', 'name']));
    }

    public function createOrder(Request $request, $id, ChatOrderService $orders)
    {
        $c = $this->conversation($request, $id, 'create_order_from_chat');
        $input = $request->validate([
            'idempotency_key' => 'required|string', 'items' => 'required|array|min:1|max:50', 'items.*.product_id' => 'required|integer',
            'items.*.variant' => 'nullable|string|max:190', 'items.*.quantity' => 'required|integer|min:1|max:999',
            'customer' => 'required|array', 'discount' => 'nullable|numeric|min:0', 'note' => 'nullable|string|max:500',
        ]);
        try {
            $combined = $orders->place($c, $request->user(), $input, (string) $request->ip());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['combined_order_id' => $combined->id, 'grand_total' => $combined->grand_total], 201);
    }

    public function orders(Request $request, $id)
    {
        $c = $this->conversation($request, $id);
        abort_unless($request->user()->can('create_order_from_chat') || $request->user()->can('view_all_orders') || $request->user()->can('view_order_details'), 403);

        return response()->json($c->orders()->with('combinedOrder:id,grand_total')->latest()->get()->map(function ($l) {
            $codes = Order::where('combined_order_id', $l->combined_order_id)->get(['code', 'delivery_status', 'payment_status']);

            return ['combined_order_id' => $l->combined_order_id, 'total' => $l->combinedOrder->grand_total ?? null, 'orders' => $codes, 'created_at' => $l->created_at->toIso8601String()];
        }));
    }

    // ── Attachments ───────────────────────────────────────────────────────

    public function attachment(Request $request, $messageId, $index)
    {
        $m = ChatMessage::findOrFail($messageId);
        $this->conversation($request, $m->chat_conversation_id); // same authorization as reading the conversation
        $a = ($m->attachments ?: [])[(int) $index] ?? abort(404);

        if (!empty($a['path'])) {
            $disk = Storage::disk(config('unified_chat.attachment_disk'));
            abort_unless($disk->exists($a['path']), 404);
            return response($disk->get($a['path']), 200, ['Content-Type' => $a['mime'] ?? 'application/octet-stream', 'Content-Disposition' => 'inline', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=300']);
        }

        $fetched = app(SafeFetch::class)->attachment($m->account ?? \App\Models\ChatAccount::find($m->chat_account_id), $a);
        abort_unless($fetched, 404);

        return response($fetched['body'], 200, ['Content-Type' => $fetched['mime'], 'Content-Disposition' => 'inline', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=300']);
    }

    // ── Saved replies / settings ──────────────────────────────────────────

    public function savedReplies(Request $request)
    {
        return response()->json(ChatSavedReply::orderBy('title')->get(['id', 'title', 'body', 'lang']));
    }

    public function saveReply(Request $request, $id = null)
    {
        abort_unless($request->user()->can('manage_chat_settings') || $request->user()->can('manage_chat_automation'), 403);
        $d = $request->validate(['title' => 'required|string|max:120', 'body' => 'required|string|max:4000', 'lang' => 'required|in:en,bn']);
        $r = $id ? ChatSavedReply::findOrFail($id) : new ChatSavedReply(['created_by' => $request->user()->id]);
        $r->fill($d)->save();

        return response()->json(['id' => $r->id]);
    }

    public function deleteReply(Request $request, $id)
    {
        abort_unless($request->user()->can('manage_chat_settings') || $request->user()->can('manage_chat_automation'), 403);
        ChatSavedReply::whereKey($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function settings(Request $request)
    {
        abort_unless($request->user()->can('manage_chat_settings') || $request->user()->can('manage_chat_automation'), 403);

        return response()->json(Automation::settings());
    }

    public function saveSettings(Request $request)
    {
        abort_unless($request->user()->can('manage_chat_automation') || $request->user()->can('manage_chat_settings'), 403);
        $d = $request->validate([
            'greeting.enabled' => 'boolean', 'greeting.text' => 'nullable|string|max:1000', 'away.enabled' => 'boolean', 'away.text' => 'nullable|string|max:1000',
            'hours.start' => 'required|date_format:H:i', 'hours.end' => 'required|date_format:H:i', 'hours.days' => 'array', 'hours.days.*' => 'integer|between:0,6',
            'hours.tz' => 'required|timezone', 'auto_close_days' => 'integer|min:0|max:365', 'unanswered_alert_minutes' => 'integer|min:0|max:10080',
        ]);
        Automation::save($d);
        ChatAudit::log($request->user()->id, 'settings.updated');

        return response()->json(['ok' => true]);
    }

    // ── Polling fallback ──────────────────────────────────────────────────

    /** For hosts with no WebSocket server: cheap, authorized, ids-only. */
    public function updates(Request $request)
    {
        $since = $request->query('since') ? \Carbon\Carbon::parse($request->query('since')) : now()->subMinute();
        $q = $this->access->conversations($request->user())->where('updated_at', '>', $since);

        return response()->json([
            'now' => now()->toIso8601String(),
            'conversation_ids' => $q->orderByDesc('updated_at')->limit(100)->pluck('id'),
            'unread_total' => $this->access->conversations($request->user())->where('unread_count', '>', 0)->count(),
        ]);
    }
}
