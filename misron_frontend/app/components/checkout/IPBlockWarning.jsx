// app/components/checkout/IPBlockWarning.jsx
'use client';

/**
 * Shown in place of the checkout when the shopper's IP is flagged.
 *
 * Restyled on the danger tokens: a tinted panel rather than a red-on-red
 * block, and the retry uses the shared button.
 */

import { ShieldAlert, RotateCw } from "lucide-react";
import { Button } from "../ui";

export default function IPBlockWarning({ reason, onRetry }) {
  return (
    <div
      role="alert"
      className="mx-auto flex max-w-lg flex-col items-center rounded-xl bg-danger-tint px-6 py-12 text-center"
    >
      <ShieldAlert size={28} className="mb-4 text-danger" strokeWidth={1.5} aria-hidden="true" />

      <h2 className="type-title-3 text-ink">Checkout unavailable</h2>

      <p className="type-callout mt-2 max-w-sm text-ink-secondary">
        {reason ||
          "Your connection has been flagged for unusual activity. Please try again later or contact support."}
      </p>

      <Button variant="secondary" className="mt-7" iconLeft={RotateCw} onClick={onRetry}>
        Try again
      </Button>
    </div>
  );
}
