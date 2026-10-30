"use client";

import { useEffect, useMemo, useState } from "react";
import { History, Loader2, Monitor, RotateCcw, Send, Smartphone } from "lucide-react";
import toast from "react-hot-toast";
import Modal from "@/app/components/ui/Modal";
import { editorCommerce } from "../../commerce/adapters.js";
import { previewCheckout } from "../../commerce/checkoutAdapters.js";
import { CheckoutProvider } from "../../commerce/CheckoutProvider.jsx";
import { CommerceProvider } from "../../commerce/CommerceProvider.jsx";
import { formatWhen } from "../../core/dates.js";
import { cx } from "../../core/values.js";
import FunnelPage from "../../render/FunnelPage.jsx";
import { normalizeDocument } from "../store.js";
import { useEditor } from "../useEditorState.js";

const PREVIEW_CONTEXT = { mode: "preview" };

/**
 * Every published version and every restore point of the page. Pick one to see
 * it as customers would, then load it back into the draft. Restoring never
 * deletes anything: the draft it replaces is kept as a restore point, and the
 * live page only changes if you also choose to publish.
 */
export default function HistoryDialog({ open, onClose }) {
  return (
    <Modal open={open} onClose={onClose} title="Version history" size="full" bodyClassName="px-6 pb-6">
      <HistoryBody onClose={onClose} />
    </Modal>
  );
}

function HistoryBody({ onClose }) {
  const { state, actions, api } = useEditor();
  const [versions, setVersions] = useState(null);
  const [error, setError] = useState(null);
  const [selected, setSelected] = useState(null);
  const [loaded, setLoaded] = useState(null);
  const [width, setWidth] = useState("desktop");
  const [busy, setBusy] = useState(false);
  const id = state.funnelId;

  useEffect(() => {
    let cancelled = false;
    api
      .versions(id)
      .then((result) => {
        if (!cancelled) setVersions(result.data);
      })
      .catch((e) => {
        if (!cancelled) setError(e.message);
      });
    return () => {
      cancelled = true;
    };
  }, [api, id]);

  const choose = (version) => {
    setSelected(version);
    setLoaded(null);
    setError(null);
    api
      .version(id, version.id)
      .then((result) => setLoaded({ id: version.id, doc: normalizeDocument(result.content) }))
      .catch((e) => setError(e.message));
  };

  const restore = async (publish) => {
    if (!selected) return;
    setBusy(true);
    const result = await actions.restoreVersion(selected.id, { publish });
    setBusy(false);
    if (result.ok) {
      toast.success(publish ? `Version ${selected.version_number} restored and published` : `Version ${selected.version_number} loaded into your draft`);
      onClose();
    } else if (result.reason === "invalid") {
      setError((result.errors?.[0]?.message ?? result.message) || "That version has problems and cannot be restored.");
    } else if (result.reason !== "permission") {
      setError(result.message || "Restoring failed. Try again.");
    }
  };

  const commerce = useMemo(() => editorCommerce(api), [api]);
  const checkout = useMemo(() => previewCheckout(), []);
  const doc = loaded && selected && loaded.id === selected.id ? loaded.doc : null;

  return (
    <div className="grid gap-4 md:grid-cols-[260px_1fr]" data-testid="history-dialog">
      <div className="flex min-h-0 flex-col gap-2">
        {versions === null && !error ? <p className="m-0 text-[13px] text-ink-tertiary">Loading…</p> : null}
        {versions?.length === 0 ? <p className="m-0 text-[13px] text-ink-tertiary" data-testid="history-empty">Nothing has been published yet. Versions appear here each time you publish.</p> : null}
        <ul className="m-0 flex max-h-[62vh] list-none flex-col gap-1.5 overflow-y-auto p-0" data-testid="history-list">
          {(versions ?? []).map((version) => (
            <li key={version.id}>
              <button
                type="button"
                className={cx("flex w-full flex-col gap-0.5 rounded-[8px] border px-3 py-2 text-left", selected?.id === version.id ? "border-[#1f6feb] bg-[#f3f8ff]" : "border-hairline hover:bg-canvas")}
                onClick={() => choose(version)}
                aria-pressed={selected?.id === version.id}
                data-testid="history-item"
                data-version={version.version_number}
              >
                <span className="flex items-center gap-2 text-[14px] font-semibold">
                  <History size={14} aria-hidden="true" /> Version {version.version_number}
                  {version.is_published ? <span className="rounded-full bg-success-tint px-1.5 py-px text-[11px] text-success">Live</span> : null}
                  {version.kind === "restore_point" ? <span className="rounded-full bg-canvas-sunken px-1.5 py-px text-[11px] text-ink-secondary">Restore point</span> : null}
                </span>
                <span className="text-[12px] text-ink-tertiary">
                  {formatWhen(version.created_at)}
                  {version.created_by ? ` · ${version.created_by}` : ""}
                </span>
                {version.label ? <span className="truncate text-[12px] text-ink-secondary">{version.label}</span> : null}
              </button>
            </li>
          ))}
        </ul>
      </div>

      <div className="flex min-w-0 flex-col gap-3">
        {error ? <p className="m-0 text-[13px] text-danger" role="alert" data-testid="history-error">{error}</p> : null}

        {!selected ? (
          <div className="flex min-h-[200px] items-center justify-center rounded-[8px] bg-canvas-sunken text-[14px] text-ink-tertiary">Choose a version to see it.</div>
        ) : (
          <>
            <div className="flex flex-wrap items-center gap-2">
              <strong className="mr-auto text-[14px]">Version {selected.version_number}</strong>
              <div className="flex items-center rounded-[6px] bg-canvas p-0.5" role="group" aria-label="Preview size">
                {[["desktop", "Desktop", Monitor], ["mobile", "Mobile", Smartphone]].map(([key, label, Icon]) => (
                  <button key={key} type="button" className={cx("inline-flex size-8 items-center justify-center rounded-[4px] text-ink-secondary", width === key && "bg-surface text-ink shadow-e1")} onClick={() => setWidth(key)} aria-pressed={width === key} aria-label={label}>
                    <Icon size={16} />
                  </button>
                ))}
              </div>
              {state.canEdit ? (
                <button type="button" className="btn btn-sm btn-secondary" onClick={() => restore(false)} disabled={busy || !doc} data-testid="history-restore">
                  {busy ? <Loader2 size={14} className="animate-spin" aria-hidden="true" /> : <RotateCcw size={14} aria-hidden="true" />} Restore as draft
                </button>
              ) : null}
              {state.canEdit && state.canPublish && selected.kind === "published" && !selected.is_published ? (
                <button type="button" className="btn btn-sm btn-primary" onClick={() => restore(true)} disabled={busy || !doc} data-testid="history-restore-publish">
                  <Send size={14} aria-hidden="true" /> Restore and publish
                </button>
              ) : null}
            </div>
            <p className="m-0 text-[12px] text-ink-tertiary">Restoring replaces your current draft; the draft is kept as a restore point so you can come back to it. The live page changes only if you choose “Restore and publish”.</p>

            <div className="max-h-[56vh] overflow-auto rounded-[8px] border border-hairline bg-canvas-sunken p-2" data-testid="history-preview">
              {doc ? (
                <div className={cx("mx-auto overflow-hidden rounded-[6px] bg-white shadow-e2", width === "mobile" ? "w-[390px] max-w-full" : "w-full")} inert>
                  <CommerceProvider document={doc} adapter={commerce} interactive={false}>
                    <CheckoutProvider document={doc} adapter={checkout} mode="preview">
                      <FunnelPage document={doc} ctx={PREVIEW_CONTEXT} />
                    </CheckoutProvider>
                  </CommerceProvider>
                </div>
              ) : (
                <p className="m-0 flex min-h-[160px] items-center justify-center gap-2 text-[13px] text-ink-tertiary">
                  <Loader2 size={15} className="animate-spin" aria-hidden="true" /> Loading version…
                </p>
              )}
            </div>
          </>
        )}
      </div>
    </div>
  );
}
