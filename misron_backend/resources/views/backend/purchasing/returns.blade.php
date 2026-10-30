@extends('backend.layouts.app')
@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3"><h1 class="h3">{{ translate('Receive customer returns') }}</h1>
<p class="text-muted mb-0">{{ translate('Only goods that physically came back and were inspected. Resellable units become stock again (and return to their cost layer); damaged or rejected units never become sellable.') }}</p></div>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>{{ translate('Order') }}</th><th>{{ translate('Product') }}</th><th>{{ translate('Variation') }}</th><th class="text-right">{{ translate('Shipped') }}</th><th class="text-right">{{ translate('Already received back') }}</th><th>{{ translate('Receive') }}</th></tr></thead>
    <tbody>@forelse ($rows as $r)<tr><td>{{ $r->code }}</td><td>{{ $r->name }}</td><td>{{ $r->variant }}</td><td class="text-right">{{ $r->qty_dispatched }}</td><td class="text-right">{{ $r->returned }}</td>
        <td>@if ($r->qty_dispatched > $r->returned)<form method="post" action="{{ route('purchasing.returns.receive') }}" class="form-inline">@csrf
            <input type="hidden" name="op_key" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="order_detail_id" value="{{ $r->line_id }}">
            <input class="form-control form-control-sm mr-1" style="width:70px" type="number" min="1" max="{{ $r->qty_dispatched - $r->returned }}" name="qty" value="1">
            <select class="form-control form-control-sm mr-1" name="condition"><option value="resellable">{{ translate('Resellable') }}</option><option value="damaged">{{ translate('Damaged') }}</option><option value="rejected">{{ translate('Rejected') }}</option></select>
            <button class="btn btn-sm btn-primary">{{ translate('Receive') }}</button></form>@else <span class="text-muted">{{ translate('Complete') }}</span>@endif</td></tr>
    @empty<tr><td colspan="6" class="text-center text-muted">{{ translate('No shipped order lines yet.') }}</td></tr>@endforelse</tbody></table></div></div>
@endsection
