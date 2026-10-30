/**
 * Tracking consent, shared by the storefront's Meta Pixel / GTM loaders and the
 * sales funnel pages.
 *
 * NEXT_PUBLIC_TRACKING_CONSENT chooses the rule:
 *  - "implied" (default): marketing tags run unless the visitor said no;
 *  - "required": marketing tags run only after the visitor said yes.
 *
 * The visitor's answer is the `tracking_consent` cookie ("granted" or "denied").
 * The store has no consent banner today; one only needs to set this cookie
 * (and call window.misronConsentChanged?.() so open pages can react).
 */

export const CONSENT_COOKIE = "tracking_consent";

export function consentMode() {
  return process.env.NEXT_PUBLIC_TRACKING_CONSENT === "required" ? "required" : "implied";
}

/** The visitor's own answer, or null when they gave none. */
export function readConsent() {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(/(?:^|;\s*)tracking_consent=(granted|denied)(?:;|$)/);
  return match ? match[1] : null;
}

/** "granted", "denied", or "unknown" (required mode, no answer yet). */
export function trackingConsent() {
  const answer = readConsent();
  if (answer) return answer;
  return consentMode() === "required" ? "unknown" : "granted";
}

/** Whether marketing tags (Meta Pixel, Conversions API, Google tags) may run. */
export function marketingAllowed() {
  return trackingConsent() === "granted";
}

/**
 * The same rule as a JavaScript expression, for the inline loader snippets that
 * run before any module: true when marketing tags must stay off.
 */
export function consentBlockedExpression() {
  return consentMode() === "required"
    ? "(document.cookie.indexOf('tracking_consent=granted')<0)"
    : "(document.cookie.indexOf('tracking_consent=denied')>-1)";
}
