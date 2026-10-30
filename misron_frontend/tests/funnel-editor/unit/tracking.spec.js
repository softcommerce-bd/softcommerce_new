import { expect, test } from "@playwright/test";

/**
 * The visit bookkeeping and consent rules of the funnel tracking, run against a
 * small stand-in for the browser (storage, cookie, address, referrer).
 */

function memoryStorage() {
  const data = new Map();
  return {
    getItem: (key) => (data.has(key) ? data.get(key) : null),
    setItem: (key, value) => data.set(key, String(value)),
    removeItem: (key) => data.delete(key),
    data,
  };
}

function fakeBrowser({ url = "https://shop.test/funnel/eid-offer", referrer = "", cookie = "" } = {}) {
  const location = new URL(url);
  const history = {
    state: null,
    replaceState(state, title, next) {
      const target = new URL(next, location.href);
      location.href = target.href;
    },
  };
  const win = {
    localStorage: memoryStorage(),
    sessionStorage: memoryStorage(),
    location: {
      get href() { return location.href; },
      get pathname() { return location.pathname; },
      get search() { return location.search; },
      get host() { return location.host; },
    },
    history,
  };
  globalThis.window = win;
  globalThis.document = { cookie, referrer };
  globalThis.location = win.location;
  globalThis.history = history;
  globalThis.sessionStorage = win.sessionStorage;
  return { win, location };
}

const load = async () => {
  const stamp = `?t=${Math.random()}`;
  return {
    visit: await import(`../../../funnel/tracking/visit.js${stamp}`),
    consent: await import(`../../../lib/consent.js${stamp}`),
  };
};

test.afterEach(() => {
  delete process.env.NEXT_PUBLIC_TRACKING_CONSENT;
});

test("the stash script takes the receipt, payment state and test token out of the address before tags read it", async () => {
  const { win, location } = fakeBrowser({ url: "https://shop.test/funnel/eid-offer?utm_source=facebook&receipt=SECRET&payment=success&fn_test=123.abc&fbclid=IwAR1" });
  const { visit } = await load();
  new Function(visit.STASH_SCRIPT)();

  expect(location.search).toBe("?utm_source=facebook&fbclid=IwAR1");
  expect(win.sessionStorage.getItem("fn:receipt:eid-offer")).toBe("SECRET");
  // Read once, then gone.
  expect(visit.takeOnce("eid-offer", "receipt")).toBe("SECRET");
  expect(visit.takeOnce("eid-offer", "payment")).toBe("success");
  expect(visit.takeOnce("eid-offer", "receipt")).toBeNull();
  // The test token stays for the whole visit in this tab.
  expect(visit.testToken("eid-offer")).toBe("123.abc");
  expect(visit.isTestVisit("eid-offer")).toBe(true);
  expect(visit.isTestVisit("other-page")).toBe(false);
});

test("arrival is recorded with campaign, click id and referring host, and a plain reload keeps it", async () => {
  fakeBrowser({ url: "https://shop.test/funnel/eid-offer?utm_source=facebook&utm_campaign=eid&fbclid=IwAR1", referrer: "https://l.facebook.com/x?y=1" });
  const { visit } = await load();
  visit.captureArrival("eid-offer");
  let a = visit.attribution("eid-offer");
  expect(a).toMatchObject({ utm_source: "facebook", utm_campaign: "eid", fbclid: "IwAR1", referrer_host: "l.facebook.com", consent: "granted" });
  expect(a.visitor_id).toMatch(/^[A-Za-z0-9-]{8,}$/);
  expect(a.session_id).not.toBe(a.visitor_id);
  // The visitor id stays the same for the next page view.
  expect(visit.attribution("eid-offer").visitor_id).toBe(a.visitor_id);

  // Reload without parameters: the campaign is kept.
  globalThis.window.location = { ...globalThis.window.location, search: "", host: "shop.test" };
  visit.captureArrival("eid-offer");
  a = visit.attribution("eid-offer");
  expect(a.utm_campaign).toBe("eid");
});

test("refused consent drops ad click ids and keeps the visitor id to the session", async () => {
  fakeBrowser({ url: "https://shop.test/funnel/eid-offer?gclid=Cj0K&utm_source=google", cookie: "tracking_consent=denied" });
  const { visit, consent } = await load();
  expect(consent.marketingAllowed()).toBe(false);
  visit.captureArrival("eid-offer");
  const a = visit.attribution("eid-offer");
  expect(a.gclid).toBeNull();
  expect(a.utm_source).toBe("google");
  expect(a.consent).toBe("denied");
  expect(window.localStorage.getItem("fn_vid")).toBeNull();
  expect(window.sessionStorage.getItem("fn_vid")).toBe(a.visitor_id);
});

test("where consent is required, nothing runs until the visitor agrees", async () => {
  process.env.NEXT_PUBLIC_TRACKING_CONSENT = "required";
  fakeBrowser();
  let { consent } = await load();
  expect(consent.trackingConsent()).toBe("unknown");
  expect(consent.marketingAllowed()).toBe(false);
  expect(consent.consentBlockedExpression()).toContain("tracking_consent=granted");

  fakeBrowser({ cookie: "a=1; tracking_consent=granted" });
  ({ consent } = await load());
  expect(consent.marketingAllowed()).toBe(true);
});

test("offer contents use the catalogue (stock) id the store's feed publishes", async () => {
  fakeBrowser();
  const { offerContents } = await import(`../../../funnel/tracking/marketing.js?t=${Math.random()}`);
  const offer = { items: [{ product_id: 7, stock_id: 31, name: "Blender", variant: "Red", quantity: 2, current: 1500 }, { product_id: 8, name: "Cup", quantity: 1, current: 100 }] };
  expect(offerContents(offer, 3)).toEqual([
    { id: "31", product_id: 7, name: "Blender", variant: "Red", quantity: 6, price: 1500 },
    { id: "8", product_id: 8, name: "Cup", variant: "", quantity: 3, price: 100 },
  ]);
  expect(offerContents(undefined)).toEqual([]);
});
