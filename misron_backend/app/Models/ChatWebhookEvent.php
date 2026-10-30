<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatWebhookEvent extends Model
{
    protected $table = 'chat_webhook_events';
    protected $guarded = ['id'];
    protected $casts = ['processed_at' => 'datetime'];
}
