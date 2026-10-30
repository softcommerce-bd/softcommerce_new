"use client";

/**
 * Best-selling products, shown as the "Featured Jewelry Collection" section.
 *
 * One row of four framed cards under a pill label, a serif title and a "View
 * All Pieces" link, after the reference design. Anything past the fourth
 * product belongs on the shop page, which the link opens sorted by
 * best-selling. This file keeps the data layer — cache, fetch, retry — and the
 * section chrome; the card itself is FeaturedProductCard.
 *
 * The abort-and-retry logic, the endpoint and the cache key are unchanged. The
 * endpoint still returns thirty-two products; only the first four are drawn.
 */

import { useEffect, useState, useRef, useCallback } from "react";
import Link from "next/link";
import { AlertCircle, ArrowRight, Flame, PackageSearch } from "lucide-react";

import FeaturedProductCard from "../components/layout/FeaturedProductCard";
import { Button, EmptyState, Skeleton } from "@/app/components/ui";

const CACHE_DURATION = 10 * 60 * 1000; // 10 minutes
const CACHE_KEY = "best_selling_products_v3";
/** The reference shows one row of four. */
const FEATURED_COUNT = 4;

function FeaturedSkeletonCard() {
  return (
    <div
      className="overflow-hidden rounded-xl border border-stone-200/90 bg-white sm:rounded-2xl"
      aria-hidden="true"
    >
      <Skeleton className="aspect-square w-full" rounded="sm" />
      <div className="space-y-3 p-3 sm:p-5">
        <Skeleton className="h-3 w-1/3" />
        <Skeleton className="h-4 w-4/5" />
        <Skeleton className="h-5 w-24" />
        <div className="grid grid-cols-2 gap-2">
          <Skeleton className="h-8" />
          <Skeleton className="h-8" />
        </div>
      </div>
    </div>
  );
}

function FeaturedHeader() {
  return (
    <div className="mb-6 flex flex-col justify-between gap-3 sm:mb-8 sm:flex-row sm:items-end">
      <div>
        <div className="mb-1.5 inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-100/80 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wider text-amber-900 sm:mb-2 sm:px-3 sm:text-xs">
          <Flame size={14} className="fill-amber-500 text-amber-600" aria-hidden="true" />
          <span>Top Picks &amp; Best Sellers</span>
        </div>
        <h2 className="type-title-serif text-stone-900">Featured Jewelry Collection</h2>
        <p className="mt-0.5 text-xs font-light text-stone-500 sm:mt-1 sm:text-sm">
          Our most coveted, highest-rated signature handcrafted jewelry
        </p>
      </div>

      <Link
        href="/shop_page?sort=best-selling"
        className="group inline-flex items-center gap-1.5 self-start text-xs font-semibold text-amber-800 transition-colors hover:text-amber-950 sm:self-auto"
      >
        <span>View All Pieces</span>
        <ArrowRight
          size={14}
          className="transition-transform group-hover:translate-x-1"
          aria-hidden="true"
        />
      </Link>
    </div>
  );
}

export default function BestSelling() {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [retryCount, setRetryCount] = useState(0);

  const fetchAttempted = useRef(false);
  const abortControllerRef = useRef(null);
  const isMounted = useRef(true);

  const loadFromCache = useCallback(() => {
    try {
      const cachedData = localStorage.getItem(CACHE_KEY);
      const cachedTime = localStorage.getItem(`${CACHE_KEY}_time`);

      if (cachedData && cachedTime) {
        const timeDiff = Date.now() - parseInt(cachedTime);

        if (timeDiff < CACHE_DURATION) {
          const parsedData = JSON.parse(cachedData);
          if (parsedData && parsedData.length > 0) {
            setProducts(parsedData);
            setLoading(false);
            return true;
          }
        }
      }
      return false;
    } catch (err) {
      console.error("Cache read error:", err);
      return false;
    }
  }, []);

  const saveToCache = useCallback((data) => {
    try {
      localStorage.setItem(CACHE_KEY, JSON.stringify(data));
      localStorage.setItem(`${CACHE_KEY}_time`, Date.now().toString());
    } catch (err) {
      console.error("Cache write error:", err);
    }
  }, []);

  const fetchProducts = useCallback(
    async (retryAttempt = 0) => {
      if (fetchAttempted.current) return;
      fetchAttempted.current = true;

      if (abortControllerRef.current) abortControllerRef.current.abort();

      const controller = new AbortController();
      abortControllerRef.current = controller;

      try {
        const timeoutId = setTimeout(() => {
          if (controller && !controller.signal.aborted) controller.abort();
        }, 10000);

        const response = await fetch(
          `${process.env.NEXT_PUBLIC_API_URL}/products/best-selling`,
          {
            signal: controller.signal,
            headers: {
              Accept: "application/json",
              "Content-Type": "application/json",
            },
          },
        );

        clearTimeout(timeoutId);

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const json = await response.json();

        if (isMounted.current) {
          if (json.success) {
            const productData = json.data || [];
            setProducts(productData);
            setError(null);
            saveToCache(productData);
          } else {
            throw new Error(json.message || "Failed to fetch data");
          }
        }
      } catch (err) {
        if (isMounted.current) {
          // Timeouts and transient network failures retry with a growing
          // delay; an HTTP status is a real answer and is not retried.
          if (err.name === "AbortError") {
            if (retryAttempt < 2) {
              setTimeout(
                () => {
                  if (isMounted.current) fetchProducts(retryAttempt + 1);
                },
                1000 * (retryAttempt + 1),
              );
            } else {
              setError("Request timeout - please check your connection");
            }
          } else {
            setError(err.message);
            if (retryAttempt < 2 && !err.message.includes("HTTP")) {
              setTimeout(() => {
                if (isMounted.current) fetchProducts(retryAttempt + 1);
              }, 2000);
            }
          }
        }
      } finally {
        if (isMounted.current) {
          setLoading(false);
          fetchAttempted.current = false;
          abortControllerRef.current = null;
        }
      }
    },
    [saveToCache],
  );

  const handleRetry = useCallback(() => {
    setLoading(true);
    setError(null);
    fetchAttempted.current = false;
    setRetryCount((prev) => prev + 1);
    fetchProducts(0);
  }, [fetchProducts]);

  useEffect(() => {
    isMounted.current = true;

    // Paint from cache for an instant rail, then refresh in the background.
    loadFromCache();
    fetchProducts(0);

    return () => {
      isMounted.current = false;
      if (abortControllerRef.current) abortControllerRef.current.abort();
    };
  }, [loadFromCache, fetchProducts, retryCount]);

  if (error && products.length === 0) {
    return (
      <section className="container-page section">
        <div className="flex flex-col items-center rounded-xl bg-canvas-sunken px-6 py-14 text-center">
          <AlertCircle size={26} className="mb-4 text-ink-tertiary" strokeWidth={1.5} />
          <h3 className="type-title-3 text-ink">Couldn&apos;t load best sellers</h3>
          <p className="type-footnote mt-2 text-ink-tertiary">{error}</p>
          <Button variant="secondary" className="mt-6" onClick={handleRetry}>
            Try again
          </Button>
        </div>
      </section>
    );
  }

  const gridClass = "grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-4";

  // Loading with nothing cached: show the block's shape, not a spinner, so the
  // page does not jump once the products land.
  if (loading && products.length === 0) {
    return (
      <section className="container-page section">
        <FeaturedHeader />
        <div className={gridClass}>
          {Array.from({ length: FEATURED_COUNT }).map((_, i) => (
            <FeaturedSkeletonCard key={i} />
          ))}
        </div>
      </section>
    );
  }

  if (products.length === 0) {
    return (
      <section className="container-page section">
        <FeaturedHeader />
        <EmptyState
          icon={PackageSearch}
          title="No best sellers yet"
          description="Check back soon — this list updates as orders come in."
        />
      </section>
    );
  }

  return (
    <section className="container-page section">
      <FeaturedHeader />
      <div className={gridClass}>
        {products.slice(0, FEATURED_COUNT).map((item, index) => (
          <FeaturedProductCard
            key={item.id ?? index}
            item={item}
            // The whole row is above the fold on a wide screen only; the rest
            // load lazily so the home page's LCP stays with the hero.
            priority={index < 2}
          />
        ))}
      </div>
    </section>
  );
}
