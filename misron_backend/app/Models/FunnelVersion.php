<?php

namespace App\Models;

use App\Casts\CompactJson;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable snapshot of a funnel page. Never updated after insert.
 */
class FunnelVersion extends Model
{
    public const KIND_PUBLISHED = 'published';
    public const KIND_RESTORE_POINT = 'restore_point';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'content' => CompactJson::class,
        'version_number' => 'integer',
        'schema_version' => 'integer',
    ];

    public function funnel()
    {
        return $this->belongsTo(Funnel::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
