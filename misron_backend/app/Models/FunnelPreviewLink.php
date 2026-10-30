<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A secret link that shows one funnel's draft to someone without an account.
 * Only the hash of the secret is stored.
 */
class FunnelPreviewLink extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'use_count' => 'integer',
    ];

    public function funnel()
    {
        return $this->belongsTo(Funnel::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
