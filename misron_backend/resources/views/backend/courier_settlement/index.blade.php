@extends('backend.layouts.app')

@section('content')
    <style>
        .page-title {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #6b7280;
            font-size: 18px;
            margin-bottom: 35px;
        }

        .top-filter-box,
        .table-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        }

        .top-filter-box {
            padding: 24px;
            margin-bottom: 40px;
        }

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 18px;
        }

        .search-input {
            height: 58px;
            border-radius: 14px;
            border: 1px solid #d1d5db;
            padding-left: 52px;
            font-size: 16px;
            box-shadow: none !important;
        }

        .custom-select-box,
        .export-btn,
        .new-btn {
            height: 58px;
            border-radius: 14px;
            font-weight: 600;
        }

        .custom-select-box {
            border: 1px solid #d1d5db;
            min-width: 160px;
        }

        .filter-icon {
            width: 58px;
            height: 58px;
            border-radius: 14px;
            border: 1px solid #d1d5db;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            color: #6b7280;
            font-size: 18px;
            cursor: pointer;
        }

        .export-btn {
            border: 1px solid #d1d5db;
            background: #fff;
            padding: 0 24px;
        }

        .new-btn {
            background: var(--account-color);
            color: #fff;
            border: none;
            padding: 0 28px;
        }

        .new-btn:hover {
            background: #4338ca;
            color: #fff;
        }

        .table-card {
            overflow-x: auto;
        }

        .custom-table {
            margin-bottom: 0;
            min-width: 1200px;
        }

        .custom-table thead th {
            border: none;
            padding: 24px 18px;
            font-size: 12px;
            font-weight: 700;
            color: #9ca3af;
            letter-spacing: 1px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .custom-table tbody td {
            vertical-align: middle;
            border-top: 1px solid #eef0f3;
            padding: 19px 5px;
            font-weight: 400;
            white-space: nowrap;
        }

        .invoice-badge {
            background: #eef2ff;
            color: var(--account-color);
            border: 1px solid #c7d2fe;
            border-radius: 10px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 700;
            display: inline-block;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .green-dot {
            background: #10b981;
        }

        .red-dot {
            background: #fb7185;
        }

        .status-text {
            font-size: 14px;
            font-weight: 700;
        }

        .paid {
            color: #059669;
        }

        .unpaid {
            color: #e11d48;
        }

        .pill {
            padding: 7px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            display: inline-block;
            line-height: 1;
        }

        .pill-success {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #86efac;
        }

        .pill-warning {
            background: #fff7ed;
            color: #d97706;
            border: 1px solid #fcd34d;
        }

        .pill-info {
            background: #e0f2fe;
            color: #0284c7;
            border: 1px solid #bae6fd;
        }

        .pill-danger {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .fee-box,
        .select-control {
            height: 44px;
            border-radius: 10px;
            border: 1px solid #d1d5db;
            font-weight: 600;
        }

        .fee-box {
            width: 100px;
        }

        .settle-btn {
            border-radius: 10px;
            background: #10b981;
            color: white;
            border: none;
            font-size: 12px;
            font-weight: 700;
            padding: 9px 16px;
            cursor: pointer;
            width: 100%;
        }

        .settle-btn:hover {
            background: #059669;
            color: white;
        }

        .settle-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .settled-btn {
            border-radius: 10px;
            background: #4f46e5;
            color: white;
            border: none;
            font-size: 12px;
            font-weight: 700;
            padding: 9px 16px;
            cursor: default;
            width: 100%;
        }

        .table-footer {
            padding: 20px 24px;
            border-top: 1px solid #eef0f3;
        }

        .footer-text {
            color: #6b7280;
            font-weight: 600;
            letter-spacing: .5px;
        }

        .pagination-btn {
            height: 42px;
            border-radius: 10px;
            border: 1px solid #d1d5db;
            background: #fff;
            padding: 0 18px;
            color: #9ca3af;
            font-weight: 600;
            margin: 0 4px;
        }

        .pagination-btn.active {
            background: var(--account-color);
            color: white;
            border-color: var(--account-color);
        }

        .settlement-fee-display {
            font-weight: 700;
            color: #4f46e5;
        }

        .courier-fee-input {
            background-color: #ffffff;
        }

        .settlement-info {
            font-size: 11px;
            color: #6b7280;
            margin-top: 5px;
            text-align: center;
        }


        .courier-fee-wrapper {
            min-width: 260px;
            background: #fff;
        }

        .partial-payment-box {
            min-width: 120px;
            flex: 0 0 120px;
            display: flex;
            align-items: center;
        }

        .partial-payment-box select {
            height: 100%;
            min-height: 38px;
            font-size: 14px;
            background-color: #f8f9fa;
            border-radius: 0;
            padding: 0 10px;
        }

        .courier-fee-input {
            min-width: 100px;
            height: 38px;
        }

        .given-customer-input,
        .actual-courier-fee-input,
        .admin-fee-field .form-control {
            min-width: 90px;
            height: 38px;
        }

        .given-customer-input::placeholder,
        .actual-courier-fee-input::placeholder {
            font-size: 11px;
            font-weight: 600;
            color: #9ca3af;
        }

        .courier-fee-input:focus,
        .partial-delivered-input:focus {
            box-shadow: none !important;
            outline: none;
        }

        .input-group-text {
            min-width: 38px;
            justify-content: center;
            border-radius: 0;
        }

        {{--  modal design  --}}

        /* ===== Modal Wrapper ===== */
        .order-items-modal .modal-dialog {
            max-width: 720px;
        }

        .custom-order-modal {
            border: 0;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        /* ===== Header ===== */
        .custom-order-modal .modal-header {
            align-items: flex-start;
        }

        .custom-order-modal .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: #1f2430;
        }

        .order-meta {
            font-size: 15px;
            color: #7c8190;
            margin-top: 4px;
        }

        .invoice-text {
            color: #4f46e5;
            font-weight: 700;
        }

        .customer-name {
            color: #2b2f38;
            font-weight: 600;
        }

        .modal-close-btn {
            font-size: 32px;
            color: #a3a7b3;
            opacity: 1;
            text-shadow: none;
            outline: none !important;
            box-shadow: none !important;
            padding: 0;
            margin: 0;
            line-height: 1;
        }

        .modal-divider {
            height: 1px;
            background: #f0f1f5;
        }

        /* ===== Body ===== */
        .order-items-table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .order-items-table thead th {
            background: #f7f7f9;
            color: #a0a4af;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1px;
            border: 0;
            padding: 16px 24px;
            white-space: nowrap;
        }

        .order-items-table tbody td {
            vertical-align: middle;
            padding: 20px 24px;
            border-top: 1px solid #f0f1f5;
            background: #fff;
        }

        .order-items-table tbody tr:first-child td {
            border-top: 0;
        }

        .product-name {
            font-size: 16px;
            font-weight: 700;
            color: #242933;
            min-width: 200px;
        }

        .product-price {
            font-size: 16px;
            font-weight: 700;
            color: #4b5160;
            white-space: nowrap;
        }

        /* ===== Variant Select ===== */
        .variant-select {
            min-width: 120px;
            height: 32px;
            border: 1px solid #e6e8ee;
            border-radius: 10px;
            background: #f7f7f9;
            padding: 0 34px 0 12px;
            font-size: 13px;
            font-weight: 600;
            color: #596074;
            outline: none;
            box-shadow: none;
        }

        /* ===== Variant Badge ===== */
        .variant-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 100px;
            height: 32px;
            padding: 0 14px;
            border-radius: 10px;
            background: #f4f4f6;
            color: #596074;
            font-size: 13px;
            font-weight: 700;
        }

        /* ===== Status Select ===== */
        .status-select {
            min-width: 138px;
            height: 36px;
            border-radius: 12px;
            padding: 0 38px 0 14px;
            font-size: 14px;
            font-weight: 700;
            outline: none;
            box-shadow: none;
            background-color: #fff;
        }

        .exchange-status {
            border: 1px solid #b9d2ff;
            color: #0d4eff;
            background: #f8fbff;
        }

        .return-status {
            border: 1px solid #ffc4cc;
            color: #e11d48;
            background: #fff8f8;
        }

        /* ===== Footer ===== */
        .custom-modal-footer {
            border-top: 1px solid #f0f1f5 !important;
            display: flex;
            justify-content: flex-end;
            gap: 14px;
        }

        .cancel-btn {
            background: transparent;
            color: #3a4150;
            font-size: 16px;
            font-weight: 500;
            border: 0;
            box-shadow: none !important;
        }

        .submit-btn {
            background: linear-gradient(90deg, #5b46ff 0%, #4f46e5 100%);
            color: #fff;
            border: 0;
            border-radius: 10px;
            padding: 10px 22px;
            font-size: 16px;
            font-weight: 700;
            box-shadow: none !important;
        }

        .submit-btn:hover {
            color: #fff;
            opacity: .95;
        }
    </style>

    <div class="">
        <h1 class="page-title">Courier Settlement</h1>
        <p class="page-subtitle">
            Manage, track and reconcile courier delivery settlements.
        </p>

        <!-- Top Filter -->
        <div class="row align-items-center">
            <div class="col-md-7 d-flex align-items-center mb-3 mb-md-0">
                <div class="search-box flex-grow-1 mr-3 position-relative">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="form-control search-input" id="searchInput"
                        placeholder="Search by invoice or customer...">
                </div>
                <div class="filter-icon">
                    <i class="fas fa-filter"></i>
                </div>
            </div>

            <div class="col-md-5 d-flex align-items-center justify-content-md-end">
                <select class="custom-select custom-select-box mr-3" id="statusFilter" style="max-width: 270px;">
                    <option value="">All Statuses</option>
                    <option value="paid">Paid</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                </select>
                <button class="btn export-btn d-flex justify-content-center align-items-center mr-3" id="exportPDFBtn">
                    <i class="fas fa-file-pdf mr-2"></i> Export PDF
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <table class="table custom-table" id="settlementTable">
                <thead>
                    <tr>
                        <th>INV #</th>
                        <th>Courier ID</th>
                        <th>CUSTOMER</th>
                        <th>STATUS</th>
                        <th>COURIER STATUS</th>
                        <th>DATE</th>
                        <th>TOTAL</th>
                        <th>SETT FEE</th>
                        <th>COUR FEE</th>
                        <th>ACCOUNT</th>
                        <th>COURIER</th>
                        <th>SETTLEMENT STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php
                            // For partial_delivered: the settlement base is the full order
                            // total (grand_total), not just the shipping cost, so the
                            // settlement fee preview matches the server calculation.
                            // When shipping cost is 0, fall back to due_amount.
                            $totalAmount = $order->due_amount;
                            if ($order->courier_status == 'partial_delivered' && $order->shipping_cost_sum > 0) {
                                $totalAmount = $order->grand_total ?: $order->due_amount;
                            }
                            // Get existing values
                            $existingCourierFee = $order->courier_fee ?? 0;
                            $existingSettlementFee = $order->settlement_fee ?? 0;
                            $isSettled = $order->settlement_status == 'true' ? true : false;
                            // dd(!$isSettled);

                            // Get settlement charge percentage for calculation
                            $settlementCharge = 0;
                            if ($order->courier_name == 'steadfast' && isset($steadfast)) {
                                $settlementCharge = $steadfast->settlement_charge ?? 0;
                            } elseif ($order->courier_name == 'pathao' && isset($pathao)) {
                                $settlementCharge = $pathao->settlement_charge ?? 0;
                            }

                            // Calculate settlement fee if not settled
                            // if ($order->settlement_status == 'true') {
                            //     $amountAfterCourierFee = $totalAmount - $existingCourierFee;
                            //     $existingSettlementFee = ($amountAfterCourierFee * $settlementCharge) / 100;
                            // }

                            // Determine payment status
                            $paymentStatus = 'unpaid';
                            $statusDot = 'red-dot';
                            $statusTextClass = 'unpaid';
                            $statusText = 'NON PAID';

                            if ($order->payment_status == 'paid') {
                                $paymentStatus = 'paid';
                                $statusDot = 'green-dot';
                                $statusTextClass = 'paid';
                                $statusText = 'PAID';
                            } elseif ($order->partial_paid_amount > 0) {
                                $paymentStatus = 'partial';
                                $statusDot = 'yellow-dot';
                                $statusTextClass = 'partial';
                                $statusText = 'PARTIAL';
                            }

                            // Determine courier status badge class
                            $courierStatusClass = 'pill-warning';
                            if ($order->courier_status == 'delivered') {
                                $courierStatusClass = 'pill-success';
                            } elseif ($order->courier_status == 'partial_delivered') {
                                $courierStatusClass = 'pill-info';
                            } elseif ($order->courier_status == 'pending') {
                                $courierStatusClass = 'pill-danger';
                            }

                            $shippingAddress = json_decode($order->shipping_address, true);

                            // Check if any order items are still pending review
                            $hasPendingItems = $order->pending_items_count > 0;
                        @endphp
                        <tr class="order-row" data-payment-status="{{ $paymentStatus }}" data-order-id="{{ $order->id }}"
                            data-due-amount="{{ $totalAmount }}" data-settlement-charge="{{ $settlementCharge }}"
                            data-is-settled="{{ $isSettled ? 'true' : 'false' }}"
                            data-has-pending="{{ $hasPendingItems ? 'true' : 'false' }}"
                            data-zero-shipping="{{ $order->courier_status == 'partial_delivered' && $order->shipping_cost_sum <= 0 ? 'true' : 'false' }}">
                            <td>
                                <span class="invoice-badge">{{ $order->code ?? 'INV-' . $order->id }}</span>
                            </td>
                            <td>
                                <span class="">{{ $order->courier_tracking_id ?? '' }}</span>
                            </td>
                            <td>{{ $shippingAddress['name'] ?? 'N/A' }}</td>
                            <td>
                                <span class="status-dot {{ $statusDot }}"></span>
                                <span class="status-text {{ $statusTextClass }}">{{ $statusText }}</span>
                            </td>
                            <td>
                                <span class="pill {{ $courierStatusClass }}">
                                    {{ strtoupper($order->courier_status ?? 'N/A') }}
                                </span>
                            </td>
                            <td>
                                <i class="far fa-calendar-alt text-muted mr-1"></i>
                                {{ date('Y-m-d', strtotime($order->created_at)) }}
                            </td>

                            <td class="total-amount">{{ single_price($totalAmount) }}</td>
                            <td class="settlement-fee-display settlement-fee-cell" data-order-id="{{ $order->id }}">
                                {{ '৳' . number_format($order->settlement_fee ?? 0, 2) }}
                            </td>
                            {{--  <td>
                                <div class="d-flex form-group mb-0 border rounded-2">
                                    @if ($order->courier_status == 'partial_delivered')
                                        <div class="d-flex justify-content-center align-items-center">
                                            <select name="partial_payment_choice" class="partial-delivered-input">
                                                <option value="customer"
                                                    {{ $order->partial_payment_choice == 'customer' ? 'selected' : '' }}>
                                                    Customer
                                                </option>
                                                <option value="admin"
                                                    {{ $order->partial_payment_choice == 'admin' ? 'selected' : '' }}>
                                                    Admin
                                                </option>
                                            </select>
                                        </div>
                                    @endif
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light border-right-0 font-weight-bold px-1">
                                                ৳
                                            </span>
                                        </div>
                                            <input type="number" step="0.01"
                                                class="form-control border-0 shadow-none courier-fee-input"
                                                data-order-id="{{ $order->id }}" value="{{ $order->courier_fee ?? 0 }}"
                                                placeholder="Actual Fee">
                                        </div>
                                </div>
                            </td>  --}}

                            <td>
                                {{--  <div class="d-flex align-items-stretch flex-nowrap border rounded overflow-hidden courier-fee-wrapper">  --}}
                                <div class="d-flex flex-column  border rounded overflow-hidden courier-fee-wrapper">

                                    @if ($order->courier_status == 'partial_delivered')
                                        {{--  <div class="partial-payment-box border-right bg-light">  --}}
                                        <div class="border bg-light">
                                            <select name="partial_payment_choice"
                                                class="form-control border-0 shadow-none partial-delivered-input">
                                                <option value="customer"
                                                    {{ $order->partial_payment_choice == 'customer' ? 'selected' : '' }}>
                                                    Customer
                                                </option>
                                                <option value="admin"
                                                    {{ $order->partial_payment_choice == 'admin' ? 'selected' : '' }}>
                                                    Admin
                                                </option>
                                            </select>
                                        </div>
                                    @endif

                                    @php
                                        $isZeroShippingPartial = $order->courier_status == 'partial_delivered' && $order->shipping_cost_sum <= 0;
                                        $partialChoice = $order->partial_payment_choice ?: 'customer';
                                    @endphp

                                    @if ($isZeroShippingPartial)
                                        {{-- Customer paid: given by customer + actual courier fee --}}
                                        <div class="customer-fee-fields flex-grow-1 flex-nowrap"
                                            style="display:{{ $partialChoice == 'admin' ? 'none' : 'flex' }};">
                                            <div class="input-group flex-grow-1 flex-nowrap">
                                                <div class="input-group-prepend">
                                                    <span
                                                        class="input-group-text bg-light border-0 border-right font-weight-bold px-2 h-100"
                                                        title="Given by customer">৳</span>
                                                </div>
                                                <input type="number" step="0.01"
                                                    class="form-control border-0 shadow-none given-customer-input"
                                                    data-order-id="{{ $order->id }}"
                                                    value="{{ $order->given_customer ?? '' }}"
                                                    placeholder="Given">
                                            </div>
                                            <div class="input-group flex-grow-1 flex-nowrap">
                                                <div class="input-group-prepend">
                                                    <span
                                                        class="input-group-text bg-light border-0 border-right font-weight-bold px-2 h-100"
                                                        title="Actual courier fee">৳</span>
                                                </div>
                                                <input type="number" step="0.01"
                                                    class="form-control border-0 shadow-none actual-courier-fee-input"
                                                    data-order-id="{{ $order->id }}"
                                                    value="{{ $order->courier_fee ?? '' }}"
                                                    placeholder="Actual Fee">
                                            </div>
                                        </div>
                                        {{-- Admin paid: single actual courier fee field (as before) --}}
                                        <div class="admin-fee-field input-group flex-grow-1 flex-nowrap"
                                            style="display:{{ $partialChoice == 'admin' ? 'flex' : 'none' }};">
                                            <div class="input-group-prepend">
                                                <span
                                                    class="input-group-text bg-light border-0 border-right font-weight-bold px-2 h-100">৳</span>
                                            </div>
                                            <input type="number" step="0.01"
                                                class="form-control border-0 shadow-none courier-fee-input"
                                                data-order-id="{{ $order->id }}" value="{{ $order->courier_fee ?? 0 }}"
                                                placeholder="Actual Fee">
                                        </div>
                                    @else
                                        <div class="input-group flex-grow-1 flex-nowrap">
                                            <div class="input-group-prepend">
                                                <span
                                                    class="input-group-text bg-light border-0 border-right font-weight-bold px-2 h-100">
                                                    ৳
                                                </span>
                                            </div>
                                            <input type="number" step="0.01"
                                                class="form-control border-0 shadow-none courier-fee-input"
                                                data-order-id="{{ $order->id }}" value="{{ $order->courier_fee ?? 0 }}"
                                                placeholder="0.00">
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <select class="custom-select select-control account-select"
                                    data-order-id="{{ $order->id }}">
                                    <option value="">Select Account</option>
                                    <optgroup label="Cash Accounts">
                                        @foreach ($cashAccounts as $account)
                                            <option value="{{ $account->id }}"
                                                {{ $order->settlement_account_id == $account->id ? 'selected' : '' }}>
                                                {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="Bank Accounts">
                                        @foreach ($bankAccounts as $account)
                                            <option value="{{ $account->id }}"
                                                {{ $order->settlement_account_id == $account->id ? 'selected' : '' }}>
                                                {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="Mobile Banking">
                                        @foreach ($mobileBankingAccounts as $account)
                                            <option value="{{ $account->id }}"
                                                {{ $order->settlement_account_id == $account->id ? 'selected' : '' }}>
                                                {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                </select>
                            </td>
                            <td>{{ ucfirst($order->courier_name ?? 'N/A') }}</td>
                            <td class="align-middle">

                                <div class="d-flex align-items-center">

                                    <!-- Details Link -->
                                    @if ($order->courier_status == 'partial_delivered')
                                        <a href="javascript:void(0)" class="details-link mr-2" data-toggle="modal"
                                            data-target="#manageOrderItemsModal">
                                            <i class="fas fa-eye mr-1"></i> Details
                                        </a>
                                    @endif


                                    @if ($order->settlement_status == 'true')
                                        <!-- Settled Button -->
                                        <button class="btn settled-btn" disabled>
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Settled
                                        </button>

                                        {{--  <!-- Unsettle Button -->
                                        <button class="btn btn-sm btn-outline-danger ml-2 unsettle-btn"
                                            data-settlement-id="{{ $order->settlement->id ?? '' }}"
                                            data-order-code="{{ $order->code }}"
                                            title="Reverse this settlement">
                                            <i class="fas fa-undo mr-1"></i> Unsettle
                                        </button>

                                        <!-- Settlement Info -->
                                        @if ($order->settlement)
                                            <div class="settlement-info mt-2">
                                                <span class="label-text">Settlement ID</span>
                                                <div class="settlement-no">
                                                    #{{ $order->settlement->settlement_no ?? 'N/A' }}
                                                </div>
                                            </div>
                                        @endif  --}}
                                    @else
                                        <!-- Settle Button -->
                                        <button class="btn settle-btn settle-order-btn"
                                            data-order-id="{{ $order->id }}">
                                            <i class="fas fa-money-bill-wave mr-1"></i>
                                            Settle Now
                                        </button>
                                    @endif

                                </div>

                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="mb-0">No courier orders found</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="table-footer d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">

<div class="footer-text text-center text-md-start">
                    SHOWING {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} OF {{ $orders->total() }}
                    INVOICES
                </div>

                <div class="d-flex flex-wrap justify-content-center gap-2">
                    {{ $orders->links() }}
                </div>

            </div>
        </div>
    </div>



  <!-- Order Items Modal -->
<div class="modal fade order-items-modal" id="manageOrderItemsModal" tabindex="-1" role="dialog"
    aria-labelledby="manageOrderItemsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document" style="max-width:95%;">
        <div class="modal-content custom-order-modal">

            {{-- Modal Header --}}
            <div class="modal-header border-0 pb-2 px-4 pt-4">
                <div>
                    <h4 class="modal-title font-weight-bold mb-1" id="manageOrderItemsModalLabel">
                        Manage Order Items
                    </h4>
                    <div class="order-meta">
                        Invoice: <span class="invoice-text" id="modalInvoiceCode">-</span>
                        <span class="mx-1">•</span>
                        Customer: <span class="customer-name" id="modalCustomerName">-</span>
                    </div>
                </div>
                <button type="button" class="close modal-close-btn" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            {{-- Divider --}}
            <div class="modal-divider"></div>

            {{-- Modal Body --}}
            <div class="modal-body px-0">
                <div class="table-responsive">
                    <table class="table mb-0 order-items-table">
                        <thead>
                            <tr>
                                <th>PRODUCT NAME</th>
                                <th>PRICE</th>
                                <th>VARIANTS</th>
                                <th>QTY</th>
                                <th>SHIPPING COST</th>
                                <th>STATUS</th>
                            </tr>
                        </thead>
                        <tbody id="modalOrderItemsBody">
                            <!-- Dynamic content will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Footer --}}
            <div class="modal-footer custom-modal-footer border-0 px-4 py-3">
                <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn submit-btn" id="submitOrderItemsBtn">Submit Changes</button>
            </div>

        </div>
    </div>
</div>



<!-- Add these additional CSS styles for new status options -->
<style>
    .delivered-status {
        border: 1px solid #86efac;
        color: #059669;
        background: #ecfdf5;
    }

    .cancelled-status {
        border: 1px solid #fecaca;
        color: #dc2626;
        background: #fef2f2;
    }

    .pending-status {
        border: 1px solid #fcd34d;
        color: #d97706;
        background: #fff7ed;
    }

    .partial-delivered-status {
        border: 1px solid #bae6fd;
        color: #0284c7;
        background: #e0f2fe;
    }

    .info-status {
        border: 1px solid #b9d2ff;
        color: #0d4eff;
        background: #f8fbff;
    }

    .exchange-status {
        border: 1px solid #c4b5fd;
        color: #7c3aed;
        background: #f5f3ff;
    }

    .return-status {
        border: 1px solid #fda4af;
        color: #e11d48;
        background: #fff1f2;
    }

    .exchange-product-fields .exchange-search-results {
        position: absolute;
        z-index: 3000;
        width: 100%;
    }

    .exchange-product-fields {
        position: relative;
    }

    .order-items-table td.product-name > div {
        min-width: 200px;
    }

    .order-items-table td.product-name .exchange-product-search {
        min-width: 200px;
    }

    .order-items-table td:nth-child(1),
    .order-items-table th:nth-child(1) {
        min-width: 220px;
        max-width: 350px;
    }
</style>



<script>
// Store current order data for the modal
let currentModalOrderId = null;

// Function to open modal with dynamic data
function openOrderItemsModal(orderId) {
    currentModalOrderId = orderId;

    // Show loading state
    $('#modalOrderItemsBody').html(`
        <tr>
            <td colspan="6" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="mt-2 mb-0">Loading order items...</p>
            </td>
        </tr>
    `);

    // Fetch order details via AJAX
    fetch(`/admin/orders/${orderId}/items`) // Adjust this route as needed
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateModalData(data.order, data.orderDetails);
                $('#manageOrderItemsModal').modal('show');
            } else {
                toastr.error('Failed to load order items');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toastr.error('Error loading order items');
        });
}

// Populate modal with order data
function populateModalData(order, orderDetails) {
    // Set header info
    const shippingAddress = JSON.parse(order.shipping_address || '{}');
    $('#modalInvoiceCode').text(order.code || `INV-${order.id}`);
    $('#modalCustomerName').text(shippingAddress.name || 'N/A');

    // Build items table
    let itemsHtml = '';

    orderDetails.forEach((detail, index) => {
        const product = detail.product || {};
        const variant = detail.variation || '';

        // Determine status based on current values
        let statusValue = detail.delivery_status || 'pending';
        let statusClass = getStatusClass(statusValue);

        itemsHtml += `
            <tr class="order-item-row" data-order-detail-id="${detail.id}">
                <td class="product-name">
                    ${product.name || 'Unknown Product'}
                </td>
                <td class="product-price">
                    ${formatCurrency(detail.price || 0)}
                </td>
                <td class="product-variant">
                    ${variant ?
                        `<span class="variant-badge">${variant}</span>` :
                        `<span class="variant-badge">No Variant</span>`
                    }
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm item-qty"
                           value="${detail.quantity}" min="1" style="width:70px;">
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control form-control-sm item-shipping"
                           value="${detail.shipping_cost || 0}" min="0" style="width:100px;">
                </td>
                <td>
                    <select class="status-select ${statusClass} item-status-select"
                            data-order-detail-id="${detail.id}"
                            data-original-status="${statusValue}">
                        <option value="">Select Status</option>
                        <option value="delivered" ${statusValue === 'delivered' ? 'selected' : ''}>
                            Delivered
                        </option>
                        <option value="exchange" ${statusValue === 'exchange' ? 'selected' : ''}>
                            Exchange
                        </option>
                        <option value="return" ${statusValue === 'return' ? 'selected' : ''}>
                            Return
                        </option>
                        <option value="cancelled" ${statusValue === 'cancelled' ? 'selected' : ''}>
                            Cancelled
                        </option>
                    </select>
                    <input type="hidden" class="exchange-product-id" value="">
                </td>
            </tr>
        `;
    });

    if (orderDetails.length === 0) {
        itemsHtml = `
            <tr>
                <td colspan="6" class="text-center py-5">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <p class="mb-0">No items found in this order</p>
                </td>
            </tr>
        `;
    }

    $('#modalOrderItemsBody').html(itemsHtml);

    // Add change event listeners to status selects
    $('.item-status-select').on('change', function() {
        updateStatusSelectStyle($(this));
        toggleExchangeFields($(this));
    });

    // Initialize exchange fields for rows where exchange is already selected
    $('.item-status-select').each(function() {
        if ($(this).val() === 'exchange') {
            toggleExchangeFields($(this));
        }
    });
}

// Toggle exchange - transform row cells in-place (like order edit)
function toggleExchangeFields(selectElement) {
    const el = selectElement.jquery ? selectElement[0] : selectElement;
    const row = el.closest('tr');
    const productCell = row.querySelector('td:nth-child(1)');
    const priceCell = row.querySelector('td:nth-child(2)');
    const variantCell = row.querySelector('td:nth-child(3)');
    const qtyInput = row.querySelector('.item-qty');
    const exchangeProductId = row.querySelector('.exchange-product-id');

    if (selectElement.val() === 'exchange') {
        if (row.dataset.originalProduct) return; // already transformed

        row.dataset.originalProduct = productCell.innerHTML;
        row.dataset.originalVariant = variantCell.innerHTML;
        row.dataset.originalPrice = priceCell.innerHTML;

        productCell.innerHTML = `
            <div style="position:relative;">
                <input class="form-control form-control-sm exchange-product-search"
                       placeholder="Search product..." autocomplete="off">
                <div class="exchange-search-results dropdown-menu"
                     style="display:none;position:absolute;top:100%;left:0;z-index:3000;width:100%;max-height:200px;overflow-y:auto;">
                </div>
            </div>
        `;

        variantCell.innerHTML = `
            <select class="form-control form-control-sm exchange-variation">
                <option value="">Select variation</option>
            </select>
        `;

        priceCell.innerHTML = formatCurrency(0);
        qtyInput.value = 1;
        exchangeProductId.value = '';

        attachExchangeSearch(row);
    } else {
        if (row.dataset.originalProduct) {
            productCell.innerHTML = row.dataset.originalProduct;
            variantCell.innerHTML = row.dataset.originalVariant;
            priceCell.innerHTML = row.dataset.originalPrice;
            delete row.dataset.originalProduct;
            delete row.dataset.originalVariant;
            delete row.dataset.originalPrice;
        }
        exchangeProductId.value = '';
    }
}

// Attach search handler to exchange input (order edit pattern)
function attachExchangeSearch(row) {
    const searchInput = row.querySelector('.exchange-product-search');
    if (!searchInput) return;

    $(searchInput).on('keyup', function() {
        const input = $(this);
        const results = row.querySelector('.exchange-search-results');
        const query = input.val().trim();

        if (query.length < 2) {
            results.style.display = 'none';
            results.innerHTML = '';
            return;
        }

        $.ajax({
            url: '{{ route('order.edit.product') }}',
            method: 'get',
            data: { data: query },
            success: function(products) {
                results.innerHTML = '';
                if (products.length > 0) {
                    products.forEach(function(product) {
                        const anchor = document.createElement('a');
                        anchor.href = "javascript:void(0);";
                        anchor.className = "dropdown-item";
                        anchor.setAttribute("data-product-id", product.id);
                        anchor.setAttribute("data-product-name", product.name);
                        anchor.setAttribute("data-discount", product.discount || 0);
                        anchor.setAttribute("data-discount_type", product.discount_type || '');
                        anchor.textContent = product.name;
                        anchor.onclick = function() {
                            selectExchangeProduct(product, input, results, row);
                        };
                        results.appendChild(anchor);
                    });
                    results.style.display = 'block';
                } else {
                    results.style.display = 'none';
                }
            }
        });
    });
}

// Calculate single price with discount (order edit pattern)
function singlePriceCalculation(discount, discountType, cost) {
    var discountAmount = 0;
    if (discountType == 'amount') {
        discountAmount = discount;
    } else if (discountType == 'percent') {
        discountAmount = cost * (discount / 100);
    }
    return cost - discountAmount;
}

// Select exchange product and load variations (order edit pattern)
function selectExchangeProduct(product, searchInput, resultsContainer, row) {
    const inputEl = searchInput.jquery ? searchInput[0] : searchInput;
    const resultsEl = resultsContainer.jquery ? resultsContainer[0] : resultsContainer;
    row.querySelector('.exchange-product-id').value = product.id;
    inputEl.value = product.name;
    resultsEl.style.display = 'none';
    resultsEl.innerHTML = '';

    const priceCell = row.querySelector('td:nth-child(2)');
    const variantSelect = row.querySelector('.exchange-variation');
    if (!variantSelect) return;

    // Store product discount info on row for variation change handler
    row.dataset.exchangeDiscount = product.discount || 0;
    row.dataset.exchangeDiscountType = product.discount_type || '';

    variantSelect.innerHTML = '<option value="">Select variation</option>';

    $.ajax({
        url: '{{ route('order.edit.variation') }}',
        method: 'get',
        data: { id: product.id },
        success: function(variations) {
            if (variations.length > 0) {
                variations.forEach(function(variation, index) {
                    const option = document.createElement('option');
                    option.value = variation.variant ? variation.variant : ' ';
                    option.setAttribute('data-cost', variation.price);
                    option.setAttribute('data-stock', variation.qty);
                    option.textContent = variation.variant ? variation.variant : 'No Variation';
                    variantSelect.appendChild(option);

                    if (index === 0) {
                        option.selected = true;
                        const discount = parseFloat(row.dataset.exchangeDiscount) || 0;
                        const discountType = row.dataset.exchangeDiscountType;
                        const finalPrice = singlePriceCalculation(discount, discountType, parseFloat(variation.price));
                        priceCell.innerHTML = formatCurrency(finalPrice);
                    }
                });
            } else {
                const option = document.createElement('option');
                option.value = ' ';
                option.textContent = 'No Variation';
                variantSelect.appendChild(option);
                const discount = parseFloat(row.dataset.exchangeDiscount) || 0;
                const discountType = row.dataset.exchangeDiscountType;
                const finalPrice = singlePriceCalculation(discount, discountType, parseFloat(product.unit_price || 0));
                priceCell.innerHTML = formatCurrency(finalPrice);
            }
        }
    });

    // Update price when variation changes
    $(variantSelect).off('change').on('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const cost = parseFloat(selectedOption.getAttribute('data-cost')) || 0;
        const discount = parseFloat(row.dataset.exchangeDiscount) || 0;
        const discountType = row.dataset.exchangeDiscountType;
        const finalPrice = singlePriceCalculation(discount, discountType, cost);
        priceCell.innerHTML = formatCurrency(finalPrice);
    });
}

// Update status select styling based on selection
function updateStatusSelectStyle(selectElement) {
    const value = selectElement.val();
    selectElement.removeClass('exchange-status return-status delivered-status cancelled-status pending-status');

    switch(value) {
        case 'exchange':
            selectElement.addClass('exchange-status');
            break;
        case 'return':
            selectElement.addClass('return-status');
            break;
        case 'delivered':
            selectElement.addClass('delivered-status');
            break;
        case 'cancelled':
            selectElement.addClass('cancelled-status');
            break;
        case 'partial_delivered':
            selectElement.addClass('partial-delivered-status');
            break;
        default:
            selectElement.addClass('pending-status');
    }
}

// Get CSS class based on status
function getStatusClass(status) {
    switch(status) {
        case 'delivered':
        case 'paid':
            return 'delivered-status';
        case 'exchange':
            return 'exchange-status';
        case 'return':
            return 'return-status';
        case 'cancelled':
            return 'cancelled-status';
        case 'partial_delivered':
            return 'info-status';
        default:
            return 'pending-status';
    }
}

// Format currency
function formatCurrency(amount) {
    return '৳' + parseFloat(amount).toFixed(2);
}

// Submit changes button handler
$('#submitOrderItemsBtn').on('click', function() {
    if (!currentModalOrderId) {
        toastr.error('No order selected');
        return;
    }

    const updates = [];
    let hasChanges = false;
    let hasError = false;

    // Collect all item status changes with additional fields
    $('.item-status-select').each(function() {
        if (hasError) return;

        const selectEl = this;
        const select = $(selectEl);
        const orderDetailId = select.data('order-detail-id');
        const originalStatus = select.data('original-status');
        const newStatus = select.val();

        if (!newStatus) return;

        const row = selectEl.closest('tr');
        const qtyInput = row.querySelector('.item-qty');
        const shippingInput = row.querySelector('.item-shipping');

        const update = {
            order_detail_id: orderDetailId,
            delivery_status: newStatus,
            quantity: qtyInput ? parseInt(qtyInput.value) || 1 : 1,
            shipping_cost: shippingInput ? parseFloat(shippingInput.value) || 0 : 0
        };

        if (newStatus === 'exchange') {
            const exchangeProductId = row.querySelector('.exchange-product-id')?.value;

            if (!exchangeProductId) {
                const productName = row.querySelector('.product-name')?.innerText || 'item';
                toastr.error('Please select an exchange product for ' + productName);
                hasError = true;
                return;
            }

            const exchangeVariation = row.querySelector('.exchange-variation')?.value || '';
            const exchangeQty = row.querySelector('.item-qty')?.value || 1;
            update.exchange_product_id = parseInt(exchangeProductId);
            update.exchange_variation = exchangeVariation || '';
            update.exchange_quantity = parseInt(exchangeQty) || 1;
        }

        updates.push(update);
        if (newStatus !== originalStatus) {
            hasChanges = true;
        }
    });

    if (hasError) return;

    if (updates.length === 0) {
        toastr.info('No items to update');
        return;
    }

    if (!hasChanges) {
        // Still allow submit if quantities/shipping changed even if status same
        const hasQtyOrShippingChange = updates.some(u => u.quantity !== undefined || u.shipping_cost !== undefined);
        if (!hasQtyOrShippingChange) {
            toastr.info('No changes detected');
            return;
        }
    }

    // Show loading state
    const submitBtn = $(this);
    const originalText = submitBtn.html();
    submitBtn.prop('disabled', true);
    submitBtn.html('<i class="fas fa-spinner fa-spin mr-2"></i> Saving...');

    // Send updates to server
    fetch('/admin/orders/update-items-status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            order_id: currentModalOrderId,
            updates: updates
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            toastr.success('Order items updated successfully');
            // Move focus before hiding to avoid aria-hidden focus warning
            if (document.activeElement && document.activeElement.blur) {
                document.activeElement.blur();
            }
            $('#manageOrderItemsModal').modal('hide');
            // Reload the page to reflect changes
            setTimeout(() => location.reload(), 1000);
        } else {
            toastr.error(data.message || 'Failed to update items');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toastr.error('An error occurred while updating items');
    })
    .finally(() => {
        submitBtn.prop('disabled', false);
        submitBtn.html(originalText);
    });
});

// Click handler for details links
$(document).ready(function() {
    // Delegate click event for dynamically added details links
    $(document).on('click', '.details-link', function(e) {
        e.preventDefault();
        const orderId = $(this).closest('tr').data('order-id');
        if (orderId) {
            openOrderItemsModal(orderId);
        }
    });

    // Initialize status select styles on page load
    $('.item-status-select').each(function() {
        updateStatusSelectStyle($(this));
    });
});
</script>

    <script>
        // Function to calculate settlement fee based on your formula
        // settlementFee = ((due_amount - courier_fee) * settlement_charge) / 100
        function calculateSettlementFee(dueAmount, courierFee, settlementCharge) {
            let amountAfterCourierFee = dueAmount - parseFloat(courierFee || 0);
            let settlementFee = (amountAfterCourierFee * settlementCharge) / 100;
            return settlementFee > 0 ? settlementFee : 0;
        }

        // Update settlement fee when courier fee changes (keyup event) - ALWAYS enabled
        document.querySelectorAll('.courier-fee-input').forEach(input => {
            input.addEventListener('keyup', function() {
                let row = this.closest('tr');
                // Don't recalculate for already settled orders — show the stored fee
                if (row.dataset.isSettled === 'true') return;
                let orderId = this.dataset.orderId;
                let dueAmount = parseFloat(row.dataset.dueAmount);
                let settlementCharge = parseFloat(row.dataset.settlementCharge);
                let courierFee = parseFloat(this.value) || 0;

                // Zero-shipping partial delivery (admin pays the courier directly):
                // no settlement fee is calculated
                let settlementFee;
                if (row.dataset.zeroShipping === 'true') {
                    settlementFee = 0;
                } else {
                    // Calculate settlement fee using the formula
                    settlementFee = calculateSettlementFee(dueAmount, courierFee, settlementCharge);
                }

                // Update settlement fee display
                let settlementFeeCell = row.querySelector('.settlement-fee-cell');
                if (settlementFeeCell) {
                    settlementFeeCell.innerHTML = '৳' + settlementFee.toFixed(2);
                }

                // Store calculated fee for settlement
                row.dataset.currentSettlementFee = settlementFee;
            });

            // Trigger initial calculation if there's a default value
            if (input.value && parseFloat(input.value) > 0) {
                input.dispatchEvent(new Event('keyup'));
            }
        });

        // Toggle customer/admin fee fields for zero-shipping partial deliveries
        document.querySelectorAll('.partial-delivered-input').forEach(select => {
            select.addEventListener('change', function() {
                const row = this.closest('tr');
                if (row.dataset.zeroShipping !== 'true') return;
                const isCustomer = this.value === 'customer';
                const customerFields = row.querySelector('.customer-fee-fields');
                const adminField = row.querySelector('.admin-fee-field');
                if (customerFields) customerFields.style.display = isCustomer ? 'flex' : 'none';
                if (adminField) adminField.style.display = isCustomer ? 'none' : 'flex';
            });
        });

        // Live settlement fee preview for "given by customer" and "actual courier fee" inputs
        // Settlement fee = (Given by customer - Actual courier fee) * Settlement charge %
        document.querySelectorAll('.given-customer-input, .actual-courier-fee-input').forEach(input => {
            input.addEventListener('keyup', function() {
                const row = this.closest('tr');
                // Don't recalculate for already settled orders — show the stored fee
                if (row.dataset.isSettled === 'true') return;
                const given = parseFloat(row.querySelector('.given-customer-input').value) || 0;
                const actual = parseFloat(row.querySelector('.actual-courier-fee-input').value) || 0;
                const settlementCharge = parseFloat(row.dataset.settlementCharge) || 0;
                const settlementFee = ((given - actual) * settlementCharge) / 100;

                let settlementFeeCell = row.querySelector('.settlement-fee-cell');
                if (settlementFeeCell) {
                    settlementFeeCell.innerHTML = '৳' + settlementFee.toFixed(2);
                }
                row.dataset.currentSettlementFee = settlementFee;
            });
        });

        // Settle order functionality - ONLY for orders with settlement_status == false
        document.querySelectorAll('.settle-order-btn').forEach(button => {
            button.addEventListener('click', function() {
                let orderId = this.dataset.orderId;
                let row = this.closest('tr');
                let accountSelect = row.querySelector('.account-select');
                let accountId = accountSelect ? accountSelect.value : '';
                let courierName = row.querySelector('td:nth-child(10)').innerText.trim();
                let dueAmount = parseFloat(row.dataset.dueAmount);
                let settlementCharge = parseFloat(row.dataset.settlementCharge);
                let partialDropdown = row.querySelector('.partial-delivered-input');
                let partialPaymentChoice = partialDropdown ? partialDropdown.value : null;

                // Zero-shipping partial delivery paid by customer: two inputs
                let isZeroShipping = row.dataset.zeroShipping === 'true';
                let isCustomerPaidZeroShipping = isZeroShipping && partialPaymentChoice === 'customer';
                let isAdminPaidZeroShipping = isZeroShipping && partialPaymentChoice === 'admin';

                let courierFee = 0;
                let givenCustomer = 0;
                let actualCourierFee = 0;

                if (isCustomerPaidZeroShipping) {
                    givenCustomer = parseFloat(row.querySelector('.given-customer-input').value) || 0;
                    actualCourierFee = parseFloat(row.querySelector('.actual-courier-fee-input').value) || 0;
                    courierFee = actualCourierFee;
                } else {
                    let courierFeeInput = row.querySelector('.courier-fee-input');
                    courierFee = courierFeeInput ? parseFloat(courierFeeInput.value) || 0 : 0;
                }

                let hasPendingItems = row.dataset.hasPending === 'true';

                // Warn if partial_delivered with pending items — user should go to Details first
                if (hasPendingItems) {
                    toastr.warning('This is a partial delivery. Please open Details and submit item statuses before settling.');
                    return;
                }

                // Calculate settlement fee
                let settlementFee;
                if (isCustomerPaidZeroShipping) {
                    // Settlement fee = (Given by customer - Actual courier fee) * Settlement charge %
                    settlementFee = ((givenCustomer - actualCourierFee) * settlementCharge) / 100;
                } else if (isAdminPaidZeroShipping) {
                    // Admin pays the courier directly: no settlement fee calculated
                    settlementFee = 0;
                } else {
                    settlementFee = calculateSettlementFee(dueAmount, courierFee, settlementCharge);
                }

                // Validate account selection
                if (!accountId) {
                    toastr.error('Please select an account');
                    return;
                }

                // Validate amounts
                if (isCustomerPaidZeroShipping) {
                    if (givenCustomer <= 0) {
                        toastr.error('Please enter the amount given by customer');
                        return;
                    }
                    if (actualCourierFee < 0) {
                        toastr.error('Actual courier fee cannot be negative');
                        return;
                    }
                } else if (courierFee < 0) {
                    toastr.error('Courier fee cannot be negative');
                    return;
                }

                // Confirmation message
                let confirmText;
                if (isCustomerPaidZeroShipping) {
                    confirmText = 'Are you sure you want to settle this order?\n\n' +
                        'Order #: ' + row.querySelector('.invoice-badge').innerText + '\n' +
                        'Given by Customer: ৳' + givenCustomer.toFixed(2) + '\n' +
                        'Actual Courier Fee: ৳' + actualCourierFee.toFixed(2) + '\n' +
                        'Difference (Given - Actual): ৳' + (givenCustomer - actualCourierFee).toFixed(2) + '\n' +
                        'Settlement Charge: ' + settlementCharge + '%\n' +
                        'Settlement Fee: ৳' + settlementFee.toFixed(2) + '\n\n' +
                        'Click OK to confirm settlement';
                } else if (isAdminPaidZeroShipping) {
                    confirmText = 'Are you sure you want to settle this order?\n\n' +
                        'Order #: ' + row.querySelector('.invoice-badge').innerText + '\n' +
                        'Actual Courier Fee: ৳' + courierFee.toFixed(2) + '\n' +
                        'No Settlement Fee (goes to Bad Debt Expense)\n\n' +
                        'Click OK to confirm settlement';
                } else {
                    confirmText = 'Are you sure you want to settle this order?\n\n' +
                        'Order #: ' + row.querySelector('.invoice-badge').innerText + '\n' +
                        'Due Amount: ৳' + dueAmount.toFixed(2) + '\n' +
                        'Courier Fee: ৳' + courierFee.toFixed(2) + '\n' +
                        'Amount after courier fee: ৳' + (dueAmount - courierFee).toFixed(2) + '\n' +
                        'Settlement Charge: ' + settlementCharge + '%\n' +
                        'Settlement Fee: ৳' + settlementFee.toFixed(2) + '\n\n' +
                        'Click OK to confirm settlement';
                }

                if (confirm(confirmText)) {

                    // Disable button to prevent double submission
                    let settleBtn = this;
                    settleBtn.disabled = true;
                    settleBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> PROCESSING...';

                    let payload = {
                        order_id: orderId,
                        courier_fee: courierFee,
                        account_id: accountId,
                        courier_name: courierName,
                        settlement_fee: settlementFee,
                        due_amount: dueAmount,
                        settlement_charge: settlementCharge,
                        partial_payment_choice: partialPaymentChoice
                    };

                    if (isCustomerPaidZeroShipping) {
                        payload.given_customer = givenCustomer;
                        payload.actual_courier_fee = actualCourierFee;
                    }

                    fetch('{{ route('courier-settlement.settle') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(payload)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                toastr.success('Order settled successfully! Settlement #: ' + data
                                    .settlement_no);
                                setTimeout(() => {
                                    location.reload();
                                }, 1500);
                            } else {
                                toastr.error('Failed to settle order: ' + data.message);
                                settleBtn.disabled = false;
                                settleBtn.innerHTML =
                                    '<i class="fas fa-money-bill-wave mr-1"></i> SETTLE NOW';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            toastr.error('An error occurred while settling the order');
                            settleBtn.disabled = false;
                            settleBtn.innerHTML =
                                '<i class="fas fa-money-bill-wave mr-1"></i> SETTLE NOW';
                        });
                }
            });
        });

        // Unsettle order functionality
        document.querySelectorAll('.unsettle-btn').forEach(button => {
            button.addEventListener('click', function() {
                let settlementId = this.dataset.settlementId;
                let orderCode = this.dataset.orderCode;
                let unsettleBtn = this;

                if (!settlementId) {
                    toastr.error('Settlement ID not found');
                    return;
                }

                if (!confirm('Are you sure you want to reverse this settlement for Order #' + orderCode + '?\n\nThis will:\n• Reverse all accounting entries\n• Restore account balances\n• Delete the settlement record\n• Mark the order as unsettled\n\nClick OK to confirm.')) {
                    return;
                }

                unsettleBtn.disabled = true;
                unsettleBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> REVERSING...';

                fetch('{{ route("courier-settlement.unsettle") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        settlement_id: settlementId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        toastr.success('Settlement reversed successfully for Order #' + orderCode);
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        toastr.error('Failed to reverse settlement: ' + data.message);
                        unsettleBtn.disabled = false;
                        unsettleBtn.innerHTML = '<i class="fas fa-undo mr-1"></i> Unsettle';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    toastr.error('An error occurred while reversing the settlement');
                    unsettleBtn.disabled = false;
                    unsettleBtn.innerHTML = '<i class="fas fa-undo mr-1"></i> Unsettle';
                });
            });
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let searchValue = this.value.toLowerCase();
            let rows = document.querySelectorAll('#settlementTable tbody tr.order-row');

            rows.forEach(row => {
                let text = row.textContent.toLowerCase();
                if (text.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Status filter
        document.getElementById('statusFilter').addEventListener('change', function() {
            let status = this.value;
            let rows = document.querySelectorAll('#settlementTable tbody tr.order-row');

            rows.forEach(row => {
                if (status === '' || row.dataset.paymentStatus === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // PDF Export functionality with filters
        document.getElementById('exportPDFBtn').addEventListener('click', function() {
            let searchValue = document.getElementById('searchInput').value;
            let statusValue = document.getElementById('statusFilter').value;

            let exportBtn = this;
            let originalHtml = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Generating PDF...';

            // Build URL with filters
            let url = '{{ route('courier-settlement.export-pdf') }}';
            let params = [];

            if (searchValue) params.push('search=' + encodeURIComponent(searchValue));
            if (statusValue && statusValue !== '') params.push('payment_status=' + encodeURIComponent(statusValue));

            if (params.length > 0) {
                url += '?' + params.join('&');
            }

            window.location.href = url;
            setTimeout(() => {
                exportBtn.disabled = false;
                exportBtn.innerHTML = originalHtml;
            }, 2000);
        });
    </script>
@endsection
