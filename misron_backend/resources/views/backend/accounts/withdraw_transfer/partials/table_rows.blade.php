<table class="table table-hover mb-0">
    <thead class="bg-light">
        <tr>
            <th width="50">#</th>
            <th>
                <a href="{{ request()->fullUrlWithQuery(['sort' => 'date', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc']) }}">
                    {{ translate('Date') }}
                    @if(request('sort') == 'date')
                        <i class="las la-sort-{{ request('direction') == 'asc' ? 'up' : 'down' }}"></i>
                    @endif
                </a>
            </th>
            <th>
                <a href="{{ request()->fullUrlWithQuery(['sort' => 'voucher_no', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc']) }}">
                    {{ translate('Voucher No') }}
                    @if(request('sort') == 'voucher_no')
                        <i class="las la-sort-{{ request('direction') == 'asc' ? 'up' : 'down' }}"></i>
                    @endif
                </a>
            </th>
            <th>{{ translate('From Account') }}</th>
            <th>{{ translate('To Account') }}</th>
            <th class="text-right">{{ translate('Amount') }}</th>
            <th>{{ translate('Type') }}</th>
            <th>{{ translate('Note') }}</th>
            <th width="120" class="text-right">{{ translate('Action') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($transactions as $index => $transaction)
        <tr>
            <td>{{ $transactions->firstItem() + $index }}</td>
            <td>{{ $transaction->date->format('d-m-Y') }}</td>
            <td><strong class="text-primary">{{ $transaction->voucher_no }}</strong></td>
            <td>{{ $transaction->fromAccount->name ?? '-' }}</td>
            <td>{{ $transaction->toAccount->name ?? '-' }}</td>
            <td class="text-right">
                <strong class="text-success">{{ number_format($transaction->amount, 2) }} ৳</strong>
                @if($transaction->charge > 0)
                    <br><small class="text-muted">+ Charge: {{ number_format($transaction->charge, 2) }}</small>
                @endif
            </td>
            <td>
                @if($transaction->transaction_type == 'withdraw')
                    <span class="badge badge-warning">{{ translate('Withdraw') }}</span>
                @elseif($transaction->transaction_type == 'deposit')
                    <span class="badge badge-success">{{ translate('Deposit') }}</span>
                @elseif($transaction->transaction_type == 'transfer')
                    <span class="badge badge-info">{{ translate('Transfer') }}</span>
                @elseif($transaction->transaction_type == 'cashout')
                    <span class="badge badge-danger">{{ translate('Cash Out') }}</span>
                @endif
            </td>
            <td>{{ Str::limit($transaction->note, 50) }}</td>
            <td class="text-right">
                <div class="dropdown">
                    <button class="btn btn-light btn-sm" data-toggle="dropdown">
                        <i class="las la-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="javascript:void(0)" onclick="viewTransaction({{ $transaction->id }})">
                            <i class="las la-eye"></i> {{ translate('View') }}
                        </a>
                        <a class="dropdown-item" href="{{ route('accounts.balance-transfers.edit', $transaction->id) }}">
                            <i class="las la-edit"></i> {{ translate('Edit') }}
                        </a>
                        <a class="dropdown-item" href="{{ route('accounts.balance-transfers.print', $transaction->id) }}" target="_blank">
                            <i class="las la-print"></i> {{ translate('Print') }}
                        </a>
                        <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteTransaction({{ $transaction->id }})">
                            <i class="las la-trash"></i> {{ translate('Delete') }}
                        </a>
                    </div>
                </div>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="9" class="text-center py-5">
                <i class="las la-exchange-alt la-3x text-muted"></i>
                <p class="mt-2">{{ translate('No transactions found') }}</p>
                <a href="{{ route('accounts.balance-transfers.create') }}" class="btn btn-primary btn-sm">
                    <i class="las la-plus"></i> {{ translate('Create First Transaction') }}
                </a>
            </td>
        </tr>
        @endforelse
    </tbody>
</table>

<div class="card-footer">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <strong>{{ translate('Total Transactions') }}: {{ $transactions->total() }}</strong>
        </div>
        <nav>
            {{ $transactions->appends(request()->all())->links() }}
        </nav>
    </div>
</div>