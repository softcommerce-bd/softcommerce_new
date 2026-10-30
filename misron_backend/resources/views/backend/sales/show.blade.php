@extends('backend.layouts.app')


@section('content')

    <div class="card">
        <div class="card-header">
            <h1 class="h2 fs-16 mb-0">{{ translate('Order Details') }}</h1>
            @if (\App\Services\Inventory\InventoryMode::installed() && ($v2Demand = \Illuminate\Support\Facades\DB::table('order_demands')->where('order_id', $order->id)->where('status', 'open')->selectRaw('COALESCE(SUM(qty_confirmed - qty_dispatched - qty_cancelled),0) outstanding, COALESCE(SUM(qty_allocated),0) allocated')->first()) && $v2Demand->outstanding > 0)
                {{-- Stock position of this order under the confirmed-order model --}}
                <span class="ml-3 badge badge-inline {{ $v2Demand->allocated >= $v2Demand->outstanding ? 'badge-success' : 'badge-warning' }}">
                    {{ translate('Confirmed demand') }}: {{ $v2Demand->outstanding }} · {{ translate('in stock for this order') }}: {{ $v2Demand->allocated }}
                </span>
                @if ($v2Demand->allocated < $v2Demand->outstanding)
                    @can('view_purchase_requirements')
                        <a class="btn btn-sm btn-primary ml-2" href="{{ route('purchasing.requirements', ['order_id' => $order->id, 'only_shortage' => 1]) }}">{{ translate('Purchase Required Products') }}</a>
                    @endcan
                @endif
            @endif
        </div>
        <div class="card-body">
            <div class="row gutters-5">
                <div class="col text-md-left text-center">
                </div>
                @php
                    $delivery_status = $order->delivery_status;
                    $payment_status = $order->payment_status;
                    $admin_user_id = App\Models\User::where('user_type', 'admin')->first()->id;
                @endphp

                <!--Assign Delivery Boy-->
                @if ($order->seller_id == $admin_user_id || get_setting('product_manage_by_admin') == 1)

                    @if (addon_is_activated('delivery_boy'))
                        <div class="col-md-3 ml-auto">
                            <label for="assign_deliver_boy">{{ translate('Assign Deliver Boy') }}</label>
                            @if (
                                ($delivery_status == 'pending' || $delivery_status == 'confirmed' || $delivery_status == 'picked_up') &&
                                    auth()->user()->can('assign_delivery_boy_for_orders'))
                                <select class="form-control aiz-selectpicker" data-live-search="true"
                                    data-minimum-results-for-search="Infinity" id="assign_deliver_boy">
                                    <option value="">{{ translate('Select Delivery Boy') }}</option>
                                    @foreach ($delivery_boys as $delivery_boy)
                                        <option value="{{ $delivery_boy->id }}"
                                            @if ($order->assign_delivery_boy == $delivery_boy->id) selected @endif>
                                            {{ $delivery_boy->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <input type="text" class="form-control"
                                    value="{{ optional($order->delivery_boy)->name }}" disabled>
                            @endif
                        </div>
                    @endif

                    <div class="col-md-3 ml-auto">
                        <label for="update_payment_status">{{ translate('Payment Status') }}</label>
                        @if (auth()->user()->can('update_order_payment_status'))
                            <select class="form-control aiz-selectpicker" data-minimum-results-for-search="Infinity"
                                id="update_payment_status">
                                <option value="unpaid" @if ($payment_status == 'unpaid') selected @endif>
                                    {{ translate('Unpaid') }}
                                </option>
                                <option value="paid" @if ($payment_status == 'paid') selected @endif>
                                    {{ translate('Paid') }}
                                </option>
                            </select>
                        @else
                            <input type="text" class="form-control" value="{{ $payment_status }}" disabled>
                        @endif
                    </div>
                    <div class="col-md-3 ml-auto">
                        <label for="update_delivery_status">{{ translate('Delivery Status') }}</label>
                        @if (auth()->user()->can('update_order_delivery_status') &&
                                $delivery_status != 'delivered' &&
                                $delivery_status != 'cancelled')
                            <select class="form-control aiz-selectpicker" data-minimum-results-for-search="Infinity"
                                id="update_delivery_status">
                                <option value="pending" @if ($delivery_status == 'pending') selected @endif>
                                    {{ translate('Pending') }}
                                </option>
                                <option value="confirmed" @if ($delivery_status == 'confirmed') selected @endif>
                                    {{ translate('Confirmed') }}
                                </option>
                                <option value="picked_up" @if ($delivery_status == 'picked_up') selected @endif>
                                    {{ translate('Picked Up') }}
                                </option>
                                <option value="on_the_way" @if ($delivery_status == 'on_the_way') selected @endif>
                                    {{ translate('On The Way') }}
                                </option>
                                <option value="pathao" @if ($delivery_status == 'pathao') selected @endif>
                                    {{ translate('Send Pathao Courier') }}
                                </option>
                                <option value="steadfast" @if ($delivery_status == 'steadfast') selected @endif>
                                    {{ translate('Send Steadfast Courier') }}
                                </option>
                                <option value="delivered" @if ($delivery_status == 'delivered') selected @endif>
                                    {{ translate('Delivered') }}
                                </option>
                                <option value="cancelled" @if ($delivery_status == 'cancelled') selected @endif>
                                    {{ translate('Cancel') }}
                                </option>
                            </select>
                        @else
                            <input type="text" class="form-control" value="{{ $delivery_status }}" disabled>
                        @endif
                    </div>
                    <div class="col-md-3 ml-auto">
                        <label for="update_tracking_code">
                            {{ translate('Tracking Code (optional)') }}
                        </label>
                        <input type="text" class="form-control" id="update_tracking_code"
                            value="{{ $order->tracking_code }}">
                    </div>
                @endif
                
            </div>
            <div class="col-md-3">

                </div>
                <div class="col-md-9 ml-auto mb-3 mt-3">
                    <label for="order_note">
                        {{ translate('Order Note') }}
                    </label>
                    <textarea class="form-control" name="order_note" id="order_note" rows="4" placeholder="Enter note here...">{{ $order->order_note ?? '' }}</textarea>
                </div>
            <div class="mb-3">
                @php
                $removedXML = '<?xml version="1.0" encoding="UTF-8"@endphp';
$qrCode = QrCode::size(100)->generate($order->code);
$qrCodeWithoutXML = str_replace($removedXML, '', $qrCode);
?>
                {!! $qrCodeWithoutXML !!}
            </div>
            <div class="row gutters-5">
                <div class="col text-md-left text-center">
                    @if (json_decode($order->shipping_address))
                        <address>
                            <strong class="text-main">
                                {{ json_decode($order->shipping_address)->name }}
                            </strong><br>
                            {{ json_decode($order->shipping_address)->email }}<br>
                            {{ json_decode($order->shipping_address)->phone }}<br>
                            {{ json_decode($order->shipping_address)->address }},
                            {{ json_decode($order->shipping_address)->city ?? '' }}, @if (isset(json_decode($order->shipping_address)->state))
                                {{ json_decode($order->shipping_address)->state }} -
                            @endif {{ json_decode($order->shipping_address)->postal_code ?? '' }}<br>
                            {{ json_decode($order->shipping_address)->country ?? 'Bangladesh' }}
                        </address>
                    @else
                        <address>
                            <strong class="text-main">
                                {{ $order->user->name }}
                            </strong><br>
                            {{ $order->user->email }}<br>
                            {{ $order->user->phone }}<br>
                        </address>
                    @endif
                    @if ($order->manual_payment && is_array(json_decode($order->manual_payment_data, true)))
                        <br>
                        <strong class="text-main">{{ translate('Payment Information') }}</strong><br>
                        {{ translate('Name') }}: {{ json_decode($order->manual_payment_data)->name }},
                        {{ translate('Amount') }}:
                        {{ single_price(json_decode($order->manual_payment_data)->amount) }},
                        {{ translate('TRX ID') }}: {{ json_decode($order->manual_payment_data)->trx_id }}
                        <br>
                        <a href="{{ uploaded_asset(json_decode($order->manual_payment_data)->photo) }}" target="_blank">
                            <img src="{{ uploaded_asset(json_decode($order->manual_payment_data)->photo) }}" alt=""
                                height="100">
                        </a>
                    @endif
                </div>
                <div class="col-md-4 ml-auto">
                    <table>
                        <tbody>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Order #') }}</td>
                                <td class="text-info text-bold text-right"> {{ $order->code }}</td>
                            </tr>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Order Status') }}</td>
                                <td class="text-right">
                                    @if ($delivery_status == 'delivered')
                                        <span class="badge badge-inline badge-success">
                                            {{ translate(ucfirst(str_replace('_', ' ', $delivery_status))) }}
                                        </span>
                                    @else
                                        <span class="badge badge-inline badge-info">
                                            {{ translate(ucfirst(str_replace('_', ' ', $delivery_status))) }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                             @if (isset($order->pathao_id) || isset($order->steadfast_id))
                            <tr>
                                <td class="text-main text-bold">{{ translate('Consignment ID') }}</td>
                                <td class="text-right">{{ $order->pathao_id ?? $order->steadfast_id}}</td> 
                            </tr>
                            @endif
                            <tr>
                                <td class="text-main text-bold">{{ translate('Order Date') }} </td>
                                <td class="text-right">{{ date('d-m-Y h:i A', $order->date) }}</td>
                            </tr>
                            <tr>
                                <td class="text-main text-bold">
                                    {{ translate('Total amount') }}
                                </td>
                                <td class="text-right">
                                    {{ single_price($order->grand_total) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Payment method') }}</td>
                                <td class="text-right">
                                    @if ($order->is_partial_payment === null)
                                        {{ translate(ucfirst(str_replace('_', ' ', $order->payment_type))) }}
                                    @else
                                        {{ translate(ucfirst(str_replace('_', ' ', $order->is_partial_payment))) }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Additional Info') }}</td>
                                <td class="text-right">{{ $order->additional_info }}</td>
                            </tr>
                            @if (!empty($order->tracking_code))
                                <tr>
                                    <td class="text-main text-bold">{{ translate('Tracking Code') }}</td>
                                    <td class="text-right">{{ $order->tracking_code ?? '' }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
            <hr class="new-section-sm bord-no">
            <div class="row">
                <div class="col-lg-12 table-responsive">
                    <table class="table-bordered aiz-table invoice-summary table">
                        <thead>
                            <tr class="bg-trans-dark">
                                <th data-breakpoints="lg" class="min-col">#</th>
                                <th width="10%">{{ translate('Photo') }}</th>
                                <th class="text-uppercase">{{ translate('Description') }}</th>
                                <th data-breakpoints="lg" class="text-uppercase">{{ translate('Delivery Type') }}</th>
                                <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                                    {{ translate('Qty') }}
                                </th>
                                <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                                    {{ translate('Price') }}</th>
                                <th data-breakpoints="lg" class="min-col text-uppercase text-right">
                                    {{ translate('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->orderDetails as $key => $orderDetail)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>
                                        @if ($orderDetail->product != null && $orderDetail->product->auction_product == 0)
                                            <a href="{{ route('product', $orderDetail->product->slug) }}"
                                                target="_blank">
                                                <img height="50"
                                                    src="{{ uploaded_asset($orderDetail->product->thumbnail_img) }}">
                                            </a>
                                        @elseif ($orderDetail->product != null && $orderDetail->product->auction_product == 1)
                                            <a href="{{ route('auction-product', $orderDetail->product->slug) }}"
                                                target="_blank">
                                                <img height="50"
                                                    src="{{ uploaded_asset($orderDetail->product->thumbnail_img) }}">
                                            </a>
                                        @else
                                            <strong>{{ translate('N/A') }}</strong>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($orderDetail->product != null && $orderDetail->product->auction_product == 0)
                                            <strong>
                                                <a href="{{ route('product', $orderDetail->product->slug) }}"
                                                    target="_blank" class="text-muted">
                                                    {{ $orderDetail->product->getTranslation('name') }}
                                                </a>
                                            </strong>
                                            <small>
                                                {{ $orderDetail->variation }}
                                            </small>
                                            <br>
                                            <small>
                                                @php
                                                    $product_stock = $orderDetail->product->stocks
                                                        ->where('variant', $orderDetail->variation)
                                                        ->first();
                                                @endphp
                                                {{ translate('SKU') }}: {{ $product_stock['sku'] }}
                                            </small>
                                        @elseif ($orderDetail->product != null && $orderDetail->product->auction_product == 1)
                                            <strong>
                                                <a href="{{ route('auction-product', $orderDetail->product->slug) }}"
                                                    target="_blank" class="text-muted">
                                                    {{ $orderDetail->product->getTranslation('name') }}
                                                </a>
                                            </strong>
                                        @else
                                            <strong>{{ translate('Product Unavailable') }}</strong>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($order->shipping_type != null && $order->shipping_type == 'home_delivery')
                                            {{ translate('Home Delivery') }}
                                        @elseif ($order->shipping_type == 'pickup_point')
                                            @if ($order->pickup_point != null)
                                                {{ $order->pickup_point->getTranslation('name') }}
                                                ({{ translate('Pickup Point') }})
                                            @else
                                                {{ translate('Pickup Point') }}
                                            @endif
                                        @elseif($order->shipping_type == 'carrier')
                                            @if ($order->carrier != null)
                                                {{ $order->carrier->name }} ({{ translate('Carrier') }})
                                                <br>
                                                {{ translate('Transit Time') . ' - ' . $order->carrier->transit_time }}
                                            @else
                                                {{ translate('Carrier') }}
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        {{ $orderDetail->quantity }}
                                    </td>
                                    <td class="text-center">
                                        {{ single_price($orderDetail->price / $orderDetail->quantity) }}
                                    </td>
                                    <td class="text-center">
                                        {{ single_price($orderDetail->price) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="clearfix float-right">
                <table class="table">
                    <tbody>
                        <tr>
                            <td>
                                <strong class="text-muted">{{ translate('Sub Total') }} :</strong>
                            </td>
                            <td>
                                {{ single_price($order->orderDetails->sum('price')) }}
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <strong class="text-muted">{{ translate('Tax') }} :</strong>
                            </td>
                            <td>
                                {{ single_price($order->orderDetails->sum('tax')) }}
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <strong class="text-muted">{{ translate('Shipping') }} :</strong>
                            </td>
                            <td>
                                {{ single_price($order->orderDetails->first()->shipping_cost) }}
                            </td>
                        </tr>
                        @if ($order->gift_box)
                        <tr>
                            <td>
                                <strong class="text-muted">{{ translate('Gift box') }} :</strong>
                            </td>
                            <td>
                                {{ single_price($order->gift_box_charge) }}
                            </td>
                        </tr>
                        @endif
                        <tr style="display:none;">
                            <td>
                                <strong class="text-muted">{{ translate('Coupon') }} :</strong>
                            </td>
                            <td>
                                {{ single_price($order->coupon_discount) }}
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <strong class="text-muted">{{ translate('Extra discount') }} :</strong>
                            </td>
                            <td>
                                {{ single_price($order->extra_discount) }}
                            </td>
                        </tr>
                         @if ($order->partial_paid_amount > 0.00)
                        <tr>
                            <td>
                                <strong class="text-muted">{{ translate('Receive Amount') }} :</strong>
                            </td>
                            <td>
                                {{ single_price($order->partial_paid_amount) }}
                            </td>
                        </tr>
                        @endif
                        <tr>
                            <td>
                                <strong class="text-muted">{{ translate('TOTAL') }} :</strong>
                            </td>
                            <td class="text-muted h5">
                                {{ single_price($order->grand_total) }}
                            </td>
                        </tr>
                        @if ($order->partial_paid_amount > 0.00)
                        <tr>
                            <td>
                                <strong class="text-muted">{{ translate('TOTAL Due') }} :</strong>
                            </td>
                            <td class="text-muted h5">
                                {{ single_price($order->due_amount) ?? 0 }}
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
                <div class="no-print text-right">
                    <a href="{{ route('invoice.download', $order->id) }}" type="button"
                        class="btn btn-icon btn-light"><i class="las la-print"></i></a>
                </div>
            </div>
            
            <div class="col-lg-12 table-responsive">
                <h4>Last Orders by {{ json_decode($order->shipping_address)->name ?? 'this user' }}</h4>
                @if($previous_orders->isNotEmpty())
                    @foreach($previous_orders as $prev_order)
                        <div class="card mb-2 p-2">
                            <strong>Order #{{ $prev_order->code ?? $prev_order->id }}
                                <span class="text-muted ml-2">({{ $prev_order->created_at->format('d-m-Y') }})</span>
                            </strong>
                            <ul class="list-unstyled mt-1">
                                @foreach($prev_order->orderDetails as $detail)
                                    @if($detail->product)
                                        <li class="media mb-1">
                                            <img src="{{ uploaded_asset($detail->product->thumbnail_img) }}"
                                                alt="{{ $detail->product->name }}"
                                                class="mr-2" width="40" height="40" style="object-fit: cover;">
                                            <div class="media-body">
                                                <small>{{ $detail->product->name }}</small>
                                            </div>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                @else
                    <p>No previous orders found.</p>
                @endif
            </div>

        </div>
    </div>


    <div class="border m-5 p-5">
        <table class="table p-5">
            <thead class="table-primary">
                <tr>
                    <th scope="col">Title</th>
                    <th scope="col">Total Parcel</th>
                    <th scope="col">Success Parcel</th>
                    <th scope="col">Cancelled Parcel</th>
                    <th scope="col">Success Ratio</th>
                </tr>
            </thead>
            <tbody>
                @if (!empty($data->courierData))
                    @foreach ($data->courierData as $key => $data)
                        <tr class="{{ $key == 'summary' ? 'table-primary' : '' }}">
                            <th scope="row">{{ ucfirst($key) }}</th>
                            <td>{{ $data->total_parcel ?? 0 }}</td>
                            <td>{{ $data->success_parcel ?? 0 }}</td>
                            <td>{{ $data->cancelled_parcel ?? 0 }}</td>
                            <td>{{ $data->success_ratio ?? 0 }}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
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
<!-- jQuery -->
<!-- jQuery -->
{{--  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap JS Bundle (includes Popper.js) -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>  --}}
{{--  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>  --}}
@section('script')
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
        /* $('#update_delivery_status').on('change', function() {

              var order_id = {{ $order->id }};
              var status = $('#update_delivery_status').val();
              $.post('{{ route('orders.update_delivery_status') }}', {
                  _token: '{{ @csrf_token() }}',
                  order_id: order_id,
                  status: status
              }, function(data) {
                  AIZ.plugins.notify('success', '{{ translate('Delivery status has been updated') }}');
              });
          });*/
        // newly start
        $(document).ready(function() {
            $('.closemodal').on('click', function() {
                $('#cancelReasonModal').modal('hide');

            });
            $('#update_delivery_status').on('change', function() {
                if ($(this).val() == 'cancelled') {
                    $('#cancelReasonModal').modal('show');
                } else {
                    updateDeliveryStatus();
                }
            });

            // $('#submitCancelReason').on('click', function() {
            //     updateDeliveryStatus();
            //     $('#cancelReasonModal').modal('hide');
            //     location.reload();

            // });
            $('#submitCancelReason').on('click', function(e) {
                e.preventDefault(); // Prevent default action if necessary
                updateDeliveryStatus();
                $('#cancelReasonModal').modal('hide');
            });

            // function updateDeliveryStatus() {
            //     var order_id = {{ $order->id }};
            //     var status = $('#update_delivery_status').val();
            //     var cancelReason = $('#cancelReason').val();
            //     $.post('{{ route('orders.update_delivery_status') }}', {
            //         _token: '{{ @csrf_token() }}',
            //         order_id: order_id,
            //         status: status,
            //         cancel_reason: cancelReason
            //     }, function(data) {
            //         AIZ.plugins.notify('success', '{{ translate('Delivery status has been updated') }}');
            //         location.reload();
            //     });
            // }

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
                    cancel_reason: cancelReason
                }, function(data) {
                    if (data.success) {
                        AIZ.plugins.notify('success',
                            '{{ translate('Delivery status has been updated') }}');
                        location.reload();
                    } else {
                        AIZ.plugins.notify('danger',
                            '{{ translate('Failed to update delivery status') }}');
                    }
                }).fail(function() {
                    AIZ.plugins.notify('danger',
                        '{{ translate('Failed to update delivery status') }}');
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
