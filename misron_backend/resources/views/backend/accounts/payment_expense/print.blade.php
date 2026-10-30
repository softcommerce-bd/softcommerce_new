<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ translate('Expense Voucher') }} #{{ $expensePayment->voucher_no }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; }
        .voucher-container { max-width: 800px; margin: auto; border: 1px solid #ddd; padding: 25px; }
        .header { text-align: center; border-bottom: 2px solid #000; margin-bottom: 20px; padding-bottom: 10px; }
        .header h2 { margin: 0; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 5px 10px; }
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .details-table th, .details-table td { border: 1px solid #000; padding: 8px; text-align: left; }
        .details-table th { background-color: #f2f2f2; }
        .text-right { text-align: right; }
        .footer { margin-top: 40px; display: flex; justify-content: space-between; }
        .signature { width: 200px; text-align: center; border-top: 1px solid #000; padding-top: 5px; }
        @media print { body { margin: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="voucher-container">
        <div class="header">
            <h2>{{ translate('EXPENSE VOUCHER') }}</h2>
            <p>{{ translate('Voucher No') }}: <strong>{{ $expensePayment->voucher_no }}</strong></p>
        </div>

        <table class="info-table">
            <tr>
                <td><strong>{{ translate('Date') }}:</strong> {{ $expensePayment->date->format('d-m-Y') }}</td>
                <td><strong>{{ translate('Cost Center') }}:</strong> {{ $expensePayment->costCenter->name ?? '-' }}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>{{ translate('Pay From') }}:</strong> {{ $expensePayment->paymentAccount->name ?? '-' }} ({{ $expensePayment->paymentAccount->code ?? '' }})</td>
            </tr>
            <tr>
                <td colspan="2"><strong>{{ translate('Prepared By') }}:</strong> {{ $expensePayment->creator->name ?? '-' }}</td>
            </tr>
        </table>

        <table class="details-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ translate('Expense Account') }}</th>
                    <th class="text-right">{{ translate('Amount (৳)') }}</th>
                    <th>{{ translate('Note') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expensePayment->details as $index => $detail)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $detail->expenseAccount->name ?? 'N/A' }}</td>
                    <td class="text-right">{{ number_format($detail->amount, 2) }}</td>
                    <td>{{ $detail->note ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" class="text-right"><strong>{{ translate('Total') }}</strong></th>
                    <th class="text-right"><strong>{{ number_format($expensePayment->total_amount, 2) }} ৳</strong></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>

        @if($expensePayment->note)
        <p><strong>{{ translate('Note') }}:</strong> {{ $expensePayment->note }}</p>
        @endif

        <div class="footer">
            <div class="signature">{{ translate('Prepared By') }}</div>
            <div class="signature">{{ translate('Authorised By') }}</div>
        </div>

        <div class="no-print" style="text-align: center; margin-top: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px;">{{ translate('Print Voucher') }}</button>
        </div>
    </div>
</body>
</html>