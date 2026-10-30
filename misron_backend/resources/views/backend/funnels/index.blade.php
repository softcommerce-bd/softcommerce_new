@extends('backend.layouts.app')

@section('content')

<style>
    .aiz-table tr td,
    .aiz-table thead th {
        vertical-align: middle;
        padding: 0.6rem 0.5rem;
    }
    .funnel-thumb {
        width: 56px;
        height: 56px;
        border-radius: 8px;
        background: #f2f3f8;
        flex-shrink: 0;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .funnel-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .funnel-products img {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        object-fit: cover;
        border: 2px solid #fff;
        margin-right: -8px;
        background: #f2f3f8;
    }
    .funnel-tabs .nav-link { padding: 0.5rem 0.9rem; }
    .action-toggle.dropdown-toggle::after {
        margin-left: auto;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .funnel-actions .dropdown-item[disabled] { opacity: .5; pointer-events: none; }
    .funnel-actions form { margin: 0; }
    .funnel-actions form button { width: 100%; text-align: left; }
    .min-w-0 { min-width: 0; }
    /* Long titles and addresses are cut, never pushed past a phone's edge. */
    .funnel-name { max-width: min(320px, 52vw); }
    @media (max-width: 575.98px) {
        .funnel-name { max-width: 46vw; }
    }
</style>

@php
    $statusStyles = [
        'draft' => ['badge-soft-info', translate('Draft')],
        'published' => ['badge-soft-success', translate('Published')],
        'scheduled' => ['badge-soft-blue', translate('Scheduled')],
        'unpublished' => ['badge-soft-warning', translate('Unpublished')],
        'archived' => ['badge-soft-secondary', translate('Archived')],
    ];
    $totalFunnels = $statusCounts->sum();
@endphp

<div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="h3">{{ translate('Sales Funnels') }}</h1>
            <p class="text-muted mb-0 fs-13">{{ translate('Landing pages that sell your existing products. Orders arrive in the normal Orders list.') }}</p>
        </div>
        @can('add_funnel')
            <div class="col-md-6 text-md-right mt-2 mt-md-0">
                <a href="{{ route('funnels.create') }}" class="btn btn-circle btn-info">
                    <span>{{ translate('Create New Funnel') }}</span>
                </a>
            </div>
        @endcan
    </div>
</div>

@if ($totalFunnels === 0)
    <div class="card">
        <div class="card-body text-center py-5">
            <div class="mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#a1a5b3" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 4h18l-7 8v6l-4 2v-8z"/>
                </svg>
            </div>
            <h5 class="fw-700">{{ translate('No sales funnels yet') }}</h5>
            <p class="text-muted fs-14 mx-auto" style="max-width: 460px;">
                {{ translate('A funnel is a single page built to sell one offer: a headline, your products from the catalogue and a checkout form. Customers order without leaving the page.') }}
            </p>
            @can('add_funnel')
                <a href="{{ route('funnels.create') }}" class="btn btn-primary mt-2">{{ translate('Create your first funnel') }}</a>
            @endcan
        </div>
    </div>
@else
    <ul class="nav nav-tabs funnel-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $status === null ? 'active' : '' }}" href="{{ route('funnels.index', array_filter(['search' => request('search'), 'sort' => request('sort')])) }}">
                {{ translate('All') }} <span class="badge badge-inline badge-soft-secondary ml-1">{{ $totalFunnels }}</span>
            </a>
        </li>
        @foreach ($statuses as $key)
            <li class="nav-item">
                <a class="nav-link {{ $status === $key ? 'active' : '' }}" href="{{ route('funnels.index', array_filter(['status' => $key, 'search' => request('search'), 'sort' => request('sort')])) }}">
                    {{ $statusStyles[$key][1] }} <span class="badge badge-inline badge-soft-secondary ml-1">{{ $statusCounts[$key] ?? 0 }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card">
        <form id="funnel-filters" action="{{ route('funnels.index') }}" method="GET">
            @if ($status)
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <div class="card-header row gutters-5">
                <div class="col">
                    <h5 class="mb-0 h6">{{ $status ? $statusStyles[$status][1] : translate('All Funnels') }}</h5>
                </div>
                <div class="col-md-3">
                    <select class="form-control aiz-selectpicker" name="sort" onchange="this.form.submit()">
                        <option value="updated" @selected($sort === 'updated')>{{ translate('Last modified') }}</option>
                        <option value="created" @selected($sort === 'created')>{{ translate('Newest first') }}</option>
                        <option value="published" @selected($sort === 'published')>{{ translate('Recently published') }}</option>
                        <option value="title" @selected($sort === 'title')>{{ translate('Title A–Z') }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ translate('Search by title, URL or ID and press Enter') }}">
                </div>
            </div>
        </form>

        <div class="card-body">
            @if ($funnels->isEmpty())
                <p class="text-center text-muted py-4 mb-0">{{ translate('No funnels match these filters.') }}</p>
            @else
                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <th>{{ translate('Funnel') }}</th>
                            <th data-breakpoints="md">{{ translate('Status') }}</th>
                            <th data-breakpoints="lg">{{ translate('Products') }}</th>
                            @if ($figures !== null)
                                <th data-breakpoints="md" class="text-right" title="{{ translate('Visitors: people who opened the page. Conversion: visitors who ordered, divided by visitors. Revenue: orders paid or delivered, not cancelled.') }}">{{ translate('Last 30 days') }}</th>
                            @endif
                            <th data-breakpoints="lg">{{ translate('Last modified') }}</th>
                            <th class="text-right">{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($funnels as $funnel)
                            @php
                                $badge = $statusStyles[$funnel->status] ?? ['badge-soft-secondary', $funnel->status];
                                $boundIds = collect($funnel->product_ids ?? []);
                                $funnelProducts = $boundIds->map(function ($id) use ($products) {
                                    return $products->get($id);
                                })->filter();
                                // Products removed from the catalogue after being chosen.
                                $missingProducts = $boundIds->count() - $funnelProducts->count();
                                $archived = $funnel->status === 'archived';
                                $changed = $funnel->hasUnpublishedChanges();
                                $canOpenBuilder = !$archived && $editorReady && Auth::user()->can('edit_funnel');
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="funnel-thumb mr-3">
                                            @if ($funnel->thumbnail_upload_id)
                                                <img src="{{ uploaded_asset($funnel->thumbnail_upload_id) }}" alt="" loading="lazy">
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#a1a5b3" stroke-width="1.5" aria-hidden="true"><path d="M3 4h18l-7 8v6l-4 2v-8z"/></svg>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <div class="fs-14 fw-700 text-truncate funnel-name">{{ $funnel->title }}</div>
                                            @if ($funnel->internal_name !== $funnel->title)
                                                <div class="fs-12 text-muted text-truncate funnel-name">{{ $funnel->internal_name }}</div>
                                            @endif
                                            <div class="fs-12 text-secondary d-flex flex-wrap align-items-center" style="gap: 0 6px;">
                                                <span class="text-truncate funnel-name">{{ $funnel->publicPath() }}</span>
                                                @if ($funnel->isLive())
                                                    <button type="button" class="btn btn-link p-0 fs-12 funnel-copy" data-url="{{ $funnel->publicUrl() }}" data-done="{{ translate('Copied') }}">{{ translate('Copy link') }}</button>
                                                @endif
                                            </div>
                                            {{-- On phones the Actions column is too narrow for the button, so it sits under the name. --}}
                                            @if ($canOpenBuilder)
                                                <a href="{{ route('funnels.builder', $funnel->id) }}" class="btn btn-sm btn-primary mt-2 d-md-none funnel-open-builder">{{ translate('Open Visual Builder') }}</a>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-inline {{ $badge[0] }}">{{ $badge[1] }}</span>
                                    @if ($funnel->status === 'scheduled' && $funnel->scheduled_at)
                                        <div class="fs-11 text-muted mt-1">{{ translate('Goes live') }} {{ $funnel->scheduled_at->format('d M Y, h:i A') }}</div>
                                    @elseif ($funnel->published_version_id && $changed && !$archived)
                                        <div class="fs-11 text-warning mt-1">{{ translate('Has unpublished changes') }}</div>
                                    @endif
                                    @if ($funnel->unpublish_at && in_array($funnel->status, ['published', 'scheduled'], true))
                                        <div class="fs-11 text-muted mt-1">{{ translate('Goes offline') }} {{ $funnel->unpublish_at->format('d M Y, h:i A') }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($boundIds->isEmpty())
                                        <span class="text-muted fs-12">{{ translate('None chosen yet') }}</span>
                                    @elseif ($funnelProducts->isNotEmpty())
                                        <div class="d-flex align-items-center funnel-products" title="{{ $funnelProducts->pluck('name')->implode(', ') }}">
                                            @foreach ($funnelProducts->take(4) as $product)
                                                <img src="{{ uploaded_asset($product->thumbnail_img) }}" alt="{{ $product->name }}" loading="lazy">
                                            @endforeach
                                            <span class="fs-12 text-muted ml-3">
                                                {{ $funnelProducts->count() }} {{ $funnelProducts->count() === 1 ? translate('product') : translate('products') }}
                                            </span>
                                        </div>
                                    @endif
                                    @if ($missingProducts > 0)
                                        <div class="fs-11 text-danger mt-1">
                                            {{ $missingProducts }} {{ $missingProducts === 1 ? translate('product no longer in the catalogue') : translate('products no longer in the catalogue') }}
                                        </div>
                                    @endif
                                </td>
                                @if ($figures !== null)
                                    @php $figure = $figures[$funnel->id] ?? ['visitors' => 0, 'orders' => 0, 'revenue' => 0, 'conversion_rate' => null]; @endphp
                                    <td class="text-right fs-12 text-nowrap funnel-figures">
                                        <div><span class="text-muted">{{ translate('Visitors') }}</span> <strong>{{ number_format($figure['visitors']) }}</strong></div>
                                        <div>
                                            <span class="text-muted">{{ translate('Orders') }}</span> <strong>{{ number_format($figure['orders']) }}</strong>
                                            <span class="text-muted">· {{ $figure['conversion_rate'] === null ? '–' : rtrim(rtrim(number_format($figure['conversion_rate'], 1), '0'), '.') . '%' }}</span>
                                        </div>
                                        <div><span class="text-muted">{{ translate('Revenue') }}</span> <strong>{{ single_price($figure['revenue']) }}</strong></div>
                                    </td>
                                @endif
                                <td class="fs-12">
                                    <div>{{ optional($funnel->updated_at)->format('d M Y, h:i A') }}</div>
                                    @if ($funnel->editor)
                                        <div class="text-muted">{{ translate('by') }} {{ $funnel->editor->name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-end funnel-row-actions">
                                    @if ($canOpenBuilder)
                                        <a href="{{ route('funnels.builder', $funnel->id) }}" class="btn btn-sm btn-primary text-nowrap mr-2 d-none d-md-inline-block funnel-open-builder">{{ translate('Open Visual Builder') }}</a>
                                    @endif
                                    <div class="dropdown funnel-actions">
                                        <button class="btn btn-light size-40px action-toggle dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="{{ translate('Actions') }}"></button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            @can('edit_funnel')
                                                @if ($archived)
                                                    <span class="dropdown-item text-muted" disabled>{{ translate('Edit (restore first)') }}</span>
                                                @elseif ($editorReady)
                                                    <a class="dropdown-item" href="{{ route('funnels.builder', $funnel->id) }}">{{ translate('Edit') }}</a>
                                                @else
                                                    <span class="dropdown-item text-muted" disabled title="{{ translate('Set FUNNEL_EDITOR_URL to enable the visual editor') }}">{{ translate('Edit (editor not configured)') }}</span>
                                                @endif
                                            @endcan

                                            @if (\App\Models\Order::hasFunnelColumns() && Auth::user()->can('view_all_orders'))
                                                <a class="dropdown-item" href="{{ route('all_orders.index', ['funnel_id' => $funnel->id]) }}">{{ translate('View orders') }}</a>
                                            @endif
                                            @can('view_funnel_analytics')
                                                <a class="dropdown-item" href="{{ route('funnels.analytics', ['funnel' => $funnel->id]) }}">{{ translate('Analytics') }}</a>
                                            @endcan
                                            @if ($funnel->isLive())
                                                <a class="dropdown-item" href="{{ $funnel->publicUrl() }}" target="_blank" rel="noopener">{{ translate('View live page') }}</a>
                                            @endif

                                            @can('publish_funnel')
                                                @if (!$archived && $changed)
                                                    <form method="POST" action="{{ route('funnels.publish', $funnel->id) }}" data-confirm="{{ translate('Publish the current draft? Customers will see it immediately.') }}">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">{{ $funnel->published_version_id ? translate('Publish changes') : translate('Publish') }}</button>
                                                    </form>
                                                @endif
                                                @if (in_array($funnel->status, ['published', 'scheduled'], true))
                                                    <form method="POST" action="{{ route('funnels.unpublish', $funnel->id) }}" data-confirm="{{ translate('Unpublish this funnel? Its link will stop working until you publish again.') }}">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">{{ translate('Unpublish') }}</button>
                                                    </form>
                                                @endif
                                            @endcan

                                            @can('add_funnel')
                                                <form method="POST" action="{{ route('funnels.duplicate', $funnel->id) }}">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item">{{ translate('Duplicate') }}</button>
                                                </form>
                                                <button type="button" class="dropdown-item" data-save-template="{{ $funnel->id }}" data-name="{{ $funnel->title }}">{{ translate('Save as template') }}</button>
                                            @endcan

                                            @can('publish_funnel')
                                                @if ($archived)
                                                    <form method="POST" action="{{ route('funnels.restore', $funnel->id) }}">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">{{ translate('Restore') }}</button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('funnels.archive', $funnel->id) }}" data-confirm="{{ translate('Archive this funnel? It goes offline and is kept for reference.') }}">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">{{ translate('Archive') }}</button>
                                                    </form>
                                                @endif
                                            @endcan

                                            @can('delete_funnel')
                                                <div class="dropdown-divider"></div>
                                                <form method="POST" action="{{ route('funnels.destroy', $funnel->id) }}" data-confirm="{{ translate('Delete this funnel? Orders placed through it are kept. This cannot be undone from the dashboard.') }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">{{ translate('Delete') }}</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="aiz-pagination mt-3">
                    {{ $funnels->links() }}
                </div>
            @endif
        </div>
    </div>
@endif

@endsection

@section('modal')
    @can('add_funnel')
        <div class="modal fade" id="funnel-save-template" tabindex="-1" role="dialog" aria-labelledby="funnel-save-template-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form class="modal-content" method="POST" id="funnel-save-template-form">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title h6" id="funnel-save-template-title">{{ translate('Save as template') }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="fs-13 text-muted">{{ translate('The current draft is saved as a reusable design: layout, wording, pictures and offer settings. Products are left out and chosen again for each new funnel.') }}</p>
                        <div class="form-group">
                            <label class="fw-600" for="funnel-save-template-name">{{ translate('Template name') }}</label>
                            <input type="text" class="form-control" id="funnel-save-template-name" name="name" maxlength="150" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="fw-600" for="funnel-save-template-description">{{ translate('Description') }}</label>
                            <textarea class="form-control" id="funnel-save-template-description" name="description" rows="2" maxlength="500" placeholder="{{ translate('Optional: what the design is good for') }}"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-dismiss="modal">{{ translate('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ translate('Save template') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection

@section('script')
    <script type="text/javascript">
        // The admin theme's table plugin rebuilds the table rows after this script has run,
        // which drops listeners bound to the buttons inside them, so every handler listens
        // on the document. (Bound directly, the confirmations below were silently skipped.)
        var saveTemplateUrl = @json(route('funnels.save_as_template', '__ID__'));
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-save-template]');
            if (!button) return;
            document.getElementById('funnel-save-template-form').action = saveTemplateUrl.replace('__ID__', button.getAttribute('data-save-template'));
            document.getElementById('funnel-save-template-name').value = button.getAttribute('data-name') || '';
            $('#funnel-save-template').modal('show');
        });
        // Copies the live address; where the clipboard is not available (plain http) the address is shown to copy by hand.
        document.addEventListener('click', function (event) {
            var button = event.target.closest('.funnel-copy');
            if (!button) return;
            var url = button.getAttribute('data-url');
            var label = button.getAttribute('data-label') || button.textContent;
            button.setAttribute('data-label', label);
            var done = function () { button.textContent = button.getAttribute('data-done'); setTimeout(function () { button.textContent = label; }, 1500); };
            if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(url).then(done, function () { window.prompt('', url); });
            else window.prompt('', url);
        });
        // Confirmation text lives in data-confirm rather than inline JS, so a
        // translated message containing quotes cannot break the handler.
        document.addEventListener('submit', function (event) {
            var form = event.target.closest('.funnel-actions form[data-confirm]');
            if (form && !window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    </script>
@endsection
