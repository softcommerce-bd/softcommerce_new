@extends('backend.layouts.app')

@section('content')

    <div class="card">
        <div class="card-header">
            <h1 class="h2 fs-16 mb-0">{{ translate('Order Edit') }}</h1>
        </div>

        <form method="post" action="{{ route('order.edit.update') }}">
            @csrf
            <div class="card-body mt-5">
                <div class="row">
                    <input type="hidden" value="{{ $order->id }}" id="id">
                    @php
                        $data = json_decode($order->shipping_address);
                    @endphp

                    <div class="col-9 mb-5">
                        <input name="id" value="{{ $order->id }}" type="hidden">
                        <div class='row'>
                            <div class="col-2 mt-md-2">
                                <label>{{ translate('Name') }}</label><span class="text-danger">*</span>
                            </div>
                            <div class="col-10">
                                <input class="form-control mb-2 rounded-0" id="name" value="{{ $data->name }}"
                                    placeholder="{{ translate('Your Name *') }}" rows="2" name="name" required>
                                @if ($errors->has('name'))
                                    <small class="text-danger">{{ $errors->first('name') }}</small>
                                @endif
                            </div>
                        </div>

                        <div class='row'>
                            <div class="col-2 mt-md-2">
                                <label>{{ translate('Phone') }}</label><span class="text-danger">*</span>
                            </div>
                            <div class="col-10">
                                <input class="form-control mb-2 rounded-0" id="phone" value="{{ $data->phone }}"
                                    placeholder="" rows="2" name="phone" required>
                                @if ($errors->has('phone'))
                                    <small class="text-danger">{{ $errors->first('phone') }}</small>
                                @endif
                            </div>
                        </div>

                        <div class='row'>
                            <div class="col-2 mt-md-2">
                                <label>{{ translate('Email') }}</label><span class="text-danger">*</span>
                            </div>
                            <div class="col-10">
                                <input class="form-control mb-2 rounded-0" id="email" value="{{ $data->email }}"
                                    placeholder="" rows="2" name="email">
                                @if ($errors->has('email'))
                                    <small class="text-danger">{{ $errors->first('email') }}</small>
                                @endif
                            </div>
                        </div>

                        @if ($order->order_from == 'web')
                            <div class='row'>
                                <div class="col-2 mt-md-2">
                                    <label>{{ translate('City') }}</label><span class="text-danger">*</span>
                                </div>
                                <div class="col-10">
                                    <select class="form-control mb-2 rounded-0" data-live-search="true" name="city_id"
                                        id="city" data-value = "{{ $data->city }}" required
                                        onchange="changeShipping(this)">
                                        <option value="" readonly>{{ translate('Select your Shipping Area') }}
                                        </option>
                                        @foreach (get_active_cities() as $key => $city)
                                            <option value="{{ $city->name }}" data-cost="{{ $city->cost }}"
                                                data-name = "{{ $city->name }}"
                                                {{ $city->name == $data->city ? 'selected' : ' ' }}>{{ $city->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif

                        <div class='row'>
                            <div class="col-2 mt-md-2">
                                <label>{{ translate('Address') }}</label><span class="text-danger">*</span>
                            </div>
                            <div class="col-10">
                                <textarea class="form-control rounded-0 guest_address" id="guest_address" rows="2" name="address" required>{{ $data->address }}</textarea>
                                @if ($errors->has('address'))
                                    <small class="text-danger">{{ $errors->first('address') }}</small>
                                @endif
                            </div>
                        </div>

                    </div>

                    <div class="col-3">
                        <table>
                            <tbody>
                                <tr>
                                    <td class="text-main text-bold">{{ translate('Order #') }}</td>
                                    <td class="text-info text-bold text-right"> {{ $order->code }}</td>
                                </tr>
                                <tr>
                                    <td class="text-main text-bold">{{ translate('Order Status') }}</td>
                                    <td class="text-right">
                                        @if ($order->delivery_status == 'delivered')
                                            <span class="badge badge-inline badge-success">
                                                {{ translate(ucfirst(str_replace('_', ' ', $order->delivery_status))) }}
                                            </span>
                                        @else
                                            <span class="badge badge-inline badge-info">
                                                {{ translate(ucfirst(str_replace('_', ' ', $order->delivery_status))) }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-main text-bold">{{ translate('Order Date') }} </td>
                                    <td class="text-right">{{ date('d-m-Y h:i A', $order->date) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-main text-bold">{{ translate('Payment method') }}</td>
                                    <td class="text-right">
                                        {{ translate(ucfirst(str_replace('_', ' ', $order->payment_type))) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-main text-bold">{{ translate('Additional Info') }}</td>
                                    <td class="text-right">{{ $order->additional_info }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>


                <div>
                    <div class="row">
                        <div class="col-lg-12 ">
                            <table class="table-bordered table">
                                <thead>
                                    <tr class="bg-trans-dark">
                                        <th class="text-uppercase">{{ translate('Description') }}</th>
                                        <th class="text-uppercase">{{ translate('Variation') }}</th>
                                        <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                                            {{ translate('Qty') }}</th>
                                        <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                                            {{ translate('Price') }}</th>
                                        <th data-breakpoints="lg" class="min-col text-uppercase text-right">
                                            {{ translate('Total') }}</th>
                                        <th data-breakpoints="lg" class="min-col text-uppercase text-right">
                                            {{ translate('Action') }}</th>
                                    </tr>
                                </thead>


                                {{-- <tbody id="orderDetailsTable">
                                    @foreach ($order->orderDetails as $key => $orderDetail)
                                        <tr id="{{ $orderDetail->id }}" data-old-qty = "{{ $orderDetail->quantity }}"
                                            data-old-variation = "{{ $orderDetail->variation }}"
                                            data-old-price = "{{ $orderDetail->price }}">
                                            <input type="hidden" name="order_detail_id[]"
                                                value="{{ $orderDetail->id }}">
                                            <td>


                                                <input name='product_id[]' value="{{ $orderDetail->product_id }}"
                                                    type="hidden">
                                                <input class="form-control"
                                                    value="{{ $orderDetail->product->getTranslation('name') }}" readonly>
                                            </td>


                                            <td>
                                                <select class="form-control mb-2 rounded-0" data-live-search="true"
                                                    name="variation[]" id="select-variation"
                                                    data-id = "{{ $orderDetail->id }}" onchange = "changeVariation(this)"
                                                    data-discount = "{{ $orderDetail->product->discount }}"
                                                    data-discount_type = "{{ $orderDetail->product->discount_type }}">
                                                    @foreach ($orderDetail->product->stocks as $stock)
                                                        <option value="{{ $stock->variant }}"
                                                            data-cost="{{ $stock->price }}" id="{{ $stock->variant }}"
                                                            data-stock = "{{ $stock->qty }}"
                                                            {{ $stock->variant == $orderDetail->variation ? 'selected' : ' ' }}>
                                                            {{ $stock->variant }}</option>
                                                    @endforeach
                                                </select>
                                            </td>

                                            <td class="text-center">
                                                <input class="form-control form-control-sm qty"
                                                    data-tax = "{{ $orderDetail->tax }}"
                                                    data-id = "{{ $orderDetail->id }}"
                                                    value="{{ $orderDetail->quantity }}"
                                                    onchange = "chnageQuantity(this)" name="qty[]" />
                                            </td>

                                            <td class="text-center">
                                                <input class="form-control form-control-sm"
                                                    value="{{ $orderDetail->price / $orderDetail->quantity }}"
                                                    name="price[]" readonly />
                                            </td>

                                            <td class="text-center">
                                                <input class="form-control form-control-sm total"
                                                    value="{{ $orderDetail->price }}" name="total[]" readonly />
                                            </td>

                                            <td class="text-right">
                                                <a href="javascript:void(0);"
                                                    class="btn btn-danger btn-icon btn-circle btn-sm confirm-delete"
                                                    title="Delete" id = 'delete-botton' data-id="{{ $orderDetail->id }}"
                                                    onclick="confirmDelete(this)">
                                                    <i class="las la-trash"></i>
                                                </a>
                                            </td>

                                        </tr>
                                    @endforeach
                                </tbody> --}}
                                <tbody id="orderDetailsTable">
                                    @foreach ($order->orderDetails as $key => $orderDetail)
                                        <tr id="{{ $orderDetail->id }}" data-old-qty = "{{ $orderDetail->quantity }}"
                                            data-old-variation = "{{ $orderDetail->variation }}"
                                            data-old-price = "{{ $orderDetail->price }}">

                                            <input type="hidden" name="order_detail_id[]" value="{{ $orderDetail->id }}">

                                            <!-- Exchange tracking fields -->
                                            <input type="hidden" name="is_exchange[]" value="">
                                            <input type="hidden" name="exchange_from_order_detail_id[]" value="">
                                            <input type="hidden" name="exchange_from_product_id[]" value="">
                                            <input type="hidden" name="exchange_from_variation[]" value="">
                                            <input type="hidden" name="exchange_from_quantity[]" value="">
                                            <input type="hidden" name="exchange_from_price[]" value="">

                                            <td>
                                                <input name='product_id[]' value="{{ $orderDetail->product_id }}" type="hidden">
                                                <input class="form-control" value="{{ $orderDetail->product->getTranslation('name') }}" readonly>
                                            </td>

                                            <td>
                                                <select class="form-control mb-2 rounded-0" data-live-search="true"
                                                    name="variation[]" id="select-variation"
                                                    data-id = "{{ $orderDetail->id }}" onchange = "changeVariation(this)"
                                                    data-discount = "{{ $orderDetail->product->discount }}"
                                                    data-discount_type = "{{ $orderDetail->product->discount_type }}">
                                                    @foreach ($orderDetail->product->stocks as $stock)
                                                        <option value="{{ $stock->variant }}"
                                                            data-cost="{{ $stock->price }}" id="{{ $stock->variant }}"
                                                            data-stock = "{{ $stock->qty }}"
                                                            {{ $stock->variant == $orderDetail->variation ? 'selected' : ' ' }}>
                                                            {{ $stock->variant }}</option>
                                                    @endforeach
                                                </select>
                                            </td>

                                            <td class="text-center">
                                                <input class="form-control form-control-sm qty"
                                                    data-tax = "{{ $orderDetail->tax }}"
                                                    data-id = "{{ $orderDetail->id }}"
                                                    value="{{ $orderDetail->quantity }}"
                                                    onchange = "chnageQuantity(this)" name="qty[]" />
                                            </td>

                                            <td class="text-center">
                                                <input class="form-control form-control-sm"
                                                    value="{{ $orderDetail->price / $orderDetail->quantity }}"
                                                    name="price[]" readonly />
                                            </td>

                                            <td class="text-center">
                                                <input class="form-control form-control-sm total"
                                                    value="{{ $orderDetail->price }}" name="total[]" readonly />
                                            </td>

                                            <td class="text-right">
                                                <button type="button"
                                                    class="btn btn-warning btn-icon btn-circle btn-sm"
                                                    title="Exchange" id = 'exchange-button'
                                                    data-order-detail-id="{{ $orderDetail->id }}"
                                                    data-product-id="{{ $orderDetail->product_id }}"
                                                    data-variation="{{ $orderDetail->variation }}"
                                                    data-quantity="{{ $orderDetail->quantity }}"
                                                    data-price="{{ $orderDetail->price }}"
                                                    onclick="exchangeProduct(this)">
                                                    <i class="las la-exchange-alt"></i>
                                                </button>
                                                <a href="javascript:void(0);"
                                                    class="btn btn-danger btn-icon btn-circle btn-sm confirm-delete"
                                                    title="Delete" id = 'delete-botton' data-id="{{ $orderDetail->id }}"
                                                    onclick="confirmDelete(this)">
                                                    <i class="las la-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @php
                    $totalOrder = count($order->orderDetails) == 0 ? 1 : count($order->orderDetails);
                @endphp

                <div class="row mt-3">
                    <div class="col-6">
                        <div class="row">
                            <div class="col-12">
                                <div class="">
                                    <table class="table">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <strong class="text-muted">{{ translate('Sub Total') }} :</strong>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="mr-2">{{ currency_symbol() }}</span>
                                                        <input type="text" class="form-control" id="price"
                                                            value="{{ $order->orderDetails->sum('price') }}"
                                                            name="sub_total" readonly>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-muted">{{ translate('Tax') }} :</strong>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="mr-2">{{ currency_symbol() }}</span>
                                                        {{-- <input class="form-control" id = 'tax' value="{{ single_price($order->orderDetails->sum('tax')) }}" name="tax" readonly/> --}}
                                                        <input class="form-control" id="tax"
                                                            value="{{ $order->orderDetails->sum('tax') }}" name="tax"
                                                            readonly />
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-muted">{{ translate('Shipping') }} :</strong>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="mr-2">{{ currency_symbol() }}</span>
                                                        {{-- <input class="form-control" id="shipping_cost" value="{{ single_price($order->orderDetails->sum('shipping_cost') / $totalOrder) }}" name="shipping_cost"
                                                @if ($order->order_from == 'web') readonly @endif
                                                onchange="shippingCostChanged(this)" /> --}}
                                                        <input class="form-control" id="shipping_cost"
                                                            value="{{ $order->orderDetails->sum('shipping_cost') / $totalOrder }}"
                                                            name="shipping_cost"
                                                            @if ($order->order_from == 'web') readonly @endif
                                                            onchange="shippingCostChanged(this)" />
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-muted">{{ translate('Coupon') }} :</strong>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="mr-2">{{ currency_symbol() }}</span>
                                                        {{-- <input class="form-control" id = 'coupon_discount' value="{{ single_price($order->coupon_discount) }}" name="coupon_discount" readonly/> --}}
                                                        <input class="form-control" id="coupon_discount"
                                                            value="{{ $order->coupon_discount }}" name="coupon_discount"
                                                            readonly />
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-muted">{{ translate('TOTAL') }} :</strong>
                                                </td>
                                                <td class="text-muted fw-700">
                                                    <div class="d-flex align-items-center">
                                                        <span class="mr-2">{{ currency_symbol() }}</span>
                                                        {{-- <input class="form-control" id = 'grand_total' value="{{ single_price($order->update_grand_total??$order->grand_total) }}" name="grand_total" readonly/> --}}
                                                        <input class="form-control" id="grand_total"
                                                            value="{{ $order->update_grand_total ?? $order->grand_total }}"
                                                            name="grand_total" readonly />
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="d-flex justify-content-end mr-4 text-right">
                            <button class="btn btn-primary" id="row-add" onclick = "addRow()">Add Row</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end m-4">
                <button type="type" class="btn btn-primary">Order Edit</button>
            </div>
        </form>
    </div>

@endsection


@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');

            form.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    const target = e.target;
                    // Only prevent default if the target is an input field (not textarea)
                    if (target.tagName === 'INPUT' && target.type !== 'textarea') {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        });
    </script>
    <script>
        const orderId = `{{ $order->id }}`;
        let rowIndex = 0;

        // function addRow() {
        //     rowIndex++;

        //     const newRow = `
        //         <tr id="row-${rowIndex}" data-old-qty = "1" data-order = "{{ $order->id }}" >
        //              <input type="hidden" name="order_detail_id[]" value="">
        //             <td>
        //                     <div style="position: relative;">
        //                         <input class="form-control product"
        //                             placeholder="search product" data-id="row-${rowIndex}"
        //                             onkeyup="getProduct(this)" style="z-index: 1050;"/>

        //                             <input name = "product_id[]" type='hidden'>

        //                         <!-- The dropdown for search results (this will be shown above the table) -->
        //                         <div id="search_results" class="dropdown-menu"
        //                             style="display: none; position: absolute; top: 100%; left: 0; z-index: 3000; width: 100%;">
        //                         </div>
        //                     </div>
        //             </td>

        //             <td>
        //                 <select class="form-control mb-2 rounded-0" data-live-search="true" name="variation[]" data-id = "row-${rowIndex}" onchange = "changeVariation(this)" required >

        //                 </select>
        //             </td>

        //             <td class="text-center">
        //                 <input class="form-control form-control-sm" value="1" name="qty[]" data-id = "row-${rowIndex}"  onchange = "chnageQuantity(this)"/>
        //             </td>

        //             <td class="text-center">
        //                 <input class="form-control form-control-sm price" value="0" name="price[]" data-id = "row-${rowIndex}" readonly />
        //             </td>


        //             <td class="text-center">
        //                 <input class="form-control form-control-sm total" value="0" name="total[]" data-id = "row-${rowIndex}" readonly />
        //             </td>

        //             <td class="text-right">
        //                 <a href="javascript:void(0);" class="btn btn-danger btn-icon btn-circle btn-sm confirm-delete" title="Delete" id = 'delete-botton'
        //                  onclick = dataDelete('row-${rowIndex}')>
        //                     <i class="las la-trash"></i>
        //                 </a>
        //             </td>
        //         </tr>
        //     `;


        //     document.getElementById('orderDetailsTable').insertAdjacentHTML('beforeend', newRow);
        // }

        function addRow() {
    rowIndex++;

    const newRow = `
        <tr id="row-${rowIndex}" data-old-qty = "1" data-order = "{{ $order->id }}" >
            <input type="hidden" name="order_detail_id[]" value="">

            <!-- Exchange tracking fields (empty for new rows) -->
            <input type="hidden" name="is_exchange[]" value="">
            <input type="hidden" name="exchange_from_order_detail_id[]" value="">
            <input type="hidden" name="exchange_from_product_id[]" value="">
            <input type="hidden" name="exchange_from_variation[]" value="">
            <input type="hidden" name="exchange_from_quantity[]" value="">
            <input type="hidden" name="exchange_from_price[]" value="">

            <td>
                <div style="position: relative;">
                    <input class="form-control product" placeholder="search product" data-id="row-${rowIndex}" onkeyup="getProduct(this)" style="z-index: 1050;"/>
                    <input name = "product_id[]" type='hidden'>
                    <div id="search_results" class="dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; z-index: 3000; width: 100%;">
                    </div>
                </div>
            </td>

            <td>
                <select class="form-control mb-2 rounded-0" data-live-search="true" name="variation[]" data-id = "row-${rowIndex}" onchange = "changeVariation(this)" required >
                </select>
            </td>

            <td class="text-center">
                <input class="form-control form-control-sm" value="1" name="qty[]" data-id = "row-${rowIndex}"  onchange = "chnageQuantity(this)"/>
            </td>

            <td class="text-center">
                <input class="form-control form-control-sm price" value="0" name="price[]" data-id = "row-${rowIndex}" readonly />
            </td>

            <td class="text-center">
                <input class="form-control form-control-sm total" value="0" name="total[]" data-id = "row-${rowIndex}" readonly />
            </td>

            <td class="text-right">
                <a href="javascript:void(0);" class="btn btn-danger btn-icon btn-circle btn-sm confirm-delete" title="Delete" id = 'delete-botton' onclick = dataDelete('row-${rowIndex}')>
                    <i class="las la-trash"></i>
                </a>
            </td>
        </tr>
    `;

    document.getElementById('orderDetailsTable').insertAdjacentHTML('beforeend', newRow);
}
    </script>


    <script>
        // var subtotal  =  parseFloat(convertToInteger(document.getElementById('price').value))
        // var tax = parseFloat(convertToInteger(document.getElementById('tax').value))
        // var shippingPrice = parseFloat(convertToInteger(document.getElementById('shipping_cost').value))
        // var grandTotal = parseFloat(convertToInteger(document.getElementById('grand_total').value))
        // var id = parseFloat(document.getElementById('id').value);
        // var city = document.getElementById('city').getAttribute('data-value');
        // var coupon = parseFloat(convertToInteger(document.getElementById('coupon_discount').value));

        var subtotal = parseFloat(document.getElementById('price').value) || 0;
        var tax = parseFloat(document.getElementById('tax').value) || 0;
        var shippingPrice = parseFloat(document.getElementById('shipping_cost').value) || 0;
        var grandTotal = parseFloat(document.getElementById('grand_total').value) || 0;
        var coupon = parseFloat(document.getElementById('coupon_discount').value) || 0;
        var id = parseFloat(document.getElementById('id').value);
        var city = document.getElementById('city').getAttribute('data-value');


        // function changeShipping(selectElement)
        // {

        //     const selectedOption = selectElement.options[selectElement.selectedIndex];
        //     city = selectedOption.getAttribute('data-name');

        //     shippingPrice = parseFloat(selectedOption.getAttribute('data-cost'));
        //     grandTotal = shippingPrice + subtotal + tax;

        //     document.getElementById('shipping_cost').value = '৳'+ shippingPrice;
        //     document.getElementById('grand_total').value = '৳'+ grandTotal;
        // }
        function changeShipping(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            city = selectedOption.getAttribute('data-name');

            shippingPrice = parseFloat(selectedOption.getAttribute('data-cost'));

            // Recalculate subtotal first
            subTotal = calculateGrandTotal();
            const posCoupon = parseFloat(document.getElementById('coupon_discount').value) || 0;

            grandTotal = subTotal + shippingPrice + tax - posCoupon;

            // Update the inputs with raw numbers (no currency symbols)
            document.getElementById('shipping_cost').value = shippingPrice;
            document.getElementById('grand_total').value = grandTotal;
        }

        function chnageQuantity(element) {
            // const orderDetailsId = element.getAttribute('data-id');
            // const quantity = element.value;

            // const  row = document.getElementById(orderDetailsId);
            // const priceInput = parseFloat(convertToInteger(row.querySelector('input[name="price[]"]').value));

            const orderDetailsId = element.getAttribute('data-id');
            const quantity = parseFloat(element.value) || 0;

            const row = document.getElementById(orderDetailsId);
            const priceInput = parseFloat(row.querySelector('input[name="price[]"]').value) || 0;

            const selectElement = row.querySelector('select[name="variation[]"]');
            const selectOption = selectElement.options[selectElement.selectedIndex];
            const productStock = parseInt(selectOption.getAttribute('data-stock'));
            const productQuantity = parseInt(row.querySelector('input[name="qty[]"]').value);

            if (productQuantity > productStock) {
                alert('Insafecient stock');
                previousData(row);
                allGrandTotal();
                return false;
            }

            // const total = quantity*priceInput;
            // row.querySelector('input[name="total[]"]').value = '৳'+ total;

            // allGrandTotal();

            const total = quantity * priceInput;
            row.querySelector('input[name="total[]"]').value = total; // Remove currency symbol

            allGrandTotal();

        }

        // function previousData(row)
        // {
        //     const oldQty = row.getAttribute('data-old-qty');
        //     const oldVariation = row.getAttribute('data-old-variation');
        //     const oldPrice = parseFloat(convertToInteger(row.getAttribute('data-old-price')));

        //     row.querySelector('input[name="qty[]"]').value = oldQty;

        //     let options = row.querySelector('select').options;


        //     for(let i = 0; i<options.length; i++){

        //         if (options[i].value === oldVariation) {
        //             options[i].selected = true;
        //             break;
        //         }
        //     }

        //     row.querySelector('input[name="price[]"]').value = '৳'+ (oldPrice/oldQty);
        //     row.querySelector('input[name="total[]"]').value = '৳'+ oldPrice;
        // }

        function previousData(row) {
            const oldQty = row.getAttribute('data-old-qty');
            const oldVariation = row.getAttribute('data-old-variation');

            // Remove convertToInteger and parse directly
            const oldPrice = parseFloat(row.getAttribute('data-old-price').replace(/[^\d.]/g, '')) || 0;

            row.querySelector('input[name="qty[]"]').value = oldQty;

            let options = row.querySelector('select').options;
            for (let i = 0; i < options.length; i++) {
                if (options[i].value === oldVariation) {
                    options[i].selected = true;
                    break;
                }
            }

            // Calculate unit price and set without currency symbol
            const unitPrice = oldPrice / oldQty;
            row.querySelector('input[name="price[]"]').value = unitPrice;
            row.querySelector('input[name="total[]"]').value = oldPrice;

            allGrandTotal();
        }

        // function changeVariation(element)
        // {
        //     const discount = element.getAttribute('data-discount');
        //     const discountType = element.getAttribute('data-discount_type');

        //     const selectOption = element.options[element.selectedIndex];
        //     const productStock = parseInt(selectOption.getAttribute('data-stock'));
        //     const cost = selectOption.getAttribute('data-cost');

        //     const price = singlePriceCalculation( discount, discountType, cost )

        //     const orderDetailsId = element.getAttribute('data-id');

        //     const  row = document.getElementById(orderDetailsId);

        //     const quantity = parseInt( row.querySelector('input[name="qty[]"]').value)

        //     if( quantity > productStock ){
        //         alert('Insafecient stock');
        //         previousData( row );
        //         allGrandTotal();
        //         return false;
        //     }

        //     let total = price*quantity;

        //     row.querySelector('input[name="price[]"]').value = '৳'+ price;
        //     row.querySelector('input[name="total[]"]').value = '৳'+ total;


        //     allGrandTotal();
        // }

        function changeVariation(element) {
            const discount = parseFloat(element.getAttribute('data-discount')) || 0;
            const discountType = element.getAttribute('data-discount_type');

            const selectOption = element.options[element.selectedIndex];
            const productStock = parseInt(selectOption.getAttribute('data-stock')) || 0;
            const cost = parseFloat(selectOption.getAttribute('data-cost')) || 0;

            const price = singlePriceCalculation(discount, discountType, cost);
            const orderDetailsId = element.getAttribute('data-id');
            const row = document.getElementById(orderDetailsId);
            const quantity = parseInt(row.querySelector('input[name="qty[]"]').value) || 0;

            if (quantity > productStock) {
                alert('Insufficient stock');
                previousData(row);
                allGrandTotal();
                return false;
            }

            let total = price * quantity;

            // Set values without currency symbols
            row.querySelector('input[name="price[]"]').value = price;
            row.querySelector('input[name="total[]"]').value = total;

            allGrandTotal();
        }


        function singlePriceCalculation(discount, discountType, cost) {
            var discountAmount = 0;

            if (discountType == 'amount') {
                discountAmount = discount;
            } else {
                discountAmount = cost * (discount / 100);
            }

            return cost - discountAmount;
        }

        // function calculateGrandTotal()
        // {
        //     let totalSum = 0;

        //     document.querySelectorAll('.total').forEach(function(input) {

        //         let value = parseFloat(input.value.replace(/[^0-9.]/g, '')) || 0;
        //         totalSum += value;
        //     });

        //     return totalSum;
        // }

        function calculateGrandTotal() {
            let totalSum = 0;
            document.querySelectorAll('.total').forEach(function(input) {
                let value = parseFloat(input.value) || 0;
                totalSum += value;
            });
            return totalSum;
        }

        // function allGrandTotal()
        // {
        //     const couponPos = parseFloat(convertToInteger(document.getElementById('coupon_discount').value));
        //     subTotal = calculateGrandTotal()
        //     document.getElementById('price').value = '৳'+ subTotal;

        //     grandTotal = subTotal+shippingPrice-couponPos;
        //     document.getElementById('grand_total').value = '৳'+grandTotal;
        // }

        function allGrandTotal() {
            const couponPos = parseFloat(document.getElementById('coupon_discount').value) || 0;
            subTotal = calculateGrandTotal();

            document.getElementById('price').value = subTotal;
            grandTotal = subTotal + shippingPrice - couponPos;
            document.getElementById('grand_total').value = grandTotal;
        }


        function convertToInteger(priceString) {

            let numericValue = parseFloat(priceString.replace(/[^\d.]/g, ''));
            return Math.floor(numericValue);
        }



        function getProduct(element) {
            let query = element.value;
            const row = document.getElementById(element.getAttribute('data-id'));
            const dropdown = row.querySelector('#search_results');

            if (query.length > 1) {
                $.ajax({
                    url: "{{ route('order.edit.product') }}",
                    method: 'get',
                    data: {
                        data: query,
                    },
                    success: function(res) {
                        if (res.length > 0) {
                            dropdown.innerHTML = ''; // Clear existing results
                            res.forEach(function(product) {
                                let anchor = document.createElement('a');
                                anchor.href = "javascript:void(0);";
                                anchor.className = "dropdown-item";
                                anchor.setAttribute("data-id", product.id);
                                anchor.setAttribute("data-name", product.name);

                                anchor.textContent = product.name;
                                // Correctly assign onclick function
                                anchor.onclick = function() {
                                    getNewProduct(product, element, dropdown);
                                };

                                dropdown.appendChild(anchor);
                            });
                            dropdown.style.display = 'block';
                        } else {
                            dropdown.style.display = 'none';
                        }
                    }
                });
            }
        }


        // function  getNewProduct(product,element,dropdown)
        // {
        //     const product_id = element.getAttribute('data-id');
        //     const row = document.getElementById(product_id);

        //     row.querySelector('input[name="product_id[]"]').value = product.id;

        //     element.value = product.name;
        //     dropdown.style.display = 'none';

        //     const selectElement = row.querySelector('select[name="variation[]"]');

        //     selectElement.setAttribute('data-discount', product.discount);
        //     selectElement.setAttribute('data-discount_type', product.discount_type)
        //     selectElement.innerHTML = '';

        //     $.ajax({
        //         url:"{{ route('order.edit.variation') }}",
        //         method:'get',
        //         data:{
        //             id:product.id
        //         },
        //         success:function(res){
        //             if (res.length > 0) {
        //                     //let selectElement = row.querySelector("select");

        //                     res.forEach(function(variation,index) {

        //                         let option = document.createElement('option');
        //                         option.value = variation.variant?variation.variant:' ';
        //                         option.setAttribute("data-cost", variation.price);
        //                         option.setAttribute("data-stock", variation.qty);
        //                         option.id  = variation.variant
        //                         option.set
        //                         option.textContent = variation.variant?variation.variant:'No Variation';
        //                         selectElement.appendChild(option);

        //                         if (index === 0) {
        //                             option.setAttribute('selected', 'selected');
        //                             row.querySelector('input[name="price[]"]').value = '৳'+ singlePriceCalculation(product.discount, product.discount_type,variation.price);
        //                             row.querySelector('input[name="total[]"]').value = '৳'+ singlePriceCalculation(product.discount, product.discount_type,variation.price);

        //                             row.setAttribute("data-old-variation",variation.variant);
        //                             row.setAttribute("data-old-price", variation.price);
        //                         }
        //                 });

        //             }
        //             allGrandTotal();
        //         }
        //     })
        // }

        function getNewProduct(product, element, dropdown) {
            const product_id = element.getAttribute('data-id');
            const row = document.getElementById(product_id);

            row.querySelector('input[name="product_id[]"]').value = product.id;
            element.value = product.name;
            dropdown.style.display = 'none';

            const selectElement = row.querySelector('select[name="variation[]"]');
            selectElement.setAttribute('data-discount', product.discount);
            selectElement.setAttribute('data-discount_type', product.discount_type);
            selectElement.innerHTML = '';

            $.ajax({
                url: "{{ route('order.edit.variation') }}",
                method: 'get',
                data: {
                    id: product.id
                },
                success: function(res) {
                    if (res.length > 0) {
                        res.forEach(function(variation, index) {
                            let option = document.createElement('option');
                            option.value = variation.variant ? variation.variant : ' ';
                            option.setAttribute("data-cost", variation.price);
                            option.setAttribute("data-stock", variation.qty);
                            option.id = variation.variant;
                            option.textContent = variation.variant ? variation.variant : 'No Variation';
                            selectElement.appendChild(option);

                            if (index === 0) {
                                option.setAttribute('selected', 'selected');
                                const calculatedPrice = singlePriceCalculation(
                                    parseFloat(product.discount) || 0,
                                    product.discount_type,
                                    parseFloat(variation.price) || 0
                                );

                                // Set values without currency symbols
                                row.querySelector('input[name="price[]"]').value = calculatedPrice;
                                row.querySelector('input[name="total[]"]').value = calculatedPrice;

                                row.setAttribute("data-old-variation", variation.variant);
                                row.setAttribute("data-old-price", calculatedPrice);
                            }
                        });
                    }
                    allGrandTotal();
                }
            });
        }

        function dataDelete(id) {
            const row = document.getElementById(id);
            row.remove();
            allGrandTotal();
        }


        // function shippingCostChanged(element){
        //    const posCoupon = parseFloat(convertToInteger(document.getElementById('coupon_discount').value));

        //     const shipping = parseInt(element.value);
        //     subTotal = calculateGrandTotal()
        //     grandTotal = parseInt(subTotal)+shipping - posCoupon;
        //     document.getElementById('grand_total').value = '৳'+grandTotal;
        // }

        function shippingCostChanged(element) {
            // Remove convertToInteger and use parseFloat directly
            const posCoupon = parseFloat(document.getElementById('coupon_discount').value) || 0;

            const shipping = parseFloat(element.value) || 0;
            subTotal = calculateGrandTotal();

            // Update the global shippingPrice variable
            shippingPrice = shipping;

            grandTotal = subTotal + shippingPrice - posCoupon;

            // Update the display - remove currency symbol from input values
            document.getElementById('grand_total').value = grandTotal;

            // If you need to display with currency symbol elsewhere, do it separately
            // but keep the input values as numbers for calculations
        }


        function confirmDelete(element) {
            const id = element.getAttribute('data-id');

            const row = document.getElementById(id);
            const variation = row.querySelector('select[name="variation[]"]').value;


            $.ajax({
                url: "{{ route('order.edit.delete') }}",
                method: 'post',
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                data: {
                    id: id,
                    variation: variation,
                },
                success: function(res) {
                    row.remove();
                    allGrandTotal();
                }
            });
        }

        function exchangeProduct(button) {
            const row = button.closest('tr');
            const orderDetailId = button.getAttribute('data-order-detail-id');
            const oldProductId = button.getAttribute('data-product-id');
            const oldVariation = button.getAttribute('data-variation');
            const oldQuantity = button.getAttribute('data-quantity');
            const oldPrice = button.getAttribute('data-price');

            // Fill exchange tracking fields
            row.querySelector('input[name="is_exchange[]"]').value = '1';
            row.querySelector('input[name="exchange_from_order_detail_id[]"]').value = orderDetailId;
            row.querySelector('input[name="exchange_from_product_id[]"]').value = oldProductId;
            row.querySelector('input[name="exchange_from_variation[]"]').value = oldVariation;
            row.querySelector('input[name="exchange_from_quantity[]"]').value = oldQuantity;
            row.querySelector('input[name="exchange_from_price[]"]').value = oldPrice;

            // Clear order_detail_id (this becomes a new item replacing the old one)
            row.querySelector('input[name="order_detail_id[]"]').value = '';

            // Transform first cell (product name) into a searchable input
            const productCell = row.querySelector('td');
            productCell.innerHTML = `
                <div style="position: relative;">
                    <input class="form-control product" placeholder="search product"
                           data-id="${row.id}" onkeyup="getProduct(this)" style="z-index: 1050;"/>
                    <input name="product_id[]" type="hidden">
                    <div id="search_results" class="dropdown-menu"
                         style="display: none; position: absolute; top: 100%; left: 0; z-index: 3000; width: 100%;">
                    </div>
                </div>
            `;

            // Reset variation dropdown
            const variationCell = row.querySelectorAll('td')[1];
            variationCell.innerHTML = `
                <select class="form-control mb-2 rounded-0" data-live-search="true"
                        name="variation[]" data-id="${row.id}" onchange="changeVariation(this)" required>
                </select>
            `;

            // Reset qty to 1
            row.querySelector('input[name="qty[]"]').value = 1;

            // Reset price and total to 0
            row.querySelector('input[name="price[]"]').value = 0;
            row.querySelector('input[name="total[]"]').value = 0;

            // Update data-old attributes to prevent confusing rollback behavior
            row.setAttribute('data-old-qty', '1');
            row.setAttribute('data-old-variation', '');
            row.setAttribute('data-old-price', '0');

            // Hide the exchange button (already used)
            button.style.display = 'none';

            allGrandTotal();
        }


        // Alternative: More specific approach
        // document.querySelectorAll('input').forEach(input => {
        //     input.addEventListener('keydown', function(e) {
        //         if (e.key === 'Enter') {
        //             e.preventDefault();
        //             return false;
        //         }
        //     });
        // });
    </script>
@endsection
