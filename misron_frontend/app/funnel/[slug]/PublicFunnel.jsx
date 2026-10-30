"use client";

import { useEffect, useMemo, useState } from "react";
import FunnelPage from "@/funnel/render/FunnelPage";
import { CommerceProvider } from "@/funnel/commerce/CommerceProvider";
import { CheckoutProvider } from "@/funnel/commerce/CheckoutProvider";
import { publicCommerce } from "@/funnel/commerce/adapters";
import { publicCheckout } from "@/funnel/commerce/checkoutAdapters";
import { PUBLIC_CONTEXT } from "@/funnel/render/parts";
import FunnelTracking from "@/funnel/tracking/FunnelTracking";

const MAX_TIMER = 2 ** 31 - 1;

/**
 * The live page: the published document drawn by the same renderer as the
 * editor, with live offers, quotes and ordering from the store's public API.
 *
 * The page HTML may be a minute old, so a page that has an end time closes
 * itself in the visitor's browser at that moment; the store refuses orders from
 * a finished page regardless.
 */
export default function PublicFunnel({ slug, document, unpublishAt = null, initialOffers = null }) {
  const commerce = useMemo(() => publicCommerce(slug), [slug]);
  const checkout = useMemo(() => publicCheckout(slug), [slug]);
  const english = document?.page?.settings?.language === "en";

  const [ended, setEnded] = useState(false);
  useEffect(() => {
    if (!unpublishAt) return undefined;
    const wait = Date.parse(unpublishAt) - Date.now();
    if (Number.isNaN(wait) || wait > MAX_TIMER) return undefined;
    const timer = setTimeout(() => setEnded(true), Math.max(0, wait));
    return () => clearTimeout(timer);
  }, [unpublishAt]);

  if (ended) {
    return (
      <main className="flex min-h-dvh items-center justify-center px-6 text-center" data-testid="funnel-ended" lang={english ? "en" : "bn"}>
        <div>
          <h1 className="m-0 text-[24px] font-bold">{english ? "This offer has ended" : "এই অফারটি শেষ হয়ে গেছে"}</h1>
          <p className="mt-2 text-[16px] opacity-75">{english ? "Thank you for your interest." : "আগ্রহের জন্য ধন্যবাদ।"}</p>
        </div>
      </main>
    );
  }

  return (
    <CommerceProvider document={document} adapter={commerce} interactive initialOffers={initialOffers}>
      <CheckoutProvider document={document} adapter={checkout} mode="public">
        <FunnelPage document={document} ctx={PUBLIC_CONTEXT} />
        <FunnelTracking slug={slug} />
      </CheckoutProvider>
    </CommerceProvider>
  );
}
