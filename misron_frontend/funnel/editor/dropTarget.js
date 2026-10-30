import { acceptsChildren, allowsChild, placementProblem, ROOT } from "../core/rules.js";
import { findNode, isWithin, sectionsOf } from "../core/tree.js";

/**
 * Works out where a dragged block would land, from the pointer position and
 * the rendered canvas. Blocks carry `data-fn-id` and `data-fn-parent`, so the
 * canvas DOM itself describes the tree; nothing about the page layout needs
 * to be duplicated here.
 *
 * Rules, deepest block under the pointer first:
 *  1. Over a container that accepts the block (and not on its edge): insert
 *     inside it, at the position nearest the pointer.
 *  2. Otherwise: insert before or after that block in its own parent.
 *  3. Nothing suitable: the top level of the page, wrapping the block in a
 *     new section when it cannot stand there on its own.
 */

const EDGE = 10;

function childElements(frame, parentId) {
  const value = parentId === null ? "root" : parentId;
  return Array.from(frame.querySelectorAll(`[data-fn-parent="${CSS.escape(value)}"]`));
}

/**
 * Whether the pointer is before `rect` in reading order. Full-width rows are
 * compared top/bottom; items in a row or grid left/right.
 */
function isBefore(rect, x, y, rowLike) {
  if (y < rect.top) return true;
  if (y > rect.bottom) return false;
  return rowLike ? y < rect.top + rect.height / 2 : x < rect.left + rect.width / 2;
}

function rowLike(rect, containerRect) {
  return rect.width >= containerRect.width * 0.8;
}

/** Position among the children of a container element. */
function indexAmong(children, x, y, containerRect) {
  for (let i = 0; i < children.length; i++) {
    const rect = children[i].getBoundingClientRect();
    if (isBefore(rect, x, y, rowLike(rect, containerRect))) return i;
  }
  return children.length;
}

/** Line (or box, for an empty container) showing where the drop lands. */
function indicator(children, index, containerEl) {
  if (children.length === 0) {
    const rect = containerEl.getBoundingClientRect();
    return { kind: "box", top: rect.top, left: rect.left, width: rect.width, height: rect.height };
  }
  const containerRect = containerEl.getBoundingClientRect();
  const reference = children[Math.min(index, children.length - 1)].getBoundingClientRect();
  const row = rowLike(reference, containerRect);
  const after = index >= children.length;
  if (row) {
    return { kind: "line", top: after ? reference.bottom : reference.top, left: reference.left, width: reference.width, height: 0 };
  }
  return { kind: "vline", top: reference.top, left: after ? reference.right : reference.left, width: 0, height: reference.height };
}

/**
 * @param {object} args
 * @param {{ x: number, y: number }} args.point     viewport coordinates
 * @param {{ kind: 'new' | 'move', type: string, id?: string }} args.drag
 * @param {object} args.doc
 * @param {object} args.registry
 * @param {HTMLElement} args.frame                    the canvas page root
 * @returns {null | { parentId: string | null, index: number, wrap?: boolean, indicator: object }}
 */
export function computeDropTarget({ point, drag, doc, registry, frame }) {
  const { x, y } = point;
  const movingId = drag.kind === "move" ? drag.id : null;
  const fits = (parentId) => placementProblem(registry, doc, parentId, drag.type, movingId) === null;

  const stack = document.elementsFromPoint(x, y).filter(
    (el) => el instanceof HTMLElement && el.dataset.fnId && frame.contains(el),
  );

  for (const el of stack) {
    const id = el.dataset.fnId;
    if (movingId && isWithin(doc, movingId, id)) continue;
    const located = findNode(doc, id);
    if (!located) continue;

    const rect = el.getBoundingClientRect();
    const onEdge = y - rect.top < EDGE || rect.bottom - y < EDGE;

    if (acceptsChildren(registry, located.node.type) && !onEdge && fits(id)) {
      const children = childElements(frame, id).filter((child) => child.dataset.fnId !== movingId);
      const index = indexAmong(children, x, y, rect);
      // Index in the document, which still contains the moving block.
      const docIndex = movingId ? docIndexSkipping(located.node.children ?? [], children, index) : index;
      return { parentId: id, index: docIndex, indicator: indicator(children, index, el) };
    }

    const parentId = el.dataset.fnParent === "root" ? null : el.dataset.fnParent ?? null;
    if (fits(parentId)) {
      const containerEl = parentId === null ? frame : frame.querySelector(`[data-fn-id="${CSS.escape(parentId)}"]`);
      const containerRect = (containerEl ?? frame).getBoundingClientRect();
      const before = isBefore(rect, x, y, rowLike(rect, containerRect));
      const index = located.index + (before ? 0 : 1);
      const siblings = childElements(frame, parentId);
      const position = siblings.indexOf(el) + (before ? 0 : 1);
      return { parentId, index, indicator: indicator(siblings, position, containerEl ?? frame) };
    }
  }

  // The top level of the page.
  const sections = childElements(frame, null).filter((child) => child.dataset.fnId !== movingId);
  const index = indexAmong(sections, x, y, frame.getBoundingClientRect());
  const docIndex = movingId ? docIndexSkipping(sectionsOf(doc), sections, index) : index;

  if (allowsChild(registry, ROOT, drag.type)) {
    return fits(null) ? { parentId: null, index: docIndex, indicator: indicator(sections, index, frame) } : null;
  }
  if (drag.kind === "new" && allowsChild(registry, "section", drag.type)) {
    return { parentId: null, index: docIndex, wrap: true, indicator: indicator(sections, index, frame) };
  }
  return null;
}

/**
 * Converts a position among the rendered children without the moving block
 * into a position in the document list, which still holds it.
 */
function docIndexSkipping(docChildren, renderedWithoutMoving, index) {
  if (index >= renderedWithoutMoving.length) return docChildren.length;
  const anchorId = renderedWithoutMoving[index].dataset.fnId;
  const at = docChildren.findIndex((child) => child.id === anchorId);
  return at < 0 ? docChildren.length : at;
}

export { ROOT };
