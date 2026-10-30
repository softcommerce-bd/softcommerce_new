<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\CouponCollection;
use App\Http\Resources\V2\ProductMiniCollection;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Product;
use App\Models\UserCoupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function apply(Request $request)
    {
        $coupon = Coupon::where('code', $request->code)->first();

        if (!$coupon) {
            return response()->json(['success' => false, 'message' => translate('The coupon is invalid')]);
        }

        $userId      = auth()->id();
        $now         = strtotime(date('Y-m-d'));
        $userCoupon  = null;

        // --- welcome_base: requires a UserCoupon row with a valid expiry ---
        if ($coupon->type == 'welcome_base') {
            if (!$userId) {
                return response()->json(['success' => false, 'message' => translate('Please login to use this coupon')]);
            }
            $userCoupon = UserCoupon::where('user_id', $userId)->where('coupon_id', $coupon->id)->first();
            if (!$userCoupon) {
                return response()->json(['success' => false, 'message' => translate('The coupon is invalid')]);
            }
            if ($userCoupon->expiry_date < strtotime(date('Y-m-d H:i:s'))) {
                return response()->json(['success' => false, 'message' => translate('Coupon expired!')]);
            }
        } else {
            // --- cart_base / product_base: check date window ---
            if ($now < $coupon->start_date || $now > $coupon->end_date) {
                return response()->json(['success' => false, 'message' => translate('Coupon expired!')]);
            }
        }

        // --- Usage check (only for authenticated users) ---
        if ($userId && $this->isCouponAlreadyApplied($userId, $coupon->id)) {
            return response()->json(['success' => false, 'message' => translate('You already used this coupon!')]);
        }

        // --- Fetch cart items scoped to the coupon owner (seller) ---
        $cartQuery = Cart::where('owner_id', $coupon->user_id)->active();
        if ($userId) {
            $cartQuery->where('user_id', $userId);
        }
        $cartItems = $cartQuery->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['success' => false, 'message' => translate('This coupon is not applicable to your cart products!')]);
        }

        $couponDetails  = json_decode($coupon->details);
        $couponDiscount = 0;

        if ($coupon->type == 'cart_base' || $coupon->type == 'welcome_base') {
            // Quantity-aware sum using the same helper as the web checkout
            $sum = 0;
            foreach ($cartItems as $cartItem) {
                $product = Product::find($cartItem->product_id);
                if ($product) {
                    $sum += cart_product_price($cartItem, $product, false, false) * $cartItem->quantity;
                    $sum += cart_product_tax($cartItem, $product, false) * $cartItem->quantity;
                    $sum += $cartItem->shipping_cost;
                }
            }

            if ($coupon->type == 'cart_base') {
                // Use >= to match web checkout behaviour (was incorrectly > before)
                if ($sum >= $couponDetails->min_buy) {
                    if ($coupon->discount_type == 'percent') {
                        $couponDiscount = ($sum * $coupon->discount) / 100;
                        if ($couponDiscount > $couponDetails->max_discount) {
                            $couponDiscount = $couponDetails->max_discount;
                        }
                    } else {
                        $couponDiscount = $coupon->discount;
                    }
                }
            } elseif ($coupon->type == 'welcome_base') {
                if ($sum >= $userCoupon->min_buy) {
                    $couponDiscount = $userCoupon->discount_type == 'percent'
                        ? ($sum * $userCoupon->discount) / 100
                        : $userCoupon->discount;
                }
            }
        } elseif ($coupon->type == 'product_base') {
            foreach ($cartItems as $cartItem) {
                $product = Product::find($cartItem->product_id);
                foreach ($couponDetails as $couponDetail) {
                    if ($couponDetail->product_id == $cartItem->product_id && $product) {
                        if ($coupon->discount_type == 'percent') {
                            // Quantity-aware: multiply unit price × quantity
                            $couponDiscount += (cart_product_price($cartItem, $product, false, false) * $coupon->discount / 100) * $cartItem->quantity;
                        } elseif ($coupon->discount_type == 'amount') {
                            $couponDiscount += $coupon->discount * $cartItem->quantity;
                        }
                    }
                }
            }
        }

        if ($couponDiscount <= 0) {
            return response()->json(['success' => false, 'message' => translate('This coupon is not applicable to your cart products!')]);
        }

        return response()->json([
            'success'  => true,
            'discount' => (float) $couponDiscount,
            'message'  => translate('Coupon code applied successfully'),
        ]);
    }

    protected function isCouponAlreadyApplied($userId, $couponId)
    {
        return CouponUsage::where(['user_id' => $userId, 'coupon_id' => $couponId])->count() > 0;
    }


    public function couponList()
    {
        $now = strtotime(date('Y-m-d'));
        $coupons = Coupon::where('start_date', '<=', $now)->where('end_date', '>=', $now)->paginate(10);
        return new CouponCollection($coupons);
    }

    public function getCouponProducts($id)
    {
        $coupon = Coupon::where('id', $id)->first();
        if($coupon->type == 'product_base'){
            $products = json_decode($coupon->details);
            $coupon_products = [];
            foreach($products as $product) {
                array_push($coupon_products, $product->product_id);
            }
            $products = get_multiple_products($coupon_products);
            return new ProductMiniCollection($products);

        }
        return $this->failed(translate('Something went wrong'));
    }

}
