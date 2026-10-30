<?php

namespace App\Services\Funnel\Analytics;

use App\Services\Funnel\Checkout\FunnelOrderService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Figures for the funnel analytics dashboard.
 *
 * Two sources, never mixed up:
 *  - visitor activity (views, product views and selections, checkout starts)
 *    comes from funnel_events, which the page sends;
 *  - orders and money come from the store's own order rows only. A browser
 *    event can never make an order count as placed, paid or delivered.
 *
 * Terms used on the dashboard:
 *  - placed: every order taken through a funnel in the period (test orders left out);
 *  - paid: every order of the purchase is marked paid (online payment confirmed by
 *    the gateway, or cash collected and recorded by staff);
 *  - delivered: every order of the purchase is marked delivered;
 *  - cancelled: any order of the purchase is cancelled;
 *  - order value: what the non-cancelled orders are worth;
 *  - confirmed revenue: the value of orders that are not cancelled and are either
 *    paid or delivered (cash on delivery counts once it is delivered);
 *  - conversion rate: visitors who placed an order ÷ visitors. An order counts here only
 *    when its visit was seen (same anonymous visitor id), so orders from visitors whose
 *    browser sent no events cannot push the rate above 100 %. Order counts are exact
 *    either way.
 *
 * One "order" here is one checkout (a combined order); a checkout of products
 * from two sellers is stored as two order rows but counted once.
 */
class FunnelAnalytics
{
    /**
     * @param  int|null $funnelId one funnel, or null for all
     * @param  string   $interval day, week or month
     */
    public function report(?int $funnelId, Carbon $from, Carbon $to, string $interval = 'day'): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $interval = in_array($interval, ['day', 'week', 'month'], true) ? $interval : 'day';

        $orders = $this->orders($funnelId, $from, $to);
        $events = $this->eventTotals($funnelId, $from, $to);
        $steps = $this->steps($funnelId, $from, $to, $orders);

        $placed = count($orders);
        $live = array_filter($orders, fn ($o) => !$o['cancelled']);
        $paid = array_filter($orders, fn ($o) => $o['paid']);
        $delivered = array_filter($orders, fn ($o) => $o['delivered']);
        $cancelled = array_filter($orders, fn ($o) => $o['cancelled']);
        $confirmed = array_filter($live, fn ($o) => $o['paid'] || $o['delivered']);
        $orderValue = array_sum(array_column($live, 'total'));

        $cod = array_filter($orders, fn ($o) => $o['payment_type'] === 'cash_on_delivery');
        $online = array_filter($orders, fn ($o) => $o['payment_type'] !== 'cash_on_delivery');

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'interval' => $interval],
            'totals' => [
                'page_views' => $events['page_view'],
                'unique_visitors' => $events['visitors'],
                'product_views' => $events['product_view'],
                'product_selections' => $events['product_select'],
                'checkout_started' => $events['checkout_start'],
                'orders_placed' => $placed,
                'orders_paid' => count($paid),
                'orders_delivered' => count($delivered),
                'orders_cancelled' => count($cancelled),
                'converted_visitors' => $steps['ordered'],
                'conversion_rate' => $events['visitors'] > 0 ? round($steps['ordered'] / $events['visitors'] * 100, 2) : null,
                'order_value' => round($orderValue, 2),
                'confirmed_revenue' => round(array_sum(array_column($confirmed, 'total')), 2),
                'average_order_value' => count($live) > 0 ? round($orderValue / count($live), 2) : null,
            ],
            // Distinct visitors reaching each step, for the visit-to-order funnel.
            'steps' => $steps,
            'payment_methods' => [
                'cod' => ['orders' => count($cod), 'value' => round(array_sum(array_column(array_filter($cod, fn ($o) => !$o['cancelled']), 'total')), 2)],
                'online' => ['orders' => count($online), 'value' => round(array_sum(array_column(array_filter($online, fn ($o) => !$o['cancelled']), 'total')), 2)],
            ],
            'devices' => $this->breakdown($funnelId, $from, $to, 'device', $orders),
            'sources' => $this->breakdown($funnelId, $from, $to, 'source', $orders),
            'campaigns' => $this->campaigns($orders),
            'trend' => $this->trend($funnelId, $from, $to, $interval, $orders),
            'funnels' => $funnelId ? [] : $this->perFunnel($from, $to, $orders),
            'attention' => array_values(array_map(fn ($o) => ['codes' => $o['codes'], 'funnel_id' => $o['funnel_id'], 'total' => $o['total']],
                array_filter($orders, fn ($o) => $o['paid'] && $o['cancelled']))),
        ];
    }

    /**
     * One row per funnel checkout in the period, from the order tables.
     *
     * @return array<int, array>
     */
    private function orders(?int $funnelId, Carbon $from, Carbon $to): array
    {
        $attribution = FunnelOrderService::attributionTableReady();

        $query = DB::table('orders as o')
            ->whereNotNull('o.funnel_id')
            ->whereBetween('o.created_at', [$from, $to])
            ->groupBy('o.combined_order_id')
            ->selectRaw("o.combined_order_id, MAX(o.funnel_id) AS funnel_id, SUM(o.grand_total) AS total,
                MIN(CASE WHEN o.payment_status = 'paid' THEN 1 ELSE 0 END) AS paid,
                MIN(CASE WHEN o.delivery_status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                MAX(CASE WHEN o.delivery_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                MAX(o.payment_type) AS payment_type, MIN(o.created_at) AS created_at,
                GROUP_CONCAT(o.code ORDER BY o.id SEPARATOR ', ') AS codes");

        if ($funnelId) {
            $query->where('o.funnel_id', $funnelId);
        }
        if ($attribution) {
            $query->leftJoin('funnel_order_attributions as a', 'a.combined_order_id', '=', 'o.combined_order_id')
                ->where(fn ($q) => $q->whereNull('a.is_test')->orWhere('a.is_test', false))
                ->selectRaw("MAX(COALESCE(a.source, 'unknown')) AS source, MAX(COALESCE(a.device, 'unknown')) AS device, MAX(a.utm_campaign) AS campaign, MAX(a.visitor_id) AS visitor_id");
        } else {
            $query->selectRaw("'unknown' AS source, 'unknown' AS device, NULL AS campaign, NULL AS visitor_id");
        }

        return $query->get()->map(fn ($row) => [
            'combined_order_id' => (int) $row->combined_order_id,
            'funnel_id' => (int) $row->funnel_id,
            'total' => (float) $row->total,
            'paid' => (bool) $row->paid,
            'delivered' => (bool) $row->delivered,
            'cancelled' => (bool) $row->cancelled,
            'payment_type' => (string) $row->payment_type,
            'created_at' => Carbon::parse($row->created_at),
            'codes' => (string) $row->codes,
            'source' => (string) $row->source,
            'device' => (string) $row->device,
            'campaign' => $row->campaign,
            'visitor_id' => $row->visitor_id,
        ])->all();
    }

    private function events(?int $funnelId, Carbon $from, Carbon $to)
    {
        $query = DB::table('funnel_events')->whereBetween('created_at', [$from, $to]);
        if ($funnelId) {
            $query->where('funnel_id', $funnelId);
        }

        return $query;
    }

    private function eventTotals(?int $funnelId, Carbon $from, Carbon $to): array
    {
        $totals = ['page_view' => 0, 'product_view' => 0, 'product_select' => 0, 'checkout_start' => 0, 'visitors' => 0];
        if (!EventRecorder::ready()) {
            return $totals;
        }

        foreach ($this->events($funnelId, $from, $to)->groupBy('type')->selectRaw('type, COUNT(*) AS n')->get() as $row) {
            $totals[$row->type] = (int) $row->n;
        }
        // Checkout starts are counted once per visit.
        $totals['checkout_start'] = (int) $this->events($funnelId, $from, $to)->where('type', 'checkout_start')
            ->selectRaw('COUNT(DISTINCT COALESCE(session_id, event_id)) AS n')->value('n');
        $totals['visitors'] = (int) $this->events($funnelId, $from, $to)->where('type', 'page_view')
            ->selectRaw('COUNT(DISTINCT COALESCE(visitor_id, event_id)) AS n')->value('n');

        return $totals;
    }

    /**
     * Distinct visitors at each step: on the page, looked at a product, chose one, started
     * the checkout, placed an order (an order counts when its visitor was seen on the page).
     *
     * @return array{visitors: int, product_view: int, product_select: int, checkout_start: int, ordered: int}
     */
    private function steps(?int $funnelId, Carbon $from, Carbon $to, array $orders): array
    {
        $steps = ['visitors' => 0, 'product_view' => 0, 'product_select' => 0, 'checkout_start' => 0, 'ordered' => 0];
        if (!EventRecorder::ready()) {
            return $steps;
        }

        $rows = $this->events($funnelId, $from, $to)->groupBy('type')
            ->selectRaw('type, COUNT(DISTINCT COALESCE(visitor_id, event_id)) AS n')->get();
        foreach ($rows as $row) {
            $steps[$row->type === 'page_view' ? 'visitors' : $row->type] = (int) $row->n;
        }

        $buyers = array_values(array_unique(array_filter(array_column($orders, 'visitor_id'))));
        foreach (array_chunk($buyers, 500) as $chunk) {
            $steps['ordered'] += (int) $this->events($funnelId, $from, $to)->where('type', 'page_view')->whereIn('visitor_id', $chunk)
                ->selectRaw('COUNT(DISTINCT visitor_id) AS n')->value('n');
        }

        return $steps;
    }

    /** Visitors, orders and confirmed revenue per device or per traffic source. */
    private function breakdown(?int $funnelId, Carbon $from, Carbon $to, string $column, array $orders): array
    {
        $rows = [];
        if (EventRecorder::ready()) {
            $visitors = $this->events($funnelId, $from, $to)->where('type', 'page_view')->groupBy($column)
                ->selectRaw("{$column} AS k, COUNT(DISTINCT COALESCE(visitor_id, event_id)) AS n")->get();
            foreach ($visitors as $row) {
                $rows[$row->k] = ['key' => $row->k, 'visitors' => (int) $row->n, 'orders' => 0, 'revenue' => 0.0];
            }
        }
        foreach ($orders as $order) {
            $key = $order[$column] ?: 'unknown';
            $rows[$key] ??= ['key' => $key, 'visitors' => 0, 'orders' => 0, 'revenue' => 0.0];
            $rows[$key]['orders']++;
            if (!$order['cancelled'] && ($order['paid'] || $order['delivered'])) {
                $rows[$key]['revenue'] += $order['total'];
            }
        }
        usort($rows, fn ($a, $b) => [$b['visitors'], $b['orders']] <=> [$a['visitors'], $a['orders']]);

        return array_values($rows);
    }

    private function campaigns(array $orders): array
    {
        $rows = [];
        foreach ($orders as $order) {
            if (!$order['campaign']) {
                continue;
            }
            $key = $order['campaign'];
            $rows[$key] ??= ['campaign' => $key, 'orders' => 0, 'order_value' => 0.0, 'revenue' => 0.0];
            $rows[$key]['orders']++;
            if (!$order['cancelled']) {
                $rows[$key]['order_value'] += $order['total'];
                if ($order['paid'] || $order['delivered']) {
                    $rows[$key]['revenue'] += $order['total'];
                }
            }
        }
        usort($rows, fn ($a, $b) => $b['orders'] <=> $a['orders']);

        return array_slice(array_values($rows), 0, 20);
    }

    private function bucket(Carbon $date, string $interval): string
    {
        switch ($interval) {
            case 'month':
                return $date->format('Y-m');
            case 'week':
                return $date->copy()->startOfWeek()->toDateString();
            default:
                return $date->toDateString();
        }
    }

    private function trend(?int $funnelId, Carbon $from, Carbon $to, string $interval, array $orders): array
    {
        $buckets = [];
        $step = ['day' => '1 day', 'week' => '1 week', 'month' => '1 month'][$interval];
        $start = $interval === 'week' ? $from->copy()->startOfWeek() : ($interval === 'month' ? $from->copy()->startOfMonth() : $from->copy());
        foreach (CarbonPeriod::create($start, $step, $to) as $date) {
            $buckets[$this->bucket($date, $interval)] = ['period' => $this->bucket($date, $interval), 'visitors' => 0, 'orders' => 0, 'order_value' => 0.0, 'revenue' => 0.0];
        }

        if (EventRecorder::ready()) {
            $format = ['day' => '%Y-%m-%d', 'week' => '%x-%v', 'month' => '%Y-%m'][$interval];
            $rows = $this->events($funnelId, $from, $to)->where('type', 'page_view')
                ->groupBy('b')->selectRaw("DATE_FORMAT(created_at, '{$format}') AS b, MIN(created_at) AS first, COUNT(DISTINCT COALESCE(visitor_id, event_id)) AS n")->get();
            foreach ($rows as $row) {
                $key = $this->bucket(Carbon::parse($row->first), $interval);
                if (isset($buckets[$key])) {
                    $buckets[$key]['visitors'] += (int) $row->n;
                }
            }
        }

        foreach ($orders as $order) {
            $key = $this->bucket($order['created_at'], $interval);
            if (!isset($buckets[$key])) {
                continue;
            }
            $buckets[$key]['orders']++;
            if (!$order['cancelled']) {
                $buckets[$key]['order_value'] += $order['total'];
                if ($order['paid'] || $order['delivered']) {
                    $buckets[$key]['revenue'] += $order['total'];
                }
            }
        }

        return array_values($buckets);
    }

    /**
     * Visitors, orders, order value, confirmed revenue and conversion rate of the given
     * funnels in the period, for the All Funnels list (same definitions as the dashboard).
     *
     * @param  int[] $funnelIds
     * @return array<int, array> funnel id => figures (funnels without activity included)
     */
    public function listSummary(array $funnelIds, Carbon $from, Carbon $to): array
    {
        // Before sales_funnel_v2_orders.sql no order is linked to a funnel.
        if ($funnelIds === [] || !\Illuminate\Support\Facades\Schema::hasColumn('orders', 'funnel_id')) {
            return [];
        }
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $orders = array_values(array_filter($this->orders(null, $from, $to), fn ($o) => in_array($o['funnel_id'], $funnelIds, true)));

        return array_column($this->perFunnel($from, $to, $orders, $funnelIds, false), null, 'id');
    }

    private function perFunnel(Carbon $from, Carbon $to, array $orders, ?array $funnelIds = null, bool $activeOnly = true): array
    {
        $rows = [];
        $funnels = DB::table('funnels')->whereNull('deleted_at')
            ->when($funnelIds !== null, fn ($q) => $q->whereIn('id', $funnelIds))
            ->get(['id', 'title', 'slug', 'status']);
        foreach ($funnels as $funnel) {
            $rows[$funnel->id] = ['id' => (int) $funnel->id, 'title' => $funnel->title, 'slug' => $funnel->slug, 'status' => $funnel->status,
                'visitors' => 0, 'orders' => 0, 'order_value' => 0.0, 'revenue' => 0.0, 'conversion_rate' => null];
        }
        if (EventRecorder::ready()) {
            $visitors = $this->events(null, $from, $to)->where('type', 'page_view')
                ->when($funnelIds !== null, fn ($q) => $q->whereIn('funnel_id', $funnelIds))->groupBy('funnel_id')
                ->selectRaw('funnel_id, COUNT(DISTINCT COALESCE(visitor_id, event_id)) AS n')->get();
            foreach ($visitors as $row) {
                if (isset($rows[$row->funnel_id])) {
                    $rows[$row->funnel_id]['visitors'] = (int) $row->n;
                }
            }
        }
        foreach ($orders as $order) {
            if (!isset($rows[$order['funnel_id']])) {
                continue;
            }
            $row = &$rows[$order['funnel_id']];
            $row['orders']++;
            if (!$order['cancelled']) {
                $row['order_value'] += $order['total'];
                if ($order['paid'] || $order['delivered']) {
                    $row['revenue'] += $order['total'];
                }
            }
            unset($row);
        }
        // Visitors of each funnel who placed an order there (see the conversion rate above).
        $converted = [];
        if (EventRecorder::ready()) {
            $buyers = array_values(array_unique(array_filter(array_column($orders, 'visitor_id'))));
            foreach (array_chunk($buyers, 500) as $chunk) {
                $counts = $this->events(null, $from, $to)->where('type', 'page_view')->whereIn('visitor_id', $chunk)->groupBy('funnel_id')
                    ->selectRaw('funnel_id, COUNT(DISTINCT visitor_id) AS n')->get();
                foreach ($counts as $count) {
                    $converted[$count->funnel_id] = ($converted[$count->funnel_id] ?? 0) + (int) $count->n;
                }
            }
        }
        foreach ($rows as $id => &$row) {
            $row['conversion_rate'] = $row['visitors'] > 0 ? round(($converted[$id] ?? 0) / $row['visitors'] * 100, 2) : null;
        }
        unset($row);

        if ($activeOnly) {
            $rows = array_filter($rows, fn ($r) => $r['visitors'] > 0 || $r['orders'] > 0);
        }
        usort($rows, fn ($a, $b) => [$b['orders'], $b['visitors']] <=> [$a['orders'], $a['visitors']]);

        return array_values($rows);
    }
}
