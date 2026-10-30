<?php

namespace App\Services\Chat;

use App\Events\Chat\ChatUpdated;
use App\Models\ChatAccount;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Applies normalized provider events to the inbox. Safe to run twice for the
 * same event (providers redeliver) and for events that arrive out of order.
 */
class InboundProcessor
{
    /** A status may only move forward. */
    private const RANK = ['failed' => 0, 'received' => 1, 'queued' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4];

    public function __construct(private ProviderRegistry $providers, private Automation $automation)
    {
    }

    public function handle(string $platform, array $payload): int
    {
        $count = 0;
        foreach ($this->providers->get($platform)->normalize($payload) as $event) {
            $account = ChatAccount::where('platform', $platform)->where('external_id', (string) ($event['account_ext'] ?? ''))->first();
            // Strict mapping: an event for an account this installation does not own is dropped, never stored.
            if (!$account || $account->status === 'disabled') {
                continue;
            }
            $account->forceFill(['last_webhook_at' => now()])->save();

            if (!$account->inbox_enabled) {
                continue;
            }
            if ($event['kind'] === 'message') {
                $count += $this->message($account, $event) ? 1 : 0;
            } elseif ($event['kind'] === 'status') {
                $count += $this->status($account, $event) ? 1 : 0;
            }
        }

        return $count;
    }

    private function message(ChatAccount $account, array $e): bool
    {
        if ($e['mid'] === '' || $e['contact_ext'] === '') {
            return false;
        }
        if (ChatMessage::where('chat_account_id', $account->id)->where('external_id', $e['mid'])->exists()) {
            return false; // duplicate delivery, or the echo of a message this inbox sent
        }

        $at = Carbon::createFromTimestamp($e['ts']);
        $echo = (bool) $e['echo'];

        $message = DB::transaction(function () use ($account, $e, $at, $echo) {
            $conversation = $this->conversation($account, $e);

            try {
                $message = ChatMessage::create([
                    'chat_conversation_id' => $conversation->id, 'chat_account_id' => $account->id,
                    'external_id' => $e['mid'], 'direction' => $echo ? 'out' : 'in', 'type' => $e['type'] ?: 'text',
                    'body' => $e['text'], 'attachments' => $e['attachments'] ?: null,
                    'status' => $echo ? 'sent' : 'received', 'provider_at' => $at,
                ]);
            } catch (QueryException $ex) {
                if ($this->isDuplicate($ex)) {
                    return null; // lost a race with a parallel delivery of the same event
                }
                throw $ex;
            }

            $newest = !$conversation->last_message_at || $at->gte($conversation->last_message_at);
            if ($newest) { // an older message arriving late never replaces the list preview or moves the chat backwards
                $conversation->last_message_preview = $e['text'] !== null && $e['text'] !== '' ? mb_substr($e['text'], 0, 200) : '[' . ($e['type'] ?: 'attachment') . ']';
                $conversation->last_message_at = $at;
            }
            if ($echo) {
                $conversation->first_response_at = $conversation->first_response_at ?: $at;
            } else {
                $conversation->unread_count++;
                if (!$conversation->last_inbound_at || $at->gt($conversation->last_inbound_at)) {
                    $conversation->last_inbound_at = $at;
                }
                if (in_array($conversation->status, ['resolved', 'archived'], true)) {
                    $conversation->status = 'open';
                    $conversation->resolved_at = null;
                }
            }
            $conversation->save();

            return $message;
        });

        if (!$message) {
            return false;
        }

        $account->forceFill(['last_message_at' => now(), 'status' => $account->status === 'error' ? 'connected' : $account->status])->save();
        event(new ChatUpdated($account->id, $echo ? 'message.out' : 'message.in', $message->chat_conversation_id, $message->id));

        if (!$echo) {
            $this->automation->afterInbound($account, ChatConversation::find($message->chat_conversation_id));
        }

        return true;
    }

    private function conversation(ChatAccount $account, array $e): ChatConversation
    {
        $find = fn () => ChatConversation::where('chat_account_id', $account->id)->where('external_id', $e['contact_ext'])->first();
        $conversation = $find();

        if (!$conversation) {
            $profile = [];
            try {
                $profile = $e['contact_name'] ? [] : app(ProviderRegistry::class)->get($account->platform)->profile($account, $e['contact_ext']);
            } catch (\Throwable $t) {
                // Display data is a nicety; never block message storage on it.
            }
            try {
                $conversation = ChatConversation::create([
                    'chat_account_id' => $account->id, 'platform' => $account->platform, 'external_id' => $e['contact_ext'],
                    'customer_name' => $e['contact_name'] ?? ($profile['name'] ?? null),
                    'customer_avatar' => $profile['avatar'] ?? null,
                    'customer_phone' => $e['contact_phone'] ?? null,
                    'status' => 'open',
                ]);
                event(new ChatUpdated($account->id, 'conversation.new', $conversation->id));
            } catch (QueryException $ex) {
                if (!$this->isDuplicate($ex) || !($conversation = $find())) {
                    throw $ex;
                }
            }
        } elseif ($e['contact_name'] && $conversation->customer_name !== $e['contact_name']) {
            $conversation->customer_name = $e['contact_name'];
        }

        return $conversation;
    }

    private function status(ChatAccount $account, array $e): bool
    {
        $new = $e['status'];

        if (isset($e['watermark'])) {
            // Messenger/Instagram "read": everything we sent to this contact up to the watermark.
            $conversation = ChatConversation::where('chat_account_id', $account->id)->where('external_id', $e['contact_ext'])->first();
            if (!$conversation) {
                return false;
            }
            $ids = ChatMessage::where('chat_conversation_id', $conversation->id)->where('direction', 'out')
                ->whereIn('status', ['sent', 'delivered'])->where('provider_at', '<=', Carbon::createFromTimestamp($e['watermark']))->pluck('id');
            if ($ids->isEmpty()) {
                return false;
            }
            ChatMessage::whereIn('id', $ids)->update(['status' => 'read']);
            event(new ChatUpdated($account->id, 'message.status', $conversation->id));

            return true;
        }

        $message = ChatMessage::where('chat_account_id', $account->id)->where('external_id', $e['mid'])->where('direction', 'out')->first();
        if (!$message) {
            // The status beat our own send confirmation: keep the best one and apply it when the id is known.
            $key = "chat:early:{$account->id}:{$e['mid']}";
            $kept = Cache::get($key);
            if (!$kept || self::RANK[$new] > self::RANK[$kept['status']]) {
                Cache::put($key, ['status' => $new, 'error' => $e['error'] ?? null], now()->addDay());
            }

            return false;
        }

        return $this->advance($message, $new, $e['error'] ?? null);
    }

    public function advance(ChatMessage $message, string $new, ?string $error = null): bool
    {
        if ($new !== 'failed' && self::RANK[$new] <= self::RANK[$message->status]) {
            return false;
        }
        if ($new === 'failed' && in_array($message->status, ['delivered', 'read'], true)) {
            return false;
        }
        $message->status = $new;
        $message->error = $new === 'failed' ? mb_substr((string) $error, 0, 500) : null;
        $message->save();
        event(new ChatUpdated($message->chat_account_id, 'message.status', $message->chat_conversation_id, $message->id));

        return true;
    }

    /** Apply a status that arrived before the message id was known. */
    public function applyEarly(ChatMessage $message): void
    {
        $early = Cache::pull("chat:early:{$message->chat_account_id}:{$message->external_id}");
        if ($early) {
            $this->advance($message, $early['status'], $early['error']);
        }
    }

    private function isDuplicate(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['23000', '23505', '19'], true) || str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'Duplicate');
    }
}
