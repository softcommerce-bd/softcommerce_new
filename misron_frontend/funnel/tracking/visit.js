/**
 * What a funnel page remembers about the current visit, in the visitor's own
 * browser: an anonymous visitor id, a session id, where the visit came from
 * (campaign parameters, ad click ids, the referring site), and the one-time
 * values a link can carry (an order receipt back from the payment gateway, a
 * staff test-order token).
 *
 * None of it is personal: ids are random, the referrer is reduced to its host
 * name. Storage can be missing (private windows, blocked site data), so every
 * read and write is guarded and the page works without it.
 */
import { trackingConsent } from "../../lib/consent.js";

const CAMPAIGN = ["utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term"];

/** Values a link may carry once; removed from the address before any tag can read it. */
export const ONE_TIME = ["receipt", "payment", "fn_test"];

function store(kind) {
  try {
    return kind === "local" ? window.localStorage : window.sessionStorage;
  } catch {
    return null;
  }
}

function get(kind, key) {
  try {
    return store(kind)?.getItem(key) ?? null;
  } catch {
    return null;
  }
}

function set(kind, key, value) {
  try {
    if (value === null || value === undefined) store(kind)?.removeItem(key);
    else store(kind)?.setItem(key, value);
  } catch {
    /* storage unavailable: the visit simply is not remembered */
  }
}

export function randomId() {
  if (typeof crypto !== "undefined" && crypto.randomUUID) return crypto.randomUUID();
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
}

/**
 * The anonymous visitor id. Kept across visits only when tracking consent allows
 * it; otherwise it lives for this tab's session.
 */
export function visitorId() {
  const persistent = trackingConsent() !== "denied";
  let id = get(persistent ? "local" : "session", "fn_vid") ?? get("session", "fn_vid");
  if (!id) {
    id = randomId();
    set(persistent ? "local" : "session", "fn_vid", id);
  }
  return id;
}

export function sessionId() {
  let id = get("session", "fn_sid");
  if (!id) {
    id = randomId();
    set("session", "fn_sid", id);
  }
  return id;
}

/**
 * The inline script that moves one-time values out of the address bar into this
 * tab's storage before the page (and any tracking tag) reads the address. It runs
 * while the HTML is parsed, so the Pixel and GTM, which load later, never see an
 * order receipt or a test token in the page URL.
 */
export const STASH_SCRIPT = `(function(){try{var u=new URL(location.href),p=u.searchParams,s=(location.pathname.split('/')[2]||''),c=false;${JSON.stringify(ONE_TIME)}.forEach(function(k){var v=p.get(k);if(v!==null){sessionStorage.setItem('fn:'+k+':'+s,v);p.delete(k);c=true;}});if(c){var q=p.toString();history.replaceState(history.state,'',u.pathname+(q?'?'+q:'')+u.hash);}}catch(e){}})();`;

/** A one-time value the stash script kept for this page, removed once read. */
export function takeOnce(slug, key) {
  const value = get("session", `fn:${key}:${slug}`);
  if (value !== null) set("session", `fn:${key}:${slug}`, null);
  if (value !== null) return value;
  // Without the stash script (or storage) the value is still in the address.
  try {
    return new URLSearchParams(window.location.search).get(key);
  } catch {
    return null;
  }
}

/** The staff test-order token of this tab, if the page was opened from the editor's test link. */
export function testToken(slug) {
  const fresh = get("session", `fn:fn_test:${slug}`);
  if (fresh !== null) {
    set("session", `fn:fn_test:${slug}`, null);
    set("session", `fn:test:${slug}`, fresh);
  }
  return get("session", `fn:test:${slug}`);
}

export function isTestVisit(slug) {
  return Boolean(testToken(slug));
}

function referrerHost() {
  try {
    if (!document.referrer) return null;
    const host = new URL(document.referrer).host;
    return host && host !== window.location.host ? host.toLowerCase() : null;
  } catch {
    return null;
  }
}

/**
 * Records where this visit came from. Called once when the page opens. A visit
 * that arrives with campaign parameters or an ad click replaces what an earlier
 * visit in the same tab recorded; a plain reload keeps it.
 */
export function captureArrival(slug) {
  let params;
  try {
    params = new URLSearchParams(window.location.search);
  } catch {
    params = new URLSearchParams();
  }
  const fresh = {};
  for (const key of CAMPAIGN) {
    const value = params.get(key);
    if (value) fresh[key] = value.slice(0, 150);
  }
  const fbclid = params.get("fbclid");
  const gclid = params.get("gclid");
  if (fbclid) fresh.fbclid = fbclid.slice(0, 255);
  if (gclid) fresh.gclid = gclid.slice(0, 255);
  const host = referrerHost();
  if (host) fresh.referrer_host = host;

  const key = `fn:attr:${slug}`;
  const hasCampaign = CAMPAIGN.some((k) => fresh[k]) || fresh.fbclid || fresh.gclid;
  if (hasCampaign || !get("session", key)) set("session", key, JSON.stringify(fresh));
}

/** Attribution sent with events and with the order. */
export function attribution(slug) {
  let saved = {};
  try {
    saved = JSON.parse(get("session", `fn:attr:${slug}`) || "{}") || {};
  } catch {
    saved = {};
  }
  const consent = trackingConsent();
  return {
    ...saved,
    // Ad click ids identify an ad interaction: kept only with consent.
    fbclid: consent === "denied" ? null : saved.fbclid ?? null,
    gclid: consent === "denied" ? null : saved.gclid ?? null,
    visitor_id: visitorId(),
    session_id: sessionId(),
    consent,
  };
}
