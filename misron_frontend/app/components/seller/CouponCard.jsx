"use client";

/**
 * Shop coupon.
 *
 * Redrawn as a ticket: a dark panel with a perforated notch and a dashed
 * tear line, the code set in tabular figures beside a copy button.
 *
 * The previous copy button rendered `<i class="las la-copy">`, a Line Awesome
 * glyph — that font is never loaded anywhere in the app, so the button showed
 * as blank space. It is now a lucide icon, and copying gives feedback.
 *
 * The clipboard write and coupon fields are unchanged.
 */

import { useState } from "react";
import Link from "next/link";
import { Copy, Check } from "lucide-react";

export default function CouponCard({ coupon }) {
  const [copied, setCopied] = useState(false);

  const handleCopy = () => {
    navigator.clipboard.writeText(coupon.code).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  };

  return (
    <div className="relative overflow-hidden rounded-xl bg-ink p-6 text-ink-inverse">
      {/* Notches sit half outside the card, in the page's own canvas colour,
          so the ticket reads as torn rather than drawn. */}
      <span
        className="absolute -left-3 top-1/2 h-6 w-6 -translate-y-1/2 rounded-full bg-canvas"
        aria-hidden="true"
      />
      <span
        className="absolute -right-3 top-1/2 h-6 w-6 -translate-y-1/2 rounded-full bg-canvas"
        aria-hidden="true"
      />

      <div className="pb-6">
        <p className="type-caption text-white/60">
          {coupon.shop_name}
          {coupon.shop_slug && (
            <>
              {" · "}
              <Link
                href={`/shop/${coupon.shop_slug}`}
                className="underline underline-offset-2 hover:text-white"
              >
                Visit store
              </Link>
            </>
          )}
        </p>

        <p className="type-title-1 mt-2 text-white">{coupon.discount} off</p>
      </div>

      <div className="border-t border-dashed border-white/25 pt-5">
        <p className="type-caption text-white/70">
          Spend {coupon.coupon_discount_details?.min_buy} at {coupon.shop_name} to
          get {coupon.discount} off your order.
        </p>

        <div className="mt-4 flex items-center justify-between gap-3">
          <span className="tabular rounded-md bg-white/10 px-3 py-2 text-[0.875rem] font-semibold tracking-[0.08em] text-white">
            {coupon.code}
          </span>

          <button
            type="button"
            onClick={handleCopy}
            className="flex items-center gap-1.5 rounded-md px-3 py-2 text-[0.75rem] font-semibold text-white transition-colors hover:bg-white/10"
            aria-label={`Copy coupon code ${coupon.code}`}
          >
            {copied ? (
              <Check size={14} aria-hidden="true" />
            ) : (
              <Copy size={14} aria-hidden="true" />
            )}
            {copied ? "Copied" : "Copy"}
          </button>
        </div>
      </div>
    </div>
  );
}
