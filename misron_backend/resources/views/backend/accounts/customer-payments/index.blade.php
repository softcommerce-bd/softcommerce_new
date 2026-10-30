<div class="card shadow-sm border-0">
    <!-- Header -->
    <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <h5 class="mb-2 mb-md-0 fw-600">
            <i class="las la-truck text-primary mr-2"></i>
            {{ translate('Customer Payments') }}
        </h5>
    </div>

    <!-- Filters (optional, you can adapt them) -->
    <div class="card-body border-bottom">
        <div class="row">
            <div class="col-md-3 mb-3">
                <label>{{ translate('Order No') }}</label>
                <input type="text" id="filter_order_no" class="form-control" placeholder="Order code...">
            </div>
            <div class="col-md-3 mb-3">
                <label>{{ translate('Customer Search') }}</label>
                <input type="text" id="filter_customer_search" class="form-control"
                    placeholder="Name, phone or email...">
            </div>
            <div class="col-md-2 mb-3">
                <label>{{ translate('Courier') }}</label>
                <input type="text" id="filter_courier" class="form-control" placeholder="Courier name">
            </div>
            {{-- <div class="col-md-2 mb-3">
                <label>{{ translate('Status') }}</label>
                <select id="filter_settlement_status" class="form-control">
                    <option value="">All</option>
                    <option value="settled">Settled</option>
                    <option value="unsettled">Unsettled</option>
                </select>
            </div> --}}
            <div class="col-md-2 mb-3">
                <label>{{ translate('Date Range') }}</label>
                <input type="text" class="form-control aiz-date-range" id="filter_date_range" name="date"
                    placeholder="{{ translate('Select date') }}" data-format="DD-MM-Y" data-separator=" to "
                    data-advanced-range="true" autocomplete="off">
            </div>
            <div class="col-md-1 mb-3 d-flex align-items-end">
                <button class="btn-account-color btn-block" onclick="filterSettlements()">
                    <i class="las la-search"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Desktop Table -->
    <div class="table-responsive d-none d-lg-block">
        <table class="table table-hover mb-0">
            <thead class="bg-account-color text-white">
                <tr>
                    <th width="60">#</th>
                    <th>
                        @include('backend.accounts.customer-payments.partials.sort_link', [
                            'label' => translate('Settlement No'),
                            'field' => 'settlement_no',
                        ])
                    </th>
                    <th>{{ translate('Customer Name') }}</th>
                    <th>
                        @include('backend.accounts.customer-payments.partials.sort_link', [
                            'label' => translate('Courier'),
                            'field' => 'courier_name',
                        ])
                    </th>
                    <th>{{ translate('Total Amount') }}</th>
                    <th>
                        @include('backend.accounts.customer-payments.partials.sort_link', [
                            'label' => translate('Net Payable'),
                            'field' => 'total_amount',
                        ])
                    </th>
                    <th>{{ translate('Products') }}</th>
                    <th>
                        @include('backend.accounts.customer-payments.partials.sort_link', [
                            'label' => translate('Account'),
                            'field' => 'account_id',
                        ])
                    </th>
                    <th>
                        @include('backend.accounts.customer-payments.partials.sort_link', [
                            'label' => translate('Settlement Date'),
                            'field' => 'settlement_date',
                        ])
                    </th>
                    <th>{{ translate('Status') }}</th>
                    <th class="text-right" width="120">{{ translate('Actions') }}</th>
                </tr>
            </thead>
            <tbody id="settlements_table_body">
                @forelse($courierSettlements ?? [] as $index => $settlement)
                    @php
                        $order = $settlement->order;
                        $shippingAddress = $order ? json_decode($order->shipping_address) : null;
                        $detailsCount = 0;

                        // Safe check for settlement details count
                        if ($settlement->relationLoaded('settlementDetails') && $settlement->settlementDetails) {
                            $detailsCount = $settlement->settlementDetails->count();
                        } elseif ($order && $order->relationLoaded('orderDetails') && $order->orderDetails) {
                            $detailsCount = $order->orderDetails->count();
                        }
                    @endphp
                    <tr>
                        <td>{{ ($courierSettlements->currentPage() - 1) * $courierSettlements->perPage() + $index + 1 }}
                        </td>
                        <td>
                            <strong class="text-primary">{{ $settlement->settlement_no }}</strong>
                            @if ($order)
                                <br><small class="text-muted">Order: {{ $order->code }}</small>
                            @endif
                        </td>
                        <td>
                            <strong>
                                {{ $settlement->user->name ?? ($shippingAddress->name ?? 'N/A') }}
                            </strong>
                            @if ($shippingAddress && !empty($shippingAddress->phone))
                                <br><small class="text-muted">{{ $shippingAddress->phone }}</small>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mr-2"
                                    style="width:40px;height:40px;">
                                    <i class="las la-truck text-primary"></i>
                                </div>
                                <strong>{{ $settlement->courier_name }}</strong>
                            </div>
                        </td>
                        <td><strong>{{ single_price($settlement->total_amount) }}</strong></td>
                        <td><strong class="text-success">{{ single_price($settlement->net_payable) }}</strong></td>
                        <td>
                            <small class="text-muted d-block">
                                {{ $detailsCount }} product(s)
                            </small>
                        </td>
                        <td>
                            <span class="badge badge-soft-info px-3 py-2">
                                {{ $settlement->account->name ?? ($settlement->account_name ?? '-') }}
                            </span>
                        </td>
                        <td>{{ $settlement->settlement_date ? \Carbon\Carbon::parse($settlement->settlement_date)->format('j F Y') : 'N/A' }}
                        </td>
                        <td>
                            @if ($settlement->status === 'completed' || $settlement->status === 'settled')
                                <span class="badge badge-success px-3 py-2">Settled</span>
                            @elseif($settlement->status === 'pending')
                                <span class="badge badge-warning px-3 py-2">Pending</span>
                            @else
                                <span
                                    class="badge badge-secondary px-3 py-2">{{ ucfirst($settlement->status ?? 'Unknown') }}</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm" data-toggle="dropdown">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item" href="javascript:void(0)"
                                        onclick="viewCourierSettlement({{ $settlement->id }})">
                                        <i class="las la-eye mr-2"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr id="empty_row">
                        <td colspan="11" class="text-center py-5">
                            <i class="las la-file-invoice-dollar la-3x text-muted mb-3 d-block"></i>
                            <p class="text-muted mb-0">{{ translate('No settlements found') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Mobile Card View -->
    <div class="d-block d-lg-none p-3" id="mobile_settlements_container">
        @forelse($courierSettlements ?? [] as $settlement)
            @php
                $order = $settlement->order;
                $shippingAddress = $order ? json_decode($order->shipping_address) : null;
            @endphp
            <div class="card border shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="mb-1">{{ $settlement->settlement_no }}</h6>
                            <small
                                class="text-muted">{{ $settlement->user->name ?? ($shippingAddress->name ?? 'N/A') }}</small>
                            @if ($shippingAddress && !empty($shippingAddress->phone))
                                <br><small class="text-muted">{{ $shippingAddress->phone }}</small>
                            @endif
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm" data-toggle="dropdown">
                                <i class="fa fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="javascript:void(0)"
                                    onclick="viewCourierSettlement({{ $settlement->id }})">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Net Payable</small>
                            <strong class="text-success">{{ single_price($settlement->net_payable) }}</strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Products</small>
                            <strong>{{ $settlement->settlementDetails->count() }}</strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Courier</small>
                            <strong>{{ $settlement->courier_name }}</strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Date</small>
                            <strong>{{ optional($settlement->settlement_date)->format('d M Y') }}</strong>
                        </div>
                        <div class="col-12 mb-2">
                            <small class="text-muted d-block">Status</small>
                            @if ($settlement->status === 'completed' || $settlement->status === 'settled')
                                <span class="badge badge-success">Settled</span>
                            @elseif($settlement->status === 'pending')
                                <span class="badge badge-warning">Pending</span>
                            @else
                                <span class="badge badge-secondary">{{ ucfirst($settlement->status) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-5" id="mobile_empty_row">
                <i class="las la-file-invoice-dollar la-3x text-muted mb-3 d-block"></i>
                <p class="text-muted">{{ translate('No settlements found') }}</p>
            </div>
        @endforelse
    </div>

    <!-- Mobile Card View -->
    <!-- Mobile Card View -->
    <div class="d-block d-lg-none p-3" id="mobile_settlements_container">
        @forelse($courierSettlements ?? [] as $order)
            @php
                $netPayable = $order->grand_total - $order->settlement_fee - $order->courier_fee;
                $shippingAddress = json_decode($order->shipping_address);
             
    $detailsCount = $order && $order->orderDetails ? $order->orderDetails->count() : 0;


            @endphp
            <div class="card border shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="mb-1">#{{ $order->code }}</h6>
                            <small class="text-muted">{{ $shippingAddress->name ?? 'N/A' }}</small>
                            @if (!empty($shippingAddress->phone))
                                <br><small class="text-muted">{{ $shippingAddress->phone }}</small>
                            @endif
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm" data-toggle="dropdown">
                                <i class="fa fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="javascript:void(0)"
                                    onclick="viewCourierSettlement({{ $order->id }})">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Net Payable</small>
                            <strong class="text-success">{{ single_price($netPayable) }} </strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Products</small>
                            <strong>{{ $detailsCount ?? 0 }}</strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Courier</small>
                            <strong>{{ $order->courier_name }}</strong>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Date</small>
                            <strong>{{ optional($order->settlement_date)->format('d M Y') }}</strong>
                        </div>
                        <div class="col-12 mb-2">
                            <small class="text-muted d-block">Status</small>
                            @if ($order->settlement_status)
                                <span class="badge badge-success">Settled</span>
                            @else
                                <span class="badge badge-warning">Unsettled</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-5" id="mobile_empty_row">
                <i class="las la-file-invoice-dollar la-3x text-muted mb-3 d-block"></i>
                <p class="text-muted">{{ translate('No settled orders found') }}</p>
            </div>
        @endforelse
    </div>

    <!-- Footer / Pagination -->
    <div class="card-footer bg-white">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div class="mb-2 mb-md-0">
                <strong>Total: {{ $courierSettlements->total() ?? 0 }}</strong>
            </div>
            <div id="pagination_links">
                {{ $courierSettlements->links() ?? '' }}
            </div>
        </div>
    </div>
</div>

<!-- Settlement Details Modal -->
<div class="modal fade" id="settlementDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Settlement Details</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="settlement_details_body">
                {{-- loaded via AJAX --}}
            </div>
        </div>
    </div>
</div>

<script>
    // Make sure this function is outside any jQuery ready block so it's globally accessible
    function viewCourierSettlement(orderId) {
        $.get(`accounts/order-settlement/${orderId}/details`, function(data) {
            $('#settlement_details_body').html(data);
            $('#settlementDetailsModal').modal('show');
        });
    }

    // Optional filter function
    function filterSettlements() {
        let params = new URLSearchParams(window.location.search);
        params.set('tab', 'customer-payments');
        params.set('order_no', $('#filter_order_no').val());
        params.set('customer_search', $('#filter_customer_search').val());
        params.set('courier', $('#filter_courier').val());
        // REMOVE THIS LINE → params.set('status', $('#filter_settlement_status').val());
        params.set('date_range', $('#filter_date_range').val());
        if (!params.has('sort')) params.set('sort', 'settlement_date');
        if (!params.has('direction')) params.set('direction', 'desc');
        window.location.search = params.toString();
    }
</script>
