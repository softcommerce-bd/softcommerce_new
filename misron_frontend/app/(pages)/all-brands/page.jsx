"use client";

/**
 * All brands.
 *
 * The grid was a fixed `grid-cols-6` at every width, so on a phone each tile
 * was about 50px wide. It is now responsive, two across on mobile up to six on
 * a wide screen.
 *
 * Loading shows tile-shaped skeletons instead of a centred spinner, and an
 * error renders a proper state rather than red text on the page.
 *
 * Fetching and caching are unchanged.
 */

import { AlertCircle, Tag } from "lucide-react";

import Breadcrumb from "@/app/components/layout/Breadcrumb";
import BrandCard from "@/app/components/layout/BrandCard";
import useCachedFetch from "@/app/utils/useCachedFetch";
import { Button, EmptyState, Skeleton } from "@/app/components/ui";

export default function AllBrands() {
  const { data: brands, loading, error } = useCachedFetch(
    `${process.env.NEXT_PUBLIC_API_URL}/brands`,
    "brands_cache",
    5 * 60 * 1000,
  );

  return (
    <div className="container-page py-8 md:py-12">
      <Breadcrumb items={[{ name: "Home", href: "/" }, { name: "Brands", href: "#" }]} />

      <header className="mb-10 mt-8">
        <h1 className="type-title-1 text-ink">Brands</h1>
        <p className="type-callout mt-2 text-ink-tertiary">
          Every label we carry, in one place.
        </p>
      </header>

      {loading ? (
        <div className="grid grid-cols-2 gap-5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
          {Array.from({ length: 12 }).map((_, i) => (
            <div key={i}>
              <Skeleton className="aspect-square w-full" rounded="xl" />
              <Skeleton className="mx-auto mt-3 h-3 w-16" />
            </div>
          ))}
        </div>
      ) : error ? (
        <div className="flex flex-col items-center rounded-xl bg-canvas-sunken px-6 py-16 text-center">
          <AlertCircle size={26} className="mb-4 text-ink-tertiary" strokeWidth={1.5} />
          <h2 className="type-title-3 text-ink">Couldn&apos;t load brands</h2>
          <p className="type-footnote mt-2 text-ink-tertiary">{error}</p>
          <Button
            variant="secondary"
            className="mt-6"
            onClick={() => window.location.reload()}
          >
            Try again
          </Button>
        </div>
      ) : !brands || brands.length === 0 ? (
        <EmptyState
          icon={Tag}
          title="No brands yet"
          description="Brands will appear here once they're added."
          action={{ label: "Browse products", href: "/shop_page" }}
        />
      ) : (
        <div className="grid grid-cols-2 gap-5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
          {brands.map((item) => (
            <BrandCard key={item.id} item={item} />
          ))}
        </div>
      )}
    </div>
  );
}
