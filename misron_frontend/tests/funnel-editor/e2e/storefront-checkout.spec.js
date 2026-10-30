import { expect, test } from "@playwright/test";
import { seed } from "./helpers.js";

/**
 * Regression check of the store's own checkout (not a funnel): a guest adds a
 * product on its product page, checks out with cash on delivery, and the order
 * reaches the store with the stock taken once. Funnel work changed shared code
 * (stock writers, payment handling, the root layout's loading screens), so the
 * normal website flow is exercised end to end here.
 */

test.beforeAll(() => {
  seed("cache-clear");
  seed("products");
  seed("checkout-setup");
});

test("a guest buys from the website with cash on delivery; the order and stock are right", async ({ page }) => {
  test.setTimeout(180_000);
  const name = `REG Website Tee ${Date.now().toString(36)}`;
  const product = seed("product", name, 1500, 9);

  await page.goto(`/product/${product.slug}`);
  await expect(page.getByText(name).first()).toBeVisible();
  await page.getByRole("button", { name: "Add to cart" }).first().click();

  // The cart is kept for this browser; checkout opens on its own page too.
  await expect.poll(async () => page.evaluate(() => Boolean(localStorage.getItem("temp_user_id") || localStorage.getItem("guest_id") || document.cookie)), { timeout: 15_000 }).toBe(true);
  await page.goto("/checkout-page");
  await page.getByLabel("Your Full Name *").fill("Regression Customer");
  await page.getByLabel("Mobile Phone Number *").fill(`0161${String(Math.floor(Math.random() * 10_000_000)).padStart(7, "0")}`);
  await page.getByLabel("Detailed Delivery Address *").fill("House 1, Road 1, Mirpur, Dhaka");
  const confirm = page.getByRole("button", { name: /Confirm Order/ });
  await expect(confirm).toBeEnabled();
  await confirm.click();

  await expect.poll(() => seed("product-orders", product.id).count, { timeout: 30_000 }).toBe(1);
  const [order] = seed("product-orders", product.id).orders;
  expect(order.funnel_id).toBeNull();
  expect(order.payment_type).toBe("cash_on_delivery");
  expect(seed("stock", product.id).stock[""]).toBe(8);
  expect(seed("stock", product.id).batches).toBe(8);
});
