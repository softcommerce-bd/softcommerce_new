"use client";

/**
 * Totals for a tracked order.
 *
 * Restyled on the design system. Note this is a different component from
 * `app/components/layout/OrderSummary.jsx`, which is the cart's editable
 * summary; this one only reports a completed order's figures.
 */

import { formatPrice } from "../utils/orderHelpers";

export default function OrderSummary({ subtotal, totalItems, couponDiscount, total }) {
  return (
    <section className="card p-5">
      <h2 className="type-headline mb-4 text-ink">Summary</h2>

      <dl className="space-y-2.5">
        <div className="type-subhead flex justify-between">
          <dt className="text-ink-secondary">
            Subtotal ({totalItems} {totalItems === 1 ? "item" : "items"})
          </dt>
          <dd className="tabular text-ink">{formatPrice(subtotal)}</dd>
        </div>

        {couponDiscount > 0 && (
          <div className="type-subhead flex justify-between text-success">
            <dt>Coupon discount</dt>
            <dd className="tabular">−{formatPrice(couponDiscount)}</dd>
          </div>
        )}
      </dl>

      <div className="divider my-4" />

      <div className="flex items-baseline justify-between">
        <span className="type-headline text-ink">Total</span>
        <span className="type-title-3 tabular text-ink">{formatPrice(total)}</span>
      </div>
    </section>
  );
}
