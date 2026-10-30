@extends('backend.accounts.layout')

@section('accounts_content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 h6">{{ translate('Expense Summary Report') }}</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('accounts.reports.expense-summary') }}" class="mb-4" id="reportForm">
            <div class="form-row align-items-end">
                <div class="col-lg-2 col-md-4 mb-2">
                    <label class="d-block text-muted small">{{ translate('Date Range') }}</label>
                    <select name="date_range" class="form-control form-control-sm" onchange="toggleCustomDate(this.value)">
                        <option value="this_month" {{ $dateRange == 'this_month' ? 'selected' : '' }}>{{ translate('Month of') }} {{ now()->format('F') }}</option>
                        <option value="last_month" {{ $dateRange == 'last_month' ? 'selected' : '' }}>{{ translate('Month of') }} {{ now()->subMonth()->format('F') }}</option>
                        <option value="custom" {{ $dateRange == 'custom' ? 'selected' : '' }}>{{ translate('Custom Date') }}</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 mb-2 custom-date" style="{{ $dateRange == 'custom' ? '' : 'display:none;' }}">
                    <label class="d-block text-muted small">{{ translate('Start Date') }}</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date', $startDate->toDateString()) }}">
                </div>
                <div class="col-lg-2 col-md-4 mb-2 custom-date" style="{{ $dateRange == 'custom' ? '' : 'display:none;' }}">
                    <label class="d-block text-muted small">{{ translate('End Date') }}</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date', $endDate->toDateString()) }}">
                </div>
                <div class="col-lg-3 col-md-6 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-2">
                        <i class="las la-search"></i> {{ translate('Generate') }}
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="resetForm()">
                        <i class="las la-times"></i> {{ translate('Cancel') }}
                    </button>
                </div>
            </div>
        </form>

        @if(count($summaryData) > 0)
        <div class="mt-3">
            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light border rounded no-print">
                <div class="d-flex align-items-center flex-wrap">
                    <span class="mr-2 small text-muted">{{ translate('Period') }}: <strong>{{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}</strong></span>
                    <span class="mr-2 small text-muted">|</span>
                    <span class="mr-2 small text-muted">{{ translate('Heads') }}: <strong>{{ count($summaryData) }}</strong></span>
                    <span class="mr-2 small text-muted">|</span>
                    <span class="mr-2 small text-muted">{{ translate('Grand Total') }}: <strong class="text-danger">{{ number_format($grandTotal, 2) }} ৳</strong></span>
                    <select class="form-control form-control-sm ml-2" style="width: 90px;" onchange="zoomReport(this.value)">
                        <option value="50">50%</option><option value="75">75%</option><option value="100" selected>100%</option><option value="125">125%</option><option value="150">150%</option>
                    </select>
                </div>
                <div>
                    <a href="{{ route('accounts.reports.expense-summary', array_merge(request()->all(), ['export' => 'excel'])) }}" class="btn btn-sm btn-outline-success mr-1"><i class="las la-file-excel"></i></a>
                    <a href="{{ route('accounts.reports.expense-summary', array_merge(request()->all(), ['export' => 'pdf'])) }}" class="btn btn-sm btn-outline-danger mr-1"><i class="las la-file-pdf"></i></a>
                    <button class="btn btn-sm btn-outline-info" onclick="printReportDiv()"><i class="las la-print"></i></button>
                </div>
            </div>

            <div class="border" style="overflow: auto; max-height: 65vh; background: #e9ecef;">
                <div class="d-flex justify-content-center" style="min-width: 800px;">
                    <div id="reportContent" style="transform: scale(1); transform-origin: top center; transition: transform 0.2s; background: white; width: 210mm; padding: 15mm; margin: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); min-height: 297mm;">

                        <div style="text-align: center; margin-bottom: 15px; padding-bottom: 12px; border-bottom: 2px solid #000;">
                            <h2 style="margin: 0; font-weight: bold; font-size: 18px; text-transform: uppercase; color: #1F4E79;">{{ get_setting('site_name', env('APP_NAME', 'Company Name')) }}</h2>
                            <h3 style="margin: 6px 0; font-weight: bold; font-size: 15px;">{{ translate('EXPENSE SUMMARY REPORT') }}</h3>
                            <p style="margin: 3px 0; font-size: 11px; color: #555;">
                                <strong>{{ translate('Period') }}:</strong> {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}
                                | <strong>{{ translate('Grand Total') }}:</strong> <span style="color: #C00000;">{{ number_format($grandTotal, 2) }} ৳</span>
                            </p>
                        </div>

                        <!-- Summary Table -->
                        <div style="margin-bottom: 12px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                <thead>
                                    <tr>
                                        <th style="background: #1a3a5c; color: #fff; padding: 6px 8px; border: 1px solid #1a3a5c; width: 5%; text-align: center;">#</th>
                                        <th style="background: #1a3a5c; color: #fff; padding: 6px 8px; border: 1px solid #1a3a5c; width: 12%;">{{ translate('Code') }}</th>
                                        <th style="background: #1a3a5c; color: #fff; padding: 6px 8px; border: 1px solid #1a3a5c; width: 30%;">{{ translate('Expense Head') }}</th>
                                        <th style="background: #1a3a5c; color: #fff; padding: 6px 8px; border: 1px solid #1a3a5c; width: 10%; text-align: center;">{{ translate('Entries') }}</th>
                                        <th style="background: #1a3a5c; color: #fff; padding: 6px 8px; border: 1px solid #1a3a5c; width: 18%; text-align: right;">{{ translate('Total (৳)') }}</th>
                                        <th style="background: #1a3a5c; color: #fff; padding: 6px 8px; border: 1px solid #1a3a5c; width: 12%; text-align: center;">%</th>
                                        <th style="background: #1a3a5c; color: #fff; padding: 6px 8px; border: 1px solid #1a3a5c; width: 13%;">{{ translate('Bar') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($summaryData as $key => $item)
                                    @php $percentage = $grandTotal > 0 ? round(($item['total'] / $grandTotal) * 100, 1) : 0; @endphp
                                    <tr style="{{ $key % 2 == 0 ? 'background: #f8f9fa;' : 'background: #fff;' }}">
                                        <td style="padding: 5px 8px; border: 1px solid #dee2e6; text-align: center;">{{ $key + 1 }}</td>
                                        <td style="padding: 5px 8px; border: 1px solid #dee2e6; font-size: 11px;">{{ $item['code'] }}</td>
                                        <td style="padding: 5px 8px; border: 1px solid #dee2e6;">{{ $item['name'] }}</td>
                                        <td style="padding: 5px 8px; border: 1px solid #dee2e6; text-align: center;">{{ $item['count'] }}</td>
                                        <td style="padding: 5px 8px; border: 1px solid #dee2e6; text-align: right; font-weight: 600; font-family: 'Courier New', monospace;">{{ number_format($item['total'], 2) }}</td>
                                        <td style="padding: 5px 8px; border: 1px solid #dee2e6; text-align: center; font-weight: 500;">{{ $percentage }}%</td>
                                        <td style="padding: 5px 8px; border: 1px solid #dee2e6;">
                                            <div style="background: #e9ecef; height: 14px; border-radius: 7px; overflow: hidden;">
                                                <div style="background: linear-gradient(90deg, #1a3a5c, #2E75B6); height: 100%; width: {{ $percentage }}%; border-radius: 7px; min-width: 2px;"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr style="font-weight: bold; font-size: 14px; background: #e8f5e9;">
                                        <td colspan="4" style="padding: 10px 12px; border: 2px solid #28a745; text-align: right; color: #155724; text-transform: uppercase;">{{ translate('Grand Total') }} (৳):</td>
                                        <td style="padding: 10px 12px; border: 2px solid #28a745; text-align: right; color: #155724; font-family: 'Courier New', monospace;">{{ number_format($grandTotal, 2) }}</td>
                                        <td style="padding: 10px 12px; border: 2px solid #28a745; text-align: center; color: #155724;">100%</td>
                                        <td style="padding: 10px 12px; border: 2px solid #28a745;"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div style="margin-top: 15px; padding-top: 8px; border-top: 1px solid #dee2e6; font-size: 9px; color: #888;">
                            <table style="width: 100%;"><tr><td><i class="las la-clock"></i> {{ translate('Print Date') }}: {{ now()->format('d M Y, h:i A') }}</td><td style="text-align: right;"><i class="las la-user"></i> {{ translate('Generated by') }}: {{ auth()->user()->name ?? 'System' }}</td></tr></table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @else
        <div class="text-center py-5 text-muted">
            <i class="las la-chart-bar" style="font-size: 64px; display: block; margin-bottom: 15px;"></i>
            <h5>{{ translate('Expense Summary Report') }}</h5>
            <p>{{ translate('Select a date range and click Generate to view the report.') }}</p>
        </div>
        @endif
    </div>
</div>

<style>
@media print {
    body>*:not(#reportContent) { display: none !important; }
    html, body { margin: 0 !important; padding: 0 !important; background: white !important; }
    #reportContent { display: block !important; visibility: visible !important; position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; margin: 0 !important; padding: 10mm !important; transform: none !important; box-shadow: none !important; min-height: auto !important; background: white !important; }
    .no-print, .btn, form, select, .card-header, .navbar, .page-side-nav { display: none !important; }
    @page { size: A4; margin: 0; }
}
</style>

<script>
function toggleCustomDate(value) { document.querySelectorAll('.custom-date').forEach(el => { el.style.display = value === 'custom' ? '' : 'none'; }); }
function zoomReport(value) { const rc = document.getElementById('reportContent'); if (rc) rc.style.transform = 'scale(' + (value / 100) + ')'; }
function resetForm() { window.location.href = "{{ route('accounts.reports.expense-summary') }}"; }
function printReportDiv() {
    const rc = document.getElementById('reportContent'); if (!rc) return;
    const pw = window.open('', '_blank', 'width=900,height=700');
    pw.document.write('<!DOCTYPE html><html><head><title>Expense Summary</title><style>body{font-family:Arial,sans-serif;margin:0;padding:20px}table{border-collapse:collapse;width:100%}th{background:#1a3a5c;color:#fff;padding:6px 8px}td{padding:4px 8px;border:1px solid #dee2e6}@page{size:A4;margin:10mm}</style></head><body>');
    pw.document.write(rc.innerHTML); pw.document.write('</body></html>'); pw.document.close();
    setTimeout(() => { pw.print(); pw.close(); }, 500);
}
</script>
@endsection