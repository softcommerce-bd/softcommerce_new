<?php

namespace App\Jobs\Chat;

use App\Models\ChatWebhookEvent;
use App\Services\Chat\InboundProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessChatWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public function __construct(public int $eventId)
    {
    }

    public function backoff()
    {
        return [10, 30, 90, 270];
    }

    public function handle(InboundProcessor $processor)
    {
        $event = ChatWebhookEvent::find($this->eventId);
        if (!$event || $event->status === 'done') {
            return;
        }
        $event->increment('attempts');

        try {
            $processor->handle($event->platform, json_decode($event->payload, true) ?: []);
        } catch (\Throwable $e) {
            // Keep the event for the retry/dead-letter pass; the error text never contains payload or tokens.
            $event->forceFill(['status' => 'failed', 'error' => mb_substr(get_class($e) . ': ' . $e->getMessage(), 0, 500)])->save();
            throw $e;
        }

        $event->forceFill(['status' => 'done', 'error' => null, 'processed_at' => now()])->save();
    }

    public function failed(\Throwable $e)
    {
        ChatWebhookEvent::where('id', $this->eventId)->update(['status' => 'dead']);
    }
}
