@extends('backend.layouts.app')

@section('content')
    <div class="card">
        <form class="" action="" id="sort_orders" method="GET">
            <div class="card-header row gutters-5">
                <div class="col">
                    <h5 class="mb-md-0 h6">{{ translate('All Orders') }}</h5>
                </div>

                <div class="col-8">
                    <div class="row">
                        <div class="col-3 my-2">
                            <select class="form-control aiz-selectpicker" name="bulk_delivery_status">
                                <option value="">{{ translate('Bulk Delivery Status change') }}</option>
                                <option value="pending">{{ translate('Bulk Pending') }}</option>
                                <option value="confirmed">{{ translate('Bulk Confirmed') }}</option>
                                <option value="picked_up">{{ translate('Bulk Picked Up') }}</option>
                                <option value="on_the_way">{{ translate('Bulk On The Way') }}</option>
                                <option value="steadfast">{{ translate('Bulk Steadfast Couirer') }}</option>
                                <option value="delivered">{{ translate('Bulk Delivered') }}</option>
                                <option value="cancelled">{{ translate('Bulk Cancel') }}</option>
                            </select>
                        </div>


                        <div class="col-3 my-2">
                            <select class="form-control aiz-selectpicker" name="bulk_payment_status">
                                <option value="">{{ translate('Bulk Payment Status change') }}</option>
                                <option value="paid">{{ translate('Bulk Paid') }}</option>
                                <option value="unpaid">{{ translate('Bulk Unpaid') }}</option>
                            </select>
                        </div>

                        <div class="col-3 my-2">
                            <select class="form-control aiz-selectpicker" name="delivery_status" id="delivery_status">
                                <option value="">{{ translate('Filter by Delivery Status') }}</option>
                                <option value="pending" @if ($delivery_status == 'pending') selected @endif>
                                    {{ translate('Pending') }}
                                </option>
                                <option value="confirmed" @if ($delivery_status == 'confirmed') selected @endif>
                                    {{ translate('Confirmed') }}</option>
                                <option value="picked_up" @if ($delivery_status == 'picked_up') selected @endif>
                                    {{ translate('Picked Up') }}</option>
                                <option value="on_the_way" @if ($delivery_status == 'on_the_way') selected @endif>
                                    {{ translate('On The Way') }}</option>
                                <option value="delivered" @if ($delivery_status == 'delivered') selected @endif>
                                    {{ translate('Delivered') }}</option>
                                <option value="cancelled" @if ($delivery_status == 'cancelled') selected @endif>
                                    {{ translate('Cancel') }}</option>
                            </select>
                        </div>

                        <div class="col-3 my-2">
                            <select class="form-control aiz-selectpicker" name="payment_status" id="payment_status">
                                <option value="">{{ translate('Filter by Payment Status') }}</option>
                                <option value="paid"
                                    @isset($payment_status) @if ($payment_status == 'paid') selected @endif @endisset>
                                    {{ translate('Paid') }}</option>
                                <option value="unpaid"
                                    @isset($payment_status) @if ($payment_status == 'unpaid') selected @endif @endisset>
                                    {{ translate('Unpaid') }}</option>
                            </select>
                        </div>

                        <div class="col-3 my-2">
                            <div class="form-group mb-0">
                                <input type="text" class="aiz-date-range form-control" value="{{ $date }}"
                                    name="date" placeholder="{{ translate('Filter by date') }}" data-format="DD-MM-Y"
                                    data-separator=" to " data-advanced-range="true" autocomplete="off">
                            </div>
                        </div>

                        <div class="col-3 my-2">
                            <div class="form-group mb-0">
                                <input type="text" class="form-control" id="search"
                                    name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset
                                    placeholder="{{ translate('Type Order code & hit Enter') }}">
                            </div>
                        </div>
                        
                        <div class="col-3 my-2">
                            <div class="form-group mb-0">
                                <input type="text" class="form-control" id="search"
                                    name="user_search"@isset($user_search) value="{{ $user_search }}" @endisset
                                    placeholder="{{ translate('Type name, phone or email') }}">
                            </div>
                        </div>

                        <div class="col-3 my-2">
                            @canany(['delete_order', 'export_order'])
                                <div class="dropdown mb-2 mb-md-0">
                                    <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
                                        {{ translate('Bulk Action') }}
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        @can('delete_order')
                                            <a class="dropdown-item confirm-alert" href="javascript:void(0)"
                                                data-target="#bulk-delete-modal">{{ translate('Delete selection') }}</a>
                                        @endcan
                                        @can('export_order')
                                            <a class="dropdown-item"
                                                href="{{ route('order-bulk-export') }}">{{ translate('Export') }}</a>
                                        @endcan
                                        @can('export_order')
                                            <a class="dropdown-item" href="javascript:void(0);" onclick="order_bulk_document_export('invoice')">
                                                {{ translate('Download Invoices') }}
                                            </a>
                                            <a class="dropdown-item" href="javascript:void(0);" onclick="order_bulk_document_export('payslip')">
                                                {{ translate('Download Payslips') }}
                                            </a>
                                            <a class="dropdown-item" href="javascript:void(0);" onclick="order_bulk_document_print('invoices')">
                                                {{ translate('Print Invoices') }}
                                            </a>
                                            <a class="dropdown-item" href="javascript:void(0);" onclick="order_bulk_document_print('payslips')">
                                                {{ translate('Print payslips') }}
                                            </a>
                                        @endcan
                                    </div>
                                </div>
                            @endcan
                        </div>
                    </div>
                </div>

                <div class="col-auto">
                    <div class="form-group mb-0">
                        <button type="submit" class="btn btn-primary">{{ translate('Filter + Action') }}</button>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <table class="table aiz-table mb-0">
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

                            <th>{{ translate('Order Code') }}</th>
                            <th data-breakpoints="md">{{ translate('Num. of Products') }}</th>
                            <th data-breakpoints="md">{{ translate('Customer') }}</th>
                            {{-- <th data-breakpoints="md">{{ translate('Seller') }}</th> --}}
                            <th data-breakpoints="md">{{ translate('Phone') }}</th>
                            <th data-breakpoints="md">{{ translate('Amount') }}</th>
                            <th data-breakpoints="md">{{ translate('Delivery Status') }}</th>
                            <th data-breakpoints="md">{{ translate('Payment method') }}</th>
                            <th data-breakpoints="md">{{ translate('Payment Status') }}</th>
                            {{-- @if (addon_is_activated('refund_request'))
                            <th>{{ translate('Refund') }}</th>
                            @endif --}}
                            <th data-breakpoints="md">{{ translate('Currier Status') }}</th>
                            <th data-breakpoints="md">{{ translate('Order Note') }}</th>
                            <th class="text-right" width="15%">{{ translate('options') }}</th>
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
                                </td>
                                <td>
                                    {{ count($order->orderDetails) }}
                                </td>
                                <td>
                                    @if ($order->user_id)
                                        {{ $order->user->name ?? '' }}
                                    @else
                                        {{ json_decode($order->shipping_address)->name }}
                                    @endif
                                </td>
                                {{-- <td>
                                    @if ($order->shop)
                                        {{ $order->shop->name }}
                                    @else
                                        {{ translate('Inhouse Order') }}
                                    @endif
                                </td> --}}
                                <td>
                                    @php
                                        $shipping = json_decode($order->shipping_address);
                                        $guestPhone = preg_replace(
                                            '/^\+?88/',
                                            '',
                                            (string) data_get($shipping, 'phone', ''),
                                        );
                                        $userPhone = trim((string) data_get($order, 'user.phone', '')); // safe even if user is null
                                        $phoneToShow = $userPhone !== '' ? $userPhone : $guestPhone;
                                    @endphp
                                    {{ $phoneToShow }}
                                </td>
                                <td>
                                    {{ single_price($order->grand_total) }}
                                </td>
                                <td>
                                    {{ translate(ucfirst(str_replace('_', ' ', $order->delivery_status))) }}
                                </td>
                                <td>
                                    {{ translate(ucfirst(str_replace('_', ' ', $order->payment_type))) }}
                                </td>

                            {{-- <tr>
                                <td class="text-main text-bold">{{ translate('Courier Status') }}</td> --}}
                                {{-- <td class="text-right">
                                    @if ($pathao_status)
                                        @php
                                            $badgeClass = match (strtolower($pathao_status)) {
                                                'delivered' => 'badge-success',
                                                'pending', 'processing' => 'badge-warning',
                                                'cancelled', 'failed' => 'badge-danger',
                                                default => 'badge-info',
                                            };
                                        @endphp
                                        <span class="badge badge-inline {{ $badgeClass }}">
                                            {{ translate(ucfirst($pathao_status)) }}
                                        </span>
                                    @else
                                        <span class="badge badge-inline badge-secondary">
                                            {{ translate('Not Available') }}
                                        </span>
                                    @endif
                                </td> --}}

                               
                            {{-- </tr> --}}

                            <td>
                                @if ($order->payment_status == 'paid')
                                    <span class="badge badge-inline badge-success">{{ translate('Paid') }}</span>
                                @elseif ($order->payment_status == 'partial')
                                    <span class="badge badge-inline badge-warning text-white">{{ translate('Partial Paid') }}</span>
                                @else
                                    <span class="badge badge-inline badge-danger">{{ translate('Unpaid') }}</span>
                                @endif
                            </td>
                            {{-- @if (addon_is_activated('refund_request'))
                                <td>
                                    @if (count($order->refund_requests) > 0)
                                        {{ count($order->refund_requests) }} {{ translate('Refund') }}
                                    @else
                                        {{ translate('No Refund') }}
                                    @endif
                                </td>
                            @endif --}}

                             <td>
                                
                                @if($order->courier_status_label)
                                    <span class="badge badge-info w-100">{{ ucfirst($order->courier_status_label ) }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ translate('N/A') }}</span>
                                @endif
                               
                            </td>
                            <td>
                                <span class="order-note-text" 
                                    data-order-id="{{ $order->id }}"
                                    title="{{ $order->order_note }}" 
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top">
                                    {{ Str::limit($order->order_note, 10) ?? 'N/A' }}
                                </span>

                                <input type="text" 
                                    class="form-control order-note-input d-none" 
                                    value="{{ $order->order_note }}">
                            </td>

                            <td class="text-right">
                                <a class="btn btn-soft-info btn-icon btn-circle btn-sm"
                                    href="{{ route('order-edit', $order->id) }}" title="{{ translate('Edit Order') }}">
                                    <i class="las la-edit"></i>
                                </a>
                                @if (addon_is_activated('pos_system') && $order->order_from == 'pos')
                                    <a class="btn btn-soft-success btn-icon btn-circle btn-sm"
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
                                    <a class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                                        href="{{ $order_detail_route }}" title="{{ translate('View') }}">
                                        <i class="las la-eye"></i>
                                    </a>
                                @endcan
                                <a class="btn btn-soft-info btn-icon btn-circle btn-sm"
                                    href="{{ route('invoice.download', $order->id) }}"
                                    title="{{ translate('Download Invoice') }}">
                                    <i class="las la-download"></i>
                                </a>
                                <a class="btn btn-soft-secondary btn-icon btn-circle btn-sm"
                                    href="{{ route('pay-slip', $order->id) }}" title="{{ translate('Order Payslip') }}">
                                    <i class="las la-file-invoice"></i>
                                </a>
                                @can('delete_order')
                                    <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete"
                                        data-href="{{ route('orders.destroy', $order->id) }}"
                                        title="{{ translate('Delete') }}">
                                        <i class="las la-trash"></i>
                                    </a>
                                @endcan
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
    </div>
@endsection

@section('modal')
    <!-- Delete modal -->
    @include('modals.delete_modal')
    <!-- Bulk Delete modal -->
    @include('modals.bulk_delete_modal')
@endsection

@section('script')
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
        $(document).ready(function () {
        $('[data-bs-toggle="tooltip"]').tooltip();

        // Double click to edit
        $(document).on('dblclick', '.order-note-text', function () {
            var $textSpan = $(this);
            var $input = $textSpan.siblings('.order-note-input');

            $textSpan.addClass('d-none');
            $input.removeClass('d-none').focus();
        });

        $(document).on('blur', '.order-note-input', saveOrderNote);
        $(document).on('keypress', '.order-note-input', function (e) {
            if (e.which == 13) { // Enter key
                $(this).blur();
            }
        });

        function saveOrderNote() {
            var $input = $(this);
            var newNote = $input.val();
            var $textSpan = $input.siblings('.order-note-text');
            var orderId = $textSpan.data('order-id');

            $.post('{{ route('orders.update_order_note') }}', {
                _token: '{{ csrf_token() }}',
                order_id: orderId,
                order_note: newNote
            }, function (data) {
                if (data.success) {
                    $textSpan.text(newNote.length > 20 ? newNote.substring(0, 20) + '...' : newNote);
                    $textSpan.attr('title', newNote);
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
@endsection
