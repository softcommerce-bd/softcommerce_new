<!-- backend/accounts/voucher_show.blade.php -->
<div class="voucher-print-container p-4">
    <div class="text-center mb-4">
        <h3>{{ translate('VOUCHER') }}</h3>
        <p class="mb-0"><strong>{{ translate('Voucher No') }}:</strong> {{ $voucher->voucher_no }}</p>
        <p><strong>{{ translate('Date') }}:</strong> {{ $voucher->date->format('Y-m-d') }}</p>
    </div>
    
    <div class="mb-4">
        <p><strong>{{ translate('Type') }}:</strong> {{ ucfirst($voucher->type) }}</p>
        <p><strong>{{ translate('Description') }}:</strong> {{ $voucher->description }}</p>
        @if($voucher->reference_no)
        <p><strong>{{ translate('Reference') }}:</strong> {{ $voucher->reference_no }}</p>
        @endif
        <p><strong>{{ translate('Status') }}:</strong> {{ ucfirst($voucher->status) }}</p>
        <p><strong>{{ translate('Created By') }}:</strong> {{ $voucher->creator->name ?? '-' }}</p>
        @if($voucher->approved_by)
        <p><strong>{{ translate('Approved By') }}:</strong> {{ $voucher->approver->name ?? '-' }} at {{ $voucher->approved_at }}</p>
        @endif
    </div>
    
    <table class="table table-bordered">
        <thead class="bg-light">
            <tr>
                <th>{{ translate('Account Code') }}</th>
                <th>{{ translate('Account Name') }}</th>
                <th>{{ translate('Debit') }}</th>
                <th>{{ translate('Credit') }}</th>
                <th>{{ translate('Narration') }}</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalDebit = 0;
                $totalCredit = 0;
            @endphp
            @foreach($voucher->details as $detail)
            @php
                if($detail->dc == 'debit') {
                    $totalDebit += $detail->amount;
                } else {
                    $totalCredit += $detail->amount;
                }
            @endphp
            <tr>
                <td>{{ $detail->account->code ?? '-' }}</td>
                <td>{{ $detail->account->name ?? '-' }}</td>
                <td>@if($detail->dc == 'debit') {{ number_format($detail->amount, 2) }} @else - @endif</td>
                <td>@if($detail->dc == 'credit') {{ number_format($detail->amount, 2) }} @else - @endif</td>
                <td>{{ $detail->narration ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot class="bg-light">
            <tr>
                <td colspan="2" class="text-right font-weight-bold">{{ translate('Total') }}</td>
                <td class="font-weight-bold">{{ number_format($totalDebit, 2) }} ৳</td>
                <td class="font-weight-bold">{{ number_format($totalCredit, 2) }} ৳</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<style>
@media print {
    .modal-header, .modal-footer, .close {
        display: none;
    }
    .voucher-print-container {
        padding: 0;
    }
}
</style>