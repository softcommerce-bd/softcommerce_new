"use client";

import {
  ArrowLeft, CalendarClock, CircleAlert, CircleCheck, CloudOff, Eye, EyeOff, History, Link2, Loader2, Monitor, PanelLeft,
  PanelRight, Redo2, Save, Send, Smartphone, Tablet, Undo2,
} from "lucide-react";
import { formatWhen } from "../../core/dates.js";
import { cx } from "../../core/values.js";
import { SAVE } from "../store.js";
import { useEditor } from "../useEditorState.js";

const DEVICES = [
  ["desktop", "Desktop", Monitor],
  ["tablet", "Tablet", Tablet],
  ["mobile", "Mobile", Smartphone],
];

const STATUS_LABEL = {
  draft: "Draft",
  published: "Published",
  scheduled: "Scheduled",
  unpublished: "Unpublished",
  archived: "Archived",
};

export default function Toolbar({ returnUrl, onPublish, onShare, onHistory, onShowProblems, leftOpen, rightOpen, onToggleLeft, onToggleRight }) {
  const { state, actions } = useEditor();
  const { details } = state;

  return (
    <header className="flex h-14 shrink-0 items-center gap-2 border-b border-hairline bg-surface px-3" data-testid="toolbar">
      <a
        href={returnUrl || "#"}
        className="btn btn-sm btn-ghost px-2"
        aria-label="Back to Sales Funnels"
        title="Back to Sales Funnels"
        onClick={(event) => {
          if (!returnUrl) {
            event.preventDefault();
            window.history.back();
          }
        }}
      >
        <ArrowLeft size={17} aria-hidden="true" />
      </a>
      <button type="button" className="btn-icon btn-icon-sm hidden md:inline-flex" onClick={onToggleLeft} aria-pressed={leftOpen} aria-label="Toggle blocks panel" title="Blocks and layers">
        <PanelLeft size={16} />
      </button>

      <div className="min-w-0 flex-1 px-1">
        <button type="button" className="block max-w-full truncate text-left text-[14px] font-semibold text-ink hover:underline" onClick={() => actions.select(null)} title="Page settings" data-testid="toolbar-title">
          {details.title}
        </button>
        <div className="flex items-center gap-2 overflow-hidden whitespace-nowrap text-[11px] text-ink-tertiary">
          <span className={cx("rounded-full px-1.5 py-px font-semibold", details.status === "published" ? "bg-success-tint text-success" : "bg-canvas-sunken text-ink-secondary")} data-testid="funnel-status">
            {STATUS_LABEL[details.status] ?? details.status}
          </span>
          {details.status === "scheduled" && details.scheduledAt ? <span data-testid="funnel-scheduled-at">{formatWhen(details.scheduledAt)}</span> : null}
          {details.publishedVersion && details.hasUnpublishedChanges ? <span>Unpublished changes</span> : null}
          <SaveIndicator onShowProblems={onShowProblems} />
        </div>
      </div>

      <div className="flex items-center gap-1">
        <button type="button" className="btn-icon btn-icon-sm" onClick={actions.undo} disabled={!state.past.length || !state.canEdit} aria-label="Undo" title="Undo (Ctrl+Z)" data-testid="undo">
          <Undo2 size={16} />
        </button>
        <button type="button" className="btn-icon btn-icon-sm" onClick={actions.redo} disabled={!state.future.length || !state.canEdit} aria-label="Redo" title="Redo (Ctrl+Shift+Z)" data-testid="redo">
          <Redo2 size={16} />
        </button>
      </div>

      <div className="mx-1 flex items-center rounded-[6px] bg-canvas p-0.5" role="group" aria-label="Screen size">
        {DEVICES.map(([id, label, Icon]) => (
          <button
            key={id}
            type="button"
            className={cx("inline-flex size-8 items-center justify-center rounded-[4px] text-ink-secondary", state.device === id && "bg-surface text-ink shadow-e1")}
            onClick={() => actions.setDevice(id)}
            aria-pressed={state.device === id}
            aria-label={label}
            title={label}
            data-testid={`device-${id}`}
          >
            <Icon size={16} />
          </button>
        ))}
      </div>

      <button type="button" className="btn btn-sm btn-secondary" onClick={() => actions.setPreview(!state.preview)} aria-pressed={state.preview} data-testid="preview-toggle">
        {state.preview ? <EyeOff size={15} aria-hidden="true" /> : <Eye size={15} aria-hidden="true" />}
        <span className="hidden lg:inline">{state.preview ? "Back to editing" : "Preview"}</span>
      </button>

      <button type="button" className="btn-icon btn-icon-sm hidden sm:inline-flex" onClick={onShare} aria-label="Share a draft preview link" title="Share a draft preview link" data-testid="share">
        <Link2 size={16} />
      </button>
      <button type="button" className="btn-icon btn-icon-sm hidden sm:inline-flex" onClick={onHistory} aria-label="Version history" title="Version history" data-testid="history">
        <History size={16} />
      </button>

      {state.canEdit ? (
        <button
          type="button"
          className="btn btn-sm btn-secondary"
          onClick={() => actions.saveNow()}
          disabled={state.version <= state.savedVersion || state.save.status === SAVE.saving}
          title="Save (Ctrl+S)"
          data-testid="save"
        >
          <Save size={15} aria-hidden="true" />
          <span className="hidden lg:inline">Save</span>
        </button>
      ) : null}

      <button
        type="button"
        className="btn btn-sm btn-primary"
        onClick={onPublish}
        disabled={state.publishing}
        title={state.canPublish ? "Publish, schedule or take the page offline" : "See the publishing status (your role cannot publish)"}
        data-testid="publish"
      >
        {state.publishing ? <Loader2 size={15} className="animate-spin" aria-hidden="true" /> : details.status === "scheduled" ? <CalendarClock size={15} aria-hidden="true" /> : <Send size={15} aria-hidden="true" />}
        {details.status === "scheduled" ? "Scheduled" : details.isLive ? "Update" : "Publish"}
      </button>

      <button type="button" className="btn-icon btn-icon-sm hidden md:inline-flex" onClick={onToggleRight} aria-pressed={rightOpen} aria-label="Toggle settings panel" title="Settings">
        <PanelRight size={16} />
      </button>
    </header>
  );
}

function SaveIndicator({ onShowProblems }) {
  const { state } = useEditor();
  const { status, savedAt, errors } = state.save;

  const content = {
    [SAVE.saved]: [CircleCheck, savedAt ? `Saved ${new Date(savedAt).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}` : "Saved", "text-success"],
    [SAVE.dirty]: [null, "Unsaved changes", "text-ink-tertiary"],
    [SAVE.saving]: [Loader2, "Saving…", "text-ink-secondary"],
    [SAVE.invalid]: [CircleAlert, `Not saved — ${errors.length} problem${errors.length === 1 ? "" : "s"}`, "text-danger"],
    [SAVE.offline]: [CloudOff, "Offline — retrying", "text-warning"],
    [SAVE.conflict]: [CircleAlert, "Changed in another window", "text-danger"],
    [SAVE.expired]: [CircleAlert, "Session ended", "text-danger"],
  }[status] ?? [null, status, ""];

  const [Icon, label, color] = content;
  const body = (
    <span className={cx("inline-flex items-center gap-1", color)} role="status" aria-live="polite" data-testid="save-status" data-status={status}>
      {Icon ? <Icon size={12} className={status === SAVE.saving ? "animate-spin" : ""} aria-hidden="true" /> : null}
      {label}
    </span>
  );

  return status === SAVE.invalid ? (
    <button type="button" className="underline-offset-2 hover:underline" onClick={onShowProblems}>
      {body}
    </button>
  ) : (
    body
  );
}
