<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\Funnel;
use App\Services\Funnel\Analytics\EventRecorder;
use App\Services\Funnel\Checkout\FunnelOrderService;
use App\Services\Funnel\FunnelPreviewService;
use App\Services\Funnel\FunnelSeo;
use App\Services\Funnel\Offers\OfferEngine;
use App\Services\Funnel\Offers\PublishedOffers;
use App\Services\Funnel\PageValidator;
use App\Services\Funnel\SchemaMigrator;
use Illuminate\Http\Request;

/**
 * Customer-facing funnel API: the published page, live offer data and the
 * authoritative price of a selection. Also the same three reads for a draft
 * behind a secret preview link.
 *
 * Only the published version is ever read for customers, so edits to a draft
 * cannot leak into prices. The quote reads offer key, variation and quantity
 * from the request and nothing else: a body that also carries "price",
 * "total" or "discount" has those fields ignored.
 */
class FunnelPublicController extends Controller
{
    private $published;
    private $engine;
    private $orders;
    private $previews;
    private $seo;

    public function __construct(PublishedOffers $published, OfferEngine $engine, FunnelOrderService $orders, FunnelPreviewService $previews, FunnelSeo $seo)
    {
        $this->published = $published;
        $this->engine = $engine;
        $this->orders = $orders;
        $this->previews = $previews;
        $this->seo = $seo;
    }

    /**
     * Addresses of the pages customers can open now, for the sitemap.
     * Pages marked "noindex" are listed with the flag so the caller can skip them.
     */
    public function index()
    {
        $funnels = Funnel::query()
            ->whereIn('status', [Funnel::STATUS_PUBLISHED, Funnel::STATUS_SCHEDULED])
            ->orderByDesc('published_at')
            ->limit(500)
            ->get()
            ->filter(function (Funnel $funnel) {
                return $funnel->isLive();
            });

        return response()->json([
            'result' => true,
            'data' => $funnels->map(function (Funnel $funnel) {
                return [
                    'slug' => $funnel->slug,
                    'noindex' => $this->seo->settings($funnel)['noindex'],
                    'updated_at' => optional($funnel->updated_at)->toIso8601String(),
                ];
            })->values(),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * The published page itself: its document and the few details the page shell needs.
     * Never the draft, and nothing internal (the internal name, who edited it).
     */
    public function show($slug)
    {
        $funnel = $this->published->liveFunnel((string) $slug);
        if (!$funnel) {
            return $this->notFound();
        }

        $version = $funnel->publishedVersion;
        $offers = $this->engine->resolve($this->published->bindings($funnel));

        return response()->json([
            'result' => true,
            'funnel' => [
                'slug' => $funnel->slug,
                'title' => $funnel->title,
                'description' => $funnel->description,
                'meta_title' => $funnel->meta_title,
                'meta_description' => $funnel->meta_description,
                'version' => $version->version_number,
                'unpublish_at' => optional($funnel->unpublish_at)->toIso8601String(),
            ],
            'seo' => $this->seo->forFunnel($funnel, $version->content, $offers),
            'document' => $version->content,
        ])->header('Cache-Control', 'no-store');
    }

    public function offers($slug)
    {
        $funnel = $this->published->liveFunnel((string) $slug);
        if (!$funnel) {
            return $this->notFound();
        }

        return response()->json([
            'result' => true,
            'version' => $funnel->publishedVersion->version_number,
            'offers' => $this->engine->resolve($this->published->bindings($funnel)),
        ])->header('Cache-Control', 'no-store');
    }

    public function quote(Request $request, $slug)
    {
        $funnel = $this->published->liveFunnel((string) $slug);
        if (!$funnel) {
            return $this->notFound();
        }

        return $this->quoteResponse($request, $this->published->bindings($funnel));
    }

    /**
     * The draft behind a secret preview link. Reads the draft as it is now;
     * checks the link, not a login. Never cached, never indexed, no orders.
     */
    public function preview($slug, $token)
    {
        $funnel = $this->previews->resolve((string) $slug, (string) $token);
        if (!$funnel) {
            return $this->notFound();
        }

        $migrator = app(SchemaMigrator::class);
        $document = $migrator->migrate($funnel->draft_content ?: $migrator->emptyDocument());

        return $this->previewHeaders(response()->json([
            'result' => true,
            'funnel' => [
                'slug' => $funnel->slug,
                'title' => $funnel->title,
                'description' => $funnel->description,
                'meta_title' => $funnel->meta_title,
                'meta_description' => $funnel->meta_description,
                'status' => $funnel->status,
            ],
            'preview' => true,
            'seo' => ['noindex' => true] + $this->seo->forFunnel($funnel, $document),
            'document' => $document,
        ]));
    }

    public function previewOffers($slug, $token)
    {
        $funnel = $this->previews->resolve((string) $slug, (string) $token);
        if (!$funnel) {
            return $this->notFound();
        }

        return $this->previewHeaders(response()->json([
            'result' => true,
            'offers' => $this->engine->resolve($this->draftBindings($funnel)),
        ]));
    }

    public function previewQuote(Request $request, $slug, $token)
    {
        $funnel = $this->previews->resolve((string) $slug, (string) $token);
        if (!$funnel) {
            return $this->notFound();
        }

        return $this->previewHeaders($this->quoteResponse($request, $this->draftBindings($funnel)));
    }

    /** Clean offer bindings of the draft, as the editor would price them. */
    /**
     * Visitor activity for the analytics dashboard (views, product views and selections,
     * checkout starts). Only for live pages; a preview never records anything. Always
     * answers 202 for a live page, even when nothing was stored, so a page never retries
     * a batch that was dropped on purpose.
     */
    public function events(Request $request, $slug)
    {
        $funnel = $this->published->liveFunnel((string) $slug);
        if (!$funnel) {
            return $this->notFound();
        }

        $raw = $request->getContent();
        $body = strlen($raw) <= 16384 ? json_decode($raw, true, 8) : null;
        $stored = app(EventRecorder::class)->record($funnel, $body, $request->userAgent());

        return response()->json(['result' => true, 'stored' => $stored], 202)->header('Cache-Control', 'no-store');
    }

    private function draftBindings(Funnel $funnel): array
    {
        $result = app(PageValidator::class)->validate($funnel->draft_content, PageValidator::MODE_DRAFT);

        return $result->isValid() ? PublishedOffers::extract($result->document) : [];
    }

    private function quoteResponse(Request $request, array $bindings)
    {
        // Raw body: the global middleware would turn "" into null and trim text.
        $body = json_decode($request->getContent(), true, 32);
        $selections = is_array($body) ? ($body['selections'] ?? null) : null;

        $quote = $this->engine->quote($bindings, $selections ?? []);

        // With a district the quote also carries the delivery charge and the payable total.
        $district = is_array($body) ? ($body['district_id'] ?? null) : null;
        if ($quote['valid'] && (is_int($district) || (is_string($district) && ctype_digit($district))) && (int) $district > 0) {
            $quote = $this->orders->withDelivery($quote, (int) $district);
        }
        unset($quote['_cents']);

        return response()->json(['result' => $quote['valid']] + $quote, $quote['valid'] ? 200 : 422)
            ->header('Cache-Control', 'no-store');
    }

    private function previewHeaders($response)
    {
        return $response
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function notFound()
    {
        return response()->json(['result' => false, 'code' => 'not_found', 'message' => 'This page is not available.'], 404);
    }
}
