"use client";

/**
 * Product grid that shows one screenful and expands to two.
 *
 * Home-page sections used to be horizontal rails, which hid everything past
 * the fourth card behind arrows most visitors never press. This shows a full
 * four-by-four block up front and lets a visitor open a second block in place.
 *
 * The expansion is deliberately capped at one step. Anything past thirty-two
 * products is browsing rather than glancing, and browsing belongs on the shop
 * page, which has the filters, the sort and the pagination for it — so the
 * footer link is the only way past the second block. That also keeps the home
 * page's payload fixed and leaves the footer reachable.
 */

import { useRef, useState } from "react";
import { ArrowRight, ChevronDown, ChevronUp, PackageSearch } from "lucide-react";

import ProductCard1 from "./ProductCard1";
import { Button, SectionHeader, EmptyState, SkeletonProductCard } from "../ui";

/** One full four-column row group: four across, four down. */
const COLLAPSED_COUNT = 16;
/** Opening the grid adds a second block of the same size. */
const EXPANDED_COUNT = 32;

/**
 * Shared width for the two controls below the grid, so the pair matches
 * regardless of label length. A fixed width rather than a minimum: a minimum
 * still lets the longer label push its own button wider. Wide enough for
 * "View all" and both toggle labels at the md size without wrapping, and it
 * drops to the content width on a phone, where two buttons at this width plus
 * the gap overflow the narrowest screens.
 */
const CONTROL_WIDTH = "w-auto sm:w-40";

/**
 * The base `.btn` centres a label and its icon together as one group, so two
 * buttons with labels of different lengths put their icons at different
 * distances from the edge — visible as soon as the pair sits side by side.
 *
 * Giving the icon a fixed slot at the trailing edge and centring the label in
 * what is left makes the two buttons identical in structure: same label box,
 * same icon position, whatever the words are.
 */
const CONTROL_LABEL = "flex-1 text-center";
const CONTROL_ICON_SLOT = "w-4 shrink-0";

/**
 * `compactOnPhone`: below `md` the grid is two columns, so sixteen cards is
 * eight rows of scrolling. This hides every card past the fourth there (two
 * rows) and drops the toggle, leaving "View all" as the way on. Done in CSS
 * rather than by slicing so server and client render the same list.
 */
const PHONE_GRID_CAP = "max-md:[&>*:nth-child(n+5)]:hidden";

export default function ExpandableProductGrid({
  products = [],
  loading = false,
  title,
  eyebrow,
  description,
  /** Where "View all" points — the shop page, filtered to this section. */
  href,
  linkLabel = "View all",
  collapsedCount = COLLAPSED_COUNT,
  expandedCount = EXPANDED_COUNT,
  emptyTitle = "Nothing here yet",
  emptyDescription,
  /**
   * Replaces the default SectionHeader. A section whose heading row carries its
   * own controls — a category tab strip, say — passes the whole row here rather
   * than growing this component a prop per control.
   */
  header,
  /**
   * Fades the grid without replacing it. Used while a section reloads with
   * products already on screen, so the change reads as a refresh rather than
   * the grid emptying.
   */
  dimmed = false,
  /** Rendered instead of the built-in empty state — an error panel, say. */
  fallback,
  className = "",
  /** Card component and its extra props; defaults to the frameless card. */
  Card = ProductCard1,
  cardProps,
  /** Two rows and no toggle on a phone; see PHONE_GRID_CAP. */
  compactOnPhone = false,
}) {
  const phoneCap = compactOnPhone ? PHONE_GRID_CAP : "";
  const [expanded, setExpanded] = useState(false);
  const gridRef = useRef(null);

  const heading = header ?? (
    title ? (
      <SectionHeader eyebrow={eyebrow} title={title} description={description} />
    ) : null
  );

  if (fallback) {
    return (
      <section className={`container-page section ${className}`}>
        {heading}
        {fallback}
      </section>
    );
  }

  // Loading with nothing cached: show the block's shape, not a spinner, so the
  // page does not jump once the products land.
  if (loading && products.length === 0) {
    return (
      <section className={`container-page section ${className}`}>
        {heading}
        <div className={`grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 xl:grid-cols-4 ${phoneCap}`}>
          {Array.from({ length: collapsedCount }).map((_, i) => (
            <SkeletonProductCard key={i} />
          ))}
        </div>
      </section>
    );
  }

  if (!loading && products.length === 0) {
    return (
      <section className={`container-page section ${className}`}>
        {heading}
        <EmptyState
          icon={PackageSearch}
          title={emptyTitle}
          description={emptyDescription}
        />
      </section>
    );
  }

  const limit = expanded ? expandedCount : collapsedCount;
  const visible = products.slice(0, limit);
  // Only offer the toggle when there is a second block to open.
  const canExpand = products.length > collapsedCount;

  const handleToggle = () => {
    if (expanded) {
      setExpanded(false);
      // Collapsing removes rows from under the viewport, so without this the
      // visitor is left staring at whatever follows the section.
      gridRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
    } else {
      setExpanded(true);
    }
  };

  return (
    <section className={`container-page section ${className}`}>
      {heading}

      <div
        ref={gridRef}
        className={`scroll-mt-[calc(var(--header-height,80px)+24px)] grid grid-cols-2 gap-4 transition-opacity duration-base md:grid-cols-3 md:gap-6 xl:grid-cols-4 ${phoneCap} ${
          dimmed ? "opacity-50" : "opacity-100"
        }`}
      >
        {visible.map((item, index) => (
          <Card
            key={item.id ?? index}
            item={item}
            {...cardProps}
            // Only the first row is above the fold on a wide screen; the rest
            // load lazily so the home page's LCP stays with the hero.
            priority={index < 4}
          />
        ))}
      </div>

      {/* Both controls carry the same frosted treatment as the carousel arrows
          and the sticky bars, so the row reads as one pair rather than a
          button with a text link hanging under it. They wrap on a phone, where
          two buttons side by side would crowd.

          A shared width alone is not enough to make the pair match: the base
          `.btn` centres the label and its icon together as one group, so two
          labels of different lengths put their icons at different distances
          from the edge. The label takes the free space instead and centres
          within it, which pins both icons to the same position. */}
      <div className="mt-10 flex flex-wrap items-center justify-center gap-3">
        {canExpand && (
          <Button
            variant="glass"
            onClick={handleToggle}
            aria-expanded={expanded}
            className={`${CONTROL_WIDTH} ${compactOnPhone ? "max-md:hidden" : ""}`}
          >
            <span className={CONTROL_LABEL}>
              {expanded ? "Show less" : "Show more"}
            </span>
            <span className={CONTROL_ICON_SLOT}>
              {expanded ? (
                <ChevronUp size={16} aria-hidden="true" />
              ) : (
                <ChevronDown size={16} aria-hidden="true" />
              )}
            </span>
          </Button>
        )}

        {href && (
          <Button variant="glass" href={href} className={`${CONTROL_WIDTH} group`}>
            <span className={CONTROL_LABEL}>{linkLabel}</span>
            <span className={CONTROL_ICON_SLOT}>
              {/* The arrow nudges forward on hover, as it does in
                  SectionHeader's "View all" link. */}
              <ArrowRight
                size={16}
                className="transition-transform duration-base ease-standard group-hover:translate-x-0.5"
                aria-hidden="true"
              />
            </span>
          </Button>
        )}
      </div>
    </section>
  );
}
