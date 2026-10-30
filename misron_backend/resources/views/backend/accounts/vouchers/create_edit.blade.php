@extends('backend.accounts.layout')

@section('accounts_content')
    <style>
        input[readonly] {
            background-color: #f5f5f5 !important;
            cursor: not-allowed;
        }

        input[readonly]:focus {
            outline: none;
            box-shadow: none;
        }

        .panel-heading {
            background-color: #f8f9fa;
            padding: 10px 0;
            border-radius: 4px;
        }

        .panel-list-items {
            border-bottom: 1px solid #e9ecef;
            padding: 8px 0;
        }

        .without-focus:focus {
            outline: none;
            box-shadow: none;
        }

        .cpr,
        .cpl {
            padding-right: 10px;
            padding-left: 10px;
        }

        .table-bordered td,
        .table-bordered th {
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .btn-add-panel-item {
            background-color: #28a745;
            border-color: #28a745;
            padding: 4px 12px;
            font-size: 13px;
            border-radius: 4px;
            color: white;
        }

        .btn-add-panel-item:hover {
            background-color: #218838;
            border-color: #218838;
        }
    </style>
    <div class="simplebar-content" style="padding: 16px;">
        <div class="container-fluid">
            <!-- Header -->
            <div class="w-100 border-bottom d-flex justify-content-between align-items-center px-1 mb-3">
                <p class="title pb-1 mb-0 fw-600">
                    <i class="las la-receipt text-primary mr-2"></i>
                    {{ isset($voucher) ? translate('Edit Voucher') : translate('Create New Voucher') }}
                </p>
                <a href="{{ route('accounts.index', ['tab' => 'vouchers']) }}" class="btn btn-secondary btn-sm">
                    <i class="las la-list"></i> {{ translate('Back to List') }}
                </a>
            </div>

            <!-- item add panel -->
            <div class="w-100 pb-4">
                <form class="form-eud" autocomplete="off" name="voucher_form" id="voucherForm" method="POST"
                    action="{{ isset($voucher) ? route('accounts.vouchers.update', $voucher->id) : route('accounts.vouchers.store') }}">
                    @csrf
                    @if (isset($voucher))
                        @method('PUT')
                    @endif
                    <input type="hidden" name="voucher_id" id="voucher_id" value="{{ $voucher->id ?? '' }}">

                    <div class="form-row">
                        <!-- Date Field -->
                        <div class="col-lg-2 col-6 cpr form-group">
                            <label class="font-medium label-date" for="voucherDate">
                                {{ translate('Date') }} <span class="text-danger">*</span>
                            </label>
                            <input type="date" id="voucherDate" name="date"
                                class="form-control without-focus @error('date') is-invalid @enderror"
                                value="{{ old('date', isset($voucher) ? $voucher->date->format('Y-m-d') : date('Y-m-d')) }}"
                                required>
                            <div class="invalid-feedback">
                                {{ translate('Date is required') }}
                            </div>
                        </div>

                        <!-- Voucher Type -->
                        <div class="col-lg-3 col-6 cpl">
                            <div class="form-group cst">
                                <label class="cs-select-lbl d-block">
                                    {{ translate('Voucher Type') }} <span class="text-danger">*</span>
                                </label>
                                <select name="type" id="voucher_type" class="form-control select2" style="width: 100%"
                                    required onchange="onVoucherTypeChange()" {{ isset($voucher) ? 'disabled' : '' }}>
                                    <option value="">{{ translate('Select Voucher Type') }}</option>
                                    <option value="CP"
                                        {{ old('type', isset($voucher) ? $voucher->type : '') == 'CP' ? 'selected' : '' }}>
                                        {{ translate('Cash Payment (CP)') }}</option>
                                    <option value="BP"
                                        {{ old('type', isset($voucher) ? $voucher->type : '') == 'BP' ? 'selected' : '' }}>
                                        {{ translate('Bank Payment (BP)') }}</option>
                                    <option value="CR"
                                        {{ old('type', isset($voucher) ? $voucher->type : '') == 'CR' ? 'selected' : '' }}>
                                        {{ translate('Cash Receive (CR)') }}</option>
                                    <option value="BR"
                                        {{ old('type', isset($voucher) ? $voucher->type : '') == 'BR' ? 'selected' : '' }}>
                                        {{ translate('Bank Receive (BR)') }}</option>
                                    <option value="JV"
                                        {{ old('type', isset($voucher) ? $voucher->type : '') == 'JV' ? 'selected' : '' }}>
                                        {{ translate('Journal Voucher (JV)') }}</option>
                                    <option value="CV"
                                        {{ old('type', isset($voucher) ? $voucher->type : '') == 'CV' ? 'selected' : '' }}>
                                        {{ translate('Contra Voucher (CV)') }}</option>
                                </select>
                                @if (isset($voucher))
                                    <input type="hidden" name="type" value="{{ $voucher->type }}">
                                @endif
                                <div class="invalid-feedback">
                                    {{ translate('Voucher Type is required') }}
                                </div>
                            </div>
                        </div>

                        <!-- Cash/Bank Account -->
                        <div class="col-lg-3 col-6 cpl" id="cash_bank_account_div" style="display: none;">
                            <div class="form-group cst">
                                <label class="cs-select-lbl d-block" id="cash_bank_label">
                                    {{ translate('Cash Account') }}
                                </label>
                                <select name="cash_bank_account_id" id="cash_bank_account_id" class="form-control select2"
                                    style="width: 100%" {{ isset($voucher) ? 'disabled' : '' }}>
                                    <option value="">{{ translate('Select Account') }}</option>
                                </select>
                                @if (isset($voucher))
                                    <input type="hidden" name="cash_bank_account_id" id="hidden_cash_bank_account_id"
                                        value="">
                                @endif
                            </div>
                        </div>

                        <!-- Reference No -->
                        {{-- <div class="col-lg-3 col-6 cpl">
                            <div class="form-group">
                                <label class="font-medium">{{ translate('Reference No') }}</label>
                                <input type="text" name="reference_no" id="reference_no" class="form-control"
                                    value="{{ old('reference_no', isset($voucher) ? $voucher->reference_no : '') }}"
                                    placeholder="{{ translate('Cheque/Transaction No') }}">
                            </div>
                        </div> --}}
                    </div>

                    <!-- item panel -->
                    <div class="w-100 panel-elem pb-4" id="voucher_details_section" style="display: none;">
                        <div class="w-100 pt-3">
                            <h6 class="font-15">
                                {{ translate('Voucher Details') }} <span class="text-danger">*</span>
                            </h6>
                            <span class="text-danger" id="details_error" style="display: none;">
                                {{ translate('Details are required') }}
                            </span>
                        </div>

                        <div class="w-100 panel-elem pb-4">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="bg-light">
                                        <tr class="panel-heading" id="table_header">
                                            <th width="5%">#</th>
                                            <th width="40%">{{ translate('Account') }} <span
                                                    class="text-danger">*</span></th>
                                            <th width="25%" class="text-right" id="amount_header">
                                                {{ translate('Amount (৳)') }} <span class="text-danger">*</span>
                                            </th>
                                            <th width="25%" class="text-left">{{ translate('Note') }}</th>
                                            <th width="5%" class="text-center">{{ translate('Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="transactions_tbody">
                                        <!-- Dynamic rows will be added here -->
                                    </tbody>
                                    <tfoot class="bg-light">
                                        <tr class="panel-list-items">
                                            <td colspan="2" class="text-right"><strong>{{ translate('Total') }}</strong>
                                            </td>
                                            <td class="text-right"><strong id="total_amount">0.00</strong></td>
                                            <td colspan="2"></td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" class="text-center">
                                                <button type="button" class="btn btn-sm btn-info"
                                                    onclick="addTransactionRow()">
                                                    <i class="las la-plus"></i> {{ translate('Add Row') }}
                                                </button>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Voucher Narration -->
                        <div class="w-100 pt-3">
                            <div class="col px-1 mr-auto">
                                <div class="form-group">
                                    <label class="font-medium">{{ translate('Narration / Note') }}</label>
                                    <textarea rows="2" class="form-control" name="description" id="voucher_description"
                                        placeholder="{{ translate('Enter voucher narration...') }}">{{ old('description', isset($voucher) ? $voucher->description : '') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Save and Cancel Buttons -->
                        <div class="d-flex act-bt justify-content-start border-top pt-3 mt-4">
                            <button type="submit" class="btn btn-primary mr-4 py-2 px-4" id="save_btn">
                                <i class="las la-save"></i> {{ translate('Save Voucher') }}
                            </button>
                            <button type="button" class="btn btn-secondary py-2 px-4"
                                onclick="window.location.href='{{ route('accounts.index', ['tab' => 'vouchers']) }}'">
                                <i class="las la-times"></i> {{ translate('Cancel') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>




    <script>
        var rowCounter = 0;
        var currentVoucherType = '';
        var groupedAccountsData = {};

        $(document).ready(function() {
            // Initialize select2
            if ($.fn.select2) {
                $('.select2').select2();
            }

            // Initialize on page load if editing
            var initialType = $('#voucher_type').val();
            if (initialType) {
                currentVoucherType = initialType;
                onVoucherTypeChange();

                @if (isset($voucher) && $voucher->details && count($voucher->details) > 0)
                    // Wait for accounts to load then load existing transactions
                    setTimeout(function() {
                        // Make sure groupedAccountsData is loaded first
                        if (Object.keys(groupedAccountsData).length > 0) {
                            loadExistingTransactions(@json($voucher->details));
                        } else {
                            // Wait a bit longer for accounts to load
                            var checkInterval = setInterval(function() {
                                if (Object.keys(groupedAccountsData).length > 0) {
                                    clearInterval(checkInterval);
                                    loadExistingTransactions(@json($voucher->details));
                                }
                            }, 100);
                        }
                    }, 1000);
                @endif
            }
        });

        function onVoucherTypeChange() {
            currentVoucherType = $('#voucher_type').val();
            var cashBankDiv = $('#cash_bank_account_div');
            var voucherDetailsSection = $('#voucher_details_section');
            var cashBankLabel = $('#cash_bank_label');
            var tableHeader = $('#table_header');

            cashBankDiv.hide();
            voucherDetailsSection.hide();
            $('#cash_bank_account_id').removeAttr('required');

            if (!currentVoucherType) return;

            voucherDetailsSection.show();

            // Clear existing rows
            $('#transactions_tbody').empty();
            rowCounter = 0;

            // Change table header based on voucher type
            if (currentVoucherType === 'JV' || currentVoucherType === 'CV') {
                // For Journal and Contra Voucher - Show both Debit and Credit columns
                tableHeader.html(`
            <th width="5%">#</th>
            <th width="35%">{{ translate('Account') }} <span class="text-danger">*</span></th>
            <th width="20%" class="text-right">{{ translate('Dr. Amount (৳)') }}</th>
            <th width="20%" class="text-right">{{ translate('Cr. Amount (৳)') }}</th>
            <th width="15%" class="text-left">{{ translate('Note') }}</th>
            <th width="5%" class="text-center">{{ translate('Action') }}</th>
        `);
            } else {
                // For CP, BP, CR, BR - Show single Amount column
                tableHeader.html(`
            <th width="5%">#</th>
            <th width="55%">{{ translate('Account') }} <span class="text-danger">*</span></th>
            <th width="20%" class="text-right">{{ translate('Amount (৳)') }} <span class="text-danger">*</span></th>
            <th width="15%" class="text-left">{{ translate('Note') }}</th>
            <th width="5%" class="text-center">{{ translate('Action') }}</th>
        `);
            }

            // Load accounts based on voucher type
            loadAccountsByType(function() {
                var isEditing = {{ isset($voucher) ? 'true' : 'false' }};

                console.log('Accounts loaded, isEditing:', isEditing);
                console.log('groupedAccountsData keys:', Object.keys(groupedAccountsData));

                if (isEditing) {
                    // For edit mode, wait for existing transactions after accounts are loaded
                    console.log('Edit mode - accounts loaded, waiting for existing transactions');
                    @if (isset($voucher) && $voucher->details && count($voucher->details) > 0)
                        // Add a small delay to ensure DOM is ready
                        setTimeout(function() {
                            loadExistingTransactions(@json($voucher->details));
                        }, 200);
                    @else
                        // If no details, add empty rows
                        if (currentVoucherType === 'JV' || currentVoucherType === 'CV') {
                            addTransactionRow();
                            addTransactionRow();
                        } else {
                            addTransactionRow();
                        }
                    @endif
                } else {
                    // For create mode, add rows based on voucher type AFTER accounts are loaded
                    console.log('Create mode - adding rows after accounts loaded');
                    if (currentVoucherType === 'JV' || currentVoucherType === 'CV') {
                        // Add 2 rows for JV/CV
                        addTransactionRow();
                        addTransactionRow();
                    } else {
                        // Add 1 row for CP/BP/CR/BR
                        addTransactionRow();
                    }
                }
            });

            // Load cash/bank accounts (only for CP,BP,CR,BR)
            if (['CP', 'BP', 'CR', 'BR'].includes(currentVoucherType)) {
                loadCashBankAccounts();
                if (currentVoucherType === 'CP' || currentVoucherType === 'CR') {
                    cashBankLabel.text('{{ translate('Cash Account') }}');
                } else {
                    cashBankLabel.text('{{ translate('Bank Account') }}');
                }
                cashBankDiv.show();
            }
        }

        function loadCashBankAccounts() {
            var accountType = '';
            if (currentVoucherType === 'CP' || currentVoucherType === 'CR') {
                accountType = 'cash';
            } else if (currentVoucherType === 'BP' || currentVoucherType === 'BR') {
                accountType = 'bank';
            }

            $.ajax({
                url: '{{ route('accounts.vouchers.get-cash-bank-accounts') }}',
                type: 'GET',
                data: {
                    type: accountType,
                    voucher_type: currentVoucherType
                },
                success: function(response) {
                    if (response.success) {
                        var $select = $('#cash_bank_account_id');
                        $select.empty();
                        $select.append('<option value="">{{ translate('Select Account') }}</option>');

                        $.each(response.accounts, function(index, account) {
                            $select.append('<option value="' + account.id + '">' + account.name + ' (' +
                                account.code + ') - Balance: ' + parseFloat(account.current_balance)
                                .toFixed(2) + '</option>');
                        });
                        $select.trigger('change.select2');

                        // For edit: set the cash/bank account value if exists
                        @if (isset($cashBankAccountId))
                            $('#cash_bank_account_id').val('{{ $cashBankAccountId }}').trigger('change');
                            // Also set hidden input for form submission
                            $('#hidden_cash_bank_account_id').val('{{ $cashBankAccountId }}');
                        @endif
                    }
                }
            });
        }

        function loadAccountsByType(callback) {
            $.ajax({
                url: '{{ route('accounts.vouchers.get-accounts') }}',
                type: 'GET',
                data: {
                    voucher_type: currentVoucherType
                },
                async: true,
                success: function(response) {
                    if (response.success) {
                        groupedAccountsData = {};
                        $.each(response.accounts, function(index, account) {
                            var groupName = getAccountGroupName(account.type, account.sub_type);
                            if (!groupedAccountsData[groupName]) {
                                groupedAccountsData[groupName] = [];
                            }
                            groupedAccountsData[groupName].push(account);
                        });

                        if (callback) callback();
                    }
                },
                error: function(xhr) {
                    console.error('Error loading accounts:', xhr);
                    if (callback) callback();
                }
            });
        }

        function getAccountGroupName(type, subType) {
            var groups = {
                'asset_receivable': 'Receivable',
                'asset_inventory': 'Inventory',
                'asset_cash': 'Cash',
                'asset_bank': 'Bank',
                'asset_mobile_bank': 'Mobile Banking',
                'liability_payable': 'Payable',
                'liability_tax': 'Tax',
                'equity': 'Equity',
                'income': 'Income',
                'expense': 'Expense',
            };

            if (type === 'asset') {
                if (subType === 'receivable') return groups.asset_receivable;
                if (subType === 'inventory') return groups.asset_inventory;
                if (subType === 'cash') return groups.asset_cash;
                if (subType === 'bank') return groups.asset_bank;
                if (subType === 'mobile_bank') return groups.asset_mobile_bank;
                return 'Asset';
            }
            if (type === 'liability') {
                if (subType === 'payable') return groups.liability_payable;
                if (subType === 'equity') return groups.equity;
                if (subType === 'tax') return groups.liability_tax;
                return 'Liability';
            }
            if (type === 'equity') return groups.equity;
            if (type === 'income') return groups.income;
            if (type === 'expense') return groups.expense;

            return ucfirst(type);
        }

        function addTransactionRow(existingData = null) {
            var isJVorCV = (currentVoucherType === 'JV' || currentVoucherType === 'CV');
            var html = '';
            var currentRowCounter = rowCounter + 1;

            if (isJVorCV) {
                // Journal/Contra Voucher - Both Debit and Credit columns with mutual exclusivity
                var debitValue = (existingData && existingData.debit) ? existingData.debit : 0;
                var creditValue = (existingData && existingData.credit) ? existingData.credit : 0;
                var narrationValue = (existingData && existingData.narration) ? existingData.narration : '';
                var accountId = (existingData && existingData.account_id) ? existingData.account_id : '';

                html = `<tr class="transaction-row" data-row-id="${currentRowCounter}">
            <td class="text-center">${currentRowCounter}</td>
            <td>
                <select name="account_ids[]" class="form-control account-select" style="width: 100%" required>
                    <option value="">{{ translate('Select Account') }}</option>
                </select>
            </td>
            <td class="text-right">
                <input type="number" step="0.01" name="debits[]" class="form-control text-right debit-amount" placeholder="0.00" onchange="updateTotals(); toggleDebitCredit(this)" value="${debitValue}">
            </td>
            <td class="text-right">
                <input type="number" step="0.01" name="credits[]" class="form-control text-right credit-amount" placeholder="0.00" onchange="updateTotals(); toggleDebitCredit(this)" value="${creditValue}">
            </td>
            <td>
                <input type="text" name="narrations[]" class="form-control" placeholder="{{ translate('Note') }}" value="${narrationValue}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeTransactionRow(this)">
                    <i class="las la-trash"></i>
                </button>
            </td>
         </tr>`;

                $('#transactions_tbody').append(html);

                // Get the select element
                var $select = $('#transactions_tbody .account-select:last');

                // Build options for select2
                var options = '<option value="">{{ translate('Select Account') }}</option>';

                if (Object.keys(groupedAccountsData).length > 0) {
                    $.each(groupedAccountsData, function(groupName, accounts) {
                        options += `<optgroup label="${groupName}">`;
                        $.each(accounts, function(index, account) {
                            var selected = (accountId && account.id == accountId) ? 'selected' : '';
                            options +=
                                `<option value="${account.id}" ${selected}>${account.name} (${account.code})</option>`;
                        });
                        options += `</optgroup>`;
                    });
                    $select.html(options);
                } else {
                    // If no accounts loaded yet, show loading message
                    $select.html('<option value="">{{ translate('Loading accounts...') }}</option>');
                }

                // Initialize select2
                if ($.fn.select2) {
                    $select.select2({
                        placeholder: "{{ translate('Select Account') }}",
                        allowClear: true,
                        width: '100%'
                    });
                }

                // Set value if account exists
                if (accountId) {
                    $select.val(accountId).trigger('change');
                }

                // Apply mutual exclusivity based on existing values
                var $row = $('#transactions_tbody tr:last');
                var $debitInput = $row.find('.debit-amount');
                var $creditInput = $row.find('.credit-amount');

                if (debitValue > 0) {
                    $creditInput.prop('readonly', true).css('background-color', '#f5f5f5');
                } else if (creditValue > 0) {
                    $debitInput.prop('readonly', true).css('background-color', '#f5f5f5');
                }

                rowCounter++;

            } else {
                // Payment/Receipt Voucher - Single Amount column
                var amountValue = (existingData && existingData.amount) ? existingData.amount : 0;
                var narrationValue = (existingData && existingData.narration) ? existingData.narration : '';
                var accountId = (existingData && existingData.account_id) ? existingData.account_id : '';

                html = `<tr class="transaction-row" data-row-id="${currentRowCounter}">
            <td class="text-center">${currentRowCounter}</td>
            <td>
                <select name="account_ids[]" class="form-control account-select" style="width: 100%" required>
                    <option value="">{{ translate('Select Account') }}</option>
                </select>
            </td>
            <td class="text-right">
                <input type="number" step="0.01" name="amounts[]" class="form-control text-right transaction-amount" placeholder="0.00" onchange="updateTotals()" value="${amountValue}">
            </td>
            <td>
                <input type="text" name="narrations[]" class="form-control" placeholder="{{ translate('Note') }}" value="${narrationValue}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeTransactionRow(this)">
                    <i class="las la-trash"></i>
                </button>
            </td>
         <tr>`;

                $('#transactions_tbody').append(html);

                // Get the select element
                var $select = $('#transactions_tbody .account-select:last');

                // Build options for select2
                var options = '<option value="">{{ translate('Select Account') }}</option>';

                if (Object.keys(groupedAccountsData).length > 0) {
                    $.each(groupedAccountsData, function(groupName, accounts) {
                        options += `<optgroup label="${groupName}">`;
                        $.each(accounts, function(index, account) {
                            var selected = (accountId && account.id == accountId) ? 'selected' : '';
                            options +=
                                `<option value="${account.id}" ${selected}>${account.name} (${account.code})</option>`;
                        });
                        options += `</optgroup>`;
                    });
                    $select.html(options);
                } else {
                    // If no accounts loaded yet, show loading message
                    $select.html('<option value="">{{ translate('Loading accounts...') }}</option>');
                }

                // Initialize select2
                if ($.fn.select2) {
                    $select.select2({
                        placeholder: "{{ translate('Select Account') }}",
                        allowClear: true,
                        width: '100%'
                    });
                }

                // Set value if account exists
                if (accountId) {
                    $select.val(accountId).trigger('change');
                }

                rowCounter++;
            }

            updateTotals();
        }

        // New function to handle mutual exclusivity between debit and credit
        function toggleDebitCredit(inputElement) {
            var $input = $(inputElement);
            var $row = $input.closest('tr');
            var $debitInput = $row.find('.debit-amount');
            var $creditInput = $row.find('.credit-amount');
            var debitValue = parseFloat($debitInput.val()) || 0;
            var creditValue = parseFloat($creditInput.val()) || 0;

            if ($input.hasClass('debit-amount')) {
                // User is typing in Debit field
                if (debitValue > 0) {
                    // Disable Credit field
                    $creditInput.prop('readonly', true).css('background-color', '#f5f5f5');
                    $creditInput.val(0);
                } else {
                    // Enable Credit field
                    $creditInput.prop('readonly', false).css('background-color', '');
                }
            } else if ($input.hasClass('credit-amount')) {
                // User is typing in Credit field
                if (creditValue > 0) {
                    // Disable Debit field
                    $debitInput.prop('readonly', true).css('background-color', '#f5f5f5');
                    $debitInput.val(0);
                } else {
                    // Enable Debit field
                    $debitInput.prop('readonly', false).css('background-color', '');
                }
            }
        }

        function initializeSelect2($select) {
            // Build grouped options for select2
            var options = '<option value="">{{ translate('Select Account') }}</option>';

            if (Object.keys(groupedAccountsData).length > 0) {
                $.each(groupedAccountsData, function(groupName, accounts) {
                    options += `<optgroup label="${groupName}">`;
                    $.each(accounts, function(index, account) {
                        options +=
                            `<option value="${account.id}">${account.name} (${account.code})</option>`;
                    });
                    options += `</optgroup>`;
                });
            }

            $select.html(options);

            if ($.fn.select2) {
                $select.select2({
                    placeholder: "{{ translate('Select Account') }}",
                    allowClear: true,
                    width: '100%'
                });
            }
        }


        function removeTransactionRow(button) {
            var $row = $(button).closest('tr');
            var rowId = $row.data('row-id');

            if ($('#transactions_tbody tr').length > 1) {
                $row.remove();
                // Renumber rows
                $('#transactions_tbody tr').each(function(index) {
                    $(this).find('td:first').text(index + 1);
                    $(this).attr('data-row-id', index + 1);
                });
                updateTotals();
            } else {
                alert('{{ translate('At least one transaction row is required') }}');
            }
        }


        function loadExistingTransactions(transactions) {
            console.log('Loading existing transactions:', transactions);

            $('#transactions_tbody').empty();
            rowCounter = 0;
            var isJVorCV = (currentVoucherType === 'JV' || currentVoucherType === 'CV');
            var isPaymentReceipt = (currentVoucherType === 'CP' || currentVoucherType === 'BP' || currentVoucherType === 'CR' || currentVoucherType === 'BR');

            // For CP/BP/CR/BR only, the cash/bank line is managed by the
            // dedicated cash/bank dropdown, so hide those rows on edit.
            // JV and CV rows are ALL user-entered - never filter them out.
            var filteredTransactions = transactions;

            if (isPaymentReceipt) {
                filteredTransactions = transactions.filter(function(detail) {
                    var isCashBank = false;
                    if (detail.account && detail.account.type === 'asset') {
                        isCashBank = ['cash', 'bank', 'mobile_bank'].includes(detail.account.sub_type);
                    }
                    return !isCashBank;
                });
            }

            console.log('Filtered transactions:', filteredTransactions);
            console.log('Current voucher type:', currentVoucherType);
            console.log('Is JV or CV:', isJVorCV);

            if (isJVorCV) {
                // For JV/CV: Load all filtered transactions
                if (filteredTransactions.length > 0) {
                    $.each(filteredTransactions, function(index, detail) {
                        console.log('Adding row with:', {
                            account_id: detail.account_id,
                            debit: detail.debit,
                            credit: detail.credit,
                            narration: detail.narration
                        });

                        addTransactionRowWithData({
                            account_id: detail.account_id,
                            debit: detail.debit,
                            credit: detail.credit,
                            narration: detail.narration
                        });
                    });
                } else {
                    // No filtered transactions, add 2 empty rows
                    addTransactionRow();
                    addTransactionRow();
                }
            } else {
                // For CP/BP/CR/BR: Load ALL expense/income lines (a payment
                // voucher may contain multiple lines - previously only the
                // first line was loaded and the rest were silently lost)
                if (filteredTransactions.length > 0) {
                    $.each(filteredTransactions, function(index, detail) {
                        var amount = detail.debit > 0 ? detail.debit : detail.credit;
                        addTransactionRowWithData({
                            account_id: detail.account_id,
                            amount: amount,
                            narration: detail.narration
                        });
                    });
                } else {
                    addTransactionRow();
                }
            }

            updateTotals();
        }


        function addTransactionRowWithData(existingData) {
            var isJVorCV = (currentVoucherType === 'JV' || currentVoucherType === 'CV');
            var html = '';
            var currentRowCounter = rowCounter;

            if (isJVorCV) {
                // Journal/Contra Voucher - Both Debit and Credit columns
                var debitValue = existingData.debit || 0;
                var creditValue = existingData.credit || 0;
                var narrationValue = existingData.narration || '';
                var accountId = existingData.account_id || '';

                html = `<tr class="transaction-row" data-row-id="${currentRowCounter}">
            <td class="text-center">${currentRowCounter}</td>
            <td>
                <select name="account_ids[]" class="form-control account-select" style="width: 100%" required>
                    <option value="">{{ translate('Select Account') }}</option>
                </select>
            </td>
            <td class="text-right">
                <input type="number" step="0.01" name="debits[]" class="form-control text-right debit-amount" placeholder="0.00" onchange="updateTotals(); toggleDebitCredit(this)" value="${debitValue}">
            </td>
            <td class="text-right">
                <input type="number" step="0.01" name="credits[]" class="form-control text-right credit-amount" placeholder="0.00" onchange="updateTotals(); toggleDebitCredit(this)" value="${creditValue}">
            </td>
            <td>
                <input type="text" name="narrations[]" class="form-control" placeholder="{{ translate('Note') }}" value="${narrationValue}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeTransactionRow(this)">
                    <i class="las la-trash"></i>
                </button>
            </td>
        </tr>`;

                $('#transactions_tbody').append(html);

                // Get the newly added select element
                var $select = $('#transactions_tbody .account-select:last');

                // Build options and set value
                var options = '<option value="">{{ translate('Select Account') }}</option>';
                $.each(groupedAccountsData, function(groupName, accounts) {
                    options += `<optgroup label="${groupName}">`;
                    $.each(accounts, function(index, account) {
                        var selected = (accountId && account.id == accountId) ? 'selected' : '';
                        options +=
                            `<option value="${account.id}" ${selected}>${account.name} (${account.code})</option>`;
                    });
                    options += `</optgroup>`;
                });

                $select.html(options);

                if ($.fn.select2) {
                    $select.select2({
                        placeholder: "{{ translate('Select Account') }}",
                        allowClear: true,
                        width: '100%'
                    });
                }

                // Apply mutual exclusivity based on existing values
                var $row = $('#transactions_tbody tr:last');
                var $debitInput = $row.find('.debit-amount');
                var $creditInput = $row.find('.credit-amount');

                if (debitValue > 0) {
                    $creditInput.prop('readonly', true).css('background-color', '#f5f5f5');
                } else if (creditValue > 0) {
                    $debitInput.prop('readonly', true).css('background-color', '#f5f5f5');
                }

                // Increment row counter after adding
                rowCounter++;

            } else {
                // Payment/Receipt Voucher - Single Amount column
                var amountValue = existingData.amount || 0;
                var narrationValue = existingData.narration || '';
                var accountId = existingData.account_id || '';

                html = `<tr class="transaction-row" data-row-id="${currentRowCounter}">
            <td class="text-center">${currentRowCounter}</td>
            <td>
                <select name="account_ids[]" class="form-control account-select" style="width: 100%" required>
                    <option value="">{{ translate('Select Account') }}</option>
                </select>
            </td>
            <td class="text-right">
                <input type="number" step="0.01" name="amounts[]" class="form-control text-right transaction-amount" placeholder="0.00" onchange="updateTotals()" value="${amountValue}">
            </td>
            <td>
                <input type="text" name="narrations[]" class="form-control" placeholder="{{ translate('Note') }}" value="${narrationValue}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeTransactionRow(this)">
                    <i class="las la-trash"></i>
                </button>
            </td>
        </tr>`;

                $('#transactions_tbody').append(html);

                // Get the newly added select element
                var $select = $('#transactions_tbody .account-select:last');

                // Build options and set value
                var options = '<option value="">{{ translate('Select Account') }}</option>';
                $.each(groupedAccountsData, function(groupName, accounts) {
                    options += `<optgroup label="${groupName}">`;
                    $.each(accounts, function(index, account) {
                        var selected = (accountId && account.id == accountId) ? 'selected' : '';
                        options +=
                            `<option value="${account.id}" ${selected}>${account.name} (${account.code})</option>`;
                    });
                    options += `</optgroup>`;
                });

                $select.html(options);

                if ($.fn.select2) {
                    $select.select2({
                        placeholder: "{{ translate('Select Account') }}",
                        allowClear: true,
                        width: '100%'
                    });
                }

                // Increment row counter after adding
                rowCounter++;
            }
        }


        function addTransactionRowWithExistingData(existingData) {
            rowCounter++;
            var isJVorCV = (currentVoucherType === 'JV' || currentVoucherType === 'CV');
            var html = '';

            if (isJVorCV) {
                // Journal/Contra Voucher - Both Debit and Credit columns
                var debitValue = existingData.debit || 0;
                var creditValue = existingData.credit || 0;
                var narrationValue = existingData.narration || '';
                var accountId = existingData.account_id || '';

                html = `<tr class="transaction-row" data-row-id="${rowCounter}">
            <td class="text-center">${rowCounter}</td>
            <td>
                <select name="account_ids[]" class="form-control account-select" style="width: 100%" required>
                    <option value="">{{ translate('Select Account') }}</option>
                </select>
            </td>
            <td class="text-right">
                <input type="number" step="0.01" name="debits[]" class="form-control text-right debit-amount" placeholder="0.00" onchange="updateTotals()" value="${debitValue}">
            </td>
            <td class="text-right">
                <input type="number" step="0.01" name="credits[]" class="form-control text-right credit-amount" placeholder="0.00" onchange="updateTotals()" value="${creditValue}">
            </td>
            <td>
                <input type="text" name="narrations[]" class="form-control" placeholder="{{ translate('Note') }}" value="${narrationValue}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeTransactionRow(this)">
                    <i class="las la-trash"></i>
                </button>
            </td>
        </tr>`;

                $('#transactions_tbody').append(html);

                // Set the selected account value after select2 initialization
                var $select = $('#transactions_tbody .account-select:last');
                initializeSelect2WithValue($select, accountId);

            } else {
                // Payment/Receipt Voucher - Single Amount column
                var amountValue = existingData.amount || 0;
                var narrationValue = existingData.narration || '';
                var accountId = existingData.account_id || '';

                html = `<tr class="transaction-row" data-row-id="${rowCounter}">
            <td class="text-center">${rowCounter}</td>
            <td>
                <select name="account_ids[]" class="form-control account-select" style="width: 100%" required>
                    <option value="">{{ translate('Select Account') }}</option>
                </select>
            </td>
            <td class="text-right">
                <input type="number" step="0.01" name="amounts[]" class="form-control text-right transaction-amount" placeholder="0.00" onchange="updateTotals()" value="${amountValue}">
            </td>
            <td>
                <input type="text" name="narrations[]" class="form-control" placeholder="{{ translate('Note') }}" value="${narrationValue}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeTransactionRow(this)">
                    <i class="las la-trash"></i>
                </button>
            </td>
        </tr>`;

                $('#transactions_tbody').append(html);

                // Set the selected account value after select2 initialization
                var $select = $('#transactions_tbody .account-select:last');
                initializeSelect2WithValue($select, accountId);
            }
        }


        function initializeSelect2WithValue($select, selectedValue) {
            // Build grouped options for select2
            var options = '<option value="">{{ translate('Select Account') }}</option>';

            $.each(groupedAccountsData, function(groupName, accounts) {
                options += `<optgroup label="${groupName}">`;
                $.each(accounts, function(index, account) {
                    var selected = (selectedValue && account.id == selectedValue) ? 'selected' : '';
                    options +=
                        `<option value="${account.id}" ${selected}>${account.name} (${account.code})</option>`;
                });
                options += `</optgroup>`;
            });

            $select.html(options);

            // Initialize select2
            if ($.fn.select2) {
                $select.select2({
                    placeholder: "{{ translate('Select Account') }}",
                    allowClear: true,
                    width: '100%'
                });
            }

            // Ensure the value is set
            if (selectedValue) {
                $select.val(selectedValue).trigger('change');
            }
        }



        function updateTotals() {
            var isJVorCV = (currentVoucherType === 'JV' || currentVoucherType === 'CV');

            if (isJVorCV) {
                var totalDebit = 0;
                var totalCredit = 0;

                $('.debit-amount').each(function() {
                    totalDebit += parseFloat($(this).val()) || 0;
                });

                $('.credit-amount').each(function() {
                    totalCredit += parseFloat($(this).val()) || 0;
                });

                var totalAmount = totalDebit;
                $('#total_amount').text(totalAmount.toFixed(2));

                if (totalDebit !== totalCredit && (totalDebit > 0 || totalCredit > 0)) {
                    $('#details_error').show();
                    $('#save_btn').prop('disabled', true);
                } else {
                    $('#details_error').hide();
                    $('#save_btn').prop('disabled', false);
                }
            } else {
                var totalAmount = 0;
                $('.transaction-amount').each(function() {
                    totalAmount += parseFloat($(this).val()) || 0;
                });
                $('#total_amount').text(totalAmount.toFixed(2));
                $('#details_error').hide();
                $('#save_btn').prop('disabled', false);
            }
        }

        $('#voucherForm').on('submit', function(e) {
            e.preventDefault();

            var voucherType = $('#voucher_type').val();
            if (!voucherType) {
                // For edit mode, get value from hidden input or disabled select
                @if (isset($voucher))
                    voucherType = '{{ $voucher->type }}';
                @else
                    alert('{{ translate('Please select voucher type') }}');
                    return false;
                @endif
            }

            var hasAmount = false;
            var isJVorCV = (voucherType === 'JV' || voucherType === 'CV');

            if (isJVorCV) {
                $('.debit-amount, .credit-amount').each(function() {
                    if (parseFloat($(this).val()) > 0) {
                        hasAmount = true;
                    }
                });
            } else {
                $('.transaction-amount').each(function() {
                    if (parseFloat($(this).val()) > 0) {
                        hasAmount = true;
                    }
                });
            }

            if (!hasAmount) {
                alert('{{ translate('Please add at least one transaction with amount') }}');
                return false;
            }

            if (isJVorCV) {
                var totalDebit = 0,
                    totalCredit = 0;
                $('.debit-amount').each(function() {
                    totalDebit += parseFloat($(this).val()) || 0;
                });
                $('.credit-amount').each(function() {
                    totalCredit += parseFloat($(this).val()) || 0;
                });

                if (totalDebit !== totalCredit) {
                    alert('{{ translate('Total debit must equal total credit') }}');
                    return false;
                }

                if (totalDebit === 0) {
                    alert('{{ translate('Please enter transaction amounts') }}');
                    return false;
                }
            } else {
                // For CP/BP/CR/BR: Also validate that cash/bank account is selected
                var cashBankAccountId = $('#cash_bank_account_id').val();
                @if (isset($voucher))
                    // For edit mode, get from hidden input if select is disabled
                    if (!cashBankAccountId) {
                        cashBankAccountId = $('#hidden_cash_bank_account_id').val();
                    }
                @endif

                if (!cashBankAccountId) {
                    alert('{{ translate('Please select Cash/Bank account') }}');
                    return false;
                }

                var totalAmount = 0;
                $('.transaction-amount').each(function() {
                    totalAmount += parseFloat($(this).val()) || 0;
                });

                if (totalAmount === 0) {
                    alert('{{ translate('Please enter amount') }}');
                    return false;
                }
            }

            // Enable disabled fields before submit so their values are sent
            $('#voucher_type').prop('disabled', false);
            $('#cash_bank_account_id').prop('disabled', false);

            this.submit();
        });

        function ucfirst(str) {
            if (!str) return str;
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
    </script>
@endsection
