<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice</title>
    <style>
        @page {
            margin: 10px;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 10px;
            color: #000;
        }
        .text-center {
            text-align: center;
        }
        .text-left {
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .bold {
            font-weight: bold;
        }
        .section {
    margin-bottom: 2px;
    page-break-inside: avoid;
    page-break-after: auto;
}
        .box {
            border: 1px solid #000;
            padding: 5px;
            margin-top: 10px;
        }
        .box table {
            width: 100%;
        }
        .box td {
            padding: 4px;
            border-bottom: 1px solid #000;
        }
        .box td:last-child {
            text-align: right;
        }
        .no-border td {
            border: none;
        }
        table {
            page-break-inside: avoid;
        }
        .section, td, th, div, p {
            line-height: 1.8;
        }
    </style>
</head>
<body style="width: {{ $print_width }}mm; margin: 0 auto; padding: 5mm;">

    <div style="page-break-inside: avoid;">
    <!-- Header -->
    <div class="text-center section">
        @php
            $logo = get_setting('header_logo');
        @endphp

        @if ($logo)
            <img src="{{ uploaded_asset($logo) }}" alt="Logo" style="height: 40px; margin-bottom: 2px;">
        @else
            <h2 style="margin: 0; font-size: 20px;">KORAS</h2>
        @endif

        {{-- <div style="font-size: 10px; letter-spacing: 1px;">BE YOUR OWN LABEL</div> --}}
        
        <div>{{ get_setting('contact_address', null, App::getLocale()) }}</div>
        <div>Hotline: {{ get_setting('contact_phone') }}</div>
        <div>Website: {{ get_setting('website_name') }}</div>
    </div>

    @php
        $shipping_address = json_decode($order->shipping_address);
        $pathao = App\Models\PathaoCourierSetting::first();
    @endphp

    <!-- Invoice Info -->
    <div class="section">
        <table style="width: 100%;">
            <tr>
                <td><strong>Invoice no:</strong>{{ $order->code }}</td>
                <td class="text-right"><strong>Date:</strong> {{ date('d M Y h:i:sa', $order->date) }}</td>
            </tr>
        </table>
    </div>

    <!-- Delivery Info -->
    <div class="section">
        <strong>Deliver to</strong><br>
        {{ $shipping_address->name }}<br>
        {{ $shipping_address->address ?? '' }},{{ $shipping_address->city ?? '' }},
		@if(!empty(json_decode($order->shipping_address)->state ?? '')) {{ json_decode($order->shipping_address)->state }} - @endif {{ $shipping_address->postal_code ?? ''}},
			{{ $shipping_address->country ?? ''}}<br>
        <strong>Mobile:</strong> {{ $shipping_address->phone ?? ''}}
    </div>

    <!-- Order Summary Box -->
    <div class="box">
        <table class="no-border">
            <tr>
                <td>Total amount</td>
                <td>{{ single_price($order->grand_total) }}</td>
            </tr>
            <tr>
                <td>Deliver by</td>
                <td>
                    @if ($order->steadfast_status !== null)
                        Steadfast - {{ $order->steadfast_status }}
                    @elseif ($order->pathao_status !== null)
                        Pathao - {{ $order->pathao_status }}
                    @else
                        {{ $order->delivery_status }}
                    @endif
                </td>
            </tr>
            @if(!empty($order->pathao_id))
            <tr>
                <td>Merchant ID</td>
                <td>{{$pathao->store_id}}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Consignment ID -->
    @if(!empty($order->pathao_id))
    <div class="section" style="margin-top: 10px;">
        <strong>Consignment ID: {{ $order->pathao_id ?? '' }}</strong>
    </div>
    @elseif(!empty($order->steadfast_id))
    <div class="section" style="margin-top: 10px;">
        <strong>Consignment ID: {{ $order->steadfast_id ?? '' }}</strong>
    </div>
    @endif

    <!-- Software Info -->
    <div class="section">
        <small>Developed by SofttechSolutionLtd.com</small>
    </div>
    </div>

    <div style="height: 20px; page-break-after: avoid;"></div>

</body>
</html>
