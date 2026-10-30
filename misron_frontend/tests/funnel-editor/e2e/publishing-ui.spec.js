import { expect, test } from "@playwright/test";
import { canvasNodes, draftPage, openEditor, publishNow, publishPage, seed, serverFunnel, waitForSaved } from "./helpers.js";

/**
 * What an admin does to get a page out: check it, publish or schedule it, share
 * a draft, go back to an older version, and set up a page made from a template.
 */

let counter = 0;
function freshProduct(name = "PUBUI Product", price = 2000) {
  counter += 1;
  const tag = `${Date.now().toString(36)}${counter}`;
  return { ...seed("product", `${name} ${tag}`, price, 20), name: `${name} ${tag}` };
}

function pageFor(product, headline = "Dialog page") {
  return {
    schemaVersion: 1,
    page: {
      sections: [
        { id: "hero_001", type: "section", children: [{ id: "heading_001", type: "heading", props: { text: headline, level: "h1" } }] },
        {
          id: "offers_001", type: "product_offer_selector", props: { selectionMode: "single" },
          productBindings: [{ key: "offer_one", items: [{ productId: product.id, variant: null, quantity: 1, lockVariant: false }], offer: { type: "none" } }],
        },
        { id: "checkout_001", type: "checkout_form", props: {}, children: [{ id: "buy_001", type: "purchase_button", props: { text: "Order now" } }] },
      ],
    },
  };
}

/** A datetime-local value some days ahead, at 10:30. */
function inDays(days) {
  const date = new Date(Date.now() + days * 86_400_000);
  const pad = (n) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T10:30`;
}

async function isOpen(request, slug) {
  const html = await (await request.get(`/funnel/${slug}`)).text();
  return html.includes("fn-root");
}

test.beforeAll(() => {
  seed("cache-clear");
  seed("products");
  seed("checkout-setup");
  seed("templates");
});

test("the Publish dialog checks the page, publishes it, and then offers the live link", async ({ page, request }) => {
  const funnel = draftPage("Dialog: publish", pageFor(freshProduct()));
  await openEditor(page, funnel.id);

  await page.getByTestId("publish").click();
  await expect(page.getByTestId("publish-checks")).toHaveAttribute("data-state", "ok");
  await expect(page.getByTestId("publish-checks")).toContainText("passes every check");
  await expect(page.getByTestId("publish-status")).toContainText("Not published yet");
  await page.getByTestId("publish-confirm").click();
  await expect(page.getByText("Version 1 published")).toBeVisible();
  await expect(page.getByTestId("funnel-status")).toHaveText("Published");
  expect(await isOpen(request, funnel.slug)).toBe(true);

  // Reopened, the dialog is about the live page.
  await page.getByTestId("publish").click();
  await expect(page.getByTestId("publish-status")).toContainText("Live");
  await expect(page.getByTestId("live-link")).toContainText(`/funnel/${funnel.slug}`);
  await expect(page.getByTestId("publish-confirm")).toHaveText(/Update live page/);
  await expect(page.getByTestId("unpublish")).toBeVisible();
});

test("publishing warnings are shown but do not block, and problems do block", async ({ page }) => {
  const product = freshProduct();
  const document = pageFor(product);
  // Two offers for one product at the same time: allowed, but worth a look.
  document.page.sections[1].productBindings.push({ key: "offer_two", items: [{ productId: product.id, variant: null, quantity: 2, lockVariant: false }], offer: { type: "percent", value: 10 } });
  const funnel = draftPage("Dialog: warnings", document);
  await openEditor(page, funnel.id);

  await page.getByTestId("publish").click();
  await expect(page.getByTestId("publish-warnings")).toContainText("offered twice");
  await expect(page.getByTestId("publish-confirm")).toBeEnabled();
  await page.keyboard.press("Escape");

  // A countdown without an end time stops publishing and points at the block.
  await page.getByTestId("library-countdown_timer").click();
  await waitForSaved(page);
  await page.getByTestId("publish").click();
  await expect(page.getByTestId("publish-errors")).toContainText("A countdown needs a real end time");
  await expect(page.getByTestId("publish-confirm")).toBeDisabled();
});

test("scheduling keeps the page closed until its time, can be cancelled, and goes live by itself", async ({ page, request }) => {
  const funnel = draftPage("Dialog: schedule", pageFor(freshProduct()));
  await openEditor(page, funnel.id);

  await page.getByTestId("publish").click();
  await page.getByTestId("publish-mode-later").check();
  await expect(page.getByTestId("publish-confirm")).toBeDisabled();
  await page.getByTestId("schedule-at").fill(inDays(2));
  await page.getByTestId("schedule-until").fill(inDays(5));
  await page.getByTestId("publish-confirm").click();
  await expect(page.getByText("Page scheduled")).toBeVisible();
  await expect(page.getByTestId("funnel-status")).toHaveText("Scheduled");
  await expect(page.getByTestId("funnel-scheduled-at")).toBeVisible();
  expect(serverFunnel(funnel.id).status).toBe("scheduled");
  expect(await isOpen(request, funnel.slug)).toBe(false);

  // Reopened: the schedule can be cancelled, which returns the page to draft.
  await page.getByTestId("publish").click();
  await expect(page.getByTestId("cancel-schedule")).toBeVisible();
  await page.getByTestId("cancel-schedule").click();
  await expect(page.getByText("Schedule cancelled")).toBeVisible();
  await expect(page.getByTestId("funnel-status")).toHaveText("Draft");

  // Schedule again, then let the clock run past the start: it opens without anyone publishing.
  await page.getByTestId("publish").click();
  await page.getByTestId("publish-mode-later").check();
  await page.getByTestId("schedule-at").fill(inDays(1));
  await page.getByTestId("publish-confirm").click();
  await expect(page.getByTestId("funnel-status")).toHaveText("Scheduled");
  seed("funnel-time", funnel.id, "scheduled_at", -1);
  seed("reconcile");
  expect(await isOpen(request, funnel.slug)).toBe(true);
});

test("a past start time is refused with a clear message", async ({ page }) => {
  const funnel = draftPage("Dialog: past", pageFor(freshProduct()));
  await openEditor(page, funnel.id);
  await page.getByTestId("publish").click();
  await page.getByTestId("publish-mode-later").check();
  await page.getByTestId("schedule-at").fill("2020-01-01T10:00");
  await page.getByTestId("publish-confirm").click();
  await expect(page.getByTestId("publish-notice")).toContainText("future");
  expect(serverFunnel(funnel.id).status).toBe("draft");
});

test("a live page can be given an end time, and unpublished from the dialog", async ({ page, request }) => {
  const live = publishPage("Dialog: end time", pageFor(freshProduct()));
  await openEditor(page, live.id);

  await page.getByTestId("publish").click();
  await page.getByTestId("end-time").fill(inDays(3));
  await page.getByTestId("end-time-save").click();
  await expect(page.getByTestId("publish-notice")).toContainText("goes offline");
  await expect(page.getByTestId("publish-status")).toContainText("Goes offline");
  await page.getByTestId("end-time-clear").click();
  await expect(page.getByTestId("publish-notice")).toContainText("stays live");
  await expect(page.getByTestId("end-time-clear")).toHaveCount(0);

  await page.getByTestId("unpublish").click();
  await expect(page.getByText("Page unpublished")).toBeVisible();
  await expect(page.getByTestId("funnel-status")).toHaveText("Unpublished");
  expect(await isOpen(request, live.slug)).toBe(false);
});

test("Share creates a secret draft link, lists it without the secret, and switches it off", async ({ page, context }) => {
  const funnel = draftPage("Dialog: share", pageFor(freshProduct("PUBUI Shared"), "Shared draft headline"));
  await openEditor(page, funnel.id);

  await page.getByTestId("share").click();
  await expect(page.getByTestId("share-empty")).toBeVisible();
  await page.getByTestId("share-label").fill("Client review");
  await page.getByTestId("share-hours").selectOption("24");
  await page.getByTestId("share-create").click();
  const url = await page.getByTestId("share-url").inputValue();
  expect(url).toMatch(new RegExp(`/funnel/${funnel.slug}/preview/[A-Za-z0-9]{40}$`));
  await expect(page.getByTestId("share-item")).toContainText("Client review");
  // Only the one-time box shows the secret.
  expect(await page.getByTestId("share-list").innerText()).not.toContain(url.slice(-40));

  const viewer = await context.newPage();
  await viewer.goto(url);
  await expect(viewer.getByTestId("preview-link-banner")).toBeVisible();
  await expect(viewer.getByRole("heading", { name: "Shared draft headline" })).toBeVisible();

  await page.getByTestId("share-revoke").click();
  await expect(page.getByTestId("share-empty")).toBeVisible();
  await viewer.reload();
  await expect(viewer.getByTestId("preview-link-banner")).toHaveCount(0);
  await expect(viewer.locator(".fn-root")).toHaveCount(0);
  await viewer.close();
});

test("Version history shows every version, previews one, and restores it as the draft", async ({ page }) => {
  const funnel = draftPage("History: versions", pageFor(freshProduct(), "First headline"));
  await openEditor(page, funnel.id);
  await publishNow(page);
  await expect(page.getByText("Version 1 published")).toBeVisible();

  // Change the headline in the draft and publish version 2.
  await canvasNodes(page, "heading").first().click();
  await page.getByTestId("field-text").fill("Second headline");
  await waitForSaved(page);
  await publishNow(page);
  await expect(page.getByText("Version 2 published")).toBeVisible();

  await page.getByTestId("history").click();
  const items = page.getByTestId("history-item");
  await expect(items).toHaveCount(2);
  await expect(items.first()).toContainText("Version 2");
  await expect(items.first()).toContainText("Live");

  // Look at version 1 as customers saw it.
  await page.locator('[data-testid="history-item"][data-version="1"]').click();
  await expect(page.getByTestId("history-preview")).toContainText("First headline");
  await expect(page.getByTestId("history-preview")).not.toContainText("Second headline");
  await expect(page.getByTestId("history-restore-publish")).toBeVisible();

  await page.getByTestId("history-restore").click();
  await expect(page.getByText("Version 1 loaded into your draft")).toBeVisible();
  await expect(canvasNodes(page, "heading").first()).toContainText("First headline");
  const server = serverFunnel(funnel.id);
  expect(server.content.page.sections[0].children[0].props.text).toBe("First headline");
  // The live page is still version 2 until the merchant publishes.
  expect(server.published_content.page.sections[0].children[0].props.text).toBe("Second headline");
  expect(server.published_version).toBe(2);
});

test("a page made from a template starts on its setup list, and choosing a product fills every offer", async ({ page }) => {
  const product = freshProduct("PUBUI Template Product", 3000);
  const created = seed("template-funnel", "classic-single-product", "en", "Setup: classic");
  await openEditor(page, created.id);

  const setup = page.getByTestId("setup-checklist");
  await expect(setup).toBeVisible();
  await expect(page.getByTestId("setup-summary")).toContainText("1 product still to choose");
  await expect(page.getByTestId("setup-slot")).toHaveAttribute("data-done", "false");

  await page.getByTestId("setup-choose").click();
  await page.getByTestId("picker-search").fill(product.name);
  await page.getByTestId(`pick-${product.id}`).getByRole("button", { pressed: false }).first().click();
  await page.getByTestId("picker-confirm").click();

  await expect(page.getByTestId("setup-summary")).toContainText("Every product is chosen");
  await expect(page.getByTestId("setup-slot")).toHaveAttribute("data-done", "true");
  // All three package cards now show the product, live from the store.
  await expect(page.locator('[data-testid="canvas-frame"] [data-fn-offer]')).toHaveCount(3);
  await expect(page.locator('[data-testid="canvas-frame"] [data-fn-offer]').first()).toContainText("৳3,000");
  expect(serverFunnel(created.id).content.page.sections[4].children[0].productBindings.map((b) => b.items[0].quantity)).toEqual([1, 2, 3]);

  // Ready to publish: the checks pass.
  await page.getByTestId("publish").click();
  await expect(page.getByTestId("publish-checks")).toHaveAttribute("data-state", "ok");
});
