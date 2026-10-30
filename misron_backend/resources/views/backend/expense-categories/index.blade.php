{{-- resources/views/expense-categories/index.blade.php --}}
@extends('backend.layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 h6">{{ translate('Expense Categories') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            {{-- Left Side - Add Form --}}
                            <div class="col-md-5">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">{{ translate('Add New Category') }}</h6>
                                    </div>
                                    <div class="card-body">
                                        <form action="{{ route('expense-categories.store') }}" method="POST"
                                            enctype="multipart/form-data">
                                            @csrf

                                            <div class="form-group row">
                                                <label class="col-md-3 col-form-label">{{ translate('Name') }} <span
                                                        class="text-danger">*</span></label>
                                                <div class="col-md-9">
                                                    <input type="text" class="form-control" name="name"
                                                        placeholder="{{ translate('Category Name') }}" required>
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label
                                                    class="col-md-3 col-form-label">{{ translate('Parent Category') }}</label>
                                                <div class="col-md-9">
                                                    <select class="select2 form-control aiz-selectpicker" name="parent_id"
                                                        data-live-search="true">
                                                        <option value="">{{ translate('Select Parent Category') }}
                                                        </option>
                                                        @foreach ($categories as $cat)
                                                            <option value="{{ $cat->id }}">
                                                                {{ str_repeat('--', $cat->level) }} {{ $cat->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <div class="col-md-9 offset-md-3">
                                                    <button type="submit"
                                                        class="btn btn-primary">{{ translate('Save Category') }}</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            {{-- Right Side - Table --}}
                            <div class="col-md-7">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">{{ translate('Category List') }}</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table aiz-table mb-0">
                                            <thead>
                                                <tr>
                                                    <th data-breakpoints="lg">#</th>
                                                    <th>{{ translate('Name') }}</th>
                                                    <th data-breakpoints="lg">{{ translate('Parent Category') }}</th>
                                                    <th width="10%" class="text-right">{{ translate('Options') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($categories as $key => $category)
                                                    <tr>
                                                        <td>{{ $key + 1 + ($categories->currentPage() - 1) * $categories->perPage() }}
                                                        </td>
                                                        <td class="d-flex align-items-center">
                                                            {{ $category->name }}
                                                        </td>
                                                        <td>
                                                            @if ($category->parent)
                                                                {{ $category->parent->name }}
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                        <td class="text-right">
                                                            <a class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                                                                href="javascript:void(0)"
                                                                onclick="editCategory({{ $category->id }})"
                                                                title="{{ translate('Edit') }}">
                                                                <i class="las la-edit"></i>
                                                            </a>

                                                            {{-- Delete Button with Form --}}
                                                            <a href="#"
                                                                class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete"
                                                                data-href="{{ route('expense-category.destroy', $category->id) }}"
                                                                title="{{ translate('Delete') }}">
                                                                <i class="las la-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                        <div class="aiz-pagination">
                                            {{ $categories->appends(request()->input())->links() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog" aria-labelledby="editCategoryModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCategoryModalLabel">{{ translate('Edit Category') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editCategoryForm" action="" method="POST">
                    @csrf
                    @method('POST')
                    <div class="modal-body">
                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">{{ translate('Name') }} <span
                                    class="text-danger">*</span></label>
                            <div class="col-md-9">
                                <input type="text" class="form-control" id="edit_name" name="name"
                                    placeholder="{{ translate('Category Name') }}" required>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">{{ translate('Parent Category') }}</label>
                            <div class="col-md-9">
                                <select class="select2 form-control aiz-selectpicker" id="edit_parent_id" name="parent_id"
                                    data-live-search="true">
                                    <option value="">{{ translate('Select Parent Category') }}</option>
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
                        <button type="submit" class="btn btn-primary">{{ translate('Update Category') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@section('modal')
    @include('modals.delete_modal')
@endsection


@section('script')
    <script>
        function editCategory(id) {
            $.ajax({
                url: '{{ route('expense-categories.edit', '') }}/' + id,
                type: 'GET',
                success: function(response) {
                    var category = response.category;

                    $('#edit_name').val(category.name);
                    $('#edit_parent_id').val(category.parent_id).trigger('change');

                    // Update form action for update
                    $('#editCategoryForm').attr('action', '{{ route('expense-categories.update', '') }}/' +
                    id);

                    $('#editCategoryModal').modal('show');

                    // Reinitialize selectpicker
                    $('.aiz-selectpicker').selectpicker('refresh');
                },
                error: function(xhr) {
                    console.log(xhr);
                    AIZ.plugins.notify('danger', '{{ translate('Failed to load category data') }}');
                }
            });
        }
    </script>
@endsection
