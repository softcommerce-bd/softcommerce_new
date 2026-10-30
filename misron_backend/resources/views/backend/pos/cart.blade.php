<div class="aiz-pos-cart-list mb-4 mt-3 c-scrollbar-light">
    @php
        $subtotal = 0;
        $tax = 0;
        $carts = get_pos_user_cart();

        // Get accounts from chart_of_accounts table
        $cashAccounts = App\Models\ChartOfAccount::where('sub_type', 'cash')->where('status', 'active')->get();

        $bankAccounts = App\Models\ChartOfAccount::where('sub_type', 'bank')->where('status', 'active')->get();

        $mobileBankingAccounts = App\Models\ChartOfAccount::where('sub_type', 'mobile_bank')
            ->where('status', 'active')
            ->get();
    @endphp
    @if (count($carts) > 0)
        <ul class="list-group list-group-flush">
            @forelse ($carts as $key => $cartItem)
                @php
                    $product = $cartItem->product;
                    $stock = $cartItem->product->stocks->where('variant', $cartItem['variation'])->first();
                    $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                    $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
                    $cartID = $cartItem['id'];
                @endphp
                <li class="list-group-item py-0 pl-2">
                    <div class="row gutters-5 align-items-center">
                        <div class="col-auto w-60px">
                            <div class="row no-gutters align-items-center flex-column aiz-plus-minus">
                                <button class="btn col-auto btn-icon btn-sm fs-15" type="button" data-type="plus"
                                    data-field="qty-{{ $cartID }}">
                                    <i class="las la-plus"></i>
                                </button>
                                <input type="text" name="qty-{{ $cartID }}" id="qty-{{ $cartID }}"
                                    class="col border-0 text-center flex-grow-1 fs-16 input-number" placeholder="1"
                                    value="{{ $cartItem['quantity'] }}" min="{{ $product->min_qty }}"
                                    max="{{ $stock->qty }}" onchange="updateQuantity({{ $cartID }})">
                                <button class="btn col-auto btn-icon btn-sm fs-15" type="button" data-type="minus"
                                    data-field="qty-{{ $cartID }}">
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
                            <div class="fs-12 opacity-60">{{ single_price($cartItem['price']) }} x
                                {{ $cartItem['quantity'] }}</div>
                            <div class="fs-15 fw-600">{{ single_price($cartItem['price'] * $cartItem['quantity']) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-circle btn-icon btn-sm btn-soft-danger ml-2 mr-0"
                                onclick="removeFromCart({{ $cartItem['id'] }})">
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
            <input type="number" min="0" name="shipping" class="form-control text-right p-0"
                placeholder="{{ translate('Amount') }}" value="{{ Session::get('pos.shipping', 0) }}" required
                onchange="setShipping()">
        </div>
    </div>
    <div class="d-flex justify-content-between fw-600 opacity-70">

        <div class="col-md-6 px-0 d-flex align-items-center">
            <span>{{ translate('Discount') }}</span>
        </div>
        <div class="col-md-6 pr-0">
            <input type="number" min="0" name="discount" class="form-control text-right p-0"
                placeholder="{{ translate('Amount') }}" value="{{ Session::get('pos.discount', 0) }}" required
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
                                <select class="form-control aiz-selectpicker" name="cash_account_id">
                                    <option value="">Select Cash Account</option>
                                    @foreach ($cashAccounts as $cash)
                                        <option value="{{ $cash->id }}" data-name="{{ $cash->name }}"
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
                                <input type="number" class="form-control text-right p-0 cash-amount payment-input"
                                    name="cash_amount" placeholder="Enter Amount" value="0"
                                    oninput="validatePaymentAmount(this, 'cash')">
                            </div>
                        </div>

                        <!-- Bank -->
                        <div class="row mb-2">
                            <div class="col-md-2 d-flex align-items-center">
                                <label class="fw-600 mb-2 d-block">Bank</label>
                            </div>
                            <div class="col-md-6 mb-2 mb-md-0">
                                <select class="form-control aiz-selectpicker" name="bank_account_id">
                                    <option value="">Select Bank</option>
                                    @foreach ($bankAccounts as $bank)
                                        <option value="{{ $bank->id }}" data-name="{{ $bank->name }}"
                                            data-bank="{{ $bank->bank_name }}"
                                            data-account="{{ $bank->account_number }}" data-type="bank">
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
                                <input type="number" class="form-control text-right p-0 bank-amount payment-input"
                                    name="bank_amount" placeholder="Enter Amount" value="0"
                                    oninput="validatePaymentAmount(this, 'bank')">
                            </div>
                        </div>

                        <!-- Mobile Banking -->
                        <div class="row">
                            <div class="col-md-2 d-flex align-items-center">
                                <label class="fw-600 mb-2 d-block">Mobile Banking</label>
                            </div>
                            <div class="col-md-6 mb-2 mb-md-0">
                                <select class="form-control aiz-selectpicker" name="mobile_bank_account_id">
                                    <option value="">Select Method</option>
                                    @foreach ($mobileBankingAccounts as $mobileBank)
                                        <option value="{{ $mobileBank->id }}" data-name="{{ $mobileBank->name }}"
                                            data-mobile="{{ $mobileBank->mobile_number }}"
                                            data-charge="{{ $mobileBank->cashout_charge }}" data-type="mobile_bank">
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
                                <input type="number" class="form-control text-right p-0 mobile_amount payment-input"
                                    name="mobile_amount" placeholder="Enter Amount" value="0"
                                    oninput="validatePaymentAmount(this, 'mobile')">
                            </div>
                        </div>


                    </div>
                </div>

            </div>
        </div>
    </div>
    {{-- payment design end --}}
    {{-- Payment Summary --}}
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
            <span id="total-due-amount" class="fw-700" style="color: #e91e2c;">0.00</span>
        </div>
    </div>
</div>

<script>
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