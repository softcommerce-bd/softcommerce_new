<div class="mb-3">
    <strong>Order No:</strong> #{{ $order->code }}<br>
    <strong>Courier:</strong> {{ $order->courier_name }}<br>
    <strong>Grand Total:</strong> {{ number_format($order->grand_total, 2) }} ৳<br>
    <strong>Settlement Fee:</strong> {{ number_format($order->settlement_fee, 2) }} ৳<br>
    <strong>Courier Fee:</strong> {{ number_format($order->courier_fee, 2) }} ৳<br>
    <strong>Net Payable:</strong> {{ number_format($order->grand_total - $order->settlement_fee - $order->courier_fee, 2) }} ৳<br>
    <strong>Date:</strong> {{ optional($order->settlement_date)->format('d M Y') }}
</div>

<table class="table table-sm table-bordered">
    <thead class="bg-light">
        <tr>
            <th>Product</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Shipping</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($order->orderDetails as $detail)
            @php
                $shipping = $detail->shipping_cost ?? 0;
                $lineTotal = $detail->price * $detail->quantity + $shipping;
            @endphp
            <tr>
                <td>{{ $detail->product->name ?? $detail->product_name ?? 'N/A' }}</td>
                <td>{{ $detail->quantity }}</td>
                <td>{{ number_format($detail->price, 2) }} ৳</td>
                <td>{{ number_format($shipping, 2) }} ৳</td>
                <td>{{ number_format($lineTotal, 2) }} ৳</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted">No products found</td></tr>
        @endforelse
    </tbody>
</table>