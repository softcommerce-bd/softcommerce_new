<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatConversationOrder extends Model
{
    protected $table = 'chat_conversation_orders';
    protected $guarded = ['id'];
    public function combinedOrder() { return $this->belongsTo(CombinedOrder::class, 'combined_order_id'); }
}
