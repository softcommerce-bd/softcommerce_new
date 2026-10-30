"use client";

/**
 * The checkout popup.
 *
 * Every "checkout" entry point — the cart drawer, the cart dropdown, the cart
 * page's order summary and the Buy-now button — calls `openCheckout()` from
 * `useCheckoutModal()` instead of routing to /checkout-page. The popup is
 * mounted once here, above the routes, so it opens over whatever page the
 * shopper is on and leaves their place in it untouched.
 *
 * The /checkout-page route still exists for direct links and reloads; it
 * renders the same card through CheckoutFlow.
 */

import { createContext, useCallback, useContext, useMemo, useState } from "react";
import { usePathname } from "next/navigation";
import dynamic from "next/dynamic";

import { Modal } from "@/app/components/ui";

// Loaded on first open: the checkout flow pulls in the delivery form, address
// book and payment code, none of which most page views ever need.
const CheckoutFlow = dynamic(() => import("@/app/components/layout/CheckoutFlow"), {
  ssr: false,
  loading: () => null,
});

const CheckoutModalContext = createContext({
  // Without a provider (a stray render in isolation) fall back to the page.
  openCheckout: () => window.location.assign("/checkout-page"),
  closeCheckout: () => {},
});

export function useCheckoutModal() {
  return useContext(CheckoutModalContext);
}

export default function CheckoutModalProvider({ children }) {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);

  const openCheckout = useCallback(() => setOpen(true), []);
  const closeCheckout = useCallback(() => setOpen(false), []);

  // Placing an order, or following any link out of the popup, changes the
  // route. The popup lives above the routes, so it would otherwise stay open
  // over the next page. Reset during render rather than in an effect, so the
  // stale popup never paints over the new route.
  const [prevPathname, setPrevPathname] = useState(pathname);
  if (pathname !== prevPathname) {
    setPrevPathname(pathname);
    setOpen(false);
  }

  const value = useMemo(
    () => ({ openCheckout, closeCheckout }),
    [openCheckout, closeCheckout],
  );

  return (
    <CheckoutModalContext.Provider value={value}>
      {children}

      {/* The card draws its own header — dot, title, close button — so the
          dialog supplies none, and no padding so that header meets the edges. */}
      <Modal
        open={open}
        onClose={closeCheckout}
        ariaLabel="Checkout"
        showClose={false}
        size="lg"
        className="overflow-hidden bg-[#FAF8F5] sm:max-w-xl"
        bodyClassName="p-0"
      >
        <CheckoutFlow onClose={closeCheckout} />
      </Modal>
    </CheckoutModalContext.Provider>
  );
}
