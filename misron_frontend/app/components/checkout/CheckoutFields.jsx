"use client";

/**
 * Form primitives for the checkout card.
 *
 * They reproduce the reference design's 1-Click Express Checkout modal: a
 * 12px label above a white field with a stone border, a leading icon, and an
 * amber border on focus. Radii are written as arbitrary values because the
 * app's theme tightens `rounded-lg` and `rounded-xl` to 6px and 8px, and the
 * reference uses 8px and 12px.
 *
 * Font size is inherited, not set here: the global `input, select, textarea`
 * rule is unlayered and would beat a `text-xs` utility, so the card's wrapper
 * sets the size and the controls follow it.
 */

import { useId } from "react";
import { ChevronDown } from "lucide-react";

const CONTROL =
  "w-full rounded-lg border border-stone-300 bg-white py-2.5 pl-9 text-stone-800 focus:border-amber-700 focus:outline-none disabled:bg-stone-100 disabled:text-stone-400";

/** Small uppercase heading that opens a group of fields. */
export function SectionHeading({ children }) {
  return (
    <p
      role="heading"
      aria-level={2}
      className="text-[11px] font-semibold uppercase tracking-wider text-stone-800"
    >
      {children}
    </p>
  );
}

/** Label above a group of radios, rendered as the fieldset's legend. */
export function GroupLegend({ children }) {
  return (
    <legend className="mb-1.5 block font-medium text-stone-700">{children}</legend>
  );
}

/**
 * Label + leading icon + control. `as` picks the element: "input" (default),
 * "textarea" or "select". A select gets the app's chevron in place of the
 * browser arrow.
 */
export function IconField({
  label,
  icon: Icon,
  as: Control = "input",
  className = "",
  children,
  id: providedId,
  ...props
}) {
  const generatedId = useId();
  const id = providedId ?? generatedId;
  const isSelect = Control === "select";

  return (
    <div>
      <label htmlFor={id} className="mb-1 block font-medium text-stone-700">
        {label}
      </label>

      <div className="relative">
        {Icon && (
          <Icon
            className={`pointer-events-none absolute left-3 h-4 w-4 text-stone-400 ${
              Control === "textarea" ? "top-3" : "top-1/2 -translate-y-1/2"
            }`}
            aria-hidden="true"
          />
        )}

        <Control
          id={id}
          className={`${CONTROL} ${
            isSelect ? "cursor-pointer appearance-none pr-9" : "pr-3"
          } ${className}`}
          {...props}
        >
          {children}
        </Control>

        {isSelect && (
          <ChevronDown
            className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400"
            aria-hidden="true"
          />
        )}
      </div>
    </div>
  );
}

/**
 * Bordered radio or checkbox row. The native control is kept, as in the
 * reference, so keyboard and screen-reader behaviour comes for free.
 *
 * `layout="tile"` is the compact two-up shape used for the shipping region;
 * `layout="row"` is the full-width shape used for payment methods, with an
 * optional `badge` pinned to the right.
 */
export function OptionRow({
  type = "radio",
  layout = "row",
  checked,
  badge,
  children,
  ...props
}) {
  const isTile = layout === "tile";

  const shape = isTile
    ? "gap-2 p-2.5"
    : "justify-between gap-3 p-3";

  const tone = checked
    ? `border-amber-700 ${isTile ? "bg-amber-50/50" : "bg-amber-50/40"} font-medium text-amber-950`
    : `border-stone-200 bg-white ${isTile ? "text-stone-700" : ""}`;

  return (
    <label
      className={`flex cursor-pointer items-center rounded-lg border transition-all ${shape} ${tone}`}
    >
      <span className="flex min-w-0 items-center gap-2">
        <input type={type} checked={checked} {...props} />
        <span>{children}</span>
      </span>

      {badge}
    </label>
  );
}

/** Right-hand tag on an option row: "Popular" is filled, "Online" is plain. */
export function OptionBadge({ tone = "plain", children }) {
  return tone === "filled" ? (
    <span className="shrink-0 rounded-sm bg-amber-100 px-2 py-0.5 text-[11px] font-medium leading-3.5 text-amber-900">
      {children}
    </span>
  ) : (
    <span className="shrink-0 text-[11px] text-stone-400">{children}</span>
  );
}
