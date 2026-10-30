"use client";

import { useEffect, useState } from "react";
import { CalendarClock, CircleAlert, CircleCheck, Copy, ExternalLink, FlaskConical, Info, Loader2, Power, Send } from "lucide-react";
import toast from "react-hot-toast";
import Modal from "@/app/components/ui/Modal";
import { formatWhen, fromLocalInput, nowLocalInput, toLocalInput } from "../../core/dates.js";
import { locatePath } from "../../core/paths.js";
import { findNode } from "../../core/tree.js";
import { cx } from "../../core/values.js";
import { nodeTitle } from "../registry.js";
import { useEditor } from "../useEditorState.js";

const INPUT = "field h-10 w-full px-3 text-[14px]";

/**
 * What the Publish button opens: the page's checks, then the choice between
 * publishing now and publishing at a set time, plus the page's schedule,
 * address and the way to take it offline.
 *
 * The checks run on the saved draft by the same server code that publishes,
 * so what this dialog says is exactly what Publish will do.
 */
export default function PublishDialog({ open, onClose, onShare }) {
  const { state } = useEditor();
  const { details } = state;
  const title = details.isLive ? "Update or manage this page" : details.status === "scheduled" ? "This page is scheduled" : "Publish this page";

  // The body mounts each time the dialog opens, so it always starts with a fresh check and an empty form.
  return (
    <Modal open={open} onClose={onClose} title={title} size="xl" bodyClassName="px-6 pb-6">
      <PublishBody onClose={onClose} onShare={onShare} />
    </Modal>
  );
}

function PublishBody({ onClose, onShare }) {
  const { state, actions } = useEditor();
  const { details } = state;
  // The checks need the right to edit (they read the draft); a view-only role just sees the status.
  const [checks, setChecks] = useState({ status: state.canEdit ? "running" : "skipped", errors: [], warnings: [] });
  const [mode, setMode] = useState("now");
  const [at, setAt] = useState("");
  const [until, setUntil] = useState("");
  const [endTime, setEndTime] = useState(() => toLocalInput(details.unpublishAt));
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState(null);

  const live = details.isLive;
  const scheduled = details.status === "scheduled";

  useEffect(() => {
    if (!state.canEdit) return undefined;
    let cancelled = false;
    actions
      .check()
      .then((result) => {
        if (!cancelled) setChecks({ status: result.valid ? "ok" : "failed", errors: result.errors, warnings: result.warnings });
      })
      .catch((error) => {
        if (!cancelled) setChecks({ status: "error", errors: [{ message: error.message }], warnings: [] });
      });
    return () => {
      cancelled = true;
    };
  }, [actions, state.canEdit]);

  // On success the dialog closes (a toast confirms); `stay` keeps it open to show the new state.
  const finish = (result, success, { stay = false } = {}) => {
    setBusy(false);
    if (result.ok) {
      if (stay) setNotice({ tone: "success", text: success(result) });
      else onClose();
      return true;
    }
    if (result.reason === "invalid") {
      setChecks({ status: "failed", errors: result.errors, warnings: [] });
      setNotice({ tone: "error", text: result.message || "Fix the problems below first." });
    } else if (result.reason !== "permission") {
      setNotice({ tone: "error", text: result.message || "That did not work. Try again." });
    }
    return false;
  };

  const publish = async () => {
    setBusy(true);
    if (mode === "later") {
      const startIso = fromLocalInput(at);
      const endIso = fromLocalInput(until);
      if (!startIso) {
        setBusy(false);
        setNotice({ tone: "error", text: "Choose the date and time the page should go live." });
        return;
      }
      const result = await actions.publish({ at: startIso, until: endIso });
      if (finish(result, () => `Scheduled for ${formatWhen(startIso)}.`)) toast.success("Page scheduled");
      return;
    }
    const result = await actions.publish();
    if (finish(result, (r) => `Version ${r.version} is live.`)) toast.success(`Version ${result.version} published`);
  };

  const unpublish = async () => {
    setBusy(true);
    const result = await actions.unpublish();
    if (finish(result, () => "The page is offline. Its link shows a not-available page until you publish again.")) toast.success("Page unpublished");
  };

  const cancelSchedule = async () => {
    setBusy(true);
    const result = await actions.cancelSchedule();
    if (finish(result, () => "The schedule was cancelled.")) toast.success("Schedule cancelled");
  };

  const saveEndTime = async (value) => {
    setBusy(true);
    const iso = value ? fromLocalInput(value) : null;
    const result = await actions.setEndTime(iso);
    if (finish(result, () => (iso ? `The page goes offline on ${formatWhen(iso)}.` : "The page stays live until you unpublish it."), { stay: true })) {
      if (!iso) setEndTime("");
    }
  };

  const canPublish = state.canPublish && checks.status !== "running" && !busy;
  const blocked = checks.status === "failed" || checks.status === "error";
  const min = nowLocalInput();

  return (
      <div className="flex flex-col gap-5" data-testid="publish-dialog">
        <StatusLine details={details} />

        {notice ? (
          <p
            className={cx("m-0 flex items-start gap-2 rounded-[8px] px-3 py-2.5 text-[14px]", notice.tone === "success" ? "bg-success-tint text-success" : "bg-danger-tint text-danger")}
            role="status"
            data-testid="publish-notice"
          >
            {notice.tone === "success" ? <CircleCheck size={17} className="mt-0.5 shrink-0" aria-hidden="true" /> : <CircleAlert size={17} className="mt-0.5 shrink-0" aria-hidden="true" />}
            {notice.text}
          </p>
        ) : null}

        {checks.status === "skipped" ? null : <Checks checks={checks} onNavigate={onClose} />}

        {!state.canPublish ? (
          <p className="m-0 text-[13px] text-ink-secondary" data-testid="cannot-publish">
            {state.canEdit
              ? "Your role can edit this page but cannot publish it. Ask someone with publishing rights to do it."
              : "Your role can only look at this page. It cannot publish or edit it."}
          </p>
        ) : (
          <>
            {!live ? (
              <fieldset className="m-0 flex flex-col gap-3 border-0 p-0">
                <legend className="mb-1 text-[13px] font-semibold text-ink">When should customers see it?</legend>
                <label className="flex cursor-pointer items-start gap-2.5 rounded-[8px] border border-hairline px-3 py-2.5 has-[:checked]:border-[#1f6feb] has-[:checked]:bg-[#f3f8ff]">
                  <input type="radio" name="publish-mode" className="mt-1" checked={mode === "now"} onChange={() => setMode("now")} data-testid="publish-mode-now" />
                  <span>
                    <span className="block text-[14px] font-semibold">Publish now</span>
                    <span className="block text-[13px] text-ink-secondary">The page opens for customers straight away.</span>
                  </span>
                </label>
                <label className="flex cursor-pointer items-start gap-2.5 rounded-[8px] border border-hairline px-3 py-2.5 has-[:checked]:border-[#1f6feb] has-[:checked]:bg-[#f3f8ff]">
                  <input type="radio" name="publish-mode" className="mt-1" checked={mode === "later"} onChange={() => setMode("later")} data-testid="publish-mode-later" />
                  <span className="min-w-0 flex-1">
                    <span className="block text-[14px] font-semibold">Schedule</span>
                    <span className="block text-[13px] text-ink-secondary">The page goes live by itself at the time you choose. What is scheduled is the page as it is now.</span>
                  </span>
                </label>
                {mode === "later" ? (
                  <div className="grid gap-3 sm:grid-cols-2">
                    <label className="flex flex-col gap-1 text-[13px] font-semibold">
                      Goes live
                      <input type="datetime-local" className={INPUT} min={min} value={at} onChange={(event) => setAt(event.target.value)} data-testid="schedule-at" />
                    </label>
                    <label className="flex flex-col gap-1 text-[13px] font-semibold">
                      Goes offline (optional)
                      <input type="datetime-local" className={INPUT} min={at || min} value={until} onChange={(event) => setUntil(event.target.value)} data-testid="schedule-until" />
                    </label>
                  </div>
                ) : null}
              </fieldset>
            ) : null}

            {scheduled ? (
              <div className="flex flex-wrap items-center gap-2 rounded-[8px] bg-canvas-sunken px-3 py-2.5 text-[13px]">
                <CalendarClock size={16} aria-hidden="true" />
                <span className="min-w-0 flex-1">Goes live {formatWhen(details.scheduledAt)}.</span>
                <button type="button" className="btn btn-sm btn-secondary" onClick={cancelSchedule} disabled={busy} data-testid="cancel-schedule">
                  Cancel schedule
                </button>
              </div>
            ) : null}

            {live || scheduled ? (
              <div className="flex flex-col gap-2 rounded-[8px] border border-hairline px-3 py-3">
                <span className="text-[13px] font-semibold">Take it offline automatically</span>
                <div className="flex flex-wrap items-end gap-2">
                  <input type="datetime-local" className={cx(INPUT, "max-w-[260px]")} min={min} value={endTime} onChange={(event) => setEndTime(event.target.value)} aria-label="Goes offline at" data-testid="end-time" />
                  <button type="button" className="btn btn-sm btn-secondary" onClick={() => saveEndTime(endTime)} disabled={busy || !endTime} data-testid="end-time-save">
                    Set end time
                  </button>
                  {details.unpublishAt ? (
                    <button type="button" className="btn btn-sm btn-ghost" onClick={() => saveEndTime("")} disabled={busy} data-testid="end-time-clear">
                      Remove end time
                    </button>
                  ) : null}
                </div>
                <span className="text-[12px] text-ink-tertiary">At that time the page stops opening and its link shows a not-available page. Orders already placed are not affected.</span>
              </div>
            ) : null}
          </>
        )}

        {live ? <LiveLink url={details.publicUrl} onShare={onShare} canTest={state.canEdit} /> : null}

        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-hairline pt-4">
          <div>
            {state.canPublish && (live || scheduled) ? (
              <button type="button" className="btn btn-md btn-ghost text-danger" onClick={unpublish} disabled={busy} data-testid="unpublish">
                <Power size={15} aria-hidden="true" /> Unpublish
              </button>
            ) : null}
          </div>
          <div className="flex gap-2">
            <button type="button" className="btn btn-md btn-secondary" onClick={onClose}>
              Close
            </button>
            {state.canPublish ? (
              <button type="button" className="btn btn-md btn-primary" onClick={publish} disabled={!canPublish || blocked || (mode === "later" && !at && !live)} data-testid="publish-confirm">
                {busy || checks.status === "running" ? <Loader2 size={15} className="animate-spin" aria-hidden="true" /> : mode === "later" && !live ? <CalendarClock size={15} aria-hidden="true" /> : <Send size={15} aria-hidden="true" />}
                {live ? "Update live page" : mode === "later" ? "Schedule" : scheduled ? "Publish now instead" : "Publish"}
              </button>
            ) : null}
          </div>
        </div>
      </div>
  );
}

function StatusLine({ details }) {
  const labels = { draft: "Not published yet", published: "Live", scheduled: "Scheduled", unpublished: "Offline", archived: "Archived" };
  return (
    <div className="flex flex-wrap items-center gap-2 text-[13px] text-ink-secondary" data-testid="publish-status">
      <span className={cx("rounded-full px-2 py-0.5 font-semibold", details.isLive ? "bg-success-tint text-success" : "bg-canvas-sunken text-ink-secondary")}>{labels[details.status] ?? details.status}</span>
      {details.publishedVersion ? <span>Version {details.publishedVersion}</span> : null}
      {details.isLive && details.hasUnpublishedChanges ? <span>· Has changes that are not live yet</span> : null}
      {details.isLive && details.unpublishAt ? <span>· Goes offline {formatWhen(details.unpublishAt)}</span> : null}
    </div>
  );
}

function Checks({ checks, onNavigate }) {
  const { state, actions } = useEditor();
  const where = (problem) => {
    const { nodeId } = locatePath(state.doc, problem.path ?? "");
    return nodeId ? findNode(state.doc, nodeId)?.node : null;
  };
  const row = (problem, index, tone) => {
    const node = where(problem);
    const Icon = tone === "error" ? CircleAlert : Info;
    return (
      <li key={`${tone}-${index}`} className="flex items-start gap-2 rounded-[6px] border border-hairline px-3 py-2">
        <Icon size={16} className={cx("mt-0.5 shrink-0", tone === "error" ? "text-danger" : "text-warning")} aria-hidden="true" />
        <div className="min-w-0 flex-1">
          <p className="m-0 text-[14px] text-ink">{problem.message}</p>
          {node ? (
            <button
              type="button"
              className="mt-0.5 text-[12px] text-[#1f6feb] hover:underline"
              onClick={() => {
                actions.select(node.id);
                onNavigate();
              }}
            >
              Go to: {nodeTitle(node)}
            </button>
          ) : null}
        </div>
      </li>
    );
  };

  if (checks.status === "idle" || checks.status === "running") {
    return (
      <p className="m-0 flex items-center gap-2 text-[13px] text-ink-secondary" role="status" data-testid="publish-checking">
        <Loader2 size={15} className="animate-spin" aria-hidden="true" /> Checking your page…
      </p>
    );
  }

  return (
    <div className="flex flex-col gap-2" data-testid="publish-checks" data-state={checks.status}>
      {checks.errors.length ? (
        <>
          <p className="m-0 text-[13px] font-semibold text-danger">Fix these before publishing</p>
          <ul className="m-0 flex max-h-[30vh] list-none flex-col gap-2 overflow-y-auto p-0" data-testid="publish-errors">{checks.errors.map((p, i) => row(p, i, "error"))}</ul>
        </>
      ) : (
        <p className="m-0 flex items-center gap-2 text-[13px] font-semibold text-success">
          <CircleCheck size={16} aria-hidden="true" /> The page passes every check.
        </p>
      )}
      {checks.warnings.length ? (
        <>
          <p className="m-0 mt-1 text-[13px] font-semibold text-warning">Worth a look (does not stop publishing)</p>
          <ul className="m-0 flex max-h-[24vh] list-none flex-col gap-2 overflow-y-auto p-0" data-testid="publish-warnings">{checks.warnings.map((p, i) => row(p, i, "warning"))}</ul>
        </>
      ) : null}
    </div>
  );
}

function LiveLink({ url, onShare, canTest }) {
  const { state, api } = useEditor();
  const [testing, setTesting] = useState(false);
  if (!url) return null;
  // A real order through the live page, marked as a test: it reserves stock and shows in
  // Orders (with a "Test" badge to cancel afterwards) but is left out of the analytics
  // and never reported to Meta or Google.
  const testOrder = async () => {
    const tab = window.open("", "_blank");
    setTesting(true);
    try {
      const result = await api.testOrderLink(state.funnelId);
      if (tab) {
        tab.opener = null;
        tab.location.href = result.url;
      } else window.location.assign(result.url);
    } catch (error) {
      tab?.close();
      toast.error(error.message || "Could not open a test order link.");
    } finally {
      setTesting(false);
    }
  };
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(url);
      toast.success("Link copied");
    } catch {
      toast.error("Could not copy. Select the link and copy it.");
    }
  };
  return (
    <div className="flex flex-col gap-2 rounded-[8px] bg-canvas-sunken px-3 py-3" data-testid="live-link">
      <span className="text-[12px] font-semibold uppercase tracking-wide text-ink-tertiary">Page address</span>
      <div className="flex flex-wrap items-center gap-2">
        <a href={url} target="_blank" rel="noopener noreferrer" className="min-w-0 flex-1 truncate text-[14px] text-[#1f6feb] hover:underline">
          {url}
        </a>
        <button type="button" className="btn btn-sm btn-secondary" onClick={copy}>
          <Copy size={14} aria-hidden="true" /> Copy
        </button>
        <a className="btn btn-sm btn-secondary" href={url} target="_blank" rel="noopener noreferrer">
          <ExternalLink size={14} aria-hidden="true" /> Open
        </a>
        {onShare ? (
          <button type="button" className="btn btn-sm btn-ghost" onClick={onShare}>
            Share a draft preview
          </button>
        ) : null}
      </div>
      {canTest ? (
        <div className="flex flex-wrap items-center gap-2 border-t border-hairline pt-2">
          <button type="button" className="btn btn-sm btn-secondary" onClick={testOrder} disabled={testing} data-testid="test-order">
            {testing ? <Loader2 size={14} className="animate-spin" aria-hidden="true" /> : <FlaskConical size={14} aria-hidden="true" />} Place a test order
          </button>
          <span className="text-[12px] text-ink-tertiary">Opens the live page for 24 hours in test mode: the order is real but marked “Test”, not counted and not sent to ad platforms.</span>
        </div>
      ) : null}
    </div>
  );
}
