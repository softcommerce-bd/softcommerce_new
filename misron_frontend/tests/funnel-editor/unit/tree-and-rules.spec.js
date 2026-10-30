import { expect, test } from "@playwright/test";
import registry from "../fixtures/registry.json" with { type: "json" };
import { allowsChild, placementProblem } from "../../../funnel/core/rules.js";
import { locatePath } from "../../../funnel/core/paths.js";
import {
  ancestorsOf, cloneWithFreshIds, collectIds, collectOfferKeys, findNode, isWithin, makeId, moveNode,
} from "../../../funnel/core/tree.js";

function sampleDoc() {
  return {
    schemaVersion: 1,
    page: {
      sections: [
        {
          id: "sec_a",
          type: "section",
          children: [
            { id: "head_a", type: "heading", props: { text: "A" } },
            { id: "text_a", type: "text", props: { text: "B" } },
            { id: "cols_a", type: "columns", children: [{ id: "col_1", type: "column", children: [] }, { id: "col_2", type: "column", children: [] }] },
          ],
        },
        { id: "sec_b", type: "section", children: [] },
      ],
    },
  };
}

test("finds nodes, ancestors and containment", () => {
  const doc = sampleDoc();
  const located = findNode(doc, "col_2");
  expect(located.parent.id).toBe("cols_a");
  expect(located.index).toBe(1);
  expect(located.depth).toBe(3);
  expect(ancestorsOf(doc, "col_2").map((n) => n.id)).toEqual(["sec_a", "cols_a"]);
  expect(isWithin(doc, "sec_a", "col_1")).toBe(true);
  expect(isWithin(doc, "sec_b", "col_1")).toBe(false);
  expect(findNode(doc, "missing")).toBeNull();
});

test("move uses the index measured before the move", () => {
  const doc = sampleDoc();
  // Dropping the heading after the text (index 2 in the list as drawn).
  expect(moveNode(doc, "head_a", "sec_a", 2)).toBe(true);
  expect(doc.page.sections[0].children.map((n) => n.id)).toEqual(["text_a", "head_a", "cols_a"]);
  // Dropping it right where it already is changes nothing.
  expect(moveNode(doc, "head_a", "sec_a", 1)).toBe(false);
  expect(moveNode(doc, "head_a", "sec_a", 2)).toBe(false);
  // Into another section, and never into itself.
  expect(moveNode(doc, "head_a", "sec_b", 0)).toBe(true);
  expect(doc.page.sections[1].children[0].id).toBe("head_a");
  expect(moveNode(doc, "cols_a", "col_1", 0)).toBe(false);
});

test("copies get fresh ids and their own offers", () => {
  const node = {
    id: "card_x",
    type: "container",
    children: [
      { id: "offer_block", type: "product_card", productBindings: [{ key: "offer_one", items: [{ productId: 3 }] }] },
      { id: "price_ref", type: "product_price", props: { offerKey: "offer_one" } },
      { id: "foreign_ref", type: "product_price", props: { offerKey: "offer_elsewhere" } },
    ],
  };
  const takenIds = new Set(["card_x", "offer_block", "price_ref", "foreign_ref"]);
  const takenKeys = new Set(["offer_one", "offer_elsewhere"]);
  const copy = cloneWithFreshIds(node, takenIds, takenKeys);

  const ids = [copy.id, ...copy.children.map((c) => c.id)];
  expect(new Set(ids).size).toBe(4);
  for (const id of ids) {
    expect(id).toMatch(/^[A-Za-z0-9_-]{3,64}$/);
    expect(["card_x", "offer_block", "price_ref", "foreign_ref"]).not.toContain(id);
  }
  const newKey = copy.children[0].productBindings[0].key;
  expect(newKey).not.toBe("offer_one");
  expect(copy.children[1].props.offerKey).toBe(newKey);
  // A reference to an offer outside the copy is left alone.
  expect(copy.children[2].props.offerKey).toBe("offer_elsewhere");
  // The original is untouched.
  expect(node.children[0].productBindings[0].key).toBe("offer_one");
});

test("ids are unique and valid", () => {
  const taken = new Set();
  for (let i = 0; i < 200; i++) makeId("promo_hero", taken);
  expect(taken.size).toBe(200);
  for (const id of taken) expect(id).toMatch(/^promohero_[a-z0-9]{6}$/);
});

test("placement rules follow the server registry", () => {
  const doc = sampleDoc();
  expect(allowsChild(registry, "root", "section")).toBe(true);
  expect(allowsChild(registry, "root", "heading")).toBe(false);
  expect(allowsChild(registry, "section", "heading")).toBe(true);
  expect(allowsChild(registry, "section", "section")).toBe(false);
  expect(allowsChild(registry, "columns", "heading")).toBe(false);
  expect(allowsChild(registry, "column", "heading")).toBe(true);

  expect(placementProblem(registry, doc, null, "heading")).toBe("nesting");
  expect(placementProblem(registry, doc, "sec_a", "heading")).toBeNull();
  expect(placementProblem(registry, doc, "col_1", "cols_a", "sec_a")).toBe("nesting");

  doc.page.sections.push({ id: "sticky_1", type: "sticky_cta" });
  expect(placementProblem(registry, doc, null, "sticky_cta")).toBe("limit");
  // Moving the existing one is not adding a second.
  expect(placementProblem(registry, doc, null, "sticky_cta", "sticky_1")).toBeNull();
});

test("collects ids and offer keys", () => {
  const doc = sampleDoc();
  expect(collectIds(doc).size).toBe(7);
  doc.page.sections[1].children.push({ id: "sel_1", type: "product_offer_selector", productBindings: [{ key: "offer_a" }, { key: "offer_b" }] });
  expect([...collectOfferKeys(doc)]).toEqual(["offer_a", "offer_b"]);
});

test("server error paths point at blocks and fields", () => {
  const doc = sampleDoc();
  expect(locatePath(doc, "page.sections[0].children[1].props.text")).toEqual({ nodeId: "text_a", field: "props.text" });
  expect(locatePath(doc, "page.sections[0].children[2].children[1].style.mobile.paddingTop")).toEqual({ nodeId: "col_2", field: "style.mobile.paddingTop" });
  expect(locatePath(doc, "page.sections[1].children")).toEqual({ nodeId: "sec_b", field: "children" });
  expect(locatePath(doc, "page.sections[1]")).toEqual({ nodeId: "sec_b", field: null });
  expect(locatePath(doc, "page.sections")).toEqual({ nodeId: null, field: null });
  expect(locatePath(doc, "page.settings.accentColor")).toEqual({ nodeId: null, field: null });
});
