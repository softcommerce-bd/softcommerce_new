<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Purchase Order - {{ $order->po_number ?? 'N/A' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
            color: #000;
            line-height: 1.3;
        }

        .company-info {
            margin-bottom: 5px;
        }

        .company-info img {
            width: 150px;
            height: auto;
        }

        .purchase-order-title {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .from-to-container {
            margin-bottom: 15px;
        }

        .from-section,
        .to-section {
            margin-bottom: 10px;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 10px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 4px 6px;
            text-align: left;
        }

        th {
            background-color: #f0f0f0;
            text-align: center;
            font-weight: bold;
        }

        .totals-section {
            width: 100%;
            margin-top: 10px;
        }

        .totals-table {
            width: 200px;
            margin-left: auto;
            border: none;
        }

        .totals-table td {
            border: none;
            padding: 2px 5px;
        }

        .totals-table .total-row {
            border-top: 1px solid #000;
            font-weight: bold;
        }

        .notes {
            margin-top: 15px;
            font-size: 11px;
            line-height: 1.2;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .amount-column {
            text-align: right;
        }

        .mrp-column,
        .tp-column {
            text-align: right;
        }

        .qty-column {
            text-align: center;
        }

        .po-info {
            margin-bottom: 10px;
        }

        .po-info strong {
            display: inline-block;
            width: 120px;
        }

        .reference-box {
            border: 1px solid #000;
            padding: 5px;
            margin-bottom: 10px;
            width: 200px;
            margin-left: auto;
        }

        .reference-box table {
            width: 100%;
            border: none;
        }

        .reference-box td {
            border: none;
            padding: 2px 4px;
        }

        .reference-box .label {
            font-weight: normal;
        }

        .reference-box .value {
            font-weight: bold;
        }

        .from-to-text {
            font-weight: normal;
            font-size: 11px;
        }
    </style>
</head>

<body>

    {{-- Company Header --}}
    <div class="company-info">
        <table width="100%" style="border: none;">
            <tr>
                <td style="width: 50%; border: none; vertical-align: top;">
                    @php $logo = get_setting('system_logo_black'); @endphp
                    @if ($logo)
                        <img src="{{ uploaded_asset($logo) }}" alt="Logo" style="width: 150px; height: auto;">
                    @else
                        <img src="{{ static_asset('assets/img/logo.png') }}" alt="Default Logo"
                            style="width: 150px; height: auto;">
                    @endif
                </td>
                <td style="text-align: right; border: none; vertical-align: top;">
                    <div class="reference-box">
                        <table>
                            <tr>
                                <td class="label">Date:</td>
                                <td class="value">
                                    {{ $order->created_at ? $order->created_at->format('d/m/Y') : date('d/m/Y') }}</td>
                            </tr>
                            <tr>
                                <td class="label">PO No:</td>
                                <td class="value">{{ $order->reference_no ?? 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Purchase Order Info --}}
    <div class="po-info">
        <div class="purchase-order-title">PURCHASE ORDER</div>
    </div>

    {{-- From and To Sections --}}
    <div class="from-to-container">
        <div class="from-section">
            <div class="section-title">Purchase Order From</div>
            <div class="from-to-text">
                <strong>{{ get_setting('site_name') ?? '' }}</strong><br>
                {{ get_setting('contact_address',null,App::getLocale()) ?? '' }}
            </div>
        </div>
        <div class="to-section">
            <div class="section-title">Purchase Order To</div>
            <div class="from-to-text">
                <strong>{{ $order->supplier?->business_name ?? 'N/A' }}</strong><br>
                {{ $order->supplier?->address ?? 'N/A' }}<br>

            </div>
        </div>
    </div>
    {{-- Items Table --}}
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">S/L</th>
                <th style="width: 8%;">CODE</th>
                <th style="width: 45%;">Product name</th>
                <th style="width: 8%;">MRP</th>
                <th style="width: 8%;">TP</th>
                <th style="width: 6%;">Qty</th>
                <th style="width: 13%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @if (isset($order->details) && count($order->details) > 0)
                @php
                    $totalAmount = 0;
                    $adjustments = $order->adjustments ?? 0;
                @endphp

                @foreach ($order->details as $index => $detail)
                    @php
                        // Calculate item amount based on TP and quantity
                        $qty = (!empty($detail->purchase_quantity) && $detail->purchase_quantity > 0
                                    ? $detail->purchase_quantity
                                    : 0);
                        $itemAmount = ($detail->buying_unit_cost ?? 0) * ($qty ?? 0);
                        $totalAmount += $itemAmount;
                    @endphp
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $detail->code ?? ($detail->product_id ?? 'N/A') }}</td>
                        <td>{{ $detail->product_name ?? 'N/A' }}</td>
                        <td class="mrp-column">{{ number_format($detail?->product->unit_price ?? 0, 0) }}</td>
                        <td class="tp-column">{{ number_format($detail->buying_unit_cost ?? 0, 0) }}</td>
                        <td class="qty-column">{{ $qty ?? 'N/A' }}</td>
                        <td class="amount-column">{{ $itemAmount ?? 0 }}</td>
                    </tr>
                @endforeach

                {{-- Summary Rows --}}
                <tr>
                    <td colspan="3" style="border: none;"></td>
                    <td colspan="3" style="border: none; text-align: right; padding-right: 10px;">Total</td>
                    <td class="amount-column"><strong>{{ number_format($totalAmount, 2) }}</strong></td>
                </tr>
                <tr>
                    <td colspan="3" style="border: none;"></td>
                    <td colspan="3" style="border: none; text-align: right; padding-right: 10px;">Adjustments</td>
                    <td class="amount-column">{{ number_format($adjustments, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="3" style="border: none;"></td>
                    <td colspan="3" style="border: none; text-align: right; padding-right: 10px;">Gross Total</td>
                    <td class="amount-column"><strong>{{ number_format($totalAmount + $adjustments, 2) }}</strong></td>
                </tr>
            @else
                @foreach ($items as $index => $item)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $item['code'] }}</td>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['size'] }}</td>
                        <td class="mrp-column">{{ $item['mrp'] }}</td>
                        <td class="tp-column">{{ $item['tp'] }}</td>
                        <td class="qty-column">{{ $item['qty'] }}</td>
                        <td class="amount-column">{{ $item['amount'] }}</td>
                    </tr>
                @endforeach

                {{-- Static summary --}}
                <tr>
                    <td colspan="4" style="border: none;"></td>
                    <td colspan="3" style="border: none; text-align: right; padding-right: 10px;">Total</td>
                    <td class="amount-column"><strong>47823.00</strong></td>
                </tr>
                <tr>
                    <td colspan="4" style="border: none;"></td>
                    <td colspan="3" style="border: none; text-align: right; padding-right: 10px;">Adjustments</td>
                    <td class="amount-column"></td>
                </tr>
                <tr>
                    <td colspan="4" style="border: none;"></td>
                    <td colspan="3" style="border: none; text-align: right; padding-right: 10px;">Gross Total</td>
                    <td class="amount-column"><strong>47823.00</strong></td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Notes Section --}}
    <div class="notes">
        <p>*** Damaged, short dated, and expired products will be returned.</p>
        <p>*** This is a computer generated PO; no signature required.</p>
    </div>
</body>

</html>
