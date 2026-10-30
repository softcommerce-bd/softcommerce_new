"use client";

import { useMemo, useState } from "react";
import { CircleCheck, Circle, Package } from "lucide-react";
import toast from "react-hot-toast";
import { slotState } from "../../core/template.js";
import { cx } from "../../core/values.js";
import { useEditor } from "../useEditorState.js";
import ProductPicker from "./ProductPicker.jsx";

/**
 * The to-do list of a page made from a template: the products it still needs,
 * and the wording the merchant should check. Choosing a product here fills
 * every offer of that slot at once (the page then refreshes with the product's
 * live photos and prices).
 */
export default function SetupChecklist() {
  const { state, actions } = useEditor();
  const template = state.details.template;
  const slots = useMemo(() => template?.slots ?? [], [template]);
  const done = useMemo(() => slotState(state.doc, slots), [state.doc, slots]);
  const [picking, setPicking] = useState(null);
  const [busy, setBusy] = useState(false);
  const storageKey = `misron:funnel-todos:${state.funnelId}`;
  const [ticked, setTicked] = useState(() => {
    try {
      return JSON.parse(window.localStorage.getItem(storageKey) || "[]");
    } catch {
      return [];
    }
  });

  if (!template) {
    return <p className="m-0 p-4 text-[13px] text-ink-tertiary">This page was not made from a template, so there is nothing to set up.</p>;
  }

  const remaining = slots.filter((slot) => !done[slot.key]).length;

  const choose = async (picked) => {
    const slot = picking;
    setPicking(null);
    if (!slot || !picked?.[0]) return;
    setBusy(true);
    const result = await actions.fillSlot(slot.key, picked[0].productId, picked[0].variant ?? null);
    setBusy(false);
    if (result.ok) toast.success("Product added");
    else toast.error(result.message || "Could not add the product. Try again.");
  };

  const toggle = (index) => {
    const next = ticked.includes(index) ? ticked.filter((i) => i !== index) : [...ticked, index];
    setTicked(next);
    try {
      window.localStorage.setItem(storageKey, JSON.stringify(next));
    } catch {
      // The tick is a convenience; losing it is fine.
    }
  };

  return (
    <div className="flex h-full flex-col gap-4 overflow-y-auto p-4" data-testid="setup-checklist">
      <div>
        <h2 className="m-0 text-[15px] font-semibold">Set up “{template.name}”</h2>
        <p className="m-0 mt-1 text-[12px] text-ink-tertiary" data-testid="setup-summary">
          {remaining === 0 ? "Every product is chosen." : `${remaining} product${remaining === 1 ? "" : "s"} still to choose.`}
        </p>
      </div>

      <section className="flex flex-col gap-2" aria-label="Products">
        <h3 className="m-0 text-[12px] font-semibold uppercase tracking-wide text-ink-tertiary">Products</h3>
        {slots.map((slot) => (
          <div key={slot.key} className="flex items-start gap-2 rounded-[8px] border border-hairline px-3 py-2.5" data-testid="setup-slot" data-slot={slot.key} data-done={done[slot.key] ? "true" : "false"}>
            {done[slot.key] ? <CircleCheck size={18} className="mt-0.5 shrink-0 text-success" aria-hidden="true" /> : <Circle size={18} className="mt-0.5 shrink-0 text-ink-tertiary" aria-hidden="true" />}
            <div className="min-w-0 flex-1">
              <div className="text-[14px] font-semibold">{slot.label}</div>
              {slot.hint ? <div className="text-[12px] text-ink-tertiary">{slot.hint}</div> : null}
              {state.canEdit ? (
                <button type="button" className="btn btn-sm btn-secondary mt-2" onClick={() => setPicking(slot)} disabled={busy} data-testid="setup-choose">
                  <Package size={14} aria-hidden="true" /> {done[slot.key] ? "Change product" : "Choose product"}
                </button>
              ) : null}
            </div>
          </div>
        ))}
      </section>

      {template.todos?.length ? (
        <section className="flex flex-col gap-2" aria-label="Things to check">
          <h3 className="m-0 text-[12px] font-semibold uppercase tracking-wide text-ink-tertiary">Check before you publish</h3>
          {template.todos.map((todo, index) => (
            <label key={index} className="flex cursor-pointer items-start gap-2 text-[13px]">
              <input type="checkbox" className="mt-1" checked={ticked.includes(index)} onChange={() => toggle(index)} />
              <span className={cx(ticked.includes(index) && "text-ink-tertiary line-through")}>{todo}</span>
            </label>
          ))}
        </section>
      ) : null}

      <ProductPicker open={Boolean(picking)} onClose={() => setPicking(null)} onConfirm={choose} max={1} title={picking ? `Choose: ${picking.label}` : ""} confirmLabel="Use this product" />
    </div>
  );
}
