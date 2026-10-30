@extends('backend.layouts.app')
@section('content')
<form id="purchase_update_form" action="{{ route('purchase.update', $purchase->id) }}" method="POST" enctype="multipart/form-data">
    @method('PUT')
    @csrf
    <input type="hidden" name="pay_by" value="{{ $purchase->payment_method_type }}">
    <input type="hidden" name="payment_account_id" value="{{ $purchase->payment_account_id }}">
    
    <!-- Supplier Section (readonly) -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label>Supplier</label>
                    <input type="text" class="form-control" value="{{ $purchase->supplier->business_name }}" disabled>
                    <input type="hidden" name="supplier_id" value="{{ $purchase->supplier_id }}">
                </div>
                <div class="col-md-3">
                    <label>Reference No</label>
                    <input type="text" class="form-control" value="{{ $purchase->reference_no }}" readonly>
                </div>
                <div class="col-md-3">
                    <label>Purchase Date</label>
                    <input type="date" name="purchase_date" class="form-control" value="{{ $purchase->purchase_date }}">
                </div>
                <div class="col-md-3">
                    <label>Business Location</label>
                    <input type="text" name="business_location" class="form-control" value="{{ $purchase->business_location }}">
                </div>
                <div class="col-md-3">
                    <label>Pay Term</label>
                    <div class="input-group">
                        <input type="number" name="pay_term" class="form-control" value="{{ $purchase->pay_term }}">
                        <select name="pay_term_type" class="form-control">
                            <option value="days" {{ $purchase->pay_term_type=='days'?'selected':'' }}>Days</option>
                            <option value="months" {{ $purchase->pay_term_type=='months'?'selected':'' }}>Months</option>
                            <option value="years" {{ $purchase->pay_term_type=='years'?'selected':'' }}>Years</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <label>Document</label>
                    @if($purchase->document)
                    <p><a href="{{ asset('storage/app/public/'.$purchase->document) }}" target="_blank">View Current Document</a></p>
                    @endif
                    <input type="file" name="document" class="form-control">
                </div>
            </div>
        </div>
    </div>

    <!-- Product Search -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mx-auto">
                    <div class="input-group">
                        <input type="text" id="search_product" class="form-control" placeholder="Search product to add...">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fa fa-search"></i></span>
                        </div>
                    </div>
                    <div id="search_results" class="dropdown-menu w-100" style="display:none; max-height: 300px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Purchase Entry Table with Product Headers -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered text-center" id="purchase_entry_table">
                    <thead class="thead-light">
                        <tr>
                            <th>Product ID</th>
                            <th>Product Name</th>
                            <th>Variant</th>
                            <th>Purchase Qty</th>
                            <th>Unit Cost</th>
                            <th>Total Cost</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $currentProduct = '';
                        @endphp
                        
                        @foreach($purchaseProducts as $product)
                            {{-- Add product header if it's a new product --}}
                            @if($currentProduct != $product->product_id)
                                <tr class="product-header bg-light">
                                    <td colspan="7" style="background-color: #e9ecef; font-weight: bold; padding: 8px; text-align: left;">
                                        <i class="fa fa-tag"></i> 
                                        {{ $product->product_name }}
                                        <span class="badge badge-info ml-2 w-auto">Existing</span>
                                        <button type="button" class="btn btn-sm btn-outline-danger float-right remove-product-group">
                                            <i class="fa fa-times"></i> Remove All
                                        </button>
                                    </td>
                                </tr>
                                @php $currentProduct = $product->product_id; @endphp
                            @endif
                            
                            {{-- Product variant row --}}
                            <tr class="variant-row existing-row" data-product-id="{{ $product->product_id }}">
                                <td>
                                    <input type="text" name="product_id[]" class="form-control" value="{{ $product->product_id }}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="name[]" class="form-control" value="{{ $product->product_name }}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="variant[]" class="form-control" value="{{ $product->variant ?: 'N/A' }}" readonly>
                                </td>
                                <td>
                                    <input type="number" name="purchase_quantity[]" class="form-control purchase-quantity" value="{{ $product->purchase_quantity }}" min="1" step="0.01">
                                </td>
                                <td>
                                    <input type="number" name="buying_unit_cost[]" class="form-control buying-unit-cost" value="{{ $product->buying_unit_cost }}" step="0.01">
                                </td>
                                <td>
                                    <input type="number" name="total_cost[]" class="form-control total-cost" value="{{ $product->total_cost }}" step="0.01">
                                </td>
                                <td>
                                    <input type="hidden" name="purchase_product_id[]" value="{{ $product->id }}">
                                    <button type="button" class="btn btn-danger btn-sm delete-product" data-id="{{ $product->id }}">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Summary Section -->
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> 
                        <strong>Note:</strong> Product headers show existing products. New products added via search will also get their own header.
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="text-right">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Total Items:</strong></td>
                                <td><span id="total_item_count">{{ $purchase->total_item }}</span></td>
                                <td><input type="hidden" id="total_item_input" name="total_item" value="{{ $purchase->total_item }}"></td>
                            </tr>
                            <tr>
                                <td><strong>Net Total:</strong></td>
                                <td><span id="total_subtotal" class="h5">{{ number_format($purchase->net_total_amount, 2) }}</span></td>
                                <td><input type="hidden" id="total_subtotal_input" name="net_total_amount" value="{{ $purchase->net_total_amount }}"></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Section -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Payment Information</h5>
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label>Pay By</label>
                    <select name="pay_by_display" id="pay_by_display" disabled class="form-control">
                        <option value="">Select</option>
                        <option value="cash" {{ $purchase->payment_method_type=='cash'?'selected':'' }}>Cash</option>
                        <option value="bank" {{ $purchase->payment_method_type=='bank'?'selected':'' }}>Bank</option>
                        <option value="mbanking" {{ $purchase->payment_method_type=='mbanking'?'selected':'' }}>Mobile Banking</option>
                        <option value="cheque" {{ $purchase->payment_method_type=='cheque'?'selected':'' }}>Cheque</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label>Paid From</label>
                    <select name="payment_account_id_display" id="payment_account_id_display" disabled class="form-control">
                        <option value="">-- Select Account --</option>
                        @if($purchase->payment_account_id)
                            @php $acc = \App\Models\ChartOfAccount::find($purchase->payment_account_id); @endphp
                            @if($acc)
                                <option value="{{ $acc->id }}" data-balance="{{ $acc->current_balance }}" selected>
                                    {{ $acc->name }} (Balance: {{ number_format($acc->current_balance, 2) }})
                                </option>
                            @endif
                        @endif
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label>Paid Amount</label>
                    <input type="number" name="paid_amount" id="paid_amount" class="form-control" readonly value="{{ $purchase->total_paid }}" step="0.01">
                </div>
                
                <div class="col-md-2">
                    <div id="balance_warning" class="alert alert-warning p-2" style="display:none;">
                        <i class="fa fa-exclamation-triangle"></i> Insufficient balance
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Payment Notes</label>
                        <textarea name="payment_note" class="form-control" rows="2" placeholder="Add any payment notes...">{{ $purchase->payment_note }}</textarea>
                    </div>
                </div>
                <div class="col-md-6 text-right">
                    <div class="mt-4">
                        <h5>
                            <strong>Payment Due:</strong> 
                            <span id="payment_due_display" class="text-danger">
                                {{ number_format($purchase->total_payment_due, 2) }}
                            </span>
                        </h5>
                        <input type="hidden" name="total_payment_due" id="payment_due_input" value="{{ $purchase->total_payment_due }}">
                    </div>
                </div>
            </div>
            
            <div class="text-right mt-3">
                <a href="{{ route('purchase.index') }}" class="btn btn-secondary">
                    <i class="fa fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save"></i> Update Purchase
                </button>
            </div>
        </div>
    </div>
</form>
@endsection

@section('script')
<script>
$(document).ready(function() {
    // Product search with debounce
    let searchTimeout;
    $('#search_product').on('keyup', function() {
        clearTimeout(searchTimeout);
        let query = $(this).val().trim();
        if(query.length > 0) {
            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: "{{ route('product.ajaxsearch') }}",
                    data: { search: query },
                    success: function(res) {
                        let dropdown = $('#search_results');
                        dropdown.empty().show();
                        if(res.data.length) {
                            $.each(res.data, function(i, p) {
                                // Check if product already exists in table
                                let exists = $('#purchase_entry_table').find(`tr[data-product-id="${p.id}"]`).length > 0;
                                let badge = exists ? '<span class="badge badge-warning ml-2">Already Added</span>' : '';
                                dropdown.append(`
                                    <a href="#" class="dropdown-item ${exists ? 'disabled' : ''}" 
                                       data-id="${p.id}" 
                                       data-name="${p.name}">
                                        <i class="fa fa-cube"></i> ${p.name} ${badge}
                                    </a>
                                `);
                            });
                        } else {
                            dropdown.append('<a class="dropdown-item disabled">No products found</a>');
                        }
                    }
                });
            }, 300);
        } else {
            $('#search_results').empty().hide();
        }
    });

    // Hide search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#search_product, #search_results').length) {
            $('#search_results').hide();
        }
    });

    // When product selected from search
    $(document).on('click', '.dropdown-item:not(.disabled)', function(e) {
        e.preventDefault();
        let productId = $(this).data('id');
        let productName = $(this).data('name');
        
        // Show loading state
        $(this).html('<i class="fa fa-spinner fa-spin"></i> Loading...');
        
        $.ajax({
            url: "{{ route('product.ajaxsearch') }}",
            data: { id: productId },
            success: function(res) {
                let product = res.data;
                let variants = res.variants || [];
                
                // Add product header
                addProductHeaderRow(productName, false);
                
                if(variants.length > 0) {
                    // Add a row for each variant
                    $.each(variants, function(i, v) {
                        fetchLastPurchasePrice(product.id, v.variant, function(lastPrice, lastQty) {
                            addNewProductRow(
                                product.id, 
                                product.name, 
                                v.variant, 
                                lastPrice || v.price,
                                lastQty || 0
                            );
                        });
                    });
                } else {
                    // No variants: add single row
                    fetchLastPurchasePrice(product.id, '', function(lastPrice, lastQty) {
                        addNewProductRow(
                            product.id, 
                            product.name, 
                            '', 
                            lastPrice || product.buying_unit_cost,
                            lastQty || 0
                        );
                    });
                }
                
                $('#search_product').val('');
                $('#search_results').hide();
                toastr.success(`${productName} added successfully!`);
            },
            error: function() {
                toastr.error('Failed to load product details');
            }
        });
    });

    // Function to add product header row
    function addProductHeaderRow(productName, isExisting = false) {
        let badge = isExisting ? '<span class="badge badge-info ml-2">Existing</span>' : '<span class="badge badge-success ml-2">New</span>';
        let headerRow = `
            <tr class="product-header bg-light">
                <td colspan="7" style="background-color: #e9ecef; font-weight: bold; padding: 8px; text-align: left;">
                    <i class="fa fa-tag"></i> ${productName} ${badge}
                    <button type="button" class="btn btn-sm btn-outline-danger float-right remove-product-group">
                        <i class="fa fa-times"></i> Remove All
                    </button>
                </td>
            </tr>`;
        $('#purchase_entry_table tbody').append(headerRow);
    }

    // Fetch last purchase price
    function fetchLastPurchasePrice(productId, variant, callback) {
        $.ajax({
            url: "{{ route('product.last.purchase.price') }}",
            data: {
                product_id: productId,
                variant: variant
            },
            success: function(response) {
                if (response.success) {
                    callback(response.last_price, response.last_quantity);
                } else {
                    callback(null, null);
                }
            },
            error: function() {
                callback(null, null);
            }
        });
    }

    // Add new product row
    function addNewProductRow(productId, productName, variant, price, qty) {
        let totalCost = (qty * price).toFixed(2);
        let variantDisplay = variant ? variant : 'N/A';
        
        let row = `
            <tr class="variant-row new-row" data-product-id="${productId}">
                <td><input type="text" name="product_id[]" class="form-control" value="${productId}" readonly></td>
                <td><input type="text" name="name[]" class="form-control" value="${productName}" readonly></td>
                <td><input type="text" name="variant[]" class="form-control" value="${variantDisplay}" readonly></td>
                <td><input type="number" name="purchase_quantity[]" class="form-control purchase-quantity" value="${qty}" min="1" step="0.01"></td>
                <td><input type="number" name="buying_unit_cost[]" class="form-control buying-unit-cost" value="${price}" step="0.01"></td>
                <td><input type="number" name="total_cost[]" class="form-control total-cost" value="${totalCost}" step="0.01"></td>
                <td><button type="button" class="btn btn-danger btn-sm delete-product"><i class="fa fa-trash"></i></button></td>
            </tr>`;
        $('#purchase_entry_table tbody').append(row);
        calculateTotals();
    }

    // Remove entire product group
    $(document).on('click', '.remove-product-group', function() {
        if(confirm('Are you sure you want to remove this entire product group?')) {
            let headerRow = $(this).closest('tr');
            let nextRow = headerRow.next('tr');
            
            // Remove all variant rows until next header or end of table
            while (nextRow.length && !nextRow.hasClass('product-header')) {
                let temp = nextRow.next('tr');
                nextRow.remove();
                nextRow = temp;
            }
            
            // Remove the header row itself
            headerRow.remove();
            calculateTotals();
            toastr.info('Product group removed');
        }
    });

    // Delete individual variant row
    $(document).on('click', '.delete-product', function() {
        let row = $(this).closest('tr');
        let productId = $(this).data('id');
        
        if(productId) {
            // Existing product - might want to mark for deletion
            if(confirm('Are you sure you want to delete this variant? It will be removed from the purchase.')) {
                row.remove();
                
                // Check if there are any more variant rows for this product
                let headerRow = row.prevAll('.product-header').first();
                if(headerRow.length) {
                    let remainingVariants = headerRow.nextUntil('.product-header', '.variant-row');
                    if(remainingVariants.length === 0) {
                        // If no variants left, remove the header too
                        headerRow.remove();
                    }
                }
                
                calculateTotals();
                toastr.warning('Variant removed');
            }
        } else {
            // New product - can be deleted directly
            row.remove();
            
            // Check if there are any more variant rows for this product
            let headerRow = row.prevAll('.product-header').first();
            if(headerRow.length) {
                let remainingVariants = headerRow.nextUntil('.product-header', '.variant-row');
                if(remainingVariants.length === 0) {
                    headerRow.remove();
                }
            }
            
            calculateTotals();
        }
    });

    // Two‑way calculation for quantity, unit cost, total cost
    $(document).on('input', '.purchase-quantity, .buying-unit-cost, .total-cost', function() {
        let row = $(this).closest('tr');
        let qty = parseFloat(row.find('.purchase-quantity').val()) || 0;
        let unit = parseFloat(row.find('.buying-unit-cost').val()) || 0;
        let total = parseFloat(row.find('.total-cost').val()) || 0;

        if ($(this).hasClass('buying-unit-cost')) {
            // User changed unit cost → update total cost
            total = qty * unit;
            row.find('.total-cost').val(total.toFixed(2));
        } else if ($(this).hasClass('total-cost')) {
            // User changed total cost → update unit cost
            if (qty > 0) {
                unit = total / qty;
                row.find('.buying-unit-cost').val(unit.toFixed(2));
            }
        } else if ($(this).hasClass('purchase-quantity')) {
            // User changed quantity → update total cost based on existing unit cost
            if (unit > 0) {
                total = qty * unit;
                row.find('.total-cost').val(total.toFixed(2));
            } else if (total > 0 && qty > 0) {
                unit = total / qty;
                row.find('.buying-unit-cost').val(unit.toFixed(2));
            }
        }
        calculateTotals();
    });

    // Calculate totals
    function calculateTotals() {
        let totalQty = 0, netTotal = 0, itemCount = 0;
        
        $('#purchase_entry_table tbody tr.variant-row').each(function() {
            let qty = parseFloat($(this).find('.purchase-quantity').val()) || 0;
            let cost = parseFloat($(this).find('.total-cost').val()) || 0;
            totalQty += qty;
            netTotal += cost;
            itemCount++;
        });
        
        $('#total_item_count').text(itemCount);
        $('#total_item_input').val(itemCount);
        $('#total_subtotal').text(netTotal.toFixed(2));
        $('#total_subtotal_input').val(netTotal.toFixed(2));
        updateDueAndBalance();
    }

    // Update payment due
    function updateDueAndBalance() {
        let netTotal = parseFloat($('#total_subtotal_input').val()) || 0;
        let paid = parseFloat($('#paid_amount').val()) || 0;
        let due = netTotal - paid;
        
        $('#payment_due_display').text(due.toFixed(2));
        $('#payment_due_input').val(due.toFixed(2));
    }

    // Update due when paid amount changes
    $('#paid_amount').on('input', function() {
        updateDueAndBalance();
    });

    // Initialize calculations
    calculateTotals();

    // Form submission
    $('#purchase_update_form').on('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        let submitBtn = $(this).find('button[type="submit"]');
        let originalText = submitBtn.html();
        submitBtn.html('<i class="fa fa-spinner fa-spin"></i> Updating...').prop('disabled', true);
        
        let formData = new FormData(this);
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: { 
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-HTTP-Method-Override': 'PUT'
            },
            success: function(res) {
                if(res.success) {
                    toastr.success(res.message);
                    if(res.redirect_url) {
                        setTimeout(function() {
                            window.location.href = res.redirect_url;
                        }, 1000);
                    }
                } else {
                    toastr.error(res.message);
                    submitBtn.html(originalText).prop('disabled', false);
                }
            },
            error: function(xhr) {
                let message = 'An error occurred while updating the purchase';
                if(xhr.responseJSON?.message) {
                    message = xhr.responseJSON.message;
                }
                toastr.error(message);
                submitBtn.html(originalText).prop('disabled', false);
            }
        });
    });

    // Initialize tooltips and popovers
    $('[data-toggle="tooltip"]').tooltip();
});
</script>

<style>
.product-header {
    border-left: 4px solid #007bff !important;
}

.product-header .badge {
    font-size: 12px;
    padding: 4px 8px;
}

.variant-row:hover {
    background-color: var(--primary);
    color:white;
}

.variant-row input:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

#search_results {
    max-height: 300px;
    overflow-y: auto;
    box-shadow: 0 4px 6px rgba(0,0,0,.1);
    border: 1px solid #dee2e6;
}

#search_results .dropdown-item {
    padding: 8px 15px;
    border-bottom: 1px solid #f1f1f1;
}

#search_results .dropdown-item:hover {
     background-color: var(--primary);
    color:white;
}

#search_results .dropdown-item.disabled {
    color: #6c757d;
    background-color: #f8f9fa;
}

.btn-outline-danger:hover {
    color: #fff;
    background-color: #dc3545;
    border-color: #dc3545;
}
</style>
@endsection