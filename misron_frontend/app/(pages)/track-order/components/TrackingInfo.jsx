"use client";

/**
 * Courier tracking reference.
 *
 * Only rendered once the order has a courier ID. The ID is set in tabular
 * figures and selectable, since the usual reason to look at it is to copy it
 * into the courier's own tracking page.
 */

import { Truck } from "lucide-react";

export default function TrackingInfo({ steadfastId }) {
  if (!steadfastId) return null;

  return (
    <section className="card p-5">
      <h2 className="type-headline mb-4 text-ink">Courier</h2>

      <div className="flex items-center gap-3">
        <span
          className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-canvas-sunken"
          aria-hidden="true"
        >
          <Truck size={18} strokeWidth={1.75} className="text-ink-secondary" />
        </span>

        <div className="min-w-0">
          <p className="type-subhead font-medium text-ink">Steadfast</p>
          <p className="type-caption tabular mt-0.5 select-all text-ink-tertiary">
            Tracking ID {steadfastId}
          </p>
        </div>
      </div>
    </section>
  );
}
