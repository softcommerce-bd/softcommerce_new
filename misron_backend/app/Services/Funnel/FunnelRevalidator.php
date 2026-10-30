<?php

namespace App\Services\Funnel;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tells the storefront to rebuild the cached copy of a funnel page.
 *
 * The public page is cached by the Next.js app. The cache is short-lived on
 * its own (config funnel.revalidate.seconds), and this call makes a publish,
 * an unpublish or a change of address show up at once instead of waiting.
 *
 * It is best effort by design: a missing or unreachable storefront must never
 * make a publish fail, because the short cache lifetime still catches up.
 */
class FunnelRevalidator
{
    /**
     * @param string[] $slugs addresses whose cached page is stale
     */
    public function pages(array $slugs): void
    {
        $slugs = array_values(array_unique(array_filter($slugs)));
        $url = (string) config('funnel.revalidate.url');
        $secret = (string) config('funnel.revalidate.secret');

        if ($slugs === [] || $url === '' || $secret === '') {
            return;
        }

        try {
            Http::timeout((int) config('funnel.revalidate.timeout_seconds'))
                ->withHeaders(['Authorization' => 'Bearer ' . $secret])
                ->acceptJson()
                ->post($url, ['slugs' => $slugs]);
        } catch (\Throwable $e) {
            Log::warning('Funnel page cache refresh failed: ' . $e->getMessage(), ['slugs' => $slugs]);
        }
    }
}
