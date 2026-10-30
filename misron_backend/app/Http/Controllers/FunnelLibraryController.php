<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Models\FunnelSavedSection;
use App\Models\User;
use App\Services\Funnel\Analytics\EventRecorder;
use App\Services\Funnel\Checkout\DeliveryCharge;
use App\Services\Funnel\Checkout\FunnelOrderService;
use App\Services\Funnel\Checkout\PaymentOptions;
use App\Services\Funnel\FunnelException;
use App\Services\Funnel\SavedSectionService;
use App\Services\Funnel\Templates\CustomTemplates;
use App\Services\Funnel\Templates\TemplateLibrary;
use App\Models\PaymentGatewayAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * The library pages of the Sales Funnels menu: templates (built-in ones and the
 * merchant's own, made with "Save as template"), saved sections, and a settings page
 * that shows how the module is set up on this installation.
 */
class FunnelLibraryController extends Controller
{
    private CustomTemplates $custom;
    private SavedSectionService $sections;

    public function __construct(CustomTemplates $custom, SavedSectionService $sections)
    {
        $this->custom = $custom;
        $this->sections = $sections;

        $this->middleware(['permission:view_funnels'])->only(['templates', 'savedSections', 'settings']);
        $this->middleware(['permission:add_funnel'])->only(['saveAsTemplate', 'renameTemplate', 'duplicateTemplate']);
        $this->middleware(['permission:delete_funnel'])->only('deleteTemplate');
        $this->middleware(['permission:edit_funnel'])->only(['renameSection', 'deleteSection']);
    }

    // ── Templates ──────────────────────────────────────────────────────────

    public function templates(TemplateLibrary $library)
    {
        $gallery = collect($library->gallery());
        $custom = Schema::hasColumn('funnel_templates', 'template_key') ? $this->custom->all() : collect();
        $authors = User::whereIn('id', $custom->pluck('created_by')->filter()->unique())->pluck('name', 'id');

        return view('backend.funnels.templates', [
            'system' => $gallery->reject(fn ($entry) => !empty($entry['custom']))->values(),
            'custom' => $custom,
            'authors' => $authors,
        ]);
    }

    public function saveAsTemplate(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:150', 'description' => 'nullable|string|max:500']);

        return $this->act(function () use ($request, $id) {
            $template = $this->custom->saveFunnel(Funnel::findOrFail($id), $request->name, $request->description, $request->user());
            $slots = count(($template->product_slots ?: [])['slots'] ?? []);

            return translate('Saved as a template. Products were left out') . ($slots > 0 ? ' (' . $slots . ' ' . translate('to choose when it is used') . ')' : '') . '.';
        }, 'funnels.templates');
    }

    public function renameTemplate(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:150', 'description' => 'nullable|string|max:500']);

        return $this->act(function () use ($request, $id) {
            $this->custom->rename($this->custom->find($id), $request->name, $request->description);

            return translate('Template has been updated');
        }, 'funnels.templates');
    }

    public function duplicateTemplate(Request $request, $id)
    {
        return $this->act(function () use ($request, $id) {
            $this->custom->duplicate($this->custom->find($id), $request->user());

            return translate('Template has been duplicated');
        }, 'funnels.templates');
    }

    public function deleteTemplate($id)
    {
        return $this->act(function () use ($id) {
            $this->custom->delete($this->custom->find($id));

            return translate('Template has been deleted. Funnels made from it are not changed.');
        }, 'funnels.templates');
    }

    // ── Saved sections ─────────────────────────────────────────────────────

    public function savedSections(Request $request)
    {
        $sections = $this->sections->list((string) $request->query('search', ''), 200);
        $authors = User::whereIn('id', $sections->pluck('created_by')->filter()->unique())->pluck('name', 'id');

        return view('backend.funnels.saved_sections', [
            'sections' => $sections,
            'authors' => $authors,
            'search' => (string) $request->query('search', ''),
        ]);
    }

    public function renameSection(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:150']);

        return $this->act(function () use ($request, $id) {
            $this->sections->rename(FunnelSavedSection::findOrFail($id), $request->name);

            return translate('Section has been renamed');
        }, 'funnels.sections');
    }

    public function deleteSection($id)
    {
        return $this->act(function () use ($id) {
            $this->sections->delete(FunnelSavedSection::findOrFail($id));

            return translate('Section has been deleted. Pages that already use it keep their copy.');
        }, 'funnels.sections');
    }

    // ── Settings ───────────────────────────────────────────────────────────

    /**
     * How the module is set up here. Everything shown comes from .env, the store's own
     * business settings or the database; changing it is described next to each row.
     */
    public function settings(PaymentOptions $payments, DeliveryCharge $delivery)
    {
        $config = config('funnel');
        $methods = $payments->available();

        return view('backend.funnels.settings', [
            'checkoutEnabled' => (bool) $config['checkout']['enabled'],
            'requireCheckout' => (bool) $config['require_checkout'],
            'publicUrl' => $config['public_url'],
            'editorUrl' => $config['editor_url'],
            'revalidateReady' => $config['revalidate']['url'] !== '' && $config['revalidate']['secret'] !== '',
            'testPhones' => count($config['analytics']['test_phones']),
            'shareCustomerData' => (bool) $config['analytics']['share_customer_data'],
            'retentionDays' => (int) $config['analytics']['event_retention_days'],
            'expiryEnabled' => (bool) $config['unpaid_online_orders']['expire'],
            'expiryMinutes' => (int) $config['unpaid_online_orders']['expire_after_minutes'],
            'paymentMethods' => $methods,
            'deliveryMode' => $delivery->mode(),
            'deliverySupported' => $delivery->supported(),
            'database' => [
                'Funnel pages (sales_funnel_v1)' => Schema::hasTable('funnels'),
                'Orders linked to funnels (sales_funnel_v2_orders)' => Schema::hasColumn('orders', 'funnel_id'),
                'Checkout protection (sales_funnel_v3_checkout)' => Schema::hasTable('funnel_checkout_attempts'),
                'Scheduling, share links, template library (sales_funnel_v4_publishing)' => Funnel::hasPublishingColumns(),
                'Analytics and payment records (sales_funnel_v5_analytics)' => EventRecorder::ready() && FunnelOrderService::attributionTableReady() && PaymentGatewayAttempt::ready(),
            ],
        ]);
    }

    private function act(callable $action, string $route)
    {
        try {
            flash($action())->success();
        } catch (FunnelException $e) {
            flash(translate($e->getMessage()))->error();
        }

        return redirect()->route($route);
    }
}
