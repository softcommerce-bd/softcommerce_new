<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    protected $table = 'chat_conversations';
    protected $guarded = ['id'];
    protected $casts = ['last_message_at' => 'datetime', 'last_inbound_at' => 'datetime', 'first_response_at' => 'datetime', 'resolved_at' => 'datetime', 'tags' => 'array'];

    public function account() { return $this->belongsTo(ChatAccount::class, 'chat_account_id'); }
    public function messages() { return $this->hasMany(ChatMessage::class); }
    public function notes() { return $this->hasMany(ChatNote::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function customer() { return $this->belongsTo(User::class, 'user_id'); }
    public function orders() { return $this->hasMany(ChatConversationOrder::class); }
}
