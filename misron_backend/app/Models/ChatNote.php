<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatNote extends Model
{
    protected $table = 'chat_notes';
    protected $guarded = ['id'];
    public function author() { return $this->belongsTo(User::class, 'user_id'); }
}
