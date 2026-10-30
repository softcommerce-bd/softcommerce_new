@extends('backend.layouts.app')
<style>
    .card {
        transition: all 0.2s;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .card:hover {
        box-shadow: 0 6px 10px rgba(0, 0, 0, 0.2);
        transform: translateY(-5px);
    }

    .card-body {
        overflow-x: hidden;
    }
</style>
@section('content')

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form action="{{ route('purchase.order.store') }}" method="POST" enctype="multipart/form-data" id="purchase_store_form">
        @csrf
        <!-- Supplier Section -->
        <div class="mt-4">
            <div class="card mb-4 rounded">
                <div class="card-body">
                    <div class="row">
                        <!-- Supplier -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="supplier_id">Supplier:*</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">
                                            <i class="fa fa-user"></i>
                                        </span>
                                    </div>
                                    <select class="form-control" required id="supplier_id" name="supplier_id">
                                        <option selected disabled value="">Please Select</option>
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->business_name }}</option>
                                        @endforeach
                                    </select>
                                    <div id="supplierInputMessage" class="message text-danger"></div>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-light bg-white add_new_supplier"
                                            data-toggle="modal" data-target="#addSupplierModal">
                                            <i class="fa fa-plus-circle text-primary fa-lg"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reference No -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="ref_no">Reference No:</label>
                                <i class="fa fa-info-circle text-info" data-toggle="popover" data-trigger="hover"
                                    data-content="Leave empty to autogenerate"></i>
                                <input type="text" class="form-control" id="ref_no" name="reference_no" required readonly>
                            </div>
                            <div id="referInputMessage" class="message text-danger"></div>
                        </div>

                        <!-- Purchase Date -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="transaction_date">Purchase Date:*</label>
                                <input type="date" class="form-control" name="purchase_date" value="" required>
                            </div>
                        </div>

                        <!-- Business Location -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="location_id">Business Location:*</label>
                                <input type="text" class="form-control" name="business_location" required>
                            </div>
                        </div>

                        <!-- Pay Term -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="pay_term">Pay term:</label>
                                <i class="fa fa-info-circle text-info" data-toggle="popover" data-trigger="hover"
                                    data-content="Payments to be paid for purchases/sales within the given time period."></i>
                                <div class="input-group">
                                    <input type="number" class="form-control" placeholder="Pay term" id="pay_term"
                                        name="pay_term" min="0" required>
                                    <select class="form-control" id="pay_term_type" name="pay_term_type" required>
                                        <option selected disabled value="">Please Select</option>
                                        <option value="months">Months</option>
                                        <option value="years">Years</option>
                                        <option value="days">Days</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Attach Document -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="document">Attach Document:</label>
                                <input type="file" class="form-control" id="upload_document" name="document"
                                    accept=".pdf,.csv,.zip,.doc,.docx,.jpeg,.jpg,.png">
                                <small class="form-text text-muted">
                                    Max File size: 5MB<br>
                                    Allowed File: .pdf, .csv, .zip, .doc, .docx, .jpeg, .jpg, .png
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase Entry Section -->
        <div class="mb-4 bg-white shadow-sm rounded-xl">
            <div class="">
                <div class="border border-gray-200 p-2 bg-white">
                    <div class="overflow-x-auto">
                        <div class="py-2 align-middle sm:px-5">
                            <div class="row">
                                <div class="col-sm-8 mx-auto">
                                    <div class="form-group">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fa fa-search"></i>
                                                </span>
                                            </div>
                                            <input class="form-control" id="search_product" placeholder="Enter Product name"
                                                name="search_product" type="text" autocomplete="off">
                                        </div>
                                        <div id="search_results" class="dropdown-menu mt-5"
                                            style="display: none; position: absolute; left:0; z-index: 1000; max-height:500px; overflow-y:auto;">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="table-responsive">
                                        <table class="table table-condensed table-bordered text-center table-striped"
                                            id="purchase_entry_table">
                                            <thead>
                                                <tr>
                                                    <th style="width:5%;">Product ID</th>
                                                    <th style="width:45%;">Product Name</th>
                                                    <th style="width:6%;">Remaining Quantity</th>
                                                    <th style="width:6%;">Purchase Quantity</th>
                                                    <th style="width:6%;">Recieved Quantity</th>
                                                    <th style="width:18%;">Buying Unit cost</th>
                                                    <th style="width:18%;">Total Cost</th>
                                                    {{-- <th>Product Tax(%)</th> --}}
                                                    <th><i class="fa fa-trash" aria-hidden="true"></i></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
                                    </div>
                                    <hr>
                                    <div class="text-right">
                                        <table class="table table-borderless">
                                            <tbody>
                                                <tr>
                                                    <th class="text-right">Total Items:</th>
                                                    <td class="text-left">
                                                        <span id="total_quantity" class="display_currency">0.00</span>
                                                        <input type="hidden" id="total_quantity_input"
                                                            name="total_item">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th class="text-right">Net Total Amount:</th>
                                                    <td class="text-left">
                                                        <span id="total_subtotal" class="display_currency">0</span>
                                                        <input type="hidden" id="total_subtotal_input"
                                                            name="net_total_amount" value="0">
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <input type="hidden" id="row_count" value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Section -->

        <div class="card mb-4 shadow-sm rounded">
            <div class="card-body">
                <h3 class="card-title">Add Payment</h3>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong class="ml-2">Advance Balance:</strong> <span id="advance_balance_text">0</span>
                        <input id="advance_balance" data-error-msg="Required advance balance not available"
                            name="advance_balance" type="hidden">
                    </div>
                </div>
                <div class="border border-gray-200 rounded p-2 p-sm-3">
                    <div id="payment-rows-container">
                        {{-- Payment Row Template --}}
                        <div class="payment-row" id="payment-row-0">
                            <input type="hidden" class="payment_row_index" value="0">
                            <div class="row mb-3 align-items-center">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="amount_0">Amount:*</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-money-bill-alt"></i>
                                                </span>
                                            </div>
                                            <input class="form-control payment-amount" id="amount_0" required
                                                placeholder="Amount" name="payments[0][amount]" type="number"
                                                value="0.00">
                                        </div>
                                        <!-- Error message container -->
                                        <span id="amount-error" class="text-danger" style="display:none;">Required
                                            advance balance not available.</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="paid_on_0">Paid on:*</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fa fa-calendar"></i>
                                                </span>
                                            </div>
                                            <input class="form-control paid_on" required name="payments[0][paid_on]"
                                                type="date" value="">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="method_0">Payment Method:*</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-money-bill-alt"></i>
                                                </span>
                                            </div>
                                            <select class="form-control" required id="method_0"
                                                name="payments[0][method]">
                                                <option value="cash">Cash</option>
                                                <option value="advance">Advance</option>
                                                <option value="cheque">Cheque</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                            </select>


                                        </div>
                                    </div>
                                </div>

                                <!-- Action button: Initially 'Add More Payment', clones will show 'Delete' -->
                                <div class="col-md-1 d-flex align-items-center justify-content-center action-btn pt-2">
                                    <button type="button" class="btn btn-success" id="add-payment-btn"><i
                                            class="las la-plus"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Optional Notes -->
                    <div class="form-group">
                        <label for="note">Notes:</label>
                        <textarea class="form-control" id="note" name="payment_note" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-sm-12 text-right">
                            <strong>Payment due:</strong> <span id="payment_due">0.00</span>
                            <input type="hidden" id="payment_due_input" name="total_payment_due">
                        </div>
                    </div>

                    <br>

                    <!-- Submit Button -->
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" id="submit_button">Save Draft</button>
                        {{-- <button type="button" class="btn btn-primary" id="confirm_complete_btn">Complete Purchase</button> --}}
                    </div>
                </div>
            </div>
        </div>
    </form>




    {{-- supplier add modal  --}}
    {{-- <div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog" aria-labelledby="addSupplierModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSupplierModalLabel">Add New Supplier</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('supplier.store') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="business_name">Business Name:</label>
                                    <input type="text" name="business_name" class="form-control mb-2"
                                        id="business_name">
                                </div>
                                <div class="form-group d-none">
                                    <label for="contact_id">Contact ID:</label>
                                    <input type="text" name="contact_id" class="form-control mb-2" id="contact_id_new"
                                        readonly>
                                </div>
                                <div class="form-group">
                                    <label for="mobile">Mobile:</label>
                                    <input type="number" name="mobile" class="form-control mb-2" id="mobile">
                                </div>
                                <div class="form-group">
                                    <label for="opening_balance">Opening Balance:</label>
                                    <input type="number" name="opening_balance" class="form-control mb-2"
                                        id="opening_balance" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="address">Address:</label>
                                    <input type="text" name="address" class="form-control mb-2" id="address">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contact_person_name">Contact Person Name:</label>
                                    <input type="text" name="contact_person_name" class="form-control mb-2"
                                        id="contact_person_name">
                                </div>
                                <div class="form-group">
                                    <label for="email">Email:</label>
                                    <input type="email" name="email" class="form-control mb-2" id="email">
                                </div>
                               <div class="form-group">
                                    <label for="tax_number">Tax Number:</label>
                                    <input type="text" name="tax_number" class="form-control mb-2" value="0" id="tax_number">
                                </div>
                                <div class="form-group">
                                    <label for="pay_term">Pay Term:</label>
                                    <select name="pay_term" class="form-control mb-2" id="pay_term">
                                    <option value="cash">Cash</option>
                                    <option value="bill_to_bill">Bill To Bill</option>
                                    <option value="days">Days</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="after_sales">After Sales</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div> --}}
    @include('backend.supplier.supplier_modal.modal')

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
                                    <input type="text" class="form-control" id="modal_supplier" 
                                           value="{{ $purchase->supplier->business_name ?? '' }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="modal_ref_no"><strong>Reference No:</strong></label>
                                    <input type="text" class="form-control" id="modal_ref_no" 
                                           value="{{ $purchase->reference_no ?? ''}}" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="modal_purchase_date"><strong>Purchase Date:</strong></label>
                                    <input type="date" class="form-control" id="modal_purchase_date" 
                                           value="{{ $purchase->purchase_date ?? ''}}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products with Received Quantity > 0 -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Products to Complete (Received Quantity > 0)</h6>
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
                                    <input type="text" class="form-control text-right" id="modal_payment_due_input" 
                                           value="{{ $purchase->total_payment_due ?? '0.00' }}" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="modal_payment_note"><strong>Notes:</strong></label>
                            <textarea class="form-control" id="modal_payment_note" rows="3">{{ $purchase->payment_note ?? ''}}</textarea>
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
@endsection
@section('script')
    <!-- Bootstrap 5 JavaScript for Popover -->
    <script>
        $(function() {
            $('[data-toggle="popover"]').popover()
        });
        // Auto generate Contact ID and reference
         document.addEventListener("DOMContentLoaded", function() {
                document.getElementById("contact_id_new").value = "Nisa-" + Math.floor(Math.random() * 100000);
                document.getElementById("ref_no").value = "NisaRef-" + Math.floor(Math.random() * 100000);
            });
        
    </script>

    <script>
        $(document).ready(function() {

            function checkReviewButton() {
            let hasReceivedQuantity = false;
            
            $('input[name="recieved_quantity[]"]').each(function() {
                let receivedQty = parseFloat($(this).val()) || 0;
                if (receivedQty > 0) {
                    hasReceivedQuantity = true;
                    return false; // break the loop
                }
            });
            
            $('#review_complete_btn').prop('disabled', !hasReceivedQuantity);
            
            // Add visual feedback for disabled state
            if (!hasReceivedQuantity) {
                $('#review_complete_btn').attr('title', 'Add received quantity to enable this button');
            } else {
                $('#review_complete_btn').removeAttr('title');
            }
        }

        // Populate and show the modal
$('#review_complete_btn').on('click', function() {
    if ($(this).prop('disabled')) return;
    
    // Populate products with received quantity > 0
    let productsHtml = '';
    let totalNetAmount = 0;
    
    $('#purchase_entry_table tbody tr').each(function() {
        let $row = $(this);
        let receivedQty = parseFloat($row.find('input[name="recieved_quantity[]"]').val()) || 0;
        
        if (receivedQty > 0) {
            let productId = $row.find('input[name="product_id[]"]').val();
            let productName = $row.find('input[name="name[]"]').val();
            let purchaseQty = $row.find('input[name="purchase_quantity[]"]').val();
            let unitCost = $row.find('input[name="buying_unit_cost[]"]').val();
            let totalCost = $row.find('input[name="total_cost[]"]').val();
            
            productsHtml += `
                <tr data-product-id="${productId}">
                    <td>${productId}</td>
                    <td>${productName}</td>
                    <td>
                        <input type="text" class="form-control modal-purchase-quantity" 
                               value="${purchaseQty}" data-original-value="${purchaseQty}">
                    </td>
                    <td>
                        <input type="text" class="form-control modal-received-quantity" 
                               value="${receivedQty}" data-original-value="${receivedQty}">
                    </td>
                    <td>
                        <input type="text" class="form-control modal-unit-cost" 
                               value="${unitCost}" data-original-value="${unitCost}">
                    </td>
                    <td>
                        <input type="text" class="form-control modal-total-cost" 
                               value="${totalCost}" data-original-value="${totalCost}" readonly>
                    </td>
                </tr>
            `;
            
            totalNetAmount += parseFloat(totalCost) || 0;
        }
    });
    
    $('#modal_products_body').html(productsHtml);
    
    // Populate payment information
    let paymentHtml = '';
    let paymentIndex = 0;
    $('.payment-row').each(function(index) {
        let amount = $(this).find('.payment-amount').val();
        let paidOn = $(this).find('.paid_on').val();
        let method = $(this).find('select').val();
        
        paymentHtml += `
            <div class="payment-modal-row mb-3 border-bottom pb-3" data-index="${index}">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Amount:</label>
                            <input type="number" class="form-control modal-payment-amount" 
                                   value="${amount}" data-original-value="${amount}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Paid On:</label>
                            <input type="date" class="form-control modal-paid-on" 
                                   value="${paidOn}" data-original-value="${paidOn}">
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
                        <button type="button" class="btn btn-danger btn-sm delete-modal-payment" ${paymentIndex === 0 ? 'disabled' : ''}>
                            <i class="las la-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        paymentIndex++;
    });
    
    $('#modal_payment_info').html(paymentHtml);
    $('#modal_payment_due_input').val(totalNetAmount.toFixed(2));
    
    // Add event listeners for modal inputs
    attachModalEventListeners();
    
    // Show the modal
    $('#reviewCompleteModal').modal('show');
});

// Attach event listeners to modal inputs
function attachModalEventListeners() {
    // Product quantity and cost calculations
    $(document).on('input', '.modal-purchase-quantity, .modal-received-quantity, .modal-unit-cost', function() {
        let $row = $(this).closest('tr');
        let purchaseQty = parseFloat($row.find('.modal-purchase-quantity').val()) || 0;
        let receivedQty = parseFloat($row.find('.modal-received-quantity').val()) || 0;
        let unitCost = parseFloat($row.find('.modal-unit-cost').val()) || 0;
        
        let totalCost = purchaseQty * unitCost;
        $row.find('.modal-total-cost').val(totalCost.toFixed(2));
        
        updateModalTotals();
    });

    // Payment amount changes
    $(document).on('input', '.modal-payment-amount', function() {
        updateModalTotals();
    });

    // Delete payment in modal
    $(document).on('click', '.delete-modal-payment', function() {
        if ($('.payment-modal-row').length > 1) {
            $(this).closest('.payment-modal-row').remove();
            updateModalTotals();
        }
    });
}

// Update modal totals
function updateModalTotals() {
    let totalNetAmount = 0;
    
    $('#modal_products_body tr').each(function() {
        let totalCost = parseFloat($(this).find('.modal-total-cost').val()) || 0;
        totalNetAmount += totalCost;
    });
    
    let totalPayment = 0;
    $('.modal-payment-amount').each(function() {
        totalPayment += parseFloat($(this).val()) || 0;
    });
    
    let paymentDue = totalNetAmount - totalPayment;
    $('#modal_payment_due_input').val(paymentDue.toFixed(2));
}

// Handle complete purchase button
$('#confirm_complete_btn').on('click', function() {
    // Update main form with modal data
    updateMainFormFromModal();
    
    // Submit the form
    $('#purchase_update_form').submit();
});

// Update main form with modal data
function updateMainFormFromModal() {
    // Update products
    $('#modal_products_body tr').each(function() {
        let productId = $(this).data('product-id');
        let purchaseQty = $(this).find('.modal-purchase-quantity').val();
        let receivedQty = $(this).find('.modal-received-quantity').val();
        let unitCost = $(this).find('.modal-unit-cost').val();
        let totalCost = $(this).find('.modal-total-cost').val();
        
        // Find and update corresponding row in main form
        $('#purchase_entry_table tbody tr').each(function() {
            let mainProductId = $(this).find('input[name="product_id[]"]').val();
            if (mainProductId == productId) {
                $(this).find('input[name="purchase_quantity[]"]').val(purchaseQty);
                $(this).find('input[name="recieved_quantity[]"]').val(receivedQty);
                $(this).find('input[name="buying_unit_cost[]"]').val(unitCost);
                $(this).find('input[name="total_cost[]"]').val(totalCost);
            }
        });
    });
    
    // Update payments
    let paymentIndex = 0;
    $('.payment-modal-row').each(function() {
        let amount = $(this).find('.modal-payment-amount').val();
        let paidOn = $(this).find('.modal-paid-on').val();
        let method = $(this).find('.modal-payment-method').val();
        
        // Find and update corresponding payment row in main form
        let $mainPaymentRow = $('.payment-row').eq(paymentIndex);
        if ($mainPaymentRow.length) {
            $mainPaymentRow.find('.payment-amount').val(amount);
            $mainPaymentRow.find('.paid_on').val(paidOn);
            $mainPaymentRow.find('select').val(method);
        }
        paymentIndex++;
    });
    
    // Update purchase date
    $('input[name="purchase_date"]').val($('#modal_purchase_date').val());
    
    // Update payment note
    $('textarea[name="payment_note"]').val($('#modal_payment_note').val());
    
    // Recalculate totals in main form
    calculateTotals();
}

            // supplier opening balance 
            $('#supplier_id').change(function() {
                var supplierId = $(this).val();

                if (supplierId) {
                    $.ajax({
                        url: "{{ route('supplier_balance', ':supplierId') }}".replace(':supplierId',
                            supplierId), // Replace the placeholder with supplierId
                        type: 'GET',
                        success: function(response) {
                            // Update the Advance Balance text and input field
                            $('#advance_balance_text').text(response.balance);
                            $('#advance_balance').val(response.balance);
                        },
                        error: function() {
                            alert('Error retrieving supplier balance');
                        }
                    });
                }
            });

            let searchTimeout;

            $('#search_product').on('keyup', function() {
                clearTimeout(searchTimeout); // Clear previous timeout
                var query = $(this).val().trim();

                if (query.length > 0) {
                    searchTimeout = setTimeout(function() {
                        $.ajax({
                            url: '{{ route('product.ajaxsearch') }}',
                            method: 'GET',
                            data: { search: query },
                            success: function(response) {
                                // alert(response.data);
                                var dropdown = $('#search_results');
                                dropdown.empty();

                                if (response.data.length > 0) {
                                    $.each(response.data, function(index, product) {
                                        dropdown.append(
                                            '<a href="#" class="dropdown-item" data-id="' + product.id + 
                                            '" data-name="' + product.name + '">' + product.name + '</a>'
                                        );
                                    });
                                    dropdown.show();
                                } else {
                                    dropdown.append('<a href="#" class="dropdown-item disabled text-danger">No items found</a>');
                                    dropdown.show();
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX Error: ' + status + error);
                            }
                        });
                    }, 300); // Delay request by 300ms
                } else {
                    $('#search_results').empty().hide();
                }
            });
            $('#search_product').on('keypress', function(event) {
                if (event.keyCode === 13) { // Enter key
                    event.preventDefault();
                }
            });

            $(document).on('click', '.dropdown-item', function(event) {
                event.preventDefault();
                var productId = $(this).data('id');

                // AJAX call to fetch the selected product and its variants
                $.ajax({
                    url: '{{ route('purchase.order.ajaxsearch') }}',
                    method: 'GET',
                    data: {
                        id: productId
                    },
                    success: function(response) {
                        var product = response.data;
                        var variants = response.variants || [];

                        if (variants.length > 0) {
                            // If product has variants, generate rows for each variant
                            $.each(variants, function(index, variant) {
                                addProductRow(product.id, product.name, variant.variant, variant.qty,
                                    variant.price);
                            });
                        } else {
                            // If no variants, generate a single row
                            addProductRow(product.id, product.name, '', variant.qty || 0, product.buying_unit_cost);
                        }

                        $('#search_product').val(''); // Clear the search input field
                        $('#search_results').empty().hide(); // Hide dropdown after selection
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error: ' + status + error);
                    }
                });
            });

            // Function to add a product row to the table
            function addProductRow(productId, productName, variant, remainingQty, price) {
                var row = '<tr>';
                row += '<td><input type="text" name="product_id[]" class="form-control" value="' + productId + '" readonly></td>';
                row += '<td><input type="text" name="name[]" class="form-control" value="' + productName + 
                        (variant ? ' (' + variant + ')' : '') + '" readonly></td>';
                row += '<input type="hidden" name="variant[]" value="' + (variant ? variant : '') + '">'; // Add hidden input for variant
                row += '<td><input type="number" name="remaining_quantity[]" class="form-control remaining-quantity" value="' + (remainingQty ? remainingQty : '0') + '" readonly></td>';
                row += '<td><input type="number" name="purchase_quantity[]" class="form-control purchase-quantity" value="0"></td>';
                row += '<td><input type="number" name="recieved_quantity[]" class="form-control recieved-quantity" value="0"></td>';
                row += '<td><input type="text" name="buying_unit_cost[]" class="form-control buying-unit-cost" value="" readonly></td>';
                row += '<td><input type="text" name="total_cost[]" class="form-control total-cost" value=""></td>';
                row += '<td><button class="btn btn-danger delete-product"><i class="fa fa-trash"></i></button></td>';
                row += '</tr>';

                $('#purchase_entry_table tbody').append(row);
            }



            // Function to calculate and update buying_unit_cost per row
            function calculateBuyingUnitCost(row) {
                var purchaseQuantity = parseFloat(row.find('.purchase-quantity').val()) || 0;
                var totalCost = parseFloat(row.find('.total-cost').val()) || 0;
                // var tax = parseFloat(row.find('.tax').val()) || 0;

                if (purchaseQuantity > 0) {
                    // var buyingUnitCost = totalCost / (purchaseQuantity * (1 + tax / 100));
                    var buyingUnitCost = totalCost / (purchaseQuantity);
                    row.find('.buying-unit-cost').val(buyingUnitCost.toFixed(2));
                } else {
                    row.find('.buying-unit-cost').val('');
                }
            }

            // Event listeners for calculation per row
            $(document).on('input', '.purchase-quantity, .total-cost', function() {
                var row = $(this).closest('tr');
                calculateBuyingUnitCost(row);
            });

            // Close the dropdown menu if the user clicks outside of it
            $(document).on('click', function(event) {
                var target = $(event.target);
                if (!target.closest('#search_results').length && !target.closest('#search_product')
                    .length) {
                    $('#search_results').empty().hide(); // Hide dropdown if click is outside
                }
            });


            function calculateTotals() {
                var totalQuantity = 0;
                var netTotal = 0;

                $('#purchase_entry_table tbody tr').each(function() {
                    var purchaseQuantity = parseFloat($(this).find('.purchase-quantity').val()) || 0;
                    var totalCost = parseFloat($(this).find('.total-cost').val()) || 0;

                    totalQuantity += purchaseQuantity;
                    netTotal += totalCost;
                });

                $('#total_quantity').text(totalQuantity.toFixed(2));
                $('#total_quantity_input').val(totalQuantity.toFixed(2));
                $('#total_subtotal').text(netTotal.toFixed(2));
                $('#total_subtotal_input').val(netTotal.toFixed(2));
                $('#payment_due').text(netTotal.toFixed(2));


            }

            // Trigger totals calculation when quantity, cost, or tax fields change
            $(document).on('input', '.purchase-quantity, .total-cost', function() {
                var row = $(this).closest('tr');
                calculateBuyingUnitCost(row); // Recalculate buying unit cost
                calculateTotals(); // Recalculate totals
            });

            // Recalculate totals when a product is added or removed
            $(document).on('click', '.delete-product', function() {
                $(this).closest('tr').remove();
                calculateTotals(); // Recalculate totals after row removal
            });


        });

        $(document).ready(function() {
            // Handle Add Payment button click
            $('#add-payment-btn').on('click', function() {
                // Get the container where the payment rows are added
                let container = $('#payment-rows-container');

                // Get the current number of payment rows
                let currentIndex = $('.payment-row').length;

                // Clone the first payment row
                let newRow = $('.payment-row:first').clone();

                // Update the new row's inputs and attributes
                newRow.attr('id', `payment-row-${currentIndex}`);
                newRow.find('.payment-amount').attr('name', `payments[${currentIndex}][amount]`);
                newRow.find('.paid_on').attr('name', `payments[${currentIndex}][paid_on]`);
                newRow.find('select').attr('name', `payments[${currentIndex}][method]`);

                // Clear the values for the new row
                newRow.find('.payment-amount').val('0.00');
                newRow.find('.paid_on').val('');
                newRow.find('select').prop('selectedIndex', 0);

                // Remove any existing "Add More Payment" button from the cloned row and replace it with a "Delete" button
                newRow.find('#add-payment-btn').remove(); // Remove the "Add More Payment" button
                newRow.find('.action-btn').html(
                    '<button type="button" class="btn btn-danger delete-payment-btn"><i class="las la-trash"></i></button>'
                ); // Add Delete button

                // Append the new row to the container
                container.append(newRow);
            });

            // Handle delete button click
            $(document).on('click', '.delete-payment-btn', function() {
                $(this).closest('.payment-row').remove();
            });

            // Bind the blur event to dynamically added rows using delegated event handling
            $(document).on('blur', '.payment-amount', function() {
                // Get the payment row's index
                let paymentRow = $(this).closest('.payment-row');
                let rowIndex = paymentRow.attr('id').split('-')[2];

                // Get the selected payment method for the specific row
                let paymentMethod = $(`#method_${rowIndex}`).val();

                // Get the payment amount and convert it to a float
                let amountPaymentValue = parseFloat($(`#amount_${rowIndex}`).val()) || 0;

                // Get the advance balance and convert it to a float
                let advanceBalance = parseFloat($('#advance_balance_text').text()) || 0;

                // Error message span
                let errorMessage = paymentRow.find('#amount-error');

                // If 'Advance' is selected and the amount is greater than the advance balance
                if (paymentMethod === 'advance' && amountPaymentValue > advanceBalance) {
                    // Show error message
                    errorMessage.text('Required advance balance not available.');
                    errorMessage.show();
                    return;
                } else {
                    // Hide the error message if validation passes
                    errorMessage.hide();
                }

                // Proceed with calculating the new payment due if no errors
                let paymentDueValue = parseFloat($('#payment_due').text()) || 0;
                let newPaymentDue = paymentDueValue - amountPaymentValue;

                // Update the payment due fields
                $('#payment_due').text(newPaymentDue.toFixed(2));
                $('#payment_due_input').val(newPaymentDue.toFixed(2));
            });
        });
    </script>

    <script>
        $(document).ready(function() {
            $('#purchase_store_form').on('submit', function(e) {
                e.preventDefault();
                console.log('Form submitted successfully');

                // Create a FormData object
                var formData = new FormData(this);

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        console.log('Form submitted successfully');
                        console.log(response);

                        if (response.success) {
                            toastr.success(response.message, 'Success');

                            // Optionally reset the form
                              $('#purchase_store_form')[0].reset();

                            // Remove all rows from the purchase entry table
                            $('#purchase_entry_table tbody').empty();

                            if (response.redirect_url) {
                                window.location.href = response.redirect_url;
                            }
                        } else {
                            if (response.type === 'warning') {
                                toastr.warning(response.message, 'Warning');
                            } else {
                                toastr.error(response.message || 'Something went wrong.',
                                    'Error');
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error submitting form');
                        console.error(xhr.responseText);

                        // Show error toastr notification
                        toastr.error(error, 'error');
                    }
                });
            });
        });
    </script>

@endsection
