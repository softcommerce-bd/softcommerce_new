@extends('backend.accounts.layout')

@section('accounts_content')
    <div class="simplebar-content" style="padding: 16px;">
        <div class="container-fluid">

            <!-- ================= AUTO PAYMENT METHODS (Card & Mobile Banking) ================= -->
            <div class="w-100 border-bottom d-flex justify-content-between align-items-center px-1 mb-3">
                <p class="title pb-1 mb-0 fw-600">
                    <i class="las la-credit-card text-primary mr-2"></i>
                    {{ translate('Card & Mobile Banking Payment Method List (Auto deposited to Bank)') }}
                </p>
                <a href="javascript:void(0)" onclick="openPaymentMethodModal('auto')" class="btn-account-color">
                    <i class="las la-plus"></i> {{ translate('Add') }}
                </a>
            </div>

            <div grid-data="" class="mb-4">
                <table class="table">
                    <thead>
                        <tr class="bg-account-color text-white py-4">
                            <th class="col-3 pl-2" scope="col">
                                <span class="lbl sortable"
                                    onclick="sortBy('name')">{{ translate('Name') }}&nbsp;&nbsp;</span>
                            </th>
                            <th class="col-2" scope="col">
                                <span class="lbl sortable"
                                    onclick="sortBy('type')">{{ translate('Payment Type') }}&nbsp;&nbsp;</span>
                            </th>
                            <th class="col-3" scope="col">
                                <span class="lbl sortable"
                                    onclick="sortBy('account')">{{ translate('Account') }}&nbsp;&nbsp;</span>
                            </th>
                            <th class="col-2 text-right" scope="col">
                                <span class="lbl sortable"
                                    onclick="sortBy('charge')">{{ translate('Charge') }}&nbsp;&nbsp;</span>
                            </th>
                            <th class="col-2 text-right pr-2" scope="col">
                                <span class="lbl">{{ translate('Action') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="payment_methods_tbody">
                        @php
                            $autoMethods = $methods->where('deposit_type', 'auto');
                        @endphp

                        @forelse($autoMethods as $method)
                            <tr class="panel-list-items d-flex flex-wrap">
                                <td class="col-3 pl-2">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mr-2"
                                            style="width:35px;height:35px;">
                                            <i class="las la-credit-card text-primary"></i>
                                        </div>
                                        <div>
                                            <strong>{{ $method->name }}</strong>
                                            @if ($method->description)
                                                <br><small class="text-muted">{{ $method->description }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="col-2">
                                    <span class="badge badge-info">{{ ucfirst($method->type) }}</span>
                                </td>
                                <td class="col-3">
                                    @if ($method->deposit_type == 'auto')
                                        <div class="small">
                                            <i class="las la-mobile-alt text-primary"></i>
                                            {{ $method->sourceAccount->name ?? 'N/A' }}
                                            <i class="las la-arrow-right mx-1"></i>
                                            <i class="las la-university text-success"></i>
                                            {{ $method->bankAccount->name ?? '-' }}
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="col-2 text-right">
                                    <strong class="{{ $method->charge > 0 ? 'text-danger' : 'text-success' }}">
                                        {{ $method->charge }}%
                                    </strong>
                                </td>
                                <td class="col-2 text-right pr-2">
                                    <button class="btn without-focus border-0 px-1 py-0 mr-2"
                                        onclick="editPaymentMethod({{ $method->id }})" title="{{ translate('Edit') }}">
                                        <i class="las la-edit text-primary" style="font-size: 18px;"></i>
                                    </button>
                                    <button class="btn without-focus border-0 px-1 py-0"
                                        onclick="deletePaymentMethod({{ $method->id }})"
                                        title="{{ translate('Delete') }}">
                                        <i class="las la-trash text-danger" style="font-size: 18px;"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr class="panel-list-items">
                                <td colspan="5" class="text-center py-5">
                                    <i class="las la-credit-card la-3x text-muted mb-3 d-block"></i>
                                    <p class="text-muted">{{ translate('No payment methods found') }}</p>
                                    <button class="btn-account-color" onclick="openPaymentMethodModal('auto')">
                                        <i class="las la-plus"></i> {{ translate('Add New') }}
                                    </button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($autoMethods->count() > 0)
                    <form class="form-inline margin-bottom-basic">
                        <div class="w-100 d-flex align-items-center justify-content-between px-3 pb-4 mb-3">
                            <div class="count">
                                <span class="ng-binding">{{ translate('Total Items') }}:
                                    {{ $autoMethods->count() }}</span>
                            </div>
                            <div class="count">
                                <div class="form-group d-flex flex-wrap mb-0 itemPerPage">
                                    <label class="col col-form-label white-space-nowrap px-0"
                                        for="itemPerPage">{{ translate('Items per page') }}:</label>
                                    <select class="form-control cs-select py-0 px-2 border-0 without-focus font-13"
                                        id="itemPerPage">
                                        <option value="5">5</option>
                                        <option value="10">10</option>
                                        <option value="50" selected>50</option>
                                        <option value="75">75</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </form>
                @endif
            </div>

            <!-- ================= MOBILE BANK LIST ================= -->
            <div class="w-100 px-1 pb-3">
                <p class="border-bottom title pb-1 mb-0 fw-600">
                    <i class="las la-mobile-alt text-primary mr-2"></i>
                    {{ translate('Mobile Bank List (Not auto deposit to Bank)') }}
                </p>
            </div>

            <!-- nav-pills -->
            <div class="w-100">
                <!-- content -->
                <div class="tab-content" id="pills-tabContent">
                    <!-- Asset Tab -->
                    <div id="pills-Asset" class="tab-pane fade show active" role="tabpanel">

                        @php
                            $mobileBankAccounts = $assetAccounts->where('sub_type', 'mobile_bank');
                        @endphp

                        <div class="w-100 panel-elem pb-4">
                            <div class="w-100 d-flex flex-wrap bg-account-color p-2 text-white">
                                <div class="col d-flex align-items-center">
                                    <span class="lbl fw-600">{{ translate('Name') }}</span>
                                </div>
                                <div class="col d-flex align-items-center">
                                    <span class="lbl fw-600">{{ translate('Current Balance (৳)') }}</span>
                                </div>
                                <div class="col d-flex align-items-center">
                                    <span class="lbl fw-600">{{ translate('Cash Out Balance (৳)') }}</span>
                                </div>
                                <div class="col-1 px-1 d-flex align-items-center">
                                    <span class="lbl fw-600">{{ translate('Action') }}</span>
                                </div>
                            </div>

                            <!-- item rows -->
                            <div class="w-100 pb-2 mb-1">
                                @forelse($mobileBankAccounts as $account)
                                    @php
                                        $cashoutCharge = $account->cashout_charge ?? 0;
                                        $cashoutBalance =
                                            $account->current_balance -
                                            $account->current_balance * ($cashoutCharge / 100);
                                    @endphp
                                    <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
                                        <div class="col d-flex align-items-center">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mr-2"
                                                    style="width:35px;height:35px;">
                                                    <i class="las la-mobile-alt text-success"></i>
                                                </div>
                                                <div>
                                                    <strong>{{ $account->name }}</strong>
                                                    @if ($account->mobile_number)
                                                        <br><small
                                                            class="text-muted">{{ $account->mobile_number }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col d-flex align-items-center">
                                            <span class="text-success font-weight-bold">
                                                {{ number_format($account->current_balance, 2) }}
                                            </span>
                                        </div>
                                        <div class="col d-flex align-items-center">
                                            <span class="text-warning font-weight-bold">
                                                {{ number_format($cashoutBalance, 2) }}
                                            </span>
                                            @if ($cashoutCharge > 0)
                                                <br><small class="text-muted">(Charge: {{ $cashoutCharge }}%)</small>
                                            @endif
                                        </div>
                                        <div class="col-1 px-1 d-flex align-items-center">
                                            <button class="btn without-focus border-0 px-1 py-0"
                                                onclick="editAccount({{ $account->id }})"
                                                title="{{ translate('Edit') }}">
                                                <i class="las la-edit text-primary" style="font-size: 18px;"></i>
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="w-100 panel-list-items text-center py-4">
                                        <i class="las la-mobile-alt la-3x text-muted mb-2 d-block"></i>
                                        <p class="text-muted">{{ translate('No mobile bank accounts found') }}</p>
                                        <button class="btn-account-color"
                                            onclick="openAccountModal('mobile_bank', 'Mobile Bank')">
                                            <i class="las la-plus"></i> {{ translate('Add Mobile Bank') }}
                                        </button>
                                    </div>
                                @endforelse
                            </div>

                            @if ($mobileBankAccounts->count() > 0)
                                <div class="w-100">
                                    <button class="btn text-white btn-add-panel-item"
                                        onclick="openAccountModal('mobile_bank', 'Mobile Bank')" type="button">
                                        <i class="las la-plus"></i> {{ translate('Add') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include modals -->
    @include('backend.accounts.payment_methods.modals.payment_method_modal')
    @include('backend.accounts.payment_methods.modals.mobile_bank_modal')

    <style>
        .panel-heading {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 10px 0;
        }

        .panel-list-items {
            border-bottom: 1px solid #e9ecef;
        }

        .panel-list-items:hover {
            background-color: #f8f9fa;
        }

        .btn-add-panel-item {
            background-color: #28a745;
            border-color: #28a745;
            padding: 5px 15px;
            font-size: 13px;
            border-radius: 4px;
        }

        .btn-add-panel-item:hover {
            background-color: #218838;
            border-color: #218838;
        }

        .without-focus:focus {
            outline: none;
            box-shadow: none;
        }

        .sortable {
            cursor: pointer;
        }

        .sortable:hover {
            color: #007bff;
        }
    </style>
@endsection

@section('script')
    <script>
        var currentDepositType = 'auto';

        // Payment Method Functions
        function openPaymentMethodModal(depositType) {
            currentDepositType = depositType;
            $('#paymentMethodForm')[0].reset();
            $('#payment_method_id').val('');
            $('#deposit_type').val(depositType);

            if (depositType === 'auto') {
                $('#bank_account_group').show();
                $('#source_account_group').show();
                $('#bank_account_id').prop('required', true);
                $('#source_account_id').prop('required', true);
            } else {

                $('#bank_account_group').hide();
                $('#source_account_group').hide();
                $('#bank_account_id').prop('required', false);
                $('#source_account_id').prop('required', false);
            }

            $('#paymentMethodModalLabel').text(depositType === 'auto' ?
                '{{ translate('Add Auto Deposit Payment Method') }}' :
                '{{ translate('Add Manual Payment Method') }}');

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
                            $('#bank_account_group').show();
                            $('#bank_account_id').prop('required', true);
                            $('#bank_account_id').val(method.bank_account_id);
                            $('#source_account_id').val(method.source_account_id); // ADDED
                        } else {
                            $('#bank_account_group').hide();
                            $('#bank_account_id').prop('required', false);
                        }

                        $('#paymentMethodModalLabel').text('{{ translate('Edit Payment Method') }}');
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

        function sortBy(field) {
            showNotification('info', 'Sorting by ' + field);
        }
    </script>
@endsection
