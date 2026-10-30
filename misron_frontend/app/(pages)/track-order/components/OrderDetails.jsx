"use client";

/**
 * Tracked order summary.
 *
 * Restyled on the design system. Two fixes carried along:
 *
 *   · "Track another order" called `onNewSearch`, a prop the page never
 *     passed, so the button did nothing. The page now passes it and the
 *     button clears the result.
 *   · A "Need Help?" button had no handler at all and has been replaced by a
 *     link to the contact page.
 *
 * Status is now a badge rather than a coloured dot plus text, so it matches
 * the dashboard's order statuses.
 *
 * Order parsing, totals and the child components are unchanged.
 */

import Link from "next/link";
import { RotateCcw } from "lucide-react";

import OrderItems from "./OrderItems";
import ShippingAddress from "./ShippingAddress";
import TrackingInfo from "./TrackingInfo";
import OrderSummary from "./OrderSummary";
import OrderStatusBadge from "@/app/components/dashboard/OrderStatusBadge";
import { Button } from "@/app/components/ui";
import {
  formatDateFromTimestamp,
  getDeliveryStatus,
  getPaymentStatusBadge,
  formatPrice,
} from "../utils/orderHelpers";

export default function OrderDetails({ order, onNewSearch }) {
  const paymentStatus = getPaymentStatusBadge(order.payment_status);
  const deliveryStatus = getDeliveryStatus(order);

  const orderDetails = order.order_details?.[0]?.data || [];
  const totalItems = orderDetails.length;

  // Prices arrive pre-formatted ("৳1,299"), so the symbol and separators are
  // stripped before summing.
  const subtotal = orderDetails.reduce(
    (sum, product) =>
      sum + parseFloat(product.main_price?.replace(/[^0-9.-]+/g, "") || 0),
    0,
  );
  const total = order.grand_total || subtotal;

  return (
    <div className="space-y-4">
      {onNewSearch && (
        <div className="flex justify-end">
          <button
            type="button"
            onClick={onNewSearch}
            className="type-footnote flex items-center gap-1.5 font-semibold text-ink-tertiary transition-colors hover:text-ink"
          >
            <RotateCcw size={14} aria-hidden="true" />
            Track another order
          </button>
        </div>
      )}

      <section className="card p-5">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p className="type-caption text-ink-tertiary">Order code</p>
            <p className="type-title-3 tabular mt-0.5 text-ink">{order.code}</p>
          </div>

          <div className="text-right">
            <p className="type-caption text-ink-tertiary">Placed</p>
            <p className="type-subhead mt-0.5 font-medium text-ink">
              {formatDateFromTimestamp(order.date)}
            </p>
          </div>
        </div>

        <div className="mt-5 grid grid-cols-2 gap-5 border-t border-hairline pt-5 md:grid-cols-4">
          <div>
            <p className="type-caption mb-1.5 text-ink-tertiary">Status</p>
            <OrderStatusBadge status={deliveryStatus} type="delivery" />
          </div>

          <div>
            <p className="type-caption mb-1.5 text-ink-tertiary">Payment</p>
            <OrderStatusBadge status={paymentStatus.text} type="payment" />
          </div>

          <div>
            <p className="type-caption text-ink-tertiary">Items</p>
            <p className="type-subhead tabular mt-1 font-semibold text-ink">
              {totalItems}
            </p>
          </div>

          <div>
            <p className="type-caption text-ink-tertiary">Total</p>
            <p className="type-subhead tabular mt-1 font-semibold text-ink">
              {formatPrice(total)}
            </p>
          </div>
        </div>
      </section>

      <OrderItems orderDetails={orderDetails} />
      <ShippingAddress shippingAddress={order.shipping_address} />
      <TrackingInfo steadfastId={order.steadfast_id} />
      <OrderSummary
        subtotal={subtotal}
        totalItems={totalItems}
        couponDiscount={order.coupon_discount || 0}
        total={total}
      />

      <div className="flex flex-col gap-2.5 pt-2 sm:flex-row">
        <Button href="/pages/contact-us" variant="secondary" className="flex-1">
          Get help with this order
        </Button>
        <Button href="/shop_page" className="flex-1">
          Continue shopping
        </Button>
      </div>
    </div>
  );
}
