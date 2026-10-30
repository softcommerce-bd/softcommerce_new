"use client";

/**
 * A shop's newest products.
 *
 * Now delegates to the shared ProductCarousel, matching SellerFeaturedProducts
 * above it. The private Swiper setup and DOM-querying progress bar are gone.
 *
 * The cached fetch and endpoint are unchanged.
 */

import ProductCarousel from "@/app/components/layout/ProductCarousel";
import useCachedFetch from "@/app/utils/useCachedFetch";

export default function SellerNewestProducts({ shopId }) {
  const { data: products, loading, error } = useCachedFetch(
    `${process.env.NEXT_PUBLIC_API_URL}/shops/products/new/${shopId}`,
    `seller-newest-products-${shopId}`,
    5 * 60 * 1000,
  );

  if (error) return null;
  if (!loading && (!products || products.length === 0)) return null;

  return (
    <ProductCarousel
      name="seller-newest"
      title="New arrivals"
      products={products || []}
      loading={loading}
      className="px-0!"
    />
  );
}
