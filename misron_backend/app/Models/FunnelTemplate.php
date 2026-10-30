<?php

namespace App\Models;

use App\Casts\CompactJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reusable page document. product_slots describes the bindings a merchant is
 * asked to fill when applying the template; no product ids are stored.
 */
class FunnelTemplate extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'content' => CompactJson::class,
        'product_slots' => CompactJson::class,
        'is_system' => 'boolean',
    ];
}
