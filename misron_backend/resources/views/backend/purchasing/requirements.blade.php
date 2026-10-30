@extends('backend.layouts.app')
@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3"><h1 class="h3">{{ translate('Purchase Requirements') }}</h1>
    <p class="text-muted mb-0">{{ translate('Confirmed, unfulfilled order demand that stock and open purchase orders do not cover. Pending and cancelled orders never appear here.') }}</p></div>
<form class="form-inline mb-3" method="get">
    <input class="form-control mr-2 mb-2" type="number" name="order_id" placeholder="{{ translate('Order id') }}" value="{{ $filters['order_id'] ?? '' }}">
    <input class="form-control mr-2 mb-2" type="number" name="product_id" placeholder="{{ translate('Product id') }}" value="{{ $filters['product_id'] ?? '' }}">
    <select class="form-control mr-2 mb-2" name="supplier_id"><option value="">{{ translate('Preferred supplier') }}</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(($filters['supplier_id'] ?? '') == $s->id)>{{ $s->business_name }}</option>@endforeach</select>
    <input class="form-control mr-2 mb-2" type="date" name="from" value="{{ $filters['from'] ?? '' }}"><input class="form-control mr-2 mb-2" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
    <label class="mr-2 mb-2"><input type="checkbox" name="only_shortage" value="1" @checked(!empty($filters['only_shortage']))> {{ translate('Only items still to buy') }}</label>
    <button class="btn btn-secondary mb-2">{{ translate('Filter') }}</button>
</form>
@php $statusLabel = ['needs_purchase' => 'Needs Purchase', 'purchase_ordered' => 'Purchase Ordered', 'partially_received' => 'Partially Received', 'ready_for_fulfillment' => 'Ready for Fulfillment', 'fulfilled' => 'Fulfilled', 'cancelled' => 'Cancelled']; @endphp
<form method="post" action="{{ route('purchasing.requirements.po') }}">@csrf
<input type="hidden" name="op_key" value="{{ $opKey }}">
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th></th><th>{{ translate('Product') }}</th><th>{{ translate('Variation') }}</th><th class="text-right">{{ translate('Confirmed') }}</th><th class="text-right">{{ translate('Physical') }}</th><th class="text-right">{{ translate('Allocated') }}</th><th class="text-right">{{ translate('Incoming') }}</th><th class="text-right">{{ translate('To buy') }}</th><th>{{ translate('Orders') }}</th><th style="width:90px">{{ translate('Qty') }}</th><th style="width:110px">{{ translate('Unit cost') }}</th></tr></thead>
    <tbody>
    @forelse ($items as $i => $it)
        <tr>
            <td><input type="checkbox" name="items[{{ $i }}][on]" value="1" onchange="this.closest('tr').querySelectorAll('input.f').forEach(e => e.disabled = !this.checked)" @checked($it['shortage'] > 0)></td>
            <td>{{ $it['product_name'] }}<input type="hidden" class="f" name="items[{{ $i }}][product_id]" value="{{ $it['product_id'] }}"></td>
            <td>{{ $it['variant'] }}<input type="hidden" class="f" name="items[{{ $i }}][variant]" value="{{ $it['variant'] }}"></td>
            <td class="text-right">{{ $it['demand'] }}</td><td class="text-right">{{ $it['on_hand'] }}</td><td class="text-right">{{ $it['allocated'] }}</td><td class="text-right">{{ $it['incoming'] }}</td>
            <td class="text-right font-weight-bold {{ $it['shortage'] > 0 ? 'text-danger' : 'text-success' }}">{{ $it['shortage'] }}</td>
            <td class="small">@foreach ($it['rows'] as $r)<span class="badge badge-light" title="{{ translate($statusLabel[$r['status']] ?? $r['status']) }}">{{ $r['order_code'] }} ×{{ $r['outstanding'] }} · {{ translate($statusLabel[$r['status']] ?? $r['status']) }}</span> @endforeach</td>
            <td><input class="form-control form-control-sm f" type="number" min="1" name="items[{{ $i }}][qty]" value="{{ max(1, $it['shortage']) }}"></td>
            <td><input class="form-control form-control-sm f" type="number" step="0.01" min="0" name="items[{{ $i }}][unit_cost]" value="{{ $it['estimated_purchase_cost'] ?? '' }}" placeholder="{{ translate('required') }}"></td>
        </tr>
    @empty <tr><td colspan="11" class="text-center text-muted">{{ translate('No confirmed demand is waiting.') }}</td></tr> @endforelse
    </tbody></table></div>
@if (count($items))
<div class="card-footer form-inline">
    <select class="form-control mr-2" name="supplier_id" required><option value="">{{ translate('Supplier') }}</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->business_name }}</option>@endforeach</select>
    <select class="form-control mr-2" name="status"><option value="ordered">{{ translate('Create purchase order') }}</option><option value="draft">{{ translate('Save as draft') }}</option></select>
    <button class="btn btn-primary" onclick="document.querySelectorAll('tr').forEach(function(tr){var c=tr.querySelector('input[type=checkbox]'); if(c && !c.checked){tr.querySelectorAll('input.f').forEach(function(e){e.disabled=true})}})">{{ translate('Purchase selected') }}</button>
    <small class="text-muted ml-3">{{ translate('Buying covers the confirmed demand once: the same units cannot be ordered twice.') }}</small>
</div>@endif
</div></form>
@endsection
