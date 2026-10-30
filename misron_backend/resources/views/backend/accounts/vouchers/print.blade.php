<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ translate('Voucher') }} - {{ $voucher->voucher_no }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: dejavusans, freeserif, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            background: #fff;
            padding: 10px;
        }
        
        .voucher-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
        }
        
        .voucher-header {
            width: 100%;
            border-bottom: 3px solid #4e73df;
            background: #667eea;
            color: white;
        }
        
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .header-table td {
            padding: 15px;
        }
        
        .company-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .company-info {
            font-size: 10px;
            line-height: 1.3;
        }
        
        .voucher-title {
            text-align: center;
            padding: 12px;
            background: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            font-size: 18px;
            font-weight: bold;
            color: #4e73df;
        }
        
        .info-section {
            width: 100%;
            border-collapse: collapse;
            background: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .info-section td {
            padding: 8px 12px;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .info-label {
            width: 120px;
            font-weight: bold;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .items-table th {
            background: #4e73df;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            font-size: 11px;
            border: 1px solid #4e73df;
        }
        
        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e3e6f0;
            border-left: 1px solid #e3e6f0;
            border-right: 1px solid #e3e6f0;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .amount-summary {
            width: 100%;
            margin: 15px 0;
            border-top: 2px solid #4e73df;
            background: #f8f9fc;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .summary-table td {
            padding: 8px 12px;
        }
        
        .summary-label {
            text-align: right;
            font-weight: bold;
            width: 80%;
        }
        
        .summary-value {
            text-align: right;
            font-weight: bold;
            width: 20%;
        }
        
        .total-row {
            border-top: 2px solid #333;
            font-size: 13px;
        }
        
        .signature-section {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        
        .signature-section td {
            text-align: center;
            padding: 0 10px;
            width: 33%;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            padding-top: 8px;
            margin-top: 30px;
        }
        
        .voucher-footer {
            text-align: center;
            padding: 12px;
            border-top: 1px solid #e3e6f0;
            font-size: 9px;
            color: #666;
            background: #f8f9fc;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            background: #1cc88a;
            color: white;
            font-size: 10px;
            font-weight: bold;
        }
        
        .action-buttons {
            text-align: center;
            padding: 15px;
            background: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .btn {
            display: inline-block;
            padding: 6px 12px;
            margin: 0 3px;
            background: #4e73df;
            color: white;
            text-decoration: none;
            font-size: 11px;
            border: none;
            cursor: pointer;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="voucher-container">
        <div class="action-buttons no-print">
            <button onclick="window.print();" class="btn">🖨️ {{ translate('Print') }}</button>
            <button onclick="window.close();" class="btn">✕ {{ translate('Close') }}</button>
        </div>
        
        <table class="voucher-header">
            <tr>
                <td style="padding: 15px;">
                    <div class="company-name">{{ get_setting('site_name', 'Your Company Name') }}</div>
                    <div class="company-info">
                        {{ get_setting('site_address', 'Company Address') }}<br>
                        {{ translate('Phone') }}: {{ get_setting('site_phone', 'N/A') }} | 
                        {{ translate('Email') }}: {{ get_setting('site_email', 'N/A') }}
                    </div>
                </td>
            </tr>
        </table>
        
        <div class="voucher-title">
            @if($voucher->type == 'CP')
                {{ translate('CASH PAYMENT VOUCHER') }}
            @elseif($voucher->type == 'BP')
                {{ translate('BANK PAYMENT VOUCHER') }}
            @elseif($voucher->type == 'CR')
                {{ translate('CASH RECEIPT VOUCHER') }}
            @elseif($voucher->type == 'BR')
                {{ translate('BANK RECEIPT VOUCHER') }}
            @elseif($voucher->type == 'JV')
                {{ translate('JOURNAL VOUCHER') }}
            @elseif($voucher->type == 'CV')
                {{ translate('CONTRA VOUCHER') }}
            @endif
        </div>
        
        <table class="info-section">
            <tr>
                <td class="info-label">{{ translate('Voucher No') }}:</td>
                <td class="info-value"><strong>{{ $voucher->voucher_no }}</strong></td>
            </tr>
            <tr>
                <td class="info-label">{{ translate('Date') }}:</td>
                <td class="info-value">{{ date('d-m-Y', strtotime($voucher->date)) }}</td>
            </tr>
            <tr>
                <td class="info-label">{{ translate('Voucher Type') }}:</td>
                <td class="info-value">
                    <span class="badge">
                        @if($voucher->type == 'CP')
                            {{ translate('Cash Payment') }}
                        @elseif($voucher->type == 'BP')
                            {{ translate('Bank Payment') }}
                        @elseif($voucher->type == 'CR')
                            {{ translate('Cash Receipt') }}
                        @elseif($voucher->type == 'BR')
                            {{ translate('Bank Receipt') }}
                        @elseif($voucher->type == 'JV')
                            {{ translate('Journal Voucher') }}
                        @elseif($voucher->type == 'CV')
                            {{ translate('Contra Voucher') }}
                        @endif
                    </span>
                </td>
            </tr>
            @if($voucher->description)
            <tr>
                <td class="info-label">{{ translate('Narration') }}:</td>
                <td class="info-value">{{ $voucher->description }}</td>
            </tr>
            @endif
        </table>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="35%">{{ translate('Account Head') }}</th>
                    <th width="25%">{{ translate('Account Code') }}</th>
                    <th width="17%" class="text-right">{{ translate('Debit') }}</th>
                    <th width="18%" class="text-right">{{ translate('Credit') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalDebit = 0;
                    $totalCredit = 0;
                @endphp
                @foreach($voucher->details as $index => $detail)
                    @php
                        $totalDebit += $detail->debit ?? 0;
                        $totalCredit += $detail->credit ?? 0;
                    @endphp
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $detail->account->name ?? 'N/A' }}</strong>
                            @if($detail->description)
                                <br><small>{{ $detail->description }}</small>
                            @endif
                        </td>
                        <td>{{ $detail->account->code ?? 'N/A' }}</td>
                        <td class="text-right">
                            @if(($detail->debit ?? 0) > 0)
                                <strong>{{ number_format($detail->debit, 2) }} BDT</strong>
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-right">
                            @if(($detail->credit ?? 0) > 0)
                                <strong>{{ number_format($detail->credit, 2) }} BDT</strong>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        
        <table class="amount-summary">
            <tr>
                <td class="summary-label">{{ translate('Total Debit') }}:</td>
                <td class="summary-value">{{ number_format($totalDebit, 2) }} BDT</td>
            </tr>
            <tr>
                <td class="summary-label">{{ translate('Total Credit') }}:</td>
                <td class="summary-value">{{ number_format($totalCredit, 2) }} BDT</td>
            </tr>
            @if(round($totalDebit, 2) == round($totalCredit, 2))
            <tr class="total-row">
                <td class="summary-label"><strong>{{ translate('Total Amount') }}:</strong></td>
                <td class="summary-value"><strong>{{ number_format($totalDebit, 2) }} BDT</strong></td>
            </tr>
            @endif
        </table>
        
        <table style="width: 100%; margin: 10px 0;">
            <tr>
                <td style="padding: 8px;">
                    <strong>{{ translate('Amount in Words') }}:</strong> 
                    {{ convertNumberToWords(round($totalDebit, 2)) }} {{ translate('Taka Only') }}
                </td>
            </tr>
        </table>
        
        <table class="signature-section">
            <tr>
                <td>
                    <div class="signature-line">{{ translate('Prepared By') }}</div>
                    <div style="margin-top: 5px;">
                        @if($voucher->creator)
                            {{ $voucher->creator->name }}
                        @else
                            ___________________
                        @endif
                    </div>
                </td>
                <td>
                    <div class="signature-line">{{ translate('Checked By') }}</div>
                    <div style="margin-top: 5px;">___________________</div>
                </td>
                <td>
                    <div class="signature-line">{{ translate('Approved By') }}</div>
                    <div style="margin-top: 5px;">___________________</div>
                </td>
            </tr>
        </table>
        
        <div class="voucher-footer">
            <div>{{ translate('This is a computer generated voucher') }}</div>
            <div>{{ translate('Print Date') }}: {{ date('d-m-Y h:i A') }}</div>
            @if(in_array($voucher->type, ['CP', 'BP']))
                <div style="margin-top: 5px; color: #e74a3b;">
                    <strong>{{ translate('Note') }}:</strong> {{ translate('This payment is made against proper authorization') }}
                </div>
            @elseif(in_array($voucher->type, ['CR', 'BR']))
                <div style="margin-top: 5px; color: #1cc88a;">
                    <strong>{{ translate('Note') }}:</strong> {{ translate('Received with thanks') }}
                </div>
            @endif
        </div>
    </div>
</body>
</html>