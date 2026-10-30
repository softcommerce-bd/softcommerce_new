<!-- backend/accounts/modals/bank_modal.blade.php -->
<div class="modal fade" id="bankModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bank_operation_title">{{ translate('Bank Operation') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="bankForm">
                @csrf
                <input type="hidden" id="bank_operation_type" name="type">
                <div class="modal-body">
                    <div id="single_account_fields">
                        <div class="form-group">
                            <label id="account_label">{{ translate('Account') }}</label>
                            <div id="account_field"></div>
                        </div>
                    </div>
                    
                    <div id="transfer_fields" style="display: none;">
                        <div class="form-group">
                            <label>{{ translate('From Account') }}</label>
                            <select class="form-control" id="from_account_id" name="from_account_id">
                                <option value="">{{ translate('Select Account') }}</option>
                                @foreach($bankAccounts ?? [] as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} (Balance: {{ number_format($account->current_balance, 2) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>{{ translate('To Account') }}</label>
                            <select class="form-control" id="to_account_id" name="to_account_id">
                                <option value="">{{ translate('Select Account') }}</option>
                                @foreach($bankAccounts ?? [] as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('Amount') }} <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('Transaction Date') }} <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="{{ date('Y-m-d') }}" required>
                    </div>
                    
                    <div class="form-group" id="cheque_fields" style="display: none;">
                        <label>{{ translate('Cheque No') }}</label>
                        <input type="text" class="form-control" id="cheque_no" name="cheque_no">
                        <label class="mt-2">{{ translate('Cheque Date') }}</label>
                        <input type="date" class="form-control" id="cheque_date" name="cheque_date">
                        <label class="mt-2">{{ translate('Clearing Date') }}</label>
                        <input type="date" class="form-control" id="clearing_date" name="clearing_date">
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('Description') }}</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('Reference') }}</label>
                        <input type="text" class="form-control" id="reference" name="reference">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ translate('Process') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openBankModal(type) {
    $('#bank_operation_type').val(type);
    var title = '';
    switch(type) {
        case 'deposit':
            title = '{{ translate("Deposit Money") }}';
            $('#transfer_fields').hide();
            $('#single_account_fields').show();
            $('#cheque_fields').hide();
            $('#account_label').text('{{ translate("Deposit To Account") }}');
            loadAccounts('to');
            break;
        case 'withdraw':
            title = '{{ translate("Withdraw Money") }}';
            $('#transfer_fields').hide();
            $('#single_account_fields').show();
            $('#cheque_fields').hide();
            $('#account_label').text('{{ translate("Withdraw From Account") }}');
            loadAccounts('from');
            break;
        case 'transfer':
            title = '{{ translate("Transfer Money") }}';
            $('#transfer_fields').show();
            $('#single_account_fields').hide();
            $('#cheque_fields').hide();
            break;
        case 'cheque':
            title = '{{ translate("Cheque Clearing") }}';
            $('#transfer_fields').hide();
            $('#single_account_fields').show();
            $('#cheque_fields').show();
            $('#account_label').text('{{ translate("Deposit To Account") }}');
            loadAccounts('to');
            break;
        default:
            title = '{{ translate("Bank Operation") }}';
    }
    $('#bank_operation_title').text(title);
    $('#bankForm')[0].reset();
    $('#transaction_date').val(new Date().toISOString().slice(0,10));
    $('#bankModal').modal('show');
}

function loadAccounts(type) {
    $.ajax({
        url: '{{ route("accounts.bank-transactions.accounts") }}',
        type: 'GET',
        success: function(response) {
            if (response.success) {
                var html = '<select class="form-control" id="account_id" name="account_id" required>';
                html += '<option value="">{{ translate("Select Account") }}</option>';
                $.each(response.accounts, function(index, account) {
                    html += '<option value="' + account.id + '">' + account.name + ' (Balance: ' + parseFloat(account.current_balance).toFixed(2) + ')</option>';
                });
                html += '</select>';
                $('#account_field').html(html);
            }
        },
        error: function() {
            $('#account_field').html('<select class="form-control" id="account_id" name="account_id"><option value="">{{ translate("Error loading accounts") }}</option></select>');
        }
    });
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
                showNotification('success', response.message);
                setTimeout(function() { location.reload(); }, 1500);
            }
        },
        error: function(xhr) {
            var message = xhr.responseJSON?.message || '{{ translate("Error processing transaction") }}';
            showNotification('danger', message);
        }
    });
});
</script>