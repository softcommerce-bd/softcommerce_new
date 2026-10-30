<!DOCTYPE html>
<html>
<head>
    <title>{{ translate('Voucher') }} - {{ $transaction->voucher_no }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .voucher-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
        }
        .voucher-title {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
        }
        .voucher-details {
            width: 100%;
            margin-bottom: 20px;
        }
        .voucher-details td {
            padding: 5px;
        }
        .voucher-details td:first-child {
            font-weight: bold;
            width: 150px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 12px;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
        button {
            margin-top: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="voucher-container">
        <div class="header">
            <div class="company-name">{{ translate('Company Name') }}</div>
            <div>{{ translate('Your Company Address Here') }}</div>
            <div>{{ translate('Phone: +880XXXXXXXXX') }}</div>
        </div>
        
        <div class="voucher-title">
            {{ translate('BALANCE TRANSFER VOUCHER') }}
        </div>
        
        <table class="voucher-details">
            <tr>
                <td>{{ translate('Voucher No') }}:</td>
                <td><strong>{{ $transaction->voucher_no }}</strong></td>
                <td>{{ translate('Date') }}:</td>
                <td>{{ $transaction->date->format('d-m-Y') }}</td>
            </tr>
            <tr>
                <td>{{ translate('Transaction Type') }}:</td>
                <td colspan="3">
                    @if($transaction->transaction_type == 'withdraw')
                        {{ translate('Withdraw') }}
                    @elseif($transaction->transaction_type == 'deposit')
                        {{ translate('Deposit') }}
                    @elseif($transaction->transaction_type == 'transfer')
                        {{ translate('Transfer') }}
                    @elseif($transaction->transaction_type == 'cashout')
                        {{ translate('Cash Out') }}
                    @endif
                </td>
            </tr>
            <tr>
                <td>{{ translate('From Account') }}:</td>
                <td>{{ $transaction->fromAccount->name ?? '-' }}</td>
                <td>{{ translate('To Account') }}:</td>
                <td>{{ $transaction->toAccount->name ?? '-' }}</td>
            </tr>
            <tr>
                <td>{{ translate('Amount') }}:</td>
                <td colspan="3"><strong>{{ number_format($transaction->amount, 2) }} ৳</strong></td>
            </tr>
            @if($transaction->charge > 0)
            <tr>
                <td>{{ translate('Charge') }}:</td>
                <td colspan="3">{{ number_format($transaction->charge, 2) }} ৳</td>
            </tr>
            <tr>
                <td>{{ translate('Total Amount') }}:</td>
                <td colspan="3"><strong>{{ number_format($transaction->total_amount, 2) }} ৳</strong></td>
            </tr>
            @endif
            @if($transaction->reference_no)
            <tr>
                <td>{{ translate('Reference No') }}:</td>
                <td colspan="3">{{ $transaction->reference_no }}</td>
            </tr>
            @endif
            @if($transaction->costCenter)
            <tr>
                <td>{{ translate('Cost Center') }}:</td>
                <td colspan="3">{{ $transaction->costCenter->name }}</td>
            </tr>
            @endif
            @if($transaction->note)
            <tr>
                <td>{{ translate('Note') }}:</td>
                <td colspan="3">{{ $transaction->note }}</td>
            </tr>
            @endif
            <tr>
                <td>{{ translate('Created By') }}:</td>
                <td colspan="3">{{ $transaction->creator->name ?? '-' }}</td>
            </tr>
        </table>
        
        <div style="margin-top: 40px;">
            <div style="float: left; text-align: center; width: 45%;">
                <hr style="width: 80%;">
                <small>{{ translate('Authorized Signature') }}</small>
            </div>
            <div style="float: right; text-align: center; width: 45%;">
                <hr style="width: 80%;">
                <small>{{ translate('Receiver Signature') }}</small>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <div class="footer">
            {{ translate('This is a computer generated voucher') }}
        </div>
    </div>
    
    <div class="no-print" style="text-align: center;">
        <button onclick="window.print()">{{ translate('Print') }}</button>
        <button onclick="window.close()">{{ translate('Close') }}</button>
    </div>
</body>
</html>