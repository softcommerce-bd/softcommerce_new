@extends('backend.accounts.layout')

@section('accounts_content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 h6">{{ translate('Day Closing Report') }}</h5>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" action="{{ route('accounts.reports.day-closing') }}" class="mb-4" id="reportForm">
            <div class="form-row align-items-end">
                <div class="col-lg-3 col-md-4 mb-2">
                    <label class="d-block text-muted small">{{ translate('Date Range') }}</label>
                    <select name="date_range" class="form-control" onchange="toggleCustomDate(this.value)">
                        <option value="today" {{ $dateRange == 'today' ? 'selected' : '' }}>{{ translate('Today') }}</option>
                        <option value="yesterday" {{ $dateRange == 'yesterday' ? 'selected' : '' }}>{{ translate('Yesterday') }}</option>
                        <option value="custom" {{ $dateRange == 'custom' ? 'selected' : '' }}>{{ translate('Custom Date') }}</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 mb-2 custom-date" style="{{ $dateRange == 'custom' ? '' : 'display:none;' }}">
                    <label class="d-block text-muted small">{{ translate('Date') }}</label>
                    <input type="date" name="start_date" class="form-control" value="{{ request('start_date', $date ?? now()->toDateString()) }}">
                </div>
                <div class="col-lg-3 col-md-6 mb-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="las la-search"></i> {{ translate('Generate') }}
                    </button>
                    @if(request()->has('date_range') || request()->has('date'))
                    <button type="button" onclick="printReportDiv()" class="btn btn-outline-info">
                        <i class="las la-print"></i> {{ translate('Print') }}
                    </button>
                    @endif
                </div>
            </div>
        </form>

        <!-- Report Viewer Section -->
        @if(request()->has('date_range') || request()->has('date'))
        <div class="mt-3">
            <!-- Toolbar -->
            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light border rounded no-print">
                <div class="d-flex align-items-center">
                    <span class="mr-3 small text-muted">
                        {{ translate('Report Generated') }}: {{ now()->format('d-m-Y H:i') }}
                    </span>
                    <span class="mr-2 small text-muted">|</span>
                    <span class="small text-muted">
                        {{ translate('Date') }}: <strong>{{ \Carbon\Carbon::parse($date ?? now())->format('d M Y') }}</strong>
                    </span>
                    <span class="mx-2 small text-muted">|</span>
                    <span class="small text-muted">{{ translate('Transactions') }}: <strong>{{ $transactions->count() }}</strong></span>
                    <select class="form-control form-control-sm ml-3" style="width: 100px;" onchange="zoomReport(this.value)">
                        <option value="50">50%</option>
                        <option value="75">75%</option>
                        <option value="100" selected>100%</option>
                        <option value="125">125%</option>
                        <option value="150">150%</option>
                    </select>
                </div>
                <div>
                    <a href="{{ route('accounts.reports.day-closing', array_merge(request()->all(), ['export' => 'excel'])) }}" 
                       class="btn btn-sm btn-outline-success mr-1" title="Download Excel">
                        <i class="las la-file-excel"></i> Excel
                    </a>
                    <a href="{{ route('accounts.reports.day-closing', array_merge(request()->all(), ['export' => 'pdf'])) }}" 
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
                <div class="d-flex justify-content-center" style="min-width: 900px;">
                    <div id="reportContent" style="transform: scale(1); transform-origin: top center; transition: transform 0.2s; background: white; width: 210mm; padding: 12mm; margin: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); min-height: 297mm;">

                        <!-- Report Header -->
                        <div style="text-align: center; margin-bottom: 15px; padding-bottom: 12px; border-bottom: 2px solid #000;">
                            <h2 style="margin: 0; font-weight: bold; font-size: 18px; text-transform: uppercase;">
                                {{ get_setting('site_name', env('APP_NAME', 'Company Name')) }}
                            </h2>
                            <h3 style="margin: 6px 0; font-weight: bold; font-size: 14px;">
                                {{ translate('DAY CLOSING REPORT') }}
                            </h3>
                            <p style="margin: 3px 0; font-size: 11px;">
                                <strong>{{ translate('Date') }}:</strong> {{ \Carbon\Carbon::parse($date ?? now())->format('d M Y') }}
                                | <strong>{{ translate('Transactions') }}:</strong> {{ $transactions->count() }}
                            </p>
                        </div>

                        <!-- Day Closing Summary -->
                        <div style="margin-bottom: 12px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                                <tr>
                                    <td style="width: 25%; padding: 8px; text-align: center; background: #1F4E79; color: #fff; border: 2px solid #fff;">
                                        <div style="font-size: 8px; text-transform: uppercase; opacity: 0.9;">{{ translate('Opening') }}</div>
                                        <div style="font-size: 13px; font-weight: bold;">{{ number_format($summary['openingBalance'], 2) }}</div>
                                    </td>
                                    <td style="width: 25%; padding: 8px; text-align: center; background: #28a745; color: #fff; border: 2px solid #fff;">
                                        <div style="font-size: 8px; text-transform: uppercase; opacity: 0.9;">{{ translate('Deposit') }}</div>
                                        <div style="font-size: 13px; font-weight: bold;">{{ number_format($summary['deposit'], 2) }}</div>
                                    </td>
                                    <td style="width: 25%; padding: 8px; text-align: center; background: #dc3545; color: #fff; border: 2px solid #fff;">
                                        <div style="font-size: 8px; text-transform: uppercase; opacity: 0.9;">{{ translate('Withdraw') }}</div>
                                        <div style="font-size: 13px; font-weight: bold;">{{ number_format($summary['withdraw'], 2) }}</div>
                                    </td>
                                    <td style="width: 25%; padding: 8px; text-align: center; background: #17a2b8; color: #fff; border: 2px solid #fff;">
                                        <div style="font-size: 8px; text-transform: uppercase; opacity: 0.9;">{{ translate('Closing') }}</div>
                                        <div style="font-size: 13px; font-weight: bold;">{{ number_format($summary['closingBalance'], 2) }}</div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- All Transactions -->
                        <div style="margin-bottom: 12px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 9px;">
                                <thead>
                                    <tr>
                                        <th colspan="8" style="background: #e8e8e8; padding: 6px 10px; text-align: left; font-size: 12px; border: 1px solid #ccc;">
                                            {{ translate('ALL TRANSACTIONS') }}
                                        </th>
                                    </tr>
                                    <tr>
                                        <th style="background: #333; color: #fff; padding: 3px 4px; border: 1px solid #333; width: 3%;">#</th>
                                        <th style="background: #333; color: #fff; padding: 3px 4px; border: 1px solid #333; width: 8%;">{{ translate('Time') }}</th>
                                        <th style="background: #333; color: #fff; padding: 3px 4px; border: 1px solid #333; width: 15%;">{{ translate('Reference') }}</th>
                                        <th style="background: #333; color: #fff; padding: 3px 4px; border: 1px solid #333; width: 18%;">{{ translate('Account') }}</th>
                                        <th style="background: #333; color: #fff; padding: 3px 4px; border: 1px solid #333; width: 12%;">{{ translate('Type') }}</th>
                                        <th style="background: #333; color: #fff; padding: 3px 4px; border: 1px solid #333; width: 12%; text-align: right;">{{ translate('Debit (৳)') }}</th>
                                        <th style="background: #333; color: #fff; padding: 3px 4px; border: 1px solid #333; width: 12%; text-align: right;">{{ translate('Credit (৳)') }}</th>
                                        <th style="background: #333; color: #fff; padding: 3px 4px; border: 1px solid #333; width: 20%;">{{ translate('Description') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transactions as $key => $trans)
                                    <tr style="{{ $key % 2 == 0 ? 'background: #fafafa;' : '' }}">
                                        <td style="padding: 2px 4px; border: 1px solid #ddd; text-align: center;">{{ $key + 1 }}</td>
                                        <td style="padding: 2px 4px; border: 1px solid #ddd;">{{ \Carbon\Carbon::parse($trans->transaction_date)->format('H:i') }}</td>
                                        <td style="padding: 2px 4px; border: 1px solid #ddd;">{{ $trans->reference_no }}</td>
                                        <td style="padding: 2px 4px; border: 1px solid #ddd;">{{ $trans->account->name ?? 'N/A' }}</td>
                                        <td style="padding: 2px 4px; border: 1px solid #ddd; font-size: 8px;">{{ $trans->transaction_type }}</td>
                                        <td style="padding: 2px 4px; border: 1px solid #ddd; text-align: right; color: #1F4E79;">{{ $trans->debit > 0 ? number_format($trans->debit, 2) : '-' }}</td>
                                        <td style="padding: 2px 4px; border: 1px solid #ddd; text-align: right; color: #C00000;">{{ $trans->credit > 0 ? number_format($trans->credit, 2) : '-' }}</td>
                                        <td style="padding: 2px 4px; border: 1px solid #ddd; font-size: 8px;">{{ Str::limit($trans->description, 35) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" style="padding: 15px; text-align: center; color: #999; border: 1px solid #ddd; font-style: italic;">
                                            {{ translate('No transactions found for this date') }}
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr style="font-weight: bold; background: #f5f5f5;">
                                        <td colspan="5" style="padding: 5px 8px; border: 1px solid #ccc; border-top: 2px solid #000; text-align: right;">{{ translate('Total') }}:</td>
                                        <td style="padding: 5px 8px; border: 1px solid #ccc; border-top: 2px solid #000; text-align: right; color: #1F4E79;">{{ $transactions->sum('debit') > 0 ? number_format($transactions->sum('debit'), 2) : '-' }}</td>
                                        <td style="padding: 5px 8px; border: 1px solid #ccc; border-top: 2px solid #000; text-align: right; color: #C00000;">{{ $transactions->sum('credit') > 0 ? number_format($transactions->sum('credit'), 2) : '-' }}</td>
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
            <i class="las la-calendar-check" style="font-size: 64px; display: block; margin-bottom: 15px;"></i>
            <h5>{{ translate('Day Closing Report') }}</h5>
            <p>{{ translate('Select a date and click Generate to view the report.') }}</p>
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
        size: A4;
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
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    printWindow.document.write('<!DOCTYPE html><html><head><title>Day Closing Report</title>');
    printWindow.document.write('<style>body{font-family:Arial,sans-serif;margin:0;padding:20px}table{border-collapse:collapse;width:100%}td,th{padding:4px 6px;font-size:11px}@page{size:A4;margin:10mm}</style>');
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