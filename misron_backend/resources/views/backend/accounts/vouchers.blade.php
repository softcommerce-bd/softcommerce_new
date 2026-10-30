<div class="card shadow-sm border-0">

    <!-- Header -->
    <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center">

        <h5 class="mb-2 mb-md-0 fw-600">
            <i class="las la-receipt text-primary mr-2"></i>
            {{ translate('Voucher List') }}
        </h5>

        <button class="btn-account-color btn-sm px-3 rounded-pill" onclick="openVoucherModal()">
            <i class="las la-plus"></i>
            {{ translate('New Voucher') }}
        </button>

    </div>

    <!-- Filters -->
    <div class="card-body border-bottom">
        <div class="row">
            <div class="col-md-3 mb-3">
                <label>{{ translate('Voucher No') }}</label>
                <input type="text" id="filter_voucher_no" class="form-control" placeholder="Search voucher no...">
            </div>

            <div class="col-md-3 mb-3">
                <label>{{ translate('Voucher Type') }}</label>
                <select id="filter_voucher_type" class="form-control">
                    <option value="">{{ translate('All Types') }}</option>
                    <option value="CP">{{ translate('Cash Payment (CP)') }}</option>
                    <option value="CR">{{ translate('Cash Receipt (CR)') }}</option>
                    <option value="BP">{{ translate('Bank Payment (BP)') }}</option>
                    <option value="BR">{{ translate('Bank Receipt (BR)') }}</option>
                    <option value="JV">{{ translate('Journal Voucher (JV)') }}</option>
                    <option value="CV">{{ translate('Contra Voucher (CV)') }}</option>
                </select>
            </div>

            <div class="col-md-3 mb-3">
                <label>{{ translate('Date Range') }}</label>
                <input type="text" class="form-control aiz-date-range" id="filter_date_range"
                    placeholder="{{ translate('Select date range') }}">
            </div>

            <div class="col-md-3 mb-3 d-flex align-items-end">
                <button class="btn-account-color btn-block" onclick="filterVouchers()">
                    <i class="las la-search"></i> {{ translate('Search') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Desktop Table -->
    <div class="table-responsive d-none d-lg-block">
        <table class="table table-hover mb-0">
            <thead class="bg-account-color text-white">
                <tr>
                    <th class="sortable" onclick="sortBy('date')">
                        {{ translate('Date') }} <i class="las la-arrow-up"></i>
                    </th>
                    <th class="sortable" onclick="sortBy('voucher_no')">
                        {{ translate('Voucher No') }} <i class="las la-arrow-up"></i>
                    </th>
                    <th class="sortable" onclick="sortBy('voucher_type')">
                        {{ translate('Tran. Type') }} <i class="las la-arrow-up"></i>
                    </th>
                    <th class="text-right sortable" onclick="sortBy('amount')">
                        {{ translate('Amount') }} <i class="las la-arrow-up"></i>
                    </th>
                    <th>{{ translate('Note') }}</th>
                    <th class="text-right" width="120">{{ translate('Action') }}</th>
                </tr>
            </thead>
            <tbody id="vouchers_table_body">
                @forelse($vouchers ?? [] as $index => $voucher)
                    <tr class="panel-list-items">
                        <td>{{ $voucher->date->format('d-m-Y') }}</td>
                        <td><strong class="text-primary">{{ $voucher->voucher_no }}</strong></td>
                        <td>
                            <span class="badge badge-soft-info px-3 py-2">
                                {{ $voucher->type_label }}
                            </span>
                        </td>
                        <td class="text-right">
                            <strong class="text-success">{{ number_format($voucher->amount, 2) }} ৳</strong>
                        </td>
                        <td>{{ Str::limit($voucher->description, 50) ?? '-' }}</td>
                        <td class="text-right">
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm" data-toggle="dropdown">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item" href="javascript:void(0)"
                                        onclick="printVoucher({{ $voucher->id }})">
                                        <i class="las la-print mr-2"></i> {{ translate('Print') }}
                                    </a>
                                    <a class="dropdown-item" href="javascript:void(0)"
                                        onclick="editVoucher({{ $voucher->id }})">
                                        <i class="las la-edit mr-2"></i> {{ translate('Edit') }}
                                    </a>
                                    <a class="dropdown-item text-danger" href="javascript:void(0)"
                                        onclick="deleteVoucher({{ $voucher->id }})">
                                        <i class="las la-trash mr-2"></i> {{ translate('Delete') }}
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="las la-receipt la-3x text-muted mb-3 d-block"></i>
                            <p class="text-muted mb-0">{{ translate('No vouchers found') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Mobile Card View -->
    <div class="d-block d-lg-none p-3" id="mobile_vouchers_container">
        @forelse($vouchers ?? [] as $voucher)
            <div class="card border shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="mb-1">{{ $voucher->voucher_no }}</h6>
                            <small class="text-muted">{{ $voucher->date->format('d M Y') }}</small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm" data-toggle="dropdown">
                                <i class="fa fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="javascript:void(0)"
                                    onclick="printVoucher({{ $voucher->id }})">
                                    <i class="las la-print mr-2"></i> {{ translate('Print') }}
                                </a>
                                <a class="dropdown-item" href="javascript:void(0)"
                                    onclick="editVoucher({{ $voucher->id }})">
                                    <i class="las la-edit mr-2"></i> {{ translate('Edit') }}
                                </a>
                                <a class="dropdown-item text-danger" href="javascript:void(0)"
                                    onclick="deleteVoucher({{ $voucher->id }})">
                                    <i class="las la-trash mr-2"></i> {{ translate('Delete') }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">{{ translate('Type') }}</small>
                            <strong>{{ $voucher->type_label }}</strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">{{ translate('Amount') }}</small>
                            <strong class="text-success">{{ number_format($voucher->amount, 2) }} ৳</strong>
                        </div>
                        <div class="col-12">
                            <small class="text-muted d-block">{{ translate('Note') }}</small>
                            <small>{{ Str::limit($voucher->description, 80) ?? '-' }}</small>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-5">
                <i class="las la-receipt la-3x text-muted mb-3 d-block"></i>
                <p class="text-muted">{{ translate('No vouchers found') }}</p>
            </div>
        @endforelse
    </div>

    <!-- Footer with Pagination -->
    <div class="card-footer bg-white">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div class="mb-2 mb-md-0">
                <strong>{{ translate('Total Vouchers') }}: <span
                        id="total_count">{{ ($vouchers ?? collect())->count() }}</span></strong>
            </div>
            <div id="pagination_links">
                {{ ($vouchers ?? collect())->links() }}
            </div>
        </div>
    </div>

</div>

<!-- Create New Voucher Modal -->
<div class="modal fade" id="voucherModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom px-4">
                <p class="title mb-0 fw-600">
                    <i class="las la-receipt text-primary mr-2"></i>
                    {{ translate('Create New Voucher') }}
                </p>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-4">
                <form id="voucherForm" class="form-eud" autocomplete="off">
                    @csrf
                    <input type="hidden" name="voucher_id" id="voucher_id">

                    <!-- Form Row 1 -->
                    <div class="form-row">
                        <!-- Date Field -->
                        <div class="col-lg-2 col-6 cpr form-label-group form-group">
                            <label class="font-medium label-date" for="voucherDate">
                                {{ translate('Date') }} <span class="text-danger">*</span>
                            </label>
                            <div class="datepicker-wrapper">
                                <input type="date" id="voucherDate" name="date"
                                    class="form-control without-focus" ng-model="transaction.Date"
                                    value="{{ date('Y-m-d') }}" required>
                            </div>
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
                                <select name="voucher_type" id="voucher_type" class="form-control select2"
                                    style="width: 100%" required onchange="onVoucherTypeChange()">
                                    <option value="">{{ translate('Select Voucher Type') }}</option>
                                    <option value="CP">{{ translate('Cash Payment (CP)') }}</option>
                                    <option value="BP">{{ translate('Bank Payment (BP)') }}</option>
                                    <option value="CR">{{ translate('Cash Receive (CR)') }}</option>
                                    <option value="BR">{{ translate('Bank Receive (BR)') }}</option>
                                    <option value="JV">{{ translate('Journal Voucher (JV)') }}</option>
                                    <option value="CV">{{ translate('Contra Voucher (CV)') }}</option>
                                </select>
                                <div class="invalid-feedback">
                                    {{ translate('Voucher Type is required') }}
                                </div>
                            </div>
                        </div>

                        <!-- Cash/Bank Account (Conditional) -->
                        <div class="col-lg-3 col-6 cpl" id="cash_bank_account_div" style="display: none;">
                            <div class="form-group cst">
                                <label class="cs-select-lbl d-block" id="cash_bank_label">
                                    {{ translate('Cash Account') }}
                                </label>
                                <select name="cash_bank_account_id" id="cash_bank_account_id"
                                    class="form-control select2" style="width: 100%">
                                    <option value="">{{ translate('Select Account') }}</option>
                                    @foreach ($cashBankAccounts ?? [] as $account)
                                        <option value="{{ $account->id }}"
                                            data-balance="{{ $account->balance ?? 0 }}">
                                            {{ $account->name }} (Balance:
                                            {{ number_format($account->balance ?? 0, 2) }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback">
                                    {{ translate('Account is required') }}
                                </div>
                            </div>
                        </div>

                        <!-- Cost Center (Optional) -->
                        <div class="col-lg-3 col-6 cpl" id="cost_center_div" style="display: none;">
                            <div class="form-group cst">
                                <label class="cs-select-lbl d-block">
                                    {{ translate('Cost Center') }}
                                </label>
                                <select name="cost_center_id" id="cost_center_id" class="form-control select2"
                                    style="width: 100%">
                                    <option value="">{{ translate('All Cost Center') }}</option>
                                    @foreach ($costCenters ?? [] as $center)
                                        <option value="{{ $center->id }}">{{ $center->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Details Section -->
                    <div class="w-100 panel-elem pb-4" id="payment_details_section" style="display: none;">
                        <div class="w-100 pt-3">
                            <h6 class="font-15">
                                {{ translate('Payment Details') }} <span class="text-danger">*</span>
                            </h6>
                            <span class="text-danger" id="details_error" style="display: none;">
                                {{ translate('Details are required') }}
                            </span>
                        </div>

                        <!-- Transactions Table -->
                        <div class="w-100 panel-elem pb-4">
                            <table class="table">
                                <thead>
                                    <tr class="panel-heading d-flex flex-wrap">
                                        <th class="col-5 pl-2" scope="col">
                                            <span class="lbl">{{ translate('Accounts') }} <span
                                                    class="text-danger">*</span></span>
                                        </th>
                                        <th class="col-2" scope="col" id="amount_header">
                                            <span class="lbl">{{ translate('Amount (৳)') }} <span
                                                    class="text-danger">*</span></span>
                                        </th>
                                        <th class="col-3 text-left" scope="col">
                                            <span class="lbl">{{ translate('Note') }}</span>
                                        </th>
                                        <th class="text-right col-2" scope="col">
                                            <span class="lbl">{{ translate('Action') }}</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="transactions_tbody">
                                    <!-- Dynamic rows will be added here -->
                                </tbody>
                                <tfoot>
                                    <tr class="panel-list-items d-flex flex-wrap">
                                        <td class="col-5 text-right">
                                            <span class="font-14">{{ translate('Total (৳):') }}</span>
                                        </td>
                                        <td class="col-2" id="total_amount_display">
                                            <strong class="text-success">0.00</strong>
                                        </td>
                                        <td class="col-3"></td>
                                        <td class="text-right col-2">
                                            <button class="btn text-white btn-add-panel-item" type="button"
                                                onclick="addTransactionRow()">
                                                + {{ translate('Add') }}
                                            </button>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Voucher Narration / Note -->
                        <div class="w-100 pt-3">
                            <div class="col px-1 mr-auto">
                                <div class="form-label-group form-group">
                                    <textarea rows="2" class="form-control bg-transparent px-0 without-focus" name="narration"
                                        id="voucher_narration" placeholder="{{ translate('Note') }}"></textarea>
                                    <label class="font-medium" for="voucher_narration">
                                        {{ translate('Note') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Save and Cancel Buttons -->
                        <div class="d-flex act-bt justify-content-start border-top pt-3 mt-4">
                            <button type="submit"
                                class="btn mr-4 py-1 text-white bg-color-lightseagreen without-focus">
                                {{ translate('Save') }}
                            </button>
                            <button type="button" class="btn text-white bg-color-red py-1 without-focus"
                                data-dismiss="modal">
                                {{ translate('Cancel') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Voucher Modal -->
<div class="modal fade" id="viewVoucherModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <p class="title mb-0 fw-600">{{ translate('Voucher Details') }}</p>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="view_voucher_content"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-dismiss="modal">{{ translate('Close') }}</button>
                <button type="button" class="btn btn-primary"
                    onclick="window.print()">{{ translate('Print') }}</button>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-color-lightseagreen {
        background-color: #20b2aa;
        border-color: #20b2aa;
    }

    .bg-color-lightseagreen:hover {
        background-color: #1a9e97;
        border-color: #1a9e97;
    }

    .bg-color-red {
        background-color: #dc3545;
        border-color: #dc3545;
    }

    .bg-color-red:hover {
        background-color: #c82333;
        border-color: #c82333;
    }

    .btn-add-panel-item {
        background-color: #28a745;
        border-color: #28a745;
        padding: 4px 12px;
        font-size: 13px;
        border-radius: 4px;
    }

    .btn-add-panel-item:hover {
        background-color: #218838;
        border-color: #218838;
    }

    .panel-list-items {
        border-bottom: 1px solid #e9ecef;
        padding: 8px 0;
    }

    .panel-heading {
        background-color: #f8f9fa;
        padding: 10px 0;
        border-radius: 4px;
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
</style>

{{-- <script>
    // Store transaction entries
    var transactionEntries = [];
    var rowCounter = 0;

    // Initialize when modal opens
    $('#voucherModal').on('shown.bs.modal', function() {
        if ($('.select2').length > 0) {
            $('.select2').select2({
                dropdownParent: $('#voucherModal')
            });
        }
    });

    function onVoucherTypeChange() {
        var voucherType = $('#voucher_type').val();
        var cashBankDiv = $('#cash_bank_account_div');
        var costCenterDiv = $('#cost_center_div');
        var paymentSection = $('#payment_details_section');
        var cashBankLabel = $('#cash_bank_label');

        // Reset
        cashBankDiv.hide();
        costCenterDiv.hide();
        paymentSection.hide();
        $('#cash_bank_account_id').removeAttr('required');

        if (!voucherType) return;

        paymentSection.show();

        // Handle Cash/Bank account based on voucher type
        if (voucherType === 'CP') {
            cashBankLabel.text('{{ translate('Cash Account') }}');
            cashBankDiv.show();
            $('#cash_bank_account_id').attr('required', true);
            // Initialize with default debit/credit structure
            initTransactionEntries('payment');
        } else if (voucherType === 'BP') {
            cashBankLabel.text('{{ translate('Bank Account') }}');
            cashBankDiv.show();
            $('#cash_bank_account_id').attr('required', true);
            initTransactionEntries('payment');
        } else if (voucherType === 'CR') {
            cashBankLabel.text('{{ translate('Cash Account') }}');
            cashBankDiv.show();
            $('#cash_bank_account_id').attr('required', true);
            initTransactionEntries('receipt');
        } else if (voucherType === 'BR') {
            cashBankLabel.text('{{ translate('Bank Account') }}');
            cashBankDiv.show();
            $('#cash_bank_account_id').attr('required', true);
            initTransactionEntries('receipt');
        } else if (voucherType === 'JV') {
            cashBankDiv.hide();
            initTransactionEntries('journal');
        } else if (voucherType === 'CV') {
            cashBankLabel.text('{{ translate('From Account') }}');
            cashBankDiv.show();
            initTransactionEntries('contra');
        }

        // Show cost center if multiple exist
        @if (isset($costCenters) && count($costCenters) > 1)
            costCenterDiv.show();
        @endif
    }

    function initTransactionEntries(type) {
        transactionEntries = [];
        rowCounter = 0;
        $('#transactions_tbody').empty();

        if (type === 'payment') {
            // Payment: Credit Cash/Bank, Debit Expense/Asset
            addTransactionRow('debit');
            addTransactionRow('credit');
        } else if (type === 'receipt') {
            // Receipt: Debit Cash/Bank, Credit Income
            addTransactionRow('debit');
            addTransactionRow('credit');
        } else if (type === 'journal') {
            // Journal: Two rows with both debit/credit columns
            addTransactionRow('journal');
            addTransactionRow('journal');
        } else if (type === 'contra') {
            // Contra: Debit one bank, Credit another bank
            addTransactionRow('debit');
            addTransactionRow('credit');
        }
    }

    function addTransactionRow(rowType = 'both') {
        rowCounter++;
        var html = '';

        if (rowType === 'journal') {
            html = `<tr class="panel-list-items d-flex flex-wrap" data-row-id="${rowCounter}">
            <td class="col-5 pl-2">
                <div class="form-group cst mb-0 w-100">
                    <select name="account_ids[]" class="form-control account-select select2" style="width: 100%" required>
                        <option value="">{{ translate('Select Account') }}</option>
                        @foreach ($allAccounts ?? [] as $account)
                            <option value="{{ $account->id }}" data-type="{{ $account->account_type }}">
                                {{ $account->name }} ({{ $account->code ?? '' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </td>
            <td class="col-2 pl-2">
                <div class="form-group mb-0">
                    <div class="row">
                        <div class="col-6 pr-1">
                            <input type="number" step="0.01" name="debits[]" class="form-control text-right debit-amount" placeholder="Debit" onchange="updateTotal()">
                        </div>
                        <div class="col-6 pl-1">
                            <input type="number" step="0.01" name="credits[]" class="form-control text-right credit-amount" placeholder="Credit" onchange="updateTotal()">
                        </div>
                    </div>
                </div>
            </td>
            <td class="col-3 text-left">
                <div class="form-group mb-0">
                    <textarea rows="1" class="form-control bg-transparent px-0 without-focus" name="narrations[]" placeholder="{{ translate('Note') }}"></textarea>
                </div>
            </td>
            <td class="text-right col-2">
                <button class="btn without-focus border-0 px-1 py-0" type="button" onclick="removeTransactionRow(this)">
                    <i class="las la-trash text-danger"></i>
                </button>
            </td>
        </tr>`;
        } else {
            var amountLabel = (rowType === 'debit') ? 'Debit Amount' : 'Credit Amount';
            var amountField = (rowType === 'debit') ? 'debit_amounts[]' : 'credit_amounts[]';
            var amountClass = (rowType === 'debit') ? 'debit-amount' : 'credit-amount';
            var otherField = (rowType === 'debit') ? 'credit_amounts[]' : 'debit_amounts[]';

            html = `<tr class="panel-list-items d-flex flex-wrap" data-row-id="${rowCounter}">
            <td class="col-5 pl-2">
                <div class="form-group cst mb-0 w-100">
                    <select name="account_ids[]" class="form-control account-select select2" style="width: 100%" required>
                        <option value="">{{ translate('Select Account') }}</option>
                        @foreach ($allAccounts ?? [] as $account)
                            <option value="{{ $account->id }}" data-type="{{ $account->account_type }}">
                                ${accountName}
                            </option>
                        @endforeach
                    </select>
                </div>
            </td>
            <td class="col-2 pl-2">
                <div class="form-group mb-0">
                    <input type="hidden" name="${otherField}" value="0">
                    <input type="number" step="0.01" name="${amountField}" class="form-control text-right ${amountClass}" placeholder="${amountLabel}" onchange="updateTotal()" required>
                </div>
            </td>
            <td class="col-3 text-left">
                <div class="form-group mb-0">
                    <textarea rows="1" class="form-control bg-transparent px-0 without-focus" name="narrations[]" placeholder="{{ translate('Note') }}"></textarea>
                </div>
            </td>
            <td class="text-right col-2">
                <button class="btn without-focus border-0 px-1 py-0" type="button" onclick="removeTransactionRow(this)">
                    <i class="las la-trash text-danger"></i>
                </button>
            </td>
        </tr>`;
        }

        $('#transactions_tbody').append(html);

        // Initialize select2 for new selects
        if ($.fn.select2) {
            $('#transactions_tbody .select2').select2({
                dropdownParent: $('#voucherModal')
            });
        }

        updateTotal();
    }

    function removeTransactionRow(button) {
        if ($('#transactions_tbody tr').length > 1) {
            $(button).closest('tr').remove();
            updateTotal();
        } else {
            showNotification('warning', '{{ translate('At least one transaction row is required') }}');
        }
    }

    function updateTotal() {
        var totalDebit = 0;
        var totalCredit = 0;

        $('.debit-amount').each(function() {
            var val = parseFloat($(this).val()) || 0;
            totalDebit += val;
        });

        $('.credit-amount').each(function() {
            var val = parseFloat($(this).val()) || 0;
            totalCredit += val;
        });

        var totalAmount = Math.max(totalDebit, totalCredit);
        $('#total_amount_display').html('<strong class="text-success">' + totalAmount.toFixed(2) + '</strong>');

        // For payment/receipt types, also update the cash/bank amount
        var voucherType = $('#voucher_type').val();
        if (voucherType === 'CP' || voucherType === 'BP') {
            // Total amount should match the payment amount
            var total = totalDebit; // Payment: Debit expense = total
            $('#voucher_amount_hidden').val(total);
        } else if (voucherType === 'CR' || voucherType === 'BR') {
            var total = totalCredit; // Receipt: Credit income = total
            $('#voucher_amount_hidden').val(total);
        }

        // Validate balance
        if (totalDebit !== totalCredit && totalDebit > 0 && totalCredit > 0) {
            $('#details_error').show();
            $('#total_amount_display').find('strong').css('color', 'red');
        } else {
            $('#details_error').hide();
            $('#total_amount_display').find('strong').css('color', 'green');
        }
    }

    $('#voucherForm').on('submit', function(e) {
        e.preventDefault();

        var voucherType = $('#voucher_type').val();
        if (!voucherType) {
            showNotification('danger', '{{ translate('Please select voucher type') }}');
            return;
        }

        // Validate at least one transaction row
        if ($('#transactions_tbody tr').length === 0) {
            showNotification('danger', '{{ translate('Please add at least one transaction') }}');
            return;
        }

        // Validate debit equals credit for journal
        if (voucherType === 'JV') {
            var totalDebit = 0,
                totalCredit = 0;
            $('.debit-amount').each(function() {
                totalDebit += parseFloat($(this).val()) || 0;
            });
            $('.credit-amount').each(function() {
                totalCredit += parseFloat($(this).val()) || 0;
            });

            if (totalDebit !== totalCredit) {
                showNotification('danger', '{{ translate('Total debit must equal total credit') }}');
                return;
            }
        }

        var formData = new FormData(this);

        $.ajax({
            url: '',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#voucherModal').modal('hide');
                    if (typeof location !== 'undefined') {
                        location.reload();
                    }
                    showNotification('success', response.message);
                } else {
                    showNotification('danger', response.message);
                }
            },
            error: function(xhr) {
                var message = xhr.responseJSON?.message ||
                    '{{ translate('Error processing voucher') }}';
                if (xhr.status === 422 && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    for (var field in errors) {
                        showNotification('danger', errors[field][0]);
                    }
                } else {
                    showNotification('danger', message);
                }
            }
        });
    });

    function openVoucherModal() {
        $('#voucherForm')[0].reset();
        $('#voucher_id').val('');
        $('#voucher_type').val('').trigger('change');
        $('#cash_bank_account_id').val('').trigger('change');
        $('#voucher_narration').val('');
        $('#transactions_tbody').empty();
        transactionEntries = [];
        rowCounter = 0;
        $('#payment_details_section').hide();
        $('#voucherModal').modal('show');
    }

    function editVoucher(id) {
        $.ajax({
            url: '/',
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    var voucher = response.data;
                    $('#voucher_id').val(voucher.id);
                    $('#voucher_date').val(voucher.date);
                    $('#voucher_type').val(voucher.voucher_type).trigger('change');
                    $('#voucher_narration').val(voucher.narration);

                    // Wait for type change to initialize rows
                    setTimeout(function() {
                        // Populate transaction rows
                        $('#transactions_tbody').empty();
                        if (voucher.transactions && voucher.transactions.length) {
                            $.each(voucher.transactions, function(index, trans) {
                                addTransactionRowWithData(trans);
                            });
                        }
                        updateTotal();
                    }, 500);

                    $('#voucherModal').modal('show');
                }
            }
        });
    }

    function addTransactionRowWithData(transaction) {
        rowCounter++;
        var isJournal = $('#voucher_type').val() === 'JV';

        var html = `<tr class="panel-list-items d-flex flex-wrap" data-row-id="${rowCounter}">
        <td class="col-5 pl-2">
            <div class="form-group cst mb-0 w-100">
                <select name="account_ids[]" class="form-control account-select select2" style="width: 100%" required>
                    <option value="">{{ translate('Select Account') }}</option>`;

        @foreach ($allAccounts ?? [] as $account)
            html += `<option value="{{ $account->id }}" ${transaction.account_id == {{ $account->id }} ? 'selected' : ''}>
                {{ $account->name }} ({{ $account->code ?? '' }})
            </option>`;
        @endforeach

        html += `</select>
            </div>
        </td>`;

        if (isJournal) {
            html += `<td class="col-2 pl-2">
            <div class="form-group mb-0">
                <div class="row">
                    <div class="col-6 pr-1">
                        <input type="number" step="0.01" name="debits[]" class="form-control text-right debit-amount" value="${transaction.debit || 0}" placeholder="Debit" onchange="updateTotal()">
                    </div>
                    <div class="col-6 pl-1">
                        <input type="number" step="0.01" name="credits[]" class="form-control text-right credit-amount" value="${transaction.credit || 0}" placeholder="Credit" onchange="updateTotal()">
                    </div>
                </div>
            </div>
        </td>`;
        } else {
            var isDebit = transaction.debit > 0;
            html += `<td class="col-2 pl-2">
            <div class="form-group mb-0">
                <input type="hidden" name="${isDebit ? 'credit_amounts[]' : 'debit_amounts[]'}" value="0">
                <input type="number" step="0.01" name="${isDebit ? 'debit_amounts[]' : 'credit_amounts[]'}" class="form-control text-right ${isDebit ? 'debit-amount' : 'credit-amount'}" value="${isDebit ? transaction.debit : transaction.credit}" placeholder="Amount" onchange="updateTotal()" required>
            </div>
        </td>`;
        }

        html += `<td class="col-3 text-left">
        <div class="form-group mb-0">
            <textarea rows="1" class="form-control bg-transparent px-0 without-focus" name="narrations[]" placeholder="{{ translate('Note') }}">${transaction.note || ''}</textarea>
        </div>
    </td>
    <td class="text-right col-2">
        <button class="btn without-focus border-0 px-1 py-0" type="button" onclick="removeTransactionRow(this)">
            <i class="las la-trash text-danger"></i>
        </button>
    </td>
    </tr>`;

        $('#transactions_tbody').append(html);

        if ($.fn.select2) {
            $('#transactions_tbody .select2').select2({
                dropdownParent: $('#voucherModal')
            });
        }
    }

    function deleteVoucher(id) {
        if (confirm('{{ translate('Are you sure? This action cannot be undone.') }}')) {
            $.ajax({
                url: '',
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                        showNotification('success', response.message);
                    }
                }
            });
        }
    }

    function showNotification(type, message) {
        if (typeof AIZ !== 'undefined' && AIZ.plugins.notify) {
            AIZ.plugins.notify(type, message);
        } else {
            alert(message);
        }
    }
</script> --}}

<script>
var rowCounter = 2;
var voucherType = '';

$(document).ready(function() {
    // Initialize select2
    $('.select2').select2({
        dropdownParent: $('#voucherModal')
    });
});

function onVoucherTypeChange() {
    voucherType = $('#voucher_type').val();
    var cashBankDiv = $('#cash_bank_account_div');
    var costCenterDiv = $('#cost_center_div');
    var paymentSection = $('#payment_details_section');
    var cashBankLabel = $('#cash_bank_label');
    
    cashBankDiv.hide();
    costCenterDiv.hide();
    paymentSection.hide();
    $('#cash_bank_account_id').removeAttr('required');
    
    if (!voucherType) return;
    
    paymentSection.show();
    
    // Set label and show appropriate fields
    if (voucherType === 'CP') {
        cashBankLabel.text('{{ translate("Cash Account") }}');
        cashBankDiv.show();
        $('#cash_bank_account_id').attr('required', true);
        initTransactionEntries('payment');
    } 
    else if (voucherType === 'BP') {
        cashBankLabel.text('{{ translate("Bank Account") }}');
        cashBankDiv.show();
        $('#cash_bank_account_id').attr('required', true);
        initTransactionEntries('payment');
    }
    else if (voucherType === 'CR') {
        cashBankLabel.text('{{ translate("Cash Account") }}');
        cashBankDiv.show();
        $('#cash_bank_account_id').attr('required', true);
        initTransactionEntries('receipt');
    }
    else if (voucherType === 'BR') {
        cashBankLabel.text('{{ translate("Bank Account") }}');
        cashBankDiv.show();
        $('#cash_bank_account_id').attr('required', true);
        initTransactionEntries('receipt');
    }
    else if (voucherType === 'JV') {
        cashBankDiv.hide();
        initTransactionEntries('journal');
    }
    else if (voucherType === 'CV') {
        cashBankLabel.text('{{ translate("From Account") }}');
        cashBankDiv.show();
        initTransactionEntries('contra');
    }
    
    // Load accounts based on voucher type
    loadAccountsByType();
}

function loadAccountsByType() {
    $.ajax({
        url: '{{ route("accounts.vouchers.get-accounts") }}',
        type: 'GET',
        data: { voucher_type: voucherType },
        success: function(response) {
            if (response.success) {
                // Store accounts data for dynamic selects
                window.accountsData = response.accounts;
                updateAccountSelects();
            }
        }
    });
}

function updateAccountSelects() {
    $('.account-select').each(function() {
        var $select = $(this);
        var currentValue = $select.val();
        $select.empty();
        $select.append('<option value="">{{ translate("Select Account") }}</option>');
        
        $.each(window.accountsData, function(index, account) {
            $select.append('<option value="' + account.id + '" data-type="' + account.type + '" data-sub_type="' + account.sub_type + '">' + account.name + ' (' + account.code + ') - Balance: ' + parseFloat(account.current_balance).toFixed(2) + '</option>');
        });
        
        if (currentValue) {
            $select.val(currentValue);
        }
        
        $select.trigger('change.select2');
    });
}

function initTransactionEntries(type) {
    $('#transactions_tbody').empty();
    rowCounter = 0;
    
    if (type === 'payment') {
        addTransactionRow('debit');
        addTransactionRow('credit');
    } 
    else if (type === 'receipt') {
        addTransactionRow('debit');
        addTransactionRow('credit');
    }
    else if (type === 'journal') {
        addTransactionRow('journal');
        addTransactionRow('journal');
    }
    else if (type === 'contra') {
        addTransactionRow('debit');
        addTransactionRow('credit');
    }
}

function addTransactionRow(rowType = 'both') {
    rowCounter++;
    var isJournal = voucherType === 'JV';
    
    var html = `<tr class="transaction-row" data-row-id="${rowCounter}">
        <td class="text-center">${rowCounter}</td>
        <td>
            <select name="account_ids[]" class="form-control account-select select2" style="width: 100%" required onchange="updateTotal()">
                <option value="">{{ translate('Select Account') }}</option>
            </select>
        </td>`;
    
    if (isJournal || rowType === 'journal') {
        html += `<td class="text-right">
                    <input type="number" step="0.01" name="debits[]" class="form-control text-right debit-amount" placeholder="0.00" onchange="updateTotal()">
                 </td>
                 <td class="text-right">
                    <input type="number" step="0.01" name="credits[]" class="form-control text-right credit-amount" placeholder="0.00" onchange="updateTotal()">
                 </td>`;
    } else if (rowType === 'debit') {
        html += `<td class="text-right">
                    <input type="number" step="0.01" name="debits[]" class="form-control text-right debit-amount" placeholder="0.00" onchange="updateTotal()" required>
                 </td>
                 <td class="text-right">
                    <input type="hidden" name="credits[]" value="0">
                    <span class="text-muted">--</span>
                 </td>`;
    } else {
        html += `<td class="text-right">
                    <input type="hidden" name="debits[]" value="0">
                    <span class="text-muted">--</span>
                 </td>
                 <td class="text-right">
                    <input type="number" step="0.01" name="credits[]" class="form-control text-right credit-amount" placeholder="0.00" onchange="updateTotal()" required>
                 </td>`;
    }
    
    html += `<td>
                <input type="text" name="narrations[]" class="form-control" placeholder="{{ translate('Note') }}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeTransactionRow(this)">
                    <i class="las la-trash"></i>
                </button>
            </td>
        </tr>`;
    
    $('#transactions_tbody').append(html);
    
    // Initialize select2
    if ($.fn.select2) {
        $('#transactions_tbody .select2:last').select2({
            dropdownParent: $('#voucherModal')
        });
    }
    
    updateTotal();
}

function removeTransactionRow(button) {
    if ($('#transactions_tbody tr').length > 1) {
        $(button).closest('tr').remove();
        updateTotal();
    } else {
        alert('{{ translate("At least one transaction row is required") }}');
    }
}

function updateTotal() {
    var totalDebit = 0;
    var totalCredit = 0;
    
    $('.debit-amount').each(function() {
        totalDebit += parseFloat($(this).val()) || 0;
    });
    
    $('.credit-amount').each(function() {
        totalCredit += parseFloat($(this).val()) || 0;
    });
    
    $('#total_amount_display').html('<strong class="text-success">' + totalDebit.toFixed(2) + '</strong>');
    
    if (totalDebit !== totalCredit && totalDebit > 0 && totalCredit > 0) {
        $('#details_error').show();
        $('#save_btn').prop('disabled', true);
    } else {
        $('#details_error').hide();
        $('#save_btn').prop('disabled', false);
    }
}

$('#voucherForm').on('submit', function(e) {
    e.preventDefault();
    
    var voucherId = $('#voucher_id').val();
    var formData = new FormData(this);
    formData.append('type', $('#voucher_type').val());
    
    var ajaxUrl = voucherId ? '{{ route("accounts.vouchers.update", "") }}/' + voucherId : '{{ route("accounts.vouchers.store") }}';
    
    $.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-HTTP-Method-Override': voucherId ? 'PUT' : 'POST'
        },
        success: function(response) {
            if (response.success) {
                $('#voucherModal').modal('hide');
                location.reload();
                showNotification('success', response.message);
            }
        },
        error: function(xhr) {
            var message = xhr.responseJSON?.error || xhr.responseJSON?.message || 'Error processing voucher';
            if (xhr.status === 422 && xhr.responseJSON.errors) {
                var errors = xhr.responseJSON.errors;
                for (var field in errors) {
                    showNotification('danger', errors[field][0]);
                }
            } else {
                showNotification('danger', message);
            }
        }
    });
});

function openVoucherModal() {
    $('#voucherForm')[0].reset();
    $('#voucher_id').val('');
    $('#voucher_type').val('').trigger('change');
    $('#transactions_tbody').empty();
    rowCounter = 0;
    $('#payment_details_section').hide();
    $('#voucherModal').modal('show');
}

function editVoucher(id) {
    window.location.href = '{{ route("accounts.vouchers.edit", "") }}/' + id;
}

function addExistingTransactionRow(detail) {
    rowCounter++;
    var isJournal = voucherType === 'JV';
    
    var html = `<tr class="transaction-row" data-row-id="${rowCounter}">
        <td class="text-center">${rowCounter}</td>
        <td>
            <select name="account_ids[]" class="form-control account-select select2" style="width: 100%" required>
                <option value="">{{ translate('Select Account') }}</option>
            </select>
        </td>`;
    
    if (isJournal) {
        html += `<td class="text-right">
                    <input type="number" step="0.01" name="debits[]" class="form-control text-right debit-amount" value="${detail.debit}" placeholder="0.00" onchange="updateTotal()">
                 </td>
                 <td class="text-right">
                    <input type="number" step="0.01" name="credits[]" class="form-control text-right credit-amount" value="${detail.credit}" placeholder="0.00" onchange="updateTotal()">
                 </td>`;
    } else {
        var amount = detail.debit > 0 ? detail.debit : detail.credit;
        html += `<td class="text-right">
                    <input type="number" step="0.01" name="amounts[]" class="form-control text-right transaction-amount" value="${amount}" placeholder="0.00" onchange="updateTotal()" required>
                 </td>
                 <td class="text-right">
                    <span class="text-muted">--</span>
                 </td>`;
    }
    
    html += `<td>
                <input type="text" name="narrations[]" class="form-control" placeholder="{{ translate('Note') }}" value="${detail.narration || ''}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeTransactionRow(this)">
                    <i class="las la-trash"></i>
                </button>
            </td>
        </tr>`;
    
    $('#transactions_tbody').append(html);
    
    // Set the selected account
    var $select = $('#transactions_tbody .select2:last');
    $select.val(detail.account_id).trigger('change');
}

function deleteVoucher(id) {
    if (confirm('{{ translate("Are you sure? This action cannot be undone.") }}')) {
        $.ajax({
            url: '{{ route("accounts.vouchers.delete", "") }}/' + id,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    location.reload();
                    showNotification('success', response.message);
                }
            },
            error: function(xhr) {
                showNotification('danger', xhr.responseJSON?.error || 'Error deleting voucher');
            }
        });
    }
}

function showNotification(type, message) {
    if (typeof AIZ !== 'undefined' && AIZ.plugins && AIZ.plugins.notify) {
        AIZ.plugins.notify(type, message);
    } else {
        alert(message);
    }
}
</script>

