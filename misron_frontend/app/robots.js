/**
 * /robots.txt. The store was crawlable before this file existed, so everything
 * stays allowed except the places that are not for the public: the funnel
 * editor, secret draft previews, and the customer dashboard.
 */

export default function robots() {
  const site = (process.env.NEXT_PUBLIC_SITE_URL || "").replace(/\/+$/, "");

  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        disallow: ["/builder", "/funnel/*/preview/", "/dashboard"],
      },
    ],
    ...(site ? { sitemap: [`${site}/funnel/sitemap.xml`] } : {}),
  };
}
