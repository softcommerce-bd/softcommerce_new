"use client";

import { createContext, useCallback, useContext, useEffect, useLayoutEffect, useMemo, useReducer, useRef } from "react";
import { acceptsChildren, placementProblem } from "../core/rules.js";
import { ancestorsOf, findNode, sectionsOf } from "../core/tree.js";
import {
  addColumn, deleteBlock, duplicateBlock, insertBlock, insertCopy, moveBlock, nudgeBlock, setAdvanced, setPageSetting,
  setProp, setStyle,
} from "./operations.js";
import { blockMeta } from "./registry.js";
import { createInitialState, editorReducer, hasUnsavedWork, pickDetails, SAVE } from "./store.js";

export const EditorContext = createContext(null);

export function useEditor() {
  const value = useContext(EditorContext);
  if (!value) throw new Error("useEditor must be used inside the funnel editor");
  return value;
}

const AUTOSAVE_DELAY = 1200;
const RETRY_DELAYS = [3000, 6000, 12000, 30000];
const CLIPBOARD_KEY = "misron:funnel-editor-clipboard";

/**
 * Owns the editor state and everything that talks to the server about it:
 * debounced autosave, retries while offline, revision conflicts and expired
 * sessions. Components get `state` and `actions` through EditorContext.
 */
export function useEditorState({ funnel, registry, user, api }) {
  const [state, dispatch] = useReducer(editorReducer, { funnel, registry, user }, createInitialState);
  // Latest state for event handlers and timers, which outlive a render.
  const stateRef = useRef(state);
  useLayoutEffect(() => {
    stateRef.current = state;
  });

  // ── Autosave ────────────────────────────────────────────────────────────
  const savingRef = useRef(false);
  const retryRef = useRef(0);
  const timerRef = useRef(null);

  const saveNow = useCallback(async () => {
    const current = stateRef.current;
    if (savingRef.current || !current.canEdit) return false;
    if (current.version <= current.savedVersion) return true;
    if ([SAVE.conflict, SAVE.expired].includes(current.save.status)) return false;

    savingRef.current = true;
    const version = current.version;
    dispatch({ type: "save/start" });
    try {
      const result = await api.saveDraft(current.funnelId, current.doc, current.revision);
      retryRef.current = 0;
      dispatch({
        type: "save/success",
        revision: result.revision,
        version,
        savedAt: result.saved_at,
        hasUnpublishedChanges: result.has_unpublished_changes,
      });
      return true;
    } catch (error) {
      if (error.status === 409) {
        dispatch({ type: "save/conflict", revision: error.body?.revision, message: error.message });
      } else if (error.status === 422) {
        dispatch({ type: "save/invalid", version, errors: error.body?.errors ?? [], message: error.message });
      } else if (error.status === 401) {
        dispatch({ type: "save/expired", message: error.message });
      } else {
        dispatch({ type: "save/offline", message: error.message });
        const delay = RETRY_DELAYS[Math.min(retryRef.current, RETRY_DELAYS.length - 1)];
        retryRef.current += 1;
        clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => saveNow(), delay);
      }
      return false;
    } finally {
      savingRef.current = false;
    }
  }, [api]);

  // Hover and selection changes must not push the save back, so only the
  // values that decide whether to save are dependencies.
  const { version, savedVersion, failedVersion, canEdit } = state;
  const saveStatus = state.save.status;
  useEffect(() => {
    if (!canEdit || version <= savedVersion || version === failedVersion) return undefined;
    if ([SAVE.conflict, SAVE.expired, SAVE.saving, SAVE.offline].includes(saveStatus)) return undefined;
    clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => saveNow(), AUTOSAVE_DELAY);
    return () => clearTimeout(timerRef.current);
  }, [version, savedVersion, failedVersion, canEdit, saveStatus, saveNow]);

  // Leaving with unsaved edits asks first.
  useEffect(() => {
    const warn = (event) => {
      if (hasUnsavedWork(stateRef.current)) {
        event.preventDefault();
        event.returnValue = "";
      }
    };
    window.addEventListener("beforeunload", warn);
    return () => window.removeEventListener("beforeunload", warn);
  }, []);

  // ── Actions ─────────────────────────────────────────────────────────────
  const actions = useMemo(() => {
    /**
     * A publish, schedule, unpublish or similar call: saves pending edits first
     * when asked, shows the busy state, and applies the status the server reports.
     */
    const lifecycle = async (run, { needsSave = true } = {}) => {
      const current = stateRef.current;
      if (!current.canPublish) return { ok: false, reason: "permission" };
      dispatch({ type: "publish/start" });
      try {
        if (needsSave && current.version > current.savedVersion) {
          const saved = await saveNow();
          if (!saved) {
            dispatch({ type: "publish/end" });
            return { ok: false, reason: "save", errors: stateRef.current.save.errors };
          }
        }
        const { result, version = null, warnings = [] } = await run();
        dispatch({ type: "publish/end", details: pickDetails(result.data) });
        return { ok: true, version, warnings, data: result.data };
      } catch (error) {
        dispatch({ type: "publish/end" });
        if (error.status === 401) dispatch({ type: "save/expired", message: error.message });
        return { ok: false, reason: error.status === 422 ? "invalid" : "error", errors: error.body?.errors ?? [], message: error.message };
      }
    };

    const edit = (recipe, options = {}) => dispatch({ type: "edit", recipe, ...options });
    const selected = () => stateRef.current.selectedId;

    const insert = (type, parentId, index, wrap = false) =>
      edit((draft) => insertBlock(draft, registry, { type, parentId, index, wrap, preset: blockMeta(type).preset }), { select: "result" });

    return {
      edit,
      select: (id) => dispatch({ type: "select", id }),
      hover: (id) => dispatch({ type: "hover", id }),
      openPicker: (picker) => dispatch({ type: "picker/open", picker }),
      closePicker: () => dispatch({ type: "picker/close" }),
      focusOffer: (key) => dispatch({ type: "focusOffer", key }),
      setDevice: (device) => dispatch({ type: "device", device }),
      setPreview: (on) => dispatch({ type: "preview", on }),
      undo: () => dispatch({ type: "undo" }),
      redo: () => dispatch({ type: "redo" }),

      insert,

      /** Adds a block next to the selection, or at the end of the page. */
      insertNear(type) {
        const { doc } = stateRef.current;
        const id = selected();
        const located = id ? findNode(doc, id) : null;
        if (located) {
          if (acceptsChildren(registry, located.node.type) && !placementProblem(registry, doc, id, type)) {
            return insert(type, id, located.node.children?.length ?? 0);
          }
          const parentId = located.parent?.id ?? null;
          if (!placementProblem(registry, doc, parentId, type)) return insert(type, parentId, located.index + 1);
          const top = ancestorsOf(doc, id)[0] ?? located.node;
          const at = sectionsOf(doc).findIndex((node) => node.id === top.id);
          return insert(type, null, at + 1, true);
        }
        return insert(type, null, sectionsOf(doc).length, true);
      },

      /**
       * Adds a saved section to the page: after the section that holds the selection,
       * or at the end. It gets fresh block ids and offer keys, so it never clashes
       * with what is already on the page.
       */
      insertSection(node) {
        if (!node?.type) return;
        const { doc } = stateRef.current;
        const id = selected();
        const top = id ? ancestorsOf(doc, id)[0] ?? findNode(doc, id)?.node : null;
        const at = top ? sectionsOf(doc).findIndex((section) => section.id === top.id) + 1 : sectionsOf(doc).length;
        edit((draft) => insertCopy(draft, registry, { node, parentId: null, index: at }), { select: "result" });
      },

      move: (id, parentId, index) => edit((draft) => moveBlock(draft, registry, { id, parentId, index }), { select: id }),
      nudge: (id, step) => edit((draft) => nudgeBlock(draft, id, step), { select: id }),
      duplicate: (id) => edit((draft) => duplicateBlock(draft, registry, id), { select: "result" }),
      remove: (id) => edit((draft) => deleteBlock(draft, id), { select: "result" }),
      addColumn: (id) => edit((draft) => addColumn(draft, registry, id)),

      setProp: (id, path, value, merge = false) =>
        edit((draft) => setProp(draft, id, path, value), merge ? { mergeKey: `prop:${id}:${path.join(".")}` } : {}),
      setStyle: (id, key, value, merge = false) => {
        const device = stateRef.current.device;
        edit((draft) => setStyle(draft, id, device, key, value), merge ? { mergeKey: `style:${id}:${device}:${key}` } : {});
      },
      setAdvanced: (id, key, value) => edit((draft) => setAdvanced(draft, id, key, value)),
      setPageSetting: (key, value, merge = false) =>
        edit((draft) => setPageSetting(draft, key, value), merge ? { mergeKey: `page:${key}` } : {}),

      copy(id) {
        const node = findNode(stateRef.current.doc, id)?.node;
        if (!node) return false;
        try {
          window.localStorage.setItem(CLIPBOARD_KEY, JSON.stringify(node));
          return true;
        } catch {
          return false;
        }
      },
      paste() {
        let node = null;
        try {
          node = JSON.parse(window.localStorage.getItem(CLIPBOARD_KEY) || "null");
        } catch {
          node = null;
        }
        if (!node?.type) return false;
        const { doc } = stateRef.current;
        const id = selected();
        const located = id ? findNode(doc, id) : null;
        let parentId = null;
        let index = sectionsOf(doc).length;
        if (located) {
          if (acceptsChildren(registry, located.node.type) && !placementProblem(registry, doc, id, node.type)) {
            parentId = id;
            index = located.node.children?.length ?? 0;
          } else {
            parentId = located.parent?.id ?? null;
            index = located.index + 1;
          }
        }
        edit((draft) => insertCopy(draft, registry, { node, parentId, index }), { select: "result" });
        return true;
      },

      saveNow,

      /**
       * Publishes now, or at a chosen time (options.at, optional options.until),
       * after making sure the latest edits are saved.
       */
      async publish(options = {}) {
        const current = stateRef.current;
        return lifecycle(async () => {
          const result = options.at
            ? await api.schedule(current.funnelId, options.at, options.until)
            : await api.publish(current.funnelId);
          return { result, version: result.version?.version_number ?? null, warnings: result.warnings ?? [] };
        });
      },

      /** Takes the page offline now. */
      unpublish: () => lifecycle(async () => ({ result: await api.unpublish(stateRef.current.funnelId) }), { needsSave: false }),

      /** Cancels a scheduled start. */
      cancelSchedule: () => lifecycle(async () => ({ result: await api.cancelSchedule(stateRef.current.funnelId) }), { needsSave: false }),

      /** Sets (or with null, clears) the time the live page goes offline by itself. */
      setEndTime: (until) => lifecycle(async () => ({ result: await api.setEndTime(stateRef.current.funnelId, until) }), { needsSave: false }),

      /** Runs the publish checks on the saved draft: { valid, errors, warnings }. */
      async check() {
        const current = stateRef.current;
        if (current.version > current.savedVersion) {
          const saved = await saveNow();
          if (!saved) return { saved: false, valid: false, errors: stateRef.current.save.errors, warnings: [] };
        }
        const result = await api.validate(current.funnelId);
        return { saved: true, valid: result.valid, errors: result.errors ?? [], warnings: result.warnings ?? [] };
      },

      /**
       * Loads a stored version into the draft (the draft it replaces is kept as
       * a restore point on the server). With publish, also makes it live.
       */
      async restoreVersion(versionId, { publish = false } = {}) {
        const current = stateRef.current;
        if (!current.canEdit || (publish && !current.canPublish)) return { ok: false, reason: "permission" };
        if (current.version > current.savedVersion && !(await saveNow())) return { ok: false, reason: "save", errors: stateRef.current.save.errors };
        try {
          const result = await api.rollback(current.funnelId, versionId, publish);
          dispatch({ type: "reload", funnel: result.data });
          return { ok: true };
        } catch (error) {
          if (error.status === 401) dispatch({ type: "save/expired", message: error.message });
          return { ok: false, reason: error.status === 422 ? "invalid" : "error", errors: error.body?.errors ?? [], message: error.message };
        }
      },

      /** Puts a product into one slot of the template this funnel came from, then reloads the page. */
      async fillSlot(slotKey, productId, variant = null) {
        const current = stateRef.current;
        if (!current.canEdit) return { ok: false, reason: "permission" };
        if (current.version > current.savedVersion && !(await saveNow())) return { ok: false, reason: "save", errors: stateRef.current.save.errors };
        try {
          const result = await api.fillTemplateSlot(current.funnelId, slotKey, productId, variant, stateRef.current.revision);
          dispatch({ type: "reload", funnel: result.data });
          return { ok: true };
        } catch (error) {
          if (error.status === 409) dispatch({ type: "save/conflict", revision: error.body?.revision, message: error.message });
          return { ok: false, reason: "error", errors: error.body?.errors ?? [], message: error.message };
        }
      },

      /** Replaces local state with the server's latest draft. */
      async reload() {
        const result = await api.funnel(stateRef.current.funnelId);
        dispatch({ type: "reload", funnel: result.data });
      },

      /** After a conflict: keep this tab's document and save it as the newest revision. */
      async keepMine() {
        const result = await api.funnel(stateRef.current.funnelId);
        dispatch({ type: "conflict/keep-mine", revision: result.data.draft.revision });
      },

      async saveDetails(patch) {
        const result = await api.updateDetails(stateRef.current.funnelId, patch);
        dispatch({ type: "details", patch: pickDetails(result.data) });
        return result.data;
      },
    };
  }, [api, registry, saveNow]);

  return { state, actions, dispatch };
}
