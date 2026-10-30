"use client";

import { useCallback, useEffect, useState } from "react";
import { Copy, Link2, Loader2, ShieldOff } from "lucide-react";
import toast from "react-hot-toast";
import Modal from "@/app/components/ui/Modal";
import { formatWhen } from "../../core/dates.js";
import { cx } from "../../core/values.js";
import { useEditor } from "../useEditorState.js";

const LIFETIMES = [
  [24, "1 day"],
  [72, "3 days"],
  [168, "7 days"],
  [720, "30 days"],
];

/**
 * Secret links to the draft, for showing a page that is not published yet to
 * someone without an account. A link shows the draft as it is when opened,
 * never takes orders, expires, and can be switched off here at any time.
 */
export default function ShareDialog({ open, onClose }) {
  return (
    <Modal open={open} onClose={onClose} title="Share a draft preview" size="xl" bodyClassName="px-6 pb-6">
      <ShareBody onClose={onClose} />
    </Modal>
  );
}

function ShareBody({ onClose }) {
  const { state, api } = useEditor();
  const [links, setLinks] = useState(null);
  const [error, setError] = useState(null);
  const [hours, setHours] = useState(72);
  const [label, setLabel] = useState("");
  const [creating, setCreating] = useState(false);
  const [fresh, setFresh] = useState(null);
  const id = state.funnelId;

  const load = useCallback(async () => {
    try {
      const result = await api.previewLinks(id);
      setLinks(result.data);
      setError(null);
    } catch (e) {
      setError(e.message);
    }
  }, [api, id]);

  useEffect(() => {
    let cancelled = false;
    api
      .previewLinks(id)
      .then((result) => {
        if (!cancelled) setLinks(result.data);
      })
      .catch((e) => {
        if (!cancelled) setError(e.message);
      });
    return () => {
      cancelled = true;
    };
  }, [api, id]);

  const create = async () => {
    setCreating(true);
    setError(null);
    try {
      const result = await api.createPreviewLink(id, hours, label.trim() || undefined);
      setFresh({ url: result.url, expiresAt: result.data.expires_at });
      setLabel("");
      await load();
    } catch (e) {
      setError(e.message);
    } finally {
      setCreating(false);
    }
  };

  const revoke = async (linkId) => {
    try {
      await api.revokePreviewLink(id, linkId);
      toast.success("Link switched off");
      await load();
    } catch (e) {
      setError(e.message);
    }
  };

  const copy = async (url) => {
    try {
      await navigator.clipboard.writeText(url);
      toast.success("Link copied");
    } catch {
      toast.error("Could not copy. Select the link and copy it.");
    }
  };

  const active = (links ?? []).filter((link) => link.is_active);
  const past = (links ?? []).filter((link) => !link.is_active);

  return (
    <div className="flex flex-col gap-5" data-testid="share-dialog">
      <p className="m-0 text-[14px] text-ink-secondary">
        Anyone with the link can see this page while you work on it, without signing in. The preview shows your latest saved changes, cannot place orders, and is hidden from search engines.
      </p>

      {!state.canEdit ? <p className="m-0 text-[13px] text-ink-secondary">Your role cannot create preview links.</p> : (
        <div className="flex flex-col gap-3 rounded-[8px] border border-hairline p-3">
          <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
            <label className="flex flex-col gap-1 text-[13px] font-semibold">
              Who is it for? (optional)
              <input className="field h-10 px-3 text-[14px]" value={label} maxLength={120} onChange={(event) => setLabel(event.target.value)} placeholder="e.g. Client review" data-testid="share-label" />
            </label>
            <label className="flex flex-col gap-1 text-[13px] font-semibold">
              Link works for
              <select className="field h-10 px-3 text-[14px]" value={hours} onChange={(event) => setHours(Number(event.target.value))} data-testid="share-hours">
                {LIFETIMES.map(([value, text]) => (
                  <option key={value} value={value}>
                    {text}
                  </option>
                ))}
              </select>
            </label>
          </div>
          <button type="button" className="btn btn-md btn-primary self-start" onClick={create} disabled={creating} data-testid="share-create">
            {creating ? <Loader2 size={15} className="animate-spin" aria-hidden="true" /> : <Link2 size={15} aria-hidden="true" />} Create link
          </button>
        </div>
      )}

      {fresh ? (
        <div className="flex flex-col gap-2 rounded-[8px] bg-success-tint px-3 py-3" data-testid="share-fresh">
          <span className="text-[13px] font-semibold text-success">Copy this link now. For safety it is not shown again.</span>
          <div className="flex flex-wrap items-center gap-2">
            <input readOnly className="field h-10 min-w-0 flex-1 px-3 text-[13px]" value={fresh.url} onFocus={(event) => event.target.select()} aria-label="Preview link" data-testid="share-url" />
            <button type="button" className="btn btn-sm btn-secondary" onClick={() => copy(fresh.url)} data-testid="share-copy">
              <Copy size={14} aria-hidden="true" /> Copy
            </button>
          </div>
          <span className="text-[12px] text-ink-secondary">Works until {formatWhen(fresh.expiresAt)}.</span>
        </div>
      ) : null}

      {error ? (
        <p className="m-0 text-[13px] text-danger" role="alert" data-testid="share-error">
          {error}
        </p>
      ) : null}

      <div className="flex flex-col gap-2">
        <h3 className="m-0 text-[13px] font-semibold">Active links</h3>
        {links === null ? (
          <p className="m-0 text-[13px] text-ink-tertiary">Loading…</p>
        ) : active.length === 0 ? (
          <p className="m-0 text-[13px] text-ink-tertiary" data-testid="share-empty">No active links.</p>
        ) : (
          <ul className="m-0 flex list-none flex-col gap-2 p-0" data-testid="share-list">
            {active.map((link) => (
              <li key={link.id} className="flex flex-wrap items-center gap-2 rounded-[6px] border border-hairline px-3 py-2 text-[13px]" data-testid="share-item">
                <div className="min-w-0 flex-1">
                  <div className="truncate font-semibold">{link.label || "Preview link"}</div>
                  <div className="text-[12px] text-ink-tertiary">
                    Works until {formatWhen(link.expires_at)} · opened {link.use_count} time{link.use_count === 1 ? "" : "s"}
                  </div>
                </div>
                {state.canEdit ? (
                  <button type="button" className="btn btn-sm btn-ghost text-danger" onClick={() => revoke(link.id)} data-testid="share-revoke">
                    <ShieldOff size={14} aria-hidden="true" /> Switch off
                  </button>
                ) : null}
              </li>
            ))}
          </ul>
        )}
        {past.length ? (
          <p className={cx("m-0 text-[12px] text-ink-tertiary")}>{past.length} expired or switched-off link{past.length === 1 ? "" : "s"} from the last 30 days.</p>
        ) : null}
      </div>

      <div className="flex justify-end border-t border-hairline pt-4">
        <button type="button" className="btn btn-md btn-secondary" onClick={onClose}>
          Close
        </button>
      </div>
    </div>
  );
}
