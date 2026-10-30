<?php


namespace App\Http\Controllers\Api\V2;


use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\UserCoupon;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;

class CheckoutController
{
    public function apply_coupon_code(Request $request)
    {
        $coupon = Coupon::where('code', $request->coupon_code)->first();
        if ($coupon == null) {
            return response()->json(['result' => false, 'message' => translate('Invalid coupon code!')]);
        }

        // Resolve user: read Bearer token via Sanctum without requiring authentication,
        // so guest users (temp_user_id) can still apply cart/product coupons.
        $authUser     = \Laravel\Sanctum\PersonalAccessToken::findToken(
            request()->bearerToken() ?? ''
        )?->tokenable;
        $user_id      = $authUser ? $authUser->id : $request->user_id;
        $temp_user_id = $request->temp_user_id;

        // --- welcome_base: requires a UserCoupon row with a valid expiry ---
        $userCoupon = null;
        if ($coupon->type == 'welcome_base') {
            if (!$user_id) {
                return response()->json(['result' => false, 'message' => translate('Please login to use this coupon')]);
            }
            $userCoupon = UserCoupon::where('user_id', $user_id)->where('coupon_id', $coupon->id)->first();
            if (!$userCoupon) {
                return response()->json(['result' => false, 'message' => translate('Invalid coupon code!')]);
            }
            if ($userCoupon->expiry_date < time()) {
                return response()->json(['result' => false, 'message' => translate('Coupon expired!')]);
            }
        } else {
            // cart_base / product_base: check the active date window
            $now = strtotime(date('Y-m-d'));
            if ($now < $coupon->start_date || $now > $coupon->end_date) {
                return response()->json(['result' => false, 'message' => translate('Coupon expired!')]);
            }
        }

        // Check cart items scoped to the coupon's seller
        $cart_items = ($user_id != null)
            ? Cart::where('user_id', $user_id)->where('owner_id', $coupon->user_id)->active()->get()
            : Cart::where('temp_user_id', $temp_user_id)->where('owner_id', $coupon->user_id)->active()->get();

        if ($cart_items->isEmpty()) {
            return response()->json(['result' => false, 'message' => translate('This coupon is not applicable to your cart products!')]);
        }

        // Usage check
        if ($user_id != null) {
            $is_used = CouponUsage::where('user_id', $user_id)->where('coupon_id', $coupon->id)->exists();
            if ($is_used) {
                return response()->json(['result' => false, 'message' => translate('You already used this coupon!')]);
            }
        }

        $coupon_details  = json_decode($coupon->details);
        $coupon_discount = 0;

        if ($coupon->type == 'cart_base' || $coupon->type == 'welcome_base') {
            $subtotal = 0;
            $tax      = 0;
            $shipping = 0;
            foreach ($cart_items as $cartItem) {
                $product   = Product::find($cartItem['product_id']);
                $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                $tax      += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
                $shipping += $cartItem['shipping_cost'];
            }
            $sum = $subtotal + $tax + $shipping;

            if ($coupon->type == 'cart_base' && $sum >= $coupon_details->min_buy) {
                if ($coupon->discount_type == 'percent') {
                    $coupon_discount = ($sum * $coupon->discount) / 100;
                    if ($coupon_discount > $coupon_details->max_discount) {
                        $coupon_discount = $coupon_details->max_discount;
                    }
                } else {
                    $coupon_discount = $coupon->discount;
                }
            } elseif ($coupon->type == 'welcome_base' && $sum >= $userCoupon->min_buy) {
                $coupon_discount = $userCoupon->discount_type == 'percent'
                    ? ($sum * $userCoupon->discount) / 100
                    : $userCoupon->discount;
            }
        } elseif ($coupon->type == 'product_base') {
            foreach ($cart_items as $cartItem) {
                $product = Product::find($cartItem['product_id']);
                foreach ($coupon_details as $coupon_detail) {
                    if ($coupon_detail->product_id == $cartItem['product_id'] && $product) {
                        if ($coupon->discount_type == 'percent') {
                            $coupon_discount += cart_product_price($cartItem, $product, false, false) * $coupon->discount / 100 * $cartItem['quantity'];
                        } else {
                            $coupon_discount += $coupon->discount * $cartItem['quantity'];
                        }
                    }
                }
            }
        }

        if ($coupon_discount > 0) {
            // Distribute discount proportionally by item value
            $total_cart_value = 0;
            foreach ($cart_items as $cartItem) {
                $product = Product::find($cartItem['product_id']);
                $total_cart_value += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
            }
            foreach ($cart_items as $cartItem) {
                $product    = Product::find($cartItem['product_id']);
                $item_value = cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                $row_discount = $total_cart_value > 0 ? ($item_value / $total_cart_value) * $coupon_discount : 0;
                Cart::where('id', $cartItem['id'])->update([
                    'discount'       => $row_discount,
                    'coupon_code'    => $request->coupon_code,
                    'coupon_applied' => 1,
                ]);
            }

            return response()->json(['result' => true, 'message' => translate('Coupon Applied'), 'coupon_discount' => round($coupon_discount, 2)]);
        }

        return response()->json(['result' => false, 'message' => translate('This coupon is not applicable to your cart products!')]);
    }


    public function remove_coupon_code(Request $request)
    {
        $user_id        = $request->user_id;
        $temp_user_id   = $request->temp_user_id;
        $cart_query = $user_id != null ? Cart::where('user_id', $user_id) : Cart::where('temp_user_id', $temp_user_id);
        $cart_query->update([
            'discount' => 0.00,
            'coupon_code' => "",
            'coupon_applied' => 0
        ]);

        return response()->json([
            'result' => true,
            'message' => translate('Coupon Removed')
        ]);
    }
}
