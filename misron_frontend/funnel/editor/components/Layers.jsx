"use client";

import { useState } from "react";
import { ChevronDown, ChevronRight, EyeOff, Lock } from "lucide-react";
import { sectionsOf } from "../../core/tree.js";
import { blockMeta, nodeTitle } from "../registry.js";
import { useEditor } from "../useEditorState.js";

/**
 * Outline of the page. Click a row to select the block; arrow keys move the
 * selection (up/down through visible rows, left to the parent, right into
 * the first child).
 */
export default function Layers() {
  const { state, actions } = useEditor();
  const [collapsed, setCollapsed] = useState(() => new Set());
  const rows = [];

  const visit = (nodes, depth, parentId) => {
    nodes.forEach((node) => {
      const hasChildren = Array.isArray(node.children) && node.children.length > 0;
      rows.push({ node, depth, parentId, hasChildren });
      if (hasChildren && !collapsed.has(node.id)) visit(node.children, depth + 1, node.id);
    });
  };
  visit(sectionsOf(state.doc), 0, null);

  const toggle = (id) =>
    setCollapsed((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });

  const onKeyDown = (event) => {
    const index = rows.findIndex((row) => row.node.id === state.selectedId);
    if (index < 0) return;
    const row = rows[index];
    if (event.key === "ArrowDown" && rows[index + 1]) actions.select(rows[index + 1].node.id);
    else if (event.key === "ArrowUp" && rows[index - 1]) actions.select(rows[index - 1].node.id);
    else if (event.key === "ArrowLeft" && row.parentId) actions.select(row.parentId);
    else if (event.key === "ArrowRight" && row.hasChildren) {
      if (collapsed.has(row.node.id)) toggle(row.node.id);
      else actions.select(row.node.children[0].id);
    } else return;
    event.preventDefault();
  };

  if (rows.length === 0) {
    return <p className="p-4 text-center text-[13px] text-ink-tertiary">The page is empty. Add a block to see it here.</p>;
  }

  return (
    <ul role="tree" aria-label="Page layers" tabIndex={0} onKeyDown={onKeyDown} className="m-0 list-none overflow-y-auto p-2 outline-none" data-testid="layers">
      {rows.map(({ node, depth, hasChildren }) => {
        const Icon = blockMeta(node.type).icon;
        const selected = node.id === state.selectedId;
        const hiddenOn = Object.keys(node.advanced?.hidden ?? {});
        return (
          <li
            key={node.id}
            role="treeitem"
            aria-selected={selected}
            aria-expanded={hasChildren ? !collapsed.has(node.id) : undefined}
            aria-level={depth + 1}
            className={`flex cursor-pointer items-center gap-1 rounded-[4px] py-1 pr-2 text-[13px] ${selected ? "bg-[#1f6feb] text-white" : "text-ink-secondary hover:bg-surface-hover"}`}
            style={{ paddingLeft: 6 + depth * 14 }}
            onClick={() => actions.select(node.id)}
            onMouseEnter={() => actions.hover(node.id)}
            onMouseLeave={() => actions.hover(null)}
            data-testid={`layer-${node.id}`}
          >
            {hasChildren ? (
              <button
                type="button"
                className="inline-flex size-5 shrink-0 items-center justify-center rounded-[3px] hover:bg-black/10"
                onClick={(event) => {
                  event.stopPropagation();
                  toggle(node.id);
                }}
                aria-label={collapsed.has(node.id) ? "Expand" : "Collapse"}
                tabIndex={-1}
              >
                {collapsed.has(node.id) ? <ChevronRight size={14} /> : <ChevronDown size={14} />}
              </button>
            ) : (
              <span className="size-5 shrink-0" />
            )}
            {Icon ? <Icon size={14} className="shrink-0" aria-hidden="true" /> : null}
            <span className="min-w-0 flex-1 truncate">{nodeTitle(node)}</span>
            {hiddenOn.length ? <EyeOff size={13} className="shrink-0 opacity-70" aria-label={`Hidden on ${hiddenOn.join(", ")}`} /> : null}
            {node.advanced?.locked ? <Lock size={13} className="shrink-0 opacity-70" aria-label="Locked" /> : null}
          </li>
        );
      })}
    </ul>
  );
}
