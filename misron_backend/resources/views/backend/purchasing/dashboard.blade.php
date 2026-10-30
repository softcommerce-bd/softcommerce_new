@extends('backend.layouts.app')
@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3"><h1 class="h3">{{ translate('Purchase Dashboard') }}</h1></div>
@php
    $tile = [
        ['awaiting_orders', 'Confirmed orders awaiting stock', route('purchasing.requirements', ['only_shortage' => 1]), 'danger'],
        ['units_to_source', 'Units to source', route('purchasing.requirements', ['only_shortage' => 1]), 'danger'],
        ['open_pos', 'Outstanding purchase orders', route('purchasing.receiving.index'), 'primary'],
        ['awaiting_receipt', 'Units awaiting receipt', route('purchasing.receiving.index'), 'primary'],
        ['payables', 'Supplier payables', route('purchasing.supplier.outstanding'), 'warning'],
        ['valuation', 'Inventory valuation (FIFO)', route('purchasing.valuation'), 'success'],
        ['exceptions', 'Cost exceptions', route('purchasing.report', 'reconciliation'), 'secondary'],
    ];
@endphp
<div class="row">
    @foreach ($tile as [$key, $label, $url, $color])
        <div class="col-6 col-md-4 col-xl-3 mb-3">
            <a href="{{ $url }}" class="card h-100 text-reset text-decoration-none border-{{ $color }}">
                <div class="card-body"><div class="text-muted small">{{ translate($label) }}</div><div class="h3 mb-0">{{ $cards[$key] }}</div></div>
            </a>
        </div>
    @endforeach
</div>
<div class="mb-3">
    <a class="btn btn-primary" href="{{ route('purchasing.quick') }}">{{ translate('Quick Purchase') }}</a>
    <a class="btn btn-outline-primary" href="{{ route('purchasing.requirements') }}">{{ translate('Purchase Requirements') }}</a>
    <a class="btn btn-outline-primary" href="{{ route('purchasing.receiving.index') }}">{{ translate('Goods Receiving') }}</a>
    <a class="btn btn-outline-secondary" href="{{ route('purchasing.stock') }}">{{ translate('Stock Overview') }}</a>
</div>
<div class="card"><div class="card-header"><h5 class="mb-0 h6">{{ translate('Recent goods receipts') }}</h5></div>
    <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>{{ translate('Receipt') }}</th><th>{{ translate('Date') }}</th><th>{{ translate('Supplier') }}</th><th class="text-right">{{ translate('Total') }}</th><th>{{ translate('Invoice') }}</th></tr></thead>
        <tbody>@forelse ($recent as $r)<tr><td>{{ $r->receipt_no }}</td><td>{{ $r->received_at }}</td><td>{{ $r->business_name }}</td><td class="text-right">{{ $r->total }}</td><td>{{ $r->invoice_status }}</td></tr>
        @empty<tr><td colspan="5" class="text-muted text-center">{{ translate('Nothing received yet') }}</td></tr>@endforelse</tbody>
    </table></div></div>
@endsection
