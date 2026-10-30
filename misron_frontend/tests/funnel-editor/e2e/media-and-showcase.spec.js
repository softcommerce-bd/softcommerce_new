import path from "node:path";
import { expect, test } from "@playwright/test";
import { canvasNodes, createFunnel, openEditor, serverFunnel, waitForSaved } from "./helpers.js";

const SHOTS = "test-results/funnel-editor-screens";
const SAMPLE = path.resolve("tests/funnel-editor/fixtures/sample-product.png");

test("images upload into the shared media library and appear on the page", async ({ page }) => {
  const funnel = createFunnel("Media: upload");
  await openEditor(page, funnel.id);
  await page.getByTestId("library-promo_hero").click();

  await page.getByTestId("field-image").click();
  await page.getByTestId("media-upload-input").setInputFiles(SAMPLE);
  const image = canvasNodes(page, "promohero").first().locator("img.fn-hero-media");
  await expect(image).toHaveAttribute("src", /\/uploads\/all\//);
  await expect.poll(() => image.evaluate((img) => img.naturalWidth)).toBeGreaterThan(0);

  await waitForSaved(page);
  const saved = serverFunnel(funnel.id).content.page.sections[0].props.image;
  expect(saved.uploadId).toBeGreaterThan(0);
  expect(saved.url).toMatch(/\/uploads\/all\//);

  // The upload is now in the library for the next pick.
  await page.getByTestId("field-image").click();
  await expect(page.getByRole("dialog").getByTitle("sample-product").first()).toBeVisible();
});

test("showcase: a long-form offer page in the editor", async ({ page }) => {
  const funnel = createFunnel("ব্লেন্ডার মেগা অফার");
  await openEditor(page, funnel.id);

  for (const type of ["announcement_ribbon", "promo_hero", "trust_icons", "benefits_grid", "countdown_timer", "faq_accordion", "sticky_cta"]) {
    await page.getByTestId(`library-${type}`).click();
    await page.getByTestId("canvas").click({ position: { x: 8, y: 8 } });
  }

  const hero = canvasNodes(page, "promohero").first();
  await hero.click({ position: { x: 4, y: 4 } });
  await page.getByTestId("field-image").click();
  await page.getByTestId("media-upload-input").setInputFiles(SAMPLE);
  await expect(hero.locator("img.fn-hero-media")).toBeVisible();
  await page.getByTestId("field-headline").fill("ঘরে বসেই রেস্টুরেন্টের মতো জুস আর স্মুদি");
  await page.getByTestId("field-eyebrow").fill("ঈদ স্পেশাল অফার");

  const countdown = canvasNodes(page, "countdowntimer").first();
  await countdown.click({ position: { x: 4, y: 4 } });
  const future = new Date(Date.now() + 3 * 86400000);
  const local = `${future.getFullYear()}-${String(future.getMonth() + 1).padStart(2, "0")}-${String(future.getDate()).padStart(2, "0")}T23:59`;
  await page.getByTestId("field-endsAt").fill(local);

  await hero.click({ position: { x: 4, y: 4 } });
  await waitForSaved(page);
  await page.screenshot({ path: `${SHOTS}/showcase-desktop.png` });

  await page.getByTestId("tab-style").click();
  await page.screenshot({ path: `${SHOTS}/showcase-style-tab.png` });

  await page.getByTestId("left-tab-layers").click();
  await page.screenshot({ path: `${SHOTS}/showcase-layers.png` });

  await page.getByTestId("device-mobile").click();
  await page.getByTestId("preview-toggle").click();
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${SHOTS}/showcase-mobile-preview.png` });
});
