"use client";

/**
 * A shop's full catalogue, paged with a load-more button.
 *
 * Presentation is delegated to the shared SellerProductGrid; this file is now
 * only the fetch and paging.
 *
 * NOTE: `hasMore` is inferred from a full page being returned (`length === 6`,
 * the endpoint's page size). That is unchanged, but the endpoint returning a
 * total or a `has_more` flag would make it exact — flagged as a backend
 * improvement, not changed here.
 */

import { useState, useEffect } from "react";
import SellerProductGrid from "./SellerProductGrid";

export default function SellerAllProducts({ shopId }) {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);

  useEffect(() => {
    const fetchAllProducts = async () => {
      if (!shopId) return;

      try {
        const res = await fetch(
          `${process.env.NEXT_PUBLIC_API_URL}/shops/products/all/${shopId}?page=${page}`,
        );
        const response = await res.json();

        if (response.success) {
          setProducts((prev) =>
            page === 1 ? response.data : [...prev, ...response.data],
          );
          setHasMore(response.data.length === 6);
        }
      } catch (error) {
        console.error("Error fetching all products:", error);
      } finally {
        setLoading(false);
      }
    };

    fetchAllProducts();
  }, [shopId, page]);

  return (
    <SellerProductGrid
      title="All products"
      products={products}
      loading={loading && page === 1}
      loadingMore={loading && page > 1}
      hasMore={hasMore}
      onLoadMore={() => {
        setLoading(true);
        setPage((prev) => prev + 1);
      }}
      emptyDescription="This shop hasn't listed any products yet."
    />
  );
}
