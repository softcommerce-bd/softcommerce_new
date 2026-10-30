/**
 * The editor session for this browser tab.
 *
 * Kept in sessionStorage, not localStorage: it ends with the tab, is never
 * shared with the storefront's customer login (which lives in localStorage),
 * and two tabs editing different funnels keep separate sessions.
 */

const KEY = "misron:funnel-editor-session";

// In-memory copy, used when sessionStorage is unavailable and as the source
// for the API client during this page's lifetime.
let current = null;

function fresh(session) {
  return session?.token && session.expiresAt && Date.parse(session.expiresAt) > Date.now() ? session : null;
}

/** Token for API requests, or null when signed out. */
export function currentToken() {
  current = fresh(current);
  return current?.token ?? null;
}

export function readSession() {
  try {
    const raw = window.sessionStorage.getItem(KEY);
    const session = raw ? fresh(JSON.parse(raw)) : null;
    if (raw && !session) window.sessionStorage.removeItem(KEY);
    current = session ?? fresh(current);
  } catch {
    current = fresh(current);
  }
  return current;
}

export function writeSession(session) {
  current = session;
  try {
    window.sessionStorage.setItem(KEY, JSON.stringify(session));
  } catch {
    // Storage can be unavailable (private mode quota); the session then lasts
    // only as long as the page, which is still usable.
  }
}

export function clearSession() {
  current = null;
  try {
    window.sessionStorage.removeItem(KEY);
  } catch {
    // Nothing to clear.
  }
}

/** Reads the one-time handoff code and removes it from the address bar. */
export function takeHandoffCode() {
  const url = new URL(window.location.href);
  const code = url.searchParams.get("handoff");
  if (!code) return null;
  url.searchParams.delete("handoff");
  window.history.replaceState(window.history.state, "", `${url.pathname}${url.search}${url.hash}`);
  return code;
}
