@extends('backend.accounts.layout')

@section('accounts_content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 h6">{{ translate('Trial Balance') }}</h5>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" action="{{ route('accounts.reports.trial-balance') }}" class="mb-4" id="reportForm">
            <div class="form-row align-items-end">
                <div class="col-lg-3 col-md-4 mb-2">
                    <label class="d-block text-muted small">{{ translate('Balance On') }}</label>
                    <select name="date_range" class="form-control" onchange="toggleCustomDate(this.value)">
                        <option value="this_year_end" {{ $dateRange == 'this_year_end' ? 'selected' : '' }}>
                            {{ now()->endOfYear()->format('d F Y') }}</option>
                        <option value="last_year_end" {{ $dateRange == 'last_year_end' ? 'selected' : '' }}>
                            {{ now()->subYear()->endOfYear()->format('d F Y') }}</option>
                        <option value="today" {{ $dateRange == 'today' ? 'selected' : '' }}>{{ translate('Today') }}</option>
                        <option value="custom" {{ $dateRange == 'custom' ? 'selected' : '' }}>{{ translate('Custom Date') }}</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 mb-2 custom-date" style="{{ $dateRange == 'custom' ? '' : 'display:none;' }}">
                    <label class="d-block text-muted small">{{ translate('Balance On') }}</label>
                    <input type="date" name="end_date" class="form-control" value="{{ request('end_date', $endDate->toDateString()) }}">
                </div>
                <div class="col-lg-3 col-md-6 mb-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="las la-search"></i> {{ translate('Generate') }}
                    </button>
                    @if(request()->has('date_range') || request()->has('end_date'))
                    <button type="button" onclick="printReportDiv()" class="btn btn-outline-info">
                        <i class="las la-print"></i> {{ translate('Print') }}
                    </button>
                    @endif
                </div>
            </div>
        </form>

        <!-- Report Viewer Section -->
        @if(request()->has('date_range') || request()->has('end_date'))
        <div class="mt-3">
            <!-- Toolbar -->
            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light border rounded no-print">
                <div class="d-flex align-items-center">
                    <span class="mr-3 small text-muted">
                        {{ translate('As at') }}: <strong>{{ $endDate->format('d M Y') }}</strong>
                    </span>
                    <span class="small text-muted">|</span>
                    <span class="mx-2 small text-muted">{{ translate('Accounts') }}: <strong>{{ count($trialData) }}</strong></span>
                    <select class="form-control form-control-sm ml-3" style="width: 100px;" onchange="zoomReport(this.value)">
                        <option value="50">50%</option>
                        <option value="75">75%</option>
                        <option value="100" selected>100%</option>
                        <option value="125">125%</option>
                        <option value="150">150%</option>
                    </select>
                </div>
                <div>
                    <a href="{{ route('accounts.reports.trial-balance', array_merge(request()->all(), ['export' => 'excel'])) }}" 
                       class="btn btn-sm btn-outline-success mr-1" title="Download Excel">
                        <i class="las la-file-excel"></i> Excel
                    </a>
                    <a href="{{ route('accounts.reports.trial-balance', array_merge(request()->all(), ['export' => 'pdf'])) }}" 
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
                    <div id="reportContent" style="transform: scale(1); transform-origin: top center; transition: transform 0.2s; background: white; width: 210mm; padding: 15mm; margin: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); min-height: 297mm;">

                        <!-- Report Header -->
                        <div style="text-align: center; margin-bottom: 15px; padding-bottom: 12px; border-bottom: 2px solid #000;">
                            <h2 style="margin: 0; font-weight: bold; font-size: 18px; text-transform: uppercase;">
                                {{ get_setting('site_name', env('APP_NAME', 'Company Name')) }}
                            </h2>
                            <h3 style="margin: 6px 0; font-weight: bold; font-size: 15px;">
                                {{ translate('TRIAL BALANCE') }}
                            </h3>
                            <p style="margin: 3px 0; font-size: 12px;">
                                <strong>{{ translate('As at') }}:</strong> {{ $endDate->format('d M Y') }}
                            </p>
                        </div>

                        <!-- Trial Balance Table -->
                        <div style="margin-bottom: 15px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                                <thead>
                                    <tr>
                                        <th style="background: #1F4E79; color: #fff; padding: 5px 6px; border: 1px solid #1F4E79; width: 4%;">#</th>
                                        <th style="background: #1F4E79; color: #fff; padding: 5px 6px; border: 1px solid #1F4E79; width: 32%;">{{ translate('Account / Entry') }}</th>
                                        <th style="background: #1F4E79; color: #fff; padding: 5px 6px; border: 1px solid #1F4E79; width: 14%;">{{ translate('Reference') }}</th>
                                        <th style="background: #1F4E79; color: #fff; padding: 5px 6px; border: 1px solid #1F4E79; width: 16%;">{{ translate('Type') }}</th>
                                        <th style="background: #1F4E79; color: #fff; padding: 5px 6px; border: 1px solid #1F4E79; width: 17%; text-align: right;">{{ translate('Debit (৳)') }}</th>
                                        <th style="background: #1F4E79; color: #fff; padding: 5px 6px; border: 1px solid #1F4E79; width: 17%; text-align: right;">{{ translate('Credit (৳)') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($trialData as $key => $item)
                                    @php
                                        $accTypeLabel = match($item['type']) {
                                            'asset' => translate('Asset'),
                                            'liability' => translate('Liability'),
                                            'income' => translate('Income'),
                                            'expense' => translate('Expense'),
                                            default => $item['type'],
                                        };
                                    @endphp
                                    <!-- Account Header Row -->
                                    <tr style="background: #e8f0fe; font-weight: bold;">
                                        <td style="padding: 5px 6px; border: 1px solid #ccc; text-align: center;">{{ $key + 1 }}</td>
                                        <td style="padding: 5px 6px; border: 1px solid #ccc; color: #1F4E79;">{{ $item['name'] }}</td>
                                        <td style="padding: 5px 6px; border: 1px solid #ccc; color: #666; font-size: 10px;">{{ $item['code'] }}</td>
                                        <td style="padding: 5px 6px; border: 1px solid #ccc; color: #666; font-size: 10px;">{{ $accTypeLabel }}@if(!empty($item['sub_type'])) ({{ str_replace('_', ' ', $item['sub_type']) }})@endif</td>
                                        <td style="padding: 5px 6px; border: 1px solid #ccc; text-align: right; color: #1F4E79;">
                                            {{ $item['debit'] > 0 ? number_format($item['debit'], 2) : '-' }}
                                        </td>
                                        <td style="padding: 5px 6px; border: 1px solid #ccc; text-align: right; color: #C00000;">
                                            {{ $item['credit'] > 0 ? number_format($item['credit'], 2) : '-' }}
                                        </td>
                                    </tr>
                                    <!-- Entry Rows -->
                                    @forelse($item['entries'] as $entry)
                                    <tr style="background: #fff;">
                                        <td style="padding: 3px 6px; border: 1px solid #eee; text-align: center;"></td>
                                        <td style="padding: 3px 6px; border: 1px solid #eee; padding-left: 20px; font-size: 10px; color: #444;">
                                            {{ $entry['description'] }}
                                        </td>
                                        <td style="padding: 3px 6px; border: 1px solid #eee; font-size: 10px; color: #333;">
                                            {{ $entry['reference_no'] }}
                                        </td>
                                        <td style="padding: 3px 6px; border: 1px solid #eee; font-size: 9px; color: #666;">
                                            {{ str_replace('_', ' ', $entry['transaction_type']) }}
                                        </td>
                                        <td style="padding: 3px 6px; border: 1px solid #eee; text-align: right; font-size: 10px; color: #1F4E79;">
                                            {{ $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '-' }}
                                        </td>
                                        <td style="padding: 3px 6px; border: 1px solid #eee; text-align: right; font-size: 10px; color: #C00000;">
                                            {{ $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '-' }}
                                        </td>
                                    </tr>
                                    @empty
                                    <tr style="background: #fafafa;">
                                        <td colspan="6" style="padding: 3px 6px; border: 1px solid #eee; text-align: center; font-size: 10px; color: #999; font-style: italic;">
                                            {{ translate('No individual entries') }}
                                        </td>
                                    </tr>
                                    @endforelse
                                    @empty
                                    <tr>
                                        <td colspan="6" style="padding: 15px; text-align: center; color: #999; border: 1px solid #ddd; font-style: italic;">
                                            {{ translate('No accounts found') }}
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr style="font-weight: bold; font-size: 12px; background: #d4edda;">
                                        <td colspan="4" style="padding: 6px 10px; border: 2px solid #28a745; text-align: right; color: #155724;">
                                            {{ translate('TOTAL') }} (৳):
                                        </td>
                                        <td style="padding: 6px 10px; border: 2px solid #28a745; text-align: right; color: #155724;">
                                            {{ number_format($totalDebit, 2) }}
                                        </td>
                                        <td style="padding: 6px 10px; border: 2px solid #28a745; text-align: right; color: #155724;">
                                            {{ number_format($totalCredit, 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Footer -->
                        <div style="margin-top: 15px; padding-top: 8px; border-top: 1px solid #ccc; font-size: 9px; color: #888;">
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
            <i class="las la-table" style="font-size: 64px; display: block; margin-bottom: 15px;"></i>
            <h5>{{ translate('Trial Balance') }}</h5>
            <p>{{ translate('Select a date and click Generate to view the report.') }}</p>
        </div>
        @endif
    </div>
</div>

<style>
@media print {
    body>*:not(#reportContent) { display: none !important; }
    html, body { margin: 0 !important; padding: 0 !important; background: white !important; }
    #reportContent {
        display: block !important; visibility: visible !important;
        position: absolute !important; left: 0 !important; top: 0 !important;
        width: 100% !important; margin: 0 !important; padding: 10mm !important;
        transform: none !important; box-shadow: none !important;
        min-height: auto !important; background: white !important;
    }
    .no-print, .btn, form, select, .card-header, .navbar, .page-side-nav { display: none !important; }
    @page { size: A4; margin: 0; }
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
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write('<!DOCTYPE html><html><head><title>Trial Balance</title>');
    printWindow.document.write('<style>body{font-family:Arial,sans-serif;margin:0;padding:20px}table{border-collapse:collapse;width:100%}td,th{padding:5px 8px}@page{size:A4;margin:10mm}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(reportContent.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
}
</script>
@endsection