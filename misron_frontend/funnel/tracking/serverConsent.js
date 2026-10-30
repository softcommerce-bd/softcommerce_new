/**
 * The tracking consent rule of lib/consent.js, read from a request's cookies on
 * the server.
 */
export function marketingAllowedFromCookies(cookies) {
  const answer = cookies.get("tracking_consent")?.value;
  if (answer === "granted") return true;
  if (answer === "denied") return false;
  return process.env.NEXT_PUBLIC_TRACKING_CONSENT !== "required";
}
