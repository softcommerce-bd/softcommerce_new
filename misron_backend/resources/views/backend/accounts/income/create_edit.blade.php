@extends('backend.accounts.layout')

@section('accounts_content')
<div class="simplebar-content" style="padding: 16px;">
    <div class="container-fluid">
        <!-- Header -->
        <div class="w-100 border-bottom d-flex justify-content-between align-items-center px-1 mb-3">
            <p class="title pb-1 mb-0 fw-600">
                <i class="las la-chart-line text-primary mr-2"></i>
                {{ isset($income) ? translate('Edit Income') : translate('Add Income') }}
            </p>
            <a href="{{ route('accounts.incomes.index') }}" class="btn btn-secondary btn-sm">
                <i class="las la-list"></i> {{ translate('Back to List') }}
            </a>
        </div>

        <!-- Income Form -->
        <div class="w-100 pb-4">
            <form class="form-eud" name="income_add_form" id="incomeForm" method="POST" 
                  action="{{ isset($income) ? route('accounts.incomes.update', $income->id) : route('accounts.incomes.store') }}" 
                  enctype="multipart/form-data">
                @csrf
                @if(isset($income))
                    @method('PUT')
                @endif
                <input type="hidden" name="income_id" id="income_id" value="{{ $income->id ?? '' }}">

                <!-- Form Row 1 -->
                <div class="form-row">
                    <!-- Date Field -->
                    <div class="col-lg-2 col-6 cpr">
                        <div class="form-label-group form-group">
                            <label class="font-medium label-date" for="incomeDate">
                                {{ translate('Date') }} <span class="text-danger">*</span>
                            </label>
                            <input type="date" id="incomeDate" name="date"
                                class="form-control without-focus @error('date') is-invalid @enderror"
                                value="{{ old('date', isset($income) ? $income->date->format('Y-m-d') : date('Y-m-d')) }}"
                                required>
                            @error('date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Deposit To Account -->
                    <div class="col-lg-3 col-6 cpl">
                        <div class="form-group cst">
                            <label class="cs-select-lbl d-block">
                                {{ translate('Deposit To') }} <span class="text-danger">*</span>
                            </label>
                            <select name="received_account_id" id="received_account_id"
                                class="form-control select2 @error('received_account_id') is-invalid @enderror"
                                style="width: 100%" required>
                                <option value="">{{ translate('Select Account') }}</option>
                                @foreach($receivedAccounts ?? [] as $account)
                                    <option value="{{ $account->id }}" 
                                            data-balance="{{ $account->current_balance ?? 0 }}"
                                            {{ old('received_account_id', $income->received_account_id ?? '') == $account->id ? 'selected' : '' }}>
                                        {{ $account->name }} ({{ $account->code ?? '' }}) - Balance: {{ number_format($account->current_balance ?? 0, 2) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback">{{ translate('Please select account') }}</div>
                        </div>
                    </div>

                    <!-- Cost Center -->
                    @if(isset($costCenters) && count($costCenters) > 1)
                    <div class="col-lg-2 col-6 cpl">
                        <div class="form-group cst">
                            <label class="cs-select-lbl d-block">{{ translate('Cost Center') }}</label>
                            <select name="cost_center_id" id="cost_center_id" class="form-control select2" style="width: 100%">
                                <option value="">{{ translate('Select Cost Center') }}</option>
                                @foreach($costCenters ?? [] as $center)
                                    <option value="{{ $center->id }}" 
                                        {{ old('cost_center_id', $income->cost_center_id ?? '') == $center->id ? 'selected' : '' }}>
                                        {{ $center->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Income Details Section -->
                <div class="w-100 panel-elem pb-4">
                    <div class="w-100 pt-3">
                        <h6 class="font-15">
                            {{ translate('Income Details') }} <span class="text-danger">*</span>
                        </h6>
                        <span class="text-danger" id="details_error" style="display: none;">
                            {{ translate('Please add income details') }}
                        </span>
                    </div>

                    <!-- Incomes Table -->
                    <div class="w-100 panel-elem pb-4 mt-2">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="bg-light">
                                    <tr class="panel-heading">
                                        <th width="40">#</th>
                                        <th>{{ translate('Income Account') }} <span class="text-danger">*</span></th>
                                        <th width="150" class="text-right">{{ translate('Amount (৳)') }} <span class="text-danger">*</span></th>
                                        <th>{{ translate('Note') }}</th>
                                        <th width="50" class="text-center">{{ translate('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="incomes_tbody">
                                    @if(isset($income) && $income->details && count($income->details) > 0)
                                        @foreach($income->details as $index => $detail)
                                        <tr class="income-row">
                                            <td class="text-center">{{ $index + 1 }}</td>
                                            <td>
                                                <select name="income_account_ids[]" class="form-control income-select select2" style="width: 100%" required>
                                                    <option value="">{{ translate('Select Income Account') }}</option>
                                                    @foreach($incomeAccounts ?? [] as $account)
                                                        <option value="{{ $account->id }}" {{ $detail->income_account_id == $account->id ? 'selected' : '' }}>
                                                            {{ $account->name }} ({{ $account->code ?? '' }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="text-right">
                                                <input type="number" step="0.01" name="amounts[]" class="form-control text-right income-amount" value="{{ $detail->amount }}" placeholder="0.00" required onchange="updateTotal()">
                                            </td>
                                            <td>
                                                <input type="text" name="notes[]" class="form-control" placeholder="{{ translate('Note') }}" value="{{ $detail->note }}">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-danger" onclick="removeIncomeRow(this)">
                                                    <i class="las la-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        @endforeach
                                    @else
                                        <tr class="income-row">
                                            <td class="text-center">1</td>
                                            <td>
                                                <select name="income_account_ids[]" class="form-control income-select select2" style="width: 100%" required>
                                                    <option value="">{{ translate('Select Income Account') }}</option>
                                                    @foreach($incomeAccounts ?? [] as $account)
                                                        <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->code ?? '' }})</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="text-right">
                                                <input type="number" step="0.01" name="amounts[]" class="form-control text-right income-amount" placeholder="0.00" required onchange="updateTotal()">
                                            </td>
                                            <td>
                                                <input type="text" name="notes[]" class="form-control" placeholder="{{ translate('Note') }}">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-danger" onclick="removeIncomeRow(this)">
                                                    <i class="las la-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <td colspan="2" class="text-right"><strong>{{ translate('Total') }}</strong></td>
                                        <td class="text-right"><strong id="total_amount">0.00</strong></td>
                                        <td colspan="2"></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-center">
                                            <button type="button" class="btn-account-color" onclick="addIncomeRow()">
                                                <i class="las la-plus"></i> {{ translate('Add Income') }}
                                            </button>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Note Field -->
                    <div class="w-100 pt-2">
                        <div class="form-group">
                            <label class="font-medium">{{ translate('Note') }}</label>
                            <textarea rows="2" class="form-control" name="note" id="note" placeholder="{{ translate('Enter note...') }}">{{ old('note', $income->note ?? '') }}</textarea>
                        </div>
                    </div>

                    <!-- Attachment Section -->
                    <div class="form-row cs-file pb-2">
                        <div class="col-4">
                            <p class="lbl-form text-color-5">{{ translate('Attachment') }}</p>
                            <div class="custom-file w-auto">
                                <input type="file" class="custom-file-input cursor-pointer" id="attachments" name="attachments[]" multiple accept=".pdf,image/jpeg,image/jpg,image/png,image/gif">
                                <label class="custom-file-label" for="attachments">{{ translate('Upload Files') }}</label>
                            </div>
                            <label class="font-italic font-11">
                                {{ translate('Select attachments (PDF, JPEG, PNG, GIF)') }}
                            </label>
                        </div>
                    </div>

                    <!-- Save and Cancel Buttons -->
                    <div class="d-flex act-bt justify-content-start border-top pt-3 mt-4">
                        <button type="submit" class="btn-account-color mr-4 py-1 px-4" id="save_btn">
                            <i class="las la-save"></i> {{ translate('Save') }}
                        </button>
                        <button type="button" class="btn btn-success mr-4 py-1 px-4" onclick="saveAndNew()">
                            <i class="las la-save"></i> {{ translate('Save & New') }}
                        </button>
                        <button type="button" class="btn btn-secondary py-1 px-4" onclick="window.location.href='{{ route('accounts.incomes.index') }}'">
                            <i class="las la-times"></i> {{ translate('Cancel') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
var rowCounter = {{ isset($income) && $income->details ? count($income->details) : 1 }};

$(document).ready(function() {
    if ($.fn.select2) {
        $('.select2').select2();
    }
    
    updateTotal();
    
    // File input label update
    $('#attachments').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName || '{{ translate("Upload Files") }}');
    });
});

function addIncomeRow() {
    rowCounter++;
    var html = `<tr class="income-row">
        <td class="text-center">${rowCounter}</td>
        <td>
            <select name="income_account_ids[]" class="form-control income-select select2" style="width: 100%" required>
                <option value="">{{ translate('Select Income Account') }}</option>`;
    
    @foreach($incomeAccounts ?? [] as $account)
    html += `<option value="{{ $account->id }}">{{ $account->name }} ({{ $account->code ?? '' }})</option>`;
    @endforeach
    
    html += `</select>
        </td>
        <td class="text-right">
            <input type="number" step="0.01" name="amounts[]" class="form-control text-right income-amount" placeholder="0.00" required onchange="updateTotal()">
        </td>
        <td>
            <input type="text" name="notes[]" class="form-control" placeholder="{{ translate('Note') }}">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger" onclick="removeIncomeRow(this)">
                <i class="las la-trash"></i>
            </button>
        </td>
    </tr>`;
    
    $('#incomes_tbody').append(html);
    
    // Initialize select2 for new select
    if ($.fn.select2) {
        $('#incomes_tbody .select2').select2();
    }
    
    updateTotal();
}

function removeIncomeRow(button) {
    if ($('#incomes_tbody tr').length > 1) {
        $(button).closest('tr').remove();
        // Renumber rows
        $('#incomes_tbody tr').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
        updateTotal();
    } else {
        alert('{{ translate("At least one income entry is required") }}');
    }
}

function updateTotal() {
    var total = 0;
    $('.income-amount').each(function() {
        var amount = parseFloat($(this).val()) || 0;
        total += amount;
    });
    $('#total_amount').text(total.toFixed(2));
}

function saveAndNew() {
    $('#incomeForm').append('<input type="hidden" name="save_and_new" value="1">');
    $('#incomeForm').submit();
}

$('#incomeForm').on('submit', function(e) {
    // Validate at least one income entry
    var hasAmount = false;
    $('.income-amount').each(function() {
        if (parseFloat($(this).val()) > 0) {
            hasAmount = true;
        }
    });
    
    if (!hasAmount) {
        e.preventDefault();
        alert('{{ translate("Please add at least one income with amount") }}');
        return false;
    }
    
    return true;
});
</script>
@endsection