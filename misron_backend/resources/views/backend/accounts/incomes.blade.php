<div class="row">
    <div class="col-md-12 mb-3">
        <div class="card data-table-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h5 class="mb-0 fw-600"><i class="las la-chart-line text-primary mr-2"></i>{{ translate('Incomes') }}</h5>
                <button class="btn btn-sm btn-soft-primary rounded-pill px-3" onclick="openIncomeModal()">
                    <i class="las la-plus"></i> {{ translate('Add Income') }}
                </button>
            </div>
            <div class="card-body p-0">
                <table class="table aiz-table mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th width="50">#</th>
                            <th>{{ translate('Income No') }}</th>
                            <th>{{ translate('Income Type') }}</th>
                            <th>{{ translate('Amount') }}</th>
                            <th>{{ translate('Received Account') }}</th>
                            <th>{{ translate('Payment Method') }}</th>
                            <th>{{ translate('Date') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th width="100" class="text-right">{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($incomes ?? [] as $index => $income)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><strong>{{ $income->income_no }}</strong></td>
                            <td>{{ $income->incomeAccount->name ?? '-' }}</td>
                            <td class="text-success">{{ number_format($income->amount, 2) }} ৳</td>
                            <td>{{ $income->receivedAccount->name ?? '-' }}</td>
                            <td>{{ $income->transactionMethod->name ?? '-' }}</td>
                            <td>{{ $income->income_date->format('Y-m-d') }}</td>
                            <td>
                                @if($income->status == 'completed')
                                    <span class="badge-completed">Completed</span>
                                @else
                                    <span class="badge-pending">{{ ucfirst($income->status) }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a class="btn btn-soft-info btn-icon btn-circle btn-sm" href="javascript:void(0)" onclick="viewIncome({{ $income->id }})">
                                    <i class="las la-eye"></i>
                                </a>
                                <a class="btn btn-soft-danger btn-icon btn-circle btn-sm" href="javascript:void(0)" onclick="deleteIncome({{ $income->id }})">
                                    <i class="las la-trash"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center">{{ translate('No incomes found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function openIncomeModal() {
    $('#income_id').val('');
    $('#incomeForm')[0].reset();
    $('#income_date').val(new Date().toISOString().slice(0,10));
    $('#incomeModal').modal('show');
}

$('#incomeForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: '{{ route("accounts.incomes.store") }}',
        type: 'POST',
        data: $(this).serialize(),
        success: function(response) {
            if (response.success) {
                $('#incomeModal').modal('hide');
                location.reload();
                showNotification('success', response.message);
            }
        },
        error: function(xhr) {
            var message = xhr.responseJSON?.message || 'Error recording income';
            showNotification('danger', message);
        }
    });
});
</script>