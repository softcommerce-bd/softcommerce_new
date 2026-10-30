<?php

namespace App\Models;

use App\Casts\CompactJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sales funnel page. Holds the editable draft and points at the published
 * snapshot; it never stores product, stock or order data of its own.
 */
class Funnel extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_UNPUBLISHED = 'unpublished';
    public const STATUS_ARCHIVED = 'archived';

    protected $guarded = ['id'];

    protected $casts = [
        'draft_content' => CompactJson::class,
        'settings' => CompactJson::class,
        'product_ids' => CompactJson::class,
        'draft_revision' => 'integer',
        'draft_updated_at' => 'datetime',
        'published_at' => 'datetime',
        'unpublished_at' => 'datetime',
        'archived_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'unpublish_at' => 'datetime',
    ];

    /**
     * Whether the publishing columns (sales_funnel_v4_publishing.sql) are installed.
     * Lets the admin keep working if the code is deployed a moment before the SQL.
     */
    public static function hasPublishingColumns(): bool
    {
        static $known = null;
        if ($known === null) {
            try {
                $known = \Illuminate\Support\Facades\Schema::hasColumn('funnels', 'unpublish_at');
            } catch (\Throwable $e) {
                $known = false;
            }
        }

        return $known;
    }

    public function publishedVersion()
    {
        return $this->belongsTo(FunnelVersion::class, 'published_version_id');
    }

    public function versions()
    {
        return $this->hasMany(FunnelVersion::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

        return $query->where(function ($q) use ($like, $term) {
            $q->where('title', 'like', $like)
                ->orWhere('internal_name', 'like', $like)
                ->orWhere('slug', 'like', $like);
            if (ctype_digit($term)) {
                $q->orWhere('id', (int) $term);
            }
        });
    }

    /**
     * Whether customers can open the page right now. A scheduled funnel goes
     * live by itself once its time has passed, and a funnel with an end time
     * disappears once that time has passed, even if the scheduler has not run
     * yet, so the public read never depends on cron.
     */
    public function isLive(): bool
    {
        if ($this->published_version_id === null) {
            return false;
        }

        if ($this->unpublish_at !== null && $this->unpublish_at->isPast()) {
            return false;
        }

        if ($this->status === self::STATUS_PUBLISHED) {
            return true;
        }

        return $this->status === self::STATUS_SCHEDULED
            && $this->scheduled_at !== null
            && $this->scheduled_at->isPast();
    }

    public function hasUnpublishedChanges(): bool
    {
        if ($this->published_version_id === null) {
            return true;
        }

        $published = $this->relationLoaded('publishedVersion')
            ? $this->publishedVersion
            : $this->publishedVersion()->first(['id', 'content_hash']);

        return $published === null || $published->content_hash !== $this->draft_hash;
    }

    public function publicPath(): string
    {
        return '/funnel/' . $this->slug;
    }

    public function publicUrl(): string
    {
        return config('funnel.public_url') . $this->publicPath();
    }
}
