<?php

namespace Tests\Feature\Funnel;

use App\Models\Funnel;
use App\Models\FunnelSavedSection;
use App\Models\FunnelTemplate;
use App\Services\Funnel\FunnelService;
use App\Services\Funnel\Templates\TemplateLibrary;
use Tests\Funnel\FunnelTestCase;

/**
 * The Sales Funnels library pages: the merchant's own templates ("Save as template",
 * UC-14), saved sections, settings, and the figures in the All Funnels list.
 */
class FunnelLibraryTest extends FunnelTestCase
{
    /** A page with two offers of one product, a bundle of two products and a free gift. */
    private function campaignPage(int $a, int $b): array
    {
        $page = $this->sellingPage($a, 'Eid Blender Offer');
        $this->ensureProduct($b);
        $page['page']['settings'] = ['language' => 'en'];
        $page['page']['sections'][1]['productBindings'] = [
            ['key' => 'one', 'items' => [['productId' => $a, 'quantity' => 1]], 'offer' => ['type' => 'percent', 'value' => 10]],
            ['key' => 'two', 'items' => [['productId' => $a, 'quantity' => 2]], 'offer' => ['type' => 'percent', 'value' => 15]],
            ['key' => 'pair', 'items' => [['productId' => $a, 'quantity' => 1], ['productId' => $b, 'quantity' => 1]], 'offer' => ['type' => 'fixed_price', 'value' => 900]],
            ['key' => 'gift', 'items' => [['productId' => $b, 'quantity' => 1]], 'offer' => ['type' => 'free_gift', 'giftProductId' => $a]],
        ];

        return $page;
    }

    private function funnelWith(array $page, $admin): Funnel
    {
        $service = app(FunnelService::class);
        $funnel = $service->create(['title' => 'Eid Blender Offer'], $admin);
        $service->saveDraft($funnel, $page, 1, $admin);

        return $funnel->fresh();
    }

    public function test_a_funnel_saved_as_template_keeps_the_page_and_drops_every_product(): void
    {
        $admin = $this->makeAdmin();
        [$a, $b] = [$this->makeProduct()->id, $this->makeProduct()->id];
        $funnel = $this->funnelWith($this->campaignPage($a, $b), $admin);

        $this->actingAs($admin)->post(route('funnels.save_as_template', $funnel->id), ['name' => 'Eid campaign', 'description' => 'For Eid'])
            ->assertRedirect(route('funnels.templates'));

        $template = FunnelTemplate::where('is_system', false)->latest('id')->firstOrFail();
        $this->assertSame('Eid campaign', $template->name);
        $this->assertSame('custom-' . $template->id, $template->template_key);
        $this->assertSame('en', $template->language);

        // No product id is left anywhere in the template.
        $json = json_encode($template->content);
        $this->assertStringNotContainsString('"productId":' . $a, $json);
        $this->assertStringNotContainsString('"productId":' . $b, $json);
        $this->assertStringNotContainsString('giftProductId', $json);
        $this->assertStringContainsString('Eid Blender Offer', $json);

        // Offers that held one product become slots, grouped by that product.
        $slots = $template->product_slots['slots'];
        $this->assertCount(2, $slots);
        $this->assertSame(['one' => 1, 'two' => 2], $slots[0]['bindings']);
        $this->assertSame(['gift' => 1], $slots[1]['bindings']);
        $this->assertNotEmpty($template->product_slots['todos'], 'the bundle and the gift are flagged to choose again');

        // The source funnel is untouched.
        $this->assertStringContainsString('"productId":' . $a, json_encode($funnel->fresh()->draft_content));
    }

    public function test_a_new_campaign_from_a_custom_template_uses_different_products(): void
    {
        $admin = $this->makeAdmin();
        [$a, $b, $c] = [$this->makeProduct()->id, $this->makeProduct()->id, $this->makeProduct()->id];
        $source = $this->funnelWith($this->campaignPage($a, $b), $admin);
        $this->actingAs($admin)->post(route('funnels.save_as_template', $source->id), ['name' => 'Reusable']);
        $template = FunnelTemplate::where('is_system', false)->latest('id')->firstOrFail();

        // It is offered on the create page, in both language views.
        $create = $this->get(route('funnels.create', ['template' => $template->id]))->assertOk();
        $create->assertSee('Reusable')->assertSee('data-id-bn="' . $template->id . '"', false)->assertSee('data-id-en="' . $template->id . '"', false);

        $this->post(route('funnels.store'), ['title' => 'Winter Offer', 'template_id' => $template->id, 'slots' => ['product_1' => $c]]);
        $copy = Funnel::where('title', 'Winter Offer')->firstOrFail();
        $bindings = collect($copy->draft_content['page']['sections'][1]['productBindings'])->keyBy('key');

        $this->assertSame([['productId' => $c, 'variant' => null, 'quantity' => 1, 'lockVariant' => false]], $bindings['one']['items']);
        $this->assertSame(2, $bindings['two']['items'][0]['quantity']);
        $this->assertSame([], $bindings['pair']['items']);
        $this->assertSame($template->id, (int) $copy->source_template_id);
        $this->assertNotSame($source->slug, $copy->slug);
    }

    public function test_own_templates_can_be_renamed_duplicated_and_deleted_but_built_in_ones_cannot(): void
    {
        app(TemplateLibrary::class)->sync();
        $admin = $this->makeAdmin();
        $funnel = $this->funnelWith($this->sellingPage($this->makeProduct()->id), $admin);
        $this->actingAs($admin)->post(route('funnels.save_as_template', $funnel->id), ['name' => 'Mine']);
        $template = FunnelTemplate::where('is_system', false)->latest('id')->firstOrFail();

        $this->get(route('funnels.templates'))->assertOk()->assertSee('Mine')->assertSee('Built-in designs')->assertSee('Classic single product');

        $this->post(route('funnels.templates.update', $template->id), ['name' => 'Mine, renamed', 'description' => 'Short'])->assertRedirect();
        $this->assertSame('Mine, renamed', $template->fresh()->name);

        $this->post(route('funnels.templates.duplicate', $template->id))->assertRedirect();
        $this->assertSame(2, FunnelTemplate::where('is_system', false)->count());

        $this->delete(route('funnels.templates.destroy', $template->id))->assertRedirect();
        $this->assertSoftDeleted('funnel_templates', ['id' => $template->id]);
        $this->assertNotNull(Funnel::find($funnel->id));

        $system = FunnelTemplate::where('is_system', true)->firstOrFail();
        $this->post(route('funnels.templates.update', $system->id), ['name' => 'Hijacked'])->assertRedirect();
        $this->delete(route('funnels.templates.destroy', $system->id))->assertRedirect();
        $this->assertNotSame('Hijacked', $system->fresh()->name);
        $this->assertNull($system->fresh()->deleted_at);
    }

    public function test_library_pages_follow_the_funnel_permissions(): void
    {
        $admin = $this->makeAdmin();
        $funnel = $this->funnelWith($this->sellingPage($this->makeProduct()->id), $admin);
        $this->actingAs($admin)->post(route('funnels.save_as_template', $funnel->id), ['name' => 'Kept']);
        $template = FunnelTemplate::where('is_system', false)->latest('id')->firstOrFail();
        $section = FunnelSavedSection::create(['name' => 'Hero', 'category' => 'section', 'content' => ['id' => 'x', 'type' => 'section'], 'created_by' => $admin->id]);

        $viewer = $this->makeUser('staff', ['view_funnels']);
        $this->forgetAuth();
        $this->actingAs($viewer);
        $this->get(route('funnels.templates'))->assertOk();
        $this->get(route('funnels.sections'))->assertOk()->assertSee('Hero');
        $this->get(route('funnels.settings'))->assertOk();
        $this->post(route('funnels.save_as_template', $funnel->id), ['name' => 'No'])->assertStatus(403);
        $this->post(route('funnels.templates.update', $template->id), ['name' => 'No'])->assertStatus(403);
        $this->delete(route('funnels.templates.destroy', $template->id))->assertStatus(403);
        $this->post(route('funnels.sections.update', $section->id), ['name' => 'No'])->assertStatus(403);
        $this->delete(route('funnels.sections.destroy', $section->id))->assertStatus(403);
        $this->assertSame('Kept', $template->fresh()->name);
        $this->assertSame('Hero', $section->fresh()->name);

        $this->forgetAuth();
        $this->actingAs($this->makeUser('customer'))->get(route('funnels.templates'))->assertStatus(404);
    }

    public function test_saved_sections_can_be_renamed_and_deleted_from_the_admin(): void
    {
        $admin = $this->makeAdmin();
        $section = FunnelSavedSection::create(['name' => 'Reviews', 'category' => 'customer_reviews', 'content' => ['id' => 'x', 'type' => 'customer_reviews'], 'created_by' => $admin->id]);

        $this->actingAs($admin)->get(route('funnels.sections', ['search' => 'Rev']))->assertOk()->assertSee('Reviews')->assertSee('Customer reviews');
        $this->post(route('funnels.sections.update', $section->id), ['name' => 'Happy customers'])->assertRedirect(route('funnels.sections'));
        $this->assertSame('Happy customers', $section->fresh()->name);
        $this->delete(route('funnels.sections.destroy', $section->id))->assertRedirect(route('funnels.sections'));
        $this->assertSoftDeleted('funnel_saved_sections', ['id' => $section->id]);
    }

    public function test_settings_page_says_plainly_whether_ordering_is_open(): void
    {
        $admin = $this->makeAdmin();
        config(['funnel.checkout.enabled' => false]);
        $this->actingAs($admin)->get(route('funnels.settings'))->assertOk()
            ->assertSee('Customers can order from funnel pages')
            ->assertSee('ordering is not open yet')
            ->assertSee('FUNNEL_CHECKOUT_ENABLED')
            ->assertSee('Installed');
    }

    public function test_the_list_shows_visitors_orders_conversion_and_revenue_of_the_last_30_days(): void
    {
        $admin = $this->makeAdmin();
        $funnel = app(FunnelService::class)->create(['title' => 'Figures Offer'], $admin);

        $page = $this->actingAs($admin)->get(route('funnels.index'))->assertOk();
        $page->assertSee('Last 30 days')->assertSee('Visitors')->assertSee('Revenue')->assertSee('Save as template');

        // Staff without analytics permission do not see the figures.
        $this->forgetAuth();
        $this->actingAs($this->makeUser('staff', ['view_funnels']))->get(route('funnels.index'))->assertOk()->assertDontSee('Last 30 days');
        $this->assertNotNull($funnel);
    }
}
