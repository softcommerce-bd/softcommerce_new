@extends('backend.layouts.app')

@section('content')

<style>
    .fa-root {
        --fa-series-1: #2a78d6;
        --fa-series-2: #eb6834;
        --fa-series-3: #1baf7a;
        --fa-ink: #1b1b28;
        --fa-ink-2: #52514e;
        --fa-ink-3: #8a8a99;
        --fa-line: #ebedf2;
        --fa-track: #f2f3f8;
    }
    .fa-root .fa-kpi { border: 1px solid var(--fa-line); border-radius: 10px; padding: 14px 16px; background: #fff; height: 100%; }
    .fa-root .fa-kpi-label { font-size: 12px; color: var(--fa-ink-2); margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
    .fa-root .fa-kpi-value { font-size: 22px; font-weight: 600; color: var(--fa-ink); line-height: 1.2; font-variant-numeric: tabular-nums; }
    .fa-root .fa-kpi-note { font-size: 12px; color: var(--fa-ink-3); margin-top: 2px; }
    .fa-root .fa-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
    .fa-root .fa-section-title { font-size: 14px; font-weight: 600; color: var(--fa-ink); margin: 0; }
    .fa-root .fa-steps .fa-step { margin-bottom: 12px; }
    .fa-root .fa-step-head { display: flex; justify-content: space-between; font-size: 13px; color: var(--fa-ink-2); margin-bottom: 4px; gap: 8px; }
    .fa-root .fa-step-head strong { color: var(--fa-ink); font-variant-numeric: tabular-nums; }
    .fa-root .fa-bar { height: 10px; background: var(--fa-track); border-radius: 5px; overflow: hidden; }
    .fa-root .fa-bar span { display: block; height: 100%; border-radius: 0 4px 4px 0; background: var(--fa-series-1); min-width: 2px; }
    .fa-root .fa-bar.fa-orders span { background: var(--fa-series-2); }
    .fa-root .fa-chart { position: relative; height: 220px; }
    /* The admin theme gives tables an empty ::before box, which shows as a stray row under the header. */
    .fa-root .fa-table::before, .fa-root .fa-table::after { content: none; display: none; }
    .fa-root .fa-table td, .fa-root .fa-table th { font-size: 13px; vertical-align: middle; padding: .5rem .5rem; }
    .fa-root .fa-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fa-root .fa-empty { color: var(--fa-ink-3); font-size: 13px; padding: 24px 0; text-align: center; }
    .fa-root .fa-filters .form-control, .fa-root .fa-filters .btn { height: 38px; }
    .fa-root .fa-quick .btn { padding: .3rem .7rem; font-size: 12px; }
    .fa-root details.fa-terms summary { cursor: pointer; font-size: 13px; color: var(--fa-ink-2); }
    .fa-root details.fa-terms dl { font-size: 13px; color: var(--fa-ink-2); margin: 10px 0 0; }
    .fa-root details.fa-terms dt { color: var(--fa-ink); font-weight: 600; }
    .fa-root details.fa-terms dd { margin-bottom: 8px; }
</style>

@php
    $t = $report['totals'];
    $money = fn ($v) => $v === null ? '—' : single_price($v);
    $count = fn ($v) => $v === null ? '—' : number_format($v);
    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 2), '0'), '.') . '%';
    $share = fn ($part, $whole) => $whole > 0 ? max(0, min(100, round($part / $whole * 100, 1))) : 0;
    $s = $report['steps'];
    // Distinct visitors at each step, so every bar is a share of the one above it.
    $steps = [
        ['label' => translate('Visitors'), 'value' => $s['visitors']],
        ['label' => translate('Looked at a product'), 'value' => $s['product_view']],
        ['label' => translate('Chose a product'), 'value' => $s['product_select']],
        ['label' => translate('Started the checkout'), 'value' => $s['checkout_start']],
        ['label' => translate('Placed an order'), 'value' => $s['ordered'], 'orders' => true],
    ];
    $top = max(1, max(array_column($steps, 'value')));
    $funnelRows = collect($report['funnels']);
    $labels = [
        'mobile' => translate('Mobile'), 'tablet' => translate('Tablet'), 'desktop' => translate('Desktop'), 'unknown' => translate('Unknown'),
        'facebook' => 'Facebook / Instagram', 'google' => 'Google / YouTube', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp',
        'email' => translate('Email'), 'sms' => 'SMS', 'direct' => translate('Direct / unknown'), 'referral' => translate('Other websites'), 'other' => translate('Other campaigns'),
    ];
    $quick = [7 => translate('7 days'), 30 => translate('30 days'), 90 => translate('90 days'), 365 => translate('12 months')];
    $base = ['funnel' => optional($funnel)->id];
@endphp

<div class="fa-root">
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h1 class="h3">{{ $funnel ? translate('Analytics') . ': ' . $funnel->title : translate('Funnel Analytics') }}</h1>
                <p class="text-muted mb-0 fs-13">{{ translate('Visitors come from the pages themselves. Orders, payments and revenue are read from your orders, never from browser events.') }}</p>
            </div>
            <div class="col-md-5 text-md-right mt-2 mt-md-0">
                <a href="{{ route('funnels.index') }}" class="btn btn-soft-secondary btn-sm">{{ translate('All funnels') }}</a>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('funnels.analytics') }}" class="card" data-testid="analytics-filters">
        <div class="card-body pb-2">
            <div class="row fa-filters">
                <div class="col-lg-4 col-md-6 mb-2">
                    <label class="fs-12 text-muted mb-1" for="fa-funnel">{{ translate('Funnel') }}</label>
                    <select name="funnel" id="fa-funnel" class="form-control">
                        <option value="">{{ translate('All funnels') }}</option>
                        @foreach ($funnels as $item)
                            <option value="{{ $item->id }}" @selected(optional($funnel)->id === $item->id)>{{ $item->title }}{{ $item->deleted_at ? ' (' . translate('deleted') . ')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-6 mb-2">
                    <label class="fs-12 text-muted mb-1" for="fa-from">{{ translate('From') }}</label>
                    <input type="date" name="from" id="fa-from" class="form-control" value="{{ $from->toDateString() }}">
                </div>
                <div class="col-lg-2 col-md-3 col-6 mb-2">
                    <label class="fs-12 text-muted mb-1" for="fa-to">{{ translate('To') }}</label>
                    <input type="date" name="to" id="fa-to" class="form-control" value="{{ $to->toDateString() }}">
                </div>
                <div class="col-lg-2 col-md-6 col-6 mb-2">
                    <label class="fs-12 text-muted mb-1" for="fa-interval">{{ translate('Trend by') }}</label>
                    <select name="interval" id="fa-interval" class="form-control">
                        <option value="day" @selected($interval === 'day')>{{ translate('Day') }}</option>
                        <option value="week" @selected($interval === 'week')>{{ translate('Week') }}</option>
                        <option value="month" @selected($interval === 'month')>{{ translate('Month') }}</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6 col-6 mb-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-block">{{ translate('Show') }}</button>
                </div>
            </div>
            <div class="fa-quick mb-1">
                @foreach ($quick as $days => $label)
                    <a class="btn btn-soft-secondary mb-1" href="{{ route('funnels.analytics', $base + ['from' => now()->subDays($days - 1)->toDateString(), 'to' => now()->toDateString()]) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </form>

    @unless ($eventsReady)
        <div class="alert alert-warning fs-13">{{ translate('Visitor tracking is not installed yet (sales_funnel_v5_analytics.sql). Orders and revenue are still shown.') }}</div>
    @endunless

    @if (count($report['attention']))
        <div class="alert alert-danger fs-13" data-testid="analytics-attention">
            <strong>{{ translate('Needs attention') }}:</strong>
            {{ translate('These orders were paid online after they had been cancelled. Refund the customer or restore the order.') }}
            {{ collect($report['attention'])->pluck('codes')->implode(' · ') }}
        </div>
    @endif

    {{-- Orders and money (from the order records) --}}
    <div class="row gutters-10" data-testid="analytics-orders">
        @foreach ([
            ['orders_placed', translate('Orders placed'), $count($t['orders_placed']), translate('Test orders left out')],
            ['orders_paid', translate('Paid'), $count($t['orders_paid']), translate('Payment confirmed or recorded')],
            ['orders_delivered', translate('Delivered'), $count($t['orders_delivered']), null],
            ['orders_cancelled', translate('Cancelled'), $count($t['orders_cancelled']), null],
            ['conversion_rate', translate('Conversion rate'), $pct($t['conversion_rate']), translate('Visitors who ordered ÷ visitors')],
        ] as [$key, $label, $value, $note])
            <div class="col-xl col-md-4 col-6 mb-2">
                <div class="fa-kpi" data-testid="kpi-{{ $key }}">
                    <div class="fa-kpi-label">{{ $label }}</div>
                    <div class="fa-kpi-value">{{ $value }}</div>
                    @if ($note)<div class="fa-kpi-note">{{ $note }}</div>@endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="row gutters-10 mb-2">
        @foreach ([
            ['order_value', translate('Total order value'), $money($t['order_value']), translate('Orders not cancelled')],
            ['confirmed_revenue', translate('Confirmed revenue'), $money($t['confirmed_revenue']), translate('Paid or delivered, not cancelled')],
            ['average_order_value', translate('Average order value'), $money($t['average_order_value']), null],
        ] as [$key, $label, $value, $note])
            <div class="col-md-3 col-6 mb-2">
                <div class="fa-kpi" data-testid="kpi-{{ $key }}">
                    <div class="fa-kpi-label">{{ $label }}</div>
                    <div class="fa-kpi-value">{{ $value }}</div>
                    @if ($note)<div class="fa-kpi-note">{{ $note }}</div>@endif
                </div>
            </div>
        @endforeach
        <div class="col-md-3 col-6 mb-2">
            <div class="fa-kpi" data-testid="kpi-payment-methods">
                <div class="fa-kpi-label">{{ translate('Cash on delivery / online') }}</div>
                <div class="fa-kpi-value">{{ $count($report['payment_methods']['cod']['orders']) }} / {{ $count($report['payment_methods']['online']['orders']) }}</div>
                <div class="fa-kpi-note">{{ $money($report['payment_methods']['cod']['value']) }} / {{ $money($report['payment_methods']['online']['value']) }}</div>
            </div>
        </div>
    </div>

    <div class="row gutters-10">
        {{-- Funnel steps --}}
        <div class="col-lg-5 mb-3">
            <div class="card h-100 mb-0">
                <div class="card-header"><h2 class="fa-section-title">{{ translate('From visit to order') }}</h2></div>
                <div class="card-body fa-steps" data-testid="analytics-steps">
                    @foreach ($steps as $i => $step)
                        <div class="fa-step">
                            <div class="fa-step-head">
                                <span>{{ $step['label'] }}</span>
                                <span>
                                    <strong>{{ $count($step['value']) }}</strong>
                                    @if ($i > 0 && $steps[0]['value'] > 0)
                                        <span class="text-muted">· {{ $pct($share($step['value'], $steps[0]['value'])) }} {{ translate('of visitors') }}</span>
                                    @endif
                                </span>
                            </div>
                            <div class="fa-bar {{ !empty($step['orders']) ? 'fa-orders' : '' }}" role="img" aria-label="{{ $step['label'] }}: {{ $step['value'] }}">
                                <span style="width: {{ $share($step['value'], $top) }}%"></span>
                            </div>
                        </div>
                    @endforeach
                    <div class="fs-12 text-muted mt-3">
                        {{ translate('Page views') }}: <strong>{{ $count($t['page_views']) }}</strong> ·
                        {{ translate('Product views') }}: <strong>{{ $count($t['product_views']) }}</strong> ·
                        {{ translate('Product selections') }}: <strong>{{ $count($t['product_selections']) }}</strong>.
                        @if ($t['orders_placed'] > $s['ordered'])
                            <br>{{ translate('Orders whose visit was not seen (for example scripts were blocked in the browser)') }}: {{ $count($t['orders_placed'] - $s['ordered']) }}.
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Trends: one measure per chart, each with its own scale --}}
        <div class="col-lg-7 mb-3">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="fa-section-title">{{ translate('Trend') }}</h2>
                    <div class="btn-group btn-group-sm" role="tablist" aria-label="{{ translate('Trend measure') }}">
                        <button type="button" class="btn btn-soft-primary active" data-fa-measure="visitors">{{ translate('Visitors') }}</button>
                        <button type="button" class="btn btn-soft-primary" data-fa-measure="orders">{{ translate('Orders') }}</button>
                        <button type="button" class="btn btn-soft-primary" data-fa-measure="revenue">{{ translate('Confirmed revenue') }}</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="fa-chart"><canvas id="fa-trend" aria-label="{{ translate('Trend chart') }}" role="img"></canvas></div>
                    <details class="mt-2">
                        <summary class="fs-12 text-muted">{{ translate('Show as table') }}</summary>
                        <div class="table-responsive mt-2" style="max-height: 260px">
                            <table class="table fa-table mb-0">
                                <thead><tr><th>{{ translate('Period') }}</th><th class="fa-num">{{ translate('Visitors') }}</th><th class="fa-num">{{ translate('Orders') }}</th><th class="fa-num">{{ translate('Order value') }}</th><th class="fa-num">{{ translate('Confirmed revenue') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($report['trend'] as $row)
                                        <tr><td>{{ $row['period'] }}</td><td class="fa-num">{{ $count($row['visitors']) }}</td><td class="fa-num">{{ $count($row['orders']) }}</td><td class="fa-num">{{ $money($row['order_value']) }}</td><td class="fa-num">{{ $money($row['revenue']) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </div>

    <div class="row gutters-10">
        @foreach (['devices' => translate('Devices'), 'sources' => translate('Traffic sources')] as $key => $title)
            <div class="col-lg-6 mb-3">
                <div class="card h-100 mb-0">
                    <div class="card-header"><h2 class="fa-section-title">{{ $title }}</h2></div>
                    <div class="card-body" data-testid="analytics-{{ $key }}">
                        @if (count($report[$key]) === 0)
                            <div class="fa-empty">{{ translate('No visits or orders in this period.') }}</div>
                        @else
                            @php $most = max(1, max(array_column($report[$key], 'visitors'))); @endphp
                            <div class="table-responsive">
                            <table class="table fa-table mb-0">
                                <thead><tr><th>{{ $key === 'devices' ? translate('Device') : translate('Source') }}</th><th style="width: 38%; min-width: 120px">{{ translate('Visitors') }}</th><th class="fa-num">{{ translate('Orders') }}</th><th class="fa-num">{{ translate('Confirmed revenue') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($report[$key] as $row)
                                        <tr>
                                            <td>{{ $labels[$row['key']] ?? ucfirst($row['key']) }}</td>
                                            <td>
                                                <div class="d-flex align-items-center" style="gap: 8px">
                                                    <div class="fa-bar flex-grow-1"><span style="width: {{ $share($row['visitors'], $most) }}%"></span></div>
                                                    <span class="fa-num" style="min-width: 36px">{{ $count($row['visitors']) }}</span>
                                                </div>
                                            </td>
                                            <td class="fa-num">{{ $count($row['orders']) }}</td>
                                            <td class="fa-num">{{ $money($row['revenue']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header"><h2 class="fa-section-title">{{ translate('Campaigns') }} <span class="text-muted fw-400 fs-12">(utm_campaign)</span></h2></div>
        <div class="card-body" data-testid="analytics-campaigns">
            @if (count($report['campaigns']) === 0)
                <div class="fa-empty">{{ translate('No orders carried a campaign name. Add ?utm_source=facebook&utm_campaign=your-campaign to the links in your ads.') }}</div>
            @else
                <div class="table-responsive">
                    <table class="table fa-table mb-0">
                        <thead><tr><th>{{ translate('Campaign') }}</th><th class="fa-num">{{ translate('Orders') }}</th><th class="fa-num">{{ translate('Order value') }}</th><th class="fa-num">{{ translate('Confirmed revenue') }}</th></tr></thead>
                        <tbody>
                            @foreach ($report['campaigns'] as $row)
                                <tr><td>{{ $row['campaign'] }}</td><td class="fa-num">{{ $count($row['orders']) }}</td><td class="fa-num">{{ $money($row['order_value']) }}</td><td class="fa-num">{{ $money($row['revenue']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @unless ($funnel)
        <div class="card">
            <div class="card-header"><h2 class="fa-section-title">{{ translate('By funnel') }}</h2></div>
            <div class="card-body" data-testid="analytics-funnels">
                @if (count($report['funnels']) === 0)
                    <div class="fa-empty">{{ translate('No funnel had visitors or orders in this period.') }}</div>
                @else
                    <div class="table-responsive">
                        <table class="table fa-table mb-0">
                            <thead><tr><th>{{ translate('Funnel') }}</th><th class="fa-num">{{ translate('Visitors') }}</th><th class="fa-num">{{ translate('Orders') }}</th><th class="fa-num">{{ translate('Conv.') }}</th><th class="fa-num">{{ translate('Order value') }}</th><th class="fa-num">{{ translate('Confirmed revenue') }}</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($funnelRows as $i => $row)
                                    <tr @if ($i >= 15) class="fa-more d-none" @endif>
                                        <td>{{ $row['title'] }}</td>
                                        <td class="fa-num">{{ $count($row['visitors']) }}</td>
                                        <td class="fa-num">{{ $count($row['orders']) }}</td>
                                        <td class="fa-num">{{ $pct($row['conversion_rate']) }}</td>
                                        <td class="fa-num">{{ $money($row['order_value']) }}</td>
                                        <td class="fa-num">{{ $money($row['revenue']) }}</td>
                                        <td class="text-right"><a class="btn btn-soft-primary btn-xs" href="{{ route('funnels.analytics', ['funnel' => $row['id'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">{{ translate('Details') }}</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($funnelRows->count() > 15)
                        <button type="button" class="btn btn-soft-secondary btn-sm mt-2" onclick="document.querySelectorAll('.fa-more').forEach(function (r) { r.classList.remove('d-none'); }); this.remove();">{{ translate('Show all') }} ({{ $funnelRows->count() }})</button>
                    @endif
                @endif
            </div>
        </div>
    @endunless

    @if ($funnel)
        <p class="fs-13">
            <a href="{{ route('all_orders.index', ['funnel_id' => $funnel->id]) }}">{{ translate('See the orders of this funnel') }} →</a>
        </p>
    @endif

    <details class="fa-terms card card-body">
        <summary>{{ translate('How these numbers are counted') }}</summary>
        <dl>
            <dt>{{ translate('Orders placed') }}</dt><dd>{{ translate('Every checkout taken through a funnel page in the period, including ones cancelled later. Test orders placed by staff are left out.') }}</dd>
            <dt>{{ translate('Paid') }}</dt><dd>{{ translate('Online payments confirmed by the gateway, and cash-on-delivery orders whose payment staff recorded.') }}</dd>
            <dt>{{ translate('Confirmed revenue') }}</dt><dd>{{ translate('Value of orders that are not cancelled and are paid or delivered. Cash on delivery counts once it is delivered.') }}</dd>
            <dt>{{ translate('Visitors') }}</dt><dd>{{ translate('Counted by an anonymous id kept in the visitor\'s browser. Visitors who block scripts or clear their browser are counted again or not at all, so treat visitor numbers as an estimate. Order numbers are exact.') }}</dd>
        </dl>
    </details>
</div>

@endsection

@section('script')
<script>
(function () {
    var trend = @json($report['trend']);
    var canvas = document.getElementById('fa-trend');
    if (!canvas || typeof Chart === 'undefined') return;

    var measures = {
        visitors: { label: @json(translate('Visitors')), color: '#2a78d6', type: 'line', money: false },
        orders: { label: @json(translate('Orders')), color: '#eb6834', type: 'bar', money: false },
        revenue: { label: @json(translate('Confirmed revenue')), color: '#1baf7a', type: 'bar', money: true }
    };
    var number = new Intl.NumberFormat();
    var chart = null;

    function draw(key) {
        var m = measures[key];
        if (chart) chart.destroy();
        chart = new Chart(canvas, {
            type: m.type,
            data: {
                labels: trend.map(function (r) { return r.period; }),
                datasets: [{
                    label: m.label,
                    data: trend.map(function (r) { return r[key]; }),
                    borderColor: m.color,
                    backgroundColor: m.color,
                    borderWidth: m.type === 'line' ? 2 : 0,
                    borderRadius: 4,
                    maxBarThickness: 28,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    tension: 0.25
                }]
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (ctx) { return m.label + ': ' + (m.money ? '৳' : '') + number.format(ctx.parsed.y); } } }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#8a8a99', maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
                    y: { beginAtZero: true, grid: { color: '#ebedf2' }, border: { display: false }, ticks: { color: '#8a8a99', precision: 0 } }
                }
            }
        });
    }

    document.querySelectorAll('[data-fa-measure]').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('[data-fa-measure]').forEach(function (b) { b.classList.toggle('active', b === button); });
            draw(button.getAttribute('data-fa-measure'));
        });
    });
    draw('visitors');
})();
</script>
@endsection
