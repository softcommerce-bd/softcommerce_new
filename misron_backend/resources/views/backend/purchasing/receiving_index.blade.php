@extends('backend.layouts.app')
@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3"><h1 class="h3">{{ translate('Goods Receiving') }}</h1><p class="text-muted">{{ translate('Open purchase orders waiting for goods. Receiving adds physical stock and creates FIFO cost layers; it does not pay anyone.') }}</p></div>
<div class="card"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>PO</th><th>{{ translate('Date') }}</th><th>{{ translate('Supplier') }}</th><th>{{ translate('Status') }}</th><th class="text-right">{{ translate('Value') }}</th><th></th></tr></thead>
    <tbody>@forelse ($pos as $p)<tr><td>{{ $p->reference_no }}</td><td>{{ $p->purchase_date }}</td><td>{{ $p->business_name }}</td><td>{{ $p->status }}</td><td class="text-right">{{ $p->net_total_amount }}</td><td class="text-right"><a class="btn btn-sm btn-primary" href="{{ route('purchasing.receiving.form', $p->id) }}">{{ translate('Receive') }}</a></td></tr>
    @empty<tr><td colspan="6" class="text-center text-muted">{{ translate('Nothing is waiting to be received.') }}</td></tr>@endforelse</tbody>
</table></div></div>
@endsection
