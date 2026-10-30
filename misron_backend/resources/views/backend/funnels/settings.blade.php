@extends('backend.layouts.app')

@section('content')

@php
    $on = fn ($value) => $value
        ? '<span class="badge badge-inline badge-soft-success">' . e(translate('On')) . '</span>'
        : '<span class="badge badge-inline badge-soft-secondary">' . e(translate('Off')) . '</span>';
    $ok = fn ($value, $yes, $no) => $value
        ? '<span class="badge badge-inline badge-soft-success">' . e($yes) . '</span>'
        : '<span class="badge badge-inline badge-soft-warning">' . e($no) . '</span>';
    $deliveryNames = [
        'flat_rate' => translate('Flat rate'),
        'area_wise_shipping' => translate('By delivery area'),
        'seller_wise_shipping' => translate('By seller'),
        'product_wise_shipping' => translate('By product'),
        'carrier_wise_shipping' => translate('By carrier'),
    ];
@endphp

<style>
    .fst-table::before, .fst-table::after { content: none; display: none; }
    .fst-table td { vertical-align: top; padding: .7rem .5rem; font-size: 13px; }
    .fst-table td:first-child { width: 32%; font-weight: 600; }
    .fst-table td:nth-child(2) { width: 18%; white-space: nowrap; }
    .fst-how { color: #8a8a99; font-size: 12px; margin-top: 2px; }
    .fst-how code { font-size: 12px; word-break: break-all; }
    /* On a phone each setting reads top to bottom: name, value, explanation. */
    @media (max-width: 767px) {
        .fst-table, .fst-table tbody, .fst-table tr, .fst-table td { display: block; width: auto !important; }
        .fst-table tr { border-top: 1px solid #ebedf2; padding: .5rem 0; }
        .fst-table td { border: 0; padding: .15rem 0; }
    }
</style>

<div class="aiz-titlebar text-left mt-2 mb-3">
    <h1 class="h3">{{ translate('Sales Funnel Settings') }}</h1>
    <p class="text-muted mb-0 fs-13">{{ translate('How sales funnels are set up on this store. Values come from the server settings (.env) and your existing business settings; each row says where to change it.') }}</p>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0 h6">{{ translate('Ordering') }}</h5></div>
    <div class="card-body">
        <table class="table fst-table mb-0">
            <tr>
                <td>{{ translate('Customers can order from funnel pages') }}</td>
                <td>{!! $on($checkoutEnabled) !!}</td>
                <td>
                    {{ $checkoutEnabled ? translate('Order forms on live pages take real orders. They appear in Orders with a Funnel badge.') : translate('Pages can be built and published, but their order form says ordering is not open yet.') }}
                    <div class="fst-how">{{ translate('Server setting') }} <code>FUNNEL_CHECKOUT_ENABLED</code></div>
                </td>
            </tr>
            <tr>
                <td>{{ translate('Pages with products need an order form to publish') }}</td>
                <td>{!! $on($requireCheckout) !!}</td>
                <td>
                    {{ translate('Stops a page that shows offers from going live without a way to order.') }}
                    <div class="fst-how">{{ translate('Server setting') }} <code>FUNNEL_REQUIRE_CHECKOUT</code></div>
                </td>
            </tr>
            <tr>
                <td>{{ translate('Payment methods offered') }}</td>
                <td>
                    @forelse ($paymentMethods as $method)
                        <span class="badge badge-inline badge-soft-success d-inline-block mb-1">{{ translate($method['label']) }}</span>
                    @empty
                        <span class="badge badge-inline badge-soft-warning">{{ translate('None') }}</span>
                    @endforelse
                </td>
                <td>
                    {{ translate('The same methods as your website: cash on delivery when it is active, and online payment through Paystation when it is set up. Each page can narrow this down in its payment block.') }}
                    <div class="fst-how">{{ translate('Business Settings → Payment Methods') }}</div>
                </td>
            </tr>
            <tr>
                <td>{{ translate('Delivery charges') }}</td>
                <td>{!! $ok($deliverySupported, $deliveryNames[$deliveryMode] ?? $deliveryMode, $deliveryNames[$deliveryMode] ?? translate('Not set')) !!}</td>
                <td>
                    {{ $deliverySupported ? translate('Funnel orders use your website’s delivery charges.') : translate('Charges by carrier need the customer to pick a courier, which funnel pages do not offer. Ordering from funnel pages is refused until another option is chosen.') }}
                    <div class="fst-how">{{ translate('Setup & Configurations → Shipping') }}</div>
                </td>
            </tr>
            <tr>
                <td>{{ translate('Staff test phone numbers') }}</td>
                <td>{{ $testPhones }}</td>
                <td>
                    {{ translate('Orders from these numbers are marked as tests, left out of analytics and never reported to Facebook or Google. The editor’s “Place a test order” link does the same for anyone.') }}
                    <div class="fst-how">{{ translate('Server setting') }} <code>FUNNEL_TEST_PHONES</code></div>
                </td>
            </tr>
            <tr>
                <td>{{ translate('Cancel unpaid online orders automatically') }}</td>
                <td>{!! $on($expiryEnabled) !!}</td>
                <td>
                    {{ translate('After') }} {{ $expiryMinutes }} {{ translate('minutes, the gateway is asked about the payment. Only a confirmed failed payment cancels the order and returns its stock.') }}
                    <div class="fst-how">{{ translate('Server settings') }} <code>ONLINE_ORDER_EXPIRY_ENABLED</code>, <code>ONLINE_ORDER_EXPIRE_MINUTES</code></div>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0 h6">{{ translate('Pages and editor') }}</h5></div>
    <div class="card-body">
        <table class="table fst-table mb-0">
            <tr>
                <td>{{ translate('Public page address') }}</td>
                <td>{!! $ok($publicUrl !== '', translate('Set'), translate('Not set')) !!}</td>
                <td>
                    @if ($publicUrl !== '')<code>{{ $publicUrl }}/funnel/…</code>@else{{ translate('Links to funnel pages cannot be shown.') }}@endif
                    <div class="fst-how">{{ translate('Server setting') }} <code>FUNNEL_PUBLIC_URL</code> {{ translate('or') }} <code>FRONTEND_URL</code></div>
                </td>
            </tr>
            <tr>
                <td>{{ translate('Visual editor') }}</td>
                <td>{!! $ok($editorUrl !== '', translate('Ready'), translate('Not set')) !!}</td>
                <td>
                    {{ $editorUrl !== '' ? translate('“Edit” opens the editor on your storefront.') : translate('The Edit button is hidden until the editor address is set.') }}
                    <div class="fst-how">{{ translate('Server setting') }} <code>FUNNEL_EDITOR_URL</code></div>
                </td>
            </tr>
            <tr>
                <td>{{ translate('Instant page refresh') }}</td>
                <td>{!! $ok($revalidateReady, translate('Ready'), translate('Not set')) !!}</td>
                <td>
                    {{ $revalidateReady ? translate('Publishing or unpublishing shows on the live page within seconds.') : translate('Changes reach the live page within a minute.') }}
                    <div class="fst-how">{{ translate('Server settings') }} <code>FUNNEL_REVALIDATE_URL</code>, <code>FUNNEL_REVALIDATE_SECRET</code></div>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0 h6">{{ translate('Tracking and privacy') }}</h5></div>
    <div class="card-body">
        <table class="table fst-table mb-0">
            <tr>
                <td>{{ translate('Send hashed phone and email to Facebook with purchases') }}</td>
                <td>{!! $on($shareCustomerData) !!}</td>
                <td>
                    {{ translate('Improves ad matching. Turn on only if your privacy notice covers it.') }}
                    <div class="fst-how">{{ translate('Server setting') }} <code>FUNNEL_TRACKING_SHARE_CUSTOMER_DATA</code></div>
                </td>
            </tr>
            <tr>
                <td>{{ translate('Visitor activity kept for') }}</td>
                <td>{{ $retentionDays }} {{ translate('days') }}</td>
                <td>
                    {{ translate('Older page visits are removed every night. Orders are never removed.') }}
                    <div class="fst-how">{{ translate('Server setting') }} <code>FUNNEL_EVENT_RETENTION_DAYS</code></div>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0 h6">{{ translate('Database') }}</h5></div>
    <div class="card-body">
        <table class="table fst-table mb-0">
            @foreach ($database as $label => $installed)
                <tr>
                    <td>{{ $label }}</td>
                    <td>{!! $ok($installed, translate('Installed'), translate('Not installed')) !!}</td>
                    <td>{{ $installed ? '' : translate('The features of this step are switched off until it is installed (see docs/sales-funnel/DEPLOYMENT.md).') }}</td>
                </tr>
            @endforeach
        </table>
    </div>
</div>

@endsection
