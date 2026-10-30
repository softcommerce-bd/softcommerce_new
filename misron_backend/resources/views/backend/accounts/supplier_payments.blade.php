<div class="card shadow-sm border-0">

    <!-- Header -->
    <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center">

        <h5 class="mb-2 mb-md-0 fw-600">
            <i class="las la-truck text-primary mr-2"></i>
            {{ translate('Supplier Payments') }}
        </h5>

        <button class="btn-account-color px-3 rounded-pill"
            onclick="openSupplierPaymentModal()">

            <i class="las la-plus"></i>
            {{ translate('Make Payment') }}

        </button>

    </div>


    <!-- Filters -->
    <div class="card-body border-bottom">

        <div class="row">

            <div class="col-md-3 mb-3">
                <label>{{ translate('Supplier') }}</label>
                <input type="text" id="filter_supplier" class="form-control" placeholder="Search supplier...">
            </div>

            <div class="col-md-2 mb-3">
                <label>{{ translate('Payment No') }}</label>
                <input type="text" id="filter_payment_no" class="form-control" placeholder="PAY-0001">
            </div>

            <div class="col-md-2 mb-3">
                <label>{{ translate('Status') }}</label>
                <select id="filter_status" class="form-control">
                    <option value="">All</option>
                    <option value="paid">Paid</option>
                    <option value="partial">Partial</option>
                    <option value="pending">Pending</option>
                </select>
            </div>

            <div class="col-md-2 mb-3">
                <label>Date Range</label>
                <input type="text" class="form-control aiz-date-range" id="filter_date_range" name="date" value=""
                        placeholder="{{ translate('Select date') }}" data-format="DD-MM-Y" data-separator=" to "
                        data-advanced-range="true" autocomplete="off">
            </div>

            <div class="col-md-2 mb-3">
                <label>{{ translate('To Date') }}</label>
                <input type="date" id="filter_to_date" class="form-control">
            </div>

            <div class="col-md-1 mb-3 d-flex align-items-end">
                <button class="btn-account-color btn-block" onclick="filterPayments()">
                    <i class="las la-search"></i>
                </button>
            </div>

        </div>

    </div>



    <!-- Desktop Table -->
    <div class="table-responsive d-none d-lg-block">

        <table class="table table-hover mb-0">

            <thead class="bg-account-color text-white">

                <tr>

                    <th width="60">#</th>

                    <th>{{ translate('Payment No') }}</th>

                    <th>{{ translate('Supplier') }}</th>

                    <th>{{ translate('Amount') }}</th>

                    <th>{{ translate('Payment Method') }}</th>

                    <th>{{ translate('Date') }}</th>

                    <th>{{ translate('Status') }}</th>

                    <th class="text-right" width="120">
                        {{ translate('Actions') }}
                    </th>

                </tr>

            </thead>

            <tbody id="payments_table_body">

                @forelse($supplierPayments ?? [] as $index => $payment)

                    <tr>

                        <td>{{ $index + 1 }}</td>

                        <td>
                            <strong class="text-primary">
                                {{ $payment->payment_no }}
                            </strong>
                        </td>

                        <td>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mr-2"
                                    style="width:40px;height:40px;">
                                    <i class="las la-user text-primary"></i>
                                </div>
                                <div>
                                    <strong>{{ $payment->supplier->business_name ?? '-' }}</strong>
                                </div>
                            </div>
                        </td>

                        <td>
                            <strong class="text-success">
                                {{ number_format($payment->amount, 2) }} ৳
                            </strong>
                        </td>

                        <td>
                            <span class="badge badge-soft-info px-3 py-2">
                                {{ $payment->transactionMethod->name ?? '-' }}
                            </span>
                        </td>

                        <td>{{ $payment->payment_date->format('d M Y') }}</td>

                        <td>
                            @if($payment->status == 'paid')
                                <span class="badge badge-success px-3 py-2">Paid</span>
                            @elseif($payment->status == 'partial')
                                <span class="badge badge-warning px-3 py-2">Partial</span>
                            @else
                                <span class="badge badge-danger px-3 py-2">{{ ucfirst($payment->status) }}</span>
                            @endif
                        </td>

                        <td class="text-right">
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm" data-toggle="dropdown">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item" href="javascript:void(0)" onclick="viewSupplierPayment({{ $payment->id }})">
                                        <i class="las la-eye mr-2"></i> View
                                    </a>
                                    <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteSupplierPayment({{ $payment->id }})">
                                        <i class="las la-trash mr-2"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </td>

                    </tr>

                @empty

                    <tr id="empty_row">
                        <td colspan="8" class="text-center py-5">
                            <i class="las la-file-invoice-dollar la-3x text-muted mb-3 d-block"></i>
                            <p class="text-muted mb-0">{{ translate('No supplier payments found') }}</p>
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>



    <!-- Mobile Card View -->
    <div class="d-block d-lg-none p-3" id="mobile_payments_container">

        @forelse($supplierPayments ?? [] as $payment)

            <div class="card border shadow-sm mb-3">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>
                            <h6 class="mb-1">{{ $payment->supplier->business_name ?? '-' }}</h6>
                            <small class="text-muted">{{ $payment->payment_no }}</small>
                        </div>

                        <div class="dropdown">
                            <button class="btn btn-light btn-sm" data-toggle="dropdown">
                                <i class="fa fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="javascript:void(0)" onclick="viewSupplierPayment({{ $payment->id }})">View</a>
                                <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteSupplierPayment({{ $payment->id }})">Delete</a>
                            </div>
                        </div>

                    </div>

                    <hr>

                    <div class="row">

                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Amount</small>
                            <strong class="text-success">{{ number_format($payment->amount, 2) }} ৳</strong>
                        </div>

                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Method</small>
                            <strong>{{ $payment->transactionMethod->name ?? '-' }}</strong>
                        </div>

                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Date</small>
                            <strong>{{ $payment->payment_date->format('d M Y') }}</strong>
                        </div>

                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Status</small>
                            @if($payment->status == 'paid')
                                <span class="badge badge-success">Paid</span>
                            @elseif($payment->status == 'partial')
                                <span class="badge badge-warning">Partial</span>
                            @else
                                <span class="badge badge-danger">{{ ucfirst($payment->status) }}</span>
                            @endif
                        </div>

                    </div>

                </div>

            </div>

        @empty

            <div class="text-center py-5" id="mobile_empty_row">
                <i class="las la-file-invoice-dollar la-3x text-muted mb-3 d-block"></i>
                <p class="text-muted">{{ translate('No supplier payments found') }}</p>
            </div>

        @endforelse

    </div>



    <!-- Footer -->
    <div class="card-footer bg-white">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">

            <div class="mb-2 mb-md-0">
                <strong>Total Payments: <span id="total_count">{{ ($supplierPayments ?? collect())->count() }}</span></strong>
            </div>

            <div id="pagination_links">
                {{ ($supplierPayments ?? collect())->links() }}
            </div>

        </div>

    </div>

</div>

<!-- Supplier Payment Modal -->
<div class="modal fade" id="supplierPaymentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-600">
                    <i class="las la-truck text-primary mr-2"></i>
                    {{ translate('Make Supplier Payment') }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="supplierPaymentForm" method="POST">
                @csrf
                <input type="hidden" name="supplier_payment_id" id="supplier_payment_id">
                
                <div class="modal-body p-4">
                    <div class="md-dialog-content">
                        
                        <!-- Row 1: Date & Supplier -->
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group row">
                                    <label class="font-weight-medium col-form-label col-5" for="paymentDate">
                                        {{ translate('Date') }} <span class="text-danger">*</span>
                                    </label>
                                    <div class="col-7">
                                        <input type="date" name="payment_date" id="supplier_payment_date" class="form-control" required>
                                        <div class="invalid-feedback">{{ translate('Date is required') }}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 ml-auto">
                                <div class="form-group cst px-0">
                                    <label class="font-weight-medium">{{ translate('Supplier') }} <span class="text-danger">*</span></label>
                                    <select name="supplier_id" id="supplier_id" class="form-control select2" style="width: 100%" required onchange="loadSupplierDue()">
                                        <option value="">{{ translate('Search Supplier (Name/ Mobile No.)') }}</option>
                                        @foreach($suppliers ?? [] as $supplier)
                                            <option value="{{ $supplier->id }}">
                                                {{ $supplier->business_name }} - {{ $supplier->mobile ?? '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback">{{ translate('Supplier is required') }}</div>
                                    <small class="text-muted" id="supplier_due_amount"></small>
                                    <input type="hidden" name="supplier_due_amount" id="supplier_due_amount_input">
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Payment Method & Account Details -->
                        <div class="row mt-3">
                            <div class="col-md-5">
                                <!-- Payment Method -->
                                <div class="form-group row">
                                    <label class="col-form-label col-5 pr-0">
                                        {{ translate('Payment Method') }} <span class="text-danger">*</span>
                                    </label>
                                    <div class="col-7">
                                        <select name="payment_method" id="payment_method" class="form-control" required onchange="togglePaymentMethodFields()">
                                            <option value="CASH">{{ translate('Cash') }}</option>
                                            <option value="BANK">{{ translate('Bank') }}</option>
                                            <option value="MBANKING">{{ translate('Mobile Banking') }}</option>
                                            <option value="CHEQUE">{{ translate('Cheque') }}</option>
                                        </select>
                                        <div class="invalid-feedback">{{ translate('Payment Method is required') }}</div>
                                    </div>
                                </div>

                                <!-- Method Name -->
                                <div class="form-group row" id="method_name_row" style="display: none;">
                                    <label class="col-form-label col-5 pr-0">
                                        {{ translate('Method Name') }} <span class="text-danger">*</span>
                                    </label>
                                    <div class="col-7">
                                        <select name="payment_method_id" id="payment_method_id" class="form-control">
                                            <option value="">{{ translate('Select Method') }}</option>
                                            @foreach($paymentServices ?? [] as $service)
                                                <option value="{{ $service->id }}">{{ $service->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback">{{ translate('Method Name is required') }}</div>
                                    </div>
                                </div>

                                <!-- Account -->
                                <div class="form-group row" id="account_row">
                                    <label class="col-form-label col-5 pr-0">
                                        {{ translate('Account') }} <span class="text-danger">*</span>
                                    </label>
                                    <div class="col-7">
                                        <select name="account_head_id" id="account_head_id" class="form-control" onchange="checkAccountBalance()">
                                            <option value="">{{ translate('Select Account') }}</option>
                                            @foreach($cashBankHeads ?? [] as $account)
                                                <option value="{{ $account->id }}" data-balance="{{ $account->balance ?? 0 }}">{{ $account->name }} (Balance: {{ number_format($account->balance ?? 0, 2) }})</option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback">{{ translate('Account is required') }}</div>
                                        <small class="text-danger" id="balance_error" style="display: none;">
                                            {{ translate('Insufficient Balance in account') }}
                                        </small>
                                    </div>
                                </div>

                                <!-- Cheque Fields -->
                                <div class="form-group row" id="cheque_no_row" style="display: none;">
                                    <label class="col-form-label col-5 pr-0">{{ translate('Cheque No') }} <span class="text-danger">*</span></label>
                                    <div class="col-7">
                                        <input type="text" name="cheque_no" id="cheque_no" class="form-control">
                                    </div>
                                </div>

                                <div class="form-group row" id="bank_name_row" style="display: none;">
                                    <label class="col-form-label col-5 pr-0">{{ translate('Bank Name') }} <span class="text-danger">*</span></label>
                                    <div class="col-7">
                                        <input type="text" name="bank_name" id="bank_name" class="form-control">
                                    </div>
                                </div>

                                <div class="form-group row" id="branch_name_row" style="display: none;">
                                    <label class="col-form-label col-5 pr-0">{{ translate('Branch Name') }}</label>
                                    <div class="col-7">
                                        <input type="text" name="branch_name" id="branch_name" class="form-control">
                                    </div>
                                </div>

                                <div class="form-group row" id="encash_date_row" style="display: none;">
                                    <label class="col-form-label col-5 pr-0">{{ translate('Encash Date') }}</label>
                                    <div class="col-7">
                                        <input type="date" name="encash_date" id="encash_date" class="form-control">
                                    </div>
                                </div>

                                <!-- Mobile Banking Fields -->
                                <div class="form-group row" id="transaction_no_row" style="display: none;">
                                    <label class="col-form-label col-5 pr-0">{{ translate('Transaction No') }} <span class="text-danger">*</span></label>
                                    <div class="col-7">
                                        <input type="text" name="transaction_no" id="transaction_no" class="form-control">
                                    </div>
                                </div>

                                <div class="form-group row" id="paid_from_row" style="display: none;">
                                    <label class="col-form-label col-5 pr-0">{{ translate('Paid From') }}</label>
                                    <div class="col-7">
                                        <input type="text" name="paid_from" id="paid_from" class="form-control">
                                    </div>
                                </div>

                                <!-- Paid Amount -->
                                <div class="form-group row">
                                    <label class="col-form-label col-5 pr-0">
                                        {{ translate('Paid Amount') }} <span class="text-danger">*</span>
                                    </label>
                                    <div class="col-7">
                                        <input type="number" step="0.01" name="amount" id="supplier_payment_amount" class="form-control text-right" placeholder="0.00" required onchange="validateAmount()">
                                        <div class="invalid-feedback">{{ translate('Paid amount is required') }}</div>
                                    </div>
                                </div>

                                <!-- Note -->
                                <div class="form-group row">
                                    <label class="col-form-label col-4">{{ translate('Note') }}</label>
                                    <div class="col-8">
                                        <textarea name="notes" id="notes" rows="3" class="form-control"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Side: Due Invoices Table -->
                            <div class="col-md-7 pr-3">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th width="40">
                                                    <input type="checkbox" id="select_all_invoices" onchange="toggleAllInvoices()">
                                                </th>
                                                <th>{{ translate('Date') }}</th>
                                                <th>{{ translate('Invoice No') }}</th>
                                                <th class="text-right">{{ translate('Due Amount') }}</th>
                                                <th class="text-right">{{ translate('Pay Amount') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody id="due_invoices_body" style="max-height: 300px; display: block; overflow-y: auto;">
                                            <tr>
                                                <td colspan="5" class="text-center py-3">
                                                    {{ translate('Select a supplier to view due invoices') }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Attachment Section -->
                        <div class="form-row cs-file pb-2 mt-3">
                            <div class="col-md-4">
                                <label class="lbl-form text-muted">{{ translate('Attachment') }}</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="attachments" name="attachments[]" multiple accept=".pdf,image/jpeg,image/jpg,image/png,image/gif">
                                    <label class="custom-file-label" for="attachments">{{ translate('Upload Files') }}</label>
                                </div>
                                <small class="font-italic font-11 text-muted">{{ translate('Select attachments (PDF, JPEG, PNG, GIF)') }}</small>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer justify-content-start px-4 py-3 bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn-account-color" id="save_btn">{{ translate('Save') }}</button>
                    <button type="button" class="btn-account-color" id="save_and_new_btn" onclick="saveAndNew()">{{ translate('Save & New') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Payment Modal -->
<div class="modal fade" id="viewPaymentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">{{ translate('Payment Details') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="view_payment_content"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize Select2
$(document).ready(function() {
    $('.select2').select2({
        dropdownParent: $('#supplierPaymentModal')
    });
    
    // Initialize date range picker
    if ($('.aiz-date-range').length > 0) {
        AIZ.plugins.aizDateRange();
    }
});

function togglePaymentMethodFields() {
    var method = $('#payment_method').val();
    
    $('#method_name_row, #account_row, #cheque_no_row, #bank_name_row, #branch_name_row, #encash_date_row, #transaction_no_row, #paid_from_row').hide();
    
    if (method === 'CASH') {
        $('#account_row').show();
        $('#payment_method_id').removeAttr('required');
        $('#account_head_id').attr('required', true);
    } else if (method === 'BANK') {
        $('#account_row').show();
        $('#payment_method_id').removeAttr('required');
        $('#account_head_id').attr('required', true);
    } else if (method === 'MBANKING') {
        $('#method_name_row').show();
        $('#transaction_no_row').show();
        $('#paid_from_row').show();
        $('#payment_method_id').attr('required', true);
        $('#account_head_id').removeAttr('required');
    } else if (method === 'CHEQUE') {
        $('#method_name_row').show();
        $('#cheque_no_row').show();
        $('#bank_name_row').show();
        $('#branch_name_row').show();
        $('#encash_date_row').show();
        $('#payment_method_id').attr('required', true);
        $('#account_head_id').removeAttr('required');
    }
}

function loadSupplierDue() {
    var supplierId = $('#supplier_id').val();
    if (supplierId) {
        $.ajax({
            url: '#',
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    $('#supplier_due_amount').text('Total Due: ' + parseFloat(response.due_amount).toFixed(2) + ' ৳');
                    $('#supplier_due_amount_input').val(response.due_amount);
                    $('#supplier_payment_amount').attr('max', response.due_amount);
                    loadDueInvoices(response.invoices);
                }
            },
            error: function(xhr) {
                console.error('Error loading due:', xhr);
            }
        });
    } else {
        $('#supplier_due_amount').text('');
        $('#due_invoices_body').html('<tr><td colspan="5" class="text-center py-3">{{ translate("Select a supplier to view due invoices") }}</td></tr>');
    }
}

function loadDueInvoices(invoices) {
    var html = '';
    if (invoices && invoices.length > 0) {
        $.each(invoices, function(index, invoice) {
            html += '<tr>' +
                '<td><input type="checkbox" class="invoice_checkbox" data-id="' + invoice.id + '" data-amount="' + invoice.due_amount + '" onchange="updateTotalAmount()"></td>' +
                '<td>' + invoice.date + '</td>' +
                '<td>' + invoice.invoice_no + '</td>' +
                '<td class="text-right">' + parseFloat(invoice.due_amount).toFixed(2) + '</td>' +
                '<td class="text-right"><input type="number" step="0.01" class="form-control form-control-sm text-right invoice_payment_amount" data-max="' + invoice.due_amount + '" placeholder="0.00" disabled onchange="updateTotalAmount()"></td>' +
            '</tr>';
        });
    } else {
        html = '<tr><td colspan="5" class="text-center py-3">{{ translate("No due invoices found") }}</td></tr>';
    }
    $('#due_invoices_body').html(html);
}

function toggleAllInvoices() {
    var isChecked = $('#select_all_invoices').is(':checked');
    $('.invoice_checkbox').prop('checked', isChecked);
    $('.invoice_payment_amount').prop('disabled', !isChecked);
    if (!isChecked) {
        $('.invoice_payment_amount').val('');
    }
    updateTotalAmount();
}

function updateTotalAmount() {
    var total = 0;
    $('.invoice_checkbox:checked').each(function() {
        var row = $(this).closest('tr');
        var amount = parseFloat(row.find('.invoice_payment_amount').val()) || 0;
        total += amount;
    });
    $('#supplier_payment_amount').val(total.toFixed(2));
    validateAmount();
}

function checkAccountBalance() {
    var selectedOption = $('#account_head_id option:selected');
    var balance = parseFloat(selectedOption.data('balance')) || 0;
    var amount = parseFloat($('#supplier_payment_amount').val()) || 0;
    
    if (amount > balance) {
        $('#balance_error').show();
        $('#save_btn').prop('disabled', true);
        $('#save_and_new_btn').prop('disabled', true);
    } else {
        $('#balance_error').hide();
        $('#save_btn').prop('disabled', false);
        $('#save_and_new_btn').prop('disabled', false);
    }
}

function validateAmount() {
    var amount = parseFloat($('#supplier_payment_amount').val()) || 0;
    var dueAmount = parseFloat($('#supplier_due_amount_input').val()) || 0;
    
    if (amount > dueAmount) {
        alert('{{ translate("Payment amount cannot exceed due amount!") }}');
        $('#supplier_payment_amount').val(dueAmount);
        amount = dueAmount;
    }
    
    checkAccountBalance();
}

function saveAndNew() {
    $('#supplierPaymentForm').attr('data-save-new', 'true');
    $('#supplierPaymentForm').submit();
}

$('#supplier_payment_amount').on('change', function() {
    var amount = parseFloat($(this).val()) || 0;
    var dueAmount = parseFloat($('#supplier_due_amount_input').val()) || 0;
    
    if (amount > dueAmount) {
        $(this).val(dueAmount);
    }
    checkAccountBalance();
});

$('#supplierPaymentForm').on('submit', function(e) {
    e.preventDefault();
    
    var formData = new FormData(this);
    
    $.ajax({
        url: '#',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                if ($('#supplierPaymentForm').attr('data-save-new') === 'true') {
                    $('#supplierPaymentForm')[0].reset();
                    $('#supplier_payment_id').val('');
                    $('#supplier_payment_date').val(new Date().toISOString().slice(0,10));
                    $('#supplier_id').val('').trigger('change');
                    $('#payment_method').val('CASH').trigger('change');
                    $('#supplierPaymentForm').removeAttr('data-save-new');
                    showNotification('success', response.message);
                } else {
                    $('#supplierPaymentModal').modal('hide');
                    location.reload();
                }
            }
        },
        error: function(xhr) {
            var message = xhr.responseJSON?.message || '{{ translate("Error processing payment") }}';
            if (xhr.status === 422 && xhr.responseJSON.errors) {
                var errors = xhr.responseJSON.errors;
                for (var field in errors) {
                    showNotification('danger', errors[field][0]);
                }
            } else {
                showNotification('danger', message);
            }
        }
    });
});

// function openSupplierPaymentModal() {
//     $('#supplier_payment_id').val('');
//     $('#supplierPaymentForm')[0].reset();
//     $('#supplier_payment_date').val(new Date().toISOString().slice(0,10));
//     $('#supplier_id').val('').trigger('change');
//     $('#payment_method').val('CASH').trigger('change');
//     $('#supplier_due_amount').text('');
//     $('#supplier_due_amount_input').val('');
//     $('#due_invoices_body').html('<tr><td colspan="5" class="text-center py-3">{{ translate("Select a supplier to view due invoices") }}</td></tr>');
//     $('#supplierPaymentModal').modal('show');
// }

function viewSupplierPayment(id) {
    $.ajax({
        url: '',
        type: 'GET',
        success: function(response) {
            if (response.success) {
                var payment = response.data;
                var html = '<div class="p-3">';
                html += '<p><strong>Payment No:</strong> ' + payment.payment_no + '</p>';
                html += '<p><strong>Supplier:</strong> ' + (payment.supplier?.business_name || '-') + '</p>';
                html += '<p><strong>Amount:</strong> ' + parseFloat(payment.amount).toFixed(2) + ' ৳</p>';
                html += '<p><strong>Payment Method:</strong> ' + (payment.transaction_method?.name || payment.payment_method || '-') + '</p>';
                html += '<p><strong>Status:</strong> <span class="badge badge-' + (payment.status === 'paid' ? 'success' : (payment.status === 'partial' ? 'warning' : 'danger')) + '">' + payment.status.toUpperCase() + '</span></p>';
                html += '<p><strong>Date:</strong> ' + payment.payment_date + '</p>';
                html += '<p><strong>Notes:</strong> ' + (payment.notes || '-') + '</p>';
                html += '</div>';
                
                $('#view_payment_content').html(html);
                $('#viewPaymentModal').modal('show');
            }
        },
        error: function(xhr) {
            showNotification('danger', '{{ translate("Error loading payment details") }}');
        }
    });
}

function deleteSupplierPayment(id) {
    if (confirm('{{ translate("Are you sure? This action cannot be undone.") }}')) {
        $.ajax({
            url: '',
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    location.reload();
                    showNotification('success', response.message);
                } else {
                    showNotification('danger', response.message);
                }
            },
            error: function(xhr) {
                showNotification('danger', '{{ translate("Error deleting payment") }}');
            }
        });
    }
}

function filterPayments() {
    var supplier = $('#filter_supplier').val();
    var paymentNo = $('#filter_payment_no').val();
    var status = $('#filter_status').val();
    var dateRange = $('#filter_date_range').val();
    var toDate = $('#filter_to_date').val();
    
    $.ajax({

        url: '#',
        type: 'GET',
        data: {
            supplier: supplier,
            payment_no: paymentNo,
            status: status,
            date_range: dateRange,
            to_date: toDate
        },
        success: function(response) {
            $('#payments_table_body').html(response.table_html);
            $('#mobile_payments_container').html(response.mobile_html);
            $('#total_count').text(response.total_count);
            $('#pagination_links').html(response.pagination);
        },
        error: function(xhr) {
            showNotification('danger', '{{ translate("Error filtering payments") }}');
        }
    });
}

function showNotification(type, message) {
    // Implement your notification system here
    alert(message);
}

// File input label update
$('#attachments').on('change', function() {
    var fileName = $(this).val().split('\\').pop();
    $(this).next('.custom-file-label').html(fileName || '{{ translate("Upload Files") }}');
});
</script>