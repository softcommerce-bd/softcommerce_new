"use client";

import { ChevronDown } from "lucide-react";

/**
 * Collapsible filter group in the shop sidebar.
 *
 * Still a native <details>, which gives keyboard operation and open state for
 * free. The bordered box is replaced by a hairline rule between groups, so a
 * sidebar of five filters reads as one panel rather than five stacked cards,
 * and the marker is a rotating chevron rather than a "⌄" character.
 */
export default function FilterCard({ title, children, defaultOpen = true }) {
  return (
    <details open={defaultOpen} className="group border-b border-hairline py-5">
      <summary className="type-headline flex cursor-pointer list-none items-center justify-between text-ink [&::-webkit-details-marker]:hidden">
        {title}
        <ChevronDown
          size={16}
          strokeWidth={2}
          className="shrink-0 text-ink-tertiary transition-transform duration-base ease-standard group-open:rotate-180"
          aria-hidden="true"
        />
      </summary>

      <div className="pt-4">{children}</div>
    </details>
  );
}
