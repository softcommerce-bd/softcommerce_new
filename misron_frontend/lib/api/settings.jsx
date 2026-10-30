/**
 * Business settings read from the admin backend.
 *
 * `GET /business-settings` returns every setting row as a flat list of
 * `{ type, value }` pairs, so one request covers any setting a server
 * component needs. The list is fetched here and turned into a lookup.
 *
 * Server-only. The base URL is `BACKEND_API_URL`, not `NEXT_PUBLIC_API_URL`:
 * the public value points at this app's own `/api/v2` rewrite, so using it on
 * the server would make Next call back into itself over the network for data
 * it can fetch from the backend directly.
 */

const BACKEND_API_URL = process.env.BACKEND_API_URL;

// Settings change rarely and are read on the home page, so the response is
// cached and refreshed in the background rather than fetched per request.
const SETTINGS_REVALIDATE_SECONDS = 300;

/**
 * Every setting as a `{ [type]: value }` object.
 *
 * Returns an empty object when the backend is unreachable or answers with
 * something unexpected. Callers are decorative or have their own defaults, so
 * a failed settings fetch must not take the page down with it.
 */
export async function getBusinessSettings() {
  if (!BACKEND_API_URL) {
    console.error("BACKEND_API_URL is not set; business settings unavailable.");
    return {};
  }

  try {
    const response = await fetch(`${BACKEND_API_URL.replace(/\/+$/, "")}/business-settings`, {
      headers: { Accept: "application/json" },
      next: { revalidate: SETTINGS_REVALIDATE_SECONDS },
    });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);

    const json = await response.json();
    if (!Array.isArray(json?.data)) return {};

    return Object.fromEntries(json.data.map(({ type, value }) => [type, value]));
  } catch (error) {
    console.error("Business settings fetch failed:", error);
    return {};
  }
}

/**
 * One setting, or `fallback` when it is missing or empty.
 */
export function getSetting(settings, type, fallback = null) {
  const value = settings?.[type];
  return value === undefined || value === null || value === "" ? fallback : value;
}

/**
 * A setting stored as a JSON array, as a plain array of non-empty strings.
 *
 * The admin form posts these fields as arrays, which the settings handler
 * json_encodes before saving, so the stored value is a JSON string. Blank
 * rows are dropped — the form keeps one empty input when nothing is set yet,
 * and that row would otherwise render as an empty item.
 */
export function getSettingList(settings, type, fallback = []) {
  const raw = getSetting(settings, type);
  if (!raw) return fallback;

  let parsed;
  try {
    parsed = typeof raw === "string" ? JSON.parse(raw) : raw;
  } catch {
    return fallback;
  }

  if (!Array.isArray(parsed)) return fallback;

  const items = parsed
    .filter((item) => typeof item === "string")
    .map((item) => item.trim())
    .filter(Boolean);

  return items.length > 0 ? items : fallback;
}
