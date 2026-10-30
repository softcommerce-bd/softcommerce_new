"use client";

import { ChevronLeft, ChevronRight } from "lucide-react";

/**
 * Page navigation.
 *
 * Shows the first and last page, the current page and two either side, with
 * ellipses standing in for the rest. Previous and next collapse to arrows on
 * a phone, where their labels would push the numbers off screen.
 *
 * The shop page and the dashboard each had their own version of this.
 */
export default function Pagination({
  currentPage,
  lastPage,
  onPageChange,
  className = "",
}) {
  if (!lastPage || lastPage <= 1) return null;

  const pages = [];
  const delta = 2;

  for (let i = 1; i <= lastPage; i++) {
    const isEdge = i === 1 || i === lastPage;
    const isNearCurrent = i >= currentPage - delta && i <= currentPage + delta;

    if (isEdge || isNearCurrent) {
      pages.push(i);
    } else if (pages[pages.length - 1] !== "…") {
      pages.push("…");
    }
  }

  const stepClass =
    "inline-flex h-9 items-center gap-1 rounded-md px-2.5 text-[0.8125rem] font-medium " +
    "text-ink-secondary transition-colors duration-fast hover:bg-surface-hover " +
    "disabled:pointer-events-none disabled:opacity-30 sm:px-3";

  return (
    <nav
      className={`flex items-center justify-center gap-1 ${className}`}
      aria-label="Pagination"
    >
      <button
        type="button"
        onClick={() => onPageChange(currentPage - 1)}
        disabled={currentPage === 1}
        className={stepClass}
      >
        <ChevronLeft size={15} aria-hidden="true" />
        <span className="hidden sm:inline">Previous</span>
      </button>

      {pages.map((pageNum, index) => {
        if (pageNum === "…") {
          return (
            <span
              key={`gap-${index}`}
              className="px-1.5 text-[0.8125rem] text-ink-quaternary"
              aria-hidden="true"
            >
              …
            </span>
          );
        }

        const isCurrent = pageNum === currentPage;

        return (
          <button
            key={pageNum}
            type="button"
            onClick={() => onPageChange(pageNum)}
            aria-current={isCurrent ? "page" : undefined}
            className={`tabular inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2 text-[0.8125rem] font-semibold transition-colors duration-fast ${
              isCurrent
                ? "bg-ink text-ink-inverse"
                : "text-ink-secondary hover:bg-surface-hover"
            }`}
          >
            {pageNum}
          </button>
        );
      })}

      <button
        type="button"
        onClick={() => onPageChange(currentPage + 1)}
        disabled={currentPage === lastPage}
        className={stepClass}
      >
        <span className="hidden sm:inline">Next</span>
        <ChevronRight size={15} aria-hidden="true" />
      </button>
    </nav>
  );
}
