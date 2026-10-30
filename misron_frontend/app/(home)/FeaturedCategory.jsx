"use client";

/**
 * Featured category cards.
 *
 * Copied from the reference storefront's "Explore Categories" strip: a gold
 * sparkle eyebrow over a serif headline, then a row of white cards, each with a
 * round photo, a small dark icon badge on its corner and the name on one line.
 * The first card is "All Products", drawn highlighted, with a gradient sparkle
 * disc in place of a photo.
 *
 * Below `md`: pages of eight in two rows of four. The arrow buttons beside
 * "View All" (or a swipe) turn the page, and a short page centres itself.
 *
 * From `md`: one row holding every card, eight in view. Cards past the eighth
 * carry on to the right of it. A mouse drags the row and the arrows move it
 * eight cards at a time. With eight cards or fewer there are no arrows and the
 * row centres itself.
 *
 * Each card is a single link. A category with no image renders its initial on
 * the gold gradient rather than a broken picture, and like the reference it
 * drops the corner badge. The reference's per-card
 * "Best Seller" / "Exclusive" tags have no field behind them, so only the
 * "Popular" tag on the first card is drawn.
 *
 * Data fetching and caching are unchanged.
 */

import Link from "next/link";
import Image from "next/image";
import { memo, useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  Award,
  ChevronLeft,
  ChevronRight,
  Circle,
  CircleDot,
  Crown,
  Gem,
  RotateCw,
  Sparkles,
  Star,
} from "lucide-react";

import useCachedFetch from "../utils/useCachedFetch";
import { Button, Skeleton } from "@/app/components/ui";

const PAGE_SIZE = 8;
const SWIPE_DISTANCE = 40;
// How far a mouse moves before a press on the row becomes a drag.
const DRAG_DISTANCE = 5;

// Four per row below `md` (three 0.75rem gaps), eight from `md` (seven 1rem
// gaps). The widths and the row's gaps are a pair.
const CARD_WIDTH =
  "w-[calc((100%_-_2.25rem)/4)] md:w-[calc((100%_-_7rem)/8)]";

// The dark corner badge is decoration, not data: the cards take these in turn.
const BADGE_ICONS = [Gem, Star, CircleDot, Circle, Crown, Award];

const TRACK = "flex flex-wrap justify-center gap-3 md:gap-4";

const CARD_BASE =
  "group flex shrink-0 snap-start flex-col items-center rounded-2xl border p-3 text-center transition-all duration-200 sm:p-3.5";
const CARD_REST =
  "border-stone-200/90 bg-white hover:border-amber-400 hover:bg-stone-50/80 hover:shadow-sm";
const CARD_HIGHLIGHTED =
  "-translate-y-0.5 border-amber-600 bg-amber-50/90 shadow-md ring-2 ring-amber-600/20";

const DISC =
  "size-14 shrink-0 rounded-full transition-transform duration-300 group-hover:scale-105 sm:size-16";

const AllCard = () => (
  <Link
    href="/shop_page"
    className={`${CARD_BASE} ${CARD_HIGHLIGHTED} ${CARD_WIDTH}`}
  >
    <span className="relative mb-2.5 block">
      <span
        className={`${DISC} flex items-center justify-center bg-linear-to-tr from-amber-500 to-amber-700 text-white shadow-md`}
        aria-hidden="true"
      >
        <Sparkles className="size-6" strokeWidth={1.8} />
      </span>
      <span className="absolute -right-2 -top-1.5 rounded-full bg-[#F05A22] px-1.5 text-[9px] font-bold uppercase leading-tight text-white shadow-xs">
        Popular
      </span>
    </span>

    <span className="line-clamp-1 text-xs font-bold leading-tight text-amber-950 sm:text-[13px]">
      All Products
    </span>
  </Link>
);

const CategoryCard = memo(({ category, index }) => {
  // The cover image is the tile artwork; banner is the wide strip on the
  // category page, kept here only as a fallback for older records.
  const image = category.cover_image || category.banner;
  const BadgeIcon = BADGE_ICONS[index % BADGE_ICONS.length];

  return (
    <Link
      href={`/shop_page?categories=${category.id}`}
      className={`${CARD_BASE} ${CARD_REST} ${CARD_WIDTH}`}
    >
      <span className="relative mb-2.5 block">
        {image ? (
          <span
            className={`${DISC} block overflow-hidden border-2 border-stone-200 p-0.5 shadow-sm group-hover:border-amber-400`}
          >
            <span className="relative block size-full overflow-hidden rounded-full">
              <Image
                src={image}
                alt=""
                fill
                className="object-cover"
                sizes="64px"
              />
            </span>
          </span>
        ) : (
          <span
            className={`${DISC} flex items-center justify-center bg-linear-to-tr from-amber-600 to-yellow-600 font-display text-xl font-semibold uppercase text-white shadow-md`}
            aria-hidden="true"
          >
            {category.name?.charAt(0)}
          </span>
        )}

        {image && (
          <span
            className="absolute -bottom-1 -right-1 flex size-6 items-center justify-center rounded-full border-2 border-white bg-stone-900 text-amber-300 shadow-xs"
            aria-hidden="true"
          >
            <BadgeIcon className="size-3" strokeWidth={2} />
          </span>
        )}
      </span>

      <span className="line-clamp-1 text-xs font-semibold leading-tight text-stone-800 group-hover:text-stone-950 sm:text-[13px]">
        {category.name}
      </span>
    </Link>
  );
});

CategoryCard.displayName = "CategoryCard";

const Heading = ({ controls }) => (
  <div className="mb-6 flex items-end justify-between gap-4">
    <div className="min-w-0">
      <div className="mb-1 inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-amber-800">
        <Sparkles className="size-3.5 text-amber-600" aria-hidden="true" />
        <span>Featured Collections</span>
      </div>
      <h2 className="type-title-serif text-stone-900">Explore Categories</h2>
      <p className="mt-0.5 text-xs font-light text-stone-500 sm:text-sm">
        Select a category to browse the collection
      </p>
    </div>

    <div className="flex shrink-0 items-center gap-4">
      <Link
        href="/shop_page"
        className="group hidden items-center gap-1 text-xs font-semibold text-amber-800 transition-colors hover:text-amber-950 sm:inline-flex"
      >
        View All
        <ChevronRight
          className="size-3.5 transition-transform group-hover:translate-x-0.5"
          aria-hidden="true"
        />
      </Link>
      {controls}
    </div>
  </div>
);

const ARROW =
  "flex size-8 items-center justify-center rounded-full border border-stone-300 bg-white text-stone-700 transition-colors hover:border-amber-500 hover:text-amber-800 disabled:cursor-default disabled:opacity-40 disabled:hover:border-stone-300 disabled:hover:text-stone-700";

const Arrows = ({ canPrev, canNext, onSlide }) => (
  <div className="flex items-center gap-2">
    <button
      type="button"
      className={ARROW}
      onClick={() => onSlide(-1)}
      disabled={!canPrev}
      aria-label="Previous categories"
    >
      <ChevronLeft className="size-4" aria-hidden="true" />
    </button>
    <button
      type="button"
      className={ARROW}
      onClick={() => onSlide(1)}
      disabled={!canNext}
      aria-label="Next categories"
    >
      <ChevronRight className="size-4" aria-hidden="true" />
    </button>
  </div>
);

/** Shared by the loaded and loading states so the row never changes shape. */
const Row = ({ children }) => <div className={TRACK}>{children}</div>;

/**
 * The `md`-and-up row: every card in one horizontal line that scrolls. A mouse
 * press-and-drag scrolls it; on release it settles on the next card edge in the
 * direction of the drag, and a drag swallows the click that ends it. Touch and
 * trackpads scroll it natively. `edges` drives the arrows.
 */
const useDragRow = (count) => {
  const ref = useRef(null);
  const drag = useRef({ startX: null, startScroll: 0, moved: false });
  const [edges, setEdges] = useState({
    overflows: false,
    atStart: true,
    atEnd: true,
  });

  const measure = useCallback(() => {
    const el = ref.current;
    if (!el) return;
    const max = el.scrollWidth - el.clientWidth;
    const next = {
      overflows: max > 1,
      atStart: el.scrollLeft <= 1,
      atEnd: el.scrollLeft >= max - 1,
    };
    setEdges((prev) =>
      prev.overflows === next.overflows &&
      prev.atStart === next.atStart &&
      prev.atEnd === next.atEnd
        ? prev
        : next,
    );
  }, []);

  // Hidden below `md`, so it measures zero until the screen is wide enough.
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(el);
    return () => observer.disconnect();
  }, [measure, count]);

  // One card's width plus the gap after it.
  const cardStep = () => {
    const [first, second] = ref.current.children;
    return second
      ? second.offsetLeft - first.offsetLeft
      : ref.current.clientWidth;
  };

  const scrollToCard = (index) =>
    ref.current.scrollTo({ left: index * cardStep(), behavior: "smooth" });

  const slide = (direction) =>
    scrollToCard(
      Math.round(ref.current.scrollLeft / cardStep()) + direction * PAGE_SIZE,
    );

  const onPointerDown = (e) => {
    drag.current = {
      startX: e.pointerType === "mouse" && e.button === 0 ? e.clientX : null,
      startScroll: ref.current.scrollLeft,
      moved: false,
    };
  };

  const onPointerMove = (e) => {
    const d = drag.current;
    if (d.startX === null) return;
    // The button came up outside the window, so no pointerup arrived.
    if (!(e.buttons & 1)) {
      d.startX = null;
      return;
    }
    const delta = e.clientX - d.startX;
    if (!d.moved) {
      if (Math.abs(delta) < DRAG_DISTANCE) return;
      d.moved = true;
      ref.current.setPointerCapture(e.pointerId);
    }
    ref.current.scrollLeft = d.startScroll - delta;
  };

  const onPointerUp = () => {
    const d = drag.current;
    if (d.startX === null) return;
    d.startX = null;
    if (!d.moved) return;
    const at = ref.current.scrollLeft / cardStep();
    scrollToCard(
      ref.current.scrollLeft > d.startScroll
        ? Math.ceil(at - 0.05)
        : Math.floor(at + 0.05),
    );
  };

  const onClickCapture = (e) => {
    if (!drag.current.moved) return;
    drag.current.moved = false;
    e.preventDefault();
    e.stopPropagation();
  };

  return {
    ref,
    edges,
    slide,
    handlers: {
      onPointerDown,
      onPointerMove,
      onPointerUp,
      onPointerCancel: onPointerUp,
      onClickCapture,
      onScroll: measure,
      // A link or image would otherwise start a native drag and end ours.
      onDragStart: (e) => e.preventDefault(),
    },
  };
};

/**
 * The heading, then the cards twice: a pager below `md` and a scrolling row
 * from `md`, each hidden at the other size. Each has its own arrows.
 *
 * Pager: one page of cards at a time. "All Products" takes the first slot of
 * the first page, so page one holds seven categories and every later page
 * eight. Arrows (and a touch swipe) turn the page; they disable at either end
 * and are not drawn at all when everything fits on one page.
 */
const Strip = ({ categories }) => {
  const [page, setPage] = useState(0);

  const lastPage = Math.ceil((categories.length + 1) / PAGE_SIZE) - 1;
  const current = Math.min(page, lastPage);
  const offset = Math.max(current * PAGE_SIZE - 1, 0);
  const visible = categories.slice(
    offset,
    (current + 1) * PAGE_SIZE - 1,
  );

  const slide = (direction) =>
    setPage(Math.min(Math.max(current + direction, 0), lastPage));

  // Touch swipe only: a mouse has the arrows. A real swipe swallows the click
  // that ends it so it does not follow the card under the finger.
  const swipe = useRef({ startX: null, swiped: false });

  const onPointerDown = (e) => {
    swipe.current = {
      startX: e.pointerType === "mouse" ? null : e.clientX,
      swiped: false,
    };
  };

  const onPointerUp = (e) => {
    const { startX } = swipe.current;
    if (startX === null) return;
    swipe.current.startX = null;
    const delta = e.clientX - startX;
    if (Math.abs(delta) < SWIPE_DISTANCE) return;
    swipe.current.swiped = true;
    slide(delta < 0 ? 1 : -1);
  };

  const onClickCapture = (e) => {
    if (!swipe.current.swiped) return;
    swipe.current.swiped = false;
    e.preventDefault();
    e.stopPropagation();
  };

  const {
    ref: rowRef,
    edges,
    slide: slideRow,
    handlers: rowHandlers,
  } = useDragRow(categories.length);

  return (
    <>
      <Heading
        controls={
          <>
            {lastPage > 0 && (
              <div className="md:hidden">
                <Arrows
                  canPrev={current > 0}
                  canNext={current < lastPage}
                  onSlide={slide}
                />
              </div>
            )}
            {edges.overflows && (
              <div className="hidden md:block">
                <Arrows
                  canPrev={!edges.atStart}
                  canNext={!edges.atEnd}
                  onSlide={slideRow}
                />
              </div>
            )}
          </>
        }
      />
      <div
        onPointerDown={onPointerDown}
        onPointerUp={onPointerUp}
        onPointerCancel={() => {
          swipe.current.startX = null;
        }}
        onClickCapture={onClickCapture}
        className={`${TRACK} touch-pan-y md:hidden`}
      >
        {current === 0 && <AllCard />}
        {visible.map((category, index) => (
          <CategoryCard
            key={category.id}
            category={category}
            index={offset + index}
          />
        ))}
      </div>
      {/* The padding gives the highlighted card's ring and shadow room inside
          the scroll box; the negative margin keeps the row where it was. */}
      <div
        ref={rowRef}
        {...rowHandlers}
        className={`scrollbar-hide -mx-2 -my-3 hidden select-none gap-4 overflow-x-auto px-2 py-3 md:flex ${
          categories.length + 1 > PAGE_SIZE ? "" : "justify-center"
        }`}
      >
        <AllCard />
        {categories.map((category, index) => (
          <CategoryCard key={category.id} category={category} index={index} />
        ))}
      </div>
    </>
  );
};

export default function FeaturedCategory() {
  const API_URL = process.env.NEXT_PUBLIC_API_URL || "";

  const {
    data: categories,
    loading,
    error,
  } = useCachedFetch(
    `${API_URL}/top-featured-categories`,
    "featured-categories",
    6 * 60 * 1000,
  );

  const categoryList = useMemo(() => categories || [], [categories]);

  if (loading && !categoryList.length) {
    return (
      <section className="container-page section">
        <Heading />
        <Row>
          {[0, 1, 2, 3, 4, 5, 6, 7].map((i) => (
            <Skeleton
              key={i}
              className={`h-[8.5rem] shrink-0 sm:h-[9.25rem] ${CARD_WIDTH}`}
              rounded="xl"
            />
          ))}
        </Row>
      </section>
    );
  }

  if (error && !categoryList.length) {
    return (
      <section className="container-page section">
        <div className="rounded-lg bg-danger-tint p-6 text-center">
          <p className="type-callout mb-4 text-danger">Couldn&apos;t load categories</p>
          <Button
            variant="secondary"
            size="sm"
            iconLeft={RotateCw}
            onClick={() => window.location.reload()}
          >
            Try again
          </Button>
        </div>
      </section>
    );
  }

  if (!categoryList.length) return null;

  return (
    <section className="container-page section">
      <Strip categories={categoryList} />
    </section>
  );
}
