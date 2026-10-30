import { expect, test } from "@playwright/test";
import { canvasNodes, createFunnel, openEditor, seed, serverFunnel, waitForSaved, publishNow } from "./helpers.js";

test("a save from another window is detected; keeping mine wins", async ({ page }) => {
  const funnel = createFunnel("Save: keep mine");
  await openEditor(page, funnel.id);
  await page.getByTestId("library-section").click();
  await waitForSaved(page);

  seed("touch", funnel.id);
  await page.getByTestId("library-heading").click();
  await expect(page.getByTestId("conflict-dialog")).toBeVisible();

  await page.getByTestId("conflict-keep-mine").click();
  await waitForSaved(page);
  const saved = serverFunnel(funnel.id).content.page.sections;
  expect(saved).toHaveLength(1);
  expect(saved[0].children[0].type).toBe("heading");
});

test("after a conflict, loading their version replaces mine", async ({ page }) => {
  const funnel = createFunnel("Save: load theirs");
  await openEditor(page, funnel.id);
  await page.getByTestId("library-section").click();
  await waitForSaved(page);

  seed("touch", funnel.id);
  await page.getByTestId("library-heading").click();
  await page.getByTestId("conflict-reload").click();
  await expect(page.getByTestId("conflict-dialog")).toHaveCount(0);
  await expect(page.getByTestId("canvas-frame")).toContainText("Saved from another window");
  await expect(canvasNodes(page, "heading")).toHaveCount(0);
});

test("publishing checks the page, then creates versions", async ({ page }) => {
  const funnel = createFunnel("Publish: versions");
  await openEditor(page, funnel.id);

  await page.getByTestId("publish").click();
  await expect(page.getByTestId("publish-errors")).toContainText("Add at least one section");
  await page.getByRole("button", { name: "Close" }).last().click();

  await page.getByTestId("library-promo_hero").click();
  await publishNow(page);
  await expect(page.getByText("Version 1 published")).toBeVisible();
  await expect(page.getByTestId("funnel-status")).toHaveText("Published");
  let server = serverFunnel(funnel.id);
  expect(server.status).toBe("published");
  expect(server.published_version).toBe(1);

  // Editing after publishing leaves the live version alone until "Update".
  const headline = canvasNodes(page, "promohero").first().locator("h1 [contenteditable], h1[contenteditable]").first();
  await canvasNodes(page, "promohero").first().click({ position: { x: 4, y: 4 } });
  await page.getByTestId("field-headline").fill("নতুন শিরোনাম");
  await waitForSaved(page);
  server = serverFunnel(funnel.id);
  expect(server.content.page.sections[0].props.headline).toBe("নতুন শিরোনাম");
  expect(server.published_content.page.sections[0].props.headline).not.toBe("নতুন শিরোনাম");
  await expect(headline).toHaveText("নতুন শিরোনাম");

  await publishNow(page);
  await expect(page.getByText("Version 2 published")).toBeVisible();
  expect(serverFunnel(funnel.id).published_content.page.sections[0].props.headline).toBe("নতুন শিরোনাম");
});

test("problems the server reports point at the block", async ({ page }) => {
  const funnel = createFunnel("Publish: countdown");
  await openEditor(page, funnel.id);
  // A countdown with no end time saves as a draft but cannot be published.
  await page.getByTestId("library-countdown_timer").click();
  await waitForSaved(page);
  await page.getByTestId("publish").click();
  const dialog = page.getByTestId("publish-dialog");
  await expect(dialog).toContainText("A countdown needs a real end time");
  await dialog.getByRole("button", { name: /Go to/ }).click();
  await expect(page.getByTestId("settings-title")).toContainText("Countdown timer");
});
