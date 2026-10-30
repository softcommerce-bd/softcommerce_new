<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutOtp extends Model
{
    protected $fillable = [
        'phone',
        'otp',
        'temp_user_id',
        'user_id',
        'session_id',
        'is_verified',
        'expires_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'expires_at' => 'datetime',
    ];
}
