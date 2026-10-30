import { expect, test } from "@playwright/test";
import { publishPage, seed } from "./helpers.js";

/**
 * What a live funnel page reports: visitor events for the dashboard, campaign
 * attribution on the order, Meta Pixel / Google events mapped to real actions,
 * and the Purchase taken from the order itself. Staff test visits report nothing.
 *
 * The E2E storefront has no Pixel or GTM id, so a recording stand-in for
 * window.fbq is installed before the page loads; the server-side Purchase relay
 * is intercepted to see what the page asks it to send.
 */

let areas;
test.beforeAll(() => {
  seed("cache-clear");
  seed("products");
  areas = seed("checkout-setup").areas;
});

let counter = 0;
function freshProducts() {
  counter += 1;
  const tag = `${Date.now().toString(36)}${counter}`;
  return { a: seed("product", `TRK A ${tag}`, 2000, 30), b: seed("product", `TRK B ${tag}`, 3000, 30), nameA: `TRK A ${tag}`, nameB: `TRK B ${tag}` };
}

function pageFor(products) {
  return {
    schemaVersion: 1,
    page: {
      settings: { language: "en" },
      sections: [
        { id: "hero_001", type: "section", children: [{ id: "heading_001", type: "heading", props: { text: "Tracking page", level: "h1" } }] },
        { id: "spacer_001", type: "section", children: [{ id: "spacer_inner", type: "spacer", style: { desktop: { height: 1200 } } }] },
        {
          id: "offers_001", type: "product_offer_selector", props: { selectionMode: "single" },
          productBindings: [
            { key: "offer_one", items: [{ productId: products.a.id, variant: null, quantity: 1, lockVariant: false }], offer: { type: "none" } },
            { key: "offer_two", items: [{ productId: products.b.id, variant: null, quantity: 1, lockVariant: false }], offer: { type: "none" } },
          ],
        },
        { id: "checkout_001", type: "checkout_form", props: {}, children: [{ id: "summary_001", type: "order_summary", props: {} }, { id: "buy_001", type: "purchase_button", props: { text: "Order now" } }] },
        { id: "thanks_001", type: "order_confirmation", props: { showOrderCode: true } },
      ],
    },
  };
}

function freshPhone() {
  return `0181${String(Math.floor(Math.random() * 10_000_000)).padStart(7, "0")}`;
}

async function recordTags(page) {
  await page.addInitScript(() => {
    window.__fbq = [];
    window.fbq = (...args) => window.__fbq.push(args);
  });
  const relayed = [];
  await page.route("**/api/funnel/purchase", async (route) => {
    relayed.push(route.request().postDataJSON());
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ sent: false }) });
  });
  return relayed;
}

const fbqEvents = (page) => page.evaluate(() => (window.__fbq ?? []).filter((c) => c[0] === "track").map((c) => ({ name: c[1], data: c[2], options: c[3] })));
const dataLayerEvents = (page) => page.evaluate(() => (window.dataLayer ?? []).filter((e) => e && e.event).map((e) => ({ event: e.event, ecommerce: e.ecommerce })));

async function placeOrder(page) {
  await page.getByTestId("input-name").fill("Karim Ahmed");
  await page.getByTestId("input-phone").fill(freshPhone());
  await page.getByTestId("input-address").fill("House 5, Road 7, Dhanmondi, Dhaka");
  await page.getByTestId("input-district").selectOption(String(areas["E2E Inside Dhaka"]));
  // The offer and district choices each ask the server for a new total. Ordering before
  // that answer arrives is correctly refused with "the price changed", so wait until the
  // summary is idle and the total on the button has stopped changing.
  const summary = page.getByTestId("order-summary");
  const button = page.getByTestId("order-button");
  await expect
    .poll(async () => {
      const before = await button.textContent();
      await page.waitForTimeout(800);
      return !(await summary.getAttribute("data-updating")) && before === (await button.textContent());
    }, { timeout: 20_000 })
    .toBe(true);
  await button.click();
  await expect(page.getByTestId("order-confirmation").first()).toBeVisible();
}

test("a visit is counted, attributed to its campaign, and its purchase reported once from the order", async ({ page }) => {
  test.setTimeout(120_000);
  const products = freshProducts();
  const funnel = publishPage("Tracking: campaign", pageFor(products));
  const relayed = await recordTags(page);

  await page.goto(`/funnel/${funnel.slug}?utm_source=facebook&utm_medium=cpc&utm_campaign=eid-e2e&fbclid=IwAR_e2e_click`);
  await expect(page.locator("[data-fn-offer]").first()).toBeAttached();

  // Offers far down the page are not "viewed" until they are on screen.
  await page.waitForTimeout(800);
  expect((await fbqEvents(page)).filter((e) => e.name === "ViewContent")).toHaveLength(0);
  await page.locator("[data-fn-offer]").first().scrollIntoViewIfNeeded();
  await expect.poll(async () => (await fbqEvents(page)).filter((e) => e.name === "ViewContent").length).toBe(2);

  // The first offer is chosen for them: that is not an add to cart. Choosing the other one is.
  await page.locator("[data-fn-offer]").filter({ hasText: products.nameB }).click();
  await expect.poll(async () => (await fbqEvents(page)).filter((e) => e.name === "AddToCart").length).toBe(1);
  const add = (await fbqEvents(page)).find((e) => e.name === "AddToCart");
  expect(add.data.value).toBe(3000);
  expect(add.data.currency).toBe("BDT");
  expect(add.options.eventID).toBeTruthy();

  await placeOrder(page);
  // Checkout started once, however many fields were typed in.
  expect((await fbqEvents(page)).filter((e) => e.name === "InitiateCheckout")).toHaveLength(1);

  // Purchase: from the order, once, with the order's own event id.
  await expect.poll(async () => (await fbqEvents(page)).filter((e) => e.name === "Purchase").length).toBe(1);
  const purchase = (await fbqEvents(page)).find((e) => e.name === "Purchase");
  expect(purchase.options.eventID).toMatch(/^purchase_[a-f0-9]{32}$/);
  expect(purchase.data.value).toBe(3080); // 3,000 + 80 delivery, from the order
  const ga = (await dataLayerEvents(page)).filter((e) => e.event === "purchase");
  expect(ga).toHaveLength(1);
  expect(ga[0].ecommerce.value).toBe(3080);
  // The server copy is asked for with the receipt only: no amount comes from the page.
  await expect.poll(() => relayed.length).toBe(1);
  expect(Object.keys(relayed[0]).sort()).toEqual(["receipt", "slug"]);

  // The order carries the campaign; the address bar never carried the receipt.
  const { rows } = seed("attribution", funnel.id);
  expect(rows).toHaveLength(1);
  expect(rows[0]).toMatchObject({ utm_source: "facebook", utm_medium: "cpc", utm_campaign: "eid-e2e", fbclid: "IwAR_e2e_click", source: "facebook", is_test: 0 });
  expect(page.url()).not.toContain("receipt");

  // Visitor events for the dashboard (sent when the page is left).
  await page.goto("about:blank");
  await expect.poll(() => seed("events", funnel.id).types.product_view ?? 0).toBe(2);
  const events = seed("events", funnel.id);
  expect(events.types).toMatchObject({ page_view: 1, product_view: 2, product_select: 1, checkout_start: 1 });
  expect(events.visitors).toHaveLength(1);
  expect(events.rows[0]).toMatchObject({ source: "facebook", campaign: "eid-e2e", device: "desktop" });
});

test("back from the payment gateway: the receipt leaves the address before any tag sees it, and a reload reports nothing twice", async ({ page }) => {
  const products = freshProducts();
  const funnel = publishPage("Tracking: return", pageFor(products));
  const relayed = await recordTags(page);
  await page.goto(`/funnel/${funnel.slug}`);
  await placeOrder(page);
  await expect.poll(() => relayed.length).toBe(1);

  const { receipt, code } = seed("receipt", funnel.id);
  const second = await page.context().newPage();
  await second.addInitScript(() => {
    window.__fbq = [];
    window.__urls = [location.href];
    window.fbq = (...args) => window.__fbq.push(args);
  });
  await second.goto(`/funnel/${funnel.slug}?receipt=${encodeURIComponent(receipt)}&payment=pending`);
  await expect(second.getByTestId("order-code")).toHaveText(code);
  expect(second.url()).not.toContain("receipt");
  // Payment state unclear: no second payment is offered.
  await expect(second.getByTestId("pay-now")).toHaveCount(0);
  // Reloading shows a fresh page; the same purchase is not reported again from this tab.
  const before = (await second.evaluate(() => window.__fbq.filter((c) => c[1] === "Purchase").length));
  await second.reload();
  await expect(second.locator("[data-fn-offer]").first()).toBeVisible();
  await expect(second.getByTestId("order-confirmation")).toHaveCount(0);
  expect(await second.evaluate(() => window.__fbq.filter((c) => c[1] === "Purchase").length)).toBeLessThanOrEqual(before);
  await second.close();
});

test("a staff test visit places a real order marked as a test and reports nothing", async ({ page }) => {
  const products = freshProducts();
  const funnel = publishPage("Tracking: test order", pageFor(products));
  const relayed = await recordTags(page);
  const { token } = seed("test-token", funnel.id);

  await page.goto(`/funnel/${funnel.slug}?fn_test=${token}`);
  expect(page.url()).not.toContain("fn_test");
  await page.locator("[data-fn-offer]").filter({ hasText: products.nameB }).click();
  await placeOrder(page);
  await page.waitForTimeout(1000);

  expect(await fbqEvents(page)).toEqual([]);
  expect(relayed).toHaveLength(0);
  const { orders } = seed("orders", funnel.id);
  expect(orders).toHaveLength(1);
  expect(orders[0].additional_info).toContain("TEST ORDER");
  expect(seed("attribution", funnel.id).rows[0].is_test).toBe(1);
  await page.goto("about:blank");
  await page.waitForTimeout(500);
  expect(seed("events", funnel.id).count).toBe(0);
});

test("without tracking consent, where consent is refused, no Meta or Google event is sent", async ({ page, context }) => {
  const products = freshProducts();
  const funnel = publishPage("Tracking: no consent", pageFor(products));
  await context.addCookies([{ name: "tracking_consent", value: "denied", url: "http://localhost:3100" }]);
  const relayed = await recordTags(page);

  await page.goto(`/funnel/${funnel.slug}?utm_source=google&gclid=Cj0K_e2e`);
  await page.locator("[data-fn-offer]").filter({ hasText: products.nameB }).click();
  await placeOrder(page);
  await page.waitForTimeout(1000);

  expect(await fbqEvents(page)).toEqual([]);
  expect((await dataLayerEvents(page)).filter((e) => ["view_item", "add_to_cart", "begin_checkout", "purchase"].includes(e.event))).toEqual([]);
  expect(relayed).toHaveLength(0);
  // The order still records its campaign, but not the ad click id.
  const [row] = seed("attribution", funnel.id).rows;
  expect(row.utm_source).toBe("google");
  expect(row.gclid).toBeNull();
  expect(row.consent).toBe("denied");
});
