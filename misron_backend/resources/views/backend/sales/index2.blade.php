@extends('backend.layouts.app')

@section('content')
    <style>
        .order-filters .form-control {
            height: 34px;
        }

        .filter-divider {
            width: 1px;
            height: 28px;
            background: #e5e7eb;
            margin-right: 15px;
        }

        .dropdown-toggle {
            padding: 5px;
        }

        /* select input */
        .outline-select {
            border: 1px solid var(--primary) !important;
            color: var(--primary) !important;
            background: transparent;
            padding: .25rem .5rem;
            border-radius: .25rem;
        }


        /* Normalizes the height of inputs and buttons in the action bar */
        .outline-select,
        #bulk-action-select,
        #page-size-select,
        .btn-outline-primary,
        .dropdown-toggle.outline-select {
            height: 35px !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        /* Fixes the select box arrow alignment */
        select.form-control {
            line-height: 1.5;
        }
    </style>
    <div class="card rounded-2 p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between">

            <!-- Left Title -->
            <h5 class="mb-2 mb-md-0 font-weight-bold">Orders</h5>

            <!-- Right Buttons -->
            <div class="d-flex flex-wrap">
                <button class="btn btn-outline-primary btn-sm mr-2 mb-2 mb-md-0 rounded-2">
                    <i class="fa fa-download"></i> Export CSV
                </button>

                <button class="btn btn-outline-primary btn-sm mr-2 mb-2 mb-md-0 rounded-2">
                    <i class="fa fa-print"></i> Print
                </button>

                <a href="{{ route('poin-of-sales.index') }}" class="btn btn-primary btn-sm rounded-2">
                    + New Order
                </a>
            </div>

        </div>
    </div>

    {{-- all order infos --}}
    <div class="row mb-3">
        <div class="col-6 col-md mb-2 pr-1">
            <div class="card border-0 bg-white h-100 p-3">
                <div class="text-muted small">All Orders</div>
                <h4 class="mb-0">{{ $totalOrders }}</h4>
                <div class="text-muted small">total entries</div>
            </div>
        </div>

        <div class="col-6 col-md mb-2 px-md-1 pl-2">
            <div class="card border-0 bg-white h-100 p-3">
                <div class="small text-warning">Pending</div>
                <h4 class="mb-0 text-warning">{{ $pendingOrders }}</h4>
                <div class="text-muted small">awaiting actions</div>
            </div>
        </div>

        <div class="col-6 col-md mb-2 px-md-1 pr-1">
            <div class="card border-0 bg-white h-100 p-3">
                <div class="small text-primary">Processing</div>
                <h4 class="mb-0 text-primary">{{ $processingOrders }}</h4>
                <div class="text-muted small">in progress</div>
            </div>
        </div>

        <div class="col-6 col-md mb-2 px-md-1 pl-2">
            <div class="card border-0 bg-white h-100 p-3">
                <div class="small text-success">Complete</div>
                <h4 class="mb-0 text-success">{{ $completeOrders }}</h4>
                <div class="text-muted small">fulfilled</div>
            </div>
        </div>

        <div class="col-12 col-md mb-2 pl-md-1 px-3">
            <div class="card border-0 bg-white h-100 p-3">
                <div class="small text-danger">Cancelled</div>
                <h4 class="mb-0 text-danger">{{ $cancelledOrders }}</h4>
                <div class="text-muted small">lost revenue</div>
            </div>
        </div>
    </div>

    <form class="" action="" id="sort_orders" method="GET">
        @if (request('funnel_id') && ctype_digit((string) request('funnel_id')))
            <input type="hidden" name="funnel_id" value="{{ (int) request('funnel_id') }}">
            <div class="alert alert-soft-primary d-flex align-items-center justify-content-between py-2">
                <span>{{ translate('Showing only orders placed through sales funnel') }} #{{ (int) request('funnel_id') }}</span>
                <a href="{{ route('all_orders.index') }}" class="btn btn-sm btn-soft-primary">{{ translate('Show all orders') }}</a>
            </div>
        @endif
        <div class="card">
            <div class="card-body py-2">

                <div class="row align-items-center order-filters mt-2">
                    <!-- Search -->
                    <div class="col-xl col-lg-3 col-md-6 mb-2 px-1">
                        <input type="text" class="form-control" name="search" value="{{ request('search') }}"
                            placeholder="Search by order ID, customer name">
                    </div>

                    <!-- Date -->
                    <div class="col-xl col-lg-2 col-md-6 mb-2 d-flex align-items-center px-1">
                        <label class="mb-0 mr-2 text-muted">Date</label>
                        {{-- <input type="text" class="form-control aiz-date-range" name="date" value="{{ request('date') }}" placeholder="mm/dd/yyyy"> --}}
                        <input type="text" class="form-control aiz-date-range" name="date" value="{{ $date }}"
                            placeholder="{{ translate('Select date') }}" data-format="DD-MM-Y" data-separator=" to "
                            data-advanced-range="true" autocomplete="off">
                    </div>

                    <!-- Payment -->
                    {{--<div class="col-xl col-lg-2 col-md-6 mb-2 d-flex align-items-center px-1">
                        <label class="mb-0 mr-2 text-muted">Payment</label>
                        <select class="form-control aiz-selectpicker" name="payment_status">
                            <option value="">All</option>
                            <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="unpaid" {{ request('payment_status') == 'unpaid' ? 'selected' : '' }}>Unpaid
                            </option>
                        </select>
                    </div>--}}

                    <!-- Status -->
                    <div class="col-xl col-lg-2 col-md-6 mb-2 d-flex align-items-center px-1">
                        <label class="mb-0 mr-2 text-muted">Status</label>
                        <select class="form-control p-2" name="delivery_status">
                            <option value="">All</option>
                            <option value="pending" {{ request('delivery_status') == 'pending' ? 'selected' : '' }}>Pending
                            </option>
                            <option value="confirmed" {{ request('delivery_status') == 'confirmed' ? 'selected' : '' }}>
                                Confirmed</option>
                            <option value="picked_up" {{ request('delivery_status') == 'picked_up' ? 'selected' : '' }}>
                                Picked Up</option>
                            <option value="on_the_way" {{ request('delivery_status') == 'on_the_way' ? 'selected' : '' }}>
                                On The Way</option>
                            <option value="delivered" {{ request('delivery_status') == 'delivered' ? 'selected' : '' }}>
                                Delivered</option>
                            <option value="cancelled" {{ request('delivery_status') == 'cancelled' ? 'selected' : '' }}>
                                Cancelled</option>
                        </select>
                    </div>

                    <!-- Courier -->
                    <div class="col-xl col-lg-2 col-md-6 mb-2 d-flex align-items-center px-1">
                        <label class="mb-0 mr-2 text-muted">Courier</label>
                        <select class="form-control p-2" name="courier">
                            <option value="">All</option>
                            <option value="steadfast" {{ request('courier') == 'steadfast' ? 'selected' : '' }}>Steadfast
                            </option>
                            <option value="pathao" {{ request('courier') == 'pathao' ? 'selected' : '' }}>Pathao</option>
                        </select>
                    </div>

                    <!-- Min Max -->
                    <div class="col-xl col-lg-2 col-md-6 mb-2 px-1">
                        <div class="d-flex align-items-center price-range">
                            <label class="mb-0 mr-2 text-muted text-nowrap">min ৳</label>

                            <input type="number" class="form-control p-2 mr-2" name="min" value="{{ request('min') }}"
                                placeholder="0">

                            <span class="text-muted mr-2">–</span>

                            <input type="number" class="form-control p-2" name="max" value="{{ request('max') }}"
                                placeholder="∞">
                        </div>
                    </div>
                 

                        
                         
                      

                    <!-- Filter -->
                    @if ($fixableOrdersCount > 0)
                    <div class="col-xl-auto col-lg-2 col-md-12 mb-2 px-1">
                        <button class="btn btn-outline-primary btn-block" style="padding: 5px 10px;" id="fix-all-payment-btn">
                            Fix All Payment ({{ $fixableOrdersCount }})
                        </button>
                    </div>
                      @endif
                    <!-- Filter -->
                    <div class="col-xl-auto col-lg-2 col-md-12 mb-2 px-1">
                        <button class="btn btn-outline-primary btn-block" style="padding: 5px 10px;">
                            Filter
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <div class="card mt-3 p-3">
            <div class="row align-items-center mb-3 flex-wrap"> <!-- flex-wrap to avoid overflow -->

                <!-- Left side: Bulk action buttons -->
                <div class="col-12 col-md d-flex flex-wrap">
                    <span id="selected-count" class="p-2 m-2 text-nowrap order-1 order-md-0">0 selected</span>

                    @canany(['delete_order', 'export_order'])
                        <div class="dropdown mb-2 mb-md-0 my-2 order-3 order-md-1">
                            <button class="btn border dropdown-toggle outline-select rounded-2" type="button"
                                data-toggle="dropdown">
                                {{ translate('Bulk Action') }}
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                @can('delete_order')
                                    <a class="dropdown-item confirm-alert" href="javascript:void(0)"
                                        data-target="#bulk-delete-modal">
                                        {{ translate('Delete selection') }}
                                    </a>
                                @endcan
                                @can('export_order')
                                    <a class="dropdown-item"
                                        href="{{ route('order-bulk-export') }}">{{ translate('Export') }}</a>
                                @endcan
                                @can('export_order')
                                    <a class="dropdown-item" href="javascript:void(0);"
                                        onclick="order_bulk_document_export('invoice')">
                                        {{ translate('Download Invoices') }}
                                    </a>
                                    <a class="dropdown-item" href="javascript:void(0);"
                                        onclick="order_bulk_document_export('payslip')">
                                        {{ translate('Download Payslips') }}
                                    </a>
                                    <a class="dropdown-item" href="javascript:void(0);"
                                        onclick="order_bulk_document_print('invoices')">
                                        {{ translate('Print Invoices') }}
                                    </a>
                                    <a class="dropdown-item" href="javascript:void(0);"
                                        onclick="order_bulk_document_print('payslips')">
                                        {{ translate('Print payslips') }}
                                    </a>
                                @endcan
                            </div>
                        </div>
                    @endcan

                    <div class="col-auto my-2 px-2 px-md-1 order-2 order-md-2" style="min-width: 220px;">
                        <select class="form-control outline-select rounded-2" id="bulk-action-select">
                            <option value="">Status Change</option>
                            <optgroup label="Delivery Status">
                                <option value="delivery_pending">Pending</option>
                                <option value="delivery_confirmed">Confirmed</option>
                                <option value="delivery_picked_up">Picked Up</option>
                                <option value="delivery_on_the_way">On The Way</option>
                                <option value="delivery_delivered">Delivered</option>
                                <option value="delivery_cancelled">Cancelled</option>
                            </optgroup>
                            <optgroup label="Payment Status">
                                <option value="payment_paid">Paid</option>
                                <option value="payment_unpaid">Unpaid</option>
                            </optgroup>
                        </select>
                    </div>

                    <input type="hidden" name="bulk_delivery_status" id="bulk_delivery_status">
                    <input type="hidden" name="bulk_payment_status" id="bulk_payment_status">

                    <div class="d-flex align-items-center my-2 order-4 order-md-3">
                        <select class="form-control outline-select rounded-2 mr-1" id="courier-select" style="min-width: 140px;">
                            <option value="">{{ translate('Select Courier') }}</option>
                            <option value="steadfast">{{ translate('Steadfast') }}</option>
                            <option value="pathao">{{ translate('Pathao') }}</option>
                        </select>
                        <button type="button"
                            class="btn btn-outline-primary px-2 rounded-2 text-nowrap ml-1"
                            id="assign-courier-btn">
                            {{ translate('Assign Courier') }}
                        </button>
                    </div>
                    <button type="button"
                        class="btn btn-outline-primary mx-0 mx-md-1 my-2 px-2 rounded-2 order-5 order-md-4"
                        id="delete-btn">
                        Delete
                    </button>

                     

                </div>

                <!-- Right side: Pagination data selector -->
                <div
                    class="col-auto d-flex align-items-center justify-content-start justify-content-md-end mt-3 mt-md-0 mx-3 mx-md-0 px-1 flex-shrink-1">
                    <label for="page-size-select" class="mr-2 mb-0 text-nowrap">Show</label>
                    <select class="form-control" id="page-size-select" style="min-width: 80px;">
                        <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                        <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20</option>
                        <option value="30" {{ request('per_page') == 30 ? 'selected' : '' }}>30</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                    </select>
                    <span class="ml-2 text-nowrap">entries</span>
                </div>

            </div>

            <table class="table aiz-table mb-0 text-center">
                <thead>
                    <tr>
                        @if (auth()->user()->can('delete_order') || auth()->user()->can('export_order'))
                            <th>
                                <div class="form-group">
                                    <div class="aiz-checkbox-inline">
                                        <label class="aiz-checkbox">
                                            <input type="checkbox" class="check-all">
                                            <span class="aiz-square-check"></span>
                                        </label>
                                    </div>
                                </div>
                            </th>
                        @else
                            <th data-breakpoints="lg">#</th>
                        @endif

                        <th width="10%">{{ translate('Order Id') }}</th>
                        <th data-breakpoints="md">{{ translate('Customer') }}</th>
                        <th data-breakpoints="md">{{ translate('Status') }}</th>
                        <th data-breakpoints="md">{{ translate('Courier') }}</th>
                        <th data-breakpoints="md">{{ translate('Total') }}</th>
                        <th data-breakpoints="md">{{ translate('Date') }}</th>

                        <th data-breakpoints="md">{{ translate('Order Note') }}</th>
                        <th data-breakpoints="md">{{ translate('Success Rate') }}</th>
                        <th class="text-center" width="12%">{{ translate('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $key => $order)
                      
                        <tr>
                            @if (auth()->user()->can('delete_order') || auth()->user()->can('export_order'))
                                <td>
                                    <div class="form-group">
                                        <div class="aiz-checkbox-inline">
                                            <label class="aiz-checkbox">
                                                <input type="checkbox" class="check-one" name="id[]"
                                                    value="{{ $order->id }}">
                                                <span class="aiz-square-check"></span>
                                            </label>
                                        </div>
                                    </div>
                                </td>
                            @else
                                <td>{{ $key + 1 + ($orders->currentPage() - 1) * $orders->perPage() }}</td>
                            @endif

                            <td>
                                {{ $order->code }}
                                @if ($order->viewed == 0)
                                    <span class="badge badge-inline badge-info">{{ translate('New') }}</span>
                                @endif
                                @if (addon_is_activated('pos_system') && $order->order_from == 'pos')
                                    <span class="badge badge-inline badge-danger">{{ translate('POS') }}</span>
                                @endif
                                @if (!empty($order->funnel_id))
                                    <a href="{{ route('all_orders.index', ['funnel_id' => $order->funnel_id]) }}" class="badge badge-inline badge-soft-primary" title="{{ translate('Placed through the sales funnel') }}: {{ optional($order->funnel)->title }}">{{ translate('Funnel') }}</a>
                                    @if (str_contains((string) $order->additional_info, 'TEST ORDER'))
                                        <span class="badge badge-inline badge-soft-warning" title="{{ translate('Placed by staff to test the page. Not counted in funnel analytics.') }}">{{ translate('Test') }}</span>
                                    @endif
                                @endif
                            </td>

                            <td>
                                @if ($order->user_id)
                                    {{ $order->user->name ?? '' }}
                                @else
                                    {{ json_decode($order->shipping_address)->name }}
                                @endif
                                <br>
                                @php
                                    $shipping = json_decode($order->shipping_address);
                                    $guestPhone = preg_replace(
                                        '/^\+?88/',
                                        '',
                                        (string) data_get($shipping, 'phone', ''),
                                    );
                                    $userPhone = trim((string) data_get($order, 'user.phone', ''));
                                    $phoneToShow = $userPhone !== '' ? $userPhone : $guestPhone;
                                @endphp
                                {{ $phoneToShow }}
                            </td>


                            <td>
                                @if ($order->delivery_status == 'delivered')
                                    <span class="badge badge-inline badge-success px-3 py-1" style="border-radius: 5px;">
                                        {{ translate(ucfirst(str_replace('_', ' ', $order->delivery_status))) }}
                                    </span>
                                @elseif ($order->delivery_status == 'pending')
                                    <span class="badge badge-inline px-3 py-1 text-white"
                                        style="border-radius: 5px; background-color: #ffcc00;">
                                        {{ translate(ucfirst(str_replace('_', ' ', $order->delivery_status))) }}
                                    </span>
                                @elseif ($order->delivery_status == 'cancelled')
                                    <span class="badge badge-inline px-3 py-1 text-white bg-danger"
                                        style="border-radius: 5px;">
                                        {{ translate(ucfirst(str_replace('_', ' ', $order->delivery_status))) }}
                                    </span>
                                @else
                                    <span class="badge badge-inline badge-info px-3 py-1" style="border-radius: 5px;">
                                        {{ translate(ucfirst(str_replace('_', ' ', $order->delivery_status))) }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-inline px-3 py-1 text-dark"
                                    style="border-radius: 5px; background-color: #e2e3e5;">
                                    {{ translate(ucfirst($order->courier_status_label)) }}
                                </span>
                            </td>


                            <!-- To this: -->
                            <td>
                                {{ single_price($order->grand_total) }}
                                <br>
                                <span
                                    class="payment-status-span
                                        @if ($order->payment_status === 'unpaid') text-danger
                                        @elseif($order->payment_status === 'paid') text-success
                                        @else text-warning @endif
                                    ">
                                    {{ translate(ucfirst($order->payment_status)) ?? '' }}
                                </span>
                                <!-- Add data attribute to mark orders that need fixing -->
                                @if ($order->courier_status == 'delivered' && $order->payment_status != 'paid')
                                    <input type="hidden" class="needs-payment-fix" value="{{ $order->id }}">
                                @endif
                            </td>

                            <td>
                                {{ $order->created_at->format('Y-m-d') }}
                            </td>

                            <td>
                                <span class="order-note-text" data-order-id="{{ $order->id }}"
                                    title="{{ $order->order_note ?? 'N/A' }}" data-bs-toggle="tooltip"
                                    data-bs-placement="top">
                                    {{ $order->order_note ? Str::limit($order->order_note, 10) : 'N/A' }}
                                </span>

                                <input type="text" class="form-control order-note-input d-none"
                                    value="{{ $order->order_note }}">
                            </td>

                            <td>
                                {{-- success rate --}}
                                @include('backend.sales.success_rate', ['order' => $order])
                            </td>

                            <td class="text-right">
                                <a class="btn btn-soft-info btn-icon btn-sm" href="{{ route('order-edit', $order->id) }}"
                                    title="{{ translate('Edit Order') }}">
                                    <i class="las la-edit"></i>
                                </a>
                                @if (addon_is_activated('pos_system') && $order->order_from == 'pos')
                                    <a class="btn btn-soft-success btn-icon btn-sm"
                                        href="{{ route('admin.invoice.thermal_printer', $order->id) }}" target="_blank"
                                        title="{{ translate('Thermal Printer') }}">
                                        <i class="las la-print"></i>
                                    </a>
                                @endif
                                @can('view_order_details')
                                    @php
                                        $order_detail_route = route('orders.show', encrypt($order->id));
                                        if (Route::currentRouteName() == 'seller_orders.index') {
                                            $order_detail_route = route('seller_orders.show', encrypt($order->id));
                                        } elseif (Route::currentRouteName() == 'pick_up_point.index') {
                                            $order_detail_route = route(
                                                'pick_up_point.order_show',
                                                encrypt($order->id),
                                            );
                                        }
                                        if (Route::currentRouteName() == 'inhouse_orders.index') {
                                            $order_detail_route = route('inhouse_orders.show', encrypt($order->id));
                                        }
                                    @endphp
                                    <a class="btn btn-soft-primary btn-icon btn-sm" href="{{ $order_detail_route }}"
                                        title="{{ translate('View') }}">
                                        <i class="las la-eye"></i>
                                    </a>
                                @endcan
                                <a class="btn btn-soft-info btn-icon btn-sm"
                                    href="{{ route('invoice.download', $order->id) }}"
                                    title="{{ translate('Download Invoice') }}">
                                    <i class="las la-download"></i>
                                </a>
                                <a class="btn btn-soft-secondary btn-icon btn-sm"
                                    href="{{ route('pay-slip', $order->id) }}" title="{{ translate('Order Payslip') }}">
                                    <i class="las la-file-invoice"></i>
                                </a>
                                {{--@can('delete_order')
                                    <a href="#" class="btn btn-soft-danger btn-icon btn-sm confirm-delete"
                                        data-href="{{ route('orders.destroy', $order->id) }}"
                                        title="{{ translate('Delete') }}">
                                        <i class="las la-trash"></i>
                                    </a>
                                @endcan--}}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="aiz-pagination">
                {{ $orders->appends(request()->input())->links() }}
            </div>

        </div>
    </form>
@endsection

@section('modal')
    {{-- need for success rate --}}
    @include('backend.sales.right_sidebar')
    <!-- Delete modal -->
    @include('modals.delete_modal')
    <!-- Bulk Delete modal -->
    @include('modals.bulk_delete_modal')
@endsection

@section('script')
    <script src="{{ static_asset('assets/js/partials/success-rate.js') }}"></script> // success rate
    <script type="text/javascript">
        $(document).on("change", ".check-all", function() {
            if (this.checked) {
                // Iterate each checkbox
                $('.check-one:checkbox').each(function() {
                    this.checked = true;
                });
            } else {
                $('.check-one:checkbox').each(function() {
                    this.checked = false;
                });
            }

        });


        $('#bulk-action-select').on('change', function() {

            let value = $(this).val();
            if (!value) return;

            let parts = value.split('_');
            let type = parts[0];
            let status = parts.slice(1).join('_');

            $('#bulk_delivery_status').val('');
            $('#bulk_payment_status').val('');

            if (type === 'delivery') {
                $('#bulk_delivery_status').val(status);
            }

            if (type === 'payment') {
                $('#bulk_payment_status').val(status);
            }

            $('#sort_orders').submit();
        });

        $('#page-size-select').on('change', function() {

            let perPage = $(this).val();
            let url = new URL(window.location.href);

            url.searchParams.set('per_page', perPage);

            window.location.href = url.toString();
        });

        function updateSelectedCount() {
            let count = $('.check-one:checked').length;
            $('#selected-count').text(count + ' selected');
        }

        $(document).on('change', '.check-one', function() {
            updateSelectedCount();
        });

        $(document).on('change', '.check-all', function() {

            if (this.checked) {
                $('.check-one').prop('checked', true);
            } else {
                $('.check-one').prop('checked', false);
            }

            updateSelectedCount();
        });

        function bulk_delete() {
            var data = new FormData($('#sort_orders')[0]);
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{ route('bulk-order-delete') }}",
                type: 'POST',
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response == 1) {
                        location.reload();
                    }
                }
            });
        }

        $('#assign-courier-btn').on('click', function() {
            var courier = $('#courier-select').val();
            if (!courier) {
                AIZ.plugins.notify('warning', '{{ translate('Please select a courier') }}');
                return;
            }
            var selected = [];
            $('.check-one:checked').each(function() {
                selected.push($(this).val());
            });
            if (selected.length === 0) {
                AIZ.plugins.notify('warning', '{{ translate('Please select orders') }}');
                return;
            }
            $.ajax({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                url: "{{ route('orders.bulk-assign-courier') }}",
                type: 'POST',
                data: {
                    ids: selected,
                    courier: courier
                },
                success: function(data) {
                    if (data.success) {
                        AIZ.plugins.notify('success', data.message);
                        if (data.errors && data.errors.length > 0) {
                            console.log('Courier errors:', data.errors);
                        }
                        location.reload();
                    } else {
                        AIZ.plugins.notify('danger', data.message);
                    }
                },
                error: function() {
                    AIZ.plugins.notify('danger', '{{ translate('Failed to assign courier') }}');
                }
            });
        });

        function order_bulk_export() {
            var url = '{{ route('order-bulk-export') }}';
            $("#sort_orders").attr("action", url);
            $('#sort_orders').submit();
            $("#sort_orders").attr("action", '');
        }

        function order_bulk_document_export(type) {
            const form = $('#sort_orders');
            const url = '{{ route('bulk.document.export') }}';

            // Set form action
            form.attr('action', url);

            // Ensure there's a hidden input for "type"
            let typeInput = form.find('input[name="type"]');
            if (typeInput.length === 0) {
                form.append('<input type="hidden" name="type" value="' + type + '">');
            } else {
                typeInput.val(type);
            }

            form.submit();

            // Optional: Clear action after short delay
            setTimeout(() => {
                form.attr('action', '');
            }, 1000);
        }

        function order_bulk_document_print(type) {
            const form = $('#sort_orders');
            const url = '{{ route('bulk.document.print') }}';

            // Set form action
            form.attr('action', url);

            // Ensure there's a hidden input for "type"
            let typeInput = form.find('input[name="type"]');
            if (typeInput.length === 0) {
                form.append('<input type="hidden" name="type" value="' + type + '">');
            } else {
                typeInput.val(type);
            }

            // Submit the form (which includes checked checkboxes for IDs)
            form.submit();

            // Optional: Clear action after short delay
            setTimeout(() => {
                form.attr('action', '');
            }, 1000);
        }
    </script>
    <script>
        $(document).ready(function() {
            $('[data-bs-toggle="tooltip"]').tooltip();

            // Double click to edit
            $(document).on('dblclick', '.order-note-text', function() {
                var $textSpan = $(this);
                var $input = $textSpan.siblings('.order-note-input');

                $textSpan.addClass('d-none');
                $input.removeClass('d-none').focus();
            });

            $(document).on('blur', '.order-note-input', saveOrderNote);
            $(document).on('keypress', '.order-note-input', function(e) {
                if (e.which == 13) { // Enter key
                    $(this).blur();
                }
            });

            function saveOrderNote() {
                var $input = $(this);
                var newNote = $input.val().trim();
                var $textSpan = $input.siblings('.order-note-text');
                var orderId = $textSpan.data('order-id');

                $.post('{{ route('orders.update_order_note') }}', {
                    _token: '{{ csrf_token() }}',
                    order_id: orderId,
                    order_note: newNote
                }, function(data) {
                    if (data.success) {
                        if (newNote === '') {
                            $textSpan.text('N/A');
                            $textSpan.attr('title', '');
                        } else {
                            $textSpan.text(newNote.length > 20 ? newNote.substring(0, 20) + '...' :
                                newNote);
                            $textSpan.attr('title', newNote);
                        }
                        $textSpan.removeClass('d-none');
                        $input.addClass('d-none');

                        AIZ.plugins.notify('success', '{{ translate('Order note updated') }}');
                    } else {
                        AIZ.plugins.notify('danger', '{{ translate('Update failed') }}');
                    }
                });
            }
        });
    </script>
   <script>
$(document).ready(function() {
    // Fix All Payment Button Click
    $('#fix-all-payment-btn').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.html();
        
        // Confirm action
        if (!confirm('Are you sure you want to update payment status to PAID for all delivered orders?')) {
            return;
        }
        
        // Show loading state
        $btn.html('<i class="las la-spinner la-spin"></i> Updating...').prop('disabled', true);
        
        // Make AJAX request
        $.ajax({
            url: '{{ route("orders.fix-all-payment") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    AIZ.plugins.notify('success', response.message);
                    
                    // Update all rows in the table
                    if (response.updated_order_ids.length > 0) {
                        $.each(response.updated_order_ids, function(index, orderId) {
                            var $row = $('input.check-one[value="' + orderId + '"]').closest('tr');
                            
                            // Update payment status span
                            var $paymentSpan = $row.find('.payment-status-span');
                            $paymentSpan
                                .removeClass('text-danger text-warning')
                                .addClass('text-success')
                                .text('Paid');
                        });
                    }
                    
                    // Change button to success state
                    $btn.html('<i class="las la-check-circle"></i> Fixed!')
                        .removeClass('btn-warning')
                        .addClass('btn-success');
                    
                    // Reload page after 2 seconds to refresh counts
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                    
                } else {
                    AIZ.plugins.notify('danger', response.message);
                    $btn.html(originalText).prop('disabled', false);
                }
            },
            error: function(xhr) {
                console.error('Error:', xhr);
                AIZ.plugins.notify('danger', 'Something went wrong! Please try again.');
                $btn.html(originalText).prop('disabled', false);
            }
        });
    });
});
</script>
@endsection
