<!-- backend/accounts/modals/account_modal.blade.php -->
<div class="modal fade" id="accountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="accountModalLabel">{{ translate('Create New Account') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="accountModalForm" method="POST">
                @csrf
                <input type="hidden" id="modal_account_id" name="account_id">
                <input type="hidden" id="modal_account_type" name="type" value="asset">
                <input type="hidden" id="modal_account_sub_type" name="sub_type">
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ translate('Account Name') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_account_name" name="name" placeholder="{{ translate('Account Name') }}" required>
                        <div class="invalid-feedback" id="name_error"></div>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="is_sub_account">
                            <label class="custom-control-label" for="is_sub_account">{{ translate('Is Sub-Account?') }}</label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="parent_account_group" style="display: none;">
                        <label>{{ translate('Enter Parent Account') }}</label>
                        <select class="form-control" id="modal_parent_id" name="parent_id">
                            <option value="">{{ translate('Select Parent Account') }}</option>
                        </select>
                    </div>
                    
                    <!-- Bank Fields -->
                    <div class="bank-fields" style="display: none;">
                        <div class="form-group">
                            <label>{{ translate('Bank Name') }}</label>
                            <input type="text" class="form-control" id="modal_bank_name" name="bank_name" placeholder="{{ translate('Bank Name') }}">
                        </div>
                        <div class="form-group">
                            <label>{{ translate('Account Number') }}</label>
                            <input type="text" class="form-control" id="modal_account_number" name="account_number" placeholder="{{ translate('Account Number') }}">
                        </div>
                    </div>
                    
                    
                    {{-- <div class="mobile-fields" style="display: none;">
                        <div class="form-group">
                            <label>{{ translate('Mobile Number') }}</label>
                            <input type="text" class="form-control" id="modal_mobile_number" name="mobile_number" placeholder="{{ translate('Mobile Number') }}">
                        </div>
                    </div> --}}
                    <!-- Mobile Bank Fields -->
                    <div class="mobile-fields" style="display: none;">
                        <div class="form-group">
                            <label>{{ translate('Mobile Number') }}</label>
                            <input type="text" class="form-control" id="modal_mobile_number" name="mobile_number" placeholder="{{ translate('Mobile Number') }}">
                        </div>
                        <div class="form-group">
                            <label>{{ translate('Cashout Charge (%)') }}</label>
                            <input type="number" step="0.01" class="form-control" id="modal_cashout_charge" name="cashout_charge" placeholder="{{ translate('Cashout Charge Percentage') }}" value="0">
                            <small class="form-text text-muted">{{ translate('Enter cashout charge percentage (e.g., 1.85 for 1.85%)') }}</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('Opening Balance') }}</label>
                        <input type="number" step="0.01" class="form-control" id="modal_opening_balance" name="opening_balance" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('As of Date') }}</label>
                        <input type="date" class="form-control" id="modal_as_of_date" name="as_of_date" value="{{ date('Y-m-d') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn-account-color">{{ translate('Save Account') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle Is Sub-Account checkbox change
    // $('#is_sub_account').on('change', function() {
    //     if ($(this).is(':checked')) {
    //         var subType = $('#modal_account_sub_type').val();
    //         var currentId = $('#modal_account_id').val();
    //         loadParentAccounts(subType, currentId);
    //         $('#parent_account_group').show();
    //         $('#modal_parent_id').prop('required', true);
    //     } else {
    //         $('#parent_account_group').hide();
    //         $('#modal_parent_id').prop('required', false);
    //         $('#modal_parent_id').val('');
    //     }
    // });
    // Handle Is Sub-Account checkbox change
$('#is_sub_account').on('change', function() {
    if ($(this).is(':checked')) {
        var subType = $('#modal_account_sub_type').val();
        var currentId = $('#modal_account_id').val();
        var selectedParentId = $('#modal_parent_id').val();
        
        // If we're editing and already have a parent, load with callback to preserve selection
        if (currentId && selectedParentId) {
            loadParentAccountsWithCallback(subType, currentId, selectedParentId);
        } else {
            loadParentAccounts(subType, currentId);
        }
        $('#parent_account_group').show();
        $('#modal_parent_id').prop('required', true);
    } else {
        $('#parent_account_group').hide();
        $('#modal_parent_id').prop('required', false);
        $('#modal_parent_id').val('');
    }
});
    
    // Handle form submission
    $('#accountModalForm').on('submit', function(e) {
        e.preventDefault();
        
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').html('');
        
        var accountId = $('#modal_account_id').val();
        var formData = {
            name: $('#modal_account_name').val(),
            type: $('#modal_account_type').val(),
            sub_type: $('#modal_account_sub_type').val(),
            parent_id: $('#is_sub_account').is(':checked') ? $('#modal_parent_id').val() : null,
            opening_balance: $('#modal_opening_balance').val(),
            as_of_date: $('#modal_as_of_date').val(),
            _token: '{{ csrf_token() }}'
        };
        
        // Add bank fields if needed
        if ($('#modal_account_sub_type').val() === 'bank') {
            formData.bank_name = $('#modal_bank_name').val();
            formData.account_number = $('#modal_account_number').val();
        }
        
        // Add mobile number if needed
        if ($('#modal_account_sub_type').val() === 'mobile_bank') {
            formData.mobile_number = $('#modal_mobile_number').val();
             formData.cashout_charge = $('#modal_cashout_charge').val();
        }
        
        var url = accountId 
            ? '{{ route("accounts.chart-of-accounts.update", ":id") }}'.replace(':id', accountId)
            : '{{ route("accounts.chart-of-accounts.store") }}';
        
        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#accountModal').modal('hide');
                    if (typeof showNotification === 'function') {
                        showNotification('success', response.message);
                    } else {
                        alert(response.message);
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                }
            },
            error: function(xhr) {
                if (xhr.status === 422 && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    for (var field in errors) {
                        if (field === 'name') {
                            $('#modal_account_name').addClass('is-invalid');
                            $('#name_error').html(errors[field][0]);
                        }
                    }
                    if (typeof showNotification === 'function') {
                        showNotification('danger', 'Please fix the validation errors');
                    }
                } else {
                    var errorMsg = xhr.responseJSON?.message || 'Error saving account';
                    if (typeof showNotification === 'function') {
                        showNotification('danger', errorMsg);
                    } else {
                        alert(errorMsg);
                    }
                }
            }
        });
    });
});


// Function to load parent accounts
// function loadParentAccounts(subType, currentId) {
//     var url = '{{ route("accounts.chart-of-accounts.parents") }}?sub_type=' + subType;
//     if (currentId) {
//         url += '&current_id=' + currentId;
//     }
    
//     console.log('Loading parent accounts from URL:', url);
    
//     $.ajax({
//         url: url,
//         type: 'GET',
//         success: function(response) {
//             console.log('Parent accounts response:', response);
            
//             if (response.success) {
//                 var select = $('#modal_parent_id');
//                 select.empty();
//                 select.append('<option value="">{{ translate("Select Parent Account") }}</option>');
                
//                 if (response.accounts && response.accounts.length > 0) {
//                     $.each(response.accounts, function(index, account) {
//                         select.append('<option value="' + account.id + '">' + account.name + '</option>');
//                     });
//                     console.log('Added ' + response.accounts.length + ' parent options');
//                 } else {
//                     select.append('<option value="" disabled>{{ translate("No parent accounts available") }}</option>');
//                     console.log('No parent accounts found');
//                 }
//             }
//         },
//         error: function(xhr) {
//             console.error('Error loading parent accounts:', xhr);
//             var select = $('#modal_parent_id');
//             select.empty();
//             select.append('<option value="">{{ translate("Error loading parent accounts") }}</option>');
//         }
//     });
// }

// function loadParentAccounts(subType, currentId) {
//     var url = '{{ route("accounts.chart-of-accounts.parents") }}?sub_type=' + subType;
//     if (currentId) {
//         url += '&current_id=' + currentId;
//     }
    
//     $.ajax({
//         url: url,
//         type: 'GET',
//         success: function(response) {
//             if (response.success) {
//                 var select = $('#modal_parent_id');
//                 select.empty();
//                 select.append('<option value="">{{ translate("Select Parent Account") }}</option>');
                
//                 if (response.accounts && response.accounts.length > 0) {
//                     $.each(response.accounts, function(index, account) {
//                         select.append('<option value="' + account.id + '">' + account.name + '</option>');
//                     });
//                 } else {
//                     select.append('<option value="" disabled>{{ translate("No parent accounts available") }}</option>');
//                 }
//             }
//         },
//         error: function(xhr) {
//             console.error('Error loading parent accounts:', xhr);
//             var select = $('#modal_parent_id');
//             select.empty();
//             select.append('<option value="">{{ translate("Error loading parent accounts") }}</option>');
//         }
//     });
// }
// Function for create mode (no callback needed)
function loadParentAccounts(subType, currentId) {
    var url = '{{ route("accounts.chart-of-accounts.parents") }}?sub_type=' + subType;
    if (currentId) {
        url += '&current_id=' + currentId;
    }
    
    $.ajax({
        url: url,
        type: 'GET',
        success: function(response) {
            if (response.success) {
                var select = $('#modal_parent_id');
                select.empty();
                select.append('<option value="">{{ translate("Select Parent Account") }}</option>');
                
                if (response.accounts && response.accounts.length > 0) {
                    $.each(response.accounts, function(index, account) {
                        select.append('<option value="' + account.id + '">' + account.name + '</option>');
                    });
                } else {
                    select.append('<option value="" disabled>{{ translate("No parent accounts available") }}</option>');
                }
            }
        },
        error: function(xhr) {
            console.error('Error loading parent accounts:', xhr);
            var select = $('#modal_parent_id');
            select.empty();
            select.append('<option value="">{{ translate("Error loading parent accounts") }}</option>');
        }
    });
}


// Open modal for creating new account
// function openAccountModal(subType, sectionName) {
//     $('#accountModalForm')[0].reset();
//     $('#modal_account_id').val('');
//     $('#modal_account_sub_type').val(subType);
//     $('#modal_account_type').val('asset');
//     $('#accountModalLabel').text('{{ translate("Create New Account") }} - ' + sectionName);
//     $('#modal_opening_balance').val(0);
//     $('#modal_as_of_date').val(new Date().toISOString().slice(0,10));
    
//     // IMPORTANT: Always unchecked for new account
//     $('#is_sub_account').prop('checked', false);
//     $('#parent_account_group').hide();
//     $('#modal_parent_id').val('');
//     $('#modal_parent_id').prop('required', false);
    
//     $('.is-invalid').removeClass('is-invalid');
//     $('.invalid-feedback').html('');
    
//     // Show/hide fields based on sub_type
//     if (subType === 'bank') {
//         $('.bank-fields').show();
//         $('.mobile-fields').hide();
//         $('#modal_bank_name').val('');
//         $('#modal_account_number').val('');
//     } else if (subType === 'mobile_bank') {
//         $('.bank-fields').hide();
//         $('.mobile-fields').show();
//         $('#modal_mobile_number').val('');
//        $('#modal_cashout_charge').val(0);
//     } else {
//         $('.bank-fields').hide();
//         $('.mobile-fields').hide();
//     }
    
//     $('#accountModal').modal('show');
// }
// ONE FUNCTION FOR ALL ACCOUNT TYPES - Asset, Liability, Income, Expense
function openAccountModal(subType, sectionName) {
    // Reset form
    $('#accountModalForm')[0].reset();
    $('#modal_account_id').val('');
    $('#modal_account_sub_type').val(subType);
    
    // Determine account type based on sub_type
    var accountType = 'asset'; // default
    
    // LIABILITY accounts (Payable, Tax, Equity)
    if (subType === 'payable' || subType === 'tax' || subType === 'equity' || subType === 'equity_account') {
        accountType = 'liability';
    } 
    // INCOME accounts (Sales, Income, Revenue)
    else if (subType === 'sales' || subType === 'income' || subType === 'revenue') {
        accountType = 'income';
    } 
    // EXPENSE accounts
    else if (subType === 'expense' || subType === 'expense_category') {
        accountType = 'expense';
    } 
    // ASSET accounts (cash, bank, mobile_bank, receivable, inventory)
    else {
        accountType = 'asset';
    }
    
    $('#modal_account_type').val(accountType);
    $('#accountModalLabel').text('{{ translate("Create New Account") }} - ' + sectionName);
    $('#modal_opening_balance').val(0);
    $('#modal_as_of_date').val(new Date().toISOString().slice(0,10));
    
    // Always unchecked for new account
    $('#is_sub_account').prop('checked', false);
    $('#parent_account_group').hide();
    $('#modal_parent_id').val('');
    $('#modal_parent_id').prop('required', false);
    
    // Clear validation errors
    $('.is-invalid').removeClass('is-invalid');
    $('.invalid-feedback').html('');
    
    // Show/hide specific fields based on sub_type (only for Asset types)
    if (subType === 'bank') {
        $('.bank-fields').show();
        $('.mobile-fields').hide();
        $('#modal_bank_name').val('');
        $('#modal_account_number').val('');
    } else if (subType === 'mobile_bank') {
        $('.bank-fields').hide();
        $('.mobile-fields').show();
        $('#modal_mobile_number').val('');
        $('#modal_cashout_charge').val(0);
    } else {
        $('.bank-fields').hide();
        $('.mobile-fields').hide();
    }
    
    // Debug log
    console.log('Opening modal:', sectionName, '| subType:', subType, '| accountType:', accountType);
    
    $('#accountModal').modal('show');
}

// Edit account function
// function editAccount(id) {
//     var url = '{{ route("accounts.chart-of-accounts.edit", ":id") }}'.replace(':id', id);
    
//     $.ajax({
//         url: url,
//         type: 'GET',
//         success: function(response) {
//             if (response.success) {
//                 var account = response.data;
                
//                 $('#modal_account_id').val(account.id);
//                 $('#modal_account_name').val(account.name);
//                 $('#modal_account_type').val(account.type);
//                 $('#modal_account_sub_type').val(account.sub_type);
//                 $('#modal_opening_balance').val(account.opening_balance);
//                 $('#accountModalLabel').text('{{ translate("Edit Account") }} - ' + account.name);
                
//                 // Show/hide fields based on sub_type
//                 if (account.sub_type === 'bank') {
//                     $('.bank-fields').show();
//                     $('.mobile-fields').hide();
//                     $('#modal_bank_name').val(account.bank_name);
//                     $('#modal_account_number').val(account.account_number);
//                 } else if (account.sub_type === 'mobile_bank') {
//                     $('.bank-fields').hide();
//                     $('.mobile-fields').show();
//                     $('#modal_mobile_number').val(account.mobile_number);
//                 } else {
//                     $('.bank-fields').hide();
//                     $('.mobile-fields').hide();
//                 }
                
//                 // Check if this is a sub-account (has parent_id)
//                 if (account.parent_id && account.parent_id !== null && account.parent_id != 0) {
//                     $('#is_sub_account').prop('checked', true);
//                     $('#parent_account_group').show();
//                     $('#modal_parent_id').prop('required', true);
                    
//                     // Load parents and then set the value
//                     loadParentAccounts(account.sub_type, account.id);
//                     setTimeout(function() {
//                         $('#modal_parent_id').val(account.parent_id);
//                     }, 500);
//                 } else {
//                     $('#is_sub_account').prop('checked', false);
//                     $('#parent_account_group').hide();
//                     $('#modal_parent_id').prop('required', false);
//                     $('#modal_parent_id').val('');
//                 }
                
//                 $('#accountModal').modal('show');
//             }
//         },
//         error: function(xhr) {
//             console.error('Error loading account:', xhr);
//             if (typeof showNotification === 'function') {
//                 showNotification('danger', 'Error loading account data');
//             }
//         }
//     });
// }
// Edit account function
// Edit account function
// function editAccount(id) {
//     var url = '{{ route("accounts.chart-of-accounts.edit", ":id") }}'.replace(':id', id);
    
//     $.ajax({
//         url: url,
//         type: 'GET',
//         success: function(response) {
//             if (response.success) {
//                 var account = response.data;
                
//                 console.log('Editing account:', account);
                
//                 $('#modal_account_id').val(account.id);
//                 $('#modal_account_name').val(account.name);
//                 $('#modal_account_type').val(account.type);
//                 $('#modal_account_sub_type').val(account.sub_type);
//                 $('#modal_opening_balance').val(account.opening_balance);
//                 $('#accountModalLabel').text('{{ translate("Edit Account") }} - ' + account.name);
                
//                 // Show/hide fields based on sub_type
//                 if (account.sub_type === 'bank') {
//                     $('.bank-fields').show();
//                     $('.mobile-fields').hide();
//                     $('#modal_bank_name').val(account.bank_name || '');
//                     $('#modal_account_number').val(account.account_number || '');
//                 } else if (account.sub_type === 'mobile_bank') {
//                     $('.bank-fields').hide();
//                     $('.mobile-fields').show();
//                     $('#modal_mobile_number').val(account.mobile_number || '');
//                     $('#modal_cashout_charge').val(account.cashout_charge || 0);
//                 } else {
//                     $('.bank-fields').hide();
//                     $('.mobile-fields').hide();
//                 }
                
//                 // IMPORTANT: Check if account has a parent
//                 var hasParent = (account.parent_id !== null && account.parent_id !== undefined && account.parent_id != 0);
                
//                 console.log('Has parent:', hasParent, 'Parent ID:', account.parent_id);
                
//                 if (hasParent) {
//                     // This is a sub-account - check the box
//                     $('#is_sub_account').prop('checked', true);
//                     $('#parent_account_group').show();
//                     $('#modal_parent_id').prop('required', true);
                    
//                     // Load parent accounts first, then set the value
//                     loadParentAccounts(account.sub_type, account.id);
                    
//                     // Wait for parents to load, then set the selected value
//                     setTimeout(function() {
//                         console.log('Setting parent dropdown to value:', account.parent_id);
//                         $('#modal_parent_id').val(account.parent_id);
                        
//                         // Verify if it was set correctly
//                         if ($('#modal_parent_id').val() != account.parent_id) {
//                             console.log('Failed to set parent_id, trying again...');
//                             $('#modal_parent_id').val(account.parent_id);
//                         }
//                     }, 800);
//                 } else {
//                     // No parent - uncheck the box
//                     $('#is_sub_account').prop('checked', false);
//                     $('#parent_account_group').hide();
//                     $('#modal_parent_id').prop('required', false);
//                     $('#modal_parent_id').val('');
//                 }
                
//                 $('#accountModal').modal('show');
//             }
//         },
//         error: function(xhr) {
//             console.error('Error loading account:', xhr);
//             if (typeof showNotification === 'function') {
//                 showNotification('danger', 'Error loading account data');
//             }
//         }
//     });
// }

function editAccount(id) {
    var url = '{{ route("accounts.chart-of-accounts.edit", ":id") }}'.replace(':id', id);
    
    $.ajax({
        url: url,
        type: 'GET',
        success: function(response) {
            if (response.success) {
                var account = response.data;
                
                console.log('Editing account:', account);
                
                // Set basic account info
                $('#modal_account_id').val(account.id);
                $('#modal_account_name').val(account.name);
                $('#modal_account_type').val(account.type);
                $('#modal_account_sub_type').val(account.sub_type);
                $('#modal_opening_balance').val(account.opening_balance);
                $('#accountModalLabel').text('{{ translate("Edit Account") }} - ' + account.name);
                
                // Show/hide fields based on sub_type
                if (account.sub_type === 'bank') {
                    $('.bank-fields').show();
                    $('.mobile-fields').hide();
                    $('#modal_bank_name').val(account.bank_name || '');
                    $('#modal_account_number').val(account.account_number || '');
                } else if (account.sub_type === 'mobile_bank') {
                    $('.bank-fields').hide();
                    $('.mobile-fields').show();
                    $('#modal_mobile_number').val(account.mobile_number || '');
                    $('#modal_cashout_charge').val(account.cashout_charge || 0);
                } else {
                    $('.bank-fields').hide();
                    $('.mobile-fields').hide();
                }
                
                // IMPORTANT: Check if account has a parent
                var hasParent = (account.parent_id !== null && account.parent_id !== undefined && account.parent_id != 0);
                
                console.log('Has parent:', hasParent, 'Parent ID:', account.parent_id);
                
                // FIRST, show the modal
                $('#accountModal').modal('show');
                
                if (hasParent) {
                    // This is a sub-account - check the box and show parent group
                    $('#is_sub_account').prop('checked', true);
                    $('#parent_account_group').show();
                    $('#modal_parent_id').prop('required', true);
                    
                    // Load parent accounts and set the selected value after they load
                    loadParentAccountsWithCallback(account.sub_type, account.id, account.parent_id);
                } else {
                    // No parent - uncheck the box
                    $('#is_sub_account').prop('checked', false);
                    $('#parent_account_group').hide();
                    $('#modal_parent_id').prop('required', false);
                    $('#modal_parent_id').val('');
                }
            }
        },
        error: function(xhr) {
            console.error('Error loading account:', xhr);
            if (typeof showNotification === 'function') {
                showNotification('danger', 'Error loading account data');
            }
        }
    });
}

// New function with callback to set parent value after loading
function loadParentAccountsWithCallback(subType, currentId, selectedParentId) {
    var url = '{{ route("accounts.chart-of-accounts.parents") }}?sub_type=' + subType;
    if (currentId) {
        url += '&current_id=' + currentId;
    }
    
    console.log('Loading parents with callback, selectedParentId:', selectedParentId);
    
    $.ajax({
        url: url,
        type: 'GET',
        success: function(response) {
            if (response.success) {
                var select = $('#modal_parent_id');
                select.empty();
                select.append('<option value="">{{ translate("Select Parent Account") }}</option>');
                
                if (response.accounts && response.accounts.length > 0) {
                    $.each(response.accounts, function(index, account) {
                        var selected = (account.id == selectedParentId) ? 'selected' : '';
                        select.append('<option value="' + account.id + '" ' + selected + '>' + account.name + '</option>');
                    });
                    console.log('Added ' + response.accounts.length + ' parent options, selected: ' + selectedParentId);
                } else {
                    select.append('<option value="" disabled>{{ translate("No parent accounts available") }}</option>');
                }
            }
        },
        error: function(xhr) {
            console.error('Error loading parent accounts:', xhr);
            var select = $('#modal_parent_id');
            select.empty();
            select.append('<option value="">{{ translate("Error loading parent accounts") }}</option>');
        }
    });
}

function viewStatement(accountId) {
    var url = '{{ route("accounts.statement", ":id") }}'.replace(':id', accountId);
    window.location.href = url;
}

function deleteAccount(id) {
    if (confirm('{{ translate("Are you sure? This action cannot be undone.") }}')) {
        var url = '{{ route("accounts.chart-of-accounts.delete", ":id") }}'.replace(':id', id);
        
        $.ajax({
            url: url,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    if (typeof showNotification === 'function') {
                        showNotification('success', response.message);
                    } else {
                        alert(response.message);
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                }
            },
            error: function(xhr) {
                var errorMsg = xhr.responseJSON?.message || 'Error deleting account';
                if (typeof showNotification === 'function') {
                    showNotification('danger', errorMsg);
                }
            }
        });
    }
}
</script>