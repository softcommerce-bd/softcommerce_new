import { redirect } from "next/navigation";

/**
 * Bare `/product` route.
 *
 * This rendered a placeholder that printed the literal word "page". Nothing
 * links here — product pages live at `/product/[slug]` — so the route now
 * forwards to the shop rather than showing a stub to anyone who trims a URL.
 */
export default function ProductIndexRedirect() {
  redirect("/shop_page");
}
