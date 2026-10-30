import { timingSafeEqual } from "node:crypto";
import { revalidateTag } from "next/cache";
import { funnelTag } from "../../../funnel/_lib/data";

/**
 * Called by the store's backend when a funnel page changes (published,
 * unpublished, scheduled time reached, address or search details edited), so
 * the cached copy is dropped at once instead of after its short lifetime.
 *
 * Body: { "slugs": ["blender-offer", ...] }. Needs the shared secret:
 * `Authorization: Bearer <FUNNEL_REVALIDATE_SECRET>`. Without the secret being
 * configured the route refuses everything.
 */

const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const MAX_SLUGS = 20;

function sameSecret(given, expected) {
  const a = Buffer.from(given);
  const b = Buffer.from(expected);
  return a.length === b.length && timingSafeEqual(a, b);
}

export async function POST(request) {
  const secret = process.env.FUNNEL_REVALIDATE_SECRET;
  if (!secret) return Response.json({ ok: false, error: "not_configured" }, { status: 503 });

  const header = request.headers.get("authorization") ?? "";
  const given = header.startsWith("Bearer ") ? header.slice(7) : "";
  if (!given || !sameSecret(given, secret)) return Response.json({ ok: false, error: "unauthorized" }, { status: 401 });

  let body = null;
  try {
    body = await request.json();
  } catch {
    body = null;
  }
  const slugs = Array.isArray(body?.slugs) ? body.slugs.filter((slug) => typeof slug === "string" && SLUG.test(slug)).slice(0, MAX_SLUGS) : [];
  if (slugs.length === 0) return Response.json({ ok: false, error: "no_slugs" }, { status: 422 });

  // { expire: 0 }: the next visitor gets the fresh page, never a stale one, so an
  // unpublished page stops showing at once.
  for (const slug of slugs) revalidateTag(funnelTag(slug), { expire: 0 });
  revalidateTag("funnel:list", { expire: 0 });

  return Response.json({ ok: true, refreshed: slugs });
}
