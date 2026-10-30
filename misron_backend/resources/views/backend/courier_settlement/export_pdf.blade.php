<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Courier Settlement Report</title>
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 20px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 10px;
        }
        .header h1 {
            color: #4f46e5;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            color: #6b7280;
        }
        .company-info {
            text-align: center;
            margin-bottom: 15px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
        }
        .summary-box {
            background: #f3f4f6;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
        }
        .summary-item {
            text-align: center;
            flex: 1;
        }
        .summary-label {
            font-size: 10px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #4f46e5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background: #4f46e5;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
        }
        td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            font-size: 14px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
        }
        .status-paid {
            background: #10b981;
            color: white;
            padding: 5px;
        }
        .status-unpaid {
            background: #ef4444;
            color: white;
            padding: 5px;
        }
        .status-partial {
            background: #f59e0b;
            color: white;
            padding: 5px;
        }
        .courier-status {
            display: inline-block;
            padding: 5px;
            border-radius: 12px;
            font-size: 10px;
        }
        .delivered {
            background: #10b981;
            color: white;
            padding: 5px;
        }
        .pending {
            background: #ef4444;
            color: white;
            padding: 5px;
        }
        .partial_delivered {
            background: #f59e0b;
            color: white;
            padding: 5px;
        }
        .footer {
            text-align: center;
            font-size: 9px;
            color: #6b7280;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
        }
        .account-info {
            font-size: 8px;
            color: #6b7280;
            margin-top: 3px;
        }
        .text-right {
            text-align: right;
        }
        .font-bold {
            font-weight: bold;
        }
        .settled-badge {
            background: #10b981;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-block;
            font-size: 10px;
            padding: 5px;
        }
        .unsettled-badge {
            background: #ef4444;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-block;
            font-size: 10px;
            padding: 5px;
        }
        .text-center {
            text-align: center;
        }
        .text-muted {
            color: #6b7280;
        }
        .mb-10 {
            margin-bottom: 10px;
        }
        .mt-20 {
            margin-top: 20px;
        }
        .filter-info {
            background: #f9fafb;
            padding: 8px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Courier Settlement Report</h1>
        <p>Generated on: {{ date('F j, Y, g:i a') }}</p>
    </div>
    
    @if($filters['search'] || $filters['payment_status'])
    <div class="filter-info">
        <strong>Applied Filters:</strong>
        @if($filters['search'])
            <span>🔍 Search: "{{ $filters['search'] }}"</span>
        @endif
        @if($filters['payment_status'])
            <span>📊 Status: {{ ucfirst($filters['payment_status']) }}</span>
        @endif
    </div>
    @endif
    
    <div class="summary-box">
        <div class="summary-item">
            <div class="summary-label">Total Orders</div>
            <div class="summary-value">{{ $orders->count() }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Total Due Amount</div>
            <div class="summary-value">${{ number_format($totals['due_amount'], 2) }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Total Courier Fee</div>
            <div class="summary-value">${{ number_format($totals['courier_fee'], 2) }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Total Settlement Fee</div>
            <div class="summary-value">${{ number_format($totals['settlement_fee'], 2) }}</div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>INV #</th>
                <th>CUSTOMER</th>
                <th>STATUS</th>
                <th>COURIER STATUS</th>
                <th>DATE</th>
                <th>TOTAL</th>
                <th>SETT FEE</th>
                <th>COUR FEE</th>
                <th>ACCOUNT</th>
                <th>COURIER</th>
                <th>SETTLEMENT</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            @php
                $shippingAddress = json_decode($order->shipping_address, true);
                $customerName = $shippingAddress['name'] ?? 'N/A';
                
                // Payment status
                $paymentStatus = 'unpaid';
                $statusClass = 'status-unpaid';
                $statusText = 'NON PAID';
                
                if($order->payment_status == 'paid') {
                    $paymentStatus = 'paid';
                    $statusClass = 'status-paid';
                    $statusText = 'PAID';
                } elseif($order->partial_paid_amount > 0) {
                    $paymentStatus = 'partial';
                    $statusClass = 'status-partial';
                    $statusText = 'PARTIAL';
                }
                
                // Courier status
                $courierStatusClass = 'pending';
                $courierStatusText = 'PENDING';
                if($order->courier_status == 'delivered') {
                    $courierStatusClass = 'delivered';
                    $courierStatusText = 'DELIVERED';
                } elseif($order->courier_status == 'partial_delivered') {
                    $courierStatusClass = 'partial_delivered';
                    $courierStatusText = 'PARTIAL';
                }
                
                // Settlement status
                $isSettled = $order->settlement_status == 'true' ? true : false;
                
                // Get account name
                $accountName = 'Not Selected';
                if($order->settlement_account_id) {
                    $account = \App\Models\ChartOfAccount::find($order->settlement_account_id);
                    $accountName = $account ? $account->name : 'Not Selected';
                }
            @endphp
            <tr>
                <td>{{ $order->code ?? 'INV-' . $order->id }}</td>
                <td>{{ $customerName }}</td>
                <td class="text-center">
                    <span class="">{{ $statusText }}</span>
                </td>
                <td class="text-center">
                    <span class="">{{ $courierStatusText }}</span>
                </td>
                <td>{{ date('Y-m-d', strtotime($order->created_at)) }}</td>
                <td class="text-right">${{ number_format($order->due_amount, 2) }}</td>
                <td class="text-right">${{ number_format($order->settlement_fee ?? 0, 2) }}</td>
                <td class="text-right">${{ number_format($order->courier_fee ?? 0, 2) }}</td>
                <td>{{ $accountName }}</td>
                <td>{{ ucfirst($order->courier_name ?? 'N/A') }}</td>
                <td class="text-center">
                    @if($isSettled)
                        <span class="">✓ SETTLED</span>
                        @if($order->settlement)
                            <div class="account-info">#{{ $order->settlement->settlement_no }}</div>
                        @endif
                    @else
                        <span class="">✗ UNSETTLED</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="11" class="text-center">No courier orders found</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    
    @if($orders->count() > 0)
    <div class="summary-box mt-20">
        <div class="summary-item">
            <div class="summary-label">Settled Orders</div>
            <div class="summary-value">{{ $totals['settled_count'] }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Unsettled Orders</div>
            <div class="summary-value">{{ $totals['unsettled_count'] }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Net Payable</div>
            <div class="summary-value">${{ number_format($totals['net_payable'], 2) }}</div>
        </div>
    </div>
    @endif
    
</body>
</html>