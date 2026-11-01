<div class="row">
    <div class="col-md-12 mb-3">
        <div class="card data-table-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="las la-exchange-alt text-primary mr-2"></i>{{ translate('Bank Operations') }}</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    <strong>{{ translate('These bank actions have been retired.') }}</strong>
                    {{ translate('Deposits, withdrawals and transfers are made on the Balance Transfers screen. Existing bank transactions below are kept for reference.') }}
                    <a class="btn btn-primary btn-sm ml-2" href="{{ route('accounts.balance-transfers.create') }}">{{ translate('Go to Balance Transfers') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-12">
        <div class="card data-table-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="las la-history"></i> {{ translate('Recent Transactions') }}</h5>
            </div>
            <div class="card-body p-0">
                <table class="table aiz-table mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>{{ translate('Date') }}</th>
                            <th>{{ translate('Transaction No') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('From Account') }}</th>
                            <th>{{ translate('To Account') }}</th>
                            <th>{{ translate('Amount') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th width="80" class="text-right">{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bankTransactions ?? [] as $transaction)
                        <tr>
                            <td>{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                            <td><strong>{{ $transaction->transaction_no }}</strong></td>
                            <td>{{ ucfirst($transaction->type) }}</td>
                            <td>{{ $transaction->fromAccount->name ?? '-' }}</td>
                            <td>{{ $transaction->toAccount->name ?? '-' }}</td>
                            <td class="{{ $transaction->type == 'deposit' ? 'text-success' : 'text-danger' }}">
                                {{ number_format($transaction->amount, 2) }} ৳
                            </td>
                            <td>
                                @if($transaction->status == 'completed')
                                    <span class="badge-completed">Completed</span>
                                @elseif($transaction->status == 'pending')
                                    <span class="badge-pending">Pending</span>
                                @elseif($transaction->status == 'cleared')
                                    <span class="badge-success">Cleared</span>
                                @else
                                    <span class="badge-danger">{{ ucfirst($transaction->status) }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a class="btn btn-soft-info btn-icon btn-circle btn-sm" href="javascript:void(0)" onclick="viewBankTransaction({{ $transaction->id }})">
                                    <i class="las la-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center">{{ translate('No bank transactions found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
