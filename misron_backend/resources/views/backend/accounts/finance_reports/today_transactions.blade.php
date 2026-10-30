@extends('backend.accounts.layout')

@section('accounts_content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 h6">{{ translate("Today's Transaction Report") }}</h5>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" action="{{ route('accounts.reports.today-transactions') }}" class="mb-4" id="reportForm">
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
                    <span class="mr-2 small text-muted">
                        {{ translate('Date') }}: <strong>{{ \Carbon\Carbon::parse($date ?? now())->format('d M Y') }}</strong>
                    </span>
                    <span class="small text-muted">|</span>
                    <span class="mx-2 small text-muted">{{ translate('Transactions') }}: <strong>{{ $summary['transaction_count'] }}</strong></span>
                    <span class="small text-muted">|</span>
                    <span class="mx-2 small text-muted">{{ translate('Total') }}: <strong>{{ number_format($summary['total_amount'], 2) }}</strong></span>
                    <select class="form-control form-control-sm ml-3" style="width: 100px;" onchange="zoomReport(this.value)">
                        <option value="50">50%</option>
                        <option value="75">75%</option>
                        <option value="100" selected>100%</option>
                        <option value="125">125%</option>
                        <option value="150">150%</option>
                    </select>
                </div>
                <div>
                    <a href="{{ route('accounts.reports.today-transactions', array_merge(request()->all(), ['export' => 'excel'])) }}" 
                       class="btn btn-sm btn-outline-success mr-1" title="Download Excel">
                        <i class="las la-file-excel"></i> Excel
                    </a>
                    <a href="{{ route('accounts.reports.today-transactions', array_merge(request()->all(), ['export' => 'pdf'])) }}" 
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
                                {{ translate("TODAY'S TRANSACTION REPORT") }}
                            </h3>
                            <p style="margin: 3px 0; font-size: 11px;">
                                <strong>{{ translate('Date') }}:</strong> {{ \Carbon\Carbon::parse($date ?? now())->format('d M Y') }}
                                | <strong>{{ translate('Transactions') }}:</strong> {{ $summary['transaction_count'] }}
                                | <strong>{{ translate('Total Amount') }}:</strong> {{ number_format($summary['total_amount'], 2) }}
                            </p>
                        </div>

                        <!-- Summary Cards -->
                        <div style="margin-bottom: 12px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                                <tr>
                                    <td style="width: 25%; padding: 8px; text-align: center; background: #1F4E79; color: #fff; border: 2px solid #fff;">
                                        <div style="font-size: 8px; text-transform: uppercase; opacity: 0.9;">{{ translate('Total Amount') }}</div>
                                        <div style="font-size: 13px; font-weight: bold;">{{ number_format($summary['total_amount'], 2) }}</div>
                                    </td>
                                    <td style="width: 25%; padding: 8px; text-align: center; background: #28a745; color: #fff; border: 2px solid #fff;">
                                        <div style="font-size: 8px; text-transform: uppercase; opacity: 0.9;">{{ translate('Transactions') }}</div>
                                        <div style="font-size: 13px; font-weight: bold;">{{ $summary['transaction_count'] }}</div>
                                    </td>
                                    <td style="width: 25%; padding: 8px; text-align: center; background: #17a2b8; color: #fff; border: 2px solid #fff;">
                                        <div style="font-size: 8px; text-transform: uppercase; opacity: 0.9;">{{ translate('Cash In') }}</div>
                                        <div style="font-size: 13px; font-weight: bold;">{{ number_format($summary['cash_in'], 2) }}</div>
                                    </td>
                                    <td style="width: 25%; padding: 8px; text-align: center; background: #fd7e14; color: #fff; border: 2px solid #fff;">
                                        <div style="font-size: 8px; text-transform: uppercase; opacity: 0.9;">{{ translate('Cash Out') }}</div>
                                        <div style="font-size: 13px; font-weight: bold;">{{ number_format($summary['cash_out'], 2) }}</div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Transactions By Section -->
                        @forelse($sections as $sectionName => $sectionRows)
                        <div style="margin-bottom: 16px;">
                            <div style="background: #1F4E79; color: #fff; padding: 5px 10px; font-size: 11px; font-weight: bold; text-transform: uppercase;">
                                {{ $sectionName }}
                                <span style="float: right; font-weight: normal; font-size: 10px;">
                                    {{ count($sectionRows) }} {{ translate('transaction(s)') }} | {{ translate('Total') }}: {{ number_format(array_sum(array_column($sectionRows, 'amount')), 2) }}
                                </span>
                            </div>
                            <table style="width: 100%; border-collapse: collapse; font-size: 9px;">
                                <thead>
                                    <tr>
                                        <th style="background: #e8eef5; color: #333; padding: 4px 5px; border: 1px solid #ddd; width: 4%;">#</th>
                                        <th style="background: #e8eef5; color: #333; padding: 4px 5px; border: 1px solid #ddd; width: 15%;">{{ translate('Slip No') }}</th>
                                        <th style="background: #e8eef5; color: #333; padding: 4px 5px; border: 1px solid #ddd; width: 25%;">{{ translate('Paid From Account') }}</th>
                                        <th style="background: #e8eef5; color: #333; padding: 4px 5px; border: 1px solid #ddd; width: 25%;">{{ translate('To') }}</th>
                                        <th style="background: #e8eef5; color: #333; padding: 4px 5px; border: 1px solid #ddd; width: 15%; text-align: right;">{{ translate('Amount (৳)') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sectionRows as $key => $row)
                                    <tr style="{{ $key % 2 == 1 ? 'background: #fafafa;' : '' }}">
                                        <td style="padding: 3px 5px; border: 1px solid #ddd; text-align: center;">{{ $key + 1 }}</td>
                                        <td style="padding: 3px 5px; border: 1px solid #ddd;">{{ $row->slip_no }}</td>
                                        <td style="padding: 3px 5px; border: 1px solid #ddd;">{{ $row->from_account }}</td>
                                        <td style="padding: 3px 5px; border: 1px solid #ddd;">{{ $row->to_account }}</td>
                                        <td style="padding: 3px 5px; border: 1px solid #ddd; text-align: right; color: #1F4E79;">{{ number_format($row->amount, 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr style="font-weight: bold; background: #f5f8fb;">
                                        <td colspan="4" style="padding: 4px 8px; border: 1px solid #ddd; text-align: right;">{{ translate('Section Total') }}:</td>
                                        <td style="padding: 4px 8px; border: 1px solid #ddd; text-align: right; color: #1F4E79;">{{ number_format(array_sum(array_column($sectionRows, 'amount')), 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        @empty
                        <div style="text-align: center; padding: 30px; color: #999; font-style: italic;">
                            {{ translate('No transactions found for this date') }}
                        </div>
                        @endforelse

                        <!-- Grand Total -->
                        <div style="margin-bottom: 12px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                                <tr style="font-weight: bold; background: #1F4E79; color: #fff;">
                                    <td style="padding: 6px 10px; text-align: right; font-size: 12px;">
                                        {{ translate('Grand Total') }}: {{ number_format($summary['total_amount'], 2) }}
                                    </td>
                                </tr>
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
            <i class="las la-list-alt" style="font-size: 64px; display: block; margin-bottom: 15px;"></i>
            <h5>{{ translate("Today's Transaction Report") }}</h5>
            <p>{{ translate('Select a date range and click Generate to view the report.') }}</p>
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
    printWindow.document.write('<!DOCTYPE html><html><head><title>Today\'s Transaction Report</title>');
    printWindow.document.write('<style>body{font-family:Arial,sans-serif;margin:0;padding:20px}table{border-collapse:collapse;width:100%}td,th{padding:4px 6px;font-size:11px}@page{size:A4 portrait;margin:10mm}</style>');
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