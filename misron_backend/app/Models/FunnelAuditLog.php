<?php

namespace App\Models;

use App\Casts\CompactJson;
use Illuminate\Database\Eloquent\Model;

class FunnelAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'meta' => CompactJson::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
