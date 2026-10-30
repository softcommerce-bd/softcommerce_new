@extends('backend.layouts.app')
@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3"><h1 class="h3">{{ translate('Supplier Payments') }}</h1>
<p class="text-muted mb-0">{{ translate('Record the supplier invoice for goods you received, then pay it in full or in parts. Paying never changes stock; an advance is held for the supplier and can be applied later.') }}</p></div>
<form method="get" class="form-inline mb-3" id="supplier-pick">
    <label class="mr-2" for="sp-supplier">{{ translate('Supplier') }}</label>
    <select id="sp-supplier" name="supplier_id" class="form-control mr-2" onchange="this.form.submit()"><option value="">— {{ translate('choose') }} —</option>
        @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected($supplier && $supplier->id == $s->id)>{{ $s->business_name }} ({{ translate('due') }} {{ $s->due_amount }})</option>@endforeach</select>
    <noscript><button class="btn btn-primary">{{ translate('Show') }}</button></noscript>
    @if ($supplier)<a class="btn btn-link" href="{{ route('purchasing.report', ['name' => 'supplier-statement', 'supplier_id' => $supplier->id]) }}">{{ translate('Supplier ledger / statement') }}</a>@endif
</form>
@if ($supplier)
<div class="row">
 <div class="col-lg-8">
  <div class="card mb-3"><div class="card-header h6 mb-0">{{ translate('Received, waiting for the supplier invoice') }}</div>
   <div class="table-responsive"><table class="table table-sm mb-0" id="uninvoiced">
    <thead><tr><th>{{ translate('Receipt') }}</th><th>{{ translate('Date') }}</th><th class="text-right">{{ translate('Goods value') }}</th><th>{{ translate('Record the invoice') }}</th></tr></thead>
    <tbody>@forelse ($uninvoiced as $g)<tr><td>{{ $g->receipt_no }}</td><td>{{ $g->received_at }}</td><td class="text-right">{{ $g->goods_total }}</td>
     <td>@can('add_purchase')<form method="post" action="{{ route('purchasing.invoices.store') }}" class="form-inline js-once">@csrf
        <input type="hidden" name="op_key" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="supplier_id" value="{{ $supplier->id }}"><input type="hidden" name="goods_receipt_id" value="{{ $g->id }}">
        <input class="form-control form-control-sm mr-1 mb-1" style="width:120px" name="invoice_no" placeholder="{{ translate('Invoice no.') }}" required maxlength="80">
        <input class="form-control form-control-sm mr-1 mb-1" style="width:110px" type="number" step="0.01" min="0.01" name="subtotal" value="{{ $g->goods_total }}" required title="{{ translate('Invoice subtotal') }}">
        <input class="form-control form-control-sm mr-1 mb-1" style="width:80px" type="number" step="0.01" min="0" name="freight" placeholder="{{ translate('Freight') }}">
        <input class="form-control form-control-sm mr-1 mb-1" style="width:80px" type="number" step="0.01" min="0" name="tax" placeholder="{{ translate('Tax') }}">
        <button class="btn btn-sm btn-primary mb-1">{{ translate('Record invoice') }}</button></form>@endcan</td></tr>
    @empty<tr><td colspan="4" class="text-center text-muted">{{ translate('Every receipt of this supplier has its invoice.') }}</td></tr>@endforelse</tbody></table></div></div>

  <div class="card mb-3"><div class="card-header h6 mb-0">{{ translate('Invoices') }}</div>
   <div class="table-responsive"><table class="table table-sm mb-0" id="invoices">
    <thead><tr><th>{{ translate('Invoice') }}</th><th>{{ translate('Date') }}</th><th class="text-right">{{ translate('Total') }}</th><th class="text-right">{{ translate('Paid') }}</th><th class="text-right">{{ translate('Due') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Pay') }}</th></tr></thead>
    <tbody>@forelse ($invoices as $i)@php($due = round($i->total - $i->paid, 2))<tr><td>{{ $i->invoice_no }}</td><td>{{ $i->invoice_date }}</td><td class="text-right">{{ $i->total }}</td><td class="text-right">{{ $i->paid }}</td><td class="text-right font-weight-bold">{{ number_format($due, 2, '.', '') }}</td><td>{{ $i->status }}</td>
     <td>@if ($due > 0)@can('make_supplier_payment')<form method="post" action="{{ route('purchasing.invoices.pay', $i->id) }}" class="form-inline js-once">@csrf
        <input type="hidden" name="op_key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <select name="account_id" class="form-control form-control-sm mr-1 mb-1" required aria-label="{{ translate('Pay from') }}"><option value="">{{ translate('Pay from') }}</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->current_balance }})</option>@endforeach</select>
        <input class="form-control form-control-sm mr-1 mb-1" style="width:100px" type="number" step="0.01" min="0.01" max="{{ number_format($due, 2, '.', '') }}" name="amount" value="{{ number_format($due, 2, '.', '') }}" required aria-label="{{ translate('Amount') }}">
        <button class="btn btn-sm btn-success mb-1">{{ translate('Pay') }}</button></form>@endcan @else<span class="text-muted">{{ translate('Paid') }}</span>@endif</td></tr>
    @empty<tr><td colspan="7" class="text-center text-muted">{{ translate('No invoices yet.') }}</td></tr>@endforelse</tbody></table></div></div>
 </div>
 <div class="col-lg-4">
  <div class="card mb-3"><div class="card-body">
    <h6>{{ translate('Supplier balance') }}</h6>
    <dl class="row mb-0"><dt class="col-7">{{ translate('Owed (due_amount)') }}</dt><dd class="col-5 text-right" id="owed">{{ $supplier->due_amount }}</dd><dt class="col-7">{{ translate('Advance held') }}</dt><dd class="col-5 text-right" id="advance">{{ $supplier->opening_balance }}</dd></dl></div></div>
  @can('make_supplier_payment')
  <div class="card"><div class="card-body"><h6>{{ translate('Pay in advance') }}</h6>
    <form method="post" action="{{ route('purchasing.advances.store') }}" class="js-once">@csrf<input type="hidden" name="op_key" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
        <select name="account_id" class="form-control form-control-sm mb-2" required aria-label="{{ translate('Pay from') }}"><option value="">{{ translate('Pay from') }}</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->current_balance }})</option>@endforeach</select>
        <input class="form-control form-control-sm mb-2" type="number" step="0.01" min="0.01" name="amount" placeholder="{{ translate('Amount') }}" required>
        <button class="btn btn-sm btn-outline-success">{{ translate('Record advance') }}</button></form></div></div>
  @endcan
 </div>
</div>
@endif
@endsection
@section('script')
<script>
  // one submit per form: the button locks and says so while the request runs (the op_key also makes a repeat harmless server-side)
  document.querySelectorAll('form.js-once').forEach(function (f) { f.addEventListener('submit', function () {
    if (f.dataset.sent) return; f.dataset.sent = '1';
    f.querySelectorAll('button').forEach(function (b) { b.disabled = true; b.textContent = '{{ translate('Saving…') }}'; });
  }); });
</script>
@endsection
