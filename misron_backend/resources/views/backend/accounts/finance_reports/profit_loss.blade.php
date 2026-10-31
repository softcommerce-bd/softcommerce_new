@extends('backend.accounts.layout')

@section('accounts_content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 h6">{{ translate('Profit Loss Statement') }}</h5>
        </div>
        <div class="card-body">
    @include('backend.accounts.finance_reports._notice')
    @if (!empty($plBasisGap))
        <div class="alert alert-warning small py-2" role="alert" id="pl-basis-gap">
            <strong>{{ translate('Some income / expense balances are not in this report.') }}</strong>
            {{ translate('This report is built from statement rows. These accounts hold balances that no statement row explains, so their amounts are missing here:') }}
            <ul class="mb-0">@foreach ($plBasisGap as $g)<li>{{ $g['name'] }} ({{ $g['type'] }}): {{ translate('balance') }} {{ number_format($g['balance'], 2) }}, {{ translate('explained by rows') }} {{ number_format($g['explained'], 2) }}, <strong>{{ translate('not in report') }} {{ number_format($g['gap'], 2) }}</strong></li>@endforeach</ul>
        </div>
    @endif
            <!-- Filter Form -->
            <form method="GET" action="{{ route('accounts.reports.profit-loss') }}" class="mb-4" id="reportForm">
                <div class="form-row align-items-end">
                    <div class="col-lg-3 col-md-4 mb-2">
                        <label class="d-block text-muted small">{{ translate('Date Range') }}</label>
                        <select name="date_range" class="form-control" onchange="toggleCustomDate(this.value)">
                            <option value="this_month" {{ $dateRange == 'this_month' ? 'selected' : '' }}>
                                {{ translate('Month of') }} {{ now()->format('F') }}</option>
                            <option value="last_month" {{ $dateRange == 'last_month' ? 'selected' : '' }}>
                                {{ translate('Month of') }} {{ now()->subMonth()->format('F') }}</option>
                            <option value="this_year" {{ $dateRange == 'this_year' ? 'selected' : '' }}>
                                {{ translate('Year of') }} {{ now()->format('Y') }}</option>
                            <option value="custom" {{ $dateRange == 'custom' ? 'selected' : '' }}>
                                {{ translate('Custom Date') }}</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 mb-2 custom-date"
                        style="{{ $dateRange == 'custom' ? '' : 'display:none;' }}">
                        <label class="d-block text-muted small">{{ translate('Start Date') }}</label>
                        <input type="date" name="start_date" class="form-control"
                            value="{{ request('start_date', $startDate->toDateString()) }}">
                    </div>
                    <div class="col-lg-2 col-md-4 mb-2 custom-date"
                        style="{{ $dateRange == 'custom' ? '' : 'display:none;' }}">
                        <label class="d-block text-muted small">{{ translate('End Date') }}</label>
                        <input type="date" name="end_date" class="form-control"
                            value="{{ request('end_date', $endDate->toDateString()) }}">
                    </div>
                    <div class="col-lg-3 col-md-6 mb-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="las la-search"></i> {{ translate('Generate') }}
                        </button>
                        @if (request()->has('date_range') || request()->has('start_date'))
                            <button type="button" onclick="printReportDiv()" class="btn btn-outline-info">
                                <i class="las la-print"></i> {{ translate('Print') }}
                            </button>
                        @endif
                    </div>
                </div>
            </form>

            <!-- Report Viewer Section -->
            @if (request()->has('date_range') || request()->has('start_date'))
                <div class="mt-3">
                    <!-- Toolbar -->
                    <div
                        class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light border rounded no-print">
                        <div class="d-flex align-items-center">
                            <span class="mr-3 small text-muted">
                                {{ translate('Report Generated') }}: {{ now()->format('d-m-Y H:i') }}
                            </span>
                            <select class="form-control form-control-sm mr-2" style="width: 100px;"
                                onchange="zoomReport(this.value)">
                                <option value="50">50%</option>
                                <option value="75">75%</option>
                                <option value="100" selected>100%</option>
                                <option value="125">125%</option>
                                <option value="150">150%</option>
                            </select>
                        </div>
                        <div>
                            <!-- Direct Download Buttons -->
                            <a href="{{ route('accounts.reports.profit-loss', array_merge(request()->all(), ['export' => 'excel'])) }}"
                                class="btn btn-sm btn-outline-success mr-1" title="Download Excel">
                                <i class="las la-file-excel"></i> Excel
                            </a>
                            <a href="{{ route('accounts.reports.profit-loss', array_merge(request()->all(), ['export' => 'pdf'])) }}"
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
                            <div id="reportContent"
                                style="transform: scale(1); transform-origin: top center; transition: transform 0.2s; background: white; width: 210mm; padding: 15mm; margin: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); min-height: 297mm;">

                                <!-- Report Header -->
                                <div
                                    style="text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #000;">
                                    <h2 style="margin: 0; font-weight: bold; font-size: 20px; text-transform: uppercase;">
                                        {{ get_setting('site_name', env('APP_NAME', 'Company Name')) }}
                                    </h2>
                                    <h3 style="margin: 8px 0; font-weight: bold; font-size: 16px;">
                                        {{ translate('PROFIT LOSS STATEMENT') }}
                                    </h3>
                                    <p style="margin: 5px 0; font-size: 13px;">
                                        <strong>{{ translate('Period') }}:</strong>
                                        {{ $startDate->format('d-m-Y') }} {{ translate('to') }}
                                        {{ $endDate->format('d-m-Y') }}
                                    </p>
                                </div>

                                <!-- INCOME SECTION -->
                                <div style="margin-bottom: 15px;">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                        <thead>
                                            <tr>
                                                <th colspan="2"
                                                    style="background: #e8e8e8; padding: 8px 10px; text-align: left; font-size: 14px; border: 1px solid #ccc;">
                                                    {{ translate('INCOMES') }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($incomeData as $income)
                                                <tr>
                                                    <td style="width: 70%; padding: 5px 10px; border: 1px solid #ddd;">
                                                        {{ $income['name'] }}</td>
                                                    <td
                                                        style="width: 30%; padding: 5px 10px; text-align: right; border: 1px solid #ddd;">
                                                        {{ number_format($income['balance'], 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="2"
                                                        style="padding: 10px; text-align: center; color: #999; border: 1px solid #ddd; font-style: italic;">
                                                        {{ translate('No income records found') }}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        <tfoot>
                                            <tr style="font-weight: bold;">
                                                <td
                                                    style="padding: 8px 10px; border: 1px solid #ccc; border-top: 2px solid #000; background: #f5f5f5;">
                                                    {{ translate('Total Income') }} (৳):</td>
                                                <td
                                                    style="padding: 8px 10px; text-align: right; border: 1px solid #ccc; border-top: 2px solid #000; background: #f5f5f5;">
                                                    {{ number_format($totalIncome, 2) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- COST OF SALES SECTION -->
                                <div style="margin-bottom: 15px;">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                        <thead>
                                            <tr>
                                                <th colspan="2"
                                                    style="background: #e8e8e8; padding: 8px 10px; text-align: left; font-size: 14px; border: 1px solid #ccc;">
                                                    {{ translate('COST OF SALES') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($cogsData as $cogs)
                                                <tr>
                                                    <td style="width: 70%; padding: 5px 10px; border: 1px solid #ddd;">
                                                        {{ $cogs['name'] }}</td>
                                                    <td
                                                        style="width: 30%; padding: 5px 10px; text-align: right; border: 1px solid #ddd;">
                                                        {{ number_format($cogs['balance'], 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="2"
                                                        style="padding: 10px; text-align: center; color: #999; border: 1px solid #ddd; font-style: italic;">
                                                        {{ translate('No COGS records found') }}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        <tfoot>
                                            <tr style="font-weight: bold;">
                                                <td
                                                    style="padding: 8px 10px; border: 1px solid #ccc; border-top: 2px solid #000; background: #f5f5f5;">
                                                    {{ translate('Total Cost of Sales') }} (৳):</td>
                                                <td
                                                    style="padding: 8px 10px; text-align: right; border: 1px solid #ccc; border-top: 2px solid #000; background: #f5f5f5;">
                                                    {{ number_format($totalCOGS, 2) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- GROSS PROFIT -->
                                <div
                                    style="margin-bottom: 15px; background: #f0f0f0; padding: 10px; border: 1px solid #ccc; font-size: 14px; font-weight: bold;">
                                    <table style="width: 100%;">
                                        <tr>
                                            <td style="width: 70%;">{{ translate('GROSS PROFIT') }} (৳):</td>
                                            <td style="width: 30%; text-align: right;">{{ number_format($grossProfit, 2) }}
                                            </td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- EXPENSES SECTION -->
                                <div style="margin-bottom: 15px;">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                        <thead>
                                            <tr>
                                                <th colspan="2"
                                                    style="background: #e8e8e8; padding: 8px 10px; text-align: left; font-size: 14px; border: 1px solid #ccc;">
                                                    {{ translate('EXPENSES') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($expenseData as $expense)
                                                <tr>
                                                    <td style="width: 70%; padding: 5px 10px; border: 1px solid #ddd;">
                                                        {{ $expense['name'] }}</td>
                                                    <td
                                                        style="width: 30%; padding: 5px 10px; text-align: right; border: 1px solid #ddd;">
                                                        {{ number_format($expense['balance'], 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="2"
                                                        style="padding: 10px; text-align: center; color: #999; border: 1px solid #ddd; font-style: italic;">
                                                        {{ translate('No expense records found') }}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        <tfoot>
                                            <tr style="font-weight: bold;">
                                                <td
                                                    style="padding: 8px 10px; border: 1px solid #ccc; border-top: 2px solid #000; background: #f5f5f5;">
                                                    {{ translate('Total Expenses') }} (৳):</td>
                                                <td
                                                    style="padding: 8px 10px; text-align: right; border: 1px solid #ccc; border-top: 2px solid #000; background: #f5f5f5;">
                                                    {{ number_format($totalExpenses, 2) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- NET PROFIT / LOSS -->
                                <div
                                    style="padding: 12px; border: 2px solid {{ $netProfit >= 0 ? '#28a745' : '#dc3545' }}; 
                                    background: {{ $netProfit >= 0 ? '#d4edda' : '#f8d7da' }}; font-size: 15px; font-weight: bold;
                                    color: {{ $netProfit >= 0 ? '#155724' : '#721c24' }};">
                                    <table style="width: 100%;">
                                        <tr>
                                            <td style="width: 70%; font-size: 16px;">
                                                {{ $netProfit >= 0 ? strtoupper(translate('Net Profit')) : strtoupper(translate('Net Loss')) }}
                                                (৳):</td>
                                            <td style="width: 30%; text-align: right; font-size: 16px;">
                                                {{ number_format(abs($netProfit), 2) }}</td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- Footer -->
                                <div
                                    style="margin-top: 20px; padding-top: 10px; border-top: 1px solid #ccc; font-size: 10px; color: #888;">
                                    <table style="width: 100%;">
                                        <tr>
                                            <td>{{ translate('Print Date') }}: {{ now()->format('d-m-Y H:i') }}</td>
                                            <td style="text-align: right;">{{ translate('Generated by') }}:
                                                {{ auth()->user()->name ?? 'System' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="las la-chart-bar" style="font-size: 64px; display: block; margin-bottom: 15px;"></i>
                    <h5>{{ translate('Profit Loss Statement') }}</h5>
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

            html,
            body {
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

            .no-print,
            .btn,
            form,
            select,
            .card-header,
            .navbar,
            .page-side-nav {
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
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            printWindow.document.write('<!DOCTYPE html><html><head><title>Profit Loss Statement</title>');
            printWindow.document.write(
                '<style>body{font-family:Arial,sans-serif;margin:0;padding:20px}table{border-collapse:collapse;width:100%}td,th{padding:5px 10px}@page{size:A4;margin:10mm}</style>'
                );
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
