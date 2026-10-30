<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatAccount extends Model
{
    protected $table = 'chat_accounts';
    protected $guarded = ['id'];
    protected $hidden = ['credentials'];
    protected $casts = ['inbox_enabled' => 'boolean', 'token_expires_at' => 'datetime', 'last_webhook_at' => 'datetime', 'last_message_at' => 'datetime', 'last_api_ok_at' => 'datetime', 'last_sync_at' => 'datetime', 'meta' => 'array'];

    public function setCredentialsAttribute($v) { $this->attributes['credentials'] = $v === null ? null : \Illuminate\Support\Facades\Crypt::encryptString(json_encode($v)); }

    public function secrets(): array
    {
        $raw = $this->attributes['credentials'] ?? null;
        return $raw ? (json_decode(\Illuminate\Support\Facades\Crypt::decryptString($raw), true) ?: []) : [];
    }

    public function staff() { return $this->belongsToMany(User::class, 'chat_account_user', 'chat_account_id', 'user_id'); }
    public function conversations() { return $this->hasMany(ChatConversation::class); }

    /** What the browser may see: never the credentials. */
    public function toPublic(): array
    {
        return $this->only(['id', 'platform', 'external_id', 'name', 'handle', 'status', 'inbox_enabled', 'token_expires_at', 'last_webhook_at', 'last_message_at', 'last_api_ok_at', 'last_error']);
    }
}
