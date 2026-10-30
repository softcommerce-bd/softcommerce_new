{{-- resources/views/expenses/create.blade.php --}}
@extends('backend.layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 h6">{{ translate('Add New Expense') }}</h5>
                        <a href="{{ route('expenses.index') }}" class="btn btn-secondary btn-sm">
                            <i class="las la-arrow-left"></i> {{ translate('Back') }}
                        </a>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('expenses.store') }}" method="POST">
                            @csrf

                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">{{ translate('Title') }} <span
                                        class="text-danger">*</span></label>
                                <div class="col-md-10">
                                    <input type="text" class="form-control @error('title') is-invalid @enderror"
                                        name="title" value="{{ old('title') }}" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">{{ translate('Category') }} <span
                                        class="text-danger">*</span></label>
                                <div class="col-md-8">
                                    <select
                                        class="select2 form-control aiz-selectpicker @error('expense_category_id') is-invalid @enderror"
                                        name="expense_category_id" data-live-search="true" required>
                                        <option value="">{{ translate('Select Category') }}</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                {{ old('expense_category_id') == $category->id ? 'selected' : '' }}>
                                                {{ str_repeat('--', $category->level) }} {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('expense_category_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-2">
                                    <button type="button" onclick="openCategoryModal()" class="btn btn-primary">
                                        <i class="las la-plus"></i> {{ translate('Add Category') }}
                                    </button>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">{{ translate('Amount') }} <span
                                        class="text-danger">*</span></label>
                                <div class="col-md-10">
                                    <input type="number" step="0.01"
                                        class="form-control @error('amount') is-invalid @enderror" name="amount"
                                        value="{{ old('amount') }}" required>
                                    @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">{{ translate('Date') }} <span
                                        class="text-danger">*</span></label>
                                <div class="col-md-10">
                                    <input type="date" class="form-control @error('date') is-invalid @enderror"
                                        name="date" value="{{ old('date', date('Y-m-d')) }}" required>
                                    @error('date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">{{ translate('Description') }}</label>
                                <div class="col-md-10">
                                    <textarea class="form-control @error('description') is-invalid @enderror" name="description" rows="4">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-md-10 offset-md-2">
                                    <button type="submit" class="btn btn-primary">{{ translate('Save Expense') }}</button>
                                    <a href="{{ route('expenses.index') }}"
                                        class="btn btn-secondary">{{ translate('Cancel') }}</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Category Modal --}}
    <div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog" aria-labelledby="addCategoryModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">{{ translate('Add New Category') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('expense-categories.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">{{ translate('Name') }} <span
                                    class="text-danger">*</span></label>
                            <div class="col-md-9">
                                <input type="text" class="form-control" name="name"
                                    placeholder="{{ translate('Category Name') }}" required>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">{{ translate('Parent Category') }}</label>
                            <div class="col-md-9">
                                <select class="select2 form-control aiz-selectpicker" name="parent_id"
                                    data-live-search="true">
                                    <option value="">{{ translate('None') }}</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ str_repeat('--', $cat->level) }}
                                            {{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                            data-dismiss="modal">{{ translate('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ translate('Save Category') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
    function openCategoryModal() {
        $('#addCategoryModal').modal('show');
        
        // Refresh selectpicker
        setTimeout(function() {
            $('.aiz-selectpicker').selectpicker('refresh');
        }, 100);
    }
    
    // Optional: Show success message if category was created
    @if(session('success'))
        $(document).ready(function() {
            AIZ.plugins.notify('success', '{{ session('success') }}');
        });
    @endif
</script>
@endsection