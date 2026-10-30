{{-- @extends('layouts.app')

@section('title', translate('Transaction Details'))

@section('content') --}}
<div class="simplebar-content" style="padding: 16px;">
    <div class="container-fluid">
        <div class="w-100 border-bottom d-flex justify-content-between align-items-center px-1 mb-3">
            <p class="title pb-1 mb-0 fw-600">
                <i class="las la-receipt text-primary mr-2"></i>
                {{ translate('Transaction Details') }}
            </p>
            <div>
                <a href="{{ route('accounts.balance-transfers.edit', $transaction->id) }}" class="btn btn-primary btn-sm">
                    <i class="las la-edit"></i> {{ translate('Edit') }}
                </a>
                <a href="{{ route('accounts.balance-transfers.index') }}" class="btn btn-secondary btn-sm">
                    <i class="las la-list"></i> {{ translate('Back to List') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="150">{{ translate('Voucher No') }}:</th>
                                <td><strong>{{ $transaction->voucher_no }}</strong></td>
                            </tr>
                            <tr>
                                <th>{{ translate('Date') }}:</th>
                                <td>{{ $transaction->date->format('d-m-Y') }}</td>
                            </tr>
                            <tr>
                                <th>{{ translate('Transaction Type') }}:</th>
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
                             </tr>
                            <tr>
                                <th>{{ translate('From Account') }}:</th>
                                <td>{{ $transaction->fromAccount->name ?? '-' }} ({{ $transaction->fromAccount->account_no ?? '' }})</td>
                             </tr>
                            <tr>
                                <th>{{ translate('To Account') }}:</th>
                                <td>{{ $transaction->toAccount->name ?? '-' }} ({{ $transaction->toAccount->account_no ?? '' }})</td>
                             </tr>
                            </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="150">{{ translate('Amount') }}:</th>
                                <td><strong class="text-success">{{ number_format($transaction->amount, 2) }} ৳</strong></td>
                             </tr>
                            @if($transaction->charge > 0)
                            <tr>
                                <th>{{ translate('Charge') }}:</th>
                                <td>{{ number_format($transaction->charge, 2) }} ৳</td>
                             </tr>
                            <tr>
                                <th>{{ translate('Total Amount') }}:</th>
                                <td><strong>{{ number_format($transaction->amount + $transaction->charge, 2) }} ৳</strong></td>
                             </tr>
                            @endif
                            <tr>
                                <th>{{ translate('Reference No') }}:</th>
                                <td>{{ $transaction->reference_no ?? '-' }}</td>
                             </tr>
                            <tr>
                                <th>{{ translate('Created By') }}:</th>
                                <td>{{ $transaction->createdBy->name ?? '-' }}</td>
                             </tr>
                            <tr>
                                <th>{{ translate('Note') }}:</th>
                                <td>{{ $transaction->note ?? '-' }}</td>
                             </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card-footer text-right">
                <button type="button" class="btn btn-info" onclick="window.print()">
                    <i class="las la-print"></i> {{ translate('Print') }}
                </button>
            </div>
        </div>
    </div>
</div>
{{-- @endsection --}}