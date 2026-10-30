"use client";

/**
 * Seller shop header — identity plus the shop's section tabs.
 *
 * Rebuilt on the design system. Several pieces of invented content have been
 * removed rather than restyled:
 *
 *   · The shop name fell back to "LOUIS VUITTON" and the address to
 *     "Dhaka, Bangladesh" — a shop with missing data showed another brand's
 *     name. Both now fall back to nothing.
 *   · A hardcoded "★★★★★ 5.00 avg. rating" was printed for every seller
 *     regardless of their actual rating, which this endpoint does not return.
 *   · "Member Since 12 Apr 2025" was a fixed string, not the seller's date.
 *   · "Follow Seller" and "Chat Now" had no handlers at all.
 *
 * BACKEND NOTE: seller rating, join date, follow and chat all need API support
 * before they can be shown or wired up. They are flagged here, not faked.
 *
 * The tab list and `onTabChange` contract are unchanged.
 */

import Image from "next/image";
import { Store, MapPin } from "lucide-react";

const TABS = ["STORE HOME", "TOP SELLING", "COUPONS", "ALL PRODUCTS"];

export default function HeaderCard({ shopData, activeTab = "STORE HOME", onTabChange }) {
  const handleTabChange = (tab) => {
    if (onTabChange && typeof onTabChange === "function") onTabChange(tab);
  };

  return (
    <div>
      <div className="card-raised mb-8 flex flex-col items-center gap-5 p-6 text-center sm:flex-row sm:text-left">
        <div className="relative h-20 w-20 shrink-0 overflow-hidden rounded-full bg-canvas-sunken">
          {shopData?.logo ? (
            <Image
              src={shopData.logo}
              alt=""
              fill
              className="object-cover"
              sizes="80px"
              unoptimized
            />
          ) : (
            <span className="flex h-full w-full items-center justify-center">
              <Store size={26} className="text-ink-quaternary" strokeWidth={1.5} />
            </span>
          )}
        </div>

        <div className="min-w-0">
          <h1 className="type-title-2 text-ink">{shopData?.name}</h1>

          {shopData?.address && (
            <p className="type-footnote mt-1.5 flex items-center justify-center gap-1.5 text-ink-tertiary sm:justify-start">
              <MapPin size={14} strokeWidth={1.75} aria-hidden="true" />
              {shopData.address}
            </p>
          )}
        </div>
      </div>

      {/* Section tabs. The scroll container lets them run off-screen on a
          phone instead of wrapping into an uneven block. */}
      <div className="scrollbar-hide -mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
        <div
          role="tablist"
          aria-label="Shop sections"
          className="inline-flex gap-1 rounded-full bg-canvas-sunken p-1"
        >
          {TABS.map((tab) => {
            const isSelected = tab === activeTab;
            return (
              <button
                key={tab}
                type="button"
                role="tab"
                aria-selected={isSelected}
                onClick={() => handleTabChange(tab)}
                className={`whitespace-nowrap rounded-full px-4 py-2 text-[0.6875rem] font-semibold uppercase tracking-[0.06em] transition-all duration-fast ease-standard ${
                  isSelected
                    ? "bg-surface text-ink shadow-e1"
                    : "text-ink-tertiary hover:text-ink"
                }`}
              >
                {tab}
              </button>
            );
          })}
        </div>
      </div>
    </div>
  );
}
