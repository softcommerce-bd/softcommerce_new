"use client";

/**
 * Items in a tracked order.
 *
 * Restyled to match the cart and checkout rows: image on the sunken canvas,
 * name, then price. The hardcoded "Qty: 1" has been removed — it was printed
 * for every line regardless of the actual quantity, which the tracking
 * endpoint does not return.
 *
 * BACKEND NOTE: per-line quantity is not in the `track-order` response, so it
 * cannot be shown here without an API change.
 */

import Image from "next/image";
import Link from "next/link";

import { tkText } from "@/utils/currency";

export default function OrderItems({ orderDetails }) {
  if (!orderDetails || orderDetails.length === 0) return null;

  return (
    <section className="card overflow-hidden">
      <header className="border-b border-hairline px-5 py-4">
        <h2 className="type-headline text-ink">Items</h2>
      </header>

      <ul className="divide-y divide-hairline">
        {orderDetails.map((product, index) => (
          <li key={index} className="flex gap-3 px-5 py-4">
            <Link
              href={`/product/${product.slug}`}
              className="relative h-16 w-16 shrink-0 overflow-hidden rounded-md bg-canvas-sunken"
            >
              {/* No thumbnail leaves the tinted square on its own. */}
              {product.thumbnail_image && (
                <Image
                  src={product.thumbnail_image}
                  alt=""
                  fill
                  className="object-cover"
                  sizes="64px"
                />
              )}
            </Link>

            <div className="flex min-w-0 flex-1 items-start justify-between gap-3">
              <Link href={`/product/${product.slug}`} className="min-w-0">
                <p className="type-subhead line-clamp-2 font-medium text-ink hover:text-ink-secondary">
                  {product.name}
                </p>
              </Link>

              <div className="shrink-0 text-right">
                <p className="type-subhead tabular font-semibold text-ink">
                  {tkText(product.main_price)}
                </p>

                {product.stroked_price &&
                  product.stroked_price !== product.main_price && (
                    <p className="type-caption tabular mt-0.5 text-ink-tertiary line-through">
                      {tkText(product.stroked_price)}
                    </p>
                  )}
              </div>
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}
