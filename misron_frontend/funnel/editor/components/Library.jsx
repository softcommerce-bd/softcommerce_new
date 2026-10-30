"use client";

import { useMemo, useState } from "react";
import { useDraggable } from "@dnd-kit/core";
import { Search } from "lucide-react";
import { placementProblem } from "../../core/rules.js";
import { availableBlocks, CATEGORIES } from "../registry.js";
import { useEditor } from "../useEditorState.js";

/**
 * Searchable list of blocks. Drag one onto the page, or click (or press
 * Enter on) it to add it next to the current selection.
 */
export default function Library() {
  const { state, actions } = useEditor();
  const [query, setQuery] = useState("");
  const blocks = useMemo(() => availableBlocks(state.registry), [state.registry]);

  const term = query.trim().toLowerCase();
  const matches = term
    ? blocks.filter((block) =>
        [block.label, block.description, ...(block.keywords ?? [])].some((text) => text?.toLowerCase().includes(term)),
      )
    : blocks;

  return (
    <div className="flex h-full flex-col">
      <div className="border-b border-hairline p-3">
        <label className="relative block">
          <span className="sr-only">Search blocks</span>
          <Search size={15} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-tertiary" aria-hidden="true" />
          <input
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Search blocks"
            className="field h-9 pl-9 text-[13px]"
            data-testid="library-search"
          />
        </label>
      </div>

      <div className="flex-1 overflow-y-auto p-3">
        {CATEGORIES.map((category) => {
          const items = matches.filter((block) => block.category === category.id);
          if (items.length === 0) return null;
          return (
            <section key={category.id} className="mb-4">
              <h3 className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-ink-tertiary">{category.label}</h3>
              <div className="grid grid-cols-2 gap-2">
                {items.map((block) => (
                  <LibraryItem key={block.type} block={block} disabled={!state.canEdit || limitReached(state, block.type)} onAdd={() => actions.insertNear(block.type)} />
                ))}
              </div>
            </section>
          );
        })}
        {matches.length === 0 ? <p className="text-center text-[13px] text-ink-tertiary">No block matches “{query}”.</p> : null}
      </div>
    </div>
  );
}

function limitReached(state, type) {
  return placementProblem(state.registry, state.doc, null, type) === "limit";
}

function LibraryItem({ block, disabled, onAdd }) {
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
    id: `lib:${block.type}`,
    data: { kind: "new", type: block.type },
    disabled,
  });
  const Icon = block.icon;

  return (
    <button
      ref={setNodeRef}
      type="button"
      {...attributes}
      {...listeners}
      onClick={onAdd}
      onKeyDown={(event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          onAdd();
        }
      }}
      disabled={disabled}
      title={block.description || block.label}
      data-testid={`library-${block.type}`}
      className={`flex flex-col items-center gap-1.5 rounded-[6px] border border-hairline bg-surface px-2 py-3 text-center text-[12px] font-medium text-ink-secondary transition-colors hover:border-[#1f6feb] hover:text-ink disabled:cursor-not-allowed disabled:opacity-40 ${isDragging ? "opacity-50" : ""}`}
    >
      <Icon size={20} aria-hidden="true" />
      <span className="leading-tight">{block.label}</span>
    </button>
  );
}
