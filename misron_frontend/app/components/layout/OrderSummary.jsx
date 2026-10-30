"use client";

/**
 * Cart totals panel with voucher entry.
 *
 * Restyled on the design system: a raised card with a hairline rule above the
 * total, tabular figures so the numbers line up, and the shared Button and
 * field styles for the voucher row.
 *
 * Voucher apply posts to /api/v2/coupon-apply (the real Laravel endpoint).
 * The backend expects { coupon_code } for authenticated users, plus
 * { temp_user_id } for guests.  On success it returns { result: true,
 * coupon_discount, discount_type }.  The discount is applied locally until
 * the cart is refreshed from the server.
 *
 * Total calculation, shipping accumulation and navigation are unchanged.
 */

import { useState } from "react";
import { ArrowRight, Check } from "lucide-react";

import { useAppSelector } from "@/store/hooks";
import { useCart } from "@/store/hooks/useCart";
import { toTk } from "@/utils/currency";
import { Button } from "../ui";
import { useCheckoutModal } from "@/app/components/checkout/CheckoutModalProvider";

const parsePrice = (priceString) => {
  if (!priceString) return 0;
  const numericValue = priceString.replace(/[^\d.-]/g, "");
  return parseFloat(numericValue) || 0;
};

// The API's currency_symbol is ignored on purpose: the store shows the "৳" sign only.
const formatPrice = (price) => toTk(price);

export default function OrderSummary() {
  const { openCheckout } = useCheckoutModal();
  const { cartData } = useAppSelector((state) => state.cart);
  const { applyVoucher } = useCart();

  const [voucherCode, setVoucherCode] = useState("");
  const [appliedVoucher, setAppliedVoucher] = useState(null);
  const [voucherError, setVoucherError] = useState("");
  const [isApplying, setIsApplying] = useState(false);

  const calculateTotals = () => {
    if (!cartData?.data) {
      return { totalItems: 0, subtotal: 0, shippingFee: 0, discount: 0, total: 0 };
    }

    let totalItems = 0;
    let subtotal = 0;
    let shippingFee = 0;

    cartData.data.forEach((shop) => {
      totalItems += shop.cart_items.length;
      subtotal += parsePrice(shop.sub_total);

      shop.cart_items.forEach((item) => {
        shippingFee += item.shipping_cost || 0;
      });
    });

    let discount = 0;
    if (appliedVoucher) {
      if (appliedVoucher.type === "percentage") {
        discount = (subtotal * appliedVoucher.value) / 100;
      } else if (appliedVoucher.type === "fixed") {
        discount = appliedVoucher.value;
      }
    }

    return {
      totalItems,
      subtotal,
      shippingFee,
      discount,
      total: subtotal + shippingFee - discount,
    };
  };

  const { totalItems, subtotal, shippingFee, discount, total } = calculateTotals();

  const handleApplyVoucher = async () => {
    if (!voucherCode.trim()) {
      setVoucherError("Please enter a voucher code");
      return;
    }

    setIsApplying(true);
    setVoucherError("");

    try {
      const token = typeof window !== "undefined" && localStorage.getItem("token");
      const guestId =
        typeof window !== "undefined" &&
        (localStorage.getItem("temp_user_id") || sessionStorage.getItem("temp_user_id"));

      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/coupon-apply`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            ...(token && { Authorization: `Bearer ${token}` }),
          },
          body: JSON.stringify({
            coupon_code: voucherCode,
            ...(!token && guestId && { temp_user_id: guestId }),
          }),
        },
      );

      const data = await response.json();

      if (data.result) {
        setAppliedVoucher({
          code: voucherCode,
          // Normalise to the shape calculateTotals() expects
          type: data.discount_type === "percent" ? "percentage" : "fixed",
          value: parseFloat(data.coupon_discount ?? data.discount ?? 0),
          discount: parseFloat(data.coupon_discount ?? data.discount ?? 0),
        });
        setVoucherCode("");
      } else {
        setVoucherError(data.message || "Invalid voucher code");
      }
    } catch (error) {
      setVoucherError("Failed to apply voucher. Please try again.");
    } finally {
      setIsApplying(false);
    }
  };

  const handleRemoveVoucher = () => setAppliedVoucher(null);

  const handleProceedToCheckout = openCheckout;

  return (
    <div className="card-raised p-6">
      <h2 className="type-headline mb-5 text-ink">Order summary</h2>

      <dl className="space-y-3">
        <div className="type-subhead flex justify-between">
          <dt className="text-ink-secondary">
            {totalItems} {totalItems === 1 ? "item" : "items"}
          </dt>
          <dd className="tabular text-ink">{formatPrice(subtotal)}</dd>
        </div>

        <div className="type-subhead flex justify-between">
          <dt className="text-ink-secondary">Shipping</dt>
          <dd className="tabular text-ink">
            {shippingFee > 0 ? formatPrice(shippingFee) : "Free"}
          </dd>
        </div>

        {discount > 0 && (
          <div className="type-subhead flex justify-between text-success">
            <dt>Discount · {appliedVoucher?.code}</dt>
            <dd className="tabular">−{formatPrice(discount)}</dd>
          </div>
        )}
      </dl>

      {/* ── Voucher ────────────────────────────────────────────────────────*/}
      <div className="mt-5">
        <div className="flex gap-2">
          <label htmlFor="voucher-code" className="sr-only-focusable">
            Voucher code
          </label>
          <input
            id="voucher-code"
            type="text"
            placeholder="Voucher code"
            value={voucherCode}
            onChange={(e) => setVoucherCode(e.target.value)}
            disabled={!!appliedVoucher || isApplying}
            className="field h-10 flex-1 text-[0.8125rem]"
          />

          {!appliedVoucher ? (
            <Button
              variant="secondary"
              size="sm"
              className="h-10 shrink-0"
              onClick={handleApplyVoucher}
              disabled={!voucherCode.trim()}
              loading={isApplying}
            >
              Apply
            </Button>
          ) : (
            <Button
              variant="ghost"
              size="sm"
              className="h-10 shrink-0 text-danger"
              onClick={handleRemoveVoucher}
            >
              Remove
            </Button>
          )}
        </div>

        {voucherError && (
          <p role="alert" className="type-caption mt-2 text-danger">
            {voucherError}
          </p>
        )}

        {appliedVoucher && (
          <p className="type-caption mt-2 flex items-center gap-1.5 text-success">
            <Check size={13} aria-hidden="true" />
            {appliedVoucher.type === "percentage"
              ? `${appliedVoucher.value}% off applied`
              : `${formatPrice(appliedVoucher.value)} off applied`}
          </p>
        )}
      </div>

      <div className="divider my-5" />

      <div className="mb-6 flex items-baseline justify-between">
        <span className="type-headline text-ink">Total</span>
        <span className="type-title-3 tabular text-ink">
          {formatPrice(total)}
        </span>
      </div>

      <Button
        size="lg"
        fullWidth
        onClick={handleProceedToCheckout}
        disabled={totalItems === 0}
        iconRight={ArrowRight}
      >
        Checkout
      </Button>

      <p className="type-caption mt-3.5 text-center text-ink-tertiary">
        Shipping and taxes confirmed at checkout
      </p>
    </div>
  );
}
