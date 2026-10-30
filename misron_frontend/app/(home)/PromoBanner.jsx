"use client";

/**
 * One promo banner row, for one admin banner slot.
 *
 * Admin has three separate slots — "Banner Level 1", "Banner Level 2" and
 * "Banner Level 3" under Website Setup → Homepage Settings — stored as
 * `home_banner1_images`, `home_banner2_images` and `home_banner3_images` and
 * served by `/banners-one`, `/banners-two` and `/banners-three`.
 *
 * Each slot is placed on its own in the page rather than merged into a single
 * row at the bottom: level 1 sits above the category tiles, level 2 above the
 * best sellers, level 3 above the shop-by-category grid. The previous
 * component read all three endpoints and rendered them side by side, which put
 * every banner in one strip no matter which slot it came from.
 *
 * Nothing is drawn for a slot the admin has not filled, or whose file the
 * backend no longer serves: rows without an image URL are dropped up front,
 * and a tile removes itself if the image fails to load. When that leaves
 * nothing, the whole section — and its vertical padding — is gone. A slot can
 * ask for a short message in that case instead, through `emptyMessage`, so a
 * missing banner is visible on the page rather than silently absent.
 */

import { useCallback, useState } from "react";
import Image from "next/image";
import useCachedFetch from "@/app/utils/useCachedFetch";
import { Skeleton } from "@/app/components/ui";

/** Slot number → the endpoint and cache key that serve it. */
const SLOTS = {
  1: { path: "banners-one", cacheKey: "banners-one-data" },
  2: { path: "banners-two", cacheKey: "banners-two-data" },
  3: { path: "banners-three", cacheKey: "banners-three-data" },
};

/**
 * The backend's stand-in for an image it cannot find.
 *
 * `uploaded_asset()` (app/Http/Helpers.php) answers with
 * `assets/img/placeholder.jpg` when the upload row is gone, so a slot pointing
 * at a deleted file returns a perfectly loadable grey picture-icon rather than
 * a 404. That image would never trip `onError`, so it is matched by name and
 * treated as "no banner assigned".
 */
const PLACEHOLDER = /\/assets\/img\/placeholder\.(jpg|jpeg|png|webp)(\?|$)/i;

/**
 * The image URL a banner row carries, or "" when it carries none — an unset
 * slot, or one the backend answered with its placeholder.
 */
const bannerSrc = (banner) => {
  const src = banner?.photo || banner?.image;
  if (typeof src !== "string") return "";

  const trimmed = src.trim();
  return PLACEHOLDER.test(trimmed) ? "" : trimmed;
};

const CACHE_MS = 30 * 60 * 1000;

/**
 * One banner tile. `tall` is used when the slot holds a single banner, which
 * is the usual case now that each slot renders on its own.
 */
function BannerTile({ banner, index, tall = false, sizes, priority, onBroken }) {
  const src = bannerSrc(banner);

  return (
    <a
      href={banner.url || "#"}
      target="_blank"
      rel="noopener noreferrer"
      className={`group relative block overflow-hidden rounded-xl bg-canvas-sunken shadow-e1 transition-shadow duration-base ease-standard hover:shadow-e2 ${
        tall ? "h-[200px] md:h-[480px]" : "h-[110px] md:h-60"
      }`}
    >
      <Image
        src={src}
        alt={banner.title || `Banner ${index + 1}`}
        fill
        sizes={sizes}
        className="object-cover transition-transform duration-slower ease-standard group-hover:scale-[1.04]"
        priority={priority}
        loading={priority ? "eager" : "lazy"}
        quality={78}
        onError={onBroken}
      />
    </a>
  );
}

/** Shown in place of a slot that has nothing to display. */
function EmptyBanner({ message }) {
  return (
    <section className="container-page section">
      <div className="rounded-xl bg-canvas-sunken px-6 py-12 text-center">
        <p className="type-callout text-ink-tertiary">{message}</p>
      </div>
    </section>
  );
}

/**
 * `level` is the admin slot number, 1 to 3.
 *
 * `priority` should be set only on a banner above the fold — level 1 on most
 * viewports — so the others are lazily loaded.
 *
 * `emptyMessage`, when given, is drawn when the slot has no banner to show —
 * unset, failed to load, or pointing at a file the backend no longer serves.
 * Without it such a slot renders nothing.
 */
export default function PromoBanner({
  level = 1,
  priority = false,
  emptyMessage,
}) {
  const slot = SLOTS[level] ?? SLOTS[1];
  const API_URL = process.env.NEXT_PUBLIC_API_URL || "";

  const { data, loading, error } = useCachedFetch(
    `${API_URL}/${slot.path}`,
    slot.cacheKey,
    CACHE_MS,
  );

  // Keys of the banners whose image failed to load. Held here rather than in
  // the tile so the section itself — and its vertical padding — goes away when
  // nothing is left to show, instead of leaving an empty band on the page.
  const [brokenKeys, setBrokenKeys] = useState(() => new Set());

  const markBroken = useCallback((key) => {
    setBrokenKeys((previous) => {
      if (previous.has(key)) return previous;

      const next = new Set(previous);
      next.add(key);
      return next;
    });
  }, []);

  const banners = Array.isArray(data) ? data : [];

  if (loading && banners.length === 0) {
    return (
      <section className="container-page section">
        <Skeleton className="h-[200px] w-full md:h-[480px]" rounded="xl" />
      </section>
    );
  }

  // A failed promo row is not worth an error panel on the home page.
  if (error && banners.length === 0) {
    console.warn(`Error fetching banner level ${level}:`, error);
    return emptyMessage ? <EmptyBanner message={emptyMessage} /> : null;
  }

  // Only banners that carry an image URL and whose image has actually loaded
  // reach the page. A slot the admin left empty, or one pointing at a file the
  // backend no longer serves, is dropped rather than drawn as an empty card.
  const visible = banners
    .map((banner, index) => ({
      banner,
      index,
      key: `${level}-${banner?.id ?? index}`,
    }))
    .filter(({ banner, key }) => bannerSrc(banner) && !brokenKeys.has(key));

  if (visible.length === 0) {
    return emptyMessage ? <EmptyBanner message={emptyMessage} /> : null;
  }

  if (visible.length === 1) {
    const { banner, index, key } = visible[0];

    return (
      <section className="container-page section">
        <BannerTile
          banner={banner}
          index={index}
          tall
          priority={priority}
          sizes="(max-width: 768px) 100vw, 1400px"
          onBroken={() => markBroken(key)}
        />
      </section>
    );
  }

  // A slot can hold up to three images. They stay within their own slot's row
  // rather than merging with another slot's.
  const gridClass = visible.length === 2 ? "grid-cols-2" : "grid-cols-3";

  return (
    <section className="container-page section">
      <div className={`grid gap-5 ${gridClass}`}>
        {visible.map(({ banner, index, key }, position) => (
          <BannerTile
            key={key}
            banner={banner}
            index={index}
            priority={priority && position === 0}
            sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
            onBroken={() => markBroken(key)}
          />
        ))}
      </div>
    </section>
  );
}
