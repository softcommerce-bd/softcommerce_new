<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\PurchaseOrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;

class PurchaseOrderController extends Controller
{
    public function __construct()
    {
        // Authentication is on the route group; these are the per-action role permissions.
        $this->middleware(['permission:view_purchase_orders'])->only(['purchaseOrderIndex', 'purchaseOrderView', 'getSupplierBalance', 'search', 'searchProducts', 'getReviewModalData', 'downloadPdfSinglePO']);
        $this->middleware(['permission:manage_purchase_orders'])->only(['create', 'purchaseOrderEdit', 'store', 'update', 'purchaseUpdatePayment', 'purchaseOrderDelete', 'convertToPurchase']);
    }

    // purchase List 
    public function purchaseOrderIndex()
    {
        $purchase = PurchaseOrder::latest()->get();
        return view('backend.purchaseOrder.index', compact('purchase'));
    }
    // purchase create method
    public function create()
    {
        $suppliers = Supplier::all();

        return view('backend.purchaseOrder.create', compact('suppliers'));
    }

    public function getSupplierBalance($id)
    {

        // Find the supplier by ID
        $supplier = Supplier::find($id);
        // If the supplier exists, return the opening balance
        if ($supplier) {
            return response()->json(['balance' => $supplier->opening_balance]);
        }

        // If supplier not found, return 0 balance
        return response()->json(['balance' => 0]);
    }



    // purchase Edit method 
    public function purchaseOrderEdit($id)
    {
        $suppliers = Supplier::all();
        $purchase = PurchaseOrder::with('details.product.stocks')->find($id);

        // Get all product IDs in this purchase order
        $productCodes = $purchase->details->pluck('product_id')->filter()->unique()->toArray();

        // Fetch latest previous buying price (TP) for each product from other purchase orders
        $previousPrices = PurchaseOrderDetail::whereIn('product_id', $productCodes)
            ->whereHas('purchaseOrder', function ($query) use ($id) {
                $query->where('id', '!=', $id);
            })
            ->select('product_id', 'buying_unit_cost', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('product_id')
            ->map(function ($group) {
                $latest = $group->first();
                return $latest->buying_unit_cost ?? null;
            });

        // Loop through each purchase detail to determine remaining stock qty
        foreach ($purchase->details as $detail) {
            $product = $detail->product;
            $productStocks = $product->stocks;
            $matchedStockQty = 0;

            if ($productStocks->count() > 0) {
                // If product has multiple variants
                if ($productStocks->count() > 1) {
                    foreach ($productStocks as $stock) {
                        if (
                            !empty($stock->variant) &&
                            stripos($product->name, $stock->variant) !== false
                        ) {
                            $matchedStockQty = $stock->qty;
                            break;
                        }
                    }
                }
                // If product has no variants (only one stock)
                else {
                    $matchedStockQty = $productStocks->first()->qty;
                }
            }

            $detail->remaining_quantity = $matchedStockQty;

            if (isset($previousPrices[$detail->product_id]) && !empty($previousPrices[$detail->product_id])) {
                $detail->previous_buying_cost = $previousPrices[$detail->product_id];
            } else {
                $detail->previous_buying_cost = null;
            }
        }

        // Use updated details as purchase products
        $purchaseProducts = $purchase->details;

        return view('backend.purchaseOrder.edit', compact('purchase', 'suppliers', 'purchaseProducts'));
    }

    //    purchase view method 
    public function purchaseOrderView($id)
    {
        $purchase = PurchaseOrder::find($id);
        if ($purchase) {
            $supplier = Supplier::where('id', $purchase->supplier_id)->first();
        }
        $suppliers = Supplier::all();

        // Make sure to get the products based on the purchase ID
        $purchaseProducts = PurchaseOrderDetail::where('purchase_order_id', $purchase->id)->get();
        return view('backend.purchaseOrder.view', compact('purchase', 'suppliers', 'supplier', 'purchaseProducts'));
    }

    public function search(Request $request)
    {
        $query = $request->input('search');
        if ($query) {
            $products = Product::when(is_numeric($query), function ($q) use ($query) {
                return $q->where('id', $query);
            })
                ->when(!is_numeric($query), function ($q) use ($query) {
                    return $q->where('name', 'LIKE', "%{$query}%");
                })
                ->limit(10)
                ->get();

            return response()->json(['data' => $products]);
        } elseif ($request->has('id')) {

            // Fetch the product by ID
            $product = Product::find($request->input('id'));

            if ($product) {
                $variants = ProductStock::where('product_id', $product->id)->get();
                $previousPurchase = PurchaseOrderDetail::where('product_id', $product->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $previousTP = $previousPurchase ? $previousPurchase->buying_unit_cost : null;
                if ($product->variant_product == 1) {
                    // Fetch all variants from the product_stocks table

                    return response()->json([
                        'data' => $product,
                        'variants' => $variants,
                        'previous_tp' => $previousTP
                    ]);
                } else {
                    // Return the product without variants
                    return response()->json([
                        'data' => $product,
                        'variants' => $variants,
                        'previous_tp' => $previousTP
                    ]);
                }
            }
        }

        return response()->json(['data' => []]);
    }

    // payment update method purchse list view  
    public function purchaseUpdatePayment(Request $request)
    {
        DB::beginTransaction();

        try {
            // Fetch the purchase record
            $purchase = PurchaseOrder::findOrFail($request->purchase_id);

            // Fetch the supplier record
            $supplier = Supplier::findOrFail($request->supplier_id);

            // Initialize variables
            $totalPaid = 0;
            $payments = $request->payments;

            // Decode existing advanced paid dates and payment methods
            $existingPaidOnDates = json_decode($purchase->advanced_paid_on, true) ?? [];
            $existingPaymentMethods = json_decode($purchase->payment_method, true) ?? [];

            // Process each payment
            foreach ($payments as $payment) {
                $amount = floatval($payment['amount']);
                $method = $payment['method'];

                // Update payment amounts based on method
                switch ($method) {
                    case 'advance':
                        // Deduct from supplier's opening balance
                        if ($supplier->opening_balance < $amount) {
                            return back()->with('error', 'Insufficient advance balance.');
                        }
                        $supplier->opening_balance -= $amount;
                        // Set the advance amount (don't add to the previous value)
                        $purchase->total_advance_amount = $amount;
                        break;

                    case 'cash':
                        // Set the cash amount (don't add to the previous value)
                        $purchase->total_cash_amount = $amount;
                        break;

                    case 'cheque':
                        // Set the cheque amount (don't add to the previous value)
                        $purchase->total_cheque_amount = $amount;
                        break;

                    case 'bank_transfer':
                        // Set the bank transfer amount (don't add to the previous value)
                        $purchase->total_bank_transfer_amount = $amount;
                        break;
                }

                // Add to the total paid amount
                $totalPaid += $amount;

                // Add new entries to the existing arrays
                $existingPaidOnDates[] = $payment['paid_on'];
                $existingPaymentMethods[] = $method;
            }

            // Update the payment arrays in the purchase model
            $purchase->advanced_paid_on = json_encode($existingPaidOnDates);
            $purchase->payment_method = json_encode($existingPaymentMethods);

            // Update the total payment due
            $purchase->total_payment_due -= $totalPaid;

            // Save the updated purchase and supplier
            $purchase->save();
            $supplier->save();

            DB::commit();
            flash(translate('Payment updated successfully!'))->success();
            return back();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update payment: ' . $e->getMessage());
        }
    }



    // purchase product store  
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $supplier = Supplier::find($request->supplier_id);

            $paymentDetails = [];
            $totalPayments = 0;
            $advancedAmounts = [];

            $totalAdvanceAmount = 0;
            $totalCashAmount = 0;
            $totalChequeAmount = 0;
            $totalBankTransferAmount = 0;

            foreach ($request->input('payments') as $payment) {
                // Collect payment details
                $paymentDetails[] = [
                    'advanced_amount' => $payment['amount'] ?? 0,
                    'advanced_paid_on' => $payment['paid_on'] ?? null,
                    'payment_method' => $payment['method'] ?? null,
                ];

                // Sum payment amounts
                $totalPayments += $payment['amount'] ?? 0;

                // Collect advanced amounts into an array
                if (isset($payment['amount'])) {
                    $advancedAmounts[] = $payment['amount'];
                }

                // Handle advance payment method
                if ($payment['method'] === 'advance') {
                    if ($supplier) {
                        // Check if the advance amount exceeds the supplier's opening balance
                        if ($payment['amount'] > $supplier->opening_balance) {
                            return response()->json([
                                'success' => false,
                                'type' => 'warning',
                                'message' => 'Not enough advanced balance in this supplier',
                            ]);
                        }

                        // Deduct the advance amount from the supplier's opening balance
                        $supplier->opening_balance -= $payment['amount'] ?? 0;
                        $supplier->save();

                        $totalAdvanceAmount += $payment['amount'] ?? 0;
                    }
                } elseif ($payment['method'] === 'cash') {
                    $totalCashAmount += $payment['amount'] ?? 0;
                } elseif ($payment['method'] === 'cheque') {
                    $totalChequeAmount += $payment['amount'] ?? 0;
                } elseif ($payment['method'] === 'bank_transfer') {
                    $totalBankTransferAmount += $payment['amount'] ?? 0;
                }
            }

            $request->validate([
                'supplier_id' => 'required|integer',
                'reference_no' => 'required|string|max:255|unique:purchases,reference_no',
                'purchase_date' => 'required|date',
                'business_location' => 'required|string|max:255',
                'pay_term' => 'nullable|integer',
                'pay_term_type' => 'nullable|string|max:255',
                'product_id' => 'required|array',
                'product_id.*' => 'required|integer',
                'total_cost' => 'required|array',
                'total_cost.*' => 'required|numeric',
                'payments' => 'required|array',
                'payments.*.amount' => 'required|numeric',
                'payments.*.paid_on' => 'required|date',
                'payments.*.method' => 'required|string|max:255',
                'document' => 'nullable|file|mimes:pdf,jpeg,png,jpg',
            ]);

            // Handle document upload
            $documentPath = null;
            if ($request->hasFile('document')) {
                $documentPath = $request->file('document')->store('documents', 'public');
            }

            // Store data in Purchase table
            $purchase = new PurchaseOrder();
            $purchase->supplier_id = $request->supplier_id;
            $purchase->reference_no = $request->reference_no;
            $purchase->purchase_date = $request->purchase_date;
            $purchase->business_location = $request->business_location;
            $purchase->pay_term = $request->pay_term;
            $purchase->payment_note = $request->payment_note;
            $purchase->pay_term_type = $request->pay_term_type;
            $purchase->document = $documentPath;

            // Initialize total amount for products
            $totalAmount = 0;

            // Store each product as a separate row in PurchaseProduct table
            $purchaseProducts = []; // Array to hold purchase product instances

            foreach ($request->input('product_id') as $index => $productId) {
                $purchaseProduct = new PurchaseOrderDetail();
                $purchaseProduct->product_id = $productId;
                $purchaseProduct->product_name = $request->input('name')[$index];
                $purchaseProduct->remaining_quantity = $request->input('remaining_quantity')[$index];
                $purchaseProduct->purchase_quantity = $request->input('purchase_quantity')[$index];
                $purchaseProduct->recieved_quantity = $request->input('recieved_quantity')[$index];
                $purchaseProduct->buying_unit_cost = $request->input('buying_unit_cost')[$index];
                $purchaseProduct->total_cost = $request->input('total_cost')[$index];
                $purchaseProduct->product_tax = $request->input('tax')[$index] ?? 0;

                // Calculate total amount
                $totalAmount += $purchaseProduct->total_cost;

                // Add the purchase product instance to the array
                $purchaseProducts[] = $purchaseProduct;
            }

            // Store total amount in the purchase table
            $purchase->total_item = count($request->input('product_id')); // Count of products
            $purchase->net_total_amount = $totalAmount; // Store total amount of all products

            // Store all advanced amounts as a JSON array
            $purchase->advanced_amount = json_encode($advancedAmounts);

            // Store payment details as JSON in the purchase table fields
            $purchase->advanced_paid_on = json_encode(array_column($paymentDetails, 'advanced_paid_on'));
            $purchase->payment_method = json_encode(array_column($paymentDetails, 'payment_method'));

            // Calculate total payment due
            $purchase->total_payment_due = $totalAmount - $totalPayments;
            $purchase->total_advance_amount = $totalAdvanceAmount;
            $purchase->total_cash_amount = $totalCashAmount;
            $purchase->total_cheque_amount = $totalChequeAmount;
            $purchase->total_bank_transfer_amount = $totalBankTransferAmount;

            // Save the purchase to the database
            $purchase->save();

            // Now that the purchase is saved, assign the purchase_order_id to each purchase product
            foreach ($purchaseProducts as $purchaseProduct) {
                $purchaseProduct->purchase_order_id = $purchase->id; // Link to the Purchase table
                $purchaseProduct->save(); // Save each purchase product
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Purchase and payments stored successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $purchaseId)
    {
        DB::transaction(function () use ($request, $purchaseId) {
            // Fetch existing purchase and products
            $purchase = PurchaseOrder::findOrFail($purchaseId);

            if ($purchase->document) {
                $filePath = $purchase->document; // Already stored as 'documents/filename.pdf'

                if (Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }
            }

            if ($purchase->total_advance_amount > 0) {
                $supplierold = Supplier::find($purchase->supplier_id);
                if ($supplierold) { // Ensure supplier exists before updating
                    $supplierold->opening_balance += $purchase->total_advance_amount;
                    $supplierold->save(); // Save the updated supplier balance
                }
            }

            // Delete old purchase product records but keep purchase record
            PurchaseOrderDetail::where('purchase_order_id', $purchaseId)->delete();

            // Handle supplier advance balance
            $supplier = Supplier::find($request->supplier_id);
            $paymentDetails = [];
            $totalPayments = 0;
            $advancedAmounts = [];
            $totalAdvanceAmount = 0;
            $totalCashAmount = 0;
            $totalChequeAmount = 0;
            $totalBankTransferAmount = 0;

            foreach ($request->input('payments') as $payment) {
                $paymentDetails[] = [
                    'advanced_amount' => $payment['amount'] ?? 0,
                    'advanced_paid_on' => $payment['paid_on'] ?? null,
                    'payment_method' => $payment['method'] ?? null,
                ];

                $totalPayments += $payment['amount'] ?? 0;
                if (isset($payment['amount'])) {
                    $advancedAmounts[] = $payment['amount'];
                }

                if ($payment['method'] === 'advance' && $supplier) {
                    if ($payment['amount'] > $supplier->opening_balance) {
                        throw new \Exception('Not enough advanced balance in this supplier');
                    }
                    $supplier->opening_balance -= $payment['amount'] ?? 0;
                    $supplier->save();
                    $totalAdvanceAmount += $payment['amount'] ?? 0;
                } elseif ($payment['method'] === 'cash') {
                    $totalCashAmount += $payment['amount'] ?? 0;
                } elseif ($payment['method'] === 'cheque') {
                    $totalChequeAmount += $payment['amount'] ?? 0;
                } elseif ($payment['method'] === 'bank_transfer') {
                    $totalBankTransferAmount += $payment['amount'] ?? 0;
                }
            }

            // Handle document upload
            $documentPath = $purchase->document;
            if ($request->hasFile('document')) {
                $documentPath = $request->file('document')->store('documents', 'public');
            }

            // Update existing purchase record
            $purchase->supplier_id = $request->supplier_id;
            $purchase->reference_no = $request->reference_no;
            $purchase->purchase_date = $request->purchase_date;
            $purchase->business_location = $request->business_location;
            $purchase->pay_term = $request->pay_term;
            $purchase->payment_note = $request->payment_note;
            $purchase->pay_term_type = $request->pay_term_type;
            $purchase->document = $documentPath;

            // Initialize total amount for products
            $totalAmount = 0;
            $purchaseProducts = [];

            foreach ($request->input('product_id') as $index => $productId) {

                $purchaseProduct = new PurchaseOrderDetail();

                $purchaseProduct->purchase_order_id = $purchaseId;
                $purchaseProduct->product_id = $productId;
                $purchaseProduct->product_name = $request->input('name')[$index];
                $value = $request->input('remaining_quantity')[$index] ?? $purchaseProduct->remaining_quantity;
                if (filled($value)) {
                    $purchaseProduct->remaining_quantity = $value;
                }
                $purchaseProduct->purchase_quantity = $request->input('purchase_quantity')[$index] ?? $purchaseProduct->purchase_quantity;
                $purchaseProduct->recieved_quantity = $request->input('recieved_quantity')[$index] ?? $purchaseProduct->recieved_quantity;
                $purchaseProduct->buying_unit_cost = $request->input('buying_unit_cost')[$index];
                $purchaseProduct->total_cost = $request->input('total_cost')[$index];
                $purchaseProduct->product_tax = $request->input('tax')[$index] ?? 0;
                $purchaseProduct->save(); // Save PurchaseProduct

                $totalAmount += $purchaseProduct->total_cost;
                $addedQuantity = $purchaseProduct->purchase_quantity;
            }

            // Update purchase details
            $purchase->total_item = count($request->input('product_id'));
            $purchase->net_total_amount = $totalAmount;
            $purchase->advanced_amount = json_encode($advancedAmounts);
            $purchase->advanced_paid_on = json_encode(array_column($paymentDetails, 'advanced_paid_on'));
            $purchase->payment_method = json_encode(array_column($paymentDetails, 'payment_method'));
            $purchase->total_payment_due = $totalAmount - $totalPayments;
            $purchase->total_advance_amount = $totalAdvanceAmount;
            $purchase->total_cash_amount = $totalCashAmount;
            $purchase->total_cheque_amount = $totalChequeAmount;
            $purchase->total_bank_transfer_amount = $totalBankTransferAmount;

            // Save updated purchase
            $purchase->save();
        });

        return response()->json(['success' => true, 'redirect_url' => route('purchase.order.index'), 'message' => 'Purchase updated successfully']);
    }


    // supplier wise search product in ajax
    public function searchProducts(Request $request)
    {

        $searchKeyword = $request->input('search');
        $supplierId = $request->input('supplier_id');

        // Fetch the IDs of purchases for the selected supplier
        $purchaseIds = PurchaseOrder::where('supplier_id', $supplierId)->pluck('id');
        // dd($purchaseIds);

        // Fetch products related to the retrieved purchase IDs and filter by search keyword
        $products = PurchaseOrderDetail::whereIn('purchase_order_id', $purchaseIds)
            ->join('products', 'purchase_products.product_id', '=', 'products.id')
            ->where('products.name', 'like', "%$searchKeyword%")
            ->select('purchase_products.purchase_order_id', 'products.id', 'products.name', 'purchase_products.purchase_quantity', 'purchase_products.buying_unit_cost', DB::raw('purchase_products.purchase_quantity * purchase_products.buying_unit_cost as subtotal'))
            ->get();
        // dd($products);

        return response()->json(['products' => $products, 'purchaseId' => $purchaseIds]);
    }

    public function getReviewModalData($id)
    {
        $purchase = PurchaseOrder::with(['supplier', 'details.product'])->findOrFail($id);

        // Get payment amounts (you'll need to adjust this based on your data structure)
        $paymentAmounts = [];
        if ($purchase->payment_method) {
            $methods = json_decode($purchase->payment_method, true);
            foreach ($methods as $method) {
                switch (strtolower($method)) {
                    case 'advance':
                        $paymentAmounts[] = (float) $purchase->total_advance_amount;
                        break;
                    case 'cash':
                        $paymentAmounts[] = (float) $purchase->total_cash_amount;
                        break;
                    case 'cheque':
                        $paymentAmounts[] = (float) $purchase->total_cheque_amount;
                        break;
                    case 'bank_transfer':
                    case 'bank transfer':
                        $paymentAmounts[] = (float) $purchase->total_bank_transfer_amount;
                        break;
                    default:
                        $paymentAmounts[] = 0;
                        break;
                }
            }
        }

        // Map through details to get product info
        $products = $purchase->details->map(function ($detail) {
            return [
                'id' => $detail->product_id,
                'name' => $detail->product ? $detail->product_name : 'N/A',
                'purchase_quantity' => $detail->purchase_quantity,
                'received_quantity' => $detail->received_quantity ?? 0,
                'buying_unit_cost' => $detail->buying_unit_cost,
                'total_cost' => $detail->total_cost,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'supplier' => $purchase->supplier->business_name,
                'supplier_id' => $purchase->supplier_id,
                'business_location' => $purchase->business_location,
                'pay_term' => $purchase->pay_term,
                'pay_term_type' => $purchase->pay_term_type,
                'reference_no' => $purchase->reference_no,
                'purchase_date' => $purchase->purchase_date,
                'product_id' => $products,
                'payment_methods' => json_decode($purchase->payment_method, true),
                'payment_paid_on' => json_decode($purchase->advanced_paid_on, true),
                'payment_amounts' => $paymentAmounts,
                'payment_note' => $purchase->payment_note,
                'total_payment_due' => $purchase->total_payment_due,
            ]
        ]);
    }


    public function convertToPurchase(Request $request, $id)
    {
        $request->validate([
            'purchase_quantity' => ['required', 'array'],
            'purchase_quantity.*' => ['required', 'gt:0'],
        ], [
            'purchase_quantity.*.gt' => 'Purchase quantity must be greater than 0.',
            'purchase_quantity.*.required' => 'Please fill all purchase quantities.',
        ]);

        $data = $request->all();
        $data['purchase_quantity'] = array_map('intval', $data['purchase_quantity'] ?? []);

        $po = PurchaseOrder::find($id);

        if ($po->status === 'purchased' || Purchase::where('reference_no', $request->reference_no)->exists()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'PO already converted to purchase.',
                ]);
            } else {
                // For normal browser requests
                flash(__('PO already converted to purchase.'))->error();
                return redirect()->back()->withInput();
            }
        }


        // Create new request with filtered data
        $newRequest = new Request($data);

        try {
            $purchaseController = app(PurchaseController::class);
            $response = $purchaseController->store($newRequest);
            $responseData = $response->getData(true);

            if ($responseData['success'] ?? false) {
                $po->update(['status' => 'purchased']);

                foreach ($data['purchase_quantity'] as $index => $qty) {

                    $productId = $data['product_id'][$index] ?? null;

                    $detail = $po->details()
                        ->where('product_id', $productId)
                        ->first();

                    if ($detail) {
                        $detail->update([
                            'recieved_quantity' => $qty
                        ]);
                    }
                }

                if ($request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Purchase created successfully.',
                        'redirect_url' => route('purchase.index'),
                    ]);
                } else {
                    // For normal browser requests
                    flash(__('Purchase created successfully!'))->success();
                    return redirect()->route('purchase.index');
                }
            } else {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Something went wrong during purchase.',
                    ]);
                } else {
                    flash(__('Something went wrong during purchase.'))->error();
                    return redirect()->back()->withInput();
                }
            }
        } catch (\Exception $e) {
            flash(__('Unexpected error: ') . $e->getMessage())->error();
            return redirect()->back()->withInput();
        }
    }

    public function downloadPdfSinglePO($id)
    {
        // Find the specific purchase order by ID with details and supplier
        $order = PurchaseOrder::with(['details.product', 'supplier'])->findOrFail($id);

        $productCodes = $order->details->pluck('product_id')->filter()->unique()->toArray();

        $previousPrices = PurchaseOrderDetail::whereIn('product_id', $productCodes)
            ->whereHas('purchaseOrder', function ($query) use ($id) {
                $query->where('id', '!=', $id);
            })
            ->select('product_id', 'buying_unit_cost')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('product_id')
            ->map(function ($group) {
                $latest = $group->first();
                // Calculate your TP based on available fields
                return $latest->buying_unit_cost; // or your custom calculation
            });

        $font_family = 'DejaVu Sans'; // universal for mPDF
        $direction   = 'ltr';
        $text_align  = 'left';
        $not_text_align = 'right';

        // Pass the single order instead of all orders
        $data = compact('order', 'font_family', 'direction', 'text_align', 'not_text_align', 'previousPrices');

        $html = view('backend.purchaseOrder.pdfPO', $data)->render();
        // return $html;

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'orientation'   => 'P',
            'default_font'  => $font_family,
            'margin_left'   => 10,
            'margin_right'  => 10,
            'margin_top'    => 10,
            'margin_bottom' => 10,
        ]);

        $mpdf->WriteHTML($html);

        // Use the purchase order number in the filename
        $filename = 'purchase_order_' . ($order->po_number ?? $order->id) . '.pdf';

        return $mpdf->Output($filename, 'D');
    }
}
