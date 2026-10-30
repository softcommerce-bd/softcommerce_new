"use client";

/**
 * Shoppable video tile.
 *
 * Redrawn as a portrait card with the play control over a bottom scrim and a
 * compact product row beneath it. Changes worth noting:
 *
 *   · The play triangle was built from CSS borders; it is now a lucide Play.
 *   · The "View Product" link's icon was a 3KB inline SVG of a winking face
 *     with arrows; it is now a chevron.
 *   · `formatPrice` rewrote the taka sign as "Rs." — the wrong currency here.
 *     It was only used by commented-out markup and has been removed.
 *   · The thumbnail was rendered twice, once large and again at 12×16 beside
 *     the title. The small duplicate is gone.
 *
 * The modal dispatch and product link are unchanged.
 */

import Link from "next/link";
import { useDispatch } from "react-redux";
import { Play, ChevronRight } from "lucide-react";

import { openVideoModal } from "@/store/slices/videoModalSlice";

export default function VideoCard({ item }) {
  const dispatch = useDispatch();

  const openModal = (e) => {
    e?.stopPropagation();
    dispatch(openVideoModal(item));
  };

  return (
    <div className="group">
      <button
        type="button"
        onClick={openModal}
        aria-label={`Play video for ${item.name}`}
        className="relative block aspect-9/16 w-full overflow-hidden rounded-lg bg-canvas-sunken"
      >
        <img
          src={item.thumbnail_image}
          alt=""
          className="h-full w-full object-cover transition-transform duration-slower ease-standard group-hover:scale-[1.04]"
        />

        <span
          className="absolute inset-0 bg-linear-to-t from-black/45 to-transparent"
          aria-hidden="true"
        />

        <span
          className="absolute inset-0 flex items-center justify-center"
          aria-hidden="true"
        >
          <span className="flex h-11 w-11 items-center justify-center rounded-full bg-white/85 shadow-e2 backdrop-blur-md transition-transform duration-base ease-standard group-hover:scale-110">
            {/* Nudged right so the triangle looks centred in the circle. */}
            <Play size={17} className="ml-0.5 text-ink" fill="currentColor" />
          </span>
        </span>
      </button>

      <div className="mt-3">
        <p className="type-footnote line-clamp-2 font-medium text-ink">
          {item.name}
        </p>

        <Link
          href={`/product/${item.slug}`}
          onClick={(e) => e.stopPropagation()}
          className="type-caption mt-1.5 inline-flex items-center gap-0.5 font-semibold text-ink-tertiary transition-colors hover:text-ink"
        >
          View product
          <ChevronRight size={13} aria-hidden="true" />
        </Link>
      </div>
    </div>
  );
}
