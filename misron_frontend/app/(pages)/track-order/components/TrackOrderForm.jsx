"use client";

/**
 * Order lookup form.
 *
 * The input previously carried `bg-main` — the dark brand fill — with no text
 * colour set, so a shopper typed near-black text onto a near-black field. It
 * is now a normal field on the light surface, with the search icon inside it
 * and the action button beside it rather than floating on top of the input.
 *
 * The lookup request and its callbacks are unchanged.
 */

import { useState } from "react";
import { toast } from "react-hot-toast";
import { Search } from "lucide-react";

import { Button } from "@/app/components/ui";

export default function TrackOrderForm({ onOrderFound, onLoading, onSearchStart }) {
  const [orderCode, setOrderCode] = useState("");
  const [loading, setLoading] = useState(false);

  const handleSearchOrder = async (e) => {
    e.preventDefault();

    if (!orderCode.trim()) {
      toast.error("Please enter an order code");
      return;
    }

    setLoading(true);
    onLoading(true);
    onSearchStart();

    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/track-order/${orderCode}`,
        { headers: { Accept: "application/json" } },
      );

      const data = await response.json();

      if (data.success && data.data) {
        onOrderFound(data.data);
        toast.success("Order found");
      } else {
        toast.error(data.message || "Order not found");
        onOrderFound(null);
      }
    } catch (error) {
      console.error("Error tracking order:", error);
      toast.error("Failed to track order. Please try again.");
      onOrderFound(null);
    } finally {
      setLoading(false);
      onLoading(false);
    }
  };

  return (
    <form onSubmit={handleSearchOrder} className="flex flex-col gap-2.5 sm:flex-row">
      <div className="relative flex-1">
        <label htmlFor="order-code" className="sr-only-focusable">
          Order code
        </label>
        <Search
          size={17}
          className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-tertiary"
          aria-hidden="true"
        />
        <input
          id="order-code"
          type="text"
          value={orderCode}
          onChange={(e) => setOrderCode(e.target.value)}
          placeholder="Order code"
          className="field h-13 pl-11"
          disabled={loading}
        />
      </div>

      <Button type="submit" size="lg" className="shrink-0" loading={loading}>
        {loading ? "Searching" : "Track order"}
      </Button>
    </form>
  );
}
