<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Invoice</title>
    <style>
        @page {
            margin: 0px 15px;
            margin-top:5px;
        }

        body {
            font-family: "Ariel",'solaiman';
            font-size: 12px;
            margin: 0;
            padding: 0px 10px;
            color: #000;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left !important;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .section {
            /*margin-bottom: 2px;*/
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .box {
            border: 1px solid #000;
            padding: 2px;
            margin-top: 10px;
        }

        .box table {
            width: 100%;
        }

        .box td {
            padding: 2px;
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

      
    </style>
</head>

<body style="width: 80mm; margin-top: 0px; margin-right: auto; margin-left: auto; padding:2mm; font-size:11px;">
    <div style="height: 10px;"></div>
        @php
            $logo = get_setting('system_logo_black');
        @endphp
    <div style="page-break-inside: avoid;">
        <!-- Header -->
        {{-- <div class="text-center section">
        @php
            $logo = get_setting('header_logo');
        @endphp

        @if ($logo)
            <img src="{{ uploaded_asset($logo) }}" alt="Logo" style="height: 25px; margin-bottom: 2px;">
        @else
            <h2 style="margin: 0; font-size: 20px;">KORAS</h2>
        @endif
        
        <div>{{ get_setting('contact_address', null, App::getLocale()) }}</div>
        <div>Hotline: {{ get_setting('contact_phone') }}</div>
        <div>Website: {{ get_setting('website_name') }}</div>
       </div> --}}

       <!-- Logo -->
        <table style="width:100%; border-collapse:collapse; margin-top:8px; padding-top:5px;">
            <tr>
                <td style="text-align:center;">
                    @if ($logo != null)
                        <img src="{{ uploaded_asset($logo) }}" height="25" width="110">
                    @else
                        <img src="{{ static_asset('assets/img/logo.png') }}" height="25" width="110">
                    @endif
                </td>
            </tr>
        </table>

        <!-- Contact Info -->
        <div style="text-align:center; margin-top:5px;">
            <div style="margin:0; padding:0; line-height:1.2;">
                <span style="font-weight:bold;">Call:</span> {{ get_setting('contact_phone') }}
            </div>
            <div style="margin:0; padding:0; line-height:1.2;">
                <span style="font-weight:bold;">Website:</span> {{ parse_url(config('app.url'), PHP_URL_HOST) }}
            </div>
        </div>

        <hr>

        @php
            $shipping_address = json_decode($order->shipping_address);
            $pathao = App\Models\PathaoCourierSetting::first();
        @endphp

        <!-- Invoice Info -->
        <table style="width:100%; border-collapse: collapse; margin-top:5px;">
    <tr>
        <!-- Left side -->
        <td style="width:60%; vertical-align:top; text-align:left; padding:0; margin:0;">
            <div style="margin:0; padding:0; line-height:1.5;">
                <strong>Order ID:</strong> {{ $order->code }}<br>
                <strong>Date:</strong> {{ date('d M Y', $order->date) }}
            </div>
        </td>

      <!-- Right side -->
<td style="width:40%; vertical-align:top; text-align:center; padding:0; margin:0;">
    @php
        $invoiceUrl = route('invoice.download', $order->id); // generate the invoice download URL
        $removedXML = '<?xml version="1.0" encoding="UTF-8"?>'; // remove unwanted XML header
    @endphp

    {!! str_replace($removedXML, '', QrCode::size(50)->generate($invoiceUrl)) !!}
</td>
    </tr>
</table>



        <!-- Delivery Info -->
         @php
            $address_parts = array_filter([
                $shipping_address->address ?? ''
            ]);
        @endphp
        <div class="section">
            <strong>Deliver to</strong><br>
            {{ $shipping_address->name }}<br>
            {{ implode(', ', $address_parts) }}<br>
            <strong>Mobile:</strong> {{ $shipping_address->phone ?? '' }}
        </div>
        
        {{-- <div class="section">
            <strong>Deliver to</strong><br>
            {{ $shipping_address->name }}<br>
            {{ $shipping_address->address ?? '' }},{{ $shipping_address->city ?? '' }},
            @if (!empty(json_decode($order->shipping_address)->state ?? ''))
                {{ json_decode($order->shipping_address)->state }} -
            @endif {{ $shipping_address->postal_code ?? '' }},
            {{ $shipping_address->country ?? '' }}<br>
            <strong>Mobile:</strong> {{ $shipping_address->phone ?? '' }}
        </div> --}}

        <!-- Order Summary Box -->
        <div class="box">
            <table class="no-border">
                <tr>
                    <td class="text-right" style="margin:0; line-height:0;">Payable Amount :</td>
                    <td class="text-left" style="margin:0; line-height:0;">{{ $order->grand_total }} Taka</td>
                </tr>
                <tr>
                    <td class="text-right" style="margin:0; line-height:.2;">Deliver By :</td>
                    <td class="text-left" style="margin:0; line-height:.2;">
                        @if ($order->steadfast_status !== null)
                            Steadfast - {{ $order->steadfast_status }}
                        @elseif ($order->pathao_status !== null)
                            Pathao - {{ $order->pathao_status }}
                        @else
                            {{ $order->delivery_status }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="text-right" style="margin:0; line-height:.2;">Parcel ID :</td>
                    <td class="text-left" style="margin:0; line-height:0; font-weight:bold;">
                        @if ($order->steadfast_id !== null)
                            {{ $order->steadfast_id }}
                        @elseif ($order->pathao_id !== null)
                            {{ $order->pathao_id }}
                        @else
                            'N/A'
                        @endif
                    </td>
                </tr>
                {{-- @if (!empty($order->pathao_id))
                    <tr>
                        <td>Merchant ID</td>
                        <td>{{ $pathao->store_id }}</td>
                    </tr>
                @endif --}}
            </table>
        </div>

        <!-- Consignment ID -->
        {{-- @if (!empty($order->pathao_id))
            <div class="section" style="margin-top: 10px;">
                <strong>Consignment ID: {{ $order->pathao_id ?? '' }}</strong>
            </div>
        @elseif(!empty($order->steadfast_id))
            <div class="section" style="margin-top: 10px;">
                <strong>Consignment ID: {{ $order->steadfast_id ?? '' }}</strong>
            </div>
        @endif --}}

        <!-- Software Info -->
        <div class="section">
            <p style="font-family: solaiman;font-weight: bold; text-align:center;">বিঃ দ্রঃ কোনো কারণে পার্সেল রিটার্ন বা এক্সচেঞ্জ করলে অবশ্যই ডেলিভারি চার্জ প্রদান করতে হবে। 
(ঢাকা সিটি-৮০ টাকা/ ঢাকা সিটির বাইরে ১৫০ টাকা)</p>
        </div>
    </div>
    </div>

    <div style="height: 20px; page-break-after: avoid;"></div>

</body>

</html>
