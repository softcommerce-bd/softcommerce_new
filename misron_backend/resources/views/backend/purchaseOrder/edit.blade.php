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
    @include('backend.purchaseOrder.reviewModal')

    <form id="purchase_update_form" action="{{ route('purchase.order.update', $purchase->id) }}" method="POST"
        enctype="multipart/form-data">
        {{-- @method('PUT') --}}
        @csrf
        <!-- Your existing form content remains exactly the same -->
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
                                    <select class="form-control d-none" required id="supplier_id" name="supplier_id">
                                        <option selected disabled value="">Please Select</option>
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}"
                                                {{ $purchase->supplier_id == $supplier->id ? 'selected' : '' }}>
                                                {{ $supplier->business_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="text" value="{{ $purchase->supplier->business_name }}" disabled
                                        class="form-control">
                                </div>
                            </div>
                        </div>

                        <!-- Reference No -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="ref_no">Reference No:</label>
                                <i class="fa fa-info-circle text-info" data-toggle="popover" data-trigger="hover"
                                    data-content="Leave empty to autogenerate"></i>
                                <input type="text" class="form-control" id="ref_no" name="reference_no"
                                    value="{{ $purchase->reference_no }}" readonly>
                            </div>
                        </div>

                        <!-- Purchase Date -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="transaction_date">Purchase Date:*</label>
                                <input type="date" class="form-control" name="purchase_date"
                                    value="{{ $purchase->purchase_date }}">
                            </div>
                        </div>

                        <!-- Business Location -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="location_id">Business Location:*</label>
                                <input type="text" class="form-control" name="business_location"
                                    value="{{ $purchase->business_location }}">
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
                                        name="pay_term" min="0" value="{{ $purchase->pay_term }}">
                                    <select class="form-control" id="pay_term_type" name="pay_term_type">
                                        <option selected disabled value="">Please Select</option>
                                        <option value="months" {{ $purchase->pay_term_type == 'months' ? 'selected' : '' }}>
                                            Months</option>
                                        <option value="years" {{ $purchase->pay_term_type == 'years' ? 'selected' : '' }}>
                                            Years</option>
                                        <option value="days" {{ $purchase->pay_term_type == 'days' ? 'selected' : '' }}>
                                            Days</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Attach Document -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="document">Attach Document:</label>
                                @if ($purchase->document)
                                    <p>Current Document:
                                        <a href="{{ asset('storage/app/public/' . $purchase->document) }}"
                                            target="_blank">View Document</a>
                                    </p>
                                @endif
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
        <div class="">
            <div class="border border-gray-200 p-2 bg-white">
                <div class="overflow-x-auto">
                    <div class="py-2 align-middle sm:px-5">
                        <!-- Product Search Input -->
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
                                        style="display: none; position: absolute; left:0; z-index: 1000;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Purchase Entry Table -->
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="table-responsive">
                                    <table class="table table-condensed table-bordered text-center table-striped"
                                        id="purchase_entry_table">
                                        <thead>
                                            <tr>
                                                <th style="width:5px;">Product ID</th>
                                                <th style="width:45%;">Product Name</th>
                                                <th style="width:6%;">Remaining Quantity</th>
                                                <th style="width:18%;">MRP</th>
                                                <th style="width:6%;">Purchase Quantity</th>
                                                <th style="width:18%;">TP</th>
                                                <th style="width:18%;">Total Cost</th>
                                                <th><i class="fa fa-trash" aria-hidden="true"></i></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($purchaseProducts as $product)
                                                <tr>
                                                    <td><input type="text" name="product_id[]" class="form-control"
                                                            value="{{ $product->product_id }}" readonly></td>
                                                    <td><input type="text" name="name[]" class="form-control"
                                                            value="{{ $product->product_name }}" readonly></td>
                                                    <td><input type="text" name="remaining_quantity[]"
                                                            class="form-control remaining-quantity"
                                                            value="{{ $product->remaining_quantity ?? 0 }}" readonly></td>
                                                    <td><input type="text" name="mrp[]"
                                                            class="form-control mrp-cost"
                                                            value="{{ $product?->product->unit_price ?? 0 }}" readonly>
                                                    </td>
                                                    <td><input type="text" name="purchase_quantity[]"
                                                            class="form-control purchase-quantity"
                                                            value="{{ $product->purchase_quantity ?? 0 }}"></td>
                                                    <td><input type="text" name="buying_unit_cost[]"
                                                            class="form-control buying-unit-cost"
                                                            value="{{ $product->buying_unit_cost ?? $product->previous_buying_cost ?? 0 }}"></td>
                                                    <td><input type="text" name="total_cost[]"
                                                            class="form-control total-cost"
                                                            value="{{ $product->total_cost }}" readonly></td>
                                                    <td><button class="btn btn-danger delete-product"
                                                            data-id="{{ $product->id }}"><i
                                                                class="fa fa-trash"></i></button></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <hr>

                                <!-- Totals Section -->
                                <div class="text-right">
                                    <table class="table table-borderless">
                                        <tbody>
                                            <tr>
                                                <th class="text-right">Total Items:</th>
                                                <td class="text-left">
                                                    <span id="total_quantity"
                                                        class="display_currency">{{ $purchase->total_item }}</span>
                                                    <input type="hidden" id="total_quantity_input" name="total_item"
                                                        value="{{ $purchase->total_item }}">
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="text-right">Net Total Amount:</th>
                                                <td class="text-left">
                                                    <span id="total_subtotal"
                                                        class="display_currency">{{ $purchase->net_total_amount }}</span>
                                                    <input type="hidden" id="total_subtotal_input"
                                                        name="net_total_amount"
                                                        value="{{ $purchase->net_total_amount }}">
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

        <!-- Payment Section -->
        <div class="card mb-4 shadow-sm rounded">
            <div class="card-body">
                <h3 class="card-title">Edit Payment</h3>
                <div class="row mb-3">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <strong class="ml-2">Advance Balance:</strong> <span id="advance_balance_text">0</span>
                            <input id="advance_balance" data-error-msg="Required advance balance not available"
                                name="advance_balance" type="hidden">
                        </div>
                    </div>
                </div>
                @php
                    $paymentMethod = $purchase->payment_method = json_decode($purchase->payment_method, true);
                    $paymentPaid = $purchase->advanced_paid_on = json_decode($purchase->advanced_paid_on, true);
                @endphp
                <div class="border border-gray-200 rounded p-2 p-sm-3">
                    <div id="payment-rows-container">
                        @if (!empty($paymentMethod) && is_array($paymentMethod))
                            @foreach ($paymentMethod as $index => $method)
                                @php
                                    $amount = 0;
                                    if ($method == 'cash') {
                                        $amount = $purchase->total_cash_amount;
                                    } elseif ($method == 'advance') {
                                        $amount = $purchase->total_advance_amount;
                                    } elseif ($method == 'cheque') {
                                        $amount = $purchase->total_cheque_amount;
                                    } elseif ($method == 'bank_transfer') {
                                        $amount = $purchase->total_bank_transfer_amount;
                                    }
                                @endphp

                                <div class="payment-row" id="payment-row-{{ $index }}">
                                    <input type="hidden" class="payment_row_index" value="{{ $index }}">
                                    <div class="row mb-3 align-items-center">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="amount_{{ $index }}">Amount:*</label>
                                                <input class="form-control payment-amount"
                                                    id="amount_{{ $index }}" required
                                                    name="payments[{{ $index }}][amount]" type="number"
                                                    value="{{ $amount }}">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="paid_on_{{ $index }}">Paid on:*</label>
                                                <input class="form-control paid_on" id="paid_on_{{ $index }}"
                                                    required name="payments[{{ $index }}][paid_on]" type="date"
                                                    value="{{ $paymentPaid[$index] ?? '' }}">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="method_{{ $index }}">Payment Method:*</label>
                                                <select class="form-control" id="method_{{ $index }}"
                                                    name="payments[{{ $index }}][method]">
                                                    <option value="cash" {{ $method == 'cash' ? 'selected' : '' }}>Cash
                                                    </option>
                                                    <option value="advance" {{ $method == 'advance' ? 'selected' : '' }}>
                                                        Advance</option>
                                                    <option value="cheque" {{ $method == 'cheque' ? 'selected' : '' }}>
                                                        Cheque</option>
                                                    <option value="bank_transfer"
                                                        {{ $method == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                        <div
                                            class="col-md-1 d-flex align-items-center justify-content-center action-btn pt-2">
                                            <button type="button" class="btn btn-danger delete-payment-btn">
                                                <i class="las la-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <p>No payment records found.</p>
                        @endif
                    </div>
                    <button type="button" class="btn btn-success" id="add-payment-btn">
                        <i class="las la-plus"></i> Add More Payment
                    </button>
                    <div class="form-group mt-3">
                        <label for="note">Notes:</label>
                        <textarea class="form-control" id="note" name="payment_note" rows="3">{{ $purchase->payment_note }}</textarea>
                    </div>
                    <div class="row">
                        <div class="col-sm-12 text-right">
                            <strong>Payment due:</strong> <span
                                id="payment_due">{{ $purchase->total_payment_due ?? '0.00' }}</span>
                            <input type="hidden" id="payment_due_input" name="total_payment_due"
                                value="{{ $purchase->total_payment_due }}">
                        </div>
                    </div>
                    <br>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" id="submit_button">Update Draft</button>

                        {{-- <a href="{{ route('purchase.order.pdf', ['id' => $purchase->id]) }}" class="btn btn-primary">
                            Download PDF
                        </a> --}}
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- supplier add modal --}}
    <div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog" aria-labelledby="addSupplierModalLabel"
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
                                <div class="form-group">
                                    <label for="contact_id">Contact ID:</label>
                                    <input type="text" name="contact_id" class="form-control mb-2" id="contact_id">
                                </div>
                                <div class="form-group">
                                    <label for="mobile">Mobile:</label>
                                    <input type="number" name="mobile" class="form-control mb-2" id="mobile">
                                </div>
                                <div class="form-group">
                                    <label for="opening_balance">Opening Balance:</label>
                                    <input type="number" name="opening_balance" class="form-control mb-2"
                                        id="opening_balance">
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
                                    <input type="text" name="tax_number" class="form-control mb-2" id="tax_number">
                                </div>
                                <div class="form-group">
                                    <label for="pay_term">Pay Term:</label>
                                    <select name="pay_term" class="form-control mb-2" id="pay_term">
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly">Yearly</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="btn btn-primary">Save Draft</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 5 JavaScript for Popover -->

<script>
    $(document).ready(function() {

        function fetchSupplierBalance(supplierId) {
            if (supplierId) {
                $.ajax({
                    url: "{{ route('supplier_balance', ':supplierId') }}".replace(':supplierId',
                        supplierId),
                    type: 'GET',
                    success: function(response) {
                        $('#advance_balance_text').text(response.balance);
                        $('#advance_balance').val(response.balance);
                    },
                    error: function() {
                        alert('Error retrieving supplier balance');
                    }
                });
            }
        }

        // Trigger change event manually if a supplier is preselected
        var preselectedSupplier = $('#supplier_id').val();
        fetchSupplierBalance(preselectedSupplier);

        // Attach change event listener
        $('#supplier_id').change(function() {
            fetchSupplierBalance($(this).val());
        });

        $('#search_product').on('keyup', function() {
            var query = $(this).val();
            if (query.length > 0) {
                $.ajax({
                    url: '{{ route('product.ajaxsearch') }}',
                    method: 'GET',
                    data: {
                        search: query
                    },
                    success: function(response) {
                        var dropdown = $('#search_results');
                        dropdown.empty();
                        if (response.data.length > 0) {
                            $.each(response.data, function(index, product) {
                                dropdown.append(
                                    '<a href="#" class="dropdown-item" data-id="' +
                                    product.id + '" data-name="' + product
                                    .name + '">' + product.name + '</a>'
                                );
                            });
                            dropdown.show();
                        } else {
                            dropdown.append(
                                '<a href="#" class="dropdown-item disabled text-danger">No items found</a>'
                            );
                        }
                        dropdown.show();
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error: ' + status + error);
                    }
                });
            } else {
                $('#search_results').empty().hide();
            }
        });

        $('#search_product').on('keypress', function(event) {
            if (event.keyCode === 13) {
                event.preventDefault();
            }
        });

        // Handle dropdown item click
        $(document).on('click', '.dropdown-item', function(event) {
    event.preventDefault();
    var productId = $(this).data('id');

    $.ajax({
        url: '{{ route('purchase.order.ajaxsearch') }}',
        method: 'GET',
        data: { id: productId },
        success: function(response) {
            var product = response.data;
            var variants = response.variants || [];
            var previousTP = response.previous_tp || ''; // ✅ safely define it here

            if (variants.length > 0) {
                // Product has variants
                $.each(variants, function(index, variant) {
                    addProductRow(
                        product.id,
                        product.name,
                        variant.variant,
                        variant.qty,
                        previousTP,          // ✅ Pass previous TP correctly
                        product.unit_price
                    );
                });
            } else {
                // Product has no variants
                addProductRow(
                    product.id,
                    product.name,
                    '',
                    product.qty || 0,
                    previousTP,          // ✅ Pass previous TP
                    product.unit_price
                );
            }

            $('#search_product').val('');
            $('#search_results').empty().hide();
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
        }
    });
});


// ✅ Corrected function definition
function addProductRow(productId, productName, variant, remainingQty, previousTP, mrp) {
    var row = '<tr>';
    row += '<td><input type="text" name="product_id[]" class="form-control" value="' + productId + '" readonly></td>';
    row += '<td><input type="text" name="name[]" class="form-control" value="' + productName +
        (variant ? ' (' + variant + ')' : '') + '" readonly></td>';
    row += '<input type="hidden" name="variant[]" value="' + (variant ? variant : '') + '">';
    row += '<td><input type="number" name="remaining_quantity[]" class="form-control remaining-quantity" value="' +
        (remainingQty ? remainingQty : '0') + '" readonly></td>';
    row += '<td><input type="text" name="mrp[]" class="form-control mrp" value="' +
        (mrp ? mrp : 'N/A') + '" readonly></td>';
    row += '<td><input type="number" name="purchase_quantity[]" class="form-control purchase-quantity" value="0"></td>';

    row += '<td><input type="text" name="buying_unit_cost[]" class="form-control buying-unit-cost" value="' +
        (previousTP ? previousTP : 0) + '"></td>';

    row += '<td><input type="text" name="total_cost[]" class="form-control total-cost" value="" readonly></td>';
    row += '<td><button class="btn btn-danger delete-product"><i class="fa fa-trash"></i></button></td>';
    row += '</tr>';

    $('#purchase_entry_table tbody').append(row);
}

        // Function to calculate and update buying_unit_cost per row
        function calculateTotalCost(row) {
            var purchaseQuantity = parseFloat(row.find('.purchase-quantity').val()) || 0;
            var buyingUnitCost = parseFloat(row.find('.buying-unit-cost').val()) || 0;

            var totalCost = purchaseQuantity * buyingUnitCost;
            row.find('.total-cost').val(totalCost.toFixed(2));
        }

        // Event listeners for calculation per row
        $(document).on('input', '.purchase-quantity, .buying-unit-cost', function() {
            var row = $(this).closest('tr');
            calculateTotalCost(row);
            calculateTotals();
        });

        // Close the dropdown menu if the user clicks outside of it
        $(document).on('click', function(event) {
            var target = $(event.target);
            if (!target.closest('#search_results').length && !target.closest('#search_product')
                .length) {
                $('#search_results').empty().hide();
            }
        });

        // Function to calculate totals for all rows
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
        $(document).on('input', '.purchase-quantity, .total-cost, .tax', function() {
            var row = $(this).closest('tr');
            calculateTotals();
        });

        // Recalculate totals when a product is added or removed
        $(document).on('click', '.delete-product', function() {
            $(this).closest('tr').remove();
            calculateTotals();
        });

        // Payment amount blur handler
        $(document).on('blur', '#amountPayment', function() {
            let amountPaymentText = $('#amountPayment').val();
            let amountPaymentValue = parseFloat(amountPaymentText) || 0;
            let paymentDueValue = parseFloat($('#payment_due').text()) || 0;
            let newPaymentDue = paymentDueValue - amountPaymentValue;

            $('#payment_due').text(newPaymentDue.toFixed(2));
            $('#payment_due_input').val(newPaymentDue.toFixed(2));
        });

        // Retrieve CSRF token from meta tag
        var csrfToken = $('meta[name="csrf-token"]').attr('content');

        // Handle draft update
        $('#submit_button').on('click', function(e) {
            e.preventDefault();

            let formData = new FormData($('#purchase_update_form')[0]);

            $.ajax({
                url: "{{ route('purchase.order.update', $purchase->id) }}",
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message || 'Purchase updated successfully!',
                            'Success');
                        if (response.redirect_url) {
                            window.location.href = response.redirect_url;
                        }
                    } else {
                        toastr.error(response.message || 'Something went wrong!', 'Error');
                    }
                },
                error: function(xhr, status, error) {
                    toastr.error('An error occurred while updating the purchase.', 'Error');
                    console.error('Error:', xhr.responseText);
                }
            });
        });

        // Handle Add Payment button click
        $('#add-payment-btn').on('click', function() {
            let container = $('#payment-rows-container');
            let currentIndex = $('.payment-row').length;
            let newRow = $('.payment-row:first').clone();

            newRow.attr('id', `payment-row-${currentIndex}`);
            newRow.find('.payment-amount').attr('name', `payments[${currentIndex}][amount]`);
            newRow.find('.paid_on').attr('name', `payments[${currentIndex}][paid_on]`);
            newRow.find('select').attr('name', `payments[${currentIndex}][method]`);

            newRow.find('.payment-amount').val('0.00');
            newRow.find('.paid_on').val('');
            newRow.find('select').prop('selectedIndex', 0);

            newRow.find('#add-payment-btn').remove();
            newRow.find('.action-btn').html(
                '<button type="button" class="btn btn-danger delete-payment-btn"><i class="las la-trash"></i></button>'
            );

            container.append(newRow);
        });

        // Delete payment row
        $(document).on('click', '.delete-payment-btn', function() {
            $(this).closest('.payment-row').remove();
        });

        // Close search dropdown if clicked outside
        $(document).on('click', function(event) {
            var target = $(event.target);
            if (!target.closest('#search_results').length && !target.closest('#search_product')
                .length) {
                $('#search_results').empty().hide();
            }
        });
    });
</script>
