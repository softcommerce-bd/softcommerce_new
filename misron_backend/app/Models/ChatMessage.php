<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';
    protected $guarded = ['id'];
    protected $casts = ['attachments' => 'array', 'provider_at' => 'datetime'];

    public function conversation() { return $this->belongsTo(ChatConversation::class, 'chat_conversation_id'); }
}
