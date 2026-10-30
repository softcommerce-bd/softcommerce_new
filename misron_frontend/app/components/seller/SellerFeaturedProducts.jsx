"use client";

/**
 * A shop's featured products.
 *
 * Now delegates to the shared ProductCarousel. That removes this file's own
 * Swiper configuration, arrows, and a progress bar that was wired up by
 * querying the document for `.seller-featured-swiper` inside an effect and
 * mutating an element found by id — reaching outside React to do it.
 *
 * The cached fetch and endpoint are unchanged.
 */

import ProductCarousel from "@/app/components/layout/ProductCarousel";
import useCachedFetch from "@/app/utils/useCachedFetch";

export default function SellerFeaturedProducts({ shopId }) {
  const { data: products, loading, error } = useCachedFetch(
    `${process.env.NEXT_PUBLIC_API_URL}/shops/products/featured/${shopId}`,
    `seller-featured-products-${shopId}`,
    5 * 60 * 1000,
  );

  // A shop with no featured products simply omits the section.
  if (error) return null;
  if (!loading && (!products || products.length === 0)) return null;

  return (
    <ProductCarousel
      name="seller-featured"
      title="Featured"
      products={products || []}
      loading={loading}
      className="px-0!"
    />
  );
}
