@forelse($supplierPayments as $payment)
<div class="card border shadow-sm mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between"><div><h6 class="mb-1">{{ $payment->supplier->business_name ?? '-' }}</h6><small class="text-muted">{{ $payment->payment_no }}</small></div><div class="dropdown"><button class="btn btn-light btn-sm" data-toggle="dropdown"><i class="fa fa-ellipsis-v"></i></button><div class="dropdown-menu dropdown-menu-right"><a class="dropdown-item" href="javascript:void(0)" onclick="viewSupplierPayment({{ $payment->id }})">{{ translate('View') }}</a><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteSupplierPayment({{ $payment->id }})">{{ translate('Delete') }}</a></div></div></div>
        <hr>
        <div class="row"><div class="col-6"><small class="text-muted">{{ translate('Amount') }}</small><br><strong class="text-success">{{ number_format($payment->amount,2) }} ৳</strong></div><div class="col-6"><small class="text-muted">{{ translate('Method') }}</small><br><strong>{{ $payment->payment_method }}</strong></div><div class="col-6"><small class="text-muted">{{ translate('Date') }}</small><br><strong>{{ date('d M Y', strtotime($payment->payment_date)) }}</strong></div><div class="col-6"><small class="text-muted">{{ translate('Status') }}</small><br>@if($payment->status=='paid')<span class="badge badge-success">{{ translate('Paid') }}</span>@else<span class="badge badge-warning">{{ translate('Partial') }}</span>@endif</div></div>
    </div>
</div>
@empty
<div class="text-center py-5">{{ translate('No payments found') }}</div>
@endforelse