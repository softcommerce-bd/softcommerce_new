"use client";

import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";
import { DndContext, DragOverlay, PointerSensor, useSensor, useSensors } from "@dnd-kit/core";
import toast from "react-hot-toast";
import { Bookmark, ClipboardCheck, Layers as LayersIcon, LayoutGrid } from "lucide-react";
import { CommerceProvider } from "../commerce/CommerceProvider.jsx";
import { CheckoutProvider } from "../commerce/CheckoutProvider.jsx";
import { editorCommerce } from "../commerce/adapters.js";
import { previewCheckout } from "../commerce/checkoutAdapters.js";
import { unfilledSlots } from "../core/template.js";
import { cx } from "../core/values.js";
import Canvas from "./components/Canvas.jsx";
import { ConflictDialog, ProblemsDialog, SessionEndedDialog } from "./components/Dialogs.jsx";
import { DragStateContext } from "./components/DragState.jsx";
import HistoryDialog from "./components/HistoryDialog.jsx";
import Layers from "./components/Layers.jsx";
import Library from "./components/Library.jsx";
import PickerHost from "./components/PickerHost.jsx";
import PublishDialog from "./components/PublishDialog.jsx";
import SavedSections from "./components/SavedSections.jsx";
import SettingsPanel from "./components/SettingsPanel.jsx";
import SetupChecklist from "./components/SetupChecklist.jsx";
import ShareDialog from "./components/ShareDialog.jsx";
import Toolbar from "./components/Toolbar.jsx";
import { computeDropTarget } from "./dropTarget.js";
import { blockMeta } from "./registry.js";
import { SAVE } from "./store.js";
import { EditorContext, useEditorState } from "./useEditorState.js";

const REFUSALS = {
  nesting: "That block can't go there.",
  limit: "Only one of these blocks is allowed per page.",
  locked: "This block is locked. Unlock it under Advanced first.",
  children_full: "This block can't hold any more items.",
  cycle: "A block can't be moved inside itself.",
};

const AUTOSCROLL_EDGE = 56;
// Below this width the page and two side panels do not fit together: one panel at a time.
const ROOM_FOR_BOTH_PANELS = 1280;
// Below this width (Tailwind lg) the panels float over the page as drawers.
const DRAWER_WIDTH = 1024;
const AUTOSCROLL_STEP = 14;

export default function Editor({ funnel, registry, user, api, returnUrl }) {
  const { state, actions } = useEditorState({ funnel, registry, user, api });
  const stateRef = useRef(state);
  useLayoutEffect(() => {
    stateRef.current = state;
  });

  // A page made from a template starts on its setup list while products are still missing.
  const [leftTab, setLeftTab] = useState(() => (unfilledSlots(state.doc, state.details.template?.slots ?? []).length > 0 ? "setup" : "blocks"));
  const [dialog, setDialog] = useState(null);
  // Narrow screens start with the panels closed so the page is visible.
  const [leftOpen, setLeftOpen] = useState(() => window.innerWidth >= ROOM_FOR_BOTH_PANELS);
  const [rightOpen, setRightOpen] = useState(() => window.innerWidth >= ROOM_FOR_BOTH_PANELS);
  // Opening a panel where there is no room for two closes the other one, so the page never disappears.
  const toggleLeft = useCallback(() => {
    setLeftOpen((open) => {
      if (!open && window.innerWidth < ROOM_FOR_BOTH_PANELS) setRightOpen(false);
      return !open;
    });
  }, []);
  const toggleRight = useCallback(() => {
    setRightOpen((open) => {
      if (!open && window.innerWidth < ROOM_FOR_BOTH_PANELS) setLeftOpen(false);
      return !open;
    });
  }, []);
  const [drawers, setDrawers] = useState(() => window.innerWidth < DRAWER_WIDTH);
  useEffect(() => {
    const onResize = () => setDrawers(window.innerWidth < DRAWER_WIDTH);
    window.addEventListener("resize", onResize);
    return () => window.removeEventListener("resize", onResize);
  }, []);
  const [problems, setProblems] = useState(null);

  // ── Messages for refused edits ──────────────────────────────────────────
  useEffect(() => {
    if (state.lastError) toast.error(REFUSALS[state.lastError.code] ?? "That change was not possible.");
  }, [state.lastError]);

  // ── Keyboard shortcuts ──────────────────────────────────────────────────
  useEffect(() => {
    const onKeyDown = (event) => {
      const key = event.key.toLowerCase();
      const mod = event.ctrlKey || event.metaKey;
      if (mod && key === "s") {
        event.preventDefault();
        actions.saveNow();
        return;
      }
      const typing = event.target.closest?.("input, textarea, select, [contenteditable]:not([contenteditable='false'])");
      if (typing || document.querySelector("[role='dialog']")) return;

      if (mod && key === "z") {
        event.preventDefault();
        if (event.shiftKey) actions.redo();
        else actions.undo();
        return;
      }
      if (mod && key === "y") {
        event.preventDefault();
        actions.redo();
        return;
      }

      const selected = stateRef.current.selectedId;
      if (mod && key === "v") {
        event.preventDefault();
        if (actions.paste() === false) toast("Nothing copied yet");
        return;
      }
      if (!selected) return;

      if (event.key === "Delete" || event.key === "Backspace") {
        event.preventDefault();
        actions.remove(selected);
      } else if (mod && key === "d") {
        event.preventDefault();
        actions.duplicate(selected);
      } else if (mod && key === "c") {
        if (window.getSelection()?.toString()) return;
        if (actions.copy(selected)) toast.success("Block copied");
      } else if (event.altKey && event.key === "ArrowUp") {
        event.preventDefault();
        actions.nudge(selected, -1);
      } else if (event.altKey && event.key === "ArrowDown") {
        event.preventDefault();
        actions.nudge(selected, 1);
      } else if (event.key === "Escape") {
        actions.select(null);
      }
    };
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [actions]);

  // ── Drag and drop ───────────────────────────────────────────────────────
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }));
  const [activeDrag, setActiveDrag] = useState(null);
  const [dropTarget, setDropTarget] = useState(null);
  const targetRef = useRef(null);
  const startRef = useRef(null);
  const pointRef = useRef(null);
  const frameRequest = useRef(0);

  const updateTarget = useCallback(() => {
    frameRequest.current = 0;
    const point = pointRef.current;
    const drag = startRef.current?.drag;
    const scroller = document.getElementById("fn-canvas-scroller");
    const frame = document.querySelector("[data-testid='canvas-frame']");
    if (!point || !drag || !scroller || !frame) return;

    const bounds = scroller.getBoundingClientRect();
    const inside = point.x >= bounds.left && point.x <= bounds.right && point.y >= bounds.top && point.y <= bounds.bottom;
    const target = inside
      ? computeDropTarget({ point, drag, doc: stateRef.current.doc, registry: stateRef.current.registry, frame })
      : null;
    if (target) {
      // The indicator is drawn inside the scrolling canvas, so it is stored in
      // the canvas content's own coordinates.
      const raw = target.indicator;
      target.local = {
        kind: raw.kind,
        top: raw.top - bounds.top + scroller.scrollTop,
        left: raw.left - bounds.left + scroller.scrollLeft,
        width: raw.width,
        height: raw.height,
      };
    }
    targetRef.current = target;
    setDropTarget(target);
  }, []);

  // Scrolls the canvas while a block is held near its top or bottom edge.
  useEffect(() => {
    if (!activeDrag) return undefined;
    let raf = 0;
    const tick = () => {
      const scroller = document.getElementById("fn-canvas-scroller");
      const point = pointRef.current;
      if (scroller && point) {
        const bounds = scroller.getBoundingClientRect();
        const inX = point.x >= bounds.left && point.x <= bounds.right;
        if (inX && point.y < bounds.top + AUTOSCROLL_EDGE) scroller.scrollTop -= AUTOSCROLL_STEP;
        else if (inX && point.y > bounds.bottom - AUTOSCROLL_EDGE) scroller.scrollTop += AUTOSCROLL_STEP;
      }
      raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
  }, [activeDrag]);

  const endDrag = () => {
    setActiveDrag(null);
    setDropTarget(null);
    targetRef.current = null;
    startRef.current = null;
    pointRef.current = null;
  };

  const onDragStart = ({ active, activatorEvent }) => {
    const drag = active.data.current;
    startRef.current = { drag, x: activatorEvent.clientX, y: activatorEvent.clientY };
    pointRef.current = { x: activatorEvent.clientX, y: activatorEvent.clientY };
    setActiveDrag(drag);
    actions.hover(null);
  };

  const onDragMove = ({ delta }) => {
    if (!startRef.current) return;
    pointRef.current = { x: startRef.current.x + delta.x, y: startRef.current.y + delta.y };
    if (!frameRequest.current) frameRequest.current = requestAnimationFrame(updateTarget);
  };

  const onDragEnd = () => {
    const drag = startRef.current?.drag;
    const target = targetRef.current;
    endDrag();
    if (!drag || !target) return;
    if (drag.kind === "new") actions.insert(drag.type, target.parentId, target.index, Boolean(target.wrap));
    else actions.move(drag.id, target.parentId, target.index);
  };

  const editor = useMemo(() => ({ state, actions, api }), [state, actions, api]);
  const commerceAdapter = useMemo(() => editorCommerce(api), [api]);
  // Preview runs the real order form without placing an order.
  const checkoutAdapter = useMemo(() => previewCheckout(), []);
  const dragState = useMemo(() => ({ activeDrag, dropTarget }), [activeDrag, dropTarget]);

  return (
    <EditorContext.Provider value={editor}>
      <DragStateContext.Provider value={dragState}>
        <CommerceProvider document={state.doc} adapter={commerceAdapter} interactive={state.preview}>
        <CheckoutProvider document={state.doc} adapter={checkoutAdapter} mode={state.preview ? "preview" : "editor"}>
        <DndContext sensors={sensors} autoScroll={false} onDragStart={onDragStart} onDragMove={onDragMove} onDragEnd={onDragEnd} onDragCancel={endDrag}>
          <div className="flex h-dvh flex-col bg-canvas text-ink" data-testid="funnel-editor">
            <Toolbar
              returnUrl={returnUrl}
              onPublish={() => setDialog("publish")}
              onShare={() => setDialog("share")}
              onHistory={() => setDialog("history")}
              onShowProblems={() => setProblems({ title: "These changes were not saved", intro: "Fix them and the page saves again automatically.", list: state.save.errors })}
              leftOpen={leftOpen}
              rightOpen={rightOpen}
              onToggleLeft={toggleLeft}
              onToggleRight={toggleRight}
            />

            {!state.canEdit ? (
              <p className="m-0 border-b border-warning/20 bg-warning-tint px-4 py-2 text-[13px] text-warning" role="status" data-testid="read-only-banner">
                You can view this funnel but your role cannot edit it.
              </p>
            ) : null}

            <div className="relative flex min-h-0 flex-1">
              {drawers && !state.preview && (leftOpen || rightOpen) ? (
                // On small screens a panel floats over the page; tapping the page closes it.
                <button
                  type="button"
                  className="absolute inset-0 z-10 cursor-default bg-[rgba(19,19,21,.28)]"
                  aria-label="Close panel"
                  onClick={() => {
                    setLeftOpen(false);
                    setRightOpen(false);
                  }}
                  data-testid="panel-backdrop"
                />
              ) : null}
              {leftOpen && !state.preview ? (
                <aside className="absolute inset-y-0 left-0 z-20 flex w-[272px] flex-col border-r border-hairline bg-surface shadow-e3 lg:static lg:shadow-none" aria-label="Blocks and layers">
                  <div className="flex border-b border-hairline" role="tablist">
                    {[
                      ...(state.details.template ? [["setup", "Setup", ClipboardCheck]] : []),
                      ["blocks", "Blocks", LayoutGrid],
                      ["saved", "Saved", Bookmark],
                      ["layers", "Layers", LayersIcon],
                    ].map(([id, label, Icon]) => (
                      <button
                        key={id}
                        type="button"
                        role="tab"
                        aria-selected={leftTab === id}
                        className={cx("flex flex-1 items-center justify-center gap-1.5 border-b-2 py-2.5 text-[13px] font-semibold", leftTab === id ? "border-[#1f6feb] text-ink" : "border-transparent text-ink-tertiary")}
                        onClick={() => setLeftTab(id)}
                        data-testid={`left-tab-${id}`}
                      >
                        <Icon size={15} aria-hidden="true" /> {label}
                      </button>
                    ))}
                  </div>
                  <div className="min-h-0 flex-1 overflow-hidden">{leftTab === "setup" && state.details.template ? <SetupChecklist /> : leftTab === "layers" ? <Layers /> : leftTab === "saved" ? <SavedSections /> : <Library />}</div>
                </aside>
              ) : null}

              <main className="min-w-0 flex-1" aria-label="Page canvas">
                <Canvas />
              </main>

              {rightOpen && !state.preview ? (
                <aside className="absolute inset-y-0 right-0 z-20 flex w-[320px] flex-col border-l border-hairline bg-surface shadow-e3 lg:static lg:shadow-none" aria-label="Settings">
                  <SettingsPanel />
                </aside>
              ) : null}
            </div>
          </div>

          <DragOverlay dropAnimation={null}>
            {activeDrag ? (
              <div className={cx("pointer-events-none inline-flex items-center gap-2 rounded-[6px] px-3 py-2 text-[13px] font-semibold shadow-e3", dropTarget ? "bg-[#1f6feb] text-white" : "bg-surface text-ink-secondary")}>
                {blockMeta(activeDrag.type).label}
                {!dropTarget ? <span className="font-normal">— move over the page</span> : null}
              </div>
            ) : null}
          </DragOverlay>
        </DndContext>

        <PickerHost />
        <PublishDialog open={dialog === "publish"} onClose={() => setDialog(null)} onShare={() => setDialog("share")} />
        <ShareDialog open={dialog === "share"} onClose={() => setDialog(null)} />
        <HistoryDialog open={dialog === "history"} onClose={() => setDialog(null)} />
        <ProblemsDialog open={Boolean(problems)} onClose={() => setProblems(null)} title={problems?.title ?? ""} intro={problems?.intro} problems={problems?.list ?? []} />
        <ConflictDialog open={state.save.status === SAVE.conflict} />
        <SessionEndedDialog open={state.save.status === SAVE.expired} returnUrl={returnUrl} />
        </CheckoutProvider>
        </CommerceProvider>
      </DragStateContext.Provider>
    </EditorContext.Provider>
  );
}
