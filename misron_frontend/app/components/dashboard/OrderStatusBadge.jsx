/**
 * Order status pill.
 *
 * Maps a payment or delivery status onto one of the system's status tones.
 * The previous version matched exact-cased strings against a small lookup, so
 * anything the API sent outside that list — "shipped", "cancelled", "Refunded"
 * — silently fell through to plain grey. Matching is now case-insensitive and
 * covers the full set of statuses used elsewhere in the app.
 */

import Badge from "../ui/Badge";

const PAYMENT_TONES = {
  paid: "success",
  "un-paid": "danger",
  unpaid: "danger",
  pending: "warning",
  failed: "danger",
  refunded: "info",
};

const DELIVERY_TONES = {
  pending: "warning",
  confirmed: "info",
  processing: "info",
  "on-delivery": "info",
  "on the way": "info",
  shipped: "info",
  delivered: "success",
  cancelled: "danger",
  canceled: "danger",
  returned: "neutral",
};

/** "un-paid" → "Un-paid"; leaves already-formatted labels alone. */
const toLabel = (status) => {
  if (!status) return "Unknown";
  return status.charAt(0).toUpperCase() + status.slice(1);
};

export default function OrderStatusBadge({ status, type }) {
  const key = status?.toLowerCase() ?? "";
  const tones = type === "payment" ? PAYMENT_TONES : DELIVERY_TONES;

  return <Badge tone={tones[key] ?? "neutral"}>{toLabel(status)}</Badge>;
}
