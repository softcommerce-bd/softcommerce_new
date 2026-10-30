@forelse($expensePayments as $index => $payment)
<tr>
    <td>{{ $expensePayments->firstItem() + $index }}</td>
    <td>{{ $payment->date->format('d-m-Y') }}</td>
    <td><strong class="text-primary">{{ $payment->voucher_no }}</strong></td>
    <td>
        @foreach($payment->details as $detail)
            <span class="badge badge-info mb-1 d-inline-block">{{ $detail->expenseAccount->name ?? '-' }}</span>
        @endforeach
    </td>
    <td>{{ $payment->paymentAccount->name ?? '-' }}</td>
    <td class="text-right"><strong class="text-success">{{ number_format($payment->total_amount, 2) }}</strong></td>
    <td>{{ $payment->creator->name ?? '-' }}</td>
    <td>{{ Str::limit($payment->note, 50) ?? '-' }}</td>
    <td class="text-right">
        <button class="btn btn-sm btn-outline-secondary border-0" title="Print" onclick="printExpense({{ $payment->id }})">
            <i class="las la-print"></i>
        </button>
        <a href="{{ route('accounts.expense-payments.edit', $payment->id) }}" class="btn btn-sm btn-outline-primary border-0" title="Edit">
            <i class="las la-edit"></i>
        </a>
        <button class="btn btn-sm btn-outline-danger border-0" title="Delete" onclick="deleteExpense({{ $payment->id }})">
            <i class="las la-trash"></i>
        </button>
    </td>
</tr>
@empty
<tr>
    <td colspan="9" class="text-center py-5">
        <i class="las la-receipt la-3x text-muted"></i>
        <p class="mt-2">{{ translate('No expense payments found') }}</p>
    </td>
</tr>
@endforelse