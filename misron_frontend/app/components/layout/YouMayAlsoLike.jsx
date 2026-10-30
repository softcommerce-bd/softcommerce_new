"use client";

/**
 * Related products rail on the product page.
 *
 * Now delegates to the shared ProductCarousel, which brings its own header,
 * arrows and skeleton. That removes this file's private skeleton components,
 * its inline "See All" arrow glyph, and a progress bar that was driven by
 * querying the DOM for `.you-may-also-like-swiper` and mutating an element's
 * style by id.
 *
 * The endpoint and the filter that drops the current product are unchanged.
 */

import { useEffect, useState } from "react";
import ProductCarousel from "./ProductCarousel";

export default function YouMayAlsoLike({ currentProductId, category }) {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchRelatedProducts = async () => {
      try {
        const response = await fetch(
          `${process.env.NEXT_PUBLIC_API_URL}/products/frequently-bought/${currentProductId}`,
        );
        const result = await response.json();

        if (result.success) {
          const filtered = result.data
            .filter((product) => product.id !== currentProductId)
            .slice(0, 8);
          setProducts(filtered);
        }
      } catch (error) {
        console.error("Error fetching related products:", error);
        setProducts([]);
      } finally {
        setLoading(false);
      }
    };

    fetchRelatedProducts();
  }, [currentProductId, category]);

  // A product page with no related items simply ends after the description.
  if (!loading && products.length === 0) return null;

  return (
    <ProductCarousel
      name="also-like"
      title="You may also like"
      products={products}
      loading={loading}
      href="/shop_page"
      linkLabel="See all"
      className="px-0!"
    />
  );
}
