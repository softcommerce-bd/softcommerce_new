@extends('backend.accounts.layout')

@section('accounts_content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 h6">{{ translate('Balance Sheet') }}</h5>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" action="{{ route('accounts.reports.balance-sheet') }}" class="mb-4" id="reportForm">
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
                    <select class="form-control form-control-sm" style="width: 100px;" onchange="zoomReport(this.value)">
                        <option value="50">50%</option>
                        <option value="75">75%</option>
                        <option value="100" selected>100%</option>
                        <option value="125">125%</option>
                        <option value="150">150%</option>
                    </select>
                </div>
                <div>
                    <a href="{{ route('accounts.reports.balance-sheet', array_merge(request()->all(), ['export' => 'excel'])) }}" 
                       class="btn btn-sm btn-outline-success mr-1" title="Download Excel">
                        <i class="las la-file-excel"></i> Excel
                    </a>
                    <a href="{{ route('accounts.reports.balance-sheet', array_merge(request()->all(), ['export' => 'pdf'])) }}" 
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
                <div class="d-flex justify-content-center" style="min-width: 800px;">
                    <div id="reportContent" style="transform: scale(1); transform-origin: top center; transition: transform 0.2s; background: white; width: 210mm; padding: 15mm; margin: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); min-height: 297mm;">

                        <!-- Report Header -->
                        <div style="text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #000;">
                            <h2 style="margin: 0; font-weight: bold; font-size: 20px; text-transform: uppercase;">
                                {{ get_setting('site_name', env('APP_NAME', 'Company Name')) }}
                            </h2>
                            <h3 style="margin: 8px 0; font-weight: bold; font-size: 16px;">
                                {{ translate('BALANCE SHEET') }}
                            </h3>
                            <p style="margin: 5px 0; font-size: 13px;">
                                <strong>{{ translate('As at') }}:</strong> {{ $endDate->format('d M Y') }}
                            </p>
                        </div>

                        <!-- ASSETS SECTION -->
                        <div style="margin-bottom: 18px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                <thead>
                                    <tr>
                                        <th colspan="2" style="background: #1F4E79; color: #fff; padding: 8px 12px; text-align: left; font-size: 14px; border: 1px solid #1F4E79; text-transform: uppercase;">
                                            {{ translate('ASSETS') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($assetGroups as $group)
                                    <tr>
                                        <td colspan="2" style="background: #f5f5f5; padding: 6px 12px; font-weight: bold; font-size: 12px; border: 1px solid #ddd; color: #555;">
                                            {{ $group['label'] }}
                                        </td>
                                    </tr>
                                    @foreach($group['items'] as $item)
                                    <tr>
                                        <td style="width: 70%; padding: 4px 12px 4px 20px; border: 1px solid #ddd;">{{ $item['name'] }}</td>
                                        <td style="width: 30%; padding: 4px 12px; text-align: right; border: 1px solid #ddd;">{{ number_format($item['balance'], 2) }}</td>
                                    </tr>
                                    @endforeach
                                    <tr style="font-weight: bold; background: #f0f4f8;">
                                        <td style="padding: 6px 12px; border: 1px solid #ccc;">{{ translate('Total') }} {{ $group['label'] }}</td>
                                        <td style="padding: 6px 12px; text-align: right; border: 1px solid #ccc;">{{ number_format($group['total'], 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr style="font-weight: bold; font-size: 14px; background: #d4edda;">
                                        <td style="padding: 10px 12px; border: 2px solid #28a745; color: #155724;">
                                            {{ translate('TOTAL ASSETS') }} (৳):
                                        </td>
                                        <td style="padding: 10px 12px; text-align: right; border: 2px solid #28a745; color: #155724;">
                                            {{ number_format($totalAssets, 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- LIABILITIES & EQUITY SECTION -->
                        <div style="margin-bottom: 18px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                <thead>
                                    <tr>
                                        <th colspan="2" style="background: #C00000; color: #fff; padding: 8px 12px; text-align: left; font-size: 14px; border: 1px solid #C00000; text-transform: uppercase;">
                                            {{ translate('LIABILITIES & EQUITY') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($liabilityGroups as $group)
                                    <tr>
                                        <td colspan="2" style="background: #f5f5f5; padding: 6px 12px; font-weight: bold; font-size: 12px; border: 1px solid #ddd; color: #555;">
                                            {{ $group['label'] }}
                                        </td>
                                    </tr>
                                    @foreach($group['items'] as $item)
                                    <tr>
                                        <td style="width: 70%; padding: 4px 12px 4px 20px; border: 1px solid #ddd;">{{ $item['name'] }}</td>
                                        <td style="width: 30%; padding: 4px 12px; text-align: right; border: 1px solid #ddd;">{{ number_format($item['balance'], 2) }}</td>
                                    </tr>
                                    @endforeach
                                    <tr style="font-weight: bold; background: #f0f4f8;">
                                        <td style="padding: 6px 12px; border: 1px solid #ccc;">{{ translate('Total') }} {{ $group['label'] }}</td>
                                        <td style="padding: 6px 12px; text-align: right; border: 1px solid #ccc;">{{ number_format($group['total'], 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr style="font-weight: bold; font-size: 14px; background: #d4edda;">
                                        <td style="padding: 10px 12px; border: 2px solid #28a745; color: #155724;">
                                            {{ translate('TOTAL LIABILITIES & EQUITY') }} (৳):
                                        </td>
                                        <td style="padding: 10px 12px; text-align: right; border: 2px solid #28a745; color: #155724;">
                                            {{ number_format($totalLiabilities + $totalEquity, 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Footer -->
                        <div style="margin-top: 20px; padding-top: 10px; border-top: 1px solid #ccc; font-size: 10px; color: #888;">
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
            <i class="las la-balance-scale" style="font-size: 64px; display: block; margin-bottom: 15px;"></i>
            <h5>{{ translate('Balance Sheet') }}</h5>
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
    printWindow.document.write('<!DOCTYPE html><html><head><title>Balance Sheet</title>');
    printWindow.document.write('<style>body{font-family:Arial,sans-serif;margin:0;padding:20px}table{border-collapse:collapse;width:100%}td,th{padding:5px 10px}@page{size:A4;margin:10mm}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(reportContent.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
}
</script>
@endsection