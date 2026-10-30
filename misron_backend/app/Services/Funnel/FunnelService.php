<?php

namespace App\Services\Funnel;

use App\Casts\CompactJson;
use App\Models\Funnel;
use App\Models\FunnelTemplate;
use App\Models\FunnelVersion;
use App\Models\Product;
use App\Models\User;
use App\Services\Funnel\Templates\TemplateLibrary;
use App\Services\Funnel\Offers\OfferAudit;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Funnel lifecycle: create, edit the draft, publish, unpublish, archive,
 * duplicate, roll back and delete.
 *
 * Publishing copies the validated draft into a new immutable FunnelVersion and
 * points the funnel at it in one transaction, so customers always see either
 * the previous version or the new one, never a half-saved draft.
 */
class FunnelService
{
    private $validator;
    private $migrator;
    private $audit;
    private $offers;
    private $revalidator;
    private $seo;
    private $templates;

    public function __construct(PageValidator $validator, SchemaMigrator $migrator, FunnelAudit $audit, OfferAudit $offers, FunnelRevalidator $revalidator, FunnelSeo $seo, TemplateLibrary $templates)
    {
        $this->seo = $seo;
        $this->templates = $templates;
        $this->validator = $validator;
        $this->migrator = $migrator;
        $this->audit = $audit;
        $this->offers = $offers;
        $this->revalidator = $revalidator;
    }

    /**
     * @param array $data title, internal_name, slug, description, content, source_template_id
     */
    public function create(array $data, User $user): Funnel
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new FunnelException('Enter a title for the funnel.', 'invalid_title');
        }

        $document = $this->migrator->emptyDocument();
        $productIds = [];
        if (!empty($data['content'])) {
            $result = $this->validator->validate($data['content'], PageValidator::MODE_DRAFT);
            if (!$result->isValid()) {
                throw FunnelException::invalid($result);
            }
            $document = $result->document;
            $productIds = $result->productIdList();
        }

        $slug = isset($data['slug']) && trim((string) $data['slug']) !== ''
            ? $this->assertSlugAvailable($this->normalizeSlug((string) $data['slug']))
            : $this->uniqueSlug($title);

        $funnel = $this->guardSlugRace(function () use ($data, $title, $slug, $document, $productIds, $user) {
            return Funnel::create([
                'internal_name' => trim((string) ($data['internal_name'] ?? '')) ?: $title,
                'title' => $title,
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'status' => Funnel::STATUS_DRAFT,
                'draft_content' => $document,
                'draft_revision' => 1,
                'draft_hash' => $this->hash($document),
                'draft_updated_at' => now(),
                'product_ids' => $productIds,
                'source_template_id' => $data['source_template_id'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });

        $this->audit->log($funnel, 'created', $user, ['slug' => $funnel->slug]);

        return $funnel;
    }

    /**
     * A new funnel from a template, with the chosen products already in place.
     *
     * @param array<string, array{product_id: int, variant?: ?string}> $choices slot key => product
     */
    public function createFromTemplate(array $data, FunnelTemplate $template, array $choices, User $user): Funnel
    {
        $data['content'] = $this->templates->documentWithProducts($template, $choices);
        $this->assertProductsExist($choices);
        $data['source_template_id'] = $template->id;

        return $this->create($data, $user);
    }

    /**
     * Puts a product into one slot of the funnel's template (the editor's setup
     * checklist). Goes through saveDraft(), so the revision guard applies.
     *
     * @return array{0: Funnel, 1: ValidationResult}
     */
    public function fillTemplateSlot(Funnel $funnel, string $slotKey, int $productId, ?string $variant, ?int $expectedRevision, User $user): array
    {
        $template = $funnel->source_template_id ? FunnelTemplate::find($funnel->source_template_id) : null;
        if (!$template) {
            throw new FunnelException('This funnel was not made from a template.', 'no_template', 422);
        }
        $slot = $this->templates->findSlot(($template->product_slots ?: [])['slots'] ?? [], $slotKey);
        if (!$slot) {
            throw new FunnelException("The template has no product slot \"{$slotKey}\".", 'unknown_slot', 422);
        }
        $this->assertProductsExist([$slotKey => ['product_id' => $productId]]);

        $document = $this->templates->fillSlot($funnel->draft_content ?: $this->migrator->emptyDocument(), $slot, $productId, $variant);

        return $this->saveDraft($funnel, $document, $expectedRevision, $user);
    }

    private function assertProductsExist(array $choices): void
    {
        foreach ($choices as $slotKey => $choice) {
            $id = (int) ($choice['product_id'] ?? 0);
            if ($id < 1 || !Product::whereKey($id)->exists()) {
                throw new FunnelException('That product does not exist.', 'unknown_product', 422, ['errors' => [['path' => "slots.{$slotKey}", 'code' => 'unknown_product', 'message' => 'That product does not exist.']]]);
            }
        }
    }

    /**
     * Title, slug, description and SEO fields. The page document is saved
     * through saveDraft() instead.
     */
    public function updateDetails(Funnel $funnel, array $data, User $user): Funnel
    {
        $this->assertNotArchived($funnel);

        $changes = [];
        foreach (['title', 'internal_name', 'description', 'meta_title', 'meta_description'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field] === null ? null : trim((string) $data[$field]);
                if ($field === 'title' && ($value === null || $value === '')) {
                    throw new FunnelException('Enter a title for the funnel.', 'invalid_title');
                }
                $changes[$field] = $value === '' && $field !== 'internal_name' ? null : $value;
            }
        }
        foreach (['og_image_upload_id', 'thumbnail_upload_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $changes[$field] = $data[$field] === null ? null : (int) $data[$field];
            }
        }
        if (array_key_exists('internal_name', $changes) && ($changes['internal_name'] ?? '') === '') {
            $changes['internal_name'] = $changes['title'] ?? $funnel->title;
        }

        $seoInput = array_intersect_key($data, array_flip(FunnelSeo::SETTINGS));
        if ($seoInput !== []) {
            $merged = $this->seo->merge($funnel, $seoInput);
            if ($merged !== ($funnel->settings ?: [])) {
                $changes['settings'] = $merged;
            }
        }

        $oldSlug = $funnel->slug;
        if (array_key_exists('slug', $data) && trim((string) $data['slug']) !== $funnel->slug) {
            $changes['slug'] = $this->assertSlugAvailable($this->normalizeSlug((string) $data['slug']), $funnel->id);
        }

        if ($changes === []) {
            return $funnel;
        }

        $funnel->fill($changes);
        $funnel->updated_by = $user->id;
        $this->guardSlugRace(function () use ($funnel) {
            $funnel->save();
            return $funnel;
        });

        $meta = ['fields' => array_keys($changes)];
        if (isset($changes['slug'])) {
            $meta['old_slug'] = $oldSlug;
            $meta['new_slug'] = $changes['slug'];
        }
        $this->audit->log($funnel, 'details_updated', $user, $meta);
        $this->revalidator->pages([$oldSlug, $funnel->slug]);

        return $funnel;
    }

    /**
     * Autosave and manual save of the working copy. Never touches what
     * customers see. $expectedRevision guards against two tabs overwriting
     * each other; pass null only for server-side writes.
     *
     * @return array{0: Funnel, 1: ValidationResult}
     */
    public function saveDraft(Funnel $funnel, $content, ?int $expectedRevision, User $user): array
    {
        $result = $this->validator->validate($content, PageValidator::MODE_DRAFT);
        if (!$result->isValid()) {
            throw FunnelException::invalid($result);
        }

        $document = $result->document;
        $hash = $this->hash($document);

        $funnel = DB::transaction(function () use ($funnel, $expectedRevision, $document, $hash, $result, $user) {
            /** @var Funnel $locked */
            $locked = Funnel::whereKey($funnel->id)->lockForUpdate()->firstOrFail();
            $this->assertNotArchived($locked);

            if ($expectedRevision !== null && $expectedRevision !== $locked->draft_revision) {
                throw FunnelException::conflict($locked->draft_revision);
            }

            if ($locked->draft_hash === $hash) {
                return $locked;
            }

            $locked->draft_content = $document;
            $locked->draft_hash = $hash;
            $locked->draft_revision = $locked->draft_revision + 1;
            $locked->draft_updated_at = now();
            $locked->product_ids = $result->productIdList();
            $locked->updated_by = $user->id;
            $locked->save();

            return $locked;
        });

        return [$funnel, $result];
    }

    /**
     * Checks the draft as if publishing, without changing anything.
     */
    public function check(Funnel $funnel, $content = null): ValidationResult
    {
        $result = $this->validator->validate($content ?? $funnel->draft_content, PageValidator::MODE_PUBLISH);
        if ($result->isValid()) {
            $this->offers->audit($result);
        }

        return $result;
    }

    /**
     * @return array{0: Funnel, 1: FunnelVersion, 2: ValidationResult}
     */
    public function publish(Funnel $funnel, User $user, ?string $label = null): array
    {
        $out = DB::transaction(function () use ($funnel, $user, $label) {
            /** @var Funnel $locked */
            $locked = Funnel::whereKey($funnel->id)->lockForUpdate()->firstOrFail();
            $this->assertNotArchived($locked);

            [$version, $result, $created] = $this->snapshot($locked, $user, $label);

            // Publishing an unchanged draft again is a no-op, which keeps a
            // double-clicked Publish button from creating empty versions.
            if (!$created && $locked->status === Funnel::STATUS_PUBLISHED) {
                return [$locked, $version, $result];
            }

            $locked->forceFill([
                'published_version_id' => $version->id,
                'status' => Funnel::STATUS_PUBLISHED,
                'published_at' => now(),
                'unpublished_at' => null,
                'scheduled_at' => null,
                // A planned end time still in the future survives a re-publish; a past one is dropped.
                'unpublish_at' => $locked->unpublish_at && $locked->unpublish_at->isFuture() ? $locked->unpublish_at : null,
                'draft_content' => $result->document,
                'draft_hash' => $version->content_hash,
                'product_ids' => $result->productIdList(),
                'updated_by' => $user->id,
            ])->save();
            $locked->setRelation('publishedVersion', $version);

            $this->audit->log($locked, 'published', $user, ['version' => $version->version_number]);

            return [$locked, $version, $result];
        });

        $this->revalidator->pages([$out[0]->slug]);

        return $out;
    }

    /**
     * Publishes the draft at a future time instead of now. The version is
     * frozen now (what is scheduled is exactly what was checked), and the page
     * goes live on its own once $at has passed. A funnel that is live already
     * keeps running; use publish() to update it.
     *
     * @return array{0: Funnel, 1: FunnelVersion, 2: ValidationResult}
     */
    public function schedule(Funnel $funnel, Carbon $at, ?Carbon $until, User $user, ?string $label = null): array
    {
        $out = DB::transaction(function () use ($funnel, $at, $until, $user, $label) {
            /** @var Funnel $locked */
            $locked = Funnel::whereKey($funnel->id)->lockForUpdate()->firstOrFail();
            $this->assertNotArchived($locked);

            if ($locked->status === Funnel::STATUS_PUBLISHED && $locked->isLive()) {
                throw FunnelException::state('This funnel is live already. Update it now, or unpublish it before scheduling a new start.');
            }
            $this->assertWindow($at, $until);

            [$version, $result] = $this->snapshot($locked, $user, $label);

            $locked->forceFill([
                'published_version_id' => $version->id,
                'status' => Funnel::STATUS_SCHEDULED,
                'scheduled_at' => $at,
                'unpublish_at' => $until,
                'unpublished_at' => null,
                'draft_content' => $result->document,
                'draft_hash' => $version->content_hash,
                'product_ids' => $result->productIdList(),
                'updated_by' => $user->id,
            ])->save();
            $locked->setRelation('publishedVersion', $version);

            $this->audit->log($locked, 'scheduled', $user, [
                'version' => $version->version_number,
                'at' => $at->toIso8601String(),
                'until' => $until ? $until->toIso8601String() : null,
            ]);

            return [$locked, $version, $result];
        });

        $this->revalidator->pages([$out[0]->slug]);

        return $out;
    }

    /** Takes a scheduled funnel back to draft (or unpublished if it was published before). */
    public function cancelSchedule(Funnel $funnel, User $user): Funnel
    {
        return $this->transition($funnel, $user, 'schedule_cancelled', function (Funnel $locked) {
            if ($locked->status !== Funnel::STATUS_SCHEDULED) {
                throw FunnelException::state('This funnel is not scheduled.');
            }
            $locked->status = $locked->published_at ? Funnel::STATUS_UNPUBLISHED : Funnel::STATUS_DRAFT;
            if (!$locked->published_at) {
                $locked->published_version_id = null;
            }
            $locked->scheduled_at = null;
            $locked->unpublish_at = null;
        });
    }

    /**
     * Sets or clears the moment a live (or scheduled) page goes offline by itself.
     */
    public function setEndTime(Funnel $funnel, ?Carbon $until, User $user): Funnel
    {
        return $this->transition($funnel, $user, $until ? 'end_time_set' : 'end_time_cleared', function (Funnel $locked) use ($until) {
            if (!in_array($locked->status, [Funnel::STATUS_PUBLISHED, Funnel::STATUS_SCHEDULED], true)) {
                throw FunnelException::state('Only a published or scheduled funnel can have an end time.');
            }
            if ($until !== null) {
                $this->assertWindow($locked->status === Funnel::STATUS_SCHEDULED ? $locked->scheduled_at : null, $until);
            }
            $locked->unpublish_at = $until;
        });
    }

    /**
     * Brings the stored status in line with the clock: scheduled pages that are
     * due become published, and published pages past their end time become
     * unpublished. The public read already treats them that way (see
     * Funnel::isLive); this makes the admin lists and the audit trail agree.
     * Safe to run at any time, by any number of workers at once.
     *
     * @return string[] slugs whose public page changed
     */
    public function reconcile(): array
    {
        if (!Funnel::hasPublishingColumns()) {
            return [];
        }

        $touched = [];
        $now = now();

        $due = Funnel::query()
            ->where(function ($q) use ($now) {
                $q->where('status', Funnel::STATUS_SCHEDULED)->where('scheduled_at', '<=', $now);
            })
            ->orWhere(function ($q) use ($now) {
                $q->whereIn('status', [Funnel::STATUS_PUBLISHED, Funnel::STATUS_SCHEDULED])->where('unpublish_at', '<=', $now);
            })
            ->pluck('id');

        foreach ($due as $id) {
            $slug = DB::transaction(function () use ($id) {
                /** @var Funnel|null $locked */
                $locked = Funnel::whereKey($id)->lockForUpdate()->first();
                if (!$locked) {
                    return null;
                }
                $now = now();

                // Window already over: it never went live, or its end has come.
                if ($locked->unpublish_at !== null && $locked->unpublish_at->lte($now)
                    && in_array($locked->status, [Funnel::STATUS_PUBLISHED, Funnel::STATUS_SCHEDULED], true)) {
                    $from = $locked->status;
                    $locked->forceFill([
                        'status' => Funnel::STATUS_UNPUBLISHED,
                        'unpublished_at' => $locked->unpublish_at,
                        'unpublish_at' => null,
                        'scheduled_at' => null,
                    ])->save();
                    $this->audit->log($locked, 'went_offline', null, ['from' => $from, 'automatic' => true]);

                    return $locked->slug;
                }

                if ($locked->status === Funnel::STATUS_SCHEDULED && $locked->scheduled_at !== null && $locked->scheduled_at->lte($now)) {
                    $locked->forceFill([
                        'status' => Funnel::STATUS_PUBLISHED,
                        'published_at' => $locked->scheduled_at,
                        'scheduled_at' => null,
                    ])->save();
                    $this->audit->log($locked, 'went_live', null, ['automatic' => true]);

                    return $locked->slug;
                }

                return null;
            });

            if ($slug !== null) {
                $touched[] = $slug;
            }
        }

        $this->revalidator->pages($touched);

        return $touched;
    }

    /**
     * Validates the locked funnel's draft as a publication and returns the
     * version that holds it: the current one when its content is identical,
     * otherwise a new immutable row.
     *
     * @return array{0: FunnelVersion, 1: ValidationResult, 2: bool} version, checks, whether a version was created
     */
    private function snapshot(Funnel $locked, User $user, ?string $label): array
    {
        $result = $this->validator->validate($locked->draft_content, PageValidator::MODE_PUBLISH);
        if ($result->isValid()) {
            // The shop's own data: products that exist, variations that exist.
            $this->offers->audit($result);
        }
        if (!$result->isValid()) {
            throw FunnelException::invalid($result);
        }

        $document = $result->document;
        $hash = $this->hash($document);

        $current = $locked->published_version_id ? FunnelVersion::find($locked->published_version_id) : null;
        if ($current && $current->content_hash === $hash) {
            return [$current, $result, false];
        }

        $version = FunnelVersion::create([
            'funnel_id' => $locked->id,
            'version_number' => $this->nextVersionNumber($locked),
            'kind' => FunnelVersion::KIND_PUBLISHED,
            'schema_version' => $document['schemaVersion'],
            'content' => $document,
            'content_hash' => $hash,
            'label' => $label,
            'created_by' => $user->id,
        ]);

        return [$version, $result, true];
    }

    /** A start in the future, and an end after both now and the start. */
    private function assertWindow(?Carbon $from, ?Carbon $until): void
    {
        if ($from !== null && $from->lte(now())) {
            throw new FunnelException('Choose a start time in the future.', 'invalid_schedule', 422);
        }
        if ($until !== null && $until->lte($from ?? now())) {
            throw new FunnelException('The end time must be after the start time, and in the future.', 'invalid_schedule', 422);
        }
    }

    public function unpublish(Funnel $funnel, User $user): Funnel
    {
        return $this->transition($funnel, $user, 'unpublished', function (Funnel $locked) {
            if (!in_array($locked->status, [Funnel::STATUS_PUBLISHED, Funnel::STATUS_SCHEDULED], true)) {
                throw FunnelException::state('Only a published or scheduled funnel can be unpublished.');
            }
            $locked->status = Funnel::STATUS_UNPUBLISHED;
            $locked->unpublished_at = now();
            $locked->scheduled_at = null;
            $locked->unpublish_at = null;
        });
    }

    public function archive(Funnel $funnel, User $user): Funnel
    {
        return $this->transition($funnel, $user, 'archived', function (Funnel $locked) {
            if ($locked->status === Funnel::STATUS_ARCHIVED) {
                throw FunnelException::state('This funnel is already archived.');
            }
            $locked->status = Funnel::STATUS_ARCHIVED;
            $locked->archived_at = now();
            $locked->scheduled_at = null;
            $locked->unpublish_at = null;
        });
    }

    /**
     * Brings an archived funnel back as not public. It is never re-published
     * implicitly: the merchant publishes again on purpose.
     */
    public function restore(Funnel $funnel, User $user): Funnel
    {
        return $this->transition($funnel, $user, 'restored', function (Funnel $locked) {
            if ($locked->status !== Funnel::STATUS_ARCHIVED) {
                throw FunnelException::state('Only an archived funnel can be restored.');
            }
            $locked->status = $locked->published_version_id ? Funnel::STATUS_UNPUBLISHED : Funnel::STATUS_DRAFT;
            $locked->archived_at = null;
        });
    }

    public function duplicate(Funnel $source, User $user): Funnel
    {
        $document = $this->migrator->migrate($source->draft_content ?: $this->migrator->emptyDocument());
        $title = Str::limit($source->title . ' (Copy)', 200, '');

        $copy = $this->guardSlugRace(function () use ($source, $title, $document, $user) {
            return Funnel::create([
                'internal_name' => Str::limit($source->internal_name . ' (Copy)', 150, ''),
                'title' => $title,
                'slug' => $this->uniqueSlug($source->slug . '-copy'),
                'description' => $source->description,
                'status' => Funnel::STATUS_DRAFT,
                'draft_content' => $document,
                'draft_revision' => 1,
                'draft_hash' => $this->hash($document),
                'draft_updated_at' => now(),
                'meta_title' => $source->meta_title,
                'meta_description' => $source->meta_description,
                'og_image_upload_id' => $source->og_image_upload_id,
                'thumbnail_upload_id' => $source->thumbnail_upload_id,
                'settings' => $source->settings,
                'product_ids' => $source->product_ids,
                'source_template_id' => $source->source_template_id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });

        $this->audit->log($copy, 'created', $user, ['duplicated_from' => $source->id, 'slug' => $copy->slug]);
        $this->audit->log($source, 'duplicated', $user, ['copy_id' => $copy->id]);

        return $copy;
    }

    /**
     * Soft delete. Orders keep their funnel id; nothing outside the funnel
     * tables is touched. The slug is released so a new funnel can use it.
     */
    public function delete(Funnel $funnel, User $user): void
    {
        $oldSlug = $funnel->slug;
        DB::transaction(function () use ($funnel, $user) {
            /** @var Funnel $locked */
            $locked = Funnel::whereKey($funnel->id)->lockForUpdate()->firstOrFail();
            $oldSlug = $locked->slug;

            // "--" never appears in a valid slug, so a released slug cannot
            // collide with one a merchant types.
            $locked->slug = Str::limit($oldSlug, 50, '') . '--deleted-' . $locked->id;
            $locked->updated_by = $user->id;
            $locked->save();
            $locked->delete();

            $this->audit->log($locked, 'deleted', $user, ['slug' => $oldSlug]);
        });

        $this->revalidator->pages([$oldSlug]);
    }

    /**
     * Copies a stored version into the draft, keeping the current draft as a
     * restore point first. Publishing the result is a separate, explicitly
     * authorized step.
     */
    public function rollback(Funnel $funnel, FunnelVersion $version, User $user): Funnel
    {
        if ((int) $version->funnel_id !== (int) $funnel->id) {
            throw new FunnelException('That version belongs to another funnel.', 'version_mismatch', 404);
        }

        $document = $this->migrator->migrate($version->content);
        $result = $this->validator->validate($document, PageValidator::MODE_DRAFT);
        if (!$result->isValid()) {
            throw FunnelException::invalid($result);
        }
        $document = $result->document;
        $hash = $this->hash($document);

        return DB::transaction(function () use ($funnel, $version, $document, $hash, $result, $user) {
            /** @var Funnel $locked */
            $locked = Funnel::whereKey($funnel->id)->lockForUpdate()->firstOrFail();
            $this->assertNotArchived($locked);

            if ($locked->draft_hash !== $hash) {
                $current = $locked->draft_content ?: $this->migrator->emptyDocument();
                FunnelVersion::create([
                    'funnel_id' => $locked->id,
                    'version_number' => $this->nextVersionNumber($locked),
                    'kind' => FunnelVersion::KIND_RESTORE_POINT,
                    'schema_version' => (int) ($current['schemaVersion'] ?? $this->migrator->currentVersion()),
                    'content' => $current,
                    'content_hash' => $locked->draft_hash ?: $this->hash($current),
                    'label' => 'Before restoring version ' . $version->version_number,
                    'created_by' => $user->id,
                ]);
                $this->pruneRestorePoints($locked);
            }

            $locked->draft_content = $document;
            $locked->draft_hash = $hash;
            $locked->draft_revision = $locked->draft_revision + 1;
            $locked->draft_updated_at = now();
            $locked->product_ids = $result->productIdList();
            $locked->updated_by = $user->id;
            $locked->save();

            $this->audit->log($locked, 'rolled_back', $user, ['version' => $version->version_number]);

            return $locked;
        });
    }

    public function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $min = (int) config('funnel.slug.min');
        $max = (int) config('funnel.slug.max');

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw FunnelException::slug('The URL may only use lowercase English letters, numbers and single dashes.');
        }
        if (strlen($slug) < $min || strlen($slug) > $max) {
            throw FunnelException::slug("The URL must be {$min} to {$max} characters long.");
        }
        if (in_array($slug, config('funnel.slug.reserved'), true)) {
            throw FunnelException::slug('That URL is reserved. Choose another one.');
        }

        return $slug;
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return Funnel::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, function ($q) use ($ignoreId) {
                $q->where('id', '!=', $ignoreId);
            })
            ->exists();
    }

    /**
     * A free slug derived from $text: "blender-offer", then "blender-offer-2"
     * and so on. Bengali titles are transliterated by Str::slug; anything that
     * still yields nothing usable gets a random slug.
     */
    public function uniqueSlug(string $text): string
    {
        $max = (int) config('funnel.slug.max');
        $base = Str::limit(trim(Str::slug($text), '-'), $max - 4, '');
        $base = trim($base, '-');

        if (strlen($base) < (int) config('funnel.slug.min') || in_array($base, config('funnel.slug.reserved'), true)) {
            $base = 'funnel-' . strtolower(Str::random(6));
        }

        $slug = $base;
        for ($i = 2; $this->slugExists($slug); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    public function hash(array $document): string
    {
        return sha1(CompactJson::encode($document));
    }

    private function assertSlugAvailable(string $slug, ?int $ignoreId = null): string
    {
        if ($this->slugExists($slug, $ignoreId)) {
            throw FunnelException::slug('Another funnel already uses that URL.');
        }

        return $slug;
    }

    private function assertNotArchived(Funnel $funnel): void
    {
        if ($funnel->status === Funnel::STATUS_ARCHIVED) {
            throw FunnelException::state('This funnel is archived. Restore it before making changes.');
        }
    }

    private function transition(Funnel $funnel, User $user, string $action, callable $apply): Funnel
    {
        $locked = DB::transaction(function () use ($funnel, $user, $action, $apply) {
            /** @var Funnel $locked */
            $locked = Funnel::whereKey($funnel->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $apply($locked);
            $locked->updated_by = $user->id;
            $locked->save();

            $this->audit->log($locked, $action, $user, ['from' => $from, 'to' => $locked->status]);

            return $locked;
        });

        $this->revalidator->pages([$locked->slug]);

        return $locked;
    }

    private function nextVersionNumber(Funnel $funnel): int
    {
        return (int) FunnelVersion::where('funnel_id', $funnel->id)->max('version_number') + 1;
    }

    private function pruneRestorePoints(Funnel $funnel): void
    {
        $keep = (int) config('funnel.versions.max_restore_points');
        $stale = FunnelVersion::where('funnel_id', $funnel->id)
            ->where('kind', FunnelVersion::KIND_RESTORE_POINT)
            ->orderByDesc('version_number')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($stale->isNotEmpty()) {
            FunnelVersion::whereIn('id', $stale)->delete();
        }
    }

    /**
     * Two requests can pick the same free slug at once. The unique index lets
     * one through; the other gets a readable error instead of a SQL one.
     */
    private function guardSlugRace(callable $write)
    {
        try {
            return $write();
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            throw FunnelException::slug('Another funnel just took that URL. Choose another one.');
        }
    }
}
