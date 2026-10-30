<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Income Voucher #{{ $income->voucher_no }}</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 20px; }
        .voucher { max-width: 800px; margin: auto; border: 1px solid #ddd; padding: 25px; }
        .header { text-align: center; border-bottom: 2px solid #000; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 8px; }
        th { background: #f2f2f2; }
        .text-right { text-align: right; }
        .signature { margin-top: 40px; display: flex; justify-content: space-between; }
        .sign { width: 200px; border-top: 1px solid #000; padding-top: 5px; }
        @media print { body { margin: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
<div class="voucher">
    <div class="header">
        <h2>INCOME VOUCHER</h2>
        <p>Voucher No: <strong>{{ $income->voucher_no }}</strong></p>
    </div>
    <table>
        <tr><td><strong>Date:</strong> {{ $income->date->format('d-m-Y') }}</td><td><strong>Cost Center:</strong> {{ $income->costCenter->name ?? '-' }}</td></tr>
        <tr><td colspan="2"><strong>Deposit To:</strong> {{ $income->receivedAccount->name ?? '-' }}</td></tr>
        <tr><td colspan="2"><strong>Prepared By:</strong> {{ $income->creator->name ?? '-' }}</td></tr>
    </table>
    <table>
        <thead><tr><th>#</th><th>Income Account</th><th class="text-right">Amount</th><th>Note</th></tr></thead>
        <tbody>
            @foreach($income->details as $i => $detail)
            <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $detail->incomeAccount->name ?? '-' }}</td>
                <td class="text-right">{{ number_format($detail->amount, 2) }} ৳</td>
                <td>{{ $detail->note }}</td>
            </tr>
            @endforeach
            <tr><th colspan="2" class="text-right">Total</th><th class="text-right">{{ number_format($income->total_amount, 2) }} ৳</th><th></th></tr>
        </tbody>
    </table>
    @if($income->note)
    <p><strong>Note:</strong> {{ $income->note }}</p>
    @endif
    <div class="signature">
        <div class="sign">Prepared By</div>
        <div class="sign">Authorised By</div>
    </div>
    <div class="no-print" style="text-align:center; margin-top:20px;">
        <button onclick="window.print()" style="padding:10px 20px;">Print Voucher</button>
    </div>
</div>
</body>
</html>