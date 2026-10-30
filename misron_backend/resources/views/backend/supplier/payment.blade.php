@php
    $purchaseNew2 = \App\Models\Purchase::where('supplier_id', $supplier->id)->get();

@endphp

@php
    $total = $supplier->purchases()->sum('total_payment_due');
    // dd($total);
    $totalViaAccessor = $supplier->total_payment_due;
@endphp
<div id="contact_payments_div" class="overflow-auto" style="height: 500px;">
    <div class="d-flex justify-content-end mb-3 mt-3">
        <button class="btn btn-outline-secondary btn-sm print_tab_pdf">
            <i class="fas fa-print"></i> Print
        </button>
        <button class="btn btn-outline-secondary btn-sm download_tab_pdf ms-2">
            <i class="fas fa-download"></i> Download PDF
        </button>
    </div>

    <!-- Payment Due Form -->
    <div class="card mb-3">
        <div class="card-header bg-info text-white">
            <h6 class="mb-0">Make Payment</h6>
        </div>
        <div class="card-body">
            <form id="paymentDueForm">
                @csrf
                <div class="row">
                    <!-- Supplier Total Due Display -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Current Total Payment Due</label>
                        <input type="text" class="form-control bg-light" id="current_total_due" 
                               value="{{ number_format($totalViaAccessor, 2) }}" readonly>
                    </div>
                    
                    <!-- Payment Input -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Payment Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" 
                               max="{{ $totalViaAccessor ?? 0 }}" 
                               class="form-control" id="payment_amount" 
                               name="payment_amount" required>
                        <small class="text-muted">Max: {{ number_format($totalViaAccessor ?? 0, 2) }}</small>
                    </div>
                    <!-- Submit Button -->
                    <div class="col-md-4 mb-3 d-flex align-items-center">
                        <button type="submit" class="btn btn-primary" id="submitPayment">
                            <i class="fas fa-money-bill"></i> Process Payment
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Payment Success/Error Messages -->
            <div id="paymentMessage" style="display: none;"></div>
        </div>
    </div>


    <div class="print-area">
        <table class="table table-bordered" id="contact_payments_table">
            <thead>
                <tr>
                    <th>Purchase ID</th>
                    <th>Paid on</th>
                    <th>Reference No</th>
                    <th>Amount paid</th>
                    <th>Payment Method</th>
                    <th>Total Item</th>
                    <th>Total Purchase Amount</th>
                    <th>Total Payment Due</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchaseNew2 as $product)
                    <tr>
                        <td>{{ $product->id }}</td>
                        <td>
                            @php
                                $dates = is_array($product->advanced_paid_on)
                                    ? $product->advanced_paid_on
                                    : json_decode($product->advanced_paid_on, true) ?? [];
                            @endphp
                            {{ collect($dates)->map(fn($d) => \Carbon\Carbon::parse($d)->format('m/d/Y'))->implode(', ') }}
                        </td>
                        <td>{{ $product->reference_no }}</td>
                        <td>{{ ($product->total_advance_amount ?? 0) +
                            ($product->total_cash_amount ?? 0) +
                            ($product->total_cheque_amount ?? 0) +
                            ($product->total_bank_transfer_amount ?? 0) }}
                        </td>
                        <td>{{ is_array($product->payment_method)
                            ? implode(', ', $product->payment_method)
                            : (is_string($product->payment_method)
                                ? implode(', ', json_decode($product->payment_method, true) ?? [])
                                : $product->payment_method) }}
                        </td>
                        <td>{{ $product->total_item }}</td>
                        <td>{{ $product->net_total_amount }}</td>
                        <td>{{ $product->total_payment_due }}</td>

                    </tr>
                @endforeach
                @if (!$purchaseNew2)
                    <tr>
                        <td colspan="17" class="text-center">{{ translate('No data available') }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>


<script>
$(document).ready(function() {
    $('#paymentDueForm').on('submit', function(e) {
        e.preventDefault();
        
        // Get the payment amount
        let paymentAmount = parseFloat($('#payment_amount').val());
        let currentTotalDue = parseFloat($('#current_total_due').val().replace(/,/g, ''));
        
        // Validate
        if (paymentAmount <= 0) {
            showPaymentMessage('Please enter a valid payment amount', 'danger');
            return;
        }
        
        if (paymentAmount > currentTotalDue) {
            showPaymentMessage('Payment amount cannot exceed total due amount', 'danger');
            return;
        }
        
        // Show loading state
        let submitBtn = $('#submitPayment');
        let originalText = submitBtn.html();
        submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Processing...').prop('disabled', true);
        
        // Send AJAX request
        $.ajax({
            url: '{{ route("supplier.payment.process") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                supplier_id: '{{ $supplier->id }}',
                payment_amount: paymentAmount
            },
            success: function(response) {
                if (response.success) {
                    // Update the total due display
                    $('#current_total_due').val(response.new_total_due_formatted);
                    
                    // Update the max attribute
                    $('#payment_amount').attr('max', response.new_total_due);
                    $('small.text-muted').text('Max: ' + response.new_total_due_formatted);
                    
                    // Clear the payment input
                    $('#payment_amount').val('');
                    
                    // Show success message
                    showPaymentMessage('Payment processed successfully!', 'success');
                    
                    // Optionally reload the table data or update the specific row
                    // You could add a new row to the table or reload the page
                    setTimeout(function() {
                        location.reload(); // Reload to show updated data
                    }, 2000);
                } else {
                    showPaymentMessage(response.message || 'Error processing payment', 'danger');
                }
            },
            error: function(xhr) {
                let errorMsg = 'Error processing payment';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showPaymentMessage(errorMsg, 'danger');
            },
            complete: function() {
                // Restore button state
                submitBtn.html(originalText).prop('disabled', false);
            }
        });
    });
    
    function showPaymentMessage(message, type) {
        let messageDiv = $('#paymentMessage');
        messageDiv.html('<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            '</div>');
        messageDiv.show();
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            messageDiv.fadeOut();
        }, 5000);
    }
});
</script>
