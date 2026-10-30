import { notFound } from "next/navigation";
import { connection } from "next/server";
import { loadFunnel, loadOffers, metadataFor, safeJson } from "../_lib/data";
import PublicFunnel from "./PublicFunnel";
import { STASH_SCRIPT } from "@/funnel/tracking/visit";

/**
 * A published sales funnel page.
 *
 * The published version is read from the store's API and cached for a short
 * time. The backend tells this app to refresh a page the moment it is
 * published, unpublished or edited, so the cache only matters if that call
 * fails. A missing, unpublished, scheduled-for-later or expired page is a real
 * 404: no loading boundary sits above this route (the store's loading screens
 * live in its own segments, not in app/loading.jsx), so the page is rendered
 * before any of it is sent and notFound() can still set the status.
 *
 * Only the page shell lives here: ordering is done by the funnel components
 * against the same API.
 */


export async function generateMetadata({ params }) {
  const { slug } = await params;
  const data = await loadFunnel(slug);
  if (!data) return { title: "Page not found", robots: { index: false, follow: false } };

  return metadataFor(data);
}

export default async function FunnelPublicPage({ params }) {
  // Rendered for each visit from cached data (the page and offer reads below are cached
  // for REVALIDATE_SECONDS and dropped by tag the moment the page changes), rather than
  // as a cached static page: the store's root layout reads the address
  // (useSearchParams), and in a static page that makes the browser draw the whole page a
  // second time after loading, which delays the first product photo by seconds on phones.
  await connection();
  const { slug } = await params;
  const data = await loadFunnel(slug);
  if (!data) notFound();
  const offers = await loadOffers(slug);

  return (
    <>
      {/* Runs while the HTML is parsed, before any tracking tag reads the address. */}
      <script dangerouslySetInnerHTML={{ __html: STASH_SCRIPT }} />
      {data.seo?.json_ld ? <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: safeJson(data.seo.json_ld) }} /> : null}
      <PublicFunnel slug={data.funnel.slug} document={data.document} unpublishAt={data.funnel.unpublish_at ?? null} initialOffers={offers} />
    </>
  );
}
