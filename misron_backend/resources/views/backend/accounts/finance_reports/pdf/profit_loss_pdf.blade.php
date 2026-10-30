{{-- resources/views/backend/accounts/finance_reports/pdf/profit_loss_pdf.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profit Loss Statement</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'freeserif', 'DejaVu Sans', 'Arial Unicode MS', sans-serif;
            font-size: 14px;
            color: #1a1a1a;
            padding: 25px;
            line-height: 1.5;
        }
        
        /* Report Header */
        .report-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 3px solid #1a1a1a;
        }
        
        .report-header .company-name {
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            color: #1F4E79;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }
        
        .report-header .report-title {
            font-size: 18px;
            font-weight: bold;
            color: #2E75B6;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .report-header .period {
            font-size: 13px;
            color: #555;
        }
        
        .report-header .period strong {
            color: #333;
        }
        
        /* Section */
        .section {
            margin-bottom: 18px;
        }
        
        .section-title {
            background: linear-gradient(135deg, #1F4E79 0%, #2E75B6 100%);
            padding: 10px 15px;
            font-weight: bold;
            font-size: 14px;
            color: #ffffff;
            border: none;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* COGS Section Title - Different Color */
        .section-title.cogs {
            background: linear-gradient(135deg, #C55A11 0%, #E67E22 100%);
        }
        
        /* Expenses Section Title - Different Color */
        .section-title.expenses {
            background: linear-gradient(135deg, #C00000 0%, #E74C3C 100%);
        }
        
        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        table thead th {
            padding: 0;
        }
        
        table tbody td {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            vertical-align: middle;
        }
        
        table tbody tr:nth-child(even) td {
            background-color: #fafafa;
        }
        
        table tbody tr:nth-child(odd) td {
            background-color: #ffffff;
        }
        
        /* Total Row */
        .total-row td {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #333;
            background: #f0f4f8 !important;
            padding: 10px 15px;
            color: #1a1a1a;
        }
        
        /* Gross Profit Row */
        .gross-profit-row {
            background: #e8f5e9;
            padding: 12px 15px;
            border: 1px solid #a5d6a7;
            font-size: 15px;
            font-weight: bold;
        }
        
        .gross-profit-row td {
            padding: 10px 15px;
            background: #e8f5e9 !important;
        }
        
        .gross-profit-label {
            font-size: 15px;
            font-weight: bold;
            color: #2e7d32;
            text-transform: uppercase;
        }
        
        .gross-profit-amount {
            font-size: 16px;
            font-weight: bold;
            color: #1b5e20;
        }
        
        /* Net Profit/Loss Row */
        .net-result {
            padding: 14px 15px;
            border: 3px solid;
            font-size: 16px;
            font-weight: bold;
        }
        
        .net-profit-bg {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .net-loss-bg {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .net-label {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .net-amount {
            font-size: 18px;
            font-weight: bold;
        }
        
        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            font-size: 10px;
            color: #888;
        }
        
        .footer table td {
            border: none;
            padding: 4px 0;
            background: transparent !important;
        }
        
        /* Utility Classes */
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: #999;
            font-style: italic;
            font-size: 13px;
        }
        
        .w-70 {
            width: 70%;
        }
        
        .w-30 {
            width: 30%;
        }
        
        .amount {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        
        /* Page break for printing */
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <!-- Report Header -->
    <div class="report-header">
        <div class="company-name">{{ get_setting('site_name', env('APP_NAME', 'Company Name')) }}</div>
        <div class="report-title">PROFIT LOSS STATEMENT</div>
        <p class="period">
            <strong>Period:</strong> 
            {{ $startDate->format('d M Y') }} <strong>to</strong> {{ $endDate->format('d M Y') }}
        </p>
    </div>

    <!-- INCOME SECTION -->
    <div class="section">
        <table>
            <thead>
                <tr>
                    <th colspan="2" class="section-title">INCOMES</th>
                </tr>
            </thead>
            <tbody>
                @forelse($incomeData as $income)
                <tr>
                    <td class="w-70">{{ $income['name'] }}</td>
                    <td class="w-30 text-right amount">{{ number_format($income['balance'], 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" class="text-center text-muted">— No income records found for this period —</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td class="w-70">Total Income (৳)</td>
                    <td class="w-30 text-right amount">{{ number_format($totalIncome, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- COST OF SALES SECTION -->
    <div class="section">
        <table>
            <thead>
                <tr>
                    <th colspan="2" class="section-title cogs">COST OF SALES</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cogsData as $cogs)
                <tr>
                    <td class="w-70">{{ $cogs['name'] }}</td>
                    <td class="w-30 text-right amount">{{ number_format($cogs['balance'], 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" class="text-center text-muted">— No COGS records found for this period —</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td class="w-70">Total Cost of Sales (৳)</td>
                    <td class="w-30 text-right amount">{{ number_format($totalCOGS, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- GROSS PROFIT -->
    <div class="section">
        <table>
            <tbody>
                <tr class="gross-profit-row">
                    <td class="w-70 gross-profit-label">GROSS PROFIT (৳)</td>
                    <td class="w-30 text-right gross-profit-amount">{{ number_format($grossProfit, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- EXPENSES SECTION -->
    <div class="section">
        <table>
            <thead>
                <tr>
                    <th colspan="2" class="section-title expenses">EXPENSES</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expenseData as $expense)
                <tr>
                    <td class="w-70">{{ $expense['name'] }}</td>
                    <td class="w-30 text-right amount">{{ number_format($expense['balance'], 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" class="text-center text-muted">— No expense records found for this period —</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td class="w-70">Total Expenses (৳)</td>
                    <td class="w-30 text-right amount">{{ number_format($totalExpenses, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- NET PROFIT / LOSS -->
    <div class="section">
        <table>
            <tbody>
                <tr class="net-result {{ $netProfit >= 0 ? 'net-profit-bg' : 'net-loss-bg' }}">
                    <td class="w-70 net-label">
                        {{ $netProfit >= 0 ? 'NET PROFIT' : 'NET LOSS' }} (৳)
                    </td>
                    <td class="w-30 text-right net-amount">
                        {{ number_format(abs($netProfit), 2) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Footer -->
    <div class="footer">
        <table>
            <tr>
                <td style="width: 60%;">Print Date: <strong>{{ now()->format('d M Y, h:i A') }}</strong></td>
                <td style="width: 40%; text-align: right;">Generated by: <strong>{{ auth()->user()->name ?? 'System' }}</strong></td>
            </tr>
        </table>
    </div>
</body>
</html>