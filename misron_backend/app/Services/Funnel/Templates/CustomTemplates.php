<?php

namespace App\Services\Funnel\Templates;

use App\Models\Funnel;
use App\Models\FunnelTemplate;
use App\Models\User;
use App\Services\Funnel\FunnelAudit;
use App\Services\Funnel\FunnelException;
use App\Services\Funnel\SchemaMigrator;
use Illuminate\Support\Str;

/**
 * Templates made by the merchant from one of their own funnels ("Save as template").
 *
 * A saved template keeps the page's layout, wording, pictures and offer settings, but no
 * products: every offer is emptied, and the offers that held one product become product
 * slots, grouped by the product they held, so a campaign made from the template asks for
 * "Product 1", "Product 2" … exactly like the built-in templates do. Offers that held
 * several different products (bundles) and free gifts are emptied and chosen again in the
 * editor. Built-in templates (is_system) are never changed here.
 */
class CustomTemplates
{
    private SchemaMigrator $migrator;
    private FunnelAudit $audit;

    public function __construct(SchemaMigrator $migrator, FunnelAudit $audit)
    {
        $this->migrator = $migrator;
        $this->audit = $audit;
    }

    /** @return \Illuminate\Support\Collection<int, FunnelTemplate> */
    public function all()
    {
        return FunnelTemplate::where('is_system', false)->orderByDesc('updated_at')->orderByDesc('id')->get();
    }

    public function find($id): FunnelTemplate
    {
        $template = FunnelTemplate::where('is_system', false)->find((int) $id);
        if (!$template) {
            throw new FunnelException('Template not found.', 'not_found', 404);
        }

        return $template;
    }

    public function saveFunnel(Funnel $funnel, string $name, ?string $description, User $user): FunnelTemplate
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn("funnel_templates", "template_key")) {
            throw new FunnelException("Install sales_funnel_v4_publishing.sql first.", "not_installed", 409);
        }
        $name = $this->cleanName($name);
        $document = $this->migrator->migrate($funnel->draft_content ?: $this->migrator->emptyDocument());
        [$document, $slots, $todos] = $this->withoutProducts($document);
        $language = in_array($document['page']['settings']['language'] ?? null, TemplateLibrary::LANGUAGES, true)
            ? $document['page']['settings']['language'] : 'bn';

        $template = FunnelTemplate::create([
            'name' => $name,
            'slug' => 'custom-' . Str::lower(Str::random(12)),
            'language' => $language,
            'description' => $this->cleanDescription($description),
            'category' => 'custom',
            'thumbnail_upload_id' => $funnel->thumbnail_upload_id ?: $funnel->og_image_upload_id,
            'content' => $document,
            'product_slots' => ['slots' => $slots, 'todos' => $todos, 'tags' => []],
            'is_system' => false,
            'sort_order' => 1000,
            'created_by' => $user->id,
        ]);
        // The gallery groups templates by key; a merchant's template is its own group.
        $template->forceFill(['template_key' => 'custom-' . $template->id])->save();

        $this->audit->log($funnel, 'saved_as_template', $user, ['template_id' => $template->id, 'name' => $name]);

        return $template;
    }

    public function rename(FunnelTemplate $template, string $name, ?string $description): FunnelTemplate
    {
        $this->assertCustom($template);
        $template->fill(['name' => $this->cleanName($name), 'description' => $this->cleanDescription($description)])->save();

        return $template;
    }

    public function duplicate(FunnelTemplate $template, User $user): FunnelTemplate
    {
        $this->assertCustom($template);
        $copy = $template->replicate(['template_key']);
        $copy->fill([
            'name' => Str::limit($template->name . ' (Copy)', 150, ''),
            'slug' => 'custom-' . Str::lower(Str::random(12)),
            'created_by' => $user->id,
        ])->save();
        $copy->forceFill(['template_key' => 'custom-' . $copy->id])->save();

        return $copy;
    }

    /** Funnels already made from the template keep their pages; only the template goes. */
    public function delete(FunnelTemplate $template): void
    {
        $this->assertCustom($template);
        $template->delete();
    }

    /**
     * @return array{0: array, 1: array, 2: string[]} document without products, slots, notes
     */
    public function withoutProducts(array $document): array
    {
        $groups = [];
        $emptied = 0;

        $visit = function (array $nodes) use (&$visit, &$groups, &$emptied): array {
            foreach ($nodes as $i => $node) {
                foreach ($node['productBindings'] ?? [] as $j => $binding) {
                    $items = $binding['items'] ?? [];
                    $products = array_values(array_unique(array_map(fn ($item) => (int) ($item['productId'] ?? 0), $items)));
                    if (count($items) === 1 && $products[0] > 0) {
                        $groups[$products[0]][$binding['key']] = max(1, (int) ($items[0]['quantity'] ?? 1));
                    } elseif ($items !== []) {
                        $emptied++;
                    }
                    $nodes[$i]['productBindings'][$j]['items'] = [];
                    if (array_key_exists('giftProductId', $binding['offer'] ?? [])) {
                        unset($nodes[$i]['productBindings'][$j]['offer']['giftProductId'], $nodes[$i]['productBindings'][$j]['offer']['giftVariant']);
                        $emptied++;
                    }
                }
                if (!empty($node['children'])) {
                    $nodes[$i]['children'] = $visit($node['children']);
                }
            }

            return $nodes;
        };
        $document['page']['sections'] = $visit($document['page']['sections'] ?? []);

        $slots = [];
        $number = 0;
        foreach ($groups as $bindings) {
            $number++;
            $slots[] = [
                'key' => 'product_' . $number,
                'label' => count($groups) === 1 ? 'Product' : 'Product ' . $number,
                'hint' => count($bindings) > 1 ? 'Used in ' . count($bindings) . ' offers on the page.' : null,
                'bindings' => $bindings,
                'required' => true,
            ];
        }

        $todos = [];
        if ($emptied > 0) {
            $todos[] = 'Some offers held several products or a free gift. Choose them again in the editor.';
        }

        return [$document, $slots, $todos];
    }

    private function assertCustom(FunnelTemplate $template): void
    {
        if ($template->is_system) {
            throw new FunnelException('Built-in templates cannot be changed.', 'system_template', 422);
        }
    }

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if ($name === '') {
            throw new FunnelException('Give the template a name.', 'invalid_name', 422);
        }

        return mb_substr($name, 0, 150);
    }

    private function cleanDescription(?string $description): ?string
    {
        $description = trim((string) $description);

        return $description === '' ? null : mb_substr($description, 0, 500);
    }
}
