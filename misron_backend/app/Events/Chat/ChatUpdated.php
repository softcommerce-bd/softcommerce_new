<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "Something changed" signal on the private channel of one connected account.
 * It carries ids only, never message text or customer data: the browser then
 * re-reads through the authorized API, so a staff member who may hear the
 * channel but not see a particular conversation learns nothing from it.
 */
class ChatUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $accountId, public string $type, public ?int $conversationId = null, public ?int $messageId = null)
    {
    }

    public function broadcastOn()
    {
        return new PrivateChannel('chat.account.' . $this->accountId);
    }

    public function broadcastAs()
    {
        return 'chat.updated';
    }

    public function broadcastWith()
    {
        return ['type' => $this->type, 'conversation_id' => $this->conversationId, 'message_id' => $this->messageId];
    }
}
