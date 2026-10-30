"use client";

import { useState } from "react";
import Image from "next/image";

/**
 * One of the two image cards beside the hero slider.
 *
 * The image is admin-uploaded and has no link or copy. With nothing uploaded,
 * or a file that fails to load, the card says so rather than collapsing, so the
 * column keeps its shape.
 */
export default function HeroSideCard({ src, alt, loading = false }) {
  const [failed, setFailed] = useState(false);

  return (
    <div className="relative aspect-7/4 min-w-0 flex-1 overflow-hidden rounded-2xl border border-hairline bg-canvas-sunken shadow-e1 sm:aspect-auto sm:min-h-[200px]">
      {loading ? (
        <div className="skeleton absolute inset-0" aria-hidden="true" />
      ) : src && !failed ? (
        <Image
          src={src}
          alt={alt}
          fill
          className="object-cover object-center"
          sizes="(min-width: 1024px) 33vw, 50vw"
          quality={80}
          // A missing file is an expected state, not a bug; see HeroSlide.
          onError={() => setFailed(true)}
        />
      ) : (
        <div className="absolute inset-0 flex items-center justify-center text-sm text-ink-tertiary">
          No image found
        </div>
      )}
    </div>
  );
}
