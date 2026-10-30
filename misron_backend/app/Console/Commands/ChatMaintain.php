<?php

namespace App\Console\Commands;

use App\Jobs\Chat\ProcessChatWebhookEvent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatWebhookEvent;
use App\Services\Chat\Automation;
use Illuminate\Console\Command;

/** Scheduled housekeeping: re-queue stuck events, auto-close idle chats, apply retention. */
class ChatMaintain extends Command
{
    protected $signature = 'chat:maintain';
    protected $description = 'Unified Chat: retry stuck webhook events, auto-close idle conversations, apply data retention';

    public function handle()
    {
        // Events that were stored but never finished (worker died, queue outage): hand them back to the queue.
        ChatWebhookEvent::whereIn('status', ['pending', 'failed'])->where('attempts', '<', 8)
            ->where('updated_at', '<', now()->subMinutes(5))->limit(200)->get()
            ->each(fn ($e) => ProcessChatWebhookEvent::dispatch($e->id));

        $days = (int) (Automation::settings()['auto_close_days'] ?? 0);
        if ($days > 0) {
            ChatConversation::whereIn('status', ['open', 'pending'])->where('last_message_at', '<', now()->subDays($days))
                ->update(['status' => 'resolved', 'resolved_at' => now()]);
        }

        $keep = (int) config('unified_chat.retention_days');
        if ($keep > 0) {
            ChatMessage::where('created_at', '<', now()->subDays($keep))->delete();
            ChatWebhookEvent::where('created_at', '<', now()->subDays(min($keep, 30)))->whereIn('status', ['done', 'dead'])->delete();
        }

        return 0;
    }
}
