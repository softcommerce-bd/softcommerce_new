<div class="card shadow-sm border-0">

    <!-- Header -->
    <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center">

        <h5 class="mb-2 mb-md-0 fw-600">
            <i class="las la-user-check text-primary mr-2"></i>
            {{ translate('Customer Payments') }}
        </h5>

        <button class="btn-account-color btn-sm px-3 rounded-pill"
            onclick="openCustomerPaymentModal()">

            <i class="las la-plus"></i>
            {{ translate('Receive Payment') }}

        </button>

    </div>


    <!-- Filters -->
    <div class="card-body border-bottom">

        <div class="row">

            <div class="col-md-3 mb-3">
                <label>{{ translate('Customer') }}</label>
                <input type="text" id="filter_customer" class="form-control" placeholder="Search customer...">
            </div>

            <div class="col-md-2 mb-3">
                <label>{{ translate('Payment No') }}</label>
                <input type="text" id="filter_payment_no" class="form-control" placeholder="PAY-0001">
            </div>

            <div class="col-md-2 mb-3">
                <label>{{ translate('Status') }}</label>
                <select id="filter_status" class="form-control">
                    <option value="">All</option>
                    <option value="received">Received</option>
                    <option value="partial">Partial</option>
                    <option value="pending">Pending</option>
                </select>
            </div>

            <div class="col-md-2 mb-3">
                <label>{{ translate('Date Range') }}</label>
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

                    <th>{{ translate('Customer') }}</th>

                    <th>{{ translate('Amount') }}</th>

                    <th>{{ translate('Orders') }}</th>

                    <th>{{ translate('Payment Method') }}</th>

                    <th>{{ translate('Date') }}</th>

                    <th>{{ translate('Status') }}</th>

                    <th class="text-right" width="120">
                        {{ translate('Actions') }}
                    </th>

                </tr>

            </thead>

            <tbody id="payments_table_body">

                @forelse($customerPayments ?? [] as $index => $payment)

                    <tr>

                        <!-- SL -->
                        <td>
                            {{ $index + 1 }}
                        </td>

                        <!-- Payment No -->
                        <td>
                            <strong class="text-primary">
                                {{ $payment->payment_no }}
                            </strong>
                        </td>

                        <!-- Customer -->
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mr-2"
                                    style="width:40px;height:40px;">
                                    <i class="las la-user text-primary"></i>
                                </div>
                                <div>
                                    <strong>{{ $payment->customer->name ?? '-' }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $payment->customer->email ?? '' }}</small>
                                </div>
                            </div>
                        </td>

                        <!-- Amount -->
                        <td>
                            <strong class="text-success">
                                {{ number_format($payment->amount, 2) }} ৳
                            </strong>
                        </td>

                        <!-- Orders -->
                        <td>
                            @php
                                $orderIds = $payment->orders->pluck('code', 'id')->toArray();
                            @endphp
                            @foreach($orderIds as $order)
                                <span class="badge badge-info mb-1 d-inline-block">
                                    #{{ $order }}
                                </span>
                            @endforeach
                            @if($payment->orders->isEmpty())
                                <span class="text-muted">-</span>
                            @endif
                        </td>

                        <!-- Method -->
                        <td>
                            <span class="badge badge-soft-info px-3 py-2">
                                {{ $payment->transactionMethod->name ?? '-' }}
                            </span>
                        </td>

                        <!-- Date -->
                        <td>
                            {{ $payment->payment_date->format('d M Y') }}
                        </td>

                        <!-- Status -->
                        <td>
                            @if($payment->status == 'received')
                                <span class="badge badge-success px-3 py-2">
                                    Received
                                </span>
                            @elseif($payment->status == 'partial')
                                <span class="badge badge-warning px-3 py-2">
                                    Partial
                                </span>
                            @else
                                <span class="badge badge-danger px-3 py-2">
                                    {{ ucfirst($payment->status) }}
                                </span>
                            @endif
                        </td>

                        <!-- Actions -->
                        <td class="text-right">

                            <div class="dropdown">

                                <button class="btn btn-light btn-sm"
                                    data-toggle="dropdown">

                                    <i class="fa fa-ellipsis-v"></i>

                                </button>

                                <div class="dropdown-menu dropdown-menu-right">

                                    <a class="dropdown-item"
                                        href="javascript:void(0)"
                                        onclick="viewCustomerPayment({{ $payment->id }})">

                                        <i class="las la-eye mr-2"></i>
                                        View

                                    </a>

                                    <a class="dropdown-item text-danger"
                                        href="javascript:void(0)"
                                        onclick="deleteCustomerPayment({{ $payment->id }})">

                                        <i class="las la-trash mr-2"></i>
                                        Delete

                                    </a>

                                </div>

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr id="empty_row">
                        <td colspan="9" class="text-center py-5">
                            <i class="las la-file-invoice-dollar la-3x text-muted mb-3 d-block"></i>
                            <p class="text-muted mb-0">{{ translate('No customer payments found') }}</p>
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>



    <!-- Mobile Card View -->
    <div class="d-block d-lg-none p-3" id="mobile_payments_container">

        @forelse($customerPayments ?? [] as $payment)

            <div class="card border shadow-sm mb-3">

                <div class="card-body">

                    <!-- Top -->
                    <div class="d-flex justify-content-between">

                        <div>
                            <h6 class="mb-1">{{ $payment->customer->name ?? '-' }}</h6>
                            <small class="text-muted">{{ $payment->payment_no }}</small>
                        </div>

                        <div class="dropdown">
                            <button class="btn btn-light btn-sm" data-toggle="dropdown">
                                <i class="fa fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="javascript:void(0)" onclick="viewCustomerPayment({{ $payment->id }})">View</a>
                                <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteCustomerPayment({{ $payment->id }})">Delete</a>
                            </div>
                        </div>

                    </div>

                    <hr>

                    <!-- Details -->
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
                            <small class="text-muted d-block">Orders</small>
                            @foreach($payment->orders as $order)
                                <span class="badge badge-info">#{{ $order->code ?? $order->id }}</span>
                            @endforeach
                        </div>

                        <div class="col-6 mb-2">
                            <small class="text-muted d-block">Date</small>
                            <strong>{{ $payment->payment_date->format('d M Y') }}</strong>
                        </div>

                        <div class="col-12 mb-2">
                            <small class="text-muted d-block">Status</small>
                            @if($payment->status == 'received')
                                <span class="badge badge-success">Received</span>
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
                <p class="text-muted">{{ translate('No customer payments found') }}</p>
            </div>

        @endforelse

    </div>



    <!-- Footer -->
    <div class="card-footer bg-white">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">

            <div class="mb-2 mb-md-0">
                <strong>Total Payments: <span id="total_count">{{ ($customerPayments ?? collect())->count() }}</span></strong>
            </div>

            <div id="pagination_links">
                {{ ($customerPayments ?? collect())->links() }}
            </div>

        </div>

    </div>

</div>

<!-- Customer Payment Modal -->
<div class="modal fade" id="customerPaymentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-600">
                    <i class="las la-user-check text-primary mr-2"></i>
                    {{ translate('Receive Customer Payment') }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="customerPaymentForm" method="POST">
                @csrf
                <input type="hidden" name="customer_payment_id" id="customer_payment_id">
                
                <div class="modal-body p-4">
                    <div class="md-dialog-content">
                        
                        <!-- Row 1: Date & Customer -->
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group row">
                                    <label class="font-weight-medium col-form-label col-5" for="paymentDate">
                                        {{ translate('Date') }} <span class="text-danger">*</span>
                                    </label>
                                    <div class="col-7">
                                        <input type="date" name="payment_date" id="customer_payment_date" class="form-control" required>
                                        <div class="invalid-feedback">{{ translate('Date is required') }}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 ml-auto">
                                <div class="form-group cst px-0">
                                    <label class="font-weight-medium">{{ translate('Customer') }} <span class="text-danger">*</span></label>
                                    <select name="customer_id" id="customer_id" class="form-control select2" style="width: 100%" required onchange="loadPendingOrders()">
                                        <option value="">{{ translate('Search Customer (Name/ Email/ Mobile)') }}</option>
                                        @foreach($customers ?? [] as $customer)
                                            <option value="{{ $customer->id }}" data-balance="{{ $customer->total_due ?? 0 }}">
                                                {{ $customer->name }} - {{ $customer->email ?? $customer->mobile ?? '' }}
                                                @if(isset($customer->total_due) && $customer->total_due > 0)
                                                    (Due: {{ number_format($customer->total_due, 2) }})
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback">{{ translate('Customer is required') }}</div>
                                    <small class="text-muted" id="customer_due_amount"></small>
                                    <input type="hidden" name="customer_due_amount" id="customer_due_amount_input">
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Orders & Payment Details -->
                        <div class="row mt-3">
                            <!-- Left Column - Order List -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ translate('Select Orders') }} <span class="text-danger">*</span></label>
                                    <div id="pending_orders_list" class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                                        <div class="text-muted text-center py-3">
                                            <i class="las la-spinner la-spin la-2x"></i>
                                            <p class="mt-2">{{ translate('Select a customer to load orders') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column - Payment Details -->
                            <div class="col-md-6">
                                <!-- Payment Amount -->
                                <div class="form-group row">
                                    <label class="col-form-label col-5 pr-0">
                                        {{ translate('Payment Amount') }} <span class="text-danger">*</span>
                                    </label>
                                    <div class="col-7">
                                        <input type="number" step="0.01" name="amount" id="customer_payment_amount" class="form-control text-right" placeholder="0.00" required readonly>
                                        <div class="invalid-feedback">{{ translate('Payment amount is required') }}</div>
                                    </div>
                                </div>

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
                                        <select name="transaction_method_id" id="transaction_method_id" class="form-control">
                                            <option value="">{{ translate('Select Method') }}</option>
                                            @foreach($transactionMethods ?? [] as $method)
                                                <option value="{{ $method->id }}">{{ $method->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback">{{ translate('Method Name is required') }}</div>
                                    </div>
                                </div>

                                <!-- Payment Account -->
                                <div class="form-group row" id="account_row">
                                    <label class="col-form-label col-5 pr-0">
                                        {{ translate('Payment Account') }} <span class="text-danger">*</span>
                                    </label>
                                    <div class="col-7">
                                        <select name="payment_account_id" id="payment_account_id" class="form-control" onchange="checkAccountBalance()">
                                            <option value="">{{ translate('Select Account') }}</option>
                                            @foreach($paymentAccounts ?? [] as $account)
                                                <option value="{{ $account->id }}" data-balance="{{ $account->current_balance ?? 0 }}">
                                                    {{ $account->name }} (Balance: {{ number_format($account->current_balance ?? 0, 2) }})
                                                </option>
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

                                <!-- Notes -->
                                <div class="form-group row">
                                    <label class="col-form-label col-5">{{ translate('Notes') }}</label>
                                    <div class="col-7">
                                        <textarea name="notes" id="payment_notes" rows="3" class="form-control"></textarea>
                                    </div>
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
                    <button type="submit" class="btn-account-color" id="save_btn">{{ translate('Receive Payment') }}</button>
                    <button type="button" class="btn btn-account-color" id="save_and_new_btn" onclick="saveAndNew()">{{ translate('Save & New') }}</button>
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
    if ($.fn.select2) {
        $('.select2').select2({
            dropdownParent: $('#customerPaymentModal')
        });
    }
    
    // Initialize date range picker
    if ($('.aiz-date-range').length > 0 && typeof AIZ !== 'undefined') {
        AIZ.plugins.aizDateRange();
    }
    
    // File input label update
    $('#attachments').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName || '{{ translate("Upload Files") }}');
    });
});

function togglePaymentMethodFields() {
    var method = $('#payment_method').val();
    
    $('#method_name_row, #account_row, #cheque_no_row, #bank_name_row, #branch_name_row, #encash_date_row, #transaction_no_row, #paid_from_row').hide();
    
    if (method === 'CASH') {
        $('#account_row').show();
        $('#transaction_method_id').removeAttr('required');
        $('#payment_account_id').attr('required', true);
    } else if (method === 'BANK') {
        $('#account_row').show();
        $('#transaction_method_id').removeAttr('required');
        $('#payment_account_id').attr('required', true);
    } else if (method === 'MBANKING') {
        $('#method_name_row').show();
        $('#transaction_no_row').show();
        $('#paid_from_row').show();
        $('#transaction_method_id').attr('required', true);
        $('#payment_account_id').removeAttr('required');
    } else if (method === 'CHEQUE') {
        $('#method_name_row').show();
        $('#cheque_no_row').show();
        $('#bank_name_row').show();
        $('#branch_name_row').show();
        $('#encash_date_row').show();
        $('#transaction_method_id').attr('required', true);
        $('#payment_account_id').removeAttr('required');
    }
}

function loadPendingOrders() {
    var customerId = $('#customer_id').val();
    if (customerId) {
        $('#pending_orders_list').html('<div class="text-center py-3"><i class="las la-spinner la-spin la-2x"></i><p class="mt-2">{{ translate("Loading orders...") }}</p></div>');
        
        $.ajax({
            url: '/',
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    var customerDue = response.customer_due || 0;
                    $('#customer_due_amount').text('Total Due: ' + formatNumber(customerDue) + ' ৳');
                    $('#customer_due_amount_input').val(customerDue);
                    
                    var html = '';
                    if (response.orders.length === 0) {
                        html = '<div class="text-muted text-center py-3">' +
                            '<i class="las la-inbox la-3x"></i>' +
                            '<p class="mt-2">{{ translate("No pending orders found") }}</p>' +
                            '</div>';
                    } else {
                        $.each(response.orders, function(index, order) {
                            html += '<div class="form-check mb-3 border-bottom pb-2">';
                            html += '<div class="d-flex justify-content-between align-items-start">';
                            html += '<div class="flex-grow-1">';
                            html += '<input class="form-check-input order-checkbox" type="checkbox" name="order_ids[]" value="' + order.id + '" data-amount="' + order.remaining_amount + '" data-order-code="' + (order.order_code || order.id) + '">';
                            html += '<label class="form-check-label ml-2">';
                            html += '<strong>Order #' + (order.order_code || order.id) + '</strong><br>';
                            html += '<small class="text-muted">Date: ' + (order.date || order.created_at || '-') + '</small><br>';
                            html += '<small>Total: <strong>' + formatNumber(order.grand_total) + '</strong> ৳ | ';
                            html += 'Paid: ' + formatNumber(order.paid_amount) + ' ৳ | ';
                            html += '<span class="text-warning">Remaining: ' + formatNumber(order.remaining_amount) + ' ৳</span></small>';
                            html += '</label>';
                            html += '</div>';
                            html += '<div class="col-4 text-right">';
                            html += '<input type="number" step="0.01" class="form-control form-control-sm text-right order-pay-amount" placeholder="Amount" data-max="' + order.remaining_amount + '" disabled style="width: 120px;">';
                            html += '</div>';
                            html += '</div>';
                            html += '</div>';
                        });
                    }
                    $('#pending_orders_list').html(html);
                    
                    // Bind events to dynamically created elements
                    $('.order-checkbox').on('change', function() {
                        var payAmountInput = $(this).closest('.form-check').find('.order-pay-amount');
                        if ($(this).is(':checked')) {
                            payAmountInput.prop('disabled', false);
                            payAmountInput.val($(this).data('amount'));
                        } else {
                            payAmountInput.prop('disabled', true);
                            payAmountInput.val('');
                        }
                        updateTotalAmount();
                    });
                    
                    $('.order-pay-amount').on('change', function() {
                        var maxAmount = parseFloat($(this).data('max'));
                        var currentAmount = parseFloat($(this).val()) || 0;
                        if (currentAmount > maxAmount) {
                            $(this).val(maxAmount);
                        }
                        updateTotalAmount();
                    });
                }
            },
            error: function(xhr) {
                console.error('Error loading orders:', xhr);
                $('#pending_orders_list').html('<div class="text-danger text-center py-3">{{ translate("Error loading orders") }}</div>');
            }
        });
    } else {
        $('#pending_orders_list').html('<div class="text-muted text-center py-3">{{ translate("Select a customer to load orders") }}</div>');
        $('#customer_due_amount').text('');
        $('#customer_due_amount_input').val('');
    }
}

function updateTotalAmount() {
    var total = 0;
    $('.order-checkbox:checked').each(function() {
        var row = $(this).closest('.form-check');
        var amount = parseFloat(row.find('.order-pay-amount').val()) || 0;
        total += amount;
    });
    $('#customer_payment_amount').val(total.toFixed(2));
    validateAmount();
}

function checkAccountBalance() {
    var selectedOption = $('#payment_account_id option:selected');
    var balance = parseFloat(selectedOption.data('balance')) || 0;
    var amount = parseFloat($('#customer_payment_amount').val()) || 0;
    
    if (amount > balance) {
        $('#balance_error').show();
        $('#save_btn, #save_and_new_btn').prop('disabled', true);
    } else {
        $('#balance_error').hide();
        $('#save_btn, #save_and_new_btn').prop('disabled', false);
    }
}

function validateAmount() {
    var amount = parseFloat($('#customer_payment_amount').val()) || 0;
    var dueAmount = parseFloat($('#customer_due_amount_input').val()) || 0;
    
    if (amount > dueAmount && dueAmount > 0) {
        alert('{{ translate("Payment amount cannot exceed total due amount!") }}');
        $('#customer_payment_amount').val(dueAmount);
        amount = dueAmount;
    }
    
    // Update order payment amounts proportionally if needed
    if ($('.order-checkbox:checked').length > 0 && amount > 0) {
        var selectedOrders = $('.order-checkbox:checked');
        var totalRemaining = 0;
        selectedOrders.each(function() {
            totalRemaining += parseFloat($(this).data('amount'));
        });
        
        if (Math.abs(totalRemaining - amount) > 0.01) {
            // Distribute proportionally
            selectedOrders.each(function() {
                var orderAmount = parseFloat($(this).data('amount'));
                var row = $(this).closest('.form-check');
                var proportionalAmount = (orderAmount / totalRemaining) * amount;
                row.find('.order-pay-amount').val(proportionalAmount.toFixed(2));
            });
        }
    }
    
    checkAccountBalance();
}

function formatNumber(num) {
    return parseFloat(num).toFixed(2);
}

function saveAndNew() {
    $('#customerPaymentForm').attr('data-save-new', 'true');
    $('#customerPaymentForm').submit();
}

$('#customerPaymentForm').on('submit', function(e) {
    e.preventDefault();
    
    if ($('.order-checkbox:checked').length === 0) {
        showNotification('danger', '{{ translate("Please select at least one order") }}');
        return;
    }
    
    var formData = new FormData(this);
    
    $.ajax({
        url: '',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                if ($('#customerPaymentForm').attr('data-save-new') === 'true') {
                    $('#customerPaymentForm')[0].reset();
                    $('#customer_payment_id').val('');
                    $('#customer_payment_date').val(new Date().toISOString().slice(0,10));
                    $('#customer_id').val('').trigger('change');
                    $('#payment_method').val('CASH').trigger('change');
                    $('#customerPaymentForm').removeAttr('data-save-new');
                    showNotification('success', response.message);
                } else {
                    $('#customerPaymentModal').modal('hide');
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

function openCustomerPaymentModal() {
    $('#customer_payment_id').val('');
    $('#customerPaymentForm')[0].reset();
    $('#customer_payment_date').val(new Date().toISOString().slice(0,10));
    $('#customer_id').val('').trigger('change');
    $('#payment_method').val('CASH').trigger('change');
    $('#customer_due_amount').text('');
    $('#customer_due_amount_input').val('');
    $('#pending_orders_list').html('<div class="text-muted text-center py-3">{{ translate("Select a customer to load orders") }}</div>');
    $('#customerPaymentModal').modal('show');
}

function viewCustomerPayment(id) {
    $.ajax({
        url: '',
        type: 'GET',
        success: function(response) {
            if (response.success) {
                var payment = response.data;
                var html = '<div class="p-3">';
                html += '<p><strong>{{ translate("Payment No") }}:</strong> ' + payment.payment_no + '</p>';
                html += '<p><strong>{{ translate("Customer") }}:</strong> ' + (payment.customer?.name || '-') + '</p>';
                html += '<p><strong>{{ translate("Amount") }}:</strong> ' + formatNumber(payment.amount) + ' ৳</p>';
                html += '<p><strong>{{ translate("Orders") }}:</strong><br>';
                if (payment.orders && payment.orders.length > 0) {
                    $.each(payment.orders, function(i, order) {
                        html += '<span class="badge badge-info mr-1">#' + (order.code || order.id) + '</span> ';
                    });
                } else {
                    html += '-';
                }
                html += '</p>';
                html += '<p><strong>{{ translate("Payment Method") }}:</strong> ' + (payment.transaction_method?.name || payment.payment_method || '-') + '</p>';
                html += '<p><strong>{{ translate("Status") }}:</strong> <span class="badge badge-' + (payment.status === 'received' ? 'success' : (payment.status === 'partial' ? 'warning' : 'danger')) + '">' + payment.status.toUpperCase() + '</span></p>';
                html += '<p><strong>{{ translate("Date") }}:</strong> ' + payment.payment_date + '</p>';
                html += '<p><strong>{{ translate("Notes") }}:</strong> ' + (payment.notes || '-') + '</p>';
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

function deleteCustomerPayment(id) {
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
    var customer = $('#filter_customer').val();
    var paymentNo = $('#filter_payment_no').val();
    var status = $('#filter_status').val();
    var dateRange = $('#filter_date_range').val();
    var toDate = $('#filter_to_date').val();
    
    $.ajax({
        url: '',
        type: 'GET',
        data: {
            customer: customer,
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
    if (typeof AIZ !== 'undefined' && AIZ.plugins.notify) {
        AIZ.plugins.notify(type, message);
    } else {
        alert(message);
    }
}
</script>