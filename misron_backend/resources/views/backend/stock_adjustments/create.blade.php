{{-- resources/views/backend/stock_adjustments/create.blade.php --}}

@extends('backend.layouts.app')

@section('title', translate('Add Stock Adjustment'))

@section('content')

    <div class="stock-adjustment-create">

        <div class="aiz-titlebar text-left mt-2 mb-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="h3">{{ translate('Add Stock Adjustment') }}</h1>
                </div>
                <div class="col-md-6 text-md-right">
                    <a href="{{ route('stock-adjustments.index') }}" class="btn btn-secondary">
                        <i class="la la-arrow-left"></i> {{ translate('Back to List') }}
                    </a>
                </div>
            </div>
        </div>

        <form id="stock_adjustment_form" method="POST" action="{{ route('stock-adjustments.store') }}">
            @csrf

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0 h6">{{ translate('Basic Information') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="ref_no">{{ translate('Reference No') }}</label>
                                <input type="text" class="form-control" id="ref_no" name="ref_no"
                                    value="{{ $reference_no }}" readonly>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="transaction_date">{{ translate('Date') }} <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control aiz-date-range" id="transaction_date"
                                    name="transaction_date" value="{{ date('m/d/Y H:i') }}" data-single="true"
                                    data-time-picker="true" data-format="MM/DD/YYYY HH:mm" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="adjustment_type">{{ translate('Adjustment Type') }} <span
                                        class="text-danger">*</span></label>
                                <select class="form-control aiz-selectpicker" id="adjustment_type" name="adjustment_type"
                                    required>
                                    <option value="">{{ translate('Select Type') }}</option>
                                    <option value="normal">{{ translate('Normal') }}</option>
                                    <option value="abnormal">{{ translate('Abnormal') }}</option>
                                </select>
                                <small class="text-muted">
                                    <i class="las la-info-circle"></i>
                                    {{ translate('Normal: Leakage, Damage etc. | Abnormal: Fire, Accident etc.') }}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0 h6">{{ translate('Add Products') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-8 offset-md-2">
                            <div class="form-group">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">
                                            <i class="las la-search"></i>
                                        </span>
                                    </div>
                                    <input type="text" class="form-control" id="search_product"
                                        placeholder="{{ translate('Search products by name...') }}" autocomplete="off">
                                </div>
                                <div id="product_suggestions" class="position-absolute w-100"
                                    style="z-index: 1000; max-height: 300px; overflow-y: auto; display: none;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered" id="products_table">
                            <thead>
                                <tr>
                                    <th>{{ translate('Product') }}</th>
                                    <th>{{ translate('Variant') }}</th>
                                    <th width="120">{{ translate('Quantity') }}</th>
                                    <th width="120">{{ translate('Unit Price') }}</th>
                                    <th width="120">{{ translate('Subtotal') }}</th>
                                    <th width="50">{{ translate('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Product rows will be added here -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-right">
                                        <strong>{{ translate('Total Amount:') }}</strong>
                                    </td>
                                    <td>
                                        <span id="total_amount_display">0.00</span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0 h6">{{ translate('Additional Information') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 d-none">
                            <div class="form-group">
                                <label for="total_amount_recovered">
                                    {{ translate('Total Amount Recovered') }}
                                    <i class="las la-info-circle" data-toggle="tooltip"
                                        title="{{ translate('Amount recovered from insurance or selling of scraps or others') }}"></i>
                                </label>
                                <input type="number" class="form-control" id="total_amount_recovered"
                                    name="total_amount_recovered" value="0" min="0" step="0.01">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="additional_notes">{{ translate('Reason/Notes') }}</label>
                                <textarea class="form-control" id="additional_notes" name="additional_notes" rows="3"
                                    placeholder="{{ translate('Enter reason for adjustment...') }}"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mb-5">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="las la-save"></i> {{ translate('Save Stock Adjustment') }}
                </button>
            </div>
        </form>
    </div>

@endsection

@section('script')
    <script type="text/javascript">
        var product_row_index = 0;

        // Search Products
        $('#search_product').on('keyup', function() {
            var search_term = $(this).val();

            if (search_term.length < 2) {
                $('#product_suggestions').hide();
                return;
            }

            $.ajax({
                url: '{{ route('stock-adjustments.search-product') }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    search_term: search_term
                },
                success: function(response) {
                    if (response.length > 0) {
                        var html = '<div class="list-group">';
                        $.each(response, function(index, product) {
                            html +=
                                '<a href="javascript:void(0)" class="list-group-item list-group-item-action" ';
                            html += 'onclick="addProduct(' + product.product_id + ', \'' +
                                escapeHtml(product.product_name) + '\', \'' +
                                (product.sku || '') + '\', ' +
                                (product.product_stock_id || 'null') + ', \'' +
                                (product.variant || '') + '\', ' +
                                product.unit_price + ', ' +
                                product.current_stock + ', ' +
                                product.variant_stock + ')">';

                            // Show product name
                            html += '<strong>' + product.product_name + '</strong>';

                            // Show variant in parentheses like: (38), (40), (42)
                            if (product.variant) {
                                html += ' <span class="text-primary">(' + product.variant +
                                    ')</span>';
                            }

                            if (product.sku) {
                                html += '<br><small class="text-muted">SKU: ' + product.sku +
                                    '</small>';
                            }

                            // Show stock information
                            html += '<br><small class="text-info">';
                            html += '{{ translate('Available Stock:') }} ' + product
                                .variant_stock;
                            html += '</small>';

                            // Show buying cost
                            html += '<br><small class="text-success">';
                            html += '{{ translate('Cost:') }} ' + product.unit_price;
                            html += '</small>';

                            html += '</a>';
                        });
                        html += '</div>';
                        $('#product_suggestions').html(html).show();
                    } else {
                        $('#product_suggestions').html(
                            '<div class="list-group"><div class="list-group-item text-center text-muted">{{ translate('No products found') }}</div></div>'
                        ).show();
                    }
                }
            });
        });

        // Hide suggestions when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#search_product, #product_suggestions').length) {
                $('#product_suggestions').hide();
            }
        });

        // Add Product to Table
        function addProduct(product_id, product_name, sku, product_stock_id, variant, unit_price, current_stock,
            variant_stock) {
            var row_id = 'product_' + product_row_index;

            // Check if product already added (check by product_id and variant)
            var alreadyExists = false;
            $('#products_table tbody tr').each(function() {
                var existingProductId = $(this).find('input[name*="[product_id]"]').val();
                var existingVariant = $(this).find('.product-variant-text').text();
                if (existingProductId == product_id && existingVariant == variant) {
                    alreadyExists = true;
                    return false;
                }
            });

            if (alreadyExists) {
                aiz.plugins.notify('warning', '{{ translate('This product variant is already added.') }}');
                return;
            }

            var row_html = '<tr id="' + row_id + '">';

            // Product column
            row_html += '<td>';
            row_html += '<strong>' + escapeHtml(product_name) + '</strong>';
            if (sku) {
                row_html += '<br><small class="text-muted">' + sku + '</small>';
            }
            row_html += '<input type="hidden" name="products[' + product_row_index + '][product_id]" value="' + product_id +
                '">';
            row_html += '<input type="hidden" name="products[' + product_row_index + '][variant]" value="' + (variant ||
                '') + '">';
            if (product_stock_id) {
                row_html += '<input type="hidden" name="products[' + product_row_index + '][product_stock_id]" value="' +
                    product_stock_id + '">';
            }
            row_html += '</td>';

            // Variant column - NEW
            row_html += '<td>';
            if (variant) {
                row_html += '<span class="product-variant-text badge badge-inline badge-info">' + variant + '</span>';
            } else {
                row_html += '<span class="text-muted">--</span>';
            }
            row_html += '</td>';

            // Quantity column
            row_html += '<td>';
            row_html += '<input type="number" class="form-control product_quantity" ';
            row_html += 'name="products[' + product_row_index + '][quantity]" ';
            row_html += 'value="1" min="0.01" step="0.01" ';
            row_html += 'data-max="' + variant_stock + '" ';
            row_html += 'onchange="calculateSubtotal(\'' + row_id + '\')" ';
            row_html += 'onkeyup="calculateSubtotal(\'' + row_id + '\')">';
            row_html += '<small class="text-muted">{{ translate('Available:') }} ' + variant_stock + '</small>';
            row_html += '</td>';

            // Unit Price column
            row_html += '<td>';
            row_html += '<input type="number" class="form-control unit_price" ';
            row_html += 'name="products[' + product_row_index + '][unit_price]" ';
            row_html += 'value="' + unit_price + '" min="0" step="0.01" ';
            row_html += 'onchange="calculateSubtotal(\'' + row_id + '\')" ';
            row_html += 'onkeyup="calculateSubtotal(\'' + row_id + '\')">';
            row_html += '</td>';

            // Subtotal column
            row_html += '<td>';
            row_html += '<input type="text" class="form-control subtotal" readonly ';
            row_html += 'value="' + (unit_price * 1).toFixed(2) + '">';
            row_html += '</td>';

            // Action column
            row_html += '<td class="text-center">';
            row_html += '<button type="button" class="btn btn-aiz-danger btn-icon btn-sm" ';
            row_html += 'onclick="removeProduct(\'' + row_id + '\')">';
            row_html += '<i class="las la-trash"></i>';
            row_html += '</button>';
            row_html += '</td>';

            row_html += '</tr>';

            $('#products_table tbody').append(row_html);
            $('#product_suggestions').hide();
            $('#search_product').val('');

            product_row_index++;
            calculateTotal();
        }

        // Calculate Subtotal
        function calculateSubtotal(row_id) {
            var row = $('#' + row_id);
            var quantity = parseFloat(row.find('.product_quantity').val()) || 0;
            var unit_price = parseFloat(row.find('.unit_price').val()) || 0;
            var max_quantity = parseFloat(row.find('.product_quantity').data('max'));

            // Validate quantity
            if (quantity > max_quantity) {
                aiz.plugins.notify('warning', '{{ translate('Quantity cannot exceed available stock') }} (' +
                    max_quantity + ')');
                row.find('.product_quantity').val(max_quantity);
                quantity = max_quantity;
            }

            var subtotal = (quantity * unit_price).toFixed(2);
            row.find('.subtotal').val(subtotal);

            calculateTotal();
        }

        // Calculate Total
        function calculateTotal() {
            var total = 0;
            $('.subtotal').each(function() {
                total += parseFloat($(this).val()) || 0;
            });
            $('#total_amount_display').text(total.toFixed(2));
        }

        // Remove Product
        function removeProduct(row_id) {
            $('#' + row_id).remove();
            calculateTotal();
        }

        // Escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Form Submission
        $('#stock_adjustment_form').on('submit', function(e) {
            e.preventDefault();

            if ($('#products_table tbody tr').length === 0) {
                aiz.plugins.notify('warning', '{{ translate('Please add at least one product.') }}');
                return false;
            }

            var formData = $(this).serialize();

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        if (res.redirect_url) window.location.href = res.redirect_url;
                    } else toastr.error(res.message);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Error');
                }
            });
        });
    </script>
@endsection
