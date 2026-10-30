<?php

namespace Tests\Feature\Funnel;

use App\Models\Funnel;
use App\Models\FunnelTemplate;
use App\Services\Funnel\FunnelException;
use App\Services\Funnel\FunnelService;
use App\Services\Funnel\PageValidator;
use App\Services\Funnel\Templates\Builder;
use App\Services\Funnel\Templates\TemplateLibrary;
use Illuminate\Support\Str;
use Tests\Funnel\FunnelTestCase;

/**
 * The eight built-in templates, in both languages: every one is a valid page,
 * holds no product, and becomes publishable once its products are chosen.
 */
class FunnelTemplatesTest extends FunnelTestCase
{
    private TemplateLibrary $library;

    protected function setUp(): void
    {
        parent::setUp();
        $this->library = app(TemplateLibrary::class);
        $this->library->sync();
    }

    /** @return FunnelTemplate[] */
    private function rows(): array
    {
        return FunnelTemplate::whereNotNull('template_key')->orderBy('sort_order')->orderBy('language')->get()->all();
    }

    private function choices(FunnelTemplate $template): array
    {
        $choices = [];
        foreach (($template->product_slots['slots'] ?? []) as $slot) {
            $choices[$slot['key']] = ['product_id' => $this->makeProduct(['name' => 'Fixture ' . $slot['key']])->id];
        }

        return $choices;
    }

    public function test_there_are_eight_templates_in_two_languages(): void
    {
        $rows = $this->rows();
        $this->assertCount(16, $rows);
        $this->assertCount(8, array_unique(array_map(function ($row) {
            return $row->template_key;
        }, $rows)));
        foreach (['bn', 'en'] as $lang) {
            $this->assertCount(8, array_filter($rows, function ($row) use ($lang) {
                return $row->language === $lang;
            }));
        }
        $this->assertCount(8, $this->library->gallery());
    }

    public function test_syncing_again_updates_in_place_and_never_duplicates(): void
    {
        $before = FunnelTemplate::count();
        $result = $this->library->sync();

        $this->assertSame(['created' => 0, 'updated' => 16], $result);
        $this->assertSame($before, FunnelTemplate::count());
    }

    public function test_every_template_is_a_valid_draft_and_holds_no_product(): void
    {
        $validator = app(PageValidator::class);

        foreach ($this->rows() as $row) {
            $label = "{$row->template_key}/{$row->language}";
            $document = $this->library->document($row);

            $result = $validator->validate($document, PageValidator::MODE_DRAFT);
            $this->assertTrue($result->isValid(), "{$label}: " . json_encode($result->errors(), JSON_UNESCAPED_UNICODE));
            $this->assertSame([], $result->productIdList(), "{$label} must not name any product");
            $this->assertStringNotContainsString(Builder::END_TOKEN, json_encode($document), "{$label}: the end-date placeholder must be replaced");
            $this->assertSame($row->language, $document['page']['settings']['language']);
        }
    }

    public function test_bengali_and_english_versions_have_their_own_wording(): void
    {
        foreach ($this->library->definitions() as $definition) {
            $bn = FunnelTemplate::where('template_key', $definition->key())->where('language', 'bn')->firstOrFail();
            $en = FunnelTemplate::where('template_key', $definition->key())->where('language', 'en')->firstOrFail();

            $this->assertNotSame($bn->name, $en->name, $definition->key());
            $this->assertNotSame($bn->description, $en->description, $definition->key());
            $this->assertMatchesRegularExpression('/[\x{0980}-\x{09FF}]/u', json_encode($bn->content, JSON_UNESCAPED_UNICODE), "{$definition->key()} bn has Bengali text");
            $this->assertDoesNotMatchRegularExpression('/[\x{0980}-\x{09FF}]/u', json_encode($en->content, JSON_UNESCAPED_UNICODE), "{$definition->key()} en has no Bengali text");
        }
    }

    public function test_every_template_has_a_slot_for_each_of_its_offers(): void
    {
        foreach ($this->rows() as $row) {
            $document = $this->library->document($row);
            $slots = $row->product_slots['slots'];
            $this->assertNotEmpty($slots, $row->slug);

            $offerKeys = [];
            $walk = function (array $nodes) use (&$walk, &$offerKeys) {
                foreach ($nodes as $node) {
                    foreach ($node['productBindings'] ?? [] as $binding) {
                        $offerKeys[] = $binding['key'];
                    }
                    $walk($node['children'] ?? []);
                }
            };
            $walk($document['page']['sections']);

            $slotKeys = [];
            foreach ($slots as $slot) {
                $slotKeys = array_merge($slotKeys, array_keys($slot['bindings']));
            }
            sort($offerKeys);
            sort($slotKeys);
            $this->assertSame($offerKeys, $slotKeys, "{$row->slug}: slots and offers must match");

            $state = $this->library->slotState($document, $slots);
            $this->assertNotContains(true, $state, "{$row->slug}: nothing is filled in a fresh template");
        }
    }

    public function test_filling_a_slot_sets_the_product_and_keeps_each_offers_piece_count(): void
    {
        $row = FunnelTemplate::where('template_key', 'classic-single-product')->where('language', 'en')->firstOrFail();
        $product = $this->makeProduct();
        $slot = $row->product_slots['slots'][0];

        $document = $this->library->fillSlot($this->library->document($row), $slot, $product->id, null);

        $quantities = [];
        $walk = function (array $nodes) use (&$walk, &$quantities) {
            foreach ($nodes as $node) {
                foreach ($node['productBindings'] ?? [] as $binding) {
                    $quantities[$binding['key']] = [$binding['items'][0]['productId'], $binding['items'][0]['quantity']];
                }
                $walk($node['children'] ?? []);
            }
        };
        $walk($document['page']['sections']);

        $this->assertSame([$product->id, 1], $quantities['offer_1']);
        $this->assertSame([$product->id, 2], $quantities['offer_2']);
        $this->assertSame([$product->id, 3], $quantities['offer_3']);
        $this->assertSame(['main' => true], $this->library->slotState($document, $row->product_slots['slots']));
    }

    public function test_each_template_publishes_once_its_products_are_chosen_except_where_the_merchant_must_write_something(): void
    {
        $service = app(FunnelService::class);
        $admin = $this->makeAdmin();
        $mustEdit = ['video-story' => 'video_without_url', 'social-proof' => 'sample_text'];

        foreach ($this->rows() as $row) {
            $label = "{$row->template_key}/{$row->language}";
            $funnel = $service->createFromTemplate(
                ['title' => $label, 'slug' => $row->slug . '-' . strtolower(Str::random(4))],
                $row,
                $this->choices($row),
                $admin
            );
            $this->assertSame($row->id, (int) $funnel->source_template_id);

            if (isset($mustEdit[$row->template_key])) {
                try {
                    $service->publish($funnel, $admin);
                    $this->fail("{$label} must not publish before the merchant edits it.");
                } catch (FunnelException $e) {
                    $codes = array_column($e->details()['errors'] ?? [], 'code');
                    $this->assertContains($mustEdit[$row->template_key], $codes, $label);
                }
                continue;
            }

            [$published] = $service->publish($funnel, $admin);
            $this->assertTrue($published->isLive(), $label);
            $this->getJson("/api/v2/funnels/{$published->slug}")->assertOk()->assertJsonPath('seo.language', $row->language);
        }
    }

    public function test_the_two_templates_that_need_the_merchants_words_publish_after_they_supply_them(): void
    {
        $service = app(FunnelService::class);
        $admin = $this->makeAdmin();

        // Video: add a link.
        $video = FunnelTemplate::where('template_key', 'video-story')->where('language', 'en')->firstOrFail();
        $funnel = $service->createFromTemplate(['title' => 'Video', 'slug' => 'video-' . strtolower(Str::random(5))], $video, $this->choices($video), $admin);
        $document = $funnel->draft_content;
        $document['page']['sections'][0]['props']['url'] = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        [$funnel] = $service->saveDraft($funnel, $document, $funnel->draft_revision, $admin);
        [$published] = $service->publish($funnel, $admin);
        $this->assertTrue($published->isLive());

        // Reviews: delete the review section.
        $proof = FunnelTemplate::where('template_key', 'social-proof')->where('language', 'en')->firstOrFail();
        $funnel = $service->createFromTemplate(['title' => 'Proof', 'slug' => 'proof-' . strtolower(Str::random(5))], $proof, $this->choices($proof), $admin);
        $document = $funnel->draft_content;
        $document['page']['sections'] = array_values(array_filter($document['page']['sections'], function ($section) {
            return !(($section['children'][0]['type'] ?? '') === 'testimonial_cards') && !(($section['type'] ?? '') === 'testimonial_cards');
        }));
        // The review block is a child of its own section.
        $this->assertStringNotContainsString('testimonial_cards', json_encode($document));
        [$funnel] = $service->saveDraft($funnel, $document, $funnel->draft_revision, $admin);
        [$published] = $service->publish($funnel, $admin);
        $this->assertTrue($published->isLive());
    }

    public function test_sample_text_in_double_brackets_blocks_publishing_wherever_it_is(): void
    {
        $service = app(FunnelService::class);
        $admin = $this->makeAdmin();
        $funnel = $service->create(['title' => 'Marked', 'slug' => 'marked-' . strtolower(Str::random(5))], $admin);
        $page = $this->sellingPage();
        $page['page']['sections'][0]['children'][0]['props']['text'] = 'Intro [[write your own line here]]';
        [$funnel] = $service->saveDraft($funnel, $page, 1, $admin);

        try {
            $service->publish($funnel, $admin);
            $this->fail('Marked text must block publishing.');
        } catch (FunnelException $e) {
            $error = collect($e->details()['errors'])->firstWhere('code', 'sample_text');
            $this->assertNotNull($error);
            $this->assertStringContainsString('write your own line here', $error['message']);
        }
    }

    public function test_a_video_without_a_link_cannot_be_published_but_can_be_saved(): void
    {
        $service = app(FunnelService::class);
        $admin = $this->makeAdmin();
        $funnel = $service->create(['title' => 'Video', 'slug' => 'vid-' . strtolower(Str::random(5))], $admin);
        $page = $this->sellingPage();
        array_unshift($page['page']['sections'], ['id' => 'video_001', 'type' => 'video_hero', 'props' => ['headline' => 'Watch']]);
        [$funnel] = $service->saveDraft($funnel, $page, 1, $admin);

        $this->expectException(FunnelException::class);
        $service->publish($funnel, $admin);
    }

    public function test_the_flash_sale_countdown_and_offer_end_at_a_real_date_a_week_ahead(): void
    {
        $row = FunnelTemplate::where('template_key', 'flash-sale')->where('language', 'en')->firstOrFail();
        $now = now()->setTime(10, 0);
        $json = json_encode($this->library->document($row, $now));

        $expected = $now->copy()->addDays(7)->endOfDay()->toIso8601String();
        $this->assertSame(2, substr_count($json, str_replace('/', '\/', $expected)), 'countdown and offer both end then');
    }

    // ── through the editor API ─────────────────────────────────────────────

    public function test_the_api_lists_the_gallery_and_creates_a_funnel_from_a_template_with_products(): void
    {
        $token = $this->makeAdmin()->createToken('funnel-editor', ['funnel:edit'])->plainTextToken;
        $product = $this->makeProduct();
        $row = FunnelTemplate::where('template_key', 'quick-order')->where('language', 'bn')->firstOrFail();

        $gallery = $this->withToken($token)->getJson('/api/v2/funnel-editor/templates');
        $gallery->assertOk()->assertJsonCount(8, 'data')->assertJsonPath('data.0.key', 'classic-single-product');
        $this->assertSame(['bn', 'en'], array_keys($gallery->json('data.0.languages')));

        $this->forgetAuth();
        $created = $this->withToken($token)->postJson('/api/v2/funnel-editor/funnels', [
            'title' => 'কুইক অর্ডার',
            'slug' => 'quick-' . strtolower(Str::random(5)),
            'template_id' => $row->id,
            'slots' => ['main' => ['product_id' => $product->id]],
        ]);
        $created->assertStatus(201)
            ->assertJsonPath('data.template.key', 'quick-order')
            ->assertJsonPath('data.template.language', 'bn')
            ->assertJsonPath('data.product_ids.0', $product->id);
    }

    public function test_a_template_funnel_can_be_created_without_products_and_filled_in_later(): void
    {
        $token = $this->makeAdmin()->createToken('funnel-editor', ['funnel:edit'])->plainTextToken;
        $product = $this->makeProduct();
        $row = FunnelTemplate::where('template_key', 'classic-single-product')->where('language', 'en')->firstOrFail();

        $created = $this->withToken($token)->postJson('/api/v2/funnel-editor/funnels', [
            'title' => 'Classic', 'slug' => 'classic-' . strtolower(Str::random(5)), 'template_id' => $row->id,
        ]);
        $created->assertStatus(201)->assertJsonPath('data.product_ids', []);
        $id = $created->json('data.id');
        $revision = $created->json('data.draft.revision');

        $this->forgetAuth();
        $filled = $this->withToken($token)->postJson("/api/v2/funnel-editor/funnels/{$id}/template-slots", [
            'slot' => 'main', 'product_id' => $product->id, 'revision' => $revision,
        ]);
        $filled->assertOk()->assertJsonPath('data.product_ids.0', $product->id);

        // A stale revision is refused like any other save.
        $this->forgetAuth();
        $this->withToken($token)->postJson("/api/v2/funnel-editor/funnels/{$id}/template-slots", [
            'slot' => 'main', 'product_id' => $product->id, 'revision' => $revision,
        ])->assertStatus(409);
    }

    public function test_unknown_templates_slots_and_products_are_refused(): void
    {
        $token = $this->makeAdmin()->createToken('funnel-editor', ['funnel:edit'])->plainTextToken;
        $row = FunnelTemplate::where('template_key', 'quick-order')->where('language', 'en')->firstOrFail();

        $this->withToken($token)->postJson('/api/v2/funnel-editor/funnels', ['title' => 'X', 'template_id' => 999999])
            ->assertStatus(422)->assertJsonPath('code', 'unknown_template');

        $this->forgetAuth();
        $this->withToken($token)->postJson('/api/v2/funnel-editor/funnels', [
            'title' => 'X', 'slug' => 'x-' . strtolower(Str::random(5)), 'template_id' => $row->id, 'slots' => ['nope' => ['product_id' => 1]],
        ])->assertStatus(422)->assertJsonPath('code', 'unknown_slot');

        $this->forgetAuth();
        $this->withToken($token)->postJson('/api/v2/funnel-editor/funnels', [
            'title' => 'X', 'slug' => 'x-' . strtolower(Str::random(5)), 'template_id' => $row->id, 'slots' => ['main' => ['product_id' => 987654321]],
        ])->assertStatus(422)->assertJsonPath('code', 'unknown_product');
    }

    public function test_a_funnel_not_made_from_a_template_has_no_slots_to_fill(): void
    {
        $admin = $this->makeAdmin();
        $funnel = app(FunnelService::class)->create(['title' => 'Plain', 'slug' => 'plain-' . strtolower(Str::random(5))], $admin);
        $token = $admin->createToken('funnel-editor', ['funnel:edit'])->plainTextToken;

        $this->withToken($token)->postJson("/api/v2/funnel-editor/funnels/{$funnel->id}/template-slots", ['slot' => 'main', 'product_id' => $this->makeProduct()->id])
            ->assertStatus(422)->assertJsonPath('code', 'no_template');
    }
}
