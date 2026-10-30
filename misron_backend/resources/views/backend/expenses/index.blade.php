{{-- resources/views/expenses/index.blade.php --}}
@extends('backend.layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            {{-- Success/Error Messages --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>{{ translate('Success!') }}</strong> {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>{{ translate('Error!') }}</strong> {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 h6">{{ translate('Expenses Management') }}</h5>
                    <a href="{{ route('expenses.create') }}" class="btn btn-primary btn-sm">
                        <i class="las la-plus"></i> {{ translate('Add New Expense') }}
                    </a>
                </div>
                <div class="card-body px-0">
                    {{-- Filter Form --}}
                    <form action="{{ route('expenses.index') }}" method="GET" id="filter-form">
                        <div class="row align-items-end">
                            <div class="col-md-3 my-2">
                                <div class="form-group mb-0">
                                    <label class="form-label">{{ translate('Search by Title') }}</label>
                                    <input type="text" class="form-control" name="search" value="{{ $search }}" placeholder="{{ translate('Search by title...') }}" autocomplete="off">
                                </div>
                            </div>
                            
                            <div class="col-md-3 my-2">
                                <div class="form-group mb-0">
                                    <label class="form-label">{{ translate('Expense Category') }}</label>
                                    <select class="select2 form-control aiz-selectpicker" name="expense_category_id" data-live-search="true">
                                        <option value="">{{ translate('All Categories') }}</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" {{ $selectedCategory == $category->id ? 'selected' : '' }}>
                                                {{ str_repeat('--', $category->level) }} {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-3 my-2">
                                <div class="form-group mb-0">
                                    <label class="form-label">{{ translate('Filter by Date') }}</label>
                                    <input type="text" class="aiz-date-range form-control" value="{{ $date }}" name="date" placeholder="{{ translate('Filter by date') }}" data-format="DD-MM-Y" data-separator=" to " data-advanced-range="true" autocomplete="off">
                                </div>
                            </div>
                            
                            <div class="col-md-3 my-2">
                                <div class="form-group mb-0">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-flex">
                                        <button type="submit" class="btn btn-primary mr-2">
                                            <i class="las la-search"></i> {{ translate('Filter') }}
                                        </button>
                                        <a href="{{ route('expenses.index') }}" class="btn btn-secondary mr-2">
                                            <i class="las la-sync-alt"></i> {{ translate('Reset') }}
                                        </a>
                                        <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                            <i class="las la-file-excel"></i> {{ translate('Export') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Hidden fields for sorting --}}
                        <input type="hidden" name="sort_by" id="sort_by" value="{{ $sortBy }}">
                        <input type="hidden" name="sort_order" id="sort_order" value="{{ $sortOrder }}">
                    </form>

                    <hr>

                    {{-- Expenses Table --}}
                    <div class="table-responsive">
                        <table class="table aiz-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortBy('title')">
                                            {{ translate('Title') }}
                                            @if($sortBy == 'title')
                                                <i class="las la-sort-{{ $sortOrder == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortBy('expense_category_id')">
                                            {{ translate('Category') }}
                                            @if($sortBy == 'expense_category_id')
                                                <i class="las la-sort-{{ $sortOrder == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortBy('amount')">
                                            {{ translate('Amount') }} (TK)
                                            @if($sortBy == 'amount')
                                                <i class="las la-sort-{{ $sortOrder == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>{{ translate('Description') }}</th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortBy('date')">
                                            {{ translate('Date') }}
                                            @if($sortBy == 'date')
                                                <i class="las la-sort-{{ $sortOrder == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th width="10%" class="text-right">{{ translate('Options') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($expenses as $key => $expense)
                                    <tr>
                                        <td>{{ ($key+1) + ($expenses->currentPage() - 1)*$expenses->perPage() }}</td>
                                        <td>{{ $expense->title }}</td>
                                        <td>
                                            @if($expense->category)
                                                {{ $expense->category->name }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ number_format($expense->amount, 2) }} TK</td>
                                        <td>{{ Str::limit($expense->description, 50) }}</td>
                                        <td>{{ date('j F Y', strtotime($expense->date)) }}</td>
                                        <td class="text-right">
                                            <a class="btn btn-soft-primary btn-icon btn-circle btn-sm" href="javascript:void(0)" onclick="editExpense({{ $expense->id }})" title="{{ translate('Edit') }}">
                                                <i class="las la-edit"></i>
                                            </a>
                                            {{-- <form action="{{ route('expenses.destroy', $expense->id) }}" method="POST" style="display: inline-block;" onsubmit="return confirmDelete(event, this)">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-soft-danger btn-icon btn-circle btn-sm" title="{{ translate('Delete') }}">
                                                    <i class="las la-trash"></i>
                                                </button>
                                            </form> --}}
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            {{ translate('No expenses found') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-right"><strong>{{ translate('Total:') }}</strong></td>
                                    <td colspan="4"><strong>{{ number_format($expenses->sum('amount'), 2) }} TK</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="aiz-pagination mt-3">
                        {{ $expenses->appends(request()->input())->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Edit Modal --}}
<div class="modal fade" id="editExpenseModal" tabindex="-1" role="dialog" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editExpenseModalLabel">{{ translate('Edit Expense') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editExpenseForm" action="" method="POST">
                @csrf
                @method('POST')
                <div class="modal-body">
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{ translate('Title') }} <span class="text-danger">*</span></label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{ translate('Category') }} <span class="text-danger">*</span></label>
                        <div class="col-md-9">
                            <select class="select2 form-control aiz-selectpicker" id="edit_expense_category_id" name="expense_category_id" data-live-search="true" required>
                                <option value="">{{ translate('Select Category') }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ str_repeat('--', $category->level) }} {{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{ translate('Amount') }} <span class="text-danger">*</span></label>
                        <div class="col-md-9">
                            <input type="number" step="0.01" class="form-control" id="edit_amount" name="amount" required>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{ translate('Date') }} <span class="text-danger">*</span></label>
                        <div class="col-md-9">
                            <input type="date" class="form-control" id="edit_date" name="date" required>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{ translate('Description') }}</label>
                        <div class="col-md-9">
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ translate('Update Expense') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    function sortBy(field) {
        var currentSortBy = $('#sort_by').val();
        var currentSortOrder = $('#sort_order').val();
        var newSortOrder = 'asc';
        
        if (currentSortBy == field && currentSortOrder == 'asc') {
            newSortOrder = 'desc';
        }
        
        $('#sort_by').val(field);
        $('#sort_order').val(newSortOrder);
        $('#filter-form').submit();
    }
    
    function editExpense(id) {
        $.ajax({
            url: '{{ route("expenses.edit", "") }}/' + id,
            type: 'GET',
            success: function(response) {
                let expense = response.expense;
                let dateValue = expense.date;
                // alert(expense.date);
                
                $('#edit_title').val(expense.title);
                $('#edit_expense_category_id').val(expense.expense_category_id).trigger('change');
                $('#edit_amount').val(expense.amount);
                
                if (dateValue) {
                    // If date comes with time component, extract just the date part
                    if (dateValue.includes(' ')) {
                        dateValue = dateValue.split(' ')[0];
                    }
                    $('#edit_date').val(dateValue);
                }
                $('#edit_description').val(expense.description);
                
                $('#editExpenseForm').attr('action', '{{ route("expenses.update", "") }}/' + id);
                
                $('#editExpenseModal').modal('show');
                
                $('.aiz-selectpicker').selectpicker('refresh');
            },
            error: function(xhr) {
                AIZ.plugins.notify('danger', '{{ translate("Failed to load expense data") }}');
            }
        });
    }
    
    function confirmDelete(event, form) {
        event.preventDefault();
        if(confirm('{{ translate("Are you sure you want to delete this expense?") }}')) {
            form.submit();
        }
        return false;
    }
    
    function exportToExcel() {
        var formData = $('#filter-form').serialize();
        window.location.href = '{{ route("expenses.export") }}?' + formData;
    }
    
    $(document).ready(function() {
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    });
</script>
@endsection