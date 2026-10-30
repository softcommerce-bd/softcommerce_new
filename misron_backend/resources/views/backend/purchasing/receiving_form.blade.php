@extends('backend.layouts.app')
@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3"><h1 class="h3">{{ translate('Receive goods') }}: {{ $po->reference_no }} — {{ $supplier->business_name }}</h1></div>
<form method="post" class="js-once" action="{{ route('purchasing.receiving.receive', $po->id) }}">@csrf
<input type="hidden" name="op_key" value="{{ $opKey }}">
<div class="card mb-3"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>{{ translate('Product') }}</th><th>{{ translate('Variation') }}</th><th class="text-right">{{ translate('Ordered') }}</th><th class="text-right">{{ translate('Received') }}</th><th class="text-right">{{ translate('Pending') }}</th><th style="width:110px">{{ translate('Receive now') }}</th><th style="width:120px">{{ translate('Actual unit cost') }}</th></tr></thead>
    <tbody>@foreach ($lines as $i => $l)<tr><td>{{ $l->product_name }}</td><td>{{ $l->variant }}</td><td class="text-right">{{ (int) $l->purchase_quantity }}</td><td class="text-right">{{ (int) $l->recieved_quantity }}</td><td class="text-right">{{ (int) $l->pending_quantity }}</td>
        <td><input type="hidden" name="lines[{{ $i }}][detail_id]" value="{{ $l->id }}"><input class="form-control form-control-sm" type="number" min="0" max="{{ (int) $l->pending_quantity }}" name="lines[{{ $i }}][qty]" value="{{ (int) $l->pending_quantity }}"></td>
        <td><input class="form-control form-control-sm" type="number" step="0.0001" min="0" name="lines[{{ $i }}][unit_cost]" value="{{ $l->buying_unit_cost }}" required></td></tr>@endforeach</tbody>
</table></div></div>
<div class="card"><div class="card-body">
    <div class="form-row">
        <div class="form-group col-md-3"><label>{{ translate('Freight / landed cost (capitalised)') }}</label><input class="form-control" type="number" step="0.01" min="0" name="freight" value="0"></div>
        <div class="form-group col-md-3"><label>{{ translate('Paid from account') }}</label><select class="form-control" name="freight_account_id"><option value="">—</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->current_balance }})</option>@endforeach</select></div>
        <div class="form-group col-md-3"><label>{{ translate('Supplier invoice no. (records the payable)') }}</label><input class="form-control" name="invoice_no" maxlength="80"></div>
    </div>
    @can('make_supplier_payment')
    <div class="form-row"><div class="form-group col-md-3"><label>{{ translate('Pay now (optional)') }}</label><input class="form-control" type="number" step="0.01" min="0" name="pay_amount" value="0"></div>
        <div class="form-group col-md-3"><label>{{ translate('Pay from') }}</label><select class="form-control" name="pay_account_id"><option value="">—</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->current_balance }})</option>@endforeach</select></div></div>
    @endcan
    <p class="text-muted small">{{ translate('Receiving, invoicing and paying are recorded as three separate steps. Submitting twice does nothing the second time.') }}</p>
    <button class="btn btn-primary">{{ translate('Receive') }}</button>
</div></div></form>
@endsection
@section('script')
<script>document.querySelectorAll('form.js-once').forEach(function (f) { f.addEventListener('submit', function (e) { if (f.dataset.sent) { e.preventDefault(); return; } f.dataset.sent = '1'; f.querySelectorAll('button:not([type=button])').forEach(function (b) { b.disabled = true; b.textContent = '{{ translate('Saving…') }}'; }); }); });</script>
@endsection
