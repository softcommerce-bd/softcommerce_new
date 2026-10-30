@extends('backend.layouts.app')

@section('content')
<style>
    #search_results {
        border: 1px solid #ddd;
        background: #fff;
        position: absolute;
        width: 100%;
        max-width: 400px;
        max-height: 300px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .search-result-item {
        padding: 10px 15px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
        transition: background 0.2s;
    }

    .search-result-item:hover {
        background-color: #f8f9fa;
    }

    .search-result-item:last-child {
        border-bottom: none;
    }

    #product_table th {
        background-color: #f8f9fa;
        font-weight: 600;
    }
</style>

<section class="content no-print">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-barcode mr-2"></i> Generate Barcode Labels</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label class="font-weight-bold">Search Product</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-search text-primary"></i>
                                </span>
                            </div>
                            <input type="text" class="form-control form-control-lg" 
                                   id="search_product_for_label"
                                   placeholder="Type product name to search..." 
                                   autocomplete="off">
                        </div>
                    </div>
                </div>
            </div>

            <div id="search_results"></div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="product_table">
                            <thead class="thead-dark">
                                <tr>
                                    <th width="40%" class="text-dark">Product</th>
                                    <th width="20%" class="text-dark">No. of Labels</th>
                                    <th width="25%" class="text-dark">Packing Date</th>
                                    <th width="15%" class="text-dark">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Products will be added here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-print mr-2"></i> Print Settings</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="font-weight-bold">Label Format</label>
                        <select class="form-control" id="barcode_setting">
                            <option value="6">📜 Continuous Roll (31.75mm × 25.4mm)</option>
                            <option value="1">📄 Sheet Paper - 20 labels/sheet</option>
                            <option value="2">📄 Sheet Paper - 30 labels/sheet</option>
                            <option value="4">📄 Sheet Paper - 40 labels/sheet</option>
                            <option value="5">📄 Sheet Paper - 50 labels/sheet</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-12 mt-4">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Note:</strong> For continuous roll printing, ensure your printer is set to the correct label size (31.75mm × 25.4mm)
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-12 text-center">
                    <button type="button" id="labels_preview" class="btn btn-primary btn-lg px-5">
                        <i class="fas fa-eye mr-2"></i> Preview & Print
                    </button>
                    
                    <button type="button" id="clear_all" class="btn btn-outline-danger btn-lg ml-3">
                        <i class="fas fa-trash mr-2"></i> Clear All
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    let searchTimer;

    // Search Products
    $('#search_product_for_label').on('keyup', function() {
        clearTimeout(searchTimer);
        let query = $(this).val().trim();
        
        if (query.length < 2) {
            $('#search_results').html('').hide();
            return;
        }
        
        searchTimer = setTimeout(function() {
            $.ajax({
                url: "{{ route('products.search') }}",
                method: 'GET',
                data: { query: query },
                success: function(response) {
                    if (response.trim()) {
                        $('#search_results').html(response).show();
                    } else {
                        $('#search_results').html('<div class="search-result-item">No products found</div>').show();
                    }
                }
            });
        }, 500);
    });

    // Close search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#search_results, #search_product_for_label').length) {
            $('#search_results').hide();
        }
    });

    // Add product to table
    $(document).on('click', '.search-result-item', function() {
        let product = $(this).data('product');
        addProductToTable(product);
        $('#search_results').hide();
    });

    function addProductToTable(product) {
        // Check if product already exists
        if ($(`input[name="products[${product.id}][product_id]"]`).length) {
            alert('⚠️ This product is already in the list.');
            return;
        }

        let row = `
        <tr id="product-row-${product.id}">
            <td>
                <strong>${product.name}</strong><br>
                <small class="text-muted">SKU: ${product.barcode || product.id}</small>
                <input type="hidden" name="products[${product.id}][product_id]" value="${product.id}">
                <input type="hidden" name="products[${product.id}][name]" value="${product.name}">
                <input type="hidden" name="products[${product.id}][barcode]" value="${product.barcode}">
                <input type="hidden" name="products[${product.id}][unit_price]" value="${product.unit_price}">
                <input type="hidden" name="products[${product.id}][discount]" value="${product.discount || 0}">
                <input type="hidden" name="products[${product.id}][discount_type]" value="${product.discount_type || ''}">
            </td>
            <td>
                <input type="number" class="form-control" min="1" max="100" 
                       name="products[${product.id}][quantity]" value="1" 
                       style="width: 80px;">
            </td>
            <td>
                <input type="date" class="form-control" 
                       name="products[${product.id}][packing_date]"
                       value="${new Date().toISOString().split('T')[0]}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger remove-product" 
                        data-id="${product.id}">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        </tr>`;
        
        $('#product_table tbody').append(row);
        $('#search_product_for_label').val('').focus();
    }

    // Remove product
    $(document).on('click', '.remove-product', function() {
        let productId = $(this).data('id');
        $(`#product-row-${productId}`).remove();
    });

    // Clear all products
    $('#clear_all').on('click', function() {
        if (confirm('Are you sure you want to clear all products?')) {
            $('#product_table tbody').empty();
        }
    });

    // Preview & Print
    $('#labels_preview').on('click', function() {
        let products = [];
        let hasError = false;
        
        $('#product_table tbody tr').each(function() {
            let quantity = $(this).find('[name$="[quantity]"]').val();
            if (quantity < 1 || quantity > 100) {
                alert('⚠️ Quantity must be between 1 and 100');
                $(this).find('[name$="[quantity]"]').focus();
                hasError = true;
                return false;
            }
            
            products.push({
                id: $(this).find('[name$="[product_id]"]').val(),
                name: $(this).find('[name$="[name]"]').val(),
                barcode: $(this).find('[name$="[barcode]"]').val(),
                unit_price: $(this).find('[name$="[unit_price]"]').val(),
                discount: $(this).find('[name$="[discount]"]').val(),
                discount_type: $(this).find('[name$="[discount_type]"]').val(),
                quantity: quantity,
                packing_date: $(this).find('[name$="[packing_date]"]').val()
            });
        });

        if (hasError) return;
        
        if (products.length === 0) {
            alert('❌ Please add at least one product.');
            return;
        }

        // Show loading
        $(this).html('<i class="fas fa-spinner fa-spin mr-2"></i> Generating...').prop('disabled', true);
        
        $.ajax({
            url: "{{ route('barcodes.generate') }}",
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                products: products,
                barcode_setting: $('#barcode_setting').val()
            },
            success: function(response) {
                $('#labels_preview').html('<i class="fas fa-eye mr-2"></i> Preview & Print').prop('disabled', false);
                
                if (response && response.html) {
                    generatePrintPreview(response);
                } else {
                    alert('❌ Error: Could not generate labels.');
                }
            },
            error: function() {
                $('#labels_preview').html('<i class="fas fa-eye mr-2"></i> Preview & Print').prop('disabled', false);
                alert('❌ Server error. Please try again.');
            }
        });
    });

    function generatePrintPreview(response) {
        let iframe = document.createElement('iframe');
        iframe.name = 'printFrame';
        iframe.style.cssText = 'position:absolute;width:0;height:0;border:0;';
        document.body.appendChild(iframe);
        
        let doc = iframe.contentWindow.document;
        doc.open();
        
        // Write print document
        doc.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Barcode Labels - Print</title>
            <meta charset="UTF-8">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                ${response.isContinuousRoll ? getContinuousRollStyles() : getSheetStyles(response.labelsPerSheet)}
            </style>
        </head>
        <body>
            ${response.html}
            <script>
                window.onload = function() {
                    window.focus();
                    setTimeout(function() {
                        window.print();
                        setTimeout(function() {
                            window.close();
                        }, 1000);
                    }, 500);
                };
            <\/script>
        </body>
        </html>`);
        
        doc.close();
        
        // Remove iframe after printing
        setTimeout(() => {
            document.body.removeChild(iframe);
        }, 2000);
    }

    // function getContinuousRollStyles() {
    //     return `
    //     @page {
    //         size: 31.75mm 25.4mm;
    //         margin: 0px;
    //     }
        
    //     body {
    //         font-family: 'Segoe UI', Arial, sans-serif;
    //         background: white;
    //         margin: 0;
    //         padding: 0;
    //         -webkit-print-color-adjust: exact;
    //         print-color-adjust: exact;
    //     }
        
    //     .barcode-page {
    //         width: 31.75mm;
    //         height: 25.4mm;
    //         margin:5px 0;
    //         page-break-after: always;
    //         display: flex;
    //         align-items: center;
    //         justify-content: center;
    //         position: relative;
    //     }
        
    //     .barcode-item {
    //         width: 31.75mm;
    //         height: 25.4mm;
    //         padding: 1.5mm;
    //         text-align: center;
    //         border: none;
    //     }
        
    //     .store-name {
    //         font-size: 8pt;
    //         font-weight: bold;
    //         color: #000;
    //         margin-bottom: 0.5mm;
    //         text-transform: uppercase;
    //         letter-spacing: 0.5px;
    //     }
        
    //     .product-name {
    //         font-size: 7pt;
    //         font-weight: 600;
    //         color: #333;
    //         margin-bottom: 0px;
    //         line-height: 1.1;
    //         overflow: hidden;
    //         text-overflow: ellipsis;
    //         white-space: nowrap;
    //     }
        
    //     .price-container {
    //         margin-bottom: 0px;
    //     }
        
    //     .current-price {
    //         font-size: 9pt;
    //         font-weight: bold;
    //         color: #d32f2f;
    //     }
        
    //     .original-price {
    //         font-size: 6pt;
    //         color: #666;
    //         text-decoration: line-through;
    //         margin-left: 1mm;
    //     }
        
    //     .barcode-image {
    //         width: 100%;
    //         height: 6mm;
    //         margin: 0;
    //     }
        
    //     .barcode-image img {
    //         width: 100%;
    //         height: 100%;
    //         object-fit: contain;
    //         image-rendering: crisp-edges;
    //     }
        
    //     .barcode-number {
    //         font-size: 6pt;
    //         font-family: 'Courier New', monospace;
    //         color: #000;
    //         letter-spacing: 0.5px;
    //         margin-top: 0.5mm;
    //     }
        
    //     .packing-date {
    //         font-size: 5pt;
    //         color: #666;
    //         margin-top: 0.3mm;
    //     }
    //     `;
    // }
    
    function getContinuousRollStyles() {
    return `
    @page {
        size: 31.75mm 25.4mm;
        margin: 0;
        padding: 0;
    }
    
    body {
        font-family: Arial, sans-serif;
        background: white;
        margin: 0 !important;
        padding: 0 !important;
        width: 31.75mm !important;
        height: 25.4mm !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        overflow: hidden !important;
    }
    
    .continuous-roll-page {
        width: 31.75mm !important;
        height: 25.4mm !important;
        margin: 0 !important;
        padding: 0 !important;
        page-break-after: always !important;
        page-break-inside: avoid !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        position: relative !important;
        overflow: hidden !important;
        border: none !important;
    }
    
    /* Ensure every label fits exactly in one page */
    .barcode-item {
        width: 31.75mm !important;
        height: 25.4mm !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    
    @media print {
        html, body {
            width: 31.75mm !important;
            height: 25.4mm !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .continuous-roll-page {
            break-after: always !important;
            break-inside: avoid !important;
        }
    }
    `;
}

    function getSheetStyles(labelsPerSheet) {
        let columns, rows;
        
        if (labelsPerSheet <= 20) {
            columns = 4;
            rows = 5;
        } else if (labelsPerSheet <= 30) {
            columns = 5;
            rows = 6;
        } else if (labelsPerSheet <= 40) {
            columns = 5;
            rows = 8;
        } else {
            columns = 5;
            rows = 10;
        }
        
        let labelWidth = (100 / columns) - 0.5;
        let labelHeight = (100 / rows) - 0.5;
        
        return `
        @page {
            size: letter;
            margin: 0.5in;
        }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: white;
            margin: 0;
            padding: 0;
        }
        
        .barcode-page {
            width: 8.5in;
            height: 11in;
            page-break-after: always;
            padding: 0.25in;
            display: flex;
            flex-wrap: wrap;
            align-content: flex-start;
        }
        
        .barcode-item {
            width: ${labelWidth}%;
            height: ${labelHeight}%;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin: 0.25%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            box-sizing: border-box;
            background: white;
            page-break-inside: avoid;
        }
        
        .store-name {
            font-size: 9px;
            font-weight: bold;
            color: #000;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        
        .product-name {
            font-size: 8px;
            font-weight: 600;
            color: #333;
            margin-bottom: 0px;
            line-height: 1.1;
            height: 18px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .price-container {
            margin-bottom: 0px;
        }
        
        .current-price {
            font-size: 12px;
            font-weight: bold;
            color: #d32f2f;
        }
        
        .original-price {
            font-size: 9px;
            color: #666;
            text-decoration: line-through;
            margin-left: 2px;
        }
        
        .barcode-image {
            width: 100%;
            height: 10px;
            margin: 1px 0;
        }
        
        .barcode-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .barcode-number {
            font-size: 7px;
            font-family: 'Courier New', monospace;
            color: #555;
            letter-spacing: 0.5px;
        }
        
        .packing-date {
            font-size: 6px;
            color: #888;
            margin-top: 1px;
        }
        
        @media print {
            .barcode-page {
                page-break-inside: avoid;
            }
        }
        `;
    }
});
</script>
@endsection