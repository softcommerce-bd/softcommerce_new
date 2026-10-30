import { resolveFields } from "./checkoutRules.js";
import { attribution, takeOnce, testToken } from "../tracking/visit.js";
import { sectionsOf, walk } from "../core/tree.js";

/**
 * Where the order form gets its data and sends the order. Both adapters answer
 * the same calls, each returning { status, body } and never throwing for an
 * HTTP error, so the form can tell a refusal from a dropped connection
 * (status 0).
 *
 *   config()               what the form asks for, delivery areas, payment methods
 *   place(body, context)   send the order
 *   order(receipt)         the summary of a placed order
 *   pay(receipt)           the payment link of an unpaid online order
 *   sendOtp / verifyOtp    phone verification, when the store requires it
 *
 * The public adapter talks to the store. The preview adapter, used by the
 * editor's Preview, answers from the page being edited and places NO order.
 */

async function call(method, url, body) {
  let response;
  try {
    response = await fetch(url, {
      method,
      headers: { Accept: "application/json", ...(body === undefined ? {} : { "Content-Type": "application/json" }) },
      body: body === undefined ? undefined : JSON.stringify(body),
      cache: "no-store",
      credentials: "omit",
    });
  } catch {
    return { status: 0, body: null, retryAfter: null };
  }
  const data = await response.json().catch(() => null);
  return { status: response.status, body: data, retryAfter: Number(response.headers.get("Retry-After")) || null };
}

export function publicCheckout(slug) {
  const base = `/api/v2/funnels/${encodeURIComponent(slug)}`;
  return {
    mode: "public",
    config: () => call("GET", `${base}/checkout/config`),
    // Where the visit came from, and the staff test token when there is one. Never an amount.
    place: (body) => call("POST", `${base}/checkout`, { ...body, attribution: attribution(slug), test_token: testToken(slug) }),
    // An order receipt the payment gateway sent back with the visitor, if any (read once).
    takeReturn: () => {
      const receipt = takeOnce(slug, "receipt");
      const payment = takeOnce(slug, "payment");
      return receipt ? { receipt, payment } : null;
    },
    order: (receipt) => call("GET", `${base}/orders/${encodeURIComponent(receipt)}`),
    pay: (receipt) => call("POST", `${base}/orders/${encodeURIComponent(receipt)}/pay`),
    sendOtp: (phone) => call("POST", "/api/v2/checkout/send-otp", { phone }),
    verifyOtp: (phone, otp) => call("POST", "/api/v2/checkout/verify-otp", { phone, otp }),
  };
}

/** The checkout settings of a page document, in the shape the server's config endpoint uses. */
export function configFromDocument(document) {
  let form = null;
  let selector = null;
  walk(sectionsOf(document), (node) => {
    if (node.type === "checkout_form" && !form) form = node;
    if (node.type === "payment_method_selector" && !selector) selector = node;
  });

  const props = form?.props ?? {};
  const allowed = selector?.props?.allowed ?? [];
  const methods = [
    { key: "cash_on_delivery", label: "Cash on delivery", online: false },
    { key: "paystation", label: "Pay online", online: true },
  ].filter((method) => allowed.length === 0 || allowed.includes(method.key));

  return {
    result: true,
    enabled: Boolean(form),
    reason: form ? null : "no_checkout",
    checkout: {
      fields: resolveFields(props.fields),
      require_consent: Boolean(props.requireConsent),
      consent_text: props.consentText ?? "",
      language: document?.page?.settings?.language === "en" ? "en" : "bn",
    },
    payment_methods: methods,
    otp_required: false,
  };
}

async function loadDistricts() {
  try {
    const response = await fetch("/api/v2/cities", { headers: { Accept: "application/json" }, cache: "no-store" });
    const data = await response.json();
    return (data?.data ?? []).map((city) => ({
      id: city.id,
      name: city.name,
      charge: { amount: Number(city.cost) || 0, text: `৳${(Number(city.cost) || 0).toLocaleString("en-US")}` },
    }));
  } catch {
    return [];
  }
}

/**
 * Preview: the form behaves as on the live page, but "placing" an order only
 * shows what the confirmation would look like.
 *
 * The page being edited is handed to config() by the form's provider.
 */
export function previewCheckout() {
  let districts = null;
  return {
    mode: "preview",
    config: async ({ document } = {}) => {
      const config = configFromDocument(document);
      // The delivery areas are the store's own list, the same one the website offers.
      districts ??= await loadDistricts();
      return { status: 200, body: { ...config, districts } };
    },
    place: async (body, context) => {
      await new Promise((resolve) => setTimeout(resolve, 400));
      const quote = context?.quote;
      const money = (amount) => ({ amount, text: `৳${Number(amount).toLocaleString("en-US")}` });
      const products = Number(quote?.totals?.product_subtotal?.amount ?? 0);
      const tax = Number(quote?.totals?.tax?.amount ?? 0);
      const delivery = Number(quote?.totals?.delivery?.amount?.amount ?? 0);
      return {
        status: 201,
        body: {
          result: true,
          replayed: false,
          preview: true,
          order: {
            code: "PREVIEW",
            receipt: null,
            status: "pending",
            payment: { method: body.payment_type, status: "unpaid", action: "none" },
            items: (quote?.lines ?? []).flatMap((line) => line.items.map((item) => ({ name: item.name, variant: item.variant || null, quantity: item.pieces, total: item.total }))),
            totals: { products: money(products), tax: money(tax), delivery: money(delivery), total: money(products + tax + delivery) },
          },
        },
      };
    },
    order: async () => ({ status: 404, body: null }),
    pay: async () => ({ status: 409, body: { code: "not_online" } }),
    sendOtp: async () => ({ status: 200, body: { result: true } }),
    verifyOtp: async () => ({ status: 200, body: { result: true } }),
  };
}
