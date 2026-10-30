@extends('backend.layouts.app')

<style>
    #product_dropdown_container {
        display: none;
        position: absolute;
        width: calc(100% - 2px);
        /* Adjust width if necessary */
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #ddd;
        background-color: #fff;
        z-index: 1000;
        /* Ensure it is above other content */
    }

    .dropdown-content div {
        padding: 10px;
        cursor: pointer;
    }

    .dropdown-content div:hover {
        background-color: #f1f1f1;
    }
</style>


@section('content')
    <form action="{{ route('purchase-return.update', $purchaseReturn->id) }}" method="POST" enctype="multipart/form-data" id="purchase_return_edit_form">
        @method('PUT')
        @csrf
        <section class="first-part border rounded p-4 bg-white">
            <div class="row" style="background-color: #fff !important;">
                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="supplier_id">Supplier:*</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-user"></i></span>
                            </div>
                            <select class="form-control select2" required id="supplier_id" name="supplier_id" aria-required="true">
                                <option value="" disabled>Please Select</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ $purchaseReturn->supplier_id == $supplier->id ? 'selected' : '' }}>
                                        {{ $supplier->business_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="col-sm-4">
                    <div class="form-group">
                        <label for="ref_no">Reference No:</label>
                        <input class="form-control" name="reference_no" type="text" id="ref_no" value="{{ $purchaseReturn->reference_no }}">
                    </div>
                </div>

                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="transaction_date">Date:*</label>
                        <div class="input-group">
                            <input class="form-control" required name="transaction_date" type="date" value="{{ $purchaseReturn->transaction_date }}" id="transaction_date" aria-required="true">
                        </div>
                    </div>
                </div>

                <div class="col-sm-4">
                    <div class="form-group">
                        <label for="document">Attach Document:</label>
                        <div class="file-input">
                            <div class="input-group">
                                <input type="file" class="form-control" name="document">
                            </div>
                        </div>
                        <p class="help-block">
                            Max File size: 5MB<br>
                            Allowed File: .pdf, .csv, .zip, .doc, .docx, .jpeg, .jpg, .png
                        </p>
                        @if ($purchaseReturn->document)
                                    <p>Current Document:
                                        <a href="{{ asset('storage/app/public/' . $purchaseReturn->document) }}"
                                            target="_blank">View Document</a>
                                    </p>
                        @endif
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
                                        <span class="input-group-text">
                                            <i class="fa fa-search"></i>
                                        </span>
                                    </div>
                                    <input class="form-control" id="search_product_for_purchase_return"
                                        placeholder="Search Products" name="search_product" type="text"
                                        autocomplete="off" readonly>
                                </div>
                                <div class="form-group" id="product_dropdown_container">
                                    <div id="product_dropdown" class="dropdown-content">
                                        <!-- Options will be added dynamically -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <input type="hidden" id="product_row_index" value="0">
                        <input type="hidden" id="total_amount" name="final_total" value="0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-condensed" id="purchase_return_product_table">
                                <thead>
                                    <tr class="text-center">
                                        <th>Product</th>
                                        <th>Variant</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Subtotal</th>
                                        <th><i class="fa fa-trash" aria-hidden="true"></i></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchaseReturn->productReturn as $product)
                                    {{-- @dd($product); --}}
                                        <tr>
                                            <td>
                                                <input type="hidden" name="product_id[]" class="form-control" value="{{ $product->product_id }}">
                                                <input type="text" name="product_name[]" class="form-control" value="{{ $product->product_name }}">
                                            </td>
                                            <td>
                                                <input type="hidden" name="variant[]" class="form-control" value="{{ $product->variant }}">
                                                <input type="text" name="variant[]" class="form-control" value="{{ $product->variant }}">
                                            </td>
                                            <td>
                                                <input type="hidden" name="purchase_id[]" class="form-control" value="{{ $product->purchase_return_id }}">
                                                <input type="number" name="product_quantity[]" class="form-control product-quantity" value="{{ $product->purchase_quantity }}" max="{{ $product->purchase_quantity }}">
                                                <small class="text-danger error-message"></small> <!-- Error message will show here -->
                                            </td>
                                            <td>
                                                <input type="number" name="product_unit_cost[]" class="form-control product-unit-cost" value="{{ $product->buying_unit_cost }}">
                                            </td>
                                            <td>
                                                <input type="number" name="product_subtotal[]" class="form-control product-subtotal" value="{{ $product->total_cost }}" readonly>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger delete-product" data-id="{{ $product->id }}">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        {{--<div class="d-flex justify-content-end p-5">
            <button type="submit" class="btn btn-primary">Update</button>
        </div>--}}
    </form>
@endsection

@section('script')
    <script>
        $(document).ready(function() {
            // Enable search field when supplier is selected
            $('#supplier_id').on('change', function() {
                $('#search_product_for_purchase_return').prop('readonly', false);
            });

            // Handle search input
            $('#search_product_for_purchase_return').on('input', function() {
                var searchKeyword = $(this).val();
                var supplierId = $('#supplier_id').val();
                var supplierWiseSearchProductsUrl = "{{ route('supplier-wise-search-products') }}";

                // Show the dropdown container when typing starts
                if (searchKeyword.length > 0) {
                    $('#product_dropdown_container').slideDown();
                } else {
                    $('#product_dropdown_container').slideUp();
                }

                if (searchKeyword.length > 0 && supplierId) {
                    $.ajax({
                        url: supplierWiseSearchProductsUrl,
                        method: 'GET',
                        data: {
                            search: searchKeyword,
                            supplier_id: supplierId
                        },
                        success: function(response) {
                            $('#product_dropdown').empty();

                            $.each(response.products, function(index, product) {
                                $('#product_dropdown').append(
                                    `<div data-purchase_id="${product.purchase_id}" data-name="${product.name}" data-quantity="${product.purchase_quantity}" data-unit_cost="${product.buying_unit_cost}" data-subtotal="${product.subtotal}" data-id="${product.id}">
                                    ${product.name}
                                </div>`
                                );
                            });
                        },
                        error: function() {
                            alert('An error occurred while fetching products.');
                        }
                    });
                }
            });

            $('#product_dropdown').on('click', 'div', function() {
                var name = $(this).data('name');
                var quantity = $(this).data('quantity');
                var unitCost = $(this).data('unit_cost');
                var subtotal = quantity * unitCost;
                var id = $(this).data('id');
                var purchaseId = $(this).data('purchase_id');

                $('#purchase_return_product_table tbody').append(
                    `<tr>
                        <td>
                            <input type="hidden" name="product_id[]" class="form-control" value="${id}">
                            <input type="text" name="product_name[]" class="form-control" value="${name}">
                        </td>
                        <td>
                            <input type="hidden" name="purchase_id[]" class="form-control" value="${purchaseId}">
                            <input type="number" name="product_quantity[]" class="form-control product-quantity" value="${quantity}" max="${quantity}">
                            <small class="text-danger error-message"></small>
                        </td>
                        <td>
                            <input type="number" name="product_unit_cost[]" class="form-control product-unit-cost" value="${unitCost}">
                        </td>
                        <td>
                            <input type="number" name="product_subtotal[]" class="form-control product-subtotal" value="${subtotal}" readonly>
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger delete-product" data-id="${id}">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>`
                );

                // Hide dropdown after selection
                $('#product_dropdown_container').slideUp();
                $('#search_product_for_purchase_return').val('');
            });

            // Remove product row
            $('#purchase_return_product_table').on('click', '.delete-product', function() {
                $(this).closest('tr').remove();
                calculateTotal();
            });

            // Calculate total
            function calculateTotal() {
                var total = 0;
                $('#purchase_return_product_table .product-subtotal').each(function() {
                    total += parseFloat($(this).val()) || 0;
                });
                $('#total_amount').val(total);
            }

            // Update subtotal when quantity or unit cost changes
            $('#purchase_return_product_table').on('input', '.product-quantity, .product-unit-cost', function() {
                var $row = $(this).closest('tr');
                var quantity = parseFloat($row.find('.product-quantity').val()) || 0;
                var unitCost = parseFloat($row.find('.product-unit-cost').val()) || 0;
                var subtotal = quantity * unitCost;

                $row.find('.product-subtotal').val(subtotal);
                calculateTotal();
            });

            $('#purchase_return_edit_form').on('submit', function(e) {
                e.preventDefault();

                // Validation or AJAX submit logic here
                // For example:
                $.ajax({
                    url: $(this).attr('action'),
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        toastr.success('Purchase Return updated successfully.');
                    },
                    error: function(response) {
                        toastr.error('An error occurred while updating the Purchase Return.');
                    }
                });
            });
        });
    </script>
@endsection
