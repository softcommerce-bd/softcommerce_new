/**
 * Brand promises strip.
 *
 * Four claims, each with an icon. The previous version hand-inlined four
 * decorative SVGs — one of them a full world map running to roughly 30KB of
 * path data — for a total of 130 lines; the copy is now data and the icons
 * come from lucide.
 *
 * The body copy was also set in `text-gray-300`, which is near-invisible on
 * the light canvas. It now uses the secondary ink.
 */

import { ShieldCheck, Ruler, Headset, Truck } from "lucide-react";

const POLICIES = [
  {
    icon: ShieldCheck,
    title: "Ethical transparency",
    description:
      "We are accountable to our customers, community, and workers, to ensure our high ethical standards.",
  },
  {
    icon: Ruler,
    title: "Inclusive size range",
    description: "For everyone of all shapes and sizes, ranging from S to XXL.",
  },
  {
    icon: Headset,
    title: "Customer service",
    description:
      "Questions? Concerns? Compliments? We are here via email, chat, or phone.",
  },
  {
    icon: Truck,
    title: "Shipping nationwide",
    description:
      "We ship our products nationwide, making them accessible to customers everywhere.",
  },
];

export default function ServicePolicies() {
  return (
    <section className="container-page section">
      {/* The strip closes the page, so it is boxed on all four sides rather
          than hung under a single top rule.

          The dividers are drawn as an inset pseudo-element on each item rather
          than as a border on the grid cell: a cell border would run the full
          cell height and meet the box's own border at top and bottom, reading
          as one continuous grid. `inset-y-*` holds each rule short of both
          edges so it stays a separator inside the box.

          A rule belongs to every item that is not first in its row: the second
          of each pair on the two-column layout, everything after the first on
          the four-column one. The four-column rule is written as `nth-[n+2]`
          rather than as a `first:` override, because `first:` and `odd:` carry
          equal specificity and the later class in the stylesheet — not the
          later one in this string — decides which wins. */}
      <div className="grid grid-cols-2 gap-x-6 gap-y-10 rounded-xl border border-hairline p-8 md:grid-cols-4 md:gap-8 md:p-12">
        {POLICIES.map(({ icon: Icon, title, description }) => (
          <div
            key={title}
            className="relative flex flex-col items-center text-center before:absolute before:inset-y-2 before:-left-3 before:w-px before:bg-hairline before:content-[''] first:before:hidden odd:before:hidden md:before:-left-4 md:nth-[n+2]:before:block"
          >
            {/* Light stroke weight keeps the icons quiet against the copy. */}
            <Icon
              size={28}
              strokeWidth={1.25}
              className="mb-4 text-ink"
              aria-hidden="true"
            />
            <h3 className="type-headline mb-2 text-ink">{title}</h3>
            <p className="type-footnote max-w-[26ch] text-ink-tertiary">{description}</p>
          </div>
        ))}
      </div>
    </section>
  );
}
