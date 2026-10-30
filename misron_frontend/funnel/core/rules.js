// @ts-check

import { countByType, findNode, isWithin } from "./tree.js";

/**
 * Placement rules, read from the block registry the server sends
 * (config/funnel_blocks.php). The server validates every save with the same
 * rules; checking them here keeps the editor from offering drops it would
 * reject.
 */

/**
 * @typedef {object} BlockRule
 * @property {string} category
 * @property {string[]} parents
 * @property {'*' | string[] | null} children
 * @property {number} [max_per_page]
 * @property {number} [min_children]
 * @property {number} [max_children]
 * @property {[number, number]} [bindings]
 * @property {Record<string, string>} props
 */

/** @typedef {{ blocks: Record<string, BlockRule> } & Record<string, any>} Registry */

export const ROOT = "root";

/**
 * @param {Registry} registry
 * @param {string} parentType  a block type, or "root"
 * @param {string} childType
 */
export function allowsChild(registry, parentType, childType) {
  const child = registry.blocks[childType];
  if (!child || !child.parents.includes(parentType)) return false;
  if (parentType === ROOT) return true;
  const accepted = registry.blocks[parentType]?.children;
  return accepted === "*" || (Array.isArray(accepted) && accepted.includes(childType));
}

/**
 * @param {Registry} registry
 * @param {string} type
 */
export function acceptsChildren(registry, type) {
  const children = registry.blocks[type]?.children;
  return children !== null && children !== undefined;
}

/**
 * Why a block of `type` cannot go into `parentId`, or null when it can.
 * @param {Registry} registry
 * @param {import("./tree.js").FunnelDocument} doc
 * @param {string | null} parentId   null for the top level
 * @param {string} type
 * @param {string | null} [movingId]  the node being moved, if any
 * @returns {null | 'nesting' | 'limit' | 'cycle' | 'children_full'}
 */
export function placementProblem(registry, doc, parentId, type, movingId = null) {
  const parentType = parentId === null ? ROOT : findNode(doc, parentId)?.node.type;
  if (!parentType || !allowsChild(registry, parentType, type)) return "nesting";

  if (movingId && parentId !== null && isWithin(doc, movingId, parentId)) return "cycle";

  if (!movingId) {
    const max = registry.blocks[type]?.max_per_page;
    if (max !== undefined && (countByType(doc)[type] ?? 0) >= max) return "limit";
  }

  if (parentId !== null) {
    const parent = findNode(doc, parentId)?.node;
    const limit = registry.blocks[parentType]?.max_children;
    const movingInside = movingId ? (parent?.children ?? []).some((child) => child.id === movingId) : false;
    if (limit !== undefined && !movingInside && (parent?.children?.length ?? 0) >= limit) return "children_full";
  }

  return null;
}

/**
 * Maximum length of a text prop, from rules such as "string:300" or "text:5000".
 * @param {Registry} registry
 * @param {string} type
 * @param {string} prop
 * @returns {number | undefined}
 */
export function maxLength(registry, type, prop) {
  const rule = registry.blocks[type]?.props?.[prop];
  if (typeof rule !== "string") return undefined;
  const match = /^(string|text):(\d+)$/.exec(rule);
  return match ? Number(match[2]) : undefined;
}

/**
 * Maximum length for a field of a list item, e.g. items[].question.
 * @param {Registry} registry
 * @param {string} itemType
 * @param {string} field
 */
export function itemMaxLength(registry, itemType, field) {
  const rule = registry.items?.[itemType]?.[field];
  if (typeof rule !== "string") return undefined;
  const match = /^(string|text):(\d+)$/.exec(rule);
  return match ? Number(match[2]) : undefined;
}
