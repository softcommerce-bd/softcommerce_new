<!-- payment_method_modal.blade.php -->
<div class="modal fade" id="paymentMethodModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title" id="paymentMethodModalLabel">
                    <i class="las la-credit-card text-primary mr-2"></i>
                    {{ translate('Create New Payment Type') }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="paymentMethodForm" method="POST">
                @csrf
                <input type="hidden" name="payment_method_id" id="payment_method_id">
                <input type="hidden" name="deposit_type" id="deposit_type" value="auto">

                <div class="modal-body">
                    <!-- Payment Type -->
                    <div class="form-group">
                        <label class="font-weight-bold">{{ translate('Payment Type') }} <span class="text-danger">*</span></label>
                        <select name="type" id="payment_type" class="form-control" required>
                            <option value="card">{{ translate('Card') }}</option>
                            <option value="mobile">{{ translate('Mobile Banking') }}</option>
                        </select>
                        <small class="text-muted">Select payment method type</small>
                    </div>

                    <!-- Name -->
                    <div class="form-group">
                        <label class="font-weight-bold">{{ translate('Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="method_name" class="form-control" 
                               placeholder="e.g., Visa Card, bKash, Nagad" required>
                        <small class="text-muted">Enter payment method name</small>
                    </div>

                    <!-- Auto Deposit to Account (Like Dizi Cashier) -->
                    <div class="form-group" id="auto_deposit_group">
                        <label class="font-weight-bold">{{ translate('Auto Deposit to Account') }} <span class="text-danger">*</span></label>
                        <select name="bank_account_id" id="bank_account_id" class="form-control select2" required>
                            <option value="">{{ translate('Select Bank Account') }}</option>
                            @foreach ($bankAccounts ?? [] as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Select bank account for auto deposit</small>
                    </div>

                    <!-- Hidden Source Account (Mobile Bank) -->
                    <input type="hidden" name="source_account_id" id="source_account_id">

                    <!-- Charge with Percentage (Like Dizi Cashier) -->
                    <div class="form-group">
                        <label class="font-weight-bold">{{ translate('Charge') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="charge" id="method_charge" 
                                   class="form-control text-right" value="0" required>
                            <div class="input-group-append">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <small class="text-muted">Enter charge percentage (if any)</small>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label class="font-weight-bold">{{ translate('Description') }}</label>
                        <textarea name="description" id="method_description" class="form-control" 
                                  rows="3" placeholder="Optional description"></textarea>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label class="font-weight-bold">{{ translate('Status') }}</label>
                        <select name="status" id="method_status" class="form-control" required>
                            <option value="active">{{ translate('Active') }}</option>
                            <option value="inactive">{{ translate('Inactive') }}</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn text-white" style="background-color: #20b2aa;">
                        <i class="las la-save"></i> {{ translate('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize select2
    $('#bank_account_id').select2({
        dropdownParent: $('#paymentMethodModal'),
        placeholder: 'Select Bank Account',
        width: '100%'
    });

    // Form submission
    $('#paymentMethodForm').on('submit', function(e) {
        e.preventDefault();
        
        var id = $('#payment_method_id').val();
        var url = id ? '/transaction-method/update/' + id : '/transaction-method/store';
        var formData = $(this).serialize();

        // Add source_account_id (first mobile bank account by default)
        $('input[name="source_account_id"]').val($('#source_account_hidden').val());

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#paymentMethodModal').modal('hide');
                    toastr.success(response.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            },
            error: function(xhr) {
                if (xhr.status === 422 && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    for (var field in errors) {
                        toastr.error(errors[field][0]);
                    }
                } else {
                    toastr.error('Error saving payment method');
                }
            }
        });
    });
});

function openPaymentMethodModal(depositType) {
    $('#paymentMethodForm')[0].reset();
    $('#payment_method_id').val('');
    $('#deposit_type').val(depositType);
    $('#payment_type').val('card');
    $('#method_charge').val(0);
    
    if (depositType === 'auto') {
        $('#auto_deposit_group').show();
        $('#bank_account_id').prop('required', true);
        $('#paymentMethodModalLabel').text('Create New Payment Type');
    } else {
        $('#auto_deposit_group').hide();
        $('#bank_account_id').prop('required', false);
        $('#paymentMethodModalLabel').text('Create Manual Payment Type');
    }
    
    $('#paymentMethodModal').modal('show');
}

function editPaymentMethod(id) {
    $.ajax({
        url: '/transaction-method/get/' + id,
        type: 'GET',
        success: function(response) {
            if (response.success) {
                var method = response.data;
                $('#payment_method_id').val(method.id);
                $('#payment_type').val(method.type);
                $('#method_name').val(method.name);
                $('#deposit_type').val(method.deposit_type);
                $('#method_charge').val(method.charge);
                $('#method_description').val(method.description);
                $('#method_status').val(method.status);
                
                if (method.deposit_type === 'auto') {
                    $('#auto_deposit_group').show();
                    $('#bank_account_id').val(method.bank_account_id).trigger('change');
                    $('#source_account_hidden').val(method.source_account_id);
                }
                
                $('#paymentMethodModalLabel').text('Edit Payment Type');
                $('#paymentMethodModal').modal('show');
            }
        },
        error: function() {
            toastr.error('Error loading payment method data');
        }
    });
}

function deletePaymentMethod(id) {
    if (confirm('Are you sure you want to delete this payment method?')) {
        $.ajax({
            url: '/transaction-method/delete/' + id,
            type: 'DELETE',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            },
            error: function() {
                toastr.error('Error deleting payment method');
            }
        });
    }
}
</script>