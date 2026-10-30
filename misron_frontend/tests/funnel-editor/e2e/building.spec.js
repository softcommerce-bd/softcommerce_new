import { expect, test } from "@playwright/test";
import { canvasNodes, createFunnel, drag, openEditor, serverFunnel, waitForSaved } from "./helpers.js";

test("drag blocks from the library, nest them and reorder them", async ({ page }) => {
  const funnel = createFunnel("Build: drag and drop");
  await openEditor(page, funnel.id);
  const frame = page.getByTestId("canvas-frame");
  const sections = canvasNodes(page, "section");

  await drag(page, page.getByTestId("library-section"), frame, { x: 0.5, y: 0.3 });
  await expect(sections).toHaveCount(1);
  const firstId = await sections.first().getAttribute("data-fn-id");

  // A heading dropped into the section lands inside it.
  await drag(page, page.getByTestId("library-heading"), sections.first());
  const headings = canvasNodes(page, "heading");
  await expect(headings).toHaveCount(1);
  await expect(headings.first()).toHaveAttribute("data-fn-parent", firstId);

  // A text dropped below everything cannot stand alone, so it gets its own section.
  await drag(page, page.getByTestId("library-text"), frame, { x: 0.5, y: 0.98 });
  await expect(sections).toHaveCount(2);
  const secondId = await sections.nth(1).getAttribute("data-fn-id");
  await expect(canvasNodes(page, "text").first()).toHaveAttribute("data-fn-parent", secondId);

  // Move the second section above the first with its drag handle.
  await sections.nth(1).click({ position: { x: 6, y: 6 } });
  await expect(page.getByTestId("settings-title")).toHaveText("Section");
  await drag(page, page.getByTestId("drag-handle"), sections.first(), { x: 0.5, y: 0.01 });
  await expect(sections.first()).toHaveAttribute("data-fn-id", secondId);

  await waitForSaved(page);
  const saved = serverFunnel(funnel.id).content.page.sections;
  expect(saved.map((section) => section.id)).toEqual([secondId, firstId]);
  expect(saved[1].children[0].type).toBe("heading");
});

test("drop indicator follows the pointer and refuses impossible drops", async ({ page }) => {
  const funnel = createFunnel("Build: indicator");
  await openEditor(page, funnel.id);
  await page.getByTestId("library-sticky_cta").click();
  await expect(canvasNodes(page, "stickycta")).toHaveCount(1);
  // Only one sticky bar per page: the library offers no second one.
  await expect(page.getByTestId("library-sticky_cta")).toBeDisabled();

  await page.getByTestId("library-section").click();
  const source = page.getByTestId("library-heading");
  const box = await source.boundingBox();
  const target = await canvasNodes(page, "section").first().boundingBox();
  await page.mouse.move(box.x + 20, box.y + 20);
  await page.mouse.down();
  await page.mouse.move(box.x + 40, box.y + 40, { steps: 4 });
  await page.mouse.move(target.x + target.width / 2, target.y + target.height / 2, { steps: 12 });
  await expect(page.getByTestId("drop-indicator")).toBeVisible();
  await page.keyboard.press("Escape");
  await page.mouse.up();
  await expect(canvasNodes(page, "heading")).toHaveCount(0);
});

test("inline editing in Bengali, settings, undo and redo survive a reload", async ({ page }) => {
  const funnel = createFunnel("Build: editing");
  await openEditor(page, funnel.id);
  await page.getByTestId("library-heading").click();

  const heading = canvasNodes(page, "heading").first();
  const editable = heading.locator('[contenteditable="plaintext-only"]');
  await editable.click();
  await page.keyboard.press("Control+A");
  await page.keyboard.type("ঈদ মেগা অফার");
  await expect(editable).toHaveText("ঈদ মেগা অফার");
  await expect(page.getByTestId("field-text")).toHaveValue("ঈদ মেগা অফার");

  await page.getByTestId("field-level").selectOption("h1");
  await expect(heading).toHaveJSProperty("tagName", "H1");
  await page.getByTestId("undo").click();
  await expect(heading).toHaveJSProperty("tagName", "H2");
  await page.getByTestId("redo").click();
  await expect(heading).toHaveJSProperty("tagName", "H1");

  await waitForSaved(page);
  await page.reload();
  await expect(page.getByTestId("funnel-editor")).toBeVisible();
  await expect(canvasNodes(page, "heading").first()).toHaveText("ঈদ মেগা অফার");
  await expect(canvasNodes(page, "heading").first()).toHaveJSProperty("tagName", "H1");
});

test("keyboard shortcuts duplicate, delete, copy and paste blocks", async ({ page }) => {
  const funnel = createFunnel("Build: shortcuts");
  await openEditor(page, funnel.id);
  await page.getByTestId("library-heading").click();
  const headings = canvasNodes(page, "heading");
  await expect(headings).toHaveCount(1);

  await page.locator("body").press("Control+d");
  await expect(headings).toHaveCount(2);
  await page.locator("body").press("Delete");
  await expect(headings).toHaveCount(1);

  // Clicking a selected heading places the caret in its text, where Ctrl+C
  // copies text; Escape leaves the text so the shortcut copies the block.
  await headings.first().click();
  await page.keyboard.press("Escape");
  await page.locator("body").press("Control+c");
  await page.locator("body").press("Control+v");
  await expect(headings).toHaveCount(2);

  // A locked block stays put.
  await page.getByTestId("tab-advanced").click();
  await page.getByTestId("field-locked").click();
  await page.locator("body").press("Delete");
  await expect(headings).toHaveCount(2);
});
