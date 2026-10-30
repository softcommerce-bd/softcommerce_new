import { expect, test } from "@playwright/test";
import { formatWhen, fromLocalInput, nowLocalInput, toIsoWithOffset, toLocalInput } from "../../../funnel/core/dates.js";
import { slotProduct, slotState, unfilledSlots } from "../../../funnel/core/template.js";
import { createInitialState, editorReducer, pickDetails } from "../../../funnel/editor/store.js";
import registry from "../fixtures/registry.json" with { type: "json" };

// ── dates ──────────────────────────────────────────────────────────────────

test("a local time round-trips through an ISO time with the browser's offset", () => {
  const iso = toIsoWithOffset(new Date(2026, 11, 31, 18, 30));
  expect(iso).toMatch(/^2026-12-31T18:30:00[+-]\d{2}:\d{2}$/);
  expect(toLocalInput(iso)).toBe("2026-12-31T18:30");
  expect(fromLocalInput("2026-12-31T18:30")).toBe(iso);
});

test("empty or broken times give empty values, never an exception", () => {
  expect(toLocalInput("")).toBe("");
  expect(toLocalInput(null)).toBe("");
  expect(toLocalInput("not a date")).toBe("");
  expect(fromLocalInput("")).toBeNull();
  expect(fromLocalInput("nonsense")).toBeNull();
  expect(formatWhen("")).toBe("");
  expect(formatWhen("nonsense")).toBe("");
});

test("times shown to the admin are in their own zone and readable", () => {
  expect(formatWhen("2026-12-31T12:00:00Z", "en-GB")).toMatch(/31 Dec 2026/);
  expect(nowLocalInput()).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);
});

// ── template slots ─────────────────────────────────────────────────────────

const SLOTS = [
  { key: "main", label: "Product", bindings: { offer_1: 1, offer_2: 2 } },
  { key: "gift", label: "Gift", bindings: { gift_offer: 1 } },
];

function page(bindings) {
  return {
    schemaVersion: 1,
    page: {
      sections: [
        { id: "a_001", type: "section", children: [{ id: "sel_001", type: "product_offer_selector", productBindings: bindings.slice(0, 2) }] },
        { id: "b_001", type: "product_card", productBindings: bindings.slice(2) },
      ],
    },
  };
}

const empty = (key) => ({ key, items: [], offer: { type: "none" } });
const filled = (key, productId) => ({ key, items: [{ productId, variant: null, quantity: 1, lockVariant: false }], offer: { type: "none" } });

test("a slot is done only when every offer it fills has a product", () => {
  expect(slotState(page([empty("offer_1"), empty("offer_2"), empty("gift_offer")]), SLOTS)).toEqual({ main: false, gift: false });
  expect(slotState(page([filled("offer_1", 7), empty("offer_2"), empty("gift_offer")]), SLOTS)).toEqual({ main: false, gift: false });
  expect(slotState(page([filled("offer_1", 7), filled("offer_2", 7), empty("gift_offer")]), SLOTS)).toEqual({ main: true, gift: false });
  expect(slotState(page([filled("offer_1", 7), filled("offer_2", 7), filled("gift_offer", 9)]), SLOTS)).toEqual({ main: true, gift: true });
});

test("a slot whose offers were deleted from the page counts as not done, not as done", () => {
  expect(slotState(page([filled("offer_1", 7)]), SLOTS)).toEqual({ main: false, gift: false });
});

test("the unfilled slots and the product behind a slot can be read back", () => {
  const doc = page([filled("offer_1", 7), filled("offer_2", 7), empty("gift_offer")]);
  expect(unfilledSlots(doc, SLOTS).map((slot) => slot.key)).toEqual(["gift"]);
  expect(slotProduct(doc, SLOTS[0])).toBe(7);
  expect(slotProduct(doc, SLOTS[1])).toBeNull();
});

test("a slot with no offers is never done", () => {
  expect(slotState(page([]), [{ key: "x", bindings: {} }])).toEqual({ x: false });
});

// ── editor details ─────────────────────────────────────────────────────────

function server(overrides = {}) {
  return {
    id: 5, title: "Offer", internal_name: "Offer", slug: "offer", status: "scheduled", is_live: false, public_url: "https://shop.test/funnel/offer",
    has_unpublished_changes: false, meta_title: null, meta_description: null, published_version: { version_number: 2 },
    scheduled_at: "2026-12-01T10:00:00+06:00", unpublish_at: "2026-12-05T10:00:00+06:00",
    seo: { noindex: true, og_title: "Share me", og_description: null, canonical_url: null },
    og_image_upload_id: 12, og_image_url: "https://shop.test/uploads/share.jpg",
    template: { id: 3, key: "classic-single-product", name: "Classic", language: "en", slots: SLOTS, todos: ["Check the wording"] },
    draft: { content: { schemaVersion: 1, page: { sections: [] } }, revision: 4 },
    ...overrides,
  };
}

test("the editor knows the schedule, the search settings and the template of the page it opens", () => {
  const state = createInitialState({ funnel: server(), registry, user: { permissions: { edit_funnel: true, publish_funnel: true } } });
  expect(state.details.status).toBe("scheduled");
  expect(state.details.scheduledAt).toBe("2026-12-01T10:00:00+06:00");
  expect(state.details.unpublishAt).toBe("2026-12-05T10:00:00+06:00");
  expect(state.details.seo).toEqual({ noindex: true, ogTitle: "Share me", ogDescription: "", canonicalUrl: "" });
  expect(state.details.ogImageUrl).toBe("https://shop.test/uploads/share.jpg");
  expect(state.details.template.slots).toHaveLength(2);
});

test("a page without a schedule, search settings or template opens with neutral values", () => {
  const state = createInitialState({
    funnel: server({ status: "draft", scheduled_at: null, unpublish_at: null, seo: undefined, og_image_upload_id: null, og_image_url: null, template: null, published_version: null }),
    registry,
    user: { permissions: {} },
  });
  expect(state.details.scheduledAt).toBeNull();
  expect(state.details.seo.noindex).toBe(false);
  expect(state.details.template).toBeNull();
  expect(state.details.publishedVersion).toBeNull();
});

test("a server answer after publishing, scheduling or editing the details updates every detail at once", () => {
  let state = createInitialState({ funnel: server({ status: "draft", scheduled_at: null, unpublish_at: null }), registry, user: { permissions: { publish_funnel: true } } });
  state = editorReducer(state, { type: "publish/end", details: pickDetails(server({ status: "published", is_live: true, scheduled_at: null })) });
  expect(state.details.status).toBe("published");
  expect(state.details.isLive).toBe(true);
  expect(state.details.scheduledAt).toBeNull();
  expect(state.details.unpublishAt).toBe("2026-12-05T10:00:00+06:00");
  expect(state.publishing).toBe(false);
});

test("reloading after a restore or a product choice keeps the screen size and takes the server's page", () => {
  let state = createInitialState({ funnel: server(), registry, user: { permissions: { edit_funnel: true } } });
  state = editorReducer(state, { type: "device", device: "mobile" });
  const fresh = server({ draft: { content: { schemaVersion: 1, page: { sections: [{ id: "x_001", type: "section", children: [] }] } }, revision: 9 } });
  state = editorReducer(state, { type: "reload", funnel: fresh });
  expect(state.device).toBe("mobile");
  expect(state.revision).toBe(9);
  expect(state.doc.page.sections).toHaveLength(1);
  expect(state.version).toBe(0);
  expect(state.save.status).toBe("saved");
});
