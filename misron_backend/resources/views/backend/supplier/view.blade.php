@extends('backend.layouts.app')

@section('content')
    <div class="card">
        <div class="card-body">
            <span id="view_contact_page"></span>
            <div class="row">
                <div class="col-md-12">
                    <div class="row">
                        <div class="col-sm-3">
                            {{-- @dd($supplier); --}}
                            <h3 class="profile-username">
                                <i class="fas fa-user"></i> {{ $supplier->business_name }},
                                <small>Supplier</small>
                            </h3>
                            <br>
                            <strong><i class="fa fa-map-marker mr-2"></i> Address</strong>
                            <p class="text-muted">{{ $supplier->address }}</p>
                            <strong><i class="fa fa-briefcase mr-2"></i> Business Name</strong>
                            <p class="text-muted">{{ $supplier->business_name }}</p>
                            <strong><i class="fa fa-mobile mr-2"></i> Mobile</strong>
                            <p class="text-muted">{{ $supplier->mobile }}</p>
                        </div>
                        <div class="col-sm-3 mt-4">
                            <!-- Empty column for spacing or future content -->
                        </div>
                        <div class="col-sm-3 mt-4">
                            <strong><i class="fa fa-info mr-2"></i> Tax number: {{ $supplier->tax_number }}</strong>
                            <p class="text-muted"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- supplier wise all product show  --}}

    <section>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0 h6">{{ translate('Supplier Products') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0" id="myTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>{{ translate('Supplier Name') }}</th>
                                        <th>{{ translate('Product Name') }}</th>
                                        <th>{{ translate('Purchase Quantity') }}</th>
                                        <th>{{ translate('Buying Unit Cost') }}</th>
                                        <th>{{ translate('Total Cost') }}</th>
                                        <th>{{ translate('Tax Number') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($supplierWisePurchased as $purchase)
                                        @foreach ($purchase->products as $key => $product)
                                            <tr>
                                                <td>{{ $key + 1 }}</td>
                                                <td>{{ $supplier->business_name }}</td>
                                                <td>{{ $product->product_name }}</td>
                                                <td>{{ $product->purchase_quantity }}</td>
                                                <td>{{ $product->buying_unit_cost }}</td>
                                                <td>{{ $product->total_cost }}</td>
                                                <td>{{ $product->product_tax }}</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                    @if (!$supplierWisePurchased)
                                        <tr>
                                            <td colspan="17" class="text-center">{{ translate('No Supplier available') }}
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>



    <section>
        <div class="row border p-4 rounded">
            <div class="col-md-12">
                <div class="nav-tabs-custom">

                    <ul class="nav nav-tabs nav-justified">
                        <li class="nav-item">
                            <a class="nav-link active" href="#ledger_tab" data-toggle="tab" aria-expanded="true">
                                <i class="fas fa-scroll" aria-hidden="true"></i> Ledger
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#purchases_tab" data-toggle="tab" aria-expanded="false">
                                <i class="fas fa-arrow-circle-down" aria-hidden="true"></i> Purchases
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#stock_report_tab" data-toggle="tab" aria-expanded="false">
                                <i class="fas fa-hourglass-half" aria-hidden="true"></i> Stock Report
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#documents_and_notes_tab" data-toggle="tab" aria-expanded="false">
                                <i class="fas fa-paperclip" aria-hidden="true"></i> Documents &amp; Note
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#payments_tab" data-toggle="tab" aria-expanded="false">
                                <i class="fas fa-money-bill-alt" aria-hidden="true"></i> Payments
                            </a>
                        </li>
                        {{-- <li class="nav-item">
                            <a class="nav-link" href="#activities_tab" data-toggle="tab" aria-expanded="false">
                                <i class="fas fa-pen-square" aria-hidden="true"></i> Activities
                            </a>
                        </li> --}}
                    </ul>


                    <div class="tab-content">

                        <!-- Include ledger_tables.blade.php -->
                        <div class="tab-pane active mt-4" id="ledger_tab">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="ledger_date_range">Date Range:</label>
                                                <input placeholder="Select a date range"
                                                    class="form-control ledger_date_range" name="ledger_date_range"
                                                    type="text" id="ledger_date_range">
                                                <button type="button" id="fetch_data" class="btn btn-primary mt-2">Fetch
                                                    Data</button>
                                            </div>
                                        </div>

                                        <div class="offset-2 col-md-4">
                                            <div class="form-group">
                                                <label for="ledger_location">Business Location: Awesome shop</label>
                                            </div>
                                        </div>
                                        <input type="hidden" id="supplier_id" value="{{ $supplier->id }}">
                                        <div class="col-md-2 text-right">
                                            {{-- <button type="button" class="btn btn-outline-secondary btn-sm"
                                                id="send_ledger">
                                                <i class="fas fa-envelope"></i>
                                            </button> --}}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="tables_container">
                                @include('backend.supplier.ledger')
                            </div>
                        </div>


                        <div class="tab-pane fade" id="purchases_tab">
                            @include('backend.supplier.purchase')
                        </div>
                        <div class="tab-pane fade" id="stock_report_tab">
                            @include('backend.supplier.stock')
                        </div>
                        <div class="tab-pane fade" id="documents_and_notes_tab">
                            @include('backend.supplier.document')
                        </div>
                        <div class="tab-pane fade" id="payments_tab">
                            @include('backend.supplier.payment')
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Add these in your head section or before the scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize ledger daterangepicker
            $('#ledger_date_range').daterangepicker({
                opens: 'left',
                locale: {
                    format: 'MM/DD/YYYY'
                }
            });

            // Ledger fetch button
            $('#fetch_data').on('click', function() {
                var dateRange = $('#ledger_date_range').val();
                if (!dateRange) return;

                var [startDate, endDate] = dateRange.split(' - ');
                var supplierId = $('#supplier_id').val();

                $.ajax({
                    url: "{{ route('ledger_data') }}",
                    type: 'GET',
                    data: {
                        start_date: startDate,
                        end_date: endDate,
                        supplier_id: supplierId
                    },
                    success: function(response) {
                        $('#tables_container').html(response.tablesHtml);
                    },
                    error: function(xhr) {
                        console.error('Ledger AJAX error:', xhr.responseText);
                    }
                });
            });

            // Print functionality for all tabs
            $(document).on('click', '.print_tab_pdf', function(e) {
                e.preventDefault();
                printTabContent($(this));
            });

            // Download PDF functionality for all tabs
            $(document).on('click', '.download_tab_pdf', function(e) {
                e.preventDefault();
                downloadTabAsPDF($(this));
            });

            // Also handle the ledger print button (which has different ID)
            $('#print_ledger_pdf').on('click', function(e) {
                e.preventDefault();
                $(this).addClass('print_tab_pdf').trigger('click');
            });
        });

        // Function to print tab content
        function printTabContent($button) {
            let $tab = $button.closest('.tab-pane');
            let tabId = $tab.attr('id');
            let tabName = tabId.replace('_tab', '').replace('_', ' ').toUpperCase();

            // Clone the content
            let $printContent = $tab.find('.print-area').clone();

            // For ledger tab, we need to include the whole ledger structure
            if (tabId === 'ledger_tab') {
                // Get the entire ledger content including summary
                $printContent = $('#tables_container').clone();
            }

            // Clean up DataTables if present
            $printContent.find('table').each(function() {
                let $table = $(this);
                if ($.fn.DataTable.isDataTable($table)) {
                    $table.DataTable().destroy();
                    $table.removeClass('dataTable');
                }

                // Remove DataTables wrapper if exists
                $table.closest('.dataTables_wrapper').replaceWith(function() {
                    return $table;
                });
            });

            // Create print window
            let printWindow = window.open('', '_blank', 'width=1200,height=800');

            // Build the print document
            let printDoc = `
    <!DOCTYPE html>
    <html>
    <head>
        <title>Supplier ${tabName} Report - {{ $supplier->business_name }}</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 10px;
                color: #000;
                background: #fff;
                padding: 10px;
            }
            
            .print-header {
                text-align: center;
                margin-bottom: 15px;
                padding-bottom: 5px;
                border-bottom: 1px solid #000;
            }
            
            .print-header h1 {
                font-size: 14px;
                font-weight: bold;
                margin-bottom: 3px;
            }
            
            .print-header .subtitle {
                font-size: 11px;
                margin-bottom: 3px;
            }
            
            .print-header .date {
                font-size: 10px;
                color: #666;
            }
            
            /* Table Styling - COMPACT */
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 10px;
            }
            
            table th, table td {
                border: 1px solid #000 !important;
                padding: 4px 3px;
                text-align: center;
                font-size: 9px;
                line-height: 1.2;
            }
            
            table th {
                font-weight: bold;
                background-color: #f0f0f0;
                padding: 5px 3px;
                font-size: 9px;
            }
            
            .text-right {
                text-align: right !important;
            }
            
            .text-center {
                text-align: center !important;
            }
            
            .text-left {
                text-align: left !important;
            }
            
            @media print {
                body {
                    padding: 5px !important;
                    font-size: 8px !important;
                }
                
                table th, table td {
                    padding: 3px 2px !important;
                    font-size: 8px !important;
                }
                
                .print-header {
                    margin-bottom: 10px !important;
                    padding-bottom: 3px !important;
                }
                
                .print-header h1 {
                    font-size: 12px !important;
                }
                
                @page {
                    size: A4 landscape;
                    margin: 0.3cm;
                }
            }
        </style>
    </head>
    <body>
        <div class="print-header">
            <h1>${tabName} REPORT</h1>
            <div class="subtitle">Supplier: {{ $supplier->business_name }}</div>
            <div class="date">Report Date: ${new Date().toLocaleDateString('en-US')}</div>
        </div>
        
        ${$printContent.html()}
        
        <div style="text-align: center; margin-top: 10px; font-size: 8px; color: #666;">
            Printed from {{ config('app.name', 'Laravel') }} on ${new Date().toLocaleString()}
        </div>
        <script>
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
                
                window.onafterprint = function() {
                    setTimeout(function() {
                        window.close();
                    }, 100);
                };
            }
        <\/script>
    </body>
    </html>`;

            printWindow.document.open();
            printWindow.document.write(printDoc);
            printWindow.document.close();
        }

        // Function to download tab as PDF with jspdf-autotable for multi-page support
        function downloadTabAsPDF($button) {
            let $tab = $button.closest('.tab-pane');
            let tabId = $tab.attr('id');
            let tabName = tabId.replace('_tab', '').replace('_', ' ').toUpperCase();

            // Show loading indicator
            let originalHtml = $button.html();
            $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generating PDF...');

            // For ledger tab, we need to get the data directly from the table
            if (tabId === 'ledger_tab') {
                downloadLedgerAsPDF($button, $tab);
                return;
            }

            // For other tabs, use the original logic
            downloadOtherTabAsPDF($button, $tab, tabName);
        }

        // Special function for ledger tab
        function downloadLedgerAsPDF($button, $tab) {
            let tabName = 'LEDGER';

            try {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF('landscape');

                // Add compact header
                doc.setFontSize(12);
                doc.text(`${tabName} REPORT`, 14, 10);
                doc.setFontSize(9);
                doc.text(`Supplier: {{ $supplier->business_name }}`, 14, 18);
                doc.text(`Report Date: ${new Date().toLocaleDateString('en-US')}`, 14, 24);

                // Get the main ledger table
                let $mainTable = $('#invoices_table');

                if ($mainTable.length === 0) {
                    // Try to find any table in the ledger tab
                    $mainTable = $tab.find('table').last();
                }

                // Extract table data
                let head = [];
                let body = [];

                // Get table headers
                $mainTable.find('thead tr').each(function() {
                    let row = [];
                    $(this).find('th').each(function() {
                        row.push($(this).text().trim());
                    });
                    if (row.length > 0) head = row;
                });

                // Get table body rows
                $mainTable.find('tbody tr').each(function() {
                    let row = [];
                    $(this).find('td').each(function() {
                        // Get cell content
                        let cellContent = $(this).text().trim();
                        // Remove any extra whitespace
                        cellContent = cellContent.replace(/\s+/g, ' ');
                        row.push(cellContent);
                    });
                    if (row.length > 0) body.push(row);
                });

                // Check if we have data
                if (head.length === 0 || body.length === 0) {
                    // If no data found in the main table, try to get data from DataTable if it exists
                    if ($.fn.DataTable.isDataTable($mainTable)) {
                        let dataTable = $mainTable.DataTable();
                        head = dataTable.columns().header().toArray().map(th => $(th).text().trim());
                        body = dataTable.data().toArray().map(row => {
                            return Object.values(row).map(cell => {
                                if (cell === null || cell === undefined) return '';
                                return String(cell).trim();
                            });
                        });
                    }

                    if (head.length === 0 || body.length === 0) {
                        alert("No data found in the table");
                        $button.prop('disabled', false).html(originalHtml);
                        return;
                    }
                }

                // Calculate dynamic column widths based on content
                let columnStyles = {};
                let totalColumns = head.length;

                // Set compact column widths (auto adjust)
                for (let i = 0; i < totalColumns; i++) {
                    columnStyles[i] = {
                        cellWidth: 'auto',
                        minCellWidth: 20, // Minimum width for each column
                        maxCellWidth: 80 // Maximum width for each column
                    };
                }

                // Generate PDF with autoTable - COMPACT VERSION
                doc.autoTable({
                    head: [head],
                    body: body,
                    startY: 28, // Reduced from 45 to 28
                    theme: 'grid',
                    styles: {
                        fontSize: 7, // Smaller font size
                        cellPadding: 1.5, // Reduced padding
                        lineWidth: 0.1,
                        lineColor: [0, 0, 0],
                        valign: 'middle'
                    },
                    headStyles: {
                        fillColor: [220, 220, 220],
                        textColor: [0, 0, 0],
                        fontStyle: 'bold',
                        fontSize: 8, // Slightly larger for headers
                        cellPadding: 2,
                        lineWidth: 0.1,
                        lineColor: [0, 0, 0],
                        halign: 'center'
                    },
                    bodyStyles: {
                        fontSize: 7,
                        cellPadding: 1.5,
                        lineWidth: 0.1,
                        lineColor: [0, 0, 0],
                        minCellHeight: 6, // Reduced row height
                        valign: 'middle'
                    },
                    columnStyles: columnStyles,
                    margin: {
                        top: 28,
                        right: 5,
                        bottom: 5,
                        left: 5
                    },
                    tableWidth: 'auto',
                    showHead: 'firstPage',
                    rowPageBreak: 'auto',
                    didDrawPage: function(data) {
                        // Add page numbers
                        let pageCount = doc.internal.getNumberOfPages();
                        let currentPage = data.pageNumber;

                        // Compact footer
                        doc.setFontSize(6);
                        doc.text(
                            `Page ${currentPage} of ${pageCount}`,
                            doc.internal.pageSize.width / 2,
                            doc.internal.pageSize.height - 7, {
                                align: 'center'
                            }
                        );
                        doc.text(
                            `Generated from {{ config('app.name', 'Laravel') }}`,
                            doc.internal.pageSize.width / 2,
                            doc.internal.pageSize.height - 3, {
                                align: 'center'
                            }
                        );
                    },
                    // Override default row height calculation
                    didParseCell: function(data) {
                        // Force compact row height
                        if (data.section === 'body') {
                            data.row.height = 6; // Force row height
                        }
                    },
                    // Optimize for more rows per page
                    pageBreak: 'avoid',
                    overflow: 'linebreak'
                });

                // Save the PDF
                const fileName = `ledger_report_${new Date().toISOString().split('T')[0]}.pdf`;
                doc.save(fileName);

                // Re-enable button
                $button.prop('disabled', false).html('<i class="fas fa-download"></i>');

            } catch (error) {
                console.error('Error generating PDF:', error);
                alert('Error generating PDF: ' + error.message);
                $button.prop('disabled', false).html('<i class="fas fa-download"></i>');
            }
        }

        // Function for other tabs (stock, payment, etc.)
        function downloadOtherTabAsPDF($button, $tab, tabName) {
            // Clone the content
            let $printContent = $tab.find('.print-area').clone();

            // Clean up DataTables if present
            $printContent.find('table').each(function() {
                let $table = $(this);
                if ($.fn.DataTable.isDataTable($table)) {
                    $table.DataTable().destroy();
                    $table.removeClass('dataTable');
                }

                // Remove DataTables wrapper if exists
                $table.closest('.dataTables_wrapper').replaceWith(function() {
                    return $table;
                });
            });

            // Get the main table from the print content
            let $mainTable = $printContent.find('table').first();

            if ($mainTable.length === 0) {
                alert("No table found to export");
                $button.prop('disabled', false).html('<i class="fas fa-download"></i>');
                return;
            }

            try {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF('landscape');

                // Add compact header
                doc.setFontSize(12);
                doc.text(`${tabName} REPORT`, 14, 10);
                doc.setFontSize(9);
                doc.text(`Supplier: {{ $supplier->business_name }}`, 14, 18);
                doc.text(`Report Date: ${new Date().toLocaleDateString('en-US')}`, 14, 24);

                // Extract table data
                let head = [];
                let body = [];

                // Get table headers
                $mainTable.find('thead tr').each(function() {
                    let row = [];
                    $(this).find('th').each(function() {
                        row.push($(this).text().trim());
                    });
                    if (row.length > 0) head = row;
                });

                // Get table body rows
                $mainTable.find('tbody tr').each(function() {
                    let row = [];
                    $(this).find('td').each(function() {
                        // Get cell content
                        let cellContent = $(this).text().trim();
                        // Remove any extra whitespace
                        cellContent = cellContent.replace(/\s+/g, ' ');
                        row.push(cellContent);
                    });
                    if (row.length > 0) body.push(row);
                });

                // Check if we have data
                if (head.length === 0 || body.length === 0) {
                    alert("No data found in the table");
                    $button.prop('disabled', false).html('<i class="fas fa-download"></i>');
                    return;
                }

                // Calculate dynamic column widths based on content
                let columnStyles = {};
                let totalColumns = head.length;

                // Set compact column widths (auto adjust)
                for (let i = 0; i < totalColumns; i++) {
                    columnStyles[i] = {
                        cellWidth: 'auto',
                        minCellWidth: 20, // Minimum width for each column
                        maxCellWidth: 80 // Maximum width for each column
                    };
                }

                // Generate PDF with autoTable - COMPACT VERSION
                doc.autoTable({
                    head: [head],
                    body: body,
                    startY: 28,
                    theme: 'grid',
                    styles: {
                        fontSize: 7,
                        cellPadding: 1.5,
                        lineWidth: 0.1,
                        lineColor: [0, 0, 0],
                        valign: 'middle'
                    },
                    headStyles: {
                        fillColor: [220, 220, 220],
                        textColor: [0, 0, 0],
                        fontStyle: 'bold',
                        fontSize: 8,
                        cellPadding: 2,
                        lineWidth: 0.1,
                        lineColor: [0, 0, 0],
                        halign: 'center'
                    },
                    bodyStyles: {
                        fontSize: 7,
                        cellPadding: 1.5,
                        lineWidth: 0.1,
                        lineColor: [0, 0, 0],
                        minCellHeight: 6,
                        valign: 'middle'
                    },
                    columnStyles: columnStyles,
                    margin: {
                        top: 28,
                        right: 5,
                        bottom: 5,
                        left: 5
                    },
                    tableWidth: 'auto',
                    showHead: 'firstPage',
                    rowPageBreak: 'auto',
                    didDrawPage: function(data) {
                        // Add page numbers
                        let pageCount = doc.internal.getNumberOfPages();
                        let currentPage = data.pageNumber;

                        // Compact footer
                        doc.setFontSize(6);
                        doc.text(
                            `Page ${currentPage} of ${pageCount}`,
                            doc.internal.pageSize.width / 2,
                            doc.internal.pageSize.height - 7, {
                                align: 'center'
                            }
                        );
                        doc.text(
                            `Generated from {{ config('app.name', 'Laravel') }}`,
                            doc.internal.pageSize.width / 2,
                            doc.internal.pageSize.height - 3, {
                                align: 'center'
                            }
                        );
                    },
                    didParseCell: function(data) {
                        // Force compact row height
                        if (data.section === 'body') {
                            data.row.height = 6;
                        }
                    },
                    pageBreak: 'avoid',
                    overflow: 'linebreak'
                });

                // Save the PDF
                const fileName =
                    `${tabName.toLowerCase().replace(/\s+/g, '_')}_report_${new Date().toISOString().split('T')[0]}.pdf`;
                doc.save(fileName);

                // Re-enable button
                $button.prop('disabled', false).html('<i class="fas fa-download"></i>');

            } catch (error) {
                console.error('Error generating PDF:', error);
                alert('Error generating PDF: ' + error.message);
                $button.prop('disabled', false).html('<i class="fas fa-download"></i>');
            }
        }
    </script>
@endsection
