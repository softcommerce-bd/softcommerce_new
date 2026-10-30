<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Day Closing Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'freeserif', 'DejaVu Sans', sans-serif; font-size: 10px; padding: 15px; color: #1a1a1a; }
        
        /* Header */
        .header { text-align: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #1F4E79; }
        .header .company { font-size: 18px; font-weight: bold; color: #1F4E79; text-transform: uppercase; margin-bottom: 5px; }
        .header .title { font-size: 15px; font-weight: bold; color: #2E75B6; margin-bottom: 5px; text-transform: uppercase; }
        .header .info { font-size: 11px; color: #555; }
        
        /* Summary Cards */
        .summary-row { margin-bottom: 10px; }
        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .summary-table td { 
            width: 25%; padding: 8px 10px; text-align: center; vertical-align: middle;
            border: 1px solid #ddd; font-size: 10px;
        }
        .summary-table .card-blue { background: #1F4E79; color: #fff; }
        .summary-table .card-green { background: #28a745; color: #fff; }
        .summary-table .card-red { background: #dc3545; color: #fff; }
        .summary-table .card-teal { background: #17a2b8; color: #fff; }
        .summary-table .card-yellow { background: #ffc107; color: #333; }
        .summary-table .card-gray { background: #6c757d; color: #fff; }
        .summary-table .card-dark { background: #343a40; color: #fff; }
        .summary-table .card-orange { background: #fd7e14; color: #fff; }
        
        .summary-table .card-label { font-size: 8px; text-transform: uppercase; display: block; margin-bottom: 3px; opacity: 0.9; }
        .summary-table .card-amount { font-size: 14px; font-weight: bold; }
        
        /* Section */
        .section { margin-bottom: 12px; }
        .section-title { background: #e8e8e8; padding: 6px 10px; font-weight: bold; font-size: 11px; border: 1px solid #ccc; text-transform: uppercase; }
        
        /* Tables */
        table.data { width: 100%; border-collapse: collapse; font-size: 9px; margin-top: 8px; }
        table.data th { background: #1F4E79; color: #fff; padding: 5px 6px; border: 1px solid #1F4E79; font-size: 9px; }
        table.data td { padding: 3px 5px; border: 1px solid #ddd; }
        table.data tr:nth-child(even) td { background: #fafafa; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-primary { color: #1F4E79; }
        .text-danger { color: #C00000; }
        .font-bold { font-weight: bold; }
        .badge { display: inline-block; padding: 1px 5px; background: #e0e0e0; font-size: 7px; }
        
        .footer { margin-top: 15px; padding-top: 8px; border-top: 1px solid #ccc; font-size: 8px; color: #888; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company">{{ get_setting('site_name', env('APP_NAME', 'Company Name')) }}</div>
        <div class="title">DAY CLOSING REPORT</div>
        <p class="info">Date: <strong>{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</strong> | Total Transactions: <strong>{{ $transactions->count() }}</strong></p>
    </div>

    <!-- Day Closing Summary -->
    <div class="summary-row">
        <table class="summary-table">
            <tr>
                <td class="card-blue">
                    <span class="card-label">Opening</span>
                    <span class="card-amount">{{ number_format($openingBalance, 2) }}</span>
                </td>
                <td class="card-green">
                    <span class="card-label">Deposit</span>
                    <span class="card-amount">{{ number_format($deposit, 2) }}</span>
                </td>
                <td class="card-red">
                    <span class="card-label">Withdraw</span>
                    <span class="card-amount">{{ number_format($withdraw, 2) }}</span>
                </td>
                <td class="card-teal">
                    <span class="card-label">Closing</span>
                    <span class="card-amount">{{ number_format($closingBalance, 2) }}</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- All Transactions -->
    <div class="section">
        <div class="section-title">All Transactions</div>
        <table class="data">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Time</th>
                    <th>Reference</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th class="text-right">Debit (৳)</th>
                    <th class="text-right">Credit (৳)</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $key => $trans)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($trans->transaction_date)->format('H:i') }}</td>
                    <td>{{ $trans->reference_no }}</td>
                    <td>{{ $trans->account->name ?? 'N/A' }}</td>
                    <td><span class="badge">{{ $trans->transaction_type }}</span></td>
                    <td class="text-right text-primary">{{ $trans->debit > 0 ? number_format($trans->debit, 2) : '-' }}</td>
                    <td class="text-right text-danger">{{ $trans->credit > 0 ? number_format($trans->credit, 2) : '-' }}</td>
                    <td>{{ Str::limit($trans->description, 35) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center">No transactions found</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="font-weight: bold; background: #f0f4f8;">
                    <td colspan="5" class="text-right">Total:</td>
                    <td class="text-right text-primary">{{ number_format($totalDebit, 2) }}</td>
                    <td class="text-right text-danger">{{ number_format($totalCredit, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Footer -->
    <div class="footer">
        <table style="width: 100%;">
            <tr>
                <td>Print Date: {{ now()->format('d M Y, h:i A') }}</td>
                <td style="text-align: right;">Generated by: {{ auth()->user()->name ?? 'System' }}</td>
            </tr>
        </table>
    </div>
</body>
</html>