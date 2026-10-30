"use client";

import { useCallback, useLayoutEffect, useMemo, useRef, useState } from "react";
import { useDraggable } from "@dnd-kit/core";
import { ArrowDown, ArrowUp, Copy, CornerLeftUp, Eye, EyeOff, GripVertical, Lock, Trash2 } from "lucide-react";
import { DESKTOP_MIN_WIDTH, PREVIEW_WIDTH } from "../../core/breakpoints.js";
import { itemMaxLength, maxLength } from "../../core/rules.js";
import { findNode } from "../../core/tree.js";
import FunnelPage from "../../render/FunnelPage.jsx";
import { blockMeta, EDITOR_BLOCKS, nodeTitle } from "../registry.js";
import { useEditor } from "../useEditorState.js";
import { useDragState } from "./DragState.jsx";
import InlineText from "./InlineText.jsx";

const GUTTER = 48;

/** Character limit for an inline text, from the server registry. */
function limitFor(registry, type, path) {
  if (path.length === 1) return maxLength(registry, type, String(path[0]));
  if (path.length === 3) {
    const listField = EDITOR_BLOCKS[type]?.fields?.find((field) => field.key === path[0]);
    return listField?.item ? itemMaxLength(registry, listField.item, String(path[2])) : undefined;
  }
  return undefined;
}

export default function Canvas() {
  const { state, actions } = useEditor();
  const { dropTarget, activeDrag } = useDragState();
  const scrollRef = useRef(null);
  const frameRef = useRef(null);
  const [available, setAvailable] = useState(1200);
  const [boxes, setBoxes] = useState({ selected: null, hovered: null });

  const { registry, selectedId, hoveredId, device, preview, canEdit } = state;

  // Width of the preview frame, and how far it is scaled down when the
  // window is too narrow to show a real desktop width.
  const room = Math.max(320, available - GUTTER);
  let width = PREVIEW_WIDTH[device];
  let zoom = 1;
  if (device === "desktop") {
    width = Math.max(DESKTOP_MIN_WIDTH, Math.min(PREVIEW_WIDTH.desktop, room));
  }
  if (width > room) zoom = room / width;

  const renderText = useCallback(
    ({ node, path, as, className, value, multiline, placeholder, optional }) => (
      <InlineText
        as={as}
        className={className}
        value={value}
        multiline={multiline}
        placeholder={placeholder}
        optional={optional}
        selected={node.id === selectedId}
        editable={canEdit && !node.advanced?.locked}
        maxLength={limitFor(registry, node.type, path)}
        onChange={(text) => actions.setProp(node.id, path, text, true)}
      />
    ),
    [selectedId, canEdit, registry, actions],
  );

  const renderEmpty = useCallback(
    (node) => (
      <div className="flex min-h-[72px] w-full items-center justify-center rounded-[6px] border-2 border-dashed border-[#c6c6cd] bg-white/60 px-3 text-center text-[13px] text-ink-tertiary">
        {node.type === "column" ? "Drop blocks into this column" : "Drag blocks here from the left panel"}
      </div>
    ),
    [],
  );

  const ctx = useMemo(
    () =>
      preview
        ? { mode: "preview" }
        : { mode: "editor", renderText, renderEmpty, chooseProducts: (node) => actions.openPicker({ nodeId: node.id, purpose: "add" }) },
    [preview, renderText, renderEmpty, actions],
  );

  const measure = useCallback(() => {
    const scroller = scrollRef.current;
    const frame = frameRef.current;
    if (!scroller || !frame) return;
    const base = scroller.getBoundingClientRect();
    const local = (el) => {
      if (!el) return null;
      const rect = el.getBoundingClientRect();
      return {
        top: rect.top - base.top + scroller.scrollTop,
        left: rect.left - base.left + scroller.scrollLeft,
        width: rect.width,
        height: rect.height,
      };
    };
    const find = (id) => (id ? frame.querySelector(`[data-fn-id="${CSS.escape(id)}"]`) : null);
    setBoxes({ selected: local(find(selectedId)), hovered: hoveredId !== selectedId ? local(find(hoveredId)) : null });
  }, [selectedId, hoveredId]);

  useLayoutEffect(() => {
    measure();
  }, [measure, state.doc, device, zoom, preview]);

  useLayoutEffect(() => {
    const scroller = scrollRef.current;
    const frame = frameRef.current;
    if (!scroller || !frame) return undefined;
    const observer = new ResizeObserver(() => {
      setAvailable(scroller.clientWidth);
      measure();
    });
    observer.observe(scroller);
    observer.observe(frame);
    return () => observer.disconnect();
  }, [measure]);

  // Bring the selected block into view when it is chosen from the layers
  // panel or a validation message.
  useLayoutEffect(() => {
    if (!selectedId || !frameRef.current) return;
    const el = frameRef.current.querySelector(`[data-fn-id="${CSS.escape(selectedId)}"]`);
    if (el && scrollRef.current) {
      const rect = el.getBoundingClientRect();
      const view = scrollRef.current.getBoundingClientRect();
      if (rect.bottom < view.top || rect.top > view.bottom) el.scrollIntoView({ block: "center", behavior: "smooth" });
    }
  }, [selectedId]);

  const handleClick = (event) => {
    if (preview) return;
    const el = event.target.closest?.("[data-fn-id]");
    actions.select(el ? el.dataset.fnId : null);
    // A tap on a product card also opens that offer in the settings panel.
    actions.focusOffer(event.target.closest?.("[data-fn-offer]")?.dataset.fnOffer ?? null);
  };

  const handleOver = (event) => {
    if (preview || activeDrag) return;
    const el = event.target.closest?.("[data-fn-id]");
    actions.hover(el ? el.dataset.fnId : null);
  };

  return (
    <div
      ref={scrollRef}
      id="fn-canvas-scroller"
      className="relative h-full overflow-auto bg-[#eceef2]"
      data-testid="canvas"
    >
      <div className="flex min-h-full justify-center px-6 py-6">
        <div
          ref={frameRef}
          className="fn-canvas-frame relative h-fit min-h-[480px] shrink-0 bg-white shadow-e3 transition-[width] duration-200"
          style={{ width, zoom }}
          data-device={device}
          data-testid="canvas-frame"
          onClick={handleClick}
          onMouseOver={handleOver}
          onMouseLeave={() => actions.hover(null)}
        >
          {state.doc.page.sections.length === 0 && !preview ? (
            <EmptyPage />
          ) : null}
          <FunnelPage document={state.doc} ctx={ctx} />
        </div>
      </div>

      {!preview ? (
        <Overlay boxes={boxes} dropTarget={dropTarget} activeDrag={activeDrag} />
      ) : null}
    </div>
  );
}

function EmptyPage() {
  return (
    <div className="pointer-events-none absolute inset-x-0 top-0 flex flex-col items-center gap-2 px-6 pt-24 text-center" data-testid="empty-page">
      <p className="m-0 text-[18px] font-semibold text-ink">Start building your page</p>
      <p className="m-0 max-w-[420px] text-[14px] text-ink-secondary">
        Drag a block from the left panel onto this page, or click a block to add it. A good start is a Promotional hero.
      </p>
    </div>
  );
}

function Overlay({ boxes, dropTarget, activeDrag }) {
  const { state } = useEditor();
  const selected = state.selectedId ? findNode(state.doc, state.selectedId)?.node : null;
  const indicator = activeDrag ? dropTarget?.local ?? null : null;

  return (
    <div className="pointer-events-none absolute left-0 top-0 h-0 w-0" aria-hidden={false}>
      {boxes.hovered && !activeDrag ? (
        <div
          className="absolute rounded-[2px] border border-[#1f6feb]/60"
          style={{ top: boxes.hovered.top, left: boxes.hovered.left, width: boxes.hovered.width, height: boxes.hovered.height }}
        />
      ) : null}

      {boxes.selected && selected ? (
        <>
          {/* Kept mounted while dragging: the drag handle lives in the toolbar,
              and unmounting it would cancel the drag. */}
          <div
            className={activeDrag ? "hidden" : "absolute rounded-[2px] border-2 border-[#1f6feb]"}
            style={{ top: boxes.selected.top, left: boxes.selected.left, width: boxes.selected.width, height: boxes.selected.height }}
            data-testid="selection-outline"
          />
          <NodeToolbar node={selected} box={boxes.selected} dragging={Boolean(activeDrag)} />
        </>
      ) : null}

      {indicator ? (
        <div
          data-testid="drop-indicator"
          className={
            indicator.kind === "box"
              ? "absolute rounded-[4px] border-2 border-dashed border-[#1f6feb] bg-[#1f6feb]/10"
              : "absolute rounded-full bg-[#1f6feb] shadow-[0_0_0_2px_rgba(255,255,255,.9)]"
          }
          style={
            indicator.kind === "box"
              ? { top: indicator.top, left: indicator.left, width: indicator.width, height: Math.max(indicator.height, 24) }
              : indicator.kind === "line"
                ? { top: indicator.top - 2, left: indicator.left, width: indicator.width, height: 4 }
                : { top: indicator.top, left: indicator.left - 2, width: 4, height: indicator.height }
          }
        />
      ) : null}
    </div>
  );
}

function ToolbarButton({ label, onClick, children, disabled, testId }) {
  return (
    <button
      type="button"
      className="inline-flex size-7 items-center justify-center rounded-[3px] text-white hover:bg-white/20 disabled:opacity-40"
      onClick={(event) => {
        event.stopPropagation();
        onClick();
      }}
      title={label}
      aria-label={label}
      disabled={disabled}
      data-testid={testId}
    >
      {children}
    </button>
  );
}

function NodeToolbar({ node, box, dragging }) {
  const { state, actions } = useEditor();
  const located = findNode(state.doc, node.id);
  const locked = Boolean(node.advanced?.locked);
  const hiddenHere = node.advanced?.hidden?.[state.device] === true;
  const { attributes, listeners, setNodeRef } = useDraggable({
    id: `node:${node.id}`,
    data: { kind: "move", id: node.id, type: node.type },
    disabled: locked || !state.canEdit,
  });

  const above = box.top >= 34;
  const style = {
    top: above ? box.top - 32 : box.top + 2,
    left: box.left + box.width,
    transform: "translateX(-100%)",
  };

  return (
    <div
      className={`pointer-events-auto absolute z-10 flex items-center gap-0.5 rounded-[4px] bg-[#1f6feb] px-1 py-0.5 shadow-e2 ${dragging ? "opacity-0" : ""}`}
      style={style}
      data-testid="node-toolbar"
      onMouseDown={(event) => event.stopPropagation()}
    >
      <button
        ref={setNodeRef}
        type="button"
        className="inline-flex h-7 cursor-grab items-center gap-1 rounded-[3px] px-1.5 text-[12px] font-semibold text-white hover:bg-white/20 active:cursor-grabbing disabled:cursor-not-allowed"
        aria-label={`Drag to move ${blockMeta(node.type).label}`}
        title="Drag to move"
        data-testid="drag-handle"
        {...attributes}
        {...listeners}
      >
        {locked ? <Lock size={13} aria-hidden="true" /> : <GripVertical size={14} aria-hidden="true" />}
        <span className="max-w-[160px] truncate">{nodeTitle(node)}</span>
      </button>
      {located?.parent ? (
        <ToolbarButton label="Select parent block" onClick={() => actions.select(located.parent.id)}>
          <CornerLeftUp size={15} />
        </ToolbarButton>
      ) : null}
      {state.canEdit ? (
        <>
          <ToolbarButton label="Move up" onClick={() => actions.nudge(node.id, -1)} disabled={locked || located?.index === 0} testId="move-up">
            <ArrowUp size={15} />
          </ToolbarButton>
          <ToolbarButton
            label="Move down"
            onClick={() => actions.nudge(node.id, 1)}
            disabled={locked || (located ? located.index >= located.siblings.length - 1 : true)}
            testId="move-down"
          >
            <ArrowDown size={15} />
          </ToolbarButton>
          <ToolbarButton label="Duplicate" onClick={() => actions.duplicate(node.id)} testId="duplicate">
            <Copy size={15} />
          </ToolbarButton>
          <ToolbarButton
            label={hiddenHere ? `Show on ${state.device}` : `Hide on ${state.device}`}
            onClick={() => actions.setAdvanced(node.id, `hidden.${state.device}`, !hiddenHere)}
            testId="toggle-visibility"
          >
            {hiddenHere ? <EyeOff size={15} /> : <Eye size={15} />}
          </ToolbarButton>
          <ToolbarButton label="Delete" onClick={() => actions.remove(node.id)} disabled={locked} testId="delete">
            <Trash2 size={15} />
          </ToolbarButton>
        </>
      ) : null}
    </div>
  );
}
