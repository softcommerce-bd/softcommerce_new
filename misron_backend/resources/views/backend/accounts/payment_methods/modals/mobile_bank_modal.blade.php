<!-- Mobile Bank Account Modal -->
<div class="modal fade" id="accountModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="accountModalLabel">
                    <i class="las la-mobile-alt text-primary mr-2"></i>
                    {{ translate('Add Mobile Bank Account') }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="accountForm" method="POST">
                @csrf
                <input type="hidden" name="account_id" id="account_id">
                <input type="hidden" name="type" value="asset">
                <input type="hidden" name="sub_type" value="mobile_bank">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ translate('Account Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="account_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>{{ translate('Mobile Number') }} <span class="text-danger">*</span></label>
                        <input type="text" name="mobile_number" id="mobile_number" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>{{ translate('Opening Balance') }}</label>
                        <input type="number" step="0.01" name="opening_balance" id="opening_balance" class="form-control" value="0">
                    </div>

                    <div class="form-group">
                        <label>{{ translate('Cash Out Charge (%)') }}</label>
                        <input type="number" step="0.01" name="cashout_charge" id="cashout_charge" class="form-control" value="0">
                    </div>

                    <div class="form-group">
                        <label>{{ translate('As Of Date') }}</label>
                        <input type="date" name="as_of_date" id="as_of_date" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn-account-color">{{ translate('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openAccountModal(subType, title) {
        $('#accountForm')[0].reset();
        $('#account_id').val('');
        $('#sub_type').val(subType);
        $('#opening_balance').val(0);
        $('#as_of_date').val(new Date().toISOString().slice(0,10));
        $('#accountModalLabel').text('{{ translate('Add') }} ' + title + ' {{ translate('Account') }}');
        $('#accountModal').modal('show');
    }

    function editAccount(id) {
        $.ajax({
            url: '/admin/accounts/chart-of-accounts/edit/' + id,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    var account = response.data;
                    $('#account_id').val(account.id);
                    $('#account_name').val(account.name);
                    $('#mobile_number').val(account.mobile_number);
                    $('#opening_balance').val(account.opening_balance);
                    $('#cashout_charge').val(account.cashout_charge);
                    $('#as_of_date').val(account.created_at ? account.created_at.slice(0,10) : new Date().toISOString().slice(0,10));
                    $('#accountModalLabel').text('{{ translate('Edit') }} ' + account.name);
                    $('#accountModal').modal('show');
                }
            },
            error: function() {
                showNotification('danger', '{{ translate('Error loading account data') }}');
            }
        });
    }

    $('#accountForm').on('submit', function(e) {
        e.preventDefault();
        
        var id = $('#account_id').val();
        var url = id ? '/admin/accounts/chart-of-accounts/update/' + id : '/admin/accounts/chart-of-accounts/store';
        var formData = $(this).serialize();

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#accountModal').modal('hide');
                    location.reload();
                    showNotification('success', response.message);
                }
            },
            error: function(xhr) {
                if (xhr.status === 422 && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    for (var field in errors) {
                        showNotification('danger', errors[field][0]);
                    }
                } else {
                    showNotification('danger', '{{ translate('Error saving account') }}');
                }
            }
        });
    });
</script>