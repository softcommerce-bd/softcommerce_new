<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One checkout submitted through a funnel page: the idempotency record that stops
 * a repeated submit from placing a second order, and the unchanged record of the
 * prices the customer agreed to. Holds no personal data.
 */
class FunnelCheckoutAttempt extends Model
{
    public const STARTED = 'started';
    public const PLACED = 'placed';
    public const REJECTED = 'rejected';
    public const ERRORED = 'errored';

    protected $guarded = ['id'];

    protected $casts = [
        'funnel_id' => 'integer',
        'funnel_version_id' => 'integer',
        'combined_order_id' => 'integer',
    ];

    public function snapshot(): ?array
    {
        return $this->quote_snapshot ? json_decode($this->quote_snapshot, true) : null;
    }
}
