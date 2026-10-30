<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Models\FunnelTemplate;
use App\Models\Product;
use App\Services\Funnel\Analytics\FunnelAnalytics;
use App\Services\Funnel\EditorSession;
use App\Services\Funnel\FunnelCatalogue;
use App\Services\Funnel\FunnelException;
use App\Services\Funnel\FunnelService;
use App\Services\Funnel\Templates\TemplateLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sales funnel pages of the admin dashboard: the list, the create form and
 * the lifecycle buttons. Page editing happens in the visual editor, which
 * this controller opens through EditorSession.
 */
class FunnelController extends Controller
{
    private $funnels;
    private $session;

    public function __construct(FunnelService $funnels, EditorSession $session)
    {
        $this->funnels = $funnels;
        $this->session = $session;

        $this->middleware(['permission:view_funnels'])->only('index');
        $this->middleware(['permission:add_funnel'])->only(['create', 'store', 'duplicate', 'products']);
        $this->middleware(['permission:edit_funnel'])->only('builder');
        $this->middleware(['permission:publish_funnel'])->only(['publish', 'unpublish', 'archive', 'restore']);
        $this->middleware(['permission:delete_funnel'])->only('destroy');
    }

    public function index(Request $request, FunnelAnalytics $analytics)
    {
        $statuses = config('funnel.statuses');
        $status = in_array($request->status, $statuses, true) ? $request->status : null;
        $sorts = [
            'updated' => ['updated_at', 'desc'],
            'created' => ['created_at', 'desc'],
            'title' => ['title', 'asc'],
            'published' => ['published_at', 'desc'],
        ];
        $sort = array_key_exists($request->sort, $sorts) ? $request->sort : 'updated';

        $funnels = Funnel::query()
            ->select(array_merge(Funnel::hasPublishingColumns() ? ['unpublish_at'] : [], [
                'id', 'internal_name', 'title', 'slug', 'status', 'draft_hash', 'draft_updated_at',
                'published_version_id', 'published_at', 'scheduled_at', 'thumbnail_upload_id', 'product_ids',
                'created_at', 'updated_at', 'updated_by',
            ]))
            ->with(['publishedVersion:id,content_hash', 'editor:id,name'])
            ->search($request->search)
            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->orderBy($sorts[$sort][0], $sorts[$sort][1])
            ->orderByDesc('id')
            ->paginate(15)
            ->appends($request->only(['search', 'status', 'sort']));

        $statusCounts = Funnel::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // One query for every product shown on this page of the list.
        $productIds = $funnels->getCollection()->pluck('product_ids')->flatten()->filter()->unique()->values();
        $products = $productIds->isEmpty()
            ? collect()
            : Product::whereIn('id', $productIds)->get(['id', 'name', 'thumbnail_img'])->keyBy('id');

        // Last 30 days, with the analytics dashboard's definitions (orders from the order rows).
        $figures = $request->user()->can('view_funnel_analytics')
            ? $analytics->listSummary($funnels->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all(), now()->subDays(29), now())
            : null;

        return view('backend.funnels.index', [
            'funnels' => $funnels,
            'products' => $products,
            'figures' => $figures,
            'statuses' => $statuses,
            'status' => $status,
            'sort' => $sort,
            'statusCounts' => $statusCounts,
            'editorReady' => config('funnel.editor_url') !== '',
        ]);
    }

    public function create(TemplateLibrary $library)
    {
        return view('backend.funnels.create', [
            'publicBase' => config('funnel.public_url') . '/funnel/',
            'gallery' => $library->gallery(),
        ]);
    }

    /** Product search for the create form's product choice (the same read-only catalogue the editor uses). */
    public function products(Request $request, FunnelCatalogue $catalogue)
    {
        $page = $catalogue->search(['q' => (string) $request->query('q', ''), 'in_stock' => true], 8);

        return response()->json(['data' => $catalogue->present($page->getCollection())]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'internal_name' => 'nullable|string|max:150',
            'slug' => 'nullable|string|max:80',
            'description' => 'nullable|string|max:2000',
            'template_id' => 'nullable|integer',
            'slots' => 'nullable|array',
            'slots.*' => 'nullable|integer|min:1',
        ]);

        try {
            $fields = $request->only(['title', 'internal_name', 'slug', 'description']);
            $template = $request->filled('template_id') ? FunnelTemplate::find((int) $request->template_id) : null;
            if ($request->filled('template_id') && !$template) {
                return back()->withInput()->withErrors(['template_id' => translate('Choose one of the templates shown.')]);
            }

            if ($template) {
                $choices = [];
                foreach ((array) $request->input('slots', []) as $slot => $productId) {
                    if ($productId) {
                        $choices[(string) $slot] = ['product_id' => (int) $productId];
                    }
                }
                $funnel = $this->funnels->createFromTemplate($fields, $template, $choices, $request->user());
            } else {
                $funnel = $this->funnels->create($fields, $request->user());
            }
        } catch (FunnelException $e) {
            $field = $e->errorCode() === 'invalid_slug' ? 'slug' : (in_array($e->errorCode(), ['unknown_product', 'unknown_slot'], true) ? 'template_id' : 'title');

            return back()->withInput()->withErrors([$field => $e->getMessage()]);
        }

        flash(translate('Funnel has been created'))->success();

        if (config('funnel.editor_url') !== '' && $request->user()->can('edit_funnel')) {
            return redirect()->route('funnels.builder', $funnel->id);
        }

        return redirect()->route('funnels.index');
    }

    /**
     * Opens the visual editor for one funnel, signed in as the current admin.
     */
    public function builder(Request $request, $id)
    {
        $funnel = Funnel::findOrFail($id);

        if (config('funnel.editor_url') === '') {
            flash(translate('The funnel editor is not configured yet. Set FUNNEL_EDITOR_URL in the environment.'))->error();
            return redirect()->route('funnels.index');
        }
        if ($funnel->status === Funnel::STATUS_ARCHIVED) {
            flash(translate('This funnel is archived. Restore it before editing.'))->warning();
            return redirect()->route('funnels.index');
        }

        $code = $this->session->issueHandoff($request->user(), $funnel);

        return redirect()->away($this->session->editorUrl($funnel, $code));
    }

    public function duplicate(Request $request, $id)
    {
        return $this->act(function () use ($request, $id) {
            $this->funnels->duplicate(Funnel::findOrFail($id), $request->user());
            return translate('Funnel has been duplicated as a new draft');
        });
    }

    public function publish(Request $request, $id)
    {
        return $this->act(function () use ($request, $id) {
            $this->funnels->publish(Funnel::findOrFail($id), $request->user());
            return translate('Funnel has been published');
        });
    }

    public function unpublish(Request $request, $id)
    {
        return $this->act(function () use ($request, $id) {
            $this->funnels->unpublish(Funnel::findOrFail($id), $request->user());
            return translate('Funnel has been unpublished');
        });
    }

    public function archive(Request $request, $id)
    {
        return $this->act(function () use ($request, $id) {
            $this->funnels->archive(Funnel::findOrFail($id), $request->user());
            return translate('Funnel has been archived');
        });
    }

    public function restore(Request $request, $id)
    {
        return $this->act(function () use ($request, $id) {
            $this->funnels->restore(Funnel::findOrFail($id), $request->user());
            return translate('Funnel has been restored');
        });
    }

    public function destroy(Request $request, $id)
    {
        return $this->act(function () use ($request, $id) {
            $this->funnels->delete(Funnel::findOrFail($id), $request->user());
            return translate('Funnel has been deleted. Orders placed through it are kept.');
        });
    }

    private function act(callable $action)
    {
        try {
            flash($action())->success();
        } catch (FunnelException $e) {
            $message = $e->getMessage();
            $errors = $e->details()['errors'] ?? [];
            if ($errors !== []) {
                $message .= ' ' . collect($errors)->take(3)->pluck('message')->implode(' ');
            }
            flash($message)->error();
        }

        return back();
    }
}
