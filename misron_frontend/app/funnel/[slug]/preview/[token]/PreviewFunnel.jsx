"use client";

import { useMemo, useState } from "react";
import { Eye, X } from "lucide-react";
import FunnelPage from "@/funnel/render/FunnelPage";
import { CommerceProvider } from "@/funnel/commerce/CommerceProvider";
import { CheckoutProvider } from "@/funnel/commerce/CheckoutProvider";
import { previewLinkCommerce } from "@/funnel/commerce/adapters";
import { previewCheckout } from "@/funnel/commerce/checkoutAdapters";
import { PUBLIC_CONTEXT } from "@/funnel/render/parts";

/**
 * The draft with a banner that says what it is. The order form runs, so the
 * whole page can be tried, but "ordering" only shows the confirmation.
 */
export default function PreviewFunnel({ slug, token, document, status, title }) {
  const commerce = useMemo(() => previewLinkCommerce(slug, token), [slug, token]);
  const checkout = useMemo(() => previewCheckout(), []);
  const [bannerOpen, setBannerOpen] = useState(true);
  const english = document?.page?.settings?.language === "en";

  return (
    <>
      {bannerOpen ? (
        <div className="sticky top-0 z-50 flex items-center gap-2 bg-[#1f2937] px-4 py-2 text-[13px] text-white" role="status" data-testid="preview-link-banner">
          <Eye size={15} aria-hidden="true" className="shrink-0" />
          <span className="min-w-0 flex-1">
            {english
              ? `Draft preview of “${title}”${status === "published" ? "" : " (not published)"}. Customers cannot see this link and no real orders are placed.`
              : `“${title}” পেজের খসড়া প্রিভিউ${status === "published" ? "" : " (প্রকাশিত নয়)"}। এটি গ্রাহকরা দেখতে পাবেন না এবং এখান থেকে সত্যিকারের অর্ডার হয় না।`}
          </span>
          <button type="button" className="inline-flex size-8 shrink-0 items-center justify-center rounded-full hover:bg-white/15" onClick={() => setBannerOpen(false)} aria-label={english ? "Hide this notice" : "বার্তাটি লুকান"}>
            <X size={16} aria-hidden="true" />
          </button>
        </div>
      ) : null}
      <CommerceProvider document={document} adapter={commerce} interactive>
        <CheckoutProvider document={document} adapter={checkout} mode="preview">
          <FunnelPage document={document} ctx={PUBLIC_CONTEXT} />
        </CheckoutProvider>
      </CommerceProvider>
    </>
  );
}
