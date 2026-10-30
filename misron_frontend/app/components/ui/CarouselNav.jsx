"use client";

import { ChevronLeft, ChevronRight } from "lucide-react";

/**
 * Previous / next arrows for a Swiper carousel.
 *
 * Swiper is told which elements to bind to via CSS selectors, so each carousel
 * passes a unique `name` and these buttons claim `.<name>-prev` / `.<name>-next`.
 *
 * The previous code repeated this pair in five sections, each with its own
 * inline chevron SVG and its own shadow and border values.
 *
 * The arrows rest at zero opacity and fade in when the pointer is over the
 * carousel — the `.carousel-hover-nav` class on the wrapper drives that, and
 * keeps them permanently visible on touch, where there is no hover.
 */
export default function CarouselNav({ name, className = "" }) {
  const base =
    "absolute top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center " +
    "rounded-full bg-surface/85 text-ink shadow-e2 backdrop-blur-md " +
    "transition-all duration-150 ease-standard " +
    "hover:scale-105 hover:shadow-e3 active:scale-95 " +
    "disabled:opacity-30 disabled:shadow-none";

  return (
    <>
      <button
        type="button"
        aria-label="Previous"
        className={`${name}-prev ${base} left-0 -translate-x-1 md:-translate-x-4 ${className}`}
      >
        <ChevronLeft size={18} strokeWidth={2} aria-hidden="true" />
      </button>

      <button
        type="button"
        aria-label="Next"
        className={`${name}-next ${base} right-0 translate-x-1 md:translate-x-4 ${className}`}
      >
        <ChevronRight size={18} strokeWidth={2} aria-hidden="true" />
      </button>
    </>
  );
}
