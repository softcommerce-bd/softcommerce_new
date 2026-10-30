@forelse($incomes as $index => $income)
<tr>
    <td>{{ $incomes->firstItem() + $index }}</td>
    <td>{{ $income->date->format('d-m-Y') }}</td>
    <td><strong class="text-primary">{{ $income->voucher_no }}</strong></td>
    <td>
        @foreach($income->details as $detail)
            <span class="badge badge-info mb-1 d-inline-block">{{ $detail->incomeAccount->name ?? '-' }}</span>
        @endforeach
    </td>
    <td>{{ $income->receivedAccount->name ?? '-' }}</td>
    <td class="text-right"><strong class="text-success">{{ number_format($income->total_amount, 2) }}</strong></td>
    <td>{{ Str::limit($income->note, 50) }}</td>
    <td class="text-right">
        <button class="btn btn-sm btn-outline-secondary border-0" title="Print" onclick="printIncome({{ $income->id }})">
            <i class="las la-print"></i>
        </button>
        <a href="{{ route('accounts.incomes.edit', $income->id) }}" class="btn btn-sm btn-outline-primary border-0" title="Edit">
            <i class="las la-edit"></i>
        </a>
        <button class="btn btn-sm btn-outline-danger border-0" title="Delete" onclick="deleteIncome({{ $income->id }})">
            <i class="las la-trash"></i>
        </button>
    </td>
</tr>
@empty
<tr>
    <td colspan="8" class="text-center py-5">
        <i class="las la-chart-line la-3x text-muted"></i>
        <p>{{ translate('No incomes found') }}</p>
    </td>
</tr>
@endforelse