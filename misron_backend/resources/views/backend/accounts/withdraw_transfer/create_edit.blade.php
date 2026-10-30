@extends('backend.accounts.layout')

@section('accounts_content')
<div class="simplebar-content" style="padding: 16px;">
    <div class="container-fluid">
        <!-- Header -->
        <div class="w-100 border-bottom d-flex justify-content-between align-items-center px-1 mb-3">
            <p class="title pb-1 mb-0 fw-600">
                <i class="las la-exchange-alt text-primary mr-2"></i>
                {{ isset($transaction) ? translate('Edit Transaction') : translate('Create New Transaction') }}
            </p>
        </div>

        <div class="w-100 pb-4">
            <!-- Transaction Type Tabs -->
            <div class="col-12 px-0">
                <ul class="nav cs-nav-type nav-pills mb-4" id="transactionTypeTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link {{ (!isset($transaction) && old('transaction_type', 'withdraw') == 'withdraw') || (isset($transaction) && $transaction->transaction_type == 'withdraw') ? 'active' : '' }}"
                            data-type="withdraw" onclick="selectTransactionType('withdraw')" style="cursor: pointer;">
                            {{ translate('Withdraw') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ (!isset($transaction) && old('transaction_type') == 'deposit') || (isset($transaction) && $transaction->transaction_type == 'deposit') ? 'active' : '' }}"
                            data-type="deposit" onclick="selectTransactionType('deposit')" style="cursor: pointer;">
                            {{ translate('Deposit') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ (!isset($transaction) && old('transaction_type') == 'transfer') || (isset($transaction) && $transaction->transaction_type == 'transfer') ? 'active' : '' }}"
                            data-type="transfer" onclick="selectTransactionType('transfer')" style="cursor: pointer;">
                            {{ translate('Transfer') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ (!isset($transaction) && old('transaction_type') == 'cashout') || (isset($transaction) && $transaction->transaction_type == 'cashout') ? 'active' : '' }}"
                            data-type="cashout" onclick="selectTransactionType('cashout')" style="cursor: pointer;">
                            {{ translate('Cash Out') }}
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Form -->
            <div class="w-100 pb-4">
                <form class="form-eud" id="balanceTransferForm" method="POST"
                    action="{{ isset($transaction) ? route('accounts.balance-transfers.update', $transaction->id) : route('accounts.balance-transfers.store') }}"
                    autocomplete="off">
                    @csrf
                    @if(isset($transaction)) @method('PUT') @endif
                    <input type="hidden" name="transaction_type" id="transaction_type"
                        value="{{ old('transaction_type', $transaction->transaction_type ?? 'withdraw') }}">

                    <div class="w-100 panel-elem pb-4">
                        <div class="form-row">
                            <!-- Date -->
                            <div class="col-lg-2 col-6 cpr">
                                <div class="form-label-group form-group">
                                    <label class="font-medium label-date" for="transactionDate">
                                        {{ translate('Date') }} <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" id="transactionDate" name="date"
                                        class="form-control without-focus @error('date') is-invalid @enderror"
                                        value="{{ old('date', isset($transaction) ? $transaction->date->format('Y-m-d') : date('Y-m-d')) }}" required>
                                    @error('date')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- From Account -->
                            <div class="col-lg-2 col-6 cpl" id="from_account_div">
                                <div class="form-group cst">
                                    <label class="cs-select-lbl d-block" id="from_account_label">
                                        {{ translate('Withdraw from') }} <span class="text-danger">*</span>
                                    </label>
                                    <select name="from_account_id" id="from_account_id"
                                        class="form-control account-select @error('from_account_id') is-invalid @enderror"
                                        style="width: 100%" required>
                                        <option value="">{{ translate('Select Account') }}</option>
                                        <!-- Options will be populated by JavaScript -->
                                    </select>
                                    @error('from_account_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="text-danger" id="insufficient_balance" style="display: none;">
                                        {{ translate('Insufficient balance') }}
                                    </small>
                                </div>
                            </div>

                            <!-- Amount -->
                            <div class="col-lg-2 col-6 cpl">
                                <div class="form-label-group form-group">
                                    <label class="font-medium" for="amount">{{ translate('Amount') }} <span class="text-danger">*</span></label>
                                    <input type="text" id="amount" name="amount"
                                        class="form-control px-0 without-focus bg-transparent @error('amount') is-invalid @enderror"
                                        placeholder="{{ translate('Amount') }}"
                                        value="{{ old('amount', $transaction->amount ?? '') }}" required
                                        oninput="validateAmount()">
                                    @error('amount')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- To Account -->
                            <div class="col-lg-2 col-6 cpl" id="to_account_div">
                                <div class="form-group cst">
                                    <label class="cs-select-lbl d-block" id="to_account_label">
                                        {{ translate('Withdraw To') }} <span class="text-danger">*</span>
                                    </label>
                                    <select name="to_account_id" id="to_account_id"
                                        class="form-control account-select @error('to_account_id') is-invalid @enderror"
                                        style="width: 100%" required>
                                        <option value="">{{ translate('Select Account') }}</option>
                                        <!-- Options will be populated by JavaScript -->
                                    </select>
                                    @error('to_account_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Charge (only for Cash Out) -->
                            <div class="col-lg-2 col-6 cpl" id="charge_div" style="display: {{ (old('transaction_type', $transaction->transaction_type ?? '') == 'cashout') ? 'block' : 'none' }};">
                                <div class="form-label-group form-group">
                                    <label class="font-medium" for="charge">{{ translate('Charge') }}</label>
                                    <input type="text" id="charge" name="charge"
                                        class="form-control px-0 without-focus bg-transparent"
                                        placeholder="{{ translate('Charge') }}"
                                        value="{{ old('charge', $transaction->charge ?? '0') }}"
                                        oninput="calculateTotal()">
                                </div>
                            </div>
                        </div>

                        <!-- Note -->
                        <div class="form-row mt-2">
                            <div class="col cpl">
                                <div class="form-label-group form-group">
                                    <textarea class="form-control px-0 without-focus bg-transparent" id="note" name="note" rows="3"
                                        placeholder="{{ translate('Note') }}">{{ old('note', $transaction->note ?? '') }}</textarea>
                                    <label class="font-medium" for="note">{{ translate('Note') }}</label>
                                </div>
                            </div>
                        </div>

                        <!-- Save & Cancel Buttons -->
                        <div class="d-flex act-bt justify-content-start border-top pt-3 mt-4">
                            <button type="submit" class="btn mr-4 py-1 px-4 btn-account-color" id="save_btn">
                                <i class="las la-save"></i> {{ translate('Save') }}
                            </button>
                            <a href="{{ route('accounts.index', ['tab' => 'withdraw-deposit']) }}" class="btn btn-secondary py-1 px-4">
                                <i class="las la-times"></i> {{ translate('Cancel') }}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
// All accounts data from controller
// var allAccounts = JSON.parse('@json($accountsJson ?? [])');
// var currentFromId = "{{ old('from_account_id', $transaction->from_account_id ?? '') }}";
// var currentToId   = "{{ old('to_account_id', $transaction->to_account_id ?? '') }}";

// $(document).ready(function() {
//     // Initial render
//     var initialType = $('#transaction_type').val();
//     applyAccountFilters(initialType);
//     updateFormByType(initialType);
    
//     // Select2 initialization will be done inside applyAccountFilters after building HTML
//     checkAccountBalance();
//     if (initialType === 'cashout') calculateTotal();
// });

var allAccounts = @json($accounts);
var currentFromId = "{{ old('from_account_id', $transaction->from_account_id ?? '') }}";
var currentToId   = "{{ old('to_account_id', $transaction->to_account_id ?? '') }}";

$(document).ready(function() {
    var initialType = $('#transaction_type').val();
    applyAccountFilters(initialType);
    updateFormByType(initialType);
    checkAccountBalance();
    if (initialType === 'cashout') calculateTotal();
});

function selectTransactionType(type) {
    $('#transaction_type').val(type);
    $('.nav-link').removeClass('active');
    $(`.nav-link[data-type="${type}"]`).addClass('active');
    updateFormByType(type);
    applyAccountFilters(type);
}

function updateFormByType(type) {
    var fromLabel = $('#from_account_label');
    var toLabel = $('#to_account_label');
    var chargeDiv = $('#charge_div');

    chargeDiv.hide();
    $('#from_account_div, #to_account_div').show();
    $('#from_account_id, #to_account_id').prop('required', true);

    switch(type) {
        case 'withdraw':
            fromLabel.html('{{ translate("Withdraw from") }} <span class="text-danger">*</span>');
            toLabel.html('{{ translate("Withdraw To") }} <span class="text-danger">*</span>');
            break;
        case 'deposit':
            fromLabel.html('{{ translate("Deposit from") }} <span class="text-danger">*</span>');
            toLabel.html('{{ translate("Deposit To") }} <span class="text-danger">*</span>');
            break;
        case 'transfer':
            fromLabel.html('{{ translate("Transfer from") }} <span class="text-danger">*</span>');
            toLabel.html('{{ translate("Transfer To") }} <span class="text-danger">*</span>');
            break;
        case 'cashout':
            fromLabel.html('{{ translate("Cash Out from") }} <span class="text-danger">*</span>');
            toLabel.html('{{ translate("Cash Out To") }} <span class="text-danger">*</span>');
            chargeDiv.show();
            calculateTotal();
            break;
    }
    checkAccountBalance();
}

// Define which sub_types are allowed for each type/field
var accountFilters = {
    withdraw: {
        from: ['bank'],
        to:   ['cash']
    },
    deposit: {
        from: ['cash'],
        to:   ['bank']
    },
    transfer: {
        from: ['bank', 'cash', 'mobile_bank'],
        to:   ['bank', 'cash', 'mobile_bank']
    },
    cashout: {
        from: ['mobile_bank'],
        to:   ['bank', 'cash', 'mobile_bank']
    }
};

function applyAccountFilters(type) {
    buildSelect('from_account_id', accountFilters[type].from, currentFromId);
    buildSelect('to_account_id', accountFilters[type].to, currentToId);
    
    // Reinitialize select2 on both
    if ($.fn.select2) {
        $('.account-select').select2('destroy').select2();
    }
}

function buildSelect(selectId, allowedSubTypes, selectedValue) {
    var select = $('#' + selectId);
    var optionsHtml = '<option value="">{{ translate("Select Account") }}</option>';
    
    // Group accounts by sub_type
    var grouped = {};
    allAccounts.forEach(function(acc) {
        if (allowedSubTypes.includes(acc.sub_type)) {
            if (!grouped[acc.sub_type]) grouped[acc.sub_type] = [];
            grouped[acc.sub_type].push(acc);
        }
    });
    
    // Build optgroups
    for (var subType in grouped) {
        var label = formatSubType(subType);
        optionsHtml += '<optgroup label="' + label + '">';
        grouped[subType].forEach(function(acc) {
            var selected = (acc.id == selectedValue) ? ' selected' : '';
            optionsHtml += '<option value="' + acc.id + '" data-balance="' + acc.current_balance + '"' + selected + '>' + acc.name + ' (' + acc.code + ')</option>';
        });
        optionsHtml += '</optgroup>';
    }
    
    select.html(optionsHtml);
}

// Helper to convert sub_type to a readable label
function formatSubType(subType) {
    var labels = {
        'bank': 'Bank',
        'cash': 'Cash',
        'mobile_bank': 'Mobile Banking',
        'receivable': 'Receivable',
        'payable': 'Payable',
        'income': 'Income',
        'expense': 'Expense',
        'tax': 'Tax',
        'equity': 'Equity',
        'inventory': 'Inventory',
        'fixed_asset': 'Fixed Asset',
        'sales': 'Sales',
        'revenue': 'Revenue'
    };
    return labels[subType] || subType.charAt(0).toUpperCase() + subType.slice(1).replace('_', ' ');
}

// Existing validation functions remain unchanged
function checkAccountBalance() {
    var fromOption = $('#from_account_id').find('option:selected');
    var balance = parseFloat(fromOption.data('balance')) || 0;
    var amount = parseFloat($('#amount').val().replace(/,/g, '')) || 0;
    var charge = parseFloat($('#charge').val().replace(/,/g, '')) || 0;
    var type = $('#transaction_type').val();
    var total = amount + charge;

    if (['withdraw', 'transfer', 'cashout'].includes(type) && total > balance) {
        $('#insufficient_balance').show();
        $('#save_btn').prop('disabled', true);
    } else {
        $('#insufficient_balance').hide();
        $('#save_btn').prop('disabled', false);
    }
}

function validateAmount() {
    var val = $('#amount').val();
    val = val.replace(/[^0-9.]/g, '');
    var parts = val.split('.');
    if (parts.length > 2) val = parts[0] + '.' + parts.slice(1).join('');
    $('#amount').val(val);
    checkAccountBalance();
    if ($('#transaction_type').val() === 'cashout') calculateTotal();
}

function calculateTotal() {
    checkAccountBalance();
}

$('#from_account_id, #amount, #charge').on('change input', function() {
    checkAccountBalance();
    if ($('#transaction_type').val() === 'cashout') calculateTotal();
});

$('#charge').on('input', function() {
    var val = $(this).val().replace(/[^0-9.]/g, '');
    var parts = val.split('.');
    if (parts.length > 2) val = parts[0] + '.' + parts.slice(1).join('');
    $(this).val(val);
});
</script>
@endsection