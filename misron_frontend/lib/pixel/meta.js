/**
 * Meta Pixel event helpers.
 *
 * Every product event carries `content_ids`, and the values must be the same
 * ids the catalogue feed publishes as `<g:id>` — the stock SKU. Meta matches a
 * tracked event to a catalogue item by that id alone, so sending the product id
 * here while the feed publishes SKUs would leave dynamic ads with nothing to
 * retarget, silently and with no error anywhere.
 *
 * Each event is also sent from the server through the Conversions API. Both
 * copies share one `event_id` so Meta keeps them as a single event rather than
 * counting the conversion twice.
 */

/** Currency for every event. The catalogue feed publishes prices in BDT. */
const CURRENCY = "BDT";

/** Browser-unique id shared with the server copy of the same event. */
export function newEventId() {
  if (typeof crypto !== "undefined" && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

/**
 * Catalogue id for a selection.
 *
 * This is the product_stocks row id, which is what the feed publishes as an
 * item's id. The SKU column looks like the natural choice but is unusable: it
 * is null on most rows, and where it is set it holds a bare size ("XL") shared
 * by unrelated garments, so it identifies no single catalogue item.
 *
 * Falls back to the product id only when no stock row is known, which happens
 * before a shopper has chosen a size.
 */
export function contentId(product, variantStock) {
  if (variantStock?.stock_id) return String(variantStock.stock_id);
  return String(product?.id ?? "");
}

/** True once the Pixel script has loaded. */
function ready() {
  return typeof window !== "undefined" && typeof window.fbq === "function";
}

/**
 * Send one event to the Pixel and, in parallel, to our own server so it can
 * relay it to the Conversions API. A failed relay is swallowed: tracking must
 * never break a purchase.
 */
export function track(eventName, payload, { eventId = newEventId() } = {}) {
  const data = { ...payload, currency: payload.currency || CURRENCY };

  if (ready()) {
    window.fbq("track", eventName, data, { eventID: eventId });
  }

  if (typeof window !== "undefined") {
    const body = JSON.stringify({
      event_name: eventName,
      event_id: eventId,
      event_source_url: window.location.href,
      custom_data: data,
    });

    // `keepalive` lets the request outlive the page, which matters for
    // Purchase: the shopper is usually redirected the moment it fires.
    fetch("/api/meta/conversion", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body,
      keepalive: true,
    }).catch(() => {});
  }

  return eventId;
}

/** Product detail page view. */
export function trackViewContent({ id, name, price, category }) {
  return track("ViewContent", {
    content_ids: [String(id)],
    content_type: "product",
    content_name: name,
    content_category: category,
    value: Number(price) || 0,
  });
}

/** Add to cart, fired with the size the shopper actually chose. */
export function trackAddToCart({ id, name, price, quantity = 1 }) {
  return track("AddToCart", {
    content_ids: [String(id)],
    content_type: "product",
    content_name: name,
    contents: [{ id: String(id), quantity: Number(quantity) || 1 }],
    value: (Number(price) || 0) * (Number(quantity) || 1),
  });
}

/** Checkout started. `items` are {id, quantity, price}. */
export function trackInitiateCheckout(items = [], value = 0) {
  return track("InitiateCheckout", {
    content_ids: items.map((i) => String(i.id)),
    content_type: "product",
    contents: items.map((i) => ({
      id: String(i.id),
      quantity: Number(i.quantity) || 1,
    })),
    num_items: items.reduce((sum, i) => sum + (Number(i.quantity) || 1), 0),
    value: Number(value) || 0,
  });
}

/** Completed order. */
export function trackPurchase({ items = [], value = 0, orderId }) {
  return track("Purchase", {
    content_ids: items.map((i) => String(i.id)),
    content_type: "product",
    contents: items.map((i) => ({
      id: String(i.id),
      quantity: Number(i.quantity) || 1,
      item_price: Number(i.price) || 0,
    })),
    num_items: items.reduce((sum, i) => sum + (Number(i.quantity) || 1), 0),
    value: Number(value) || 0,
    order_id: orderId ? String(orderId) : undefined,
  });
}
