"use client";

import { useState } from "react";
import { AlertTriangle, ArrowDown, ArrowUp, ChevronDown, ChevronRight, Package, Plus, Trash2 } from "lucide-react";
import { useCommerce } from "../../commerce/CommerceProvider.jsx";
import { cx } from "../../core/values.js";
import { applyOfferToAll, moveBinding, removeBinding, updateBinding } from "../operations.js";
import { useEditor } from "../useEditorState.js";
import { ImageField, NumberField, SelectField, TextareaField, TextField, ToggleField } from "./fields.jsx";
import OfferBuilder from "./OfferBuilder.jsx";

/**
 * The "Products" section of a product block's Content tab: the chosen
 * products as a list the merchant can reorder, remove and open to set the
 * card's own wording and the offer. Everything shown about a product (name,
 * price, stock) is read live from the store; what is stored on the page is
 * only the product reference, the offer rule and the funnel's own wording.
 */
export default function BindingsEditor({ node }) {
  const { state, actions } = useEditor();
  const commerce = useCommerce();
  const bindings = node.productBindings ?? [];
  const range = state.registry.blocks[node.type]?.bindings ?? [0, 40];
  const bundleBlock = node.type === "bundle_offer_selector";
  const single = node.type === "product_card";
  // undefined: automatic (a lone offer is open); null: the merchant closed it.
  const [openKey, setOpenKey] = useState(() => state.focusOffer ?? undefined);

  // A card clicked on the canvas opens its row.
  const [seenFocus, setSeenFocus] = useState(state.focusOffer);
  if (state.focusOffer && state.focusOffer !== seenFocus) {
    setSeenFocus(state.focusOffer);
    if (bindings.some((binding) => binding.key === state.focusOffer)) setOpenKey(state.focusOffer);
  }

  const edit = (key, change, merge = false) =>
    actions.edit((draft) => updateBinding(draft, node.id, key, change), merge ? { mergeKey: `binding:${key}:${merge}` } : {});

  return (
    <div className="flex flex-col gap-2" data-testid="bindings-editor">
      <div className="flex items-center justify-between gap-2">
        <span className="text-[12px] font-semibold text-ink-secondary" data-testid="bindings-count">
          {single ? "Product" : bundleBlock ? "Bundles" : "Products"} ({bindings.length})
        </span>
        {state.canEdit ? (
          <button type="button" className="btn btn-sm btn-primary" onClick={() => actions.openPicker({ nodeId: node.id, purpose: "add" })} disabled={!single && bindings.length >= range[1]} data-testid="add-products">
            <Plus size={14} aria-hidden="true" />
            {single ? (bindings.length ? "Change product" : "Choose product") : bundleBlock ? "Add bundle" : "Add products"}
          </button>
        ) : null}
      </div>

      {bindings.length === 0 ? (
        <p className="m-0 flex items-center gap-2 rounded-[6px] bg-canvas px-3 py-3 text-[13px] text-ink-tertiary">
          <Package size={16} aria-hidden="true" /> No products yet. {single ? "Choose one" : "Add products"} from your catalogue.
        </p>
      ) : null}

      {bindings.map((binding, index) => {
        const resolved = commerce?.offers[binding.key];
        const open = openKey === undefined ? bindings.length === 1 : openKey === binding.key;
        const warnings = (commerce?.warnings ?? []).filter((warning) => warning.path?.startsWith(`bindings[${index}]`));
        const thumb = binding.display?.image?.url ?? resolved?.items?.[0]?.image;
        const bundle = binding.items.length > 1 || bundleBlock;

        return (
          <div key={binding.key} className={cx("rounded-[6px] border", open ? "border-[#1f6feb]" : "border-hairline")} data-testid={`binding-${index}`}>
            <div className="flex items-center gap-1.5 px-2 py-1.5">
              <button type="button" className="flex min-w-0 flex-1 items-center gap-2 text-left" onClick={() => setOpenKey(open ? null : binding.key)} aria-expanded={open}>
                {open ? <ChevronDown size={14} aria-hidden="true" /> : <ChevronRight size={14} aria-hidden="true" />}
                {thumb ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={thumb} alt="" className="size-9 shrink-0 rounded-[4px] bg-canvas-sunken object-cover" />
                ) : (
                  <span className="size-9 shrink-0 rounded-[4px] bg-canvas-sunken" />
                )}
                <span className="flex min-w-0 flex-col">
                  <span className="truncate text-[13px] font-medium" data-testid="binding-title">{resolved?.title || "…"}</span>
                  <span className="truncate text-[11px] text-ink-tertiary">
                    {resolved ? (resolved.price ? resolved.price.offer.text : "—") : "…"}
                    {resolved && !resolved.available ? ` · ${resolved.unavailable_message}` : ""}
                  </span>
                </span>
              </button>
              {warnings.length ? <AlertTriangle size={14} className="shrink-0 text-warning" aria-label="Has a warning" /> : null}
              {state.canEdit && !single ? (
                <>
                  <button type="button" className="btn-icon btn-icon-sm" aria-label="Move up" disabled={index === 0} onClick={() => actions.edit((draft) => moveBinding(draft, node.id, binding.key, -1))}>
                    <ArrowUp size={13} />
                  </button>
                  <button type="button" className="btn-icon btn-icon-sm" aria-label="Move down" disabled={index === bindings.length - 1} onClick={() => actions.edit((draft) => moveBinding(draft, node.id, binding.key, 1))}>
                    <ArrowDown size={13} />
                  </button>
                  <button type="button" className="btn-icon btn-icon-sm" aria-label="Remove" onClick={() => actions.edit((draft) => removeBinding(draft, node.id, binding.key))} data-testid="remove-binding">
                    <Trash2 size={13} />
                  </button>
                </>
              ) : null}
            </div>

            {open ? (
              <div className="flex flex-col gap-4 border-t border-hairline p-3">
                {warnings.map((warning) => (
                  <p key={warning.code + warning.path} className="m-0 rounded-[6px] bg-warning-tint px-3 py-2 text-[12px] text-warning" role="note">
                    {warning.message}
                  </p>
                ))}

                <Section title="Card">
                  <TextField label="Title on the card" value={binding.display?.title ?? ""} placeholder={resolved?.items?.[0]?.name} maxLength={200}
                    help="Only changes this page. The product keeps its own name."
                    onChange={(v) => edit(binding.key, (b) => setDisplay(b, "title", v || undefined), "title")} testId="binding-title-input" />
                  <TextareaField label="Short description" value={binding.display?.description ?? ""} maxLength={1000} onChange={(v) => edit(binding.key, (b) => setDisplay(b, "description", v || undefined), "description")} />
                  <TextField label="Badge (e.g. Best seller)" value={binding.display?.badge ?? ""} maxLength={60} onChange={(v) => edit(binding.key, (b) => setDisplay(b, "badge", v || undefined), "badge")} testId="binding-badge-input" />
                  {single ? <TextField label="Button text" value={binding.display?.ctaText ?? ""} maxLength={60} onChange={(v) => edit(binding.key, (b) => setDisplay(b, "ctaText", v || undefined), "cta")} /> : null}
                  <ImageField label="Picture for this card" help="Leave empty to use the product's photo." value={binding.display?.image} onChange={(v) => edit(binding.key, (b) => setDisplay(b, "image", v))} />
                  {!single ? <ToggleField label="Selected when the page opens" value={Boolean(binding.display?.defaultSelected)} onChange={(v) => edit(binding.key, (b) => setDisplay(b, "defaultSelected", v || undefined))} testId="binding-preselected" /> : null}
                </Section>

                <Section title={bundle ? "Products in this bundle" : "Variation"}>
                  <Items node={node} binding={binding} resolved={resolved} bundle={bundle} edit={edit} />
                </Section>

                <Section title="Offer">
                  <OfferBuilder
                    offer={binding.offer ?? { type: "none" }}
                    resolved={resolved}
                    bundle={bundle}
                    onChange={(offer) => edit(binding.key, (b) => { b.offer = offer; })}
                    onApplyAll={bindings.length > 1 && !single ? () => actions.edit((draft) => applyOfferToAll(draft, node.id, binding.offer ?? { type: "none" })) : undefined}
                    onPickGift={() => actions.openPicker({ nodeId: node.id, purpose: "gift", key: binding.key })}
                  />
                </Section>
              </div>
            ) : null}
          </div>
        );
      })}
    </div>
  );
}

function setDisplay(binding, field, value) {
  if (!binding.display) binding.display = {};
  if (value === undefined || value === null) delete binding.display[field];
  else binding.display[field] = value;
  if (Object.keys(binding.display).length === 0) delete binding.display;
}

function Section({ title, children }) {
  return (
    <section className="flex flex-col gap-3">
      <h4 className="m-0 text-[11px] font-semibold uppercase tracking-wide text-ink-tertiary">{title}</h4>
      {children}
    </section>
  );
}

/** Products inside one offer: a variation choice for a single product, quantities for a bundle. */
function Items({ node, binding, resolved, bundle, edit }) {
  const { actions } = useEditor();

  if (!bundle) {
    const item = binding.items[0];
    const live = resolved?.items?.[0];
    if (!live?.has_variants) return <p className="m-0 text-[12px] text-ink-tertiary">This product has no variations.</p>;
    const options = [["", "Customer chooses"], ...live.variants.map((row) => [row.variant, `${row.variant} · ${row.quantity} in stock`])];
    return (
      <>
        <SelectField
          label="Preselected variation"
          value={item.variant ?? ""}
          options={options}
          onChange={(v) => edit(binding.key, (b) => { b.items[0].variant = v || null; if (!v) b.items[0].lockVariant = false; })}
          testId="binding-variant"
        />
        <ToggleField
          label="Customers cannot change the variation"
          value={Boolean(item.lockVariant)}
          onChange={(v) => edit(binding.key, (b) => { b.items[0].lockVariant = v && b.items[0].variant != null; })}
          help={item.variant == null ? "Choose a variation above to lock it." : undefined}
        />
      </>
    );
  }

  return (
    <div className="flex flex-col gap-2" data-testid="bundle-items">
      {binding.items.map((item, index) => {
        const live = resolved?.items?.[index];
        return (
          <div key={index} className="flex flex-col gap-2 rounded-[6px] bg-canvas p-2">
            <div className="flex items-center gap-2">
              {live?.image ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={live.image} alt="" className="size-9 rounded-[4px] object-cover" />
              ) : null}
              <span className="min-w-0 flex-1 truncate text-[13px] font-medium">{live?.name ?? `Product #${item.productId}`}</span>
              <button type="button" className="btn-icon btn-icon-sm" aria-label="Remove from bundle" disabled={binding.items.length <= 1} onClick={() => edit(binding.key, (b) => { b.items.splice(index, 1); })}>
                <Trash2 size={13} />
              </button>
            </div>
            <div className="grid grid-cols-2 gap-2">
              <NumberField label="Pieces" value={item.quantity} min={1} max={999} onChange={(v) => edit(binding.key, (b) => { b.items[index].quantity = v ?? 1; }, `qty${index}`)} />
              {live?.has_variants ? (
                <SelectField label="Variation" value={item.variant ?? ""} options={[["", "Choose…"], ...live.variants.map((row) => [row.variant, row.variant])]}
                  onChange={(v) => edit(binding.key, (b) => { b.items[index].variant = v || null; b.items[index].lockVariant = Boolean(v); })} />
              ) : null}
            </div>
          </div>
        );
      })}
      {binding.items.length < 10 ? (
        <button type="button" className="btn btn-sm btn-secondary self-start" onClick={() => actions.openPicker({ nodeId: node.id, purpose: "bundle", key: binding.key })}>
          <Plus size={14} aria-hidden="true" /> Add product to this bundle
        </button>
      ) : null}
    </div>
  );
}
