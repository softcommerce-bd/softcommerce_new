"use client";

/**
 * Checkout page.
 *
 * Shopping flows open checkout as a popup (see CheckoutModalProvider); this
 * route remains for direct links and reloads. It renders the same centred
 * checkout card, styled after the reference design's "Fast 1-Click Express
 * Checkout" modal. The card carries its own title and a way back to the cart.
 */

import CheckoutFlow from "@/app/components/layout/CheckoutFlow";

export default function CheckoutPage() {
  return (
    <div className="container-page min-h-screen py-8 md:py-12">
      <CheckoutFlow />
    </div>
  );
}
