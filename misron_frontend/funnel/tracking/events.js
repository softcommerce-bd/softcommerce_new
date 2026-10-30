/**
 * Sending a funnel page's visitor activity to the store, for the analytics
 * dashboard: page views, product views, product selections, checkout starts.
 *
 * Events wait in a small queue and go out together a moment later, or when the
 * visitor leaves the page (sendBeacon, which survives the page closing). Every
 * event has its own id, so a batch sent twice is counted once. Orders are never
 * reported from here: the dashboard reads them from the orders themselves.
 */
import { attribution, randomId } from "./visit.js";

const FLUSH_DELAY = 1500;
const MAX_BATCH = 25;

export function createEventQueue(slug, { endpoint, enabled = true } = {}) {
  const url = endpoint ?? `/api/v2/funnels/${encodeURIComponent(slug)}/events`;
  let queue = [];
  let timer = null;

  function payload(events) {
    const visit = attribution(slug);
    return JSON.stringify({
      visitor_id: visit.visitor_id,
      session_id: visit.session_id,
      context: {
        utm_source: visit.utm_source ?? null,
        utm_medium: visit.utm_medium ?? null,
        utm_campaign: visit.utm_campaign ?? null,
        referrer_host: visit.referrer_host ?? null,
        click: visit.fbclid ? "fbclid" : visit.gclid ? "gclid" : null,
      },
      events,
    });
  }

  function flush(leaving = false) {
    if (timer) {
      clearTimeout(timer);
      timer = null;
    }
    while (queue.length > 0) {
      const events = queue.splice(0, MAX_BATCH);
      const body = payload(events);
      let sent = false;
      if (leaving && typeof navigator !== "undefined" && navigator.sendBeacon) {
        try {
          sent = navigator.sendBeacon(url, new Blob([body], { type: "application/json" }));
        } catch {
          sent = false;
        }
      }
      if (!sent) {
        fetch(url, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json" }, body, keepalive: true, credentials: "omit" }).catch(() => {});
      }
    }
  }

  function record(type, extra = {}) {
    if (!enabled) return null;
    const id = randomId();
    queue.push({ id, type, ...extra });
    if (queue.length >= MAX_BATCH) flush();
    else if (!timer) timer = setTimeout(() => flush(), FLUSH_DELAY);
    return id;
  }

  return { record, flush };
}
