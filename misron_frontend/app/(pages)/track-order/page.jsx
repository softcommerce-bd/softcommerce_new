// app/track-order/page.js
"use client";

/**
 * Order tracking.
 *
 * Restyled on the design system. The three states — idle, searching, not
 * found — now use the shared EmptyState and Skeleton rather than three
 * hand-built panels with purple icon discs, and the copy explains what to do
 * next instead of only reporting what happened.
 *
 * Lookup and state handling are unchanged.
 */

import { useState } from "react";
import { PackageSearch, SearchX } from "lucide-react";

import Breadcrumb from "@/app/components/layout/Breadcrumb";
import TrackOrderForm from "./components/TrackOrderForm";
import OrderDetails from "./components/OrderDetails";
import { EmptyState, Skeleton } from "@/app/components/ui";

export default function TrackOrder() {
  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);

  const handleOrderFound = (foundOrder) => setOrder(foundOrder);
  const handleLoading = (isLoading) => setLoading(isLoading);
  const handleSearchStart = () => setSearched(true);

  // Clears the result so the form is ready for another code.
  const handleNewSearch = () => {
    setOrder(null);
    setSearched(false);
  };

  return (
    <div className="container-page py-8 md:py-12">
      <Breadcrumb
        items={[
          { name: "Home", href: "/" },
          { name: "Track order", href: "/track-order" },
        ]}
      />

      <div className="mx-auto mt-8 max-w-2xl">
        <header className="mb-8">
          <h1 className="type-title-1 text-ink">
            {order ? "Your order" : "Track your order"}
          </h1>
          {!order && (
            <p className="type-callout mt-2 text-ink-tertiary">
              Enter the code from your order confirmation to see where it is.
            </p>
          )}
        </header>

        <TrackOrderForm
          onOrderFound={handleOrderFound}
          onLoading={handleLoading}
          onSearchStart={handleSearchStart}
        />

        <div className="mt-10">
          {loading ? (
            <div className="space-y-4">
              <Skeleton className="h-32 w-full" rounded="lg" />
              <Skeleton className="h-48 w-full" rounded="lg" />
            </div>
          ) : order ? (
            <OrderDetails order={order} onNewSearch={handleNewSearch} />
          ) : searched ? (
            <EmptyState
              icon={SearchX}
              title="No order with that code"
              description="Check the code from your confirmation email or SMS and try again."
              className="py-16"
            />
          ) : (
            <EmptyState
              icon={PackageSearch}
              title="Enter your order code"
              description="It looks something like AB123456 and is in your order confirmation."
              className="py-16"
            />
          )}
        </div>
      </div>
    </div>
  );
}
