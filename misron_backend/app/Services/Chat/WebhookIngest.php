<?php

namespace App\Services\Chat;

use App\Jobs\Chat\ProcessChatWebhookEvent;
use App\Models\ChatWebhookEvent;
use Illuminate\Database\QueryException;

/** Stores a verified provider event once and queues it. Redelivery of the same event is a harmless no-op. */
class WebhookIngest
{
    /** @return bool true when newly stored, false when it was a duplicate */
    public function accept(string $source, string $platform, string $eventKey, array $payload): bool
    {
        try {
            $event = ChatWebhookEvent::create([
                'source' => $source, 'platform' => $platform, 'event_key' => mb_substr($eventKey, 0, 190),
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'status' => 'pending',
            ]);
        } catch (QueryException $e) {
            return false;
        }

        ProcessChatWebhookEvent::dispatch($event->id);

        return true;
    }
}
