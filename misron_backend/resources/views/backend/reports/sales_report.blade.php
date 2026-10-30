@extends('backend.layouts.app')

@section('content')
    <style>
        .filter-card {
            margin-bottom: 20px;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
            background: #fff;
        }

        .filter-card .form-group label {
            font-weight: 600;
        }

        .button-submit {
            margin-top: 30px;
        }

        .table thead th {
            vertical-align: middle;
            text-align: center;
        }

        .table tbody td {
            vertical-align: middle;
            text-align: center;
        }

        .total-row {
            font-weight: 700;
            background: #f8f9fa;
        }
        
        .order-details {
            background-color: #f8f9fa;
        }
        
        .order-header {
            background-color: #e9ecef;
            font-weight: bold;
        }
        
        .pagination-info {
            margin-bottom: 15px;
            font-size: 14px;
            color: #6c757d;
        }
    </style>

    <div class="container-fluid">
        <h3 class="mb-0">{{ translate('Order Wise Sales Report') }}</h3>
        <!-- Page Header -->
        <div class="d-flex justify-content-end align-items-center mb-2">
            
            <div class="d-flex justify-content-between align-items-end">
                <div class="bg-soft-primary rounded-2 h-50px d-flex align-items-center justify-content-between text-primary px-2rem my-1 mr-2">
                    <div class="d-flex flex-wrap align-items-center">
                        <p class="fs-13 fw-600 text-dark mb-0">{{ translate('Total Orders ') }}</p>
                    </div>
                    <h1 class="fs-24 fw-600 mb-0">{{ $totalOrder }}</h1>
                </div>
                <div class="bg-soft-primary rounded-2 h-50px d-flex align-items-center justify-content-between text-primary px-2rem my-1">
                    <div class="d-flex flex-wrap align-items-center">
                        <p class="fs-13 fw-600 text-dark mb-0">{{ translate('Total Sales') }}</p>
                    </div>
                    <h1 class="fs-24 fw-600 mb-0">{{ single_price($totalSale) }}</h1>
                </div>
                <div class="bg-soft-primary rounded-2 h-50px d-flex align-items-center justify-content-between text-primary px-2rem my-1 mr-2">
                    <div class="d-flex flex-wrap align-items-center">
                        <p class="fs-13 fw-600 text-dark mb-0">{{ translate('Total Cost') }}</p>
                    </div>
                    <h1 class="fs-24 fw-600 mb-0">{{ single_price($totalBuyingCost) }}</h1>
                </div>
                <div class="bg-soft-primary rounded-2 h-50px d-flex align-items-center justify-content-between text-primary px-2rem my-1 mr-2">
                    <div class="d-flex flex-wrap align-items-center">
                        <p class="fs-13 fw-600 text-dark mb-0">{{ translate('Total Profit') }}</p>
                    </div>
                    <h1 class="fs-24 fw-600 mb-0">{{ single_price($totalProfit) }}</h1>
                </div>
                
                 <div class="bg-soft-primary rounded-2 h-50px d-flex align-items-center justify-content-between text-primary px-2rem my-1">
                    <div class="">
                        <p class="fs-13 fw-600 text-dark mb-0">{{ translate('Total Delivery Charge') }}</p>
                    </div>
                    <h1 class="fs-24 fw-600 mb-0">{{ single_price($totaldeliveryCharge) ?? 0 }}</h1>
                </div>
               
            </div>
        </div>
        

        <!-- Filter Section -->
        <div class="filter-card">
            <form action="{{ route('sales_report.index') }}" method="GET" id="filterForm">
                <div class="form-row">
                    <!-- Date Filter -->
                    <div class="form-group col-md-3">
                        <label>{{ translate('Filter by Date') }}</label>
                        <input type="text" class="form-control aiz-date-range" name="date" value="{{ $date }}"
                            placeholder="{{ translate('Select date') }}" data-format="DD-MM-Y" data-separator=" to "
                            data-advanced-range="true" autocomplete="off">
                    </div>

                    <!-- Category Filter -->
                    <div class="form-group col-md-2">
                        <label>{{ translate('Category') }}</label>
                        <select class="form-control aiz-selectpicker" name="category_id" data-live-search="true">
                            <option value="">{{ translate('All Categories') }}</option>
                            @foreach (\App\Models\Category::all() as $category)
                                <option value="{{ $category->id }}" @if ($category->id == $sort_by) selected @endif>
                                    {{ $category->getTranslation('name') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Payment Status -->
                    <div class="form-group col-md-2">
                        <label>{{ translate('Payment Status') }}</label>
                        <select class="form-control aiz-selectpicker" name="payment_status">
                            <option value="">{{ translate('All') }}</option>
                            <option value="paid" {{ $payment_status == 'paid' ? 'selected' : '' }}>
                                {{ translate('Paid') }}</option>
                            <option value="unpaid" {{ $payment_status == 'unpaid' ? 'selected' : '' }}>
                                {{ translate('Unpaid') }}</option>
                        </select>
                    </div>

                    <!-- Delivery Status -->
                    <div class="form-group col-md-2">
                        <label>{{ translate('Delivery Status') }}</label>
                        <select class="form-control aiz-selectpicker" name="delivery_status">
                            <option value="">{{ translate('All') }}</option>
                            <option value="pending" {{ $delivery_status == 'pending' ? 'selected' : '' }}>
                                {{ translate('Pending') }}</option>
                            <option value="confirmed" {{ $delivery_status == 'confirmed' ? 'selected' : '' }}>
                                {{ translate('Confirmed') }}</option>
                            <option value="delivered" {{ $delivery_status == 'delivered' ? 'selected' : '' }}>
                                {{ translate('Delivered') }}</option>
                            <option value="steadfast" {{ $delivery_status == 'steadfast' ? 'selected' : '' }}>
                                {{ translate('Steadfast Courier') }}</option>
                            <option value="cancelled" {{ $delivery_status == 'cancelled' ? 'selected' : '' }}>
                                {{ translate('Cancelled') }}</option>
                        </select>
                    </div>

                    <!-- Buttons -->
                    <div class="form-group col-md-1 button-submit">
                        <button type="submit" class="btn btn-primary btn-block" name="action"
                            value="filter">{{ translate('Filter') }}</button>
                    </div>

                    <div class="form-group col-md-1 button-submit">
                        <button type="submit" formaction="{{ route('sales_report.csv') }}"
                            class="btn btn-success btn-block" name="action" value="csv">{{ translate('CSV') }}</button>
                    </div>

                    <!-- Items per page selector -->
                    <div class="form-group col-md-1 button-submit">
                        <select class="form-control" name="per_page" onchange="this.form.submit()">
                            <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20</option>
                            <option value="50" {{ request('per_page') == 50 || !request('per_page') ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                            <option value="200" {{ request('per_page') == 200 ? 'selected' : '' }}>200</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- Pagination Info -->
        <div class="pagination-info">
            {{ translate('Showing') }} {{ $orders->firstItem() }} - {{ $orders->lastItem() }} {{ translate('of') }} {{ $orders->total() }} {{ translate('orders') }}
        </div>

        <!-- Orders Table -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0 table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Order ID') }}</th>
                                <th>{{ translate('Order Code') }}</th>
                                <th>{{ translate('Order Date') }}</th>
                                <th>{{ translate('Customer') }}</th>
                                <th>{{ translate('Items') }}</th>
                                <th>{{ translate('Payment Status') }}</th>
                                <th>{{ translate('Delivery Status') }}</th>
                                <th>{{ translate('Coupon Discount') }}</th>
                                <th>{{ translate('Total Sale') }}</th>
                                <th>{{ translate('Total Buying Cost') }}</th>
                                <th>{{ translate('Profit') }}</th>
                                <th>{{ translate('Delivery Charge') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $key => $order)
                            @php
                             $personName = json_decode($order->shipping_address)->name ?? '';
                            @endphp
                                <tr class="order-header">
                                    <td>{{ $orders->firstItem() + $key }}</td>
                                    <td>#{{ $order->id }}</td>
                                    <td>{{ $order->code ?? '' }}</td>
                                    <td>{{ date('d M Y', strtotime($order->created_at)) }}</td>
                                     <td>{{ $order->user->name ?? $personName }}</td>
                                    <td>{{ $order->orderDetails->count() }}</td>
                                    <td>
                                        <span class="badge badge-{{ $order->payment_status == 'paid' ? 'success' : 'warning' }} w-100">
                                            {{ ucfirst($order->payment_status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ getDeliveryStatusColor($order->delivery_status) }} w-100">
                                            {{ ucfirst($order->delivery_status) }}
                                        </span>
                                    </td>
                                    <td class="discount-text">
                                        @if($order->calculated_discount > 0)
                                            -{{ single_price($order->calculated_discount) }}
                                        @else
                                            {{ single_price(0) }}
                                        @endif
                                    </td>

                                    <td>{{ single_price($order->calculated_net_sale) }}</td>
                                    <td>{{ single_price($order->calculated_buying_cost) }}</td>
                                    <td class="{{ $order->calculated_profit >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ single_price($order->calculated_profit) }}
                                    </td>
                                    <td>{{ $order->orderDetails->first()->shipping_cost ?? 0 }}</td>
                                </tr>
                                
                                <!-- Order Details -->
                                @foreach($order->orderDetails as $detail)
                                <tr class="order-details">
                                    <td></td>
                                    <td colspan="2" class="text-left">
                                        <small><strong>Product Name:</strong> {{ $detail->product?->getTranslation('name') ?? 'N/A' }}</small>
                                    </td>
                                    <td>
                                        <small><strong>Qty x price:</strong>{{ $detail->quantity }} x {{ single_price($detail->price / max($detail->quantity, 1)) }}</small>
                                    </td>
                                    <td>
                                        <small><strong>Category:</strong>{{ $detail->product?->main_category?->getTranslation('name') ?? 'N/A' }}</small>
                                    </td>
                                    <td colspan="7" class="text-left">
                                        @php
                                            $buying_unit_cost = $detail->buying_price ?? 0;
                                            if ($buying_unit_cost == 0 && $detail->product && $detail->product->purchases->isNotEmpty()) {
                                                $buying_unit_cost = $detail->product->purchases->first()->buying_unit_cost ?? 0;
                                            }
                                            $detail_buying_cost = $buying_unit_cost * $detail->quantity;
                                            $detail_profit = $detail->price - $detail_buying_cost;
                                        @endphp
                                        <strong>Single Profit:</strong>
                                        <small>
                                            <strong>Buying Cost:</strong> {{ single_price($detail_buying_cost) }} | 
                                            <strong>Sale:</strong> {{ single_price($detail->price) }} | 
                                            <strong>Profit:</strong> <span class="{{ $detail_profit >= 0 ? 'text-success' : 'text-danger' }}">{{ single_price($detail_profit) }}</span>
                                        </small>
                                    </td>
                                </tr>
                                @endforeach
                                
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center">{{ translate('No orders found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="9" class="text-right">{{ translate('Grand Total') }}</td>
                                <td>{{ single_price($totalSale) }}</td>
                                <td>{{ single_price($totalBuyingCost) }}</td>
                                <td class="{{ $totalProfit >= 0 ? 'text-success' : 'text-danger' }}">{{ single_price($totalProfit) }}</td>
                                <td class="text-center">{{ single_price($totaldeliveryCharge) ?? 0 }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination Links -->
        @if($orders->hasPages())
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="pagination-info">
                {{ translate('Page') }} {{ $orders->currentPage() }} {{ translate('of') }} {{ $orders->lastPage() }}
            </div>
            <div>
                {{ $orders->appends(request()->input())->links() }}
            </div>
        </div>
        @endif
    </div>
@endsection

@php
    function getDeliveryStatusColor($status) {
        switch ($status) {
            case 'delivered': return 'success';
            case 'confirmed': return 'info';
            case 'pending': return 'warning';
            case 'cancelled': return 'danger';
            default: return 'secondary';
        }
    }
@endphp