"use client";

/**
 * Social links rail, pinned to the right edge.
 *
 * A single frosted card holding the icons rather than a stack of separate
 * black discs, and hidden below `lg`. On a phone it sat vertically centred
 * over the page content, overlapping product cards and the cart drawer, while
 * the footer already carries the same links.
 *
 * Reads `/footer-data` through the shared cache rather than making its own
 * `no-store` request; the header strip and footer need the same payload, so
 * the three of them now share one fetch.
 */

import useCachedFetch from "../utils/useCachedFetch";
import SocialIcons from "./SocialIcons";

export default function FloatingSocialBar() {
  const { data: footerData } = useCachedFetch(
    `${process.env.NEXT_PUBLIC_API_URL}/footer-data`,
    "footer-data",
    30 * 60 * 1000,
  );

  const socialLinks = footerData?.show_social_links
    ? footerData.social_links || []
    : [];

  if (!socialLinks.length) return null;

  return (
    <div className="fixed right-4 top-1/2 z-40 hidden -translate-y-1/2 lg:block">
      <div className="surface-blur rounded-full p-1.5 shadow-e2">
        <SocialIcons
          socialLinks={socialLinks}
          className="h-4 w-4"
          gap="flex-col gap-1"
          linkClassName="flex h-9 w-9 items-center justify-center rounded-full text-ink-secondary transition-colors duration-fast hover:bg-brand-tint hover:text-ink"
        />
      </div>
    </div>
  );
}
