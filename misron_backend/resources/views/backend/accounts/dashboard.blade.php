<style>
    #expenseChart,
    #assetChart {
        width: 320px !important;
        height: 235px !important;
    }

    .dashboard-page {
        background: #f5f7fb;
        padding: 20px;
        min-height: 100vh;
        font-family: 'Inter', sans-serif;
    }

    .dashboard-title {
        font-size: 28px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 4px;
    }

    .dashboard-subtitle {
        color: #94a3b8;
        font-size: 13px;
    }

    .dashboard-btn {
        height: 48px;
        padding: 0 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.3s ease;
    }

    .btn-export {
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #475569;
    }

    .btn-export:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }

    .btn-primary-custom {
        background: #4f46e5;
        color: #fff;
        border: none;
        box-shadow: 0 8px 20px rgba(79, 70, 229, 0.15);
    }

    .btn-primary-custom:hover {
        background: #4338ca;
        transform: translateY(-1px);
    }

    .dashboard-card-custom,
    .mini-card {
        background: #fff;
        border-radius: 24px;
        padding: 20px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        transition: all 0.3s ease;
    }

    .dashboard-card-custom:hover,
    .mini-card:hover {
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
    }

    .icon-box {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .bg-light-blue {
        background: #eff6ff;
    }

    .bg-light-warning {
        background: #fffbeb;
    }

    .bg-light-indigo {
        background: #eef2ff;
    }

    .bg-light-danger {
        background: #fef2f2;
    }

    .trend {
        padding: 5px 10px;
        border-radius: 30px;
        font-size: 11px;
        font-weight: 700;
    }

    .trend-success {
        background: #ecfdf3;
        color: #16a34a;
    }

    .trend-danger {
        background: #fef2f2;
        color: #e11d48;
    }

    .card-label {
        margin-top: 18px;
        margin-bottom: 8px;
        color: #94a3b8;
        font-size: 12px;
        letter-spacing: 0.5px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .card-value {
        font-size: 16px;
        font-weight: 600;
        color: #0f172a;
    }

    .section-title {
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    .chart-legend {
        font-size: 12px;
        color: #94a3b8;
        font-weight: 600;
    }

    .legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }

    .bank-total {
        font-size: 20px;
        font-weight: 700;
        color: #4f46e5;
    }

    .bank-item {
        display: flex;
        justify-content: space-between;
        padding: 16px 0;
        border-bottom: 1px solid #eef2f7;
        color: #64748b;
        font-size: 14px;
    }

    .bank-item:last-child {
        border-bottom: none;
    }

    .history-link {
        color: #4f46e5;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
    }

    .history-link:hover {
        text-decoration: underline;
    }

    .transaction-table thead th {
        border-top: none;
        border-bottom: 1px solid #eef2f7;
        color: #94a3b8;
        font-size: 11px;
        letter-spacing: 0.5px;
        font-weight: 700;
        text-transform: uppercase;
        padding: 12px 8px;
    }

    .transaction-table tbody td {
        padding: 16px 8px;
        border-top: 1px solid #f1f5f9;
        vertical-align: middle;
        font-size: 14px;
    }

    .transaction-table small {
        color: #94a3b8;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .badge-status {
        padding: 5px 12px;
        border-radius: 30px;
        font-size: 11px;
        font-weight: 700;
        display: inline-block;
    }

    .badge-status.paid {
        background: #ecfdf3;
        color: #16a34a;
    }

    .badge-status.received {
        background: #ecfdf3;
        color: #16a34a;
    }

    .badge-status.pending {
        background: #fffbeb;
        color: #ea580c;
    }

    .badge-status.partial {
        background: #fffbeb;
        color: #ea580c;
    }

    .badge-status.withdraw {
        background: #fef2f2;
        color: #e11d48;
    }

    .insight-label {
        color: #94a3b8;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        margin-bottom: 8px;
    }

    .insight-value {
        font-size: 22px;
        font-weight: 700;
        color: #0f172a;
    }

    .text-primary-custom {
        color: #4f46e5 !important;
    }

    .progress-custom {
        height: 6px;
        border-radius: 30px;
        background: #eef2f7;
    }

    .bg-primary-custom {
        background: #4f46e5;
    }

    .btn-settle {
        width: 100%;
        background: #0f172a;
        color: #fff;
        height: 48px;
        border: none;
        border-radius: 14px;
        font-size: 13px;
        font-weight: 700;
        transition: all 0.3s ease;
    }

    .btn-settle:hover {
        background: #1e293b;
        transform: translateY(-1px);
    }

    .badge-optimal {
        background: #ecfdf3;
        color: #16a34a;
        padding: 5px 12px;
        border-radius: 30px;
        font-size: 11px;
        font-weight: 700;
    }

    .health-block {
        margin-bottom: 24px;
    }

    .health-block span {
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
    }

    /* Custom Column for 5 items */
    /* @media (min-width: 1024px) {
        .col-lg-2-4 {
            flex: 0 0 20%;
            max-width: 20%;
        }
    } */

    .col-custom-5 {
        flex: 0 0 20%;
        max-width: 20%;
    }

    @media (max-width: 1800px) {
        .col-custom-5 {
            flex: 0 0 33.3333%;
            max-width: 33.3333%;
        }
    }

    @media (max-width: 767px) {
        .col-custom-5 {
            flex: 0 0 50%;
            max-width: 50%;
        }
    }

    @media (max-width: 575px) {
        .col-custom-5 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .dashboard-page {
            padding: 16px;
        }

        .dashboard-title {
            font-size: 22px;
        }

        .dashboard-btn {
            height: 42px;
            padding: 0 16px;
            font-size: 12px;
        }

        .card-value,
        .insight-value {
            font-size: 20px;
        }

        .bank-total {
            font-size: 18px;
        }

        .dashboard-card-custom,
        .mini-card {
            padding: 16px;
        }
    }

    @media (max-width: 576px) {
        .dashboard-title {
            font-size: 20px;
        }

        .dashboard-subtitle {
            font-size: 11px;
        }

        .card-value {
            font-size: 14px;
        }

        .section-title {
            font-size: 12px;
        }
    }
</style>

<div class="dashboard-page">

    <!-- HEADER -->
    <div class="row align-items-center mb-4 d-flex justify-content-between">
        <div class="col-md-6">
            <h2 class="dashboard-title mb-1">{{ translate('Accounts Overview') }}</h2>
            <p class="dashboard-subtitle mb-0">
                {{ translate('Real-time summary of corporate ledger and fiscal health.') }}
                <small class="text-muted">As of {{ now()->format('F d, Y') }}</small>
            </p>
        </div>
        <div class="col-auto mt-3 mt-md-0">
            <div class="d-flex align-items-center gap-2">
                <button class="btn dashboard-btn btn-export mr-2 d-flex align-items-center text-uppercase"
                    onclick="exportReport()">
                    <i class="las la-download mr-2"></i> {{ translate('EXPORT') }}
                </button>
                <button class="btn dashboard-btn btn-primary-custom text-uppercase"
                    onclick="window.location.href='{{ route('accounts.balance-transfers.create') }}'">
                    <i class="las la-plus mr-2"></i> {{ translate('NEW Transaction') }}
                </button>
            </div>
        </div>
    </div>

    <!-- TOP STATS -->
    <div class="row d-flex flex-wrap px-2">
        <div class="col-custom-5 mb-4 px-2">
            <div class="mini-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="icon-box bg-light-blue">
                        <i class="las la-wallet text-primary"></i>
                    </div>
                    <div class="trend {{ $totalAssetsGrowth >= 0 ? 'trend-success' : 'trend-danger' }}">
                        <i class="las la-arrow-{{ $totalAssetsGrowth >= 0 ? 'up' : 'down' }}"></i>
                        {{ number_format(abs($totalAssetsGrowth), 1) }}%
                    </div>
                </div>
                <div class="card-label">{{ translate('TOTAL ASSETS') }}</div>
                <div class="card-value">{{ single_price($totalAssets) ?? 0 }}</div>
                <small class="text-muted">Current balance of all asset accounts</small>
            </div>
        </div>

        <div class="col-custom-5 mb-4 px-2">
            <div class="mini-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="icon-box bg-light-warning">
                        <i class="las la-credit-card text-warning"></i>
                    </div>
                    <div class="trend {{ $totalLiabilitiesGrowth <= 0 ? 'trend-success' : 'trend-danger' }}">
                        <i class="las la-arrow-{{ $totalLiabilitiesGrowth <= 0 ? 'down' : 'up' }}"></i>
                        {{ number_format(abs($totalLiabilitiesGrowth), 1) }}%
                    </div>
                </div>
                <div class="card-label">{{ translate('TOTAL LIABILITIES') }}</div>
                <div class="card-value">{{ single_price($totalLiabilities) ?? 0 }}</div>
                <small class="text-muted">Total debts and obligations</small>
            </div>
        </div>

        <div class="col-custom-5 mb-4 px-2">
            <div class="mini-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="icon-box bg-light-indigo">
                        <i class="las la-chart-line text-primary"></i>
                    </div>
                    <div class="trend {{ $netIncomeGrowth >= 0 ? 'trend-success' : 'trend-danger' }}">
                        <i class="las la-arrow-{{ $netIncomeGrowth >= 0 ? 'up' : 'down' }}"></i>
                        {{ number_format(abs($netIncomeGrowth), 1) }}%
                    </div>
                </div>
                <div class="card-label">{{ translate('NET INCOME') }}</div>
                <div class="card-value">{{ single_price($netIncome) ?? 0 }}</div>
                <small class="text-muted">Revenue - Expenses</small>
            </div>
        </div>

        <div class="col-custom-5 mb-4 px-2">
            <div class="mini-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="icon-box bg-light-danger">
                        <i class="las la-chart-line text-danger"></i>
                    </div>
                    <div class="trend {{ $totalExpensesGrowth <= 0 ? 'trend-success' : 'trend-danger' }}">
                        <i class="las la-arrow-{{ $totalExpensesGrowth <= 0 ? 'down' : 'up' }}"></i>
                        {{ number_format(abs($totalExpensesGrowth), 1) }}%
                    </div>
                </div>
                <div class="card-label">{{ translate('TOTAL EXPENSES') }}</div>
                <div class="card-value">{{ single_price($totalExpense) ?? 0 }}</div>
                <small class="text-muted">All operational expenses</small>
            </div>
        </div>

        <div class="col-custom-5 mb-4 px-2">
            <div class="mini-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="icon-box bg-light-indigo">
                        <i class="las la-percent text-primary"></i>
                    </div>
                    <div class="trend {{ $profitMarginGrowth >= 0 ? 'trend-success' : 'trend-danger' }}">
                        <i class="las la-arrow-{{ $profitMarginGrowth >= 0 ? 'up' : 'down' }}"></i>
                        {{ number_format(abs($profitMarginGrowth), 1) }}%
                    </div>
                </div>
                <div class="card-label">{{ translate('NET PROFIT MARGIN') }}</div>
                <div class="card-value">{{ number_format($profitMargin ?? 0, 1) }}%</div>
                <small class="text-muted">Net Income / Total Revenue</small>
            </div>
        </div>
    </div>
    
    {{-- stock value,todays sales receiveable amount,payable amount all code start --}}
    <!-- BUSINESS METRICS -->
    <div class="row d-flex flex-wrap">
        <div class="col-xxl-3 col-lg-6 col-md-6 mb-4">
            <div class="mini-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="icon-box bg-light-blue">
                        <i class="las la-boxes text-primary"></i>
                    </div>
                </div>
                <div class="card-label">{{ translate('STOCK VALUE') }}</div>
                <div class="card-value">{{ single_price($stockValue ?? 0) }}</div>
                <small class="text-muted">Inventory at cost (purchase quantity × unit cost)</small>
            </div>
        </div>

        <div class="col-xxl-3 col-lg-6 col-md-6 mb-4">
            <div class="mini-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="icon-box bg-light-indigo">
                        <i class="las la-shopping-cart text-success"></i>
                    </div>
                </div>
                <div class="card-label">{{ translate("TODAY'S SALES") }}</div>
                <div class="card-value">{{ single_price($todaySales ?? 0) }}</div>
                <small class="text-muted">Orders created {{ now()->format('M d, Y') }}</small>
            </div>
        </div>

        <div class="col-xxl-3 col-lg-6 col-md-6 mb-4">
            <div class="mini-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="icon-box bg-light-warning">
                        <i class="las la-hand-holding-usd text-warning"></i>
                    </div>
                </div>
                <div class="card-label">{{ translate('RECEIVABLE AMOUNT') }}</div>
                <div class="card-value">{{ single_price($receivableAmount ?? 0) }}</div>
                <small class="text-muted">Asset accounts - receivable sub type</small>
            </div>
        </div>

        <div class="col-xxl-3 col-lg-6 col-md-6 mb-4">
            <div class="mini-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="icon-box bg-light-danger">
                        <i class="las la-file-invoice-dollar text-danger"></i>
                    </div>
                </div>
                <div class="card-label">{{ translate('PAYABLE AMOUNT') }}</div>
                <div class="card-value">{{ single_price($payableAmount ?? 0) }}</div>
                <small class="text-muted">Liability accounts - payable sub type</small>
            </div>
        </div>
        <div class="col-xxl-3 col-lg-6 col-md-6 mb-4">
            <div class="mini-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="icon-box bg-light-success">
                        <i class="las la-wallet text-success"></i>
                    </div>
                </div>
                <div class="card-label">{{ translate('Petty Cash Total Amount') }}</div>
                <div class="card-value">{{ single_price($pettycashValue ?? 0) }}</div>
                <small class="text-muted">Petty Cash Account Total Amount</small>
            </div>
        </div>
        <div class="col-xxl-3 col-lg-6 col-md-6 mb-4">
            <div class="mini-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="icon-box bg-light-primary">
                        <i class="las la-boxes text-primary"></i>
                    </div>
                </div>
                <div class="card-label">{{ translate('Current Stock Value') }}</div>
                <div class="card-value">{{ single_price($currentStockvalue ?? 0) }}</div>
                <small class="text-muted">Purchases current stock value</small>
            </div>
        </div>
        <div class="col-xxl-3 col-lg-6 col-md-6 mb-4">
            <div class="mini-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="icon-box bg-light-warning">
                        <i class="las la-hand-holding-usd text-warning"></i>
                    </div>
                </div>
                <div class="card-label">{{ translate('Supplier Payable') }}</div>
                <div class="card-value">{{ single_price($totalSupplierDueBalance ?? 0) }}</div>
                <small class="text-muted">Total Supplier Due</small>
            </div>
        </div>
        <div class="col-xxl-3 col-lg-6 col-md-6 mb-4">
            <div class="mini-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="icon-box bg-light-warning">
                        <i class="las la-dollar-sign text-warning"></i>
                    </div>
                </div>
                <div class="card-label">{{ translate('Monthly Sales') }}</div>
                <div class="card-value">{{ single_price($totalMonthlySale ?? 0) }}</div>
                <small class="text-muted">Total Current Month Sale</small>
            </div>
        </div>
    </div>
    {{-- stock value,todays sales receiveable amount,payable amount all code end --}}

    <!-- CHART + BANK -->
    <div class="row">
        <!-- CHART -->
        <div class="col-lg-8 mb-4">
            <div class="dashboard-card-custom">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <h5 class="section-title mb-0">{{ translate('FINANCIAL PERFORMANCE') }}</h5>
                    <div class="chart-legend">
                        <span class="mr-3">
                            <span class="legend-dot bg-success"></span> {{ translate('INCOME') }}
                        </span>
                        <span>
                            <span class="legend-dot bg-danger"></span> {{ translate('EXPENSE') }}
                        </span>
                    </div>
                </div>
                <canvas id="financeChart" height="120"></canvas>
            </div>
        </div>

        <!-- BANK BALANCES -->
        <div class="col-lg-4 mb-4">
            <div class="dashboard-card-custom h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="section-title mb-0">{{ translate('BANK & MOBILE BALANCE') }}</h5>
                    <div class="bank-total">{{ single_price($bankCashTotal) ?? 0 }}</div>
                </div>
                @forelse ($bankAccounts ?? [] as $account)
                    <div class="bank-item">
                        <span>
                            <i
                                class="las {{ $account->sub_type == 'mobile_bank' ? 'la-mobile-alt' : 'la-university' }} mr-2"></i>
                            {{ $account->name }}
                            @if ($account->mobile_number)
                                <br><small class="text-muted">{{ $account->mobile_number }}</small>
                            @endif
                        </span>
                        <strong class="{{ $account->current_balance > 0 ? 'text-success' : 'text-danger' }}">
                            {{ single_price($account->current_balance) ?? 0 }}
                        </strong>
                    </div>
                @empty
                    <div class="bank-item">
                        <span class="text-muted">{{ translate('No bank/mobile accounts found') }}</span>
                        <strong>0.00</strong>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- TRANSACTION + INSIGHT -->
    <div class="row">
        <!-- RECENT TRANSACTIONS -->
        <div class="col-lg-8 mb-4">
            <div class="dashboard-card-custom">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <h5 class="section-title mb-0">{{ translate('RECENT TRANSACTIONS') }}</h5>
                    <a href="{{ route('accounts.index', ['tab' => 'withdraw-deposit']) }}" class="history-link">
                        {{ translate('EXPLORE FULL HISTORY') }}
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table transaction-table">
                        <thead>
                            <tr>
                                <th>{{ translate('DATE') }}</th>
                                <th>{{ translate('DESCRIPTION') }}</th>
                                <th>{{ translate('FROM/TO') }}</th>
                                <th>{{ translate('AMOUNT') }}</th>
                                <th>{{ translate('STATUS') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentTransactions ?? [] as $transaction)
                                <tr>
                                    <td>
                                        {{ $transaction->date ? $transaction->date->format('M d, Y') : 'N/A' }}
                                        <br><small>{{ $transaction->date ? $transaction->date->format('h:i A') : '' }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}</strong><br>
                                        <small>{{ Str::limit($transaction->note ?? 'No description', 40) }}</small>
                                    </td>
                                    <td>
                                        <small class="text-muted">From:</small>
                                        {{ $transaction->fromAccount->name ?? 'N/A' }}<br>
                                        <small class="text-muted">To:</small>
                                        {{ $transaction->toAccount->name ?? 'N/A' }}
                                    </td>
                                    <td
                                        class="{{ $transaction->transaction_type == 'deposit' ? 'text-success' : 'text-danger' }}">
                                        <strong>{{ $transaction->transaction_type == 'deposit' ? '+' : '-' }}{{ number_format($transaction->amount, 2) }}</strong>
                                    </td>
                                    <td>
                                        <span
                                            class="badge-status {{ $transaction->status == 'completed' ? 'paid' : 'pending' }}">
                                            {{ strtoupper($transaction->status ?? 'pending') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <i class="las la-exchange-alt la-2x text-muted"></i>
                                        <p class="mt-2">{{ translate('No recent transactions') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- FINANCIAL INSIGHTS -->
        <div class="col-lg-4 mb-4">
            <div class="dashboard-card-custom h-100">
                <h5 class="section-title mb-4">{{ translate('FINANCIAL INSIGHTS') }}</h5>
                <div class="mb-4">
                    <div class="insight-label">{{ translate('INCOMING REVENUE') }}</div>
                    <div class="insight-value text-primary-custom">{{ single_price($incomingRevenue) ?? 0 }}
                    </div>
                    <div class="progress progress-custom mt-3">
                        <div class="progress-bar bg-primary-custom" style="width: {{ $revenuePercentage ?? 0 }}%">
                        </div>
                    </div>
                    <small class="text-muted">{{ single_price($revenuePercentage) ?? 0 }}% of total
                        income+expense</small>
                </div>
                <div class="mb-4">
                    <div class="insight-label">{{ translate('OPERATING EXPENSES') }}</div>
                    <div class="insight-value">{{ single_price($operatingExpenses) ?? 0 }}</div>
                    <div class="progress progress-custom mt-3">
                        <div class="progress-bar bg-secondary" style="width: {{ $expensePercentage ?? 0 }}%"></div>
                    </div>
                    <small class="text-muted">{{ number_format($expensePercentage ?? 0, 1) }}% of total
                        income+expense</small>
                </div>
                <div class="mb-4">
                    <div class="insight-label">{{ translate('NET SURPLUS / DEFICIT') }}</div>
                    <div class="insight-value {{ $netSurplus >= 0 ? 'text-primary-custom' : 'text-danger' }}">
                        {{ $netSurplus >= 0 ? '+' : '-' }}{{ number_format(abs($netSurplus ?? 0), 2) }} ৳
                    </div>
                    <small class="text-muted">Income after all expenses</small>
                </div>
                <button class="btn btn-settle"
                    onclick="window.location.href='{{ route('accounts.expense-payments.create') }}'">
                    {{ translate('ADD NEW EXPENSE') }}
                </button>
            </div>
        </div>
    </div>

    <!-- BOTTOM SECTION -->
    <div class="row">
        <!-- ACCOUNT HEALTH -->
        <div class="col-lg-4 mb-4">
            <div class="dashboard-card-custom h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="section-title mb-0">{{ translate('ACCOUNT HEALTH') }}</h5>
                    <span class="badge-optimal">{{ $reconciledPercentage >= 80 ? 'HEALTHY' : 'NEEDS REVIEW' }}</span>
                </div>
                <div class="health-block">
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ translate('RECONCILED ACCOUNTS') }}</span>
                        <strong
                            class="text-primary-custom">{{ $reconciledAccounts ?? 0 }}/{{ $totalAccounts ?? 0 }}</strong>
                    </div>
                    <div class="progress progress-custom">
                        <div class="progress-bar bg-primary-custom" style="width: {{ $reconciledPercentage ?? 0 }}%">
                        </div>
                    </div>
                    <small class="text-muted">Accounts with positive balance</small>
                </div>
                <div class="health-block">
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ translate('PENDING APPROVALS') }}</span>
                        <strong class="text-warning">{{ $pendingApprovals ?? 0 }}</strong>
                    </div>
                    <div class="progress progress-custom">
                        <div class="progress-bar bg-warning" style="width: {{ $pendingPercentage ?? 0 }}%"></div>
                    </div>
                    <small class="text-muted">Transactions awaiting approval</small>
                </div>
                <div class="health-block mb-0">
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ translate('ACTIVE ACCOUNTS') }}</span>
                        <strong class="text-success">{{ $totalAccounts ?? 0 }}</strong>
                    </div>
                    <div class="progress progress-custom">
                        <div class="progress-bar bg-success" style="width: 100%"></div>
                    </div>
                    <small class="text-muted">Total active chart of accounts</small>
                </div>
            </div>
        </div>

        <!-- EXPENSE ANALYSIS -->
        <div class="col-lg-4 mb-4">
            <div class="dashboard-card-custom text-center h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="section-title mb-0">{{ translate('EXPENSE ANALYSIS') }}</h5>
                    <small>{{ translate('Top Expenses') }}</small>
                </div>
                <canvas id="expenseChart" width="300" height="300"
                    style="max-width: 100%; height: auto;"></canvas>
                @if (array_sum($expenseValues) == 0)
                    <p class="text-muted mt-3">No expense data available</p>
                @endif
            </div>
        </div>

        <!-- ASSET ANALYSIS -->
        <div class="col-lg-4 mb-4">
            <div class="dashboard-card-custom text-center h-100">
                <h5 class="section-title mb-4">{{ translate('ASSET BREAKDOWN') }}</h5>
                <canvas id="assetChart" width="300" height="300"
                    style="max-width: 100%; height: auto;"></canvas>
                @if (array_sum($assetValues) == 0)
                    <p class="text-muted mt-3">No asset data available</p>
                @endif
                <div class="mt-3">
                    <small class="text-muted">Total Assets: {{ single_price($totalAssets) ?? 0 }}</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Helper function to ensure numeric values
        function ensureNumericArray(arr) {
            if (!arr || !Array.isArray(arr)) return [0, 0, 0, 0, 0, 0];
            return arr.map(item => typeof item === 'number' ? item : parseFloat(item) || 0);
        }

        // Financial Performance Bar Chart
        var monthlyIncomeData = {!! json_encode(!empty($monthlyIncome) ? array_values($monthlyIncome) : [0, 0, 0, 0, 0, 0]) !!};
        var monthlyExpenseData = {!! json_encode(!empty($monthlyExpense) ? array_values($monthlyExpense) : [0, 0, 0, 0, 0, 0]) !!};
        var monthLabelsData = {!! json_encode(!empty($monthLabels) ? $monthLabels : ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN']) !!};

        console.log('Monthly Income:', monthlyIncomeData);
        console.log('Monthly Expense:', monthlyExpenseData);
        console.log('Month Labels:', monthLabelsData);

        var ctx1 = document.getElementById('financeChart').getContext('2d');
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: monthLabelsData,
                datasets: [{
                    label: 'Income',
                    data: ensureNumericArray(monthlyIncomeData),
                    backgroundColor: '#16A34A',
                    borderRadius: 8,
                    barThickness: 18
                }, {
                    label: 'Expense',
                    data: ensureNumericArray(monthlyExpenseData),
                    backgroundColor: '#DC2626',
                    borderRadius: 8,
                    barThickness: 18
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let value = context.raw;
                                if (typeof value === 'string') value = parseFloat(value) || 0;
                                if (typeof value !== 'number') value = 0;
                                return context.dataset.label + ': ' + value.toFixed(2) + ' ৳';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#eef2f7'
                        },
                        ticks: {
                            color: '#94a3b8',
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });

        // Expense Donut Chart
        var expenseLabelsData = {!! json_encode(!empty($expenseLabels) ? $expenseLabels : ['No Data']) !!};
        var expenseValuesData = {!! json_encode(!empty($expenseValues) ? array_values($expenseValues) : [1]) !!};

        console.log('Expense Labels:', expenseLabelsData);
        console.log('Expense Values:', expenseValuesData);

        var ctx2 = document.getElementById('expenseChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: expenseLabelsData,
                datasets: [{
                    data: ensureNumericArray(expenseValuesData),
                    backgroundColor: ['#86efac', '#818cf8', '#facc15', '#fb7185', '#cbd5e1'],
                    borderWidth: 0
                }]
            },
            options: {
                cutout: '70%',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let value = context.raw;
                                if (typeof value === 'string') value = parseFloat(value) || 0;
                                if (typeof value !== 'number') value = 0;
                                const total = context.dataset.data.reduce((a, b) => {
                                    let numA = typeof a === 'number' ? a : parseFloat(a) ||
                                        0;
                                    let numB = typeof b === 'number' ? b : parseFloat(b) ||
                                        0;
                                    return numA + numB;
                                }, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) :
                                    0;
                                return context.label + ': ' + value.toFixed(2) + ' ৳ (' +
                                    percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Asset Donut Chart
        var assetLabelsData = {!! json_encode(!empty($assetLabels) ? $assetLabels : ['No Data']) !!};
        var assetValuesData = {!! json_encode(!empty($assetValues) ? array_values($assetValues) : [1]) !!};

        console.log('Asset Labels:', assetLabelsData);
        console.log('Asset Values:', assetValuesData);

        var ctx3 = document.getElementById('assetChart').getContext('2d');
        new Chart(ctx3, {
            type: 'doughnut',
            data: {
                labels: assetLabelsData,
                datasets: [{
                    data: ensureNumericArray(assetValuesData),
                    backgroundColor: ['#60a5fa', '#fb7185', '#facc15', '#86efac', '#cbd5e1',
                        '#818cf8'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                cutout: '70%',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let value = context.raw;
                                if (typeof value === 'string') value = parseFloat(value) || 0;
                                if (typeof value !== 'number') value = 0;
                                const total = context.dataset.data.reduce((a, b) => {
                                    let numA = typeof a === 'number' ? a : parseFloat(a) ||
                                        0;
                                    let numB = typeof b === 'number' ? b : parseFloat(b) ||
                                        0;
                                    return numA + numB;
                                }, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) :
                                    0;
                                return context.label + ': ' + value.toFixed(2) + ' ৳ (' +
                                    percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    });

    function exportReport() {
        window.location.href = '{{ route('accounts.export') }}?tab=dashboard';
    }
</script>
