import { expect, test } from "@playwright/test";
import { draftPage, publishPage, seed } from "./helpers.js";

/**
 * The live page at /funnel/[slug]: closed pages, search and sharing tags, the
 * cache refresh when a page changes, the sitemap, and secret draft links.
 */

/**
 * What a visitor gets at an address: the page ("open", HTTP 200) or the store's not-found
 * screen ("closed", a real HTTP 404, marked noindex, carrying none of the page).
 */
async function state(request, path) {
  const response = await request.get(path);
  const html = await response.text();
  const open = html.includes("fn-root");
  if (open) {
    expect(response.status()).toBe(200);
  } else {
    expect(response.status()).toBe(404);
    expect(html).toMatch(/<meta name="robots" content="noindex/);
  }
  return open ? "open" : "closed";
}

let counter = 0;
function freshProduct(name = "PUB Product", price = 2400) {
  counter += 1;
  const tag = `${Date.now().toString(36)}${counter}`;
  return { ...seed("product", `${name} ${tag}`, price, 20), name: `${name} ${tag}` };
}

function pageFor(product, headline = "Public page headline") {
  return {
    schemaVersion: 1,
    page: {
      settings: { language: "en" },
      sections: [
        { id: "hero_001", type: "section", children: [{ id: "heading_001", type: "heading", props: { text: headline, level: "h1" } }] },
        {
          id: "offers_001", type: "product_offer_selector", props: { selectionMode: "single" },
          productBindings: [{ key: "offer_one", items: [{ productId: product.id, variant: null, quantity: 1, lockVariant: false }], offer: { type: "percent", value: 10 } }],
        },
        {
          id: "checkout_001", type: "checkout_form", props: {},
          children: [{ id: "buy_001", type: "purchase_button", props: { text: "Order now" } }],
        },
      ],
    },
  };
}

test.beforeAll(() => {
  seed("cache-clear");
  seed("products");
  seed("checkout-setup");
});

test("a page that does not exist, is a draft, or is unpublished is closed, and closes at once when unpublished", async ({ request }) => {
  expect(await state(request, "/funnel/no-such-page-anywhere")).toBe("closed");

  const product = freshProduct();
  const draft = draftPage("Draft only", pageFor(product));
  expect(await state(request, `/funnel/${draft.slug}`)).toBe("closed");

  const live = publishPage("To unpublish", pageFor(freshProduct()));
  expect(await state(request, `/funnel/${live.slug}`)).toBe("open");
  seed("unpublish", live.id);
  // The backend told the storefront to drop its cached copy, so there is no wait.
  expect(await state(request, `/funnel/${live.slug}`)).toBe("closed");
});

test("archived and expired pages answer a real 404, and a bad preview link too", async ({ request }) => {
  const archived = publishPage("To archive", pageFor(freshProduct()));
  expect(await state(request, `/funnel/${archived.slug}`)).toBe("open");
  seed("archive", archived.id);
  expect(await state(request, `/funnel/${archived.slug}`)).toBe("closed");

  const expiring = publishPage("To expire", pageFor(freshProduct()));
  expect(await state(request, `/funnel/${expiring.slug}`)).toBe("open");
  // Its end time passes; the scheduler tidies the status and refreshes the cache.
  seed("funnel-time", expiring.id, "unpublish_at", -1);
  seed("reconcile");
  expect(await state(request, `/funnel/${expiring.slug}`)).toBe("closed");

  expect(await state(request, `/funnel/${archived.slug}/preview/${"A".repeat(40)}`)).toBe("closed");
  expect(await state(request, "/funnel/Not_A_Valid_Slug")).toBe("closed");
});

test("a live page carries its search and sharing tags, structured data and language", async ({ page }) => {
  const product = freshProduct("PUB Blender", 3000);
  const funnel = publishPage("Search tags page", pageFor(product));
  const response = await page.goto(`/funnel/${funnel.slug}`);
  expect(response.status()).toBe(200);

  await expect(page).toHaveTitle("Search tags page");
  const meta = (selector) => page.locator(selector).first().getAttribute("content");
  expect(await meta('meta[name="robots"]')).toContain("index");
  expect(await meta('meta[name="robots"]')).not.toContain("noindex");
  expect(await page.locator('link[rel="canonical"]').first().getAttribute("href")).toBe(`http://localhost:3100/funnel/${funnel.slug}`);
  expect(await meta('meta[property="og:title"]')).toBe("Search tags page");
  expect(await meta('meta[property="og:type"]')).toBe("website");
  expect(await meta('meta[name="twitter:card"]')).toBe("summary");
  await expect(page.locator(".fn-root")).toHaveAttribute("lang", "en");

  const ld = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent());
  expect(ld["@type"]).toBe("Product");
  expect(ld.name).toBe(product.name);
  // 3,000 with 10% off.
  expect(ld.offers.price).toBe("2700.00");
  expect(ld.offers.availability).toBe("https://schema.org/InStock");
});

test("search details edited in the editor reach the live page at once, and 'hide from search' sets noindex", async ({ page }) => {
  const funnel = publishPage("Editable tags", pageFor(freshProduct()));
  const { url } = seed("handoff", "e2e-admin@funnel.test", funnel.id);
  await page.goto(url);
  await expect(page.getByTestId("funnel-editor")).toBeVisible();
  await page.getByTestId("toolbar-title").click();

  await page.getByTestId("seo-title").fill("A much better title");
  await page.getByTestId("seo-description").fill("Why this page is worth opening.");
  await page.getByTestId("seo-noindex").click();
  await expect(page.getByTestId("seo-snippet")).toContainText("A much better title");
  await page.getByTestId("seo-save").click();
  await expect(page.getByText("Search and sharing settings saved")).toBeVisible();

  const live = await page.context().newPage();
  await live.goto(`/funnel/${funnel.slug}`);
  await expect(live).toHaveTitle("A much better title");
  expect(await live.locator('meta[name="description"]').getAttribute("content")).toBe("Why this page is worth opening.");
  expect(await live.locator('meta[name="robots"]').first().getAttribute("content")).toContain("noindex");
  await live.close();
});

test("the sitemap lists live pages that are not hidden, and robots.txt keeps private places out", async ({ request }) => {
  const shown = publishPage("In the sitemap", pageFor(freshProduct()));
  const hidden = publishPage("Hidden from search", pageFor(freshProduct()));
  const { url } = seed("handoff", "e2e-admin@funnel.test", hidden.id);
  expect(url).toContain("handoff=");
  // Hide it through the API the editor uses, via the same service call.
  seed("noindex", hidden.id);
  const draft = draftPage("Not live", pageFor(freshProduct()));

  const sitemap = await (await request.get("/funnel/sitemap.xml")).text();
  expect(sitemap).toContain(`/funnel/${shown.slug}<`);
  expect(sitemap).not.toContain(`/funnel/${hidden.slug}<`);
  expect(sitemap).not.toContain(`/funnel/${draft.slug}<`);

  const robots = await (await request.get("/robots.txt")).text();
  expect(robots).toContain("Disallow: /builder");
  expect(robots).toContain("Disallow: /funnel/*/preview/");
  expect(robots).toContain("/funnel/sitemap.xml");
});

test("the cache refresh call needs the shared secret", async ({ request }) => {
  const refused = await request.post("/api/funnel/revalidate", { data: { slugs: ["x-page"] } });
  expect(refused.status()).toBe(401);
  const wrong = await request.post("/api/funnel/revalidate", { data: { slugs: ["x-page"] }, headers: { Authorization: "Bearer nope" } });
  expect(wrong.status()).toBe(401);
  const empty = await request.post("/api/funnel/revalidate", { data: { slugs: ["Not A Slug!"] }, headers: { Authorization: "Bearer e2e-revalidate-secret" } });
  expect(empty.status()).toBe(422);
  const ok = await request.post("/api/funnel/revalidate", { data: { slugs: ["x-page"] }, headers: { Authorization: "Bearer e2e-revalidate-secret" } });
  expect(ok.status()).toBe(200);
});

test("a secret draft link shows the unpublished page, cannot place orders, and stops when switched off", async ({ page, request }) => {
  const product = freshProduct("PUB Preview Product", 1800);
  const draft = draftPage("Preview me", pageFor(product, "Work in progress headline"));
  const link = seed("preview-link", draft.id, 24);

  // The ordinary address stays closed.
  expect(await state(request, `/funnel/${draft.slug}`)).toBe("closed");

  const ordersBefore = seed("orders").count;
  await page.goto(link.url);
  await expect(page.getByTestId("preview-link-banner")).toBeVisible();
  await expect(page.getByRole("heading", { name: "Work in progress headline" })).toBeVisible();
  await expect(page.getByText(product.name).first()).toBeVisible();
  expect(await page.locator('meta[name="robots"]').first().getAttribute("content")).toContain("noindex");
  expect(await page.locator('meta[name="referrer"]').first().getAttribute("content")).toBe("no-referrer");

  // The order form runs for trying, but ordering only shows the confirmation.
  await expect(page.getByTestId("preview-banner")).toBeVisible();
  await expect(page.locator("[data-fn-offer]").first()).toBeVisible();
  await page.locator("[data-fn-offer]").first().click();
  await page.getByTestId("input-name").fill("Preview Tester");
  await page.getByTestId("input-phone").fill("01712345679");
  await page.getByTestId("input-address").fill("Somewhere in Dhaka, road 4");
  const district = page.getByTestId("input-district");
  await district.selectOption({ index: 1 });
  await page.getByTestId("order-button").click();
  await expect(page.getByTestId("order-confirmation").first()).toContainText("PREVIEW");
  expect(seed("orders").count).toBe(ordersBefore);

  // Switching the link off closes it at once.
  seed("revoke-link", draft.id, link.id);
  expect(await state(request, new URL(link.url).pathname)).toBe("closed");
});

test("a wrong secret never shows the page", async ({ request }) => {
  const draft = draftPage("Guarded", pageFor(freshProduct()));
  seed("preview-link", draft.id, 24);
  expect(await state(request, `/funnel/${draft.slug}/preview/${"a".repeat(40)}`)).toBe("closed");
  expect(await state(request, `/funnel/${draft.slug}/preview/abc`)).toBe("closed");
});

test("a scheduled page opens by itself and a page past its end time closes", async ({ request }) => {
  const funnel = draftPage("Scheduled live", pageFor(freshProduct()));
  // Schedule through the service, then act out the clock passing.
  seed("schedule", funnel.id, 30);
  expect(await state(request, `/funnel/${funnel.slug}`)).toBe("closed");

  seed("funnel-time", funnel.id, "scheduled_at", -1);
  // The scheduler's minute tick: it flips the status and refreshes the storefront.
  expect(seed("reconcile").updated).toContain(funnel.slug);
  expect(await state(request, `/funnel/${funnel.slug}`)).toBe("open");

  seed("funnel-time", funnel.id, "unpublish_at", -1);
  expect(seed("reconcile").updated).toContain(funnel.slug);
  expect(await state(request, `/funnel/${funnel.slug}`)).toBe("closed");
});
