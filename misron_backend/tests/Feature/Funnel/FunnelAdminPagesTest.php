<?php

namespace Tests\Feature\Funnel;

use App\Models\Funnel;
use App\Services\Funnel\FunnelService;
use Tests\Funnel\FunnelTestCase;

class FunnelAdminPagesTest extends FunnelTestCase
{
    public function test_admin_sees_the_list_and_empty_state()
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('funnels.index'))
            ->assertOk()
            ->assertSee('Sales Funnels')
            ->assertSee('No sales funnels yet');

        app(FunnelService::class)->create(['title' => 'Blender Mega Offer'], $admin);

        $this->get(route('funnels.index', ['search' => 'blender']))
            ->assertOk()
            ->assertSee('Blender Mega Offer')
            ->assertSee('/funnel/blender-mega-offer');
    }

    public function test_staff_without_permission_cannot_open_or_change_funnels()
    {
        $admin = $this->makeAdmin();
        $funnel = app(FunnelService::class)->create(['title' => 'Offer'], $admin);
        app(FunnelService::class)->saveDraft($funnel, $this->sellingPage(), 1, $admin);

        $viewer = $this->makeUser('staff', ['view_funnels']);
        $this->actingAs($viewer);

        $this->get(route('funnels.index'))->assertOk();
        $this->get(route('funnels.create'))->assertStatus(403);
        $this->post(route('funnels.publish', $funnel->id))->assertStatus(403);
        $this->delete(route('funnels.destroy', $funnel->id))->assertStatus(403);
        $this->get(route('funnels.builder', $funnel->id))->assertStatus(403);

        $this->assertSame(Funnel::STATUS_DRAFT, Funnel::find($funnel->id)->status);

        $customer = $this->makeUser('customer');
        $this->actingAs($customer)->get(route('funnels.index'))->assertStatus(404);
    }

    public function test_create_opens_the_editor_with_a_one_time_code()
    {
        $admin = $this->makeAdmin();

        $store = $this->actingAs($admin)->post(route('funnels.store'), ['title' => 'Flash Sale']);
        $funnel = Funnel::where('title', 'Flash Sale')->firstOrFail();
        $store->assertRedirect(route('funnels.builder', $funnel->id));

        $builder = $this->get(route('funnels.builder', $funnel->id));
        $builder->assertRedirect();
        $location = $builder->headers->get('Location');
        $this->assertStringStartsWith('http://editor.test/builder/funnels/' . $funnel->id . '?handoff=', $location);
    }

    public function test_the_visual_builder_is_one_click_away_from_the_list_and_the_create_page()
    {
        $admin = $this->makeAdmin();
        $funnel = app(FunnelService::class)->create(['title' => 'Builder Link'], $admin);
        $archived = app(FunnelService::class)->create(['title' => 'Old Offer'], $admin);
        app(FunnelService::class)->archive($archived, $admin);
        $this->actingAs($admin);

        $this->get(route('funnels.create'))->assertOk()->assertSee('Create and open Visual Builder');

        $list = $this->get(route('funnels.index'))->assertOk()->getContent();
        $builderLink = 'href="' . route('funnels.builder', $funnel->id) . '" class="btn btn-sm btn-primary';
        // One button for phones (under the name) and one for wider screens (in Actions).
        $this->assertSame(2, substr_count($list, $builderLink));
        $this->assertSame(2, substr_count($list, 'Open Visual Builder'), 'the archived funnel gets no button');
        $this->assertStringNotContainsString('href="' . route('funnels.builder', $archived->id) . '" class="btn', $list);

        // Without the editor address there is nothing to open.
        config(['funnel.editor_url' => '']);
        $this->get(route('funnels.create'))->assertOk()->assertDontSee('Create and open Visual Builder');
        $this->get(route('funnels.index'))->assertOk()->assertDontSee('Open Visual Builder');
    }

    public function test_create_page_shows_the_template_gallery_in_both_languages()
    {
        app(\App\Services\Funnel\Templates\TemplateLibrary::class)->sync();

        $page = $this->actingAs($this->makeAdmin())->get(route('funnels.create'))->assertOk();

        $page->assertSee('Choose a design')
            ->assertSee('Classic single product')
            ->assertSee('ক্লাসিক সিঙ্গেল প্রোডাক্ট')
            ->assertSee('Flash sale')
            ->assertSee('Blank page');
        $this->assertSame(8, substr_count($page->getContent(), 'data-template="') - 1, 'eight templates plus the blank card');
    }

    public function test_create_from_a_template_with_a_product_opens_the_editor_on_a_ready_page()
    {
        $library = app(\App\Services\Funnel\Templates\TemplateLibrary::class);
        $library->sync();
        $template = \App\Models\FunnelTemplate::where('template_key', 'quick-order')->where('language', 'en')->firstOrFail();
        $product = $this->makeProduct();
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('funnels.store'), [
            'title' => 'Quick order page',
            'template_id' => $template->id,
            'slots' => ['main' => $product->id],
        ]);

        $funnel = Funnel::where('title', 'Quick order page')->firstOrFail();
        $response->assertRedirect(route('funnels.builder', $funnel->id));
        $this->assertSame($template->id, (int) $funnel->source_template_id);
        $this->assertSame([$product->id], $funnel->product_ids);
    }

    public function test_create_from_a_template_without_choosing_a_product_is_allowed()
    {
        app(\App\Services\Funnel\Templates\TemplateLibrary::class)->sync();
        $template = \App\Models\FunnelTemplate::where('template_key', 'flash-sale')->where('language', 'bn')->firstOrFail();

        $this->actingAs($this->makeAdmin())->post(route('funnels.store'), ['title' => 'ফ্ল্যাশ সেল', 'template_id' => $template->id])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame([], Funnel::where('title', 'ফ্ল্যাশ সেল')->firstOrFail()->product_ids);
    }

    public function test_create_rejects_an_unknown_template_and_an_unknown_product()
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->from(route('funnels.create'))
            ->post(route('funnels.store'), ['title' => 'X', 'template_id' => 999999])
            ->assertRedirect(route('funnels.create'))->assertSessionHasErrors('template_id');

        app(\App\Services\Funnel\Templates\TemplateLibrary::class)->sync();
        $template = \App\Models\FunnelTemplate::where('template_key', 'quick-order')->where('language', 'en')->firstOrFail();
        $this->from(route('funnels.create'))
            ->post(route('funnels.store'), ['title' => 'Y', 'template_id' => $template->id, 'slots' => ['main' => 987654321]])
            ->assertRedirect(route('funnels.create'))->assertSessionHasErrors('template_id');
        $this->assertNull(Funnel::where('title', 'Y')->first());
    }

    public function test_product_search_for_the_create_form_needs_the_add_permission()
    {
        $product = $this->makeProduct(['name' => 'Searchable Blender']);

        $this->actingAs($this->makeAdmin())->getJson(route('funnels.products', ['q' => 'Searchable']))
            ->assertOk()->assertJsonPath('data.0.name', 'Searchable Blender');

        $viewer = $this->makeUser('staff', ['view_funnels']);
        $this->actingAs($viewer)->getJson(route('funnels.products', ['q' => 'Searchable']))->assertStatus(403);
    }

    public function test_create_reports_a_taken_address()
    {
        $admin = $this->makeAdmin();
        app(FunnelService::class)->create(['title' => 'One', 'slug' => 'eid-offer'], $admin);

        $this->actingAs($admin)->from(route('funnels.create'))
            ->post(route('funnels.store'), ['title' => 'Two', 'slug' => 'eid-offer'])
            ->assertRedirect(route('funnels.create'))
            ->assertSessionHasErrors('slug');
    }

    public function test_publish_from_the_list_reports_what_is_missing()
    {
        $admin = $this->makeAdmin();
        $funnel = app(FunnelService::class)->create(['title' => 'Empty'], $admin);

        $this->actingAs($admin)->from(route('funnels.index'))
            ->post(route('funnels.publish', $funnel->id))
            ->assertRedirect(route('funnels.index'));

        $this->assertSame(Funnel::STATUS_DRAFT, Funnel::find($funnel->id)->status);
        $this->assertStringContainsString('Add at least one section', collect(session('flash_notification'))->pluck('message')->implode(' '));
    }
}
