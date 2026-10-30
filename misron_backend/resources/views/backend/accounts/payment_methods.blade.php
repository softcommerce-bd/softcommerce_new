<!-- Responsive Wrapper -->
<div class="container-fluid">
    <div class="row">

        <!-- ================= AUTO PAYMENT METHODS ================= -->
        <div class="col-12 mb-4">
            <div class="card shadow-sm border-0">

                <!-- Header -->
                <div class="bg-account-color d-flex flex-column flex-md-row justify-content-between align-items-md-center p-2">
                    <h5 class="text-white mb-2 mb-md-0">
                        <i class="las la-credit-card mr-2"></i>
                        {{ translate('Card & Mobile Banking Payment Method List') }}
                    </h5>

                    <button class="btn btn-light btn-sm"
                        onclick="openPaymentMethodModal('auto')">
                        <i class="las la-plus"></i>
                        {{ translate('Add New') }}
                    </button>
                </div>

                <!-- Desktop Table -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>{{ translate('Method') }}</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Linked Account') }}</th>
                                <th>{{ translate('Charge') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th class="text-center">{{ translate('Action') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            @php
                                $autoMethods = $methods->where('deposit_type', 'auto');
                            @endphp

                            @forelse($autoMethods as $method)
                                <tr>

                                    <!-- Method -->
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="mr-3">
                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                                    style="width:45px;height:45px;">
                                                    <i class="las la-credit-card text-primary"></i>
                                                </div>
                                            </div>

                                            <div>
                                                <strong>{{ $method->name }}</strong>

                                                @if ($method->description)
                                                    <br>
                                                    <small class="text-muted">
                                                        {{ $method->description }}
                                                    </small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Type -->
                                    <td>
                                        <span class="badge badge-info px-3 py-2">
                                            {{ ucfirst($method->type) }}
                                        </span>
                                    </td>

                                    <!-- Bank -->
                                    <td>
                                        @if ($method->bank_account_id)
                                            <span class="text-success">
                                                <i class="las la-university"></i>
                                                {{ $method->bankAccount->name ?? '-' }}
                                            </span>
                                        @else
                                            <span class="text-warning">
                                                <i class="las la-exclamation-triangle"></i>
                                                {{ translate('Bank Required') }}
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Charge -->
                                    <td>
                                        <span class="{{ $method->charge > 0 ? 'text-danger' : 'text-success' }}">
                                            {{ $method->charge }}%
                                        </span>
                                    </td>

                                    <!-- Status -->
                                    <td>
                                        @if ($method->status == 'active')
                                            <span class="badge badge-success">
                                                Active
                                            </span>
                                        @else
                                            <span class="badge badge-danger">
                                                Inactive
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Action -->
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">

                                            <button class="btn btn-soft-primary"
                                                onclick="editPaymentMethod({{ $method->id }})">
                                                <i class="las la-edit"></i>
                                            </button>

                                            <button class="btn btn-soft-danger"
                                                onclick="deletePaymentMethod({{ $method->id }})">
                                                <i class="las la-trash"></i>
                                            </button>

                                        </div>
                                    </td>

                                </tr>
                            @empty

                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="las la-credit-card la-3x text-muted mb-3 d-block"></i>

                                        <p class="text-muted">
                                            {{ translate('No payment methods found') }}
                                        </p>

                                        <button class="btn btn-soft-primary btn-sm"
                                            onclick="openPaymentMethodModal('auto')">

                                            <i class="las la-plus"></i>
                                            {{ translate('Add New') }}

                                        </button>
                                    </td>
                                </tr>

                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card Design -->
                <div class="d-block d-md-none p-2">

                    @forelse($autoMethods as $method)

                        <div class="card border mb-3 shadow-sm">
                            <div class="card-body">

                                <div class="d-flex justify-content-between align-items-start">

                                    <div class="d-flex">

                                        <div class="mr-3">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                                style="width:45px;height:45px;">
                                                <i class="las la-credit-card text-primary"></i>
                                            </div>
                                        </div>

                                        <div>
                                            <h6 class="mb-1">
                                                {{ $method->name }}
                                            </h6>

                                            <small class="text-muted">
                                                {{ ucfirst($method->type) }}
                                            </small>
                                        </div>

                                    </div>

                                    @if ($method->status == 'active')
                                        <span class="badge badge-success">
                                            Active
                                        </span>
                                    @else
                                        <span class="badge badge-danger">
                                            Inactive
                                        </span>
                                    @endif

                                </div>

                                <hr>

                                <div class="row">

                                    <div class="col-6 mb-2">
                                        <small class="text-muted d-block">
                                            {{ translate('Charge') }}
                                        </small>

                                        <strong>
                                            {{ $method->charge }}%
                                        </strong>
                                    </div>

                                    <div class="col-6 mb-2">
                                        <small class="text-muted d-block">
                                            {{ translate('Bank') }}
                                        </small>

                                        <strong>
                                            {{ $method->bankAccount->name ?? 'N/A' }}
                                        </strong>
                                    </div>

                                </div>

                                <div class="d-flex mt-3">

                                    <button class="btn btn-sm btn-soft-primary mr-2 flex-fill"
                                        onclick="editPaymentMethod({{ $method->id }})">

                                        <i class="las la-edit"></i>
                                        Edit

                                    </button>

                                    <button class="btn btn-sm btn-soft-danger flex-fill"
                                        onclick="deletePaymentMethod({{ $method->id }})">

                                        <i class="las la-trash"></i>
                                        Delete

                                    </button>

                                </div>

                            </div>
                        </div>

                    @empty

                        <div class="text-center py-5">
                            <i class="las la-credit-card la-3x text-muted mb-3 d-block"></i>

                            <p class="text-muted">
                                {{ translate('No payment methods found') }}
                            </p>
                        </div>

                    @endforelse

                </div>

            </div>
        </div>





        <!-- ================= MOBILE BANK SECTION ================= -->

        <div class="col-12">

            <div class="card shadow-sm border-0">

                <!-- Header -->
                <div class="bg-account-color d-flex flex-column flex-md-row justify-content-between align-items-md-center p-2">

                    <h5 class="text-white mb-2 mb-md-0">
                        <i class="las la-mobile-alt mr-2"></i>
                        {{ translate('Mobile Bank List') }}
                    </h5>

                    <button class="btn btn-light btn-sm"
                        onclick="openAccountModal('mobile_bank', 'Mobile Bank')">

                        <i class="las la-plus"></i>
                        {{ translate('Add New') }}

                    </button>

                </div>

                <!-- Desktop Table -->
                <div class="table-responsive d-none d-md-block">

                    <table class="table table-hover mb-0">

                        <thead class="bg-light">
                            <tr>
                                <th>{{ translate('Account') }}</th>
                                <th>{{ translate('Balance') }}</th>
                                <th>{{ translate('Cash Out') }}</th>
                                <th class="text-center">{{ translate('Action') }}</th>
                            </tr>
                        </thead>

                        <tbody>

                            @php
                                $mobileBankAccounts = isset($assetAccounts)
                                    ? $assetAccounts->where('sub_type', 'mobile_bank')
                                    : collect();
                            @endphp

                            @forelse($mobileBankAccounts as $account)

                                @php
                                    $cashoutCharge = $account->cashout_charge ?? 0;

                                    $cashoutBalance =
                                        $account->current_balance -
                                        $account->current_balance * ($cashoutCharge / 100);
                                @endphp

                                <tr>

                                    <td>
                                        <div class="d-flex align-items-center">

                                            <div class="mr-3">
                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                                    style="width:45px;height:45px;">
                                                    <i class="las la-mobile-alt text-success"></i>
                                                </div>
                                            </div>

                                            <div>
                                                <strong>{{ $account->name }}</strong>

                                                @if ($account->mobile_number)
                                                    <br>
                                                    <small class="text-muted">
                                                        {{ $account->mobile_number }}
                                                    </small>
                                                @endif
                                            </div>

                                        </div>
                                    </td>

                                    <td>
                                        <span class="text-success font-weight-bold">
                                            {{ number_format($account->current_balance, 2) }} ৳
                                        </span>
                                    </td>

                                    <td>

                                        <span class="text-warning font-weight-bold">
                                            {{ number_format($cashoutBalance, 2) }} ৳
                                        </span>

                                        @if ($cashoutCharge > 0)
                                            <br>
                                            <small class="text-muted">
                                                Charge: {{ $cashoutCharge }}%
                                            </small>
                                        @endif

                                    </td>

                                    <td class="text-center">

                                        <button class="btn btn-soft-primary btn-sm"
                                            onclick="editAccount({{ $account->id }})">

                                            <i class="las la-edit"></i>

                                        </button>

                                    </td>

                                </tr>

                            @empty

                                <tr>
                                    <td colspan="4" class="text-center py-5">

                                        <i class="las la-mobile-alt la-3x text-muted mb-3 d-block"></i>

                                        <p class="text-muted">
                                            {{ translate('No mobile bank accounts found') }}
                                        </p>

                                    </td>
                                </tr>

                            @endforelse

                        </tbody>

                    </table>

                </div>





                <!-- Mobile View Cards -->
                <div class="d-block d-md-none p-2">

                    @forelse($mobileBankAccounts as $account)

                        @php
                            $cashoutCharge = $account->cashout_charge ?? 0;

                            $cashoutBalance =
                                $account->current_balance -
                                $account->current_balance * ($cashoutCharge / 100);
                        @endphp

                        <div class="card border mb-3 shadow-sm">

                            <div class="card-body">

                                <div class="d-flex justify-content-between">

                                    <div class="d-flex">

                                        <div class="mr-3">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                                style="width:45px;height:45px;">
                                                <i class="las la-mobile-alt text-success"></i>
                                            </div>
                                        </div>

                                        <div>
                                            <h6 class="mb-1">
                                                {{ $account->name }}
                                            </h6>

                                            <small class="text-muted">
                                                {{ $account->mobile_number }}
                                            </small>
                                        </div>

                                    </div>

                                    <button class="btn btn-soft-primary btn-sm"
                                        onclick="editAccount({{ $account->id }})">

                                        <i class="las la-edit"></i>

                                    </button>

                                </div>

                                <hr>

                                <div class="row">

                                    <div class="col-6">

                                        <small class="text-muted d-block">
                                            Balance
                                        </small>

                                        <strong class="text-success">
                                            {{ number_format($account->current_balance, 2) }} ৳
                                        </strong>

                                    </div>

                                    <div class="col-6">

                                        <small class="text-muted d-block">
                                            Cash Out
                                        </small>

                                        <strong class="text-warning">
                                            {{ number_format($cashoutBalance, 2) }} ৳
                                        </strong>

                                    </div>

                                </div>

                            </div>

                        </div>

                    @empty

                        <div class="text-center py-5">

                            <i class="las la-mobile-alt la-3x text-muted mb-3 d-block"></i>

                            <p class="text-muted">
                                {{ translate('No mobile bank accounts found') }}
                            </p>

                        </div>

                    @endforelse

                </div>

            </div>

        </div>

    </div>
</div>

<!-- Include modals -->
@include('backend.accounts.payment_method.modals.payment_method_modal')
@include('backend.accounts.payment_method.modals.mobile_bank_modal')



<script>
    // Payment Method Functions
    function openPaymentMethodModal(depositType) {
        $('#paymentMethodForm')[0].reset();
        $('#payment_method_id').val('');
        $('#deposit_type').val(depositType);
        $('#paymentMethodModalLabel').text(depositType === 'auto' ?
            '{{ translate('Add Auto Deposit Payment Method') }}' :
            '{{ translate('Add Manual Payment Method') }}');

        if (depositType === 'auto') {
            $('#bank_account_group').show();
            $('#bank_account_id').prop('required', true);
        } else {
            $('#bank_account_group').hide();
            $('#bank_account_id').prop('required', false);
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
                    $('#paymentMethodModalLabel').text('{{ translate('Edit Payment Method') }}');

                    if (method.deposit_type === 'auto') {
                        $('#bank_account_group').show();
                        $('#bank_account_id').prop('required', true);
                        $('#bank_account_id').val(method.bank_account_id);
                    } else {
                        $('#bank_account_group').hide();
                        $('#bank_account_id').prop('required', false);
                    }

                    $('#paymentMethodModal').modal('show');
                }
            },
            error: function() {
                showNotification('danger', '{{ translate('Error loading payment method data') }}');
            }
        });
    }

    $('#paymentMethodForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#payment_method_id').val();
        var url = id ? '/transaction-method/update/' + id : '/transaction-method/store';

        $.ajax({
            url: url,
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    $('#paymentMethodModal').modal('hide');
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
                    showNotification('danger', '{{ translate('Error saving payment method') }}');
                }
            }
        });
    });

    function deletePaymentMethod(id) {
        if (confirm('{{ translate('Are you sure? This will soft delete the payment method.') }}')) {
            $.ajax({
                url: '/transaction-method/delete/' + id,
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                        showNotification('success', response.message);
                    }
                },
                error: function() {
                    showNotification('danger', '{{ translate('Error deleting payment method') }}');
                }
            });
        }
    }

    function restorePaymentMethod(id) {
        if (confirm('{{ translate('Are you sure you want to restore this payment method?') }}')) {
            $.ajax({
                url: '/transaction-method/restore/' + id,
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                        showNotification('success', response.message);
                    }
                },
                error: function() {
                    showNotification('danger', '{{ translate('Error restoring payment method') }}');
                }
            });
        }
    }

    // function showDeletedMethods() {
    //     $.ajax({
    //         url: '/transaction-method/get?with_trashed=1',
    //         type: 'GET',
    //         success: function(response) {
    //             if (response.success) {
    //                 var methods = response.data.filter(function(m) {
    //                     return m.deleted_at !== null;
    //                 });

    //                 var html = '';
    //                 if (methods.length === 0) {
    //                     html =
    //                         '<tr><td colspan="4" class="text-center py-4"><i class="las la-inbox la-2x text-muted"></i><p class="mt-2">{{ translate('No deleted methods found') }}</p></td></tr>';
    //                 } else {
    //                     $.each(methods, function(index, method) {
    //                         html += '<tr>';
    //                         html += '<td><strong>' + method.name + '</strong></td>';
    //                         html += '<td><span class="badge badge-soft-info">' + method.type +
    //                             '</span></td>';
    //                         html += '<td><small>' + method.deleted_at + '</small></td>';
    //                         html += '<td class="text-center">';
    //                         html +=
    //                             '<button class="btn btn-sm btn-soft-success" onclick="restorePaymentMethod(' +
    //                             method.id + ')">';
    //                         html +=
    //                             '<i class="las la-trash-restore"></i> {{ translate('Restore') }}';
    //                         html += '</button>';
    //                         html += '</td>';
    //                         html += '</tr>';
    //                     });
    //                 }
    //                 $('#deletedMethodsList').html(html);
    //                 $('#deletedMethodsModal').modal('show');
    //             }
    //         },
    //         error: function() {
    //             showNotification('danger', '{{ translate('Error loading deleted methods') }}');
    //         }
    //     });
    // }

    function openMobileBankModal() {
        $('#mobileBankForm')[0].reset();
        $('#mobile_bank_id').val('');
        $('#mobileBankModalLabel').text('{{ translate('Add Mobile Bank Account') }}');
        $('#mobileBankModal').modal('show');
    }

    function editMobileBank(id) {
        editAccount(id);
    }

    function deleteMobileBank(id) {
        deleteAccount(id);
    }

    $('#mobileBankForm').on('submit', function(e) {
        e.preventDefault();

        var formData = {
            name: $('#mobile_bank_name').val(),
            type: 'asset',
            sub_type: 'mobile_bank',
            mobile_number: $('#mobile_number').val(),
            opening_balance: $('#opening_balance_mb').val(),
            cashout_charge: $('#cashout_charge_mb').val(),
            as_of_date: $('#as_of_date_mb').val(),
            _token: '{{ csrf_token() }}'
        };

        var id = $('#mobile_bank_id').val();
        var url = id ? '/admin/accounts/chart-of-accounts/update/' + id :
            '/admin/accounts/chart-of-accounts/store';

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#mobileBankModal').modal('hide');
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
                    showNotification('danger', '{{ translate('Error saving mobile bank') }}');
                }
            }
        });
    });
</script>
