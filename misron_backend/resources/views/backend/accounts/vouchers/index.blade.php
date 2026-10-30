<div class="card shadow-sm border-0">

    <!-- Header -->
    <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <h5 class="mb-2 mb-md-0 fw-600">
            <i class="las la-receipt text-primary mr-2"></i>
            {{ translate('Voucher List') }}
        </h5>
        <a href="{{ route('accounts.vouchers.create') }}" class="btn-account-color btn-sm px-3 rounded-pill">
            <i class="las la-plus"></i>
            {{ translate('New Voucher') }}
        </a>
    </div>

    <!-- Filters -->
    <div class="card-body border-bottom">
        <div class="row">
            <div class="col-md-3 mb-3">
                <label>{{ translate('Voucher No') }}</label>
                <input type="text" id="filter_voucher_no" class="form-control" placeholder="Search voucher no...">
            </div>

            <div class="col-md-3 mb-3">
                <label>{{ translate('Voucher Type') }}</label>
                <select id="filter_voucher_type" class="form-control">
                    <option value="">{{ translate('All Types') }}</option>
                    <option value="CP">{{ translate('Cash Payment (CP)') }}</option>
                    <option value="BP">{{ translate('Bank Payment (BP)') }}</option>
                    <option value="CR">{{ translate('Cash Receive (CR)') }}</option>
                    <option value="BR">{{ translate('Bank Receive (BR)') }}</option>
                    <option value="JV">{{ translate('Journal Voucher (JV)') }}</option>
                    <option value="CV">{{ translate('Contra Voucher (CV)') }}</option>
                </select>
            </div>

            <div class="col-md-3 mb-3">
                <label>{{ translate('Date Range') }}</label>
                <input type="text" class="form-control aiz-date-range" id="filter_date_range" placeholder="{{ translate('Select date range') }}">
            </div>

            <div class="col-md-3 mb-3 d-flex align-items-end">
                <button class="btn-account-color btn-block" onclick="filterVouchers()">
                    <i class="las la-search"></i> {{ translate('Search') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Desktop Table -->
    <div class="table-responsive d-none d-lg-block">
        <table class="table table-hover mb-0">
            <thead class="bg-account-color text-white">
                <tr>
                    <th class="sortable" onclick="sortBy('date')">
                        {{ translate('Date') }} <i class="las la-arrow-up"></i>
                    </th>
                    <th class="sortable" onclick="sortBy('voucher_no')">
                        {{ translate('Voucher No') }} <i class="las la-arrow-up"></i>
                    </th>
                    <th class="sortable" onclick="sortBy('type')">
                        {{ translate('Tran. Type') }} <i class="las la-arrow-up"></i>
                    </th>
                    <th class="text-right sortable" onclick="sortBy('amount')">
                        {{ translate('Amount') }} <i class="las la-arrow-up"></i>
                    </th>
                    <th>{{ translate('Note') }}</th>
                    <th class="text-right" width="120">{{ translate('Action') }}</th>
                </tr>
            </thead>
            <tbody id="vouchers_table_body">
                @forelse($vouchers ?? [] as $index => $voucher)
                    <tr class="panel-list-items">
                        <td>{{ $voucher->date->format('d-m-Y') }}</td>
                        <td><strong class="text-primary">{{ $voucher->voucher_no }}</strong></td>
                        <td>
                            <span class="badge badge-soft-info px-3 py-2">
                                @if($voucher->type == 'CP') {{ translate('Cash Payment') }}
                                @elseif($voucher->type == 'BP') {{ translate('Bank Payment') }}
                                @elseif($voucher->type == 'CR') {{ translate('Cash Receipt') }}
                                @elseif($voucher->type == 'BR') {{ translate('Bank Receipt') }}
                                @elseif($voucher->type == 'JV') {{ translate('Journal Voucher') }}
                                @elseif($voucher->type == 'CV') {{ translate('Contra Voucher') }}
                                @endif
                            </span>
                        </td>
                        <td class="text-right">
                            <strong class="text-success">{{ number_format($voucher->amount, 2) }} ৳</strong>
                        </td>
                        <td>{{ Str::limit($voucher->description, 50) ?? '-' }}</td>
                        <td class="text-right">
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm" data-toggle="dropdown">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item" href="javascript:void(0)" onclick="printVoucher({{ $voucher->id }})">
                                        <i class="las la-print mr-2"></i> {{ translate('Print') }}
                                    </a>
                                    <a class="dropdown-item" href="{{ route('accounts.vouchers.edit', $voucher->id) }}">
                                        <i class="las la-edit mr-2"></i> {{ translate('Edit') }}
                                    </a>
                                    <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteVoucher({{ $voucher->id }})">
                                        <i class="las la-trash mr-2"></i> {{ translate('Delete') }}
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="las la-receipt la-3x text-muted mb-3 d-block"></i>
                            <p class="text-muted mb-0">{{ translate('No vouchers found') }}</p>
                            <a href="{{ route('accounts.vouchers.create') }}" class="btn btn-primary btn-sm mt-3">
                                <i class="las la-plus"></i> {{ translate('Create First Voucher') }}
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Mobile Card View -->
    <div class="d-block d-lg-none p-3" id="mobile_vouchers_container">
        @forelse($vouchers ?? [] as $voucher)
            <div class="card border shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="mb-1">{{ $voucher->voucher_no }}</h6>
                            <small class="text-muted">{{ $voucher->date->format('d M Y') }}</small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm" data-toggle="dropdown">
                                <i class="fa fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="javascript:void(0)" onclick="printVoucher({{ $voucher->id }})">
                                    <i class="las la-print mr-2"></i> {{ translate('Print') }}
                                </a>
                                <a class="dropdown-item" href="{{ route('accounts.vouchers.edit', $voucher->id) }}">
                                    <i class="las la-edit mr-2"></i> {{ translate('Edit') }}
                                </a>
                                <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteVoucher({{ $voucher->id }})">
                                    <i class="las la-trash mr-2"></i> {{ translate('Delete') }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">{{ translate('Type') }}</small>
                            <strong>
                                @if($voucher->type == 'CP') {{ translate('Cash Payment') }}
                                @elseif($voucher->type == 'BP') {{ translate('Bank Payment') }}
                                @elseif($voucher->type == 'CR') {{ translate('Cash Receipt') }}
                                @elseif($voucher->type == 'BR') {{ translate('Bank Receipt') }}
                                @elseif($voucher->type == 'JV') {{ translate('Journal Voucher') }}
                                @elseif($voucher->type == 'CV') {{ translate('Contra Voucher') }}
                                @endif
                            </strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">{{ translate('Amount') }}</small>
                            <strong class="text-success">{{ number_format($voucher->amount, 2) }} ৳</strong>
                        </div>
                        <div class="col-12">
                            <small class="text-muted d-block">{{ translate('Note') }}</small>
                            <small>{{ Str::limit($voucher->description, 80) ?? '-' }}</small>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-5">
                <i class="las la-receipt la-3x text-muted mb-3 d-block"></i>
                <p class="text-muted">{{ translate('No vouchers found') }}</p>
            </div>
        @endforelse
    </div>

    <!-- Footer with Pagination -->
    <div class="card-footer bg-white">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div class="mb-2 mb-md-0">
                <strong>{{ translate('Total Vouchers') }}: <span id="total_count">{{ ($vouchers ?? collect())->count() }}</span></strong>
            </div>
            <div id="pagination_links">
                {{ ($vouchers ?? collect())->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Print Modal -->
<div class="modal fade" id="printVoucherModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">{{ translate('Voucher Details') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="print_voucher_content"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">{{ translate('Print') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
function filterVouchers() {
    var voucherNo = $('#filter_voucher_no').val();
    var voucherType = $('#filter_voucher_type').val();
    var dateRange = $('#filter_date_range').val();
    
    $.ajax({
        url: '{{ route("accounts.vouchers.filter") }}',
        type: 'GET',
        data: {
            voucher_no: voucherNo,
            voucher_type: voucherType,
            date_range: dateRange
        },
        success: function(response) {
            if (response.html) {
                $('#vouchers_table_body').html(response.html);
            }
        },
        error: function(xhr) {
            showNotification('danger', '{{ translate("Error filtering vouchers") }}');
        }
    });
}

function sortBy(field) {
    var direction = $('#sort_direction').val() === 'asc' ? 'desc' : 'asc';
    $('#sort_field').val(field);
    $('#sort_direction').val(direction);
    filterVouchers();
}

function printVoucher(id) {
    window.open('{{ route("accounts.vouchers.print", "") }}/' + id, '_blank');
}

function deleteVoucher(id) {
    if (confirm('{{ translate("Are you sure? This action cannot be undone.") }}')) {
        $.ajax({
            url: '{{ route("accounts.vouchers.delete", "") }}/' + id,
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
            error: function(xhr) {
                showNotification('danger', '{{ translate("Error deleting voucher") }}');
            }
        });
    }
}

function showNotification(type, message) {
    if (typeof AIZ !== 'undefined' && AIZ.plugins && AIZ.plugins.notify) {
        AIZ.plugins.notify(type, message);
    } else {
        alert(type + ': ' + message);
    }
}
</script>