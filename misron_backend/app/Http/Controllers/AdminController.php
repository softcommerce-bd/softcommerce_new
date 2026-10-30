<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Upload;
use App\Models\User;
use Artisan;
use Cache;
use Carbon\Carbon;
use DB;
use App\Models\Draft;
use App\Http\Controllers\OrderController;
use App\Jobs\ThirdpartyCourierStatus;

class AdminController extends Controller
{
    /**
     * Show the admin dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    public function doughnutData(Request $request)
    {
        $filter = $request->get('filter', 'month'); // default = month
        $now = Carbon::now();

        // Base query for the selected time filter
        $query = Order::query();

        if ($filter === 'day') {
            $query->whereDate('created_at', $now->toDateString());
        } elseif ($filter === 'month') {
            $query->whereYear('created_at', $now->year)
                ->whereMonth('created_at', $now->month);
        } elseif ($filter === 'year') {
            $query->whereYear('created_at', $now->year);
        }

        // Define display labels
        // $labels = ['Pending', 'Delivered', 'Placed', 'Cancelled', 'Courier'];
        $labels = ['Pending', 'Delivered', 'Cancelled', 'Others'];

        // Fetch counts
        $values = [];

        $values[] = (clone $query)->where('delivery_status', 'pending')->count();

        $values[] = (clone $query)->where('delivery_status', 'delivered')->count();

        // $values[] = (clone $query)->where('delivery_status', '!=', 'cancelled')->count();

        $values[] = (clone $query)->where('delivery_status', 'cancelled')->count();

        $values[] = (clone $query)->whereIn('delivery_status', ['steadfast', 'pathao','courier_assigned'])->count();

        $total = (clone $query)->count(); // since 'Placed' represents all orders

        return response()->json([
            'labels' => $labels,
            'values' => $values,
            'total' => $total,
        ]);
    }

    public function salesChartData(Request $request)
    {
        $filter = $request->get('filter', 'month'); // default = month
        $now = Carbon::now();

        if ($filter === 'day') {
            // Last 7 days
            $days = collect(range(0, 6))
                ->map(fn($i) => $now->copy()->subDays($i))
                ->reverse();

            $labels = $days->map(fn($d) => $d->format('d'))->toArray();
        } elseif ($filter === 'year') {
            // Months of current year (Jan–Dec)
            $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        } else {
            // Last 7 months
            $months = collect(range(0, 6))
                ->map(fn($i) => $now->copy()->subMonths($i))
                ->reverse();

            $labels = $months->map(fn($m) => $m->format('M'))->toArray();
        }

        // $sellerSales = [];
        $inhouseSales = [];

        // --------------------------
        // FETCH DYNAMIC DATA
        // --------------------------
        if ($filter === 'day') {
            foreach ($days as $date) {
                // $sellerSales[]  = (float) $this->getSales($date, 'seller', $filter);
                $inhouseSales[] = (float) $this->getSales($date, 'admin', $filter);
            }
        } elseif ($filter === 'year') {
            foreach (range(1, 12) as $month) {
                $date = Carbon::create($now->year, $month, 1);

                // $sellerSales[]  = (float) $this->getSales($date, 'seller', $filter);
                $inhouseSales[] = (float) $this->getSales($date, 'admin', $filter);
            }
        } else {
            foreach ($months as $date) {
                // $sellerSales[]  = (float) $this->getSales($date, 'seller', $filter);
                $inhouseSales[] = (float) $this->getSales($date, 'admin', $filter);
            }
        }

        return response()->json([
            'labels'   => array_values($labels),
            // 'seller'   => array_map('floatval', $sellerSales),
            'inhouse'  => array_map('floatval', $inhouseSales),
        ]);
    }

    private function getSales($date, $type, $filter)
    {
        $query = Order::select(DB::raw('COALESCE(SUM(grand_total),0) as total'))
            ->leftJoin('users', 'orders.seller_id', '=', 'users.id')
            ->when($type === 'seller', fn($q) => $q->where('users.user_type', 'seller'))
            ->when($type === 'admin', fn($q) => $q->where('users.user_type', 'admin'));

        if ($filter === 'day') {
            $query->whereDate('orders.created_at', $date);
        }

        if ($filter === 'month') {
            $query->whereYear('orders.created_at', $date->year)
                ->whereMonth('orders.created_at', $date->month);
        }

        if ($filter === 'year') {
            $query->whereYear('orders.created_at', $date->year)
                ->whereMonth('orders.created_at', $date->month);
        }

        return $query->value('total');
    }

    public function getTopCategories(Request $request)
{
    $filter = $request->get('filter', 'month');

    $query = Product::select(
            'categories.id',
            'categories.name',
            DB::raw('SUM(order_details.price * order_details.quantity) as total_sales'),
            DB::raw('SUM(order_details.quantity) as num_of_sale')
        )
        ->join('categories', 'products.category_id', '=', 'categories.id')
        ->join('order_details', 'order_details.product_id', '=', 'products.id')
        ->join('orders', 'orders.id', '=', 'order_details.order_id')
        ->where(function ($q) {
            $q->where('orders.delivery_status', 'delivered')
              ->orWhere('orders.steadfast_status', 'delivered');
        });

    // Date filter
    if ($filter === 'day') {
        $query->whereDate('orders.created_at', today());
    } elseif ($filter === 'month') {
        $query->whereYear('orders.created_at', now()->year)
              ->whereMonth('orders.created_at', now()->month);
    } elseif ($filter === 'year') {
        $query->whereYear('orders.created_at', now()->year);
    }

    $topCategories = $query
        ->groupBy('categories.id', 'categories.name')
        ->orderByDesc('total_sales')
        ->limit(6)
        ->get();

        // dd($topCategories);
    return response()->json($topCategories);
}

    public function getTopSellingProducts(Request $request)
    {
        $filter = $request->get('filter', 'month');

        $query = Product::select(
            'products.id',
            'products.name',
            'products.thumbnail_img',
            'products.num_of_sale',
            'products.rating',
            DB::raw('SUM(order_details.quantity) as units_sold'),
            DB::raw('SUM(order_details.price * order_details.quantity) as revenue'),
        )
            ->leftJoin('order_details', 'order_details.product_id', '=', 'products.id')
            ->leftJoin('orders', 'orders.id', '=', 'order_details.order_id')
            ->where(function ($q) {
                $q->where('orders.delivery_status', 'delivered')
                ->orWhere('orders.steadfast_status', 'delivered');
            });

        // Filter by time range
        if ($filter === 'day') {
            $query->whereDate('orders.created_at', today());
        } elseif ($filter === 'month') {
            $query->whereMonth('orders.created_at', now()->month)
                ->whereYear('orders.created_at', now()->year);
        } elseif ($filter === 'year') {
            $query->whereYear('orders.created_at', now()->year);
        }

        $data = $query
            ->groupBy('products.id', 'products.name', 'products.thumbnail_img')
            ->orderByDesc('revenue')
            ->take(6)
            ->get();

        $data = $data->map(function ($product) {
                $product->thumbnail_img = $product->thumbnail_img
                    ? uploaded_asset($product->thumbnail_img)
                    : static_asset('assets/img/placeholder.jpg');

                return $product;
            });

        return response()->json($data);
    }

    public function admin_dashboard(Request $request, OrderController $orderController)
    {
        $root_categories = Category::where('level', 0)->get();

        // first section
        $currentMonth = Carbon::now();
        $previousMonth = Carbon::now()->subMonth();

        //courier stats
        $data['pathao_stats'] = $this->getCourierStats('pathao', $currentMonth, $previousMonth);
        $data['steadfast_stats'] = $this->getCourierStats('steadfast', $currentMonth, $previousMonth);

        // --- Sale calculation ---
        $data['sale_this_month'] = Order::whereYear('created_at', $currentMonth->year)
            ->whereMonth('created_at', $currentMonth->month)
            ->sum('grand_total');

        $data['sale_previous_month'] = Order::whereYear('created_at', $previousMonth->year)
            ->whereMonth('created_at', $previousMonth->month)
            ->sum('grand_total');

        if ($data['sale_previous_month'] == 0) {
            // Avoid division by zero
            $data['sale_percent'] = $data['sale_this_month'] > 0 ? 100 : 0;
        } else {
            $data['sale_percent'] = (($data['sale_this_month'] - $data['sale_previous_month']) / $data['sale_previous_month']) * 100;
        }

        $data['sale_percent'] = round($data['sale_percent'], 2);

        // --- Order calculations ---
        $data['total_order'] = Order::count();
        $data['order_this_month'] = Order::whereYear('created_at', $currentMonth->year)
            ->whereMonth('created_at', $currentMonth->month)
            ->count();

        $data['order_previous_month'] = Order::whereYear('created_at', $previousMonth->year)
            ->whereMonth('created_at', $previousMonth->month)
            ->count();

        if ($data['order_previous_month'] == 0) {
            // Avoid division by zero
            $data['order_percent'] = $data['order_this_month'] > 0 ? 100 : 0;
        } else {
            $data['order_percent'] = (($data['order_this_month'] - $data['order_previous_month']) / $data['order_previous_month']) * 100;
        }
        $data['order_percent'] = round($data['order_percent'], 2);

        // --- Average calculations ---
        $data['avg_order_this_month'] = $data['order_this_month'] > 0
            ? $data['sale_this_month'] / $data['order_this_month']
            : 0;

        $data['avg_order_previous_month'] = $data['order_previous_month'] > 0
            ? $data['sale_previous_month'] / $data['order_previous_month']
            : 0;

        // --- Average order value percentage change ---
        if ($data['avg_order_previous_month'] == 0) {
            $data['avg_order_percent'] = $data['avg_order_this_month'] > 0 ? 100 : 0;
        } else {
            $data['avg_order_percent'] = (($data['avg_order_this_month'] - $data['avg_order_previous_month']) / $data['avg_order_previous_month']) * 100;
        }
        $data['avg_order_percent'] = round($data['avg_order_percent'], 2);
        // first section end


        $data['cached_graph_data'] = Cache::remember('cached_graph_data', 86400, function () use ($root_categories) {
            $num_of_sale_data = null;
            $qty_data = null;
            foreach ($root_categories as $key => $category) {
                $category_ids = \App\Utility\CategoryUtility::children_ids($category->id);
                $category_ids[] = $category->id;

                $products = Product::with('stocks')->whereIn('category_id', $category_ids)->get();
                $qty = 0;
                $sale = 0;
                foreach ($products as $key => $product) {
                    $sale += $product->num_of_sale;
                    foreach ($product->stocks as $key => $stock) {
                        $qty += $stock->qty;
                    }
                }
                $qty_data .= $qty . ',';
                $num_of_sale_data .= $sale . ',';
            }
            $item['num_of_sale_data'] = $num_of_sale_data;
            $item['qty_data'] = $qty_data;

            return $item;
        });

        $data['root_categories'] = $root_categories;

        $data['total_customers'] = User::where('user_type', 'customer')->count();
        $data['top_customers'] = User::select(
                'users.id',
                'users.name',
                'users.avatar_original',
                DB::raw('SUM(orders.grand_total) as total')
            )
            ->join('orders', 'orders.user_id', '=', 'users.id')
            ->where('users.user_type', 'customer')
            ->where('orders.delivery_status', '!=', 'cancelled')
            ->groupBy('users.id', 'users.name', 'users.avatar_original')
            ->orderByDesc('total')
            ->limit(6)
            ->get();
        // --- Customer Analytics Data ---
        $data['customer_analytics'] = $this->getCustomerAnalytics($currentMonth, $previousMonth);

        $data['total_products'] = Product::count();
        $data['total_order_today'] = Order::whereDate('created_at', today())->count();
        $data['total_order_amount_today'] = Order::whereDate('created_at', today())->sum('grand_total');
        $data['total_inhouse_products'] = Product::where('approved', 1)->where('published', 1)->where('added_by', 'admin')->count();
        $data['total_sellers_products'] = Product::where('approved', 1)->where('published', 1)->where('added_by', '!=', 'admin')->count();
        $data['total_categories'] = Category::count();
        $data['seller_categories'] = Category::where('added_by', '!=', 'admin')->count();
        $data['inhouse_categories'] = Category::where('added_by', 'admin')->count();
        $data['top_categories'] = Product::select('categories.name', 'categories.id', DB::raw('SUM(grand_total) as total'))
            ->leftJoin('order_details', 'order_details.product_id', '=', 'products.id')
            ->leftJoin('orders', 'orders.id', '=', 'order_details.order_id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('orders.delivery_status', 'delivered')
            ->groupBy('categories.id')
            ->orderBy('total', 'desc')
            ->limit(3)
            ->get();
        $data['total_brands'] = Brand::count();
        $data['today_incomplete_orders'] = Draft::whereDate('created_at', today())->count();
        $data['seller_brands'] = Brand::where('added_by', '!=', 'admin')->count();
        $data['inhouse_brands'] = Brand::where('added_by', 'admin')->count();
        $data['top_brands'] = Product::select('brands.name', 'brands.id', DB::raw('SUM(grand_total) as total'))
            ->leftJoin('order_details', 'order_details.product_id', '=', 'products.id')
            ->leftJoin('orders', 'orders.id', '=', 'order_details.order_id')
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->where('orders.delivery_status', 'delivered')
            ->groupBy('brands.id')
            ->orderBy('total', 'desc')
            ->limit(3)
            ->get();
        $data['total_sale'] = Order::where('delivery_status', 'delivered')->sum('grand_total');

        //out of stock
        $baseOutOfStockQuery = function ($extraFilter = null) {
            return Product::withSum(['stocks as total_stock'], 'qty')
                ->when($extraFilter, function ($q) use ($extraFilter) {
                    $extraFilter($q);
                })
                ->having('total_stock', '<', 1);
        };

        $data['total_out_of_stock'] = $baseOutOfStockQuery()->count();

        $data['seller_out_of_stock'] = $baseOutOfStockQuery(function ($q) {
            $q->where('added_by', '!=', 'admin');
        })->count();

        $data['inhouse_out_of_stock'] = $baseOutOfStockQuery(function ($q) {
            $q->where('added_by', 'admin');
        })->count();

        $data['admin_sale_this_month'] = Order::select(DB::raw('COALESCE(users.user_type, "admin") as user_type'), DB::raw('COALESCE(SUM(grand_total), 0) as total_sale'))
            ->leftJoin('users', 'orders.seller_id', '=', 'users.id')
            ->whereRaw('users.user_type = "admin"')
            ->whereMonth('orders.created_at', Carbon::now()->month)
            ->first();
        $data['seller_sale_this_month'] = Order::select(DB::raw('COALESCE(users.user_type, "seller") as user_type'), DB::raw('COALESCE(SUM(grand_total), 0) as total_sale'))
            ->leftJoin('users', 'orders.seller_id', '=', 'users.id')
            ->whereRaw('users.user_type = "seller"')
            ->whereMonth('orders.created_at', Carbon::now()->month)
            ->first();
        $sales_stat = Order::select('orders.user_id', 'users.name', 'users.user_type', 'users.avatar_original', DB::raw('SUM(grand_total) as total'), DB::raw('DATE_FORMAT(orders.created_at, "%M") AS month'))
            ->leftJoin('users', 'orders.seller_id', '=', 'users.id')
            ->whereRaw('users.user_type = "admin"')
            ->whereYear('orders.created_at', '=', Date("Y"))
            // ->orWhereRaw('users.user_type = "seller"')
            // ->groupBy('users.user_type')
            ->groupBy('month')
            ->orderBy(DB::raw('MONTH(orders.created_at)'), 'asc')
            ->get();
        $new_stat = array();
        foreach ($sales_stat as $row) {
            $new_stat[$row->month][] = $row;
        }
        $data['sales_stat'] = $new_stat;
        // dd($sales_stat);
        // "SELECT users.user_type, SUM(grand_total) FROM `orders` LEFT JOIN users ON orders.seller_id = users.id WHERE users.user_type = 'admin' OR users.user_type = 'seller' GROUP BY users.user_type, MONTH(orders.created_at)";
        $data['total_sellers'] = User::where('user_type', 'seller')->where('email_verified_at', '!=', null)->count();
        $data['status_wise_sellers'] = Shop::select('verification_status', DB::raw('COUNT(*) as total'))
            ->whereIn('user_id', function ($q) {
                $q->select('id')
                    ->from(with(new User)->getTable())
                    ->where('user_type', 'seller')
                    ->where('email_verified_at', '!=', null);
            })
            ->groupBy('verification_status')
            ->get();
        $data['top_sellers'] = Order::select('orders.seller_id', 'users.name', 'users.user_type', 'users.avatar_original', DB::raw('SUM(grand_total) as total'))
            ->leftJoin('users', 'orders.seller_id', '=', 'users.id')
            ->whereRaw('users.user_type = "seller"')
            ->groupBy('users.id')
            ->orderBy('total', 'desc')
            ->limit(6)
            ->get();


        $data['total_placed_order'] = Order::where('delivery_status', '!=', 'cancelled')->count();
        $data['total_pending_order'] = Order::where('delivery_status', 'pending')->count();
        $data['total_confirmed_order'] = Order::where('delivery_status', 'confirmed')->count();
        $data['total_picked_up_order'] = Order::where('delivery_status', 'picked_up')->count();
        $data['total_shipped_order'] = Order::where('delivery_status', 'on_the_way')->count();
        $admin_id = User::select('id')->where('user_type', 'admin')->first()->id;
        $data['total_inhouse_sale'] = Order::where("seller_id", $admin_id)->sum('grand_total');
        $data['payment_type_wise_inhouse_sale'] = Order::select(DB::raw('case
                                                    when payment_type in ("wallet") then "wallet"
                                                    when payment_type NOT in ("cash_on_delivery") then "others"
                                                    else cast(payment_type as char)
                                                    end as payment_type, SUM(grand_total)  as total_amount'),)
            ->where("user_id", '!=', null)
            ->where("seller_id", $admin_id)
            ->groupBy(DB::raw('1'))
            ->get();
        $data['inhouse_product_rating'] = Product::where('added_by', 'admin')->where('rating', '!=', 0)->avg('rating');
        $data['total_inhouse_order'] = Order::where("seller_id", $admin_id)->count();


        // dd($data['payment_type_wise_inhouse_sale']);
        return view('backend.dashboard_new', $data);
    }


    private function getCustomerAnalytics($currentMonth, $previousMonth)
    {
        // Cart Abandonment Rate Calculation
        $currentMonthAbandonment = $this->calculateCartAbandonmentRate($currentMonth);
        $previousMonthAbandonment = $this->calculateCartAbandonmentRate($previousMonth);

        // Repeat Purchase Rate Calculation
        $currentMonthRepeat = $this->calculateRepeatPurchaseRate($currentMonth);
        $previousMonthRepeat = $this->calculateRepeatPurchaseRate($previousMonth);

        // Customer Lifetime Value Data
        $clvData = $this->getCustomerLifetimeValueData();

        return [
            'cart_abandonment_rate' => $currentMonthAbandonment['rate'],
            'cart_abandonment_change' => $this->calculatePercentageChange(
                $currentMonthAbandonment['rate'],
                $previousMonthAbandonment['rate']
            ),
            'repeat_purchase_rate' => $currentMonthRepeat['rate'],
            'repeat_purchase_change' => $this->calculatePercentageChange(
                $currentMonthRepeat['rate'],
                $previousMonthRepeat['rate']
            ),
            'clv_data' => $clvData
        ];
    }

    private function calculateCartAbandonmentRate($month)
    {
        $drafts = \App\Models\Draft::whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->count();

        $orders = \App\Models\Order::whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->count();

        $totalAttempts = $drafts + $orders;

        if ($totalAttempts === 0) {
            return ['rate' => 0, 'total' => 0, 'abandoned' => 0];
        }

        $abandonmentRate = ($drafts / $totalAttempts) * 100;

        return [
            'rate' => round($abandonmentRate, 1),
            'total' => $totalAttempts,
            'abandoned' => $drafts
        ];
    }

    private function calculateRepeatPurchaseRate($month)
    {
        // Get order count per customer in the month
        $customerOrders = Order::whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->where('delivery_status', 'delivered')
            ->select('user_id', DB::raw('COUNT(*) as order_count'))
            ->groupBy('user_id')
            ->get();

        if ($customerOrders->count() == 0) {
            return ['rate' => 0, 'total' => 0, 'repeat' => 0];
        }

        // total customers
        $totalCustomers = $customerOrders->count();

        // customers who purchased more than once
        $repeatCustomers = $customerOrders->where('order_count', '>', 1)->count();

        // percentage calculation
        $repeatRate = ($repeatCustomers / $totalCustomers) * 100;

        return [
            'rate' => round($repeatRate, 1),
            'total' => $totalCustomers,
            'repeat' => $repeatCustomers
        ];
    }

    private function getCustomerLifetimeValueData()
    {
        $months = [];
        $values = [];

        for ($i = 6; $i >= 0; $i--) {

            $month = Carbon::now()->subMonths($i);
            $monthName = $month->format('M');

            $data = Order::whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->where('delivery_status', '!=', 'cancelled')
                    ->select(
                        DB::raw('COUNT(DISTINCT COALESCE(user_id, guest_id)) as customers'),
                        DB::raw('SUM(grand_total) as revenue')
                    )
                    ->first();

            $clv = ($data->customers > 0)
                ? $data->revenue / $data->customers
                : 0;

            $months[] = $monthName;
            $values[] = round($clv, 2);
        }

        return [
            'labels' => $months,
            'values' => $values
        ];
    }

    private function calculatePercentageChange($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        $change = (($current - $previous) / $previous) * 100;

        return round($change, 2);
    }

    // private function getCourierStats($courier, $currentMonth, $previousMonth)
    // {
    //     $baseQuery = Order::where('courier_name', $courier);

    //     // Total orders
    //     $totalOrders = (clone $baseQuery)->count();

    //     // Current month
    //     $currentMonthOrders = (clone $baseQuery)
    //         ->whereYear('created_at', $currentMonth->year)
    //         ->whereMonth('created_at', $currentMonth->month)
    //         ->count();

    //     // Previous month
    //     $previousMonthOrders = (clone $baseQuery)
    //         ->whereYear('created_at', $previousMonth->year)
    //         ->whereMonth('created_at', $previousMonth->month)
    //         ->count();

    //     // Percent change
    //     if ($previousMonthOrders == 0) {
    //         $orderPercent = $currentMonthOrders > 0 ? 100 : 0;
    //     } else {
    //         $orderPercent = (($currentMonthOrders - $previousMonthOrders) / $previousMonthOrders) * 100;
    //     }

    //     // Status counts
    //     $pending = (clone $baseQuery)
    //         ->whereIn('courier_status', ['pending', 'accepted', 'picked', 'on_the_way', 'in_review'])
    //         ->count();

    //     $delivered = (clone $baseQuery)
    //         ->whereIn('courier_status', ['delivered', 'partial_delivered'])
    //         ->count();

    //     $canceled = (clone $baseQuery)
    //         ->where('courier_status', 'cancelled')
    //         ->count();

    //     return [
    //         'total_orders' => $totalOrders,
    //         'current_month_orders' => $currentMonthOrders,
    //         'previous_month_orders' => $previousMonthOrders,
    //         'order_percent' => round($orderPercent, 2),
    //         'canceled_orders' => $canceled,
    //         'pending_orders' => $pending,
    //         'delivered_orders' => $delivered,
    //     ];
    // }
    private function getCourierStats($courier, $currentMonth, $previousMonth)
    {
        $baseQuery = Order::where('courier_name', $courier);

        // Total orders
        $totalOrders = (clone $baseQuery)->count();

        // Current month
        $currentMonthOrders = (clone $baseQuery)
            ->whereYear('created_at', $currentMonth->year)
            ->whereMonth('created_at', $currentMonth->month)
            ->count();

        // Previous month
        $previousMonthOrders = (clone $baseQuery)
            ->whereYear('created_at', $previousMonth->year)
            ->whereMonth('created_at', $previousMonth->month)
            ->count();

        // Percent change
        if ($previousMonthOrders == 0) {
            $orderPercent = $currentMonthOrders > 0 ? 100 : 0;
        } else {
            $orderPercent = (($currentMonthOrders - $previousMonthOrders) / $previousMonthOrders) * 100;
        }

        // Cancelled: highest priority
        $cancelled = (clone $baseQuery)
            ->where(function($query) {
                $query->whereIn('courier_status', ['partial_delivered','cancelled']);
            })
            ->count();

        // Delivered: not cancelled, but delivered in either status
        $delivered = (clone $baseQuery)
            ->where(function($query) {
                $query->where('courier_status', 'delivered')
                      ->orWhere('delivery_status', 'delivered');
            })
            ->where(function($query) {
                $query->where('courier_status', '!=', 'cancelled')
                      ->where('delivery_status', '!=', 'cancelled');
            })
            ->count();

        // Pending: everything else (not cancelled, not delivered)
        $pending = (clone $baseQuery)
            ->where(function($query) {
                $query->whereIn('courier_status', ['pending', 'accepted', 'picked', 'on_the_way', 'in_review', 'steadfast']);
            })
            ->orWhere(function($query) {
                $query->where('delivery_status', 'pending')
                      ->whereNotIn('courier_status', ['delivered', 'cancelled']);
            })
            ->count();

        return [
            'total_orders' => $totalOrders,
            'current_month_orders' => $currentMonthOrders,
            'previous_month_orders' => $previousMonthOrders,
            'order_percent' => round($orderPercent, 2),
            'cancelled_orders' => $cancelled,
            'pending_orders' => $pending,
            'delivered_orders' => $delivered,
        ];
    }

    public function top_category_products_section(Request $request)
    {
        $top_categories_products = DB::table(DB::raw('(SELECT products.id product_id, products.name product_name, products.slug product_slug, products.auction_product, products.category_id,
                                                        `products`.`thumbnail_img` as `product_thumbnail_img`, od.sales, od.total, od.created_at order_detail_created,
                                                        categories.name AS category_name,
                                                        `categories`.`cover_image`,
                                                        ROW_NUMBER() OVER (PARTITION BY products.category_id ORDER BY od.sales DESC) rn
                                                from products
                                                INNER JOIN (
                                                SELECT product_id, SUM(quantity) sales, SUM(price + tax) AS total, created_at
                                                FROM order_details
                                                WHERE ' . ($request->interval_type == 'all' ?: 'created_at >= DATE_SUB(NOW(), INTERVAL 1 ' . $request->interval_type . ')') . '
                                                AND order_details.delivery_status = "delivered"
                                                GROUP BY product_id
                                                )  od ON od.product_id = products.id
                                                LEFT JOIN categories ON products.category_id = categories.id
                                                ) t'))
            ->select(DB::raw('category_id, category_name, cover_image, product_id, product_name, product_slug, auction_product, product_thumbnail_img, sales, total, order_detail_created'))
            ->where('rn', '<=', 3)
            ->orderBy('sales', 'desc')
            ->get();

        $category_array = [];
        $new_array = array();
        foreach ($top_categories_products as $key => $row) {
            $row->product_thumbnail_img = Upload::where('id', $row->product_thumbnail_img)->first();
            $category_array[] = $row->category_id;
            $new_array[$row->category_id][] = $row;
        }
        $top_categories2 = array_unique($category_array);
        $top_categories_products = $new_array;

        return view('backend.dashboard.top_category_products_section', compact('top_categories2', 'top_categories_products'))->render();
    }

    public function inhouse_top_categories(Request $request)
    {
        $inhouse_top_category_query = Order::query();
        $inhouse_top_category_query->select('categories.id', 'categories.name', 'categories.cover_image', DB::raw('SUM(order_details.price + order_details.tax) as total'))
            ->leftJoin('order_details', 'orders.id', '=', 'order_details.order_id')
            ->leftJoin('products', 'order_details.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('orders.delivery_status', '=', 'delivered')
            ->whereRaw('products.added_by = "admin"');
        if ($request->interval_type != 'all') {
            $inhouse_top_category_query->where('orders.created_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 1 ' . $request->interval_type . ')'));
        }
        $inhouse_top_categories = $inhouse_top_category_query->groupBy('categories.name')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get();

        return view('backend.dashboard.inhouse_top_categories', compact('inhouse_top_categories'))->render();
    }

    public function inhouse_top_brands(Request $request)
    {
        $inhouse_top_brand_query = Order::query();
        $inhouse_top_brand_query->select('brands.id', 'brands.name', 'brands.logo', DB::raw('SUM(order_details.price + order_details.tax) as total'))
            ->leftJoin('order_details', 'orders.id', '=', 'order_details.order_id')
            ->leftJoin('products', 'order_details.product_id', '=', 'products.id')
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->where('orders.delivery_status', '=', 'delivered')
            ->where('products.brand_id', '!=', null)
            ->whereRaw('products.added_by = "admin"');
        if ($request->interval_type != 'all') {
            $inhouse_top_brand_query->where('orders.created_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 1 ' . $request->interval_type . ')'));
        }
        $inhouse_top_brands = $inhouse_top_brand_query->groupBy('brands.name')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get();

        return view('backend.dashboard.inhouse_top_brands', compact('inhouse_top_brands'))->render();
    }

    public function top_sellers_products_section(Request $request)
    {
        // $top_sellers_products = DB::table(DB::raw('(SELECT products.id product_id, products.name product_name, products.user_id,
        //                                                 `products`.`thumbnail_img` as `product_thumbnail_img`, od.sales, od.total, shops.name AS shop_name,
        //                                                 `shops`.`logo`,
        //                                                 ROW_NUMBER() OVER (PARTITION BY products.user_id ORDER BY od.sales DESC) rn
        //                                     from products
        //                                     INNER JOIN (
        //                                         SELECT product_id, SUM(quantity) sales, SUM(price + tax) AS total, created_at
        //                                         FROM order_details
        //                                         WHERE ' . ($request->interval_type == 'all' ?: 'created_at >= DATE_SUB(NOW(), INTERVAL 1 ' . $request->interval_type . ')') . '
        //                                         AND order_details.delivery_status = "delivered"
        //                                         GROUP BY product_id
        //                                     )  od ON od.product_id = products.id
        //                                     LEFT JOIN shops ON products.user_id = shops.user_id
        //                                 ) t'))
        //     ->select(DB::raw('user_id, shop_name, logo, product_id, product_name, product_thumbnail_img, sales, total'))
        //     ->where('rn', '<=', 3)
        //     ->where('shop_name', '!=', null)
        //     ->orderBy('total', 'desc')
        //     ->get();

        $new_top_sellers_query = Order::query();
        $new_top_sellers_query = Order::select('shops.user_id AS shop_id', 'shops.name AS shop_name', 'shops.logo', DB::raw('SUM(grand_total) AS sale'))
            ->join('shops', 'orders.seller_id', '=', 'shops.user_id')
            ->whereIn("seller_id", function ($query) {
                $query->select('id')
                    ->from('users')
                    ->where('user_type', 'seller');
            })
            ->where('orders.delivery_status', 'delivered')
            ->groupBy('orders.seller_id')
            ->orderBy('sale', 'desc');
        if ($request->interval_type != 'all') {
            $new_top_sellers_query->where('orders.created_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 1 ' . $request->interval_type . ')'));
        }

        $new_top_sellers = $new_top_sellers_query->get();

        foreach ($new_top_sellers as $key => $row) {
            $products_query = Product::query();
            $products_query->select('products.id AS product_id', 'products.name', 'products.slug AS product_slug', 'products.auction_product', 'products.thumbnail_img', DB::raw('SUM(quantity) AS total_quantity, SUM(price * quantity) AS sale'))
                ->join('order_details', 'order_details.product_id', '=', 'products.id')
                ->where("seller_id", $row->shop_id)
                ->where('order_details.delivery_status', 'delivered')
                ->where('products.approved', 1)
                ->where('products.published', 1);
            if ($request->interval_type != 'all') {
                $products_query->where('order_details.created_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 1 ' . $request->interval_type . ')'));
            }
            $products_query->groupBy('product_id')
                ->orderBy('sale', 'desc')
                ->limit(3);
            $row->products = $products_query->get();
            // $row->product_thumbnail_img = Upload::where('id', $row->product_thumbnail_img)->first();
            // $seller_array[] = $row->user_id;
            // $new_top_sellers_products_array[$row->user_id][] = $row;
            // echo '<pre>';print_r($new_top_sellers);
        }
        // dd($new_top_sellers);
        // $top_sellers2 = array_unique($seller_array);
        // $top_sellers_products = $new_top_sellers_products_array;

        // return view('backend.dashboard.top_sellers_products_section', compact('top_sellers2', 'top_sellers_products'))->render();
        return view('backend.dashboard.top_sellers_products_section', compact('new_top_sellers'))->render();
    }

    public function top_brands_products_section(Request $request)
    {
        $top_brands_products = DB::table(DB::raw('(SELECT products.id product_id, products.name product_name, products.slug product_slug, products.auction_product, products.brand_id,
                                                        `products`.`thumbnail_img` as `product_thumbnail_img`, od.sales, od.total, brands.name AS brand_name,
                                                        `brands`.`logo`,
                                                        ROW_NUMBER() OVER (PARTITION BY products.brand_id ORDER BY od.sales DESC) rn
                                            from products
                                            INNER JOIN (
                                                SELECT product_id, SUM(quantity) sales, SUM(price + tax) AS total, created_at
                                                FROM order_details
                                                WHERE ' . ($request->interval_type == 'all' ?: 'created_at >= DATE_SUB(NOW(), INTERVAL 1 ' . $request->interval_type . ')') . '
                                                AND order_details.delivery_status = "delivered"
                                                GROUP BY product_id
                                            )  od ON od.product_id = products.id
                                            LEFT JOIN brands ON products.brand_id = brands.id
                                        ) t'))
            ->select(DB::raw('brand_id, brand_name, logo, product_id, product_name, product_slug, auction_product, product_thumbnail_img, sales, total'))
            ->where('rn', '<=', 3)
            ->orderBy('total', 'desc')
            ->where('brand_name', '!=', null)
            ->get();

        $brand_array = [];
        $new_array = [];
        foreach ($top_brands_products as $key => $row) {
            $row->product_thumbnail_img = Upload::where('id', $row->product_thumbnail_img)->first();
            $brand_array[] = $row->brand_id;
            $new_array[$row->brand_id][] = $row;
        }

        $top_brands2 = array_unique($brand_array);
        $top_brands_products = $new_array;

        return view('backend.dashboard.top_brands_products_section', compact('top_brands2', 'top_brands_products'))->render();
    }

    function clearCache(Request $request)
    {
        Artisan::call('optimize:clear');
        flash(translate('Cache cleared successfully'))->success();
        return back();
    }
}
