@extends('backend.layouts.app')

<style>
    #product_dropdown_container {
        display: none;
        position: absolute;
        width: calc(100% - 2px);
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #ddd;
        background-color: #fff;
        z-index: 1000;
    }
    .dropdown-content div {
        padding: 10px;
        cursor: pointer;
    }
    .dropdown-content div:hover {
        background-color: #f1f1f1;
    }
    .main-product {
        background-color: #f8f9fa;
        border-left: 3px solid #007bff;
        font-weight: bold;
        padding: 12px;
    }
    .variant-item {
        background-color: #ffffff;
        border-left: 3px solid #28a745;
        font-size: 0.9em;
        padding: 10px;
    }
    .product-item {
        cursor: pointer;
        border-bottom: 1px solid #eee;
        transition: background-color 0.2s;
    }
    .product-item:hover {
        background-color: #e9ecef;
    }
</style>

@section('content')
<form action="{{ route('purchase-return.store') }}" method="POST" enctype="multipart/form-data" id="purchase_return_store_form">
    @csrf
    <section class="first-part border rounded p-4 bg-white">
        <div class="row">
            <div class="col-sm-3">
                <div class="form-group">
                    <label for="supplier_id">Supplier:*</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-user"></i></span>
                        </div>
                        <select class="form-control select2" required id="supplier_id" name="supplier_id">
                            <option value="" selected>Please Select</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->business_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-group">
                    <label for="ref_no">Reference No:</label>
                    <input class="form-control" name="reference_no" type="text" id="ref_no">
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    <label for="transaction_date">Date:*</label>
                    <input class="form-control" required name="transaction_date" type="date" id="transaction_date" value="{{ date('Y-m-d') }}">
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-group">
                    <label for="document">Attach Document:</label>
                    <input type="file" class="form-control" name="document">
                    <p class="help-block">Max File size: 5MB<br>Allowed: .pdf, .csv, .zip, .doc, .docx, .jpeg, .jpg, .png</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-5">
        <div class="card p-3">
            <div class="card-header">
                <h3 class="card-title">Search Products</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-8 offset-sm-2">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-search"></i></span>
                                </div>
                                <input class="form-control" id="search_product_for_purchase_return"
                                    placeholder="Search Products" name="search_product" type="text"
                                    autocomplete="off" readonly>
                            </div>
                            <div id="product_dropdown_container">
                                <div id="product_dropdown" class="dropdown-content"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-12">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-condensed" id="purchase_return_product_table">
                            <thead>
                                <tr class="text-center">
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Subtotal</th>
                                    <th><i class="fa fa-trash"></i></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="d-flex justify-content-end p-5">
        <button type="submit" class="btn btn-primary">Submit Return</button>
    </div>
</form>
@endsection

@section('script')
<script>
$(document).ready(function() {
    // Enable search when supplier is selected
    $('#supplier_id').on('change', function() {
        $('#search_product_for_purchase_return').prop('readonly', false);
    });

    let searchTimeout;

    // Product search handler
    $('#search_product_for_purchase_return').on('input', function() {
        clearTimeout(searchTimeout);
        let searchKeyword = $(this).val().trim();
        let supplierId = $('#supplier_id').val();

        if (searchKeyword.length > 0 && supplierId) {
            $('#product_dropdown_container').slideDown();
            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: "{{ route('supplier-wise-search-products') }}",
                    method: 'GET',
                    data: { search: searchKeyword, supplier_id: supplierId },
                    success: function(response) {
                        renderDropdown(response.products);
                    },
                    error: function() {
                        $('#product_dropdown').html('<div class="product-item text-danger p-3">Error loading products</div>');
                    }
                });
            }, 300);
        } else {
            $('#product_dropdown_container').slideUp();
        }
    });

    // Render dropdown items (each variant separately) – safe numeric conversion
    function renderDropdown(products) {
        let dropdown = $('#product_dropdown');
        dropdown.empty();
        if (!products || products.length === 0) {
            dropdown.html('<div class="product-item text-muted p-3">No products found</div>');
            return;
        }

        $.each(products, function(index, product) {
            // Convert to numbers safely, default to 0 if invalid
            let qty = parseFloat(product.remaining_qty) || 0;
            let unit = parseFloat(product.unit_cost) || 0;
            let isVariant = product.is_variant || false;
            let itemClass = isVariant ? 'variant-item ml-3' : 'main-product';
            let badge = isVariant ? '<span class="badge badge-info ml-2">Variant</span>' : '<span class="badge badge-success ml-2">Product</span>';

            dropdown.append(`
                <div class="product-item ${itemClass}"
                     data-product_id="${product.id}"
                     data-variant_name="${escapeHtml(product.variant_name || '')}"
                     data-name="${escapeHtml(product.name)}"
                     data-purchase_id="${product.purchase_id}"
                     data-purchase_product_id="${product.purchase_product_id}"
                     data-remaining_qty="${qty}"
                     data-unit_cost="${unit}"
                     data-is_variant="${isVariant}">
                    <strong>${escapeHtml(product.name)}</strong>
                    ${badge}
                    <br><small class="text-info">Available: ${qty} | Price: ${unit.toFixed(2)}</small>
                </div>
            `);
        });
    }

    // Prevent duplicate product/variant using unique purchase_product_id
    function isProductAlreadyAdded(purchaseProductId) {
        return $(`input[name="purchase_product_id[]"][value="${purchaseProductId}"]`).length > 0;
    }

    // Add selected product/variant to table
    function addProductToTable(productId, variantName, purchaseId, purchaseProductId, name, availableQty, unitCost, isVariant) {
        let row = `
            <tr>
                <td>
                    <div><strong>${escapeHtml(name)}</strong></div>
                    <small class="text-muted">Product ID: ${productId}</small>
                    ${isVariant && variantName ? `<br><small class="text-info">Variant: ${escapeHtml(variantName)}</small>` : ''}
                    <input type="hidden" name="product_id[]" value="${productId}">
                    <input type="hidden" name="variant_name[]" value="${escapeHtml(variantName)}">
                    <input type="hidden" name="purchase_id[]" value="${purchaseId}">
                    <input type="hidden" name="purchase_product_id[]" value="${purchaseProductId}">
                    <input type="hidden" name="product_name[]" value="${escapeHtml(name)}">
                </td>
                <td>
                    <input type="number" name="product_quantity[]" class="form-control product-quantity"
                           value="" placeholder="Enter quantity" max="${availableQty}" min="1" required>
                    <small class="text-danger error-message"></small>
                    <small class="text-info">Max: ${availableQty}</small>
                </td>
                <td>
                    <input type="number" name="product_unit_cost[]" class="form-control product-unit-cost"
                           value="${unitCost}" step="0.01" required>
                </td>
                <td>
                    <input type="number" name="product_subtotal[]" class="form-control product-subtotal" value="0" readonly>
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm delete-product"><i class="fa fa-trash"></i></button>
                </td>
            </tr>
        `;
        $('#purchase_return_product_table tbody').append(row);
        // Focus on the quantity field of the new row
        $('#purchase_return_product_table tbody tr:last .product-quantity').focus();
    }

    // Simple escape function to prevent XSS
    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // Handle product selection from dropdown
    $(document).on('click', '.product-item', function() {
        let $item = $(this);
        let productId = $item.data('product_id');
        let variantName = $item.data('variant_name') || '';
        let purchaseId = $item.data('purchase_id');
        let purchaseProductId = $item.data('purchase_product_id');
        let name = $item.data('name');
        let remainingQty = parseFloat($item.data('remaining_qty')) || 0;
        let unitCost = parseFloat($item.data('unit_cost')) || 0;
        let isVariant = $item.data('is_variant') === true;

        // Prevent adding same product/variant twice
        if (isProductAlreadyAdded(purchaseProductId)) {
            toastr.warning('This product/variant is already added to the table.');
            return;
        }

        addProductToTable(productId, variantName, purchaseId, purchaseProductId, name, remainingQty, unitCost, isVariant);

        // Clear dropdown and search input
        $('#product_dropdown').empty();
        $('#product_dropdown_container').slideUp();
        $('#search_product_for_purchase_return').val('');
    });

    // Live calculation: quantity or unit cost changes → update subtotal
    $(document).on('input', '.product-quantity, .product-unit-cost', function() {
        let $row = $(this).closest('tr');
        let maxQty = parseFloat($row.find('.product-quantity').attr('max')) || 0;
        let qty = parseFloat($row.find('.product-quantity').val()) || 0;
        let unit = parseFloat($row.find('.product-unit-cost').val()) || 0;

        $row.find('.error-message').text('');

        if ($row.find('.product-quantity').val() !== '') {
            // Validate quantity
            if (qty > maxQty) {
                $row.find('.error-message').text(`Max quantity is ${maxQty}`);
                $row.find('.product-quantity').val(maxQty);
                qty = maxQty;
            } else if (qty < 1) {
                $row.find('.error-message').text('Quantity must be at least 1');
                $row.find('.product-quantity').val(1);
                qty = 1;
            }
            let subtotal = qty * unit;
            $row.find('.product-subtotal').val(subtotal.toFixed(2));
        } else {
            $row.find('.product-subtotal').val('0');
        }
    });

    // Remove product row
    $(document).on('click', '.delete-product', function() {
        $(this).closest('tr').remove();
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#product_dropdown_container, #search_product_for_purchase_return').length) {
            $('#product_dropdown_container').slideUp();
        }
    });

    // Form validation and submission
    $('#purchase_return_store_form').on('submit', function(e) {
        e.preventDefault();

        let hasErrors = false;
        $('.product-quantity').each(function() {
            let $input = $(this);
            let val = $input.val();
            if (!val || parseFloat(val) < 1) {
                $input.closest('tr').find('.error-message').text('Enter valid quantity');
                hasErrors = true;
            }
        });

        if (hasErrors) {
            toastr.error('Please fix quantity errors.');
            return;
        }

        if ($('#purchase_return_product_table tbody tr').length === 0) {
            toastr.error('Add at least one product to return.');
            return;
        }

        let formData = new FormData(this);
        let $btn = $(this).find('button[type="submit"]');
        let originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                toastr.success(res.success || 'Return processed successfully');
                setTimeout(() => {
                    window.location.href = "{{ route('purchase.return') }}";
                }, 1500);
            },
            error: function(xhr) {
                let msg = xhr.responseJSON?.message || 'Something went wrong';
                toastr.error(msg);
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>
@endsection