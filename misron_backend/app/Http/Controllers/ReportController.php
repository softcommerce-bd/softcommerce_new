<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\CommissionHistory;
use App\Models\Wallet;
use App\Models\User;
use App\Models\Search;
use App\Models\Shop;
use Auth;
use App\Exports\InhouseSaleProductExport;
use App\Exports\ProductStockExport;
use App\Exports\SalesReportExport;
use App\Models\Order;
use App\Models\OrderDetail;
use Excel;
use Illuminate\Support\Facades\Response;

class ReportController extends Controller
{
    public function __construct()
    {
        // Staff Permission Check
        $this->middleware(['permission:in_house_product_sale_report'])->only('in_house_sale_report');
        $this->middleware(['permission:seller_products_sale_report'])->only('seller_sale_report');
        $this->middleware(['permission:products_stock_report'])->only('stock_report');
        $this->middleware(['permission:product_wishlist_report'])->only('wish_report');
        $this->middleware(['permission:user_search_report'])->only('user_search_report');
        $this->middleware(['permission:commission_history_report'])->only('commission_history');
        $this->middleware(['permission:wallet_transaction_report'])->only('wallet_transaction_history');
    }

public function stock_report(Request $request)
{
    $sort_by = null;
    $supplier_id = $request->supplier_id;
    $purchase_id = $request->purchase_id;
    $category_id = $request->category_id;
    $product_id = $request->product_id; // Add product_id filter

    $baseQuery = Product::with([
        'stocks',
        'main_category'
    ]);

    // Add purchase products with conditional loading
    $baseQuery->with(['purchaseProducts' => function($query) use ($supplier_id, $purchase_id) {
        $query->with(['purchase.supplier']);
        
        if ($supplier_id) {
            $query->whereHas('purchase', function($q) use ($supplier_id) {
                $q->where('supplier_id', $supplier_id);
            });
        }
        
        if ($purchase_id) {
            $query->where('purchase_id', $purchase_id);
        }
    }]);

    // Apply filters
    if ($request->has('category_id') && $request->category_id) {
        $sort_by = $request->category_id;
        $baseQuery->where('category_id', $sort_by);
    }

    if ($request->has('supplier_id') && $request->supplier_id) {
        $baseQuery->whereHas('purchaseProducts.purchase', function($query) use ($supplier_id) {
            $query->where('supplier_id', $supplier_id);
        });
    }

    if ($request->has('purchase_id') && $request->purchase_id) {
        $baseQuery->whereHas('purchaseProducts', function($query) use ($purchase_id) {
            $query->where('purchase_id', $purchase_id);
        });
    }

    // Add product_id filter
    if ($request->has('product_id') && $request->product_id) {
        $baseQuery->where('id', $request->product_id);
    }

    $allProducts = $baseQuery->get();

    $total_products = 0;
    $total_stock_qty = 0;
    $total_stock_value = 0;

    foreach ($allProducts as $product) {
        // Get actual stock quantity from product_stocks table
        $stock_qty = $product->stocks->sum('qty');
        $stock_value = $this->calculateStockValueWeightedAverage($product, $stock_qty, $supplier_id, $purchase_id);

        $total_products++;
        $total_stock_qty += $stock_qty;
        $total_stock_value += $stock_value;
    }

    // Paginate for display
    $products = $baseQuery->paginate(15);

    foreach ($products as $product) {
        // Get actual stock quantity from product_stocks table
        $current_stock_qty = $product->stocks->sum('qty');
        $total_value = $this->calculateStockValueWeightedAverage($product, $current_stock_qty, $supplier_id, $purchase_id);

        $product->stock_qty = $current_stock_qty;
        $product->total_buying_price = $total_value;
        
        // Add purchase details for the product (already filtered in query)
        // $product->purchase_details = $this->getPurchaseDetails($product);
        $product->purchase_details = $this->getPurchaseDetails($product, $supplier_id, $purchase_id);
    }

    return view('backend.reports.stock_report', compact(
        'products',
        'sort_by',
        'supplier_id',
        'purchase_id',
        'category_id',
        'product_id', // Pass product_id to view
        'total_products',
        'total_stock_qty',
        'total_stock_value'
    ));
}


    private function getPurchaseDetails($product, $supplier_id = null, $purchase_id = null)
{
    $purchaseDetails = [];

    foreach ($product->purchaseProducts as $purchaseProduct) {
        $purchase = $purchaseProduct->purchase;

        // Check if purchase exists
        if ($purchase) {
            // Apply supplier filter
            if ($supplier_id && $purchase->supplier_id != $supplier_id) {
                continue;
            }
            
            // Apply purchase ID filter
            if ($purchase_id && $purchase->id != $purchase_id) {
                continue;
            }
            
            // Use actual quantities from purchase_products table
            $purchase_quantity = $purchaseProduct->purchase_quantity ?? 0;
            $remaining_qty = $purchaseProduct->remaining_qty ?? 0;

            // Show ALL purchases with quantity > 0 (not just remaining_qty > 0)
            if ($purchase_quantity > 0) {
                $buying_price = $purchaseProduct->buying_unit_cost ?? $purchaseProduct->price ?? 0;

                // Format date properly
                $purchase_date = $purchase->purchase_date ?? $purchase->created_at;
                if ($purchase_date instanceof \DateTime) {
                    $purchase_date = $purchase_date->format('Y-m-d');
                }

                $purchaseDetails[] = [
                    'purchase_date' => $purchase_date,
                    'supplier_name' => $purchase->supplier ? $purchase->supplier->business_name : 'N/A',
                    'purchase_quantity' => $purchase_quantity,
                    'remaining_qty' => $remaining_qty,
                    'buying_price' => $buying_price,
                    'total_value' => $buying_price * $remaining_qty,
                    'purchase_id' => $purchase->id
                ];
            }
        }
    }

    // Sort by purchase date (newest first)
    usort($purchaseDetails, function($a, $b) {
        return strtotime($b['purchase_date']) - strtotime($a['purchase_date']);
    });

    return $purchaseDetails;
}

/**
 * Calculate stock value based on actual stock and purchase prices with filters
 */
private function calculateStockValueWeightedAverage($product, $current_stock_qty, $supplier_id = null, $purchase_id = null)
{
    if ($current_stock_qty <= 0) {
        return 0;
    }

    // If we have purchase products with prices, use weighted average
    $total_value = 0;
    $total_remaining_qty = 0;
    
    foreach ($product->purchaseProducts as $purchaseProduct) {
        $purchase = $purchaseProduct->purchase;
        
        // Apply filters
        if ($supplier_id && $purchase && $purchase->supplier_id != $supplier_id) {
            continue;
        }
        
        if ($purchase_id && $purchase && $purchase->id != $purchase_id) {
            continue;
        }
        
        $remaining_qty = $purchaseProduct->remaining_qty ?? 0;
        
        if ($remaining_qty > 0) {
            $buying_price = $purchaseProduct->buying_unit_cost ?? $purchaseProduct->price ?? 0;
            $total_value += $buying_price * $remaining_qty;
            $total_remaining_qty += $remaining_qty;
        }
    }
    
    // If we have purchase data with quantities, use weighted average
    if ($total_remaining_qty > 0) {
        $average_price = $total_value / $total_remaining_qty;
        return $average_price * $current_stock_qty;
    }
    
    // Fallback: If no purchase data, use the first purchase price or estimate
    if ($product->purchaseProducts->count() > 0) {
        $firstPurchase = $product->purchaseProducts->first();
        $buying_price = $firstPurchase->buying_unit_cost ?? $firstPurchase->price ?? 0;
        return $buying_price * $current_stock_qty;
    }
    
    return 0;
}

/**
 * Format date for Excel to prevent ####### issue
 */
private function formatDateForExcel($date)
{
    if (empty($date) || $date === 'N/A') {
        return 'N/A';
    }
    
    try {
        // If it's already a Carbon instance or DateTime
        if ($date instanceof \DateTime) {
            // Add tab character to force Excel to treat as text
            return "\t" . $date->format('Y-m-d');
        }
        
        // If it's a string, parse it
        if (is_string($date)) {
            $parsedDate = \Carbon\Carbon::parse($date);
            // Add tab character to force Excel to treat as text
            return "\t" . $parsedDate->format('Y-m-d');
        }
        
        return 'N/A';
    } catch (\Exception $e) {
        return 'N/A';
    }
}


public function stock_report_download (Request $request)
{
    $category_id = $request->category_id;
    $supplier_id = $request->supplier_id;
    $purchase_id = $request->purchase_id;
    $product_id = $request->product_id; // Get product_id from request

    $baseQuery = Product::with([
        'stocks',
        'purchaseProducts.purchase.supplier',
        'main_category'
    ]);

    // Apply filters
    if ($category_id) {
        $baseQuery->where('category_id', $category_id);
    }

    if ($supplier_id) {
        $baseQuery->whereHas('purchaseProducts.purchase', function($query) use ($supplier_id) {
            $query->where('supplier_id', $supplier_id);
        });
    }

    if ($purchase_id) {
        $baseQuery->whereHas('purchaseProducts', function($query) use ($purchase_id) {
            $query->where('purchase_id', $purchase_id);
        });
    }

    // Add product_id filter
    if ($product_id) {
        $baseQuery->where('id', $product_id);
    }

    $products = $baseQuery->get();

    $fileName = 'stock_report_' . date('Y_m_d_H_i_s') . '.csv';
    $headers = [
        'Content-Type' => 'text/csv; charset=utf-8',
        'Content-Disposition' => 'attachment; filename=' . $fileName,
        'Pragma' => 'no-cache',
        'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        'Expires' => '0'
    ];

    $callback = function() use ($products, $supplier_id, $purchase_id) {
        // Add BOM (Byte Order Mark) for UTF-8 to help Excel with special characters
        echo "\xEF\xBB\xBF";
        
        $file = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($file, [
            'Product ID',
            'Product Name',
            'Category',
            'Current Stock',
            'Purchase Date',
            'Supplier',
            'Purchase Qty',
            'Remaining Qty',
            'Buying Price',
            'Stock Value',
            'Purchase ID'
        ]);

        foreach ($products as $product) {
            $current_stock_qty = $product->stocks->sum('qty');
            // Pass filters to getPurchaseDetails
            $purchaseDetails = $this->getPurchaseDetails($product, $supplier_id, $purchase_id);
            
            if (count($purchaseDetails) > 0) {
                foreach ($purchaseDetails as $detail) {
                    // REMOVED the condition that skips records with remaining_qty <= 0
                    // Now ALL purchase records will be shown
                    
                    // Format date for Excel (YYYY-MM-DD format)
                    $purchase_date = $this->formatDateForExcel($detail['purchase_date']);
                    
                    fputcsv($file, [
                        $product->id,
                        $product->getTranslation('name'),
                        $product->main_category ? $product->main_category->getTranslation('name') : 'N/A',
                        $current_stock_qty, // Current stock from product_stocks
                        $purchase_date, // Properly formatted date
                        $detail['supplier_name'],
                        $detail['purchase_quantity'],
                        $detail['remaining_qty'],
                        $detail['buying_price'],
                        $detail['total_value'],
                        $detail['purchase_id']
                    ]);
                }
            } else {
                // Product with no purchase details but has stock
                fputcsv($file, [
                    $product->id,
                    $product->getTranslation('name'),
                    $product->main_category ? $product->main_category->getTranslation('name') : 'N/A',
                    $current_stock_qty, // Current stock from product_stocks
                    'N/A',
                    'N/A',
                    '0',
                    '0',
                    '0',
                    '0',
                    'N/A'
                ]);
            }
        }

        fclose($file);
    };

    return Response::stream($callback, 200, $headers);
}


// stock report end here 

/**
 * FIFO Calculation Method
 */
private function calculateFIFOValue($purchaseProducts, $current_stock_qty)
{
    $remaining_stock = $current_stock_qty;
    $total_value = 0;

    foreach ($purchaseProducts as $purchaseProduct) {
        if ($remaining_stock <= 0) break;

        $available_qty = min($purchaseProduct->remaining_qty, $remaining_stock);
        
        if ($available_qty > 0) {
            $total_value += $available_qty * $purchaseProduct->buying_unit_cost;
            $remaining_stock -= $available_qty;
        }
    }

    // If we couldn't allocate all stock with FIFO, use the last purchase price for remaining
    if ($remaining_stock > 0 && count($purchaseProducts) > 0) {
        $lastPurchase = $purchaseProducts->last();
        $total_value += $remaining_stock * $lastPurchase->buying_unit_cost;
    }

    return $total_value;
}

/**
 * Weighted Average Calculation Method
 */
private function calculateWeightedAverageValue($purchaseProducts, $current_stock_qty)
{
    $total_cost = 0;
    $total_quantity = 0;

    foreach ($purchaseProducts as $purchaseProduct) {
        $total_cost += $purchaseProduct->purchase_quantity * $purchaseProduct->buying_unit_cost;
        $total_quantity += $purchaseProduct->purchase_quantity;
    }

    if ($total_quantity > 0) {
        $weighted_avg_cost = $total_cost / $total_quantity;
        return $current_stock_qty * $weighted_avg_cost;
    }

    return 0;
}


    public function in_house_sale_report(Request $request)
    {
        $sort_by = null;
        $products = Product::orderBy('num_of_sale', 'desc')->where('added_by', 'admin');
        if ($request->has('category_id')) {
            $sort_by = $request->category_id;
            $products = $products->where('category_id', $sort_by);
        }
        $products = $products->paginate(15);
        return view('backend.reports.in_house_sale_report', compact('products', 'sort_by'));
    }

    public function seller_sale_report(Request $request)
    {
        $sort_by = null;
        // $sellers = User::where('user_type', 'seller')->orderBy('created_at', 'desc');
        $sellers = Shop::with('user')->orderBy('created_at', 'desc');
        if ($request->has('verification_status')) {
            $sort_by = $request->verification_status;
            $sellers = $sellers->where('verification_status', $sort_by);
        }
        $sellers = $sellers->paginate(10);
        return view('backend.reports.seller_sale_report', compact('sellers', 'sort_by'));
    }

    public function wish_report(Request $request)
    {
        $sort_by = null;
        $products = Product::orderBy('created_at', 'desc');
        if ($request->has('category_id')) {
            $sort_by = $request->category_id;
            $products = $products->where('category_id', $sort_by);
        }
        $products = $products->paginate(10);
        return view('backend.reports.wish_report', compact('products', 'sort_by'));
    }

    public function user_search_report(Request $request)
    {
        $searches = Search::orderBy('count', 'desc')->paginate(10);
        return view('backend.reports.user_search_report', compact('searches'));
    }

    public function commission_history(Request $request)
    {
        $seller_id = null;
        $date_range = null;

        if (Auth::user()->user_type == 'seller') {
            $seller_id = Auth::user()->id;
        }
        if ($request->seller_id) {
            $seller_id = $request->seller_id;
        }

        $commission_history = CommissionHistory::orderBy('created_at', 'desc');

        if ($request->date_range) {
            $date_range = $request->date_range;
            $date_range1 = explode(" / ", $request->date_range);
            $commission_history = $commission_history->where('created_at', '>=', $date_range1[0]);
            $commission_history = $commission_history->where('created_at', '<=', $date_range1[1]);
        }
        if ($seller_id) {

            $commission_history = $commission_history->where('seller_id', '=', $seller_id);
        }

        $commission_history = $commission_history->paginate(10);
        if (Auth::user()->user_type == 'seller') {
            return view('seller.reports.commission_history_report', compact('commission_history', 'seller_id', 'date_range'));
        }
        return view('backend.reports.commission_history_report', compact('commission_history', 'seller_id', 'date_range'));
    }

    public function wallet_transaction_history(Request $request)
    {
        $user_id = null;
        $date_range = null;

        if ($request->user_id) {
            $user_id = $request->user_id;
        }

        $users_with_wallet = User::whereIn('id', function ($query) {
            $query->select('user_id')->from(with(new Wallet)->getTable());
        })->get();

        $wallet_history = Wallet::orderBy('created_at', 'desc');

        if ($request->date_range) {
            $date_range = $request->date_range;
            $date_range1 = explode(" / ", $request->date_range);
            $wallet_history = $wallet_history->where('created_at', '>=', $date_range1[0]);
            $wallet_history = $wallet_history->where('created_at', '<=', $date_range1[1]);
        }
        if ($user_id) {
            $wallet_history = $wallet_history->where('user_id', '=', $user_id);
        }

        $wallets = $wallet_history->paginate(10);

        return view('backend.reports.wallet_history_report', compact('wallets', 'users_with_wallet', 'user_id', 'date_range'));
    }
    
    // sales report 

    protected function filterByCategory( $sort_by, $products )
    {
        return $products->where('category_id', $sort_by);
    }

    protected function filterByDate( $date, $products )
    {
        $startDate = date('Y-m-d', strtotime(explode('to', $date)[0])). '  00:00:00';
        $endDate = date('Y-m-d', strtotime(explode('to', $date)[1])). ' 23:59:59';
        return $products->where('created_at', '>=', $startDate)->where('created_at', '<=', $endDate);
    }
    
    public function inHouseSaleReportCSV(Request $request)
    {
        $sort_by = null;
        $date = null;

        $products = Product::orderBy('num_of_sale', 'desc')->where('added_by', 'admin');

        if ( $request->has('category_id') && $request->category_id !== null ) {
            $sort_by = $request->category_id;
            $products = $this->filterByCategory( $sort_by, $products );
        }

        if( $request->has('date') && $request->date !==  null ){
            $date = $request->date;
            $products = $this->filterByDate( $date, $products );
        }
        $products = $products->get();

        return Excel::download( new InhouseSaleProductExport($products), 'in-house-sales-report.xlsx');
    }

    public function stockReportCSV( Request $request)
    {
        $sort_by = null;
        $date = null;

        $products = Product::orderBy('created_at', 'desc');

        if ( $request->has('category_id') && $request->category_id !== null ) {
            $sort_by = $request->category_id;
            $products = $this->filterByCategory( $sort_by, $products );
        }

        if( $request->has('date') && $request->date !==  null ){
            $date = $request->date;
            $products = $this->filterByDate( $date, $products );
        }
        $products = $products->get();

        return Excel::download( new ProductStockExport($products), 'product-stock.xlsx');  
       
    }



public function salesReport(Request $request)
{
    $sort_by = null;
    $date = null;
    $payment_status = null;
    $delivery_status = null;
    $filtersApplied = false;

    // Start with Order model - EXCLUDE CANCELLED ORDERS
    $ordersQuery = Order::with(['orderDetails.product.main_category', 'orderDetails.product.purchases'])
        ->where('grand_total', '>', 0)
        ->where('delivery_status', '!=', 'cancelled')
        ->orderBy('created_at', 'desc');

    // Category filter
    if ($request->has('category_id') && $request->category_id !== null) {
        $filtersApplied = true;
        $sort_by = $request->category_id;
        $ordersQuery = $ordersQuery->whereHas('orderDetails.product', function ($query) use ($sort_by) {
            $query->where('category_id', $sort_by);
        });
    }

    // Date filter
    if ($request->has('date') && $request->date !== null) {
        $filtersApplied = true;
        $date = $request->date;
        $ordersQuery = $this->filterByDate($date, $ordersQuery);
    }

    // Payment Status filter
    if ($request->has('payment_status') && $request->payment_status !== null) {
        $filtersApplied = true;
        $payment_status = $request->payment_status;
        $ordersQuery = $ordersQuery->where('payment_status', $payment_status);
    }

    // Delivery Status filter
    if ($request->has('delivery_status') && $request->delivery_status !== null) {
        $filtersApplied = true;
        $delivery_status = $request->delivery_status;
        
        if ($delivery_status == 'cancelled') {
            $ordersQuery = Order::with(['orderDetails.product.main_category', 'orderDetails.product.purchases'])
                ->where('grand_total', '>', 0)
                ->where('delivery_status', 'cancelled')
                ->orderBy('created_at', 'desc');
                
            if ($request->has('category_id') && $request->category_id !== null) {
                $ordersQuery = $ordersQuery->whereHas('orderDetails.product', function ($query) use ($sort_by) {
                    $query->where('category_id', $sort_by);
                });
            }
            if ($request->has('date') && $request->date !== null) {
                $ordersQuery = $this->filterByDate($date, $ordersQuery);
            }
            if ($request->has('payment_status') && $request->payment_status !== null) {
                $ordersQuery = $ordersQuery->where('payment_status', $payment_status);
            }
        } else {
            $ordersQuery = $ordersQuery->where('delivery_status', $delivery_status);
        }
    }

    // Clone the query for totals calculation
    $totalsQuery = clone $ordersQuery;
    
    $allMatchingOrders = $totalsQuery->with(['orderDetails.product.purchases'])->get();
    
    $totalBuyingCost = 0;
    $totalProfit = 0;
    $totalSale = 0;
    $totalOrder = $allMatchingOrders->count();
    $totalDiscount = 0;
    $totaldeliveryCharge = 0;
    
    foreach ($allMatchingOrders as $order) {
        $orderBuyingCost = 0;
        $orderProductSales = 0;
        
        // Calculate buying cost from order details
        foreach ($order->orderDetails as $orderDetail) {
            $buying_unit_cost = $orderDetail->buying_price ?? 0;
            
            if ($buying_unit_cost == 0 && $orderDetail->product && $orderDetail->product->purchases->isNotEmpty()) {
                $buying_unit_cost = $orderDetail->product->purchases->first()->buying_unit_cost ?? 0;
            }
            
            $orderBuyingCost += $buying_unit_cost * $orderDetail->quantity;
            $orderProductSales += $orderDetail->price; // This is original price without discount
        }
        
        // Apply coupon discount proportionally or use grand_total
        // Method 1: Use grand_total as the actual sale amount (includes discount)
         $shippingCostOrder = $order->orderDetails->first()->shipping_cost ?? 0;
        $actualSaleAmount = $order->grand_total - $shippingCostOrder;
        
        // Calculate profit based on actual sale amount minus buying cost
        $orderProfit = $actualSaleAmount - $orderBuyingCost;
        
        // Alternative Method 2: Calculate discount amount and show original vs discounted
        $discountAmount = $order->coupon_discount ?? 0;
        $orderProductSalesAfterDiscount = $orderProductSales - $discountAmount;
       
        // Add to totals using grand_total (recommended)
        $totalBuyingCost += $orderBuyingCost;
        $totalProfit += $orderProfit;
        $totaldeliveryCharge += $shippingCostOrder;
        $totalSale += $actualSaleAmount; // Use grand_total for total sale amount
        
        // Store for debugging/comparison if needed
        $order->original_product_sales = $orderProductSales;
        $order->discount_amount = $discountAmount;
    }

    // Paginate the orders for display
    $orders = $ordersQuery->paginate($request->per_page ?? 50);
    
    // Calculate per-order values for displayed orders
    // foreach ($orders as $order) {
    //     $orderBuyingCost = 0;
    //     $orderProductSales = 0;
        
    //     foreach ($order->orderDetails as $orderDetail) {
    //         $buying_unit_cost = $orderDetail->buying_price ?? 0;
            
    //         if ($buying_unit_cost == 0 && $orderDetail->product && $orderDetail->product->purchases->isNotEmpty()) {
    //             $buying_unit_cost = $orderDetail->product->purchases->first()->buying_unit_cost ?? 0;
    //         }
            
    //         $orderBuyingCost += $buying_unit_cost * $orderDetail->quantity;
    //         $orderProductSales += $orderDetail->price;
    //     }
        
    //     // Use grand_total which already includes coupon discount
    //     $actualSaleAmount = $order->grand_total;
    //     $orderProfit = $actualSaleAmount - $orderBuyingCost;
    //     $discountAmount = $order->coupon_discount ?? 0;

    //     // Store calculated values on the order object
    //     $order->calculated_buying_cost = $orderBuyingCost;
    //     $order->calculated_product_sales = $orderProductSales;
    //     $order->calculated_net_sale = $actualSaleAmount; // Changed to grand_total
    //     $order->calculated_profit = $orderProfit;
    //     $order->calculated_discount = $discountAmount;
    // }
    
    // Calculate per-order values for displayed orders
    foreach ($orders as $order) {
        $orderBuyingCost = 0;
        $orderProductSales = 0;
        
        foreach ($order->orderDetails as $orderDetail) {
            $buying_unit_cost = $orderDetail->buying_price ?? 0;
            
            if ($buying_unit_cost == 0 && $orderDetail->product && $orderDetail->product->purchases->isNotEmpty()) {
                $buying_unit_cost = $orderDetail->product->purchases->first()->buying_unit_cost ?? 0;
            }
            
            $orderBuyingCost += $buying_unit_cost * $orderDetail->quantity;
            $orderProductSales += $orderDetail->price;
        }
        
        // Use grand_total which already includes coupon discount
        
        $shippingcostorderdetails = $order->orderDetails->first()->shipping_cost ?? 0;
        $actualSaleAmount = $order->grand_total - $shippingcostorderdetails;
        $orderProfit = $actualSaleAmount - $orderBuyingCost;
        $discountAmount = $order->coupon_discount ?? 0;
    
        // Store calculated values on the order object
        $order->calculated_buying_cost = $orderBuyingCost;
        $order->calculated_product_sales = $orderProductSales;
        $order->calculated_net_sale = $actualSaleAmount;
        $order->calculated_profit = $orderProfit;
        $order->calculated_discount = $discountAmount; // ADD THIS LINE
        $totalDiscount += $order->coupon_discount ?? 0;
    }

    return view('backend.reports.sales_report', compact(
        'orders', 
        'sort_by', 
        'date', 
        'totalSale', 
        'totalOrder', 
        'totalBuyingCost', 
        'totalProfit', 
        'payment_status', 
        'delivery_status',
        'totalDiscount',
        'totaldeliveryCharge'
    ));
}

public function salesReportCSV(Request $request)
{
    $sort_by = null;
    $date = null;
    $payment_status = null;
    $delivery_status = null;

    // Start with base query - EXCLUDE CANCELLED ORDERS (EXACT same logic as salesReport)
    $ordersQuery = Order::with(['orderDetails.product.main_category', 'orderDetails.product.purchases', 'user'])
        ->where('grand_total', '>', 0)
        ->where('delivery_status', '!=', 'cancelled')
        ->orderBy('created_at', 'desc');

    // Category filter
    if ($request->has('category_id') && $request->category_id !== null) {
        $sort_by = $request->category_id;
        $ordersQuery = $ordersQuery->whereHas('orderDetails.product', function ($query) use ($sort_by) {
            $query->where('category_id', $sort_by);
        });
    }

    // Date filter
    if ($request->has('date') && $request->date !== null) {
        $date = $request->date;
        $ordersQuery = $this->filterByDate($date, $ordersQuery);
    }

    // Payment Status filter
    if ($request->has('payment_status') && $request->payment_status !== null) {
        $payment_status = $request->payment_status;
        $ordersQuery = $ordersQuery->where('payment_status', $payment_status);
    }

    // Delivery Status filter
    if ($request->has('delivery_status') && $request->delivery_status !== null) {
        $delivery_status = $request->delivery_status;
        
        if ($delivery_status == 'cancelled') {
            // Remove the default cancelled exclusion to show only cancelled orders
            $ordersQuery = Order::with(['orderDetails.product.main_category', 'orderDetails.product.purchases', 'user'])
                ->where('grand_total', '>', 0)
                ->where('delivery_status', 'cancelled')
                ->orderBy('created_at', 'desc');
                
            // Re-apply other filters
            if ($request->has('category_id') && $request->category_id !== null) {
                $ordersQuery = $ordersQuery->whereHas('orderDetails.product', function ($query) use ($sort_by) {
                    $query->where('category_id', $sort_by);
                });
            }
            if ($request->has('date') && $request->date !== null) {
                $ordersQuery = $this->filterByDate($date, $ordersQuery);
            }
            if ($request->has('payment_status') && $request->payment_status !== null) {
                $ordersQuery = $ordersQuery->where('payment_status', $payment_status);
            }
        } else {
            // For non-cancelled statuses, keep the default exclusion
            $ordersQuery = $ordersQuery->where('delivery_status', $delivery_status);
        }
    }

    // Clone the query for totals calculation (EXACT same logic as salesReport)
    $totalsQuery = clone $ordersQuery;
    
    // Calculate totals across ALL orders that match the filters
    $allMatchingOrders = $totalsQuery->with(['orderDetails.product.purchases'])->get();
    
    $totalBuyingCost = 0;
    $totalProfit = 0;
    $totalSale = 0;
    $totalOrder = $allMatchingOrders->count();
    $totalDiscount = 0;
    $totalSubtotal = 0;
    $totaldeliveryCharge = 0;
    
    foreach ($allMatchingOrders as $order) {
        $orderBuyingCost = 0;
        $orderProductSales = 0;
        
        // Calculate buying cost and product sales for all order details
        foreach ($order->orderDetails as $orderDetail) {
            $buying_unit_cost = $orderDetail->buying_price ?? 0;
            
            // If buying_price is not set in order_details, try to get from product purchases
            if ($buying_unit_cost == 0 && $orderDetail->product && $orderDetail->product->purchases->isNotEmpty()) {
                $buying_unit_cost = $orderDetail->product->purchases->first()->buying_unit_cost ?? 0;
            }
            
            $orderBuyingCost += $buying_unit_cost * $orderDetail->quantity;
            $orderProductSales += $orderDetail->price; // Original price without discount
        }
        
         $shippingCostOrder = $order->orderDetails->first()->shipping_cost ?? 0;
        
        // Use grand_total as actual sale amount (includes discount)
        $actualSaleAmount = $order->grand_total - $shippingCostOrder;
        $discountAmount = $order->coupon_discount ?? 0;
        $orderProfit = $actualSaleAmount - $orderBuyingCost;

        // Add to totals
        $totalBuyingCost += $orderBuyingCost;
        $totalProfit += $orderProfit;
        $totalSale += $actualSaleAmount; // Use grand_total
        $totalSubtotal += $orderProductSales;
        $totalDiscount += $discountAmount;
        $totaldeliveryCharge += $shippingCostOrder;
        
        // Store calculated values on the order object for export
        $order->calculated_buying_cost = $orderBuyingCost;
        $order->calculated_product_sales = $orderProductSales; // Subtotal before discount
        $order->calculated_net_sale = $actualSaleAmount; // Total after discount
        $order->calculated_profit = $orderProfit;
        $order->calculated_discount = $discountAmount;
    }

    // Get all orders for export (not paginated)
    $orders = $allMatchingOrders;

    return Excel::download(new SalesReportExport($orders, $totalSale, $totalOrder, $totalBuyingCost, $totalProfit, $totalDiscount, $totalSubtotal,$totaldeliveryCharge), 'sales-report.xlsx');
}


}
