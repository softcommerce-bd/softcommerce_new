<?php

namespace App\Utility;

use App\Models\ProductStock;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Session;
use Mail;
use Log;
use App\Models\PurchaseProduct;
use App\Mail\InvoiceEmailManager;
use App\Models\AccountStatementLog;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Inventory\Exceptions\InventoryException;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

class PosUtility
{

/**
 * Insert statement log for order transactions
 */
public static function insertOrderStatementLog(
    $accountId,
    $date,
    $referenceNo,
    $type,
    $debit,
    $credit,
    $desc,
    $order
) {
    try {
        $account = ChartOfAccount::find($accountId);
        $balance = $account ? $account->current_balance : 0;

        return AccountStatementLog::create([
            'account_id'       => $accountId,
            'transaction_date' => $date,
            'reference_no'     => $referenceNo,
            'transaction_type' => $type,
            'debit'            => $debit,
            'credit'           => $credit,
            'balance'          => $balance,
            'description'      => $desc,
            'source_id'        => $order->id,
            'source_type'      => get_class($order),
            'voucher_id'       => null,
            'order_id'         => $order->id,
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to insert order statement log: ' . $e->getMessage());
        throw $e;
    }
}
    public static function product_search($request_data): object
    {
        $product_query = ProductStock::query()->join('products', 'product_stocks.product_id', '=', 'products.id');

        if (auth()->user()->user_type == 'seller') {
            $product_query->where('products.user_id', auth()->user()->id);
        } else {
            $product_query->where('products.added_by', 'admin');
        }
        // $products = $product_query->where('products.auction_product', 0)
        //     ->where('products.wholesale_product', 0)
        //     ->where('products.published', 1)
        //     ->where('products.approved', 1)
        //     ->select('products.*', 'product_stocks.id as stock_id', 'product_stocks.variant', 'product_stocks.price as stock_price', 'product_stocks.qty as stock_qty', 'product_stocks.image as stock_image')
        //     ->orderBy('products.created_at', 'desc');
        $products = $product_query->where('products.auction_product', 0)
            ->where('products.wholesale_product', 0)
            ->where('products.approved', 1)
            ->select('products.*', 'product_stocks.id as stock_id', 'product_stocks.variant', 'product_stocks.price as stock_price', 'product_stocks.qty as stock_qty', 'product_stocks.image as stock_image')
            ->orderBy('products.created_at', 'desc');


        if ($request_data['category'] != null) {
            $arr = explode('-', $request_data['category']);
            if ($arr[0] == 'category') {
                $category_ids = CategoryUtility::children_ids($arr[1]);
                $category_ids[] = $arr[1];
                $products = $products->whereIn('products.category_id', $category_ids);
            }
        }

        if ($request_data['brand'] != null) {
            $products = $products->where('products.brand_id', $request_data['brand']);
        }

        // if ($request_data['keyword'] != null) {
        //     $products = $products->where('products.name', 'like', '%' . $request_data['keyword'] . '%')->orWhere('products.barcode', $request_data['keyword']);
        // }
           if (!empty($request_data['keyword'])) {
                $keyword = trim($request_data['keyword']);
                
                if (is_numeric($keyword)) {
                    // Search by product ID, name, or barcode
                    $products = $products->where(function($query) use ($keyword) {
                        $query->where('products.id', $keyword)
                              ->orWhere('products.name', 'like', '%' . $keyword . '%')
                              ->orWhere('products.barcode', $keyword);
                    });
                } else {
                    // Search by name or barcode
                    $products = $products->where(function($query) use ($keyword) {
                        $query->where('products.name', 'like', '%' . $keyword . '%')
                              ->orWhere('products.barcode', $keyword);
                    });
                }
            }

        return $products->paginate(16);
    }

    public static function get_shipping_address($request): array
    {
        if ($request->address_id != null) {
            $address = Address::findOrFail($request->address_id);
            $data['name'] = $address->user->name ?? '';
            $data['email'] = $address->user->email ?? '';
            $data['address'] = $address->address ?? '';
            $data['country'] = $address->country->name ?? '';
            $data['state'] = $address->state->name ?? '';
            $data['city'] = $address->city->name ?? '';
            $data['postal_code'] = $address->postal_code ?? '';
            $data['phone'] = $address->phone ?? '';
        } else {
            $data['name'] = $request->name ?? '';
            $data['email'] = $request->email ?? '';
            $data['address'] = $request->address ?? '';
            $data['country'] = Country::find($request->country_id)->name ?? '';
            $data['state'] = State::find($request->state_id)->name ?? '';
            $data['city'] = City::find($request->city_id)->name ?? '';
            $data['postal_code'] = $request->postal_code ?? '';
            $data['phone'] = $request->phone ?? '';
        }

        return $data;
    }

    public static function addToCart($stockId, $userID, $temUserId)
    {
        $productStock   = ProductStock::find($stockId);
        $product        = $productStock->product;
        $quantity       = $product->min_qty;

        if ($productStock->qty < $product->min_qty && $product->digital == 0) {
            return array(
                'success' => 0,
                'message' => translate("This product doesn't have enough stock for minimum purchase quantity ") . $product->min_qty
            );
        }

        $cart = Cart::firstOrNew([
            'variation' => $productStock->variant,
            'user_id' => $userID,
            'temp_user_id' => $temUserId,
            'product_id' => $product->id
        ]);

        if ($cart->exists) {
            if ($product->digital == 1) {
                return array(
                    'success' => 0,
                    'message' => translate("This product is alreday in the cart")
                );
            } else {
                $quantity = $cart->quantity + 1;
                if ($productStock->qty < $quantity) {
                    return array(
                        'success' => 0,
                        'message' => translate("This product doesn't have more stock.")
                    );
                }
            }
        }

        $price = CartUtility::get_price($product, $productStock, $quantity);
        $tax = CartUtility::tax_calculation($product, $price);
        CartUtility::save_cart_data($cart, $product, $price, $tax, $quantity);
        return array('success' => 1, 'message' => 'Added to cart successfully');
    }

    public static function updateCartItemQuantity($cart, $data)
    {
        $product = Product::find($cart->product_id);
        $product_stock = $product->stocks->where('variant', $cart->variation)->first();

        if ($product_stock->qty < $data['quantity']) {
            $response['success'] = 0;
            $response['message'] = translate("This product doesn't have more stock.");
        } else {
            $cart->quantity = $data['quantity'];
            $cart->save();
            $response['success'] = 1;
            $response['message'] = translate("Updated the item successfully.");
        }

        return $response;
    }

    public static function updateCartOnUserChange($data)
    {
        $userID             = $data['userId'];
        $sessionUserId      = Session::has('pos.user_id') ? Session::get('pos.user_id') : null;
        $sessionTemUserId   = Session::has('pos.temp_user_id') ? Session::get('pos.temp_user_id') : null;
        $carts              = get_pos_user_cart();

        // If user is selected but user not in session or Session user is not this user, set it to session
        if ($userID) {
            if ($carts) {
                self::updatePosUserCartData($carts, $userID, null);
            }

            if (!$sessionUserId || ($sessionUserId != $userID)) {
                Session::put('pos.user_id', $userID);
            }
            Session::forget('pos.temp_user_id');
        }

        // If user is not selected, and if session has not Temp user ID, get it or set it
        if (!$userID) {
            if (!$sessionTemUserId) {
                $sessionTemUserId = bin2hex(random_bytes(10));
                Session::put('pos.temp_user_id', $sessionTemUserId);
            }
            if ($carts) {
                self::updatePosUserCartData($carts, null, $sessionTemUserId);
            }
            Session::forget('pos.user_id');
        }
    }

    public static function updatePosUserCartData($carts, $userID, $tempUsderID)
    {
        foreach ($carts as $cartItem) {
            $userCartItem = Cart::where('user_id', $userID)->where('temp_user_id', $tempUsderID)->where('product_id', $cartItem->product_id)->where('variation', $cartItem->variation)->first();
            if ($userCartItem) {
                $quantity = $userCartItem->quantity + $cartItem->quantity;
                $product_qty = $cartItem->product->stocks()->where('variant', $cartItem->$cartItem)->first();
                $quantity = $product_qty > $quantity ? $product_qty : $quantity;

                $userCartItem->update(['quantity' => $quantity]);
                $cartItem->delete();
            } else {
                $cartItem->update(['user_id' => $userID, 'temp_user_id' => $tempUsderID]);
            }
        }
    }



    // public static function orderStore($data)
    // {
    //     // dd($data);

    //     \Log::info('OrderStore called with data:', $data);
    //     \Log::info('Session shipping info:', Session::get('pos.shipping_info', []));

    //     try {
    //         // Your existing code
    //     } catch (\Exception $e) {
    //         \Log::error('OrderStore error: ' . $e->getMessage());
    //         \Log::error('Stack trace: ' . $e->getTraceAsString());
    //         return array('success' => 0, 'message' => 'Server error occurred.');
    //     }


    //     // Ensure shippingInfo is always set with default values if not provided
    //     $shippingInfo = $data['shippingInfo'] ?? [];
    //     $shippingInfo = [
    //         'name' => $shippingInfo['name'] ?? 'pos guest',
    //         'phone' => $shippingInfo['phone'] ?? '01*********',
    //         'address' => $shippingInfo['address'] ?? 'guest-address',
    //         'email' => $shippingInfo['email'] ?? ($data['email'] ?? 'guest@gmail.com'),
    //         'state' => $shippingInfo['state'] ?? 'pos',
    //         'city' => $shippingInfo['city'] ?? 'pos'
    //     ];

    //     // Validate shippingInfo fields
    //     if (empty($shippingInfo['name']) || empty($shippingInfo['phone']) || empty($shippingInfo['address'])) {
    //         return array('success' => 0, 'message' => translate("Please Add Shipping Information."));
    //     }

    //     // Continue with existing logic
    //     $carts = get_pos_user_cart($data['user_id'], $data['temp_usder_id']);
    //     if (count($carts) > 0) {
    //         $order = new Order();
    //         $userId = $data['user_id'];
    //         if ($userId == null) {
    //             $order->guest_id = $carts[0]->temp_user_id;
    //         } else {
    //             $order->user_id = $userId;
    //         }
    //         $order->shipping_address = json_encode($shippingInfo);

    //         $order->payment_type = $data['payment_type'];
    //         $order->delivery_viewed = '0';
    //         $order->payment_status_viewed = '0';
    //         $order->code = Order::generateUniqueCode();
    //         $order->date = strtotime('now');

    //         $order->payment_status = $data['payment_type'] == 'cash_on_delivery'
    //             ? 'unpaid'
    //             : ($data['payment_type'] == 'partial' ? 'partial' : 'paid');
    //         $order->payment_details = $data['payment_type'];
    //         $order->order_from = 'pos';
    //         $order->order_note = $data['order_note'] ?? '';

    //         if ($data['payment_type'] == 'offline_payment') {
    //             if ($data['offline_trx_id'] == null) {
    //                 return array('success' => 0, 'message' => translate("Transaction ID cannot be null."));
    //             }
    //             $data['name'] = $data['offline_payment_method'];
    //             $data['amount'] = $data['offline_payment_amount'];
    //             $data['trx_id'] = $data['offline_trx_id'];
    //             $data['photo'] = $data['offline_payment_proof'];
    //             $order->manual_payment_data = json_encode($data);
    //             $order->manual_payment = 1;
    //         }

    //         if ($order->save()) {
    //             $subtotal = 0;
    //             $tax = 0;
    //             foreach ($carts as $key => $cartItem) {
    //                 $product_stock = $cartItem->product->stocks->where('variant', $cartItem['variation'])->first();
    //                 $product = $product_stock->product;
    //                 $product_variation = $product_stock->variant;

    //                 $subtotal += $cartItem['price'] * $cartItem['quantity'];
    //                 $tax += $cartItem['tax'] * $cartItem['quantity'];

    //                 if ($product->digital == 0) {
    //                     if ($cartItem['quantity'] > $product_stock->qty) {
    //                         $order->delete();
    //                         return array('success' => 0, 'message' => $product->name . ' (' . $product_variation . ') ' . translate(" just stock outs."));
    //                     } else {
    //                         $product_stock->qty -= $cartItem['quantity'];
    //                         $product_stock->save();
    //                     }
    //                 }

    //                 //  FIFO Costing
    //                 $fifo = self::consumePurchaseProductQtyFIFO($product->id, $product_variation, $cartItem['quantity']);

    //                 $totalCost = $fifo['total_cost'];
    //                 $avgBuyingPrice = $totalCost / $cartItem['quantity'];
    //                 $sellingPrice = cart_product_price($cartItem, $product, false, false);
    //                 $profit = ($sellingPrice - $avgBuyingPrice) * $cartItem['quantity'];

    //                 $order_detail = new OrderDetail;
    //                 $order_detail->order_id = $order->id;
    //                 $order_detail->seller_id = $product->user_id;
    //                 $order_detail->product_id = $product->id;
    //                 $order_detail->selling_price = $sellingPrice;
    //                 $order_detail->payment_status = $data['payment_type'] == 'cash_on_delivery'
    //                     ? 'unpaid'
    //                     : ($data['payment_type'] == 'partial' ? 'partial' : 'paid');
    //                 $order_detail->variation = $product_variation;
    //                 $order_detail->price = $cartItem['price'] * $cartItem['quantity'];
    //                 $order_detail->tax = $cartItem['tax'] * $cartItem['quantity'];
    //                 $order_detail->quantity = $cartItem['quantity'];
    //                 $order_detail->shipping_type = null;

    //                 //  FIFO values
    //                 $order_detail->buying_price = $avgBuyingPrice;
    //                 // $order_detail->cost_price = $totalCost;
    //                 $order_detail->profit = $profit;

    //                 if (count($fifo['batches'])) {
    //                     $firstBatch = $fifo['batches'][0];
    //                     $order_detail->purchase_id = $firstBatch['purchase_id'];
    //                     $order_detail->supplier_id = $firstBatch['supplier_id'];
    //                 }

    //                 if ($data['shippingCost'] >= 0) {
    //                     // $order_detail->shipping_cost = $data['shippingCost'] / count($carts);
    //                     $order_detail->shipping_cost = $data['shippingCost'];
    //                 } else {
    //                     $order_detail->shipping_cost = 0;
    //                 }

    //                 $order_detail->save();

    //                 $product->num_of_sale++;
    //                 $product->save();
    //             }

    //             $order->grand_total = $subtotal + $tax + $data['shippingCost'];


    //             if ($data['discount']) {
    //                 $order->grand_total -= $data['discount'];
    //                 $order->coupon_discount = $data['discount'];
    //             }

    //             if ($data['payment_type'] == 'partial') {
    //                 $order->partial_paid_amount = $data['partial_amount'] ?? 0;
    //                 $order->due_amount = $order->grand_total - ($data['partial_amount'] ?? 0);
    //             }

    //             $order->seller_id = $product->user_id;
    //             $order->save();

    //             $array['view'] = 'emails.invoice';
    //             $array['subject'] = 'Your order has been placed - ' . $order->code;
    //             $array['from'] = env('MAIL_USERNAME');
    //             $array['order'] = $order;

    //             $admin_products = array();
    //             $seller_products = array();

    //             foreach ($order->orderDetails as $key => $orderDetail) {
    //                 if ($orderDetail->product->added_by == 'admin') {
    //                     array_push($admin_products, $orderDetail->product->id);
    //                 } else {
    //                     $product_ids = array();
    //                     if (array_key_exists($orderDetail->product->user_id, $seller_products)) {
    //                         $product_ids = $seller_products[$orderDetail->product->user_id];
    //                     }
    //                     array_push($product_ids, $orderDetail->product->id);
    //                     $seller_products[$orderDetail->product->user_id] = $product_ids;
    //                 }
    //             }

    //             foreach ($seller_products as $key => $seller_product) {
    //                 try {
    //                     Mail::to(User::find($key)->email)->queue(new InvoiceEmailManager($array));
    //                 } catch (\Exception $e) {
    //                 }
    //             }

    //             // Sends email to customer with the invoice pdf attached
    //             if (env('MAIL_USERNAME') != null) {
    //                 try {
    //                     Mail::to($shippingInfo['email'])->queue(new InvoiceEmailManager($array));
    //                     Mail::to(User::where('user_type', 'admin')->first()->email)->queue(new InvoiceEmailManager($array));
    //                 } catch (\Exception $e) {
    //                 }
    //             }

    //             if ($userId != NULL && $order->payment_status == 'paid') {
    //                 calculateCommissionAffilationClubPoint($order);
    //             }

    //             Cart::where('user_id', $order->user_id)->orWhere('temp_user_id', $order->guest_id)->delete();

    //             return array('success' => 1, 'message' => translate('Order Completed Successfully.'));
    //         } else {
    //             return array('success' => 0, 'message' => translate('Please input customer information.'));
    //         }
    //     }
    //     return array('success' => 0, 'message' => translate("Please select a product."));
    // }

    // public static function orderStore($data)
    // {
    //     \Log::info('OrderStore called with data:', $data);

    //     try {
    //         // Ensure shippingInfo is always set with default values if not provided
    //         $shippingInfo = $data['shippingInfo'] ?? [];
    //         $shippingInfo = [
    //             'name' => $shippingInfo['name'] ?? 'pos guest',
    //             'phone' => $shippingInfo['phone'] ?? '01*********',
    //             'address' => $shippingInfo['address'] ?? 'guest-address',
    //             'email' => $shippingInfo['email'] ?? ($data['email'] ?? 'guest@gmail.com'),
    //             'state' => $shippingInfo['state'] ?? 'pos',
    //             'city' => $shippingInfo['city'] ?? 'pos'
    //         ];

    //         // Validate shippingInfo fields
    //         if (empty($shippingInfo['name']) || empty($shippingInfo['phone']) || empty($shippingInfo['address'])) {
    //             return array('success' => 0, 'message' => translate("Please Add Shipping Information."));
    //         }

    //         $carts = get_pos_user_cart($data['user_id'] ?? null, $data['temp_usder_id'] ?? null);

    //         if (count($carts) > 0) {
    //             // Calculate payments from the request data
    //             $cashAmount = floatval($data['cash_amount'] ?? 0);
    //             $bankAmount = floatval($data['bank_amount'] ?? 0);
    //             $mobileAmount = floatval($data['mobile_amount'] ?? 0);
    //             $totalPayment = floatval($data['total_payment'] ?? ($cashAmount + $bankAmount + $mobileAmount));
    //             $grandTotal = floatval($data['grand_total'] ?? 0);

    //             \Log::info("Payment calculation - Cash: {$cashAmount}, Bank: {$bankAmount}, Mobile: {$mobileAmount}, Total Payment: {$totalPayment}, Grand Total: {$grandTotal}");

    //             // Prepare payment methods and amounts arrays
    //             $paymentMethods = [];
    //             $paymentAmounts = [];

    //             if ($cashAmount > 0 && !empty($data['cash_account_id'])) {
    //                 $paymentMethods[] = $data['cash_account_id'];
    //                 $paymentAmounts[] = $cashAmount;
    //             }
    //             if ($bankAmount > 0 && !empty($data['bank_account_id'])) {
    //                 $paymentMethods[] = $data['bank_account_id'];
    //                 $paymentAmounts[] = $bankAmount;
    //             }
    //             if ($mobileAmount > 0 && !empty($data['mobile_bank_account_id'])) {
    //                 $paymentMethods[] = $data['mobile_bank_account_id'];
    //                 $paymentAmounts[] = $mobileAmount;
    //             }

    //             $order = new Order();
    //             $userId = $data['user_id'] ?? null;

    //             if ($userId == null) {
    //                 $order->guest_id = $carts[0]->temp_user_id;
    //             } else {
    //                 $order->user_id = $userId;
    //             }

    //             $order->shipping_address = json_encode($shippingInfo);
    //             $order->payment_type = $data['payment_type'] ?? 'multiple';
    //             $order->delivery_viewed = '0';
    //             $order->payment_status_viewed = '0';
    //             $order->code = Order::generateUniqueCode();
    //             $order->date = strtotime('now');

    //             // Store payment methods and amounts as JSON
    //             $order->payment_methods = json_encode($paymentMethods);
    //             $order->payment_amounts = json_encode($paymentAmounts);

    //             $order->order_from = 'pos';
    //             $order->order_note = $data['order_note'] ?? '';

    //             if (($data['payment_type'] ?? '') == 'offline_payment') {
    //                 if (($data['offline_trx_id'] ?? null) == null) {
    //                     return array('success' => 0, 'message' => translate("Transaction ID cannot be null."));
    //                 }
    //                 $order->manual_payment_data = json_encode($data);
    //                 $order->manual_payment = 1;
    //             }

    //             if ($order->save()) {
    //                 $subtotal = 0;
    //                 $tax = 0;

    //                 foreach ($carts as $key => $cartItem) {
    //                     $product_stock = $cartItem->product->stocks->where('variant', $cartItem['variation'])->first();
    //                     $product = $product_stock->product;
    //                     $product_variation = $product_stock->variant;

    //                     $subtotal += $cartItem['price'] * $cartItem['quantity'];
    //                     $tax += $cartItem['tax'] * $cartItem['quantity'];

    //                     if ($product->digital == 0) {
    //                         if ($cartItem['quantity'] > $product_stock->qty) {
    //                             $order->delete();
    //                             return array('success' => 0, 'message' => $product->name . ' (' . $product_variation . ') ' . translate(" just stock outs."));
    //                         } else {
    //                             $product_stock->qty -= $cartItem['quantity'];
    //                             $product_stock->save();
    //                         }
    //                     }

    //                     // FIFO Costing
    //                     $fifo = self::consumePurchaseProductQtyFIFO($product->id, $product_variation, $cartItem['quantity']);

    //                     $totalCost = $fifo['total_cost'];
    //                     $avgBuyingPrice = $totalCost / $cartItem['quantity'];
    //                     $sellingPrice = cart_product_price($cartItem, $product, false, false);
    //                     $profit = ($sellingPrice - $avgBuyingPrice) * $cartItem['quantity'];

    //                     $order_detail = new OrderDetail;
    //                     $order_detail->order_id = $order->id;
    //                     $order_detail->seller_id = $product->user_id;
    //                     $order_detail->product_id = $product->id;
    //                     $order_detail->selling_price = $sellingPrice;
    //                     $order_detail->variation = $product_variation;
    //                     $order_detail->price = $cartItem['price'] * $cartItem['quantity'];
    //                     $order_detail->tax = $cartItem['tax'] * $cartItem['quantity'];
    //                     $order_detail->quantity = $cartItem['quantity'];
    //                     $order_detail->shipping_type = null;

    //                     // FIFO values
    //                     $order_detail->buying_price = $avgBuyingPrice;
    //                     $order_detail->profit = $profit;

    //                     if (count($fifo['batches'])) {
    //                         $firstBatch = $fifo['batches'][0];
    //                         $order_detail->purchase_id = $firstBatch['purchase_id'];
    //                         $order_detail->supplier_id = $firstBatch['supplier_id'];
    //                     }

    //                     $shippingCost = floatval($data['shippingCost'] ?? 0);
    //                     if ($shippingCost >= 0) {
    //                         $order_detail->shipping_cost = $shippingCost;
    //                     } else {
    //                         $order_detail->shipping_cost = 0;
    //                     }

    //                     $order_detail->save();

    //                     $product->num_of_sale++;
    //                     $product->save();
    //                 }

    //                 // Calculate grand total
    //                 $calculatedGrandTotal = $subtotal + $tax + floatval($data['shippingCost'] ?? 0);
    //                 $discount = floatval($data['discount'] ?? 0);

    //                 if ($discount > 0) {
    //                     $calculatedGrandTotal -= $discount;
    //                     $order->coupon_discount = $discount;
    //                 }

    //                 $order->grand_total = $calculatedGrandTotal;

    //                 // Calculate total due
    //                 $totalDue = $calculatedGrandTotal - $totalPayment;

    //                 \Log::info("Order totals - Subtotal: {$subtotal}, Tax: {$tax}, Shipping: " . ($data['shippingCost'] ?? 0) . ", Discount: {$discount}, Grand Total: {$calculatedGrandTotal}, Total Payment: {$totalPayment}, Total Due: {$totalDue}");

    //                 // Determine delivery_status and payment_status based on payment
    //                 if ($totalDue == 0) {
    //                     // Fully paid
    //                     $order->delivery_status = 'confirmed';
    //                     $order->payment_status = 'paid';
    //                     $order->partial_paid_amount = 0;
    //                     $order->due_amount = 0;

    //                     // Update chart_of_accounts current_balance for each payment method
    //                     foreach ($paymentMethods as $index => $accountId) {
    //                         $account = ChartOfAccount::find($accountId);
    //                         if ($account) {
    //                             $oldBalance = $account->current_balance;
    //                             $account->current_balance += $paymentAmounts[$index];
    //                             $account->save();

    //                             \Log::info("Account {$account->name} (ID: {$accountId}) balance updated from {$oldBalance} to {$account->current_balance} (+{$paymentAmounts[$index]})");
    //                         } else {
    //                             \Log::warning("Account not found with ID: {$accountId}");
    //                         }
    //                     }
    //                 } elseif ($totalPayment == 0) {
    //                     // No payment made - Cash on Delivery
    //                     $order->delivery_status = 'pending';
    //                     $order->payment_type = 'cash_on_delivery';
    //                     $order->payment_status = 'unpaid';
    //                     $order->partial_paid_amount = 0;
    //                     $order->due_amount = $calculatedGrandTotal;
    //                 } else {
    //                     // Partial payment
    //                     $order->delivery_status = 'confirmed';
    //                     $order->payment_status = 'partial';
    //                     $order->partial_paid_amount = $totalPayment;
    //                     $order->due_amount = $totalDue;

    //                     // Update chart_of_accounts current_balance for each payment method
    //                     foreach ($paymentMethods as $index => $accountId) {
    //                         $account = ChartOfAccount::find($accountId);
    //                         if ($account) {
    //                             $oldBalance = $account->current_balance;
    //                             $account->current_balance += $paymentAmounts[$index];
    //                             $account->save();

    //                             \Log::info("Account {$account->name} (ID: {$accountId}) balance updated from {$oldBalance} to {$account->current_balance} (+{$paymentAmounts[$index]})");
    //                         } else {
    //                             \Log::warning("Account not found with ID: {$accountId}");
    //                         }
    //                     }
    //                 }

    //                 $order->seller_id = $product->user_id ?? 1;
    //                 $order->save();

    //                 // Clear cart
    //                 Cart::where('user_id', $order->user_id)->orWhere('temp_user_id', $order->guest_id)->delete();

    //                 return array('success' => 1, 'message' => translate('Order Completed Successfully.'));
    //             } else {
    //                 return array('success' => 0, 'message' => translate('Failed to save order.'));
    //             }
    //         }

    //         return array('success' => 0, 'message' => translate("Please select a product."));
    //     } catch (\Exception $e) {
    //         \Log::error('OrderStore error: ' . $e->getMessage());
    //         \Log::error('Stack trace: ' . $e->getTraceAsString());
    //         return array('success' => 0, 'message' => 'Server error occurred: ' . $e->getMessage());
    //     }
    // }

public static function orderStore($data)
{
    Log::info('OrderStore called with data:', $data);

    // Stock and cost batches are taken through the shared stock service, inside one transaction:
    // a POS sale that fails part-way leaves no line deducted, and two sales for the last unit
    // cannot both succeed. $stockTake lets a failure give batch units back on a MyISAM schema.
    $stockTake = null;

    try {
        // Ensure shippingInfo is always set with default values if not provided
        $shippingInfo = $data['shippingInfo'] ?? [];
        $shippingInfo = [
            'name' => $shippingInfo['name'] ?? 'pos guest',
            'phone' => $shippingInfo['phone'] ?? '01*********',
            'address' => $shippingInfo['address'] ?? 'guest-address',
            'email' => $shippingInfo['email'] ?? ($data['email'] ?? 'guest@gmail.com'),
            'state' => $shippingInfo['state'] ?? 'pos',
            'city' => $shippingInfo['city'] ?? 'pos'
        ];

        // Validate shippingInfo fields
        if (empty($shippingInfo['name']) || empty($shippingInfo['phone']) || empty($shippingInfo['address'])) {
            return array('success' => 0, 'message' => translate("Please Add Shipping Information."));
        }

        $carts = get_pos_user_cart($data['user_id'] ?? null, $data['temp_usder_id'] ?? null);

        if (count($carts) > 0) {
            // Calculate payments from the request data
            $cashAmount = floatval($data['cash_amount'] ?? 0);
            $bankAmount = floatval($data['bank_amount'] ?? 0);
            $mobileAmount = floatval($data['mobile_amount'] ?? 0);
            $totalPayment = floatval($data['total_payment'] ?? ($cashAmount + $bankAmount + $mobileAmount));
            $grandTotal = floatval($data['grand_total'] ?? 0);

            Log::info("Payment calculation - Cash: {$cashAmount}, Bank: {$bankAmount}, Mobile: {$mobileAmount}, Total Payment: {$totalPayment}, Grand Total: {$grandTotal}");

            // Prepare payment methods and amounts arrays
            $paymentMethods = [];
            $paymentAmounts = [];
            $paymentMethodTypes = []; // Track payment method types for statement logs

            if ($cashAmount > 0 && !empty($data['cash_account_id'])) {
                $paymentMethods[] = $data['cash_account_id'];
                $paymentAmounts[] = $cashAmount;
                $paymentMethodTypes[] = 'cash';
            }
            if ($bankAmount > 0 && !empty($data['bank_account_id'])) {
                $paymentMethods[] = $data['bank_account_id'];
                $paymentAmounts[] = $bankAmount;
                $paymentMethodTypes[] = 'bank';
            }
            if ($mobileAmount > 0 && !empty($data['mobile_bank_account_id'])) {
                $paymentMethods[] = $data['mobile_bank_account_id'];
                $paymentAmounts[] = $mobileAmount;
                $paymentMethodTypes[] = 'mobile_bank';
            }

            $order = new Order();
            $userId = $data['user_id'] ?? null;

            if ($userId == null) {
                $order->guest_id = $carts[0]->temp_user_id;
            } else {
                $order->user_id = $userId;
            }

            $order->shipping_address = json_encode($shippingInfo);
            $order->payment_type = $data['payment_type'] ?? 'multiple';
            $order->delivery_viewed = '0';
            $order->payment_status_viewed = '0';
            $order->code = Order::generateUniqueCode();
            $order->date = strtotime('now');

            // Store payment methods and amounts as JSON
            $order->payment_methods = json_encode($paymentMethods);
            $order->payment_amounts = json_encode($paymentAmounts);

            $order->order_from = 'pos';
            $order->order_note = $data['order_note'] ?? '';

            if (($data['payment_type'] ?? '') == 'offline_payment') {
                if (($data['offline_trx_id'] ?? null) == null) {
                    return array('success' => 0, 'message' => translate("Transaction ID cannot be null."));
                }
                $order->manual_payment_data = json_encode($data);
                $order->manual_payment = 1;
            }

            DB::beginTransaction();

            // Lock exactly the cart rows being sold (primary key, ascending): the same cart submitted twice (double click, two tabs, a retry) queues here,
            // and the second finds the rows already consumed and gets the answer a sequential repeat gets ("Please select a product.").
            $cartIds = $carts->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $lockedCartIds = Cart::whereIn('id', $cartIds)->orderBy('id')->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (array_diff($cartIds, $lockedCartIds) !== []) {
                DB::rollBack();

                return array('success' => 0, 'message' => translate("Please select a product."));
            }

            // Take the stock for the whole sale first, in one place. A line that cannot be served
            // stops the sale here with nothing half-taken.
            $stockLines = [];
            foreach ($carts as $cartItem) {
                $lineProduct = $cartItem->product;
                $stockLines[] = [
                    'key' => $cartItem['id'],
                    'product_id' => $cartItem['product_id'],
                    'variant' => $cartItem['variation'],
                    'quantity' => $cartItem['quantity'],
                    'digital' => $lineProduct && $lineProduct->digital == 1,
                ];
            }
            try {
                $stockTake = app(StockLedger::class)->take($stockLines, ['immediate' => true]); // POS sells goods on the spot: stock and FIFO cost move now
            } catch (InventoryException $e) {
                DB::rollBack();
                $context = $e->context();
                $refused = Product::find($context['product_id'] ?? 0);

                return array('success' => 0, 'message' => ($refused ? $refused->name . (($context['variant'] ?? '') !== '' ? ' (' . $context['variant'] . ')' : '') . ' ' : '') . translate(" just stock outs."));
            }

            if ($order->save()) {
                $subtotal = 0;
                $tax = 0;
                $totalCOGS = 0; // Track total cost of goods sold

                foreach ($carts as $key => $cartItem) {
                    $product_stock = $cartItem->product->stocks->where('variant', $cartItem['variation'])->first();
                    $product = $product_stock->product;
                    $product_variation = $product_stock->variant;

                    $subtotal += $cartItem['price'] * $cartItem['quantity'];
                    $tax += $cartItem['tax'] * $cartItem['quantity'];

                    // FIFO costing: the batches were taken above, together with the stock.
                    $takenLine = $stockTake->line($cartItem['id']);
                    $fifo = ['total_cost' => $takenLine['cost'], 'batches' => $takenLine['batches']];

                    $totalCost = $fifo['total_cost'];
                    $avgBuyingPrice = $totalCost / $cartItem['quantity'];
                    $sellingPrice = cart_product_price($cartItem, $product, false, false);
                    $profit = ($sellingPrice - $avgBuyingPrice) * $cartItem['quantity'];
                    
                    $totalCOGS += $totalCost; // Accumulate COGS

                    $order_detail = new OrderDetail;
                    if (\App\Services\Inventory\InventoryMode::installed()) {
                        $order_detail->stock_mode = 'legacy'; // already deducted above; never part of the confirmed-order model
                    }
                    $order_detail->order_id = $order->id;
                    $order_detail->seller_id = $product->user_id;
                    $order_detail->product_id = $product->id;
                    $order_detail->selling_price = $sellingPrice;
                    $order_detail->variation = $product_variation;
                    $order_detail->price = $cartItem['price'] * $cartItem['quantity'];
                    $order_detail->tax = $cartItem['tax'] * $cartItem['quantity'];
                    $order_detail->quantity = $cartItem['quantity'];
                    $order_detail->shipping_type = null;

                    // FIFO values
                    $order_detail->buying_price = $avgBuyingPrice;
                    $order_detail->profit = $profit;

                    if (count($fifo['batches'])) {
                        $firstBatch = $fifo['batches'][0];
                        $order_detail->purchase_id = $firstBatch['purchase_id'];
                        $order_detail->supplier_id = $firstBatch['supplier_id'];
                    }

                    // Which batches supplied the line, so a later cancel returns exactly those units.
                    $order_detail->fifo_batch_info = $stockTake->batchInfo($cartItem['id']);

                    $shippingCost = floatval($data['shippingCost'] ?? 0);
                    if ($shippingCost >= 0) {
                        $order_detail->shipping_cost = $shippingCost;
                    } else {
                        $order_detail->shipping_cost = 0;
                    }

                    $order_detail->save();

                    $product->num_of_sale += $cartItem['quantity'];
                    $product->save();
                }

                // Calculate grand total
                $calculatedGrandTotal = $subtotal + $tax + floatval($data['shippingCost'] ?? 0);
                $discount = floatval($data['discount'] ?? 0);

                if ($discount > 0) {
                    $calculatedGrandTotal -= $discount;
                    $order->coupon_discount = $discount;
                }

                $order->grand_total = $calculatedGrandTotal;

                // Calculate total due
                $totalDue = $calculatedGrandTotal - $totalPayment;

                Log::info("Order totals - Subtotal: {$subtotal}, Tax: {$tax}, Shipping: " . ($data['shippingCost'] ?? 0) . ", Discount: {$discount}, Grand Total: {$calculatedGrandTotal}, Total Payment: {$totalPayment}, Total Due: {$totalDue}, COGS: {$totalCOGS}");

                // Get the transaction date for statement logs
                $transactionDate = now()->toDateString();

                // Determine delivery_status and payment_status based on payment
                if ($totalDue == 0) {
                    // Fully paid
                    $order->delivery_status = 'confirmed';
                    $order->payment_status = 'paid';
                    $order->partial_paid_amount = 0;
                    $order->due_amount = 0;

                    // Update chart_of_accounts current_balance for each payment method
                    foreach ($paymentMethods as $index => $accountId) {
                        $account = ChartOfAccount::find($accountId);
                        if ($account) {
                            $oldBalance = $account->current_balance;
                            $account->current_balance += $paymentAmounts[$index];
                            $account->save();

                            Log::info("Account {$account->name} (ID: {$accountId}) balance updated from {$oldBalance} to {$account->current_balance} (+{$paymentAmounts[$index]})");

                            // Insert statement log for payment received
                            self::insertOrderStatementLog(
                                $accountId,
                                $transactionDate,
                                $order->code,
                                'order_payment',
                                $paymentAmounts[$index], // debit (money coming in)
                                0, // credit
                                'POS Order payment: ' . $order->code . ' (' . ucfirst($paymentMethodTypes[$index]) . ')',
                                $order
                            );
                        } else {
                            Log::warning("Account not found with ID: {$accountId}");
                        }
                    }
                } elseif ($totalPayment == 0) {
                    // No payment made - Cash on Delivery
                    $order->delivery_status = 'pending';
                    $order->payment_type = 'cash_on_delivery';
                    $order->payment_status = 'unpaid';
                    $order->partial_paid_amount = 0;
                    $order->due_amount = $calculatedGrandTotal;

                    // Log due amount to Accounts Receivable (1-1301)
                    // $accountsReceivable = ChartOfAccount::where('code', '1-1301')
                    //     ->where('sub_type', 'receivable')
                    //     ->where('status', 'active')
                    //     ->first();
                    $accountsReceivable = ChartOfAccount::where('type', 'asset')
                        ->where('sub_type', 'receivable')
                        ->where('name', 'Accounts Receivable')
                        ->where('status', 'active')
                        ->first();
                    if ($accountsReceivable) {
                        $accountsReceivable->current_balance += $calculatedGrandTotal;
                        $accountsReceivable->save();

                        self::insertOrderStatementLog(
                            $accountsReceivable->id,
                            $transactionDate,
                            $order->code,
                            'order_due',
                            $calculatedGrandTotal, // debit (amount owed by customer)
                            0, // credit
                            'POS Order due amount (COD): ' . $order->code,
                            $order
                        );

                        Log::info("Accounts Receivable updated: +{$calculatedGrandTotal} for order {$order->code}");
                    }
                } else {
                    // Partial payment
                    $order->delivery_status = 'confirmed';
                    $order->payment_status = 'partial';
                    $order->partial_paid_amount = $totalPayment;
                    $order->due_amount = $totalDue;

                    // Update chart_of_accounts current_balance for each payment method
                    foreach ($paymentMethods as $index => $accountId) {
                        $account = ChartOfAccount::find($accountId);
                        if ($account) {
                            $oldBalance = $account->current_balance;
                            $account->current_balance += $paymentAmounts[$index];
                            $account->save();

                            Log::info("Account {$account->name} (ID: {$accountId}) balance updated from {$oldBalance} to {$account->current_balance} (+{$paymentAmounts[$index]})");

                            // Insert statement log for partial payment received
                            self::insertOrderStatementLog(
                                $accountId,
                                $transactionDate,
                                $order->code,
                                'order_payment',
                                $paymentAmounts[$index], // debit (money coming in)
                                0, // credit
                                'POS Order partial payment: ' . $order->code . ' (' . ucfirst($paymentMethodTypes[$index]) . ')',
                                $order
                            );
                        } else {
                            Log::warning("Account not found with ID: {$accountId}");
                        }
                    }

                    // If partial payment, log the due amount to Accounts Receivable (1-1301)
                    if ($totalDue > 0) {
                         
                        // $accountsReceivable = ChartOfAccount::where('code', '1-1301')
                        //     ->where('sub_type', 'receivable')
                        //     ->where('status', 'active')
                        //     ->first();
                        $accountsReceivable = ChartOfAccount::where('type', 'asset')
                        ->where('sub_type', 'receivable')
                        ->where('name', 'Accounts Receivable')
                        ->where('status', 'active')
                        ->first();
                        if ($accountsReceivable) {
                            $accountsReceivable->current_balance += $totalDue;
                            $accountsReceivable->save();

                            self::insertOrderStatementLog(
                                $accountsReceivable->id,
                                $transactionDate,
                                $order->code,
                                'order_due',
                                $totalDue, // debit (amount owed by customer)
                                0, // credit
                                'POS Order due amount (Partial): ' . $order->code,
                                $order
                            );

                            Log::info("Accounts Receivable updated: +{$totalDue} for order {$order->code}");
                        }
                    }
                }

                // Update COGS account (6-61000) - Debit (increase expense)
                if ($totalCOGS > 0) {
                    $cogsAccount = ChartOfAccount::where('type', 'expense')
                        ->where('sub_type', 'cost_of_sales')
                        ->where('name', 'Cost of Goods Sold')
                        ->where('status', 'active')
                        ->first();
                    
                    if ($cogsAccount) {
                        $cogsAccount->current_balance += $totalCOGS;
                        $cogsAccount->save();

                        self::insertOrderStatementLog(
                            $cogsAccount->id,
                            $transactionDate,
                            $order->code,
                            'order_cogs',
                            $totalCOGS,
                            0,
                            'COGS for POS Order: ' . $order->code,
                            $order
                        );

                        Log::info("COGS account (6-61000) updated: +{$totalCOGS} for order {$order->code}");
                    } else {
                        Log::warning("COGS account (6-61000 - cost_of_sales) not found");
                    }
                }

                // Update Sales Revenue account - Credit (increase income)
                // Try 4-41001 (Sales) first, then 4-4100 (Sales Revenue) as fallback
                // $salesAccount = ChartOfAccount::where('code', '4-41001')
                //     ->where('sub_type', 'sales')
                //     ->where('status', 'active')
                //     ->first();

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
                    $salesAccount->current_balance += $calculatedGrandTotal;
                    $salesAccount->save();

                    // Insert statement log for sales revenue
                    self::insertOrderStatementLog(
                        $salesAccount->id,
                        $transactionDate,
                        $order->code,
                        'order_sales',
                        0, // debit
                        $calculatedGrandTotal, // credit (increase income)
                        'Sales revenue for POS Order: ' . $order->code,
                        $order
                    );

                    Log::info("Sales account ({$salesAccount->code}) updated: +{$calculatedGrandTotal} for order {$order->code}");
                } else {
                    Log::warning("Sales account (4-41001 or 4-4100) not found");
                }

                // Update Inventory account - Credit (decrease asset)
                if ($totalCOGS > 0) {
                    $inventoryAccount = ChartOfAccount::where('code', '1-15001')
                        ->where('sub_type', 'inventory')
                        ->where('status', 'active')
                        ->first();
                    
                    if ($inventoryAccount) {
                        $inventoryAccount->current_balance -= $totalCOGS;
                        $inventoryAccount->save();

                        self::insertOrderStatementLog(
                            $inventoryAccount->id,
                            $transactionDate,
                            $order->code,
                            'order_inventory',
                            0,
                            $totalCOGS,
                            'Inventory reduction for POS Order: ' . $order->code,
                            $order
                        );

                        Log::info("Inventory account (1-15001) updated: -{$totalCOGS} for order {$order->code}");
                    } else {
                        Log::warning("Inventory account (1-15001) not found");
                    }
                }

                $order->seller_id = $product->user_id ?? 1;
                $order->save();

                // Clear cart
                // Only the cart rows of THIS sale. The old condition (user_id = NULL OR temp_user_id = ...) matched every walk-in cart of every counter
                // (user_id is NULL for all of them), so completing one sale deleted the carts of all the other customers waiting at the POS.
                Cart::whereIn('id', $cartIds)->delete();

                DB::commit();

                return array('success' => 1, 'message' => translate('Order Completed Successfully.'));
            } else {
                DB::rollBack();
                app(StockLedger::class)->release($stockTake);

                return array('success' => 0, 'message' => translate('Failed to save order.'));
            }
        }

        return array('success' => 0, 'message' => translate("Please select a product."));
    } catch (\Throwable $e) {
        // Nothing of a failed sale is kept: the transaction undoes the orders, accounts and stock, and
        // batch units that do not roll back (MyISAM) are given back by hand.
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        if ($stockTake !== null) {
            app(StockLedger::class)->release($stockTake);
        }
        Log::error('OrderStore error: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        return array('success' => 0, 'message' => 'Server error occurred: ' . $e->getMessage());
    }
}




    protected static function consumePurchaseProductQtyFIFO($productId, $variant, $qtyToConsume)
    {
        $remaining = $qtyToConsume;
        $totalCost = 0.0;
        $batches = [];

        if ($remaining <= 0) {
            return ['total_cost' => 0.0, 'batches' => []];
        }


        // $batchesQuery = PurchaseProduct::with('purchase')
        //     ->where('product_id', $productId)
        //     ->where('remaining_qty', '>', 0)
        //     ->when(!empty($variant), function ($q) use ($variant) {
        //         $q->where('product_name', 'LIKE', "%({$variant})%");
        //     })
        //     ->orderBy('id', 'asc')
        //     ->get();
        $batchesQuery = PurchaseProduct::with('purchase')
            ->where('product_id', $productId)
            ->where('remaining_qty', '>', 0)
            ->when(!empty($variant), function ($q) use ($variant) {
                return $q->where('variant', $variant);
            })
            ->when(empty($variant), function ($q) {
                return $q->whereNull('variant');
            })
            ->orderBy('id', 'asc')
            ->get();


        // dd($batchesQuery);

        foreach ($batchesQuery as $batch) {
            if ($remaining <= 0) break;

            $available = $batch->remaining_qty;
            if ($available <= 0) continue;

            $take = min($available, $remaining);

            // Reduce from batch
            $batch->remaining_qty -= $take;
            $batch->save();

            // Cost calculation
            $costForThisTake = $take * $batch->buying_unit_cost;
            $totalCost += $costForThisTake;

            // Keep record
            $batches[] = [
                'purchase_product_id' => $batch->id,
                'purchase_id' => $batch->purchase_id,
                'supplier_id' => optional($batch->purchase)->supplier_id,
                'qty' => $take,
                'unit_price' => $batch->buying_unit_cost,
                'total_price' => $costForThisTake,
            ];

            $remaining -= $take;
        }

        if ($remaining > 0) {
            //   flash(translate('Product has been inserted successfully'))->success();
            flash("Insufficient purchase stock for Product ID {$productId} ({$variant}). Needed {$qtyToConsume}")
                ->error();
            throw new \Exception("Insufficient purchase stock for Product ID {$productId} ({$variant}). Needed {$qtyToConsume}");
        }

        return ['total_cost' => $totalCost, 'batches' => $batches];
    }
    // without shipping info 
    // public static function orderStore($data)
    // {
    //     // Ensure shippingInfo is always set with default values if not provided
    //     $shippingInfo = $data['shippingInfo'] ?? [];
    //     $shippingInfo = [
    //         'name' => $shippingInfo['name'] ?? 'no name',
    //         'phone' => $shippingInfo['phone'] ?? '111',
    //         'address' => $shippingInfo['address'] ?? 'guest-address',
    //         'email' => $shippingInfo['email'] ?? ($data['email'] ?? 'guest@gmail.com')
    //     ];

    //     // Validate shippingInfo fields
    //     if (empty($shippingInfo['name']) || empty($shippingInfo['phone']) || empty($shippingInfo['address'])) {
    //         return array('success' => 0, 'message' => translate("Please Add Shipping Information."));
    //     }

    //     // Continue with existing logic
    //     $carts = get_pos_user_cart($data['user_id'], $data['temp_usder_id']);
    //     if (count($carts) > 0) {
    //         $order = new Order();
    //         $userId = $data['user_id'];
    //         if ($userId == null) {
    //             $order->guest_id = $carts[0]->temp_user_id;
    //         } else {
    //             $order->user_id = $userId;
    //         }
    //         $order->shipping_address = json_encode($shippingInfo);

    //         $order->payment_type = $data['payment_type'];
    //         $order->delivery_viewed = '0';
    //         $order->payment_status_viewed = '0';
    //         $order->code = date('Ymd-His') . rand(10, 99);
    //         $order->date = strtotime('now');
    //         $order->payment_status = $data['payment_type'] != 'cash_on_delivery' ? 'paid' : 'unpaid';
    //         $order->payment_details = $data['payment_type'];
    //         $order->order_from = 'pos';

    //         if ($data['payment_type'] == 'offline_payment') {
    //             if ($data['offline_trx_id'] == null) {
    //                 return array('success' => 0, 'message' => translate("Transaction ID cannot be null."));
    //             }
    //             $data['name'] = $data['offline_payment_method'];
    //             $data['amount'] = $data['offline_payment_amount'];
    //             $data['trx_id'] = $data['offline_trx_id'];
    //             $data['photo'] = $data['offline_payment_proof'];
    //             $order->manual_payment_data = json_encode($data);
    //             $order->manual_payment = 1;
    //         }

    //         if ($order->save()) {
    //             $subtotal = 0;
    //             $tax = 0;
    //             foreach ($carts as $key => $cartItem) {
    //                 $product_stock = $cartItem->product->stocks->where('variant', $cartItem['variation'])->first();
    //                 $product = $product_stock->product;
    //                 $product_variation = $product_stock->variant;

    //                 $subtotal += $cartItem['price'] * $cartItem['quantity'];
    //                 $tax += $cartItem['tax'] * $cartItem['quantity'];

    //                 if ($product->digital == 0) {
    //                     if ($cartItem['quantity'] > $product_stock->qty) {
    //                         $order->delete();
    //                         return array('success' => 0, 'message' => $product->name . ' (' . $product_variation . ') ' . translate(" just stock outs."));
    //                     } else {
    //                         $product_stock->qty -= $cartItem['quantity'];
    //                         $product_stock->save();
    //                     }
    //                 }

    //                 $order_detail = new OrderDetail;
    //                 $order_detail->order_id = $order->id;
    //                 $order_detail->seller_id = $product->user_id;
    //                 $order_detail->product_id = $product->id;
    //                 $order_detail->payment_status = $data['payment_type'] != 'cash_on_delivery' ? 'paid' : 'unpaid';
    //                 $order_detail->variation = $product_variation;
    //                 $order_detail->price = $cartItem['price'] * $cartItem['quantity'];
    //                 $order_detail->tax = $cartItem['tax'] * $cartItem['quantity'];
    //                 $order_detail->quantity = $cartItem['quantity'];
    //                 $order_detail->shipping_type = null;

    //                 if ($data['shippingCost'] >= 0) {
    //                     $order_detail->shipping_cost = $data['shippingCost'] / count($carts);
    //                 } else {
    //                     $order_detail->shipping_cost = 0;
    //                 }

    //                 $order_detail->save();

    //                 $product->num_of_sale++;
    //                 $product->save();
    //             }

    //             $order->grand_total = $subtotal + $tax + $data['shippingCost'];

    //             if ($data['discount']) {
    //                 $order->grand_total -= $data['discount'];
    //                 $order->coupon_discount = $data['discount'];
    //             }

    //             $order->seller_id = $product->user_id;
    //             $order->save();

    //             $array['view'] = 'emails.invoice';
    //             $array['subject'] = 'Your order has been placed - ' . $order->code;
    //             $array['from'] = env('MAIL_USERNAME');
    //             $array['order'] = $order;

    //             $admin_products = array();
    //             $seller_products = array();

    //             foreach ($order->orderDetails as $key => $orderDetail) {
    //                 if ($orderDetail->product->added_by == 'admin') {
    //                     array_push($admin_products, $orderDetail->product->id);
    //                 } else {
    //                     $product_ids = array();
    //                     if (array_key_exists($orderDetail->product->user_id, $seller_products)) {
    //                         $product_ids = $seller_products[$orderDetail->product->user_id];
    //                     }
    //                     array_push($product_ids, $orderDetail->product->id);
    //                     $seller_products[$orderDetail->product->user_id] = $product_ids;
    //                 }
    //             }

    //             foreach ($seller_products as $key => $seller_product) {
    //                 try {
    //                     Mail::to(User::find($key)->email)->queue(new InvoiceEmailManager($array));
    //                 } catch (\Exception $e) {
    //                 }
    //             }

    //             // Sends email to customer with the invoice pdf attached
    //             if (env('MAIL_USERNAME') != null) {
    //                 try {
    //                     Mail::to($shippingInfo['email'])->queue(new InvoiceEmailManager($array));
    //                     Mail::to(User::where('user_type', 'admin')->first()->email)->queue(new InvoiceEmailManager($array));
    //                 } catch (\Exception $e) {
    //                 }
    //             }

    //             if ($userId != NULL && $order->payment_status == 'paid') {
    //                 calculateCommissionAffilationClubPoint($order);
    //             }

    //             Cart::where('user_id', $order->user_id)->orWhere('temp_user_id', $order->guest_id)->delete();

    //             return array('success' => 1, 'message' => translate('Order Completed Successfully.'));
    //         } else {
    //             return array('success' => 0, 'message' => translate('Please input customer information.'));
    //         }
    //     }
    //     return array('success' => 0, 'message' => translate("Please select a product."));
    // }


}
