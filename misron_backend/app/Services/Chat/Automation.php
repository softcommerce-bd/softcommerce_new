<?php

namespace App\Services\Chat;

use App\Models\ChatAccount;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatSetting;
use App\Jobs\Chat\SendChatMessage;
use Illuminate\Support\Facades\DB;

/**
 * Configurable, per-installation automation. Only ever replies to a customer who
 * just wrote to us (inside the provider window); it never opens a conversation
 * and there is no bulk sending.
 *
 * Settings (chat_settings.automation): greeting {enabled, text}, away {enabled, text},
 * hours {start "09:00", end "18:00", days [0-6], tz}, auto_close_days, unanswered_alert_minutes.
 */
class Automation
{
    public static function settings(): array
    {
        $row = DB::table('chat_settings')->where('key', 'automation')->value('value');

        return (json_decode((string) $row, true) ?: []) + ['greeting' => ['enabled' => false, 'text' => ''], 'away' => ['enabled' => false, 'text' => ''],
            'hours' => ['start' => '09:00', 'end' => '18:00', 'days' => [0, 1, 2, 3, 4, 5, 6], 'tz' => config('app.timezone')], 'auto_close_days' => 0, 'unanswered_alert_minutes' => 0];
    }

    public static function save(array $settings): void
    {
        DB::table('chat_settings')->updateOrInsert(['key' => 'automation'], ['value' => json_encode($settings), 'updated_at' => now(), 'created_at' => now()]);
    }

    public function withinHours(?\DateTimeInterface $at = null): bool
    {
        $h = self::settings()['hours'];
        $now = \Carbon\Carbon::instance($at ?? now())->setTimezone($h['tz'] ?: config('app.timezone'));
        if (!in_array((int) $now->dayOfWeek, array_map('intval', $h['days']), true)) {
            return false;
        }

        return $now->format('H:i') >= $h['start'] && $now->format('H:i') < $h['end'];
    }

    public function afterInbound(ChatAccount $account, ?ChatConversation $conversation): void
    {
        if (!$conversation) {
            return;
        }
        $s = self::settings();
        $inHours = $this->withinHours();
        $rule = $inHours ? $s['greeting'] : $s['away'];
        if (empty($rule['enabled']) || trim((string) ($rule['text'] ?? '')) === '') {
            return;
        }
        // Once per 12 hours per conversation: no loops, no spam.
        $recent = ChatMessage::where('chat_conversation_id', $conversation->id)->where('direction', 'out')
            ->where('client_ref', 'like', 'auto:%')->where('created_at', '>=', now()->subHours(12))->exists();
        if ($recent) {
            return;
        }

        $message = ChatMessage::create([
            'chat_conversation_id' => $conversation->id, 'chat_account_id' => $account->id, 'direction' => 'out', 'type' => 'text',
            'body' => $rule['text'], 'status' => 'queued', 'client_ref' => 'auto:' . ($inHours ? 'g' : 'a') . ':' . now()->timestamp,
        ]);
        SendChatMessage::dispatch($message->id);
    }
}
