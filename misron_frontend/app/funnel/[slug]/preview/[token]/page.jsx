import { notFound } from "next/navigation";
import { loadPreview } from "../../../_lib/data";
import PreviewFunnel from "./PreviewFunnel";

/**
 * A draft page behind a secret link.
 *
 * Read fresh on every visit (never cached), hidden from search engines, and the
 * address is kept out of the Referer header so the secret does not leak to
 * other sites. The page runs in preview mode: it shows live offers and prices
 * but can never place an order.
 */

export const dynamic = "force-dynamic";

export const metadata = {
  title: "Draft preview",
  robots: { index: false, follow: false, nocache: true },
  referrer: "no-referrer",
};

export default async function FunnelPreviewPage({ params }) {
  const { slug, token } = await params;
  const data = await loadPreview(slug, token);
  if (!data) notFound();

  return <PreviewFunnel slug={data.funnel.slug} token={token} document={data.document} status={data.funnel.status} title={data.funnel.title} />;
}
