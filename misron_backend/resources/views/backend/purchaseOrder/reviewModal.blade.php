@if (!isset($isIncluded))
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endif

<!-- Review and Complete Modal -->
<div class="modal fade" id="reviewCompleteModal" tabindex="-1" role="dialog" aria-labelledby="reviewCompleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reviewCompleteModalLabel">Review and Complete Purchase Order</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Hidden fields for original data -->
                <input type="hidden" id="modal_supplier_id">
                <input type="hidden" id="modal_business_location">
                <input type="hidden" id="pay_term_type">
                <input type="hidden" id="pay_term">
                <input type="hidden" id="modal_original_ref_no">
                
                <!-- Supplier Information -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Supplier Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="modal_supplier"><strong>Supplier:</strong></label>
                                    <input type="text" class="form-control" id="modal_supplier" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="modal_ref_no"><strong>Reference No:</strong></label>
                                    <input type="text" class="form-control" id="modal_ref_no" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="modal_purchase_date"><strong>Purchase Date:</strong></label>
                                    <input type="date" class="form-control" id="modal_purchase_date">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Products</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="modal_products_table">
                                <thead>
                                    <tr>
                                        <th>Product ID</th>
                                        <th>Product Name</th>
                                        <th>Purchase Quantity</th>
                                        <th>Received Quantity</th>
                                        <th>Buying Unit Cost</th>
                                        <th>Total Cost</th>
                                    </tr>
                                </thead>
                                <tbody id="modal_products_body">
                                    <!-- Products will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Payment Information</h6>
                    </div>
                    <div class="card-body">
                        <div id="modal_payment_info">
                            <!-- Payment details will be populated by JavaScript -->
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12 text-right">
                                <div class="form-group">
                                    <label for="modal_payment_due_input"><strong>Payment Due:</strong></label>
                                    <input type="text" class="form-control text-right" id="modal_payment_due_input" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="modal_payment_note"><strong>Notes:</strong></label>
                            <textarea class="form-control" id="modal_payment_note" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="confirm_complete_btn">Complete Purchase</button>
            </div>
        </div>
    </div>
</div>

<script>
// Get CSRF token for AJAX requests
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// Global function to open review modal from anywhere
function openReviewCompleteModal(purchaseId) {
    // Show loading state
    $('#reviewCompleteModal').modal('show');
    $('#modal_products_body').html('<tr><td colspan="6" class="text-center">Loading...</td></tr>');
    $('#modal_payment_info').html('Loading payment information...');
    
    // Fetch modal data via AJAX
    $.ajax({
        url: `review-modal/${purchaseId}`,
        type: 'GET',
        headers: {
            'X-CSRF-TOKEN': csrfToken
        },
        success: function(response) {
            if (response.success) {
                populateModal(response.data, purchaseId);
            } else {
                toastr.error('Failed to load purchase data');
                $('#reviewCompleteModal').modal('hide');
            }
        },
        error: function(xhr, status, error) {
            toastr.error('Error loading purchase data');
            $('#reviewCompleteModal').modal('hide');
            console.error('Error:', error);
        }
    });
}

function populateModal(data, purchaseId) {
    // Set basic info
    $('#modal_supplier').val(data.supplier);
    $('#modal_ref_no').val(data.reference_no);
    $('#modal_purchase_date').val(data.purchase_date);
    $('#modal_payment_note').val(data.payment_note || '');
    
    // Store original data in hidden fields
    $('#modal_supplier_id').val(data.supplier_id);
    $('#modal_business_location').val(data.business_location);
    $('#pay_term_type').val(data.pay_term_type);
    $('#pay_term').val(data.pay_term);
    $('#modal_original_ref_no').val(data.reference_no);
    
    // Populate products
    let productsHtml = '';
    let totalNetAmount = 0;
    
    data.product_id.forEach(product => {
        let receivedQty = product.received_quantity || 0;
        let unitCost = parseFloat(product.buying_unit_cost) || 0;
        let totalCost = receivedQty * unitCost;
        
        productsHtml += `
            <tr data-product-id="${product.id}">
                <td>${product.id}</td>
                <td>${product.name}</td>
                <td>${product.purchase_quantity}</td>
                <td>
                    <input type="number" class="form-control modal-received-quantity" 
                        value="${receivedQty}" min="0">
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control modal-unit-cost" 
                        value="${unitCost}" min="0">
                </td>
                <td>
                    <input type="text" class="form-control modal-total-cost" 
                        value="${totalCost.toFixed(2)}" readonly>
                </td>
            </tr>
        `;
        
        totalNetAmount += totalCost;
    });
    
    $('#modal_products_body').html(productsHtml);
    
    // Populate payment information
    let paymentHtml = '';
    if (data.payment_methods && Array.isArray(data.payment_methods)) {
        data.payment_methods.forEach((method, index) => {
            let amount = data.payment_amounts ? data.payment_amounts[index] || 0 : 0;
            let paidOn = data.payment_paid_on ? data.payment_paid_on[index] || '' : '';
            
            paymentHtml += `
                <div class="payment-modal-row mb-3 border-bottom pb-3" data-index="${index}">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Amount:</label>
                                <input type="number" step="0.01" class="form-control modal-payment-amount" 
                                       value="${amount}" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Paid On:</label>
                                <input type="date" class="form-control modal-paid-on" 
                                       value="${paidOn}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Payment Method:</label>
                                <select class="form-control modal-payment-method">
                                    <option value="cash" ${method == 'cash' ? 'selected' : ''}>Cash</option>
                                    <option value="advance" ${method == 'advance' ? 'selected' : ''}>Advance</option>
                                    <option value="cheque" ${method == 'cheque' ? 'selected' : ''}>Cheque</option>
                                    <option value="bank_transfer" ${method == 'bank_transfer' ? 'selected' : ''}>Bank Transfer</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-danger btn-sm delete-modal-payment" ${index === 0 ? 'disabled' : ''}>
                                <i class="las la-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    $('#modal_payment_info').html(paymentHtml);
    $('#modal_payment_due_input').val(data.total_payment_due || '0.00');
    
    // Store purchase ID for completion
    $('#confirm_complete_btn').data('purchase-id', purchaseId);
    
    // Attach event listeners
    attachModalEventListeners();
    updateModalTotals();
}

function attachModalEventListeners() {
    $(document).off('input', '.modal-received-quantity, .modal-unit-cost');
    $(document).off('input', '.modal-payment-amount');
    $(document).off('click', '.delete-modal-payment');
    
    $(document).on('input', '.modal-received-quantity, .modal-unit-cost', function() {
        let $row = $(this).closest('tr');
        let receivedQty = parseFloat($row.find('.modal-received-quantity').val()) || 0;
        let unitCost = parseFloat($row.find('.modal-unit-cost').val()) || 0;
        
        let modalTotalCost = receivedQty * unitCost;
        $row.find('.modal-total-cost').val(modalTotalCost.toFixed(2));
        
        updateModalTotals();
    });

    $(document).on('input', '.modal-payment-amount', function() {
        updateModalTotals();
    });

    $(document).on('click', '.delete-modal-payment', function() {
        if ($('.payment-modal-row').length > 1) {
            $(this).closest('.payment-modal-row').remove();
            updateModalTotals();
        }
    });
}

function updateModalTotals() {
    let totalNetAmount = 0;
    
    $('#modal_products_body tr').each(function() {
        let modalTotalCost = parseFloat($(this).find('.modal-total-cost').val()) || 0;
        totalNetAmount += modalTotalCost;
    });
    
    let totalPayment = 0;
    $('.modal-payment-amount').each(function() {
        totalPayment += parseFloat($(this).val()) || 0;
    });
    
    let paymentDue = totalNetAmount - totalPayment;
    let $paymentDueInput = $('#modal_payment_due_input');
    
    $paymentDueInput.val(paymentDue.toFixed(2));
    
    if (paymentDue < 0) {
        $paymentDueInput.addClass('text-danger font-weight-bold');
        $paymentDueInput.removeClass('text-success');
    } else if (paymentDue > 0) {
        $paymentDueInput.addClass('text-success font-weight-bold');
        $paymentDueInput.removeClass('text-danger');
    } else {
        $paymentDueInput.removeClass('text-danger text-success font-weight-bold');
    }
}

// Handle complete purchase from modal
$(document).on('click', '#confirm_complete_btn', function() {
    let purchaseId = $(this).data('purchase-id');
    completePurchase(purchaseId);
});

function completePurchase(purchaseId) {
    let formData = collectModalData();
    // console.log(formData);
    // alert(formData);
    
    let $btn = $('#confirm_complete_btn');
    $btn.prop('disabled', true).html('<i class="las la-spinner la-spin"></i> Processing...');
    
    $.ajax({
        url: `convert/${purchaseId}`,
        type: 'POST',
        data: formData,
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(response) {
            $btn.prop('disabled', false).html('Complete Purchase');
            if (response.success) {
                toastr.success(response.message || 'Purchase completed successfully!', 'Success');
                $('#reviewCompleteModal').modal('hide');
                if (response.redirect_url) {
                    window.location.href = response.redirect_url;
                }
            } else {
                toastr.error(response.message || 'Something went wrong!', 'Error');
            }
        },
        error: function(xhr, status, error) {
            $btn.prop('disabled', false).html('Complete Purchase');
            toastr.error('An error occurred while completing the purchase.', 'Error');
            console.error('Error:', xhr.responseText);
        }
    });

    $.ajax({
    url: `update/${purchaseId}`,
    type: 'POST',
    data: formData,
    headers: {
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
    },
    success: function(response) {
        console.log('Update route success:', response);
    },
    error: function(xhr, status, error) {
        console.error('Update route failed:', xhr.responseText);
    }
});
}

function collectModalData() {
    let data = {
        _token: csrfToken,
        supplier_id: $('#modal_supplier_id').val(),
        reference_no: $('#modal_original_ref_no').val(),
        purchase_date: $('#modal_purchase_date').val(),
        business_location: $('#modal_business_location').val(),
        pay_term: $('#pay_term').val() || '',  
        pay_term_type: $('#pay_term_type').val() || '',

        search_product: null,  // as per your example

        product_id: [],
        name: [],
        variant: [],
        purchase_quantity: [],
        buying_unit_cost: [],
        total_cost: [],

        total_item: 0,          // You can calculate this below
        net_total_amount: 0,    // Calculate below
        advance_balance: $('#advance_balance').val() || '', // Add this field somewhere too

        payments: [],
        payment_note: $('#modal_payment_note').val() || null,
        total_payment_due: $('#modal_payment_due_input').val() || '0.00',
    };

    let totalItemQty = 0;
    let netTotal = 0;

    $('#modal_products_body tr').each(function() {
        let $row = $(this);
        data.product_id.push($row.data('product-id'));

        data.name.push($row.find('td:nth-child(2)').text().trim());
        // If your variants exist in hidden inputs or somewhere, fetch them, else null:
        data.variant.push(null); 

        let purchaseQty = $row.find('.modal-received-quantity').val() || '0';
        data.purchase_quantity.push(purchaseQty);

        let buyingCost = $row.find('.modal-unit-cost').val() || '0';
        data.buying_unit_cost.push(buyingCost);

        let totalCost = $row.find('.modal-total-cost').val() || '0';
        data.total_cost.push(totalCost);

        totalItemQty += parseFloat(purchaseQty);
        netTotal += parseFloat(totalCost);
    });

    data.total_item = totalItemQty.toFixed(2);
    data.net_total_amount = netTotal.toFixed(2);

    // advance_balance element must be somewhere in your modal or page, else leave empty or 0
    // You can assign it manually or fetch it.

    $('.payment-modal-row').each(function() {
        data.payments.push({
            amount: $(this).find('.modal-payment-amount').val(),
            paid_on: $(this).find('.modal-paid-on').val(),
            method: $(this).find('.modal-payment-method').val()
        });
    });

    return data;
}


// Make the function globally available
window.openReviewCompleteModal = openReviewCompleteModal;
</script>