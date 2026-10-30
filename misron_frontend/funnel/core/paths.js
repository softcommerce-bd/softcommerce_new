// @ts-check

import { sectionsOf } from "./tree.js";

/**
 * Turns a validation path from the server, such as
 * "page.sections[1].children[0].props.text", into the block it points at and
 * the setting inside that block ("props.text"), so the editor can select the
 * block and show the message next to the right field.
 *
 * @param {import("./tree.js").FunnelDocument | null | undefined} doc
 * @param {string} path
 * @returns {{ nodeId: string | null, field: string | null }}
 */
export function locatePath(doc, path) {
  if (typeof path !== "string" || !path.startsWith("page.sections")) {
    return { nodeId: null, field: null };
  }

  const rest = path.slice("page.sections".length);
  const step = /^\[(\d+)\](?:\.children(?=\[))?/;

  /** @type {import("./tree.js").FunnelNode[] | undefined} */
  let list = sectionsOf(doc);
  /** @type {string | null} */
  let nodeId = null;
  let cursor = rest;

  for (;;) {
    const match = step.exec(cursor);
    if (!match || !list) break;
    /** @type {import("./tree.js").FunnelNode | undefined} */
    const node = list[Number(match[1])];
    if (!node) break;
    nodeId = node.id;
    cursor = cursor.slice(match[0].length);
    if (!match[0].endsWith(".children")) break;
    list = node.children;
  }

  const field = cursor.replace(/^\./, "");
  return { nodeId, field: field === "" ? null : field };
}
