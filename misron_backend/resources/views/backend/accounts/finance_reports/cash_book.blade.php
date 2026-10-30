@extends('backend.accounts.layout')

@section('accounts_content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 h6">{{ translate('Cash Book Report') }}</h5>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" action="{{ route('accounts.reports.cash-book') }}" class="mb-4" id="reportForm">
            <div class="form-row align-items-end">
                <div class="col-md-3 mb-2">
                    <label class="d-block text-muted small">{{ translate('Date Range') }}</label>
                    <select name="date_range" class="form-control" onchange="toggleCustomDate(this.value)">
                        <option value="this_month" {{ $dateRange == 'this_month' ? 'selected' : '' }}>{{ translate('Month of') }} {{ now()->format('F') }}</option>
                        <option value="last_month" {{ $dateRange == 'last_month' ? 'selected' : '' }}>{{ translate('Month of') }} {{ now()->subMonth()->format('F') }}</option>
                        <option value="today" {{ $dateRange == 'today' ? 'selected' : '' }}>{{ translate('Today') }}</option>
                        <option value="yesterday" {{ $dateRange == 'yesterday' ? 'selected' : '' }}>{{ translate('Yesterday') }}</option>
                        <option value="custom" {{ $dateRange == 'custom' ? 'selected' : '' }}>{{ translate('Custom Date') }}</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2 custom-date" style="{{ $dateRange == 'custom' ? '' : 'display:none;' }}">
                    <label class="d-block text-muted small">{{ translate('Start Date') }}</label>
                    <input type="date" name="start_date" class="form-control" value="{{ request('start_date', $startDate->toDateString()) }}">
                </div>
                <div class="col-md-2 mb-2 custom-date" style="{{ $dateRange == 'custom' ? '' : 'display:none;' }}">
                    <label class="d-block text-muted small">{{ translate('End Date') }}</label>
                    <input type="date" name="end_date" class="form-control" value="{{ request('end_date', $endDate->toDateString()) }}">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="d-block text-muted small">{{ translate('Account') }}</label>
                    <select name="account_id" class="form-control">
                        <option value="">{{ translate('-- Select Account --') }}</option>
                        @foreach($cashBankAccounts as $acc)
                            <option value="{{ $acc->id }}" {{ $accountId == $acc->id ? 'selected' : '' }}>
                                {{ $acc->name }} ({{ number_format($acc->current_balance, 2) }} ৳)
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="las la-search"></i> {{ translate('Generate') }}
                    </button>
                </div>
            </div>
        </form>

        <!-- Report Viewer Section -->
        @if($accountId && $selectedAccount)
        <div class="mt-3">
            <!-- Toolbar -->
            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light border rounded no-print">
                <div class="d-flex align-items-center">
                    <span class="mr-3 small text-muted">
                        {{ translate('Account') }}: <strong>{{ $selectedAccount->name }}</strong>
                    </span>
                    <span class="small text-muted">|</span>
                    <span class="mx-2 small text-muted">{{ translate('Period') }}: {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}</span>
                    <select class="form-control form-control-sm ml-3" style="width: 100px;" onchange="zoomReport(this.value)">
                        <option value="50">50%</option>
                        <option value="75">75%</option>
                        <option value="100" selected>100%</option>
                        <option value="125">125%</option>
                        <option value="150">150%</option>
                    </select>
                </div>
                <div>
                    <a href="{{ route('accounts.reports.cash-book', array_merge(request()->all(), ['export' => 'excel'])) }}" 
                       class="btn btn-sm btn-outline-success mr-1" title="Download Excel">
                        <i class="las la-file-excel"></i> Excel
                    </a>
                    <a href="{{ route('accounts.reports.cash-book', array_merge(request()->all(), ['export' => 'pdf'])) }}" 
                       class="btn btn-sm btn-outline-danger mr-1" title="Download PDF">
                        <i class="las la-file-pdf"></i> PDF
                    </a>
                    <button class="btn btn-sm btn-outline-info" onclick="printReportDiv()" title="Print">
                        <i class="las la-print"></i> Print
                    </button>
                </div>
            </div>

            <!-- Report Content -->
            <div class="border" style="overflow: auto; max-height: 65vh; background: #e9ecef;">
                <div class="d-flex justify-content-center" style="min-width: 1000px;">
                    <div id="reportContent" style="transform: scale(1); transform-origin: top center; transition: transform 0.2s; background: white; width: 297mm; padding: 12mm; margin: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); min-height: 210mm;">

                        <!-- Report Header -->
                        <div style="text-align: center; margin-bottom: 15px; padding-bottom: 12px; border-bottom: 2px solid #000;">
                            <h2 style="margin: 0; font-weight: bold; font-size: 18px; text-transform: uppercase;">
                                {{ get_setting('site_name', env('APP_NAME', 'Company Name')) }}
                            </h2>
                            <h3 style="margin: 6px 0; font-weight: bold; font-size: 14px;">
                                {{ translate('CASH BOOK REPORT') }}
                            </h3>
                            <p style="margin: 3px 0; font-size: 11px;">
                                <strong>{{ translate('Account') }}:</strong> {{ $selectedAccount->name }}
                                | <strong>{{ translate('Period') }}:</strong> {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}
                            </p>
                        </div>

                        <!-- Summary Cards -->
                        <div style="margin-bottom: 12px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                                <tr>
                                    <td style="width: 25%; padding: 8px; text-align: center; background: #17a2b8; color: #fff; border: 2px solid #fff;">
                                        <div style="font-size: 8px; text-transform: uppercase; opacity: 0.9;">{{ translate('Opening Balance') }}</div>
                                        <div style="font-size: 13px; font-weight: bold;">{{ number_format($openingBalance, 2) }}</div>
                                    </td>
                                    <td style="width: 25%; padding: 8px; text-align: center; background: #1F4E79; color: #fff; border: 2px solid #fff;">
                                        <div style="font-size: 8px; text-transform: uppercase; opacity: 0.9;">{{ translate('Total Debit') }}</div>
                                        <div style="font-size: 13px; font-weight: bold;">{{ number_format($totalDebit, 2) }}</div>
                                    </td>
                                    <td style="width: 25%; padding: 8px; text-align: center; background: #dc3545; color: #fff; border: 2px solid #fff;">
                                        <div style="font-size: 8px; text-transform: uppercase; opacity: 0.9;">{{ translate('Total Credit') }}</div>
                                        <div style="font-size: 13px; font-weight: bold;">{{ number_format($totalCredit, 2) }}</div>
                                    </td>
                                    <td style="width: 25%; padding: 8px; text-align: center; background: #28a745; color: #fff; border: 2px solid #fff;">
                                        <div style="font-size: 8px; text-transform: uppercase; opacity: 0.9;">{{ translate('Closing Balance') }}</div>
                                        <div style="font-size: 13px; font-weight: bold;">{{ number_format($closingBalance, 2) }}</div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Transactions Table -->
                        <div style="margin-bottom: 12px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                                <thead>
                                    <tr>
                                        <th style="background: #333; color: #fff; padding: 4px 5px; border: 1px solid #333; width: 3%;">#</th>
                                        <th style="background: #333; color: #fff; padding: 4px 5px; border: 1px solid #333; width: 10%;">{{ translate('Date') }}</th>
                                        <th style="background: #333; color: #fff; padding: 4px 5px; border: 1px solid #333; width: 15%;">{{ translate('Reference No') }}</th>
                                        <th style="background: #333; color: #fff; padding: 4px 5px; border: 1px solid #333; width: 10%;">{{ translate('Type') }}</th>
                                        <th style="background: #333; color: #fff; padding: 4px 5px; border: 1px solid #333; width: 12%; text-align: right;">{{ translate('Debit (৳)') }}</th>
                                        <th style="background: #333; color: #fff; padding: 4px 5px; border: 1px solid #333; width: 12%; text-align: right;">{{ translate('Credit (৳)') }}</th>
                                        <th style="background: #333; color: #fff; padding: 4px 5px; border: 1px solid #333; width: 14%; text-align: right;">{{ translate('Balance (৳)') }}</th>
                                        <th style="background: #333; color: #fff; padding: 4px 5px; border: 1px solid #333; width: 24%;">{{ translate('Description') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Opening Balance Row -->
                                    <tr style="background: #f0f4f8;">
                                        <td colspan="6" style="padding: 5px 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;">{{ translate('Opening Balance') }}:</td>
                                        <td style="padding: 5px 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;">{{ number_format($openingBalance, 2) }}</td>
                                        <td style="padding: 5px 8px; border: 1px solid #ddd;"></td>
                                    </tr>
                                    
                                    @php $runningBalance = $openingBalance; @endphp
                                    @forelse($transactions as $key => $trans)
                                    @php $runningBalance = $runningBalance + $trans->debit - $trans->credit; @endphp
                                    <tr style="{{ $key % 2 == 0 ? 'background: #fafafa;' : '' }}">
                                        <td style="padding: 3px 5px; border: 1px solid #ddd; text-align: center;">{{ $key + 1 }}</td>
                                        <td style="padding: 3px 5px; border: 1px solid #ddd;">{{ \Carbon\Carbon::parse($trans->transaction_date)->format('d-m-Y') }}</td>
                                        <td style="padding: 3px 5px; border: 1px solid #ddd;">{{ $trans->reference_no }}</td>
                                        <td style="padding: 3px 5px; border: 1px solid #ddd; font-size: 8px;">{{ $trans->transaction_type }}</td>
                                        <td style="padding: 3px 5px; border: 1px solid #ddd; text-align: right; color: #1F4E79;">{{ $trans->debit > 0 ? number_format($trans->debit, 2) : '-' }}</td>
                                        <td style="padding: 3px 5px; border: 1px solid #ddd; text-align: right; color: #C00000;">{{ $trans->credit > 0 ? number_format($trans->credit, 2) : '-' }}</td>
                                        <td style="padding: 3px 5px; border: 1px solid #ddd; text-align: right; font-weight: bold;">{{ number_format($runningBalance, 2) }}</td>
                                        <td style="padding: 3px 5px; border: 1px solid #ddd; font-size: 9px;">{{ Str::limit($trans->description, 40) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" style="padding: 15px; text-align: center; color: #999; border: 1px solid #ddd; font-style: italic;">
                                            {{ translate('No transactions found for this period') }}
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr style="font-weight: bold; background: #f0f4f8;">
                                        <td colspan="4" style="padding: 5px 8px; border: 1px solid #ccc; border-top: 2px solid #000; text-align: right;">{{ translate('Closing Balance') }}:</td>
                                        <td style="padding: 5px 8px; border: 1px solid #ccc; border-top: 2px solid #000; text-align: right; color: #1F4E79;">{{ number_format($totalDebit, 2) }}</td>
                                        <td style="padding: 5px 8px; border: 1px solid #ccc; border-top: 2px solid #000; text-align: right; color: #C00000;">{{ number_format($totalCredit, 2) }}</td>
                                        <td style="padding: 5px 8px; border: 1px solid #ccc; border-top: 2px solid #000; text-align: right;">{{ number_format($closingBalance, 2) }}</td>
                                        <td style="padding: 5px 8px; border: 1px solid #ccc; border-top: 2px solid #000;"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Footer -->
                        <div style="margin-top: 15px; padding-top: 8px; border-top: 1px solid #ccc; font-size: 8px; color: #888;">
                            <table style="width: 100%;">
                                <tr>
                                    <td>{{ translate('Print Date') }}: {{ now()->format('d-m-Y H:i') }}</td>
                                    <td style="text-align: right;">{{ translate('Generated by') }}: {{ auth()->user()->name ?? 'System' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @else
        <div class="text-center py-5 text-muted">
            <i class="las la-book" style="font-size: 64px; display: block; margin-bottom: 15px;"></i>
            <h5>{{ translate('Cash Book Report') }}</h5>
            <p>{{ translate('Select an account and date range, then click Generate to view the report.') }}</p>
        </div>
        @endif
    </div>
</div>

<style>
@media print {
    body>*:not(#reportContent) {
        display: none !important;
    }
    html, body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }
    #reportContent {
        display: block !important;
        visibility: visible !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 10mm !important;
        transform: none !important;
        box-shadow: none !important;
        min-height: auto !important;
        background: white !important;
    }
    .no-print, .btn, form, select, .card-header, .navbar, .page-side-nav {
        display: none !important;
    }
    @page {
        size: A4 landscape;
        margin: 0;
    }
}
</style>

<script>
function toggleCustomDate(value) {
    document.querySelectorAll('.custom-date').forEach(el => {
        el.style.display = value === 'custom' ? '' : 'none';
    });
}

function zoomReport(value) {
    const reportContent = document.getElementById('reportContent');
    if (reportContent) reportContent.style.transform = 'scale(' + (value / 100) + ')';
}

function printReportDiv() {
    const reportContent = document.getElementById('reportContent');
    if (!reportContent) return;
    const printWindow = window.open('', '_blank', 'width=1100,height=700');
    printWindow.document.write('<!DOCTYPE html><html><head><title>Cash Book Report</title>');
    printWindow.document.write('<style>body{font-family:Arial,sans-serif;margin:0;padding:20px}table{border-collapse:collapse;width:100%}td,th{padding:4px 6px;font-size:11px}@page{size:A4 landscape;margin:10mm}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(reportContent.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}
</script>
@endsection