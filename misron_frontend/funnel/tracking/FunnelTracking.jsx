"use client";

import { useEffect, useMemo, useRef } from "react";
import { useCommerce } from "../commerce/CommerceProvider.jsx";
import { useCheckout } from "../commerce/CheckoutProvider.jsx";
import { createEventQueue } from "./events.js";
import { createMarketing, offerContents } from "./marketing.js";
import { captureArrival, isTestVisit } from "./visit.js";

/**
 * Watches the live page and reports what the visitor does, mapped to real
 * actions only:
 *
 *   page opened                         → page_view (dashboard); PageView is the Pixel's own
 *   an offer card is half on screen     → product_view + ViewContent / view_item, once per offer
 *   the visitor picks an offer          → product_select + AddToCart / add_to_cart
 *   first touch of the order form       → checkout_start + InitiateCheckout / begin_checkout, once
 *   the store confirms an order counts  → Purchase / purchase, once per order (see marketing.js)
 *
 * Mounted only on the live page (never in the editor or a preview). A staff test
 * visit reports nothing at all.
 */
export default function FunnelTracking({ slug }) {
  const commerce = useCommerce();
  const checkout = useCheckout();
  const test = useMemo(() => (typeof window === "undefined" ? false : isTestVisit(slug)), [slug]);
  const queue = useMemo(() => (typeof window === "undefined" ? null : createEventQueue(slug, { enabled: !test })), [slug, test]);
  const marketing = useMemo(() => createMarketing({ slug, test }), [slug, test]);
  const offersRef = useRef({});
  const commerceRef = useRef(commerce);
  useEffect(() => {
    offersRef.current = commerce?.offers ?? {};
    commerceRef.current = commerce;
  });

  // The visit and the page view.
  useEffect(() => {
    if (!queue) return undefined;
    captureArrival(slug);
    queue.record("page_view");
    const leave = () => queue.flush(true);
    const hidden = () => {
      if (document.visibilityState === "hidden") queue.flush(true);
    };
    window.addEventListener("pagehide", leave);
    document.addEventListener("visibilitychange", hidden);
    return () => {
      window.removeEventListener("pagehide", leave);
      document.removeEventListener("visibilitychange", hidden);
      queue.flush(true);
    };
  }, [slug, queue]);

  // Offer cards seen on screen.
  const ready = Boolean(commerce?.ready);
  useEffect(() => {
    if (!queue || !ready || typeof IntersectionObserver === "undefined") return undefined;
    const seen = new Set();
    const observer = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        const key = entry.target.getAttribute("data-fn-offer");
        if (!entry.isIntersecting || !key || seen.has(key)) continue;
        seen.add(key);
        observer.unobserve(entry.target);
        queue.record("product_view", { offer_key: key });
        marketing.viewContent(offersRef.current[key]);
      }
    }, { threshold: 0.5 });
    document.querySelectorAll("[data-fn-offer]").forEach((element) => observer.observe(element));
    return () => observer.disconnect();
  }, [queue, ready, marketing]);

  // Offers the visitor picks (not the ones the page starts with).
  const previous = useRef(null);
  const selectedKeys = (commerce?.selections ?? []).map((s) => s.key).join("|");
  useEffect(() => {
    const keys = selectedKeys ? selectedKeys.split("|") : [];
    if (previous.current === null || !commerce?.ready) {
      if (commerce?.ready) previous.current = keys;
      return;
    }
    const added = keys.filter((key) => !previous.current.includes(key));
    previous.current = keys;
    for (const key of added) {
      queue?.record("product_select", { offer_key: key });
      const quantity = commerceRef.current?.selections?.find((s) => s.key === key)?.quantity ?? 1;
      marketing.addToCart(offersRef.current[key], quantity);
    }
  }, [selectedKeys, commerce?.ready, queue, marketing]);

  // First touch of the order form.
  useEffect(() => {
    if (!queue) return undefined;
    let started = false;
    const onFocus = (event) => {
      if (started || !(event.target instanceof Element) || !event.target.closest("[data-fn-checkout]")) return;
      started = true;
      queue.record("checkout_start");
      const live = commerceRef.current;
      const contents = (live?.selections ?? []).flatMap((s) => offerContents(live.offers?.[s.key], s.quantity));
      const value = Number(live?.quote?.totals?.payable?.amount ?? live?.quote?.totals?.total_before_delivery?.amount ?? 0);
      marketing.initiateCheckout(contents, value);
    };
    document.addEventListener("focusin", onFocus);
    return () => document.removeEventListener("focusin", onFocus);
  }, [queue, marketing]);

  // A placed order the store says counts as a purchase.
  const order = checkout?.order;
  const preview = checkout?.preview;
  useEffect(() => {
    if (order && !preview) marketing.purchase(order);
  }, [order, preview, marketing]);

  return null;
}
