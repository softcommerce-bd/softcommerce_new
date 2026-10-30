// @ts-check

import { allowsChild, placementProblem, ROOT } from "../core/rules.js";
import {
  cloneWithFreshIds, collectIds, collectOfferKeys, countByType, findNode, insertNode, makeId, makeOfferKey, moveNode,
  removeNode,
} from "../core/tree.js";
import { setIn } from "../core/values.js";

/**
 * Edits the editor can make to a page document. Each function mutates an
 * Immer draft and returns a small result (usually the id to select). They
 * check the same placement rules as the server, so an edit the server would
 * reject is refused here first.
 */

/** @typedef {import("../core/tree.js").FunnelDocument} FunnelDocument */
/** @typedef {import("../core/tree.js").FunnelNode} FunnelNode */
/** @typedef {import("../core/rules.js").Registry} Registry */

/**
 * @typedef {object} Preset
 * @property {Record<string, any>} [props]
 * @property {Record<string, any>} [style]
 * @property {Array<Preset & { type: string }>} [children]
 */

/**
 * Builds a new node with fresh ids from a preset.
 * @param {string} type
 * @param {Preset | undefined} preset
 * @param {Set<string>} taken
 * @returns {FunnelNode}
 */
export function buildNode(type, preset, taken) {
  /** @type {FunnelNode} */
  const node = { id: makeId(type, taken), type };
  if (preset?.props && Object.keys(preset.props).length) node.props = JSON.parse(JSON.stringify(preset.props));
  if (preset?.style && Object.keys(preset.style).length) node.style = JSON.parse(JSON.stringify(preset.style));
  if (preset?.children) {
    node.children = preset.children.map((child) => buildNode(child.type, child, taken));
  }
  return node;
}

/**
 * Blocks that span the page by design and stand at the top level on their
 * own. Anything else added at the top level is wrapped in a section, so it
 * gets the page's usual spacing and content width.
 */
const FULL_WIDTH = new Set(["section", "promo_hero", "image_banner", "video_hero", "announcement_ribbon", "sticky_cta", "floating_whatsapp"]);

/**
 * Inserts a new block. A block that cannot sit at the top level (a heading,
 * say) dropped there is wrapped in a new section, which is what a merchant
 * expects when dropping onto empty space.
 * @param {FunnelDocument} doc
 * @param {Registry} registry
 * @param {{ type: string, parentId: string | null, index: number, preset?: Preset, wrap?: boolean }} args
 * @returns {{ id: string, picker?: boolean } | { error: string }}
 */
export function insertBlock(doc, registry, { type, parentId, index, preset, wrap = false }) {
  const taken = collectIds(doc);

  const needsSection = !allowsChild(registry, ROOT, type) || !FULL_WIDTH.has(type);
  if (parentId === null && (wrap || allowsChild(registry, ROOT, type)) && needsSection && allowsChild(registry, "section", type)) {
    const max = registry.blocks[type]?.max_per_page;
    if (max !== undefined && (countByType(doc)[type] ?? 0) >= max) return { error: "limit" };
    const node = buildNode(type, preset, taken);
    const section = { id: makeId("section", taken), type: "section", children: [node] };
    insertNode(doc, null, index, section);
    return { id: node.id, picker: Boolean(registry.blocks[type]?.bindings) };
  }

  const problem = placementProblem(registry, doc, parentId, type);
  if (problem) return { error: problem };

  const node = buildNode(type, preset, taken);
  insertNode(doc, parentId, index, node);
  // Product blocks open the product picker right away.
  return { id: node.id, picker: Boolean(registry.blocks[type]?.bindings) };
}

/**
 * Inserts a ready-made node (a pasted copy). Ids and offer keys are renewed.
 * @param {FunnelDocument} doc
 * @param {Registry} registry
 * @param {{ node: FunnelNode, parentId: string | null, index: number }} args
 * @returns {{ id: string } | { error: string }}
 */
export function insertCopy(doc, registry, { node, parentId, index }) {
  const problem = placementProblem(registry, doc, parentId, node.type);
  if (problem) return { error: problem };
  const copy = cloneWithFreshIds(node, collectIds(doc), collectOfferKeys(doc));
  insertNode(doc, parentId, index, copy);
  return { id: copy.id };
}

/**
 * @param {FunnelDocument} doc
 * @param {Registry} registry
 * @param {{ id: string, parentId: string | null, index: number }} args
 * @returns {{ id: string } | { error: string }}
 */
export function moveBlock(doc, registry, { id, parentId, index }) {
  const located = findNode(doc, id);
  if (!located) return { error: "missing" };
  if (located.node.advanced?.locked) return { error: "locked" };
  const problem = placementProblem(registry, doc, parentId, located.node.type, id);
  if (problem) return { error: problem };
  moveNode(doc, id, parentId, index);
  return { id };
}

/**
 * Moves a block one step up or down among its siblings.
 * @param {FunnelDocument} doc
 * @param {string} id
 * @param {-1 | 1} step
 */
export function nudgeBlock(doc, id, step) {
  const located = findNode(doc, id);
  if (!located || located.node.advanced?.locked) return { error: "locked" };
  const target = located.index + step;
  if (target < 0 || target >= located.siblings.length) return { error: "edge" };
  const [node] = located.siblings.splice(located.index, 1);
  located.siblings.splice(target, 0, node);
  return { id };
}

/**
 * @param {FunnelDocument} doc
 * @param {Registry} registry
 * @param {string} id
 * @returns {{ id: string } | { error: string }}
 */
export function duplicateBlock(doc, registry, id) {
  const located = findNode(doc, id);
  if (!located) return { error: "missing" };
  const max = registry.blocks[located.node.type]?.max_per_page;
  if (max !== undefined) return { error: "limit" };
  const copy = cloneWithFreshIds(located.node, collectIds(doc), collectOfferKeys(doc));
  located.siblings.splice(located.index + 1, 0, copy);
  return { id: copy.id };
}

/**
 * Deletes a block and returns the id that should be selected next.
 * @param {FunnelDocument} doc
 * @param {string} id
 * @returns {{ select: string | null } | { error: string }}
 */
export function deleteBlock(doc, id) {
  const located = findNode(doc, id);
  if (!located) return { error: "missing" };
  if (located.node.advanced?.locked) return { error: "locked" };
  const next = located.siblings[located.index + 1] ?? located.siblings[located.index - 1] ?? located.parent;
  removeNode(doc, id);
  return { select: next ? next.id : null };
}

/**
 * Sets a prop at a path inside node.props. `undefined` removes it.
 * @param {FunnelDocument} doc
 * @param {string} id
 * @param {Array<string | number>} path
 * @param {unknown} value
 */
export function setProp(doc, id, path, value) {
  const node = findNode(doc, id)?.node;
  if (!node) return { error: "missing" };
  if (!node.props) node.props = {};
  setIn(node.props, path, value);
  if (Object.keys(node.props).length === 0) delete node.props;
  return { id };
}

/**
 * Sets one style value for one device. `undefined` falls back to the value
 * inherited from the larger screen.
 * @param {FunnelDocument} doc
 * @param {string} id
 * @param {'desktop' | 'tablet' | 'mobile'} device
 * @param {string} key
 * @param {unknown} value
 */
export function setStyle(doc, id, device, key, value) {
  const node = findNode(doc, id)?.node;
  if (!node) return { error: "missing" };
  if (!node.style) node.style = {};
  if (!node.style[device]) node.style[device] = {};
  if (value === undefined || value === null || value === "") delete node.style[device][key];
  else node.style[device][key] = value;
  if (Object.keys(node.style[device]).length === 0) delete node.style[device];
  if (Object.keys(node.style).length === 0) delete node.style;
  return { id };
}

/**
 * @param {FunnelDocument} doc
 * @param {string} id
 * @param {string} key  hidden.<device> | locked | anchor | label
 * @param {unknown} value
 */
export function setAdvanced(doc, id, key, value) {
  const node = findNode(doc, id)?.node;
  if (!node) return { error: "missing" };
  if (!node.advanced) node.advanced = {};
  const path = key.split(".");
  const empty = value === undefined || value === null || value === "" || value === false;
  setIn(node.advanced, path, empty ? undefined : value);
  if (node.advanced.hidden && Object.keys(node.advanced.hidden).length === 0) delete node.advanced.hidden;
  if (Object.keys(node.advanced).length === 0) delete node.advanced;
  return { id };
}

/**
 * @param {FunnelDocument} doc
 * @param {string} key
 * @param {unknown} value
 */
export function setPageSetting(doc, key, value) {
  if (!doc.page.settings) doc.page.settings = {};
  if (value === undefined || value === null || value === "") delete doc.page.settings[key];
  else doc.page.settings[key] = value;
  if (Object.keys(doc.page.settings).length === 0) delete doc.page.settings;
  return { id: null };
}

/**
 * Adds a column to a columns block, up to the registry maximum.
 * @param {FunnelDocument} doc
 * @param {Registry} registry
 * @param {string} columnsId
 */
export function addColumn(doc, registry, columnsId) {
  const node = findNode(doc, columnsId)?.node;
  if (!node || node.type !== "columns") return { error: "missing" };
  const max = registry.blocks.columns?.max_children ?? 6;
  if ((node.children?.length ?? 0) >= max) return { error: "children_full" };
  const column = buildNode("column", undefined, collectIds(doc));
  column.children = [];
  if (!node.children) node.children = [];
  node.children.push(column);
  return { id: column.id };
}

/**
 * Anchors defined on the page, for "scroll to" links.
 * @param {FunnelDocument} doc
 * @returns {Array<{ anchor: string, label: string }>}
 */
export function pageAnchors(doc) {
  /** @type {Array<{ anchor: string, label: string }>} */
  const anchors = [];
  /** @param {FunnelNode[]} list */
  const visit = (list) => {
    for (const node of list) {
      const anchor = node.advanced?.anchor;
      if (typeof anchor === "string" && anchor) anchors.push({ anchor, label: node.advanced?.label || anchor });
      if (node.children) visit(node.children);
    }
  };
  visit(doc.page.sections);
  return anchors;
}

// ── Offers (product bindings) ──────────────────────────────────────────────

/**
 * A product as the picker reports it.
 * @typedef {{ productId: number, variant: string | null, quantity?: number }} PickedProduct
 */

/** @param {FunnelDocument} doc @param {string} nodeId */
function bindingsOf(doc, nodeId) {
  const node = findNode(doc, nodeId)?.node;
  if (!node) return null;
  if (!node.productBindings) node.productBindings = [];
  return node.productBindings;
}

/**
 * @param {PickedProduct} picked
 * @param {boolean} [lock]  fix the variation so customers cannot change it
 */
function newItem(picked, lock = false) {
  return { productId: picked.productId, variant: picked.variant ?? null, quantity: picked.quantity ?? 1, lockVariant: lock && picked.variant != null };
}

/**
 * Adds chosen products to an offer block. A grid gets one card per product
 * (a product already on the block is skipped); a bundle block gets one new
 * bundle holding all of them; a single product card has its product replaced
 * and keeps its offer rule.
 * @param {FunnelDocument} doc
 * @param {Registry} registry
 * @param {string} nodeId
 * @param {PickedProduct[]} products
 * @returns {{ id: string, added: number, skipped: number } | { error: string }}
 */
export function addProducts(doc, registry, nodeId, products) {
  const node = findNode(doc, nodeId)?.node;
  const list = bindingsOf(doc, nodeId);
  const range = registry.blocks[node?.type ?? ""]?.bindings;
  if (!node || !list || !range) return { error: "missing" };
  const keys = collectOfferKeys(doc);

  if (node.type === "product_card") {
    const first = products[0];
    if (!first) return { error: "missing" };
    const previous = list[0];
    list.splice(0, list.length, {
      key: previous?.key ?? makeOfferKey(keys),
      items: [newItem(first)],
      offer: previous?.offer ?? { type: "none" },
      ...(previous?.display ? { display: { ...previous.display, title: undefined, image: undefined } } : {}),
    });
    return { id: nodeId, added: 1, skipped: 0 };
  }

  if (node.type === "bundle_offer_selector") {
    if (list.length >= range[1]) return { error: "children_full" };
    list.push({ key: makeOfferKey(keys), items: products.map((p) => newItem(p, true)), offer: { type: "none" } });
    return { id: nodeId, added: 1, skipped: 0 };
  }

  let added = 0;
  let skipped = 0;
  for (const picked of products) {
    const exists = list.some((binding) => binding.items.length === 1 && binding.items[0].productId === picked.productId);
    if (exists || list.length >= range[1]) {
      skipped += 1;
      continue;
    }
    list.push({ key: makeOfferKey(keys), items: [newItem(picked)], offer: { type: "none" } });
    added += 1;
  }
  return { id: nodeId, added, skipped };
}

/** @param {FunnelDocument} doc @param {string} nodeId @param {string} key @param {(binding: any) => void} change */
export function updateBinding(doc, nodeId, key, change) {
  const binding = bindingsOf(doc, nodeId)?.find((item) => item.key === key);
  if (!binding) return { error: "missing" };
  change(binding);
  return { id: nodeId };
}

/**
 * Moves an offer one place up or down within its block.
 * @param {FunnelDocument} doc
 * @param {string} nodeId
 * @param {string} key
 * @param {-1 | 1} step
 */
export function moveBinding(doc, nodeId, key, step) {
  const list = bindingsOf(doc, nodeId);
  const index = list ? list.findIndex((item) => item.key === key) : -1;
  if (!list || index < 0 || index + step < 0 || index + step >= list.length) return { error: "edge" };
  const [moved] = list.splice(index, 1);
  list.splice(index + step, 0, moved);
  return { id: nodeId };
}

/**
 * @param {FunnelDocument} doc
 * @param {string} nodeId
 * @param {string} key
 */
export function removeBinding(doc, nodeId, key) {
  const list = bindingsOf(doc, nodeId);
  const index = list ? list.findIndex((item) => item.key === key) : -1;
  if (!list || index < 0) return { error: "missing" };
  list.splice(index, 1);
  return { id: nodeId };
}

/**
 * Gives every offer of a block the same rule, keeping each offer's own
 * products and display text. The rule is copied, never shared.
 * @param {FunnelDocument} doc
 * @param {string} nodeId
 * @param {Record<string, any>} offer
 */
export function applyOfferToAll(doc, nodeId, offer) {
  const list = bindingsOf(doc, nodeId);
  if (!list) return { error: "missing" };
  for (const binding of list) binding.offer = JSON.parse(JSON.stringify(offer));
  return { id: nodeId };
}

/**
 * Adds products to an existing bundle (keeps variations fixed).
 * @param {FunnelDocument} doc
 * @param {string} nodeId
 * @param {string} key
 * @param {PickedProduct[]} products
 */
export function addBundleItems(doc, nodeId, key, products) {
  return updateBinding(doc, nodeId, key, (binding) => {
    for (const picked of products) binding.items.push(newItem(picked, true));
  });
}

/**
 * Sets the offer rule's type, dropping settings that belong to the old type.
 * @param {Record<string, any>} offer
 * @param {string} type
 * @returns {Record<string, any>}
 */
export function setOfferType(offer, type) {
  const keep = { freeDelivery: offer.freeDelivery, startsAt: offer.startsAt, endsAt: offer.endsAt, maxPerOrder: offer.maxPerOrder };
  const base = { type, ...Object.fromEntries(Object.entries(keep).filter(([, value]) => value !== undefined)) };
  /** @type {Record<string, Record<string, any>>} */
  const defaults = {
    percent: { value: 10 },
    fixed_amount: { value: 100 },
    fixed_price: {},
    quantity_discount: { tiers: [{ minQty: 2, type: "percent", value: 5 }] },
    bxgy: { buyQty: 2, getQty: 1 },
    free_gift: { giftQuantity: 1 },
  };
  return { ...base, ...(defaults[type] ?? {}) };
}
