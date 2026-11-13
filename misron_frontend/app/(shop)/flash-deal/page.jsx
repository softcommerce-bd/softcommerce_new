"use client";

/**
 * Flash deals: every deal that is live right now, each with its countdown and products.
 * Data comes from the admin's Flash Deals (`/flash-deals`, `/flash-deal-products/{slug}`).
 */

import { useEffect, useState } from "react";
import { Zap } from "lucide-react";

import FeaturedProductCard from "@/app/components/layout/FeaturedProductCard";
import { EmptyState, Skeleton } from "@/app/components/ui";

const API = process.env.NEXT_PUBLIC_API_URL || "";

/** Time left until `endSeconds` (unix seconds), as { d, h, m, s } or null once over. */
function remaining(endSeconds, now) {
  const left = Math.floor(endSeconds - now / 1000);
  if (!Number.isFinite(left) || left <= 0) return null;
  return {
    d: Math.floor(left / 86400),
    h: Math.floor((left % 86400) / 3600),
    m: Math.floor((left % 3600) / 60),
    s: left % 60,
  };
}

function Countdown({ end }) {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(timer);
  }, []);
  const t = remaining(end, now);
  if (!t) return <span className="text-xs font-semibold text-stone-500">Ended</span>;
  const cell = (value, label) => (
    <span className="flex min-w-10 flex-col items-center rounded-lg bg-stone-900 px-2 py-1 text-white">
      <span className="text-sm font-bold tabular-nums leading-none">{String(value).padStart(2, "0")}</span>
      <span className="mt-0.5 text-[9px] uppercase tracking-wide text-stone-300">{label}</span>
    </span>
  );
  return (
    <div className="flex items-center gap-1.5" aria-label="Time left">
      {t.d > 0 && cell(t.d, "Day")}
      {cell(t.h, "Hr")}
      {cell(t.m, "Min")}
      {cell(t.s, "Sec")}
    </div>
  );
}

function Deal({ deal }) {
  const [products, setProducts] = useState(null);

  useEffect(() => {
    let alive = true;
    fetch(`${API}/flash-deal-products/${deal.slug}`)
      .then((res) => res.json())
      .then((json) => alive && setProducts(Array.isArray(json?.data) ? json.data : []))
      .catch(() => alive && setProducts([]));
    return () => {
      alive = false;
    };
  }, [deal.slug]);

  return (
    <section className="mb-10">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 className="flex items-center gap-2 text-lg font-bold text-stone-900 sm:text-xl">
          <Zap className="h-5 w-5 fill-current text-brand" aria-hidden="true" />
          {deal.title}
        </h2>
        <Countdown end={deal.date} />
      </div>

      {products === null ? (
        <div className="grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-4">
          {[0, 1, 2, 3].map((i) => (
            <Skeleton key={i} className="aspect-[3/4] w-full" rounded="lg" />
          ))}
        </div>
      ) : products.length === 0 ? (
        <p className="text-sm text-stone-500">No products in this deal yet.</p>
      ) : (
        <div className="grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-4">
          {products.map((product) => (
            <FeaturedProductCard key={product.id} item={product} featured={false} />
          ))}
        </div>
      )}
    </section>
  );
}

export default function FlashDealPage() {
  const [deals, setDeals] = useState(null);

  useEffect(() => {
    let alive = true;
    fetch(`${API}/flash-deals`)
      .then((res) => res.json())
      .then((json) => alive && setDeals(Array.isArray(json?.data) ? json.data : []))
      .catch(() => alive && setDeals([]));
    return () => {
      alive = false;
    };
  }, []);

  return (
    <div className="container-page section">
      <h1 className="mb-6 text-2xl font-bold text-stone-900">Flash Deals</h1>

      {deals === null ? (
        <Skeleton className="h-64 w-full" rounded="lg" />
      ) : deals.length === 0 ? (
        <EmptyState icon={Zap} title="No flash deals right now" description="Come back soon: new deals start all the time." />
      ) : (
        deals.map((deal) => <Deal key={deal.id} deal={deal} />)
      )}
    </div>
  );
}
