<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AffiliateController;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Address;
use App\Models\Product;
use App\Models\OrderDetail;
use App\Models\CouponUsage;
use App\Models\Coupon;
use App\Models\User;
use App\Models\CombinedOrder;
use App\Models\SmsTemplate;
use Auth;
use Mail;
use App\Mail\InvoiceEmailManager;
use App\Models\ChartOfAccount;
use App\Models\AccountStatementLog;
use App\Models\CourierStat;
use App\Models\Currency;
use App\Models\Language;
use App\Models\OrdersExport;
use App\Models\PathaoCourierSetting;
use App\Utility\NotificationUtility;
use App\Services\CoreComponentService;
use App\Utility\SmsUtility;
use App\Utility\SendSMSUtility;
use App\Models\SteadfastSetting;
use SteadFast\SteadFastCourierLaravelPackage\Facades\SteadfastCourier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Session;
use CoreComponentRepository;
use App\Models\ProductStock;
use App\Models\PurchaseProduct;
use App\Services\BdCourierService;
use App\Services\Couriers\CourierManager;
use App\Utility\FontUtility;
use Config;
use Exception;
use App\Models\AffiliateUser;
use App\Models\AffiliateOption;
use App\Models\AffiliateLog;
use Illuminate\Support\Facades\Http;
use Mpdf\Mpdf;
use Illuminate\Support\Facades\Cache;
use App\Services\Inventory\Exceptions\InventoryException;
use App\Services\Inventory\StockLedger;

class OrderController extends Controller
{
    /** Batch takes made by the current order edit, so a failed edit can give them back (see orderEditUpdate). */
    protected $editTakes = [];


    public function __construct()
    {
        // Staff Permission Check
        $this->middleware(['permission:view_all_orders|view_inhouse_orders|view_seller_orders|view_pickup_point_orders'])->only('all_orders');
        $this->middleware(['permission:view_order_details'])->only('show');
        $this->middleware(['permission:delete_order'])->only('destroy', 'bulk_order_delete');
    }


    // courier settlement partial payment order start
    // OrderController.php
    public function getOrderItems($orderId)
    {
        $order = Order::with(['orderDetails.product.stocks'])->findOrFail($orderId);

        return response()->json([
            'success' => true,
            'order' => $order->only([
                'id',
                'code',
                'shipping_address',
                'courier_status',
                'payment_status',
                'due_amount'
            ]),
            'orderDetails' => $order->orderDetails->map(function ($detail) {
                return [
                    'id' => $detail->id,
                    'product_id' => $detail->product_id,
                    'product' => [
                        'id' => $detail->product->id,
                        'name' => $detail->product->name,
                        'thumbnail_img' => $detail->product->thumbnail_img,
                    ],
                    'variation' => $detail->variation,
                    'price' => $detail->price,
                    'quantity' => $detail->quantity,
                    'delivery_status' => $detail->delivery_status,
                    'payment_status' => $detail->payment_status,
                ];
            })
        ]);
    }

    public function updateItemsStatus(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'updates' => 'required|array',
            'updates.*.order_detail_id' => 'required|exists:order_details,id',
            'updates.*.delivery_status' => 'required|string|in:delivered,partial_delivered,exchange,return,cancelled,pending',
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($request->order_id);

            foreach ($request->updates as $update) {
                OrderDetail::where('id', $update['order_detail_id'])
                    ->where('order_id', $order->id)
                    ->update([
                        'delivery_status' => $update['delivery_status']
                    ]);
            }

            // Check if all items are delivered to update order status
            $allDelivered = $order->orderDetails()
                ->where('delivery_status', '!=', 'delivered')
                ->doesntExist();

            if ($allDelivered) {
                $order->update([
                    'delivery_status' => 'delivered',
                    'courier_status' => 'delivered'
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order items updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update order items: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update order items'
            ], 500);
        }
    }
    // courier settlement partial payment order end

    public function fixAllPayment(Request $request)
    {
        try {
            DB::beginTransaction();

            // Get all orders that need to be updated
            $orders = Order::where('courier_status', 'delivered')
                ->where('payment_status', '!=', 'paid')
                ->get();

            if ($orders->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No orders found that need payment update'
                ]);
            }

            $updatedOrderIds = [];
            $affiliateStatsUpdated = 0;

            // Update each order
            foreach ($orders as $order) {
                $order->payment_status = 'paid';
                $order->delivery_status = 'delivered';
                $order->save();
                $updatedOrderIds[] = $order->id;

                // Process affiliate commission when order is delivered and paid
                if (addon_is_activated('affiliate_system')) {
                    $affiliateController = new \App\Http\Controllers\AffiliateController();

                    foreach ($order->orderDetails as $orderDetail) {
                        // Update delivered stats for product sharing
                        if ($orderDetail->product_referral_code) {
                            $referred_by_user = User::where('referral_code', $orderDetail->product_referral_code)->first();
                            if ($referred_by_user) {
                                $affiliateUser = \App\Models\AffiliateUser::where('user_id', $referred_by_user->id)->first();
                                if ($affiliateUser && $affiliateUser->status == 1) {
                                    // Track delivered stats
                                    $affiliateController->processAffiliateStats(
                                        $affiliateUser->id,
                                        0,                           // no_click
                                        0,                           // no_item
                                        $orderDetail->quantity,      // no_delivered
                                        0                            // no_cancel
                                    );
                                    $affiliateStatsUpdated++;
                                }
                            }
                        }
                    }

                    // Process first purchase commission for the referred user
                    if ($order->user_id && $order->user->referred_by) {
                        // Check if this is the first order for this user
                        $orderCount = Order::where('user_id', $order->user_id)->count();
                        if ($orderCount == 1) {
                            $affiliateUser = \App\Models\AffiliateUser::where('user_id', $order->user->referred_by)->first();
                            if ($affiliateUser && $affiliateUser->status == 1) {
                                // Calculate commission based on admin settings
                                $commissionSetting = \App\Models\AffiliateOption::where('type', 'user_registration_first_purchase')->first();
                                if ($commissionSetting && $commissionSetting->status) {
                                    $commissionAmount = ($commissionSetting->percentage * $order->grand_total) / 100;

                                    // Add to affiliate balance
                                    $affiliateUser->balance += $commissionAmount;
                                    $affiliateUser->save();

                                    // Create affiliate log
                                    $affiliateLog = new \App\Models\AffiliateLog();
                                    $affiliateLog->user_id = $order->user_id;
                                    $affiliateLog->referred_by_user = $order->user->referred_by;
                                    $affiliateLog->amount = $commissionAmount;
                                    $affiliateLog->order_id = $order->id;
                                    $affiliateLog->affiliate_type = 'user_registration_first_purchase';
                                    $affiliateLog->save();

                                    // Track delivered stats for first purchase
                                    $affiliateController->processAffiliateStats(
                                        $affiliateUser->id,
                                        0,    // no_click
                                        0,    // no_item
                                        1,    // no_delivered
                                        0     // no_cancel
                                    );
                                    $affiliateStatsUpdated++;
                                }
                            }
                        }
                    }

                    // Process product sharing commission
                    foreach ($order->orderDetails as $orderDetail) {
                        if ($orderDetail->product_referral_code) {
                            $referred_by_user = User::where('referral_code', $orderDetail->product_referral_code)->first();
                            if ($referred_by_user) {
                                $affiliateUser = \App\Models\AffiliateUser::where('user_id', $referred_by_user->id)->first();
                                if ($affiliateUser && $affiliateUser->status == 1) {
                                    $amount = 0;

                                    // Check product sharing commission settings
                                    $productSharingSetting = \App\Models\AffiliateOption::where('type', 'product_sharing')->first();
                                    if ($productSharingSetting && $productSharingSetting->status && $productSharingSetting->details) {
                                        $details = json_decode($productSharingSetting->details);
                                        if ($details->commission_type == 'amount') {
                                            $amount = $details->commission;
                                        } elseif ($details->commission_type == 'percent') {
                                            $amount = ($details->commission * $orderDetail->price) / 100;
                                        }

                                        if ($amount > 0) {
                                            $affiliateUser->balance += $amount;
                                            $affiliateUser->save();

                                            $affiliateLog = new \App\Models\AffiliateLog();
                                            $affiliateLog->user_id = $order->user_id ?? null;
                                            $affiliateLog->guest_id = $order->guest_id ?? null;
                                            $affiliateLog->referred_by_user = $referred_by_user->id;
                                            $affiliateLog->amount = $amount;
                                            $affiliateLog->order_id = $order->id;
                                            $affiliateLog->order_detail_id = $orderDetail->id;
                                            $affiliateLog->affiliate_type = 'product_sharing';
                                            $affiliateLog->save();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$orders->count()} order(s) payment status to PAID and updated {$affiliateStatsUpdated} affiliate stats",
                'updated_count' => $orders->count(),
                'updated_order_ids' => $updatedOrderIds,
                'affiliate_stats_updated' => $affiliateStatsUpdated
            ]);
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Fix all payment error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }
    public function autoUpdatePayment(Request $request)
    {
        try {
            $order = Order::findOrFail($request->order_id);

            // Check if courier_status is delivered and payment is not paid
            if ($order->courier_status === 'delivered' && $order->payment_status !== 'paid') {
                $order->payment_status = 'paid';
                $order->save();

                //  Process affiliate commission when order is delivered and payment becomes paid
                if (addon_is_activated('affiliate_system')) {
                    $affiliateController = new AffiliateController();

                    foreach ($order->orderDetails as $orderDetail) {
                        // Update delivered stats for product sharing
                        if ($orderDetail->product_referral_code) {
                            $referred_by_user = User::where('referral_code', $orderDetail->product_referral_code)->first();
                            if ($referred_by_user) {
                                $affiliateUser = AffiliateUser::where('user_id', $referred_by_user->id)->first();
                                if ($affiliateUser && $affiliateUser->status == 1) {
                                    // Track delivered stats
                                    $affiliateController->processAffiliateStats(
                                        $affiliateUser->id,
                                        0,                           // no_click
                                        0,                           // no_item
                                        $orderDetail->quantity,      // no_delivered
                                        0                            // no_cancel
                                    );
                                }
                            }
                        }
                    }

                    // Process first purchase commission for the referred user
                    if ($order->user_id && $order->user->referred_by) {
                        $orderCount = Order::where('user_id', $order->user_id)->count();
                        if ($orderCount == 1) {
                            $affiliateUser = AffiliateUser::where('user_id', $order->user->referred_by)->first();
                            if ($affiliateUser && $affiliateUser->status == 1) {
                                // Calculate first purchase commission
                                $commissionSetting = AffiliateOption::where('type', 'user_registration_first_purchase')->first();
                                if ($commissionSetting && $commissionSetting->status) {
                                    $commissionAmount = ($commissionSetting->percentage * $order->grand_total) / 100;

                                    // Add to affiliate balance
                                    $affiliateUser->balance += $commissionAmount;
                                    $affiliateUser->save();

                                    // Create affiliate log
                                    $affiliateLog = new AffiliateLog();
                                    $affiliateLog->user_id = $order->user_id;
                                    $affiliateLog->referred_by_user = $order->user->referred_by;
                                    $affiliateLog->amount = $commissionAmount;
                                    $affiliateLog->order_id = $order->id;
                                    $affiliateLog->affiliate_type = 'user_registration_first_purchase';
                                    $affiliateLog->save();
                                }
                            }
                        }
                    }

                    // Process product sharing commission
                    foreach ($order->orderDetails as $orderDetail) {
                        if ($orderDetail->product_referral_code) {
                            $referred_by_user = User::where('referral_code', $orderDetail->product_referral_code)->first();
                            if ($referred_by_user) {
                                $affiliateUser = AffiliateUser::where('user_id', $referred_by_user->id)->first();
                                if ($affiliateUser && $affiliateUser->status == 1) {
                                    $amount = 0;

                                    $productSharingSetting = AffiliateOption::where('type', 'product_sharing')->first();
                                    if ($productSharingSetting && $productSharingSetting->status && $productSharingSetting->details) {
                                        $details = json_decode($productSharingSetting->details);
                                        if ($details->commission_type == 'amount') {
                                            $amount = $details->commission;
                                        } elseif ($details->commission_type == 'percent') {
                                            $amount = ($details->commission * $orderDetail->price) / 100;
                                        }

                                        if ($amount > 0) {
                                            $affiliateUser->balance += $amount;
                                            $affiliateUser->save();

                                            $affiliateLog = new AffiliateLog();
                                            $affiliateLog->user_id = $order->user_id ?? null;
                                            $affiliateLog->guest_id = $order->guest_id ?? null;
                                            $affiliateLog->referred_by_user = $referred_by_user->id;
                                            $affiliateLog->amount = $amount;
                                            $affiliateLog->order_id = $order->id;
                                            $affiliateLog->order_detail_id = $orderDetail->id;
                                            $affiliateLog->affiliate_type = 'product_sharing';
                                            $affiliateLog->save();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment status updated to Paid for order #' . $order->code
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Conditions not met for order #' . $order->code
                ]);
            }
        } catch (Exception $e) {
            Log::error('Auto update payment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    public function all_orders(Request $request)
    {
        // dd($request->all());
        if (!is_null($request->bulk_delivery_status) || !is_null($request->bulk_payment_status)) {
            $error = $this->bulkStatusChange($request);
            if ($error) {
                flash(translate($error))->error();
                return back();
            }
        }
        $perPage = $request->per_page ?? 10;

        CoreComponentRepository::instantiateShopRepository();

        $date = $request->date;
        $sort_search = $request->search;
        $user_search = $request->user_search;
        $delivery_status = $request->delivery_status;
        $payment_status = $request->payment_status ?? '';
        $courier = $request->courier;
        $min = $request->min;
        $max = $request->max;

        $orders = Order::orderBy('id', 'desc');

        // --- Stats Queries ---
        $totalOrders = (clone $orders)->count();
        $pendingOrders = (clone $orders)->where('delivery_status', 'pending')->count();
        $processingOrders = (clone $orders)
            ->whereNotIn('delivery_status', ['delivered', 'cancelled', 'pending'])
            ->where(function ($query) {
                $query->where('courier_status', '!=', 'delivered')
                    ->orWhereNull('courier_status');
            })
            ->count();

        $completeOrders = (clone $orders)
            ->where(function ($query) {
                $query->where('delivery_status', 'delivered')
                    ->orWhere('courier_status', 'delivered');
            })
            ->count();
        $cancelledOrders = (clone $orders)->where('delivery_status', 'cancelled')->count();

        $admin_user_id = User::where('user_type', 'admin')->first()->id;

        if (
            Route::currentRouteName() == 'inhouse_orders.index' &&
            Auth::user()->can('view_inhouse_orders')
        ) {
            $orders->where('orders.seller_id', $admin_user_id);
        } elseif (
            Route::currentRouteName() == 'seller_orders.index' &&
            Auth::user()->can('view_seller_orders')
        ) {
            $orders->where('orders.seller_id', '!=', $admin_user_id);
        } elseif (
            Route::currentRouteName() == 'pick_up_point.index' &&
            Auth::user()->can('view_pickup_point_orders')
        ) {

            if (get_setting('vendor_system_activation') != 1) {
                $orders->where('orders.seller_id', $admin_user_id);
            }

            $orders->where('shipping_type', 'pickup_point')
                ->orderBy('code', 'desc');

            if (
                Auth::user()->user_type == 'staff' &&
                Auth::user()->staff->pick_up_point != null
            ) {
                $orders->where('pickup_point_id', Auth::user()->staff->pick_up_point->id);
            }
        } elseif (
            Route::currentRouteName() == 'all_orders.index' &&
            Auth::user()->can('view_all_orders')
        ) {
            if (get_setting('vendor_system_activation') != 1) {
                $orders->where('orders.seller_id', $admin_user_id);
            }
        } else {
            abort(403);
        }

        if ($sort_search) {
            $orders->where(function ($query) use ($sort_search) {
                $query->where('code', 'like', "%$sort_search%")

                    // JSON shipping fields
                    ->orWhere('shipping_address->name', 'like', "%$sort_search%")
                    ->orWhere('shipping_address->phone', 'like', "%$sort_search%")
                    ->orWhere('shipping_address->email', 'like', "%$sort_search%")

                    // User table (relationship)
                    ->orWhereHas('user', function ($q) use ($sort_search) {
                        $q->where('name', 'like', "%$sort_search%")
                            ->orWhere('phone', 'like', "%$sort_search%")
                            ->orWhere('email', 'like', "%$sort_search%");
                    });
            });
        }

        if ($payment_status) {
            $orders->where('payment_status', $payment_status);
        }

        if ($delivery_status) {
            $orders->where('delivery_status', $delivery_status);
        }

        if ($courier) {
            $orders->where('courier_name', $courier);
        }

        // Orders placed through one sales funnel page (the funnels list links here).
        if ($request->filled('funnel_id') && ctype_digit((string) $request->funnel_id) && Order::hasFunnelColumns()) {
            $orders->where('orders.funnel_id', (int) $request->funnel_id);
        }

        if ($min && $max) {
            $orders->whereBetween('grand_total', [$min, $max]);
        } elseif ($min) {
            $orders->where('grand_total', '>=', $min);
        } elseif ($max) {
            $orders->where('grand_total', '<=', $max);
        }

        if ($date) {
            $start = date('Y-m-d', strtotime(explode(" to ", $date)[0])) . ' 00:00:00';
            $end   = date('Y-m-d', strtotime(explode(" to ", $date)[1])) . ' 23:59:59';

            $orders->whereBetween('created_at', [$start, $end]);
        }

        $orders = $orders->with(Order::hasFunnelColumns() ? ['user', 'funnel:id,title'] : ['user'])->paginate($perPage);

        // Count fixable orders across ALL orders (not just current page)
        $fixableOrdersTotalQuery = (new Order)->newQuery();
        if (
            Route::currentRouteName() == 'inhouse_orders.index' &&
            Auth::user()->can('view_inhouse_orders')
        ) {
            $fixableOrdersTotalQuery->where('orders.seller_id', $admin_user_id);
        } elseif (
            Route::currentRouteName() == 'seller_orders.index' &&
            Auth::user()->can('view_seller_orders')
        ) {
            $fixableOrdersTotalQuery->where('orders.seller_id', '!=', $admin_user_id);
        } elseif (
            Route::currentRouteName() == 'pick_up_point.index' &&
            Auth::user()->can('view_pickup_point_orders')
        ) {
            if (get_setting('vendor_system_activation') != 1) {
                $fixableOrdersTotalQuery->where('orders.seller_id', $admin_user_id);
            }
            $fixableOrdersTotalQuery->where('shipping_type', 'pickup_point');
            if (
                Auth::user()->user_type == 'staff' &&
                Auth::user()->staff->pick_up_point != null
            ) {
                $fixableOrdersTotalQuery->where('pickup_point_id', Auth::user()->staff->pick_up_point->id);
            }
        } elseif (
            Route::currentRouteName() == 'all_orders.index' &&
            Auth::user()->can('view_all_orders')
        ) {
            if (get_setting('vendor_system_activation') != 1) {
                $fixableOrdersTotalQuery->where('orders.seller_id', $admin_user_id);
            }
        }
        $fixableOrdersCount = $fixableOrdersTotalQuery
            ->where('courier_status', 'delivered')
            ->where('payment_status', '!=', 'paid')
            ->count();

        //last order indicators
        $phones = [];
        $emails = [];

        foreach ($orders as $order) {
            $address = json_decode($order->shipping_address);

            if (!empty($address->phone)) {
                $phones[] = $address->phone;
            }

            if (!empty($address->email)) {
                $emails[] = $address->email;
            }
        }

        $phones = array_unique($phones);
        $emails = array_unique($emails);

        $previousOrders = Order::query()
            ->select('id', 'shipping_address', 'delivery_status', 'created_at')
            ->where(function ($q) use ($phones, $emails) {
                foreach ($phones as $phone) {
                    $q->orWhere('shipping_address->phone', $phone);
                }

                foreach ($emails as $email) {
                    $q->orWhere('shipping_address->email', $email);
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = [];

        foreach ($previousOrders as $prev) {
            $addr = json_decode($prev->shipping_address);

            if (!empty($addr->phone)) {
                $grouped['phone'][$addr->phone][] = $prev;
            }

            if (!empty($addr->email)) {
                $grouped['email'][$addr->email][] = $prev;
            }
        }
        foreach ($orders as $order) {
            $address = json_decode($order->shipping_address);

            $matches = collect();

            if (!empty($address->phone) && isset($grouped['phone'][$address->phone])) {
                $matches = $matches->merge($grouped['phone'][$address->phone]);
            }

            if (!empty($address->email) && isset($grouped['email'][$address->email])) {
                $matches = $matches->merge($grouped['email'][$address->email]);
            }

            $indicators = $matches
                ->where('id', '!=', $order->id)
                ->unique('id')
                ->sortByDesc('created_at')
                ->take(5)
                ->map(function ($item) {
                    return $item->delivery_status === 'cancelled' ? 1 : 0;
                })
                ->values()
                ->toArray();

            $order->cancelled_flags = $indicators;
        }


        return view('backend.sales.index2', compact(
            'orders',
            'totalOrders',
            'pendingOrders',
            'processingOrders',
            'completeOrders',
            'cancelledOrders',
            'sort_search',
            'user_search',
            'delivery_status',
            'payment_status',
            'date',
            'fixableOrdersCount'
        ));
    }

    public function sidebar(Order $order)
    {
        $order_shipping_address = json_decode($order->shipping_address);
        $phone = $order_shipping_address->phone;
        $courierStatModel = CourierStat::where('phone', $phone)->first();

        $courier_stat = $courierStatModel && $courierStatModel->data
            ? (is_array($courierStatModel->data)
                ? $courierStatModel->data
                : json_decode($courierStatModel->data, true))
            : [];

        $summary = $courier_stat['summary'] ?? [];

        $totalOrders = $summary['total_parcel'] ?? 0;
        $successOrders = $summary['success_parcel'] ?? 0;
        $cancelledOrders = $summary['cancelled_parcel'] ?? 0;
        $successPercentage = $summary['success_ratio'] ?? 0;

        $order->load('orderDetails');

        $previousOrders = $this->getPreviousOrders($order, $order_shipping_address, 5);

        return view('backend.sales.right_sidebar', compact(
            'order',
            'courierStatModel',
            'courier_stat',
            'summary',
            'totalOrders',
            'successOrders',
            'cancelledOrders',
            'successPercentage',
            'previousOrders'
        ));
    }

    protected function bulkStatusChange($request)
    {

        if ($request->has('id') && is_array($request->get('id')) && count($request->get('id'))) {
            $ids = $request->get('id');

            if (!is_null($request->bulk_delivery_status)) {
                $status = $request->bulk_delivery_status;

                if ($status == 'steadfast') {
                    $error = $this->steadFastCourier($ids);
                    if ($error) {
                        return $error;
                    }
                } else {
                    foreach ($ids as $id) {
                        $this->bulkDeliveryStatusChange($id, $status);
                    }
                }
            }

            if (!is_null($request->bulk_payment_status)) {
                foreach ($ids as $id) {
                    $this->bulkPaymentStatusChange($id, $request->bulk_payment_status);
                }
            }
        } else {
            return "Please Select order to Proceed";
        }
    }



    protected function bulkDeliveryStatusChange($id, $status)
    {
        $order = Order::find($id);
        if (!$order) return;

        // Prevent overriding courier unless cancelling
        if ($order->courier_name && $status !== 'cancelled') {
            return;
        }

        $order->delivery_status = $status;

        // List of couriers (expand later)
        $couriers = ['steadfast', 'pathao'];

        if (in_array($status, $couriers)) {

            try {

                $result = CourierManager::createShipment($status, $order);

                $order->update([
                    'delivery_status' => 'courier_assigned',
                    'courier_name' => $status,
                    'courier_tracking_id' => $result['consignment_id'],
                    'tracking_code' => $result['tracking_code'],
                    'courier_status' => $result['status'] ?? 'created',
                    'courier_status_updated_at' => now(),
                    $status === 'pathao' ? 'pathao_id' : 'steadfast_id' => $result['consignment_id'],
                ]);
            } catch (\Throwable $e) {

                Log::error('Bulk courier create failed', [
                    'order_id' => $order->id,
                    'courier' => $status,
                    'error' => $e->getMessage()
                ]);
            }

            return;
        }

        // Cancel courier
        if ($status === 'cancelled') {
            $order->courier_name = null;
            $order->courier_tracking_id = null;
            $order->courier_status = 'cancelled';
        }

        $order->save();

        // Cancelling in bulk returns the stock too, as cancelling one order does.
        if ($status === 'cancelled') {
            foreach ($order->orderDetails as $orderDetail) {
                set_order_line_status($orderDetail, 'cancelled');
            }
        }
    }

    protected function bulkPaymentStatusChange($id, $status)
    {
        $order = Order::find($id);
        if ($order) {
            $order->payment_status = $status;
            $order->save();
        }
    }

    public function bulkAssignCourier(Request $request)
    {
        // dd($request->all());
        $ids = $request->input('ids', []);
        $courier = $request->input('courier');

        if (empty($ids) || !in_array($courier, ['steadfast', 'pathao'])) {
            return response()->json([
                'success' => false,
                'message' => 'Please select orders and a courier',
            ]);
        }

        $success = 0;
        $errors = [];

        foreach ($ids as $id) {
            try {
                $result = CourierManager::createShipment($courier, Order::find($id));
                $order = Order::find($id);
                if (!$order) continue;

                $order->update([
                    'delivery_status' => $order->delivery_status === 'cancelled' ? 'cancelled' : 'courier_assigned',
                    'courier_name' => $courier,
                    'courier_tracking_id' => $result['consignment_id'],
                    'tracking_code' => $result['tracking_code'],
                    'courier_status' => $result['status'] ?? 'created',
                    'courier_status_updated_at' => now(),
                    $courier === 'pathao' ? 'pathao_id' : 'steadfast_id' => $result['consignment_id'],
                ]);
                $success++;
            } catch (\Throwable $e) {
                $order = Order::find($id);
                $orderCode = $order ? $order->code : $id;
                $errors[] = "Order #{$orderCode}: " . $e->getMessage();
                Log::error("Bulk courier assign failed for order {$orderCode}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$success} order(s) assigned to " . ucfirst($courier) . " successfully",
            'errors' => $errors,
        ]);
    }

    protected function steadFastCourier($ids)
    {
        Log::info('SteadfastCourier IDs:', $ids);

        $orders = Order::whereIn('id', $ids)->get();
        if ($orders->isEmpty()) return 'No valid orders found';

        // Get API credentials
        $settings = SteadfastSetting::first();
        $apiKey = $settings->steadfast_api_key ?? config('steadfast-courier.api_key');
        $secretKey = $settings->steadfast_secret_key ?? config('steadfast-courier.secret_key');

        if (!$apiKey || !$secretKey) {
            return 'Invalid API Keys';
        }

        // Prepare payload
        $data = [];
        foreach ($orders as $order) {
            $shipping = json_decode($order->shipping_address, true);

            $data[] = [
                'invoice'          => $order->code,
                'recipient_name'   => $shipping['name'] ?? 'N/A',
                'recipient_address' => $shipping['address'] ?? 'N/A',
                'recipient_phone'  => str_replace('+', '', $shipping['phone'] ?? ''),
                'cod_amount'       => $order->due_amount > 0.00 ? (float) $order->due_amount : (float) $order->grand_total,
                // 'note'             => $order->additional_info ?? 'No special notes',
                'note'             => $order->order_note ?? 'No special notes',
            ];
        }

        Log::info('SteadfastCourier Request Data:', $data);

        // Send API Request
        $response = Http::withHeaders([
            'Api-Key' => $apiKey,
            'Secret-Key' => $secretKey,
            'Content-Type' => 'application/json',
        ])->post('https://portal.packzy.com/api/v1/create_order/bulk-order', [
            'data' => json_encode($data),
        ]);


        Log::info('SteadfastCourier Response Body:', ['body' => $response->body()]);

        if (!$response->successful()) {
            return 'API request failed: ' . $response->status();
        }

        $result = $response->json();

        // Handle both wrapped and direct array responses
        $items = $result['data'] ?? $result;

        if (!is_array($items)) {
            return 'Unexpected response structure from Steadfast API';
        }

        if (empty($items)) {
            return 'Steadfast API returned no order data (check request format or API credentials).';
        }

        // process response items safely
        foreach ($items as $res) {
            if (!is_array($res)) continue;

            if (($res['status'] ?? '') === 'success') {
                $order = Order::where('code', $res['invoice'])->first();
                if ($order) {
                    $order->delivery_status = 'steadfast';
                    $order->steadfast_id = $res['consignment_id'] ?? null;

                    /*unified courier system */
                    $order->courier_name = 'steadfast';
                    $order->courier_tracking_id = $res['consignment_id'] ?? null;
                    $order->courier_status = 'pending';
                    $order->courier_status_updated_at = now();

                    $order->tracking_code = $res['tracking_code'] ?? null;

                    $order->save();
                }
            } else {
                Log::warning(
                    'Steadfast Error for Invoice: ' . ($res['invoice'] ?? 'N/A'),
                    ['response' => $res] // Always pass array context
                );
            }
        }


        return null;
    }

    public function show($id, BdCourierService $bdCourier)
    {
        $order = Order::findOrFail(decrypt($id));
        $phone = json_decode($order->shipping_address)->phone;

        $order_shipping_address = json_decode($order->shipping_address);

        if (isset($order_shipping_address->city)) {
            $delivery_boys = User::where('city', $order_shipping_address->city)
                ->where('user_type', 'delivery_boy')
                ->get();
        } else {
            // Handle the case where city is not present
            $delivery_boys = collect(); // Returning an empty collection or handle as needed
        }

        $order_shipping_address = json_decode($order->shipping_address);

        $previous_orders = $this->getPreviousOrders($order, $order_shipping_address);

        $order->viewed = 1;
        $order->save();

        // Only sync courier if data is missing or older than 3 days
        $user = $order->user;
        $needSync = false;

        if (empty($user->courier_stat)) {
            $needSync = true;
        } elseif (empty($user->courier_stat_updated_at)) {
            $needSync = true;
        } elseif ($user->courier_stat_updated_at->lt(now()->subDays(3))) {
            $needSync = true;
        }

        if ($needSync) {
            $bdCourier->syncCourierStatsByPhone($phone);
        }
        $data = $user->courier_stats ?? "";
        // $data = $this->deliveryInfo($phone);

        return view('backend.sales.show2', compact('order', 'data', 'delivery_boys', 'previous_orders'));
    }

    public static function getPreviousOrders($order, $shippingAddress, $limit = 5)
    {
        $previousOrders = collect();

        $phone = $shippingAddress->phone ?? null;
        $email = $shippingAddress->email ?? null;

        if (!empty($phone) || !empty($email)) {
            $query = Order::query();

            $query->where(function ($q) use ($phone, $email) {
                if (!empty($phone)) {
                    $q->orWhere('shipping_address->phone', $phone);
                }
                if (!empty($email)) {
                    $q->orWhere('shipping_address->email', $email);
                }
            });

            $previousOrders = $query->with('orderDetails.product')
                ->where('id', '!=', $order->id)
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();
        }

        return $previousOrders;
    }

    private function getPathaoAccessToken()
    {
        $settings = PathaoCourierSetting::first();

        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->post($settings->base_url . '/aladdin/api/v1/issue-token', [
                'http_errors' => false,
                'timeout' => 15,
                'form_params' => [
                    'client_id'     => $settings->client_id,
                    'client_secret' => $settings->client_secret,
                    'username'      => $settings->username,
                    'password'      => $settings->password,
                    'grant_type'    => 'password',
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true);

            if ($statusCode === 200 && isset($body['access_token'])) {
                return $body['access_token'];
            }

            \Log::error("Pathao Auth Failed", [
                'status' => $statusCode,
                'body'   => $body,
            ]);

            return null;
        } catch (\Exception $e) {
            \Log::error("Pathao Auth Error: " . $e->getMessage());
            return null;
        }
    }

    public function getPathaoOrderStatus($merchantOrderId)
    {

        // dd($merchantOrderId);
        $settings = PathaoCourierSetting::first();
        $baseUrl = $settings->base_url;

        $accessToken = cache('pathao_access_token');

        if (!$accessToken) {
            $accessToken = $this->getPathaoAccessToken();
            if ($accessToken) {
                cache(['pathao_access_token' => $accessToken], now()->addMinutes(50));
            } else {
                return null; // fail early
            }
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept'        => 'application/json',
        ])->get($baseUrl . '/aladdin/api/v1/orders/' . $merchantOrderId);

        if ($response->successful()) {
            $data = $response->json();
            return $data['data']['order_status'] ?? null; // FIXED
        }

        \Log::error("Pathao Order Status Failed", [
            'merchant_order_id' => $merchantOrderId,
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return null;
    }


    public function getSteadfastOrderStatusByCid($consignmentId)
    {
        try {
            $settings = Cache::rememberForever('steadfast_settings', function () {
                return \App\Models\SteadfastSetting::first();
            });
            $apiKey    = $settings->steadfast_api_key ?? config('services.steadfast.api_key');
            $secretKey = $settings->steadfast_secret_key ?? config('services.steadfast.secret_key');

            $url = "https://portal.packzy.com/api/v1/status_by_cid/{$consignmentId}";

            $response = Http::timeout(10)
                ->retry(2, 200)
                ->withHeaders([
                    'Api-Key'    => $apiKey,
                    'Secret-Key' => $secretKey,
                    'Accept'     => 'application/json',
                ])->get($url);

            if ($response->serverError()) {
                \Log::error("Steadfast API 500 for Consignment: {$consignmentId}");
                return 'error'; // clearly distinguish server error
            }

            if ($response->successful()) {
                $data = $response->json();
                return $data['delivery_status'] ?? 'unknown';
            }

            return 'error'; // fallback for 400/401/404 etc.
        } catch (\Exception $e) {
            \Log::error('Steadfast Status Exception: ' . $e->getMessage());
            return 'error';
        }
    }



    private function deliveryInfo($phone)
    {
        $token = 'YLMszaxfTyqIZzLEi7Pny5CbD2yDYXbYsEe4y0oBjB6MdoGjoBsYW48EfC63';

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://bdcourier.com/api/courier-check?phone=' . $phone,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
            ],
        ]);

        $response = curl_exec($curl);


        curl_close($curl);
        return json_decode($response);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */


    public function store(Request $request)
    {
        // dd($request->all());
        // Determine user (guest or authenticated)
        if (Session::get('guest_created')) {
            $user_id = Session::get('guest_created');
            $user = User::where('id', $user_id)->first();
            $user_name = $user->name ?? '';
            $user_email = $user->email ?? '';
        } else {
            $user_id = Auth::user()->id;
            $user_name = Auth::user()->name;
            $user_email = Auth::user()->email ?? '';
        }

        // Get user cart
        $carts = Cart::where('user_id', $user_id)->get();
        if ($carts->isEmpty()) {
            flash(translate('Your cart is empty'))->warning();
            return redirect()->route('home');
        }

        // Get address
        if (auth()->check()) {
            $address = Address::where('id', $request->address_id)->first();
        } else {
            $address = Address::where('id', $carts[0]['address_id'])->first();
        }

        // Prepare shipping address
        $shippingAddress = [];
        if ($address != null) {
            $shippingAddress['name']        = $request->name ?? $user_name;
            $shippingAddress['email']       = $request->email ?? $user_email;
            $shippingAddress['address']     = $request->address ?? $address->address;
            $shippingAddress['country']     = $address->country->name ?? 'Bangladesh';
            $shippingAddress['state']       = $address->state->name ?? '';
            $shippingAddress['city']        = $address->city->name ?? '';
            $shippingAddress['postal_code'] = $address->postal_code ?? '';
            $shippingAddress['phone']       = $request->phone ?? $address->phone;
            if ($address->latitude || $address->longitude) {
                $shippingAddress['lat_lang'] = $address->latitude . ',' . $address->longitude;
            }
        }

        // Create combined order (wrapped in a transaction so coupon burn,
        // stock deductions, and order rows are all-or-nothing).
        $outOfStockProduct = null;
        $stockTake = null;
        try {
        DB::transaction(function () use ($request, $user_id, $shippingAddress, $carts, &$outOfStockProduct, &$stockTake) {

        // Take the stock and the purchase batches for the whole cart first, in one place shared with
        // the storefront API, POS and funnel checkout: the database checks and subtracts in one step,
        // so two shoppers cannot both get the last unit.
        $stockLines = [];
        foreach ($carts as $cartItem) {
            $lineProduct = Product::find($cartItem['product_id']);
            $stockLines[] = [
                'key' => $cartItem['id'],
                'product_id' => $cartItem['product_id'],
                'variant' => $cartItem['variation'],
                'quantity' => $cartItem['quantity'],
                'digital' => $lineProduct && $lineProduct->digital == 1,
            ];
        }
        try {
            $stockTake = app(StockLedger::class)->take($stockLines);
        } catch (InventoryException $e) {
            $refused = Product::find($e->context()['product_id'] ?? 0);
            $outOfStockProduct = $refused ? $refused->getTranslation('name') : translate('a product');
            throw new \RuntimeException('out_of_stock');
        }

        // Create combined order
        $combined_order = new CombinedOrder;
        $combined_order->user_id = $user_id;
        $combined_order->shipping_address = json_encode($shippingAddress);
        $combined_order->save();

        // Group cart items by seller
        $seller_products = [];
        foreach ($carts as $cartItem) {
            $product = Product::find($cartItem['product_id']);
            $seller_products[$product->user_id][] = $cartItem;
        }

        // Process each seller's order
        foreach ($seller_products as $seller_product) {
            $order = new Order;
            $order->combined_order_id = $combined_order->id;
            $order->user_id = $user_id;
            $order->shipping_address = $combined_order->shipping_address;
            $order->additional_info = $request->additional_info;
            $order->payment_type = $request->payment_option;
            $order->delivery_viewed = '0';
            $order->payment_status_viewed = '0';
            $order->code = Order::generateUniqueCode();
            $order->date = strtotime('now');
            $order->save();

            $subtotal = 0;
            $tax = 0;
            $shipping = 0;
            $coupon_discount = 0;

            foreach ($seller_product as $key => $cartItem) {
                $product = Product::find($cartItem['product_id']);
                $product_variation = $cartItem['variation'];
                // Stock and purchase batches were taken above; this is the cost they carried.
                $takenLine = $stockTake->line($cartItem['id']);
                $fifo = ['total_cost' => $takenLine['cost'], 'batches' => $takenLine['batches']];

                $totalCost = $fifo['total_cost'];
                $avgBuyingPrice = $totalCost / $cartItem['quantity'];
                $sellingPrice = cart_product_price($cartItem, $product, false, false);
                $profit = ($sellingPrice - $avgBuyingPrice) * $cartItem['quantity'];

                // Calculate subtotal & tax
                $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
                $coupon_discount += $cartItem['discount'];

                // dd($request->all());
                // Shipping
                $shipping_charge = 0;
                if ($cartItem['shipping_type'] != 'pickup_point') {
                    if ($request->total_shipping_cost !== null && $request->total_shipping_cost != 0) {
                        $shipping_charge = $key == 0 ? $request->total_shipping_cost : $request->shipping_cost_amount;
                    } else {
                        $shipping_charge = $request->shipping_cost_amount ?? $request->total_shipping_cost;
                    }
                }


                // Create order detail
                $order_detail = new OrderDetail;
                $order_detail->order_id = $order->id;
                $order_detail->seller_id = $product->user_id;
                $order_detail->product_id = $product->id;
                $order_detail->variation = $product_variation;
                $order_detail->selling_price = $sellingPrice;
                $order_detail->price = $sellingPrice * $cartItem['quantity'];
                $order_detail->tax = cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
                $order_detail->shipping_type = $cartItem['shipping_type'];
                $order_detail->product_referral_code = $cartItem['product_referral_code'];
                $order_detail->shipping_cost = $shipping_charge ?? $request->total_shipping_cost;
                $order_detail->quantity = $cartItem['quantity'];
                // Which batches supplied the line, so a later cancel returns exactly those units.
                $order_detail->fifo_batch_info = $stockTake->batchInfo($cartItem['id']);

                //  FIFO values
                $order_detail->buying_price = $avgBuyingPrice;
                // $order_detail->cost_price = $totalCost;
                $order_detail->profit = $profit;

                if (count($fifo['batches'])) {
                    $firstBatch = $fifo['batches'][0];
                    $order_detail->purchase_id = $firstBatch['purchase_id'];
                    $order_detail->supplier_id = $firstBatch['supplier_id'];
                }


                if (addon_is_activated('club_point')) {
                    $order_detail->earn_point = $product->earn_point;
                }

                $order_detail->save();

                // Update product & seller counters
                $product->num_of_sale += $cartItem['quantity'];
                $product->save();

                $order->seller_id = $product->user_id;
                $order->shipping_type = $cartItem['shipping_type'];
                if ($cartItem['shipping_type'] == 'pickup_point') {
                    $order->pickup_point_id = $cartItem['pickup_point'];
                }
                if ($cartItem['shipping_type'] == 'carrier') {
                    $order->carrier_id = $cartItem['carrier_id'];
                }

                if ($product->added_by == 'seller' && $product->user->seller != null) {
                    $seller = $product->user->seller;
                    $seller->num_of_sale += $cartItem['quantity'];
                    $seller->save();
                }

                if (addon_is_activated('affiliate_system') && $order_detail->product_referral_code) {
                    $referred_by_user = User::where('referral_code', $order_detail->product_referral_code)->first();
                    if ($referred_by_user) {
                        (new AffiliateController)->processAffiliateStats($referred_by_user->id, 0, $order_detail->quantity, 0, 0);
                    }
                }
            }

            // Calculate grand total
            // $all_shipping = $cartItem['shipping_type'] == 'pickup_point' ? 0 : ($request->total_shipping_cost ?? $request->shipping_cost_amount);
            $shipping_cost = (!empty($request->total_shipping_cost) && $request->total_shipping_cost != 0)
                ? $request->total_shipping_cost
                : $request->shipping_cost_amount;

            // If pickup point → shipping = 0
            $all_shipping = $cartItem['shipping_type'] == 'pickup_point' ? 0 : $shipping_cost;
            $order->grand_total = $subtotal + $tax + $all_shipping;

            // Apply coupon if exists
            if ($seller_product[0]->coupon_code != null) {
                $order->coupon_discount = $coupon_discount;
                $order->grand_total -= $coupon_discount;

                // Only record usage for authenticated users — guests have no user_id to track.
                if (Auth::check()) {
                    $coupon = Coupon::where('code', $seller_product[0]->coupon_code)->first();
                    if ($coupon) {
                        $coupon_usage = new CouponUsage;
                        $coupon_usage->user_id  = Auth::id();
                        $coupon_usage->coupon_id = $coupon->id;
                        $coupon_usage->save();
                    }
                }
            }

            $combined_order->grand_total += $order->grand_total;
            $order->save();
        }

        $combined_order->save();
        Session::forget('guest_created');
        $request->session()->put('combined_order_id', $combined_order->id);

        }); // end DB::transaction
        } catch (\Throwable $e) {
            // The transaction has been rolled back. Batch units that do not roll back (MyISAM) go back by hand.
            if ($stockTake !== null) {
                app(StockLedger::class)->release($stockTake);
            }
            if (!($outOfStockProduct && $e instanceof \RuntimeException && $e->getMessage() === 'out_of_stock')) {
                throw $e;
            }
        }

        // Handle out-of-stock redirect (transaction already rolled back via exception)
        if ($outOfStockProduct) {
            flash(translate('The requested quantity is not available for ') . $outOfStockProduct)->warning();
            return redirect()->route('cart');
        }
    }

    protected function consumePurchaseProductQtyFIFO($productId, $variant, $qtyToConsume)
    {
        // First-in-first-out through the shared stock service: locking reads and conditional updates,
        // so a concurrent sale cannot take the same batch units. A batch shortage is an error the edit
        // screen reports; it used to be silently costed at zero.
        $result = app(StockLedger::class)->consumeBatches((int) $productId, $variant, (int) $qtyToConsume);
        $this->editTakes[] = $result['take'];

        return ['total_cost' => $result['total_cost'], 'batches' => $result['batches']];
    }

    // New method for single order detail
    protected function restockSinglePurchaseProducts($orderDetail)
    {
        try {
            // Check if order detail has a purchase_id
            if ($orderDetail->purchase_id) {
                $productId = $orderDetail->product_id;
                $variation = $orderDetail->variation;
                $quantity = $orderDetail->quantity;
                $purchaseId = $orderDetail->purchase_id;

                // Find the specific purchase product - FIXED QUERY

                $purchaseProduct = PurchaseProduct::where('purchase_id', $purchaseId)
                    ->where('product_id', $productId)
                    ->where(function ($query) use ($variant) {
                        if (!empty($variant)) {
                            $query->where('variant', $variant);
                        } else {
                            $query->whereNull('variant')->orWhere('variant', '');
                        }
                    })
                    ->orderBy('id')
                    ->first();

                if ($purchaseProduct) {
                    // Increase the remaining quantity
                    $purchaseProduct->remaining_qty += $quantity;
                    $purchaseProduct->save();

                    Log::info("Restocked {$quantity} units to purchase product ID: {$purchaseProduct->id} (Purchase ID: {$purchaseId}) for product {$productId} ({$variation})");
                } else {
                    Log::warning("Purchase product not found for Purchase ID: {$purchaseId}, Product ID: {$productId}, Variation: {$variation}");

                    // Fallback: Try to find any purchase product for this product and variation
                    $this->restockUsingFallback($productId, $variation, $quantity);
                }
            } else {
                // If no purchase_id is set, use fallback method
                $this->restockUsingFallback($orderDetail->product_id, $orderDetail->variation, $orderDetail->quantity);
            }
        } catch (\Exception $e) {
            Log::error('Error restocking purchase product: ' . $e->getMessage());
            // Don't throw here to avoid breaking the transaction
        }
    }


    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */


    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        if ($order != null) {
            if ($order->settlement_status == 'true' || $order->settlement_status == 1) {
                flash(translate('Settled orders cannot be deleted'))->error();
                return redirect()->route('all_orders.index');
            }

            $this->reverseAccountingEntries($order);

            $order->commissionHistory()->delete();
            foreach ($order->orderDetails as $key => $orderDetail) {
                // Units go back once: a line that was already cancelled gave them back then.
                try {
                    product_restock($orderDetail);
                } catch (\Exception $e) {
                    Log::error('Restock while deleting order ' . $order->code . ' failed: ' . $e->getMessage());
                }

                $orderDetail->delete();
            }
            $order->delete();
            flash(translate('Order has been deleted successfully'))->success();
        } else {
            flash(translate('Something went wrong'))->error();
        }
        // return back();
        return redirect()->route('all_orders.index');
    }

    public function bulk_order_delete(Request $request)
    {
        if ($request->id) {
            foreach ($request->id as $order_id) {
                $order = Order::find($order_id);
                if ($order && ($order->settlement_status == 'true' || $order->settlement_status == 1)) {
                    flash(translate('Settled order ' . $order->code . ' skipped'))->warning();
                    continue;
                }
                $this->destroy($order_id);
            }
        }

        return 1;
    }

    public function order_details(Request $request)
    {
        $order = Order::findOrFail($request->order_id);
        $order->save();
        return view('seller.order_details_seller', compact('order'));
    }

    public function update_delivery_status(Request $request)
    {

        // Fetch the order with the related shop information
        $order = Order::with('shop')->find($request->order_id);

        // Check if the order exists
        if (!$order) {
            return response()->json(['message' => 'Order not found', 'status' => 'error',], 404);
        }

        try {
            // Decode the shipping address and validate
            $shippingAddress = json_decode($order->shipping_address, true);
            if (!$shippingAddress || !isset($shippingAddress['name'], $shippingAddress['phone'], $shippingAddress['address'])) {
                return response()->json(['error' => 'Invalid shipping address format'], 400);
            }

            // Retrieve API keys from the settings or fallback to environment variables
            $settings = SteadfastSetting::first();
            $apiKey = $settings->steadfast_api_key ?? config('steadfast-courier.api_key');
            $secretKey = $settings->steadfast_secret_key ?? config('steadfast-courier.secret_key');

            // Check if API keys are available
            if (!$apiKey || !$secretKey) {
                return response()->json(['message' => 'API keys not configured', 'status' => 'error'], 500);
            }

            // Dynamically set API keys in the config
            config(['steadfast-courier.api_key' => $apiKey]);
            config(['steadfast-courier.secret_key' => $secretKey]);

            // Prepare the order data for Steadfast Courier API
            $orderData = [
                'invoice' => $order->code,
                'recipient_name' => $shippingAddress['name'],
                'recipient_phone' => str_replace('+', '', $shippingAddress['phone']),
                'recipient_address' => $shippingAddress['address'],
                'cod_amount' => $order->due_amount > 0.00 ? (float) $order->due_amount : (float) $order->grand_total,
                // 'note' => $order->additional_info ?? 'No special notes',
                'note' => $order->order_note ?? $order->additional_info ?? 'No special notes',
            ];


            // Handle the courier status update
            if (in_array($request->status, ['steadfast', 'pathao'])) {

                try {
                    $result = CourierManager::createShipment($request->status, $order, array_filter([
                        'city_id' => $request->pathao_city_id,
                        'zone_id' => $request->pathao_zone_id,
                        'area_id' => $request->pathao_area_id,
                    ]));
                } catch (\Throwable $e) {
                    // Surface the courier's own message to the operator instead
                    // of a 500, e.g. Pathao "You need to pay your due".
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                    ], 422);
                }

                if ($request->status === 'steadfast') {
                    $order->steadfast_id = $result['consignment_id'];
                }

                if ($request->status === 'pathao') {
                    $order->pathao_id = $result['consignment_id'];
                }

                $order->courier_name = $request->status;
                $order->courier_tracking_id = $result['consignment_id'];
                $order->tracking_code = $result['tracking_code'];
                $order->courier_status = $result['status'];

                // The picked courier ('pathao'/'steadfast') is the courier name,
                // not a delivery status. Match the bulk path and mark the order
                // as courier_assigned.
                $order->delivery_status = 'courier_assigned';
                $order->save();

                return response()->json([
                    'success' => true,
                    'tracking_code' => $result['consignment_id']
                ]);
            }

            // Update the order delivery status only if not 'courier'
            $order->delivery_viewed = '0'; // Set delivery_viewed to 0
            $order->delivery_status = $request->status;

            // Handle order cancellation logic if the status is cancelled
            if ($request->status === 'cancelled') {

                // Stock and purchase batches go back line by line below, through the shared stock service.

                // $order->cancel_reason = $request->cancel_reason;

                // Update user's wallet balance if payment type is wallet
                if ($order->payment_type === 'wallet') {
                    $user = $order->user;
                    if ($user) {
                        $user->balance += $order->grand_total;
                        $user->save();
                    }
                }

                // Deduct seller earnings if applicable
                if ($order->shop && $order->shop->user->user_type === 'seller' && $order->payment_status === 'paid' && $order->commission_calculated === 1) {
                    $sellerEarning = $order->commissionHistory->seller_earning;
                    $order->shop->admin_to_pay -= $sellerEarning;
                    $order->shop->save();
                }

                // Reverse accounting entries for POS orders
                $orderLogs = AccountStatementLog::where('order_id', $order->id)->get();
                foreach ($orderLogs as $log) {
                    $account = ChartOfAccount::find($log->account_id);
                    if (!$account) continue;

                    // Reverse the balance change based on what PosUtility did
                    switch ($log->transaction_type) {
                        case 'order_payment':
                        case 'order_due':
                        case 'order_cogs':
                        case 'order_sales':
                            $account->current_balance -= $log->debit + $log->credit;
                            break;
                        case 'order_inventory':
                            $account->current_balance += $log->debit + $log->credit;
                            break;
                    }
                    $account->save();

                    // Create reversal statement log
                    AccountStatementLog::create([
                        'account_id' => $log->account_id,
                        'transaction_date' => now()->toDateString(),
                        'reference_no' => 'Order Cancelled - ' . $order->code,
                        'transaction_type' => 'order_cancelled',
                        'debit' => $log->credit,
                        'credit' => $log->debit,
                        'balance' => $account->current_balance,
                        'description' => 'Cancelled: ' . $log->description,
                        'source_id' => $order->id,
                        'source_type' => get_class($order),
                        'order_id' => $order->id,
                    ]);
                }
            }

            // Save the updated order status
            $order->save();

            // Update order details based on user type
            $orderDetails = (Auth::user()->user_type === 'seller')
                ? $order->orderDetails->where('seller_id', Auth::user()->id)
                : $order->orderDetails;

            foreach ($orderDetails as $orderDetail) {

                // ADD THIS DEBUG LOG
                Log::info('Order detail check:', [
                    'order_detail_id' => $orderDetail->id,
                    'status' => $request->status,
                    'product_referral_code' => $orderDetail->product_referral_code,
                    'has_referral' => !empty($orderDetail->product_referral_code) ? 'YES' : 'NO',
                    'addon_active' => addon_is_activated('affiliate_system') ? 'YES' : 'NO',
                ]);
                // A change to "cancelled" returns the line's units to stock and to the purchase batches
                // they came from, once; pressing "cancelled" again adds nothing.
                set_order_line_status($orderDetail, $request->status);

                // Handle affiliate system logic
                if (addon_is_activated('affiliate_system')) {
                    if (($request->status === 'delivered' || $request->status === 'cancelled') && $orderDetail->product_referral_code) {
                        $no_of_delivered = $request->status === 'delivered' ? $orderDetail->quantity : 0;
                        $no_of_canceled = $request->status === 'cancelled' ? $orderDetail->quantity : 0;
                        Log::info('check all is correct', [
                            'status' => $request->status,
                            'product_referral_code' => $orderDetail->product_referral_code,
                            'no_of_delivered' => $no_of_delivered,
                            'no_of_canceled' => $no_of_canceled,
                            'order_detail_id' => $orderDetail->id,
                            'request status' => $request->status
                        ]);

                        $referred_by_user = User::where('referral_code', $orderDetail->product_referral_code)->first();

                        if ($referred_by_user) {
                            Log::info('Referred user found:', [
                                'user_id' => $referred_by_user->id,
                                'name' => $referred_by_user->name
                            ]);

                            $affiliateUser = AffiliateUser::where('user_id', $referred_by_user->id)->first();

                            if ($affiliateUser) {
                                Log::info('Affiliate user found:', [
                                    'affiliate_user_id' => $affiliateUser->id,
                                    'status' => $affiliateUser->status
                                ]);

                                if ($affiliateUser->status == 1) {
                                    $affiliateController = new \App\Http\Controllers\AffiliateController();
                                    $affiliateController->processAffiliateStats(
                                        $affiliateUser->id,
                                        0,
                                        0,
                                        $no_of_delivered,
                                        $no_of_canceled
                                    );

                                    Log::info('Delivery stats updated for affiliate:', [
                                        'affiliate_user_id' => $affiliateUser->id,
                                        'delivered' => $no_of_delivered,
                                        'cancelled' => $no_of_canceled
                                    ]);
                                } else {
                                    Log::warning('Affiliate not active:', ['status' => $affiliateUser->status]);
                                }
                            } else {
                                Log::warning('No affiliate user record found');
                            }
                        } else {
                            Log::warning('No user found with referral code:', ['code' => $orderDetail->product_referral_code]);
                        }
                    }
                }
            }

            // Prepare and send customer notification
            $shipping = json_decode($order->shipping_address, true);
            $phone = $shipping['phone'] ?? 'Not provided';
            $customerName = $order->user?->name ?? $shipping['name'] ?? '';
            $website_name = config('app.name');
            $hotline_number = get_setting('contact_phone') ?? 'N/A';

            $customerMessage = "Dear {$customerName}, your order (ID: {$order->code}) status has been updated to {$order->delivery_status}. Thank you for shopping with us! Hotline Number: {$hotline_number}.";

            $template = SmsTemplate::where('identifier', 'delivery_status_change')->first();
            if ($template) {
                // Define placeholders and replacements
                $placeholders = ['[[delivery_status]]', '[[order_code]]', '[[user_name]]'];
                $replacements = [$order->delivery_status, $order->code, $order->user?->name];
                $customerMessage = str_replace($placeholders, $replacements, $template->sms_body);
            }

            try {
                // Send SMS to customer
                SendSMSUtility::order_send_message($phone, $customerMessage);

                // Optionally send email to customer
                // Mail::to($order->user->email)->send(new OrderStatusUpdated($order->user, $order));
            } catch (\Exception $e) {
                Log::error('Failed to send SMS/Email for order ID ' . $order->id . ': ' . $e->getMessage());
            }

            // Send notifications to the user

            NotificationUtility::sendNotification($order, $request->status);

            // Send Firebase notification if configured
            if (get_setting('google_firebase') == 1 && $order->user->device_token) {
                $request->merge([
                    'device_token' => $order->user->device_token,
                    'title' => "Order updated!",
                    'text' => "Your order {$order->code} has been " . str_replace("_", "", $order->delivery_status),
                    'type' => "order",
                    'id' => $order->id,
                    'user_id' => $order->user->id,
                ]);

                NotificationUtility::sendFirebaseNotification($request);
            }

            // Handle delivery boy actions if applicable
            if (addon_is_activated('delivery_boy') && Auth::user()->user_type === 'delivery_boy') {
                $deliveryBoyController = new DeliveryBoyController;
                $deliveryBoyController->store_delivery_history($order);
            }

            return response()->json(['success' => true]);
        } catch (Exception $e) {

            // Log exception and return error response
            Log::error('SteadfastCourier Exception: ' . $e->getMessage());
            return response()->json(['message' => 'error', 'status' => 'error',], 500);
        }
    }



    /**
     * @deprecated No longer called. It added the whole quantity to every batch of the order's first
     * purchase, without a cap. Cancelling now goes through App\Services\Inventory\StockLedger::give(),
     * which returns the units to the batches they were taken from.
     */
    protected function restockPurchaseProductsByPurchaseId($order)
    {

        try {
            foreach ($order->orderDetails as $orderDetail) {

                // Check if order detail has a purchase_id
                if ($orderDetail->purchase_id) {
                    $productId = $orderDetail->product_id;
                    $variation = $orderDetail->variation;
                    $quantity = $orderDetail->quantity;
                    $purchaseId = $orderDetail->purchase_id;

                    $purchaseProducts = PurchaseProduct::with('purchase')
                        ->where('purchase_id', $purchaseId)
                        ->where('product_id', $productId)
                        ->where('remaining_qty', '>=', 0)
                        ->when(!empty($variation), function ($q) use ($variation) {
                            $q->where('variant', $variation);
                        })
                        ->orderBy('id', 'asc')
                        ->get();

                    if ($purchaseProducts) {
                        foreach ($purchaseProducts as $purchaseProduct) {
                            $purchaseProduct->remaining_qty += $quantity;
                            $purchaseProduct->save();
                        }
                        Log::info("Restocked {$quantity} units to purchase product ID: {$purchaseProduct->id} (Purchase ID: {$purchaseId}) for product {$productId} ({$variation})");
                    } else {
                        Log::warning("Purchase product not found for Purchase ID: {$purchaseId}, Product ID: {$productId}, Variation: {$variation}");

                        // Fallback: Try to find any purchase product for this product and variation
                        $this->restockUsingFallback($productId, $variation, $quantity);
                    }
                } else {
                    // If no purchase_id is set, use fallback method
                    $this->restockUsingFallback($orderDetail->product_id, $orderDetail->variation, $orderDetail->quantity);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error restocking purchase products by purchase ID: ' . $e->getMessage());
            throw $e;
        }
    }


    protected function restockUsingFallback($productId, $variation, $quantity)
    {

        try {
            // Find purchase products for this product and variation, order by most recent first

            $purchaseProducts = PurchaseProduct::with('purchase')
                ->where('product_id', $productId)
                ->where('remaining_qty', '>=', 0)
                ->when(!empty($variation), function ($q) use ($variation) {
                    $q->where('product_name', 'LIKE', "%({$variation})%");
                })
                ->orderBy('id', 'asc')
                ->get();

            $remainingQty = $quantity;

            foreach ($purchaseProducts as $purchaseProduct) {
                if ($remainingQty <= 0) break;

                $maxCanRestock = $purchaseProduct->purchase_quantity - $purchaseProduct->remaining_qty;
                if ($maxCanRestock <= 0) continue;

                $restockQty = min($maxCanRestock, $remainingQty);

                $purchaseProduct->remaining_qty += $restockQty;
                $purchaseProduct->save();

                Log::info("Fallback restocked {$restockQty} units to purchase product ID: {$purchaseProduct->id} for product {$productId} ({$variation})");

                $remainingQty -= $restockQty;
            }

            if ($remainingQty > 0) {
                Log::warning("Could not fully restock {$quantity} units for product {$productId} ({$variation}). Remaining: {$remainingQty}");
            }
        } catch (\Exception $e) {
            Log::error('Error in fallback restocking: ' . $e->getMessage());
        }
    }

    // Helper functions
    private function formatPathaoPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $phone = substr($phone, -11); // last 11 digits

        if (!preg_match('/^01[0-9]{9}$/', $phone)) {
            throw new \Exception("Invalid phone number format: $phone");
        }

        return $phone;
    }

    private function calculateItemQuantity($order)
    {
        return $order->orderDetails->sum('quantity');
    }

    private function calculateItemWeight($order)
    {
        $totalWeightKg = 0;
        foreach ($order->orderDetails as $detail) {
            $product = $detail->product;
            $quantity = $detail->quantity;

            if ($product->weight_unit === 'gm') {
                $totalWeightKg += ($product->weight * $quantity) / 1000;
            } else {
                $totalWeightKg += $product->weight * $quantity;
            }
        }

        return round(max(0.5, min($totalWeightKg, 10.0)), 2); // 0.5kg min, 10kg max
    }

    // Optional: Update tracking code manually
    public function update_tracking_code(Request $request)
    {
        $order = Order::findOrFail($request->order_id);
        $order->tracking_code = $request->tracking_code;
        $order->save();

        return response()->json(['success' => true]);
    }

    public function update_order_note(Request $request)
    {
        $order = Order::findOrFail($request->order_id);
        $order->order_note = $request->order_note;
        $order->save();

        return response()->json(['success' => true]);
    }

    public function update_payment_status(Request $request)
    {
        $order = Order::findOrFail($request->order_id);

        if ($order->settlement_status == 'true' || $order->settlement_status == 1) {
            flash(translate('Payment status of settled orders cannot be changed'))->error();
            return 1;
        }

        $order->payment_status_viewed = '0';
        $order->save();

        if (Auth::user()->user_type == 'seller') {
            foreach ($order->orderDetails->where('seller_id', Auth::user()->id) as $key => $orderDetail) {
                $orderDetail->payment_status = $request->status;
                $orderDetail->save();
            }
        } else {
            foreach ($order->orderDetails as $key => $orderDetail) {
                $orderDetail->payment_status = $request->status;
                $orderDetail->save();
            }
        }

        $status = 'paid';
        foreach ($order->orderDetails as $key => $orderDetail) {
            if ($orderDetail->payment_status != 'paid') {
                $status = 'unpaid';
            }
        }
        $order->payment_status = $status;
        $order->save();


        if (
            $order->payment_status == 'paid' &&
            $order->commission_calculated == 0
        ) {
            calculateCommissionAffilationClubPoint($order);
        }

        //sends Notifications to user
        NotificationUtility::sendNotification($order, $request->status);
        if (get_setting('google_firebase') == 1 && $order->user->device_token != null) {
            $request->device_token = $order->user->device_token;
            $request->title = "Order updated !";
            $status = str_replace("_", "", $order->payment_status);
            $request->text = " Your order {$order->code} has been {$status}";

            $request->type = "order";
            $request->id = $order->id;
            $request->user_id = $order->user->id;

            NotificationUtility::sendFirebaseNotification($request);
        }


        if (addon_is_activated('otp_system') && SmsTemplate::where('identifier', 'payment_status_change')->first()->status == 1) {
            try {
                SmsUtility::payment_status_change(json_decode($order->shipping_address)->phone, $order);
            } catch (\Exception $e) {
            }
        }
        return 1;
    }

    public function assign_delivery_boy(Request $request)
    {
        if (addon_is_activated('delivery_boy')) {

            $order = Order::findOrFail($request->order_id);
            $order->assign_delivery_boy = $request->delivery_boy;
            $order->delivery_history_date = date("Y-m-d H:i:s");
            $order->save();

            $delivery_history = \App\Models\DeliveryHistory::where('order_id', $order->id)
                ->where('delivery_status', $order->delivery_status)
                ->first();

            if (empty($delivery_history)) {
                $delivery_history = new \App\Models\DeliveryHistory;

                $delivery_history->order_id = $order->id;
                $delivery_history->delivery_status = $order->delivery_status;
                $delivery_history->payment_type = $order->payment_type;
            }
            $delivery_history->delivery_boy_id = $request->delivery_boy;

            $delivery_history->save();

            if (env('MAIL_USERNAME') != null && get_setting('delivery_boy_mail_notification') == '1') {
                $array['view'] = 'emails.invoice';
                $array['subject'] = translate('You are assigned to delivery an order. Order code') . ' - ' . $order->code;
                $array['from'] = env('MAIL_FROM_ADDRESS');
                $array['order'] = $order;

                try {
                    Mail::to($order->delivery_boy->email)->queue(new InvoiceEmailManager($array));
                } catch (\Exception $e) {
                }
            }

            if (addon_is_activated('otp_system') && SmsTemplate::where('identifier', 'assign_delivery_boy')->first()->status == 1) {
                try {
                    SmsUtility::assign_delivery_boy($order->delivery_boy->phone, $order->code);
                } catch (\Exception $e) {
                }
            }
        }

        return 1;
    }

    public function orderBulkExport(Request $request)
    {
        if ($request->id) {
            return Excel::download(new OrdersExport($request->id), 'orders.xlsx');
        }
        return back();
    }

    public function bulkDocumentDownload(Request $request)
    {
        $orderIds = $request->id;
        $type = $request->type;

        if (!is_array($orderIds) || !in_array($type, ['invoice', 'payslip'])) {
            return back()->with('error', 'Invalid request.');
        }

        // For payslip type, create a ZIP file with individual PDFs
        if ($type === 'payslip') {
            $zipFileName = 'payslips_' . date('Y-m-d_H-i-s') . '.zip';
            $zip = new \ZipArchive();
            $zipPath = storage_path('app/' . $zipFileName);

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
                return back()->with('error', 'Could not create ZIP file.');
            }

            foreach ($orderIds as $id) {
                $order = Order::find($id);
                if (!$order) continue;

                $currency_code = Session::get('currency_code', Currency::findOrFail(get_setting('system_default_currency'))->code);
                $language_code = Session::get('locale', Config::get('app.locale'));
                $rtl = Language::where('code', $language_code)->first()->rtl ?? 0;
                $font_family = match (true) {
                    $currency_code == 'BDT' || $language_code == 'bd' => "'Hind Siliguri','freeserif'",
                    $currency_code == 'KHR' || $language_code == 'kh' => "'Hanuman','sans-serif'",
                    $currency_code == 'AMD' => "'arnamu','sans-serif'",
                    in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) ||
                        in_array($language_code, ['sa', 'ir', 'om', 'jo']) => "xbriyaz",
                    $currency_code == 'THB' || $language_code == 'th' => "'Kanit','sans-serif'",
                    $currency_code == 'CNY' || $language_code == 'zh' => "'sun-exta','gb'",
                    $currency_code == 'MMK' || $language_code == 'mm' => 'tharlon',
                    $currency_code == 'USD' => "'Roboto','sans-serif'",
                    default => "freeserif",
                };

                $pdf_style_data = FontUtility::get_font_family();
                $html = view('backend.sales.thermal_invoice_new', compact('order', 'pdf_style_data', 'font_family'))->render();

                // Create a new mPDF instance for each payslip with custom size
                $payslipPdf = new Mpdf([
                    'mode' => 'utf-8',
                    'format' => [76.2, 101.6],
                    'default_font_size' => 8,
                    'margin_left' => 3,
                    'margin_right' => 3,
                    'margin_top' => 3,
                    'margin_bottom' => 3,
                ]);

                $payslipPdf->WriteHTML($html);
                $pdfContent = $payslipPdf->Output('', 'S');

                $fileName = 'payslip_' . $order->id . '_' . date('Y-m-d') . '.pdf';
                $zip->addFromString($fileName, $pdfContent);
            }

            $zip->close();

            // Download the ZIP file
            return response()->download($zipPath)->deleteFileAfterSend(true);
        }

        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);

        foreach ($orderIds as $id) {
            $order = Order::find($id);
            if (!$order) continue;

            $currency_code = Session::get('currency_code', Currency::findOrFail(get_setting('system_default_currency'))->code);
            $language_code = Session::get('locale', Config::get('app.locale'));
            $rtl = Language::where('code', $language_code)->first()->rtl ?? 0;
            $direction = $rtl ? 'rtl' : 'ltr';
            $text_align = $rtl ? 'right' : 'left';
            $not_text_align = $rtl ? 'left' : 'right';
            $font_family = match (true) {
                $currency_code == 'BDT' || $language_code == 'bd' => "'Hind Siliguri','freeserif'",
                $currency_code == 'KHR' || $language_code == 'kh' => "'Hanuman','sans-serif'",
                $currency_code == 'AMD' => "'arnamu','sans-serif'",
                in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) ||
                    in_array($language_code, ['sa', 'ir', 'om', 'jo']) => "xbriyaz",
                $currency_code == 'THB' || $language_code == 'th' => "'Kanit','sans-serif'",
                $currency_code == 'CNY' || $language_code == 'zh' => "'sun-exta','gb'",
                $currency_code == 'MMK' || $language_code == 'mm' => 'tharlon',
                $currency_code == 'USD' => "'Roboto','sans-serif'",
                default => "freeserif",
            };

            if ($type === 'invoice') {
                $html = view('backend.invoices.invoice', compact('order', 'font_family', 'direction', 'text_align', 'not_text_align'))->render();
                $mpdf->AddPage();
                $mpdf->WriteHTML($html);
            }
        }

        return $mpdf->Output('bulk_invoices.pdf', 'D');
    }

    public function bulkDocumentPrintView(Request $request)
    {
        $orderIds = $request->id;
        $type = $request->type;

        if (!is_array($orderIds) || !in_array($type, ['invoices', 'payslips'])) {
            return back()->with('error', 'Invalid request.');
        }

        $orders = Order::whereIn('id', $orderIds)->get();

        if ($orders->isEmpty()) {
            return back()->with('error', 'No valid orders found.');
        }

        $currency_code = Session::get('currency_code', Currency::findOrFail(get_setting('system_default_currency'))->code);
        $language_code = Session::get('locale', Config::get('app.locale'));
        $rtl = Language::where('code', $language_code)->first()->rtl ?? 0;
        $direction = $rtl ? 'rtl' : 'ltr';
        $text_align = $rtl ? 'right' : 'left';
        $not_text_align = $rtl ? 'left' : 'right';

        $font_family = match (true) {
            $currency_code == 'BDT' || $language_code == 'bd' => "'Hind Siliguri','freeserif'",
            $currency_code == 'KHR' || $language_code == 'kh' => "'Hanuman','sans-serif'",
            $currency_code == 'AMD' => "'arnamu','sans-serif'",
            in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) ||
                in_array($language_code, ['sa', 'ir', 'om', 'jo']) => "xbriyaz",
            $currency_code == 'THB' || $language_code == 'th' => "'Kanit','sans-serif'",
            $currency_code == 'CNY' || $language_code == 'zh' => "'sun-exta','gb'",
            $currency_code == 'MMK' || $language_code == 'mm' => 'tharlon',
            $currency_code == 'USD' => "'Roboto','sans-serif'",
            default => "freeserif",
        };

        $pdf_style_data = FontUtility::get_font_family();

        $mpdf_config = [];
        $print_width = null;

        if ($type === 'payslips') {
            $print_width = get_setting('print_width');
            if ($print_width == null) {
                flash(translate('Thermal printer size is not given in POS configuration'))->warning();
                return back();
            }

            $mpdf_config['mode'] = 'utf-8';
            $mpdf_config['format'] = [$print_width, 110.6];
            $mpdf_config['margin_left'] = 5;
            $mpdf_config['margin_right'] = 5;
            $mpdf_config['margin_top'] = 5;
            $mpdf_config['margin_bottom'] = 5;

            $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];

            $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            $mpdf_config['fontDir'] = array_merge($fontDirs, [
                resource_path('fonts'),
            ]);
            $mpdf_config['fontdata'] = $fontData + [
                'solaiman' => [
                    'R' => 'SolaimanLipi.ttf',
                    'useOTL' => 0xFF,
                ],
            ];
            $mpdf_config['default_font'] = 'solaiman';
        } else {
            $mpdf_config['mode'] = 'utf-8';
            $mpdf_config['format'] = 'A4';
            $mpdf_config['default_font_size'] = 8;
            $mpdf_config['margin_left'] = 3;
            $mpdf_config['margin_right'] = 3;
            $mpdf_config['margin_top'] = 3;
            $mpdf_config['margin_bottom'] = 3;
        }

        $mpdf = new \Mpdf\Mpdf($mpdf_config);

        foreach ($orders as $order) {
            if ($type === 'payslips') {
                $html = view('backend.sales.thermal_invoice_new', compact(
                    'order',
                    'pdf_style_data',
                    'font_family',
                    'print_width'
                ))->render();
            } else {
                $html = view('backend.invoices.invoice', compact(
                    'order',
                    'font_family',
                    'direction',
                    'text_align',
                    'not_text_align'
                ))->render();
            }

            $mpdf->AddPage();
            $mpdf->WriteHTML($html);
        }

        // Output inline (show in browser)
        return response($mpdf->Output('', 'I'))->header('Content-Type', 'application/pdf');
    }


    public function orderEdit($id)
    {
        $order = Order::with('orderDetails.product.stocks')->findOrFail($id);
        return view('backend.sales.edit', compact('order'));
    }



    public function orderEditProductPart(Request $request)
    {
        $query = $request->data;

        $products = Product::with('stocks')
            ->where('name', 'like', "%{$query}%")
            ->orWhere('id', 'like', "%{$query}%")
            ->get();
        return response()->json($products);
    }

    public function orderEditVariationPart(Request $request)
    {
        $stock = ProductStock::where('product_id', $request->id)->get();
        return response()->json($stock);
    }


    protected function shippingAddress($request)
    {
        $shippingAddress = [];
        $shippingAddress['name']        = $request->name;
        $shippingAddress['email']       = $request->email;
        $shippingAddress['address']     = $request->address;
        $shippingAddress['country']     = $request->country ?? 'null';
        $shippingAddress['state']       = $request->state ?? 'null';
        $shippingAddress['city']        = $request->city_id ?? 'null';
        $shippingAddress['postal_code'] = $request->postal_code ?? 'null';
        $shippingAddress['phone']       = $request->phone;

        return $shippingAddress;
    }



    public function orderEditUpdate(Request $request)
    {
        $order = Order::findOrFail($request->id);

        if ($order->settlement_status == 'true' || $order->settlement_status == 1) {
            flash(translate('Settled orders cannot be edited'))->error();
            return back();
        }

        $isPosOrder = $order->order_from === 'pos';

        // Reverse accounting entries for POS orders before editing
        if ($isPosOrder) {
            $this->reverseAccountingEntries($order);
        }

        $existingOrderDetails = $order->orderDetails()->get()->keyBy('id');
        $grandTotal = preg_replace('/[^\d.]/', '', $request->grand_total);

        // Read exchange fields from request
        $isExchangeArray = $request->is_exchange ?? [];
        $exchangeFromOrderDetailIds = $request->exchange_from_order_detail_id ?? [];
        $hasExchange = !empty(array_filter($isExchangeArray));

        // Guard: block exchange if order is assigned to a courier
        if ($hasExchange && $order->courier_name !== null) {
            flash(translate('Cannot exchange products for orders already assigned to a courier'))->error();
            return back();
        }

        DB::beginTransaction();
        $this->editTakes = [];
        try {
            $this->updateAddress($request, $grandTotal);

            // Store original order details for FIFO reversal
            $originalOrderDetails = clone $existingOrderDetails;

            // Check if order_detail_id exists and is an array
            $orderDetailIds = $request->order_detail_id ?? [];
            $productIds = $request->product_id ?? [];
            $variations = $request->variation ?? [];
            $quantities = $request->qty ?? [];
            $totals = $request->total ?? [];
            $exchangedOrderDetailIds = [];

            // Process each product in the request
            foreach ($productIds as $index => $productId) {
                $orderDetailId = $orderDetailIds[$index] ?? null;
                $newVariation = $variations[$index] ?? '';
                $newQuantity = (int)$quantities[$index];
                $total = preg_replace('/[^\d.]/', '', $totals[$index]);

                $product = Product::find($productId);
                if (!$product) {
                    throw new \Exception("Product not found for ID: {$productId}");
                }

                $isExchange = !empty($isExchangeArray[$index] ?? null)
                    && !empty($exchangeFromOrderDetailIds[$index] ?? null);

                if ($isExchange) {
                    // ===== PRODUCT EXCHANGE =====
                    $oldOrderDetailId = $exchangeFromOrderDetailIds[$index];
                    $oldOrderDetail = OrderDetail::find($oldOrderDetailId);

                    if ($oldOrderDetail) {
                        // Step 1: Return old product stock to purchase batches (FIFO)
                        $this->returnPurchaseProductQtyFIFO(
                            $oldOrderDetail->product_id,
                            $oldOrderDetail->variation,
                            $oldOrderDetail->quantity,
                            $oldOrderDetail
                        );

                        // Step 2: Add old product stock back
                        $this->updateStock($oldOrderDetail->product_id, -$oldOrderDetail->quantity, $oldOrderDetail->variation);

                        // Step 3: Delete old order detail
                        $oldOrderDetail->delete();

                        $exchangedOrderDetailIds[] = $oldOrderDetailId;
                    }

                    // Step 4: Update stock for new product
                    $this->updateStock($productId, $newQuantity, $newVariation);

                    // Step 5: Apply FIFO for new product
                    $fifo = $this->consumePurchaseProductQtyFIFO($productId, $newVariation, $newQuantity);
                    $totalCost = $fifo['total_cost'];
                    $avgBuyingPrice = $newQuantity > 0 ? $totalCost / $newQuantity : 0;
                    $profit = ($total - $avgBuyingPrice * $newQuantity);

                    // Step 6: Create new order detail for the exchanged product
                    $orderDetail = new OrderDetail();
                    $orderDetail->order_id = $request->id;
                    $orderDetail->seller_id = $product->user_id ?? 9;
                    $orderDetail->product_id = $productId;
                    $orderDetail->variation = $newVariation;
                    $orderDetail->price = $total;
                    $orderDetail->selling_price = $newQuantity > 0 ? $total / $newQuantity : 0;
                    $orderDetail->tax = 0;
                    $orderDetail->shipping_cost = $order->orderDetails->first()->shipping_cost ?? 0;
                    $orderDetail->quantity = $newQuantity;
                    $orderDetail->shipping_type = $order->orderDetails->first()->shipping_type ?? 'home_delivery';
                    $orderDetail->payment_status = $order->orderDetails->first()->payment_status ?? 'unpaid';
                    $orderDetail->delivery_status = $order->orderDetails->first()->delivery_status ?? 'pending';

                    // FIFO values
                    $orderDetail->buying_price = $avgBuyingPrice;
                    $orderDetail->profit = $profit;

                    if (count($fifo['batches'])) {
                        $firstBatch = $fifo['batches'][0];
                        $orderDetail->purchase_id = $firstBatch['purchase_id'];
                        $orderDetail->supplier_id = $firstBatch['supplier_id'];
                    }

                    $orderDetail->save();
                } elseif ($orderDetailId && $existingOrderDetails->has($orderDetailId)) {
                    // Existing order detail - update
                    $orderDetail = $existingOrderDetails[$orderDetailId];
                    $oldQuantity = $orderDetail->quantity;
                    $oldVariation = $orderDetail->variation;

                    // Check if variation has changed
                    if ($oldVariation != $newVariation) {
                        // VARIATION CHANGED - Handle as removal of old + addition of new

                        // Step 1: Return the old variant stock to purchase batches
                        $this->returnPurchaseProductQtyFIFO(
                            $productId,
                            $oldVariation,
                            $oldQuantity,
                            $orderDetail
                        );

                        // Step 2: Update product stock for old variant (add back)
                        $this->updateStock($productId, -$oldQuantity, $oldVariation); // Negative to add back

                        // Step 3: Update product stock for new variant (reduce)
                        $this->updateStock($productId, $newQuantity, $newVariation);

                        // Step 4: Apply FIFO for new variant
                        $fifo = $this->consumePurchaseProductQtyFIFO($productId, $newVariation, $newQuantity);
                        $totalCost = $fifo['total_cost'];
                        $avgBuyingPrice = $newQuantity > 0 ? $totalCost / $newQuantity : 0;
                        $profit = ($total - $avgBuyingPrice * $newQuantity);

                        // Step 5: Update order detail with new variant
                        $orderDetail->variation = $newVariation;
                        $orderDetail->quantity = $newQuantity;
                        $orderDetail->price = $total;
                        $orderDetail->selling_price = $newQuantity > 0 ? $total / $newQuantity : 0;
                        $orderDetail->buying_price = $avgBuyingPrice;
                        $orderDetail->profit = $profit;

                        // Update purchase/supplier info
                        if (count($fifo['batches'])) {
                            $firstBatch = $fifo['batches'][0];
                            $orderDetail->purchase_id = $firstBatch['purchase_id'];
                            $orderDetail->supplier_id = $firstBatch['supplier_id'];
                        }

                        $orderDetail->save();
                    } else {
                        // Same variation - handle quantity change only
                        $this->handleFIFOQuantityAdjustment($orderDetail, $oldQuantity, $newQuantity, $productId, $oldVariation);

                        // Update order details
                        $orderDetail->quantity = $newQuantity;
                        $orderDetail->price = $total;
                        $orderDetail->selling_price = $newQuantity > 0 ? $total / $newQuantity : 0;
                        $orderDetail->save();
                    }
                } else {
                    // New item added to order
                    $this->updateStock($productId, $newQuantity, $newVariation);

                    // Apply FIFO for new item
                    $fifo = $this->consumePurchaseProductQtyFIFO($productId, $newVariation, $newQuantity);
                    $totalCost = $fifo['total_cost'];
                    $avgBuyingPrice = $newQuantity > 0 ? $totalCost / $newQuantity : 0;
                    $profit = ($total - $avgBuyingPrice * $newQuantity);

                    $orderDetail = new OrderDetail();
                    $orderDetail->order_id = $request->id;
                    $orderDetail->seller_id = $product->user_id ?? 9;
                    $orderDetail->product_id = $productId;
                    $orderDetail->variation = $newVariation;
                    $orderDetail->price = $total;
                    $orderDetail->selling_price = $newQuantity > 0 ? $total / $newQuantity : 0;
                    $orderDetail->tax = 0;
                    $orderDetail->shipping_cost = $order->orderDetails->first()->shipping_cost ?? 0;
                    $orderDetail->quantity = $newQuantity;
                    $orderDetail->shipping_type = $order->orderDetails->first()->shipping_type ?? 'home_delivery';
                    $orderDetail->payment_status = $order->orderDetails->first()->payment_status ?? 'unpaid';
                    $orderDetail->delivery_status = $order->orderDetails->first()->delivery_status ?? 'pending';

                    // FIFO values
                    $orderDetail->buying_price = $avgBuyingPrice;
                    $orderDetail->profit = $profit;

                    if (count($fifo['batches'])) {
                        $firstBatch = $fifo['batches'][0];
                        $orderDetail->purchase_id = $firstBatch['purchase_id'];
                        $orderDetail->supplier_id = $firstBatch['supplier_id'];
                    }

                    $orderDetail->save();
                }
            }

            // Handle removed items - return stock to purchase batches (exclude exchange-processed IDs)
            $currentOrderDetailIds = array_filter($orderDetailIds); // Remove null values
            $this->handleRemovedItems($originalOrderDetails, $currentOrderDetailIds, $exchangedOrderDetailIds);

            $this->updateOrder($order, $grandTotal);

            // Recreate accounting entries for POS orders after successful edit
            if ($isPosOrder) {
                $this->recreatePosAccountingEntries($order);
            }

            DB::commit();

            flash(translate('Order has been updated successfully'))->success();
            return back();
        } catch (\Exception $e) {
            DB::rollback();
            // Batch units taken by this edit that the rollback could not restore (MyISAM) go back by hand.
            foreach ($this->editTakes as $take) {
                app(StockLedger::class)->release($take);
            }
            flash(translate($e->getMessage()))->error();
            return back();
        }
    }

    protected function handleFIFOQuantityAdjustment($orderDetail, $oldQuantity, $newQuantity, $productId, $variation)
    {
        $quantityDifference = $newQuantity - $oldQuantity;

        if ($quantityDifference == 0) {
            return; // No change in quantity
        }

        if ($quantityDifference > 0) {
            // Quantity increased - consume more from purchase batches
            $fifo = $this->consumePurchaseProductQtyFIFO($productId, $variation, $quantityDifference);
            $this->updateOrderDetailFIFO($orderDetail, $fifo, $oldQuantity, $newQuantity);

            // Update product stock for increase
            $this->updateStock($productId, $quantityDifference, $variation);
        } else {
            // Quantity decreased - return stock to purchase batches
            $this->returnPurchaseProductQtyFIFO($productId, $variation, abs($quantityDifference), $orderDetail);
            $this->updateOrderDetailFIFOOnDecrease($orderDetail, $oldQuantity, $newQuantity);

            // Update product stock for decrease (negative difference means add back)
            $this->updateStock($productId, $quantityDifference, $variation); // quantityDifference is negative
        }
    }

    protected function returnPurchaseProductQtyFIFO($productId, $variant, $qtyToReturn, $orderDetail = null)
    {
        // The line's own batches first, then the newest with room; never above what was bought.
        app(StockLedger::class)->returnBatchUnits((int) $productId, $variant, (int) $qtyToReturn, $orderDetail instanceof OrderDetail ? $orderDetail : null);
    }

    /**
     * Handle remaining quantity that needs to be returned when no specific batches are found
     */
    protected function handleRemainingReturnQty($productId, $variant, $remainingQty)
    {
        // Handled by StockLedger::returnBatchUnits(); kept so nothing that calls it breaks.
        app(StockLedger::class)->returnBatchUnits((int) $productId, $variant, (int) $remainingQty);
    }
    /**
     * Update order detail FIFO information when quantity increases
     */
    protected function updateOrderDetailFIFO($orderDetail, $newFifo, $oldQuantity, $newQuantity)
    {
        $oldTotalCost = $orderDetail->buying_price * $oldQuantity;
        $newTotalCost = $oldTotalCost + $newFifo['total_cost'];
        $newAvgBuyingPrice = $newQuantity > 0 ? $newTotalCost / $newQuantity : 0;

        $sellingPrice = $orderDetail->selling_price;
        $newProfit = ($sellingPrice * $newQuantity) - ($newAvgBuyingPrice * $newQuantity);

        $orderDetail->buying_price = $newAvgBuyingPrice;
        $orderDetail->profit = $newProfit;

        // Update purchase/supplier info if new batches were used
        if (count($newFifo['batches'])) {
            $firstBatch = $newFifo['batches'][0];
            $orderDetail->purchase_id = $firstBatch['purchase_id'];
            $orderDetail->supplier_id = $firstBatch['supplier_id'];
        }

        $orderDetail->save();
    }


    /**
     * Update order detail FIFO information when quantity decreases
     */
    protected function updateOrderDetailFIFOOnDecrease($orderDetail, $oldQuantity, $newQuantity)
    {
        if ($newQuantity == 0) {
            // If quantity becomes zero, we'll delete the order detail elsewhere
            return;
        }

        // For quantity decrease, we need to recalculate the average buying price
        // Since we returned stock to purchase batches, the cost should be proportionally reduced
        $proportion = $newQuantity / $oldQuantity;

        // Recalculate profit based on the proportional cost
        $newBuyingPrice = $orderDetail->buying_price; // This remains the same as we returned the exact cost
        $sellingPrice = $orderDetail->selling_price;
        $newProfit = ($sellingPrice * $newQuantity) - ($newBuyingPrice * $newQuantity);

        $orderDetail->profit = $newProfit;
        $orderDetail->save();
    }

    /**
     * Update order details with FIFO calculations
     */
    protected function updateOrderDetailsWithFIFO($orderDetail, $product, $variation, $quantity, $total)
    {
        $sellingPrice = $quantity > 0 ? $total / $quantity : 0;

        $orderDetail->variation = $variation;
        $orderDetail->price = $total;
        $orderDetail->selling_price = $sellingPrice;
        $orderDetail->quantity = $quantity;
        $orderDetail->save();
    }

    /**
     * Handle items that were removed from the order
     */
    protected function handleRemovedItems($originalOrderDetails, $currentOrderDetailIds, $exchangeOrderDetailIds = [])
    {
        $removedItems = $originalOrderDetails->filter(function ($orderDetail) use ($currentOrderDetailIds, $exchangeOrderDetailIds) {
            return !in_array($orderDetail->id, (array)$currentOrderDetailIds)
                && !in_array($orderDetail->id, $exchangeOrderDetailIds);
        });

        foreach ($removedItems as $removedOrderDetail) {
            // Return the full quantity to purchase batches
            $this->returnPurchaseProductQtyFIFO(
                $removedOrderDetail->product_id,
                $removedOrderDetail->variation,
                $removedOrderDetail->quantity,
                $removedOrderDetail
            );

            // Update product stock - add back the quantity
            $this->updateStock(
                $removedOrderDetail->product_id,
                -$removedOrderDetail->quantity, // Negative to add back to stock
                $removedOrderDetail->variation
            );

            // Delete the order detail
            $removedOrderDetail->delete();
        }
    }

    // Your existing helper methods (keep these as they are correct)
    protected function updatePreviousStock($prevousStock, $orderDetail)
    {
        if ($prevousStock) {
            // One statement, not "read the row, add in PHP, save": nobody's sale in between is erased.
            app(StockLedger::class)->adjust((int) $prevousStock->product_id, $prevousStock->variant, (int) $orderDetail->quantity);
        }
    }

    protected function updateOrder($order, $grandTotal, $extraDiscount = 0)
    {
        $order->update_grand_total = $grandTotal;
        $order->grand_total = $grandTotal;
        $order->extra_discount = $extraDiscount;
        $order->save();
    }

    protected function UpdateOrderDetails($orderDetail, $variation, $quantity, $total)
    {
        $orderDetail->quantity = $quantity;
        $orderDetail->variation = $variation;
        $orderDetail->price = $total;
        $orderDetail->save();
    }

    protected function updateStock($productId, $quantity, $variation)
    {
        // Positive $quantity takes units off the shelf, negative puts them back. One conditional
        // statement: it cannot go below zero and cannot erase a sale made in between.
        if (!app(StockLedger::class)->adjust((int) $productId, $variation, -(int) $quantity)) {
            $name = optional(Product::find($productId))->name ?? ('product #' . $productId);
            throw new \Exception(translate('Not enough stock for ') . $name . ($variation !== null && $variation !== '' ? ' (' . $variation . ')' : ''));
        }
    }

    protected function updateAddress($request, $grandTotal)
    {
        // dd($grandTotal);
        $order = Order::with('orderdetails')->findOrFail($request->id);

        if ($order->order_from == 'web') {
            $conbineOrder = CombinedOrder::findOrFail($order->combined_order_id);
            // dd($conbineOrder);
        }

        DB::beginTransaction();
        try {

            $shippingAddress = $this->shippingAddress($request);

            if ($order->order_from == 'web') {
                $conbineOrder->shipping_address = json_encode($shippingAddress);
                $conbineOrder->grand_total = $grandTotal;
                $conbineOrder->save();
                // dd($conbineOrder);
            }
            $order->shipping_address = json_encode($shippingAddress);
            $order->save();

            foreach ($order->orderdetails as $details) {
                $details->shipping_cost = preg_replace('/[^\d.]/', '', $request->shipping_cost);
                $details->save();
            }

            DB::commit();
        } catch (\Exception $e) {

            DB::rollback();
        }
    }


    public function orderEditDelete(Request $request)
    {
        $orderDetail = OrderDetail::findOrFail($request->id);
        $order = $orderDetail->order;

        if ($order && ($order->settlement_status == 'true' || $order->settlement_status == 1)) {
            return response()->json(['status' => 'Settled orders cannot be edited'], 403);
        }

        DB::beginTransaction();
        try {
            // The line's units go back to the shelf and to the batches they came from, once: a line that
            // was already cancelled gave them back then. (The old code matched batches by a name pattern
            // and could return units to a different variation.)
            app(StockLedger::class)->give($orderDetail, true);

            $orderDetail->delete();

            DB::commit();
            return response()->json(['status' => 200], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => $e->getMessage()], 500);
        }
    }

    /**
     * Reverse all accounting entries for a POS order
     */
    protected function reverseAccountingEntries($order)
    {
        $orderLogs = AccountStatementLog::where('order_id', $order->id)->get();
        foreach ($orderLogs as $log) {
            $account = ChartOfAccount::find($log->account_id);
            if (!$account) continue;

            switch ($log->transaction_type) {
                case 'order_payment':
                case 'order_due':
                case 'order_cogs':
                case 'order_sales':
                case 'order_refund':
                    $account->current_balance -= $log->debit + $log->credit;
                    break;
                case 'order_inventory':
                    $account->current_balance += $log->debit + $log->credit;
                    break;
            }
            $account->save();
        }

        // Delete old statement logs for this order
        AccountStatementLog::where('order_id', $order->id)->delete();
    }

    /**
     * Recreate POS accounting entries after order edit
     */
    protected function recreatePosAccountingEntries($order)
    {
        $transactionDate = now()->toDateString();
        $paymentMethods = json_decode($order->payment_methods, true) ?? [];
        $paymentAmounts = json_decode($order->payment_amounts, true) ?? [];
        $totalPaid = array_sum($paymentAmounts);
        $grandTotal = $order->grand_total;
        $totalDue = max(0, $grandTotal - $totalPaid);

        // Calculate COGS from order details
        $totalCOGS = 0;
        foreach ($order->orderDetails as $detail) {
            $totalCOGS += $detail->buying_price * $detail->quantity;
        }

        // Payment entries (debit each payment account)
        foreach ($paymentMethods as $index => $accountId) {
            $amount = $paymentAmounts[$index] ?? 0;
            if ($amount <= 0) continue;

            $account = ChartOfAccount::find($accountId);
            if ($account) {
                $account->current_balance += $amount;
                $account->save();

                \App\Utility\PosUtility::insertOrderStatementLog(
                    $accountId,
                    $transactionDate,
                    $order->code,
                    'order_payment',
                    $amount,
                    0,
                    'POS Order payment (edited): ' . $order->code,
                    $order
                );
            }
        }

        // AR entry for due amount
        if ($totalDue > 0) {
            $accountsReceivable = ChartOfAccount::where('type', 'asset')
                ->where('sub_type', 'receivable')
                ->where('name', 'Accounts Receivable')
                ->where('status', 'active')
                ->first();
            if ($accountsReceivable) {
                $accountsReceivable->current_balance += $totalDue;
                $accountsReceivable->save();

                \App\Utility\PosUtility::insertOrderStatementLog(
                    $accountsReceivable->id,
                    $transactionDate,
                    $order->code,
                    'order_due',
                    $totalDue,
                    0,
                    'POS Order due amount (edited): ' . $order->code,
                    $order
                );
            }
        }

        // COGS entry (debit)
        if ($totalCOGS > 0) {
            $cogsAccount = ChartOfAccount::where('type', 'expense')
                ->where('sub_type', 'cost_of_sales')
                ->where('name', 'Cost of Goods Sold')
                ->where('status', 'active')
                ->first();
            if ($cogsAccount) {
                $cogsAccount->current_balance += $totalCOGS;
                $cogsAccount->save();

                \App\Utility\PosUtility::insertOrderStatementLog(
                    $cogsAccount->id,
                    $transactionDate,
                    $order->code,
                    'order_cogs',
                    $totalCOGS,
                    0,
                    'COGS for POS Order (edited): ' . $order->code,
                    $order
                );
            }
        }

        // Sales entry (credit)
        $salesAccount = ChartOfAccount::where('type', 'income')
            ->where('sub_type', 'sales')
            ->where('name', 'Sales')
            ->where('status', 'active')
            ->first();
        if (!$salesAccount) {
            $salesAccount = ChartOfAccount::where('code', '4-4100')
                ->where('sub_type', 'sales')
                ->where('status', 'active')
                ->first();
        }
        if ($salesAccount) {
            $salesAccount->current_balance += $grandTotal;
            $salesAccount->save();

            \App\Utility\PosUtility::insertOrderStatementLog(
                $salesAccount->id,
                $transactionDate,
                $order->code,
                'order_sales',
                0,
                $grandTotal,
                'Sales revenue for POS Order (edited): ' . $order->code,
                $order
            );
        }

        // Inventory entry (credit)
        if ($totalCOGS > 0) {
            $inventoryAccount = ChartOfAccount::where('code', '1-15001')
                ->where('sub_type', 'inventory')
                ->where('status', 'active')
                ->first();
            if ($inventoryAccount) {
                $inventoryAccount->current_balance -= $totalCOGS;
                $inventoryAccount->save();

                \App\Utility\PosUtility::insertOrderStatementLog(
                    $inventoryAccount->id,
                    $transactionDate,
                    $order->code,
                    'order_inventory',
                    0,
                    $totalCOGS,
                    'Inventory reduction for POS Order (edited): ' . $order->code,
                    $order
                );
            }
        }

        // Handle overpayment (edit reduced total below what was paid)
        $overpayment = max(0, $totalPaid - $grandTotal);
        if ($overpayment > 0 && $salesAccount) {
            // Reduce sales to avoid overstating revenue
            $salesAccount->current_balance -= $overpayment;
            $salesAccount->save();

            \App\Utility\PosUtility::insertOrderStatementLog(
                $salesAccount->id,
                $transactionDate,
                $order->code,
                'order_refund',
                0,
                $overpayment,
                'Sales reduction (refund) for overpayment after order edit: ' . $order->code,
                $order
            );

            // Create a payable/credit note for the refund owed to customer
            $accountsPayable = ChartOfAccount::getAccountsPayable();
            if ($accountsPayable) {
                $accountsPayable->current_balance += $overpayment;
                $accountsPayable->save();

                \App\Utility\PosUtility::insertOrderStatementLog(
                    $accountsPayable->id,
                    $transactionDate,
                    $order->code,
                    'order_refund',
                    $overpayment,
                    0,
                    'Refund payable for overpayment after order edit: ' . $order->code,
                    $order
                );
            }
        }
    }

    /**
     * Get order items JSON for courier settlement modal
     */
    public function fetchOrderItemsJson($id)
    {
        $order = Order::with(['orderDetails.product', 'orderDetails.product.stocks'])->findOrFail($id);
        return response()->json([
            'success' => true,
            'order' => $order,
            'orderDetails' => $order->orderDetails
        ]);
    }

    /**
     * Update order items status from courier settlement modal
     * Supports: delivered, exchange, return, cancelled
     * For exchange: creates a new order with Ex- prefix and same courier
     */
    public function processUpdateItemsStatus(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'updates' => 'required|array',
            'updates.*.order_detail_id' => 'required|exists:order_details,id',
            'updates.*.delivery_status' => 'required|in:delivered,exchange,return,cancelled',
            'updates.*.quantity' => 'nullable|integer|min:1',
            'updates.*.shipping_cost' => 'nullable|numeric|min:0',
            'updates.*.exchange_product_id' => 'nullable|exists:products,id',
            'updates.*.exchange_variation' => 'nullable|string',
            'updates.*.exchange_quantity' => 'nullable|integer|min:1',
        ]);

        $order = Order::findOrFail($request->order_id);

        if ($order->settlement_status == 'true' || $order->settlement_status == 1) {
            return response()->json(['success' => false, 'message' => 'Settled orders cannot be modified'], 400);
        }

        $isPosOrder = $order->order_from === 'pos';

        // Reverse accounting entries for POS orders before making changes
        if ($isPosOrder) {
            $this->reverseAccountingEntries($order);
        }

        DB::beginTransaction();
        try {
            $exchangeUpdates = [];
            $deliveredTotal = 0;

            foreach ($request->updates as $update) {
                $orderDetail = OrderDetail::findOrFail($update['order_detail_id']);
                $newStatus = $update['delivery_status'];
                $originalStatus = $orderDetail->delivery_status;
                $statusChanged = $newStatus !== $originalStatus;

                if (isset($update['quantity']) && $update['quantity'] > 0) {
                    $orderDetail->quantity = $update['quantity'];
                }
                if (isset($update['shipping_cost'])) {
                    $orderDetail->shipping_cost = $update['shipping_cost'];
                }

                $orderDetail->delivery_status = $newStatus;
                $orderDetail->save();

                if ($newStatus === 'delivered') {
                    $deliveredTotal += $orderDetail->price + $orderDetail->shipping_cost;
                } elseif ($newStatus === 'exchange') {
                    if (!empty($update['exchange_product_id'])) {
                        $exchangeUpdates[] = $update;
                    }
                    if ($statusChanged) {
                        $this->returnPurchaseProductQtyFIFO(
                            $orderDetail->product_id,
                            $orderDetail->variation,
                            $orderDetail->quantity,
                            $orderDetail
                        );
                        $this->updateStock(
                            $orderDetail->product_id,
                            -$orderDetail->quantity,
                            $orderDetail->variation
                        );
                    }
                } elseif ($newStatus === 'return' || $newStatus === 'cancelled') {
                    if ($statusChanged) {
                        $this->returnPurchaseProductQtyFIFO(
                            $orderDetail->product_id,
                            $orderDetail->variation,
                            $orderDetail->quantity,
                            $orderDetail
                        );
                        $this->updateStock(
                            $orderDetail->product_id,
                            -$orderDetail->quantity,
                            $orderDetail->variation
                        );
                    }
                }
            }

            // Create exchange order if there are exchanged items
            if (!empty($exchangeUpdates)) {
                $exchangeOrder = new Order();
                $exchangeOrder->code = 'Ex-' . $order->code;
                $exchangeOrder->user_id = $order->user_id;
                $exchangeOrder->seller_id = $order->seller_id;
                $exchangeOrder->shipping_address = $order->shipping_address;
                $exchangeOrder->courier_name = $order->courier_name;
                $exchangeOrder->courier_status = 'pending';
                $exchangeOrder->order_from = $order->order_from ?? 'web';
                $exchangeOrder->payment_status = 'unpaid';
                $exchangeOrder->grand_total = 0;
                $exchangeOrder->save();

                $exchangeGrandTotal = 0;

                foreach ($exchangeUpdates as $item) {
                    $product = Product::findOrFail($item['exchange_product_id']);
                    $variation = $item['exchange_variation'] ?? '';
                    $qty = $item['exchange_quantity'] ?? 1;
                    $discountAmount = 0;
                    if ($product->discount_type == 'amount') {
                        $discountAmount = $product->discount;
                    } elseif ($product->discount_type == 'percent') {
                        $discountAmount = $product->unit_price * ($product->discount / 100);
                    }
                    $sellingPrice = $product->unit_price - $discountAmount;
                    $price = $product->unit_price;
                    $fifoData = $this->consumePurchaseProductQtyFIFO($product->id, $variation, $qty);
                    $totalCost = $fifoData['total_cost'] ?? ($price * $qty);
                    $avgBuyingPrice = $qty > 0 ? $totalCost / $qty : 0;
                    $profit = ($sellingPrice * $qty) - $totalCost;

                    $this->updateStock($product->id, $qty, $variation);

                    $exchangeDetail = new OrderDetail();
                    $exchangeDetail->order_id = $exchangeOrder->id;
                    $exchangeDetail->seller_id = $product->user_id ?? 9;
                    $exchangeDetail->product_id = $product->id;
                    $exchangeDetail->variation = $variation;
                    $exchangeDetail->price = $sellingPrice * $qty;
                    $exchangeDetail->selling_price = $sellingPrice;
                    $exchangeDetail->tax = 0;
                    $exchangeDetail->shipping_cost = $item['shipping_cost'] ?? 0;
                    $exchangeDetail->quantity = $qty;
                    $exchangeDetail->shipping_type = 'home_delivery';
                    $exchangeDetail->payment_status = 'unpaid';
                    $exchangeDetail->delivery_status = 'pending';
                    $exchangeDetail->buying_price = $avgBuyingPrice;
                    $exchangeDetail->profit = $profit;
                    if (count($fifoData['batches'] ?? [])) {
                        $firstBatch = $fifoData['batches'][0];
                        $exchangeDetail->purchase_id = $firstBatch['purchase_id'] ?? null;
                        $exchangeDetail->supplier_id = $firstBatch['supplier_id'] ?? null;
                    }
                    $exchangeDetail->save();

                    $exchangeGrandTotal += ($sellingPrice * $qty) + ($exchangeDetail->shipping_cost);
                }

                $exchangeOrder->grand_total = $exchangeGrandTotal;
                $exchangeOrder->due_amount = $exchangeGrandTotal;
                $exchangeOrder->save();

                // For POS orders, create AR entry for exchange order
                if ($isPosOrder && $exchangeGrandTotal > 0) {
                    $accountsReceivable = ChartOfAccount::where('type', 'asset')
                        ->where('sub_type', 'receivable')
                        ->where('name', 'Accounts Receivable')
                        ->where('status', 'active')
                        ->first();
                    if ($accountsReceivable) {
                        $accountsReceivable->current_balance += $exchangeGrandTotal;
                        $accountsReceivable->save();

                        \App\Utility\PosUtility::insertOrderStatementLog(
                            $accountsReceivable->id,
                            now()->toDateString(),
                            $exchangeOrder->code,
                            'order_due',
                            $exchangeGrandTotal,
                            0,
                            'Exchange order AR: ' . $exchangeOrder->code,
                            $exchangeOrder
                        );
                    }
                }
            }

            // Recalculate original order grand_total (only delivered items)
            $order->grand_total = $deliveredTotal;
            $order->courier_status = 'partial_delivered';
            // Update due_amount to reflect only delivered items
            if ($isPosOrder) {
                $paymentAmounts = json_decode($order->payment_amounts, true) ?? [];
                $totalPaid = array_sum($paymentAmounts);
                $order->due_amount = max(0, $deliveredTotal - $totalPaid);
            } else {
                $order->due_amount = $deliveredTotal;
            }
            $order->save();

            // Recreate accounting entries for POS orders after changes
            if ($isPosOrder) {
                $this->recreatePosAccountingForSettlement($order);
            }

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Recreate POS accounting entries for settled items only
     * Only accounts for items with delivery_status = 'delivered'
     */
    protected function recreatePosAccountingForSettlement($order)
    {
        $transactionDate = now()->toDateString();
        $paymentMethods = json_decode($order->payment_methods, true) ?? [];
        $paymentAmounts = json_decode($order->payment_amounts, true) ?? [];
        $totalPaid = array_sum($paymentAmounts);
        $grandTotal = $order->grand_total;
        $totalDue = max(0, $grandTotal - $totalPaid);

        // Only calculate COGS for delivered items
        $totalCOGS = 0;
        foreach ($order->orderDetails as $detail) {
            if ($detail->delivery_status === 'delivered') {
                $totalCOGS += $detail->buying_price * $detail->quantity;
            }
        }

        // Payment entries (debit each payment account) - only up to delivered total
        foreach ($paymentMethods as $index => $accountId) {
            $amount = $paymentAmounts[$index] ?? 0;
            if ($amount <= 0) continue;

            $account = ChartOfAccount::find($accountId);
            if ($account) {
                $account->current_balance += $amount;
                $account->save();

                \App\Utility\PosUtility::insertOrderStatementLog(
                    $accountId,
                    $transactionDate,
                    $order->code,
                    'order_payment',
                    $amount,
                    0,
                    'POS Order payment (settlement update): ' . $order->code,
                    $order
                );
            }
        }

        // AR entry for due amount
        if ($totalDue > 0) {
            $accountsReceivable = ChartOfAccount::where('type', 'asset')
                ->where('sub_type', 'receivable')
                ->where('name', 'Accounts Receivable')
                ->where('status', 'active')
                ->first();
            if ($accountsReceivable) {
                $accountsReceivable->current_balance += $totalDue;
                $accountsReceivable->save();

                \App\Utility\PosUtility::insertOrderStatementLog(
                    $accountsReceivable->id,
                    $transactionDate,
                    $order->code,
                    'order_due',
                    $totalDue,
                    0,
                    'POS Order due amount (settlement update): ' . $order->code,
                    $order
                );
            }
        }

        // COGS entry (debit) - only for delivered items
        if ($totalCOGS > 0) {
            $cogsAccount = ChartOfAccount::where('type', 'expense')
                ->where('sub_type', 'cost_of_sales')
                ->where('name', 'Cost of Goods Sold')
                ->where('status', 'active')
                ->first();
            if ($cogsAccount) {
                $cogsAccount->current_balance += $totalCOGS;
                $cogsAccount->save();

                \App\Utility\PosUtility::insertOrderStatementLog(
                    $cogsAccount->id,
                    $transactionDate,
                    $order->code,
                    'order_cogs',
                    $totalCOGS,
                    0,
                    'COGS for POS Order (settlement update): ' . $order->code,
                    $order
                );
            }
        }

        // Sales entry (credit) - only for delivered items grand_total
        $salesAccount = ChartOfAccount::where('type', 'income')
            ->where('sub_type', 'sales')
            ->where('name', 'Sales')
            ->where('status', 'active')
            ->first();
        if (!$salesAccount) {
            $salesAccount = ChartOfAccount::where('code', '4-4100')
                ->where('sub_type', 'sales')
                ->where('status', 'active')
                ->first();
        }
        if ($salesAccount) {
            $salesAccount->current_balance += $grandTotal;
            $salesAccount->save();

            \App\Utility\PosUtility::insertOrderStatementLog(
                $salesAccount->id,
                $transactionDate,
                $order->code,
                'order_sales',
                0,
                $grandTotal,
                'Sales revenue for POS Order (settlement update): ' . $order->code,
                $order
            );
        }

        // Inventory entry (credit) - only for delivered items
        if ($totalCOGS > 0) {
            $inventoryAccount = ChartOfAccount::where('code', '1-15001')
                ->where('sub_type', 'inventory')
                ->where('status', 'active')
                ->first();
            if ($inventoryAccount) {
                $inventoryAccount->current_balance -= $totalCOGS;
                $inventoryAccount->save();

                \App\Utility\PosUtility::insertOrderStatementLog(
                    $inventoryAccount->id,
                    $transactionDate,
                    $order->code,
                    'order_inventory',
                    0,
                    $totalCOGS,
                    'Inventory reduction for POS Order (settlement update): ' . $order->code,
                    $order
                );
            }
        }

        // Handle overpayment (delivered total less than what was paid)
        $overpayment = max(0, $totalPaid - $grandTotal);
        if ($overpayment > 0 && $salesAccount) {
            $salesAccount->current_balance -= $overpayment;
            $salesAccount->save();

            \App\Utility\PosUtility::insertOrderStatementLog(
                $salesAccount->id,
                $transactionDate,
                $order->code,
                'order_refund',
                0,
                $overpayment,
                'Sales reduction (refund) for overpayment after settlement update: ' . $order->code,
                $order
            );

            $accountsPayable = ChartOfAccount::getAccountsPayable();
            if ($accountsPayable) {
                $accountsPayable->current_balance += $overpayment;
                $accountsPayable->save();

                \App\Utility\PosUtility::insertOrderStatementLog(
                    $accountsPayable->id,
                    $transactionDate,
                    $order->code,
                    'order_refund',
                    $overpayment,
                    0,
                    'Refund payable for overpayment after settlement update: ' . $order->code,
                    $order
                );
            }
        }
    }
}
