import { createNextState as produce } from "@reduxjs/toolkit";

/**
 * Editor state: the page document, selection, undo history and save status.
 *
 * Every change to the document goes through the "edit" action with a recipe
 * that mutates an Immer draft. Each edit bumps `version`; autosave compares it
 * with `savedVersion` to know whether there is anything left to save, so an
 * edit made while a save is in flight is never lost.
 *
 * Typing produces one history entry per field per pause rather than one per
 * keystroke: edits that carry the same `mergeKey` within MERGE_MS replace the
 * previous entry's "after" state instead of adding a new one.
 */

const HISTORY_LIMIT = 100;
const MERGE_MS = 1200;

export const SAVE = {
  saved: "saved",
  dirty: "dirty",
  saving: "saving",
  invalid: "invalid",
  conflict: "conflict",
  offline: "offline",
  expired: "expired",
};

export function createInitialState({ funnel, registry, user }) {
  const permissions = user?.permissions ?? {};
  return {
    funnelId: funnel.id,
    registry,
    user,
    canEdit: permissions.edit_funnel === true,
    canPublish: permissions.publish_funnel === true,
    details: pickDetails(funnel),
    doc: normalizeDocument(funnel.draft?.content),
    revision: funnel.draft?.revision ?? 1,
    version: 0,
    savedVersion: 0,
    failedVersion: null,
    past: [],
    future: [],
    mergeKey: null,
    mergedAt: 0,
    selectedId: null,
    hoveredId: null,
    device: "desktop",
    preview: false,
    save: { status: SAVE.saved, errors: [], message: null, savedAt: funnel.draft?.updated_at ?? null },
    publishing: false,
    lastError: null,
    // Product picker: { nodeId, purpose: "add" | "bundle" | "gift", key? } or null.
    picker: null,
    // Offer key highlighted in the settings panel (set by clicking a card).
    focusOffer: null,
  };
}

export function pickDetails(funnel) {
  return {
    title: funnel.title,
    internalName: funnel.internal_name,
    slug: funnel.slug,
    status: funnel.status,
    isLive: funnel.is_live,
    publicUrl: funnel.public_url,
    hasUnpublishedChanges: funnel.has_unpublished_changes,
    metaTitle: funnel.meta_title ?? "",
    metaDescription: funnel.meta_description ?? "",
    publishedVersion: funnel.published_version?.version_number ?? null,
    scheduledAt: funnel.scheduled_at ?? null,
    unpublishAt: funnel.unpublish_at ?? null,
    seo: {
      noindex: Boolean(funnel.seo?.noindex),
      ogTitle: funnel.seo?.og_title ?? "",
      ogDescription: funnel.seo?.og_description ?? "",
      canonicalUrl: funnel.seo?.canonical_url ?? "",
    },
    ogImageUploadId: funnel.og_image_upload_id ?? null,
    ogImageUrl: funnel.og_image_url ?? null,
    template: funnel.template ?? null,
  };
}

export function normalizeDocument(content) {
  const sections = Array.isArray(content?.page?.sections) ? content.page.sections : [];
  const page = { sections };
  if (content?.page?.settings && typeof content.page.settings === "object" && !Array.isArray(content.page.settings)) {
    page.settings = content.page.settings;
  }
  return { schemaVersion: content?.schemaVersion ?? 1, page };
}

export function editorReducer(state, action) {
  switch (action.type) {
    case "edit": {
      if (!state.canEdit) return state;
      let result = null;
      const doc = produce(state.doc, (draft) => {
        result = action.recipe(draft) ?? null;
      });
      if (result?.error) {
        // Refused edits are reported through state; the editor shows a hint.
        return { ...state, lastError: { code: result.error, at: Date.now() } };
      }
      if (doc === state.doc) return state;

      const now = Date.now();
      const merging = action.mergeKey && action.mergeKey === state.mergeKey && now - state.mergedAt < MERGE_MS && state.past.length > 0;
      const past = merging
        ? state.past
        : [...state.past, { doc: state.doc, selectedId: state.selectedId }].slice(-HISTORY_LIMIT);

      const selectedId = action.select === undefined
        ? state.selectedId
        : action.select === "result"
          ? (result?.id ?? result?.select ?? null)
          : action.select;

      return {
        ...state,
        doc,
        past,
        future: [],
        mergeKey: action.mergeKey ?? null,
        mergedAt: now,
        version: state.version + 1,
        selectedId,
        picker: result?.picker ? { nodeId: result.id, purpose: "add" } : state.picker,
        save: state.save.status === SAVE.expired || state.save.status === SAVE.conflict
          ? state.save
          : { ...state.save, status: SAVE.dirty },
      };
    }

    case "undo": {
      if (!state.canEdit || state.past.length === 0) return state;
      const previous = state.past[state.past.length - 1];
      return {
        ...state,
        doc: previous.doc,
        past: state.past.slice(0, -1),
        future: [{ doc: state.doc, selectedId: state.selectedId }, ...state.future].slice(0, HISTORY_LIMIT),
        selectedId: previous.selectedId,
        mergeKey: null,
        version: state.version + 1,
        save: { ...state.save, status: state.save.status === SAVE.expired ? SAVE.expired : SAVE.dirty },
      };
    }

    case "redo": {
      if (!state.canEdit || state.future.length === 0) return state;
      const next = state.future[0];
      return {
        ...state,
        doc: next.doc,
        past: [...state.past, { doc: state.doc, selectedId: state.selectedId }].slice(-HISTORY_LIMIT),
        future: state.future.slice(1),
        selectedId: next.selectedId,
        mergeKey: null,
        version: state.version + 1,
        save: { ...state.save, status: state.save.status === SAVE.expired ? SAVE.expired : SAVE.dirty },
      };
    }

    case "select":
      return state.selectedId === action.id ? state : { ...state, selectedId: action.id, mergeKey: null };

    case "picker/open":
      return { ...state, picker: action.picker };

    case "picker/close":
      return state.picker ? { ...state, picker: null } : state;

    case "focusOffer":
      return { ...state, focusOffer: action.key };

    case "hover":
      return state.hoveredId === action.id ? state : { ...state, hoveredId: action.id };

    case "device":
      return { ...state, device: action.device };

    case "preview":
      return { ...state, preview: action.on, selectedId: action.on ? null : state.selectedId, hoveredId: null };

    case "save/start":
      return { ...state, save: { ...state.save, status: SAVE.saving, message: null } };

    case "save/success": {
      const caughtUp = state.version === action.version;
      return {
        ...state,
        revision: action.revision,
        savedVersion: Math.max(state.savedVersion, action.version),
        failedVersion: null,
        details: { ...state.details, hasUnpublishedChanges: action.hasUnpublishedChanges ?? state.details.hasUnpublishedChanges },
        save: { status: caughtUp ? SAVE.saved : SAVE.dirty, errors: [], message: null, savedAt: action.savedAt ?? state.save.savedAt },
      };
    }

    case "save/invalid":
      return { ...state, failedVersion: action.version, save: { ...state.save, status: SAVE.invalid, errors: action.errors ?? [], message: action.message ?? null } };

    case "save/offline":
      return { ...state, save: { ...state.save, status: SAVE.offline, message: action.message ?? null } };

    case "save/conflict":
      return { ...state, save: { ...state.save, status: SAVE.conflict, message: action.message ?? null, serverRevision: action.revision } };

    case "save/expired":
      return { ...state, save: { ...state.save, status: SAVE.expired, message: action.message ?? null } };

    case "conflict/keep-mine":
      // Save this tab's document on top of the newer revision.
      return { ...state, revision: action.revision, failedVersion: null, save: { ...state.save, status: SAVE.dirty, message: null } };

    case "reload": {
      const fresh = createInitialState({ funnel: action.funnel, registry: state.registry, user: state.user });
      return { ...fresh, device: state.device };
    }

    case "details":
      return { ...state, details: { ...state.details, ...action.patch } };

    case "publish/start":
      return { ...state, publishing: true };

    case "publish/end":
      return { ...state, publishing: false, details: action.details ? { ...state.details, ...action.details } : state.details };

    default:
      return state;
  }
}

/** True while something typed is not yet confirmed saved by the server. */
export function hasUnsavedWork(state) {
  return state.version > state.savedVersion || state.save.status === SAVE.saving;
}
