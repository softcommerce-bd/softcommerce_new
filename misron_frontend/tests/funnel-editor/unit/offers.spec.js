import { expect, test } from "@playwright/test";
import registry from "../fixtures/registry.json" with { type: "json" };
import { buildCss } from "../../../funnel/core/style.js";
import { createInitialState, editorReducer } from "../../../funnel/editor/store.js";
import {
  addBundleItems, addProducts, applyOfferToAll, insertBlock, moveBinding, removeBinding, setOfferType, updateBinding,
} from "../../../funnel/editor/operations.js";

function initial() {
  return createInitialState({
    funnel: { id: 1, title: "Offers", slug: "offers", status: "draft", draft: { content: { schemaVersion: 1, page: { sections: [] } }, revision: 1 } },
    registry,
    user: { id: 1, permissions: { edit_funnel: true, publish_funnel: true } },
  });
}

const edit = (state, recipe, extra = {}) => editorReducer(state, { type: "edit", recipe, ...extra });
const picked = (...ids) => ids.map((productId) => ({ productId, variant: null, quantity: 1 }));

function withGrid(type = "product_offer_selector") {
  let state = initial();
  state = edit(state, (d) => insertBlock(d, registry, { type, parentId: null, index: 0 }), { select: "result" });
  return state;
}

const blockOf = (state, type) => state.doc.page.sections.flatMap((s) => s.children ?? [s]).find((n) => n.type === type);

test("adding a product block asks for products straight away", () => {
  const state = withGrid();
  const node = blockOf(state, "product_offer_selector");
  expect(state.picker).toEqual({ nodeId: node.id, purpose: "add" });
  // Blocks without products do not.
  const plain = edit(initial(), (d) => insertBlock(d, registry, { type: "heading", parentId: null, index: 0, wrap: true }), { select: "result" });
  expect(plain.picker).toBeNull();
});

test("products become one card each, with unique offer keys, and repeats are skipped", () => {
  let state = withGrid();
  const id = blockOf(state, "product_offer_selector").id;
  state = edit(state, (d) => addProducts(d, registry, id, picked(11, 12, 13)));
  let bindings = blockOf(state, "product_offer_selector").productBindings;
  expect(bindings.map((b) => b.items[0].productId)).toEqual([11, 12, 13]);
  expect(new Set(bindings.map((b) => b.key)).size).toBe(3);
  for (const b of bindings) {
    expect(b.key).toMatch(/^offer_[a-z0-9]{6}$/);
    expect(b.offer).toEqual({ type: "none" });
  }

  state = edit(state, (d) => addProducts(d, registry, id, picked(12, 14)));
  bindings = blockOf(state, "product_offer_selector").productBindings;
  expect(bindings.map((b) => b.items[0].productId)).toEqual([11, 12, 13, 14]);
});

test("a grid is capped by the registry limit", () => {
  let state = withGrid();
  const id = blockOf(state, "product_offer_selector").id;
  state = edit(state, (d) => addProducts(d, registry, id, picked(...Array.from({ length: 45 }, (_, i) => 100 + i))));
  expect(blockOf(state, "product_offer_selector").productBindings).toHaveLength(registry.blocks.product_offer_selector.bindings[1]);
});

test("a product card holds one product and keeps its offer when the product changes", () => {
  let state = withGrid("product_card");
  const id = blockOf(state, "product_card").id;
  state = edit(state, (d) => addProducts(d, registry, id, picked(21)));
  const key = blockOf(state, "product_card").productBindings[0].key;
  state = edit(state, (d) => updateBinding(d, id, key, (b) => { b.offer = { type: "percent", value: 15 }; b.display = { title: "Old title", badge: "New" }; }));
  state = edit(state, (d) => addProducts(d, registry, id, picked(22, 23)));
  const bindings = blockOf(state, "product_card").productBindings;
  expect(bindings).toHaveLength(1);
  expect(bindings[0].key).toBe(key);
  expect(bindings[0].items.map((i) => i.productId)).toEqual([22]);
  expect(bindings[0].offer).toEqual({ type: "percent", value: 15 });
  expect(bindings[0].display.badge).toBe("New");
  expect(bindings[0].display.title).toBeUndefined();
});

test("a bundle collects its products with fixed variations", () => {
  let state = withGrid("bundle_offer_selector");
  const id = blockOf(state, "bundle_offer_selector").id;
  state = edit(state, (d) => addProducts(d, registry, id, [{ productId: 31, variant: "M", quantity: 1 }, { productId: 32, variant: null, quantity: 1 }]));
  let [bundle] = blockOf(state, "bundle_offer_selector").productBindings;
  expect(bundle.items).toEqual([
    { productId: 31, variant: "M", quantity: 1, lockVariant: true },
    { productId: 32, variant: null, quantity: 1, lockVariant: false },
  ]);
  state = edit(state, (d) => addBundleItems(d, id, bundle.key, [{ productId: 33, variant: "L", quantity: 2 }]));
  [bundle] = blockOf(state, "bundle_offer_selector").productBindings;
  expect(bundle.items.map((i) => [i.productId, i.quantity])).toEqual([[31, 1], [32, 1], [33, 2]]);
});

test("reorder, remove, and one offer for every card", () => {
  let state = withGrid();
  const id = blockOf(state, "product_offer_selector").id;
  state = edit(state, (d) => addProducts(d, registry, id, picked(1, 2, 3)));
  const keys = blockOf(state, "product_offer_selector").productBindings.map((b) => b.key);

  state = edit(state, (d) => moveBinding(d, id, keys[2], -1));
  expect(blockOf(state, "product_offer_selector").productBindings.map((b) => b.key)).toEqual([keys[0], keys[2], keys[1]]);
  const unchanged = state;
  state = edit(state, (d) => moveBinding(d, id, keys[0], -1));
  expect(state.doc).toBe(unchanged.doc);

  state = edit(state, (d) => removeBinding(d, id, keys[2]));
  expect(blockOf(state, "product_offer_selector").productBindings.map((b) => b.key)).toEqual([keys[0], keys[1]]);

  state = edit(state, (d) => updateBinding(d, id, keys[0], (b) => { b.offer = { type: "percent", value: 10, freeDelivery: true }; b.display = { title: "Mine" }; }));
  state = edit(state, (d) => applyOfferToAll(d, id, { type: "percent", value: 10, freeDelivery: true }));
  const [first, second] = blockOf(state, "product_offer_selector").productBindings;
  expect(second.offer).toEqual(first.offer);
  expect(second.offer).not.toBe(first.offer);
  expect(first.display.title).toBe("Mine");
  expect(second.items[0].productId).toBe(2);
});

test("changing the offer type drops settings of the old type but keeps dates and limits", () => {
  const base = { type: "percent", value: 15, freeDelivery: true, startsAt: "2026-12-01T00:00:00+06:00", maxPerOrder: 3 };
  const bxgy = setOfferType(base, "bxgy");
  expect(bxgy).toEqual({ type: "bxgy", freeDelivery: true, startsAt: "2026-12-01T00:00:00+06:00", maxPerOrder: 3, buyQty: 2, getQty: 1 });
  expect(setOfferType(bxgy, "none")).toEqual({ type: "none", freeDelivery: true, startsAt: "2026-12-01T00:00:00+06:00", maxPerOrder: 3 });
  expect(setOfferType({ type: "none" }, "fixed_price")).toEqual({ type: "fixed_price" });
  expect(setOfferType({ type: "none" }, "quantity_discount").tiers).toHaveLength(1);
});

test("card style settings only ever produce allowlisted CSS", () => {
  const css = buildCss({
    schemaVersion: 1,
    page: {
      sections: [
        {
          id: "grid_001",
          type: "product_offer_selector",
          style: {
            desktop: {
              columns: 4, gap: 20, cardBackground: "#ffffff", cardRadius: 16, cardShadow: "md", titleSize: 17, titleColor: "#111111",
              priceColor: "#e8590c", priceSize: 24, selectedColor: "#2f9e44", savingColor: "#12805c",
            },
            mobile: { columns: 2 },
          },
        },
        { id: "grid_002", type: "product_offer_selector", style: { desktop: { cardBackground: "red;}body{display:none", selectedColor: "url(x)", titleSize: "9999", cardRadius: -5 } } },
      ],
    },
  });
  expect(css).toContain(".fn-n-grid_001 .fn-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:20px}");
  expect(css).toContain(".fn-n-grid_001 .fn-pcard{background-color:#ffffff;border-radius:16px;box-shadow:");
  expect(css).toContain(".fn-n-grid_001 .fn-ptitle{font-size:17px;color:#111111}");
  expect(css).toContain(".fn-n-grid_001 .fn-pprice{color:#e8590c;font-size:24px}");
  expect(css).toContain(".fn-n-grid_001{--fn-sel:#2f9e44}");
  expect(css).toContain("@container funnel (max-width: 767.98px){.fn-n-grid_001 .fn-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}");
  // Hostile values are dropped or clamped, never written through.
  expect(css).not.toContain("red;}");
  expect(css).not.toContain("url(");
  expect(css).toContain(".fn-n-grid_002 .fn-ptitle{font-size:60px}");
  expect(css).toContain(".fn-n-grid_002 .fn-pcard{border-radius:0px}");
});
