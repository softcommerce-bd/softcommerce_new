import { expect, test } from "@playwright/test";
import { canvasNodes, openEditor, publishPage, seed } from "./helpers.js";

/**
 * Layout checks at the screen sizes merchants use, with screenshots for review:
 * the editor on a small laptop and a tablet (panels must not cover the page or
 * push it off screen) and the analytics dashboard on a laptop and a phone.
 */

const SHOTS = "test-results/funnel-editor-screens/review";
const BACKEND = "http://127.0.0.1:8001";

function pageDoc(productId) {
  return {
    schemaVersion: 1,
    page: {
      settings: { language: "bn" },
      sections: [
        { id: "hero_001", type: "section", children: [{ id: "heading_001", type: "heading", props: { text: "ঈদ মেগা অফার — সীমিত সময়ের জন্য", level: "h1" } }, { id: "text_001", type: "text", props: { text: "সারা বাংলাদেশে ক্যাশ অন ডেলিভারি" } }] },
        {
          id: "offers_001", type: "product_offer_selector", props: { selectionMode: "single" },
          productBindings: [{ key: "offer_one", items: [{ productId, variant: null, quantity: 1, lockVariant: false }], offer: { type: "percent", value: 10 } }],
        },
        { id: "checkout_001", type: "checkout_form", props: {}, children: [{ id: "summary_001", type: "order_summary", props: {} }, { id: "buy_001", type: "purchase_button", props: { text: "অর্ডার করুন" } }] },
      ],
    },
  };
}

async function noSideways(page) {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow).toBeLessThanOrEqual(1);
}

test.beforeAll(() => {
  seed("users");
  seed("products");
  seed("checkout-setup");
});

for (const size of [{ width: 1024, height: 768 }, { width: 1280, height: 800 }, { width: 768, height: 1024 }]) {
  test(`editor at ${size.width}×${size.height}: the page stays visible next to the panels`, async ({ browser }) => {
    const context = await browser.newContext({ viewport: size });
    const page = await context.newPage();
    const product = seed("product", `UI Product ${Date.now().toString(36)}`, 2500, 20);
    const funnel = publishPage("UI review", pageDoc(product.id));
    await openEditor(page, funnel.id);
    const canvas = page.locator("#fn-canvas-scroller");
    await expect(canvas).toBeVisible();

    const left = page.getByRole("complementary", { name: "Blocks and layers" });
    const right = page.getByRole("complementary", { name: "Settings" });
    const wide = size.width >= 1280;

    // Wide screens start with both panels; smaller ones with the page alone.
    await expect(left).toHaveCount(wide ? 1 : 0);
    await expect(right).toHaveCount(wide ? 1 : 0);
    await canvasNodes(page, "heading").first().click();
    if (!wide) {
      // One panel at a time: opening settings closes the blocks panel and the other way round.
      await page.getByRole("button", { name: "Toggle blocks panel" }).click();
      await expect(left).toBeVisible();
      await page.getByRole("button", { name: "Toggle settings panel" }).click();
      await expect(right).toBeVisible();
      await expect(left).toHaveCount(0);
    }

    await noSideways(page);
    const box = await canvas.boundingBox();
    // The canvas keeps a usable width next to the open panel(s); on a tablet the panel floats over it.
    expect(box.width).toBeGreaterThanOrEqual(size.width >= 1024 ? 600 : 700);
    await page.screenshot({ path: `${SHOTS}/editor-${size.width}.png` });

    if (size.width < 1024) {
      // Tapping the page behind a floating panel closes it.
      await page.getByTestId("panel-backdrop").click({ position: { x: 20, y: 300 } });
      await expect(right).toHaveCount(0);
    }
    if (!(await left.isVisible())) await page.getByRole("button", { name: "Toggle blocks panel" }).click();
    await page.getByTestId("left-tab-saved").click();
    await page.screenshot({ path: `${SHOTS}/editor-${size.width}-saved.png` });
    await page.getByTestId("publish").click();
    await expect(page.getByTestId("live-link")).toBeVisible();
    await noSideways(page);
    await page.screenshot({ path: `${SHOTS}/editor-${size.width}-publish.png` });
    await context.close();
  });
}

test("analytics dashboard on a laptop and a phone", async ({ browser }) => {
  for (const viewport of [{ width: 1366, height: 900 }, { width: 390, height: 844 }]) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    await page.goto(`${BACKEND}/login`);
    await page.locator('input[name="email"]').first().fill("e2e-admin@funnel.test");
    await page.locator('input[name="password"]').first().fill("e2e-password");
    await page.locator('form:has(input[name="password"]) button[type="submit"]').first().click();
    await page.waitForURL(/\/admin/);
    await page.goto(`${BACKEND}/admin/funnels/analytics`);
    await expect(page.getByTestId("analytics-orders")).toBeVisible();
    await expect(page.getByTestId("analytics-steps")).toBeVisible();
    // No stray rows under table headers, no sideways scrolling on a phone.
    await expect.poll(() => page.evaluate(() => { const t = document.querySelector("[data-testid=analytics-funnels] table, [data-testid=analytics-devices] table"); return t ? getComputedStyle(t, "::before").display : "none"; })).toBe("none");
    await noSideways(page);
    await page.screenshot({ path: `${SHOTS}/analytics-${viewport.width}.png`, fullPage: true });
    await context.close();
  }
});

test("a merchant saves a funnel as a template and starts a new campaign from it; library pages fit a phone", async ({ browser }) => {
  test.setTimeout(240_000);
  const product = seed("product", `Template Source ${Date.now().toString(36)}`, 1200, 5);
  const source = publishPage(`Template source ${Date.now().toString(36)}`, pageDoc(product.id));
  const templateName = `Eid design ${Date.now().toString(36)}`;

  for (const viewport of [{ width: 1366, height: 900 }, { width: 390, height: 844 }]) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    await page.goto(`${BACKEND}/login`);
    await page.locator('input[name="email"]').first().fill("e2e-admin@funnel.test");
    await page.locator('input[name="password"]').first().fill("e2e-password");
    await page.locator('form:has(input[name="password"]) button[type="submit"]').first().click();
    await page.waitForURL(/\/admin/);

    await page.goto(`${BACKEND}/admin/funnels?search=${encodeURIComponent(source.slug)}`);
    const row = page.locator("tr", { hasText: source.slug });
    await expect(row.getByText("Copy link")).toBeVisible();
    await expect(row.locator(".funnel-figures")).toContainText("Visitors");
    await noSideways(page);
    await page.screenshot({ path: `${SHOTS}/funnels-list-${viewport.width}.png`, fullPage: true });

    if (viewport.width > 1000) {
      // Destructive menu actions ask first; saying no changes nothing. (The admin table plugin
      // rebuilds rows after load, which once dropped these prompts silently.)
      let asked = "";
      page.once("dialog", async (dialog) => { asked = dialog.message(); await dialog.dismiss(); });
      await row.locator(".action-toggle").click();
      await row.getByRole("button", { name: "Archive" }).click();
      await expect.poll(() => asked).toContain("Archive this funnel?");
      expect(seed("show", source.id).status).toBe("published");

      // Save as template from the row's menu.
      await page.keyboard.press("Escape");
      await row.locator(".action-toggle").click();
      await row.getByRole("button", { name: "Save as template" }).click();
      const dialog = page.locator("#funnel-save-template");
      await expect(dialog).toBeVisible();
      await dialog.getByLabel("Template name").fill(templateName);
      await dialog.getByRole("button", { name: "Save template" }).click();
      await page.waitForURL(/\/admin\/funnels\/templates/);
      await expect(page.getByRole("alert").getByText("Saved as a template")).toBeVisible();
    }

    const own = page.locator("tr", { hasText: templateName });
    if (!page.url().includes("/templates")) await page.goto(`${BACKEND}/admin/funnels/templates`);
    await expect(own).toBeVisible();
    await expect(own).toContainText("1");
    await expect(page.getByText("Built-in designs")).toBeVisible();
    await noSideways(page);
    await page.screenshot({ path: `${SHOTS}/funnel-templates-${viewport.width}.png`, fullPage: true });

    if (viewport.width > 1000) {
      // "Use" opens the create page with the template chosen and its product slot shown.
      await own.getByRole("link", { name: "Use" }).click();
      await page.waitForURL(/\/admin\/funnels\/create\?template=/);
      await expect(page.locator(".funnel-card.selected")).toContainText(templateName);
      await expect(page.locator(".funnel-slots:not(.d-none) .funnel-picker")).toHaveCount(1);
    }

    await page.goto(`${BACKEND}/admin/funnels/saved-sections`);
    await expect(page.getByRole("heading", { name: "Saved Sections", exact: true })).toBeVisible();
    await noSideways(page);
    await page.goto(`${BACKEND}/admin/funnels/settings`);
    await expect(page.getByRole("cell", { name: "Customers can order from funnel pages" })).toBeVisible();
    await noSideways(page);
    await page.screenshot({ path: `${SHOTS}/funnel-settings-${viewport.width}.png`, fullPage: true });
    await context.close();
  }

  // Deleting the template asks first and removes only the template.
  const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
  const page = await context.newPage();
  await page.goto(`${BACKEND}/login`);
  await page.locator('input[name="email"]').first().fill("e2e-admin@funnel.test");
  await page.locator('input[name="password"]').first().fill("e2e-password");
  await page.locator('form:has(input[name="password"]) button[type="submit"]').first().click();
  await page.waitForURL(/\/admin/);
  await page.goto(`${BACKEND}/admin/funnels/templates`);
  page.once("dialog", (dialog) => dialog.accept());
  await page.locator("tr", { hasText: templateName }).getByRole("button", { name: "Delete" }).click();
  await expect(page.getByRole("alert").getByText("Template has been deleted")).toBeVisible();
  await expect(page.locator("tr", { hasText: templateName })).toHaveCount(0);
  expect(seed("show", source.id).status).toBe("published");
  await context.close();
});
