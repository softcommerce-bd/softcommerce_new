<?php

namespace App\Services\Funnel;

use App\Models\FunnelSavedSection;
use App\Models\User;

/**
 * Sections a merchant saved to reuse on other pages ("Saved sections" in the
 * editor's library).
 *
 * A saved section is one top-level block with everything inside it, checked by
 * the same validator as a page, so a section that could not be saved on a page
 * cannot be saved here either. Product choices inside it (offers) are kept: they
 * point at products of this store, which every funnel shares. Inserting a saved
 * section is done by the editor, which gives every block a fresh id.
 */
class SavedSectionService
{
    public const MAX_BYTES = 262144;
    public const MAX_SECTIONS = 500;

    private PageValidator $validator;

    public function __construct(PageValidator $validator)
    {
        $this->validator = $validator;
    }

    public function list(?string $search, int $limit = 60)
    {
        $search = is_string($search) ? trim(mb_substr($search, 0, 100)) : '';

        return FunnelSavedSection::query()
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('category', 'like', $like));
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(max(1, min(200, $limit)))
            ->get();
    }

    /**
     * @param  mixed $node the block as the editor holds it
     * @throws FunnelException
     */
    public function create($node, $name, User $user): FunnelSavedSection
    {
        if (FunnelSavedSection::count() >= self::MAX_SECTIONS) {
            throw new FunnelException('You have saved the most sections allowed (' . self::MAX_SECTIONS . '). Delete some you no longer use first.', 'too_many_sections', 422);
        }

        $clean = $this->clean($node);

        return FunnelSavedSection::create([
            'name' => $this->name($name),
            'category' => $clean['type'],
            'content' => $clean,
            'created_by' => $user->id,
        ]);
    }

    public function rename(FunnelSavedSection $section, $name): FunnelSavedSection
    {
        $section->name = $this->name($name);
        $section->save();

        return $section;
    }

    public function duplicate(FunnelSavedSection $section, User $user): FunnelSavedSection
    {
        if (FunnelSavedSection::count() >= self::MAX_SECTIONS) {
            throw new FunnelException('You have saved the most sections allowed (' . self::MAX_SECTIONS . '). Delete some you no longer use first.', 'too_many_sections', 422);
        }

        return FunnelSavedSection::create([
            'name' => mb_substr($section->name . ' (copy)', 0, 150),
            'category' => $section->category,
            'content' => $section->content,
            'created_by' => $user->id,
        ]);
    }

    public function delete(FunnelSavedSection $section): void
    {
        $section->delete();
    }

    /**
     * The block, validated as the only section of a page and returned as the validator
     * normalized it.
     *
     * @throws FunnelException
     */
    private function clean($node): array
    {
        if (!is_array($node) || !is_string($node['type'] ?? null)) {
            throw new FunnelException('Choose a section to save.', 'invalid_section', 422);
        }
        $encoded = json_encode($node, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($encoded === false || strlen($encoded) > self::MAX_BYTES) {
            throw new FunnelException('This section is too large to save.', 'section_too_large', 422);
        }

        $result = $this->validator->validate([
            'schemaVersion' => (int) config('funnel.schema_version'),
            'page' => ['sections' => [$node]],
        ], PageValidator::MODE_DRAFT);

        if (!$result->isValid()) {
            throw new FunnelException('This section cannot be saved: ' . $result->summary(), 'invalid_section', 422, ['validation' => $result->toArray()]);
        }

        $sections = $result->document['page']['sections'] ?? [];
        if (count($sections) !== 1) {
            throw new FunnelException('Only a top-level section can be saved.', 'invalid_section', 422);
        }

        return $sections[0];
    }

    private function name($name): string
    {
        $name = is_string($name) ? trim(preg_replace('/\s+/u', ' ', $name)) : '';
        if (mb_strlen($name) < 1) {
            throw new FunnelException('Give the section a name.', 'invalid_name', 422, ['field' => 'name']);
        }

        return mb_substr($name, 0, 150);
    }
}
