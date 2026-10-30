@extends('backend.layouts.app')


@section('content')

<style>
.order-note-textarea {
    height: 40px !important;   /* same as input */
    overflow: hidden;
}
</style>

    <div class="card rounded-2 p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between">

            <!-- Left Title -->
            <h5 class="mb-2 mb-md-0 font-weight-bold align-items-center">Order Details<span class="fs-14 fw-400 ml-3">#{{ $order->code }}</span></h5>

            <!-- Right Buttons -->
            <div class="d-flex flex-wrap">
                <a href="{{ route('order-edit', $order->id) }}"
                class="btn btn-outline-primary btn-sm mr-2 mb-2 mb-md-0 rounded-2">
                    <i class="las la-edit"></i> Edit Order
                </a>

                <a href="{{ route('invoice.download', $order->id) }}" class="btn btn-outline-primary btn-sm mr-2 mb-2 mb-md-0 rounded-2">
                    <i class="fa fa-print"></i> Print
                </a>

                <a href="{{ route('pay-slip', $order->id) }}" class="btn btn-outline-primary btn-sm mr-2 mb-2 mb-md-0 rounded-2">
                    <i class="las la-file-invoice"></i> Order Pay Slip
                </a>

                @can('delete_order')
                <a href="#"
                class="btn btn-danger btn-sm confirm-delete"
                data-href="{{ route('orders.destroy', $order->id) }}">
                <i class="las la-trash"></i> Delete
                </a>
                @endcan

            </div>

        </div>
    </div>
    <div class="card p-3">
        <h6 class="pb-2 font-weight-bold border-bottom">Order Controls</h6>

        @php
            $delivery_status = $order->delivery_status;
            $payment_status = $order->payment_status;
        @endphp

        <div class="row">

            <!-- Delivery Status -->
            <div class="col-lg col-md-6 col-12 mb-3">
                <label>{{ translate('Delivery Status') }}</label>
                @if (auth()->user()->can('update_order_delivery_status') && $delivery_status != 'delivered' && $delivery_status != 'cancelled')
                    <select class="form-control aiz-selectpicker" id="update_delivery_status">
                        <option value="pending" {{ $delivery_status == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="confirmed" {{ $delivery_status == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                        <option value="picked_up" {{ $delivery_status == 'picked_up' ? 'selected' : '' }}>Picked Up</option>
                        <option value="on_the_way" {{ $delivery_status == 'on_the_way' ? 'selected' : '' }}>On The Way</option>
                        <option value="courier_assigned" {{ $delivery_status == 'courier_assigned' ? 'selected' : '' }} disabled>Courier Assigned</option>
                        <option value="pathao" {{ $delivery_status == 'pathao' ? 'selected' : '' }}>Send Pathao Courier</option>
                        <option value="steadfast" {{ $delivery_status == 'steadfast' ? 'selected' : '' }}>Send Steadfast Courier</option>
                        <option value="delivered" {{ $delivery_status == 'delivered' ? 'selected' : '' }}>Delivered</option>
                        <option value="cancelled" {{ $delivery_status == 'cancelled' ? 'selected' : '' }}>Cancel</option>
                    </select>
                @else
                    <input type="text" class="form-control" value="{{ $delivery_status }}" disabled>
                @endif

                <div id="pathao_location_wrapper" style="display:none; margin-top:10px;">
                    <label for="pathao_city_id">{{ translate('Pathao City') }}</label>
                    <select class="form-control" id="pathao_city_id">
                        <option value="">{{ translate('Loading...') }}</option>
                    </select>

                    <label for="pathao_zone_id" class="mt-2">{{ translate('Pathao Zone') }}</label>
                    <select class="form-control" id="pathao_zone_id">
                        <option value="">{{ translate('Select city first') }}</option>
                    </select>

                    <button type="button" class="btn btn-primary btn-sm mt-2" id="pathao_send_btn">
                        {{ translate('Send to Pathao') }}
                    </button>
                </div>
            </div>

            <!-- Payment Status -->
            <div class="col-lg col-md-6 col-12 mb-3">
                <label>{{ translate('Payment Status') }}</label>
                <small>{{$payment_status}}
                </small>
                {{--@if (auth()->user()->can('update_order_payment_status'))
                    <select class="form-control aiz-selectpicker" id="update_payment_status">
                        <option value="unpaid" {{ $payment_status == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                        <option value="paid" {{ $payment_status == 'paid' ? 'selected' : '' }}>Paid</option>
                    </select>
                @else
                    <input type="text" class="form-control" value="{{ $payment_status }}" disabled>
                @endif--}}
            </div>

            <!-- Assign Courier -->
            <div class="col-lg col-md-6 col-12 mb-3">
                <label>{{ translate('Assign Courier') }}</label>
                <select class="form-control aiz-selectpicker" id="assign_deliver_boy">
                    <option value="">{{ translate('Unassigned') }}</option>
                    @foreach ($delivery_boys as $delivery_boy)
                        <option value="{{ $delivery_boy->id }}" {{ $order->assign_delivery_boy == $delivery_boy->id ? 'selected' : '' }}>
                            {{ $delivery_boy->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Tracking Code -->
            <div class="col-lg col-md-6 col-12 mb-3">
                <label>{{ translate('Tracking Code') }}</label>
                <input id="update_tracking_code" type="text" class="form-control" value="{{ $order->tracking_code }}">
            </div>

            <!-- Order Note -->
            <div class="col-lg col-md-6 col-12 mb-3">
                <label>{{ translate('Order Note') }}</label>
                <textarea id="order_note" class="form-control order-note-textarea">{{ $order->order_note ?? '' }}</textarea>
            </div>

        </div>
    </div>


    <div class="row">

        <div class="col-12 col-md-8 pr-md-0 mb-3">
            <div class="card p-3" style="border-radius:10px;">

                <h6 class="mb-2 d-flex justify-content-between">
                    <span class="font-weight-bold">Products</span>
                    <span>
                        {{ $order->orderDetails->count() }}
                        {{ $order->orderDetails->count() == 1 ? 'item' : 'items' }}
                    </span>
                </h6>

                <div class="mx-md-3">
                    <div class="row text-muted py-2 border-bottom border-top align-items-center d-none d-md-flex">
                        <div class="col-md-1">#</div>
                        <div class="col-md-4">Product</div>
                        <div class="col-md-2">Delivery</div>
                        <div class="col-md-1 text-center">Qty</div>
                        <div class="col-md-2 text-right">Price</div>
                        <div class="col-md-2 text-right">Total</div>
                    </div>

                    @foreach ($order->orderDetails as $key => $orderDetail)
                    <div class="row align-items-center py-3 border-bottom">

                        <div class="col-12 col-md-5 mb-2 mb-md-0">
                            <div class="d-flex align-items-center">
                                <div class="col-1 p-0 text-muted d-none d-md-block">
                                    {{ str_pad($key + 1, 2, '0', STR_PAD_LEFT) }}
                                </div>

                                @if ($orderDetail->product != null)
                                    <img height="45" width="45" class="rounded mr-3" style="object-fit: cover;"
                                        src="{{ uploaded_asset($orderDetail->product->thumbnail_img) }}">
                                @endif

                                <div>
                                    <div class="font-weight-bold">
                                        {{ $orderDetail->product->getTranslation('name') ?? 'Product Name' }}@if($orderDetail->delivery_status == 'exchange')<span class="badge badge-danger badge-sm rounded-pill w-auto">{{ $orderDetail->delivery_status  }} </span>@endif
                                    </div>
                                    <small class="text-muted d-block">
                                        SKU: {{ optional($orderDetail->product->stocks->where('variant', $orderDetail->variation)->first())->sku ?? 'N/A' }}
                                    </small>
                                    <small class="text-muted">{{ $orderDetail->variation }}</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-md-2 text-muted">
                            <span class="d-md-none d-block font-weight-bold text-dark small">Delivery</span>
                            @if ($order->shipping_type == 'home_delivery') Home Delivery
                            @elseif ($order->shipping_type == 'pickup_point') Pickup Point
                            @elseif ($order->shipping_type == 'carrier') Carrier
                            @endif
                        </div>

                        <div class="col-6 col-md-1 text-md-center text-right">
                            <span class="d-md-none d-block font-weight-bold text-dark small">Qty</span>
                            <span class="badge badge-light px-2 py-1">
                                {{ $orderDetail->quantity }}
                            </span>
                        </div>

                        <div class="col-6 col-md-2 text-md-right text-muted mt-2 mt-md-0">
                            <span class="d-md-none d-block font-weight-bold text-dark small">Price</span>
                            {{ single_price($orderDetail->price / $orderDetail->quantity) }}
                        </div>

                        <div class="col-6 col-md-2 text-right font-weight-bold text-success mt-2 mt-md-0">
                            <span class="d-md-none d-block font-weight-bold text-dark small">Total</span>
                            {{ single_price($orderDetail->price) }}
                        </div>

                    </div>
                    @endforeach
                </div>

                <div class="mt-2 pt-3 px-md-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Sub Total</span>
                        <span>{{ single_price($order->orderDetails->sum('price')) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Shipping</span>
                        <span>{{ single_price($order->orderDetails->first()->shipping_cost) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Tax</span>
                        <span>{{ single_price($order->orderDetails->sum('tax')) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Discount</span>
                        @php
                            $order_discount = $order->extra_discount + $order->coupon_discount;
                        @endphp
                        <span class="text-danger">{{ single_price($order_discount) ?? '' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mt-2 pt-2 border-top">
                        <strong>Total</strong>
                        <strong class="h5">{{ single_price($order->grand_total) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            {{-- customer section --}}
            <div class="card p-3">
                <div>
                    <h6 class="font-weight-bold pb-2 border-bottom mb-3 text-dark">Customer</h6>

                    <div class="row no-gutters mb-3">
                        <div class="col-auto mr-3">
                            <div class="bg-secondary rounded" style="width: 50px; height: 50px;"></div>
                        </div>

                        @php
                            $shipping = json_decode($order->shipping_address);
                            $name = $order->user->name ?? ($shipping->name ?? '');
                            $email = $order->user->email ?? ($shipping->email ?? '');
                            $phone = $order->user->phone ?? ($shipping->phone ?? '');

                            if ($shipping) {
                                $address = trim(
                                    ($shipping->address ?? '') . ', ' .
                                    ($shipping->city ?? '') .
                                    (isset($shipping->state) ? ', '.$shipping->state : '') .
                                    (isset($shipping->postal_code) ? ' - '.$shipping->postal_code : '') .
                                    ', ' . ($shipping->country ?? 'Bangladesh')
                                );
                            } else {
                                $address = '';
                            }
                        @endphp

                        <div class="col">
                            <div class="font-weight-bold text-dark">{{ $name }}</div>
                            <div class="d-flex align-items-center text-secondary mt-1 small">
                                <i class="fas fa-envelope mr-2"></i>
                                <span class="text-truncate">{{ $email }}</span>
                            </div>

                            <div class="d-flex flex-wrap align-items-center justify-content-between mt-2" style="gap: 8px;">

                                <div class="text-secondary d-flex align-items-center">
                                    <i class="fas fa-phone mr-2"></i>
                                    <span class="small font-weight-bold">{{ $phone }}</span>
                                </div>

                                <a href="https://wa.me/{{$phone}}" target="_blank"
                                    class="btn btn-success btn-sm px-2"
                                    style="font-size: 11px; white-space: nowrap; flex-shrink: 0;">
                                    <i class="fab fa-whatsapp mr-1"></i>WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="mx-1">
                        <div class="mb-3">
                            <h6 class="font-weight-bold"><i class="fas fa-map-marker-alt mr-2"></i>Delivery Location</h6>
                            <p class="mb-0 text-secondary small">
                                {{ $address }}
                            </p>
                        </div>

                        <div class="mb-3">
                            <h6 class="font-weight-bold d-flex flex-wrap align-items-center">
                                <span class="text-nowrap mr-2">
                                    <i class="fas fa-wallet mr-2"></i>Payment Method:
                                </span>
                                <span class="fs-14 text-secondary font-weight-normal mt-1 mt-sm-0">
                                    {{ $order->payment_type }}
                                </span>
                            </h6>
                        </div>
                    </div>

                    <div class="mx-1">
                        <h6 class="font-weight-bold">Delivery Success Rate</h6>
                        @include('backend.sales.success_rate', ['order' => $order])
                    </div>
                </div>
            </div>
            {{-- summary --}}
            <div class="card p-3">
                <div class="card border-0 shadow-sm">
                    <h6 class="font-weight-bold mb-3 pb-2 border-bottom">Order Summary</h6>

                    <div class="row no-gutters">
                        <div class="col-6 pr-2">
                            <div class="mb-3">
                            <small class="d-block font-weight-bold" style="color: #3f2a56;">Order ID</small>
                            <a href="#" class="small font-weight-bold" style="color: #4a90e2; text-decoration: none;">#{{$order->id}}</a>
                            </div>

                            <div class="mb-3">
                            <small class="d-block font-weight-bold mb-1" style="color: #3f2a56;">{{ translate('Order Status') }}</small>
                            <div class="text-left">
                                @if ($delivery_status == 'delivered')
                                    <span class="badge badge-inline badge-success px-3 py-1" style="border-radius: 5px;">
                                        {{ translate(ucfirst(str_replace('_', ' ', $delivery_status))) }}
                                    </span>
                                @elseif ($delivery_status == 'pending')
                                    <span class="badge badge-inline badge-warning px-3 py-1 text-white" style="border-radius: 5px; background-color: #ffcc00;">
                                        {{ translate(ucfirst(str_replace('_', ' ', $delivery_status))) }}
                                    </span>
                                @else
                                    <span class="badge badge-inline badge-info px-3 py-1" style="border-radius: 5px;">
                                        {{ translate(ucfirst(str_replace('_', ' ', $delivery_status))) }}
                                    </span>
                                @endif
                            </div>
                            </div>

                            @if($order->courier_name)
                            <div class="">
                            <small class="d-block font-weight-bold">Courier</small>
                            <small class="text-secondary">{{ $order->courier_name }}</small>
                            </div>
                            @endif
                        </div>

                        <div class="col-6 pl-2">
                            <div class="mb-3 text-right text-sm-left">
                                <small class="d-block font-weight-bold" style="color: #3f2a56;">Order Date</small>
                                <small class="text-secondary">{{$order->created_at}}</small>
                            </div>

                            <div class="mb-3">
                            <small class="d-block font-weight-bold mb-1" style="color: #3f2a56;">{{ translate('Payment Status') }}</small>
                                <div class="text-left">
                                    @if ($payment_status == 'paid')
                                        <span class="badge badge-inline badge-success px-3 py-1" style="border-radius: 5px;">
                                            {{ translate('Paid') }}
                                        </span>
                                    @else
                                        <span class="badge badge-inline badge-danger px-3 py-1" style="border-radius: 5px; background-color: #ff5c5c;">
                                            {{ translate('Unpaid') }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if($order->courier_tracking_id)
                                <div class="text-right text-sm-left mb-3">
                                    <small class="d-block font-weight-bold" style="color: #3f2a56;">{{ translate('Tracking') }}</small>
                                    <small class="text-secondary">{{ $order->courier_tracking_id }}</small>
                                </div>
                            @endif
                        </div>
                    </div>

                    <hr class="my-4" style="border-top: 1px solid #f0f0f0;">

                    <div class="d-flex align-items-start">
                    <div class="bg-light rounded mr-3" style="flex-shrink: 0;">
                            @php
                                $removedXML = '<?xml version="1.0" encoding="UTF-8"@endphp';
                                    $qrCode = QrCode::size(100)->generate($order->code);
                                    $qrCodeWithoutXML = str_replace($removedXML, '', $qrCode);
                                    ?>
                                {!! $qrCodeWithoutXML !!}
                    </div>
                    <div class="d-flex flex-column">
                        <h6 class="font-weight-bold m-0" style="color: #3f2a56;">Order Qr Code</h6>
                        <p class="small font-weight-bold mb-2" style="color: #6c757d;">{{ $order->code }}</p>
                        <p class="small text-muted mb-0" style="line-height: 1.2;">Scan to Copy Order Code on Mobile.</p>
                    </div>
                    </div>
                </div>
            </div>


            </div>
        </div>
    </div>


    <!-- Modal -->
    <div class="modal fade" id="cancelReasonModal" tabindex="-1" aria-labelledby="cancelReasonModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelReasonModalLabel">Cancellation Reason</h5>
                    <button type="button" class="btn-close closemodal" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <textarea class="form-control" id="cancelReason" rows="3" placeholder="Enter cancellation reason"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary closemodal" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="submitCancelReason">Submit</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('modal')
    @include('backend.sales.right_sidebar')

    @include('modals.delete_modal')
@endsection

@section('script')
    <script src="{{ static_asset('assets/js/partials/success-rate.js') }}"></script> // success rate
    <script type="text/javascript">
        $('#assign_deliver_boy').on('change', function() {
            var order_id = {{ $order->id }};
            var delivery_boy = $('#assign_deliver_boy').val();
            $.post('{{ route('orders.delivery-boy-assign') }}', {
                _token: '{{ @csrf_token() }}',
                order_id: order_id,
                delivery_boy: delivery_boy
            }, function(data) {
                AIZ.plugins.notify('success', '{{ translate('Delivery boy has been assigned') }}');
            });
        });

        // newly start
        $(document).ready(function() {
            $('.closemodal').on('click', function() {
                $('#cancelReasonModal').modal('hide');

            });
            $('#update_delivery_status').on('change', function() {
                if ($(this).val() == 'cancelled') {
                    $('#cancelReasonModal').modal('show');
                } else if ($(this).val() == 'pathao') {
                    // Pathao needs a city and zone id, so the admin picks them
                    // before the order is sent.
                    $('#pathao_location_wrapper').show();
                    loadPathaoCities();
                } else {
                    $('#pathao_location_wrapper').hide();
                    updateDeliveryStatus();
                }
            });

            function loadPathaoCities() {
                if ($('#pathao_city_id option').length > 1) return;

                $.get('{{ route('pathao.cities') }}', function(res) {
                    if (!res.success) {
                        AIZ.plugins.notify('danger', res.message);
                        return;
                    }
                    var html = '<option value="">{{ translate('Select city') }}</option>';
                    $.each(res.data, function(i, c) {
                        html += '<option value="' + c.id + '">' + c.name + '</option>';
                    });
                    $('#pathao_city_id').html(html);
                }).fail(function(xhr) {
                    AIZ.plugins.notify('danger', xhr.responseJSON ? xhr.responseJSON.message :
                        'Failed to load Pathao cities');
                });
            }

            $('#pathao_city_id').on('change', function() {
                var cityId = $(this).val();
                $('#pathao_zone_id').html('<option value="">{{ translate('Loading...') }}</option>');

                if (!cityId) return;

                $.get('{{ route('pathao.zones', ['city' => '__CITY__']) }}'.replace('__CITY__', cityId),
                    function(res) {
                        if (!res.success) {
                            AIZ.plugins.notify('danger', res.message);
                            return;
                        }
                        var html = '<option value="">{{ translate('Select zone') }}</option>';
                        $.each(res.data, function(i, z) {
                            html += '<option value="' + z.zone_id + '">' + z.name + '</option>';
                        });
                        $('#pathao_zone_id').html(html);
                    }).fail(function(xhr) {
                    AIZ.plugins.notify('danger', xhr.responseJSON ? xhr.responseJSON.message :
                        'Failed to load Pathao zones');
                });
            });

            $('#pathao_send_btn').on('click', function() {
                if (!$('#pathao_city_id').val() || !$('#pathao_zone_id').val()) {
                    AIZ.plugins.notify('danger', '{{ translate('Select a Pathao city and zone first') }}');
                    return;
                }
                $(this).prop('disabled', true);
                updateDeliveryStatus();
            });

            $('#submitCancelReason').on('click', function(e) {
                e.preventDefault(); // Prevent default action if necessary
                updateDeliveryStatus();
                $('#cancelReasonModal').modal('hide');
            });

            function updateDeliveryStatus() {
                var order_id = {{ $order->id }};
                var status = $('#update_delivery_status').val();
                var cancelReason = $('#cancelReason').val();

                // Debugging: Log values to console
                console.log('Order ID:', order_id);
                console.log('Status:', status);
                console.log('Cancel Reason:', cancelReason);

                $.post('{{ route('orders.update_delivery_status') }}', {
                    _token: '{{ csrf_token() }}',
                    order_id: order_id,
                    status: status,
                    cancel_reason: cancelReason,
                    pathao_city_id: $('#pathao_city_id').val(),
                    pathao_zone_id: $('#pathao_zone_id').val()
                }, function(data) {
                    $('#pathao_send_btn').prop('disabled', false);
                    if (data.success) {
                        AIZ.plugins.notify('success',
                            '{{ translate('Delivery status has been updated') }}');
                        location.reload();
                    } else {
                        AIZ.plugins.notify('danger',
                            data.message || '{{ translate('Failed to update delivery status') }}');
                    }
                }).fail(function(xhr) {
                    // A courier error returns HTTP 422, which skips the success
                    // callback above, so the message is surfaced here.
                    $('#pathao_send_btn').prop('disabled', false);
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ?
                        xhr.responseJSON.message :
                        '{{ translate('Failed to update delivery status') }}';
                    AIZ.plugins.notify('danger', msg);
                });
            }

        });

        // end start
        $('#update_payment_status').on('change', function() {
            var order_id = {{ $order->id }};
            var status = $('#update_payment_status').val();
            $.post('{{ route('orders.update_payment_status') }}', {
                _token: '{{ @csrf_token() }}',
                order_id: order_id,
                status: status
            }, function(data) {
                AIZ.plugins.notify('success', '{{ translate('Payment status has been updated') }}');
            });
        });
        $('#update_tracking_code').on('change', function() {
            var order_id = {{ $order->id }};
            var tracking_code = $('#update_tracking_code').val();
            $.post('{{ route('orders.update_tracking_code') }}', {
                _token: '{{ @csrf_token() }}',
                order_id: order_id,
                tracking_code: tracking_code
            }, function(data) {
                AIZ.plugins.notify('success', '{{ translate('Order tracking code has been updated') }}');
            });
        });
    </script>

    <script>
        $('#order_note').on('change', function() {
            var order_id = {{ $order->id }};
            var order_note = $(this).val();

            $.post('{{ route('orders.update_order_note') }}', {
                _token: '{{ csrf_token() }}',
                order_id: order_id,
                order_note: order_note
            }, function(data) {
                if (data.success) {
                    AIZ.plugins.notify('success', '{{ translate('Order note has been updated') }}');
                } else {
                    AIZ.plugins.notify('danger', '{{ translate('Failed to update order note') }}');
                }
            });
        });
    </script>
@endsection
