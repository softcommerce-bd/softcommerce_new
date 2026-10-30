<!-- backend/accounts/modals/customer_payment_modal.blade.php -->
<div class="modal fade" id="customerPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="las la-user-check mr-2"></i> {{ translate('Receive Customer Payment') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="customerPaymentForm">
                @csrf
                <input type="hidden" id="customer_payment_id" name="payment_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ translate('Customer') }} <span class="text-danger">*</span></label>
                                <select class="form-control" id="customer_id" name="customer_id" required>
                                    <option value="">{{ translate('Select Customer') }}</option>
                                    @foreach($customers ?? [] as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->email ?? '' }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ translate('Payment Date') }} <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="customer_payment_date" name="payment_date" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>{{ translate('Select Orders') }} <span class="text-danger">*</span></label>
                                <div id="paid_orders_list" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                    <div class="text-muted text-center">{{ translate('Select a customer first') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ translate('Amount') }} <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="customer_payment_amount" name="amount" placeholder="0.00" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ translate('Payment Account') }} <span class="text-danger">*</span></label>
                                <select class="form-control" id="customer_payment_account_id" name="payment_account_id" required>
                                    <option value="">{{ translate('Select Account') }}</option>
                                    @foreach($paymentAccounts ?? [] as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }} (Balance: {{ number_format($account->current_balance, 2) }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ translate('Transaction Method') }} <span class="text-danger">*</span></label>
                                <select class="form-control" id="customer_transaction_method_id" name="transaction_method_id" required>
                                    <option value="">{{ translate('Select Method') }}</option>
                                    @foreach($transactionMethods ?? [] as $method)
                                    <option value="{{ $method->id }}">{{ $method->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>{{ translate('Notes') }}</label>
                                <textarea class="form-control" id="customer_notes" name="notes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ translate('Receive Payment') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#customer_id').on('change', function() {
    var customerId = $(this).val();
    if (customerId) {
        $.ajax({
            url: '{{ route("accounts.customer-payments.paid-orders", "") }}/' + customerId,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    var html = '';
                    if (response.orders.length === 0) {
                        html = '<div class="text-muted text-center">{{ translate("No pending orders found") }}</div>';
                    } else {
                        $.each(response.orders, function(index, order) {
                            html += '<div class="form-check mb-2">';
                            html += '<input class="form-check-input order-checkbox" type="checkbox" name="order_ids[]" value="' + order.id + '" data-amount="' + order.remaining_amount + '">';
                            html += '<label class="form-check-label">';
                            html += '<strong>{{ translate("Order") }} #' + (order.order_code || order.id) + '</strong><br>';
                            html += '<small>{{ translate("Total") }}: ' + parseFloat(order.grand_total).toFixed(2) + ' ৳ | ';
                            html += '{{ translate("Paid") }}: ' + parseFloat(order.paid_amount).toFixed(2) + ' ৳ | ';
                            html += '{{ translate("Remaining") }}: ' + parseFloat(order.remaining_amount).toFixed(2) + ' ৳</small>';
                            html += '</label>';
                            html += '</div>';
                        });
                    }
                    $('#paid_orders_list').html(html);
                    
                    $('.order-checkbox').on('change', function() {
                        var total = 0;
                        $('.order-checkbox:checked').each(function() {
                            total += parseFloat($(this).data('amount'));
                        });
                        $('#customer_payment_amount').val(total.toFixed(2));
                    });
                }
            },
            error: function() {
                $('#paid_orders_list').html('<div class="text-danger text-center">{{ translate("Error loading orders") }}</div>');
            }
        });
    } else {
        $('#paid_orders_list').html('<div class="text-muted text-center">{{ translate("Select a customer first") }}</div>');
    }
});

$('#customerPaymentForm').on('submit', function(e) {
    e.preventDefault();
    
    if ($('.order-checkbox:checked').length === 0) {
        showNotification('danger', '{{ translate("Please select at least one order") }}');
        return;
    }
    
    $.ajax({
        url: '{{ route("accounts.customer-payments.store") }}',
        type: 'POST',
        data: $(this).serialize(),
        success: function(response) {
            if (response.success) {
                $('#customerPaymentModal').modal('hide');
                showNotification('success', response.message);
                setTimeout(function() { location.reload(); }, 1000);
            }
        },
        error: function(xhr) {
            var message = xhr.responseJSON?.message || '{{ translate("Error processing payment") }}';
            showNotification('danger', message);
        }
    });
});
</script>