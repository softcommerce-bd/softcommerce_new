@extends('backend.layouts.app')

@section('content')
    <style>
        .is-invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }

        .payment-input {
            transition: all 0.3s ease;
        }

        .payment-input:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
        }
    </style>

    <section class="">
        <form class="" action="" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row gutters-5">
                <div class="col-md">
                    <div class="row gutters-5 mb-3">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <div class="form-group mb-0">
                                <input class="form-control form-control-lg" type="text" name="keyword"
                                    placeholder="{{ translate('Search by Product Name/Barcode') }}"
                                    onkeyup="filterProducts()">
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <select name="poscategory" class="form-control form-control-lg aiz-selectpicker"
                                data-live-search="true" onchange="filterProducts()">
                                <option value="">{{ translate('All Categories') }}</option>
                                @foreach (\App\Models\Category::all() as $key => $category)
                                    <option value="category-{{ $category->id }}">{{ $category->getTranslation('name') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 col-6">
                            <select name="brand" class="form-control form-control-lg aiz-selectpicker"
                                data-live-search="true" onchange="filterProducts()">
                                <option value="">{{ translate('All Brands') }}</option>
                                @foreach (\App\Models\Brand::all() as $key => $brand)
                                    <option value="{{ $brand->id }}">{{ $brand->getTranslation('name') }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="aiz-pos-product-list c-scrollbar-light">
                        <div class="d-flex flex-wrap justify-content-center" id="product-list">

                        </div>
                        <div id="load-more" class="text-center">
                            <div class="fs-14 d-inline-block fw-600 btn btn-soft-primary c-pointer"
                                onclick="loadMoreProduct()">{{ translate('Loading..') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-auto w-md-350px w-lg-400px w-xl-500px">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex border-bottom pb-3">
                                <div class="flex-grow-1">
                                    @php
                                        $userID = Session::has('pos.user_id') ? Session::get('pos.user_id') : null;
                                    @endphp
                                    <select name="user_id" class="form-control aiz-selectpicker pos-customer"
                                        data-live-search="true" onchange="getShippingAddressUpdateCartData()"
                                        data-selected="{{ $userID }}">
                                        <option value="">{{ translate('Walk In Customer') }}</option>
                                        @foreach ($customers as $key => $customer)
                                            <option value="{{ $customer->id }}" data-contact="{{ $customer->email }}" data-tokens="{{ $customer->phone }}">
                                                {{ $customer->name }} @if ($customer->phone) ({{ $customer->phone }}) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" class="btn btn-icon btn-soft-dark ml-3 mr-0"
                                    data-target="#new-customer" data-toggle="modal">
                                    <i class="las la-truck"></i>
                                </button>
                            </div>

                            <div class="" id="cart-details">
                                <div class="aiz-pos-cart-list mb-4 mt-3 c-scrollbar-light">
                                    @php
                                        $subtotal = 0;
                                        $tax = 0;
                                        $carts = get_pos_user_cart();
                                    @endphp
                                    @if (count($carts) > 0)
                                        <ul class="list-group list-group-flush">
                                            @forelse ($carts as $key => $cartItem)
                                                @php
                                                    $product = $cartItem->product;
                                                    $stock = $cartItem->product->stocks
                                                        ->where('variant', $cartItem['variation'])
                                                        ->first();
                                                    $subtotal +=
                                                        cart_product_price($cartItem, $product, false, false) *
                                                        $cartItem['quantity'];
                                                    $tax +=
                                                        cart_product_tax($cartItem, $product, false) *
                                                        $cartItem['quantity'];
                                                    $cartID = $cartItem['id'];
                                                @endphp
                                                <li class="list-group-item py-0 pl-2">
                                                    <div class="row gutters-5 align-items-center">
                                                        <div class="col-auto w-60px">
                                                            <div
                                                                class="row no-gutters align-items-center flex-column aiz-plus-minus">
                                                                <button class="btn col-auto btn-icon btn-sm fs-15"
                                                                    type="button" data-type="plus"
                                                                    data-field="qty-{{ $cartID }}"
                                                                    @if ($product->digital == 1) disabled @endif>
                                                                    <i class="las la-plus"></i>
                                                                </button>
                                                                <input type="text" name="qty-{{ $cartID }}"
                                                                    id="qty-{{ $cartID }}"
                                                                    class="col border-0 text-center flex-grow-1 fs-16 input-number"
                                                                    placeholder="1" value="{{ $cartItem['quantity'] }}"
                                                                    min="{{ $product->min_qty }}"
                                                                    max="{{ $stock->qty }}"
                                                                    onchange="updateQuantity({{ $cartID }})">
                                                                <button class="btn col-auto btn-icon btn-sm fs-15"
                                                                    type="button" data-type="minus"
                                                                    data-field="qty-{{ $cartID }}"
                                                                    @if ($product->digital == 1) disabled @endif>
                                                                    <i class="las la-minus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="col">
                                                            <div class="text-truncate-2">{{ $product->name }}</div>
                                                            <span
                                                                class="span badge badge-inline fs-12 badge-soft-secondary">{{ $cartItem['variant'] }}</span>
                                                        </div>
                                                        <div class="col-auto">
                                                            <div class="fs-12 opacity-60">
                                                                {{ single_price($cartItem['price']) }} x
                                                                {{ $cartItem['quantity'] }}</div>
                                                            <div class="fs-15 fw-600">
                                                                {{ single_price($cartItem['price'] * $cartItem['quantity']) }}
                                                            </div>
                                                        </div>
                                                        <div class="col-auto">
                                                            <button type="button"
                                                                class="btn btn-circle btn-icon btn-sm btn-soft-danger ml-2 mr-0"
                                                                onclick="removeFromCart({{ $cartItem->id }})">
                                                                <i class="las la-trash-alt"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </li>
                                            @empty
                                                <li class="list-group-item">
                                                    <div class="text-center">
                                                        <i class="las la-frown la-3x opacity-50"></i>
                                                        <p>{{ translate('No Product Added') }}</p>
                                                    </div>
                                                </li>
                                            @endforelse
                                        </ul>
                                    @else
                                        <div class="text-center">
                                            <i class="las la-frown la-3x opacity-50"></i>
                                            <p>{{ translate('No Product Added') }}</p>
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <div class="d-flex justify-content-between fw-600 mb-2 opacity-70">
                                        <span>{{ translate('Sub Total') }}</span>
                                        <span>{{ single_price($subtotal) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between fw-600 mb-2 opacity-70">
                                        <span>{{ translate('Tax') }}</span>
                                        <span>{{ single_price($tax) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between fw-600 mb-2 opacity-70">
                                        <div class="col-md-6 px-0 d-flex align-items-center">
                                            <span>{{ translate('Shipping') }}</span>
                                        </div>
                                        <div class="col-md-6 pr-0">
                                            <input type="number" min="0" name="shipping"
                                                class="form-control text-right p-0"
                                                placeholder="{{ translate('Amount') }}"
                                                value="{{ Session::get('pos.shipping', 0) }}" required
                                                onchange="setShipping()">
                                        </div>


                                    </div>
                                    <div class="d-flex justify-content-between fw-600 opacity-70">

                                        <div class="col-md-6 px-0 d-flex align-items-center">
                                            <span>{{ translate('Discount') }}</span>
                                        </div>
                                        <div class="col-md-6 pr-0">
                                            <input type="number" min="0" name="discount"
                                                class="form-control text-right p-0"
                                                placeholder="{{ translate('Amount') }}"
                                                value="{{ Session::get('pos.discount', 0) }}" required
                                                onchange="setDiscount()">
                                        </div>


                                    </div>

                                    {{-- payment design start --}}
                                    <div class="">
                                        <div class="row justify-content-center">
                                            <div class="col-md-12">
                                                <div class="shadow-sm border-0 rounded-4">
                                                    <!-- Header -->
                                                    <div class="bg-white border-0 pb-2">
                                                        <h5 class="mb-0 fw-700">Payments</h5>
                                                    </div>
                                                    <div class="">
                                                        <!-- Cash -->
                                                        <div class="row mb-2">
                                                            <div class="col-md-2 d-flex align-items-center">
                                                                <label class="fw-600 mb-2 d-block">Cash</label>
                                                            </div>
                                                            <div class="col-md-6 mb-2 mb-md-0">
                                                                <select class="form-control aiz-selectpicker"
                                                                    name="cash_account_id">
                                                                    <option value="">Select Cash Account</option>
                                                                    @foreach ($cashAccounts as $cash)
                                                                        <option value="{{ $cash->id }}"
                                                                            data-name="{{ $cash->name }}"
                                                                            data-type="cash">
                                                                            {{ $cash->name }}
                                                                            @if ($cash->current_balance != 0)
                                                                                (Balance:
                                                                                {{ number_format($cash->current_balance, 2) }})
                                                                            @endif
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <input type="number"
                                                                    class="form-control text-right p-0 cash-amount payment-input"
                                                                    name="cash_amount" placeholder="Enter Amount"
                                                                    value="0"
                                                                    oninput="validatePaymentAmount(this, 'cash')">
                                                            </div>
                                                        </div>

                                                        <!-- Bank -->
                                                        <div class="row mb-2">
                                                            <div class="col-md-2 d-flex align-items-center">
                                                                <label class="fw-600 mb-2 d-block">Bank</label>
                                                            </div>
                                                            <div class="col-md-6 mb-2 mb-md-0">
                                                                <select class="form-control aiz-selectpicker"
                                                                    name="bank_account_id">
                                                                    <option value="">Select Bank</option>
                                                                    @foreach ($bankAccounts as $bank)
                                                                        <option value="{{ $bank->id }}"
                                                                            data-name="{{ $bank->name }}"
                                                                            data-bank="{{ $bank->bank_name }}"
                                                                            data-account="{{ $bank->account_number }}"
                                                                            data-type="bank">
                                                                            {{ $bank->name }}
                                                                            @if ($bank->bank_name)
                                                                                ({{ $bank->bank_name }})
                                                                            @endif
                                                                            @if ($bank->current_balance != 0)
                                                                                - Balance:
                                                                                {{ number_format($bank->current_balance, 2) }}
                                                                            @endif
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <input type="number"
                                                                    class="form-control text-right p-0 bank-amount payment-input"
                                                                    name="bank_amount" placeholder="Enter Amount"
                                                                    value="0"
                                                                    oninput="validatePaymentAmount(this, 'bank')">
                                                            </div>
                                                        </div>

                                                        <!-- Mobile Banking -->
                                                        <div class="row">
                                                            <div class="col-md-2 d-flex align-items-center">
                                                                <label class="fw-600 mb-2 d-block">Mobile Banking</label>
                                                            </div>
                                                            <div class="col-md-6 mb-2 mb-md-0">
                                                                <select class="form-control aiz-selectpicker"
                                                                    name="mobile_bank_account_id">
                                                                    <option value="">Select Method</option>
                                                                    @foreach ($mobileBankingAccounts as $mobileBank)
                                                                        <option value="{{ $mobileBank->id }}"
                                                                            data-name="{{ $mobileBank->name }}"
                                                                            data-mobile="{{ $mobileBank->mobile_number }}"
                                                                            data-charge="{{ $mobileBank->cashout_charge }}"
                                                                            data-type="mobile_bank">
                                                                            {{ $mobileBank->name }}
                                                                            @if ($mobileBank->mobile_number)
                                                                                ({{ $mobileBank->mobile_number }})
                                                                            @endif
                                                                            @if ($mobileBank->cashout_charge > 0)
                                                                                - Charge:
                                                                                {{ $mobileBank->cashout_charge }}%
                                                                            @endif
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <input type="number"
                                                                    class="form-control text-right p-0 mobile_amount payment-input"
                                                                    name="mobile_amount" placeholder="Enter Amount"
                                                                    value="0"
                                                                    oninput="validatePaymentAmount(this, 'mobile')">
                                                            </div>
                                                        </div>


                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                    {{-- payment design end --}}

                                    {{-- Payment Summary - Add this new section --}}
                                    <div class="mt-3 pt-2 border-top">
                                        <div class="d-flex justify-content-between fw-600 mb-2">
                                            <span>{{ translate('Total') }}</span>
                                            <span
                                                id="grand-total-display">{{ single_price($subtotal + $tax + Session::get('pos.shipping', 0) - Session::get('pos.discount', 0)) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between fw-600 mb-2">
                                            <span>{{ translate('Total Payment') }}</span>
                                            <span id="total-payment-amount">0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between fw-600 mb-2" id="total-due-row">
                                            <span class="fw-700">{{ translate('Total Due') }}</span>
                                            <span id="total-due-amount" class="fw-700"
                                                style="color: #e91e2c;">0.00</span>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Place Order Button -->
                    <div class="col-12 col-md-12 text-md-end px-0">
                        <button type="button" onclick="validateAndSubmitOrder()"
                            class="btn btn-primary btn-lg w-100 px-4">
                            <i class="fa fa-check-circle me-1"></i>
                            {{ translate('Place Order') }}
                        </button>
                    </div>

                </div>
            </div>
        </form>
    </section>

@endsection

@section('modal')
    <!-- Address Modal -->
    <div id="new-customer" class="modal fade" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-dialog-zoom" role="document">
            <div class="modal-content">
                <div class="modal-header bord-btm">
                    <h4 class="modal-title h6">{{ translate('Shipping Address') }}</h4>
                    <button type="button" class="close" data-dismiss="modal"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <form id="shipping_form">
                    <div class="modal-body" id="shipping_address">


                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-styled btn-base-3" data-dismiss="modal"
                        id="close-button">{{ translate('Close') }}</button>
                    <button type="button" class="btn btn-primary btn-styled btn-base-1" id="confirm-address"
                        data-dismiss="modal">{{ translate('Confirm') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Change Customer Confirmation Modal --}}
    <div id="change-customer" class="modal fade" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-dialog-zoom" role="document">
            <div class="modal-content">
                <div class="modal-header bord-btm">
                    <h4 class="modal-title h6">{{ translate('Change Customer Confirmation') }}</h4>
                    <button type="button" class="close" data-dismiss="modal"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <form id="shipping_form">
                    <div class="modal-body">
                        <p class="mt-1 fs-14">
                            {{ translate('If you have cart data and change customers, cart data for the previous customer will be removed.') }}
                        </p>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-styled btn-base-3" data-dismiss="modal"
                        id="close-button">{{ translate('Close') }}</button>
                    <button type="button" class="btn btn-primary btn-styled btn-base-1"
                        onclick="updateSessionUserCartData()" data-dismiss="modal">{{ translate('Confirm') }}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- new address modal -->
    <div id="new-address-modal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-dialog-zoom" role="document">
            <div class="modal-content">
                <div class="modal-header bord-btm">
                    <h4 class="modal-title h6">{{ translate('Shipping Address') }}</h4>
                    <button type="button" class="close" data-dismiss="modal"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <form class="form-horizontal" action="{{ route('addresses.store') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="customer_id" id="set_customer_id" value="">
                        <div class="form-group">
                            <div class=" row">
                                <label class="col-sm-2 control-label" for="address">{{ translate('Address') }}</label>
                                <div class="col-sm-10">
                                    <textarea placeholder="{{ translate('Address') }}" id="address" name="address" class="form-control" required></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class=" row">
                                <label class="col-sm-2 control-label">{{ translate('Country') }}</label>
                                <div class="col-sm-10">
                                    <select class="form-control aiz-selectpicker" data-live-search="true"
                                        data-placeholder="{{ translate('Select your country') }}" name="country_id"
                                        required>
                                        <option value="">{{ translate('Select your country') }}</option>
                                        @foreach (\App\Models\Country::where('status', 1)->get() as $key => $country)
                                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="row">
                                <div class="col-sm-2 control-label">
                                    <label>{{ translate('State') }}</label>
                                </div>
                                <div class="col-sm-10">
                                    <select class="form-control mb-3 aiz-selectpicker" data-live-search="true"
                                        name="state_id" required>

                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="row">
                                <div class="col-sm-2">
                                    <label>{{ translate('City') }}</label>
                                </div>
                                <div class="col-sm-10">
                                    <select class="form-control mb-3 aiz-selectpicker" data-live-search="true"
                                        name="city_id" required>

                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class=" row">
                                <label class="col-sm-2 control-label"
                                    for="postal_code">{{ translate('Postal code') }}</label>
                                <div class="col-sm-10">
                                    <input type="number" min="0" placeholder="{{ translate('Postal code') }}"
                                        id="postal_code" name="postal_code" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class=" row">
                                <label class="col-sm-2 control-label" for="phone">{{ translate('Phone') }}</label>
                                <div class="col-sm-10">
                                    <input type="number" min="0" placeholder="{{ translate('Phone') }}"
                                        id="phone" name="phone" class="form-control" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-styled btn-base-3"
                            data-dismiss="modal">{{ translate('Close') }}</button>
                        <button type="submit"
                            class="btn btn-primary btn-styled btn-base-1">{{ translate('Save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection


@section('script')
    <script type="text/javascript">
        var products = null;
        // Global variable to store grand total
        let cachedGrandTotal = 0;
        const currencySymbol = "{{ currency_symbol() }}";

        $(document).ready(function() {
            $('body').addClass('side-menu-closed');
            $('#product-list').on('click', '.add-plus:not(.c-not-allowed)', function() {
                var stock_id = $(this).data('stock-id');
                var userId = $('select[name=user_id]').val();
                $.post('{{ route('pos.addToCart') }}', {
                    _token: AIZ.data.csrf,
                    stock_id: stock_id
                }, function(data) {
                    if (data.success == 1) {
                        updateCart(data.view);
                    } else {
                        AIZ.plugins.notify('danger', data.message);
                    }

                });
            });
            filterProducts();
            getShippingAddress();
        });

        $("#confirm-address").click(function() {
            var data = new FormData($('#shipping_form')[0]);

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': AIZ.data.csrf
                },
                method: "POST",
                url: "{{ route('pos.set-shipping-address') }}",
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data, textStatus, jqXHR) {}
            })
        });

        function updateCart(data) {
            $('#cart-details').html(data);
            AIZ.extra.plusMinus();

            setTimeout(function() {
                // Re-initialize payment validation after cart update
                if (typeof initializePaymentValidation === 'function') {
                    initializePaymentValidation();
                }
                // Refresh selectpickers
                if (typeof AIZ.plugins.bootstrapSelect !== 'undefined') {
                    $('.aiz-selectpicker').selectpicker('refresh');
                }
            }, 100);
        }

        function updateCart2(data) {
            let cartContainer = document.querySelector('.aiz-pos-cart-list');
            let scrollPosition = cartContainer ? cartContainer.scrollTop : 0;

            $('#cart-details').html(data);

            if (cartContainer) {
                document.querySelector('.aiz-pos-cart-list').scrollTop = scrollPosition;
            }
            AIZ.extra.plusMinus();

            setTimeout(function() {
                if (typeof initializePaymentValidation === 'function') {
                    initializePaymentValidation();
                }
                if (typeof AIZ.plugins.bootstrapSelect !== 'undefined') {
                    $('.aiz-selectpicker').selectpicker('refresh');
                }
            }, 100);
        }

        // Add this function to get dynamic total due from the updated cart
        function updateTotalDueFromCart() {
            let totalDueText = $('#total-due-amount').text();
            if (!totalDueText || totalDueText === '') {
                // Try to get from grand total
                totalDueText = $('#grand-total').text();
            }
            let totalDue = parseFloat(totalDueText.replace(/[^0-9.-]/g, '')) || 0;
            return totalDue;
        }

        function filterProducts() {
            var keyword = $('input[name=keyword]').val();
            var category = $('select[name=poscategory]').val();
            var brand = $('select[name=brand]').val();
            $.get('{{ route('pos.search_product') }}', {
                keyword: keyword,
                category: category,
                brand: brand
            }, function(data) {
                products = data;
                $('#product-list').html(null);
                setProductList(data);
            });
        }

        function loadMoreProduct() {
            var keyword = $('input[name=keyword]').val();
            var category = $('select[name=poscategory]').val();
            var brand = $('select[name=brand]').val();
            if (products != null && products.links.next != null) {
                $('#load-more').find('.btn').html('{{ translate('Loading..') }}');
                $.get(products.links.next, {
                    keyword: keyword,
                    category: category,
                    brand: brand
                }, function(data) {
                    products = data;
                    setProductList(data);
                });
            }
        }

        function setProductList(data) {
            for (var i = 0; i < data.data.length; i++) {

                $('#product-list').append(
                    `<div class="w-140px w-xl-180px w-xxl-210px mx-2">
                        <div class="card bg-white c-pointer product-card hov-container">
                            <div class="position-relative">
                                ${data.data[i].digital == 0 
                                    ?
                                        `<span class="absolute-top-left mt-1 ml-1 mr-0">
                                                                                                    ${data.data[i].qty > 0
                                                                                                        ? `<span class="badge badge-inline badge-success fs-13">{{ translate('In stock') }}`
                                                                                                        : `<span class="badge badge-inline badge-danger fs-13">{{ translate('Out of stock') }}` }
                                                                                                    : ${data.data[i].qty}</span>
                                                                                                </span>`
                                    : ''
                                }
                                ${data.data[i].variant != null
                                    ? `<span class="badge badge-inline badge-warning absolute-bottom-left mb-1 ml-1 mr-0 fs-13 text-truncate">${data.data[i].variant}</span>`
                                    : '' }
                                <img src="${data.data[i].thumbnail_image }" class="card-img-top img-fit h-120px h-xl-180px h-xxl-210px mw-100 mx-auto" >
                            </div>
                            <div class="card-body p-2 p-xl-3">
                                <div class="text-truncate fw-600 fs-14 mb-2">${data.data[i].name}</div>
                                <div class="">
                                    ${data.data[i].price != data.data[i].base_price
                                        ? `<del class="mr-2 ml-0">${data.data[i].base_price}</del><span>${data.data[i].price}</span>`
                                        : `<span>${data.data[i].base_price}</span>`
                                    }
                                </div>
                            </div>
                            <div class="add-plus absolute-full rounded overflow-hidden hov-box ${(data.data[i].digital == 0 && data.data[i].qty <= 0) ? 'c-not-allowed' : '' }" data-stock-id="${data.data[i].stock_id}">
                                <div class="absolute-full bg-dark opacity-50">
                                </div>
                                <i class="las la-plus absolute-center la-6x text-white"></i>
                            </div>
                        </div>
                    </div>`
                );
            }
            if (data.links.next != null) {
                $('#load-more').find('.btn').html('{{ translate('Load More.') }}');
            } else {
                $('#load-more').find('.btn').html('{{ translate('Nothing more found.') }}');
            }
        }

        function removeFromCart(id) {
            $.post('{{ route('pos.removeFromCart') }}', {
                _token: AIZ.data.csrf,
                id: id
            }, function(data) {
                updateCart(data);
            });
        }


        function updateQuantity(cartId) {
            $.post('{{ route('pos.updateQuantity') }}', {
                _token: AIZ.data.csrf,
                cartId: cartId,
                quantity: $('#qty-' + cartId).val()
            }, function(data) {
                if (data.success == 1) {
                    updateCart2(data.view);
                } else {
                    AIZ.plugins.notify('danger', data.message);
                }
            });
        }

        function setDiscount() {
            var discount = $('input[name=discount]').val();
            $.post('{{ route('pos.setDiscount') }}', {
                _token: AIZ.data.csrf,
                discount: discount
            }, function(data) {
                updateCart(data);
            });
        }

        function setPartialPayment() {
            var partialPayment = $('input[name=partial_payment]').val();
            $.post('{{ route('pos.partial-payment') }}', {
                _token: AIZ.data.csrf,
                partial_payment: partialPayment
            }, function(data) {
                updateCart(data);
            });
        }

        function setShipping() {
            var shipping = $('input[name=shipping]').val();
            $.post('{{ route('pos.setShipping') }}', {
                _token: AIZ.data.csrf,
                shipping: shipping
            }, function(data) {
                updateCart(data);
            });
        }

        function getShippingAddressUpdateCartData() {
            getShippingAddress();
            var $userID = '{{ $userID }}';
            if (!$userID) {
                updateSessionUserCartData();
            } else {
                $('#change-customer').modal('show');
            }
        }

        function getShippingAddress() {
            $.post('{{ route('pos.getShippingAddress') }}', {
                _token: AIZ.data.csrf,
                id: $('select[name=user_id]').val()
            }, function(data) {
                $('#shipping_address').html(data);
            });
        }

        function updateSessionUserCartData() {
            $.post('{{ route('pos.updateSessionUserCartData') }}', {
                _token: AIZ.data.csrf,
                userId: $('select[name=user_id]').val()
            }, function(data) {
                updateCart(data);
            });
        }

        function add_new_address() {
            var customer_id = $('#customer_id').val();
            $('#set_customer_id').val(customer_id);
            $('#new-address-modal').modal('show');
            $("#close-button").click();
        }

        function orderConfirmation() {
            $('#order-confirmation').html(
                `<div class="p-4 text-center"><i class="las la-spinner la-spin la-3x"></i></div>`);
            $('#order-confirm').modal('show');
            $.post('{{ route('pos.getOrderSummary') }}', {
                _token: AIZ.data.csrf
            }, function(data) {
                $('#order-confirmation').html(data);
            });
        }

        function oflinePayment() {
            var totalPrice = $('#total_price').val();
            $('#offline_payment_amount').val(totalPrice);
            $('#offlin_payment').modal('show');
        }

        // Calculate total payment from all methods
        function calculateTotalPayment() {
            let cashAmount = parseFloat($('input[name="cash_amount"]').val()) || 0;
            let bankAmount = parseFloat($('input[name="bank_amount"]').val()) || 0;
            let mobileAmount = parseFloat($('input[name="mobile_amount"]').val()) || 0;

            let totalPayment = cashAmount + bankAmount + mobileAmount;
            $('#total-payment-amount').text(formatCurrency(totalPayment));

            return totalPayment;
        }

        // Get grand total (the amount customer needs to pay)
        function getGrandTotal() {
            let grandTotalText = $('#grand-total-display').text();
            let grandTotal = parseFloat(grandTotalText.replace(/[^0-9.-]/g, '')) || 0;
            cachedGrandTotal = grandTotal;
            return grandTotal;
        }

        // Get total due amount
        function getTotalDue() {
            // Get the total due from the display (remove currency symbol and commas)
            let totalDueText = $('#total-due-amount').text();
            let totalDue = parseFloat(totalDueText.replace(/[^0-9.-]/g, '')) || 0;
            return totalDue;
        }


        function calculateTotalDue() {
            let grandTotal = getGrandTotal();
            let totalPayment = calculateTotalPayment();
            let totalDue = grandTotal - totalPayment;

            // Ensure total due is not negative
            if (totalDue < 0) {
                totalDue = 0;
            }

            $('#total-due-amount').text(formatCurrency(totalDue));

            // Change color based on payment status
            if (totalDue == 0) {
                $('#total-due-amount').css('color', '#28a745'); // Green for fully paid
            } else {
                $('#total-due-amount').css('color', '#e91e2c'); // Red for due
            }

            return totalDue;
        }

        // Validate and limit payment amount
        function validatePaymentAmount(input, type) {
            let grandTotal = getGrandTotal();
            let currentValue = parseFloat(input.value) || 0;

            // Get other payment amounts
            let cashAmount = parseFloat($('input[name="cash_amount"]').val()) || 0;
            let bankAmount = parseFloat($('input[name="bank_amount"]').val()) || 0;
            let mobileAmount = parseFloat($('input[name="mobile_amount"]').val()) || 0;

            // Calculate total without current input
            let totalWithoutCurrent = 0;
            if (type === 'cash') {
                totalWithoutCurrent = bankAmount + mobileAmount;
            } else if (type === 'bank') {
                totalWithoutCurrent = cashAmount + mobileAmount;
            } else if (type === 'mobile') {
                totalWithoutCurrent = cashAmount + bankAmount;
            }

            // Calculate maximum allowed for this field
            let maxAllowed = grandTotal - totalWithoutCurrent;

            if (maxAllowed < 0) {
                maxAllowed = 0;
            }

            // If current value exceeds maximum allowed
            if (currentValue > maxAllowed) {
                input.value = maxAllowed;
                currentValue = maxAllowed;

                // Show warning message
                AIZ.plugins.notify('warning', 'Payment amount cannot exceed remaining due of ' + formatCurrency(
                    maxAllowed));

                // Highlight the input
                $(input).addClass('is-invalid');
                setTimeout(function() {
                    $(input).removeClass('is-invalid');
                }, 2000);
            } else {
                $(input).removeClass('is-invalid');
            }

            // Update the specific amount based on type
            if (type === 'cash') {
                cashAmount = currentValue;
            } else if (type === 'bank') {
                bankAmount = currentValue;
            } else if (type === 'mobile') {
                mobileAmount = currentValue;
            }

            // Update the input value (in case it was changed)
            $('input[name="' + type + '_amount"]').val(currentValue);

            // Recalculate totals
            calculateTotalPayment();
            calculateTotalDue();

            return currentValue;
        }

        // Validate payment and show difference
        function validatePayment() {
            let totalPayment = calculateTotalPayment();
            let totalDue = getTotalDue();
            let difference = totalDue - totalPayment;

            if (difference > 0) {
                // Under payment
                $('#payment-difference').text(formatCurrency(difference));
                $('#payment-difference-row').show();
                $('#payment-difference').removeClass('text-success').addClass('text-danger');
                return false;
            } else if (difference < 0) {
                // Over payment
                let overPayment = Math.abs(difference);
                $('#payment-difference').text(formatCurrency(overPayment));
                $('#payment-difference-row').show();
                $('#payment-difference').removeClass('text-danger').addClass('text-success');
                return true; // Allow overpayment
            } else {
                // Exact payment
                $('#payment-difference-row').hide();
                return true;
            }
        }

        // Format currency
        function formatCurrency(amount) {
            amount = parseFloat(amount);

            return currencySymbol + amount.toFixed(2);
        }

        // Monitor payment input changes
        $(document).on('keyup change',
            '.payment-input, input[name="cash_amount"], input[name="bank_amount"], input[name="mobile_amount"]',
            function() {
                calculateTotalPayment();
                calculateTotalDue();
            });

        // Monitor payment input changes
        $(document).ready(function() {
            // Your existing document ready code...

            // Add event listeners for payment inputs
            $('input[name="cash_amount"], input[name="bank_amount"], input[name="mobile_amount"]').on(
                'keyup change',
                function() {
                    validatePayment();
                });
        });

        // Initialize payment validation function
        function initializePaymentValidation() {
            getGrandTotal();
            calculateTotalPayment();
            calculateTotalDue();

            // Add real-time validation to all payment inputs
            $('.payment-input, input[name="cash_amount"], input[name="bank_amount"], input[name="mobile_amount"]').off(
                'keyup change input').on('keyup change input', function() {
                let type = $(this).attr('name').replace('_amount', '');
                validatePaymentAmount(this, type);
            });
        }


        // Validate and submit order
        // function validateAndSubmitOrder() {
        //     let grandTotal = getGrandTotal();
        //     let totalPayment = calculateTotalPayment();
        //     let totalDue = grandTotal - totalPayment;

        //     // if (totalPayment <= 0) {
        //     //     AIZ.plugins.notify('danger', 'Please enter at least one payment amount');
        //     //     return false;
        //     // }

        //     if (totalDue > 0) {
        //         if (confirm('Total due amount is ' + formatCurrency(totalDue) +
        //                 '. Do you want to proceed with partial payment?')) {
        //             submitOrderWithPayments();
        //         }
        //     } else {
        //         submitOrderWithPayments();
        //     }
        // }

        // Validate and submit order
        function validateAndSubmitOrder() {
            let grandTotal = getGrandTotal();
            let totalPayment = calculateTotalPayment();
            let totalDue = grandTotal - totalPayment;

            // Allow zero payment for Cash on Delivery
            if (totalPayment == 0 && totalDue > 0) {
                if (confirm('No payment received. This will be treated as Cash on Delivery. Total due: ' + formatCurrency(
                        totalDue) + '. Do you want to proceed?')) {
                    submitOrderWithPayments();
                }
                return;
            }

            if (totalPayment > 0 && totalDue > 0) {
                if (confirm('Total due amount is ' + formatCurrency(totalDue) +
                        '. Do you want to proceed with partial payment?')) {
                    submitOrderWithPayments();
                }
            } else if (totalPayment > 0 && totalDue == 0) {
                submitOrderWithPayments();
            } else if (totalPayment == 0 && totalDue == 0) {
                AIZ.plugins.notify('danger', 'Cannot place order with zero amount');
                return false;
            } else {
                AIZ.plugins.notify('danger', 'Please enter payment amount');
                return false;
            }
        }

        // Submit order with payment details
        // function submitOrderWithPayments() {
        //     let user_id = $('select[name=user_id]').val();
        //     let shipping = $('input[name="shipping"]').val();
        //     let discount = $('input[name="discount"]').val();
        //     let order_note = $('[name="order_note"]').val();
        //     let shipping_address = $('input[name="address_id"]:checked').val();

        //     // Get payment details
        //     let cash_account_id = $('select[name="cash_account_id"]').val();
        //     let cash_amount = $('input[name="cash_amount"]').val() || 0;

        //     let bank_account_id = $('select[name="bank_account_id"]').val();
        //     let bank_amount = $('input[name="bank_amount"]').val() || 0;

        //     let mobile_bank_account_id = $('select[name="mobile_bank_account_id"]').val();
        //     let mobile_amount = $('input[name="mobile_amount"]').val() || 0;

        //     // Validate at least one payment method is selected with amount
        //     if ((parseFloat(cash_amount) > 0 && !cash_account_id) ||
        //         (parseFloat(bank_amount) > 0 && !bank_account_id) ||
        //         (parseFloat(mobile_amount) > 0 && !mobile_bank_account_id)) {
        //         AIZ.plugins.notify('danger', 'Please select account for payment method');
        //         return false;
        //     }

        //     // Double check total payment doesn't exceed grand total
        //     let grandTotalPos = getGrandTotal();
        //     let totalPayment = parseFloat(cash_amount) + parseFloat(bank_amount) + parseFloat(mobile_amount);
        //     let totalDuePos = grandTotal - totalPayment;
        //     if (totalPayment > grandTotal) {
        //         AIZ.plugins.notify('danger', 'Total payment cannot exceed grand total');
        //         return false;
        //     }

        //     // Show loading
        //     AIZ.plugins.notify('info', 'Processing order...');

        //     $.post('{{ route('pos.order_place') }}', {
        //         _token: AIZ.data.csrf,
        //         user_id: user_id,
        //         shipping_address: shipping_address,
        //         payment_type: 'multiple',
        //         shipping: shipping,
        //         discount: discount,
        //         order_note: order_note,
        //         cash_account_id: cash_account_id,
        //         cash_amount: cash_amount,
        //         bank_account_id: bank_account_id,
        //         bank_amount: bank_amount,
        //         mobile_bank_account_id: mobile_bank_account_id,
        //         mobile_amount: mobile_amount,
        //         grand_total: grandTotalPos,
        //         total_payment: totalPayment,
        //         total_due: totalDuePos,

        //     }, function(data) {
        //         if (data.success == 1) {
        //             AIZ.plugins.notify('success', data.message);
        //             setTimeout(function() {
        //                 location.reload();
        //             }, 1500);
        //         } else {
        //             AIZ.plugins.notify('danger', data.message);
        //         }
        //     }).fail(function(xhr) {
        //         AIZ.plugins.notify('danger', 'An error occurred. Please try again.');
        //         console.log(xhr.responseText);
        //     });
        // }

        // Submit order with payment details
        function submitOrderWithPayments() {
            let user_id = $('select[name=user_id]').val();
            let shipping = $('input[name="shipping"]').val();
            let discount = $('input[name="discount"]').val();
            let order_note = $('[name="order_note"]').val();
            let shipping_address = $('input[name="address_id"]:checked').val();

            // Get payment details
            let cash_account_id = $('select[name="cash_account_id"]').val();
            let cash_amount = $('input[name="cash_amount"]').val() || 0;

            let bank_account_id = $('select[name="bank_account_id"]').val();
            let bank_amount = $('input[name="bank_amount"]').val() || 0;

            let mobile_bank_account_id = $('select[name="mobile_bank_account_id"]').val();
            let mobile_amount = $('input[name="mobile_amount"]').val() || 0;

            // Validate at least one payment method is selected with amount
            if ((parseFloat(cash_amount) > 0 && !cash_account_id) ||
                (parseFloat(bank_amount) > 0 && !bank_account_id) ||
                (parseFloat(mobile_amount) > 0 && !mobile_bank_account_id)) {
                AIZ.plugins.notify('danger', 'Please select account for payment method');
                return false;
            }

            // Double check total payment doesn't exceed grand total
            let grandTotalValue = getGrandTotal();
            let totalPaymentValue = parseFloat(cash_amount) + parseFloat(bank_amount) + parseFloat(mobile_amount);
            let totalDueValue = grandTotalValue - totalPaymentValue;

            if (totalPaymentValue > grandTotalValue) {
                AIZ.plugins.notify('danger', 'Total payment cannot exceed grand total');
                return false;
            }

            // Show loading
            AIZ.plugins.notify('info', 'Processing order...');

            $.post('{{ route('pos.order_place') }}', {
                _token: AIZ.data.csrf,
                user_id: user_id,
                shipping_address: shipping_address,
                payment_type: 'multiple',
                shipping: shipping,
                discount: discount,
                order_note: order_note,
                cash_account_id: cash_account_id,
                cash_amount: cash_amount,
                bank_account_id: bank_account_id,
                bank_amount: bank_amount,
                mobile_bank_account_id: mobile_bank_account_id,
                mobile_amount: mobile_amount,
                grand_total: grandTotalValue,
                total_payment: totalPaymentValue,
                total_due: totalDueValue,
            }, function(data) {
                if (data.success == 1) {
                    AIZ.plugins.notify('success', data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    AIZ.plugins.notify('danger', data.message);
                }
            }).fail(function(xhr) {
                AIZ.plugins.notify('danger', 'An error occurred. Please try again.');
                console.log(xhr.responseText);
            });
        }


        // end of new section for payment method added 


        //address
        $(document).on('change', '[name=country_id]', function() {
            var country_id = $(this).val();
            get_states(country_id);
        });

        $(document).on('change', '[name=state_id]', function() {
            var state_id = $(this).val();
            get_city(state_id);
        });

        function get_states(country_id) {
            $('[name="state"]').html("");
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{ route('get-state') }}",
                type: 'POST',
                data: {
                    country_id: country_id
                },
                success: function(response) {
                    var obj = JSON.parse(response);
                    if (obj != '') {
                        $('[name="state_id"]').html(obj);
                        AIZ.plugins.bootstrapSelect('refresh');
                    }
                }
            });
        }

        function get_city(state_id) {
            $('[name="city"]').html("");
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{ route('get-city') }}",
                type: 'POST',
                data: {
                    state_id: state_id
                },
                success: function(response) {
                    var obj = JSON.parse(response);
                    if (obj != '') {
                        $('[name="city_id"]').html(obj);
                        AIZ.plugins.bootstrapSelect('refresh');
                    }
                }
            });
        }

          // Immediately initialize total due when cart loads
    (function() {
        let grandTotalElement = document.getElementById('grand-total-display');
        let totalDueElement = document.getElementById('total-due-amount');
        
        if (grandTotalElement && totalDueElement) {
            let grandTotalText = grandTotalElement.innerText;
            let grandTotal = parseFloat(grandTotalText.replace(/[^0-9.-]/g, '')) || 0;
            totalDueElement.innerText = grandTotalText;
            
            if (grandTotal == 0) {
                totalDueElement.style.color = '#28a745';
            } else {
                totalDueElement.style.color = '#e91e2c';
            }
        }
    })();
    </script>
@endsection
