import { expect, test } from "@playwright/test";
import { draftPage, drag, openEditor, publishNow, publishPage, seed, waitForSaved } from "./helpers.js";

const SHOTS = "test-results/funnel-editor-screens";
const money = (amount) => new RegExp(`৳\\s?${amount.toLocaleString("en-US")}(?![\\d,])`);

let areas;
test.beforeAll(() => {
  seed("cache-clear");
  seed("products");
  areas = seed("checkout-setup").areas;
});

let counter = 0;
/**
 * Two fresh products for one test, so tests never disturb each other's stock or prices:
 * A costs 3,500 and B 5,000 with a 10% website discount (4,500).
 */
function freshProducts() {
  counter += 1;
  const tag = `${Date.now().toString(36)}${counter}`;
  const a = seed("product", `CKT Product A ${tag}`, 3500, 25);
  const b = seed("product", `CKT Product B ${tag}`, 5000, 25, 10);
  return { a: a.id, b: b.id, nameA: `CKT Product A ${tag}`, nameB: `CKT Product B ${tag}` };
}

const BUTTON = { id: "buy_001", type: "purchase_button", props: { text: "অর্ডার কনফার্ম করুন", showTotal: true } };

/** A one-page sales funnel: two blenders, an order form and a thank-you panel. */
function pageDocument(products, { form = {}, selector = {}, offers } = {}) {
  const [one, three] = [products.a, products.b];
  return {
    schemaVersion: 1,
    page: {
      settings: { language: "bn" },
      sections: [
        { id: "hero_001", type: "section", children: [{ id: "heading_001", type: "heading", props: { text: "ব্লেন্ডার মেগা অফার", level: "h1" } }] },
        {
          id: "offers_001", type: "product_offer_selector", props: { selectionMode: "single", heading: "পণ্য বাছাই করুন", ...selector },
          productBindings: offers ?? [
            { key: "offer_one", items: [{ productId: one, variant: null, quantity: 1, lockVariant: false }], offer: { type: "percent", value: 10 } },
            { key: "offer_two", items: [{ productId: three, variant: null, quantity: 1, lockVariant: false }], offer: { type: "none" } },
          ],
        },
        {
          id: "checkout_001", type: "checkout_form", props: { title: "অর্ডার করতে আপনার তথ্য দিন", ...form },
          children: [
            { id: "summary_001", type: "order_summary", props: { title: "অর্ডারের সারাংশ", showImages: true } },
            { id: "pay_001", type: "payment_method_selector", props: { label: "পেমেন্ট পদ্ধতি", allowed: [] } },
            BUTTON,
          ],
        },
        { id: "thanks_001", type: "order_confirmation", props: { showOrderCode: true, showSummary: true } },
      ],
    },
  };
}

/** A different valid mobile number every time: the store limits how often one number can order. */
function freshPhone() {
  return `0171${String(Math.floor(Math.random() * 10_000_000)).padStart(7, "0")}`;
}

async function fillCustomer(page, overrides = {}) {
  const values = { name: "রহিম উদ্দিন", phone: freshPhone(), address: "বাসা ৫, রোড ৭, ধানমন্ডি, ঢাকা", district: String(areas["E2E Inside Dhaka"]), ...overrides };
  await page.getByTestId("input-name").fill(values.name);
  await page.getByTestId("input-phone").fill(values.phone);
  await page.getByTestId("input-address").fill(values.address);
  if (values.district) await page.getByTestId("input-district").selectOption(values.district);
}

async function ready(page) {
  await expect(page.getByTestId("checkout-form")).toBeVisible();
  await expect(page.locator("[data-fn-offer]").first()).toBeVisible();
  await expect(page.getByTestId("order-summary")).not.toHaveAttribute("data-updating", /.+/);
}

test("a customer orders from the live page: prices, delivery, stock and the order itself", async ({ page }) => {
  test.setTimeout(120_000);
  const products = freshProducts();
  const funnel = publishPage("Checkout: live order", pageDocument(products));
  const before = seed("stock", products.a);

  await page.goto(`/funnel/${funnel.slug}`);
  await ready(page);
  await expect(page.getByTestId("preview-banner")).toHaveCount(0);

  // The first offer is chosen for them: 3,500 less 10% = 3,150 each; delivery waits for an area.
  const summary = page.getByTestId("order-summary");
  await expect(summary).toContainText(products.nameA);
  await expect(summary).toContainText(money(3150));
  await expect(summary).toContainText("এলাকা বাছাই করুন"); // a hint instead of a delivery amount

  // Choosing the area adds the delivery charge from the store's own settings.
  await page.getByTestId("input-district").selectOption(String(areas["E2E Outside Dhaka"]));
  await expect(summary).toContainText(money(3290)); // 3,150 + 140
  await page.getByTestId("input-district").selectOption(String(areas["E2E Inside Dhaka"]));
  await expect(summary).toContainText(money(3230)); // 3,150 + 80
  await expect(page.getByTestId("button-total")).toContainText(money(3230));

  await fillCustomer(page, { district: "", phone: "০১৭১২৩৪৫৬৭৮" });
  await page.getByTestId("input-notes").fill("কল দিয়ে আসবেন");
  await page.screenshot({ path: `${SHOTS}/checkout-desktop-filled.png`, fullPage: true });

  await page.getByTestId("order-button").click();
  const confirmation = page.getByTestId("order-confirmation").first();
  await expect(confirmation).toBeVisible();
  await expect(confirmation.getByTestId("order-code")).toHaveText(/^\d{6}$/);
  await expect(confirmation).toContainText("ধন্যবাদ");
  await expect(confirmation).toContainText(money(3230));
  await expect(confirmation.getByTestId("payment-state")).toContainText("ক্যাশ অন ডেলিভারি");
  await expect(page.getByTestId("order-button")).toHaveCount(0); // the form gives way to the confirmation
  await page.screenshot({ path: `${SHOTS}/checkout-desktop-confirmed.png`, fullPage: true });

  // The order is an ordinary order of the store, attributed to the funnel, with the right numbers.
  const { count, orders } = seed("orders", funnel.id);
  expect(count).toBe(1);
  const [order] = orders;
  expect(order.payment_type).toBe("cash_on_delivery");
  expect(order.payment_status).toBe("unpaid");
  expect(order.delivery_status).toBe("pending");
  expect(order.grand_total).toBe(3230);
  expect(order.funnel_version_id).toBeGreaterThan(0);
  expect(order.code).toBe(await confirmation.getByTestId("order-code").innerText());
  expect(order.shipping_address).toMatchObject({ name: "রহিম উদ্দিন", phone: "01712345678", city: "E2E Inside Dhaka", order_note: "কল দিয়ে আসবেন" });
  expect(order.lines).toHaveLength(1);
  expect(order.lines[0]).toMatchObject({ product_id: products.a, quantity: 1, price: 3150, shipping_cost: 80 });
  expect(order.lines[0].fifo_batch_info).toContain('"qty":1');

  // One unit left the shelf and the purchase batch together.
  const after = seed("stock", products.a);
  expect(Object.values(after.stock)[0]).toBe(Object.values(before.stock)[0] - 1);
  expect(after.batches).toBe(before.batches - 1);
});

test("mistakes are explained in Bengali next to the field, and nothing is sent", async ({ page }) => {
  const funnel = publishPage("Checkout: validation", pageDocument(freshProducts()));
  const before = seed("orders", funnel.id).count;
  await page.goto(`/funnel/${funnel.slug}`);
  await ready(page);

  await page.getByTestId("order-button").click();
  await expect(page.getByTestId("error-name")).toHaveText("আপনার নাম লিখুন");
  await expect(page.getByTestId("error-phone")).toContainText("সঠিক মোবাইল নম্বর");
  await expect(page.getByTestId("error-address")).toHaveText("সম্পূর্ণ ঠিকানা লিখুন");
  await expect(page.getByTestId("error-district")).toHaveText("ডেলিভারি এলাকা বাছাই করুন");
  // The first problem is brought into view and the field is flagged for screen readers.
  await expect(page.getByTestId("input-name")).toHaveAttribute("aria-invalid", "true");

  await page.getByTestId("input-phone").fill("12345");
  await page.getByTestId("input-name").fill("রহিম");
  await expect(page.getByTestId("error-name")).toHaveCount(0); // fixing a field clears its message
  await page.getByTestId("order-button").click();
  await expect(page.getByTestId("error-phone")).toContainText("১১ ডিজিটের");

  expect(seed("orders", funnel.id).count).toBe(before);
});

test("pressing the button twice, or retrying after a lost connection, never orders twice", async ({ page }) => {
  test.setTimeout(120_000);
  const products = freshProducts();
  const funnel = publishPage("Checkout: repeats", pageDocument(products));
  const before = seed("stock", products.a);
  await page.goto(`/funnel/${funnel.slug}`);
  await ready(page);
  await fillCustomer(page);
  await expect(page.getByTestId("order-summary")).toContainText(money(3230));

  // The reply to the first attempt is lost on the way back, as on a flaky mobile connection.
  let lost = false;
  const submissions = [];
  await page.route("**/api/v2/funnels/*/checkout", async (route) => {
    if (route.request().method() !== "POST") return route.continue();
    submissions.push(JSON.parse(route.request().postData()).idempotency_key);
    if (!lost) {
      lost = true;
      await route.fetch(); // the server places the order…
      return route.abort("connectionreset"); // …but the page never hears about it
    }
    return route.continue();
  });

  await page.getByTestId("order-button").click();
  await expect(page.getByTestId("checkout-notice")).toContainText("আপনার অর্ডার দুইবার হবে না");
  expect(seed("orders", funnel.id).count).toBe(1);

  // Pressing again resends the same submission id; the server hands back the order it already has.
  await page.getByTestId("order-button").click();
  await expect(page.getByTestId("order-confirmation").first()).toBeVisible();
  expect(submissions).toHaveLength(2);
  expect(submissions[1]).toBe(submissions[0]);
  expect(seed("orders", funnel.id).count).toBe(1);
  const after = seed("stock", products.a);
  expect(Object.values(after.stock)[0]).toBe(Object.values(before.stock)[0] - 1);
});

test("a price that moved while the customer was filling in the form is shown before they pay it", async ({ page }) => {
  test.setTimeout(120_000);
  const products = freshProducts();
  const funnel = publishPage("Checkout: price change", pageDocument(products));
  const target = products.b; // 5,000 with a 10% website discount: 4,500
  await page.goto(`/funnel/${funnel.slug}`);
  await ready(page);
  await page.locator("[data-fn-offer]").filter({ hasText: products.nameB }).click();
  await fillCustomer(page);
  await expect(page.getByTestId("order-summary")).toContainText(money(4500 + 80));

  // The store raises the price after the customer saw the total.
  seed("set-price", target, 6000);
  await page.getByTestId("order-button").click();

  await expect(page.getByTestId("checkout-notice")).toContainText("দাম পরিবর্তন হয়েছে");
  await expect(page.getByTestId("checkout-notice")).toContainText(money(5480)); // 6,000 less 10% = 5,400, plus 80 delivery
  expect(seed("orders", funnel.id).count).toBe(0);
  await expect(page.getByTestId("order-summary")).toContainText(money(5400));

  // Pressing the button again is the customer accepting the new total.
  await page.getByTestId("order-button").click();
  await expect(page.getByTestId("order-confirmation").first()).toBeVisible();
  const [order] = seed("orders", funnel.id).orders;
  expect(order.grand_total).toBe(5480);
});

test("when the last unit goes to someone else, the customer is told and the page updates", async ({ page }) => {
  test.setTimeout(120_000);
  const products = freshProducts();
  const funnel = publishPage("Checkout: last unit", pageDocument(products));
  await page.goto(`/funnel/${funnel.slug}`);
  await ready(page);
  await page.locator("[data-fn-offer]").filter({ hasText: products.nameA }).click();
  await fillCustomer(page);
  await expect(page.getByTestId("order-summary")).toContainText(money(3230));

  // While they type, the stock is sold out on the website.
  seed("set-stock", products.a, "", 0);
  await page.getByTestId("order-button").click();

  await expect(page.getByTestId("checkout-notice")).toContainText("এখন আর এই পরিমাণে নেই");
  await expect(page.getByTestId("checkout-notice")).toContainText(products.nameA);
  expect(seed("orders", funnel.id).count).toBe(0);
  await expect(page.locator("[data-fn-offer]").filter({ hasText: products.nameA })).toHaveAttribute("data-available", "false");
});

test("the form says plainly when ordering is closed, and never shows a half-working form", async ({ page }) => {
  const funnel = publishPage("Checkout: closed", pageDocument(freshProducts()));
  await page.route("**/api/v2/funnels/*/checkout/config", (route) =>
    route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ result: true, enabled: false, reason: "checkout_disabled", checkout: { fields: [] }, payment_methods: [], districts: [] }) }),
  );
  await page.goto(`/funnel/${funnel.slug}`);
  await expect(page.getByTestId("checkout-closed")).toContainText("অর্ডার নেওয়া বন্ধ");
  await expect(page.getByTestId("order-button")).toHaveCount(0);
});

test("a page that is not published, or does not exist, shows the store's not-found page", async ({ page }) => {
  await page.goto("/funnel/no-such-page-here");
  await expect(page.getByRole("heading", { name: "This page doesn't exist" })).toBeVisible();
  await expect(page.getByTestId("checkout-form")).toHaveCount(0);
  // Never offered to search engines.
  const robots = await page.locator('meta[name="robots"]').evaluateAll((tags) => tags.map((tag) => tag.content));
  expect(robots.length).toBeGreaterThan(0);
  for (const content of robots) expect(content).toContain("noindex");

  // An unpublished page behaves the same.
  const draft = draftPage("Checkout: still a draft", pageDocument(freshProducts()));
  await page.goto(`/funnel/${seed("show", draft.id).status === "draft" ? draft.slug : "x"}`);
  await expect(page.getByRole("heading", { name: "This page doesn't exist" })).toBeVisible();
});

test("on a phone the whole order fits one hand: no sideways scroll, readable fields, reachable button", async ({ browser }) => {
  test.setTimeout(120_000);
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const page = await context.newPage();
  const funnel = publishPage("Checkout: phone", pageDocument(freshProducts()));
  await page.goto(`/funnel/${funnel.slug}`);
  await ready(page);
  await fillCustomer(page);

  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow).toBeLessThanOrEqual(0);
  // 16px text in fields stops phones zooming the page on focus.
  const size = await page.getByTestId("input-phone").evaluate((el) => parseFloat(getComputedStyle(el).fontSize));
  expect(size).toBeGreaterThanOrEqual(16);
  const field = await page.getByTestId("input-phone").boundingBox();
  expect(field.height).toBeGreaterThanOrEqual(44);
  await page.getByTestId("order-button").scrollIntoViewIfNeeded();
  const button = await page.getByTestId("order-button").boundingBox();
  expect(button.height).toBeGreaterThanOrEqual(48);
  expect(button.x).toBeGreaterThanOrEqual(0);
  expect(button.x + button.width).toBeLessThanOrEqual(390);
  await page.screenshot({ path: `${SHOTS}/checkout-mobile-form.png`, fullPage: true });

  await page.getByTestId("order-button").click();
  await expect(page.getByTestId("order-confirmation").first()).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
  await page.screenshot({ path: `${SHOTS}/checkout-mobile-confirmed.png`, fullPage: true });
  await context.close();
});

test("the merchant adds an order form, previews a pretend order, and publishes a page that takes real ones", async ({ page }) => {
  test.setTimeout(240_000);
  const document = pageDocument(freshProducts());
  // Start from the product grid only; the order form comes from the library.
  document.page.sections = document.page.sections.filter((s) => ["hero_001", "offers_001"].includes(s.id));
  const seeded = publishPage("Checkout: built by hand (seed)", document);
  await openEditor(page, seeded.id);

  const frame = page.getByTestId("canvas-frame");
  await expect(page.getByTestId("library-checkout_form")).toBeVisible();
  await drag(page, page.getByTestId("library-checkout_form"), frame, { x: 0.5, y: 0.97 });
  await waitForSaved(page);

  // A complete form arrives: fields, summary, payment choice and an order button.
  await expect(frame.getByTestId("checkout-form")).toBeVisible();
  await expect(frame.getByTestId("input-name")).toBeVisible();
  await expect(frame.getByTestId("order-summary")).toBeVisible();
  await expect(frame.getByTestId("order-button")).toBeVisible();
  // In the editor the form is only drawn: typing does nothing.
  await expect(frame.getByTestId("input-name")).toBeDisabled();
  await page.screenshot({ path: `${SHOTS}/checkout-editor.png` });

  // Settings: the field list is editable, and the always-required fields cannot be switched off.
  await frame.getByTestId("checkout-form").click({ position: { x: 4, y: 4 } });
  await expect(page.getByTestId("settings-title")).toContainText("Order form");
  await expect(page.getByText("always asked and always required")).toBeVisible();

  // Preview behaves like the live page but only pretends to place the order.
  await page.getByTestId("preview-toggle").click();
  await expect(page.getByTestId("preview-banner")).toBeVisible();
  const area = await page.getByTestId("input-district").locator("option").nth(1).getAttribute("value");
  await page.getByTestId("input-name").fill("প্রিভিউ গ্রাহক");
  await page.getByTestId("input-phone").fill("01712345678");
  await page.getByTestId("input-address").fill("ধানমন্ডি, ঢাকা, বাসা ১");
  await page.getByTestId("input-district").selectOption(area);
  await page.locator("[data-fn-offer]").first().click();
  await page.getByTestId("order-button").click();
  await expect(page.getByTestId("order-confirmation").first()).toContainText("প্রিভিউ — এখানে কোনো আসল অর্ডার তৈরি হয় না");
  await expect(page.getByTestId("order-code")).toHaveText("PREVIEW");
  await page.screenshot({ path: `${SHOTS}/checkout-preview-confirmed.png` });
  expect(seed("orders", seeded.id).count).toBe(0);
  await page.getByTestId("preview-toggle").click();

  // Publishing works now that the form has its button, and the live page takes a real order.
  await publishNow(page);
  await expect(page.getByText(/Version \d+ published/)).toBeVisible();
  await page.goto(`/funnel/${seeded.slug}`);
  await expect(page.getByTestId("checkout-form")).toBeVisible();
  await expect(page.getByTestId("preview-banner")).toHaveCount(0);
  await expect(page.locator("[data-fn-offer]").first()).toBeVisible();
  await fillCustomer(page);
  await expect(page.getByTestId("order-summary")).toContainText(money(3230));
  await page.getByTestId("order-button").click();
  await expect(page.getByTestId("order-confirmation").first()).toBeVisible();
  expect(seed("orders", seeded.id).count).toBe(1);
});

test("a form without an order button cannot be published, and the editor says why", async ({ page }) => {
  const document = pageDocument(freshProducts());
  document.page.sections.find((s) => s.type === "checkout_form").children = document.page.sections.find((s) => s.type === "checkout_form").children.filter((c) => c.type !== "purchase_button");
  const funnel = draftPage("Checkout: no button", document);
  await openEditor(page, funnel.id);

  await page.getByTestId("publish").click();
  const problems = page.getByTestId("publish-errors");
  await expect(problems).toBeVisible();
  await expect(problems).toContainText("no order button");
  expect(seed("show", funnel.id).status).toBe("draft");
});
