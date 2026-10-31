@extends('backend.layouts.app')
@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3"><h1 class="h3">{{ translate('Return to supplier') }}</h1>
<p class="text-muted mb-0">{{ translate('Only units still on the shelf from that receipt can go back: sold, shipped or reserved units cannot. The supplier credit follows the receipt: it clears the not-yet-invoiced goods, credits the invoice, or becomes an advance if the invoice was already paid.') }}</p></div>
<form method="get" class="form-inline mb-3"><label class="mr-2" for="sr-supplier">{{ translate('Supplier') }}</label>
    <select id="sr-supplier" name="supplier_id" class="form-control mr-2" onchange="this.form.submit()"><option value="">{{ translate('All suppliers') }}</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected($sid == $s->id)>{{ $s->business_name }}</option>@endforeach</select></form>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0" id="receipt-lines">
    <thead><tr><th>{{ translate('Receipt') }}</th><th>{{ translate('Supplier') }}</th><th>{{ translate('Product') }}</th><th>{{ translate('Variation') }}</th><th class="text-right">{{ translate('Received') }}</th><th class="text-right">{{ translate('Returned') }}</th><th class="text-right">{{ translate('Unit cost') }}</th><th>{{ translate('Invoice') }}</th><th>{{ translate('Return') }}</th></tr></thead>
    <tbody>@forelse ($rows as $r)<tr><td>{{ $r->receipt_no }}</td><td>{{ $r->business_name }}</td><td>{{ $r->name }}</td><td>{{ $r->variant }}</td><td class="text-right">{{ $r->qty }}</td><td class="text-right">{{ $r->returned }}</td><td class="text-right">{{ $r->unit_cost }}</td><td>{{ $r->invoice_status }}</td>
      <td>@if ($r->qty > $r->returned)<form method="post" action="{{ route('purchasing.supplier-returns.store') }}" class="form-inline js-once">@csrf
        <input type="hidden" name="op_key" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="receipt_line_id" value="{{ $r->id }}">
        <input class="form-control form-control-sm mr-1" style="width:70px" type="number" min="1" max="{{ $r->qty - $r->returned }}" name="qty" value="1" required aria-label="{{ translate('Quantity') }}">
        <input class="form-control form-control-sm mr-1" style="width:130px" name="note" maxlength="255" placeholder="{{ translate('Reason') }}">
        <button class="btn btn-sm btn-outline-danger">{{ translate('Return') }}</button></form>@else<span class="text-muted">{{ translate('Complete') }}</span>@endif</td></tr>
    @empty<tr><td colspan="9" class="text-center text-muted">{{ translate('No goods receipts yet.') }}</td></tr>@endforelse</tbody></table></div></div>
@endsection
@section('script')
<script>document.querySelectorAll('form.js-once').forEach(function (f) { f.addEventListener('submit', function () { if (f.dataset.sent) return; f.dataset.sent = '1'; f.querySelectorAll('button').forEach(function (b) { b.disabled = true; b.textContent = '{{ translate('Saving…') }}'; }); }); });</script>
@endsection
