<?php
// app/Http/Controllers/StockAdjustmentController.php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseProduct;
use App\Services\Inventory\StockLedger;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StockAdjustmentController extends Controller
{
    public function __construct()
    {
        // Authentication is on the route group; these are the per-action role permissions.
        $this->middleware(['permission:view_stock_adjustments'])->only(['index', 'show', 'searchProduct', 'exportCsv', 'exportExcel', 'exportPdf']);
        $this->middleware(['permission:manage_stock_adjustments'])->only(['create', 'store', 'destroy']);
    }

    /**
     * Display a listing of stock adjustments.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $per_page = $request->input('per_page', 25);

        $stock_adjustments = StockAdjustment::with(['creator'])
            ->when($search, function ($query, $search) {
                return $query->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('additional_notes', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate($per_page);

        return view('backend.stock_adjustments.index', compact('stock_adjustments', 'search', 'per_page'));
    }

    /**
     * Show the form for creating a new stock adjustment.
     */
    public function create()
    {
        $reference_no = StockAdjustment::generateReferenceNo();

        return view('backend.stock_adjustments.create', compact('reference_no'));
    }

    /**
     * Search products for stock adjustment.
     */

    public function searchProduct(Request $request)
    {
        $search_term = $request->input('search_term');

        $products = Product::where('name', 'like', '%' . $search_term . '%')
            ->where('published', 1)
            ->with(['stocks', 'purchaseProducts'])
            ->limit(20)
            ->get();

        $products_array = [];
        foreach ($products as $product) {
            // Get total remaining quantity from purchase products
            $total_remaining_qty = $product->purchaseProducts()
                ->where('remaining_qty', '>', 0)
                ->sum('remaining_qty');

            // Calculate total stock from product stocks (sum of all variants)
            $total_stock_qty = 0;
            if ($product->variant_product == 1) {
                foreach ($product->stocks as $stock) {
                    $total_stock_qty += $stock->qty;
                }
            } else {
                $total_stock_qty = optional($product->stocks->first())->qty ?? 0;
            }

            // Use purchase remaining_qty if available, otherwise use product stock qty
            $display_stock = $total_remaining_qty > 0 ? $total_remaining_qty : $total_stock_qty;

            // Get purchase products with remaining quantity for this product
            $purchase_products_with_stock = $product->purchaseProducts()
                ->where('remaining_qty', '>', 0)
                ->orderBy('id', 'asc')
                ->get();

            if ($purchase_products_with_stock->count() > 0) {
                // Show products based on purchase_products variants
                foreach ($purchase_products_with_stock as $purchase_product) {
                    // Find matching product stock
                    $matching_stock = $product->stocks->where('variant', $purchase_product->variant)->first();

                    $products_array[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_stock_id' => $matching_stock ? $matching_stock->id : null,
                        'variant' => $purchase_product->variant, // Show variant from purchase_products
                        'sku' => $matching_stock ? $matching_stock->sku : null,
                        'unit_price' => $purchase_product->buying_unit_cost, // Show buying cost from purchase
                        'current_stock' => $total_remaining_qty, // Total product stock
                        'variant_stock' => $purchase_product->remaining_qty, // Individual variant stock from purchase
                        'enable_stock' => 1,
                    ];
                }
            } else {
                // Fallback to product stocks if no purchase products
                $stocks = $product->stocks;

                if ($stocks->count() > 0) {
                    foreach ($stocks as $stock) {
                        $products_array[] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'product_stock_id' => $stock->id,
                            'variant' => $stock->variant,
                            'sku' => $stock->sku,
                            'unit_price' => $product->purchase_price ?? $stock->price ?? 0,
                            'current_stock' => $display_stock,
                            'variant_stock' => $stock->qty,
                            'enable_stock' => 1,
                        ];
                    }
                } else {
                    $products_array[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_stock_id' => null,
                        'variant' => null,
                        'sku' => null,
                        'unit_price' => $product->purchase_price ?? $product->unit_price ?? 0,
                        'current_stock' => $display_stock,
                        'variant_stock' => $display_stock,
                        'enable_stock' => 1,
                    ];
                }
            }
        }

        return response()->json($products_array);
    }

    /**
     * Store a newly created stock adjustment.
     */


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_date' => 'required|date',
            'adjustment_type' => 'required|in:normal,abnormal',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.unit_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        foreach ($request->products as $line) {
            if ((float) $line['quantity'] != (int) $line['quantity']) {
                return response()->json(['success' => false, 'message' => 'Quantities must be whole numbers.']);
            }
        }

        $this->adjustTakes = [];
        try {
            DB::beginTransaction();

            // Calculate total amount
            $total_amount = 0;
            foreach ($request->products as $product_data) {
                $total_amount += $product_data['quantity'] * $product_data['unit_price'];
            }

            // Create Stock Adjustment
            $stock_adjustment = StockAdjustment::create([
                'reference_no' => $request->ref_no ?? StockAdjustment::generateReferenceNo(),
                'transaction_date' => $request->transaction_date,
                'adjustment_type' => $request->adjustment_type,
                'total_amount' => $total_amount,
                'total_amount_recovered' => $request->total_amount_recovered ?? 0,
                'additional_notes' => $request->additional_notes,
                'created_by' => Auth::id(),
            ]);

            // Create Stock Adjustment Lines and Update Stock
            foreach ($request->products as $product_data) {
                $subtotal = $product_data['quantity'] * $product_data['unit_price'];

                // The line records the exact stock row (variation) it adjusts, found by id or by variation name.
                $stockRow = $this->stockRowFor((int) $product_data['product_id'], $product_data['product_stock_id'] ?? null, $product_data['variant'] ?? null);
                $product_data['product_stock_id'] = $stockRow ? $stockRow->id : ($product_data['product_stock_id'] ?? null);

                StockAdjustmentLine::create([
                    'stock_adjustment_id' => $stock_adjustment->id,
                    'product_id' => $product_data['product_id'],
                    'product_stock_id' => $product_data['product_stock_id'],
                    'quantity' => $product_data['quantity'],
                    'unit_price' => $product_data['unit_price'],
                    'subtotal' => $subtotal,
                ]);

                // The shelf and the purchase batches of ONE variation give up the units, each in single
                // conditional statements: nothing goes below zero, a sale made in between is not erased, and
                // a product with several variations must say which one (it used to drain every variation's batches).
                $this->removeFromStock($product_data, (int) $product_data['quantity']);

                // Update product current_stock
                $product = Product::find($product_data['product_id']);
                if ($product) {
                    $product->decrement('current_stock', $product_data['quantity']);
                }
            }

            // Update chart_of_accounts - Add expense to Stock Adjustment account
            $stock_adjustment_account = ChartOfAccount::where('name', 'Stock Adjustment')
                ->where('type', 'expense')
                ->where('sub_type', 'expense')
                ->where('is_deleted', 0)
                ->first();

            if ($stock_adjustment_account) {
                // Update current balance by adding the total amount
                $stock_adjustment_account->current_balance += $total_amount;
                $stock_adjustment_account->save();
            } else {
                // Create Stock Adjustment account if it doesn't exist
                ChartOfAccount::create([
                    'code' => '5-51007',
                    'name' => 'Stock Adjustment',
                    'type' => 'expense',
                    'sub_type' => 'expense',
                    'parent_id' => null,
                    'opening_balance' => 0.00,
                    'current_balance' => $total_amount,
                    'bank_name' => null,
                    'account_number' => null,
                    'mobile_number' => null,
                    'cashout_charge' => 0.00,
                    'status' => 'active',
                    'description' => 'Stock Adjustment for reference: ' . $stock_adjustment->reference_no,
                    'is_deleted' => 0,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjustment created successfully.',
                'redirect_url' => route('stock-adjustments.index')
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            // Batch units this request took that the rollback could not restore (MyISAM) go back by hand.
            foreach ($this->adjustTakes as $take) {
                app(StockLedger::class)->release($take);
            }

            Log::error('Stock Adjustment Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /** Batch takes made by the current request, so a failed one can give them back. */
    protected $adjustTakes = [];

    /** The stock row of an adjustment line: by id when given, else by variation, else the plain product's row. */
    protected function stockRowFor($productId, $stockId, $variant): ?ProductStock
    {
        if ($stockId) {
            return ProductStock::where('id', $stockId)->where('product_id', $productId)->first();
        }
        if ($variant !== null && $variant !== '') {
            return ProductStock::where('product_id', $productId)->where('variant', $variant)->orderBy('id')->first();
        }

        $rows = ProductStock::where('product_id', $productId)->orderBy('id')->get();
        if ($rows->count() > 1) {
            throw new Exception('This product has several variations. Choose which one to adjust.');
        }

        return $rows->first();
    }

    protected function removeFromStock(array $line, int $quantity): void
    {
        $productId = (int) $line['product_id'];
        $row = $this->stockRowFor($productId, $line['product_stock_id'] ?? null, $line['variant'] ?? null);
        $variant = $row ? $row->variant : '';
        $name = optional(Product::find($productId))->name ?? ('product #' . $productId);

        // The shelf first (it rolls back), then the batches (MyISAM may not).
        if ($row && !app(StockLedger::class)->adjustRow((int) $row->id, -$quantity)) {
            throw new Exception("Not enough stock for {$name}" . ($variant !== '' ? " ({$variant})" : '') . ': ' . max(0, (int) $row->fresh()->qty) . " on the shelf, {$quantity} to remove.");
        }

        $taken = app(StockLedger::class)->consumeBatches($productId, $variant, $quantity);
        $this->adjustTakes[] = $taken['take'];
    }

    protected function addBackToStock($line): void
    {
        $productId = (int) $line->product_id;
        $row = $this->stockRowFor($productId, $line->product_stock_id, null);
        $variant = $row ? $row->variant : '';

        if ($row) {
            app(StockLedger::class)->adjustRow((int) $row->id, (int) $line->quantity);
        }
        app(StockLedger::class)->returnBatchUnits($productId, $variant, (int) $line->quantity);
    }

    /**
     * Display the specified stock adjustment.
     */
    public function show($id)
    {
        $stock_adjustment = StockAdjustment::with(['creator', 'lines.product', 'lines.productStock'])
            ->findOrFail($id);

        if (request()->ajax()) {
            return view('backend.stock_adjustments.show_modal', compact('stock_adjustment'));
        }

        return view('backend.stock_adjustments.show', compact('stock_adjustment'));
    }

    /**
     * Remove the specified stock adjustment.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $stock_adjustment = StockAdjustment::with('lines')->findOrFail($id);

            // Reverse stock quantities
            foreach ($stock_adjustment->lines as $line) {
                // The shelf and the batches of the same variation get the units back (never beyond what was
                // bought into a batch). The shelf used to stay short after an adjustment was deleted.
                $this->addBackToStock($line);

                // Restore product current_stock
                $product = Product::find($line->product_id);
                if ($product) {
                    $product->increment('current_stock', $line->quantity);
                }
            }

            // Reverse chart_of_accounts entry
            $stock_adjustment_account = ChartOfAccount::where('name', 'Stock Adjustment')
                ->where('type', 'expense')
                ->first();

            if ($stock_adjustment_account) {
                // Subtract the total amount from current balance
                $stock_adjustment_account->current_balance -= $stock_adjustment->total_amount;

                // Ensure balance doesn't go below 0
                if ($stock_adjustment_account->current_balance < 0) {
                    $stock_adjustment_account->current_balance = 0;
                }

                $stock_adjustment_account->save();
            }

            // Delete lines
            $stock_adjustment->lines()->delete();

            // Delete stock adjustment
            $stock_adjustment->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjustment deleted successfully.'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Stock Adjustment Delete Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

   /**
     * Export stock adjustments to CSV
     */
public function exportCsv(Request $request)
{
    $stock_adjustments = $this->getFilteredData($request);
    
    // Export as .xls instead of .csv for better Excel compatibility
    $filename = 'stock_adjustments_' . date('Y-m-d') . '.xls';
    
    $headers = [
        'Content-Type' => 'application/vnd.ms-excel',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        'Pragma' => 'public',
    ];
    
    return response()->view('backend.stock_adjustments.csv_export', compact('stock_adjustments'), 200, $headers);
}

    /**
     * Export stock adjustments to Excel
     */

    public function exportExcel(Request $request)
{
    $stock_adjustments = $this->getFilteredData($request);
    
    $filename = 'stock_adjustments_' . date('Y-m-d') . '.xls';
    
    $headers = [
        'Content-Type' => 'application/vnd.ms-excel',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        'Pragma' => 'public',
    ];
    
    return response()->view('backend.stock_adjustments.excel', compact('stock_adjustments'), 200, $headers);
}

    /**
     * Export stock adjustments to PDF
     */
    public function exportPdf(Request $request)
    {
        $stock_adjustments = $this->getFilteredData($request);
        
        $pdf = \PDF::loadView('backend.stock_adjustments.pdf', compact('stock_adjustments'));
        
        return $pdf->download('stock_adjustments_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Get filtered data for exports
     */
    // private function getFilteredData(Request $request)
    // {
    //     $search = $request->input('search');

    //     return StockAdjustment::with(['creator'])
    //         ->when($search, function ($query, $search) {
    //             return $query->where('reference_no', 'like', "%{$search}%")
    //                 ->orWhere('additional_notes', 'like', "%{$search}%");
    //         })
    //         ->orderBy('id', 'desc')
    //         ->get();
    // }

     private function getFilteredData(Request $request)
    {
        $search = $request->input('search');
        
        return StockAdjustment::with(['creator'])
            ->when($search, function ($query, $search) {
                return $query->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('additional_notes', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->get();
    }
}
