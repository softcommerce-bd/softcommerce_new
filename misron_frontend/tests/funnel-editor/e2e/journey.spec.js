import { expect, test } from "@playwright/test";
import { publishNow, seed } from "./helpers.js";

/**
 * The whole story, as the people in it would live it:
 *
 *   an admin signs in, opens Sales Funnels, creates a funnel from a template with a product from the
 *   store, publishes it, a customer orders from the live page, the order shows up in the Orders
 *   dashboard marked as coming from the funnel, stock goes down, and cancelling the order puts it back.
 */

const BACKEND = "http://127.0.0.1:8001";
const SHOTS = "test-results/funnel-editor-screens";

test.beforeAll(() => {
  seed("cache-clear");
  seed("users");
  seed("products");
  seed("checkout-setup");
  seed("templates");
});

test("admin builds and publishes a funnel from a template; a customer orders; the order reaches the dashboard; cancelling restores stock", async ({ page, browser }) => {
  test.setTimeout(240_000);
  const productName = `JOURNEY Blender ${Date.now().toString(36)}`;
  const product = seed("product", productName, 4200, 12);

  // 1. Sign in to the admin panel.
  await page.goto(`${BACKEND}/login`);
  await page.locator('input[name="email"]').first().fill("e2e-admin@funnel.test");
  await page.locator('input[name="password"]').first().fill("e2e-password");
  await page.locator('form:has(input[name="password"]) button[type="submit"]').first().click();
  await page.waitForURL(/\/admin/);

  // 2. Sales Funnels → Create Funnel.
  await page.goto(`${BACKEND}/admin/funnels`);
  await expect(page.getByRole("heading", { name: /Sales Funnels/ }).first()).toBeVisible();
  await page.goto(`${BACKEND}/admin/funnels/create`);

  // 3. Pick a design (English), the product from the store, and a name.
  // Eight designs and "blank" (templates merchants saved themselves come after them).
  await expect(page.locator('#funnel-template-grid .funnel-card:not([data-template^="custom-"])')).toHaveCount(9);
  await page.locator(".btn-group-toggle label", { hasText: "English" }).click();
  await page.locator('.funnel-card[data-template="classic-single-product"]').click();
  await expect(page.locator('.funnel-slots[data-slots-for="classic-single-product"][data-slots-lang="en"]')).toBeVisible();
  const picker = page.locator('.funnel-slots[data-slots-for="classic-single-product"][data-slots-lang="en"] .funnel-picker[data-slot="main"]');
  await picker.locator(".funnel-picker-search").fill(productName);
  await picker.locator(".funnel-picker-results button").first().click();
  await expect(picker.locator(".funnel-picker-chosen")).toContainText(productName);
  await page.locator("#funnel-title").fill("Journey blender offer");
  await page.screenshot({ path: `${SHOTS}/journey-admin-create.png`, fullPage: true });
  await page.locator("#funnel-create-form button[type=submit]").click();

  // 4. The editor opens on the finished design: the product is already chosen.
  await page.waitForURL(/\/builder\/funnels\/\d+/);
  await expect(page.getByTestId("funnel-editor")).toBeVisible();
  await page.getByTestId("left-tab-setup").click();
  await expect(page.getByTestId("setup-summary")).toContainText("Every product is chosen");
  await expect(page.locator('[data-testid="canvas-frame"] [data-fn-offer]')).toHaveCount(3);
  const funnelId = Number(new URL(page.url()).pathname.split("/").pop());

  // 5. Publish.
  await publishNow(page);
  await expect(page.getByText(/Version 1 published/)).toBeVisible();
  const livePath = await page.evaluate(async (id) => {
    const session = JSON.parse(sessionStorage.getItem("misron:funnel-editor-session"));
    const response = await fetch(`/api/v2/funnel-editor/funnels/${id}`, { headers: { Authorization: `Bearer ${session.token}` } });
    return new URL((await response.json()).data.public_url).pathname;
  }, funnelId);
  await page.screenshot({ path: `${SHOTS}/journey-editor-published.png` });

  // 6. A customer orders from the live page on a phone.
  const customerContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const customer = await customerContext.newPage();
  await customer.goto(livePath);
  await expect(customer.locator("[data-fn-offer]").first()).toBeVisible();
  await expect(customer.getByText(productName).first()).toBeVisible();
  await customer.locator("[data-fn-offer]").nth(1).click(); // the pack of two
  await customer.getByTestId("input-name").fill("Journey Customer");
  await customer.getByTestId("input-phone").fill(`0171${String(Math.floor(Math.random() * 10_000_000)).padStart(7, "0")}`);
  await customer.getByTestId("input-address").fill("House 12, Road 3, Dhanmondi, Dhaka");
  await customer.getByTestId("input-district").selectOption({ index: 1 });
  await expect(customer.getByTestId("order-summary")).not.toHaveAttribute("data-updating", /.+/);
  await customer.getByTestId("order-button").click();
  const confirmation = customer.getByTestId("order-confirmation").first();
  await expect(confirmation).toBeVisible();
  const code = await confirmation.getByTestId("order-code").innerText();
  expect(code).toMatch(/^\d{6}$/);
  await customer.screenshot({ path: `${SHOTS}/journey-customer-confirmed.png`, fullPage: true });
  await customerContext.close();

  // 7. The order is a normal order of the store, from the funnel; stock went down by two.
  const orders = seed("orders", funnelId);
  expect(orders.count).toBe(1);
  expect(orders.orders[0].code).toBe(code);
  expect(orders.orders[0].lines.map((l) => l.quantity)).toEqual([2]);
  expect(seed("stock", product.id).stock[""]).toBe(10);
  expect(seed("stock", product.id).batches).toBe(10);

  // 8. It appears in the Orders dashboard, marked as a funnel order, and the funnel's own link filters to it.
  await page.goto(`${BACKEND}/admin/all_orders`);
  const row = page.locator("tr", { hasText: code });
  await expect(row).toBeVisible();
  await expect(row.getByText("Funnel", { exact: true })).toBeVisible();
  await page.screenshot({ path: `${SHOTS}/journey-admin-orders.png`, fullPage: true });
  await row.getByText("Funnel", { exact: true }).click();
  await expect(page.getByText(/Showing only orders placed through sales funnel/)).toBeVisible();
  await expect(page.locator("tr", { hasText: code })).toBeVisible();

  // 9. The admin cancels it: the units come back to the shelf and the batches, once.
  const token = await page.locator('meta[name="csrf-token"]').getAttribute("content");
  const orderId = orders.orders[0].id;
  for (let press = 0; press < 2; press += 1) {
    const response = await page.request.post(`${BACKEND}/admin/orders/update_delivery_status`, { form: { _token: token, order_id: String(orderId), status: "cancelled" } });
    expect(response.ok()).toBeTruthy();
  }
  expect(seed("stock", product.id).stock[""]).toBe(12);
  expect(seed("stock", product.id).batches).toBe(12);

  // 10. Funnel analytics: the order counts as placed and cancelled, read from the order itself.
  await page.goto(`${BACKEND}/admin/funnels/analytics?funnel=${funnelId}`);
  await expect(page.getByRole("heading", { name: /Analytics: Journey blender offer/ })).toBeVisible();
  await expect(page.getByTestId("kpi-orders_placed")).toContainText("1");
  await expect(page.getByTestId("kpi-orders_cancelled")).toContainText("1");
  await expect(page.getByTestId("kpi-confirmed_revenue")).not.toContainText(/[1-9]/);
  await page.screenshot({ path: `${SHOTS}/journey-admin-analytics.png`, fullPage: true });
});
