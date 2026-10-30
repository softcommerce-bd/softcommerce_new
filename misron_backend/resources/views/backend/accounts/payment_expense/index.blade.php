@extends('backend.accounts.layout')

@section('accounts_content')
<div class="simplebar-content" style="padding: 16px;">
    <div class="container-fluid">
        <!-- Header -->
        <div class="w-100 border-bottom d-flex justify-content-between align-items-center px-1 mb-3">
            <p class="title pb-1 mb-0 fw-600">
                <i class="las la-receipt text-primary mr-2"></i>
                {{ translate('Pay Expense') }}
            </p>
            <a href="{{ route('accounts.expense-payments.create') }}" class="btn-account-color">
                <i class="las la-plus"></i> {{ translate('+ Pay Expense') }}
            </a>
        </div>

        <!-- Filters Row -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row align-items-end">
                    <!-- Cost Center (if needed) -->
                    @if(isset($costCenters) && count($costCenters) > 2)
                    <div class="col-md-2 mb-2">
                        <select class="form-control form-control-sm" id="filter_cost_center">
                            <option value="">All Cost Center</option>
                            @foreach($costCenters as $center)
                                <option value="{{ $center->id }}">{{ $center->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div class="col-md-2 mb-2">
                        <input type="text" id="filter_pay_from" class="form-control form-control-sm" placeholder="Pay From">
                    </div>
                    <div class="col-md-2 mb-2">
                        <input type="text" id="filter_expense" class="form-control form-control-sm" placeholder="Expense">
                    </div>
                    <div class="col-md-2 mb-2">
                        <select class="form-control form-control-sm" id="filter_date_option" onchange="toggleCustomDate()">
                            <option value="">-- Date Range --</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="custom">Custom Date</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2 custom-date" style="display:none;">
                        <input type="date" id="filter_from_date" class="form-control form-control-sm" placeholder="From">
                    </div>
                    <div class="col-md-2 mb-2 custom-date" style="display:none;">
                        <input type="date" id="filter_to_date" class="form-control form-control-sm" placeholder="To">
                    </div>
                    <div class="col-md-2 mb-2">
                        <input type="text" id="filter_prepared_by" class="form-control form-control-sm" placeholder="Prepared By">
                    </div>
                    <div class="col-md-2 mb-2">
                        <button class="btn btn-sm btn-account-color text-white" onclick="filterExpenses()">
                            <i class="las la-search"></i> Search
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="resetFilters()">
                            <i class="las la-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expenses Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th width="50">#</th>
                            <th class="sortable" onclick="sortBy('date')" style="cursor:pointer;">
                                {{ translate('Date') }}
                                <span class="sort-icon" id="sort_icon_date"></span>
                            </th>
                            <th class="sortable" onclick="sortBy('voucher_no')" style="cursor:pointer;">
                                {{ translate('Voucher No') }}
                                <span class="sort-icon" id="sort_icon_voucher_no"></span>
                            </th>
                            <th>{{ translate('Expense') }}</th>
                            <th>{{ translate('Pay From') }}</th>
                            <th class="text-right sortable" onclick="sortBy('total_amount')" style="cursor:pointer;">
                                {{ translate('Amount (৳)') }}
                                <span class="sort-icon" id="sort_icon_total_amount"></span>
                            </th>
                            <th>{{ translate('Prepared By') }}</th>
                            <th>{{ translate('Note') }}</th>
                            <th width="120" class="text-right">{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody id="expenses_tbody">
                        @forelse($expensePayments ?? [] as $index => $payment)
                        <tr>
                            <td>{{ $expensePayments->firstItem() + $index }}</td>
                            <td>{{ $payment->date->format('d-m-Y') }}</td>
                            <td><strong class="text-primary">{{ $payment->voucher_no }}</strong></td>
                            <td>
                                @foreach($payment->details as $detail)
                                    <span class="badge badge-info mb-1 d-inline-block">{{ $detail->expenseAccount->name ?? '-' }}</span>
                                @endforeach
                            </td>
                            <td>{{ $payment->paymentAccount->name ?? '-' }}</td>
                            <td class="text-right"><strong class="text-success">{{ number_format($payment->total_amount, 2) }}</strong></td>
                            <td>{{ $payment->creator->name ?? '-' }}</td>
                            <td>{{ Str::limit($payment->note, 50) ?? '-' }}</td>
                            <td class="text-right">
                                <button class="btn btn-sm btn-outline-secondary border-0" title="Print" onclick="printExpense({{ $payment->id }})">
                                    <i class="las la-print"></i>
                                </button>
                                <a href="{{ route('accounts.expense-payments.edit', $payment->id) }}" class="btn btn-sm btn-outline-primary border-0" title="Edit">
                                    <i class="las la-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-danger border-0" title="Delete" onclick="deleteExpense({{ $payment->id }})">
                                    <i class="las la-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="las la-receipt la-3x text-muted"></i>
                                <p class="mt-2">{{ translate('No expense payments found') }}</p>
                                <a href="{{ route('accounts.expense-payments.create') }}" class="btn-account-color">
                                    <i class="las la-plus"></i> {{ translate('Pay Expense') }}
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <div>
                    <strong>Total Expenses: {{ $expensePayments->total() ?? 0 }}</strong>
                </div>
                <div class="d-flex align-items-center" id="pagination_container">
                    {{ ($expensePayments ?? collect())->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Expense Modal (unchanged) -->
<div class="modal fade" id="viewExpenseModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">{{ translate('Expense Details') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="view_expense_content"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                <button type="button" class="btn-account-color" onclick="window.print()">{{ translate('Print') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
let sortField = 'date';
let sortDirection = 'desc';

// Show/hide custom date inputs
function toggleCustomDate() {
    var val = $('#filter_date_option').val();
    if (val === 'custom') {
        $('.custom-date').show();
    } else {
        $('.custom-date').hide();
    }
}

// Update sort icons after AJAX
function updateSortIcons() {
    $('.sort-icon').html('');
    var icon = sortDirection === 'asc' ? 'las la-sort-up' : 'las la-sort-down';
    $('#sort_icon_' + sortField).html('<i class="' + icon + '"></i>');
}

// Sort handler
function sortBy(field) {
    if (sortField === field) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortField = field;
        sortDirection = 'asc';
    }
    filterExpenses();
}

// AJAX filter & sort
function filterExpenses() {
    var data = {
        pay_from: $('#filter_pay_from').val(),
        expense: $('#filter_expense').val(),
        date_option: $('#filter_date_option').val(),
        from_date: $('#filter_from_date').val(),
        to_date: $('#filter_to_date').val(),
        prepared_by: $('#filter_prepared_by').val(),
        cost_center_id: $('#filter_cost_center').val(),
        sort_field: sortField,
        sort_direction: sortDirection
    };

    $.ajax({
        url: '{{ route("accounts.expense-payments.filter") }}',
        type: 'GET',
        data: data,
        success: function(response) {
            $('#expenses_tbody').html(response.table_html);
            $('#pagination_container').html(response.pagination_html);
            updateSortIcons();
        },
        error: function() {
            showNotification('danger', 'Error filtering expenses');
        }
    });
}

// Reset filters & sorting
function resetFilters() {
    $('#filter_pay_from, #filter_expense, #filter_prepared_by').val('');
    $('#filter_date_option').val('');
    $('#filter_from_date, #filter_to_date').val('');
    $('#filter_cost_center').val('');
    $('.custom-date').hide();
    sortField = 'date';
    sortDirection = 'desc';
    filterExpenses();
}

// View expense modal
function viewExpense(id) {
    $.ajax({
        url: '{{ route("accounts.expense-payments.show", "") }}/' + id,
        type: 'GET',
        success: function(response) {
            if (response.success) {
                $('#view_expense_content').html(response.html);
                $('#viewExpenseModal').modal('show');
            }
        },
        error: function() {
            showNotification('danger', 'Error loading expense details');
        }
    });
}

// Print expense
function printExpense(id) {
    window.open('{{ route("accounts.expense-payments.print", "") }}/' + id, '_blank');
}

// Delete expense
function deleteExpense(id) {
    if (confirm('{{ translate("Are you sure?") }}')) {
        $.ajax({
            url: '{{ route("accounts.expense-payments.delete", "") }}/' + id,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    location.reload();
                    showNotification('success', response.message);
                } else {
                    showNotification('danger', response.message);
                }
            },
            error: function() {
                showNotification('danger', 'Error deleting expense payment');
            }
        });
    }
}

// Simple notification
function showNotification(type, message) {
    if (typeof AIZ !== 'undefined' && AIZ.plugins && AIZ.plugins.notify) {
        AIZ.plugins.notify(type, message);
    } else {
        alert(type + ': ' + message);
    }
}
</script>
@endsection