// @ts-check

/**
 * Operations on the page tree: `document.page.sections`, each node with an
 * optional `children` list. Read helpers work on any object; the mutating
 * helpers are meant for Immer drafts inside the editor store.
 */

/**
 * @typedef {object} FunnelNode
 * @property {string} id
 * @property {string} type
 * @property {Record<string, any>} [props]
 * @property {Record<string, Record<string, any>>} [style]
 * @property {Record<string, any>} [advanced]
 * @property {Array<Record<string, any>>} [productBindings]
 * @property {FunnelNode[]} [children]
 */

/**
 * @typedef {object} FunnelDocument
 * @property {number} schemaVersion
 * @property {{ settings?: Record<string, any>, sections: FunnelNode[] }} page
 */

/**
 * @typedef {object} Located
 * @property {FunnelNode} node
 * @property {FunnelNode | null} parent  null for top-level sections
 * @property {number} index              position in the parent's list
 * @property {FunnelNode[]} siblings     the list that holds the node
 * @property {number} depth              1 for top-level sections
 */

/** @param {FunnelDocument | null | undefined} doc */
export function sectionsOf(doc) {
  return doc?.page?.sections ?? [];
}

/**
 * Depth-first walk. Returning false from `visit` skips that node's children.
 * @param {FunnelNode[]} nodes
 * @param {(node: FunnelNode, parent: FunnelNode | null, index: number, depth: number) => boolean | void} visit
 * @param {FunnelNode | null} [parent]
 * @param {number} [depth]
 */
export function walk(nodes, visit, parent = null, depth = 1) {
  nodes.forEach((node, index) => {
    if (visit(node, parent, index, depth) === false) return;
    if (Array.isArray(node.children)) walk(node.children, visit, node, depth + 1);
  });
}

/**
 * @param {FunnelDocument | null | undefined} doc
 * @param {string | null | undefined} id
 * @returns {Located | null}
 */
export function findNode(doc, id) {
  if (!id) return null;
  /** @type {Located | null} */
  let found = null;
  /**
   * @param {FunnelNode[]} list
   * @param {FunnelNode | null} parent
   * @param {number} depth
   * @returns {boolean}
   */
  const search = (list, parent, depth) => {
    for (let index = 0; index < list.length; index++) {
      const node = list[index];
      if (node.id === id) {
        found = { node, parent, index, siblings: list, depth };
        return true;
      }
      if (Array.isArray(node.children) && search(node.children, node, depth + 1)) return true;
    }
    return false;
  };
  search(sectionsOf(doc), null, 1);
  return found;
}

/**
 * Ancestors of a node, outermost first (the node itself excluded).
 * @param {FunnelDocument} doc
 * @param {string} id
 * @returns {FunnelNode[]}
 */
export function ancestorsOf(doc, id) {
  /** @type {FunnelNode[]} */
  const trail = [];
  /**
   * @param {FunnelNode[]} list
   * @returns {boolean}
   */
  const search = (list) => {
    for (const node of list) {
      if (node.id === id) return true;
      if (Array.isArray(node.children)) {
        trail.push(node);
        if (search(node.children)) return true;
        trail.pop();
      }
    }
    return false;
  };
  return search(sectionsOf(doc)) ? trail : [];
}

/**
 * Children of `parentId`, or the top-level sections for null.
 * @param {FunnelDocument} doc
 * @param {string | null} parentId
 * @returns {FunnelNode[]}
 */
export function childrenOf(doc, parentId) {
  if (parentId === null) return sectionsOf(doc);
  return findNode(doc, parentId)?.node.children ?? [];
}

/**
 * True when `id` is `ancestorId` or sits anywhere inside it.
 * @param {FunnelDocument} doc
 * @param {string} ancestorId
 * @param {string | null} id
 */
export function isWithin(doc, ancestorId, id) {
  if (!id) return false;
  if (id === ancestorId) return true;
  return ancestorsOf(doc, id).some((node) => node.id === ancestorId);
}

/** @param {FunnelDocument | null | undefined} doc */
export function collectIds(doc) {
  /** @type {Set<string>} */
  const ids = new Set();
  walk(sectionsOf(doc), (node) => {
    ids.add(node.id);
  });
  return ids;
}

/** @param {FunnelDocument | null | undefined} doc */
export function collectOfferKeys(doc) {
  /** @type {Set<string>} */
  const keys = new Set();
  walk(sectionsOf(doc), (node) => {
    for (const binding of node.productBindings ?? []) {
      if (binding && typeof binding.key === "string") keys.add(binding.key);
    }
  });
  return keys;
}

/** @param {FunnelDocument | null | undefined} doc */
export function countByType(doc) {
  /** @type {Record<string, number>} */
  const counts = {};
  walk(sectionsOf(doc), (node) => {
    counts[node.type] = (counts[node.type] ?? 0) + 1;
  });
  return counts;
}

/** @param {FunnelNode} node */
export function countNodes(node) {
  let total = 0;
  walk([node], () => {
    total += 1;
  });
  return total;
}

const ID_ALPHABET = "abcdefghijklmnopqrstuvwxyz0123456789";

/** @param {number} length */
function randomPart(length) {
  let out = "";
  const bytes = new Uint8Array(length);
  globalThis.crypto.getRandomValues(bytes);
  for (const byte of bytes) out += ID_ALPHABET[byte % ID_ALPHABET.length];
  return out;
}

/**
 * A fresh id such as "heading_k3x9q2", unique within `taken`. Matches the
 * server's rule: 3–64 characters of letters, digits, "_" and "-".
 * @param {string} type
 * @param {Set<string>} taken
 */
export function makeId(type, taken) {
  const prefix = type.replace(/[^a-z0-9]+/gi, "").slice(0, 16).toLowerCase() || "block";
  for (;;) {
    const id = `${prefix}_${randomPart(6)}`;
    if (!taken.has(id)) {
      taken.add(id);
      return id;
    }
  }
}

/**
 * A fresh offer key such as "offer_k3x9q2", unique within `taken`.
 * @param {Set<string>} taken
 */
export function makeOfferKey(taken) {
  for (;;) {
    const key = `offer_${randomPart(6)}`;
    if (!taken.has(key)) {
      taken.add(key);
      return key;
    }
  }
}

/**
 * Deep copy of a node with new ids for it and every descendant. Offer keys
 * defined inside the copy are renamed too, and references to them inside the
 * copy follow, so a duplicated product block gets its own offers.
 * @param {FunnelNode} node
 * @param {Set<string>} takenIds
 * @param {Set<string>} takenKeys
 * @returns {FunnelNode}
 */
export function cloneWithFreshIds(node, takenIds, takenKeys) {
  /** @type {FunnelNode} */
  const copy = JSON.parse(JSON.stringify(node));
  /** @type {Map<string, string>} */
  const renamed = new Map();

  walk([copy], (item) => {
    item.id = makeId(item.type, takenIds);
    for (const binding of item.productBindings ?? []) {
      if (binding && typeof binding.key === "string") {
        const fresh = makeOfferKey(takenKeys);
        renamed.set(binding.key, fresh);
        binding.key = fresh;
      }
    }
  });

  if (renamed.size > 0) {
    walk([copy], (item) => {
      const ref = item.props?.offerKey;
      if (typeof ref === "string" && renamed.has(ref) && item.props) {
        item.props.offerKey = renamed.get(ref);
      }
    });
  }

  return copy;
}

/**
 * Inserts `node` into `parentId` (null = top level) at `index`.
 * @param {FunnelDocument} doc  draft
 * @param {string | null} parentId
 * @param {number} index
 * @param {FunnelNode} node
 */
export function insertNode(doc, parentId, index, node) {
  /** @type {FunnelNode[]} */
  let list;
  if (parentId === null) {
    list = doc.page.sections;
  } else {
    const parent = findNode(doc, parentId)?.node;
    if (!parent) throw new Error(`Parent ${parentId} not found`);
    if (!Array.isArray(parent.children)) parent.children = [];
    list = parent.children;
  }
  const at = Math.max(0, Math.min(index, list.length));
  list.splice(at, 0, node);
}

/**
 * @param {FunnelDocument} doc  draft
 * @param {string} id
 * @returns {FunnelNode | null} the removed node
 */
export function removeNode(doc, id) {
  const located = findNode(doc, id);
  if (!located) return null;
  const [removed] = located.siblings.splice(located.index, 1);
  return removed;
}

/**
 * Moves a node. `index` is a position in the target list as it looks before
 * the move, which is what a drop indicator measures.
 * @param {FunnelDocument} doc  draft
 * @param {string} id
 * @param {string | null} parentId
 * @param {number} index
 * @returns {boolean} whether anything changed
 */
export function moveNode(doc, id, parentId, index) {
  const located = findNode(doc, id);
  if (!located) return false;
  if (parentId !== null && isWithin(doc, id, parentId)) return false;

  const sameParent = (located.parent?.id ?? null) === parentId;
  let target = index;
  if (sameParent) {
    if (target === located.index || target === located.index + 1) return false;
    if (target > located.index) target -= 1;
  }

  const [node] = located.siblings.splice(located.index, 1);
  insertNode(doc, parentId, target, node);
  return true;
}

/**
 * Every offer on the page with the block that holds it, in page order.
 * @param {FunnelDocument | null | undefined} doc
 * @returns {Array<{ binding: Record<string, any>, blockId: string, blockType: string, props: Record<string, any> }>}
 */
export function collectBindings(doc) {
  /** @type {Array<{ binding: Record<string, any>, blockId: string, blockType: string, props: Record<string, any> }>} */
  const found = [];
  walk(sectionsOf(doc), (node) => {
    for (const binding of node.productBindings ?? []) {
      if (binding && typeof binding.key === "string") found.push({ binding, blockId: node.id, blockType: node.type, props: node.props ?? {} });
    }
  });
  return found;
}
