<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Services\Funnel\Analytics\EventRecorder;
use App\Services\Funnel\Analytics\FunnelAnalytics;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The funnel analytics dashboard of the admin: visitors, orders and revenue for
 * all funnels or one, over a date range. Orders and money come from the order
 * records (see FunnelAnalytics for the definitions).
 */
class FunnelAnalyticsController extends Controller
{
    private const MAX_DAYS = 400;

    public function __construct()
    {
        $this->middleware(['permission:view_funnel_analytics']);
    }

    public function index(Request $request, FunnelAnalytics $analytics)
    {
        [$from, $to] = $this->range($request);
        $funnelId = ctype_digit((string) $request->funnel) ? (int) $request->funnel : null;
        $funnel = $funnelId ? Funnel::withTrashed()->find($funnelId) : null;
        if ($funnelId && !$funnel) {
            $funnelId = null;
        }

        $days = $from->diffInDays($to) + 1;
        $interval = in_array($request->interval, ['day', 'week', 'month'], true)
            ? $request->interval
            : ($days > 120 ? 'month' : ($days > 31 ? 'week' : 'day'));

        $report = $analytics->report($funnelId, $from, $to, $interval);

        if ($request->wantsJson()) {
            return response()->json(['result' => true, 'report' => $report]);
        }

        return view('backend.funnels.analytics', [
            'report' => $report,
            'funnel' => $funnel,
            'funnels' => Funnel::withTrashed()->orderBy('title')->get(['id', 'title', 'status', 'deleted_at']),
            'from' => $from,
            'to' => $to,
            'interval' => $interval,
            'eventsReady' => EventRecorder::ready(),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} the requested range, the last 30 days by default */
    private function range(Request $request): array
    {
        $parse = function ($value) {
            if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return null;
            }
            try {
                return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        };

        $to = $parse($request->to) ?? now()->startOfDay();
        $from = $parse($request->from) ?? $to->copy()->subDays(29);
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $from = $to->copy()->subDays(self::MAX_DAYS);
        }

        return [$from, $to];
    }
}
