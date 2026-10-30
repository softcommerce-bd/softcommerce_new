import { redirect } from "next/navigation";

/**
 * Bare `/confirm-order` route.
 *
 * This rendered a hardcoded mock confirmation — an invented customer
 * ("MD.Ilias Ahomed", a real-looking email and phone number), two fake Samsung
 * line items and a ৳601,140 total — with no data fetching at all.
 *
 * A real confirmation is always `/confirm-order/[id]`, which CheckoutCard
 * routes to after checkout. Nothing links here, so the route now forwards home
 * rather than showing a stranger's details to whoever opens it.
 */
export default function ConfirmOrderIndexRedirect() {
  redirect("/");
}
