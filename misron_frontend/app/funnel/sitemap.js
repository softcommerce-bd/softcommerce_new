import { loadLiveList } from "./_lib/data";

/**
 * /funnel/sitemap.xml: the sales funnel pages that are live now and not marked
 * "hide from search engines". Listed by the store's backend, so a page appears
 * here when it goes live and drops out when it is unpublished or ends.
 */

export const revalidate = 300;

export default async function sitemap() {
  const site = (process.env.NEXT_PUBLIC_SITE_URL || "").replace(/\/+$/, "");
  if (!site) return [];

  const pages = await loadLiveList();
  return pages
    .filter((page) => !page.noindex)
    .map((page) => ({
      url: `${site}/funnel/${page.slug}`,
      lastModified: page.updated_at ? new Date(page.updated_at) : undefined,
      changeFrequency: "weekly",
      priority: 0.6,
    }));
}
