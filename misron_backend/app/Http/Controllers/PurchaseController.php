<?php

namespace App\Http\Controllers;

use App\Models\AccountStatementLog;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use App\Models\PurchaseReturn;
use App\Models\purchaseReturnProduct;
use App\Models\Supplier;
use App\Services\Inventory\StockLedger;
use App\Traits\StatementLogTrait;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
{
    public function __construct()
    {
        // Authentication is on the route group; these are the per-action role permissions.
        $this->middleware(['permission:view_purchases'])->only(['purchaseIndex', 'purchaseView', 'getSupplierBalance', 'search', 'searchProducts', 'getLastPurchasePrice', 'getPaymentAccounts', 'purchaseReturnList']);
        $this->middleware(['permission:add_purchase'])->only(['create', 'store']);
        $this->middleware(['permission:edit_purchase'])->only(['purchaseEdit', 'update', 'purchaseUpdatePayment']);
        $this->middleware(['permission:delete_purchase'])->only(['purchaseDelete']);
        $this->middleware(['permission:manage_purchase_returns'])->only(['purchaseReturnAdd', 'storePurchaseReturn', 'purchaseReturnEdit', 'updatePurchaseReturn']);
    }

    use StatementLogTrait;

    /**
     * Get the last purchase price and quantity for a product (with optional variant)
     */
    public function getLastPurchasePrice(Request $request)
    {
        try {
            $productId = $request->product_id;
            $variant = $request->variant;

            // Query the most recent purchase for this product and variant
            $lastPurchase = PurchaseProduct::where('product_id', $productId)
                ->when($variant, function ($query) use ($variant) {
                    return $query->where('variant', $variant);
                }, function ($query) {
                    return $query->whereNull('variant');
                })
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastPurchase) {
                return response()->json([
                    'success' => true,
                    'last_price' => $lastPurchase->buying_unit_cost,
                    'last_quantity' => $lastPurchase->purchase_quantity,
                    'purchase_date' => $lastPurchase->created_at
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No previous purchase found'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching last purchase price: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data'
            ]);
        }
    }


    public function getPaymentAccounts(Request $request)
    {
        $type = $request->type;
        $subTypeMap = [
            'cash'    => 'cash',
            'bank'    => 'bank',
            'mbanking' => 'mobile_bank',
        ];

        $subType = $subTypeMap[$type] ?? null;
        if (!$subType) {
            return response()->json([]);
        }

        $accounts = ChartOfAccount::where('type', 'asset')
            ->where('sub_type', $subType)
            ->where('status', 'active')
            ->select('id', 'name', 'current_balance')
            ->get();

        return response()->json($accounts);
    }
    // purchase List 
    public function purchaseIndex()
    {
        $purchase = Purchase::all();
        return view('backend.purchase.index', compact('purchase'));
    }
    // purchase create method
    public function create()
    {
        $suppliers = Supplier::all();

        return view('backend.purchase.create', compact('suppliers'));
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
    public function purchaseEdit($id)
    {
        $suppliers = Supplier::all();
        $purchase = Purchase::find($id);

        // Make sure to get the products based on the purchase ID
        $purchaseProducts = PurchaseProduct::where('purchase_id', $purchase->id)->get();

        // Pass the purchaseProducts to the view as well
        return view('backend.purchase.edit', compact('purchase', 'suppliers', 'purchaseProducts'));
    }

    //    purchase view method 
    public function purchaseView($id)
    {
        $purchase = Purchase::find($id);
        if ($purchase) {
            $supplier = Supplier::lockForUpdate()->where('id', $purchase->supplier_id)->first();
        }
        $suppliers = Supplier::all();

        // Make sure to get the products based on the purchase ID
        $purchaseProducts = PurchaseProduct::where('purchase_id', $purchase->id)->get();
        return view('backend.purchase.view', compact('purchase', 'suppliers', 'supplier', 'purchaseProducts'));
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
                if ($product->variant_product == 1) {
                    // Fetch all variants from the product_stocks table
                    $variants = ProductStock::where('product_id', $product->id)->get();
                    return response()->json([
                        'data' => $product,
                        'variants' => $variants
                    ]);
                } else {
                    // Return the product without variants
                    return response()->json(['data' => $product]);
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
            $purchase = Purchase::findOrFail($request->purchase_id);
            $this->refuseLedgerPurchase((int) $purchase->id);

            // Fetch the supplier record
            $supplier = Supplier::lockForUpdate()->findOrFail($request->supplier_id);

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
                            DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
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

   
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // Base validation
            $rules = [
                'supplier_id'          => 'required|exists:suppliers,id',
                'purchase_date'        => 'required|date',
                'business_location'    => 'nullable|string|max:255',
                'pay_term'             => 'nullable|integer',
                'pay_term_type'        => 'nullable|string',
                'product_id'           => 'required|array',
                'product_id.*'         => 'required|exists:products,id',
                'variant.*'            => 'nullable|string',
                'purchase_quantity.*'  => 'required|numeric|min:0.01',
                'buying_unit_cost.*'   => 'required|numeric|min:0',
                'total_cost.*'         => 'required|numeric|min:0',
                'pay_by'               => 'nullable|in:cash,bank,mbanking,cheque',
                'payment_note'         => 'nullable|string',
                'document'             => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:5120',
            ];

            if ($request->filled('pay_by')) {
                $rules['payment_account_id'] = 'required|exists:chart_of_accounts,id';
                $rules['paid_amount'] = 'required|numeric|min:0';
            } else {
                $request->merge(['paid_amount' => 0, 'payment_account_id' => null]);
            }

            $request->validate($rules);

            // A double click / retry of the same form carries the same op_key and must not create a second purchase.
            // The key row lives in this transaction, so a failed save releases it and the user can resubmit.
            if ($request->filled('op_key') && \App\Services\Inventory\InventoryMode::installed()) {
                try {
                    DB::table('idempotency_keys')->insert(['scope' => 'legacy_purchase', 'key' => substr((string) $request->op_key, 0, 120), 'created_at' => now(), 'updated_at' => now()]);
                } catch (\Illuminate\Database\QueryException $dup) {
                    DB::rollBack();

                    return response()->json(['success' => true, 'duplicate' => true, 'message' => 'This purchase was already saved.', 'redirect_url' => route('purchase.index')]);
                }
            }

            // Calculate totals
            $netTotal = 0;
            foreach ($request->total_cost as $cost) {
                $netTotal += $cost;
            }
            $paidAmount = $request->paid_amount ?? 0;
            $dueBeforeOpening = $netTotal - $paidAmount;

            if ($paidAmount > $netTotal) {
                throw new \Exception('Paid amount cannot exceed net total.');
            }

            // Supplier & opening balance offset
            $supplier = Supplier::lockForUpdate()->find($request->supplier_id);
            $openingBalance = $supplier->opening_balance ?? 0;
            $openingUsed = min($openingBalance, $dueBeforeOpening);
            $finalDue = $dueBeforeOpening - $openingUsed;

            $supplier->opening_balance -= $openingUsed;
            $supplier->due_amount += $finalDue;
            $supplier->save();

            // Document upload
            $documentPath = null;
            if ($request->hasFile('document')) {
                // once per request: a deadlock retry must not store the upload again; removed if the request finally fails
                $documentPath = \App\Support\RequestSideEffects::once($request, 'purchase_document', fn () => $request->file('document')->store('purchases', 'public'), fn ($path) => Storage::disk('public')->delete($path));
            }

            // Reference number
            $lastRef = Purchase::lockForUpdate()
                ->where('reference_no', 'like', 'Ref-%')
                ->orderBy('id', 'desc')
                ->value('reference_no');
            $nextNumber = $lastRef ? ((int)str_replace('Ref-', '', $lastRef) + 1) : 1000;
            $referenceNo = 'Ref-' . $nextNumber;

            // Create Purchase record
            $purchase = Purchase::create([
                'supplier_id'          => $request->supplier_id,
                'reference_no'         => $referenceNo,
                'purchase_date'        => $request->purchase_date,
                'business_location'    => $request->business_location ?? '',
                'pay_term'             => $request->pay_term,
                'pay_term_type'        => $request->pay_term_type,
                'document'             => $documentPath,
                // 'total_item'           => count($request->product_id),
                'total_item' => array_sum($request->purchase_quantity),
                'net_total_amount'     => $netTotal,
                'total_paid'           => $paidAmount,
                'total_payment_due'    => $finalDue,
                'payment_account_id'   => $paidAmount > 0 ? $request->payment_account_id : null,
                'payment_method_type'  => $request->pay_by,
                'payment_note'         => $request->payment_note,
                'opening_balance_used' => $openingUsed,
            ]);

            // Store purchase products & update stock (using variant array)
            foreach ($request->product_id as $index => $productId) {
                $qty = $request->purchase_quantity[$index];
                $unitCost = $request->buying_unit_cost[$index];
                $totalCost = $request->total_cost[$index];
                $productName = $request->name[$index];
                $variant = $request->variant[$index] ?? null;

                PurchaseProduct::create([
                    'purchase_id'       => $purchase->id,
                    'product_id'        => $productId,
                    'product_name'      => $productName,
                    'variant'           => $variant,
                    'purchase_quantity' => $qty,
                    'remaining_qty'     => $qty,
                    'buying_unit_cost'  => $unitCost,
                    'total_cost'        => $totalCost,
                    'product_tax'       => 0,
                ]);

                $this->updateProductStockVariant($productId, $variant, $qty);
            }

            // ---- Accounting Entries ----
            // 1. Accounts Payable (credit)
            $payableAccount = null;
            if ($finalDue > 0) {
                $payableAccount = ChartOfAccount::getAccountsPayable();
                if (!$payableAccount) throw new \Exception('Accounts Payable not found');
                $payableAccount->current_balance += $finalDue;
                $payableAccount->save();
            }

            // 2. Opening Supplier Balance (credit)
            $openingBalAccount = null;

            $inventoryAccount = ChartOfAccount::getInventoryAccount();
            if (!$inventoryAccount) throw new \Exception('Opening Supplier Balance account not found');
            $inventoryAccount->current_balance += $netTotal ?? 0;
            $inventoryAccount->save();

            if ($openingUsed > 0) {
                $openingBalAccount = ChartOfAccount::getOpeningSupplierBalanceAccount();
                if (!$openingBalAccount) throw new \Exception('Opening Supplier Balance account not found');
                $openingBalAccount->current_balance -= $openingUsed;
                $openingBalAccount->save();
            }

            // 3. Payment account (credit)
            if ($paidAmount > 0) {
                $paymentAccount = ChartOfAccount::lockForUpdate()->find($request->payment_account_id);
                if (!$paymentAccount) throw new \Exception('Payment account not found');
                if ($paymentAccount->current_balance < $paidAmount) {
                    throw new \Exception('Insufficient balance in selected account');
                }
                $paymentAccount->current_balance -= $paidAmount;
                $paymentAccount->save();
            }

            // ---- STATEMENT LOGS ----
            $logDate = $request->purchase_date;
            $refNo   = $purchase->reference_no;

            if ($finalDue > 0 && $payableAccount) {
                $this->insertStatementLog(
                    $payableAccount->id,
                    $logDate,
                    $refNo,
                    'purchase',
                    0,          // debit
                    $finalDue,  // credit
                    'Purchase: ' . $refNo,
                    $purchase
                );
            }
            if ($openingUsed > 0 && $openingBalAccount) {
                $this->insertStatementLog(
                    $openingBalAccount->id,
                    $logDate,
                    $refNo,
                    'purchase_opening_used',
                    0,
                    $openingUsed,
                    'Opening balance used for ' . $refNo,
                    $purchase
                );
            }
            if ($paidAmount > 0 && $request->payment_account_id) {
                $this->insertStatementLog(
                    $request->payment_account_id,
                    $logDate,
                    $refNo,
                    'purchase_payment',
                    0,
                    $paidAmount,
                    'Payment for ' . $refNo,
                    $purchase
                );
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Purchase stored successfully',
                'redirect_url' => route('purchase.index')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Purchase store error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }



    public function update(Request $request, $purchaseId)
    {
        DB::beginTransaction();
        try {
            $purchase = Purchase::lockForUpdate()->findOrFail($purchaseId); // document first: re-read under its lock (a repeated/simultaneous edit waits and sees the committed state)
            $this->refuseLedgerPurchase((int) $purchase->id);
            $oldSupplierId = $purchase->supplier_id;

            // Validation
            $rules = [
                'supplier_id'          => 'required|exists:suppliers,id',
                'purchase_date'        => 'required|date',
                'business_location'    => 'nullable|string|max:255',
                'pay_term'             => 'nullable|integer',
                'pay_term_type'        => 'nullable|string',
                'product_id'           => 'required|array',
                'product_id.*'         => 'required|exists:products,id',
                'variant.*'            => 'nullable|string',
                'purchase_quantity.*'  => 'required|numeric|min:0.01',
                'buying_unit_cost.*'   => 'required|numeric|min:0',
                'total_cost.*'         => 'required|numeric|min:0',
                'pay_by'               => 'nullable|in:cash,bank,mbanking,cheque',
                'payment_note'         => 'nullable|string',
                'document'             => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:5120',
            ];

            if ($request->filled('pay_by')) {
                $rules['payment_account_id'] = 'required|exists:chart_of_accounts,id';
                $rules['paid_amount'] = 'required|numeric|min:0';
            } else {
                $request->merge(['paid_amount' => 0, 'payment_account_id' => null]);
            }
            $request->validate($rules);

            // Store old values for reversal
            $oldNetTotal = $purchase->net_total_amount;
            $oldPaid = $purchase->total_paid;
            $oldDue = $purchase->total_payment_due;
            $oldOpeningUsed = $purchase->opening_balance_used ?? 0;
            $oldPaymentAccountId = $purchase->payment_account_id;
            $oldPurchaseDate = $purchase->purchase_date;

            // Calculate new totals
            $netTotal = 0;
            foreach ($request->total_cost as $cost) {
                $netTotal += $cost;
            }
            $paidAmount = $request->paid_amount ?? 0;
            $dueBeforeOpening = $netTotal - $paidAmount;
            if ($paidAmount > $netTotal) {
                throw new \Exception('Paid amount cannot exceed net total.');
            }

            // Supplier and opening balance offset
            $supplier = Supplier::lockForUpdate()->find($request->supplier_id);
            $openingBalance = $supplier->opening_balance ?? 0;
            $openingUsed = min($openingBalance, $dueBeforeOpening);
            $finalDue = $dueBeforeOpening - $openingUsed;

            // --- Revert old supplier balances ---
            if ($oldSupplierId != $request->supplier_id) {
                $oldSupplier = Supplier::lockForUpdate()->find($oldSupplierId);
                if ($oldSupplier) {
                    $oldSupplier->due_amount -= $oldDue;
                    $oldSupplier->opening_balance += $oldOpeningUsed;
                    $oldSupplier->save();
                }
            } else {
                $supplier->due_amount -= $oldDue;
                $supplier->opening_balance += $oldOpeningUsed;
            }

            // Apply new supplier balances
            $supplier->opening_balance -= $openingUsed;
            $supplier->due_amount += $finalDue;
            $supplier->save();

            // Document handling
            if ($request->hasFile('document')) {
                $oldDocument = $purchase->document;
                $documentPath = \App\Support\RequestSideEffects::once($request, 'purchase_document', fn () => $request->file('document')->store('purchases', 'public'), fn ($path) => Storage::disk('public')->delete($path));
                $purchase->document = $documentPath;
                // the previous file goes only after the new state is committed (a rolled-back update keeps its document)
                if ($oldDocument) {
                    DB::afterCommit(function () use ($oldDocument) {
                        if (Storage::exists('public/' . $oldDocument)) {
                            Storage::delete('public/' . $oldDocument);
                        }
                    });
                }
            }

            // Update purchase record
            $purchase->supplier_id = $request->supplier_id;
            $purchase->purchase_date = $request->purchase_date;
            $purchase->business_location = $request->business_location ?? '';
            $purchase->pay_term = $request->pay_term;
            $purchase->pay_term_type = $request->pay_term_type;
            $purchase->payment_note = $request->payment_note;
            // $purchase->total_item = count($request->product_id);
            $purchase->total_item = array_sum($request->purchase_quantity);
            $purchase->net_total_amount = $netTotal;
            $purchase->total_paid = $paidAmount;
            $purchase->total_payment_due = $finalDue;
            $purchase->payment_account_id = $paidAmount > 0 ? $request->payment_account_id : null;
            $purchase->payment_method_type = $request->pay_by;
            $purchase->opening_balance_used = $openingUsed;
            $purchase->save();

            // Process products
            $processedKeys = [];
            foreach ($request->product_id as $index => $productId) {
                $newQty = $request->purchase_quantity[$index];
                $unitCost = $request->buying_unit_cost[$index];
                $totalCost = $request->total_cost[$index];
                $productName = $request->name[$index];
                $variant = $request->variant[$index] ?? null;

                $productKey = $productId . '|' . $variant;
                $processedKeys[] = $productKey;

                $purchaseProduct = PurchaseProduct::where('purchase_id', $purchaseId)
                    ->where('product_id', $productId)
                    ->where('variant', $variant)
                    ->first();

                if ($purchaseProduct) {
                    $oldQty = $purchaseProduct->purchase_quantity;
                    $soldQty = $oldQty - $purchaseProduct->remaining_qty;
                    if ($newQty < $soldQty) {
                        throw new \Exception("Cannot reduce quantity below sold amount for {$productName}. Sold: {$soldQty}");
                    }
                    $qtyDiff = $newQty - $oldQty;

                    // One statement: purchase_quantity and remaining_qty move by the same difference, so a sale
                    // made since this page loaded is kept, and "below what was sold" is checked by the database.
                    $resized = app(StockLedger::class)->resizeBatch((int) $purchaseProduct->id, (int) $newQty - (int) $oldQty, [
                        'buying_unit_cost' => $unitCost,
                        'total_cost'       => $totalCost,
                        'product_name'     => $productName,
                        'variant'          => $variant,
                    ]);
                    if (!$resized) {
                        throw new \Exception("Cannot reduce quantity below sold amount for {$productName}. Sold: {$soldQty}");
                    }

                    if ($qtyDiff != 0) {
                        $this->updateProductStockVariant($productId, $variant, $qtyDiff);
                    }
                } else {
                    $purchaseProduct = PurchaseProduct::create([
                        'purchase_id'       => $purchaseId,
                        'product_id'        => $productId,
                        'product_name'      => $productName,
                        'variant'           => $variant,
                        'purchase_quantity' => $newQty,
                        'remaining_qty'     => $newQty,
                        'buying_unit_cost'  => $unitCost,
                        'total_cost'        => $totalCost,
                        'product_tax'       => 0,
                    ]);
                    $this->updateProductStockVariant($productId, $variant, $newQty);
                }
            }

            // Remove deleted products
            $existingProducts = PurchaseProduct::where('purchase_id', $purchaseId)->get();
            foreach ($existingProducts as $existing) {
                $key = $existing->product_id . '|' . $existing->variant;
                if (!in_array($key, $processedKeys)) {
                    $soldQty = $existing->purchase_quantity - $existing->remaining_qty;
                    if ($soldQty > 0) {
                        throw new \Exception("Cannot remove product {$existing->product_name} – already sold {$soldQty} units.");
                    }
                    $this->updateProductStockVariant($existing->product_id, $existing->variant, -$existing->purchase_quantity);
                    $existing->delete();
                }
            }

            // ---- ACCOUNTING ENTRIES (REVERT OLD, APPLY NEW) ----
            // 1. Accounts Payable
            $payableAccount = ChartOfAccount::getAccountsPayable();
            if ($payableAccount) {
                if ($oldDue > 0) {
                    $payableAccount->current_balance -= $oldDue;
                }
                if ($finalDue > 0) {
                    $payableAccount->current_balance += $finalDue;
                }
                $payableAccount->save();
            }

            // 2. Opening Supplier Balance account
            $openingBalAccount = ChartOfAccount::getOpeningSupplierBalanceAccount();
            if ($openingBalAccount) {
                if ($oldOpeningUsed > 0) {
                    $openingBalAccount->current_balance += $oldOpeningUsed;
                }
                if ($openingUsed > 0) {
                    $openingBalAccount->current_balance -= $openingUsed;
                }
                $openingBalAccount->save();
            }

            // 3. Payment account
            if ($oldPaid > 0 && $oldPaymentAccountId) {
                $oldPaymentAcc = ChartOfAccount::find($oldPaymentAccountId);
                if ($oldPaymentAcc) {
                    $oldPaymentAcc->current_balance += $oldPaid;
                    $oldPaymentAcc->save();
                }
            }
            if ($paidAmount > 0 && $request->payment_account_id) {
                $paymentAccount = ChartOfAccount::lockForUpdate()->find($request->payment_account_id);
                if (!$paymentAccount) {
                    throw new \Exception('Selected payment account not found');
                }
                if ($paymentAccount->current_balance < $paidAmount) {
                    throw new \Exception('Insufficient balance in selected account');
                }
                $paymentAccount->current_balance -= $paidAmount;
                $paymentAccount->save();
            }

            // ---- STATEMENT LOGS (REVERSAL + NEW) ----
            // Reversal logs use the ORIGINAL purchase date so period reports net to zero
            $reversalDate = $oldPurchaseDate ? date('Y-m-d', strtotime($oldPurchaseDate)) : now()->toDateString();
            $reversalRef  = $purchase->reference_no . '-REV';
            $logDate      = $request->purchase_date;
            $refNo        = $purchase->reference_no;

            // Reversal logs
            if ($oldDue > 0 && $payableAccount) {
                $this->insertStatementLog(
                    $payableAccount->id,
                    $reversalDate,
                    $reversalRef,
                    'purchase_reversal',
                    $oldDue,
                    0,
                    'Reversal for edit of ' . $purchase->reference_no,
                    $purchase
                );
            }
            if ($oldOpeningUsed > 0 && $openingBalAccount) {
                $this->insertStatementLog(
                    $openingBalAccount->id,
                    $reversalDate,
                    $reversalRef,
                    'purchase_opening_reversal',
                    $oldOpeningUsed,
                    0,
                    'Reversal for edit of ' . $purchase->reference_no,
                    $purchase
                );
            }
            if ($oldPaid > 0 && $oldPaymentAccountId) {
                $this->insertStatementLog(
                    $oldPaymentAccountId,
                    $reversalDate,
                    $reversalRef,
                    'purchase_payment_reversal',
                    $oldPaid,
                    0,
                    'Reversal for edit of ' . $purchase->reference_no,
                    $purchase
                );
            }

            // New logs
            if ($finalDue > 0 && $payableAccount) {
                $this->insertStatementLog(
                    $payableAccount->id,
                    $logDate,
                    $refNo,
                    'purchase',
                    0,
                    $finalDue,
                    'Purchase: ' . $refNo,
                    $purchase
                );
            }
            if ($openingUsed > 0 && $openingBalAccount) {
                $this->insertStatementLog(
                    $openingBalAccount->id,
                    $logDate,
                    $refNo,
                    'purchase_opening_used',
                    0,
                    $openingUsed,
                    'Opening balance used for ' . $refNo,
                    $purchase
                );
            }
            if ($paidAmount > 0 && $request->payment_account_id) {
                $this->insertStatementLog(
                    $request->payment_account_id,
                    $logDate,
                    $refNo,
                    'purchase_payment',
                    0,
                    $paidAmount,
                    'Payment for ' . $refNo,
                    $purchase
                );
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Purchase updated successfully',
                'redirect_url' => route('purchase.index')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase update error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }



    /**
     * Purchases created by goods receiving (and opening-cost layers) are owned by the receipt / supplier-invoice ledger and carry journal
     * entries. Editing, deleting or returning them through the legacy screens would change stock without reversing those entries, so it
     * is refused; the Purchasing screens (supplier returns, invoices, payments) are the way to change them.
     */
    protected function refuseLedgerPurchase(int $purchaseId): void
    {
        if (!\App\Services\Inventory\InventoryMode::installed()) {
            return;
        }
        $ledger = DB::table('goods_receipts')->where('purchase_id', $purchaseId)->exists()
            || DB::table('purchases')->where('id', $purchaseId)->where('reference_no', 'like', 'OPEN-%')->exists();
        if ($ledger) {
            throw new \Exception('This purchase was created by Goods Receiving. Use Purchasing > Goods Receiving / Supplier Payments / Return to supplier to change it.');
        }
    }

    protected function updateProductStockVariant($productId, $variant, $qtyDifference)
    {
        $qtyDifference = (int) $qtyDifference;
        if ($qtyDifference == 0) {
            return;
        }

        $product = Product::find($productId);
        if (!$product) {
            return;
        }

        // Which stock row: the product's variation, or (plain product) its only row.
        if ($product->variant_product == 1 && !empty($variant)) {
            $productStock = ProductStock::where('product_id', $productId)->where('variant', $variant)->orderBy('id')->first();
            $newVariant = $variant;
        } else {
            $productStock = ProductStock::where('product_id', $productId)->where('variant', '')->orderBy('id')->first()
                ?: ProductStock::where('product_id', $productId)->orderBy('id')->first();
            $newVariant = '';
        }

        if ($productStock) {
            // One statement ("qty = qty + n"): a sale made since the row was read is never erased,
            // and a removal can never take the shelf below zero.
            if (!app(StockLedger::class)->adjustRow((int) $productStock->id, $qtyDifference)) {
                throw new \Exception('Not enough stock on the shelf to remove ' . abs($qtyDifference) . ' unit(s) of ' . $product->name . ($productStock->variant !== '' ? ' (' . $productStock->variant . ')' : '') . '. Some of it has been sold.');
            }
        } elseif ($qtyDifference > 0) {
            ProductStock::create([
                'product_id' => $productId,
                'variant'    => $newVariant,
                'qty'        => $qtyDifference,
            ]);
        }

        // Always update the main product's total current stock (atomic, like the shelf).
        DB::table('products')->where('id', $productId)->increment('current_stock', $qtyDifference);
    }


    // Purchase Return Part 
    /**
     * Goods go back to the supplier: the batch and the shelf both lose the units, each in one
     * statement. False (nothing changed) when the batch holds fewer units than that.
     */
    protected function returnToSupplier($purchaseProduct, $productId, $variant, int $quantity, array $set = []): bool
    {
        if ($quantity < 1) {
            return true;
        }
        // The shelf first: if it refuses, the whole request rolls back. The batch table may not roll back
        // (MyISAM), so it is written last, once everything that can fail has not.
        $this->shelfMove($productId, $variant, -$quantity);
        if (!app(StockLedger::class)->resizeBatch((int) $purchaseProduct->id, -$quantity, $set)) {
            // The caller rolls its transaction back on false, which also undoes the shelf move above.
            return false;
        }
        DB::update('UPDATE purchase_products SET total_cost = CAST(CAST(purchase_quantity AS SIGNED) * CAST(buying_unit_cost AS DECIMAL(20,4)) AS CHAR) WHERE id = ?', [$purchaseProduct->id]);

        return true;
    }

    /** A return to the supplier is undone: the units are on the shelf and in the batch again. */
    protected function takeBackFromSupplier($purchaseProduct, $productId, $variant, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }
        $this->shelfMove($productId, $variant, $quantity);
        if ($purchaseProduct) {
            app(StockLedger::class)->resizeBatch((int) $purchaseProduct->id, $quantity);
            DB::update('UPDATE purchase_products SET total_cost = CAST(CAST(purchase_quantity AS SIGNED) * CAST(buying_unit_cost AS DECIMAL(20,4)) AS CHAR) WHERE id = ?', [$purchaseProduct->id]);
        }
    }

    /** Moves a product's shelf (and its current_stock total) by $delta; blank variant means its only row. */
    protected function shelfMove($productId, $variant, int $delta): void
    {
        $this->updateProductStockVariant($productId, $variant, $delta);
    }

    public function purchaseReturnList()
    {
        $purchaseReturns = PurchaseReturn::with('productReturn')->get();
        return view('backend.purchase.return_list', compact('purchaseReturns'));
    }

    // Purchase return add 

    public function purchaseReturnAdd()
    {
        $suppliers = Supplier::all();
        return view('backend.purchase.return_add', compact('suppliers'));
    }


    // public function searchProducts(Request $request)
    // {
    //     try {
    //         $searchKeyword = $request->input('search');
    //         $supplierId = $request->input('supplier_id');

    //         // Get all purchase IDs for this supplier
    //         $purchaseIds = Purchase::where('supplier_id', $supplierId)->pluck('id');
    //         if ($purchaseIds->isEmpty()) {
    //             return response()->json(['products' => []]);
    //         }

    //         // Get purchase products with remaining quantity > 0, matching the search keyword in product name
    //         $purchaseProducts = PurchaseProduct::whereIn('purchase_id', $purchaseIds)
    //             ->where('remaining_qty', '>', 0)
    //             ->whereHas('product', function ($query) use ($searchKeyword) {
    //                 $query->where('name', 'like', "%{$searchKeyword}%");
    //             })
    //             ->with('product')
    //             ->orderBy('id', 'asc')
    //             ->get();

    //         $products = [];

    //         foreach ($purchaseProducts as $pp) {
    //             // Use the variant column directly (already stored)
    //             $variantName = $pp->variant ?? '';
    //             $productName = $pp->product->name;

    //             // Build display name: product name + variant if present
    //             $displayName = $productName;
    //             if (!empty($variantName)) {
    //                 $displayName .= ' - ' . $variantName;
    //             }

    //             $products[] = [
    //                 'id' => $pp->product_id,
    //                 'name' => $displayName,
    //                 'variant_name' => $variantName,
    //                 'purchase_id' => $pp->purchase_id,
    //                 'purchase_product_id' => $pp->id,   // unique per purchase product
    //                 'remaining_qty' => (float) $pp->remaining_qty,
    //                 'unit_cost' => (float) $pp->buying_unit_cost,
    //                 'is_variant' => !empty($variantName),
    //             ];
    //         }

    //         return response()->json(['products' => $products]);
    //     } catch (\Exception $e) {
    //         \Log::error('Error in searchProducts: ' . $e->getMessage());
    //         return response()->json(['products' => [], 'error' => $e->getMessage()], 500);
    //     }
    // }

public function searchProducts(Request $request)
{
    try {
        $searchKeyword = $request->input('search');
        $supplierId = $request->input('supplier_id');

        // Get all purchase IDs for this supplier
        $purchaseIds = Purchase::where('supplier_id', $supplierId)->pluck('id');
        if ($purchaseIds->isEmpty()) {
            return response()->json(['products' => []]);
        }

        // Search products by ID (if numeric) or by name
        $productQuery = Product::query();
        
        if ($searchKeyword) {
            if (is_numeric($searchKeyword)) {
                $productQuery->where('id', $searchKeyword);
            } else {
                $productQuery->where('name', 'LIKE', "%{$searchKeyword}%");
            }
        }

        $products = $productQuery->get();
        $result = [];

        foreach ($products as $product) {
            // Get purchase products for this product from the supplier's purchases
            $purchaseProducts = PurchaseProduct::whereIn('purchase_id', $purchaseIds)
                ->where('product_id', $product->id)
                ->where('remaining_qty', '>', 0)
                ->get();

            if ($purchaseProducts->isEmpty()) {
                continue; // Skip products with no remaining quantity
            }

            // Check if this is a variant product
            if ($product->variant_product == 1) {
                // Get variants from product_stocks that have remaining quantity in purchases
                $variantStocks = ProductStock::where('product_id', $product->id)->get();
                
                foreach ($variantStocks as $variant) {
                    // Find purchase products for this specific variant
                    $variantPurchases = $purchaseProducts->filter(function ($pp) use ($variant) {
                        return $pp->variant === $variant->variant;
                    });
                    
                    if ($variantPurchases->isNotEmpty()) {
                        $totalRemainingQty = $variantPurchases->sum('remaining_qty');
                        $latestPurchase = $variantPurchases->sortByDesc('id')->first();
                        
                        $result[] = [
                            'id' => $product->id,
                            'name' => $product->name . ' - ' . $variant->variant,
                            'product_name' => $product->name,
                            'variant_name' => $variant->variant,
                            'purchase_id' => $latestPurchase->purchase_id,
                            'purchase_product_id' => $latestPurchase->id,
                            'remaining_qty' => (float) $totalRemainingQty,
                            'unit_cost' => (float) $latestPurchase->buying_unit_cost,
                            'is_variant' => true,
                        ];
                    }
                }
            } else {
                // Simple product (no variants)
                $totalRemainingQty = $purchaseProducts->sum('remaining_qty');
                $latestPurchase = $purchaseProducts->sortByDesc('id')->first();
                
                $result[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'product_name' => $product->name,
                    'variant_name' => '',
                    'purchase_id' => $latestPurchase->purchase_id,
                    'purchase_product_id' => $latestPurchase->id,
                    'remaining_qty' => (float) $totalRemainingQty,
                    'unit_cost' => (float) $latestPurchase->buying_unit_cost,
                    'is_variant' => false,
                ];
            }
        }

        return response()->json(['products' => $result]);
    } catch (\Exception $e) {
        \Log::error('Error in searchProducts: ' . $e->getMessage());
        return response()->json(['products' => [], 'error' => $e->getMessage()], 500);
    }
}


    // public function storePurchaseReturn(Request $request)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $validated = $request->validate([
    //             'supplier_id' => 'required|exists:suppliers,id',
    //             'transaction_date' => 'required|date',
    //             'product_id' => 'required|array',
    //             'product_id.*' => 'required|exists:products,id',
    //             'product_quantity' => 'required|array',
    //             'product_quantity.*' => 'required|numeric|min:1',
    //             'product_unit_cost' => 'required|array',
    //             'product_unit_cost.*' => 'required|numeric|min:0',
    //             'purchase_id' => 'required|array',
    //             'purchase_id.*' => 'required|exists:purchases,id',
    //             'purchase_product_id' => 'required|array',
    //             'purchase_product_id.*' => 'required|exists:purchase_products,id',
    //             'variant_name' => 'nullable|array',
    //         ]);

    //         $totalReturnAmount = 0;
    //         foreach ($request->product_subtotal as $subtotal) {
    //             $totalReturnAmount += floatval($subtotal);
    //         }

    //         $documentPath = null;
    //         if ($request->hasFile('document')) {
    //             $documentPath = $request->file('document')->store('purchase_return_documents', 'public');
    //         }

    //         $purchaseId = $request->purchase_id[0];
    //         $purchase = Purchase::findOrFail($purchaseId);
    //         $supplier = Supplier::findOrFail($request->supplier_id);

    //         $purchaseReturn = PurchaseReturn::create([
    //             'purchase_id' => $purchaseId,
    //             'supplier_id' => $request->supplier_id,
    //             'reference_no' => $request->reference_no ?? 'PR-' . time(),
    //             'transaction_date' => $request->transaction_date,
    //             'document' => $documentPath,
    //         ]);

    //         $logDate = $request->transaction_date;
    //         $refNo   = $purchaseReturn->reference_no;

    //         foreach ($request->product_id as $index => $productId) {
    //             $returnQuantity = floatval($request->product_quantity[$index]);
    //             $unitCost = floatval($request->product_unit_cost[$index]);
    //             $totalCost = floatval($request->product_subtotal[$index]);
    //             $productName = $request->product_name[$index];
    //             $purchaseProductId = $request->purchase_product_id[$index];
    //             $variantName = $request->variant_name[$index] ?? null;

    //             PurchaseReturnProduct::create([
    //                 'purchase_return_id' => $purchaseReturn->id,
    //                 'product_id' => $productId,
    //                 'product_name' => $productName,
    //                 'variant' => $variantName,
    //                 'purchase_quantity' => $returnQuantity,
    //                 'buying_unit_cost' => $unitCost,
    //                 'total_cost' => $totalCost,
    //             ]);

    //             $purchaseProduct = PurchaseProduct::findOrFail($purchaseProductId);
    //             if ($returnQuantity > $purchaseProduct->remaining_qty) {
    //                 throw new \Exception("Return quantity for {$productName} exceeds available ({$purchaseProduct->remaining_qty})");
    //             }
    //             $purchaseProduct->remaining_qty -= $returnQuantity;
    //             $purchaseProduct->purchase_quantity -= $returnQuantity;
    //             $purchaseProduct->total_cost = $purchaseProduct->purchase_quantity * $purchaseProduct->buying_unit_cost;
    //             $purchaseProduct->save();

    //             $product = Product::find($productId);
    //             if ($product) {
    //                 $product->current_stock -= $returnQuantity;
    //                 $product->save();
    //             }

    //             if (!empty($variantName)) {
    //                 $productStock = ProductStock::where('product_id', $productId)
    //                     ->where('variant', $variantName)
    //                     ->first();
    //                 if ($productStock) {
    //                     $productStock->qty -= $returnQuantity;
    //                     $productStock->save();
    //                 }
    //             } else {
    //                 $productStock = ProductStock::where('product_id', $productId)->first();
    //                 if ($productStock) {
    //                     $productStock->qty -= $returnQuantity;
    //                     $productStock->save();
    //                 }
    //             }
    //         }

    //         // Store old values
    //         $oldNetTotal = $purchase->net_total_amount;
    //         $oldPaid = $purchase->total_paid;

    //         // Recalculate this purchase totals
    //         $newNetTotal = PurchaseProduct::where('purchase_id', $purchaseId)->sum('total_cost');
    //         $newTotalItem = PurchaseProduct::where('purchase_id', $purchaseId)->sum('purchase_quantity');
    //         $actualReduction = $oldNetTotal - $newNetTotal;

    //         $purchase->net_total_amount = $newNetTotal;
    //         $purchase->total_item = $newTotalItem;

    //         // Calculate new due for this specific purchase
    //         $newDue = $newNetTotal - $oldPaid;
    //         if ($newDue < 0) {
    //             $newDue = 0;
    //         }
    //         $purchase->total_payment_due = $newDue;
    //         $purchase->save();

    //         // ---- DISTRIBUTE RETURN CREDIT TO OTHER PURCHASES ----
    //         // The return reduces supplier's overall liability by $actualReduction
    //         $remainingCredit = $actualReduction;

    //         // First, apply credit to the current purchase's own due (if any)
    //         // (This is already handled above by recalculating total_payment_due)

    //         // Get all other purchases of this supplier that have outstanding dues
    //         // Order by oldest first (FIFO)
    //         $otherDuePurchases = Purchase::where('supplier_id', $supplier->id)
    //             ->where('id', '!=', $purchaseId)
    //             ->where('total_payment_due', '>', 0)
    //             ->orderBy('purchase_date', 'asc')
    //             ->orderBy('id', 'asc')
    //             ->get();

    //         // Distribute the credit to other purchases with dues
    //         foreach ($otherDuePurchases as $otherPurchase) {
    //             if ($remainingCredit <= 0) break;

    //             $reduceAmount = min($remainingCredit, $otherPurchase->total_payment_due);

    //             // Reduce this purchase's due
    //             $otherPurchase->total_payment_due -= $reduceAmount;
    //             $otherPurchase->save();

    //             $remainingCredit -= $reduceAmount;
    //         }

    //         // Calculate total AP reduction and supplier due adjustment
    //         $totalDueReduction = $actualReduction - $remainingCredit; // How much actual dues were reduced
    //         $apReduction = $totalDueReduction;

    //         // 1. Reduce Accounts Payable
    //         $payableAccount = ChartOfAccount::getAccountsPayable();
    //         if ($payableAccount && $apReduction > 0) {
    //             $payableAccount->current_balance -= $apReduction;
    //             $payableAccount->save();

    //             // Log AP reduction
    //             $this->insertStatementLog(
    //                 $payableAccount->id,
    //                 $logDate,
    //                 $refNo,
    //                 'purchase_return',
    //                 $apReduction,   // debit (reduce liability)
    //                 0,
    //                 'Purchase return: ' . $refNo,
    //                 $purchaseReturn
    //             );
    //         }

    //         // 2. Update supplier due amount
    //         $supplier->due_amount -= $totalDueReduction;

    //         // 3. Handle any remaining credit (goes to opening balance)
    //         if ($remainingCredit > 0) {
    //             $supplier->opening_balance += $remainingCredit;

    //             $openingBalAccount = ChartOfAccount::getOpeningSupplierBalanceAccount();
    //             if ($openingBalAccount) {
    //                 $openingBalAccount->current_balance += $remainingCredit;
    //                 $openingBalAccount->save();

    //                 $this->insertStatementLog(
    //                     $openingBalAccount->id,
    //                     $logDate,
    //                     $refNo,
    //                     'purchase_return_credit',
    //                     0,
    //                     $remainingCredit,   // credit (supplier credit)
    //                     'Supplier credit from return: ' . $refNo,
    //                     $purchaseReturn
    //                 );
    //             }
    //         }

    //         // Ensure supplier due doesn't go negative
    //         if ($supplier->due_amount < 0) {
    //             $supplier->opening_balance += abs($supplier->due_amount);
    //             $supplier->due_amount = 0;
    //         }
    //         $supplier->save();

    //         // 4. Inventory Account (credit - reduce asset)
    //         $inventoryAccount = ChartOfAccount::getInventoryAccount();
    //         if ($inventoryAccount) {
    //             $inventoryAccount->current_balance -= $totalReturnAmount;
    //             $inventoryAccount->save();

    //             $this->insertStatementLog(
    //                 $inventoryAccount->id,
    //                 $logDate,
    //                 $refNo,
    //                 'purchase_return',
    //                 0,
    //                 $totalReturnAmount,   // credit (reduce asset)
    //                 'Purchase return: ' . $refNo,
    //                 $purchaseReturn
    //             );
    //         }

    //         // 5. Cost of Goods Sold (credit - reduce expense)
    //         $cogsAccount = ChartOfAccount::where('type', 'expense')
    //             ->where('sub_type', 'cost_of_sales')
    //             ->where('name', 'Cost of Goods Sold')
    //             ->first();
    //         if (!$cogsAccount) {
    //             $cogsAccount = ChartOfAccount::where('sub_type', 'cost_of_sales')
    //                 ->where('status', 'active')
    //                 ->first();
    //         }
    //         if ($cogsAccount) {
    //             $cogsAccount->current_balance -= $totalReturnAmount;
    //             $cogsAccount->save();

    //             $this->insertStatementLog(
    //                 $cogsAccount->id,
    //                 $logDate,
    //                 $refNo,
    //                 'purchase_return',
    //                 0,
    //                 $totalReturnAmount,   // credit (reduce expense)
    //                 'Purchase return COGS reversal: ' . $refNo,
    //                 $purchaseReturn
    //             );
    //         }

    //         DB::commit();
    //         return response()->json(['success' => 'Purchase return processed successfully.']);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         \Log::error('Purchase return error: ' . $e->getMessage());
    //         return response()->json(['message' => $e->getMessage()], 500);
    //     }
    // }

    public function storePurchaseReturn(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'supplier_id' => 'required|exists:suppliers,id',
                'transaction_date' => 'required|date',
                'product_id' => 'required|array',
                'product_id.*' => 'required|exists:products,id',
                'product_quantity' => 'required|array',
                'product_quantity.*' => 'required|numeric|min:1',
                'product_unit_cost' => 'required|array',
                'product_unit_cost.*' => 'required|numeric|min:0',
                'purchase_id' => 'required|array',
                'purchase_id.*' => 'required|exists:purchases,id',
                'purchase_product_id' => 'required|array',
                'purchase_product_id.*' => 'required|exists:purchase_products,id',
                'variant_name' => 'nullable|array',
            ]);

            $totalReturnAmount = 0;
            foreach ($request->product_subtotal as $subtotal) {
                $totalReturnAmount += floatval($subtotal);
            }

            $documentPath = null;
            if ($request->hasFile('document')) {
                // once per request (a deadlock retry must not store it again); removed if the request finally fails
                $documentPath = \App\Support\RequestSideEffects::once($request, 'purchase_return_document', fn () => $request->file('document')->store('purchase_return_documents', 'public'), fn ($path) => Storage::disk('public')->delete($path));
            }

            $purchaseId = $request->purchase_id[0];
            $purchase = Purchase::lockForUpdate()->findOrFail($purchaseId); // locked: simultaneous returns against one purchase must see each other's effect on its open amount
            $this->refuseLedgerPurchase((int) $purchaseId);
            $supplier = Supplier::lockForUpdate()->findOrFail($request->supplier_id);

            $purchaseReturn = PurchaseReturn::create([
                'purchase_id' => $purchaseId,
                'supplier_id' => $request->supplier_id,
                'reference_no' => $request->reference_no ?? 'PR-' . time(),
                'transaction_date' => $request->transaction_date,
                'document' => $documentPath,
            ]);

            $logDate = $request->transaction_date;
            $refNo   = $purchaseReturn->reference_no;

            foreach ($request->product_id as $index => $productId) {
                $returnQuantity = floatval($request->product_quantity[$index]);
                $unitCost = floatval($request->product_unit_cost[$index]);
                $totalCost = floatval($request->product_subtotal[$index]);
                $productName = $request->product_name[$index];
                $purchaseProductId = $request->purchase_product_id[$index];
                $variantName = $request->variant_name[$index] ?? null;

                PurchaseReturnProduct::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'product_id' => $productId,
                    'product_name' => $productName,
                    'variant' => $variantName,
                    'purchase_quantity' => $returnQuantity,
                    'buying_unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                ]);

                $purchaseProduct = PurchaseProduct::findOrFail($purchaseProductId);
                // The batch and the shelf both give up the units in single statements; the batch refuses
                // to go below zero (a sale may have taken units since this page loaded).
                if (!$this->returnToSupplier($purchaseProduct, $productId, $variantName, (int) $returnQuantity)) {
                    $left = (int) PurchaseProduct::whereKey($purchaseProductId)->value('remaining_qty');
                    throw new \Exception("Return quantity for {$productName} exceeds available ({$left})");
                }
            }

            // Store old values
            $oldNetTotal = $purchase->net_total_amount;
            $oldPaid = $purchase->total_paid;

            // Recalculate this purchase totals
            $newNetTotal = PurchaseProduct::where('purchase_id', $purchaseId)->sum('total_cost');
            $newTotalItem = PurchaseProduct::where('purchase_id', $purchaseId)->sum('purchase_quantity');
            $actualReduction = $oldNetTotal - $newNetTotal;

            // ---- ADJUST THIS PURCHASE'S PAID AMOUNT ----
            // If purchase was overpaid (paid > new net total), reduce the paid amount
            // The excess becomes available to pay other purchases
            $excessPaidFromThisPurchase = 0;
            $newPaid = $oldPaid;

            if ($oldPaid > $newNetTotal) {
                // Overpaid: reduce paid to match new net total
                $excessPaidFromThisPurchase = $oldPaid - $newNetTotal;
                $newPaid = $newNetTotal;
            }

            $purchase->net_total_amount = $newNetTotal;
            $purchase->total_item = $newTotalItem;
            $purchase->total_paid = $newPaid;
            $purchase->total_payment_due = $newNetTotal - $newPaid; // Will be 0 if overpaid
            $purchase->save();

            // ---- USE EXCESS PAID AMOUNT TO PAY OTHER PURCHASES ----
            $remainingCredit = $excessPaidFromThisPurchase;

            if ($remainingCredit > 0) {
                // Get all other purchases of this supplier that have outstanding dues
                // Order by oldest first (FIFO)
                $otherDuePurchases = Purchase::where('supplier_id', $supplier->id)
                    ->where('id', '!=', $purchaseId)
                    ->where('total_payment_due', '>', 0)
                    ->orderBy('purchase_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                foreach ($otherDuePurchases as $otherPurchase) {
                    if ($remainingCredit <= 0) break;

                    $applyAmount = min($remainingCredit, $otherPurchase->total_payment_due);

                    // Increase paid amount on this purchase
                    $otherPurchase->total_paid += $applyAmount;
                    $otherPurchase->total_payment_due -= $applyAmount;
                    $otherPurchase->save();

                    $remainingCredit -= $applyAmount;
                }
            }

            // ---- ACCOUNTING ENTRIES ----
            // Total reduction in supplier liability = actualReduction
            // This is split between: reducing this purchase's paid, and reducing other purchases' dues

            // Calculate AP reduction
            // AP decreases by the amount of actual dues that were reduced
            // $actualReduction = reduction in purchase value
            // Part of it reduced this purchase's paid amount ($excessPaidFromThisPurchase)
            // Part of it reduced other purchases' dues ($excessPaidFromThisPurchase - $remainingCredit)
            $totalDueReduction = $actualReduction;
            $apReduction = $totalDueReduction;

            // 1. Reduce Accounts Payable
            $payableAccount = ChartOfAccount::getAccountsPayable();
            if ($payableAccount && $apReduction > 0) {
                $payableAccount->current_balance -= $apReduction;
                $payableAccount->save();

                // Log AP reduction
                $this->insertStatementLog(
                    $payableAccount->id,
                    $logDate,
                    $refNo,
                    'purchase_return',
                    $apReduction,   // debit (reduce liability)
                    0,
                    'Purchase return: ' . $refNo,
                    $purchaseReturn
                );
            }

            // 2. Update supplier due amount
            $supplier->due_amount -= $totalDueReduction;

            // 3. Handle any remaining credit (goes to opening balance)
            if ($remainingCredit > 0) {
                $supplier->opening_balance += $remainingCredit;

                $openingBalAccount = ChartOfAccount::getOpeningSupplierBalanceAccount();
                if ($openingBalAccount) {
                    $openingBalAccount->current_balance += $remainingCredit;
                    $openingBalAccount->save();

                    $this->insertStatementLog(
                        $openingBalAccount->id,
                        $logDate,
                        $refNo,
                        'purchase_return_credit',
                        0,
                        $remainingCredit,   // credit (supplier credit)
                        'Supplier credit from return: ' . $refNo,
                        $purchaseReturn
                    );
                }
            }

            // Ensure supplier due doesn't go negative
            if ($supplier->due_amount < 0) {
                $supplier->opening_balance += abs($supplier->due_amount);
                $supplier->due_amount = 0;
            }
            $supplier->save();

            // 4. Inventory Account (credit - reduce asset)
            $inventoryAccount = ChartOfAccount::getInventoryAccount();
            if ($inventoryAccount) {
                $inventoryAccount->current_balance -= $totalReturnAmount;
                $inventoryAccount->save();

                $this->insertStatementLog(
                    $inventoryAccount->id,
                    $logDate,
                    $refNo,
                    'purchase_return',
                    0,
                    $totalReturnAmount,   // credit (reduce asset)
                    'Purchase return: ' . $refNo,
                    $purchaseReturn
                );
            }

            DB::commit();
            return response()->json(['success' => 'Purchase return processed successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase return error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // Helper method to update purchase totals
    private function updatePurchaseTotals($purchaseId)
    {
        $purchase = Purchase::find($purchaseId);
        if ($purchase) {
            // Recalculate net total amount from purchase products
            $netTotalAmount = PurchaseProduct::where('purchase_id', $purchaseId)
                ->sum('total_cost');

            // Recalculate total items
            $totalItem = PurchaseProduct::where('purchase_id', $purchaseId)
                ->sum('purchase_quantity');

            $purchase->net_total_amount = $netTotalAmount;
            $purchase->total_item = $totalItem;

            $purchase->save();

            \Log::info('Updated purchase totals', [
                'purchase_id' => $purchaseId,
                'net_total_amount' => $netTotalAmount,
                'total_item' => $totalItem
            ]);
        }
    }


    // purchase return edit method 
    public function purchaseReturnEdit($id)
    {
        // Retrieve the purchase return with the product return relationship
        $purchaseReturn = PurchaseReturn::with('productReturn')->find($id);
        $suppliers = Supplier::all();
        // Check if the purchase return exists
        if (!$purchaseReturn) {
            // Handle the case where the purchase return is not found
            return back() - with('error', 'Purchase Return not found.');
        }

        // Pass the purchase return to the view
        return view('backend.purchase.return_edit', compact('purchaseReturn', 'suppliers'));
    }



    // public function updatePurchaseReturn(Request $request, $id)
    // {
    //     // dd($request->all());
    //     DB::transaction(function () use ($request, $id) {
    //         // Step 0: Calculate the product subtotal
    //         $purchaseReturn = PurchaseReturn::findOrFail($id);

    //         // Calculate the product subtotal
    //         $productSubtotals = $request->product_subtotal;
    //         $subtotalSum = array_sum($productSubtotals);

    //         // Handle document upload
    //         $documentPath = $purchaseReturn->document; // Keep the old document path by default
    //         if ($request->hasFile('document')) {
    //             // Delete the old document if it exists
    //             if ($documentPath && Storage::disk('public')->exists($documentPath)) {
    //                 Storage::disk('public')->delete($documentPath);
    //             }
    //             $documentPath = $request->file('document')->store('documents', 'public');
    //         }

    //         // Update purchase_return table
    //         $purchaseReturn->update([
    //             'purchase_id'      => $request->purchase_id[0], // assuming purchase_id is an array
    //             'supplier_id'      => $request->supplier_id,
    //             'reference_no'     => $request->reference_no,
    //             'transaction_date' => $request->transaction_date,
    //             'document'         => $documentPath,
    //         ]);

    //         // Step 2: Update supplier opening balance
    //         $supplier = Supplier::find($request->supplier_id);
    //         if ($supplier) {
    //             // Recalculate the opening balance considering the new subtotal
    //             $previousTotal = $purchaseReturn->productReturn->sum('total_cost') ?? 0;
    //             $supplier->opening_balance += $previousTotal - $subtotalSum; // Adjust balance
    //             $supplier->save();
    //         }

    //         // Step 3: Update the purchase_return_products table
    //         // Delete existing products
    //         PurchaseReturnProduct::where('purchase_return_id', $id)->delete();

    //         // Insert updated products
    //         $products = [];
    //         foreach ($request->product_id as $index => $productId) {
    //             $products[] = [
    //                 'purchase_return_id' => $id,
    //                 'product_id'         => $productId,
    //                 'product_name'       => $request->product_name[$index],
    //                 'purchase_quantity'  => $request->product_quantity[$index],
    //                 'buying_unit_cost'   => $request->product_unit_cost[$index],
    //                 'total_cost'         => $request->product_subtotal[$index],
    //             ];
    //         }

    //         foreach ($products as $product) {
    //             PurchaseReturnProduct::create($product);
    //         }

    //         // Step 4: Update purchase_products table
    //         foreach ($products as $product) {
    //             $purchaseProduct = PurchaseProduct::where('purchase_id', $request->purchase_id[0])
    //                                             ->where('product_id', $product['product_id'])
    //                                             ->first();

    //             if ($purchaseProduct) {
    //                 $purchaseProduct->purchase_quantity -= $product['purchase_quantity'];
    //                 $purchaseProduct->buying_unit_cost = $product['buying_unit_cost'];
    //                 $purchaseProduct->total_cost -= $product['total_cost'];
    //                 $purchaseProduct->save();
    //             }
    //         }

    //         // Step 5: Update product table
    //         foreach ($products as $product) {
    //             $productData = Product::find($product['product_id']);
    //             if ($productData) {
    //                 $productData->current_stock -= $product['purchase_quantity'];
    //                 $productData->save();
    //             }
    //         }
    //     });

    //     return response()->json(['success' => 'Purchase return updated successfully.']);
    // }

    //         public function updatePurchaseReturn(Request $request, $id)
    // {
    //     DB::transaction(function () use ($request, $id) {
    //         $purchaseReturn = PurchaseReturn::findOrFail($id);

    //         // Step 1: Revert stock and supplier opening balance using old data
    //         $previousProducts = PurchaseReturnProduct::where('purchase_return_id', $id)->get();
    //         $previousTotal = 0;

    //         foreach ($previousProducts as $oldProduct) {
    //             $previousTotal += $oldProduct->total_cost;

    //             // Revert current stock in product table
    //             $product = Product::find($oldProduct->product_id);
    //             if ($product) {
    //                 $product->current_stock += $oldProduct->purchase_quantity;
    //                 $product->save();

    //                 // Handle variant stock revert
    //                 if ($product->variant_product == 1) {
    //                     $oldVariantName = $oldProduct->variant ?? ($request->variant[$loop->index] ?? null); // use if previously stored
    //                     if ($oldVariantName) {
    //                         $productStock = ProductStock::where('product_id', $oldProduct->product_id)
    //                             ->where('variant', $oldVariantName)
    //                             ->first();
    //                         if ($productStock) {
    //                             $productStock->qty += $oldProduct->purchase_quantity;
    //                             $productStock->save();
    //                         }
    //                     }
    //                 } else {
    //                     $productStock = ProductStock::where('product_id', $oldProduct->product_id)
    //                         ->where('variant', '')
    //                         ->first();
    //                     if ($productStock) {
    //                         $productStock->qty += $oldProduct->purchase_quantity;
    //                         $productStock->save();
    //                     }
    //                 }
    //             }

    //             // Revert purchase_products quantities
    //             $purchaseProduct = PurchaseProduct::where('purchase_id', $purchaseReturn->purchase_id)
    //                 ->where('product_id', $oldProduct->product_id)
    //                 ->first();
    //             if ($purchaseProduct) {
    //                 $purchaseProduct->purchase_quantity += $oldProduct->purchase_quantity;
    //                 $purchaseProduct->total_cost += $oldProduct->total_cost;
    //                 $purchaseProduct->save();
    //             }
    //         }

    //         // Step 2: Update document
    //         $documentPath = $purchaseReturn->document;
    //         if ($request->hasFile('document')) {
    //             if ($documentPath && Storage::disk('public')->exists($documentPath)) {
    //                 Storage::disk('public')->delete($documentPath);
    //             }
    //             $documentPath = $request->file('document')->store('documents', 'public');
    //         }

    //         // Step 3: Update main return record
    //         $purchaseReturn->update([
    //             'purchase_id'      => $request->purchase_id[0],
    //             'supplier_id'      => $request->supplier_id,
    //             'reference_no'     => $request->reference_no,
    //             'transaction_date' => $request->transaction_date,
    //             'document'         => $documentPath,
    //         ]);

    //         // Step 4: Adjust supplier opening balance
    //         $supplier = Supplier::find($request->supplier_id);
    //         if ($supplier) {
    //             $newSubtotalSum = array_sum($request->product_subtotal);
    //             $supplier->opening_balance += ($previousTotal - $newSubtotalSum);
    //             $supplier->save();
    //         }

    //         // Step 5: Remove old return products
    //         PurchaseReturnProduct::where('purchase_return_id', $id)->delete();

    //         // Step 6: Add new return products
    //         foreach ($request->product_id as $index => $productId) {
    //             $purchaseQuantity = $request->product_quantity[$index];
    //             $unitCost = $request->product_unit_cost[$index];
    //             $totalCost = $request->product_subtotal[$index];
    //             $productName = $request->product_name[$index];

    //             PurchaseReturnProduct::create([
    //                 'purchase_return_id' => $id,
    //                 'product_id'         => $productId,
    //                 'product_name'       => $productName,
    //                 'purchase_quantity'  => $purchaseQuantity,
    //                 'buying_unit_cost'   => $unitCost,
    //                 'total_cost'         => $totalCost,
    //             ]);

    //             // Step 7: Update purchase_products again
    //             $purchaseProduct = PurchaseProduct::where('purchase_id', $request->purchase_id[0])
    //                 ->where('product_id', $productId)
    //                 ->first();
    //             if ($purchaseProduct) {
    //                 $purchaseProduct->purchase_quantity -= $purchaseQuantity;
    //                 $purchaseProduct->total_cost -= $totalCost;
    //                 $purchaseProduct->buying_unit_cost = $unitCost;
    //                 $purchaseProduct->save();
    //             }

    //             // Step 8: Update current stock in product table
    //             $productData = Product::find($productId);
    //             if ($productData) {
    //                 $productData->current_stock -= $purchaseQuantity;
    //                 $productData->save();

    //                 // Handle variant and non-variant stocks
    //                 if ($productData->variant_product == 1) {
    //                     $variantName = $request->input('variant')[$index] ?? null;
    //                     if ($variantName) {
    //                         $productStock = ProductStock::where('product_id', $productId)
    //                             ->where('variant', $variantName)
    //                             ->first();
    //                         if ($productStock) {
    //                             $productStock->qty -= $purchaseQuantity;
    //                             $productStock->save();
    //                         }
    //                     }
    //                 } else {
    //                     $productStock = ProductStock::where('product_id', $productId)
    //                         ->where('variant', '')
    //                         ->first();
    //                     if ($productStock) {
    //                         $productStock->qty -= $purchaseQuantity;
    //                         $productStock->save();
    //                     }
    //                 }
    //             }
    //         }
    //     });

    //     return response()->json(['success' => 'Purchase return updated successfully.']);
    // }
    // -------------------------------------------------------------
    //  UPDATE PURCHASE RETURN (with statement logs)
    // -------------------------------------------------------------
    public function updatePurchaseReturn(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $purchaseReturn = PurchaseReturn::findOrFail($id);

            // Store previous amounts for reversal logs
            $previousProducts = PurchaseReturnProduct::where('purchase_return_id', $id)->get();
            $previousTotal = 0;
            foreach ($previousProducts as $oldProduct) {
                $previousTotal += $oldProduct->total_cost;
            }

            // --- Revert old accounting (adjust balances) ---
            $payableAccount = ChartOfAccount::getAccountsPayable();
            $inventoryAccount = ChartOfAccount::where('type', 'asset')
                ->where('sub_type', 'inventory')
                ->first();

            if ($payableAccount && $previousTotal > 0) {
                $payableAccount->current_balance += $previousTotal; // add back (reversal of debit)
                $payableAccount->save();
            }
            if ($inventoryAccount && $previousTotal > 0) {
                $inventoryAccount->current_balance += $previousTotal; // add back (reversal of credit)
                $inventoryAccount->save();
            }

            // Revert stock and supplier balance
            foreach ($previousProducts as $oldProduct) {
                // The earlier return is undone: the shelf and the batch get the units back (one statement each).
                $purchaseProduct = PurchaseProduct::where('purchase_id', $purchaseReturn->purchase_id)
                    ->where('product_id', $oldProduct->product_id)
                    ->first();
                $this->takeBackFromSupplier($purchaseProduct, $oldProduct->product_id, $oldProduct->variant ?? null, (int) $oldProduct->purchase_quantity);
            }

            // Supplier balance revert
            $supplier = Supplier::lockForUpdate()->find($purchaseReturn->supplier_id);
            if ($supplier) {
                $supplier->due_amount += $previousTotal;
                $supplier->save();
            }

            // --- Insert REVERSAL statement logs ---
            $reversalDate = now()->toDateString();
            $reversalRef  = $purchaseReturn->reference_no . '-REV';
            if ($payableAccount && $previousTotal > 0) {
                $this->insertStatementLog(
                    $payableAccount->id,
                    $reversalDate,
                    $reversalRef,
                    'purchase_return_reversal',
                    0,
                    $previousTotal,   // credit to reverse the original debit
                    'Reversal for edit of ' . $purchaseReturn->reference_no,
                    $purchaseReturn
                );
            }
            if ($inventoryAccount && $previousTotal > 0) {
                $this->insertStatementLog(
                    $inventoryAccount->id,
                    $reversalDate,
                    $reversalRef,
                    'purchase_return_reversal',
                    $previousTotal,   // debit to reverse the original credit
                    0,
                    'Reversal for edit of ' . $purchaseReturn->reference_no,
                    $purchaseReturn
                );
            }

            // ---- UPDATE RECORD ----
            // Document
            $documentPath = $purchaseReturn->document;
            if ($request->hasFile('document')) {
                $oldDocument = $documentPath;
                $documentPath = \App\Support\RequestSideEffects::once($request, 'purchase_return_document', fn () => $request->file('document')->store('documents', 'public'), fn ($path) => Storage::disk('public')->delete($path));
                // the previous file goes only after the new state is committed (a rolled-back or retried update keeps its document)
                if ($oldDocument) {
                    DB::afterCommit(function () use ($oldDocument) {
                        if (Storage::disk('public')->exists($oldDocument)) {
                            Storage::disk('public')->delete($oldDocument);
                        }
                    });
                }
            }

            $purchaseReturn->update([
                'purchase_id'      => $request->purchase_id[0],
                'supplier_id'      => $request->supplier_id,
                'reference_no'     => $request->reference_no,
                'transaction_date' => $request->transaction_date,
                'document'         => $documentPath,
            ]);

            // Delete old return products
            PurchaseReturnProduct::where('purchase_return_id', $id)->delete();

            // Calculate new subtotal
            $newSubtotalSum = 0;
            foreach ($request->product_subtotal as $subtotal) {
                $newSubtotalSum += floatval($subtotal);
            }

            // Add new return products
            foreach ($request->product_id as $index => $productId) {
                $purchaseQuantity = $request->product_quantity[$index];
                $unitCost = $request->product_unit_cost[$index];
                $totalCost = $request->product_subtotal[$index];
                $productName = $request->product_name[$index];

                PurchaseReturnProduct::create([
                    'purchase_return_id' => $id,
                    'product_id'         => $productId,
                    'product_name'       => $productName,
                    'purchase_quantity'  => $purchaseQuantity,
                    'buying_unit_cost'   => $unitCost,
                    'total_cost'         => $totalCost,
                ]);

                // Apply the new return: batch and shelf give up the units (one statement each).
                $purchaseProduct = PurchaseProduct::where('purchase_id', $request->purchase_id[0])
                    ->where('product_id', $productId)
                    ->first();
                if ($purchaseProduct) {
                    if (!$this->returnToSupplier($purchaseProduct, $productId, $request->input('variant')[$index] ?? null, (int) $purchaseQuantity, ['buying_unit_cost' => $unitCost])) {
                        throw new \Exception("Return quantity for {$productName} exceeds available ({$purchaseProduct->remaining_qty})");
                    }
                } else {
                    $this->shelfMove($productId, $request->input('variant')[$index] ?? null, -(int) $purchaseQuantity);
                }
            }

            // Update supplier (apply new return)
            if ($supplier) {
                $supplier->due_amount -= $newSubtotalSum;
                $supplier->save();
            }

            // ---- Apply new accounting entries ----
            if ($payableAccount && $newSubtotalSum > 0) {
                $payableAccount->current_balance -= $newSubtotalSum;
                $payableAccount->save();
            }
            if ($inventoryAccount && $newSubtotalSum > 0) {
                $inventoryAccount->current_balance -= $newSubtotalSum;
                $inventoryAccount->save();
            }

            // ---- Insert NEW statement logs ----
            $logDate = $request->transaction_date;
            $refNo   = $purchaseReturn->reference_no;
            if ($newSubtotalSum > 0 && $payableAccount) {
                $this->insertStatementLog(
                    $payableAccount->id,
                    $logDate,
                    $refNo,
                    'purchase_return',
                    $newSubtotalSum,
                    0,
                    'Purchase return: ' . $refNo,
                    $purchaseReturn
                );
            }
            if ($inventoryAccount && $newSubtotalSum > 0) {
                $this->insertStatementLog(
                    $inventoryAccount->id,
                    $logDate,
                    $refNo,
                    'purchase_return',
                    0,
                    $newSubtotalSum,
                    'Purchase return: ' . $refNo,
                    $purchaseReturn
                );
            }

            DB::commit();
            return response()->json(['success' => 'Purchase return updated successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Purchase return update error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    // -------------------------------------------------------------
    //  PURCHASE DELETE (add reversal logs) - optional but recommended
    // -------------------------------------------------------------
    public function purchaseDelete($id)
    {
        DB::beginTransaction();
        try {
            $purchase = Purchase::lockForUpdate()->findOrFail($id); // document first: a repeated/simultaneous delete waits here and then finds it gone
            $this->refuseLedgerPurchase((int) $purchase->id);
            $supplier = Supplier::lockForUpdate()->find($purchase->supplier_id);

            // Revert advance amount
            if ($purchase->total_advance_amount > 0 && $supplier) {
                $supplier->opening_balance += $purchase->total_advance_amount;
                $supplier->save();
            }

            // Revert stock
            $purchaseProducts = PurchaseProduct::where('purchase_id', $purchase->id)->get();
            // Deleting would erase the cost record of units that already left the shelf.
            foreach ($purchaseProducts as $pp) {
                $sold = (int) $pp->purchase_quantity - (int) $pp->remaining_qty;
                if ($sold > 0) {
                    throw new \Exception("Cannot delete this purchase: {$sold} unit(s) of {$pp->product_name} from it were already sold.");
                }
            }
            foreach ($purchaseProducts as $pp) {
                $product = Product::find($pp->product_id);
                if ($product) {
                    $variantName = $pp->variant ?? '';
                    $this->updateProductStockVariant($product->id, $variantName, -$pp->purchase_quantity);
                }
            }

            // Accounting reversals
            $payableAccount = ChartOfAccount::getAccountsPayable();
            if ($payableAccount && $purchase->total_payment_due > 0) {
                $payableAccount->current_balance -= $purchase->total_payment_due;
                $payableAccount->save();
            }
            if ($purchase->opening_balance_used > 0) {
                $openingBalAccount = ChartOfAccount::getOpeningSupplierBalanceAccount();
                if ($openingBalAccount) {
                    $openingBalAccount->current_balance += $purchase->opening_balance_used;
                    $openingBalAccount->save();
                }
            }
            if ($purchase->total_paid > 0 && $purchase->payment_account_id) {
                $paymentAcc = ChartOfAccount::find($purchase->payment_account_id);
                if ($paymentAcc) {
                    $paymentAcc->current_balance += $purchase->total_paid;
                    $paymentAcc->save();
                }
            }

            // Statement logs for deletion (reversal)
            // Use the ORIGINAL purchase date so period reports net to zero
            $delDate = $purchase->purchase_date ? date('Y-m-d', strtotime($purchase->purchase_date)) : now()->toDateString();
            $delRef  = $purchase->reference_no . '-DEL';
            if ($payableAccount && $purchase->total_payment_due > 0) {
                $this->insertStatementLog($payableAccount->id, $delDate, $delRef, 'purchase_deletion', $purchase->total_payment_due, 0, 'Deletion of ' . $purchase->reference_no, $purchase);
            }
            if ($purchase->opening_balance_used > 0 && isset($openingBalAccount)) {
                $this->insertStatementLog($openingBalAccount->id, $delDate, $delRef, 'purchase_opening_deletion', $purchase->opening_balance_used, 0, 'Deletion of ' . $purchase->reference_no, $purchase);
            }
            if ($purchase->total_paid > 0 && $purchase->payment_account_id) {
                $this->insertStatementLog($purchase->payment_account_id, $delDate, $delRef, 'purchase_payment_deletion', $purchase->total_paid, 0, 'Deletion of ' . $purchase->reference_no, $purchase);
            }

            // Delete document: only after the deletion is committed (a rolled-back or retried delete must keep the file)
            if ($purchase->document) {
                $documentToDelete = $purchase->document;
                DB::afterCommit(function () use ($documentToDelete) {
                    if (Storage::disk('public')->exists($documentToDelete)) {
                        Storage::disk('public')->delete($documentToDelete);
                    }
                });
            }

            PurchaseProduct::where('purchase_id', $purchase->id)->delete();
            $purchase->delete();

            DB::commit();
            flash(translate('Purchase deleted successfully.'))->success();
            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }


    // End of Purchase Controller method 
}
