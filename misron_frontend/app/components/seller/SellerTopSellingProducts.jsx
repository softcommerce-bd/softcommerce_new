"use client";

/**
 * A shop's best-selling products. Presentation comes from the shared
 * SellerProductGrid; the fetch is unchanged.
 */

import { useState, useEffect } from "react";
import SellerProductGrid from "./SellerProductGrid";

export default function SellerTopSellingProducts({ shopId }) {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchTopSellingProducts = async () => {
      if (!shopId) return;

      try {
        const res = await fetch(
          `${process.env.NEXT_PUBLIC_API_URL}/shops/products/top/${shopId}`,
        );
        const response = await res.json();

        if (response.success) setProducts(response.data);
      } catch (error) {
        console.error("Error fetching top selling products:", error);
      } finally {
        setLoading(false);
      }
    };

    fetchTopSellingProducts();
  }, [shopId]);

  return (
    <SellerProductGrid
      title="Top selling"
      products={products}
      loading={loading}
      emptyDescription="No sales data for this shop yet."
    />
  );
}
