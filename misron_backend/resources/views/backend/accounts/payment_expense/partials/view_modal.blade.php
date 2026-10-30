<div class="p-3">
    <table class="table table-borderless">
        <tr>
            <th width="120">{{ translate('Voucher No') }}:</th>
            <td><strong>{{ $expensePayment->voucher_no }}</strong></td>
        </tr>
        <tr>
            <th>{{ translate('Date') }}:</th>
            <td>{{ $expensePayment->date->format('d-m-Y') }}</td>
        </tr>
        <tr>
            <th>{{ translate('Pay From') }}:</th>
            <td>{{ $expensePayment->paymentAccount->name ?? '-' }}</td>
        </tr>
        <tr>
            <th>{{ translate('Total Amount') }}:</th>
            <td class="text-success"><strong>{{ $expensePayment->formatted_total_amount }}</strong></td>
        </tr>
        @if($expensePayment->costCenter)
        <tr>
            <th>{{ translate('Cost Center') }}:</th>
            <td>{{ $expensePayment->costCenter->name ?? '-' }}</td>
        </tr>
        @endif
        <tr>
            <th>{{ translate('Prepared By') }}:</th>
            <td>{{ $expensePayment->creator->name ?? '-' }}</td>
        </tr>
        <tr>
            <th>{{ translate('Status') }}:</th>
            <td><span class="badge {{ $expensePayment->status_badge_class }}">{{ $expensePayment->status_label }}</span></td>
        </tr>
        <tr>
            <th>{{ translate('Note') }}:</th>
            <td>{{ $expensePayment->note ?? '-' }}</td>
        </tr>
    </table>
    
    @if($expensePayment->details->count() > 0)
    <h6 class="mt-3">{{ translate('Expense Details') }}:</h6>
    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>#</th>
                <th>{{ translate('Expense Account') }}</th>
                <th class="text-right">{{ translate('Amount') }}</th>
                <th>{{ translate('Note') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expensePayment->details as $index => $detail)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $detail->expenseAccount->name ?? '-' }}</td>
                <td class="text-right">{{ number_format($detail->amount, 2) }} ৳</td>
                <td>{{ $detail->note ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>