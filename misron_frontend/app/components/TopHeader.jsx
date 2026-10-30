"use client";

import { Phone, Sparkles } from "lucide-react";

import useCachedFetch from "../utils/useCachedFetch";

// Monospace chip for a promo code inside the announcement strip.
function CodeBadge({ children }) {
  return (
    <strong className="rounded bg-amber-900/60 px-1.5 py-0.5 font-mono text-[11px] font-bold text-white">
      {children}
    </strong>
  );
}

/**
 * Announcement strip above the header.
 *
 * Dark warm ground, small type, no borders. The admin's announcement sits behind
 * a small gold dot; the helpline gives a visitor a way to reach the shop by
 * phone from every page.
 *
 *   laptop (1024+)   one row: announcement · promo · helpline on the right
 *   below `lg`       two rows: the announcement on top, which may wrap to two
 *                    lines, and the helpline with its number underneath; the
 *                    promo joins the second row from `md`
 *
 * Any `{...}` in `promoText` becomes a badge: `{code}` shows the admin's promo
 * code, `{SAVE10}` shows SAVE10 as typed. With no marker at all the promo code,
 * if set, follows the text.
 *
 * Each part shows only when the admin has set it, with no fallback text. With
 * no announcement and no promo the strip does not render at all, even when a
 * helpline number is set.
 */
export default function TopHeader({ topHeader, promoText, promoCode }) {
  // Shares one `/footer-data` request with the footer and the social bar, which
  // both need the same payload. This previously made its own `no-store` fetch,
  // so the endpoint was hit three times on every page load.
  const { data: footerData, loading: helplineLoading } = useCachedFetch(
    `${process.env.NEXT_PUBLIC_API_URL}/footer-data`,
    "footer-data",
    30 * 60 * 1000,
  );

  const announcement = topHeader?.trim();
  const promo = promoText?.trim();
  const helpline = footerData?.contact_phone;

  // The helpline rides along with the admin's topbar content; it never keeps the
  // strip up on its own.
  if (!announcement && !promo) return null;

  const promoParts = (promo || "").split(/(\{[^}]*\})/);
  // The promo only shows from `md`, so a strip or row with nothing else in it
  // hides below `md` instead of showing as an empty dark band.
  const hasHelplineRow = helpline || helplineLoading;

  return (
    <div
      className={`w-full bg-stone-900 text-[11px] text-stone-200 sm:text-xs ${
        announcement || helpline ? "" : "hidden md:block"
      }`}
    >
      <div className="container-page flex flex-col items-start gap-0.5 py-1.5 sm:py-2 lg:min-h-8 lg:flex-row lg:items-center lg:justify-between lg:gap-6">
        {announcement && (
          <p className="flex min-w-0 items-start gap-2 font-normal leading-4 tracking-wide">
            <span
              className="mt-[5px] size-1.5 shrink-0 rounded-full bg-amber-400"
              aria-hidden="true"
            />
            <span className="line-clamp-2 lg:line-clamp-1">{announcement}</span>
          </p>
        )}

        {/* Second row below `lg`. `lg:contents` dissolves it on a laptop so the
            promo and helpline join the announcement's row, and `lg:order-last`
            keeps the helpline at the right-hand end. The row holds its height
            while the helpline loads, so the strip does not grow under the
            page. */}
        {(promo || hasHelplineRow) && (
          <div
            className={`${
              hasHelplineRow ? "flex" : "hidden md:flex"
            } min-h-5 w-full items-center justify-between gap-3 lg:contents`}
          >
            <div className="flex shrink-0 items-center gap-4 lg:order-last">
              {helpline && (
                <a
                  href={`tel:${String(helpline).replace(/[^\d+]/g, "")}`}
                  aria-label={`Helpline ${helpline}`}
                  className="flex items-center gap-1.5 whitespace-nowrap py-0.5 text-stone-300 lg:py-0 transition-colors duration-fast hover:text-white"
                >
                  <Phone size={12} strokeWidth={2} className="shrink-0 text-amber-400" aria-hidden="true" />
                  <span>
                    Helpline:{" "}
                    <span className="tabular font-mono font-medium">{helpline}</span>
                  </span>
                </a>
              )}
            </div>

            {promo && (
              <p className="hidden shrink-0 items-center gap-2 font-medium text-amber-300/95 md:flex">
                <Sparkles size={14} strokeWidth={2} aria-hidden="true" />
                <span>
                  {promoParts.map((part, i) => {
                    const marker = part.match(/^\{(.*)\}$/);
                    if (!marker) return part;
                    const label = marker[1].trim();
                    const code = label.toLowerCase() === "code" ? promoCode : label;
                    return code ? <CodeBadge key={i}>{code}</CodeBadge> : null;
                  })}
                  {promoParts.length === 1 && promoCode && (
                    <CodeBadge>{promoCode}</CodeBadge>
                  )}
                </span>
              </p>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
