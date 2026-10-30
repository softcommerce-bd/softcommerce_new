@extends('backend.accounts.layout')

@section('accounts_content')
<div class="simplebar-content" style="padding: 16px;">
    <div class="container-fluid">
        <!-- Header -->
        <div class="w-100 border-bottom d-flex justify-content-between align-items-center px-1 mb-3">
            <p class="title pb-1 mb-0 fw-600">
                <i class="las la-exchange-alt text-primary mr-2"></i>
                {{ translate('Withdraw/Deposit/Transfer/Cash Out List') }}
            </p>
            <a href="{{ route('accounts.balance-transfers.create') }}" class="btn-account-color">
                <i class="las la-plus"></i> {{ translate('New Transaction') }}
            </a>
        </div>

        <!-- Filters -->
        <div class="card mb-3">
            <div class="card-body">
                <form id="filterForm" method="GET" action="{{ route('accounts.balance-transfers.index') }}">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <input type="text" name="from_account" class="form-control" placeholder="{{ translate('From Account') }}" value="{{ request('from_account') }}">
                        </div>
                        <div class="col-md-3 mb-2">
                            <input type="text" name="to_account" class="form-control" placeholder="{{ translate('To Account') }}" value="{{ request('to_account') }}">
                        </div>
                        <div class="col-md-3 mb-2">
                            <input type="text" name="voucher_no" class="form-control" placeholder="{{ translate('Voucher No') }}" value="{{ request('voucher_no') }}">
                        </div>
                        <div class="col-md-3 mb-2">
                            <select name="transaction_type" class="form-control">
                                <option value="">{{ translate('All Types') }}</option>
                                <option value="withdraw" {{ request('transaction_type') == 'withdraw' ? 'selected' : '' }}>{{ translate('Withdraw') }}</option>
                                <option value="deposit" {{ request('transaction_type') == 'deposit' ? 'selected' : '' }}>{{ translate('Deposit') }}</option>
                                <option value="transfer" {{ request('transaction_type') == 'transfer' ? 'selected' : '' }}>{{ translate('Transfer') }}</option>
                                <option value="cashout" {{ request('transaction_type') == 'cashout' ? 'selected' : '' }}>{{ translate('Cash Out') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                        </div>
                        <div class="col-md-3 mb-2">
                            <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                        </div>
                        <div class="col-md-6 mb-2 text-right">
                            <button type="submit" class="btn-account-color">
                                <i class="las la-search"></i> {{ translate('Search') }}
                            </button>
                            <a href="{{ route('accounts.balance-transfers.index') }}" class="btn btn-secondary">
                                <i class="las la-redo"></i> {{ translate('Reset') }}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card">
            <div class="table-responsive">
                @include('backend.accounts.withdraw_transfer.partials.table_rows')
            </div>
        </div>
    </div>
</div>

<!-- View Transaction Modal -->
<div class="modal fade" id="viewTransactionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">{{ translate('Transaction Details') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="view_transaction_content">
                <!-- Dynamic content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                <button type="button" class="btn-account-color" onclick="window.print()">{{ translate('Print') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
function deleteTransaction(id) {
    if (confirm('{{ translate("Are you sure? This action cannot be undone.") }}')) {
        $.ajax({
            url: '{{ route("accounts.balance-transfers.delete", "") }}/' + id,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    AIZ.plugins.notify('danger', response.message || 'Error');
                }
            },
            error: function() {
                AIZ.plugins.notify('danger', 'Something went wrong');
            }
        });
    }
}

function viewTransaction(id) {
    $.ajax({
        url: '{{ route("accounts.balance-transfers.show", "") }}/' + id,
        type: 'GET',
        success: function(response) {
            // You can return HTML or JSON; we'll assume a view is returned
            $('#view_transaction_content').html(response);
            $('#viewTransactionModal').modal('show');
        }
    });
}
</script>
@endsection