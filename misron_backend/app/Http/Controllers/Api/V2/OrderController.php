<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\Address;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Product;
use App\Models\OrderDetail;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\BusinessSetting;
use App\Models\User;
use DB;
use Exception;
use Illuminate\Support\Facades\Crypt;
use App\Utility\NotificationUtility;
use App\Models\CombinedOrder;
use App\Models\PurchaseProduct;
use App\Http\Controllers\Api\V2\AffiliateController as ApiAffiliateController;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\V2\ProductMiniCollection;
use App\Models\Draft;
use App\Models\AffiliateUser;
use App\Models\BlockedIp;
use App\Models\CheckoutOtp;
use Illuminate\Support\Facades\Cookie;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\Exceptions\InventoryException;

class OrderController extends Controller
{
    /**
     * Flat charge, in Taka, for gift wrapping an order. Charged once per order,
     * whatever the basket holds.
     */
    const GIFT_BOX_CHARGE = 100;

    public function userOrders(Request $request)
    {
        $user = auth()->user();

        // Load orderDetails with product relationship
        $orders = Order::where('user_id', $user->id)
            ->with(['orderDetails' => function ($query) {
                $query->with('product'); // Load product for each order detail
            }])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Transform the response to include product data through ProductMiniCollection
        $orders->getCollection()->transform(function ($order) {
            $order->orderDetails->transform(function ($detail) {
                if ($detail->product) {
                    // Use ProductMiniCollection to format product data
                    $productResource = (new ProductMiniCollection(collect([$detail->product])))->toArray(request());
                    $detail->product_data = $productResource['data'][0] ?? null;
                }
                return $detail;
            });
            return $order;
        });

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function trackOrder(Request $request, $order_code)
    {
        $order = Order::where('code', $order_code)
            ->with(['orderDetails' => function ($query) {
                $query->with('product'); // Load product for each order detail
            }])
            ->first();

        if ($order) {
            // Transform order details to include product data through ProductMiniCollection
            $order->orderDetails->transform(function ($detail) {
                if ($detail->product) {
                    $productResource = (new ProductMiniCollection(collect([$detail->product])))->toArray(request());
                }
                return $productResource;
            });

            return response()->json([
                'success' => true,
                'data' => $order
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Order not found'
        ], 404);
    }

    public function show($id)
    {
        $order = Order::where('id', $id)
            ->where('user_id', auth()->id())
            ->with(['orderDetails' => function ($query) {
                $query->with('product'); // Load product for each order detail
            }])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Transform order details to include product data through ProductMiniCollection
        $order->orderDetails->transform(function ($detail) {
            if ($detail->product) {
                $productResource = (new ProductMiniCollection(collect([$detail->product])))->toArray(request());
                $detail->product_data = $productResource['data'][0] ?? null;
            }
            return $detail;
        });

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }


    /**
     * Store order for authenticated and guest users with FIFO
     *
     * One order is placed per cart at a time: a second request for the same
     * cart (a double tap, a slow connection retried) waits for the first to
     * finish, then finds the cart empty instead of ordering everything twice.
     */
    public function store(Request $request, $set_paid = false)
    {
        $lockName = $this->checkoutLockName($request);
        $locked = $lockName === null ? null : $this->acquireCheckoutLock($lockName);

        if ($locked === false) {
            return response()->json([
                'combined_order_id' => 0,
                'result' => false,
                'code' => 'order_in_progress',
                'message' => translate('Your order is already being placed. Please wait a moment.'),
            ], 409);
        }

        try {
            return $this->placeOrder($request, $set_paid);
        } finally {
            if ($locked) {
                $this->releaseCheckoutLock($lockName);
            }
        }
    }

    /** Named lock per cart owner: the signed-in user, or the guest's temporary id. */
    private function checkoutLockName(Request $request): ?string
    {
        $owner = auth()->check() ? 'u' . auth()->user()->id : ($request->temp_user_id ? 't' . $request->temp_user_id : null);

        return $owner === null ? null : 'misron:checkout:' . md5($owner);
    }

    /**
     * @return bool|null true: lock held; false: another order for this cart is
     *                   still being placed; null: the database could not give a
     *                   lock, so the order goes ahead without one (stock safety
     *                   does not depend on it, only duplicate-order protection).
     */
    private function acquireCheckoutLock(string $name): ?bool
    {
        try {
            $row = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$name]);
        } catch (\Throwable $e) {
            Log::warning('Checkout lock unavailable: ' . $e->getMessage());

            return null;
        }

        if ($row === null || $row->acquired === null) {
            return null;
        }

        return (int) $row->acquired === 1;
    }

    private function releaseCheckoutLock(string $name): void
    {
        try {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);
        } catch (\Throwable $e) {
            Log::warning('Checkout lock release failed: ' . $e->getMessage());
        }
    }

    /** Turns a refusal from the stock ledger into the answer the storefront already understands. */
    private function inventoryRefusal(InventoryException $e)
    {
        $product = isset($e->context()['product_id']) ? Product::find($e->context()['product_id']) : null;
        $name = $product ? $product->name : '';

        switch ($e->errorCode()) {
            case 'out_of_stock':
            case 'variant_unavailable':
                $status = 200;
                $message = translate('The requested quantity is not available for ') . $name;
                break;
            case 'inventory_unverified':
                $status = 409;
                $message = $name . ' ' . translate('is temporarily unavailable. Please contact us to order it.');
                break;
            case 'inventory_busy':
                $status = 503;
                $message = translate('Too many orders at once. Please try again.');
                break;
            default:
                $status = 422;
                $message = translate('Something went wrong');
        }

        return response()->json([
            'combined_order_id' => 0,
            'result' => false,
            'code' => $e->errorCode(),
            'message' => $message,
        ], $status);
    }

    protected function placeOrder(Request $request, $set_paid = false)
    {
        // dd($request->all());
        // First, let's log the complete request to see what's coming
        Log::info('Order store request:', $request->all());

        // Start database transaction
        DB::beginTransaction();

        try {
            // Determine if it's a guest or auth user
            $isGuest = !auth()->check();
            $userId = auth()->check() ? auth()->user()->id : null;
            $tempUserId = $request->temp_user_id;
            $sessionId = $request->session_id ?? session()->getId();

            // Capture customer IP for tracking
            $customerIp = $request->ip();

            // Get delivery type from request
            $deliveryType = $request->delivery_type ?? 'home_delivery'; // 'home_delivery' or 'pickup_point'
            $pickupPointId = $request->pickup_point_id ?? 0;

            // Gift box is one flat charge for the whole basket, not per product
            // and not per seller. A basket split across sellers still becomes
            // several Order rows, so the charge is applied to the first one only
            // — see $giftBoxApplied below.
            $giftBox = (bool) $request->boolean('gift_box');
            $giftBoxApplied = false;

            // Validate pickup point selection
            if ($deliveryType == 'pickup_point' && !$pickupPointId) {
                DB::rollBack();
                return response()->json([
                    'combined_order_id' => 0,
                    'result' => false,
                    'message' => translate('Please select a pickup point')
                ]);
            }

            // Get cart items based on user type
            if ($isGuest) {
                if (!$tempUserId) {
                    DB::rollBack();
                    return response()->json([
                        'combined_order_id' => 0,
                        'result' => false,
                        'message' => translate('Guest user ID is required')
                    ]);
                }
                $cartItems = Cart::where('temp_user_id', $tempUserId)->active()->get();
            } else {
                // Check minimum order amount for auth users
                if (get_setting('minimum_order_amount_check') == 1) {
                    $subtotal = 0;
                    foreach (Cart::where('user_id', $userId)->active()->get() as $key => $cartItem) {
                        $product = Product::find($cartItem['product_id']);
                        $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                    }
                    if ($subtotal < get_setting('minimum_order_amount')) {
                        DB::rollBack();
                        return $this->failed(translate("You order amount is less then the minimum order amount"));
                    }
                }
                $cartItems = Cart::where('user_id', $userId)->active()->get();
            }

            if ($cartItems->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'combined_order_id' => 0,
                    'result' => false,
                    'message' => translate('Cart is Empty')
                ]);
            }


            // Prepare base shipping address
            $shippingAddress = [
                'name' => '',
                'email' => '',
                'phone' => '',
                'address' => '',
                'city' => '',
                'country' => 'Bangladesh',
                'postal_code' => '',
                'order_note' => '',
                'delivery_type' => $deliveryType
            ];

            // add pickup point if exists
            if ($deliveryType == 'pickup_point') {
                $shippingAddress['pickup_point_id'] = $pickupPointId ?? 0;
            }

            // ================================
            // FILL SHIPPING ADDRESS
            // ================================

            if ($isGuest) {

                $guestInfo = $request->guest_info ?? [];

                $shippingAddress['name'] = $guestInfo['name'] ?? '';
                $shippingAddress['email'] = $guestInfo['email'] ?? '';
                $shippingAddress['phone'] = $guestInfo['phone'] ?? '';
                $shippingAddress['address'] = $guestInfo['address'] ?? '';
                $shippingAddress['city'] = $guestInfo['city_name'] ?? '';
                $shippingAddress['postal_code'] = $guestInfo['postal_code'] ?? '';
                $shippingAddress['order_note'] = $guestInfo['order_note'] ?? '';
            } else {

                $user = User::find($userId);

                $shippingAddress['name'] = $user->name ?? '';
                $shippingAddress['email'] = $user->email ?? '';

                $requestAddress = $request->shipping_address ?? [];

                if (!empty($requestAddress)) {

                    $shippingAddress['phone'] = $requestAddress['phone'] ?? $user->phone ?? '';
                    $shippingAddress['address'] = $requestAddress['address'] ?? '';
                    $shippingAddress['city'] = $requestAddress['city_name'] ?? ($requestAddress['city'] ?? '');
                    $shippingAddress['postal_code'] = $requestAddress['postal_code'] ?? '';
                    $shippingAddress['order_note'] = $requestAddress['order_note'] ?? '';
                } else {

                    // fallback to saved address
                    $address = Address::where('id', $cartItems->first()->address_id)->first();

                    if ($address) {
                        $shippingAddress['phone'] = $address->phone ?? $user->phone ?? '';
                        $shippingAddress['address'] = $address->address ?? '';
                        $shippingAddress['city'] = optional($address->city)->name ?? '';
                        $shippingAddress['postal_code'] = $address->postal_code ?? '';
                    }
                }
            }

            // Create or find user account from guest info
            if ($isGuest && !empty($guestInfo['phone'])) {
                $existingUser = User::where('phone', $guestInfo['phone'])->first();

                if (!$existingUser && !empty($guestInfo['email'])) {
                    $existingUser = User::where('email', $guestInfo['email'])->first();
                }

                if ($existingUser) {
                    $userId = $existingUser->id;
                    $existingUser->update(['last_ip' => $customerIp]);
                } else {
                    try {
                        $newUser = User::create([
                            'name' => $guestInfo['name'] ?? 'Guest User',
                            'email' => $guestInfo['email'] ?? null,
                            'phone' => $guestInfo['phone'],
                            'password' => bcrypt($guestInfo['phone']),
                            'last_ip' => $customerIp,
                        ]);
                        $userId = $newUser->id;
                    } catch (\Exception $e) {
                        // Duplicate entry — find existing user and continue with order
                        $existingUser = User::where('phone', $guestInfo['phone'])
                            ->orWhere('email', $guestInfo['email'] ?? '')
                            ->first();
                        if ($existingUser) {
                            $userId = $existingUser->id;
                            $existingUser->update(['last_ip' => $customerIp]);
                        } else {
                            throw $e;
                        }
                    }
                }
            } elseif (!$isGuest) {
                User::where('id', $userId)->update(['last_ip' => $customerIp]);
            }

            // ================================
            // CHECKOUT OTP VERIFICATION CHECK
            // ================================
            if (get_setting('checkout_otp_activation') == 1) {
                $phone = $isGuest
                    ? ($guestInfo['phone'] ?? '')
                    : ($shippingAddress['phone'] ?? '');

                if (empty($phone)) {
                    DB::rollBack();
                    return response()->json([
                        'combined_order_id' => 0,
                        'result' => false,
                        'message' => translate('Phone number is required for OTP verification')
                    ]);
                }

                $otpVerified = CheckoutOtp::where('phone', $phone)
                    ->where('is_verified', true)
                    ->where('expires_at', '>', now())
                    ->latest()
                    ->first();

                if (!$otpVerified) {
                    DB::rollBack();
                    return response()->json([
                        'combined_order_id' => 0,
                        'result' => false,
                        'message' => translate('OTP verification required. Please verify your phone number.')
                    ]);
                }
            }

            // Take the stock for the whole cart first, in one place shared with
            // funnel checkout. A line that cannot be served stops the order here,
            // with nothing half-taken; the checks and subtractions below are the
            // database's own, not a read made earlier.
            $stockLines = [];
            foreach ($cartItems as $cartItem) {
                $lineProduct = Product::find($cartItem['product_id']);
                $stockLines[] = [
                    'key' => $cartItem['id'],
                    'product_id' => $cartItem['product_id'],
                    'variant' => $cartItem['variation'],
                    'quantity' => $cartItem['quantity'],
                    'digital' => $lineProduct && $lineProduct->digital == 1,
                ];
            }
            $stockTake = app(StockLedger::class)->take($stockLines);

            // Create combined order
            $combined_order = new CombinedOrder;
            $combined_order->user_id = $userId;
            if ($isGuest) {
                $combined_order->temp_user_id = $tempUserId;
            }
            $combined_order->shipping_address = json_encode($shippingAddress);
            $combined_order->save();

            // Group cart items by seller
            $seller_products = array();
            foreach ($cartItems as $cartItem) {
                $product = Product::find($cartItem['product_id']);
                if (!isset($seller_products[$product->user_id])) {
                    $seller_products[$product->user_id] = [];
                }
                $seller_products[$product->user_id][] = $cartItem;
            }

            // Create orders for each seller
            foreach ($seller_products as $sellerId => $seller_product) {
                $order = new Order;
                $order->combined_order_id = $combined_order->id;
                $order->user_id = $userId;
                if ($isGuest) {
                    $order->guest_id = $tempUserId;
                }
                $order->shipping_address = json_encode($shippingAddress);
                $order->additional_info = $request->additional_info ?? null;
                $order->payment_type = $request->payment_type;

                // Set shipping type and pickup point based on delivery type
                if ($deliveryType == 'pickup_point') {
                    $order->shipping_type = 'pickup_point';
                    $order->pickup_point_id = $pickupPointId ?? 0;
                    $order->carrier_id = 0;
                } else {
                    $order->shipping_type = 'home_delivery';
                    $order->pickup_point_id = 0;
                    $order->carrier_id = 0;
                }

                $order->order_from = 'app';
                $order->delivery_viewed = '0';
                $order->payment_status_viewed = '0';
                $order->code = rand(10, 99);
                $order->date = strtotime('now');
                $order->payment_status = $set_paid ? 'paid' : 'unpaid';
                $order->customer_ip = $customerIp;

                $order->save();

                $subtotal = 0;
                $tax = 0;
                $shipping = 0;
                $coupon_discount = 0;

                // Order Details Storing with FIFO
                foreach ($seller_product as $key => $cartItem) {
                    Log::info('cart items are', [
                        'cartItem' => $cartItem,

                    ]);

                    $product = Product::find($cartItem['product_id']);

                    $product_variation = $cartItem['variation'];

                    // ========== FIFO Costing Integration ==========
                    // Stock and purchase batches were already taken above.
                    $takenLine = $stockTake->line($cartItem['id']);
                    $fifo = ['total_cost' => $takenLine['cost'], 'batches' => $takenLine['batches']];

                    $totalCost = $fifo['total_cost'];
                    $avgBuyingPrice = $totalCost / $cartItem['quantity'];
                    $sellingPrice = cart_product_price($cartItem, $product, false, false);
                    $profit = ($sellingPrice - $avgBuyingPrice) * $cartItem['quantity'];
                    // =============================================

                    $subtotal += $sellingPrice * $cartItem['quantity'];
                    $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
                    $coupon_discount += $cartItem['discount'];

                    $order_detail = new OrderDetail;
                    $order_detail->order_id = $order->id;
                    $order_detail->seller_id = $product->user_id;
                    $order_detail->product_id = $product->id;
                    $order_detail->variation = $product_variation;
                    // Which batches supplied the line, so a cancel returns exactly those.
                    $order_detail->fifo_batch_info = $stockTake->batchInfo($cartItem['id']);

                    // ========== FIFO values to order_detail ==========
                    $order_detail->selling_price = $sellingPrice;
                    $order_detail->price = $sellingPrice * $cartItem['quantity'];
                    $order_detail->buying_price = $avgBuyingPrice;
                    $order_detail->profit = $profit;

                    // Store batch info
                    if (count($fifo['batches'])) {
                        $firstBatch = $fifo['batches'][0];
                        $order_detail->purchase_id = $firstBatch['purchase_id'];
                        $order_detail->supplier_id = $firstBatch['supplier_id'];
                    }
                    // =================================================

                    $order_detail->tax = cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];

                    // Set shipping type for order detail (same as order)
                    if ($deliveryType == 'pickup_point') {
                        $order_detail->shipping_type = 'pickup_point';
                        $order_detail->pickup_point_id = $pickupPointId ?? 0;
                    } else {
                        $order_detail->shipping_type = 'home_delivery';
                    }

                    $order_detail->product_referral_code = $cartItem['product_referral_code'];
                    $order_detail->shipping_cost = $cartItem['shipping_cost'];

                    $shipping += $order_detail->shipping_cost;

                    if (addon_is_activated('club_point')) {
                        $order_detail->earn_point = $product->earn_point;
                    }

                    $order_detail->quantity = $cartItem['quantity'];
                    $order_detail->save();

                    // Product referral tracking (inside detail loop)
                    if (addon_is_activated('affiliate_system') && $order_detail->product_referral_code) {
                        $referred_by_user = User::where('referral_code', $order_detail->product_referral_code)->first();
                        if ($referred_by_user) {
                            $affiliateUser = AffiliateUser::where('user_id', $referred_by_user->id)->first();
                            if ($affiliateUser && $affiliateUser->status == 1) {
                                $apiAffiliate = new ApiAffiliateController();
                                $apiAffiliate->processAffiliateStats($affiliateUser->id, 0, $order_detail->quantity, 0, 0);
                            }
                        }
                    }

                    $product->num_of_sale = $product->num_of_sale + $cartItem['quantity'];
                    $product->save();

                    $order->seller_id = $product->user_id;

                    // Affiliate system
                    // if (addon_is_activated('affiliate_system') && $order_detail->product_referral_code) {
                    //     $referred_by_user = User::where('referral_code', $order_detail->product_referral_code)->first();
                    //     if ($referred_by_user) {
                    //         (new AffiliateController)->processAffiliateStats($referred_by_user->id, 0, $order_detail->quantity, 0, 0);
                    //     }
                    // }
                    // Affiliate system - track order items AND user registration first purchase


                }

                $order->grand_total = $subtotal + $tax + $shipping;

                // Apply the gift box charge to the first order only, so a basket
                // spanning several sellers is still charged 100 once in total.
                if ($giftBox && !$giftBoxApplied) {
                    $order->gift_box = 1;
                    $order->gift_box_charge = self::GIFT_BOX_CHARGE;
                    $order->grand_total += self::GIFT_BOX_CHARGE;
                    $giftBoxApplied = true;
                }

                if ($seller_product[0]->coupon_code != null) {
                    $order->coupon_discount = $coupon_discount;
                    $order->grand_total -= $coupon_discount;

                    if (!$isGuest) {
                        $coupon_usage = new CouponUsage;
                        $coupon_usage->user_id = $userId;
                        $coupon_usage->coupon_id = Coupon::where('code', $seller_product[0]->coupon_code)->first()->id;
                        $coupon_usage->save();
                    }
                }
                $order->due_amount = $order->grand_total;
                $combined_order->grand_total += $order->grand_total;
                $order->save();

                // Track IP in blocked_ips table
                $shippingName = $shippingAddress['name'] ?? '';
                $shippingPhone = $shippingAddress['phone'] ?? '';
                $shippingEmail = $shippingAddress['email'] ?? '';
                BlockedIp::updateOrCreate(
                    ['ip_address' => $customerIp],
                    [
                        'user_id' => $userId,
                        'customer_name' => $shippingName,
                        'customer_phone' => $shippingPhone,
                        'customer_email' => $shippingEmail,
                    ]
                );

                // First purchase tracking (AFTER order save, OUTSIDE detail loop)
                if (addon_is_activated('affiliate_system') && $userId) {
                    $orderUser = User::find($userId);
                    if ($orderUser && $orderUser->referred_by) {
                        $orderCount = Order::where('user_id', $userId)->count();
                        if ($orderCount == 1) {
                            $affiliateUser = AffiliateUser::where('user_id', $orderUser->referred_by)->first();
                            if ($affiliateUser && $affiliateUser->status == 1) {
                                $apiAffiliate = new ApiAffiliateController();
                                $apiAffiliate->processAffiliateStats($affiliateUser->id, 0, 1, 0, 0);
                            }
                        }
                    }
                }
            }

            $combined_order->save();

            // =============================================
            // DELETE DRAFT ORDER AFTER SUCCESSFUL PLACEMENT
            // =============================================
            $this->deleteDraftAfterOrder($sessionId, $userId, $tempUserId);

            // Clear cart based on user type
            if ($isGuest) {
                Cart::where('temp_user_id', $tempUserId)->active()->delete();
            } else {
                Cart::where('user_id', $userId)->active()->delete();
            }

            // Commit transaction
            DB::commit();

            return response()->json([
                'combined_order_id' => Crypt::encrypt($combined_order->id),
                'result' => true,
                'is_guest' => $isGuest,
                'message' => translate('Your order has been placed successfully')
            ]);
        } catch (InventoryException $e) {
            DB::rollBack();
            Log::info('Order refused by stock: ' . $e->getMessage(), $e->context());

            return $this->inventoryRefusal($e);
        } catch (\Throwable $e) {
            DB::rollBack();
            // Purchase batches that do not roll back (MyISAM) are given back by hand.
            if (isset($stockTake)) {
                app(StockLedger::class)->release($stockTake);
            }
            Log::error('Order failed: ' . $e->getMessage(), [
                'user_id' => $userId ?? null,
                'temp_user_id' => $tempUserId ?? null,
                'delivery_type' => $deliveryType ?? null,
                'pickup_point_id' => $pickupPointId ?? 0,
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            // A deadlock or lock timeout means other orders were busy with the same stock
            // rows. Tell the shopper to try again, without handing them the SQL text.
            if ($e instanceof \Illuminate\Database\QueryException && in_array($e->errorInfo[1] ?? null, [1213, 1205], true)) {
                return response()->json([
                    'combined_order_id' => 0,
                    'result' => false,
                    'code' => 'inventory_busy',
                    'message' => translate('Too many orders at once. Please try again.'),
                ], 503);
            }

            return response()->json([
                'combined_order_id' => 0,
                'result' => false,
                'message' => translate('Failed to place order: ') . $e->getMessage()
            ], 500);
        }
    }





    //  Delete draft order after successful order placement

    protected function deleteDraftAfterOrder($sessionId, $userId = null, $tempUserId = null)
    {
        try {
            // Only search by session_id since drafts table doesn't have user_id
            $draft = Draft::where('session_id', $sessionId)->first();

            // Delete the draft if found
            if ($draft) {
                $draft->delete();
                Log::info('Draft order deleted after successful order placement', [
                    'draft_id' => $draft->id,
                    'session_id' => $sessionId,
                ]);
            }
        } catch (Exception $e) {
            // Don't let draft deletion failure affect the order placement
            Log::warning('Failed to delete draft after order placement: ' . $e->getMessage(), [
                'session_id' => $sessionId,
            ]);
        }
    }



    public function order_cancel($id)
    {
        $ledger = app(StockLedger::class);

        $cancelled = DB::transaction(function () use ($id, $ledger) {
            // Lock the one order row so two cancels of the same order take turns.
            $order = Order::where('id', $id)->where('user_id', auth()->user()->id)->lockForUpdate()->first();
            if (!$order || !($order->delivery_status == 'pending' && $order->payment_status == 'unpaid')) {
                return false;
            }

            $order->delivery_status = 'cancelled';
            $order->save();

            // Each line is cancelled and restocked in one step, exactly once.
            foreach ($order->orderDetails as $orderDetail) {
                $ledger->give($orderDetail);
            }

            return true;
        });

        return $cancelled
            ? $this->success(translate('Order has been canceled successfully'))
            : $this->failed(translate('Something went wrong'));
    }




    public function getOrderDetails($id, Request $request)
    {
        try {
            $isGuest = $request->query('guest', false);
            $tempUserId = $request->query('temp_user_id');
            $id = Crypt::decrypt($id);
            // Find the combined order
            $combinedOrder = CombinedOrder::find($id);

            if (!$combinedOrder) {
                return response()->json([
                    'result' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Find the associated order
            $orderQuery = Order::where('combined_order_id', $id)
                ->with(['orderDetails.product']);

            if ($isGuest) {
                // For guest users
                if (!$tempUserId) {
                    return response()->json([
                        'result' => false,
                        'message' => 'Guest user ID is required'
                    ], 400);
                }
                $orderQuery->where('guest_id', $tempUserId);
            } else {
                // For authenticated users
                if (!auth()->check()) {
                    return response()->json([
                        'result' => false,
                        'message' => 'User not authenticated'
                    ], 401);
                }
                $orderQuery->where('user_id', auth()->id());
            }

            $order = $orderQuery->first();

            if (!$order) {
                return response()->json([
                    'result' => false,
                    'message' => 'Order not found for this user'
                ], 404);
            }

            // Calculate total shipping cost from order details
            $totalShippingCost = $order->orderDetails->sum('shipping_cost');

            // Parse shipping address
            $shippingAddress = json_decode($combinedOrder->shipping_address, true);

            return response()->json([
                'result' => true,
                'order' => [
                    'id' => $order->id,
                    'combined_order_id' => $order->combined_order_id,
                    'code' => $order->code,
                    'date' => $order->date,
                    'grand_total' => (float) $order->grand_total,
                    'shipping_cost' => (float) $totalShippingCost,
                    'tax' => (float) $order->tax,
                    'coupon_discount' => (float) $order->coupon_discount,
                    'gift_box' => (bool) $order->gift_box,
                    'gift_box_charge' => (float) $order->gift_box_charge,
                    'payment_type' => $order->payment_type,
                    'delivery_status' => $order->delivery_status,
                    'shipping_type' => $order->shipping_type,
                    'shipping_address' => $shippingAddress,
                    'order_details' => $order->orderDetails->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'product_id' => $detail->product_id,
                            'product' => $detail->product ? [
                                'name' => $detail->product->name,
                                'thumbnail_image' => $detail->product->thumbnail_img ?
                                    uploaded_asset($detail->product->thumbnail_img) : null
                            ] : null,
                            'variation' => $detail->variation,
                            // Id of the stock row that was bought, matching the
                            // id the Meta catalogue feed publishes. The SKU is
                            // null on most rows and not unique where set, so it
                            // cannot identify a catalogue item on its own.
                            'stock_id' => optional(
                                $detail->product
                                    ? $detail->product->stocks->where('variant', $detail->variation)->first()
                                    : null
                            )->id,
                            'price' => (float) $detail->price,
                            'quantity' => (int) $detail->quantity,
                            'shipping_cost' => (float) $detail->shipping_cost,
                            'shipping_type' => $detail->shipping_type,
                        ];
                    })
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Order details error: ' . $e->getMessage());
            return response()->json([
                'result' => false,
                'message' => 'Error fetching order details'
            ], 500);
        }
    }
}
