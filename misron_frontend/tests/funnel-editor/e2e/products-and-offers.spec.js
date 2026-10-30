import { expect, test } from "@playwright/test";
import { canvasNodes, createFunnel, drag, openEditor, seed, serverFunnel, waitForSaved, publishNow } from "./helpers.js";

const SHOTS = "test-results/funnel-editor-screens";
const money = (amount) => new RegExp(`৳\\s?${amount.toLocaleString("en-US")}(?![\\d,])`);

let catalogue;
test.beforeAll(() => {
  catalogue = seed("products");
});

async function addGridWithEightBlenders(page) {
  const frame = page.getByTestId("canvas-frame");
  // Dragging a product block onto the page opens the product picker.
  await drag(page, page.getByTestId("library-product_offer_selector"), frame, { x: 0.5, y: 0.3 });
  await expect(page.getByTestId("product-picker")).toBeVisible();

  const search = page.getByTestId("picker-search");
  await search.fill("E2E Blender");
  await expect(page.getByTestId(`pick-${catalogue.blenders[0]}`)).toBeVisible();
  // Unpublished and digital products never appear.
  await expect(page.getByText("E2E Blender Prototype")).toHaveCount(0);
  await expect(page.getByText("E2E Blender Manual")).toHaveCount(0);

  for (const id of catalogue.blenders) {
    const row = page.getByTestId(`pick-${id}`);
    if (await row.getByTestId(`pick-${id}-any`).count()) await row.getByTestId(`pick-${id}-any`).click();
    else await row.getByRole("button", { pressed: false }).first().click();
  }
  await expect(page.getByTestId("picker-count")).toHaveText("8 selected");
  await page.getByTestId("picker-confirm").click();
  await expect(page.locator('[data-testid="canvas-frame"] [data-fn-offer]')).toHaveCount(8);
}

test("the picker finds existing products by name, SKU, category and ID", async ({ page }) => {
  const funnel = createFunnel("Offers: picker search");
  await openEditor(page, funnel.id);
  await page.getByTestId("library-product_offer_selector").click();
  const picker = page.getByTestId("product-picker");
  await expect(picker).toBeVisible();
  const search = page.getByTestId("picker-search");

  await search.fill("BLD-003");
  await expect(picker.locator('li[data-testid^="pick-"]')).toHaveCount(1);
  await expect(page.getByTestId(`pick-${catalogue.blenders[2]}`)).toContainText("E2E Blender 3");

  await search.fill("E2E Blenders");
  await expect(page.getByTestId(`pick-${catalogue.blenders[7]}`)).toBeVisible();
  await expect(page.getByTestId(`pick-${catalogue.blenders[0]}`)).toBeVisible();

  await search.fill(String(catalogue.blenders[4]));
  await expect(page.getByTestId(`pick-${catalogue.blenders[4]}`)).toContainText("E2E Blender 5");

  await search.fill("E2E Walton");
  await expect(page.getByTestId(`pick-${catalogue.blenders[1]}`)).toBeVisible();

  // Prices, availability and variations are the store's own.
  await search.fill("E2E Blender 2");
  const row = page.getByTestId(`pick-${catalogue.blenders[1]}`);
  await expect(row).toContainText("1L · 10");
  await expect(row).toContainText("2L · 0");
  await search.fill("E2E Blender 4");
  await expect(page.getByTestId(`pick-${catalogue.blenders[3]}`)).toContainText("2 in stock");

  // Closing without choosing keeps the block, with a way back in.
  await page.getByRole("button", { name: "Cancel" }).click();
  await expect(page.getByTestId("choose-products")).toBeVisible();
});

test("eight blenders become eight cards: two columns, selection mode, offers, labels, preview, save and publish", async ({ page, request }) => {
  // One long journey through the whole merchant workflow.
  test.setTimeout(240_000);
  const funnel = createFunnel("Offers: eight blenders");
  await openEditor(page, funnel.id);
  await addGridWithEightBlenders(page);

  const frame = page.getByTestId("canvas-frame");
  const cards = frame.locator("[data-fn-offer]");
  await expect(cards.first().getByTestId("card-title")).toHaveText("E2E Blender 1");
  await expect(cards.first().getByTestId("offer-price")).toHaveText(money(3500));
  // Blender 3 carries the website's own 10% discount; the card shows today's price.
  await expect(cards.nth(2).getByTestId("offer-price")).toHaveText(money(4050));
  // Round buttons by default (one product).
  await expect(cards.first().getByTestId("choice-mark")).toHaveAttribute("data-kind", "radio");
  // Variations and stock come from the store.
  await expect(cards.nth(1).getByTestId("variant-1L")).toBeVisible();
  await expect(cards.nth(1).getByTestId("variant-2L")).toBeDisabled();

  // Mobile: two cards per row.
  await page.getByTestId("device-mobile").click();
  await frame.locator('[data-testid="offer-grid"]').scrollIntoViewIfNeeded();
  await cards.first().click({ position: { x: 4, y: 4 } });
  await page.getByTestId("tab-style").click();
  await page.getByTestId("style-columns").fill("2");
  await expect.poll(async () => {
    const [a, b, c] = await Promise.all([0, 1, 2].map((i) => cards.nth(i).boundingBox()));
    return Math.abs(a.y - b.y) < 3 && c.y > a.y + 50 && a.width < 200;
  }).toBe(true);
  await page.screenshot({ path: `${SHOTS}/offers-mobile-two-columns.png` });
  await page.getByTestId("device-desktop").click();

  // Spacing, colour and type of the cards.
  await page.getByTestId("style-cardRadius").fill("24");
  await expect(cards.first()).toHaveCSS("border-radius", "24px");
  await page.getByTestId("style-titleSize").fill("18");
  await expect(cards.first().getByTestId("card-title")).toHaveCSS("font-size", "18px");
  await page.getByTestId("style-gap").fill("30");
  await expect(frame.getByTestId("offer-grid")).toHaveCSS("gap", "30px");

  // Radio buttons or tick boxes.
  await page.getByTestId("tab-content").click();
  await page.getByTestId("field-selectionMode").selectOption("multiple");
  await expect(cards.first().getByTestId("choice-mark")).toHaveAttribute("data-kind", "checkbox");

  // A label of the card, and an individual offer.
  // Tapping a card on the canvas already opened its row; open it by hand otherwise.
  if (!(await page.getByTestId("binding-title-input").isVisible())) await page.getByTestId("binding-0").getByRole("button").first().click();
  await page.getByTestId("binding-title-input").fill("ঈদ অফার ব্লেন্ডার");
  await page.getByTestId("binding-badge-input").fill("সেরা বিক্রি");
  await expect(cards.first().getByTestId("card-title")).toHaveText("ঈদ অফার ব্লেন্ডার");
  await expect(cards.first().getByTestId("card-badge")).toHaveText("সেরা বিক্রি");

  await page.getByTestId("offer-type").selectOption("percent");
  await page.getByTestId("offer-value").fill("20");
  await expect(cards.first().getByTestId("offer-price")).toHaveText(money(2800));
  await expect(cards.first().getByTestId("regular-price")).toHaveText(money(3500));
  await expect(page.getByTestId("offer-preview")).toContainText("saving");
  await expect(cards.nth(4).getByTestId("offer-price")).toHaveText(money(5500));
  await page.getByTestId("apply-offer-all").click();
  await expect(cards.nth(4).getByTestId("offer-price")).toHaveText(money(4400));
  await expect(cards.nth(1).getByTestId("savings")).toBeVisible();
  await page.getByTestId("offer-builder").scrollIntoViewIfNeeded();
  await page.screenshot({ path: `${SHOTS}/offers-offer-builder.png` });

  // The live summary, priced by the server.
  await page.getByTestId("library-discount_summary").click();
  await waitForSaved(page);
  await page.screenshot({ path: `${SHOTS}/offers-desktop-editor.png` });

  await page.getByTestId("device-mobile").click();
  await page.getByTestId("preview-toggle").click();
  const summary = page.getByTestId("offer-summary");
  await cards.first().click();
  await expect(summary).toContainText("2,800");
  // A chosen card is visibly filled, not just outlined.
  await expect(cards.first().getByTestId("choice-mark")).not.toHaveCSS("background-color", "rgb(255, 255, 255)");
  await expect(cards.nth(2).getByTestId("choice-mark")).toHaveCSS("background-color", "rgb(255, 255, 255)");
  await cards.nth(4).click();
  await expect(summary).toContainText("7,200");
  await expect(summary).toContainText("৳");
  await expect(cards.first().getByTestId("choice-mark")).toHaveAttribute("data-kind", "checkbox");
  await cards.first().click();
  await expect(summary).toContainText("4,400");
  await page.screenshot({ path: `${SHOTS}/offers-mobile-preview.png` });
  await page.getByTestId("preview-toggle").click();
  await page.getByTestId("device-desktop").click();

  // Save, reload: nothing is lost.
  await waitForSaved(page);
  const saved = serverFunnel(funnel.id).content.page.sections;
  const grid = saved.flatMap((s) => s.children ?? [s]).find((n) => n.type === "product_offer_selector");
  expect(grid.productBindings).toHaveLength(8);
  expect(grid.productBindings[0].offer).toEqual({ type: "percent", value: 20 });
  expect(grid.productBindings[0].display.title).toBe("ঈদ অফার ব্লেন্ডার");
  expect(grid.props.selectionMode).toBe("multiple");
  expect(grid.style.mobile.columns).toBe(2);

  await page.reload();
  await expect(page.getByTestId("funnel-editor")).toBeVisible();
  await expect(page.locator('[data-testid="canvas-frame"] [data-fn-offer]')).toHaveCount(8);
  await expect(page.locator('[data-testid="canvas-frame"] [data-fn-offer]').first().getByTestId("offer-price")).toHaveText(money(2800));

  // Publish keeps the offers; later draft edits do not reach the live version.
  await publishNow(page);
  await expect(page.getByText("Version 1 published")).toBeVisible();
  const slug = serverFunnel(funnel.id).slug ?? funnel.slug;

  await page.locator('[data-testid="canvas-frame"] [data-fn-offer]').first().click();
  await page.getByTestId("tab-content").click();
  await page.getByTestId("offer-value").fill("50");
  await waitForSaved(page);

  const server = serverFunnel(funnel.id);
  const draftGrid = server.content.page.sections.flatMap((s) => s.children ?? [s]).find((n) => n.type === "product_offer_selector");
  const liveGrid = server.published_content.page.sections.flatMap((s) => s.children ?? [s]).find((n) => n.type === "product_offer_selector");
  expect(draftGrid.productBindings[0].offer.value).toBe(50);
  expect(liveGrid.productBindings[0].offer.value).toBe(20);

  const offers = await request.get(`/api/v2/funnels/${slug}/offers`);
  expect(offers.ok()).toBe(true);
  const live = (await offers.json()).offers;
  const firstKey = liveGrid.productBindings[0].key;
  expect(live[firstKey].price.offer.amount).toBe(2800);

  // A request that tries to set its own price gets the published price anyway.
  const forged = await request.post(`/api/v2/funnels/${slug}/quote`, {
    data: { selections: [{ key: firstKey, quantity: 1, price: 1, total: 1 }], total: 1 },
  });
  expect((await forged.json()).totals.product_subtotal.amount).toBe(2800);
});

test("a bundle of two products at one price", async ({ page }) => {
  const funnel = createFunnel("Offers: bundle");
  await openEditor(page, funnel.id);
  await page.getByTestId("library-bundle_offer_selector").click();
  await expect(page.getByTestId("product-picker")).toBeVisible();
  await page.getByTestId("picker-search").fill("E2E");
  await page.getByTestId(`pick-${catalogue.blenders[0]}`).getByRole("button", { pressed: false }).first().click();
  await page.getByTestId(`pick-${catalogue.mug}`).getByRole("button", { pressed: false }).first().click();
  await page.getByTestId("picker-confirm").click();

  const card = page.locator('[data-testid="canvas-frame"] [data-fn-offer]');
  await expect(card).toHaveCount(1);
  await expect(card).toContainText("E2E Blender 1");
  await expect(card).toContainText("E2E Travel Mug");
  await expect(card.getByTestId("offer-price")).toHaveText(money(3900));

  await page.getByTestId("offer-type").selectOption("fixed_price");
  await page.getByTestId("offer-value").fill("3000");
  await expect(card.getByTestId("offer-price")).toHaveText(money(3000));
  await expect(page.getByTestId("offer-preview")).toContainText("per bundle");
  await expect(card.getByTestId("regular-price")).toHaveText(money(3900));
  await page.getByTestId("offer-free-delivery").click();
  await expect(card).toContainText("ফ্রি ডেলিভারি");

  await waitForSaved(page);
  const bundle = serverFunnel(funnel.id).content.page.sections.flatMap((s) => s.children ?? [s]).find((n) => n.type === "bundle_offer_selector");
  expect(bundle.productBindings[0].items.map((i) => i.productId)).toEqual([catalogue.blenders[0], catalogue.mug]);
  expect(bundle.productBindings[0].offer).toEqual({ type: "fixed_price", value: 3000, freeDelivery: true });
});

test("a product page from single blocks: card, price, badge, variations, quantity, photos", async ({ page }) => {
  const funnel = createFunnel("Offers: single product");
  await openEditor(page, funnel.id);
  await page.getByTestId("library-product_card").click();
  await expect(page.getByTestId("product-picker")).toBeVisible();
  await page.getByTestId("picker-search").fill("E2E Blender 2");
  await page.getByTestId(`pick-${catalogue.blenders[1]}-any`).click();
  await page.getByTestId("picker-confirm").click();

  for (const type of ["product_gallery", "product_price", "discount_badge", "product_variations", "quantity_selector", "discount_summary"]) {
    await page.getByTestId(`library-${type}`).click();
  }
  const frame = page.getByTestId("canvas-frame");
  await expect(frame.locator("[data-fn-offer]")).toHaveCount(1);
  await expect(frame.getByTestId("gallery-main")).toBeVisible();

  // 10% off; the badge shows the real discount on its own.
  await frame.locator("[data-fn-offer]").click();
  await page.getByTestId("tab-content").click();
  await page.getByTestId("offer-type").selectOption("percent");
  await page.getByTestId("offer-value").fill("10");
  await expect(frame.getByTestId("discount-badge")).toHaveText("-10%");
  await expect(frame.locator(".fn-product-price").getByTestId("offer-price")).toHaveText(money(3600));

  // Preview: choose the 1L variation (2L is sold out), change the quantity.
  await page.getByTestId("preview-toggle").click();
  await expect(frame.locator(".fn-product-variations").getByTestId("variant-2L")).toBeDisabled();
  await frame.locator(".fn-product-variations").getByTestId("variant-1L").click();
  await frame.locator(".fn-quantity").getByRole("button", { name: /\+/ }).click();
  await expect(frame.locator(".fn-quantity").getByTestId("quantity-value")).toHaveText("2");
  await expect(frame.getByTestId("offer-summary")).toContainText("7,200");
  await expect(frame.getByTestId("offer-summary")).toContainText("E2E Blender 2");
  await page.screenshot({ path: `${SHOTS}/offers-single-product.png` });
});

test("offers are checked against the store before publishing", async ({ page }) => {
  const funnel = createFunnel("Offers: publish checks");
  await openEditor(page, funnel.id);
  await page.getByTestId("library-product_card").click();
  await page.getByTestId("picker-search").fill("E2E Blender 4");
  await page.getByTestId(`pick-${catalogue.blenders[3]}`).getByRole("button", { pressed: false }).first().click();
  await page.getByTestId("picker-confirm").click();
  await page.getByTestId("tab-content").click();
  await page.getByTestId("offer-type").selectOption("fixed_price");
  // No price typed yet: the draft saves, publishing explains what is missing.
  await waitForSaved(page);
  await page.getByTestId("publish").click();
  await expect(page.getByTestId("publish-errors")).toContainText("Enter the amount");
  await page.getByRole("button", { name: "Close" }).last().click();

  await page.getByTestId("offer-value").fill("2000");
  await page.getByTestId("offer-type").selectOption("percent");
  await page.getByTestId("offer-value").fill("5");
  await waitForSaved(page);
  await publishNow(page);
  await expect(page.getByText("Version 1 published")).toBeVisible();
});

test("product search is closed to roles that cannot edit", async ({ page }) => {
  const funnel = createFunnel("Offers: permissions");
  await openEditor(page, funnel.id, "viewer");
  const status = await page.evaluate(async () => {
    const session = JSON.parse(sessionStorage.getItem("misron:funnel-editor-session"));
    const headers = { Accept: "application/json", Authorization: `Bearer ${session.token}` };
    const search = await fetch("/api/v2/funnel-editor/products?q=blender", { headers });
    const resolve = await fetch("/api/v2/funnel-editor/offers/resolve", { method: "POST", headers: { ...headers, "Content-Type": "application/json" }, body: JSON.stringify({ bindings: [] }) });
    return [search.status, resolve.status];
  });
  expect(status).toEqual([403, 403]);
  await expect(canvasNodes(page, "productcard")).toHaveCount(0);
});
