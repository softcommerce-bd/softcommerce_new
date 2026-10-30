import fs from "node:fs";
import path from "node:path";
import { expect, test } from "@playwright/test";
import { seed } from "./helpers.js";

/**
 * Every template, in both languages, built into a real page with real products, published, and
 * opened the way customers open it: on a desktop and on a phone. Each page must draw without
 * errors, fit the screen width, read in its language, and have an order form that can be reached.
 *
 * Set FUNNEL_THUMBS=1 to also save the pictures the admin's template gallery shows
 * (misron_backend/public/assets/funnel-templates).
 */

const SHOTS = "test-results/funnel-editor-screens/templates";
const THUMBS_DIR = path.resolve(process.cwd(), "../misron_backend/public/assets/funnel-templates");
const MAKE_THUMBS = process.env.FUNNEL_THUMBS === "1";

const TEMPLATES = [
  { key: "classic-single-product", products: 1 },
  { key: "bundle-saver", products: 1 },
  { key: "flash-sale", products: 1 },
  { key: "video-story", products: 1 },
  { key: "social-proof", products: 1 },
  { key: "quick-order", products: 1 },
  { key: "premium-dark", products: 1 },
  { key: "product-showcase", products: 4 },
];

let counter = 0;
function freshProducts(count, price) {
  const ids = [];
  const names = [];
  for (let i = 0; i < count; i += 1) {
    counter += 1;
    const name = `TPL Product ${Date.now().toString(36)}${counter}`;
    ids.push(seed("product", name, price + i * 250, 40).id);
    names.push(name);
  }
  return { ids, names };
}

test.beforeAll(() => {
  seed("cache-clear");
  seed("users");
  seed("products");
  seed("checkout-setup");
  seed("templates");
  fs.mkdirSync(SHOTS, { recursive: true });
  if (MAKE_THUMBS) fs.mkdirSync(THUMBS_DIR, { recursive: true });
});

for (const template of TEMPLATES) {
  for (const lang of ["bn", "en"]) {
    test(`${template.key} (${lang}) opens cleanly on a desktop and a phone`, async ({ browser }) => {
      test.setTimeout(180_000);
      const { ids, names } = freshProducts(template.products, 2400);
      const funnel = seed("template-funnel", template.key, lang, `${template.key} ${lang}`, ids.join(","));
      seed("fill-for-publish", funnel.id);
      const published = seed("publish", funnel.id);

      for (const device of [
        { name: "desktop", viewport: { width: 1280, height: 900 } },
        { name: "mobile", viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
      ]) {
        const context = await browser.newContext({ viewport: device.viewport, isMobile: device.isMobile, hasTouch: device.hasTouch, deviceScaleFactor: device.deviceScaleFactor });
        const page = await context.newPage();
        const problems = [];
        page.on("pageerror", (error) => problems.push(`pageerror: ${error.message}`));
        page.on("console", (message) => {
          // Missing product pictures on the test machine are not a page error.
          if (message.type() === "error" && !/Failed to load resource|placeholder|favicon|403|404/.test(message.text())) problems.push(`console: ${message.text()}`);
        });

        const response = await page.goto(`/funnel/${published.slug}`);
        expect(response.status(), `${device.name}: status`).toBe(200);
        const root = page.locator(".fn-root");
        await expect(root).toBeVisible();
        await expect(root).toHaveAttribute("lang", lang);
        // The live offers arrive, with the real product's name.
        await expect(page.locator("[data-fn-offer]").first()).toBeVisible();
        await expect(page.getByText(names[0]).first()).toBeVisible();
        await expect(page.getByTestId("checkout-form")).toBeAttached();

        // The page fits the screen: nothing pushes it sideways.
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(overflow, `${device.name}: sideways overflow`).toBeLessThanOrEqual(1);

        // Words are readable and the order button can be tapped.
        const smallest = await page.evaluate(() => {
          const sizes = [...document.querySelectorAll(".fn-root p, .fn-root li, .fn-root label, .fn-root h1, .fn-root h2, .fn-root h3")]
            .filter((el) => el.offsetParent !== null && el.textContent.trim().length > 12)
            .map((el) => parseFloat(getComputedStyle(el).fontSize));
          return sizes.length ? Math.min(...sizes) : 16;
        });
        expect(smallest, `${device.name}: smallest text size`).toBeGreaterThanOrEqual(12);
        await page.getByTestId("order-button").scrollIntoViewIfNeeded();
        const button = await page.getByTestId("order-button").boundingBox();
        expect(button.height, `${device.name}: order button height`).toBeGreaterThanOrEqual(44);
        expect(button.width).toBeLessThanOrEqual(device.viewport.width);

        await page.evaluate(() => window.scrollTo(0, 0));
        await page.waitForTimeout(400);
        await page.screenshot({ path: `${SHOTS}/${template.key}-${lang}-${device.name}.png`, fullPage: true });

        if (MAKE_THUMBS && device.name === "desktop") {
          await page.screenshot({ path: path.join(THUMBS_DIR, `${template.key}-${lang}.jpg`), type: "jpeg", quality: 72, clip: { x: 0, y: 0, width: 1280, height: 960 } });
        }

        expect(problems, `${device.name}: errors in the page`).toEqual([]);
        await context.close();
      }
    });
  }
}

test("the template gallery shows all eight designs in both languages in the admin and on the editor API", async ({ page }) => {
  const { url } = seed("handoff", "e2e-admin@funnel.test", seed("funnel", "gallery probe").id);
  await page.goto(url);
  await expect(page.getByTestId("funnel-editor")).toBeVisible();
  const gallery = await page.evaluate(async () => {
    const session = JSON.parse(sessionStorage.getItem("misron:funnel-editor-session"));
    const response = await fetch("/api/v2/funnel-editor/templates", { headers: { Authorization: `Bearer ${session.token}` } });
    return (await response.json()).data;
  });
  // Merchants' own templates ("Save as template") follow the built-in designs; they are checked in ui-review.spec.js.
  const builtIn = gallery.filter((entry) => !entry.custom);
  expect(builtIn.map((entry) => entry.key)).toEqual(TEMPLATES.map((t) => t.key));
  expect(gallery.slice(0, builtIn.length)).toEqual(builtIn);
  for (const entry of gallery) expect(Object.keys(entry.languages).sort()).toEqual(["bn", "en"]);
});
