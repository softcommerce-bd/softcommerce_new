{{-- <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label for="sr_location_id">Business Location:</label>
                <select class="form-control select2" id="sr_location_id" name="sr_location_id">
                    <option value="" selected>All locations</option>
                    <option value="1">Awesome Shop</option>
                </select>
            </div>
        </div>
    </div> --}}
<div class="row mt-4">
    <div class="col-md-12">
        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-outline-secondary btn-sm print_tab_pdf">
                <i class="fas fa-print"></i> Print
            </button>
            <button class="btn btn-outline-secondary btn-sm download_tab_pdf ms-2">
                <i class="fas fa-download"></i> Download PDF
            </button>
        </div>

        <div class="print-area">
            <div class="table-responsive">
                <div id="supplier_stock_report_table_wrapper" class="dataTables_wrapper dt-bootstrap4">
                    <table class="table table-bordered mb-0" id="myTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ translate('Supplier Name') }}</th>
                                <th>{{ translate('Product Name') }}</th>
                                <th>{{ translate('Purchase Quantity') }}</th>
                                <th>{{ translate('Buying Unit Cost') }}</th>
                                <th>{{ translate('Total Cost') }}</th>
                                <th>{{ translate('Total Return') }}</th>
                                <th>{{ translate('Current Stock') }}</th>
                                <th>{{ translate('Tax Number') }}</th>
                            </tr>
                        </thead>
                        <tbody>

                            @php
                                $purchaseReturns = \App\Models\PurchaseReturn::with('productReturn')
                                    ->where('supplier_id', $supplier->id) // Fixed typo: 'suppler_id' to 'supplier_id'
                                    ->get();
                            @endphp

                            @if ($supplierWisePurchased->isEmpty())
                                <tr>
                                    <td colspan="9" class="text-center">{{ translate('No Supplier available') }}</td>
                                </tr>
                            @else
                                {{-- @foreach ($supplierWisePurchased as $purchase)
                                @foreach ($purchase->products as $key => $product)
                                    @php
                                        // Fetch product with stocks safely
                                        $productMain = \App\Models\Product::with('stocks')->find($product->product_id);
                                       
                                        if ($productMain) {
                                            $currentStock = $productMain->stocks->sum('qty'); // Example: Assuming stocks have a 'quantity' field
                                          
                                        } else {
                                            $currentStock = 'N/A';
                                        }
                            
                                        // Calculate purchase return quantity
                                        $purchaseReturnQuantity = $purchaseReturns->flatMap->productReturn
                                            ->where('product_id', $product->product_id)
                                            ->sum('purchase_quantity');
                                        
                                        // Debug specific data if needed
                                        // Uncomment for debugging: dd($productMain, $currentStock, $purchaseReturnQuantity);
                                    @endphp
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $supplier->business_name }}</td>
                                        <td>{{ $product->product_name }}</td>
                                        <td>{{ $product->purchase_quantity }}</td>
                                        <td>{{ $product->buying_unit_cost }}</td>
                                        <td>{{ $product->total_cost }}</td>
                                        <td>{{ $purchaseReturnQuantity ?? 0 }}</td>
                                        <td>{{ $currentStock }}</td>
                                        <td>{{ $product->product_tax }}</td>
                                    </tr>
                                @endforeach
                            @endforeach --}}
                                @foreach ($supplierWisePurchased as $purchase)
                                    @foreach ($purchase->products as $key => $product)
                                        @php
                                            preg_match('/\((.*?)\)/', $product->product_name, $matches);
                                            $variantName = $matches[1] ?? null;

                                            // Fetch product with stocks
                                            $productMain = \App\Models\Product::with('stocks')->find(
                                                $product->product_id,
                                            );

                                            if ($productMain && count($productMain->stocks) > 0) {
                                                if ($variantName) {
                                                    $stockForVariant = $productMain->stocks->firstWhere(
                                                        'variant',
                                                        $variantName,
                                                    );

                                                    if ($stockForVariant) {
                                                        $currentStock = $stockForVariant->qty;
                                                    } else {
                                                        $firstStock = $productMain->stocks->first();
                                                        $variantLabel = ' (No Variant)';
                                                        $currentStock = $firstStock->qty . $variantLabel;
                                                    }
                                                } else {
                                                    // No variant in product name, just take first stock
                                                    $firstStock = $productMain->stocks->first();
                                                    $currentStock = $firstStock->qty;
                                                }
                                            }

                                            // Calculate purchase return quantity
                                            $purchaseReturnQuantity = $purchaseReturns->flatMap->productReturn
                                                ->where('product_id', $product->product_id)
                                                ->sum('purchase_quantity');
                                        @endphp
                                        <tr>
                                            <td>{{ $key + 1 }}</td>
                                            <td>{{ $supplier->business_name }}</td>
                                            <td>{{ $product->product_name }}</td>
                                            <td>{{ $product->purchase_quantity }}</td>
                                            <td>{{ $product->buying_unit_cost }}</td>
                                            <td>{{ $product->total_cost }}</td>
                                            <td>{{ $purchaseReturnQuantity ?? 0 }}</td>
                                            <td>{{ $currentStock }}</td> <!-- Variant-wise stock -->
                                            <td>{{ $product->product_tax }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach

                            @endif

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@section('script')
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#myTable').DataTable();
        });
    </script>
@endsection
