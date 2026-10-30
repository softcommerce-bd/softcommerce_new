<?php

namespace App\Models;

use App\Casts\CompactJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single top-level block saved for reuse in other funnels.
 */
class FunnelSavedSection extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'content' => CompactJson::class,
    ];
}
