<?php

namespace App\Services\Funnel\Offers;

use App\Models\Funnel;
use Illuminate\Support\Facades\Cache;

/**
 * The offers of a funnel as published: the rules every customer-facing price
 * is computed from. A published version never changes, so its offers are read
 * once and kept; a new publication is a new version id and so a new entry.
 */
class PublishedOffers
{
    /**
     * The live funnel for a public slug, or null when it is not on sale
     * (draft, unpublished, archived, scheduled for later, deleted).
     */
    public function liveFunnel(string $slug): ?Funnel
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return null;
        }

        $funnel = Funnel::with('publishedVersion')->where('slug', $slug)->first();

        return $funnel && $funnel->isLive() && $funnel->publishedVersion ? $funnel : null;
    }

    /** @return array clean bindings of the published version */
    public function bindings(Funnel $funnel): array
    {
        $version = $funnel->publishedVersion;

        return Cache::rememberForever('funnel_offers:v' . $version->id, function () use ($version) {
            return self::extract($version->content);
        });
    }

    /**
     * Every product binding in a page document, in page order.
     */
    public static function extract(?array $document): array
    {
        $found = [];
        $visit = function (array $nodes) use (&$visit, &$found) {
            foreach ($nodes as $node) {
                foreach ($node['productBindings'] ?? [] as $binding) {
                    $found[] = $binding;
                }
                if (!empty($node['children'])) {
                    $visit($node['children']);
                }
            }
        };
        $visit($document['page']['sections'] ?? []);

        return $found;
    }
}
