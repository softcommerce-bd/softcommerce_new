import { NextResponse } from "next/server";
import { hash, hashPhone, requestUserData, sendConversionEvent } from "@/lib/pixel/capi";

/**
 * Conversions API relay.
 *
 * The browser Pixel is blocked by ad blockers and by Safari's tracking
 * prevention, so a meaningful share of events never reach Meta from the page
 * alone. This route sends a server-side copy of the same event.
 *
 * Both copies carry the same `event_id`, which is how Meta recognises them as
 * one event instead of counting a purchase twice. The access token stays on the
 * server (lib/pixel/capi.js): it can write to the ad account, so it must never
 * be exposed with a NEXT_PUBLIC_ prefix.
 *
 * Only Meta's standard browsing events are relayed, with a size limit, so the
 * route cannot be used to push arbitrary events into the ad account. Funnel
 * purchases do not come through here: their value is read from the order on the
 * server (app/api/funnel/purchase).
 */

const EVENTS = new Set(["PageView", "ViewContent", "AddToCart", "InitiateCheckout", "AddPaymentInfo", "Purchase", "Search", "AddToWishlist", "CompleteRegistration", "Lead", "Contact"]);
const MAX_BODY = 16 * 1024;

export async function POST(request) {
  let body;
  try {
    const text = await request.text();
    if (text.length > MAX_BODY) return NextResponse.json({ success: false, error: "too large" }, { status: 413 });
    body = JSON.parse(text);
  } catch {
    return NextResponse.json({ success: false, error: "invalid json" }, { status: 400 });
  }

  const { event_name, event_id, event_source_url, custom_data = {}, user_data = {} } = body ?? {};

  if (!event_name || !event_id) {
    return NextResponse.json({ success: false, error: "event_name and event_id are required" }, { status: 400 });
  }
  if (!EVENTS.has(event_name) || typeof event_id !== "string" || event_id.length > 100) {
    return NextResponse.json({ success: false, error: "unsupported event" }, { status: 400 });
  }

  const result = await sendConversionEvent({
    eventName: event_name,
    eventId: event_id,
    eventSourceUrl: typeof event_source_url === "string" ? event_source_url.slice(0, 1000) : undefined,
    customData: custom_data && typeof custom_data === "object" ? custom_data : {},
    userData: {
      // Email and phone are forwarded only when the page already had them, hashed.
      em: hash(user_data?.email),
      ph: hashPhone(user_data?.phone),
      ...requestUserData(request),
    },
  });

  return NextResponse.json(result, { status: 200 });
}
