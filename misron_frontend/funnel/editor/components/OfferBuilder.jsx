"use client";

import { Gift, Plus, Trash2 } from "lucide-react";
import { cx } from "../../core/values.js";
import { setOfferType } from "../operations.js";
import { DateTimeField, FieldShell, NumberField, SelectField, ToggleField } from "./fields.jsx";

/**
 * Plain-language editor for one offer rule. Every number a merchant types
 * here is a rule ("10% off", "৳500 off"), never a final price for the
 * website: the products keep their own prices, and the preview line shows
 * what the server calculates today.
 */

const TYPES = [
  ["none", "Website price (no extra discount)"],
  ["percent", "Percentage off"],
  ["fixed_amount", "Taka off"],
  ["fixed_price", "Special price"],
  ["quantity_discount", "Buy more, save more"],
  ["bxgy", "Buy some, get some free"],
  ["free_gift", "Free gift"],
];

const HELP = {
  none: "Customers pay the price on your website.",
  percent: "A percentage is taken off the website price.",
  fixed_amount: "A fixed amount in taka is taken off for each item (for a bundle, off the whole bundle).",
  fixed_price: "Customers pay this price for each item (or for the whole bundle). If your website price drops below it, customers pay the lower price.",
  quantity_discount: "The more customers buy, the bigger the discount. The best matching price break applies.",
  bxgy: "For example buy 2, get 1 free: of every 3, one costs nothing.",
  free_gift: "Customers get a second product free with each purchase. The offer pauses if the gift runs out.",
};

export default function OfferBuilder({ offer = { type: "none" }, resolved, bundle, onChange, onApplyAll, onPickGift, applyAllLabel }) {
  const type = offer.type ?? "none";
  const patch = (changes) => {
    const next = { ...offer, ...changes };
    for (const [key, value] of Object.entries(next)) if (value === undefined || value === "" || (key === "freeDelivery" && value === false)) delete next[key];
    onChange(next);
  };

  const price = resolved?.price;
  const reason = resolved?.unavailable_reason;

  return (
    <div className="flex flex-col gap-3" data-testid="offer-builder">
      <SelectField label="Offer type" value={type} options={TYPES} onChange={(next) => onChange(setOfferType(offer, next))} testId="offer-type" help={HELP[type]} />

      {type === "percent" ? <NumberField label="Discount" value={offer.value} min={0.5} max={99.5} step={0.5} unit="%" onChange={(v) => patch({ value: v })} testId="offer-value" /> : null}
      {type === "fixed_amount" ? <NumberField label={bundle ? "Discount on the whole bundle" : "Discount per item"} value={offer.value} min={1} max={1000000} step={1} unit="৳" onChange={(v) => patch({ value: v })} testId="offer-value" /> : null}
      {type === "fixed_price" ? <NumberField label={bundle ? "Bundle price" : "Price per item"} value={offer.value} min={1} max={1000000} step={1} unit="৳" onChange={(v) => patch({ value: v })} testId="offer-value" /> : null}

      {type === "bxgy" ? (
        <div className="grid grid-cols-2 gap-3">
          <NumberField label="Customer buys" value={offer.buyQty} min={1} max={99} onChange={(v) => patch({ buyQty: v })} testId="offer-buy" />
          <NumberField label="Gets free" value={offer.getQty} min={1} max={99} onChange={(v) => patch({ getQty: v })} testId="offer-get" />
        </div>
      ) : null}

      {type === "quantity_discount" ? <Tiers tiers={offer.tiers ?? []} onChange={(tiers) => patch({ tiers })} /> : null}

      {type === "free_gift" ? (
        <FieldShell label="Gift product">
          <div className="flex items-center gap-2 rounded-[6px] border border-hairline p-2">
            {resolved?.gift?.image ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={resolved.gift.image} alt="" className="size-10 rounded-[4px] object-cover" />
            ) : (
              <span className="inline-flex size-10 items-center justify-center rounded-[4px] bg-canvas-sunken text-ink-tertiary"><Gift size={18} /></span>
            )}
            <span className="min-w-0 flex-1 truncate text-[13px]">{resolved?.gift?.name ?? "No gift chosen yet"}</span>
            <button type="button" className="btn btn-sm btn-secondary" onClick={onPickGift} data-testid="pick-gift">
              {offer.giftProductId ? "Change" : "Choose"}
            </button>
          </div>
          <NumberField label="Gifts per item" value={offer.giftQuantity} min={1} max={10} onChange={(v) => patch({ giftQuantity: v })} />
        </FieldShell>
      ) : null}

      <ToggleField label="Free delivery with this offer" value={Boolean(offer.freeDelivery)} onChange={(v) => patch({ freeDelivery: v })} testId="offer-free-delivery" />
      <NumberField label="Most a customer can order (optional)" value={offer.maxPerOrder} min={1} max={99} onChange={(v) => patch({ maxPerOrder: v })} help="Leave empty for no limit." testId="offer-max" />
      <div className="grid grid-cols-1 gap-3">
        <DateTimeField label="Offer starts (optional)" value={offer.startsAt} onChange={(v) => patch({ startsAt: v })} />
        <DateTimeField label="Offer ends (optional)" value={offer.endsAt} onChange={(v) => patch({ endsAt: v })} />
      </div>

      <div className={cx("rounded-[6px] px-3 py-2 text-[12px] leading-snug", reason && reason !== "out_of_stock" ? "bg-warning-tint text-warning" : "bg-[#eef6f1] text-[#12805c]")} data-testid="offer-preview">
        {!resolved ? (
          "Calculating…"
        ) : reason && !price ? (
          resolved.unavailable_message
        ) : (
          <>
            {price ? (
              <>
                Customers pay <strong>{price.offer.text}</strong>
                {price.has_discount ? (
                  <>
                    {" "}
                    instead of {price.regular.text} — saving {price.savings.text} ({Math.round(price.savings_percent)}%)
                  </>
                ) : null}
                {bundle ? " per bundle." : " each."}
              </>
            ) : null}
            {reason ? <span className="block">{resolved.unavailable_message}</span> : null}
            {resolved.status !== "active" ? <span className="block">This offer is {resolved.status === "scheduled" ? "scheduled for later" : "over"}, so customers cannot order it.</span> : null}
          </>
        )}
      </div>
      {resolved?.notes?.map((note) => (
        <p key={note.code} className="m-0 rounded-[6px] bg-warning-tint px-3 py-2 text-[12px] text-warning" role="note">
          {note.message}
        </p>
      ))}

      {onApplyAll ? (
        <button type="button" className="btn btn-sm btn-secondary h-auto self-start whitespace-normal py-2 text-left" onClick={onApplyAll} data-testid="apply-offer-all">
          {applyAllLabel ?? "Use this offer for all products"}
        </button>
      ) : null}
    </div>
  );
}

function Tiers({ tiers, onChange }) {
  const update = (index, changes) => onChange(tiers.map((tier, i) => (i === index ? { ...tier, ...changes } : tier)));
  return (
    <FieldShell label="Price breaks" help="Customers get the best break whose quantity they reach.">
      <div className="flex flex-col gap-2" data-testid="tiers">
        {tiers.map((tier, index) => (
          <div key={index} className="grid grid-cols-[64px_1fr_72px_28px] items-end gap-1.5">
            <NumberField label={index === 0 ? "From qty" : undefined} value={tier.minQty} min={2} max={999} onChange={(v) => update(index, { minQty: v })} testId={`tier-qty-${index}`} />
            <SelectField label={index === 0 ? "Discount" : undefined} value={tier.type} options={[["percent", "% off each"], ["fixed_amount", "৳ off each"]]} onChange={(v) => update(index, { type: v })} />
            <NumberField label={index === 0 ? "Value" : undefined} value={tier.value} min={0.5} max={1000000} step={0.5} onChange={(v) => update(index, { value: v })} testId={`tier-value-${index}`} />
            <button type="button" className="btn-icon btn-icon-sm mb-1" aria-label="Remove price break" onClick={() => onChange(tiers.filter((_, i) => i !== index))}>
              <Trash2 size={13} />
            </button>
          </div>
        ))}
        {tiers.length < 10 ? (
          <button type="button" className="btn btn-sm btn-secondary self-start" onClick={() => onChange([...tiers, { minQty: (tiers[tiers.length - 1]?.minQty ?? 1) + 1, type: "percent", value: 5 }])} data-testid="add-tier">
            <Plus size={14} aria-hidden="true" /> Add price break
          </button>
        ) : null}
      </div>
    </FieldShell>
  );
}
