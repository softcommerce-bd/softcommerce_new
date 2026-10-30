"use client";

import { useEffect, useId, useLayoutEffect, useMemo, useRef, useState } from "react";
import {
  ArrowDown, ArrowUp, Bold, ChevronDown, ChevronRight, Heading2, Heading3, ImagePlus, Italic, Link2, List,
  ListOrdered, Plus, Quote, RemoveFormatting, Trash2, Underline, X,
} from "lucide-react";
import { ICON_NAMES, Icon } from "../../blocks/icons.jsx";
import { toIsoWithOffset, toLocalInput } from "../../core/dates.js";
import { cx, imageSrc, safeHref, videoSource, whatsappHref } from "../../core/values.js";
import { pageAnchors } from "../operations.js";
import { sanitizeRichHtml } from "../richtext.js";
import { useEditor } from "../useEditorState.js";
import MediaPicker from "./MediaPicker.jsx";

/**
 * Inputs for the settings panel. Each takes `value` and `onChange(value)`;
 * `onChange(undefined)` clears a setting back to the block default.
 */

export function FieldShell({ label, help, error, htmlFor, children, inline = false }) {
  return (
    <div className={cx("flex", inline ? "items-center justify-between gap-3" : "flex-col gap-1.5")}>
      {label ? (
        <label htmlFor={htmlFor} className="text-[12px] font-semibold text-ink-secondary">
          {label}
        </label>
      ) : null}
      {children}
      {help && !error ? <p className="m-0 text-[11px] leading-snug text-ink-tertiary">{help}</p> : null}
      {error ? (
        <p className="m-0 text-[11px] leading-snug text-danger" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
}

const INPUT = "field h-9 px-3 text-[13px]";

export function TextField({ label, value, onChange, placeholder, help, error, maxLength, testId }) {
  const id = useId();
  return (
    <FieldShell label={label} help={help} error={error} htmlFor={id}>
      <input
        id={id}
        type="text"
        className={cx(INPUT, error && "field-error")}
        value={value ?? ""}
        placeholder={placeholder}
        maxLength={maxLength}
        onChange={(event) => onChange(event.target.value)}
        data-testid={testId}
      />
    </FieldShell>
  );
}

export function TextareaField({ label, value, onChange, placeholder, help, error, maxLength, testId }) {
  const id = useId();
  return (
    <FieldShell label={label} help={help} error={error} htmlFor={id}>
      <textarea
        id={id}
        className={cx("field field-textarea min-h-[84px] px-3 text-[13px]", error && "field-error")}
        value={value ?? ""}
        placeholder={placeholder}
        maxLength={maxLength}
        onChange={(event) => onChange(event.target.value)}
        data-testid={testId}
      />
      {maxLength ? <span className="self-end text-[10px] text-ink-quaternary">{(value ?? "").length} / {maxLength}</span> : null}
    </FieldShell>
  );
}

export function SelectField({ label, value, onChange, options, help, error, testId }) {
  const id = useId();
  return (
    <FieldShell label={label} help={help} error={error} htmlFor={id}>
      <select id={id} className={cx(INPUT, "pr-8")} value={value ?? ""} onChange={(event) => onChange(event.target.value)} data-testid={testId}>
        {options.map(([optionValue, optionLabel]) => (
          <option key={optionValue} value={optionValue}>
            {optionLabel}
          </option>
        ))}
      </select>
    </FieldShell>
  );
}

export function ToggleField({ label, value, onChange, help, testId }) {
  const id = useId();
  return (
    <FieldShell label={label} help={help} htmlFor={id} inline>
      <button
        id={id}
        type="button"
        role="switch"
        aria-checked={Boolean(value)}
        onClick={() => onChange(!value)}
        data-testid={testId}
        className={cx("relative h-5 w-9 shrink-0 rounded-full transition-colors", value ? "bg-[#1f6feb]" : "bg-hairline-strong")}
      >
        <span className={cx("absolute left-0 top-0.5 size-4 rounded-full bg-white shadow transition-transform", value ? "translate-x-[18px]" : "translate-x-0.5")} />
      </button>
    </FieldShell>
  );
}

export function NumberField({ label, value, onChange, min, max, step = 1, unit, placeholder, help, error, testId }) {
  const id = useId();
  return (
    <FieldShell label={label} help={help} error={error} htmlFor={id}>
      <div className="relative">
        <input
          id={id}
          type="number"
          className={cx(INPUT, unit && "pr-9", error && "field-error")}
          value={value ?? ""}
          min={min}
          max={max}
          step={step}
          placeholder={placeholder}
          onChange={(event) => {
            const raw = event.target.value;
            if (raw === "") return onChange(undefined);
            const number = Number(raw);
            if (Number.isFinite(number)) onChange(Math.min(max ?? number, Math.max(min ?? number, number)));
          }}
          data-testid={testId}
        />
        {unit ? <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-ink-tertiary">{unit}</span> : null}
      </div>
    </FieldShell>
  );
}

const SWATCHES = ["#ffffff", "#131315", "#52525b", "#e8590c", "#d6336c", "#2f9e44", "#1971c2", "#f59f00", "#7048e8", "#c92a2a"];

export function ColorField({ label, value, onChange, placeholder, help, testId }) {
  const id = useId();
  const [text, setText] = useState(value ?? "");
  // Follow changes from outside (undo, swatches) without an effect.
  const [shown, setShown] = useState(value);
  if (shown !== value) {
    setShown(value);
    setText(value ?? "");
  }
  const valid = (v) => /^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(v) || v === "transparent";

  return (
    <FieldShell label={label} help={help} htmlFor={id}>
      <div className="flex items-center gap-2">
        <input
          type="color"
          aria-label={`${label} picker`}
          className="h-9 w-10 shrink-0 cursor-pointer rounded-[3px] border border-hairline bg-surface p-0.5"
          value={/^#[0-9a-f]{6}$/i.test(value ?? "") ? value : "#ffffff"}
          onChange={(event) => onChange(event.target.value)}
        />
        <input
          id={id}
          type="text"
          className={cx(INPUT, text && !valid(text) && "field-error")}
          value={text}
          placeholder={placeholder ?? "Default"}
          onChange={(event) => {
            const next = event.target.value.trim();
            setText(next);
            if (next === "") onChange(undefined);
            else if (valid(next)) onChange(next.toLowerCase());
          }}
          data-testid={testId}
        />
        {value ? (
          <button type="button" className="btn-icon btn-icon-sm" onClick={() => onChange(undefined)} aria-label={`Reset ${label}`} title="Reset">
            <X size={14} />
          </button>
        ) : null}
      </div>
      <div className="flex flex-wrap gap-1.5">
        {SWATCHES.map((swatch) => (
          <button
            key={swatch}
            type="button"
            className={cx("size-5 rounded-full border border-hairline-strong", value === swatch && "ring-2 ring-[#1f6feb] ring-offset-1")}
            style={{ backgroundColor: swatch }}
            onClick={() => onChange(swatch)}
            aria-label={`Use ${swatch}`}
            title={swatch}
          />
        ))}
      </div>
    </FieldShell>
  );
}

export function ImageField({ label, value, onChange, help, testId }) {
  const [open, setOpen] = useState(false);
  const src = imageSrc(value);
  return (
    <FieldShell label={label} help={help}>
      <div className="flex flex-col gap-2 rounded-[6px] border border-hairline p-2">
        {src ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={src} alt="" className="max-h-36 w-full rounded-[4px] bg-canvas-sunken object-contain" />
        ) : (
          <div className="flex h-20 items-center justify-center rounded-[4px] bg-canvas-sunken text-[12px] text-ink-tertiary">No image</div>
        )}
        <div className="flex gap-2">
          <button type="button" className="btn btn-sm btn-secondary flex-1" onClick={() => setOpen(true)} data-testid={testId}>
            <ImagePlus size={15} aria-hidden="true" /> {src ? "Change" : "Choose image"}
          </button>
          {src ? (
            <button type="button" className="btn btn-sm btn-ghost" onClick={() => onChange(undefined)}>
              Remove
            </button>
          ) : null}
        </div>
        {src ? (
          <input
            type="text"
            className={INPUT}
            placeholder="Describe the image (for screen readers)"
            value={value?.alt ?? ""}
            maxLength={200}
            onChange={(event) => onChange({ ...value, alt: event.target.value })}
            aria-label="Image description"
          />
        ) : null}
      </div>
      <MediaPicker open={open} onClose={() => setOpen(false)} onSelect={(image) => onChange(image)} />
    </FieldShell>
  );
}

export function LinkField({ label, value, onChange, help }) {
  const id = useId();
  const invalid = value && !safeHref(value);
  return (
    <FieldShell label={label} help={help ?? "https://…, /page, #anchor, tel: or mailto:"} error={invalid ? "This link is not allowed." : null} htmlFor={id}>
      <input id={id} type="text" className={cx(INPUT, invalid && "field-error")} value={value ?? ""} placeholder="https://" onChange={(e) => onChange(e.target.value.trim() || undefined)} />
    </FieldShell>
  );
}

const ACTION_LABELS = {
  scroll: "Scroll to a part of this page",
  link: "Open a link",
  call: "Call a phone number",
  whatsapp: "Open WhatsApp chat",
  checkout: "Scroll to the order form",
};

/** A button's action and the link it needs, edited together. */
export function ActionField({ label, action, link, actions: allowed, onChange }) {
  const { state } = useEditor();
  const anchors = useMemo(() => pageAnchors(state.doc), [state.doc]);
  const [phone, setPhone] = useState(() => phoneFromLink(link));
  const [shownLink, setShownLink] = useState(link);
  if (shownLink !== link) {
    setShownLink(link);
    setPhone(phoneFromLink(link));
  }
  const current = allowed.includes(action) ? action : allowed[0];

  return (
    <div className="flex flex-col gap-2">
      <SelectField
        label={label}
        value={current}
        options={allowed.map((value) => [value, ACTION_LABELS[value]])}
        onChange={(next) => onChange(next, next === current ? link : undefined)}
      />
      {current === "scroll" ? (
        <FieldShell
          label="Part of the page"
          help={anchors.length ? null : "Give a section an anchor name under Advanced, then choose it here."}
        >
          <select className={INPUT} value={link ?? ""} onChange={(e) => onChange(current, e.target.value || undefined)}>
            <option value="">Choose…</option>
            {anchors.map(({ anchor, label: anchorLabel }) => (
              <option key={anchor} value={`#${anchor}`}>
                {anchorLabel === anchor ? `#${anchor}` : `${anchorLabel} (#${anchor})`}
              </option>
            ))}
          </select>
        </FieldShell>
      ) : null}
      {current === "link" ? <LinkField label="Link" value={link} onChange={(value) => onChange(current, value)} /> : null}
      {current === "call" || current === "whatsapp" ? (
        <TextField
          label={current === "call" ? "Phone number" : "WhatsApp number"}
          value={phone}
          placeholder="01XXXXXXXXX"
          onChange={(value) => {
            setPhone(value);
            const digits = value.replace(/[^\d+]/g, "");
            const href = current === "call" ? (digits.length >= 6 ? `tel:${digits}` : undefined) : whatsappHref(value, "") ?? undefined;
            onChange(current, href);
          }}
        />
      ) : null}
    </div>
  );
}

function phoneFromLink(link) {
  if (typeof link !== "string") return "";
  if (link.startsWith("tel:")) return link.slice(4);
  const match = /^https:\/\/wa\.me\/(\d+)/.exec(link);
  return match ? match[1] : "";
}

export function DateTimeField({ label, value, onChange, help, testId }) {
  const id = useId();
  const local = toLocalInput(value);
  const [past, setPast] = useState(false);
  return (
    <FieldShell label={label} help={help} error={past ? "This time has already passed." : null} htmlFor={id}>
      <input
        id={id}
        type="datetime-local"
        className={INPUT}
        value={local}
        onChange={(event) => {
          const picked = event.target.value ? new Date(event.target.value) : null;
          setPast(Boolean(picked && picked.getTime() < Date.now()));
          onChange(picked ? toIsoWithOffset(picked) : undefined);
        }}
        data-testid={testId}
      />
    </FieldShell>
  );
}

export function IconField({ label, value, onChange, optional = false }) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const names = ICON_NAMES.filter((name) => name.includes(query.trim().toLowerCase()));
  return (
    <FieldShell label={label}>
      <div className="flex items-center gap-2">
        <button type="button" className="btn btn-sm btn-secondary flex-1 justify-between" onClick={() => setOpen((v) => !v)} aria-expanded={open}>
          <span className="inline-flex items-center gap-2">
            {value ? <Icon name={value} size={16} /> : null}
            {value || "None"}
          </span>
          <ChevronDown size={14} aria-hidden="true" />
        </button>
        {optional && value ? (
          <button type="button" className="btn-icon btn-icon-sm" onClick={() => onChange(undefined)} aria-label="Remove icon">
            <X size={14} />
          </button>
        ) : null}
      </div>
      {open ? (
        <div className="rounded-[6px] border border-hairline p-2">
          <input type="search" className={cx(INPUT, "mb-2")} placeholder="Search icons" value={query} onChange={(e) => setQuery(e.target.value)} />
          <div className="grid max-h-44 grid-cols-6 gap-1 overflow-y-auto">
            {names.map((name) => (
              <button
                key={name}
                type="button"
                className={cx("flex aspect-square items-center justify-center rounded-[4px] hover:bg-surface-hover", value === name && "bg-[#1f6feb] text-white hover:bg-[#1f6feb]")}
                title={name}
                aria-label={name}
                onClick={() => {
                  onChange(name);
                  setOpen(false);
                }}
              >
                <Icon name={name} size={18} />
              </button>
            ))}
          </div>
        </div>
      ) : null}
    </FieldShell>
  );
}

export function VideoField({ label, value, provider, onChange }) {
  const id = useId();
  const source = value ? videoSource(provider, value) : null;
  return (
    <FieldShell
      label={label}
      htmlFor={id}
      help="YouTube, Facebook or Vimeo link, or a link to an .mp4 file."
      error={value && !source ? "This video link is not supported." : null}
    >
      <input
        id={id}
        type="text"
        className={cx(INPUT, value && !source && "field-error")}
        value={value ?? ""}
        placeholder="https://www.youtube.com/watch?v=…"
        onChange={(event) => {
          const url = event.target.value.trim();
          onChange(url || undefined, detectProvider(url));
        }}
      />
    </FieldShell>
  );
}

function detectProvider(url) {
  if (/youtu\.?be/i.test(url)) return "youtube";
  if (/vimeo\.com/i.test(url)) return "vimeo";
  if (/facebook\.com|fb\.watch/i.test(url)) return "facebook";
  if (/\.(mp4|webm|ogg)(\?|$)/i.test(url)) return "file";
  return undefined;
}

export function StringsField({ label, value, onChange, max = 10, placeholder }) {
  const list = Array.isArray(value) ? value : [];
  return (
    <FieldShell label={label}>
      <div className="flex flex-col gap-1.5">
        {list.map((item, index) => (
          <div key={index} className="flex items-center gap-1.5">
            <input
              type="text"
              className={INPUT}
              value={item}
              placeholder={placeholder}
              onChange={(event) => onChange(list.map((v, i) => (i === index ? event.target.value : v)))}
              aria-label={`${label} ${index + 1}`}
            />
            <button type="button" className="btn-icon btn-icon-sm" onClick={() => onChange(list.filter((_, i) => i !== index))} aria-label="Remove">
              <X size={14} />
            </button>
          </div>
        ))}
        {list.length < max ? (
          <button type="button" className="btn btn-sm btn-ghost self-start" onClick={() => onChange([...list, ""])}>
            <Plus size={14} aria-hidden="true" /> Add
          </button>
        ) : null}
      </div>
    </FieldShell>
  );
}

/**
 * Repeating items (FAQ entries, benefits, testimonials…). Each item is a
 * collapsible card with its own fields.
 */
export function ListField({ field, value, onChange, renderField, max = 50 }) {
  const list = Array.isArray(value) ? value : [];
  const [openIndex, setOpenIndex] = useState(list.length ? 0 : -1);

  const update = (index, item) => onChange(list.map((current, i) => (i === index ? item : current)));
  const move = (index, step) => {
    const target = index + step;
    if (target < 0 || target >= list.length) return;
    const next = [...list];
    [next[index], next[target]] = [next[target], next[index]];
    onChange(next);
    setOpenIndex(target);
  };

  return (
    <FieldShell label={field.label}>
      <div className="flex flex-col gap-1.5">
        {list.map((item, index) => {
          const open = openIndex === index;
          return (
            <div key={index} className="rounded-[6px] border border-hairline">
              <div className="flex items-center gap-1 px-2 py-1.5">
                <button type="button" className="flex min-w-0 flex-1 items-center gap-1.5 text-left text-[13px] text-ink" onClick={() => setOpenIndex(open ? -1 : index)} aria-expanded={open}>
                  {open ? <ChevronDown size={14} aria-hidden="true" /> : <ChevronRight size={14} aria-hidden="true" />}
                  <span className="truncate">{field.itemLabel ? field.itemLabel(item ?? {}, index) : `Item ${index + 1}`}</span>
                </button>
                <button type="button" className="btn-icon btn-icon-sm" onClick={() => move(index, -1)} disabled={index === 0} aria-label="Move up">
                  <ArrowUp size={13} />
                </button>
                <button type="button" className="btn-icon btn-icon-sm" onClick={() => move(index, 1)} disabled={index === list.length - 1} aria-label="Move down">
                  <ArrowDown size={13} />
                </button>
                <button type="button" className="btn-icon btn-icon-sm" onClick={() => onChange(list.filter((_, i) => i !== index))} aria-label="Remove item">
                  <Trash2 size={13} />
                </button>
              </div>
              {open ? (
                <div className="flex flex-col gap-3 border-t border-hairline p-2.5">
                  {field.fields.map((sub) => (
                    <div key={sub.key}>
                      {renderField(sub, item?.[sub.key], (next) => {
                        const copy = { ...(item ?? {}) };
                        if (next === undefined) delete copy[sub.key];
                        else copy[sub.key] = next;
                        update(index, copy);
                      }, { item: field.item })}
                    </div>
                  ))}
                </div>
              ) : null}
            </div>
          );
        })}
        {list.length < max ? (
          <button
            type="button"
            className="btn btn-sm btn-secondary self-start"
            onClick={() => {
              onChange([...list, field.newItem ? field.newItem(list) : {}]);
              setOpenIndex(list.length);
            }}
            data-testid={`add-${field.key}`}
          >
            <Plus size={14} aria-hidden="true" /> Add
          </button>
        ) : null}
      </div>
    </FieldShell>
  );
}

const RICH_COMMANDS = [
  { icon: Bold, label: "Bold", run: () => document.execCommand("bold") },
  { icon: Italic, label: "Italic", run: () => document.execCommand("italic") },
  { icon: Underline, label: "Underline", run: () => document.execCommand("underline") },
  { icon: Heading2, label: "Heading", run: () => document.execCommand("formatBlock", false, "h2") },
  { icon: Heading3, label: "Small heading", run: () => document.execCommand("formatBlock", false, "h3") },
  { icon: List, label: "Bullet list", run: () => document.execCommand("insertUnorderedList") },
  { icon: ListOrdered, label: "Numbered list", run: () => document.execCommand("insertOrderedList") },
  { icon: Quote, label: "Quote", run: () => document.execCommand("formatBlock", false, "blockquote") },
  {
    icon: Link2,
    label: "Link",
    run: () => {
      const url = window.prompt("Link address (https://…, /page or #anchor)");
      if (url && safeHref(url.trim())) document.execCommand("createLink", false, url.trim());
    },
  },
  { icon: RemoveFormatting, label: "Clear formatting", run: () => document.execCommand("removeFormat") },
];

export function RichTextField({ label, value, onChange }) {
  const ref = useRef(null);

  useLayoutEffect(() => {
    const el = ref.current;
    if (el && document.activeElement !== el && el.innerHTML !== (value ?? "")) el.innerHTML = value ?? "";
  });

  const commit = () => onChange(sanitizeRichHtml(ref.current?.innerHTML ?? "") || undefined);

  return (
    <FieldShell label={label}>
      <div className="rounded-[3px] border border-hairline focus-within:border-[var(--brand)]">
        <div className="flex flex-wrap gap-0.5 border-b border-hairline p-1" role="toolbar" aria-label="Formatting">
          {RICH_COMMANDS.map(({ icon: CommandIcon, label: commandLabel, run }) => (
            <button
              key={commandLabel}
              type="button"
              className="btn-icon btn-icon-sm"
              title={commandLabel}
              aria-label={commandLabel}
              onMouseDown={(event) => event.preventDefault()}
              onClick={() => {
                ref.current?.focus();
                run();
                commit();
              }}
            >
              <CommandIcon size={14} />
            </button>
          ))}
        </div>
        <div
          ref={ref}
          className="min-h-[140px] px-3 py-2 text-[13px] leading-relaxed outline-none [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5 [&_h2]:text-[17px] [&_h2]:font-bold [&_h3]:font-semibold [&_a]:text-[#1f6feb] [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:pl-2"
          contentEditable
          suppressContentEditableWarning
          onFocus={() => document.execCommand("defaultParagraphSeparator", false, "p")}
          onInput={commit}
          onBlur={() => {
            commit();
            // Show the cleaned markup once editing stops.
            if (ref.current) ref.current.innerHTML = sanitizeRichHtml(ref.current.innerHTML);
          }}
          onPaste={(event) => {
            event.preventDefault();
            document.execCommand("insertText", false, event.clipboardData.getData("text/plain"));
          }}
          data-testid="richtext-editor"
          role="textbox"
          aria-multiline="true"
          aria-label={label}
        />
      </div>
    </FieldShell>
  );
}
