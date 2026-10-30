<div class="p-3">
    <p><strong>Voucher No:</strong> {{ $income->voucher_no }}</p>
    <p><strong>Date:</strong> {{ $income->date->format('d-m-Y') }}</p>
    <p><strong>Deposit To:</strong> {{ $income->receivedAccount->name ?? '-' }}</p>
    <p><strong>Cost Center:</strong> {{ $income->costCenter->name ?? '-' }}</p>
    <p><strong>Prepared By:</strong> {{ $income->creator->name ?? '-' }}</p>
    <hr>
    <table class="table table-sm table-bordered">
        <thead><tr><th>Income Account</th><th class="text-right">Amount</th><th>Note</th></tr></thead>
        <tbody>
            @foreach($income->details as $detail)
            <tr>
                <td>{{ $detail->incomeAccount->name ?? '-' }}</td>
                <td class="text-right">{{ number_format($detail->amount, 2) }} ৳</td>
                <td>{{ $detail->note }}</td>
            </tr>
            @endforeach
            <tr><th colspan="2" class="text-right">Total</th><th>{{ number_format($income->total_amount, 2) }} ৳</th></tr>
        </tbody>
    </table>
    @if($income->note)
    <p><strong>Note:</strong> {{ $income->note }}</p>
    @endif
</div>