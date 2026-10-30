/**
 * Server-side reads for the public funnel pages. The backend is the only source
 * of truth: it decides whether a page is live (published, scheduled time
 * reached, not past its end time), so nothing here repeats those rules.
 *
 * Published pages are cached for REVALIDATE_SECONDS. The backend also tells the
 * storefront to drop the cached copy the moment a page is published,
 * unpublished or edited (app/api/funnel/revalidate), so the cache lifetime is
 * only the safety net if that call ever fails.
 */

export const REVALIDATE_SECONDS = 60;

const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const TOKEN = /^[A-Za-z0-9]{40}$/;

export function funnelTag(slug) {
  return `funnel:${slug}`;
}

function backend() {
  const base = process.env.BACKEND_API_URL;
  return base ? base.replace(/\/+$/, "") : null;
}

/** @returns {Promise<{ funnel: object, seo: object, document: object } | null>} */
export async function loadFunnel(slug) {
  const base = backend();
  if (!base || !SLUG.test(slug)) return null;

  try {
    const response = await fetch(`${base}/funnels/${slug}`, {
      headers: { Accept: "application/json" },
      next: { revalidate: REVALIDATE_SECONDS, tags: [funnelTag(slug)] },
    });
    if (!response.ok) return null;
    const data = await response.json();
    return data?.result && data.document ? data : null;
  } catch {
    return null;
  }
}

/**
 * The live offers of a published page (names, photos, prices, stock), drawn into the
 * page's HTML so product cards and their photos appear with the first paint instead of
 * after a request from the browser. Cached like the page; the browser refreshes them
 * a moment after loading, and the checkout always prices on the server.
 *
 * @returns {Promise<object | null>} offers by key, or null when they could not be read
 */
export async function loadOffers(slug) {
  const base = backend();
  if (!base || !SLUG.test(slug)) return null;

  try {
    const response = await fetch(`${base}/funnels/${slug}/offers`, {
      headers: { Accept: "application/json" },
      next: { revalidate: REVALIDATE_SECONDS, tags: [funnelTag(slug)] },
    });
    if (!response.ok) return null;
    const data = await response.json();
    return data?.offers && typeof data.offers === "object" ? data.offers : null;
  } catch {
    return null;
  }
}

/** The draft behind a secret link. Never cached. */
export async function loadPreview(slug, token) {
  const base = backend();
  if (!base || !SLUG.test(slug) || !TOKEN.test(token)) return null;

  try {
    const response = await fetch(`${base}/funnels/${slug}/preview/${token}`, {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
    if (!response.ok) return null;
    const data = await response.json();
    return data?.result && data.document ? data : null;
  } catch {
    return null;
  }
}

/** Pages that are live now, for the sitemap. */
export async function loadLiveList() {
  const base = backend();
  if (!base) return [];

  try {
    const response = await fetch(`${base}/funnels`, {
      headers: { Accept: "application/json" },
      next: { revalidate: 300, tags: ["funnel:list"] },
    });
    if (!response.ok) return [];
    const data = await response.json();
    return Array.isArray(data?.data) ? data.data : [];
  } catch {
    return [];
  }
}

/** Next.js metadata for a live page, from the backend's search and sharing details. */
export function metadataFor(data) {
  const seo = data.seo ?? {};
  const og = seo.og ?? {};
  const title = seo.title || data.funnel.title;
  const noindex = Boolean(seo.noindex);

  return {
    title: { absolute: title },
    description: seo.description || undefined,
    alternates: seo.canonical ? { canonical: seo.canonical } : undefined,
    robots: noindex ? { index: false, follow: false } : { index: true, follow: true },
    openGraph: {
      type: "website",
      locale: seo.language === "en" ? "en_US" : "bn_BD",
      url: og.url || seo.canonical || undefined,
      title: og.title || title,
      description: og.description || seo.description || undefined,
      images: og.image ? [{ url: og.image }] : undefined,
    },
    twitter: {
      card: og.image ? "summary_large_image" : "summary",
      title: og.title || title,
      description: og.description || seo.description || undefined,
      images: og.image ? [og.image] : undefined,
    },
  };
}

/** JSON for a <script type="application/ld+json">: "<" is escaped so page text can never close the tag. */
export function safeJson(value) {
  const separators = new RegExp("[" + String.fromCharCode(0x2028) + String.fromCharCode(0x2029) + "]", "g");
  return JSON.stringify(value)
    .replace(/</g, "\\u003c")
    .replace(separators, (ch) => "\\u" + ch.charCodeAt(0).toString(16));
}
