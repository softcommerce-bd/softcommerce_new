import { redirect } from "next/navigation";

/**
 * Legacy search route.
 *
 * This page previously rendered a hardcoded demo grid — eight invented
 * products ("Galaxy S22 Ultra", "Cat Carrier Bag") imported from
 * `public/products/*.png`, with non-functional filters and pagination. It was
 * linked from nowhere in the app, and real search runs through
 * `/shop_page?q=…`, which the header's search modal already uses.
 *
 * Rather than restyle a page of fictional data, the route now forwards to the
 * real search results so any old bookmark still lands somewhere useful.
 *
 * If a distinct search page is wanted later, it should be built on the same
 * `/products/search` endpoint the shop page calls.
 */
export default function SearchRedirect() {
  redirect("/shop_page");
}
