<!-- backend/accounts/transaction_method.blade.php - PARTIAL ONLY -->
<div class="data-table-card card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <h5 class="mb-0 fw-600"><i class="las la-list-ul text-primary mr-2"></i>{{ translate('Transaction Methods') }}</h5>
        <button class="btn btn-sm btn-soft-primary rounded-pill px-3" onclick="openMethodModal()">
            <i class="las la-plus"></i> {{ translate('Add Method') }}
        </button>
    </div>
    <div class="card-body p-0">
        <table class="table aiz-table mb-0">
            <thead class="bg-light">
                <tr>
                    <th width="50">#</th>
                    <th>{{ translate('Name') }}</th>
                    <th>{{ translate('Status') }}</th>
                    <th>{{ translate('Created Date') }}</th>
                    <th width="100" class="text-right">{{ translate('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($methods as $index => $method)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ $method->name }}</strong></td>
                    <td>
                        @if($method->status == 'active')
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-danger">Inactive</span>
                        @endif
                    </td>
                    <td><small>{{ $method->created_at ? $method->created_at->format('Y-m-d') : '-' }}</small></td>
                    <td class="text-right">
                        <a class="btn btn-soft-primary btn-icon btn-circle btn-sm" href="javascript:void(0)" onclick="editMethod({{ $method->id }})" title="Edit">
                            <i class="las la-edit"></i>
                        </a>
                        <a class="btn btn-soft-danger btn-icon btn-circle btn-sm" href="javascript:void(0)" onclick="deleteMethod({{ $method->id }})" title="Delete">
                            <i class="las la-trash"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center">No transaction methods found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal for Add/Edit (Keep this here) -->
<div class="modal fade" id="methodModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="las la-credit-card mr-2"></i>
                    <span id="modal-title">{{ translate('Add Transaction Method') }}</span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="methodForm" onsubmit="return false;">
                @csrf
                <input type="hidden" id="method_id" name="method_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ translate('Name') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="e.g., Bank Transfer, Cash, bKash" required>
                        <div class="invalid-feedback" id="name_error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('Status') }}</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="las la-save"></i> {{ translate('Save Method') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openMethodModal() {
    resetForm();
    $('#method_id').val('');
    $('#modal-title').text('{{ translate("Add Transaction Method") }}');
    $('#methodModal').modal('show');
}

function editMethod(id) {
    $.ajax({
        url: '{{ route("transaction-method.get.single", "") }}/' + id,
        type: 'GET',
        success: function(response) {
            if (response.success) {
                const method = response.data;
                $('#method_id').val(method.id);
                $('#name').val(method.name);
                $('#status').val(method.status);
                $('#modal-title').text('{{ translate("Edit Transaction Method") }}');
                $('#methodModal').modal('show');
            }
        },
        error: function() {
            AIZ.plugins.notify('danger', 'Error loading method data');
        }
    });
}

$('#methodForm').on('submit', function(e) {
    e.preventDefault();
    
    const id = $('#method_id').val();
    const url = id 
        ? '{{ route("transaction-method.update", "") }}/' + id
        : '{{ route("transaction-method.store") }}';
    
    $('.is-invalid').removeClass('is-invalid');
    $('.invalid-feedback').html('');
    
    const formData = {
        name: $('#name').val(),
        status: $('#status').val(),
        _token: '{{ csrf_token() }}'
    };
    
    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                $('#methodModal').modal('hide');
                location.reload();
                AIZ.plugins.notify('success', response.message);
                resetForm();
            }
        },
        error: function(xhr) {
            if (xhr.status === 422) {
                const errors = xhr.responseJSON.errors;
                for (let field in errors) {
                    $(`#${field}`).addClass('is-invalid');
                    $(`#${field}_error`).html(errors[field][0]);
                }
                AIZ.plugins.notify('danger', 'Please fix the validation errors');
            } else {
                AIZ.plugins.notify('danger', 'An error occurred');
            }
        }
    });
});

function deleteMethod(id) {
    if (confirm('Are you sure you want to delete this method?')) {
        $.ajax({
            url: '{{ route("transaction-method.delete", "") }}/' + id,
            type: 'DELETE',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                    AIZ.plugins.notify('success', response.message);
                }
            },
            error: function() {
                AIZ.plugins.notify('danger', 'Error deleting method');
            }
        });
    }
}

function resetForm() {
    $('#methodForm')[0].reset();
    $('#method_id').val('');
    $('.is-invalid').removeClass('is-invalid');
    $('.invalid-feedback').html('');
    $('#status').val('active');
}
</script>