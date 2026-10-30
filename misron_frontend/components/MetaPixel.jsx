"use client";

import Script from "next/script";
import { usePathname } from "next/navigation";
import { useEffect, useRef } from "react";
import { consentBlockedExpression, consentMode } from "@/lib/consent";

/**
 * Meta Pixel loader and PageView tracker.
 *
 * The base snippet fires PageView once on load. This app is a single-page
 * router, so subsequent navigations never reload the script and would
 * otherwise go untracked; the effect below fires PageView on each route
 * change, skipping the first run so the initial view is not counted twice.
 *
 * Only `usePathname` is read, deliberately. `useSearchParams` would make Next
 * bail out of server-rendering this subtree, which kept the <noscript> pixel
 * out of the delivered HTML entirely. Query strings do not identify a product
 * page here, so pathname alone is the right granularity anyway.
 *
 * Renders nothing when NEXT_PUBLIC_FACEBOOK_PIXEL_ID is unset, which keeps
 * local development free of stray events.
 */
export default function MetaPixel() {
  const pixelId = process.env.NEXT_PUBLIC_FACEBOOK_PIXEL_ID;
  const pathname = usePathname();
  const isFirstRun = useRef(true);
  // Staff working in the funnel editor are not shoppers; their visits must
  // not reach the ad account. A funnel draft preview is not a shopper's visit
  // either, and its address carries a secret that must not leave the site.
  const isBuilder = pathname?.startsWith("/builder") || /^\/funnel\/[^/]+\/preview\//.test(pathname ?? "");

  useEffect(() => {
    if (!pixelId || isBuilder) return;
    if (isFirstRun.current) {
      isFirstRun.current = false;
      return;
    }
    if (typeof window !== "undefined" && typeof window.fbq === "function") {
      window.fbq("track", "PageView");
    }
  }, [pathname, pixelId, isBuilder]);

  if (!pixelId || isBuilder) return null;

  return (
    <>
      <Script id="meta-pixel" strategy="afterInteractive">
        {`
          !function(f,b,e,v,n,t,s)
          {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
          n.callMethod.apply(n,arguments):n.queue.push(arguments)};
          if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
          n.queue=[];t=b.createElement(e);t.async=!0;
          t.src=v;s=b.getElementsByTagName(e)[0];
          s.parentNode.insertBefore(t,s)}(window,document,'script',
          'https://connect.facebook.net/en_US/fbevents.js');
          // Tracking consent (lib/consent.js): without it Meta keeps nothing until
          // the visitor agrees and the page calls fbq('consent', 'grant').
          if (${consentBlockedExpression()}) fbq('consent', 'revoke');
          fbq('init', '${pixelId}');
          fbq('track', 'PageView');
        `}
      </Script>
      {/* Without JavaScript nobody can be asked for consent, so where consent is
          required the no-script pixel is left out. */}
      {consentMode() === "required" ? null : (
        <noscript>
          <img
            height="1"
            width="1"
            style={{ display: "none" }}
            alt=""
            src={`https://www.facebook.com/tr?id=${pixelId}&ev=PageView&noscript=1`}
          />
        </noscript>
      )}
    </>
  );
}
