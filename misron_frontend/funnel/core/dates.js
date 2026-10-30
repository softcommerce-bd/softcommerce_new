/**
 * Date helpers for scheduling. Pages store ISO 8601 times with an offset, and
 * browsers edit them as "datetime-local" strings in the viewer's own zone.
 */

function pad(n) {
  return String(n).padStart(2, "0");
}

/** ISO 8601 with the browser's offset, e.g. 2026-12-31T23:59:00+06:00. */
export function toIsoWithOffset(date) {
  const offset = -date.getTimezoneOffset();
  const sign = offset >= 0 ? "+" : "-";
  const abs = Math.abs(offset);
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}:00${sign}${pad(Math.floor(abs / 60))}:${pad(abs % 60)}`;
}

/** The value a datetime-local input shows for an ISO time (its local wall-clock time), or "". */
export function toLocalInput(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** Parses a datetime-local input value into an ISO string with offset, or null. */
export function fromLocalInput(input) {
  if (!input) return null;
  const date = new Date(input);
  return Number.isNaN(date.getTime()) ? null : toIsoWithOffset(date);
}

/** "12 Nov 2026, 6:30 pm" in the viewer's zone. */
export function formatWhen(value, locale) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return date.toLocaleString(locale, { day: "numeric", month: "short", year: "numeric", hour: "numeric", minute: "2-digit" });
}

/** The current time as a datetime-local value, for the "min" of a picker. */
export function nowLocalInput() {
  return toLocalInput(new Date().toISOString());
}
