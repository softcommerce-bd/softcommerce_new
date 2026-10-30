import { expect, test } from "@playwright/test";
import { canvasNodes, createFunnel, openEditor, waitForSaved } from "./helpers.js";

const SHOTS = "test-results/funnel-editor-screens";

test("device previews resize the page and keep per-device settings apart", async ({ page }) => {
  const funnel = createFunnel("Responsive: preview");
  await openEditor(page, funnel.id);
  await page.getByTestId("library-promo_hero").click();
  await page.getByTestId("library-usp_highlights").click();
  await page.getByTestId("library-faq_accordion").click();

  const frame = page.getByTestId("canvas-frame");
  const section = canvasNodes(page, "section").first();

  await page.getByTestId("device-mobile").click();
  await expect(frame).toHaveAttribute("data-device", "mobile");
  await expect.poll(async () => (await frame.boundingBox()).width).toBeCloseTo(390, 0);
  await page.screenshot({ path: `${SHOTS}/editor-mobile.png` });

  // Mobile-only padding on the highlights section.
  await section.click({ position: { x: 4, y: 4 } });
  await page.getByTestId("tab-style").click();
  await expect(page.getByTestId("style-device")).toContainText("Mobile");
  await page.getByTestId("style-paddingTop").fill("8");
  await expect(section).toHaveCSS("padding-top", "8px");

  await page.getByTestId("device-desktop").click();
  await expect(section).not.toHaveCSS("padding-top", "8px");
  await page.getByTestId("device-tablet").click();
  await expect(section).not.toHaveCSS("padding-top", "8px");

  // Hide the hero on mobile only: faded in the editor there, normal elsewhere.
  const hero = canvasNodes(page, "promohero").first();
  await page.getByTestId("device-mobile").click();
  await hero.click({ position: { x: 4, y: 4 } });
  await page.getByTestId("toggle-visibility").click();
  await expect(hero).toHaveCSS("opacity", "0.35");
  await page.getByTestId("device-desktop").click();
  await expect(hero).toHaveCSS("opacity", "1");

  await waitForSaved(page);
  await page.screenshot({ path: `${SHOTS}/editor-desktop.png` });

  // Preview mode shows the page without editor chrome.
  await page.getByTestId("preview-toggle").click();
  await expect(page.getByTestId("selection-outline")).toHaveCount(0);
  await expect(page.getByRole("complementary", { name: "Settings" })).toHaveCount(0);
  await page.getByTestId("device-mobile").click();
  await page.screenshot({ path: `${SHOTS}/preview-mobile.png` });
});

test("the editor is usable on a small screen", async ({ browser }) => {
  const funnel = createFunnel("Responsive: small screen");
  const context = await browser.newContext({ viewport: { width: 820, height: 1000 } });
  const page = await context.newPage();
  await openEditor(page, funnel.id);
  // Panels start closed so the page is visible; they open on demand.
  await expect(page.getByTestId("library-section")).toHaveCount(0);
  await page.getByRole("button", { name: "Toggle blocks panel" }).click();
  await page.getByTestId("library-promo_hero").click();
  await expect(canvasNodes(page, "promohero")).toHaveCount(1);
  await page.screenshot({ path: `${SHOTS}/editor-narrow.png` });
  await context.close();
});
