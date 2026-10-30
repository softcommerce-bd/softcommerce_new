@forelse($supplierPayments as $index => $payment)
{{-- @dd($payment->paymentAccount->name); --}}
<tr>
    <td>{{ $index + 1 }}</td>
    <td><strong class="text-primary">{{ $payment->payment_no }}</strong></td>
    <td><div class="d-flex align-items-center"><div class="rounded-circle bg-light d-flex align-items-center justify-content-center mr-2" style="width:40px;height:40px;"><i class="las la-user text-primary"></i></div><strong>{{ $payment->supplier->business_name ?? '-' }}</strong></div></td>
    <td><strong class="text-success">{{ number_format($payment->amount,2) }} ৳</strong></td>
    <td><span class="badge badge-soft-info px-3 py-2">{{ $payment->paymentAccount->name }}</span></td>
    <td>{{ date('d M Y', strtotime($payment->payment_date)) }}</td>
    <td>@if($payment->status == 'paid') <span class="badge badge-success">{{ translate('Paid') }}</span> @else <span class="badge badge-warning">{{ translate('Partial') }}</span> @endif</td>
    <td class="text-right"><div class="dropdown"><button class="btn btn-light btn-sm" data-toggle="dropdown"><i class="fa fa-ellipsis-v"></i></button><div class="dropdown-menu dropdown-menu-right"><a class="dropdown-item" href="javascript:void(0)" onclick="viewSupplierPayment({{ $payment->id }})"><i class="las la-eye mr-2"></i> {{ translate('View') }}</a><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteSupplierPayment({{ $payment->id }})"><i class="las la-trash mr-2"></i> {{ translate('Delete') }}</a></div></div></td>
</tr>
@empty
<tr><td colspan="8" class="text-center py-5">{{ translate('No payments found') }}</td></tr>
@endforelse