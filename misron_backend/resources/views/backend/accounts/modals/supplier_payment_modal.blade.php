<!-- backend/accounts/modals/supplier_payment_modal.blade.php -->
<div class="modal fade" id="supplierPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="las la-truck mr-2"></i> {{ translate('Make Supplier Payment') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="supplierPaymentForm">
                @csrf
                <input type="hidden" id="supplier_payment_id" name="payment_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ translate('Supplier') }} <span class="text-danger">*</span></label>
                                <select class="form-control" id="supplier_id" name="supplier_id" required>
                                    <option value="">{{ translate('Select Supplier') }}</option>
                                    @foreach($suppliers ?? [] as $supplier)
                                    <option value="{{ $supplier->id }}" data-due="{{ $supplier->opening_balance - ($supplier->paid_amount ?? 0) }}">
                                        {{ $supplier->business_name }} - Due: {{ number_format($supplier->opening_balance - ($supplier->paid_amount ?? 0), 2) }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ translate('Payment Date') }} <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="supplier_payment_date" name="payment_date" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ translate('Amount') }} <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="supplier_payment_amount" name="amount" placeholder="0.00" required>
                                <small class="text-muted" id="supplier_due_text"></small>
                                <input type="hidden" id="supplier_due_amount">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ translate('Payment Type') }}</label>
                                <select class="form-control" id="supplier_payment_type" name="payment_type">
                                    <option value="full">{{ translate('Full Payment') }}</option>
                                    <option value="partial">{{ translate('Partial Payment') }}</option>
                                    <option value="advance">{{ translate('Advance Payment') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ translate('Payment Account') }} <span class="text-danger">*</span></label>
                                <select class="form-control" id="supplier_payment_account_id" name="payment_account_id" required>
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
                                <select class="form-control" id="supplier_transaction_method_id" name="transaction_method_id" required>
                                    <option value="">{{ translate('Select Method') }}</option>
                                    @foreach($transactionMethods ?? [] as $method)
                                    <option value="{{ $method->id }}">{{ $method->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>{{ translate('Reference No') }}</label>
                                <input type="text" class="form-control" id="supplier_reference_no" name="reference_no">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>{{ translate('Notes') }}</label>
                                <textarea class="form-control" id="supplier_notes" name="notes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ translate('Process Payment') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#supplier_id').on('change', function() {
    var supplierId = $(this).val();
    if (supplierId) {
        $.ajax({
            url: '{{ route("accounts.supplier-payments.due", "") }}/' + supplierId,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    $('#supplier_due_text').text('Due amount: ' + parseFloat(response.due_amount).toFixed(2));
                    $('#supplier_due_amount').val(response.due_amount);
                    $('#supplier_payment_amount').attr('max', response.due_amount);
                }
            }
        });
    } else {
        $('#supplier_due_text').text('');
    }
});

$('#supplier_payment_amount').on('keyup', function() {
    var dueAmount = parseFloat($('#supplier_due_amount').val());
    var amount = parseFloat($(this).val());
    if (amount >= dueAmount) {
        $('#supplier_payment_type').val('full');
    } else {
        $('#supplier_payment_type').val('partial');
    }
});

$('#supplierPaymentForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: '{{ route("accounts.supplier-payments.store") }}',
        type: 'POST',
        data: $(this).serialize(),
        success: function(response) {
            if (response.success) {
                $('#supplierPaymentModal').modal('hide');
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