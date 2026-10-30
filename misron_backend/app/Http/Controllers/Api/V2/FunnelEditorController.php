<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\Funnel;
use App\Models\FunnelSavedSection;
use App\Models\FunnelTemplate;
use App\Services\Funnel\Analytics\TestOrderToken;
use App\Services\Funnel\SavedSectionService;
use App\Models\FunnelVersion;
use App\Services\Funnel\BlockRegistry;
use App\Services\Funnel\Checkout\FunnelOrderService;
use App\Services\Funnel\EditorSession;
use App\Services\Funnel\FunnelCatalogue;
use App\Services\Funnel\FunnelException;
use App\Services\Funnel\FunnelMediaService;
use App\Services\Funnel\FunnelPresenter;
use App\Services\Funnel\FunnelPreviewService;
use App\Services\Funnel\FunnelService;
use App\Services\Funnel\Offers\OfferEngine;
use App\Services\Funnel\PageValidator;
use App\Services\Funnel\SchemaMigrator;
use App\Services\Funnel\Templates\TemplateLibrary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * JSON API behind the visual funnel editor.
 *
 * Every route except exchange() runs behind auth:sanctum and the
 * funnel.editor middleware; each action then checks its own staff
 * permission, so calling the API directly grants nothing the admin role
 * does not already allow.
 */
class FunnelEditorController extends Controller
{
    private $funnels;
    private $session;
    private $registry;
    private $presenter;

    public function __construct(FunnelService $funnels, EditorSession $session, BlockRegistry $registry, FunnelPresenter $presenter)
    {
        $this->funnels = $funnels;
        $this->session = $session;
        $this->registry = $registry;
        $this->presenter = $presenter;
    }

    public function exchange(Request $request)
    {
        $code = (string) $request->input('code', '');
        $session = $this->session->exchange($code);

        if ($session === null) {
            return $this->error('invalid_handoff', 'This editor link has expired. Open the editor again from the admin panel.', 401);
        }

        return response()->json([
            'result' => true,
            'token' => $session['token'],
            'expires_at' => $session['expires_at']->toIso8601String(),
            'funnel_id' => $session['funnel_id'],
            'user' => $this->userPayload($session['user']),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['result' => true, 'user' => $this->userPayload($request->user())]);
    }

    public function endSession(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['result' => true]);
    }

    public function registry(Request $request)
    {
        $this->authorizeTo($request, 'view_funnels');

        return response()->json([
            'result' => true,
            'schema_version' => (int) config('funnel.schema_version'),
            'limits' => config('funnel.limits'),
            'registry' => $this->registry->toArray(),
        ]);
    }

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'view_funnels');

        $status = $request->query('status');
        $sorts = [
            'updated' => ['updated_at', 'desc'],
            'created' => ['created_at', 'desc'],
            'title' => ['title', 'asc'],
        ];
        [$column, $direction] = $sorts[$request->query('sort')] ?? $sorts['updated'];

        $page = Funnel::query()
            ->select($this->listColumns())
            ->with('publishedVersion:id,content_hash')
            ->search($request->query('q'))
            ->when(in_array($status, config('funnel.statuses'), true), function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->orderBy($column, $direction)
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return response()->json([
            'result' => true,
            'data' => $page->getCollection()->map(function (Funnel $funnel) {
                return $this->presenter->summary($funnel);
            })->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTo($request, 'add_funnel');
        $data = $this->payload($request);

        return $this->attempt(function () use ($request, $data) {
            $fields = [
                'title' => $data['title'] ?? '',
                'internal_name' => $data['internal_name'] ?? null,
                'slug' => $data['slug'] ?? null,
                'description' => $data['description'] ?? null,
                'content' => $data['content'] ?? null,
            ];

            // From a template: "template_id" plus "slots" {slotKey: {product_id, variant}}.
            if (isset($data['template_id']) && is_int($data['template_id'])) {
                $template = FunnelTemplate::find($data['template_id']);
                if (!$template) {
                    return $this->error('unknown_template', 'That template does not exist.', 422);
                }
                $slots = isset($data['slots']) && is_array($data['slots']) ? $data['slots'] : [];
                $funnel = $this->funnels->createFromTemplate($fields, $template, $slots, $request->user());
            } else {
                $funnel = $this->funnels->create($fields, $request->user());
            }

            return response()->json(['result' => true, 'data' => $this->presenter->detail($funnel->fresh())], 201);
        });
    }

    public function show(Request $request, $id)
    {
        $this->authorizeTo($request, 'view_funnels');
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }

        return response()->json(['result' => true, 'data' => $this->presenter->detail($funnel)]);
    }

    public function updateDetails(Request $request, $id)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }

        $data = array_intersect_key($this->payload($request), array_flip([
            'title', 'internal_name', 'slug', 'description', 'meta_title', 'meta_description',
            'og_image_upload_id', 'thumbnail_upload_id', 'noindex', 'og_title', 'og_description', 'canonical_url',
        ]));

        $lengths = ['title' => 200, 'internal_name' => 150, 'description' => 2000, 'meta_title' => 255, 'meta_description' => 500];
        foreach ($lengths as $field => $max) {
            if (isset($data[$field]) && (!is_string($data[$field]) || mb_strlen($data[$field]) > $max)) {
                return $this->error('invalid_' . $field, "The {$field} must be text of at most {$max} characters.", 422);
            }
        }

        return $this->attempt(function () use ($request, $funnel, $data) {
            $funnel = $this->funnels->updateDetails($funnel, $data, $request->user());

            return response()->json(['result' => true, 'data' => $this->presenter->detail($funnel->fresh())]);
        });
    }

    /**
     * Autosave. The body is read raw so the global TrimStrings and
     * ConvertEmptyStringsToNull middleware cannot rewrite page text: an
     * emptied heading must stay empty, not fall back to its default.
     */
    public function saveDraft(Request $request, $id)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }

        $data = $this->payload($request);
        if (!isset($data['content']) || !is_array($data['content'])) {
            return $this->error('missing_content', 'No page content was sent.', 422);
        }
        if (!isset($data['revision']) || !is_int($data['revision'])) {
            return $this->error('missing_revision', 'The draft revision is missing.', 422);
        }

        return $this->attempt(function () use ($request, $funnel, $data) {
            [$funnel, $result] = $this->funnels->saveDraft($funnel, $data['content'], $data['revision'], $request->user());

            return response()->json([
                'result' => true,
                'revision' => $funnel->draft_revision,
                'hash' => $funnel->draft_hash,
                'saved_at' => optional($funnel->draft_updated_at)->toIso8601String(),
                'has_unpublished_changes' => $funnel->hasUnpublishedChanges(),
                'warnings' => $result->warnings(),
            ]);
        });
    }

    /**
     * Dry run of the publish checks, on the stored draft or on content sent
     * with the request. Nothing is saved.
     */
    public function validateDraft(Request $request, $id)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }

        $data = $this->payload($request);
        $result = $this->funnels->check($funnel, isset($data['content']) && is_array($data['content']) ? $data['content'] : null);

        return response()->json(['result' => true, 'valid' => $result->isValid()] + $result->toArray());
    }

    public function publish(Request $request, $id)
    {
        $this->authorizeTo($request, 'publish_funnel');
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }

        $label = $this->payload($request)['label'] ?? null;
        $label = is_string($label) ? mb_substr(trim($label), 0, 120) : null;

        return $this->attempt(function () use ($request, $funnel, $label) {
            [$funnel, $version, $result] = $this->funnels->publish($funnel, $request->user(), $label ?: null);

            return response()->json([
                'result' => true,
                'data' => $this->presenter->detail($funnel),
                'version' => $this->presenter->version($version, $funnel->published_version_id),
                'warnings' => $result->warnings(),
            ]);
        });
    }

    public function unpublish(Request $request, $id)
    {
        return $this->stateChange($request, $id, 'publish_funnel', 'unpublish');
    }

    public function archive(Request $request, $id)
    {
        return $this->stateChange($request, $id, 'publish_funnel', 'archive');
    }

    public function restore(Request $request, $id)
    {
        return $this->stateChange($request, $id, 'publish_funnel', 'restore');
    }

    public function duplicate(Request $request, $id)
    {
        $this->authorizeTo($request, 'add_funnel');
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }

        return $this->attempt(function () use ($request, $funnel) {
            $copy = $this->funnels->duplicate($funnel, $request->user());

            return response()->json(['result' => true, 'data' => $this->presenter->detail($copy->fresh())], 201);
        });
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeTo($request, 'delete_funnel');
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }

        return $this->attempt(function () use ($request, $funnel) {
            $this->funnels->delete($funnel, $request->user());

            return response()->json(['result' => true]);
        });
    }

    public function versions(Request $request, $id)
    {
        $this->authorizeTo($request, 'view_funnels');
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }

        $versions = FunnelVersion::where('funnel_id', $funnel->id)
            ->select(['id', 'funnel_id', 'version_number', 'kind', 'label', 'schema_version', 'created_by', 'created_at'])
            ->with('creator:id,name')
            ->orderByDesc('version_number')
            ->limit(100)
            ->get();

        return response()->json([
            'result' => true,
            'data' => $versions->map(function (FunnelVersion $version) use ($funnel) {
                return $this->presenter->version($version, $funnel->published_version_id ? (int) $funnel->published_version_id : null);
            })->values(),
        ]);
    }

    /**
     * Loads a stored version into the draft. With "publish": true it is also
     * published, which needs the publish permission on top of edit.
     */
    public function rollback(Request $request, $id, $versionId)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $publish = ($this->payload($request)['publish'] ?? false) === true;
        if ($publish) {
            $this->authorizeTo($request, 'publish_funnel');
        }

        $funnel = $this->findFunnel($id);
        $version = $funnel ? FunnelVersion::where('funnel_id', $funnel->id)->find((int) $versionId) : null;
        if (!$funnel || !$version) {
            return $this->notFound();
        }

        return $this->attempt(function () use ($request, $funnel, $version, $publish) {
            $funnel = $this->funnels->rollback($funnel, $version, $request->user());
            if ($publish) {
                [$funnel] = $this->funnels->publish($funnel, $request->user(), 'Restored version ' . $version->version_number);
            }

            return response()->json(['result' => true, 'data' => $this->presenter->detail($funnel->fresh())]);
        });
    }

    /**
     * Images from the shared media library, newest first.
     */
    public function media(Request $request, FunnelMediaService $media)
    {
        $this->authorizeTo($request, 'edit_funnel');

        $page = $media->search($request->query('q'), 30);

        return response()->json([
            'result' => true,
            'data' => $page->getCollection()->map(function ($upload) use ($media) {
                return $media->present($upload);
            })->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function uploadMedia(Request $request, FunnelMediaService $media)
    {
        $this->authorizeTo($request, 'edit_funnel');

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return $this->error('missing_file', 'Choose an image to upload.', 422);
        }

        return $this->attempt(function () use ($request, $media, $file) {
            $upload = $media->store($file, $request->user());

            return response()->json(['result' => true, 'data' => $media->present($upload)], 201);
        });
    }

    /**
     * Product search for the product picker. Reads the existing catalogue;
     * see FunnelCatalogue.
     */
    public function products(Request $request, FunnelCatalogue $catalogue)
    {
        $this->authorizeTo($request, 'edit_funnel');

        $page = $catalogue->search(
            $request->only(['q', 'category_id', 'brand_id', 'status', 'in_stock', 'ids']),
            (int) $request->query('per_page', 20)
        );

        return response()->json([
            'result' => true,
            'data' => $catalogue->present($page->getCollection()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Live data for the offers being edited (names, images, prices, stock,
     * status), so the canvas draws the same cards customers will see. Takes
     * the draft's bindings, not the published ones.
     */
    public function resolveOffers(Request $request, OfferEngine $engine, PageValidator $validator)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $bindings = $this->payload($request)['bindings'] ?? [];

        $result = $validator->validateBindingList($bindings);
        if (!$result->isValid()) {
            return response()->json(['result' => false, 'code' => 'invalid_offers', 'message' => 'The offers have problems.'] + $result->toArray(), 422);
        }

        return response()->json([
            'result' => true,
            'offers' => $engine->resolve($result->document['bindings']),
            'warnings' => $result->warnings(),
        ]);
    }

    /** The authoritative price of a selection against draft offers: the editor's Preview. */
    public function quoteOffers(Request $request, OfferEngine $engine, PageValidator $validator, FunnelOrderService $orders)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $body = $this->payload($request);

        $result = $validator->validateBindingList($body['bindings'] ?? []);
        if (!$result->isValid()) {
            return response()->json(['result' => false, 'code' => 'invalid_offers', 'message' => 'The offers have problems.'] + $result->toArray(), 422);
        }

        $quote = $engine->quote($result->document['bindings'], $body['selections'] ?? []);

        // The same delivery pricing the live page uses, so Preview shows what customers will see.
        $district = $body['district_id'] ?? null;
        if ($quote['valid'] && (is_int($district) || (is_string($district) && ctype_digit($district))) && (int) $district > 0) {
            $quote = $orders->withDelivery($quote, (int) $district);
        }
        unset($quote['_cents']);

        return response()->json(['result' => $quote['valid']] + $quote, $quote['valid'] ? 200 : 422);
    }

    /** One stored version with its page document, for viewing before restoring it. */
    public function version(Request $request, $id, $versionId)
    {
        $this->authorizeTo($request, 'view_funnels');
        $funnel = $this->findFunnel($id);
        $version = $funnel && ctype_digit((string) $versionId) ? FunnelVersion::with('creator:id,name')->where('funnel_id', $funnel->id)->find((int) $versionId) : null;
        if (!$funnel || !$version) {
            return $this->notFound();
        }

        return response()->json([
            'result' => true,
            'data' => $this->presenter->version($version, $funnel->published_version_id ? (int) $funnel->published_version_id : null),
            'content' => app(SchemaMigrator::class)->migrate($version->content),
        ]);
    }

    /**
     * Publishes at a chosen time. Body: at (ISO 8601 with offset), until (optional end), label.
     */
    public function schedule(Request $request, $id)
    {
        $this->authorizeTo($request, 'publish_funnel');
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }

        $body = $this->payload($request);

        return $this->attempt(function () use ($request, $funnel, $body) {
            $at = $this->dateField($body, 'at', true);
            $until = $this->dateField($body, 'until', false);
            $label = isset($body['label']) && is_string($body['label']) ? mb_substr(trim($body['label']), 0, 120) : null;

            [$funnel, $version, $result] = $this->funnels->schedule($funnel, $at, $until, $request->user(), $label ?: null);

            return response()->json([
                'result' => true,
                'data' => $this->presenter->detail($funnel),
                'version' => $this->presenter->version($version, $funnel->published_version_id),
                'warnings' => $result->warnings(),
            ]);
        });
    }

    public function cancelSchedule(Request $request, $id)
    {
        return $this->stateChange($request, $id, 'publish_funnel', 'cancelSchedule');
    }

    /** Sets (until) or clears (until: null) the moment a live page goes offline by itself. */
    public function endTime(Request $request, $id)
    {
        $this->authorizeTo($request, 'publish_funnel');
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }

        $body = $this->payload($request);

        return $this->attempt(function () use ($request, $funnel, $body) {
            $until = $this->dateField($body, 'until', false);
            $funnel = $this->funnels->setEndTime($funnel, $until, $request->user());

            return response()->json(['result' => true, 'data' => $this->presenter->detail($funnel->fresh())]);
        });
    }

    public function previewLinks(Request $request, $id, FunnelPreviewService $previews)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }

        return response()->json([
            'result' => true,
            'data' => $previews->list($funnel)->map(function ($link) {
                return $this->presenter->previewLink($link);
            })->values(),
        ]);
    }

    /** Creates a secret draft link. The link itself is in this response only. */
    public function createPreviewLink(Request $request, $id, FunnelPreviewService $previews)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }

        $body = $this->payload($request);
        $hours = isset($body['hours']) && is_int($body['hours']) ? $body['hours'] : null;
        $label = isset($body['label']) && is_string($body['label']) ? $body['label'] : null;

        return $this->attempt(function () use ($request, $funnel, $previews, $hours, $label) {
            [$link, $secret] = $previews->create($funnel, $request->user(), $hours, $label);

            return response()->json([
                'result' => true,
                'data' => $this->presenter->previewLink($link),
                'url' => config('funnel.public_url') . '/funnel/' . $funnel->slug . '/preview/' . $secret,
            ], 201);
        });
    }

    /**
     * A link to the live page that marks the order placed through it as a test order:
     * the order is real (it shows in Orders, marked "Test") but is left out of the
     * analytics and never reported to ad platforms.
     */
    public function testOrderLink(Request $request, $id)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }
        if (!$funnel->isLive()) {
            return $this->error('not_live', 'Publish the page first: test orders are placed on the live page.', 409);
        }

        $hours = (int) config('funnel.analytics.test_link_hours', 24);

        return response()->json([
            'result' => true,
            'url' => config('funnel.public_url') . '/funnel/' . $funnel->slug . '?fn_test=' . TestOrderToken::make((int) $funnel->id, $hours),
            'expires_in_hours' => $hours,
        ]);
    }

    // ── Saved sections ──────────────────────────────────────────────────────

    public function savedSections(Request $request, SavedSectionService $sections)
    {
        $this->authorizeTo($request, 'edit_funnel');

        return response()->json([
            'result' => true,
            'data' => $sections->list($request->query('search'))->map(fn ($section) => $this->savedSectionPayload($section))->values(),
        ]);
    }

    public function createSavedSection(Request $request, SavedSectionService $sections)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $body = $this->payload($request);

        return $this->attempt(function () use ($request, $sections, $body) {
            $section = $sections->create($body['node'] ?? null, $body['name'] ?? null, $request->user());

            return response()->json(['result' => true, 'data' => $this->savedSectionPayload($section)], 201);
        });
    }

    public function updateSavedSection(Request $request, $sectionId, SavedSectionService $sections)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $section = ctype_digit((string) $sectionId) ? FunnelSavedSection::find((int) $sectionId) : null;
        if (!$section) {
            return $this->error('not_found', 'Saved section not found.', 404);
        }
        $body = $this->payload($request);

        return $this->attempt(fn () => response()->json(['result' => true, 'data' => $this->savedSectionPayload($sections->rename($section, $body['name'] ?? null))]));
    }

    public function duplicateSavedSection(Request $request, $sectionId, SavedSectionService $sections)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $section = ctype_digit((string) $sectionId) ? FunnelSavedSection::find((int) $sectionId) : null;
        if (!$section) {
            return $this->error('not_found', 'Saved section not found.', 404);
        }

        return $this->attempt(fn () => response()->json(['result' => true, 'data' => $this->savedSectionPayload($sections->duplicate($section, $request->user()))], 201));
    }

    public function deleteSavedSection(Request $request, $sectionId, SavedSectionService $sections)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $section = ctype_digit((string) $sectionId) ? FunnelSavedSection::find((int) $sectionId) : null;
        if (!$section) {
            return $this->error('not_found', 'Saved section not found.', 404);
        }
        $sections->delete($section);

        return response()->json(['result' => true]);
    }

    private function savedSectionPayload(FunnelSavedSection $section): array
    {
        return [
            'id' => $section->id,
            'name' => $section->name,
            'type' => $section->category,
            'node' => $section->content,
            'updated_at' => optional($section->updated_at)->toIso8601String(),
        ];
    }

    public function revokePreviewLink(Request $request, $id, $linkId, FunnelPreviewService $previews)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $funnel = $this->findFunnel($id);
        if (!$funnel || !ctype_digit((string) $linkId)) {
            return $this->notFound();
        }

        return $this->attempt(function () use ($request, $funnel, $linkId, $previews) {
            $link = $previews->revoke($funnel, (int) $linkId, $request->user());

            return response()->json(['result' => true, 'data' => $this->presenter->previewLink($link)]);
        });
    }

    /** Puts a product into one slot of the funnel's template. Body: slot, product_id, variant, revision. */
    public function fillTemplateSlot(Request $request, $id)
    {
        $this->authorizeTo($request, 'edit_funnel');
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }

        $body = $this->payload($request);
        if (!isset($body['slot']) || !is_string($body['slot']) || !isset($body['product_id']) || !is_int($body['product_id'])) {
            return $this->error('invalid_slot_request', 'Send the slot and the product.', 422);
        }

        return $this->attempt(function () use ($request, $funnel, $body) {
            [$funnel] = $this->funnels->fillTemplateSlot(
                $funnel,
                $body['slot'],
                $body['product_id'],
                isset($body['variant']) && is_string($body['variant']) ? $body['variant'] : null,
                isset($body['revision']) && is_int($body['revision']) ? $body['revision'] : null,
                $request->user()
            );

            return response()->json(['result' => true, 'data' => $this->presenter->detail($funnel->fresh())]);
        });
    }

    /** The template gallery, one entry per design with its Bengali and English versions. */
    public function templates(Request $request, TemplateLibrary $library)
    {
        $this->authorizeTo($request, 'view_funnels');

        return response()->json(['result' => true, 'data' => $library->gallery()]);
    }

    /** An ISO 8601 date-time from the request body, or null when absent and not required. */
    private function dateField(array $body, string $field, bool $required): ?Carbon
    {
        $value = $body[$field] ?? null;
        if ($value === null || $value === '') {
            if ($required) {
                throw new FunnelException('Choose a date and time.', 'invalid_schedule', 422);
            }

            return null;
        }
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new FunnelException('Dates must look like 2026-12-31T18:00:00+06:00.', 'invalid_schedule', 422);
        }

        return Carbon::parse($value);
    }

    private function stateChange(Request $request, $id, string $permission, string $method)
    {
        $this->authorizeTo($request, $permission);
        $funnel = $this->findFunnel($id);
        if (!$funnel) {
            return $this->notFound();
        }

        return $this->attempt(function () use ($request, $funnel, $method) {
            $funnel = $this->funnels->{$method}($funnel, $request->user());

            return response()->json(['result' => true, 'data' => $this->presenter->detail($funnel->fresh())]);
        });
    }

    private function attempt(callable $action)
    {
        try {
            return $action();
        } catch (FunnelException $e) {
            return response()->json(array_merge(
                ['result' => false, 'code' => $e->errorCode(), 'message' => $e->getMessage()],
                $e->details()
            ), $e->status());
        }
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        if (!$request->user()->can($permission)) {
            abort(403, 'You do not have permission to do this.');
        }
    }

    /** Raw JSON body; see saveDraft() for why the parsed input is not used. */
    private function payload(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true, 128);

        return is_array($decoded) ? $decoded : [];
    }

    private function findFunnel($id): ?Funnel
    {
        if (!ctype_digit((string) $id)) {
            return null;
        }

        return Funnel::with('publishedVersion:id,funnel_id,version_number,kind,label,schema_version,content_hash,created_at')
            ->find((int) $id);
    }

    private function listColumns(): array
    {
        return array_merge(Funnel::hasPublishingColumns() ? ['unpublish_at'] : [], [
            'id', 'internal_name', 'title', 'slug', 'status', 'draft_revision', 'draft_hash', 'draft_updated_at',
            'published_version_id', 'published_at', 'scheduled_at', 'thumbnail_upload_id', 'product_ids',
            'created_by', 'created_at', 'updated_at',
        ]);
    }

    private function userPayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'permissions' => $this->presenter->permissions($user),
            // Where the editor's Back button leads: the funnel list in the admin.
            'return_url' => route('funnels.index'),
        ];
    }

    private function notFound()
    {
        return $this->error('not_found', 'Funnel not found.', 404);
    }

    private function error(string $code, string $message, int $status)
    {
        return response()->json(['result' => false, 'code' => $code, 'message' => $message], $status);
    }
}
