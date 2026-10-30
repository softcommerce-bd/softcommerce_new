"use client";

/**
 * "Order Summary" card at the top of the checkout.
 *
 * Replaces PackageSection. The reference lists each line as thumbnail, name,
 * "Quantity: n" and the line price, then a single Total Payable row; this does
 * the same, with the figures that move the total (shipping, voucher, gift box)
 * shown as small rows above it and the voucher field inside the card.
 *
 * Quantity steppers and remove buttons are gone: the cart page owns editing,
 * and the card links back to it.
 *
 * It also owns the cart fetch that PackageSection used to make on mount, since
 * the totals in CheckoutCard read the same store.
 */

import { useEffect } from "react";
import Link from "next/link";
import { Ticket } from "lucide-react";

import { useCart } from "@/store/hooks/useCart";
import { tkText, toTk } from "@/utils/currency";

const ROW = "flex items-center justify-between text-xs";

export default function CheckoutSummary({
  subtotal,
  shippingCost,
  shippingStatus,
  discount,
  voucherCode,
  giftBoxCharge,
  total,
  voucher,
  // Called when a link leaves the summary. The popup stays open across a
  // navigation to the page it is already over, so it must be told to close.
  onNavigate,
}) {
  const { cartData, loading, loadCart } = useCart();

  useEffect(() => {
    loadCart();
    // Fetch once on mount, as PackageSection did.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const items = (cartData?.data ?? []).flatMap((shop) => shop.cart_items ?? []);
  const showSkeleton = loading && !cartData?.data;

  return (
    <div className="rounded-xl border border-stone-200/90 bg-white p-3.5 shadow-xs">
      <div className="mb-2 flex items-center justify-between gap-3">
        <p className="text-xs font-semibold uppercase tracking-wider text-stone-500">
          Order Summary
          {!showSkeleton && ` (${items.length} ${items.length === 1 ? "item" : "items"})`}
        </p>

        <Link
          href="/view-cart"
          onClick={onNavigate}
          className="text-[11px] font-medium text-amber-800 hover:underline"
        >
          Edit cart
        </Link>
      </div>

      {showSkeleton ? (
        <div className="space-y-2" aria-hidden="true">
          {[0, 1].map((i) => (
            <div key={i} className="flex items-center gap-2.5">
              <div className="h-10 w-10 animate-pulse rounded-md bg-stone-100" />
              <div className="flex-1 space-y-1.5">
                <div className="h-3 w-3/5 animate-pulse rounded bg-stone-100" />
                <div className="h-2.5 w-1/4 animate-pulse rounded bg-stone-100" />
              </div>
            </div>
          ))}
        </div>
      ) : items.length === 0 ? (
        <p className="py-3 text-center text-xs text-stone-500">
          Your cart is empty.{" "}
          <Link
            href="/shop_page"
            onClick={onNavigate}
            className="font-medium text-amber-800 hover:underline"
          >
            Browse products
          </Link>
        </p>
      ) : (
        <ul className="max-h-36 space-y-2 overflow-y-auto pr-1">
          {items.map((item) => (
            <li key={item.id} className={ROW}>
              <div className="flex min-w-0 items-center gap-2.5">
                <img
                  src={item.product_thumbnail_image}
                  alt=""
                  loading="lazy"
                  className="h-10 w-10 shrink-0 rounded-md border border-stone-100 object-cover"
                />

                <div className="min-w-0">
                  <span className="block max-w-[200px] truncate font-medium text-stone-900 sm:max-w-xs">
                    {item.product_name}
                  </span>
                  <span className="text-[11px] text-stone-400">
                    Quantity: {item.quantity}
                    {item.variation ? ` · ${item.variation}` : ""}
                  </span>
                </div>
              </div>

              <span className="shrink-0 pl-2 font-bold tabular-nums text-stone-900">
                {tkText(item.price)}
              </span>
            </li>
          ))}
        </ul>
      )}

      {/* ── Voucher ───────────────────────────────────────────────────────
          Enter applies the code rather than submitting the checkout form. */}
      {/* <div className="mt-3 flex gap-2 border-t border-stone-100 pt-3 text-xs">
        <div className="relative flex-1">
          <Ticket
            className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400"
            aria-hidden="true"
          />
          <label htmlFor="checkout-voucher" className="sr-only">
            Voucher code
          </label>
          <input
            id="checkout-voucher"
            type="text"
            placeholder="Voucher code"
            value={voucher.code}
            onChange={(e) => voucher.setCode(e.target.value)}
            onKeyDown={(e) => {
              if (e.key !== "Enter") return;
              e.preventDefault();
              if (!voucher.applied && voucher.code.trim()) voucher.apply();
            }}
            disabled={voucher.applied}
            className="w-full rounded-lg border border-stone-300 bg-white py-2 pl-9 pr-3 text-xs text-stone-800 focus:border-amber-700 focus:outline-none disabled:bg-stone-100 disabled:text-stone-400"
          />
        </div>

        {voucher.applied ? (
          <button
            type="button"
            onClick={voucher.remove}
            className="shrink-0 rounded-lg px-3 text-xs font-semibold text-rose-700 transition-colors hover:bg-rose-50"
          >
            Remove
          </button>
        ) : (
          <button
            type="button"
            onClick={voucher.apply}
            disabled={!voucher.code.trim() || voucher.applying}
            className="shrink-0 rounded-lg border border-amber-700 px-3.5 text-xs font-semibold text-amber-900 transition-colors hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-40"
          >
            {voucher.applying ? "Applying…" : "Apply"}
          </button>
        )}
      </div> */}

      {/* ── Totals ──────────────────────────────────────────────────────── */}
      {/* The reference lists lines and a total only, with free shipping noted
          beside the total. The breakdown appears only when something moves the
          total: a shipping charge, a voucher or a gift box. */}
      {(shippingCost > 0 || discount > 0 || giftBoxCharge > 0) && (
        <dl className="mt-3 space-y-1.5 border-t border-stone-100 pt-2.5">
          <div className={ROW}>
            <dt className="text-stone-500">Subtotal</dt>
            <dd className="font-medium tabular-nums text-stone-900">{toTk(subtotal)}</dd>
          </div>

          {shippingCost > 0 && (
            <div className={ROW}>
              <dt className="text-stone-500">Shipping</dt>
              <dd className="font-medium tabular-nums text-stone-900">
                {toTk(shippingCost)}
              </dd>
            </div>
          )}

          {discount > 0 && (
            <div className={`${ROW} text-emerald-700`}>
              <dt>Voucher discount{voucherCode ? ` · ${voucherCode}` : ""}</dt>
              <dd className="font-medium tabular-nums">−{toTk(discount)}</dd>
            </div>
          )}

          {giftBoxCharge > 0 && (
            <div className={ROW}>
              <dt className="text-stone-500">Gift box</dt>
              <dd className="font-medium tabular-nums text-stone-900">
                {toTk(giftBoxCharge)}
              </dd>
            </div>
          )}
        </dl>
      )}

      <div className="mt-3 flex items-center justify-between border-t border-stone-100 pt-2.5 text-xs font-semibold text-stone-900">
        <span>Total Payable:</span>
        <span className="text-base tabular-nums text-amber-900">
          {toTk(total)}{" "}
          {shippingStatus === "quoted" && shippingCost === 0 && (
            <span className="text-xs font-normal text-emerald-700">
              (Free Express Shipping)
            </span>
          )}
        </span>
      </div>
    </div>
  );
}
