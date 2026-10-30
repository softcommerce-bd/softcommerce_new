@php
    $phone = optional(json_decode($order->shipping_address))->phone;

    $courierStatModel = App\Models\CourierStat::where('phone', $phone)->first();

    // Safe decode (handles null / string / array)
    $courier_stat =
        $courierStatModel && $courierStatModel->data
            ? (is_array($courierStatModel->data)
                ? $courierStatModel->data
                : json_decode($courierStatModel->data, true))
            : [];

    // Use summary if exists, otherwise fallback
    if (!empty($courier_stat['summary'])) {
        $totalParcel = $courier_stat['summary']['total_parcel'] ?? 0;
        $totalSuccess = $courier_stat['summary']['success_parcel'] ?? 0;
        $successRate = $courier_stat['summary']['success_ratio'] ?? 0;
    } else {
        $totalParcel = 0;
        $totalSuccess = 0;

        foreach ($courier_stat as $key => $courier) {
            if ($key === 'summary' || !is_array($courier)) {
                continue;
            }

            $totalParcel += $courier['total_parcel'] ?? 0;
            $totalSuccess += $courier['success_parcel'] ?? 0;
        }

        $successRate = $totalParcel > 0 ? round(($totalSuccess / $totalParcel) * 100, 2) : 0;
    }

    // Level logic
    if ($successRate > 90) {
        $level = 'High';
        $mainColor = '#26c299';
        $bgColor = '#e6f9f4';
    } elseif ($successRate > 60) {
        $level = 'Mid';
        $mainColor = '#FFBB18';
        $bgColor = '#fff8e6';
    } else {
        $level = 'Risk';
        $mainColor = '#ff5e6d';
        $bgColor = '#fdeeee';
    }

    // Trend
    $trendIcon = $successRate > 60 ? '↑' : '↓';
    $trendText = $successRate > 60 ? '+trend' : '-trend';
    $trendBg = $successRate > 60 ? '#e6f9f4' : '#fdeeee';
    $trendColor = $successRate > 60 ? '#26c299' : '#ff5e6d';
@endphp

<div class="d-flex justify-content-between align-items-center mb-1">
    <div>
        <span style="color: {{ $mainColor }}; font-size: 1rem;">
            {{ $successRate }}%
        </span>
        <span class="badge p-2 ml-1"
            style="background: {{ $bgColor }}; color: {{ $mainColor }}; font-size: 0.7rem;">
            {{ $level }}
        </span>
    </div>
    <div class="badge mr-3">
        <div class="px-1 py-0 rounded-1"
            style="background: {{ $trendBg }}; color: {{ $trendColor }}; font-size: 0.7rem;">
            {{ $trendIcon }} {{ $trendText }}
        </div>
    </div>
</div>

<div class="progress mb-2" style="height: 4px; background: #f0f0ff;">
    <div class="progress-bar" style="width: {{ $successRate }}%; background: {{ $mainColor }};">
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center" style="gap: 5px;">
    <div class="d-flex align-items-center mb-1 mb-sm-0">
        <small class="text-muted mr-2" style="font-weight: 600; font-size: 0.75rem; white-space: nowrap;">
            Last 05
        </small>

        <div class="d-flex align-items-center">
            @php
                $flags = $order->cancelled_flags ?? [];
            @endphp

            @foreach ($flags as $flag)
                <span class="mr-1"
                    style="
                        height: 10px;
                        min-width: 10px; /* Changed width to min-width */
                        width: 10px;
                        border-radius: 2px;
                        background: {{ $flag ? '#ff5e6d' : '#00d09c' }};
                        flex-shrink: 0;
                        ">
                </span>
            @endforeach
        </div>
    </div>
    
    <div class="mb-1 mb-sm-0">
        <a href="javascript:void(0)" class="px-2 py-1 text-dark overall-btn d-inline-block text-nowrap"
            data-order-id="{{ $order->id }}" 
            style="background:#f3f3ff; font-size:0.7rem; border-radius:4px; text-decoration: none;">
            Over all <span class="ml-1">→</span>
        </a>
    </div>
</div>
