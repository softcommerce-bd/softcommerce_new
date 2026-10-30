/**
 * Meta Pixel / Conversions API and Google (GTM data layer) events of a funnel
 * page. Uses the store's existing tags: the Pixel and GTM loaded by the root
 * layout, lib/pixel/meta (browser Pixel + the /api/meta/conversion relay with a
 * shared event id) and lib/gtm. Nothing here loads a second copy of any tag.
 *
 * Nothing is sent when tracking consent does not allow it, on a staff test
 * visit, or outside the live page (the editor and previews never mount this).
 *
 * Purchase is different from the other events: what counts as a purchase, its
 * value and its event id come from the store's order record, never from the
 * page (see trackPurchase).
 */
import { marketingAllowed } from "../../lib/consent.js";
import { track } from "../../lib/pixel/meta.js";
import { pushToDataLayer } from "../../lib/gtm.js";

const CURRENCY = "BDT";

/** Catalogue ids and values of the offers a customer looked at or chose. */
export function offerContents(offer, quantity = 1) {
  const items = offer?.items ?? [];
  return items.map((item) => ({
    id: String(item.stock_id ?? item.product_id ?? ""),
    product_id: item.product_id,
    name: item.name ?? "",
    variant: item.variant ?? "",
    quantity: (Number(item.quantity) || 1) * (Number(quantity) || 1),
    price: Number(item.current) || 0,
  }));
}

function unitValue(offer, quantity = 1) {
  const amount = Number(offer?.price?.offer?.amount);
  return Number.isFinite(amount) ? amount * (Number(quantity) || 1) : 0;
}

function gaItems(contents, funnel) {
  return contents.map((item, index) => ({
    item_id: item.id,
    item_name: item.name,
    item_variant: item.variant || undefined,
    price: item.price,
    quantity: item.quantity,
    index,
    item_list_name: `Funnel: ${funnel}`,
  }));
}

function ga(event, ecommerce) {
  // GA4 recommends clearing the previous ecommerce object before a new one.
  pushToDataLayer({ ecommerce: null });
  pushToDataLayer({ event, ecommerce });
}

export function createMarketing({ slug, test }) {
  const active = () => !test && marketingAllowed();

  return {
    viewContent(offer) {
      if (!active() || !offer) return;
      const contents = offerContents(offer);
      const value = unitValue(offer);
      track("ViewContent", { content_ids: contents.map((c) => c.id), content_type: "product", content_name: offer.title ?? contents[0]?.name, content_category: `funnel:${slug}`, value });
      ga("view_item", { currency: CURRENCY, value, items: gaItems(contents, slug) });
    },

    addToCart(offer, quantity = 1) {
      if (!active() || !offer) return;
      const contents = offerContents(offer, quantity);
      const value = unitValue(offer, quantity);
      track("AddToCart", {
        content_ids: contents.map((c) => c.id), content_type: "product", content_name: offer.title ?? contents[0]?.name,
        contents: contents.map((c) => ({ id: c.id, quantity: c.quantity })), value,
      });
      ga("add_to_cart", { currency: CURRENCY, value, items: gaItems(contents, slug) });
    },

    initiateCheckout(contents, value) {
      if (!active()) return;
      track("InitiateCheckout", {
        content_ids: contents.map((c) => c.id), content_type: "product",
        contents: contents.map((c) => ({ id: c.id, quantity: c.quantity })),
        num_items: contents.reduce((sum, c) => sum + c.quantity, 0), value: Number(value) || 0,
      });
      ga("begin_checkout", { currency: CURRENCY, value: Number(value) || 0, items: gaItems(contents, slug) });
    },

    /**
     * A placed order, reported once. `tracking` is the block the store attaches to
     * the order summary: whether it may be reported, its value, its contents and a
     * stable event id. The server copy (Conversions API) is sent by this app's own
     * server after it reads the same order from the store, not from these values.
     */
    purchase(order) {
      const tracking = order?.tracking;
      if (!tracking?.eligible || !active() || !order.receipt) return false;
      const flag = `fn:purchase:${tracking.event_id}`;
      try {
        if (window.sessionStorage.getItem(flag)) return false;
        window.sessionStorage.setItem(flag, "1");
      } catch {
        /* without storage the shared event id still lets Meta drop a repeat */
      }

      const contents = tracking.contents ?? [];
      const data = {
        content_ids: contents.map((c) => c.id), content_type: "product",
        contents: contents.map((c) => ({ id: c.id, quantity: c.quantity, item_price: c.item_price })),
        num_items: contents.reduce((sum, c) => sum + (Number(c.quantity) || 0), 0),
        value: tracking.value, currency: tracking.currency, order_id: tracking.transaction_id,
      };
      if (typeof window.fbq === "function") window.fbq("track", "Purchase", data, { eventID: tracking.event_id });
      fetch("/api/funnel/purchase", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ slug, receipt: order.receipt }),
        keepalive: true,
      }).catch(() => {});

      ga("purchase", {
        transaction_id: tracking.transaction_id,
        currency: tracking.currency,
        value: tracking.value,
        shipping: Number(order.totals?.delivery?.amount) || 0,
        tax: Number(order.totals?.tax?.amount) || 0,
        items: contents.map((c, index) => ({ item_id: c.id, item_name: c.name ?? "", price: c.item_price, quantity: c.quantity, index })),
      });
      return true;
    },
  };
}
