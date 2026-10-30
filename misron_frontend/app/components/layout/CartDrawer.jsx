"use client";

/**
 * Cart drawer, opened from the header's cart button.
 *
 * Replaces the small dropdown that hung under the button. The layout follows
 * the cart drawer in the reference design (see reference-design.md §2.12): a
 * serif masthead, a delivery banner, one card per line, then a pinned footer
 * with the coupon field, totals and the checkout button.
 *
 * Built on the shared Sheet, so it inherits the backdrop, Escape and
 * scroll-lock. Line items change through `useCart`, as in the old dropdown.
 *
 * Coupons are applied through the real endpoints. The backend stores the
 * coupon on the cart rows, so the discount shown here comes from
 * /cart-summary rather than being worked out locally. That is also what
 * checkout charges.
 */

import { useState, useEffect, useCallback, useMemo, memo } from "react";
import Image from "next/image";
import { ArrowRight, Check, Minus, Plus, ShoppingBag, Trash2, Truck, X } from "lucide-react";

import { useAppDispatch, useAppSelector } from "@/store/hooks";
import { useCart } from "@/store/hooks/useCart";
import { getCartSummary } from "@/store/slices/cartSlice";
import { toTk, tkText, parseAmount } from "@/utils/currency";
import { Button, Sheet, Skeleton } from "../ui";
import { useCheckoutModal } from "@/app/components/checkout/CheckoutModalProvider";

// Store policy copy, kept together so a change of delivery terms is one edit.
const DELIVERY_NOTE = "Nationwide Free Delivery";
const DELIVERY_STATUS = "Free Express Shipping Applied";

/** Who is shopping: the signed-in user, or the guest id the cart was built under. */
function readIdentity(tempUserId) {
  let userId = null;
  try {
    userId = JSON.parse(localStorage.getItem("user") || "null")?.id ?? null;
  } catch {
    userId = null;
  }
  const token = localStorage.getItem("token");

  return {
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...(token && { Authorization: `Bearer ${token}` }),
    },
    body: { user_id: userId, temp_user_id: tempUserId },
  };
}

function QuantityStepper({ value, onDecrement, onIncrement, canDecrement, canIncrement, disabled }) {
  return (
    <div className="inline-flex items-center rounded-xl border border-stone-200 bg-stone-50">
      <button
        type="button"
        onClick={onDecrement}
        disabled={!canDecrement || disabled}
        className="flex h-6 w-6 items-center justify-center rounded-l-xl text-stone-500 transition-colors hover:bg-stone-100 hover:text-stone-900 disabled:opacity-30"
        aria-label="Decrease quantity"
      >
        <Minus size={12} aria-hidden="true" />
      </button>

      <span className="tabular w-6 text-center text-[0.75rem] font-semibold text-stone-900">
        {value}
      </span>

      <button
        type="button"
        onClick={onIncrement}
        disabled={!canIncrement || disabled}
        className="flex h-6 w-6 items-center justify-center rounded-r-xl text-stone-500 transition-colors hover:bg-stone-100 hover:text-stone-900 disabled:opacity-30"
        aria-label="Increase quantity"
      >
        <Plus size={12} aria-hidden="true" />
      </button>
    </div>
  );
}

const CartLine = memo(({ item, onRemove, onQuantityChange, isUpdating }) => (
  <li
    className={`flex gap-3 rounded-xl border border-stone-200 bg-white p-2.5 shadow-xs transition-opacity ${
      isUpdating ? "pointer-events-none opacity-50" : ""
    }`}
  >
    <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-canvas-sunken">
      {item.product_thumbnail_image && (
        <Image
          src={item.product_thumbnail_image}
          alt=""
          fill
          className="object-cover"
          sizes="64px"
        />
      )}
    </div>

    <div className="flex min-w-0 flex-1 flex-col gap-1.5">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="line-clamp-2 text-[0.8125rem] font-bold leading-snug text-stone-900">
            {item.product_name}
          </p>
          {item.sku && (
            <p className="mt-0.5 font-mono text-[0.625rem] leading-tight text-stone-500">
              Code: {item.sku}
            </p>
          )}
          {item.variation && (
            <p className="text-[0.6875rem] leading-tight text-stone-500">{item.variation}</p>
          )}
        </div>

        <button
          type="button"
          onClick={() => onRemove(item.id)}
          disabled={isUpdating}
          className="-mr-1 -mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-stone-400 transition-colors hover:bg-rose-50 hover:text-rose-600"
          aria-label={`Remove ${item.product_name} from cart`}
        >
          <Trash2 size={14} aria-hidden="true" />
        </button>
      </div>

      <div className="mt-auto flex items-center justify-between gap-2 border-t border-stone-100 pt-1.5">
        <QuantityStepper
          value={item.quantity}
          disabled={isUpdating}
          canDecrement={item.quantity > item.lower_limit}
          canIncrement={item.quantity < item.upper_limit}
          onDecrement={() =>
            onQuantityChange(item.id, item.quantity, item.quantity - 1, item.upper_limit)
          }
          onIncrement={() =>
            onQuantityChange(item.id, item.quantity, item.quantity + 1, item.upper_limit)
          }
        />

        <p className="tabular text-[0.8125rem] font-bold text-stone-900">
          {tkText(item.price)}
        </p>
      </div>
    </div>
  </li>
));

CartLine.displayName = "CartLine";

function DrawerHeader({ count, onClose }) {
  return (
    <>
      <header className="flex items-center justify-between gap-4 bg-surface px-5 py-5">
        <div className="flex items-center gap-2.5">
          <ShoppingBag size={22} strokeWidth={1.75} className="text-amber-800" aria-hidden="true" />
          <h2 className="type-drawer-title text-stone-900">
            Shopping Cart
            {count > 0 && <span className="font-sans text-[1.125rem] font-medium"> ({count})</span>}
          </h2>
        </div>

        <button
          type="button"
          onClick={onClose}
          aria-label="Close cart"
          className="btn-icon btn-icon-sm btn-icon-round -mr-1 text-stone-400 hover:text-stone-700"
        >
          <X size={20} aria-hidden="true" />
        </button>
      </header>

      <div className="border-y border-amber-100 bg-amber-50 px-5 py-2">
        <div className="flex items-center justify-between gap-3 text-[0.6875rem] font-medium">
          <span className="flex items-center gap-1.5 text-amber-900">
            <Truck size={13} aria-hidden="true" />
            {DELIVERY_NOTE}
          </span>
          <span className="text-right font-semibold text-emerald-700">{DELIVERY_STATUS}</span>
        </div>
        <div className="mt-1.5 h-1 overflow-hidden rounded-full bg-amber-200/70">
          <div className="h-full w-full rounded-full bg-amber-700" />
        </div>
      </div>
    </>
  );
}

function TotalRow({ label, value, className = "" }) {
  return (
    <div className={`flex items-center justify-between text-[0.75rem] ${className}`}>
      <dt>{label}</dt>
      <dd className="tabular">{value}</dd>
    </div>
  );
}

export default function CartDrawer({ loading, onClose }) {
  const dispatch = useAppDispatch();
  const { cartData, totalQuantity, summary, tempUserId } = useAppSelector((state) => state.cart);
  const { removeItem, updateQuantity } = useCart();
  const { openCheckout } = useCheckoutModal();

  const [updatingItems, setUpdatingItems] = useState({});
  const [couponCode, setCouponCode] = useState("");
  const [couponError, setCouponError] = useState("");
  const [couponBusy, setCouponBusy] = useState(false);
  const [codEnabled, setCodEnabled] = useState(false);

  const items = useMemo(
    () => cartData?.data?.flatMap((shop) => shop.cart_items) ?? [],
    [cartData?.data],
  );

  // The drawer is mounted only while open, so this runs on open and again each
  // time the cart reloads (a quantity change, a removal), keeping the coupon
  // discount in step with the lines.
  useEffect(() => {
    if (items.length > 0) dispatch(getCartSummary());
  }, [cartData, items.length, dispatch]);

  // Whether to promise cash on delivery is the store's setting, not ours.
  useEffect(() => {
    let cancelled = false;
    fetch(`${process.env.NEXT_PUBLIC_API_URL}/active-payment-methods`)
      .then((res) => (res.ok ? res.json() : null))
      .then((data) => {
        if (!cancelled && data?.success) setCodEnabled(data.cash_on_delivery === "1");
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, []);

  const handleRemove = useCallback(
    async (cartId) => {
      setUpdatingItems((prev) => ({ ...prev, [cartId]: true }));
      await removeItem(cartId);
      setUpdatingItems((prev) => ({ ...prev, [cartId]: false }));
    },
    [removeItem],
  );

  const handleQuantityChange = useCallback(
    async (cartId, currentQty, newQty, upperLimit) => {
      if (newQty === currentQty || newQty < 1 || newQty > upperLimit) return;

      setUpdatingItems((prev) => ({ ...prev, [cartId]: true }));
      await updateQuantity(cartId, newQty, upperLimit);
      setUpdatingItems((prev) => ({ ...prev, [cartId]: false }));
    },
    [updateQuantity],
  );

  const callCouponEndpoint = async (path, extra = {}) => {
    const { headers, body } = readIdentity(tempUserId);
    const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/${path}`, {
      method: "POST",
      headers,
      body: JSON.stringify({ ...body, ...extra }),
    });
    return response.json();
  };

  const handleApplyCoupon = async (e) => {
    e.preventDefault();
    const code = couponCode.trim();
    if (!code) return;

    setCouponBusy(true);
    setCouponError("");
    try {
      const data = await callCouponEndpoint("coupon-apply", { coupon_code: code });
      if (data.result) {
        setCouponCode("");
        await dispatch(getCartSummary());
      } else {
        setCouponError(data.message || "Invalid coupon code");
      }
    } catch {
      setCouponError("Could not apply the coupon. Please try again.");
    } finally {
      setCouponBusy(false);
    }
  };

  const handleRemoveCoupon = async () => {
    setCouponBusy(true);
    setCouponError("");
    try {
      await callCouponEndpoint("coupon-remove");
      await dispatch(getCartSummary());
    } catch {
      setCouponError("Could not remove the coupon. Please try again.");
    } finally {
      setCouponBusy(false);
    }
  };

  const isEmpty = !loading && items.length === 0;

  const appliedCoupon = summary?.coupon_applied ? summary.coupon_code : "";
  const subtotal = parseAmount(cartData?.grand_total);
  const shippingFee = items.reduce((sum, item) => sum + (item.shipping_cost || 0), 0);
  const discount = appliedCoupon ? parseAmount(summary?.discount) : 0;
  const total = subtotal + shippingFee - discount;

  const footer = isEmpty ? null : (
    <div className="space-y-3">
      <div>
        <form onSubmit={handleApplyCoupon} className="flex gap-2">
          <label htmlFor="cart-drawer-coupon" className="sr-only">
            Coupon code
          </label>
          <input
            id="cart-drawer-coupon"
            type="text"
            value={couponCode}
            onChange={(e) => setCouponCode(e.target.value)}
            placeholder="Coupon code"
            disabled={couponBusy || !!appliedCoupon}
            autoComplete="off"
            className="h-10 min-w-0 flex-1 rounded-lg border border-stone-200 bg-stone-50 px-3 text-[0.75rem] font-medium uppercase tracking-wide text-stone-900 placeholder:text-[0.75rem] placeholder:font-normal placeholder:text-stone-400 focus:border-gold disabled:opacity-60"
          />
          <Button
            type="submit"
            disabled={!couponCode.trim() || !!appliedCoupon}
            loading={couponBusy}
            className="h-10 shrink-0 rounded-lg bg-stone-900 px-5 text-[0.8125rem] text-white hover:bg-stone-700"
          >
            Apply
          </Button>
        </form>

        {couponError && (
          <p role="alert" className="mt-2 text-[0.75rem] text-rose-600">
            {couponError}
          </p>
        )}

        {appliedCoupon && (
          <p className="mt-2 flex items-center gap-1.5 text-[0.75rem] font-medium text-emerald-700">
            <Check size={14} aria-hidden="true" />
            Coupon {appliedCoupon.toUpperCase()} applied successfully!
            <button
              type="button"
              onClick={handleRemoveCoupon}
              disabled={couponBusy}
              className="ml-auto text-stone-500 underline-offset-2 hover:text-stone-900 hover:underline"
            >
              Remove
            </button>
          </p>
        )}
      </div>

      <dl className="space-y-1.5 border-t border-stone-100 pt-3">
        <TotalRow label="Subtotal" value={toTk(subtotal)} className="text-stone-600" />
        {discount > 0 && (
          <TotalRow
            label={`Discount (${appliedCoupon.toUpperCase()})`}
            value={`- ${toTk(discount)}`}
            className="text-emerald-700"
          />
        )}
        <TotalRow
          label="Shipping Fee"
          value={shippingFee > 0 ? toTk(shippingFee) : "Free"}
          className={shippingFee > 0 ? "text-stone-600" : "text-emerald-700"}
        />
        <div className="flex items-baseline justify-between border-t border-stone-100 pt-2.5 text-stone-900">
          <dt className="text-[0.875rem] font-bold">Total Payable</dt>
          <dd className="tabular text-[0.9375rem] font-bold">{toTk(total)}</dd>
        </div>
      </dl>

      <div>
        <Button
          onClick={() => {
            onClose();
            openCheckout();
          }}
          size="md"
          fullWidth
          iconRight={ArrowRight}
          className="rounded-lg bg-gold text-[0.8125rem] font-semibold text-white shadow-e2 hover:bg-gold-hover"
        >
          {codEnabled ? "Proceed to Checkout (Cash on Delivery)" : "Proceed to Checkout"}
        </Button>

        {codEnabled && (
          <p className="mt-2 flex items-center justify-center gap-1.5 text-[0.625rem] text-stone-500">
            <span aria-hidden="true">🔒</span>
            Inspect your items upon arrival before paying cash
          </p>
        )}
      </div>
    </div>
  );

  return (
    <Sheet
      open
      onClose={onClose}
      label="Shopping cart"
      header={<DrawerHeader count={totalQuantity} onClose={onClose} />}
      footer={footer}
      className="bg-cream!"
      bodyClassName="px-5 py-5"
      footerClassName="border-t border-stone-200 bg-white px-5 py-4 shadow-[0_-8px_24px_rgba(28,25,23,0.04)]"
    >
      {loading && items.length === 0 ? (
        <ul className="space-y-3" aria-hidden="true">
          {[0, 1].map((i) => (
            <li
              key={i}
              className="flex gap-3 rounded-xl border border-stone-200 bg-white p-2.5"
            >
              <Skeleton className="h-16 w-16 shrink-0" rounded="lg" />
              <div className="flex-1 space-y-2.5 py-1">
                <Skeleton className="h-3.5 w-4/5" />
                <Skeleton className="h-3 w-1/3" />
                <Skeleton className="h-7 w-24" />
              </div>
            </li>
          ))}
        </ul>
      ) : isEmpty ? (
        <div className="flex flex-col items-center px-4 py-16 text-center">
          <div
            className="mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 text-amber-800"
            aria-hidden="true"
          >
            <ShoppingBag size={26} strokeWidth={1.5} />
          </div>
          <h3 className="type-title-3 text-stone-900">Your cart is empty</h3>
          <p className="type-callout mt-2 max-w-xs text-stone-500">
            Discover our timeless signature collections and add your favorite pieces.
          </p>
          <Button
            href="/shop_page"
            onClick={onClose}
            size="lg"
            className="mt-7 rounded-xl bg-gold font-semibold text-white shadow-e2 hover:bg-gold-hover"
          >
            Explore Collections
          </Button>
        </div>
      ) : (
        <ul className="space-y-3">
          {items.map((item) => (
            <CartLine
              key={item.id}
              item={item}
              onRemove={handleRemove}
              onQuantityChange={handleQuantityChange}
              isUpdating={!!updatingItems[item.id]}
            />
          ))}
        </ul>
      )}
    </Sheet>
  );
}
