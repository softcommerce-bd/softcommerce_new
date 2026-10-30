import { NextResponse } from "next/server";
import { capiConfigured, requestUserData, sendConversionEvent } from "@/lib/pixel/capi";
import { marketingAllowedFromCookies } from "@/funnel/tracking/serverConsent";

/**
 * Server copy (Conversions API) of a funnel order's Purchase event.
 *
 * The browser sends only the page and the order receipt it holds. Everything
 * Meta receives is read here from the store's own record of that order:
 * whether it counts as a purchase (cash on delivery once placed, online
 * payment only once the gateway confirmed it, never when cancelled or a staff
 * test), its value, its products and its event id. The browser Pixel fires
 * with the same event id, so Meta keeps one event. Calling this twice for the
 * same order sends the same event id twice, which Meta also counts once.
 */

const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

export async function POST(request) {
  let body;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ sent: false, reason: "invalid" }, { status: 400 });
  }

  const slug = typeof body?.slug === "string" ? body.slug : "";
  const receipt = typeof body?.receipt === "string" ? body.receipt : "";
  if (!SLUG.test(slug) || !receipt || receipt.length > 1000) {
    return NextResponse.json({ sent: false, reason: "invalid" }, { status: 400 });
  }
  if (!capiConfigured()) return NextResponse.json({ sent: false, reason: "not_configured" });
  if (!marketingAllowedFromCookies(request.cookies)) return NextResponse.json({ sent: false, reason: "no_consent" });

  const base = (process.env.BACKEND_API_URL || "").replace(/\/+$/, "");
  let order;
  try {
    const response = await fetch(`${base}/funnels/${slug}/orders/${encodeURIComponent(receipt)}`, { headers: { Accept: "application/json" }, cache: "no-store" });
    if (!response.ok) return NextResponse.json({ sent: false, reason: "not_found" }, { status: 404 });
    order = (await response.json())?.order;
  } catch {
    return NextResponse.json({ sent: false, reason: "store_unreachable" }, { status: 502 });
  }

  const tracking = order?.tracking;
  if (!tracking?.eligible) return NextResponse.json({ sent: false, reason: "not_a_purchase" });

  const contents = Array.isArray(tracking.contents) ? tracking.contents : [];
  const site = (process.env.NEXT_PUBLIC_SITE_URL || "").replace(/\/+$/, "");
  const result = await sendConversionEvent({
    eventName: "Purchase",
    eventId: tracking.event_id,
    eventSourceUrl: `${site}/funnel/${slug}`,
    customData: {
      currency: tracking.currency,
      value: tracking.value,
      order_id: tracking.transaction_id,
      content_type: "product",
      content_ids: contents.map((c) => c.id),
      contents: contents.map((c) => ({ id: c.id, quantity: c.quantity, item_price: c.item_price })),
      num_items: contents.reduce((sum, c) => sum + (Number(c.quantity) || 0), 0),
    },
    // Already hashed by the store, and present only when the store allows sharing it.
    userData: { ...(tracking.user_data ?? {}), ...requestUserData(request) },
  });

  return NextResponse.json({ sent: Boolean(result.success) });
}
