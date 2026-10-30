"use client";

/**
 * Product grid used by every section of a seller's shop.
 *
 * SellerAllProducts, SellerTopSellingProducts, SellerFeaturedProducts and
 * SellerNewestProducts each carried their own copy of this grid, its spinner
 * and its "No products found" line, at four different column counts. They now
 * share one, matching the shop page's grid.
 */

import { PackageSearch } from "lucide-react";

import ProductCard1 from "../layout/ProductCard1";
import { SectionHeader, EmptyState, Button, SkeletonProductCard } from "../ui";

export default function SellerProductGrid({
  title,
  products = [],
  loading = false,
  emptyDescription,
  onLoadMore,
  hasMore = false,
  loadingMore = false,
}) {
  if (loading) {
    return (
      <section className="py-8">
        <SectionHeader title={title} />
        <div className="grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 xl:grid-cols-4">
          {Array.from({ length: 8 }).map((_, i) => (
            <SkeletonProductCard key={i} />
          ))}
        </div>
      </section>
    );
  }

  return (
    <section className="py-8">
      <SectionHeader title={title} />

      {products.length === 0 ? (
        <EmptyState
          icon={PackageSearch}
          title="Nothing here yet"
          description={emptyDescription}
        />
      ) : (
        <>
          <div className="grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 xl:grid-cols-4">
            {products.map((product, index) => (
              <ProductCard1 key={product.id} item={product} priority={index < 6} />
            ))}
          </div>

          {hasMore && onLoadMore && (
            <div className="mt-12 flex justify-center">
              <Button
                variant="secondary"
                size="lg"
                onClick={onLoadMore}
                loading={loadingMore}
              >
                {loadingMore ? "Loading" : "Load more"}
              </Button>
            </div>
          )}
        </>
      )}
    </section>
  );
}
