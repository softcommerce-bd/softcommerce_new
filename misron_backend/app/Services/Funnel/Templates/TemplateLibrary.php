<?php

namespace App\Services\Funnel\Templates;

use App\Models\FunnelTemplate;
use App\Services\Funnel\FunnelException;
use App\Services\Funnel\Templates\Definitions\BundleSaver;
use App\Services\Funnel\Templates\Definitions\ClassicSingleProduct;
use App\Services\Funnel\Templates\Definitions\FlashSale;
use App\Services\Funnel\Templates\Definitions\PremiumDark;
use App\Services\Funnel\Templates\Definitions\ProductShowcase;
use App\Services\Funnel\Templates\Definitions\QuickOrder;
use App\Services\Funnel\Templates\Definitions\SocialProof;
use App\Services\Funnel\Templates\Definitions\VideoStory;
use Carbon\Carbon;

/**
 * The built-in template gallery: eight designs, each in Bengali and English.
 *
 * Definitions live in code (Definitions/*) and are copied into the
 * funnel_templates table by sync() ("php artisan funnel:templates"), which can
 * be run again after any change: rows are matched on key and language, so a
 * template is updated in place and funnels already made from it are untouched.
 *
 * A template holds no products. Its "slots" say which offers need one; applying
 * a template with chosen products fills the slots, and the editor's setup
 * checklist does the same later.
 */
class TemplateLibrary
{
    public const LANGUAGES = ['bn', 'en'];

    /** @return Template[] in gallery order */
    public function definitions(): array
    {
        return [
            new ClassicSingleProduct(),
            new BundleSaver(),
            new FlashSale(),
            new VideoStory(),
            new SocialProof(),
            new QuickOrder(),
            new PremiumDark(),
            new ProductShowcase(),
        ];
    }

    /**
     * Writes every definition, in both languages, to the database.
     *
     * @return array{created: int, updated: int}
     */
    public function sync(): array
    {
        $created = 0;
        $updated = 0;

        foreach ($this->definitions() as $order => $definition) {
            foreach (self::LANGUAGES as $lang) {
                $built = $definition->build(new Builder($lang));
                $document = [
                    'schemaVersion' => (int) config('funnel.schema_version'),
                    'page' => ['settings' => $built['settings'], 'sections' => $built['sections']],
                ];
                $common = $this->commonTodos($lang);

                $row = FunnelTemplate::withTrashed()->where('template_key', $definition->key())->where('language', $lang)->first();
                $values = [
                    'name' => $definition->name($lang),
                    'slug' => $definition->key() . '-' . $lang,
                    'template_key' => $definition->key(),
                    'language' => $lang,
                    'description' => $definition->description($lang),
                    'category' => $definition->category(),
                    'thumbnail_path' => $this->thumbnail($definition->key(), $lang),
                    'content' => $document,
                    'product_slots' => [
                        'slots' => $built['slots'],
                        'todos' => array_merge($built['todos'], $common),
                        'tags' => $definition->tags($lang),
                    ],
                    'is_system' => true,
                    'sort_order' => ($order + 1) * 10,
                ];

                if ($row) {
                    $row->restore();
                    $row->fill($values)->save();
                    $updated++;
                } else {
                    FunnelTemplate::create($values);
                    $created++;
                }
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /** Gallery data: one entry per template with its languages. */
    public function gallery(): array
    {
        // Before sales_funnel_v4_publishing.sql is installed there is no library yet.
        if (!\Illuminate\Support\Facades\Schema::hasColumn('funnel_templates', 'template_key')) {
            return [];
        }

        $rows = FunnelTemplate::whereNotNull('template_key')->orderBy('sort_order')->orderByDesc('updated_at')->orderBy('language')->get();

        $out = [];
        foreach ($rows as $row) {
            $meta = $row->product_slots ?: [];
            $entry = &$out[$row->template_key];
            $entry = $entry ?? [
                'key' => $row->template_key,
                'category' => $row->category,
                'thumbnail' => $row->thumbnail_path ? static_asset($row->thumbnail_path) : null,
                'languages' => [],
            ];
            $info = [
                'id' => $row->id,
                'name' => $row->name,
                'description' => $row->description,
                'tags' => $meta['tags'] ?? [],
                'slots' => $meta['slots'] ?? [],
            ];
            if ($row->is_system) {
                $entry['languages'][$row->language] = $info;
            } else {
                // A merchant's own template has one language and is offered whichever is chosen.
                $info['tags'] = [translate('Your template')];
                $info['slots'] = array_map(fn ($slot) => ['label' => translate($slot['label']), 'hint' => $slot['hint'] ? translate($slot['hint']) : null] + $slot, $info['slots']);
                $entry['custom'] = true;
                $entry['thumbnail'] = $row->thumbnail_upload_id ? uploaded_asset($row->thumbnail_upload_id) : null;
                foreach (self::LANGUAGES as $lang) {
                    $entry['languages'][$lang] = $info;
                }
            }
            unset($entry);
        }

        return array_values($out);
    }

    /**
     * A fresh page document from a template row. Dates the template leaves to
     * "a week from now" become real dates here.
     */
    public function document(FunnelTemplate $template, ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now();
        $json = json_encode($template->content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $json = str_replace(Builder::END_TOKEN, $now->copy()->addDays(7)->endOfDay()->toIso8601String(), $json);

        return json_decode($json, true);
    }

    /**
     * @param array<string, array{product_id: int, variant?: ?string}> $choices slot key => product
     */
    public function documentWithProducts(FunnelTemplate $template, array $choices, ?Carbon $now = null): array
    {
        $document = $this->document($template, $now);
        $slots = ($template->product_slots ?: [])['slots'] ?? [];

        foreach ($choices as $slotKey => $choice) {
            $slot = $this->findSlot($slots, (string) $slotKey);
            if (!$slot) {
                throw new FunnelException("The template has no product slot \"{$slotKey}\".", 'unknown_slot', 422);
            }
            $document = $this->fillSlot($document, $slot, (int) ($choice['product_id'] ?? 0), $choice['variant'] ?? null);
        }

        return $document;
    }

    /**
     * Puts a product into every offer of a slot, keeping each offer's piece count.
     */
    public function fillSlot(array $document, array $slot, int $productId, ?string $variant = null): array
    {
        if ($productId < 1) {
            throw new FunnelException('Choose a product.', 'invalid_product', 422);
        }
        $variant = $variant === '' ? null : $variant;
        $bindings = $slot['bindings'] ?? [];

        $visit = function (array $nodes) use (&$visit, $bindings, $productId, $variant): array {
            foreach ($nodes as $i => $node) {
                foreach ($node['productBindings'] ?? [] as $j => $binding) {
                    if (isset($bindings[$binding['key']])) {
                        $nodes[$i]['productBindings'][$j]['items'] = [[
                            'productId' => $productId,
                            'variant' => $variant,
                            'quantity' => (int) $bindings[$binding['key']],
                            'lockVariant' => false,
                        ]];
                    }
                }
                if (!empty($node['children'])) {
                    $nodes[$i]['children'] = $visit($node['children']);
                }
            }

            return $nodes;
        };

        $document['page']['sections'] = $visit($document['page']['sections'] ?? []);

        return $document;
    }

    /**
     * Which slots of a document already have a product.
     *
     * @return array<string, bool> slot key => filled
     */
    public function slotState(array $document, array $slots): array
    {
        $filled = [];
        $visit = function (array $nodes) use (&$visit, &$filled) {
            foreach ($nodes as $node) {
                foreach ($node['productBindings'] ?? [] as $binding) {
                    $filled[$binding['key']] = !empty($binding['items']);
                }
                if (!empty($node['children'])) {
                    $visit($node['children']);
                }
            }
        };
        $visit($document['page']['sections'] ?? []);

        $state = [];
        foreach ($slots as $slot) {
            $all = true;
            foreach (array_keys($slot['bindings'] ?? []) as $key) {
                $all = $all && ($filled[$key] ?? false);
            }
            $state[$slot['key']] = $all && ($slot['bindings'] ?? []) !== [];
        }

        return $state;
    }

    public function findSlot(array $slots, string $key): ?array
    {
        foreach ($slots as $slot) {
            if (($slot['key'] ?? null) === $key) {
                return $slot;
            }
        }

        return null;
    }

    /** Static picture shipped with the admin (public/assets/funnel-templates). */
    private function thumbnail(string $key, string $lang): ?string
    {
        $path = "assets/funnel-templates/{$key}-{$lang}.jpg";

        return is_file(public_path($path)) ? $path : null;
    }

    private function commonTodos(string $lang): array
    {
        return [
            $lang === 'en'
                ? 'The sample wording is general. Check it against your real delivery, payment and return policies and change what does not match.'
                : 'নমুনা লেখাগুলো সাধারণভাবে লেখা। আপনার আসল ডেলিভারি, পেমেন্ট ও ফেরতের নিয়মের সাথে মিলিয়ে দেখুন, না মিললে বদলে নিন।',
        ];
    }
}
