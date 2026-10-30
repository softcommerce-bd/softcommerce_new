<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ translate('INVOICE') }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta charset="UTF-8">
    <style media="all">
        @page {
            margin: 0px 0px 120px 0px;
            /* leave bottom space for footer */
        }

        body {

            font-family: '<?php echo $font_family; ?>';
            font-weight: normal;
            direction: <?php echo $direction; ?>;
            text-align: <?php echo $text_align; ?>;
            padding: 0;
            margin: 0;
            position: relative;
        }

        .gry-color * {
            color: #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            font-weight: normal;
        }

        table.padding th,
        table.padding td {
            padding: .25rem .7rem;
        }

        table.sm-padding td {
            padding: .1rem .7rem;
        }

        table.lg-padding td {
            padding: .4rem .7rem;
            padding-left: 5px;
        }

        .border-bottom td,
        .border-bottom th {
            border-bottom: 1px solid #eceff4;
        }

        .text-left {
            text-align: <?php echo $text_align; ?>;
        }

        .text-right {
            text-align: <?php echo $not_text_align; ?>;
        }

        .invoice-layout {
            width: 100%;
            font-family: Arial, sans-serif;
            box-sizing: border-box;
        }

        td {
            padding: 5px;
            vertical-align: top;
        }

        .invoice-footer {
            position: fixed;
            bottom: -100px;
            left: 0;
            right: 0;
            width: 100%;
            height: 120px;
        }

        .invoice-footer table {
            width: 100%;
            border-collapse: collapse;
        }

        .center-align {
            text-align: center;
        }

        .page-break {
            page-break-before: always;
        }

        .main-content {
            margin-bottom: 140px;
        }
    </style>
</head>

<body>
    <div class="main-content">
        @php
            $logo = get_setting('system_logo_white');
            $shipping_address = json_decode($order->shipping_address);
        @endphp

        <!-- Header Section -->
        <table width="100%" style="background: #eceff4; padding: 1rem; border-collapse: collapse;">
            <tr>
                <!-- Logo -->
                <td style="width: 70%; vertical-align: middle; padding: 20px; padding-left: 45px; padding-top: 30px;">
                    @if ($logo != null)
                        <img src="{{ uploaded_asset($logo) }}" style="max-height: 90px; height: auto; max-width: 100%;">
                    @else
                        <img src="{{ static_asset('assets/img/logo.png') }}"
                            style="max-height: 90px; height: auto; max-width: 100%;">
                    @endif
                </td>

                <!-- Invoice Info -->
                <td style="width: 30%; vertical-align: middle; padding-right: 40px; padding-top: 35px;">
                    <div style="text-align: <?php echo $not_text_align; ?>;">
                        <span
                            style="font-size: 30px; font-weight:bold; display: block; margin-bottom: 10px; color: #000;">{{ translate('INVOICE') }}</span>
                        <div>
                            <div style="margin-bottom: 5px;">{{ translate('Phone') }}:
                                {{ get_setting('contact_phone') }}</div>
                            <div style="margin-bottom: 5px;">{{ translate('Email') }}:
                                {{ get_setting('contact_email') }}</div>
                            <div>{{ translate('Website') }}: {{ parse_url(config('app.url'), PHP_URL_HOST) }}</div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Customer and Order Info -->
        <div style="padding: 2rem; padding-bottom: 1rem;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <!-- Customer Info -->
                    <td style="width: 68%; vertical-align: top; padding-right: 2rem;">
                        <div style="line-height: 1.4;">
                            <div style="font-weight: 400; color:#000; margin-bottom: 0.5rem; font-size: 16px;">
                                {{ translate('Customer Info') }}:</div>
                            <div style="margin-bottom: 8px; font-size: 14px;"><span
                                    style="color:#000;font-weight:700;">{{ translate('Name') }}:</span>
                                {{ $shipping_address->name ?? '' }}</div>
                            <div style="margin-bottom: 8px; font-size: 14px;"><span
                                    style="color:#000;font-weight:700;">{{ translate('Mobile') }}:</span>
                                {{ $shipping_address->phone ?? '' }}</div>
                            @php
                                $address_parts = array_filter([
                                    $shipping_address->address ?? '',
                                    // $shipping_address->city ?? '',
                                    $shipping_address->state ?? '',
                                    $shipping_address->postal_code ?? '',
                                    $shipping_address->country ?? '',
                                ]);
                            @endphp

                            <div style="margin-bottom: 8px; font-size: 14px;">
                                <span style="color:#000;font-weight:700;">{{ translate('Address') }}:</span>
                                {{ implode(', ', $address_parts) }}
                            </div>

                            <div style="margin-bottom: 8px; font-size: 14px;">
                                <span style="color:#000;font-weight:700;">{{ translate('Email') }}:</span>
                                {{ $shipping_address->email ?? '' }}
                            </div>

                        </div>
                    </td>

                    <!-- Order Info -->
                    <td style="width: 32%; vertical-align: top;">
                        <div style="line-height: 1.4; text-align: <?php echo $not_text_align; ?>;">
                            <div style="font-weight: 400; color:#000; margin-bottom: 0.5rem; font-size: 16px;">
                                {{ translate('Order Info') }}:</div>
                            <div style="margin-bottom: 8px; font-size:14px;"><span
                                    style="color:#000;font-weight:700;">{{ translate('Order Id') }}:</span>
                                {{ $order->code }}</div>
                            <div style="margin-bottom: 8px; font-size:14px;"><span
                                    style="color:#000;font-weight:700;">{{ translate('Order Date') }}:</span>
                                {{ date('d-m-Y', $order->date) }}</div>
                            <div style="margin-bottom: 8px; font-size:14px;"><span
                                    style="color:#000;font-weight:700;">{{ translate('Payment Status') }}:</span>
                                {{ $order->payment_status ?? '' }}</div>
                            <div style="margin-bottom: 8px; font-size:14px;"><span
                                    style="color:#000;font-weight:700;">{{ translate('Payment Method') }}:</span>
                                {{ translate(ucfirst(str_replace('_', ' ', $order->payment_type))) }}</div>

                            <div style="margin-bottom: 8px; font-size:14px;"><span
                                    style="color:#000;font-weight:700;">{{ translate('Parcel ID') }}:</span>
                                @if ($order->steadfast_id !== null)
                                    {{ $order->steadfast_id }}(Steadfast)
                                @elseif ($order->pathao_id !== null)
                                    {{ $order->pathao_id }}(Pathao)
                                @else
                                    'N/A'
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Products Table -->
        <div style="padding: 0 2rem 2rem 2rem;">
            <table style="width: 100%; border: 1px solid black; border-collapse: collapse;">
                <thead>
                    <tr style="background: #eceff4; text-align: center;">
                        <th width="5%" style="border: 1px solid black; padding: 8px; font-weight: 400; color:#000;">
                            SL</th>
                        <th width="15%" style="border: 1px solid black; padding: 8px; font-weight: 400; color:#000;">
                            Image</th>
                        <th width="35%" style="border: 1px solid black; padding: 8px; font-weight: 400; color:#000;">
                            {{ translate('Product Name') }}</th>
                        <th width="15%" style="border: 1px solid black; padding: 8px; font-weight: 400; color:#000;">
                            {{ translate('Price') }}</th>
                        <th width="15%" style="border: 1px solid black; padding: 8px; font-weight: 400; color:#000;">
                            {{ translate('Discounted Price') }}</th>
                        <th width="10%" style="border: 1px solid black; padding: 8px; font-weight: 400; color:#000;">
                            {{ translate('Qty') }}</th>
                        <th width="15%" style="border: 1px solid black; padding: 8px; font-weight: 400; color:#000;">
                            {{ translate('Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->orderDetails as $key => $orderDetail)
                        @if ($orderDetail->product != null)
                            @php
                                $first_stock = $orderDetail->product->stocks->first();
                                $product_stock = $first_stock ? json_decode($first_stock, true) : null;
                                $product_stock_all = $orderDetail->product->stocks->map(function ($stock) {
                                    return json_decode($stock, true);
                                });

                                $matchingStock = collect($product_stock_all)->firstWhere(
                                    'variant',
                                    $orderDetail->variation,
                                );
                            @endphp
                            <tr>
                                <td style="border: 1px solid black; padding: 8px; text-align: center;">
                                    {{ $key + 1 }}</td>
                                <td style="border: 1px solid black; padding: 8px; text-align: center;">
                                    <img src="{{ uploaded_asset($orderDetail->product->thumbnail_img) ?? '' }}"
                                        alt="" style="width: 50px; height: 50px; object-fit: contain;">
                                </td>
                                <td style="border: 1px solid black; padding: 8px;">
                                    {{ $orderDetail->product->name }}
                                    @if ($orderDetail->variation != null)
                                        ({{ $orderDetail->variation }})
                                    @endif
                                    <br>
                                    <small>
                                        {{ translate('SKU') }}: {{ $product_stock['sku'] ?? 'N/A' }}
                                    </small>
                                </td>
                                <td style="border: 1px solid black; padding: 8px; text-align: center;">
                                    {{ single_price($matchingStock['price'] ?? 0) }}</td>
                                <td style="border: 1px solid black; padding: 8px; text-align: center;">
                                    {{ single_price($orderDetail->price / $orderDetail->quantity) }}</td>
                                <td style="border: 1px solid black; padding: 8px; text-align: center;">
                                    {{ $orderDetail->quantity }}</td>
                                <td style="border: 1px solid black; padding: 8px; text-align: center;">
                                    {{ single_price($orderDetail->price + $orderDetail->tax) }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Summary Section -->
        <div style="padding: 0 2.5rem 2rem 2.5rem;">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 60%; vertical-align: top;">
                        <!-- QR Code -->
                        @php
                        $removedXML = '<?xml version="1.0" encoding="UTF-8"@endphp'; ?>';
                        ?>
                        <div style="text-align: center;">
                            {!! str_replace($removedXML, '', QrCode::size(100)->generate($order->code)) !!}
                        </div>
                    </td>
                    <td style="width: 40%; vertical-align: top;">
                        <!-- Price Summary -->
                        <table style="width: 100%; border: 1px solid black; border-collapse: collapse;">
                            <tr>
                                <th style="border: 1px solid black; padding: 8px; text-align: left;">
                                    {{ translate('Sub Total') }}</th>
                                <td style="border: 1px solid black; padding: 8px; text-align: right;">
                                    {{ single_price($order->orderDetails->sum('price')) }}</td>
                            </tr>

                            <tr style="display:none;">
                                <th style="border: 1px solid black; padding: 8px; text-align: left;">
                                    {{ translate('Coupon Discount') }}</th>
                                <td style="border: 1px solid black; padding: 8px; text-align: right;">
                                    {{ single_price($order->coupon_discount) }}</td>
                            </tr>
                            <tr>
                                <th style="border: 1px solid black; padding: 8px; text-align: left;">
                                    {{ translate('Total Discount') }}</th>
                                <td style="border: 1px solid black; padding: 8px; text-align: right;">
                                    {{ single_price(
                                        $order->orderDetails->sum(function ($orderDetail) {
                                            $original_price =
                                                optional($orderDetail->product->stocks->where('variant', $orderDetail->variation)->first())->price ?? 0;
                                            $discounted_price = $orderDetail->price / max($orderDetail->quantity, 1);
                                            return ($original_price - $discounted_price) * $orderDetail->quantity;
                                        }),
                                    ) }}
                                </td>
                            </tr>
                            <tr>
                                <th style="border: 1px solid black; padding: 8px; text-align: left;">
                                    {{ translate('Shipping Cost') }}</th>
                                <td style="border: 1px solid black; padding: 8px; text-align: right;">
                                    {{ single_price($order->orderDetails->first()->shipping_cost ?? 0) }}</td>
                            </tr>

                            @if ($order->partial_paid_amount > 0.0)
                                <tr>
                                    <th style="border: 1px solid black; padding: 8px; text-align: left;">
                                        {{ translate('Receive Amount') }} :</th>
                                    <td style="border: 1px solid black; padding: 8px; text-align: right;">
                                        {{ single_price($order->partial_paid_amount) }}</td>
                                </tr>
                            @endif
                            <tr>
                                <th
                                    style="border: 1px solid black; padding: 8px; text-align: left; font-weight: 400; color:#000;">
                                    {{ translate('Grand Total') }}</th>
                                <td
                                    style="border: 1px solid black; padding: 8px; text-align: right; font-weight: 400; color:#000;">
                                    {{ single_price($order->grand_total) }}</td>
                            </tr>
                             @if ($order->partial_paid_amount > 0.0)
                            <tr>
                                <th style="border: 1px solid black; padding: 8px; text-align: left; font-weight: 400; color:#000;">
                                    {{ translate('Total Due') }}</th>
                                <td style="border: 1px solid black; padding: 8px; text-align: right; font-weight: 400; color:#000;">
                                    {{ single_price($order->due_amount) }}</td>
                            </tr>
                            @endif
                        </table>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <div class="invoice-footer">
        <table>
            <tr>
                <td style="width: 40%; vertical-align: top; padding: 20px; text-align: center;">
                    <p style="margin:0; font-size: 25px;">Thank You<br>For Being With Us.</p>
                </td>
                <td style="width: 1px; border-right: 1px solid black;"></td>

                <td style="width: 59%; vertical-align: top; padding: 20px;">
                    <div style="text-align: center; margin-bottom: 10px;">
                        <span style="font-size: 18px; display: block;">Terms & Conditions:</span>
                    </div>
                    <ul style="margin:5px 0; padding-left: 15px; font-size: 14px; text-align: left;">
                        <li>Check your products properly in front of the delivery person.</li>
                        <li>Exchange is available within 3 days of purchase/receiving the parcel.</li>
                        <li>Items must be unused, unworn, unwashed and in original packaging with tags for exchange.
                        </li>
                        <li>No refund after purchase is done/parcel is received.</li>
                        <li>Parcels may only be returned at the time of delivery.</li>
                        <li>Contact our support team via WhatsApp/phone to request an exchange.</li>
                    </ul>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
