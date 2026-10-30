import crypto from "crypto";

/**
 * Sending one event to Meta's Conversions API from this app's server.
 *
 * Shared by the storefront relay (app/api/meta/conversion) and the funnel
 * Purchase relay (app/api/funnel/purchase). Server-only: the access token can
 * write to the ad account and must never reach a browser.
 */

const API_VERSION = "v21.0";

export function capiConfigured() {
  const pixelId = process.env.META_PIXEL_ID || process.env.NEXT_PUBLIC_FACEBOOK_PIXEL_ID;
  return Boolean(pixelId && process.env.META_CONVERSIONS_API_TOKEN);
}

/** Meta requires identifiers lowercased, trimmed and SHA-256 hashed. */
export function hash(value) {
  if (!value) return undefined;
  const normalised = String(value).trim().toLowerCase();
  if (!normalised) return undefined;
  return crypto.createHash("sha256").update(normalised).digest("hex");
}

/** Phone numbers must be digits only before hashing, or matching fails. */
export function hashPhone(value) {
  if (!value) return undefined;
  const digits = String(value).replace(/\D/g, "");
  return digits ? hash(digits) : undefined;
}

/** The visitor's own address and browser, Meta's cookies, from the incoming request. */
export function requestUserData(request) {
  // The client's address, not this server's: Meta uses it for matching.
  const forwardedFor = request.headers.get("x-forwarded-for") || "";
  const clientIp = forwardedFor.split(",")[0].trim();
  return {
    client_ip_address: clientIp || undefined,
    client_user_agent: request.headers.get("user-agent") || undefined,
    fbp: request.cookies.get("_fbp")?.value,
    fbc: request.cookies.get("_fbc")?.value,
  };
}

/**
 * @returns {Promise<{ success: boolean, skipped?: boolean, result?: object, error?: object }>}
 */
export async function sendConversionEvent({ eventName, eventId, eventSourceUrl, customData = {}, userData = {} }) {
  const pixelId = process.env.META_PIXEL_ID || process.env.NEXT_PUBLIC_FACEBOOK_PIXEL_ID;
  const accessToken = process.env.META_CONVERSIONS_API_TOKEN;
  // Unconfigured is not an error: the site runs fine without server-side tracking.
  if (!pixelId || !accessToken) return { success: false, skipped: true };

  const payload = {
    data: [
      {
        event_name: eventName,
        event_id: eventId,
        event_time: Math.floor(Date.now() / 1000),
        event_source_url: eventSourceUrl,
        action_source: "website",
        user_data: userData,
        custom_data: customData,
      },
    ],
  };

  // Routes the event to Test Events in Events Manager instead of live reporting.
  if (process.env.META_TEST_EVENT_CODE) payload.test_event_code = process.env.META_TEST_EVENT_CODE;

  try {
    const response = await fetch(`https://graph.facebook.com/${API_VERSION}/${pixelId}/events?access_token=${accessToken}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const result = await response.json();
    if (!response.ok) {
      console.error("Meta Conversions API rejected event:", result);
      return { success: false, error: result };
    }
    return { success: true, result };
  } catch (error) {
    // Tracking must never break the page that triggered it.
    console.error("Meta Conversions API request failed:", error);
    return { success: false };
  }
}
