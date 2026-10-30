@extends('backend.layouts.app')

@section('content')
    <style>
        .page-title {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #6b7280;
            font-size: 18px;
            margin-bottom: 35px;
        }

        .summary-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            padding: 20px 24px;
            height: 100%;
        }

        .summary-label {
            font-size: 12px;
            font-weight: 700;
            color: #9ca3af;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .summary-value {
            font-size: 26px;
            font-weight: 700;
            color: #1f2430;
        }

        .summary-card.primary .summary-value {
            color: var(--account-color, #4f46e5);
        }

        .table-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            overflow-x: auto;
            margin-top: 30px;
        }

        .custom-table {
            margin-bottom: 0;
            min-width: 1000px;
        }

        .custom-table thead th {
            border: none;
            padding: 20px 18px;
            font-size: 12px;
            font-weight: 700;
            color: #9ca3af;
            letter-spacing: 1px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .custom-table tbody td {
            vertical-align: middle;
            border-top: 1px solid #eef0f3;
            padding: 16px 18px;
            font-weight: 400;
            white-space: nowrap;
        }

        .invoice-badge {
            background: #eef2ff;
            color: var(--account-color, #4f46e5);
            border: 1px solid #c7d2fe;
            border-radius: 10px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 700;
            display: inline-block;
        }

        .pill {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            line-height: 1;
        }

        .pill-success {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #86efac;
        }

        .table-footer {
            padding: 18px 24px;
            border-top: 1px solid #eef0f3;
        }

        .footer-text {
            color: #6b7280;
            font-weight: 600;
            letter-spacing: .5px;
        }
    </style>

    <div class="">
        <h1 class="page-title">Courier Settlement Report</h1>
        <p class="page-subtitle">
            Overview of all settled courier deliveries.
        </p>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="summary-card primary">
                    <div class="summary-label">Total Settlements</div>
                    <div class="summary-value">{{ $settlements->total() }}</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="summary-card">
                    <div class="summary-label">Total Settlement Fee</div>
                    <div class="summary-value">{{ single_price($totalSettled) }}</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="summary-card">
                    <div class="summary-label">Total Courier Fee</div>
                    <div class="summary-value">{{ single_price($totalCourierFee) }}</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="summary-card">
                    <div class="summary-label">Total Net Payable</div>
                    <div class="summary-value">{{ single_price($totalNetPayable) }}</div>
                </div>
            </div>
        </div>

        <!-- Expense Account Balances -->
        @if ($courierExpense || $settlementExpense)
            <div class="row mt-2">
                @if ($courierExpense)
                    <div class="col-md-3 mb-3">
                        <div class="summary-card">
                            <div class="summary-label">Courier Fee Expense</div>
                            <div class="summary-value">{{ single_price($courierExpense->current_balance ?? 0) }}</div>
                        </div>
                    </div>
                @endif
                @if ($settlementExpense)
                    <div class="col-md-3 mb-3">
                        <div class="summary-card">
                            <div class="summary-label">Courier Settlement Expense</div>
                            <div class="summary-value">{{ single_price($settlementExpense->current_balance ?? 0) }}</div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Settlements Table -->
        <div class="table-card">
            <table class="table custom-table">
                <thead>
                    <tr>
                        <th>SETTLEMENT NO</th>
                        <th>DATE</th>
                        <th>ORDER</th>
                        <th>COURIER</th>
                        <th>ACCOUNT</th>
                        <th>TOTAL</th>
                        <th>COUR FEE</th>
                        <th>SETT FEE</th>
                        <th>NET PAYABLE</th>
                        <th>SETTLED BY</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($settlements as $settlement)
                        <tr>
                            <td>
                                <span class="invoice-badge">{{ $settlement->settlement_no }}</span>
                            </td>
                            <td>{{ $settlement->settlement_date ? $settlement->settlement_date->format('Y-m-d') : date('Y-m-d', strtotime($settlement->created_at)) }}</td>
                            <td>
                                {{ $settlement->order->code ?? '#' . $settlement->order_id }}
                                @if ($settlement->partial_payment_choice)
                                    <span class="pill pill-success">{{ ucfirst($settlement->partial_payment_choice) }}</span>
                                @endif
                            </td>
                            <td>{{ ucfirst($settlement->courier_name ?? 'N/A') }}</td>
                            <td>{{ $settlement->account_name ?? ($settlement->account->name ?? 'N/A') }}</td>
                            <td>{{ '৳' . number_format($settlement->total_amount, 2) }}</td>
                            <td>{{ '৳' . number_format($settlement->courier_fee, 2) }}</td>
                            <td>{{ '৳' . number_format($settlement->settlement_fee, 2) }}</td>
                            <td>{{ '৳' . number_format($settlement->net_payable, 2) }}</td>
                            <td>{{ $settlement->user->name ?? 'System' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="mb-0">No settled courier orders found</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="table-footer d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <div class="footer-text">
                    SHOWING {{ $settlements->firstItem() ?? 0 }}–{{ $settlements->lastItem() ?? 0 }} OF {{ $settlements->total() }}
                    SETTLEMENTS
                </div>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    {{ $settlements->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
