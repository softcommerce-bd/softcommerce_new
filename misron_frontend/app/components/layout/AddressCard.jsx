"use client";

/**
 * Saved delivery address, selectable at checkout.
 *
 * Drawn as the reference design's radio row: a bordered white row with a native
 * radio, amber when selected. The card is the radio's own label, so clicking
 * anywhere selects it and the whole card is reachable by keyboard.
 *
 * The address itself leads, with city and phone beneath. Selection,
 * default-setting, edit and delete callbacks are unchanged.
 */

import { Star, Pencil, Trash2, Check } from "lucide-react";

const ACTION =
  "flex items-center gap-1 rounded-sm px-2 py-1 text-[11px] font-medium text-stone-500 transition-colors";

export default function AddressCard({
  address,
  isSelected,
  onSelect,
  onMakeDefault,
  onEdit,
  onDelete,
}) {
  const isDefault = address?.set_default === 1;

  // Each action stops propagation so it does not also select the card it
  // sits inside.
  const handle = (callback) => (e) => {
    e.preventDefault();
    e.stopPropagation();
    callback();
  };

  return (
    <label
      className={`block cursor-pointer rounded-lg border p-3 transition-all ${
        isSelected
          ? "border-amber-700 bg-amber-50/50 text-amber-950"
          : "border-stone-200 bg-white text-stone-700"
      }`}
    >
      <div className="flex items-start gap-2">
        <input
          type="radio"
          name="selectedAddress"
          checked={isSelected}
          onChange={onSelect}
          className="mt-0.5"
        />

        <div className="min-w-0 flex-1">
          {isDefault && (
            <span className="mb-1 inline-flex items-center gap-1 rounded-sm bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-900">
              <Check className="h-3 w-3" aria-hidden="true" />
              Default
            </span>
          )}

          <p className="font-medium text-stone-900">
            {address?.address || "No address on file"}
          </p>

          <p className="mt-0.5 text-[11px] text-stone-500">
            {[address?.city_name, address?.phone].filter(Boolean).join(" · ")}
          </p>
        </div>
      </div>

      <div className="mt-2.5 flex items-center justify-end gap-1 border-t border-stone-100 pt-2">
        {!isDefault && (
          <button
            type="button"
            onClick={handle(onMakeDefault)}
            className={`${ACTION} hover:bg-stone-100 hover:text-stone-900`}
          >
            <Star className="h-3 w-3" aria-hidden="true" />
            Make default
          </button>
        )}

        <button
          type="button"
          onClick={handle(onEdit)}
          className={`${ACTION} hover:bg-stone-100 hover:text-stone-900`}
        >
          <Pencil className="h-3 w-3" aria-hidden="true" />
          Edit
        </button>

        {!isDefault && (
          <button
            type="button"
            onClick={handle(onDelete)}
            className={`${ACTION} hover:bg-rose-50 hover:text-rose-700`}
          >
            <Trash2 className="h-3 w-3" aria-hidden="true" />
            Delete
          </button>
        )}
      </div>
    </label>
  );
}
