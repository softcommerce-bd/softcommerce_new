"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { Bookmark, Copy, Eye, Loader2, Pencil, Plus, Search, Trash2 } from "lucide-react";
import toast from "react-hot-toast";
import Modal from "@/app/components/ui/Modal";
import FunnelPage from "../../render/FunnelPage.jsx";
import { CommerceProvider } from "../../commerce/CommerceProvider.jsx";
import { CheckoutProvider } from "../../commerce/CheckoutProvider.jsx";
import { editorCommerce } from "../../commerce/adapters.js";
import { previewCheckout } from "../../commerce/checkoutAdapters.js";
import { PUBLIC_CONTEXT } from "../../render/parts.jsx";
import { formatWhen } from "../../core/dates.js";
import { blockMeta } from "../registry.js";
import { useEditor } from "../useEditorState.js";

/**
 * Sections saved for reuse on any page ("Saved" tab of the left panel).
 * Search them, look at one, add it to this page, copy, rename or delete it.
 * Sections are saved from the block settings ("Save as reusable section").
 */
export default function SavedSections() {
  const { state, actions, api } = useEditor();
  const [query, setQuery] = useState("");
  const [items, setItems] = useState(null);
  const [error, setError] = useState(null);
  const [previewing, setPreviewing] = useState(null);
  const [busy, setBusy] = useState(null);
  const [confirmDelete, setConfirmDelete] = useState(null);
  const [renaming, setRenaming] = useState(null);

  const load = useCallback(async (search) => {
    setError(null);
    try {
      const result = await api.savedSections(search);
      setItems(result.data ?? []);
    } catch (e) {
      setError(e.message || "Saved sections could not be loaded.");
      setItems((current) => current ?? []);
    }
  }, [api]);

  // Searching waits for a pause in typing.
  useEffect(() => {
    const timer = setTimeout(() => load(query.trim()), query ? 250 : 0);
    return () => clearTimeout(timer);
  }, [query, load]);

  // Reload when another part of the editor saved a section.
  useEffect(() => {
    const reload = () => load(query.trim());
    window.addEventListener("fn:saved-sections-changed", reload);
    return () => window.removeEventListener("fn:saved-sections-changed", reload);
  }, [load, query]);

  const run = async (id, work, success) => {
    setBusy(id);
    try {
      await work();
      if (success) toast.success(success);
      await load(query.trim());
    } catch (e) {
      toast.error(e.message || "That did not work. Try again.");
    } finally {
      setBusy(null);
    }
  };

  const insert = (item) => {
    actions.insertSection(item.node);
    toast.success(`“${item.name}” added to the page`);
  };

  return (
    <div className="flex h-full flex-col" data-testid="saved-sections">
      <div className="border-b border-hairline p-3">
        <label className="relative block">
          <span className="sr-only">Search saved sections</span>
          <Search size={15} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-tertiary" aria-hidden="true" />
          <input
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Search saved sections"
            className="field h-9 pl-9 text-[13px]"
            data-testid="saved-search"
          />
        </label>
      </div>

      <div className="flex-1 overflow-y-auto p-3">
        {items === null ? (
          <div className="flex flex-col gap-2" aria-busy="true" aria-label="Loading saved sections">
            {[0, 1, 2].map((i) => <div key={i} className="h-[74px] animate-pulse rounded-[6px] bg-canvas" />)}
          </div>
        ) : null}

        {error ? <p className="mb-2 rounded-[6px] bg-danger-tint px-3 py-2 text-[13px] text-danger" role="alert">{error}</p> : null}

        {items !== null && items.length === 0 && !error ? (
          <div className="rounded-[8px] border border-dashed border-hairline px-4 py-6 text-center" data-testid="saved-empty">
            <Bookmark size={22} className="mx-auto mb-2 text-ink-tertiary" aria-hidden="true" />
            {query ? (
              <p className="m-0 text-[13px] text-ink-secondary">No saved section matches “{query}”.</p>
            ) : (
              <>
                <p className="m-0 text-[13px] font-semibold text-ink">No saved sections yet</p>
                <p className="mt-1 text-[12px] leading-snug text-ink-tertiary">
                  Select a section on the page, then choose <strong>Save as reusable section</strong> in its settings. It will appear here for every funnel.
                </p>
              </>
            )}
          </div>
        ) : null}

        <ul className="m-0 flex list-none flex-col gap-2 p-0">
          {(items ?? []).map((item) => (
            <li key={item.id} className="rounded-[6px] border border-hairline bg-surface p-2.5" data-testid="saved-item" data-name={item.name}>
              {renaming === item.id ? (
                <RenameForm
                  initial={item.name}
                  onCancel={() => setRenaming(null)}
                  onSave={(name) => {
                    setRenaming(null);
                    run(item.id, () => api.renameSavedSection(item.id, name), "Renamed");
                  }}
                />
              ) : (
                <div className="min-w-0">
                  <p className="m-0 truncate text-[13px] font-semibold text-ink" title={item.name}>{item.name}</p>
                  <p className="m-0 text-[11px] text-ink-tertiary">{blockMeta(item.type).label}{item.updated_at ? ` · ${formatWhen(item.updated_at)}` : ""}</p>
                </div>
              )}

              {confirmDelete === item.id ? (
                <div className="mt-2 flex items-center gap-2 rounded-[6px] bg-danger-tint px-2 py-1.5 text-[12px] text-danger" role="alert">
                  <span className="flex-1">Delete this saved section? Pages already using it keep their copy.</span>
                  <button type="button" className="btn btn-sm btn-danger" onClick={() => { setConfirmDelete(null); run(item.id, () => api.deleteSavedSection(item.id), "Deleted"); }} data-testid="saved-delete-confirm">Delete</button>
                  <button type="button" className="btn btn-sm btn-secondary" onClick={() => setConfirmDelete(null)}>Keep</button>
                </div>
              ) : (
                <div className="mt-2 flex items-center gap-1">
                  <button type="button" className="btn btn-sm btn-primary flex-1" disabled={!state.canEdit || busy === item.id} onClick={() => insert(item)} data-testid="saved-insert">
                    {busy === item.id ? <Loader2 size={14} className="animate-spin" aria-hidden="true" /> : <Plus size={14} aria-hidden="true" />} Add to page
                  </button>
                  <button type="button" className="btn-icon btn-icon-sm" onClick={() => setPreviewing(item)} aria-label={`Preview ${item.name}`} title="Preview" data-testid="saved-preview"><Eye size={15} /></button>
                  <button type="button" className="btn-icon btn-icon-sm" disabled={!state.canEdit} onClick={() => setRenaming(item.id)} aria-label={`Rename ${item.name}`} title="Rename"><Pencil size={15} /></button>
                  <button type="button" className="btn-icon btn-icon-sm" disabled={!state.canEdit || busy === item.id} onClick={() => run(item.id, () => api.duplicateSavedSection(item.id), "Copy made")} aria-label={`Duplicate ${item.name}`} title="Duplicate" data-testid="saved-duplicate"><Copy size={15} /></button>
                  <button type="button" className="btn-icon btn-icon-sm" disabled={!state.canEdit} onClick={() => setConfirmDelete(item.id)} aria-label={`Delete ${item.name}`} title="Delete" data-testid="saved-delete"><Trash2 size={15} /></button>
                </div>
              )}
            </li>
          ))}
        </ul>
      </div>

      <SectionPreview item={previewing} onClose={() => setPreviewing(null)} onInsert={state.canEdit ? (item) => { insert(item); setPreviewing(null); } : null} />
    </div>
  );
}

function RenameForm({ initial, onSave, onCancel }) {
  const [name, setName] = useState(initial);
  return (
    <form
      className="flex items-center gap-1"
      onSubmit={(event) => {
        event.preventDefault();
        if (name.trim()) onSave(name.trim());
      }}
    >
      <input className="field h-8 flex-1 text-[13px]" value={name} maxLength={150} autoFocus onChange={(event) => setName(event.target.value)} aria-label="Section name" onKeyDown={(event) => event.key === "Escape" && onCancel()} />
      <button type="submit" className="btn btn-sm btn-primary" disabled={!name.trim()}>Save</button>
    </form>
  );
}

/** The saved section drawn by the real renderer, with live product data, before adding it. */
function SectionPreview({ item, onClose, onInsert }) {
  const { state, api } = useEditor();
  const document = useMemo(
    () => (item ? { schemaVersion: 1, page: { settings: state.doc.page?.settings ?? {}, sections: [item.node] } } : null),
    [item, state.doc.page?.settings],
  );
  const commerce = useMemo(() => editorCommerce(api), [api]);
  const checkout = useMemo(() => previewCheckout(), []);

  return (
    <Modal open={Boolean(item)} onClose={onClose} title={item ? `Preview: ${item.name}` : ""} size="xl" bodyClassName="px-6 pb-6">
      {document ? (
        <div className="flex flex-col gap-4" data-testid="saved-preview-dialog">
          <div className="max-h-[60vh] overflow-y-auto rounded-[8px] border border-hairline bg-white">
            <div className="pointer-events-none">
              <CommerceProvider document={document} adapter={commerce} interactive={false}>
                <CheckoutProvider document={document} adapter={checkout} mode="editor">
                  <FunnelPage document={document} ctx={PUBLIC_CONTEXT} />
                </CheckoutProvider>
              </CommerceProvider>
            </div>
          </div>
          <div className="flex justify-end gap-2">
            <button type="button" className="btn btn-md btn-secondary" onClick={onClose}>Close</button>
            {onInsert ? <button type="button" className="btn btn-md btn-primary" onClick={() => onInsert(item)} data-testid="saved-preview-insert">Add to page</button> : null}
          </div>
        </div>
      ) : null}
    </Modal>
  );
}

/** "Save as reusable section" for a top-level block, used by the settings panel. */
export function SaveSectionDialog({ node, open, onClose }) {
  const { api } = useEditor();
  const [name, setName] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (open) {
      setName("");
      setError(null);
    }
  }, [open]);

  const save = async (event) => {
    event.preventDefault();
    if (!name.trim() || !node) return;
    setSaving(true);
    setError(null);
    try {
      await api.createSavedSection(name.trim(), node);
      toast.success("Saved. Find it under Saved in the left panel.");
      window.dispatchEvent(new Event("fn:saved-sections-changed"));
      onClose();
    } catch (e) {
      setError(e.message || "The section could not be saved.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal open={open} onClose={onClose} title="Save as reusable section" size="md" bodyClassName="px-6 pb-6">
      <form className="flex flex-col gap-3" onSubmit={save} data-testid="save-section-dialog">
        <p className="m-0 text-[13px] text-ink-secondary">
          The section and everything inside it, including chosen products and offers, can then be added to any funnel from the <strong>Saved</strong> tab.
        </p>
        <label className="flex flex-col gap-1 text-[13px] font-semibold text-ink">
          Name
          <input className="field h-10" value={name} maxLength={150} autoFocus placeholder="e.g. Eid hero with countdown" onChange={(event) => setName(event.target.value)} data-testid="save-section-name" />
        </label>
        {error ? <p className="m-0 rounded-[6px] bg-danger-tint px-3 py-2 text-[13px] text-danger" role="alert">{error}</p> : null}
        <div className="flex justify-end gap-2">
          <button type="button" className="btn btn-md btn-secondary" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn btn-md btn-primary" disabled={!name.trim() || saving} data-testid="save-section-confirm">
            {saving ? <Loader2 size={15} className="animate-spin" aria-hidden="true" /> : <Bookmark size={15} aria-hidden="true" />} Save section
          </button>
        </div>
      </form>
    </Modal>
  );
}
