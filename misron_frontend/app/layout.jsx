// app/layout.js
import { Cormorant_Garamond, Hind_Siliguri } from "next/font/google";
import "./globals.css";
import LayoutDetector from "@/components/LayoutDetector";
import { ReduxProvider } from "./ReduxProvider";
import { ThemeProvider } from "@/components/ThemeProvider";
import MetaPixel from "@/components/MetaPixel";
import { fetchSEOSettings } from "@/lib/seo";
import Script from "next/script";
import { Suspense } from "react";
import { consentBlockedExpression } from "@/lib/consent";

// Body face, matching the reference design: Hind Siliguri covers Latin and
// Bengali in one family, so Bangla product names and addresses render in the
// same face as the English copy instead of falling to a system font.
//
// Only the four weights the app actually uses are loaded; nothing asks for
// light or black. `display: "swap"` shows the fallback immediately and swaps
// the face in when it arrives, so text is never invisible while it downloads.
const hindSiliguri = Hind_Siliguri({
  subsets: ["latin", "bengali"],
  weight: ["400", "500", "600", "700"],
  variable: "--font-hind",
  display: "swap",
});

// Serif face for the home page's section headlines (`.type-title-serif`), the
// featured product names (`.type-card-serif`) and the cart drawer title
// (`.type-drawer-title`). The first two are regular; the drawer is semibold.
// Each extra weight is a separate file to download, so add them sparingly.
const cormorant = Cormorant_Garamond({
  subsets: ["latin"],
  weight: ["400", "600"],
  variable: "--font-cormorant",
  display: "swap",
});

// Brand fallbacks.
//
// Only reached when the SEO settings request fails; the admin's own values are
// preferred whenever they load. `NEXT_PUBLIC_APP_NAME` keeps the name in one
// place so a rebrand does not mean hunting through strings.
const BRAND_NAME = process.env.NEXT_PUBLIC_APP_NAME || "Misron";
const BRAND_TAGLINE = "Tees Worth Wearing";
const BRAND_DESCRIPTION = `Shop the latest t-shirts at ${BRAND_NAME}. Quality prints with fast delivery.`;

// Dynamic metadata for layout
export async function generateMetadata() {
  try {
    const seoSettings = await fetchSEOSettings();
    
    if (seoSettings && seoSettings.success && seoSettings.data) {
      const data = seoSettings.data;
      
      return {
        title: {
          default: `${data.website_name} | ${data.site_motto}`,
          template: `%s | ${data.website_name}`
        },
        description: data.meta_description,
        keywords: data.meta_keywords,
        metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL || ''),
        openGraph: {
          title: data.meta_title,
          description: data.meta_description,
          images: [data.meta_image],
          siteName: data.website_name,
        },
        twitter: {
          card: 'summary_large_image',
          title: data.meta_title,
          description: data.meta_description,
          images: [data.meta_image],
        },
      };
    }
  } catch (error) {
    console.error("Error generating metadata:", error);
  }
  
  // Fallback metadata if API fails
  return {
    title: {
      default: `${BRAND_NAME} | ${BRAND_TAGLINE}`,
      template: `%s | ${BRAND_NAME}`
    },
    description: BRAND_DESCRIPTION,
    keywords: "t-shirts, tees, graphic tees, ecommerce, online store",
    metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL || ''),
    openGraph: {
      title: BRAND_NAME,
      description: BRAND_DESCRIPTION,
      images: ["/default-og-image.jpg"],
      siteName: BRAND_NAME,
    },
    twitter: {
      card: 'summary_large_image',
      title: BRAND_NAME,
      description: BRAND_DESCRIPTION,
      images: ["/default-og-image.jpg"],
    },
  };
}

export default async function RootLayout({ children }) {
  let seoSettings = null;
  let siteIcon = "/favicon.ico";
  
  try {
    const result = await fetchSEOSettings();
    if (result && result.success && result.data) {
      seoSettings = result.data;
      siteIcon = seoSettings.site_icon || "/favicon.ico";
    }
  } catch (error) {
    console.error("Error fetching SEO settings for layout:", error);
  }
  
  const gtmId = process.env.NEXT_PUBLIC_GTM_ID ?? '';
  
  return (
    <html lang="en">
      <head>
        <meta name="csrf-token" content={process.env.NEXT_PUBLIC_CSRF_TOKEN || ""} />
        <meta name="app-url" content={process.env.NEXT_PUBLIC_API_URL || "/api/v2"} />
        <meta name="file-base-url" content={process.env.NEXT_PUBLIC_FILE_BASE_URL || ""} />
        
        <link rel="icon" href={siteIcon} />
        <link rel="apple-touch-icon" href={siteIcon} />
        
      </head>
      <body
        suppressHydrationWarning
        className={`${hindSiliguri.variable} ${cormorant.variable} antialiased`}
      >
        <ReduxProvider>
          <ThemeProvider>
            <Suspense fallback={<div>{children}</div>}>
              <LayoutDetector>
                {children}
              </LayoutDetector>
            </Suspense>
          </ThemeProvider>
        </ReduxProvider>
        
        {/* Block ob3_plugin events - MUST run before GTM script */}
        <Script id="block-ob3-plugin" strategy="beforeInteractive">
          {`
            if (typeof window !== 'undefined') {
              window.dataLayer = window.dataLayer || [];
              const originalPush = window.dataLayer.push;
              window.dataLayer.push = function(...args) {
                const event = args[0];
                if (event && event.event_id && event.event_id.includes('ob3_plugin')) {
                  return;
                }
                if (event && event.cs_est === true && Object.keys(event).length <= 2) {
                  return;
                }
                return originalPush.apply(this, args);
              };
            }
          `}
        </Script>

        {/* Meta Pixel. Reads only the pathname, so it server-renders normally
            and its <noscript> fallback reaches browsers without JavaScript. */}
        <MetaPixel />

        {/* Google Tag Manager. Not loaded in the funnel editor (/builder),
            whose visitors are staff, not shoppers, nor on a funnel draft preview,
            whose address carries a secret. Google's consent mode starts as
            "denied" when tracking consent (lib/consent.js) is missing. */}
        <Script id="gtm-script" strategy="afterInteractive">
          {`
            (function(w,d,s,l,i){var p=w.location.pathname;if(p.indexOf('/builder')===0||(p.indexOf('/funnel/')===0&&p.indexOf('/preview/')>0))return;w[l]=w[l]||[];
            if(${consentBlockedExpression()}){(function(){w[l].push(arguments);})('consent','default',{ad_storage:'denied',analytics_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'});}
            w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','${gtmId}');
          `}
        </Script>

        <Script
          id="user-state"
          strategy="beforeInteractive"
          dangerouslySetInnerHTML={{
            __html: `
              window.isLoggedIn = false;
            `,
          }}
        />
        
        {/* Google Tag Manager (noscript) - Required for users without JavaScript */}
        <noscript>
          <iframe 
            src={`https://www.googletagmanager.com/ns.html?id=${gtmId}`}
            height="0" 
            width="0" 
            style={{ display: 'none', visibility: 'hidden' }}
          />
        </noscript>
      </body>
    </html>
  );
}