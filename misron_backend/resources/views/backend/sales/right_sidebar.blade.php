<style>
    #sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        backdrop-filter: blur(4px);
        background: rgba(0, 0, 0, 0.2);
        opacity: 0;
        visibility: hidden;
        transition: all .3s ease;
        z-index: 999;
    }

    #sidebar-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    #right-sidebar {
        position: fixed;
        top: 0;
        right: -100%;
        /* Changed from -400px to -100% for mobile */
        width: 100%;
        /* Changed from fixed 400px to full width */
        max-width: 400px;
        /* Added max-width for larger screens */
        height: 100%;
        background: #fff;
        box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
        transition: right .3s ease;
        z-index: 1000;
        padding: 20px;
        overflow-y: auto;
        /* Make sidebar scrollable */
        display: flex;
        flex-direction: column;
    }

    /* For tablets and larger devices */
    @media (min-width: 768px) {
        #right-sidebar {
            right: -400px;
            width: 400px;
        }
    }

    /* Active state */
    #right-sidebar.active {
        right: 0;
    }

   
</style>


<!-- Overlay -->
<div id="sidebar-overlay"></div>

<div id="right-sidebar" class="p-3">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h5 class="mb-1 font-weight-bold">
                @isset($order)
                    {{ $order->user->name ?? (json_decode($order->shipping_address)->name ?? 'N/A') }}
                @else
                    N/A
                @endisset
            </h5>
            <small class="text-muted">Customer History & Courier Performance</small>
        </div>

        <button id="close-sidebar" class="btn btn-sm btn-outline-secondary rounded">
            ✕
        </button>
    </div>


    <!-- Courier Success Rate -->
    <h6 class="mt-4 mb-3">Courier Success Rate</h6>

    <table class="table table-sm text-center mb-4">
        <thead class="text-muted">
            <tr>
                <th class="text-left py-3">Courier</th>
                <th class="py-3">Total</th>
                <th class="py-3">Success</th>
                <th class="py-3">Cancel</th>
                <th class="py-3">Rate</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($courier_stat ?? [] as $key => $courier)
                @continue($key === 'summary' || !is_array($courier))

                @php
                    $rate = $courier['success_ratio'] ?? 0;

                    if ($rate > 80) {
                        $rateClass = 'text-success';
                    } elseif ($rate > 50) {
                        $rateClass = 'text-warning';
                    } else {
                        $rateClass = 'text-danger';
                    }
                @endphp

                <tr>
                    <td class="text-left font-weight-bold py-3">
                        {{ $courier['name'] ?? ucfirst($key) }}
                    </td>
                    <td class="py-3">{{ $courier['total_parcel'] ?? 0 }}</td>
                    <td class="text-success py-3">{{ $courier['success_parcel'] ?? 0 }}</td>
                    <td class="text-danger py-3">{{ $courier['cancelled_parcel'] ?? 0 }}</td>
                    <td class="{{ $rateClass }} font-weight-bold py-3">
                        {{ $rate }}%
                    </td>
                </tr>

            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-3">
                        No courier data found
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>



    <!-- Summary cards -->
    <div class="row text-center mb-4">
        <div class="col-4">
            <div class="p-2 bg-light rounded">
                <small class="text-muted d-block">Total</small>
                <strong>{{ $totalOrders ?? 0 }}</strong>
            </div>
        </div>

        <div class="col-4">
            <div class="p-2 rounded" style="background:#e9f7f3;">
                <small class="text-muted d-block">Success</small>
                <strong class="text-success">{{ $successOrders ?? 0 }}</strong>
            </div>
        </div>

        <div class="col-4">
            <div class="p-2 rounded" style="background:#fdeeee;">
                <small class="text-muted d-block">Cancel</small>
                <strong class="text-danger">{{ $cancelledOrders ?? 0 }}</strong>
            </div>
        </div>
    </div>


    <!-- Success Ratio -->
    @php
        $sidebarSuccessRate = $successPercentage ?? 0;
        $sidebarTrendClass = $sidebarSuccessRate > 60 ? 'text-success' : 'text-danger';
        $progressColor = $sidebarSuccessRate > 60 ? 'bg-success' : 'bg-danger';
    @endphp

    <div class="mb-4">
        <div class="d-flex justify-content-between mb-1">
            <small class="font-weight-bold">Overall Success Ratio</small>
            <small class="{{ $sidebarTrendClass }} font-weight-bold">
                {{ $sidebarSuccessRate }}%
            </small>
        </div>

        <div class="progress mb-1" style="height:6px;">
            <div class="progress-bar {{ $progressColor }}" style="width: {{ $sidebarSuccessRate }}%;"></div>
        </div>

        <div class="d-flex justify-content-between text-muted small">
            <span>0%</span>
            <span>50%</span>
            <span>100%</span>
        </div>
    </div>


    <!-- Last Orders -->
    <h6 class="font-weight-bold mb-3">Last 05 Orders</h6>

    <div>
        @isset($previousOrders)
            @forelse($previousOrders as $order)
                <div class="d-flex flex-column p-2 bg-light rounded mb-2">

                    {{-- Order Header --}}
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="font-weight-bold">#{{ $order->code ?? 'N/A' }}</div>
                        <small class="text-muted">
                            {{ \Carbon\Carbon::parse($order->created_at)->format('m/d/Y') }}
                        </small>
                    </div>

                    {{-- Products + Total in 2-column row --}}
                    <div class="row">
                        {{-- Products Column --}}
                        <div class="col-9 d-flex flex-column">
                            @foreach ($order->orderDetails as $detail)
                                @if ($detail->product)
                                    <div class="d-flex align-items-center mb-2">
                                        <img src="{{ uploaded_asset($detail->product->thumbnail_img) }}"
                                            alt="{{ $detail->product->name }}" width="40" height="40"
                                            style="object-fit: cover; border-radius: 6px;">
                                        <div class="ml-2">
                                            <small class="d-block">{{ $detail->product->name }} x{{ $detail->quantity ?? 1 }}</small>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        {{-- Grand Total Column --}}
                        <div class="col-3 d-flex align-items-end justify-content-end">
                            <strong>৳ {{ $order->grand_total ?? 0 }}</strong>
                        </div>
                    </div>

                </div>
            @empty
                <div class="text-muted text-center py-2">No previous orders</div>
            @endforelse
        @endisset
    </div>

</div>
