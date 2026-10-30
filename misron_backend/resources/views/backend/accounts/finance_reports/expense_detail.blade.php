@extends('backend.accounts.layout')

@section('accounts_content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 h6">{{ translate('Expense Detail Report') }}</h5>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" action="{{ route('accounts.reports.expense-detail') }}" class="mb-4" id="reportForm">
            <div class="form-row align-items-end">
                <!-- Date Range -->
                <div class="col-lg-2 col-md-4 mb-2">
                    <label class="d-block text-muted small mb-1">{{ translate('Date Range') }}</label>
                    <select name="date_range" class="form-control form-control-sm" onchange="toggleCustomDate(this.value)">
                        <option value="this_month" {{ $dateRange == 'this_month' ? 'selected' : '' }}>{{ translate('Month of') }} {{ now()->format('F') }}</option>
                        <option value="last_month" {{ $dateRange == 'last_month' ? 'selected' : '' }}>{{ translate('Month of') }} {{ now()->subMonth()->format('F') }}</option>
                        <option value="custom" {{ $dateRange == 'custom' ? 'selected' : '' }}>{{ translate('Custom Date') }}</option>
                    </select>
                </div>
                
                <!-- Start Date -->
                <div class="col-lg-2 col-md-4 mb-2 custom-date" style="{{ $dateRange == 'custom' ? '' : 'display:none;' }}">
                    <label class="d-block text-muted small mb-1">{{ translate('Starting Date') }}</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date', $startDate->toDateString()) }}">
                </div>
                
                <!-- End Date -->
                <div class="col-lg-2 col-md-4 mb-2 custom-date" style="{{ $dateRange == 'custom' ? '' : 'display:none;' }}">
                    <label class="d-block text-muted small mb-1">{{ translate('End Date') }}</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date', $endDate->toDateString()) }}">
                </div>
                
                <!-- Expense Heads Dropdown -->
                <div class="col-lg-3 col-md-6 mb-2">
                    <label class="d-block text-muted small mb-1">{{ translate('Expense') }}</label>
                    <div class="position-relative">
                        <div class="expense-select-box" id="expenseSelectBox" onclick="toggleDropdown()">
                            <span id="selectedExpensesText" class="text-truncate">
                                @if($isAllExpenses)
                                    <span class="badge badge-info mr-1">All Expenses</span>
                                @elseif(is_array($accountIds) && count($accountIds) > 0)
                                    @php $selectedCount = count($accountIds); @endphp
                                    <span class="badge badge-primary mr-1">{{ $selectedCount }} selected</span>
                                    {{ $expenseAccounts->whereIn('id', $accountIds)->pluck('name')->take(2)->implode(', ') }}
                                    @if($selectedCount > 2) ... @endif
                                @else
                                    <span class="text-muted">Expense heads</span>
                                @endif
                            </span>
                            <i class="las la-angle-down float-right mt-1"></i>
                        </div>
                        <div class="expense-dropdown-menu" id="expenseDropdown" style="display: none;">
                            <!-- All Expenses Option -->
                            <div class="dropdown-item" style="border-bottom: 1px solid #eee; font-weight: 500;">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="all_expenses_check" 
                                           {{ $isAllExpenses ? 'checked' : '' }} onchange="handleAllExpenses(this)">
                                    <label class="custom-control-label" for="all_expenses_check" style="cursor: pointer;">
                                        {{ translate('All Expenses') }}
                                    </label>
                                </div>
                            </div>
                            <!-- Individual Expense Items -->
                            <div style="max-height: 200px; overflow-y: auto;">
                                @foreach($expenseAccounts as $acc)
                                <div class="dropdown-item expense-option" style="{{ $isAllExpenses ? 'opacity: 0.5;' : '' }}">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input expense-check" 
                                               name="account_ids[]" value="{{ $acc->id }}" 
                                               id="exp_{{ $acc->id }}"
                                               {{ (is_array($accountIds) && in_array($acc->id, $accountIds)) ? 'checked' : '' }}
                                               {{ $isAllExpenses ? 'disabled' : '' }}
                                               onchange="updateSelectedText()">
                                        <label class="custom-control-label" for="exp_{{ $acc->id }}" style="cursor: pointer; font-size: 13px;">
                                            {{ $acc->name }}
                                        </label>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @if($isAllExpenses)
                    <input type="hidden" name="all_expenses" value="1" id="allExpensesHidden">
                    @endif
                </div>
                
                <!-- Buttons -->
                <div class="col-lg-3 col-md-6 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-2">
                        <i class="las la-search"></i> {{ translate('Generate') }}
                    </button>
                    <button type="button" class="btn btn-danger btn-sm mr-2" onclick="resetForm()">
                        <i class="las la-times"></i> {{ translate('Cancel') }}
                    </button>
                </div>
            </div>
        </form>

        <!-- Report Viewer Section -->
        @if((is_array($accountIds) && count($accountIds) > 0) || $isAllExpenses)
        <div class="mt-3">
            <!-- Toolbar -->
            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light border rounded no-print">
                <div class="d-flex align-items-center flex-wrap">
                    <span class="mr-2 small text-muted">{{ translate('Period') }}: <strong>{{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}</strong></span>
                    <span class="mr-2 small text-muted">|</span>
                    <span class="mr-2 small text-muted">{{ translate('Transactions') }}: <strong>{{ $transactions->count() }}</strong></span>
                    <span class="mr-2 small text-muted">|</span>
                    <span class="mr-2 small text-muted">{{ translate('Total') }}: <strong class="text-danger">{{ number_format($totalExpense, 2) }} ৳</strong></span>
                    <select class="form-control form-control-sm ml-2" style="width: 90px;" onchange="zoomReport(this.value)">
                        <option value="50">50%</option>
                        <option value="75">75%</option>
                        <option value="100" selected>100%</option>
                        <option value="125">125%</option>
                        <option value="150">150%</option>
                    </select>
                </div>
                <div>
                    <a href="{{ route('accounts.reports.expense-detail', array_merge(request()->all(), ['export' => 'excel'])) }}" 
                       class="btn btn-sm btn-outline-success mr-1" title="Excel">
                        <i class="las la-file-excel"></i>
                    </a>
                    <a href="{{ route('accounts.reports.expense-detail', array_merge(request()->all(), ['export' => 'pdf'])) }}" 
                       class="btn btn-sm btn-outline-danger mr-1" title="PDF">
                        <i class="las la-file-pdf"></i>
                    </a>
                    <button class="btn btn-sm btn-outline-info" onclick="printReportDiv()" title="Print">
                        <i class="las la-print"></i>
                    </button>
                </div>
            </div>

            <!-- Report Content -->
            <div class="border" style="overflow: auto; max-height: 65vh; background: #e9ecef;">
                <div class="d-flex justify-content-center" style="min-width: 900px;">
                    <div id="reportContent" style="transform: scale(1); transform-origin: top center; transition: transform 0.2s; background: white; width: 210mm; padding: 15mm; margin: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); min-height: 297mm;">

                        <!-- Report Header -->
                        <div style="text-align: center; margin-bottom: 15px; padding-bottom: 12px; border-bottom: 2px solid #000;">
                            <h2 style="margin: 0; font-weight: bold; font-size: 18px; text-transform: uppercase; color: #1F4E79;">
                                {{ get_setting('site_name', env('APP_NAME', 'Company Name')) }}
                            </h2>
                            <h3 style="margin: 6px 0; font-weight: bold; font-size: 15px; color: #333;">
                                {{ translate('EXPENSE DETAIL REPORT') }}
                            </h3>
                            <p style="margin: 3px 0; font-size: 11px; color: #555;">
                                <strong>{{ translate('Period') }}:</strong> {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}
                                | <strong>{{ translate('Total Expense') }}:</strong> <span style="color: #C00000;">{{ number_format($totalExpense, 2) }} ৳</span>
                            </p>
                        </div>

                        <!-- Transactions Table -->
                        <div style="margin-bottom: 12px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                                <thead>
                                    <tr>
                                        <th style="background: #1a3a5c; color: #fff; padding: 6px 8px; border: 1px solid #1a3a5c; width: 4%; text-align: center;">#</th>
                                        <th style="background: #1a3a5c; color: #fff; padding: 6px 8px; border: 1px solid #1a3a5c; width: 13%;">{{ translate('Date') }}</th>
                                        <th style="background: #1a3a5c; color: #fff; padding: 6px 8px; border: 1px solid #1a3a5c; width: 16%;">{{ translate('Reference') }}</th>
                                        <th style="background: #1a3a5c; color: #fff; padding: 6px 8px; border: 1px solid #1a3a5c; width: 20%;">{{ translate('Expense Account') }}</th>
                                        <th style="background: #1a3a5c; color: #fff; padding: 6px 8px; border: 1px solid #1a3a5c; width: 17%; text-align: right;">{{ translate('Amount (৳)') }}</th>
                                        <th style="background: #1a3a5c; color: #fff; padding: 6px 8px; border: 1px solid #1a3a5c; width: 30%;">{{ translate('Description') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transactions as $key => $trans)
                                    <tr style="{{ $key % 2 == 0 ? 'background: #f8f9fa;' : 'background: #fff;' }}">
                                        <td style="padding: 4px 8px; border: 1px solid #dee2e6; text-align: center;">{{ $key + 1 }}</td>
                                        <td style="padding: 4px 8px; border: 1px solid #dee2e6;">{{ \Carbon\Carbon::parse($trans->transaction_date)->format('d M Y') }}</td>
                                        <td style="padding: 4px 8px; border: 1px solid #dee2e6; font-size: 10px;">{{ $trans->reference_no }}</td>
                                        <td style="padding: 4px 8px; border: 1px solid #dee2e6;">{{ $trans->account->name ?? 'N/A' }}</td>
                                        <td style="padding: 4px 8px; border: 1px solid #dee2e6; text-align: right; font-weight: 600; font-family: 'Courier New', monospace;">
                                            {{ number_format($trans->debit, 2) }}
                                        </td>
                                        <td style="padding: 4px 8px; border: 1px solid #dee2e6; font-size: 10px;">{{ Str::limit($trans->description, 55) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" style="padding: 20px; text-align: center; color: #999; border: 1px solid #dee2e6; font-style: italic;">
                                            <i class="las la-info-circle"></i> {{ translate('No expense records found for this period') }}
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr style="font-weight: bold; font-size: 13px; background: #e8f5e9;">
                                        <td colspan="4" style="padding: 10px 12px; border: 2px solid #28a745; text-align: right; color: #155724; text-transform: uppercase;">
                                            {{ translate('Total') }} (৳):
                                        </td>
                                        <td style="padding: 10px 12px; border: 2px solid #28a745; text-align: right; color: #155724; font-family: 'Courier New', monospace;">
                                            {{ number_format($totalExpense, 2) }}
                                        </td>
                                        <td style="padding: 10px 12px; border: 2px solid #28a745;"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Footer -->
                        <div style="margin-top: 15px; padding-top: 8px; border-top: 1px solid #dee2e6; font-size: 9px; color: #888;">
                            <table style="width: 100%;">
                                <tr>
                                    <td><i class="las la-clock"></i> {{ translate('Print Date') }}: {{ now()->format('d M Y, h:i A') }}</td>
                                    <td style="text-align: right;"><i class="las la-user"></i> {{ translate('Generated by') }}: {{ auth()->user()->name ?? 'System' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @else
        <div class="text-center py-5 text-muted">
            <i class="las la-file-invoice-dollar" style="font-size: 64px; display: block; margin-bottom: 15px;"></i>
            <h5>{{ translate('Expense Detail Report') }}</h5>
            <p>{{ translate('Select expense heads and date range, then click Generate.') }}</p>
        </div>
        @endif
    </div>
</div>

<!-- Custom Dropdown Styles -->
<style>
/* Expense Select Box */
.expense-select-box {
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 6px 30px 6px 10px;
    min-height: 34px;
    background: #fff;
    cursor: pointer;
    font-size: 13px;
    position: relative;
    display: flex;
    align-items: center;
    transition: border-color 0.15s;
}
/* .expense-select-box:hover {
    border-color: #86b7fe;
} */
.expense-select-box .la-angle-down {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    transition: transform 0.2s;
}
.expense-select-box.open .la-angle-down {
    transform: translateY(-50%) rotate(180deg);
}

/* Dropdown Menu */
.expense-dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: 0 0 4px 4px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    margin-top: -1px;
}
.dropdown-item {
    padding: 6px 12px;
    cursor: pointer;
}
/* .dropdown-item:hover {
    background: #f0f4f8;
} */

/* Print Styles */
@media print {
    body>*:not(#reportContent) { display: none !important; }
    html, body { margin: 0 !important; padding: 0 !important; background: white !important; }
    #reportContent {
        display: block !important; visibility: visible !important;
        position: absolute !important; left: 0 !important; top: 0 !important;
        width: 100% !important; margin: 0 !important; padding: 10mm !important;
        transform: none !important; box-shadow: none !important;
        min-height: auto !important; background: white !important;
    }
    .no-print, .btn, form, select, .card-header, .navbar, .page-side-nav { display: none !important; }
    @page { size: A4; margin: 0; }
}
</style>

<script>
// Toggle dropdown
function toggleDropdown() {
    const dropdown = document.getElementById('expenseDropdown');
    const selectBox = document.getElementById('expenseSelectBox');
    const isOpen = dropdown.style.display === 'block';
    
    dropdown.style.display = isOpen ? 'none' : 'block';
    if (!isOpen) {
        selectBox.classList.add('open');
    } else {
        selectBox.classList.remove('open');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const selectBox = document.getElementById('expenseSelectBox');
    const dropdown = document.getElementById('expenseDropdown');
    if (selectBox && dropdown && !selectBox.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
        selectBox.classList.remove('open');
    }
});

// Handle All Expenses
function handleAllExpenses(checkbox) {
    const expenseChecks = document.querySelectorAll('.expense-check');
    const expenseOptions = document.querySelectorAll('.expense-option');
    const allExpensesHidden = document.getElementById('allExpensesHidden');
    
    if (checkbox.checked) {
        // Disable all individual checkboxes
        expenseChecks.forEach(el => {
            el.disabled = true;
            el.checked = false;
        });
        expenseOptions.forEach(el => el.style.opacity = '0.5');
        
        // Add hidden input for all_expenses
        if (!allExpensesHidden) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'all_expenses';
            hidden.value = '1';
            hidden.id = 'allExpensesHidden';
            document.getElementById('reportForm').appendChild(hidden);
        }
        
        updateSelectedText();
        document.getElementById('reportForm').submit();
    } else {
        // Enable all individual checkboxes
        expenseChecks.forEach(el => el.disabled = false);
        expenseOptions.forEach(el => { el.style.opacity = '1'; el.style.pointerEvents = 'auto'; });
        
        // Remove hidden input
        if (allExpensesHidden) allExpensesHidden.remove();
        
        updateSelectedText();
    }
}

// Update selected text
function updateSelectedText() {
    const checkedBoxes = document.querySelectorAll('.expense-check:checked');
    const count = checkedBoxes.length;
    const textSpan = document.getElementById('selectedExpensesText');
    
    if (document.getElementById('all_expenses_check').checked) {
        textSpan.innerHTML = '<span class="badge badge-info">All Expenses</span>';
    } else if (count > 0) {
        let names = [];
        checkedBoxes.forEach(cb => {
            const label = document.querySelector('label[for="' + cb.id + '"]');
            if (label) names.push(label.textContent.trim());
        });
        const displayNames = names.slice(0, 2).join(', ');
        textSpan.innerHTML = '<span class="badge badge-primary mr-1">' + count + ' selected</span>' + 
                            displayNames + (count > 2 ? ' ...' : '');
    } else {
        textSpan.innerHTML = '<span class="text-muted">Expense heads</span>';
    }
}

function toggleCustomDate(value) { 
    document.querySelectorAll('.custom-date').forEach(el => { el.style.display = value === 'custom' ? '' : 'none'; }); 
}

function zoomReport(value) { 
    const rc = document.getElementById('reportContent'); 
    if (rc) rc.style.transform = 'scale(' + (value / 100) + ')'; 
}

function resetForm() {
    window.location.href = "{{ route('accounts.reports.expense-detail') }}";
}

function printReportDiv() {
    const rc = document.getElementById('reportContent'); 
    if (!rc) return;
    const pw = window.open('', '_blank', 'width=900,height=700');
    pw.document.write('<!DOCTYPE html><html><head><title>Expense Detail Report</title><style>body{font-family:Arial,sans-serif;margin:0;padding:20px;color:#333}table{border-collapse:collapse;width:100%}th{background:#1a3a5c;color:#fff;padding:6px 8px;border:1px solid #1a3a5c}td{padding:4px 8px;border:1px solid #dee2e6}@page{size:A4;margin:10mm}</style></head><body>');
    pw.document.write(rc.innerHTML); 
    pw.document.write('</body></html>'); 
    pw.document.close();
    setTimeout(() => { pw.print(); pw.close(); }, 500);
}
</script>
@endsection