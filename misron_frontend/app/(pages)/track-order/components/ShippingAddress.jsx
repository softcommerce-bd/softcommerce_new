"use client";

/**
 * Delivery address on a tracked order.
 *
 * Restyled on the design system and marked up as an `<address>`, which is
 * what it is.
 */

import { parseShippingAddress } from "../utils/orderHelpers";

export default function ShippingAddress({ shippingAddress }) {
  const address = shippingAddress ? parseShippingAddress(shippingAddress) : null;

  if (!address) return null;

  return (
    <section className="card p-5">
      <h2 className="type-headline mb-4 text-ink">Delivery address</h2>

      <address className="type-subhead not-italic text-ink-secondary">
        {address.name && (
          <span className="block font-medium text-ink">{address.name}</span>
        )}
        {address.address && <span className="mt-1.5 block">{address.address}</span>}
        {address.phone && <span className="mt-2 block">{address.phone}</span>}
        {address.email && <span className="block break-all">{address.email}</span>}
      </address>
    </section>
  );
}
