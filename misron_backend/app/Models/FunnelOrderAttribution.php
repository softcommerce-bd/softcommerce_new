<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Where a funnel order came from (campaign, ad click, device) and whether it was
 * a staff test order. One row per combined order.
 */
class FunnelOrderAttribution extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_test' => 'boolean',
    ];

    public function combinedOrder()
    {
        return $this->belongsTo(CombinedOrder::class);
    }
}
