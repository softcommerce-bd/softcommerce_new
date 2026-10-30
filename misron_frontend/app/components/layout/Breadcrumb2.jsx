"use client";

/**
 * Order-confirmation breadcrumb.
 *
 * Delegates to the shared Breadcrumb with a fixed trail, so the confirmation
 * page's crumbs look like every other page's. The previous version was a
 * standalone copy whose second crumb ("Confirm Order") linked back to `/`,
 * pointing the current page at the home page.
 */

import Breadcrumb from "./Breadcrumb";

export default function Breadcrumb2() {
  return (
    <Breadcrumb
      items={[
        { name: "Home", href: "/" },
        { name: "Order confirmation", href: "#" },
      ]}
    />
  );
}
