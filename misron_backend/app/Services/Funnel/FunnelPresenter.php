<?php

namespace App\Services\Funnel;

use App\Models\Funnel;
use App\Models\FunnelPreviewLink;
use App\Models\FunnelVersion;
use App\Models\User;

/**
 * Shapes funnels for the editor API. The list form leaves out the page
 * document, which can be large.
 */
class FunnelPresenter
{
    public const PERMISSIONS = ['view_funnels', 'add_funnel', 'edit_funnel', 'publish_funnel', 'delete_funnel'];

    private $seo;

    public function __construct(FunnelSeo $seo)
    {
        $this->seo = $seo;
    }

    public function summary(Funnel $funnel): array
    {
        return [
            'id' => $funnel->id,
            'title' => $funnel->title,
            'internal_name' => $funnel->internal_name,
            'slug' => $funnel->slug,
            'status' => $funnel->status,
            'is_live' => $funnel->isLive(),
            'public_url' => $funnel->publicUrl(),
            'has_unpublished_changes' => $funnel->hasUnpublishedChanges(),
            'product_ids' => $funnel->product_ids ?? [],
            'thumbnail_url' => $funnel->thumbnail_upload_id ? uploaded_asset($funnel->thumbnail_upload_id) : null,
            'published_at' => optional($funnel->published_at)->toIso8601String(),
            'scheduled_at' => optional($funnel->scheduled_at)->toIso8601String(),
            'unpublish_at' => optional($funnel->unpublish_at)->toIso8601String(),
            'draft_updated_at' => optional($funnel->draft_updated_at)->toIso8601String(),
            'updated_at' => optional($funnel->updated_at)->toIso8601String(),
            'created_at' => optional($funnel->created_at)->toIso8601String(),
        ];
    }

    public function detail(Funnel $funnel): array
    {
        $published = $funnel->publishedVersion;

        return $this->summary($funnel) + [
            'description' => $funnel->description,
            'meta_title' => $funnel->meta_title,
            'meta_description' => $funnel->meta_description,
            'og_image_upload_id' => $funnel->og_image_upload_id,
            'thumbnail_upload_id' => $funnel->thumbnail_upload_id,
            'settings' => $funnel->settings,
            'seo' => $this->seo->settings($funnel),
            'og_image_url' => $funnel->og_image_upload_id ? uploaded_asset($funnel->og_image_upload_id) : null,
            'template' => $funnel->source_template_id ? $this->templateInfo($funnel) : null,
            'draft' => [
                'content' => $funnel->draft_content,
                'revision' => $funnel->draft_revision,
                'hash' => $funnel->draft_hash,
                'updated_at' => optional($funnel->draft_updated_at)->toIso8601String(),
            ],
            'published_version' => $published ? $this->version($published) : null,
        ];
    }

    public function version(FunnelVersion $version, ?int $publishedId = null): array
    {
        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'kind' => $version->kind,
            'label' => $version->label,
            'schema_version' => $version->schema_version,
            'is_published' => $publishedId !== null && $publishedId === (int) $version->id,
            'created_by' => $version->relationLoaded('creator') && $version->creator ? $version->creator->name : null,
            'created_at' => optional($version->created_at)->toIso8601String(),
        ];
    }

    /** A preview link without its secret (which is shown once, when the link is made). */
    public function previewLink(FunnelPreviewLink $link): array
    {
        return [
            'id' => $link->id,
            'label' => $link->label,
            'expires_at' => optional($link->expires_at)->toIso8601String(),
            'revoked_at' => optional($link->revoked_at)->toIso8601String(),
            'last_used_at' => optional($link->last_used_at)->toIso8601String(),
            'use_count' => $link->use_count,
            'is_active' => $link->isActive(),
            'created_at' => optional($link->created_at)->toIso8601String(),
        ];
    }

    /** Product slots of the template this funnel was made from, for the editor's setup checklist. */
    private function templateInfo(Funnel $funnel): ?array
    {
        $template = \App\Models\FunnelTemplate::find($funnel->source_template_id);
        if (!$template) {
            return null;
        }

        // product_slots holds {slots: [...], todos: [...]}; see TemplateLibrary.
        $meta = $template->product_slots ?: [];

        return [
            'id' => $template->id,
            'key' => $template->template_key,
            'name' => $template->name,
            'language' => $template->language,
            'slots' => $meta['slots'] ?? [],
            'todos' => $meta['todos'] ?? [],
        ];
    }

    public function permissions(User $user): array
    {
        $out = [];
        foreach (self::PERMISSIONS as $permission) {
            $out[$permission] = $user->can($permission);
        }

        return $out;
    }
}
