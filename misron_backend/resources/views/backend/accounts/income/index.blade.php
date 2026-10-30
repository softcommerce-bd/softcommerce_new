@extends('backend.accounts.layout')

@section('accounts_content')
<div class="simplebar-content" style="padding: 16px;">
    <div class="container-fluid">
        <!-- Header -->
        <div class="w-100 border-bottom d-flex justify-content-between align-items-center px-1 mb-3">
            <p class="title pb-1 mb-0 fw-600">
                <i class="las la-chart-line text-primary mr-2"></i>
                {{ translate('Income List') }}
            </p>
            <a href="{{ route('accounts.incomes.create') }}" class="btn-account-color">
                <i class="las la-plus"></i> {{ translate('+ Add Income') }}
            </a>
        </div>

        <!-- Filters Row -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row align-items-end">
                    <div class="col-md-2 mb-2">
                        <input type="text" id="filter_income_account" class="form-control form-control-sm" placeholder="Income Account">
                    </div>
                    <div class="col-md-2 mb-2">
                        <input type="text" id="filter_deposit_to" class="form-control form-control-sm" placeholder="Deposit To">
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
                        <button class="btn btn-sm btn-account-color text-white" onclick="filterIncomes()">
                            <i class="las la-search"></i> Search
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="resetFilters()">
                            <i class="las la-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Incomes Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th width="50">#</th>
                            <th class="sortable" onclick="sortBy('date')" style="cursor:pointer;">
                                Date <span class="sort-icon" id="sort_icon_date"></span>
                            </th>
                            <th class="sortable" onclick="sortBy('voucher_no')" style="cursor:pointer;">
                                Voucher No <span class="sort-icon" id="sort_icon_voucher_no"></span>
                            </th>
                            <th>Income Account</th>
                            <th>Deposit To</th>
                            <th class="text-right sortable" onclick="sortBy('total_amount')" style="cursor:pointer;">
                                Amount (৳) <span class="sort-icon" id="sort_icon_total_amount"></span>
                            </th>
                            <th>Note</th>
                            <th width="120" class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="incomes_tbody">
                        @forelse($incomes as $index => $income)
                        <tr>
                            <td>{{ $incomes->firstItem() + $index }}</td>
                            <td>{{ $income->date->format('d-m-Y') }}</td>
                            <td><strong class="text-primary">{{ $income->voucher_no }}</strong></td>
                            <td>
                                @foreach($income->details as $detail)
                                    <span class="badge badge-info mb-1 d-inline-block">{{ $detail->incomeAccount->name ?? '-' }}</span>
                                @endforeach
                            </td>
                            <td>{{ $income->receivedAccount->name ?? '-' }}</td>
                            <td class="text-right"><strong class="text-success">{{ number_format($income->total_amount, 2) }}</strong></td>
                            <td>{{ Str::limit($income->note, 50) }}</td>
                            <td class="text-right">
                                <button class="btn btn-sm btn-outline-secondary border-0" title="Print" onclick="printIncome({{ $income->id }})">
                                    <i class="las la-print"></i>
                                </button>
                                <a href="{{ route('accounts.incomes.edit', $income->id) }}" class="btn btn-sm btn-outline-primary border-0" title="Edit">
                                    <i class="las la-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-danger border-0" title="Delete" onclick="deleteIncome({{ $income->id }})">
                                    <i class="las la-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="las la-chart-line la-3x text-muted"></i>
                                <p>{{ translate('No incomes found') }}</p>
                                <a href="{{ route('accounts.incomes.create') }}" class="btn-account-color">
                                    <i class="las la-plus"></i> Add Income
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <div><strong>Total Incomes: {{ $incomes->total() }}</strong></div>
                <div id="pagination_container">
                    {{ $incomes->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Income Modal -->
<div class="modal fade" id="viewIncomeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Income Details</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="view_income_content"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn-account-color" onclick="window.print()">Print</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
let sortField = 'date';
let sortDirection = 'desc';

function toggleCustomDate() {
    if ($('#filter_date_option').val() === 'custom') {
        $('.custom-date').show();
    } else {
        $('.custom-date').hide();
    }
}

function updateSortIcons() {
    $('.sort-icon').html('');
    let icon = sortDirection === 'asc' ? 'las la-sort-up' : 'las la-sort-down';
    $('#sort_icon_' + sortField).html(`<i class="${icon}"></i>`);
}

function sortBy(field) {
    if (sortField === field) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortField = field;
        sortDirection = 'asc';
    }
    filterIncomes();
}

function filterIncomes() {
    $.ajax({
        url: '{{ route("accounts.incomes.filter") }}',
        type: 'GET',
        data: {
            income_account: $('#filter_income_account').val(),
            deposit_to: $('#filter_deposit_to').val(),
            date_option: $('#filter_date_option').val(),
            from_date: $('#filter_from_date').val(),
            to_date: $('#filter_to_date').val(),
            prepared_by: $('#filter_prepared_by').val(),
            sort_field: sortField,
            sort_direction: sortDirection
        },
        success: function(response) {
            $('#incomes_tbody').html(response.table_html);
            $('#pagination_container').html(response.pagination_html);
            updateSortIcons();
        },
        error: function() {
            alert('Error filtering incomes');
        }
    });
}

function resetFilters() {
    $('#filter_income_account, #filter_deposit_to, #filter_prepared_by').val('');
    $('#filter_date_option').val('');
    $('#filter_from_date, #filter_to_date').val('');
    $('.custom-date').hide();
    sortField = 'date';
    sortDirection = 'desc';
    filterIncomes();
}

function viewIncome(id) {
    $.get('{{ route("accounts.incomes.show", "") }}/' + id, function(response) {
        if (response.success) {
            $('#view_income_content').html(response.html);
            $('#viewIncomeModal').modal('show');
        }
    });
}

function printIncome(id) {
    window.open('{{ route("accounts.incomes.print", "") }}/' + id, '_blank');
}

function deleteIncome(id) {
    if (confirm('{{ translate("Are you sure?") }}')) {
        $.ajax({
            url: '{{ route("accounts.incomes.delete", "") }}/' + id,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('Error deleting income');
            }
        });
    }
}
</script>
@endsection