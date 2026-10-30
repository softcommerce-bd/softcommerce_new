"use client";

import { useEffect, useRef } from "react";
import { trackViewContent, contentId } from "@/lib/pixel/meta";

/**
 * Fires ViewContent once per product page.
 *
 * The id sent here is the product's first stock row id, matching the `<g:id>`
 * the catalogue feed publishes. A shopper has not chosen a size at the moment
 * the page loads, so no single variant is "the" one being viewed; the first row
 * is used because every variant of a garment shares an item_group_id in the
 * feed, and Meta resolves the group from any member.
 *
 * AddToCart, which happens after a size is chosen, sends that exact row id.
 */
export default function ProductViewTracker({ product }) {
  const trackedId = useRef(null);

  useEffect(() => {
    if (!product?.id) return;

    // Guarded per product id rather than with a plain boolean so a
    // client-side navigation to another product still tracks.
    if (trackedId.current === product.id) return;
    trackedId.current = product.id;

    const variants = product.variant_stocks
      ? Object.values(product.variant_stocks)
      : [];
    const firstVariant = variants.find((v) => v?.stock_id) || null;

    trackViewContent({
      id: contentId(product, firstVariant),
      name: product.name,
      price: product.calculable_price,
      category: product.category_name || "",
    });
  }, [product]);

  return null;
}
