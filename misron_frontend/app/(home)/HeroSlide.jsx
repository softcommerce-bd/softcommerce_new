"use client";

import { useState } from "react";
import Image from "next/image";

/**
 * One hero slide.
 *
 * The admin's image, full frame, with no copy or button on it. When the admin
 * gave the slide a link, the whole slide opens it.
 *
 * Shared by the plain first-paint hero and by each slide inside the carousel,
 * so the two render identically and swapping between them causes no visible
 * change.
 *
 * `priority` is set only on the first slide, which is the page's LCP element.
 */
export default function HeroSlide({ item, index = 0, onSelect }) {
  const src = item.photo || item.image || item.src;
  const [failed, setFailed] = useState(false);
  const hasLink = item.url && item.url !== "#";

  return (
    <div className="relative h-full w-full bg-black">
      {src && !failed ? (
        <Image
          src={src}
          alt={item.title || `Slide ${index + 1}`}
          fill
          // Cover at every breakpoint. `object-contain` on mobile left a black
          // letterbox band wherever the art's ratio missed the frame's.
          className="object-cover object-center"
          priority={index === 0}
          sizes="(min-width: 1024px) 66vw, 100vw"
          loading={index === 0 ? "eager" : "lazy"}
          quality={80}
          // A missing file is an expected state, not a bug: show a placeholder
          // rather than logging console.error, which raises the dev overlay.
          onError={() => setFailed(true)}
        />
      ) : (
        <div className="absolute inset-0 flex items-center justify-center bg-neutral-900 text-sm text-white/60">
          No image found
        </div>
      )}

      {hasLink && (
        <button
          type="button"
          aria-label={item.title || `Open slide ${index + 1}`}
          onClick={() => onSelect(item.url)}
          className="absolute inset-0 z-10 cursor-pointer"
        />
      )}
    </div>
  );
}
