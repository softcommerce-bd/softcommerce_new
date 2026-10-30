{{-- resources/views/backend/stock_adjustments/index.blade.php --}}

@extends('backend.layouts.app')

@section('content')
<style>
    /* Print styles */
    @media print {
        body {
            background: white !important;
            color: black !important;
        }
        
        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
        
        .card-header {
            background: #f8f9fa !important;
            color: black !important;
            border-bottom: 2px solid #000 !important;
        }
        
        .table-responsive {
            overflow-x: visible !important;
        }
        
        table {
            width: 100% !important;
            border-collapse: collapse !important;
        }
        
        table th {
            background-color: #d1d5db !important;
            color: #000 !important;
            font-weight: 700 !important;
            border: 1px solid #000 !important;
            padding: 8px !important;
            font-size: 14px !important;
            text-align: left !important;
        }
        
        table td {
            background-color: #fff !important;
            color: #000 !important;
            border: 1px solid #ccc !important;
            padding: 6px 8px !important;
            font-size: 13px !important;
        }
        
        /* Make badge text readable in print */
        .badge {
            color: #000 !important;
            border: 1px solid #000 !important;
            background: transparent !important;
            font-weight: bold !important;
        }
        
        .badge-info {
            background-color: #e3f2fd !important;
        }
        
        .badge-warning {
            background-color: #fff3e0 !important;
        }
        
        /* Hide elements not needed in print */
        .dt-buttons,
        .btn,
        .not-export,
        .aiz-pagination,
        input,
        select,
        .input-group,
        form,
        .aiz-titlebar .text-md-right,
        #per_page,
        .modal,
        .btn-group,
        .btn-icon,
        [data-toggle="modal"],
        [data-target="#viewModal"],
        .col-md-3,
        .col-md-6,
        .card-header .row > div:not(.aiz-titlebar) {
            display: none !important;
        }
        
        /* Show only the title */
        .aiz-titlebar {
            display: block !important;
            text-align: center !important;
        }
        
        .aiz-titlebar .col-md-6 {
            width: 100% !important;
            max-width: 100% !important;
            flex: 0 0 100% !important;
            display: block !important;
        }
        
        .aiz-titlebar .text-md-right {
            display: none !important;
        }
        
        /* Full width for content */
        .col-md-6,
        .col-md-3,
        .col-md-4 {
            width: 100% !important;
            max-width: 100% !important;
            flex: 0 0 100% !important;
        }
        
        /* Title styling */
        .aiz-titlebar h1 {
            color: #000 !important;
            font-size: 18px !important;
            margin-bottom: 10px !important;
            text-align: center !important;
        }
        
        /* Remove card borders for cleaner look */
        .card {
            border: none !important;
        }
        
        .card-body {
            padding: 0 !important;
        }
        
        /* Add page margins */
        @page {
            margin: 10mm;
            size: landscape;
        }
    }
    
    /* Normal screen styles for lighter text */
    .table th {
        font-weight: 600;
        color: #333;
    }
    
    .badge-info {
        background-color: #e3f2fd;
        color: #1565c0;
    }
    
    .badge-warning {
        background-color: #fff3e0;
        color: #e65100;
    }
</style>

<div class="">

    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="h3">{{ translate('All Stock Adjustments') }}</h1>
            </div>
            <div class="col-md-6 text-md-right">
                <a href="{{ route('stock-adjustments.create') }}" class="btn btn-primary">
                    <i class="las la-plus"></i> {{ translate('Add New Stock Adjustment') }}
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="row w-100">
                <div class="col-md-3">
                    <form action="{{ route('stock-adjustments.index') }}" method="GET">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search"
                                placeholder="{{ translate('Search by reference no...') }}" value="{{ $search ?? '' }}">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="la la-search"></i>
                                </button>
                                @if ($search)
                                    <a href="{{ route('stock-adjustments.index') }}" class="btn btn-outline-secondary">
                                        <i class="la la-times"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-md-6">
                    <div class="dt-buttons btn-group">
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('stock-adjustments.export-csv', request()->all()) }}">
                            <span><i class="fa fa-file-csv" aria-hidden="true"></i> {{ translate('Export CSV') }}</span>
                        </a>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('stock-adjustments.export-excel', request()->all()) }}">
                            <span><i class="fa fa-file-excel" aria-hidden="true"></i> {{ translate('Export Excel') }}</span>
                        </a>
                        <a class="btn btn-outline-secondary btn-sm" href="#" onclick="printStockAdjustments(); return false;">
                            <span><i class="fa fa-print" aria-hidden="true"></i> {{ translate('Print') }}</span>
                        </a>
                        <a class="btn btn-outline-secondary btn-sm" href="#" onclick="toggleColumnVisibility(); return false;">
                            <span><i class="fa fa-columns" aria-hidden="true"></i> {{ translate('Column visibility') }}</span>
                        </a>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('stock-adjustments.export-pdf', request()->all()) }}">
                            <span><i class="fa fa-file-pdf" aria-hidden="true"></i> {{ translate('Export PDF') }}</span>
                        </a>
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-control" id="per_page" onchange="changePerPage(this.value)">
                        <option value="25" {{ $per_page == 25 ? 'selected' : '' }}>
                            {{ translate('Show 25 entries') }}</option>
                        <option value="50" {{ $per_page == 50 ? 'selected' : '' }}>
                            {{ translate('Show 50 entries') }}</option>
                        <option value="100" {{ $per_page == 100 ? 'selected' : '' }}>
                            {{ translate('Show 100 entries') }}</option>
                        <option value="200" {{ $per_page == 200 ? 'selected' : '' }}>
                            {{ translate('Show 200 entries') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table aiz-table mb-0" id="stock_adjustment_table">
                    <thead>
                        <tr>
                            <th class="not-export" style="width: 40px;">SL</th>
                            <th style="width: 140px;">{{ translate('Date') }}</th>
                            <th style="width: 120px;">{{ translate('Reference No') }}</th>
                            <th style="width: 100px;">{{ translate('Type') }}</th>
                            <th style="width: 110px;">{{ translate('Total Amount') }}</th>
                            {{-- <th style="width: 110px;">{{ translate('Recovered') }}</th> --}}
                            <th style="width: 150px;">{{ translate('Reason') }}</th>
                            <th style="width: 100px;">{{ translate('Added By') }}</th>
                            <th class="not-export text-right" style="width: 90px;">{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stock_adjustments as $key => $stock_adjustment)
                            <tr>
                                <td class="not-export">{{ ($stock_adjustments->currentPage() - 1) * $stock_adjustments->perPage() + $key + 1 }}</td>
                                <td>{{ $stock_adjustment->transaction_date->format('m/d/Y H:i') }}</td>
                                <td>{{ $stock_adjustment->reference_no }}</td>
                                <td>
                                    <span class="badge badge-inline {{ $stock_adjustment->adjustment_type == 'normal' ? 'badge-info' : 'badge-warning' }}">
                                        {{ ucfirst($stock_adjustment->adjustment_type) }}
                                    </span>
                                </td>
                                <td>{{ single_price($stock_adjustment->total_amount) }}</td>
                                {{-- <td>{{ single_price($stock_adjustment->total_amount_recovered) }}</td> --}}
                                <td>{{ Str::limit($stock_adjustment->additional_notes, 30) }}</td>
                                <td>{{ $stock_adjustment->creator->name ?? 'N/A' }}</td>
                                <td class="not-export text-right">
                                    <button type="button" class="btn btn-aiz-info btn-icon btn-sm" data-toggle="modal"
                                        data-target="#viewModal"
                                        data-url="{{ route('stock-adjustments.show', $stock_adjustment->id) }}"
                                        title="{{ translate('View') }}">
                                        <i class="las la-eye"></i>
                                    </button>
                                    {{--<button type="button" class="btn btn-aiz-danger btn-icon btn-sm"
                                        onclick="deleteStockAdjustment({{ $stock_adjustment->id }})"
                                        title="{{ translate('Delete') }}">
                                        <i class="las la-trash"></i>
                                    </button>--}}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center">{{ translate('No stock adjustments found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="aiz-pagination mt-3">
                {{ $stock_adjustments->appends(['search' => $search, 'per_page' => $per_page])->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Column Visibility Modal -->
<div class="modal fade" id="columnVisibilityModal" tabindex="-1" role="dialog" aria-labelledby="columnVisibilityModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="columnVisibilityModalLabel">{{ translate('Toggle Column Visibility') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="column_checkboxes"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalLabel">{{ translate('Stock Adjustment Details') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="text-center">
                    <i class="las la-spinner la-spin la-3x"></i>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
    <script type="text/javascript">
        function changePerPage(value) {
            let url = new URL(window.location.href);
            url.searchParams.set('per_page', value);
            window.location.href = url.toString();
        }

        function deleteStockAdjustment(id) {
            aiz.plugins.confirm({
                title: '{{ translate('Are you sure?') }}',
                content: '{{ translate('You want to delete this stock adjustment? This action cannot be undone.') }}',
                type: 'danger',
                onConfirm: function() {
                    $.ajax({
                        url: '{{ url('admin/stock-adjustments') }}/' + id,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                aiz.plugins.notify('success', response.message);
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);
                            } else {
                                aiz.plugins.notify('danger', response.message);
                            }
                        },
                        error: function(xhr) {
                            aiz.plugins.notify('danger',
                                '{{ translate('An error occurred while deleting.') }}');
                        }
                    });
                }
            });
        }

        // Column Visibility Function
        function toggleColumnVisibility() {
            var table = document.getElementById('stock_adjustment_table');
            var headers = table.getElementsByTagName('th');
            var checkboxesHtml = '';
            
            for (var i = 0; i < headers.length; i++) {
                var headerText = headers[i].innerText.trim();
                if (headerText && !headers[i].classList.contains('not-export')) {
                    var isChecked = headers[i].style.display !== 'none' ? 'checked' : '';
                    checkboxesHtml += '<div class="form-check">';
                    checkboxesHtml += '<input class="form-check-input column-toggle" type="checkbox" value="' + i + '" id="col_' + i + '" ' + isChecked + '>';
                    checkboxesHtml += '<label class="form-check-label" for="col_' + i + '">' + headerText + '</label>';
                    checkboxesHtml += '</div>';
                }
            }
            
            $('#column_checkboxes').html(checkboxesHtml);
            $('#columnVisibilityModal').modal('show');
        }

        // Handle column visibility changes
        $(document).on('change', '.column-toggle', function() {
            var columnIndex = $(this).val();
            var isChecked = $(this).is(':checked');
            
            $('#stock_adjustment_table th:nth-child(' + (parseInt(columnIndex) + 1) + ')').toggle(isChecked);
            $('#stock_adjustment_table td:nth-child(' + (parseInt(columnIndex) + 1) + ')').toggle(isChecked);
        });

        // Print Function - Opens clean print window
        function printStockAdjustments() {
            var printWindow = window.open('', '_blank', 'width=1200,height=800');
            
            // Get table
            var table = document.getElementById('stock_adjustment_table');
            
            // Clone the table
            var clonedTable = table.cloneNode(true);
            
            // Remove not-export columns and action column
            var rows = clonedTable.querySelectorAll('tr');
            rows.forEach(function(row) {
                var cells = row.querySelectorAll('th, td');
                // Remove from last to first to avoid index issues
                for (var i = cells.length - 1; i >= 0; i--) {
                    if (cells[i].classList.contains('not-export')) {
                        cells[i].remove();
                    }
                }
            });
            
            var title = document.querySelector('.aiz-titlebar h1').innerText || 'Stock Adjustments';
            var currentDate = new Date().toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            printWindow.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + title + '</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('* { margin: 0; padding: 0; box-sizing: border-box; }');
            printWindow.document.write('body { font-family: Arial, Helvetica, sans-serif; padding: 20px; color: #000; }');
            printWindow.document.write('.print-header { text-align: center; margin-bottom: 20px; }');
            printWindow.document.write('.print-header h2 { font-size: 20px; margin-bottom: 5px; color: #000; }');
            printWindow.document.write('.print-header p { font-size: 12px; color: #555; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 10px; }');
            printWindow.document.write('th { background-color: #d1d5db !important; color: #000 !important; font-weight: bold !important; border: 1px solid #000 !important; padding: 8px 10px; font-size: 13px; text-align: left; -webkit-print-color-adjust: exact; print-color-adjust: exact; }');
            printWindow.document.write('td { color: #000 !important; border: 1px solid #ccc !important; padding: 6px 10px; font-size: 12px; }');
            printWindow.document.write('tr:nth-child(even) td { background-color: #f9f9f9; }');
            printWindow.document.write('.badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }');
            printWindow.document.write('.badge-info { background-color: #e3f2fd; color: #1565c0; border: 1px solid #1565c0; }');
            printWindow.document.write('.badge-warning { background-color: #fff3e0; color: #e65100; border: 1px solid #e65100; }');
            printWindow.document.write('@media print { body { margin: 0; } @page { size: landscape; margin: 10mm; } }');
            printWindow.document.write('</style></head><body>');
            printWindow.document.write('<div class="print-header"><h2>' + title + '</h2><p>Print Date: ' + currentDate + '</p></div>');
            printWindow.document.write(clonedTable.outerHTML);
            printWindow.document.write('</body></html>');
            
            printWindow.document.close();
            
            // Print after everything is loaded
            setTimeout(function() {
                printWindow.print();
                // Don't close immediately so user can see preview
                setTimeout(function() {
                    printWindow.close();
                }, 2000);
            }, 500);
        }

        // Export CSV Function (client-side fallback)
        function exportToCSV() {
            var table = document.getElementById('stock_adjustment_table');
            var rows = table.querySelectorAll('tr');
            var csv = [];
            
            // Header row
            var headerRow = [];
            var headerCells = rows[0].querySelectorAll('th');
            headerCells.forEach(function(cell) {
                if (!cell.classList.contains('not-export')) {
                    headerRow.push('"' + cell.innerText.trim().replace(/"/g, '""') + '"');
                }
            });
            csv.push(headerRow.join(','));
            
            // Data rows
            for (var i = 1; i < rows.length; i++) {
                var row = [];
                var cells = rows[i].querySelectorAll('td');
                cells.forEach(function(cell) {
                    if (!cell.classList.contains('not-export') && cell.style.display !== 'none') {
                        var text = cell.innerText.trim().replace(/"/g, '""');
                        // Format date to prevent ### in Excel
                        row.push('"' + text + '"');
                    }
                });
                if (row.length > 0) {
                    csv.push(row.join(','));
                }
            }
            
            // Add BOM for UTF-8
            var BOM = '\uFEFF';
            var csvContent = BOM + csv.join('\n');
            
            var blob = new Blob([csvContent], {type: 'text/csv;charset=utf-8;'});
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'stock_adjustments_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        $(document).ready(function() {
            $('#viewModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var url = button.data('url');
                var modal = $(this);

                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        modal.find('#viewModalBody').html(response);
                    },
                    error: function() {
                        modal.find('#viewModalBody').html(
                            '<div class="alert alert-danger">{{ translate('Error loading data') }}</div>'
                        );
                    }
                });
            });
        });
    </script>
@endsection