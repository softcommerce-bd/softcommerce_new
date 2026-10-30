{{-- <!-- Include jQuery and Date Range Picker -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label for="ledger_date_range">Date Range:</label>
            <input placeholder="Select a date range" class="form-control"
                name="ledger_date_range" type="text" id="ledger_date_range_purchase">
        </div>
        <button class="btn btn-sm bg-primary" id="searchPurchase">Search</button>
    </div>
    <div class="col-md-12">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0 h6">{{ translate('Supplier Products') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="invoices_table">
                                <thead>
                                    <tr class="blue-heading">
                                        <th class="text-center">Purchase Date</th>
                                        <th class="text-center">Reference No</th>
                                        <th class="text-center">Pay Term</th>
                                        <th class="text-center">Pay Term Type</th>
                                        <th class="text-center">Total Item</th>
                                        <th class="text-center">Paid Amount</th>
                                        <th class="text-center">Paid Date</th>
                                        <th class="text-center">Location</th>
                                        <th class="text-center">Total Payment Due</th>
                                        <th class="text-center">Payment Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($supplierWisePurchased as $purchase)
                                    <tr>
                                        <td>{{ $purchase->created_at->format('m/d/Y H:i') }}</td>
                                        <td>{{ $purchase->reference_no }}</td>
                                        <td class="text-right">{{ $purchase->pay_term }}</td>
                                        <td class="text-right">{{ $purchase->pay_term_type }}</td>
                                        <td class="text-right">{{ $purchase->total_item }}</td>
                                        <td class="text-right">{{ $purchase->advanced_amount }}</td>
                                        <td class="text-right">{{ $purchase->advanced_paid_on }}</td>
                                        <td class="text-right">{{ $purchase->business_location }}</td>
                                        <td class="text-right">{{ $purchase->total_payment_due }}</td>
                                        <td class="text-right">{{ $purchase->payment_method }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize date range picker
        $('#ledger_date_range_purchase').daterangepicker({
            opens: 'left',
            locale: {
                format: 'MM/DD/YYYY' // Ensure this format matches your controller
            }
        });

        $('#searchPurchase').on('click', function() {
            var dateRange = $('#ledger_date_range_purchase').val();
            var [startDate, endDate] = dateRange.split(' - ');
            var supplierId = '{{ $supplier->id }}'; // Ensure $supplier is available in the Blade view

            $.ajax({
                url: "{{ route('fetchSupplierData') }}", // Update this URL to your route
                type: 'GET',
                data: {
                    start_date: startDate,
                    end_date: endDate,
                    supplier_id: supplierId
                },
                success: function(response) {
                    console.log(response); // Check the response in the console

                    // Update table with new data
                    tableBody.empty();
                    $('#invoices_table tbody').html(response.data);
                },
                error: function(xhr) {
                    console.error('An error occurred:', xhr.responseText);
                }
            });
        });
    });
</script> --}}




<!-- Include jQuery and Date Range Picker -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label for="ledger_date_range">Date Range:</label>
            <input placeholder="Select a date range" class="form-control" name="ledger_date_range" type="text"
                id="ledger_date_range_purchase">
        </div>
        <button class="btn btn-sm bg-primary" id="searchPurchase">Search</button>
    </div>
    <div class="col-md-12">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0 h6">{{ translate('Supplier Products') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-end mb-3">
                            <button class="btn btn-outline-secondary btn-sm print_tab_pdf">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button class="btn btn-outline-secondary btn-sm download_tab_pdf ms-2">
                                <i class="fas fa-download"></i> Download PDF
                            </button>
                        </div>


                        <div class="print-area">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="invoices_table">
                                    <thead>
                                        <tr class="blue-heading">
                                            <th class="text-center">Purchase Date</th>
                                            <th class="text-center">Reference No</th>
                                            <th class="text-center">Pay Term</th>
                                            <th class="text-center">Pay Term Type</th>
                                            <th class="text-center">Total Item</th>
                                            <th class="text-center">Paid Amount</th>
                                            <th class="text-center">Paid Date</th>
                                            <th class="text-center">Location</th>
                                            <th class="text-center">Total Payment Due</th>
                                            <th class="text-center">Payment Method</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Rows will be dynamically added here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize date range picker
        $('#ledger_date_range_purchase').daterangepicker({
            opens: 'left',
            locale: {
                format: 'MM/DD/YYYY'
            }
        });

        $('#searchPurchase').on('click', function() {
            var dateRange = $('#ledger_date_range_purchase').val();
            var [startDate, endDate] = dateRange.split(' - ');
            var supplierId = '{{ $supplier->id }}'; // Ensure $supplier is available in the Blade view

            $.ajax({
                url: "{{ route('fetchSupplierData') }}",
                type: 'GET',
                data: {
                    start_date: startDate,
                    end_date: endDate,
                    supplier_id: supplierId
                },
                success: function(response) {
                    // Clear existing table rows
                    $('#invoices_table tbody').empty();

                    // Append new rows
                    response.data.forEach(function(purchase) {
                        const totalPaid =
                            (parseFloat(purchase.total_advance_amount) || 0) +
                            (parseFloat(purchase.total_bank_transfer_amount) || 0) +
                            (parseFloat(purchase.total_cash_amount) || 0) +
                            (parseFloat(purchase.total_cheque_amount) || 0);
                        let paidOn = purchase.advanced_paid_on;
                        if (Array.isArray(paidOn)) {
                            paidOn = paidOn.join(', ');
                        } else if (typeof paidOn === 'string' && paidOn.startsWith(
                                '[')) {
                            paidOn = JSON.parse(paidOn).join(', ');
                        }

                        let paymentMethod = purchase.payment_method;
                        if (Array.isArray(paymentMethod)) {
                            paymentMethod = paymentMethod.join(', ');
                        } else if (typeof paymentMethod === 'string' &&
                            paymentMethod.startsWith('[')) {
                            paymentMethod = JSON.parse(paymentMethod).join(', ');
                        }

                        const row = `
                                    <tr>
                                        <td>${new Date(purchase.created_at).toLocaleDateString('en-US')}</td>
                                        <td>${purchase.reference_no}</td>
                                        <td class="text-right">${purchase.pay_term}</td>
                                        <td class="text-right">${purchase.pay_term_type}</td>
                                        <td class="text-right">${purchase.total_item}</td>
                                        <td class="text-right">${totalPaid}</td>
                                        <td class="text-right">${paidOn || ''}</td>
                                        <td class="text-right">${purchase.business_location}</td>
                                        <td class="text-right">${purchase.total_payment_due}</td>
                                        <td class="text-right">${paymentMethod || ''}</td>
                                    </tr>
                                `;
                        $('#invoices_table tbody').append(row);
                    });
                },
                error: function(xhr) {
                    console.error('An error occurred:', xhr.responseText);
                }
            });
        });
    });
</script>
