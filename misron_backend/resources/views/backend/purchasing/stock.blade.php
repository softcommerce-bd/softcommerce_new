@extends('backend.layouts.app')
@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3"><h1 class="h3">{{ translate('Stock Overview') }}</h1>
<p class="text-muted mb-0">{{ translate('Available to sell = physical − confirmed demand. It may be negative: that is a confirmed shortage, never negative physical stock.') }}</p></div>
<form class="form-inline mb-3" method="get"><input class="form-control mr-2" name="q" placeholder="{{ translate('Name, SKU or barcode') }}" value="{{ request('q') }}"><label class="mr-2"><input type="checkbox" name="negative" value="1" @checked(request('negative'))> {{ translate('Only below zero') }}</label><button class="btn btn-secondary">{{ translate('Search') }}</button></form>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>{{ translate('Product') }}</th><th>{{ translate('Variation') }}</th><th>SKU</th><th class="text-right">{{ translate('Physical') }}</th><th class="text-right">{{ translate('Demand') }}</th><th class="text-right">{{ translate('Allocated') }}</th><th class="text-right">{{ translate('Damaged') }}</th><th class="text-right">{{ translate('Available') }}</th><th>{{ translate('Policy') }}</th><th></th></tr></thead>
    <tbody>@foreach ($rows as $r)
        <tr><td>{{ $r->name }}</td><td>{{ $r->variant }}</td><td>{{ $r->sku }}</td><td class="text-right">{{ $r->on_hand }}</td><td class="text-right">{{ $r->demand }}</td><td class="text-right">{{ $r->allocated }}</td><td class="text-right">{{ $r->damaged }}</td>
            <td class="text-right font-weight-bold {{ $r->available < 0 ? 'text-danger' : '' }}">{{ $r->available }}</td><td>{{ $r->stock_policy }}</td>
            <td class="text-right">@can('manage_stock_policy')<details><summary class="btn btn-sm btn-outline-secondary">{{ translate('Settings') }}</summary>
                <form method="post" action="{{ route('purchasing.policy', $r->id) }}" class="text-left p-2" style="min-width:260px">@csrf
                    <select class="form-control form-control-sm mb-1" name="stock_policy"><option value="stock_only" @selected($r->stock_policy == 'stock_only')>Stock only</option><option value="backorder" @selected($r->stock_policy == 'backorder')>Allow backorder</option><option value="preorder" @selected($r->stock_policy == 'preorder')>Preorder</option></select>
                    <input class="form-control form-control-sm mb-1" type="number" min="0" name="max_backorder_qty" value="{{ $r->max_backorder_qty }}" placeholder="{{ translate('Max backorder qty') }}">
                    <input class="form-control form-control-sm mb-1" type="number" min="0" name="max_preorder_qty" value="{{ $r->max_preorder_qty }}" placeholder="{{ translate('Max preorder qty') }}">
                    <input class="form-control form-control-sm mb-1" type="date" name="preorder_available_at" value="{{ $r->preorder_available_at }}">
                    <select class="form-control form-control-sm mb-1" name="preferred_supplier_id"><option value="">{{ translate('Preferred supplier') }}</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected($r->preferred_supplier_id == $s->id)>{{ $s->business_name }}</option>@endforeach</select>
                    <input class="form-control form-control-sm mb-1" type="number" step="0.01" min="0" name="estimated_purchase_cost" value="{{ $r->estimated_purchase_cost }}" placeholder="{{ translate('Estimated cost (planning only)') }}">
                    <input class="form-control form-control-sm mb-1" type="number" min="0" name="supplier_lead_time_days" value="{{ $r->supplier_lead_time_days }}" placeholder="{{ translate('Lead time (days)') }}">
                    <button class="btn btn-sm btn-primary">{{ translate('Save') }}</button></form></details>@endcan</td></tr>
    @endforeach</tbody></table></div><div class="card-footer">{{ $rows->links() }}</div></div>
@endsection
