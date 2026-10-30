<div class="row">
    <div class="col-md-12 mb-3">
        <div class="card data-table-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="las la-exchange-alt text-primary mr-2"></i>{{ translate('Bank Operations') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-primary w-100 py-3" onclick="openBankModal('deposit')">
                            <i class="las la-download la-2x d-block mb-2"></i>
                            {{ translate('Deposit') }}
                        </button>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-warning w-100 py-3" onclick="openBankModal('withdraw')">
                            <i class="las la-upload la-2x d-block mb-2"></i>
                            {{ translate('Withdraw') }}
                        </button>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-info w-100 py-3" onclick="openBankModal('transfer')">
                            <i class="las la-random la-2x d-block mb-2"></i>
                            {{ translate('Transfer') }}
                        </button>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-secondary w-100 py-3" onclick="openBankModal('cheque')">
                            <i class="las la-file-invoice la-2x d-block mb-2"></i>
                            {{ translate('Cheque Clearing') }}
                        </button>
                    </div>
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

<script>
function openBankModal(type) {
    $('#bank_operation_type').val(type);
    $('#bank_operation_title').text(getBankTitle(type));
    $('#bankForm')[0].reset();
    $('#bank_transaction_date').val(new Date().toISOString().slice(0,10));
    
    if (type === 'transfer') {
        $('#transfer_fields').show();
        $('#single_account_fields').hide();
        $('#cheque_fields').hide();
    } else if (type === 'cheque') {
        $('#transfer_fields').hide();
        $('#single_account_fields').show();
        $('#cheque_fields').show();
        $('#account_label').text('Account');
        loadAccountsForType('both');
    } else {
        $('#transfer_fields').hide();
        $('#single_account_fields').show();
        $('#cheque_fields').hide();
        if (type === 'deposit') {
            $('#account_label').text('Deposit To Account');
            loadAccountsForType('to');
        } else {
            $('#account_label').text('Withdraw From Account');
            loadAccountsForType('from');
        }
    }
    
    $('#bankModal').modal('show');
}

function loadAccountsForType(action) {
    $.ajax({
        url: '{{ route("accounts.bank-transactions.accounts") }}',
        type: 'GET',
        success: function(response) {
            if (response.success) {
                var html = '<select class="form-control" id="bank_account_id" name="account_id" required>';
                html += '<option value="">{{ translate("Select Account") }}</option>';
                $.each(response.accounts, function(index, account) {
                    html += '<option value="' + account.id + '">' + account.name + ' (Balance: ' + account.current_balance + ')</option>';
                });
                html += '</select>';
                $('#account_field').html(html);
            }
        }
    });
}

function getBankTitle(type) {
    var titles = {
        'deposit': 'Deposit Money',
        'withdraw': 'Withdraw Money',
        'transfer': 'Transfer Money',
        'cheque': 'Cheque Clearing'
    };
    return titles[type] || 'Bank Operation';
}

$('#bankForm').on('submit', function(e) {
    e.preventDefault();
    var type = $('#bank_operation_type').val();
    var url = '';
    
    switch(type) {
        case 'deposit':
            url = '{{ route("accounts.bank-transactions.deposit") }}';
            break;
        case 'withdraw':
            url = '{{ route("accounts.bank-transactions.withdraw") }}';
            break;
        case 'transfer':
            url = '{{ route("accounts.bank-transactions.transfer") }}';
            break;
        case 'cheque':
            url = '{{ route("accounts.bank-transactions.cheque-clearing") }}';
            break;
    }
    
    $.ajax({
        url: url,
        type: 'POST',
        data: $(this).serialize(),
        success: function(response) {
            if (response.success) {
                $('#bankModal').modal('hide');
                location.reload();
                showNotification('success', response.message);
            }
        },
        error: function(xhr) {
            var message = xhr.responseJSON?.message || 'Error processing transaction';
            showNotification('danger', message);
        }
    });
});
</script>