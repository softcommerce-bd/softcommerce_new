<?php

namespace App\Services\Funnel\Analytics;

use App\Models\Funnel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stores what visitors did on a live funnel page, for the analytics dashboard.
 *
 * The browser sends small batches. Every event carries an id the browser made,
 * and a repeated id is ignored, so a retried beacon is counted once. Events of
 * unknown type, bots and malformed values are dropped silently: analytics must
 * never make a page fail. Nothing personal is stored (see the funnel_events
 * migration). Orders are not recorded here; they are counted from the orders.
 */
class EventRecorder
{
    public const TYPES = ['page_view', 'product_view', 'product_select', 'checkout_start'];
    public const MAX_BATCH = 25;

    private static ?bool $ready = null;

    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasTable('funnel_events');
    }

    public static function forgetReadiness(): void
    {
        self::$ready = null;
    }

    /** @return int how many events were new */
    public function record(Funnel $funnel, $body, ?string $userAgent): int
    {
        if (!self::ready() || !is_array($body) || !is_array($body['events'] ?? null)) {
            return 0;
        }

        $device = TrafficSource::device($userAgent);
        if ($device === 'bot') {
            return 0;
        }

        $context = is_array($body['context'] ?? null) ? $body['context'] : [];
        $click = in_array($context['click'] ?? null, ['fbclid', 'gclid'], true) ? $context['click'] : null;
        $utmSource = TrafficSource::label($context['utm_source'] ?? null, 150);
        $source = TrafficSource::source($utmSource, TrafficSource::host($context['referrer_host'] ?? null), $click);

        $common = [
            'funnel_id' => $funnel->id,
            'funnel_version_id' => $funnel->published_version_id,
            'visitor_id' => TrafficSource::id($body['visitor_id'] ?? null),
            'session_id' => TrafficSource::id($body['session_id'] ?? null),
            'device' => $device,
            'source' => $source,
            'medium' => TrafficSource::label($context['utm_medium'] ?? null, 60),
            'campaign' => TrafficSource::label($context['utm_campaign'] ?? null, 120),
            'created_at' => now(),
        ];

        $rows = [];
        foreach (array_slice($body['events'], 0, self::MAX_BATCH) as $event) {
            if (!is_array($event) || !in_array($event['type'] ?? null, self::TYPES, true)) {
                continue;
            }
            $id = TrafficSource::id($event['id'] ?? null);
            if ($id === null) {
                continue;
            }
            $offer = $event['offer_key'] ?? null;
            $rows[$id] = $common + [
                'event_id' => $id,
                'type' => $event['type'],
                'offer_key' => is_string($offer) && preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $offer) ? $offer : null,
            ];
        }

        return $rows === [] ? 0 : DB::table('funnel_events')->insertOrIgnore(array_values($rows));
    }
}
