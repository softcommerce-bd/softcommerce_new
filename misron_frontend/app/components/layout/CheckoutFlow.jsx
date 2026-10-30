"use client";

/**
 * Picks the checkout flow — signed-in or guest — for the current shopper.
 *
 * Shared by the /checkout-page route and the checkout popup. Both render the
 * same centred checkout card; the popup passes `onClose` so the card's close
 * button dismisses the dialog instead of leading back to the cart.
 *
 * Nothing renders until auth has been read from localStorage. Mounting the
 * guest flow first and swapping it for the signed-in one a frame later would
 * run the guest flow's effects (draft load, begin-checkout event) for shoppers
 * who are signed in. The server snapshot is null, so the page route hydrates
 * without a mismatch and then picks its flow on the client.
 */

import { useSyncExternalStore } from "react";

import AuthUserCheckout from "@/app/components/layout/AuthUserCheckout";
import GuestUserCheckout from "@/app/components/layout/GuestUserCheckout";

// Auth is read once per mount; nothing here needs to react to storage changes.
const subscribe = () => () => {};

const readFlow = () => {
  try {
    const token = localStorage.getItem("token");
    const user = localStorage.getItem("user");
    return token && user && user !== "undefined" ? "auth" : "guest";
  } catch {
    return "guest";
  }
};

const readServerFlow = () => null;

export default function CheckoutFlow({ onClose }) {
  const flow = useSyncExternalStore(subscribe, readFlow, readServerFlow);

  if (flow === null) return null;

  return flow === "auth" ? (
    <AuthUserCheckout onClose={onClose} />
  ) : (
    <GuestUserCheckout onClose={onClose} />
  );
}
