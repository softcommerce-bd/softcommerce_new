"use client";

import { useMemo, useState } from "react";
import { Bookmark, ChevronRight, Copy, Info, Plus, Trash2 } from "lucide-react";
import { BLOCKS } from "../../blocks/index.js";
import { itemMaxLength, maxLength } from "../../core/rules.js";
import { ancestorsOf, findNode } from "../../core/tree.js";
import { locatePath } from "../../core/paths.js";
import { cx } from "../../core/values.js";
import { blockMeta, nodeTitle } from "../registry.js";
import { useCommerce } from "../../commerce/CommerceProvider.jsx";
import { useEditor } from "../useEditorState.js";
import {
  ActionField, ColorField, DateTimeField, IconField, ImageField, LinkField, ListField, NumberField, RichTextField,
  SelectField, StringsField, TextareaField, TextField, ToggleField, VideoField,
} from "./fields.jsx";
import BindingsEditor from "./BindingsEditor.jsx";
import PageSettings from "./PageSettings.jsx";
import StylePanel from "./StylePanel.jsx";
import { SaveSectionDialog } from "./SavedSections.jsx";

const TABS = [
  ["content", "Content"],
  ["style", "Style"],
  ["advanced", "Advanced"],
];

/**
 * Right-hand panel: settings of the selected block, or page settings when
 * nothing is selected.
 */
export default function SettingsPanel() {
  const { state, actions } = useEditor();
  const [tab, setTab] = useState("content");
  const [saving, setSaving] = useState(false);
  const located = state.selectedId ? findNode(state.doc, state.selectedId) : null;

  // Server validation messages for this block, by field path.
  const fieldErrors = useMemo(() => {
    const map = {};
    for (const error of state.save.errors ?? []) {
      const { nodeId, field } = locatePath(state.doc, error.path);
      if (nodeId && nodeId === state.selectedId && field) map[field] = error.message;
    }
    return map;
  }, [state.save.errors, state.doc, state.selectedId]);

  if (!located) return <PageSettings />;

  const { node } = located;
  const meta = blockMeta(node.type);
  const trail = ancestorsOf(state.doc, node.id);
  const locked = Boolean(node.advanced?.locked);

  return (
    <div className="flex h-full flex-col" data-testid="settings-panel">
      <SaveSectionDialog node={node} open={saving} onClose={() => setSaving(false)} />
      <div className="border-b border-hairline px-4 pb-0 pt-3">
        <nav aria-label="Block path" className="mb-1 flex flex-wrap items-center gap-0.5 text-[11px] text-ink-tertiary" data-testid="breadcrumbs">
          <button type="button" className="hover:text-ink hover:underline" onClick={() => actions.select(null)}>
            Page
          </button>
          {trail.map((ancestor) => (
            <span key={ancestor.id} className="inline-flex items-center gap-0.5">
              <ChevronRight size={11} aria-hidden="true" />
              <button type="button" className="hover:text-ink hover:underline" onClick={() => actions.select(ancestor.id)}>
                {blockMeta(ancestor.type).label}
              </button>
            </span>
          ))}
        </nav>
        <div className="flex items-center gap-2">
          <h2 className="m-0 min-w-0 flex-1 truncate text-[15px] font-semibold" data-testid="settings-title">{nodeTitle(node)}</h2>
          {state.canEdit ? (
            <>
              {trail.length === 0 ? (
                <button type="button" className="btn-icon btn-icon-sm" onClick={() => setSaving(true)} aria-label="Save as reusable section" title="Save as reusable section" data-testid="save-section">
                  <Bookmark size={15} />
                </button>
              ) : null}
              <button type="button" className="btn-icon btn-icon-sm" onClick={() => actions.duplicate(node.id)} aria-label="Duplicate block" title="Duplicate (Ctrl+D)">
                <Copy size={15} />
              </button>
              <button type="button" className="btn-icon btn-icon-sm" onClick={() => actions.remove(node.id)} disabled={locked} aria-label="Delete block" title="Delete (Del)">
                <Trash2 size={15} />
              </button>
            </>
          ) : null}
        </div>
        <div role="tablist" className="mt-2 flex gap-1" aria-label="Settings">
          {TABS.map(([id, label]) => (
            <button
              key={id}
              type="button"
              role="tab"
              aria-selected={tab === id}
              onClick={() => setTab(id)}
              className={cx("border-b-2 px-3 py-2 text-[13px] font-semibold", tab === id ? "border-[#1f6feb] text-ink" : "border-transparent text-ink-tertiary hover:text-ink")}
              data-testid={`tab-${id}`}
            >
              {label}
            </button>
          ))}
        </div>
      </div>

      <fieldset disabled={!state.canEdit} className="m-0 min-w-0 flex-1 overflow-y-auto border-0 p-0">
        {tab === "content" ? <ContentFields key={node.id} node={node} meta={meta} errors={fieldErrors} /> : null}
        {tab === "style" ? <StylePanel key={node.id} node={node} groups={meta.style} /> : null}
        {tab === "advanced" ? <AdvancedFields key={node.id} node={node} errors={fieldErrors} /> : null}
      </fieldset>
    </div>
  );
}

function ContentFields({ node, meta, errors }) {
  const { state, actions } = useEditor();
  const defaults = BLOCKS[node.type]?.defaults ?? {};
  const props = { ...defaults, ...(node.props ?? {}) };
  const visible = (meta.fields ?? []).filter((field) => !field.showIf || field.showIf(props));

  const renderField = (field, value, onChange, context = {}) => {
    const limit = context.item ? itemMaxLength(state.registry, context.item, field.key) : maxLength(state.registry, node.type, field.key);
    const error = context.item ? undefined : errors[`props.${field.key}`];
    const common = { label: field.label, help: field.help, error, testId: `field-${field.key}` };
    switch (field.type) {
      case "text":
        return <TextField {...common} value={value} maxLength={limit} placeholder={field.placeholder} onChange={(v) => onChange(v)} />;
      case "textarea":
        return <TextareaField {...common} value={value} maxLength={limit} placeholder={field.placeholder} onChange={(v) => onChange(v)} />;
      case "phone":
        return <TextField {...common} value={value} placeholder={field.placeholder} onChange={(v) => onChange(v.trim() || undefined)} />;
      case "select":
        return <SelectField {...common} value={value} options={field.options} onChange={onChange} />;
      case "toggle":
        return <ToggleField {...common} value={Boolean(value)} onChange={onChange} />;
      case "number":
        return <NumberField {...common} value={value} min={field.min} max={field.max} step={field.step} onChange={onChange} />;
      case "color":
        return <ColorField {...common} value={value} onChange={onChange} />;
      case "image":
        return <ImageField {...common} value={value} onChange={onChange} />;
      case "link":
        return <LinkField {...common} value={value} onChange={onChange} />;
      case "datetime":
        return <DateTimeField {...common} value={value} onChange={onChange} />;
      case "icon":
        return <IconField {...common} value={value} optional={field.optional} onChange={onChange} />;
      case "strings":
        return <StringsField {...common} value={value} max={field.max} placeholder={field.placeholder} onChange={onChange} />;
      case "richtext":
        return <RichTextField {...common} value={value} onChange={onChange} />;
      case "list":
        return <ListField field={field} value={value} onChange={onChange} renderField={renderField} />;
      default:
        return null;
    }
  };

  return (
    <div className="flex flex-col gap-4 p-4">
      {meta.notice ? (
        <p className="m-0 flex gap-2 rounded-[6px] bg-warning-tint px-3 py-2 text-[12px] text-warning">
          <Info size={14} className="mt-0.5 shrink-0" aria-hidden="true" /> {meta.notice}
        </p>
      ) : null}
      {meta.note ? <p className="m-0 text-[12px] text-ink-tertiary">{meta.note}</p> : null}

      {visible.map((field) => {
        const set = (value) => actions.setProp(node.id, [field.key], value, field.type === "text" || field.type === "textarea");

        if (field.type === "action") {
          return (
            <ActionField
              key={field.key}
              label={field.label}
              action={props[field.key]}
              link={props[field.linkKey]}
              actions={field.actions}
              onChange={(action, link) => {
                actions.edit((draft) => {
                  const target = findNode(draft, node.id)?.node;
                  if (!target) return { error: "missing" };
                  target.props = { ...(target.props ?? {}), [field.key]: action };
                  if (link === undefined) delete target.props[field.linkKey];
                  else target.props[field.linkKey] = link;
                  return { id: node.id };
                });
              }}
            />
          );
        }

        if (field.type === "video") {
          return (
            <VideoField
              key={field.key}
              label={field.label}
              value={props[field.key]}
              provider={props[field.providerKey]}
              onChange={(url, provider) => {
                actions.edit((draft) => {
                  const target = findNode(draft, node.id)?.node;
                  if (!target) return { error: "missing" };
                  target.props = { ...(target.props ?? {}) };
                  if (url === undefined) delete target.props[field.key];
                  else target.props[field.key] = url;
                  if (provider) target.props[field.providerKey] = provider;
                  return { id: node.id };
                }, { mergeKey: `prop:${node.id}:${field.key}` });
              }}
            />
          );
        }

        if (field.type === "bindings") {
          return <BindingsEditor key={field.key} node={node} />;
        }

        if (field.type === "offerKey") {
          return <OfferKeyField key={field.key} label={field.label} value={props[field.key]} onChange={(value) => actions.setProp(node.id, [field.key], value)} />;
        }

        if (field.type === "columns") {
          return <ColumnsManager key={field.key} node={node} />;
        }

        return (
          <div key={field.key} data-field={field.key}>
            {renderField(field, props[field.key], set)}
          </div>
        );
      })}

      {visible.length === 0 && !meta.note ? <p className="m-0 text-[13px] text-ink-tertiary">This block has no content settings.</p> : null}
    </div>
  );
}

/** Which offer a price, photo, variation, quantity or badge block follows. */
function OfferKeyField({ label, value, onChange }) {
  const commerce = useCommerce();
  const entries = commerce?.entries ?? [];
  const options = [["", "The offer the customer has selected"]];
  entries.forEach(({ binding }, index) => {
    const title = commerce.offers[binding.key]?.title || "Offer " + (index + 1);
    options.push([binding.key, title]);
  });
  return (
    <SelectField
      label={label}
      value={value && entries.some((entry) => entry.binding.key === value) ? value : ""}
      options={options}
      onChange={(next) => onChange(next || undefined)}
      help={entries.length ? undefined : "Add a product card or grid to the page first."}
      testId="field-offerKey"
    />
  );
}

function ColumnsManager({ node }) {
  const { state, actions } = useEditor();
  const columns = node.children ?? [];
  const max = state.registry.blocks.columns?.max_children ?? 6;
  return (
    <div className="flex flex-col gap-2">
      <span className="text-[12px] font-semibold text-ink-secondary">Columns ({columns.length})</span>
      <div className="flex flex-wrap gap-1.5">
        {columns.map((column, index) => (
          <button key={column.id} type="button" className="btn btn-sm btn-secondary" onClick={() => actions.select(column.id)}>
            Column {index + 1}
          </button>
        ))}
      </div>
      <div className="flex gap-2">
        <button type="button" className="btn btn-sm btn-ghost" onClick={() => actions.addColumn(node.id)} disabled={columns.length >= max}>
          <Plus size={14} aria-hidden="true" /> Add column
        </button>
        {columns.length > 1 ? (
          <button type="button" className="btn btn-sm btn-ghost" onClick={() => actions.remove(columns[columns.length - 1].id)}>
            Remove last column
          </button>
        ) : null}
      </div>
      <p className="m-0 text-[11px] text-ink-tertiary">Select a column to set its width under Style.</p>
    </div>
  );
}

function AdvancedFields({ node, errors }) {
  const { actions } = useEditor();
  const advanced = node.advanced ?? {};
  const [anchor, setAnchor] = useState(advanced.anchor ?? "");

  return (
    <div className="flex flex-col gap-4 p-4">
      <div className="flex flex-col gap-2">
        <span className="text-[12px] font-semibold text-ink-secondary">Show on</span>
        {[
          ["desktop", "Desktop"],
          ["tablet", "Tablet"],
          ["mobile", "Mobile"],
        ].map(([device, label]) => (
          <ToggleField
            key={device}
            label={label}
            value={advanced.hidden?.[device] !== true}
            onChange={(visible) => actions.setAdvanced(node.id, `hidden.${device}`, !visible)}
            testId={`visible-${device}`}
          />
        ))}
        <p className="m-0 text-[11px] text-ink-tertiary">Hidden blocks appear faded in the editor and are left out of the page on that screen size.</p>
      </div>

      <TextField
        label="Anchor name"
        value={anchor}
        placeholder="e.g. order-form"
        help="Lets buttons scroll to this block. Lowercase letters, numbers and dashes."
        error={errors["advanced.anchor"]}
        onChange={(value) => {
          const clean = value.toLowerCase().replace(/[^a-z0-9-]/g, "-").replace(/^-+/, "").slice(0, 41);
          setAnchor(clean);
          actions.setAdvanced(node.id, "anchor", /^[a-z][a-z0-9-]*$/.test(clean) ? clean : undefined);
        }}
        testId="field-anchor"
      />

      <TextField
        label="Layer name"
        value={advanced.label ?? ""}
        maxLength={80}
        help="Only shown in the editor."
        onChange={(value) => actions.setAdvanced(node.id, "label", value || undefined)}
        testId="field-label"
      />

      <ToggleField
        label="Lock position"
        value={Boolean(advanced.locked)}
        help="A locked block cannot be moved or deleted until it is unlocked."
        onChange={(value) => actions.setAdvanced(node.id, "locked", value)}
        testId="field-locked"
      />
    </div>
  );
}
