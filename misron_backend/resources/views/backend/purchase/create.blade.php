@extends('backend.layouts.app')
@section('content')
    <form action="{{ route('purchase.store') }}" method="POST" enctype="multipart/form-data" id="purchase_store_form">
        <input type="hidden" name="op_key" value="{{ \Illuminate\Support\Str::uuid() }}">
        @csrf

        <!-- Supplier Section -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Supplier:*</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i
                                            class="fa fa-user"></i></span></div>
                                <select class="form-control" required name="supplier_id" id="supplier_id">
                                    <option value="">Select Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->business_name }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-light" data-toggle="modal"
                                        data-target="#addSupplierModal">
                                        <i class="fa fa-plus-circle text-primary"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Purchase Date:*</label>
                            <input type="date" class="form-control" name="purchase_date" value="{{ date('Y-m-d') }}"
                                required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Business Location:*</label>
                            <input type="text" class="form-control" name="business_location">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Pay Term:</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="pay_term" placeholder="Term">
                                <select class="form-control" name="pay_term_type">
                                    <option value="days">Days</option>
                                    <option value="months">Months</option>
                                    <option value="years">Years</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Document:</label>
                            <input type="file" class="form-control" name="document" accept=".pdf,.jpg,.png">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Search -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mx-auto">
                        <input type="text" id="search_product" class="form-control" placeholder="Search product...">
                        <div id="search_results" class="dropdown-menu w-100" style="display:none; margin-top:5%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase Entry Table with Variant Column -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered text-center" id="purchase_entry_table">
                        <thead>
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
                        <tbody></tbody>
                    </table>
                </div>
                <div class="text-right">
                    <strong>Total Items:</strong> <span id="total_quantity">0</span><br>
                    <strong>Net Total:</strong> <span id="total_subtotal">0</span>
                    <input type="hidden" id="total_quantity_input" name="total_item">
                    <input type="hidden" id="total_subtotal_input" name="net_total_amount">
                </div>
            </div>
        </div>

        <!-- Payment Section (Single Line) - unchanged -->
        <div class="card mb-4">
            <div class="card-body">
                <h5>Payment</h5>
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label>Pay By</label>
                        <select name="pay_by" id="pay_by" class="form-control">
                            <option value="">Select</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="mbanking">Mobile Banking</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Paid From</label>
                        <select name="payment_account_id" id="payment_account_id" class="form-control">
                            <option value="">-- First select Pay By --</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Paid Amount</label>
                        <input type="number" name="paid_amount" id="paid_amount" class="form-control" value="0"
                            step="0.01">
                    </div>
                    <div class="col-md-2">
                        <small id="balance_warning" class="text-danger" style="display:none;">Insufficient
                            balance</small>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-sm-12 text-right">
                        <strong>Payment Due:</strong> <span id="payment_due_display">0.00</span>
                        <input type="hidden" name="total_payment_due" id="payment_due_input">
                    </div>
                </div>
                <div class="form-group mt-3">
                    <label>Notes</label>
                    <textarea name="payment_note" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Submit Purchase</button>
            </div>
        </div>
    </form>

    @include('backend.supplier.supplier_modal.modal')
@endsection

{{-- @section('script')
    <script>
        $(document).ready(function() {
            // Supplier balance (optional)
            $('#supplier_id').change(function() {
                let id = $(this).val();
                if (id) {
                    $.get("{{ url('supplier-balance') }}/" + id, function(res) {
                        $('#advance_balance_text').text(res.balance);
                    });
                }
            });

            // Product search with debounce
            let searchTimeout;
            $('#search_product').on('keyup', function() {
                clearTimeout(searchTimeout);
                let query = $(this).val().trim();
                if (query.length > 0) {
                    searchTimeout = setTimeout(function() {
                        $.ajax({
                            url: "{{ route('product.ajaxsearch') }}",
                            data: {
                                search: query
                            },
                            success: function(res) {
                                let dropdown = $('#search_results');
                                dropdown.empty().show();
                                if (res.data.length) {
                                    $.each(res.data, function(i, p) {
                                        dropdown.append(
                                            `<a href="#" class="dropdown-item" data-id="${p.id}" data-name="${p.name}">${p.name}</a>`
                                            );
                                    });
                                } else {
                                    dropdown.append(
                                        '<a class="dropdown-item disabled">No products</a>'
                                        );
                                }
                            }
                        });
                    }, 300);
                } else {
                    $('#search_results').empty().hide();
                }
            });

            // When product selected, fetch variants and add rows (each variant as separate row)
            $(document).on('click', '.dropdown-item', function(e) {
                e.preventDefault();
                let productId = $(this).data('id');
                let productName = $(this).data('name');
                $.ajax({
                    url: "{{ route('product.ajaxsearch') }}",
                    data: {
                        id: productId
                    },
                    success: function(res) {
                        let product = res.data;
                        let variants = res.variants || [];
                        if (variants.length > 0) {
                            // Add a row for each variant
                            $.each(variants, function(i, v) {
                                addProductRow(product.id, product.name, v.variant, v
                                    .price);
                            });
                        } else {
                            // No variants: add single row with empty variant
                            addProductRow(product.id, product.name, '', product
                                .buying_unit_cost);
                        }
                        $('#search_product').val('');
                        $('#search_results').hide();
                    }
                });
            });

            // function addProductRow(productId, productName, variant, price) {
            //     let row = `<tr>
        //         <td><input type="text" name="product_id[]" class="form-control" value="${productId}" readonly></td>
        //         <td><input type="text" name="name[]" class="form-control" value="${productName}" readonly></td>
        //         <td><input type="text" name="variant[]" class="form-control" value="${variant}" readonly></td>
        //         <td><input type="number" name="purchase_quantity[]" class="form-control purchase-quantity" value="1" min="1"></td>
        //         <td><input type="text" name="buying_unit_cost[]" class="form-control buying-unit-cost" value="${price}" readonly></td>
        //         <td><input type="number" name="total_cost[]" class="form-control total-cost" value="${price}"></td>
        //         <td><button type="button" class="btn btn-danger delete-product"><i class="fa fa-trash"></i></button></td>
        //     </tr>`;
            //     $('#purchase_entry_table tbody').append(row);
            //     calculateTotals();
            // }
            function addProductRow(productId, productName, variant, price) {
                let row = `<tr>
        <td><input type="text" name="product_id[]" class="form-control" value="${productId}" readonly></td>
        <td><input type="text" name="name[]" class="form-control" value="${productName}" readonly></td>
        <td><input type="text" name="variant[]" class="form-control" value="${variant}" readonly></td>
        <td><input type="number" name="purchase_quantity[]" class="form-control purchase-quantity" value="0" min="1"></td>
        <td><input type="number" name="buying_unit_cost[]" class="form-control buying-unit-cost" value="" step="0.01" placeholder="Unit Cost"></td>
        <td><input type="number" name="total_cost[]" class="form-control total-cost" value="0" step="0.01" placeholder="Total Cost"></td>
        <td><button type="button" class="btn btn-danger delete-product"><i class="fa fa-trash"></i></button></td>
     </tr>`;
                $('#purchase_entry_table tbody').append(row);
                calculateTotals();
            }

            // Recalculate on quantity or total cost change
            $(document).on('input', '.purchase-quantity, .total-cost', function() {
                let row = $(this).closest('tr');
                let qty = parseFloat(row.find('.purchase-quantity').val()) || 0;
                let total = parseFloat(row.find('.total-cost').val()) || 0;
                if (qty > 0 && total > 0) {
                    let unit = total / qty;
                    row.find('.buying-unit-cost').val(unit.toFixed(2));
                } else if (qty > 0 && row.find('.buying-unit-cost').val()) {
                    let unit = parseFloat(row.find('.buying-unit-cost').val());
                    row.find('.total-cost').val((qty * unit).toFixed(2));
                }
                calculateTotals();
            });

            // function calculateTotals() {
            //     let totalQty = 0, netTotal = 0;
            //     $('#purchase_entry_table tbody tr').each(function() {
            //         let qty = parseFloat($(this).find('.purchase-quantity').val()) || 0;
            //         let cost = parseFloat($(this).find('.total-cost').val()) || 0;
            //         totalQty += qty;
            //         netTotal += cost;
            //     });
            //     $('#total_quantity').text(totalQty.toFixed(2));
            //     $('#total_quantity_input').val(totalQty);
            //     $('#total_subtotal').text(netTotal.toFixed(2));
            //     $('#total_subtotal_input').val(netTotal);
            //     updateDueAndBalance();
            // }

            function calculateTotals() {
                let totalQty = 0,
                    netTotal = 0;
                $('#purchase_entry_table tbody tr').each(function() {
                    let qty = parseFloat($(this).find('.purchase-quantity').val()) || 0;
                    let total = parseFloat($(this).find('.total-cost').val()) || 0;
                    totalQty += qty;
                    netTotal += total;
                });
                $('#total_quantity').text(totalQty.toFixed(2));
                $('#total_quantity_input').val(totalQty);
                $('#total_subtotal').text(netTotal.toFixed(2));
                $('#total_subtotal_input').val(netTotal);
                updateDueAndBalance(); // if you have payment due display
            }

            $(document).on('click', '.delete-product', function() {
                $(this).closest('tr').remove();
                calculateTotals();
            });

            // Payment dynamic accounts (unchanged)
            $('#pay_by').change(function() {
                let type = $(this).val();
                let accSelect = $('#payment_account_id');
                if (type) {
                    $.ajax({
                        url: "{{ route('get.payment.accounts') }}",
                        data: {
                            type: type
                        },
                        success: function(accounts) {
                            accSelect.empty().append(
                            '<option value="">Select account</option>');
                            $.each(accounts, function(i, a) {
                                accSelect.append(
                                    `<option value="${a.id}" data-balance="${a.current_balance}">${a.name} (${a.current_balance})</option>`
                                    );
                            });
                            togglePaymentRequired();
                        }
                    });
                } else {
                    accSelect.empty().append('<option value="">-- First select Pay By --</option>');
                    togglePaymentRequired();
                }
            });

            function togglePaymentRequired() {
                let payBy = $('#pay_by').val();
                if (payBy && payBy !== '') {
                    $('#payment_account_id').prop('required', true);
                    $('#paid_amount').prop('required', true);
                    updateDueAndBalance();
                } else {
                    $('#payment_account_id').prop('required', false);
                    $('#paid_amount').prop('required', false);
                    let netTotal = parseFloat($('#total_subtotal_input').val()) || 0;
                    $('#payment_due_display').text(netTotal.toFixed(2));
                    $('#payment_due_input').val(netTotal);
                    $('#balance_warning').hide();
                    $('button[type="submit"]').prop('disabled', false);
                }
            }

            window.updateDueAndBalance = function() {
                let netTotal = parseFloat($('#total_subtotal_input').val()) || 0;
                let payBy = $('#pay_by').val();
                let paid = (payBy && payBy !== '') ? parseFloat($('#paid_amount').val()) || 0 : 0;
                let due = netTotal - paid;
                $('#payment_due_display').text(due.toFixed(2));
                $('#payment_due_input').val(due);
                if (payBy && payBy !== '' && paid > 0) {
                    let selected = $('#payment_account_id option:selected');
                    let balance = parseFloat(selected.data('balance')) || 0;
                    if (paid > balance) {
                        $('#balance_warning').show();
                        $('button[type="submit"]').prop('disabled', true);
                    } else {
                        $('#balance_warning').hide();
                        $('button[type="submit"]').prop('disabled', false);
                    }
                } else {
                    $('#balance_warning').hide();
                    $('button[type="submit"]').prop('disabled', false);
                }
            };

            $('#paid_amount, #payment_account_id').on('input change', function() {
                if ($('#pay_by').val()) updateDueAndBalance();
            });
            $(document).on('input', '.total-cost', updateDueAndBalance);
            togglePaymentRequired();

            // Form submit via AJAX
            $('#purchase_store_form').on('submit', function(e) {
                e.preventDefault();
                let formData = new FormData(this);
                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
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
        });

        // Two‑way calculation: quantity, unit cost, total cost
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
                } else if (total > 0) {
                    // If unit cost is empty but total is set, recalc unit cost
                    unit = total / qty;
                    row.find('.buying-unit-cost').val(unit.toFixed(2));
                }
            }
            calculateTotals();
        });
    </script>
@endsection --}}

{{-- @section('script')
    <script>
        $(document).ready(function() {
            // Supplier balance (optional)
            $('#supplier_id').change(function() {
                let id = $(this).val();
                if (id) {
                    $.get("{{ url('supplier-balance') }}/" + id, function(res) {
                        $('#advance_balance_text').text(res.balance);
                    });
                }
            });

            // Product search with debounce
            let searchTimeout;
            $('#search_product').on('keyup', function() {
                clearTimeout(searchTimeout);
                let query = $(this).val().trim();
                if (query.length > 0) {
                    searchTimeout = setTimeout(function() {
                        $.ajax({
                            url: "{{ route('product.ajaxsearch') }}",
                            data: {
                                search: query
                            },
                            success: function(res) {
                                let dropdown = $('#search_results');
                                dropdown.empty().show();
                                if (res.data.length) {
                                    $.each(res.data, function(i, p) {
                                        dropdown.append(
                                            `<a href="#" class="dropdown-item" data-id="${p.id}" data-name="${p.name}">${p.name}</a>`
                                            );
                                    });
                                } else {
                                    dropdown.append(
                                        '<a class="dropdown-item disabled">No products</a>'
                                        );
                                }
                            }
                        });
                    }, 300);
                } else {
                    $('#search_results').empty().hide();
                }
            });

            // When product selected, fetch variants and add rows
            // $(document).on('click', '.dropdown-item', function(e) {
            //     e.preventDefault();
            //     let productId = $(this).data('id');
            //     let productName = $(this).data('name');
            //     $.ajax({
            //         url: "{{ route('product.ajaxsearch') }}",
            //         data: { id: productId },
            //         success: function(res) {
            //             let product = res.data;
            //             let variants = res.variants || [];
            //             if(variants.length > 0) {
            //                 $.each(variants, function(i, v) {
            //                     addProductRow(product.id, product.name, v.variant, v.price);
            //                 });
            //             } else {
            //                 addProductRow(product.id, product.name, '', product.buying_unit_cost);
            //             }
            //             $('#search_product').val('');
            //             $('#search_results').hide();
            //         }
            //     });
            // });
            $(document).on('click', '.dropdown-item', function(e) {
                e.preventDefault();
                let productId = $(this).data('id');
                let productName = $(this).data('name');

                $.ajax({
                    url: "{{ route('product.ajaxsearch') }}",
                    data: {
                        id: productId
                    },
                    success: function(res) {
                        let product = res.data;
                        let variants = res.variants || [];

                        if (variants.length > 0) {
                            // Add a row for each variant
                            $.each(variants, function(i, v) {
                                // Fetch last purchase price for this variant
                                fetchLastPurchasePrice(productId, v.variant, function(
                                    lastPrice, lastQty) {
                                    addProductRow(
                                        product.id,
                                        product.name,
                                        v.variant,
                                        lastPrice || v
                                        .price, // Use last purchase price if available, otherwise variant price
                                        lastQty ||
                                        0 // Use last purchase quantity if available
                                    );
                                });
                            });
                        } else {
                            // No variants: add single row with empty variant
                            fetchLastPurchasePrice(productId, '', function(lastPrice, lastQty) {
                                addProductRow(
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
                    }
                });
            });

            // Function to fetch last purchase price and quantity for a product (and variant)
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
           

            // Updated addProductRow function to accept price and quantity
            function addProductRow(productId, productName, variant, price, qty) {
                let row = `<tr>
                <td><input type="text" name="product_id[]" class="form-control" value="${productId}" readonly></td>
                <td><input type="text" name="name[]" class="form-control" value="${productName}" readonly></td>
                <td><input type="text" name="variant[]" class="form-control" value="${variant}" readonly></td>
                <td><input type="number" name="purchase_quantity[]" class="form-control purchase-quantity" value="${qty}" min="1"></td>
                <td><input type="number" name="buying_unit_cost[]" class="form-control buying-unit-cost" value="${price}" step="0.01" placeholder="Unit Cost"></td>
                <td><input type="number" name="total_cost[]" class="form-control total-cost" value="${(qty * price).toFixed(2)}" step="0.01" placeholder="Total Cost"></td>
                <td><button type="button" class="btn btn-danger delete-product"><i class="fa fa-trash"></i></button></td>
                </tr>`;
                        $('#purchase_entry_table tbody').append(row);
                        calculateTotals();
                    }

            // Two‑way calculation: quantity, unit cost, total cost
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

            function calculateTotals() {
                let totalQty = 0,
                    netTotal = 0;
                $('#purchase_entry_table tbody tr').each(function() {
                    let qty = parseFloat($(this).find('.purchase-quantity').val()) || 0;
                    let total = parseFloat($(this).find('.total-cost').val()) || 0;
                    totalQty += qty;
                    netTotal += total;
                });
                $('#total_quantity').text(totalQty.toFixed(2));
                $('#total_quantity_input').val(totalQty);
                $('#total_subtotal').text(netTotal.toFixed(2));
                $('#total_subtotal_input').val(netTotal);
                updateDueAndBalance();
            }

            $(document).on('click', '.delete-product', function() {
                $(this).closest('tr').remove();
                calculateTotals();
            });

            // Payment dynamic accounts
            $('#pay_by').change(function() {
                let type = $(this).val();
                let accSelect = $('#payment_account_id');
                if (type) {
                    $.ajax({
                        url: "{{ route('get.payment.accounts') }}",
                        data: {
                            type: type
                        },
                        success: function(accounts) {
                            accSelect.empty().append(
                            '<option value="">Select account</option>');
                            $.each(accounts, function(i, a) {
                                accSelect.append(
                                    `<option value="${a.id}" data-balance="${a.current_balance}">${a.name} (${a.current_balance})</option>`
                                    );
                            });
                            togglePaymentRequired();
                        }
                    });
                } else {
                    accSelect.empty().append('<option value="">-- First select Pay By --</option>');
                    togglePaymentRequired();
                }
            });

            function togglePaymentRequired() {
                let payBy = $('#pay_by').val();
                if (payBy && payBy !== '') {
                    $('#payment_account_id').prop('required', true);
                    $('#paid_amount').prop('required', true);
                    updateDueAndBalance();
                } else {
                    $('#payment_account_id').prop('required', false);
                    $('#paid_amount').prop('required', false);
                    let netTotal = parseFloat($('#total_subtotal_input').val()) || 0;
                    $('#payment_due_display').text(netTotal.toFixed(2));
                    $('#payment_due_input').val(netTotal);
                    $('#balance_warning').hide();
                    $('button[type="submit"]').prop('disabled', false);
                }
            }

            window.updateDueAndBalance = function() {
                let netTotal = parseFloat($('#total_subtotal_input').val()) || 0;
                let payBy = $('#pay_by').val();
                let paid = (payBy && payBy !== '') ? parseFloat($('#paid_amount').val()) || 0 : 0;
                let due = netTotal - paid;
                $('#payment_due_display').text(due.toFixed(2));
                $('#payment_due_input').val(due);
                if (payBy && payBy !== '' && paid > 0) {
                    let selected = $('#payment_account_id option:selected');
                    let balance = parseFloat(selected.data('balance')) || 0;
                    if (paid > balance) {
                        $('#balance_warning').show();
                        $('button[type="submit"]').prop('disabled', true);
                    } else {
                        $('#balance_warning').hide();
                        $('button[type="submit"]').prop('disabled', false);
                    }
                } else {
                    $('#balance_warning').hide();
                    $('button[type="submit"]').prop('disabled', false);
                }
            };

            $('#paid_amount, #payment_account_id').on('input change', function() {
                if ($('#pay_by').val()) updateDueAndBalance();
            });
            $(document).on('input', '.total-cost', updateDueAndBalance);
            togglePaymentRequired();

            // Form submit via AJAX
            $('#purchase_store_form').on('submit', function(e) {
                e.preventDefault();
                let formData = new FormData(this);
                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
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
        });
    </script>
@endsection --}}

@section('script')
    <script>
        $(document).ready(function() {
            // Supplier balance (optional)
            $('#supplier_id').change(function() {
                let id = $(this).val();
                if (id) {
                    $.get("{{ url('supplier-balance') }}/" + id, function(res) {
                        $('#advance_balance_text').text(res.balance);
                    });
                }
            });

            // Product search with debounce
            let searchTimeout;
            $('#search_product').on('keyup', function() {
                clearTimeout(searchTimeout);
                let query = $(this).val().trim();
                if (query.length > 0) {
                    searchTimeout = setTimeout(function() {
                        $.ajax({
                            url: "{{ route('product.ajaxsearch') }}",
                            data: {
                                search: query
                            },
                            success: function(res) {
                                let dropdown = $('#search_results');
                                dropdown.empty().show();
                                if (res.data.length) {
                                    $.each(res.data, function(i, p) {
                                        dropdown.append(
                                            `<a href="#" class="dropdown-item" data-id="${p.id}" data-name="${p.name}">${p.name}</a>`
                                        );
                                    });
                                } else {
                                    dropdown.append(
                                        '<a class="dropdown-item disabled">No products</a>'
                                    );
                                }
                            }
                        });
                    }, 300);
                } else {
                    $('#search_results').empty().hide();
                }
            });

            // When product selected, fetch variants and add rows with product header
            $(document).on('click', '.dropdown-item', function(e) {
                e.preventDefault();
                let productId = $(this).data('id');
                let productName = $(this).data('name');

                $.ajax({
                    url: "{{ route('product.ajaxsearch') }}",
                    data: {
                        id: productId
                    },
                    success: function(res) {
                        let product = res.data;
                        let variants = res.variants || [];

                        // Add product name header row
                        addProductHeaderRow(productName);

                        if (variants.length > 0) {
                            // Add a row for each variant
                            $.each(variants, function(i, v) {
                                // Fetch last purchase price for this variant
                                fetchLastPurchasePrice(productId, v.variant, function(
                                    lastPrice, lastQty) {
                                    addProductRow(
                                        product.id,
                                        product.name,
                                        v.variant,
                                        lastPrice || v.price,
                                        lastQty || 0
                                    );
                                });
                            });
                        } else {
                            // No variants: add single row with empty variant
                            fetchLastPurchasePrice(productId, '', function(lastPrice, lastQty) {
                                addProductRow(
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
                    }
                });
            });

            // Add a product header row with full-width colored background
           function addProductHeaderRow(productName) {
                let headerRow = `<tr class="product-header bg-light">
                    <td colspan="7" style="background-color: #f0f0f0; font-weight: bold; padding: 8px; font-size:14px; vertical-align: middle;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center;">
                                <i class="fa fa-tag"></i> 
                                <span style="margin-left: 8px;">${productName}</span>
                            </div>
                            <button type="button" class="btn btn-sm btn-danger remove-product-group">
                                <i class="fa fa-times"></i> Remove All
                            </button>
                        </div>
                    </td>
                </tr>`;
                $('#purchase_entry_table tbody').append(headerRow);
            }

            // Remove entire product group when clicking remove button
            $(document).on('click', '.remove-product-group', function() {
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
            });

            // Function to fetch last purchase price and quantity for a product (and variant)
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

            // Updated addProductRow function with variant rows
            function addProductRow(productId, productName, variant, price, qty) {
                let variantDisplay = variant ? variant : '<span class="text-muted">No Variant</span>';
                let row = `<tr class="variant-row">
                    <td><input type="text" name="product_id[]" class="form-control" value="${productId}" readonly></td>
                    <td><input type="text" name="name[]" class="form-control" value="${productName}" readonly></td>
                    <td><input type="text" name="variant[]" class="form-control" value="${variant}" readonly></td>
                    <td><input type="number" name="purchase_quantity[]" class="form-control purchase-quantity" value="${qty}" min="1"></td>
                    <td><input type="number" name="buying_unit_cost[]" class="form-control buying-unit-cost" value="${price}" step="0.01" placeholder="Unit Cost"></td>
                    <td><input type="number" name="total_cost[]" class="form-control total-cost" value="${(qty * price).toFixed(2)}" step="0.01" placeholder="Total Cost"></td>
                    <td><button type="button" class="btn btn-danger btn-sm delete-product"><i class="fa fa-trash"></i></button></td>
                </tr>`;
                $('#purchase_entry_table tbody').append(row);
                calculateTotals();
            }

            // Two‑way calculation: quantity, unit cost, total cost
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

            // Delete individual variant row
            $(document).on('click', '.delete-product', function() {
                let row = $(this).closest('tr');
                let headerRow = row.prevAll('.product-header').first();
                
                // Remove the row
                row.remove();
                
                // Check if there are any more variant rows for this product
                let remainingVariants = headerRow.nextUntil('.product-header', '.variant-row');
                if (remainingVariants.length === 0) {
                    // If no variants left, remove the header too
                    headerRow.remove();
                }
                
                calculateTotals();
            });

            function calculateTotals() {
                let totalQty = 0,
                    netTotal = 0;
                $('#purchase_entry_table tbody tr.variant-row').each(function() {
                    let qty = parseFloat($(this).find('.purchase-quantity').val()) || 0;
                    let total = parseFloat($(this).find('.total-cost').val()) || 0;
                    totalQty += qty;
                    netTotal += total;
                });
                $('#total_quantity').text(totalQty.toFixed(2));
                $('#total_quantity_input').val(totalQty);
                $('#total_subtotal').text(netTotal.toFixed(2));
                $('#total_subtotal_input').val(netTotal);
                updateDueAndBalance();
            }

            // Payment dynamic accounts
            $('#pay_by').change(function() {
                let type = $(this).val();
                let accSelect = $('#payment_account_id');
                if (type) {
                    $.ajax({
                        url: "{{ route('get.payment.accounts') }}",
                        data: {
                            type: type
                        },
                        success: function(accounts) {
                            accSelect.empty().append(
                            '<option value="">Select account</option>');
                            $.each(accounts, function(i, a) {
                                accSelect.append(
                                    `<option value="${a.id}" data-balance="${a.current_balance}">${a.name} (${a.current_balance})</option>`
                                );
                            });
                            togglePaymentRequired();
                        }
                    });
                } else {
                    accSelect.empty().append('<option value="">-- First select Pay By --</option>');
                    togglePaymentRequired();
                }
            });

            function togglePaymentRequired() {
                let payBy = $('#pay_by').val();
                if (payBy && payBy !== '') {
                    $('#payment_account_id').prop('required', true);
                    $('#paid_amount').prop('required', true);
                    updateDueAndBalance();
                } else {
                    $('#payment_account_id').prop('required', false);
                    $('#paid_amount').prop('required', false);
                    let netTotal = parseFloat($('#total_subtotal_input').val()) || 0;
                    $('#payment_due_display').text(netTotal.toFixed(2));
                    $('#payment_due_input').val(netTotal);
                    $('#balance_warning').hide();
                    $('button[type="submit"]').prop('disabled', false);
                }
            }

            window.updateDueAndBalance = function() {
                let netTotal = parseFloat($('#total_subtotal_input').val()) || 0;
                let payBy = $('#pay_by').val();
                let paid = (payBy && payBy !== '') ? parseFloat($('#paid_amount').val()) || 0 : 0;
                let due = netTotal - paid;
                $('#payment_due_display').text(due.toFixed(2));
                $('#payment_due_input').val(due);
                if (payBy && payBy !== '' && paid > 0) {
                    let selected = $('#payment_account_id option:selected');
                    let balance = parseFloat(selected.data('balance')) || 0;
                    if (paid > balance) {
                        $('#balance_warning').show();
                        $('button[type="submit"]').prop('disabled', true);
                    } else {
                        $('#balance_warning').hide();
                        $('button[type="submit"]').prop('disabled', false);
                    }
                } else {
                    $('#balance_warning').hide();
                    $('button[type="submit"]').prop('disabled', false);
                }
            };

            $('#paid_amount, #payment_account_id').on('input change', function() {
                if ($('#pay_by').val()) updateDueAndBalance();
            });
            $(document).on('input', '.total-cost', updateDueAndBalance);
            togglePaymentRequired();

            // Form submit via AJAX
            $('#purchase_store_form').on('submit', function(e) {
                e.preventDefault();
                let formData = new FormData(this);
                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
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
        });
    </script>
@endsection
