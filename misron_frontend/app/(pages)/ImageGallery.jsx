"use client";

/**
 * Product image gallery — a large framed photo with a thumbnail row below it.
 *
 * Laid out after the reference design: a square frame with a hairline border,
 * a discount badge top-left, a "Hover to zoom" hint bottom-right, and an 80px
 * thumbnail row underneath whose active thumbnail carries a gold border.
 *
 * Behaviour worth noting:
 *
 *   · Both the main frame and the thumbnails use `object-cover` inside a fixed
 *     square box, so every photo fills its frame edge to edge and the layout
 *     never jumps between images. Photos that are not square are cropped rather
 *     than letterboxed — no empty bands around the product. The crop is
 *     anchored to the top (`object-top`), because product shots put the model's
 *     head there and losing a little at the hem beats losing a face. Zoom only
 *     happens while the pointer is over the frame, anchored to the point under
 *     the cursor, and it releases the moment the pointer leaves.
 *
 *     The frame is square because the catalogue is shot square. Cropping is a
 *     display-side guard, not the real fix: keep uploading at one ratio and the
 *     frame crops nothing at all. If the catalogue ever moves to 3:4 portrait,
 *     change the ratio here rather than letting the frame crop every photo.
 *   · The thumbnail row scrolls sideways when it outgrows the frame, on every
 *     breakpoint, so a long gallery never pushes the page taller.
 *   · The hint is hidden on touch screens, which have no hover; there the photo
 *     is swiped instead.
 */

import { useState, useRef, useEffect } from "react";
import Image from "next/image";
import { ImageOff, Maximize2 } from "lucide-react";

const ZOOM_SCALE = 2.5;

export default function ImageGallery({ galleryImages, discount }) {
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [zooming, setZooming] = useState(false);
  const [origin, setOrigin] = useState("50% 50%");

  const dragStartX = useRef(0);
  const dragging = useRef(false);

  const selectedImage = galleryImages?.[selectedIndex];

  useEffect(() => {
    setSelectedIndex(0);
  }, [galleryImages]);

  // Every move to another photo drops the magnifier — the old zoom origin
  // means nothing on a picture of a different shape.
  const showImage = (next) => {
    setZooming(false);
    setOrigin("50% 50%");
    setSelectedIndex(next);
  };

  const goPrev = () => showImage((i) => Math.max(0, i - 1));
  const goNext = () =>
    showImage((i) => Math.min(galleryImages.length - 1, i + 1));

  // A horizontal drag across the main image steps through the gallery.
  const handleMouseDown = (e) => {
    dragging.current = true;
    dragStartX.current = e.clientX;
  };

  const handleMouseUp = (e) => {
    if (!dragging.current) return;

    const diff = e.clientX - dragStartX.current;
    if (diff > 50) goPrev();
    else if (diff < -50) goNext();

    dragging.current = false;
  };

  // Hover magnifier. The photo covers the frame edge to edge, so the cursor
  // position within the frame is already the position within the photo.
  const handleMouseMove = (e) => {
    const frame = e.currentTarget.getBoundingClientRect();
    if (!frame.width || !frame.height) return;

    const x = ((e.clientX - frame.left) / frame.width) * 100;
    const y = ((e.clientY - frame.top) / frame.height) * 100;

    setOrigin(`${Math.min(100, Math.max(0, x))}% ${Math.min(100, Math.max(0, y))}%`);
  };

  const handleMouseEnter = (e) => {
    handleMouseMove(e);
    setZooming(true);
  };

  const handleMouseLeave = () => {
    dragging.current = false;
    setZooming(false);
    setOrigin("50% 50%");
  };

  const handleTouchStart = (e) => {
    dragStartX.current = e.touches[0].clientX;
  };

  const handleTouchEnd = (e) => {
    const diff = e.changedTouches[0].clientX - dragStartX.current;
    if (diff > 50) goPrev();
    else if (diff < -50) goNext();
  };

  // Arrow keys move through the gallery when it has keyboard focus.
  const handleKeyDown = (e) => {
    if (e.key === "ArrowLeft") {
      e.preventDefault();
      goPrev();
    } else if (e.key === "ArrowRight") {
      e.preventDefault();
      goNext();
    }
  };

  if (!galleryImages || galleryImages.length === 0) {
    return (
      <div className="lg:col-span-6">
        <div className="flex aspect-square w-full items-center justify-center rounded-2xl border border-stone-200/80 bg-white shadow-xs">
          <ImageOff size={30} className="text-stone-300" strokeWidth={1.5} />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4 lg:col-span-6">
      {/* ── Main image ─────────────────────────────────────────────────────*/}
      <div
        role="group"
        aria-roledescription="carousel"
        aria-label={`Product images, ${selectedIndex + 1} of ${galleryImages.length}`}
        tabIndex={0}
        onKeyDown={handleKeyDown}
        onMouseDown={handleMouseDown}
        onMouseUp={handleMouseUp}
        onMouseEnter={handleMouseEnter}
        onMouseMove={handleMouseMove}
        onMouseLeave={handleMouseLeave}
        onTouchStart={handleTouchStart}
        onTouchEnd={handleTouchEnd}
        className="group relative aspect-square w-full cursor-zoom-in overflow-hidden rounded-2xl border border-stone-200/80 bg-white shadow-xs"
      >
        <Image
          key={selectedImage.src}
          src={selectedImage.src}
          alt={selectedImage.alt}
          fill
          style={{
            transformOrigin: origin,
            transform: `scale(${zooming ? ZOOM_SCALE : 1})`,
            // Animate only the zoom in and out. While zoomed the origin
            // follows the cursor, and a transition there lags behind it.
            transition: zooming ? "none" : "transform var(--duration-base) var(--ease)",
          }}
          className="object-cover object-top"
          priority
          sizes="(max-width: 1024px) 100vw, 45vw"
          quality={82}
        />

        {discount && (
          <span className="absolute left-4 top-4 z-10 rounded-sm bg-rose-700 px-2.5 py-1 text-xs font-semibold text-white shadow-sm">
            {discount} Off
          </span>
        )}

        <span className="pointer-events-none absolute bottom-4 right-4 z-10 hidden items-center gap-1 rounded-full bg-black/40 px-2.5 py-1 text-[11px] text-white opacity-80 backdrop-blur-xs transition-opacity group-hover:opacity-100 [@media(hover:hover)]:flex">
          <Maximize2 className="h-3 w-3" aria-hidden="true" />
          Hover to zoom
        </span>
      </div>

      {/* ── Thumbnails ─────────────────────────────────────────────────────*/}
      {galleryImages.length > 1 && (
        <div className="scrollbar-hide flex items-center gap-3 overflow-x-auto p-1 pb-2">
          {galleryImages.map((img, index) => {
            const isActive = index === selectedIndex;
            return (
              <button
                key={img.id}
                type="button"
                onClick={() => showImage(index)}
                aria-label={`View image ${index + 1} of ${galleryImages.length}`}
                aria-current={isActive}
                className={`relative h-20 w-20 shrink-0 cursor-pointer overflow-hidden rounded-xl border-2 bg-white transition-all ${
                  isActive
                    ? "border-amber-700 shadow-xs ring-2 ring-amber-100"
                    : "border-stone-200 opacity-75 hover:border-stone-400 hover:opacity-100"
                }`}
              >
                <Image
                  src={img.src}
                  alt=""
                  width={160}
                  height={160}
                  className="h-full w-full object-cover object-top"
                  sizes="80px"
                  quality={70}
                />
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}
