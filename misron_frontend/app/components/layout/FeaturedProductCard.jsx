"use client";

/**
 * Featured product card, for the home page's "Featured Jewelry Collection"
 * section.
 *
 * Framed card, unlike the frameless ProductCard1 used in the grids: a square
 * photograph, a "Featured" badge and a discount badge over it, a serif product
 * name, and a footer with the price and two actions.
 *
 * The payload is the list endpoint's, so there is no short description to show.
 * "Cart" and the bag icon on the image add the product's default variant
 * straight to the cart (see useQuickAdd); "Quick View" on the image opens the
 * cart modal, where the shopper picks size and colour. "Order" goes to the
 * product page: a one-click order from a grid could not ask for a variant.
 */

import Image from "next/image";
import Link from "next/link";
import { memo, useState, useMemo } from "react";
import { Eye, ImageOff, ShoppingBag, Sparkles, Star, Zap } from "lucide-react";

import { useQuickAdd } from "@/store/hooks/useQuickAdd";
import { toTk, tkText } from "@/utils/currency";
import WishlistButton from "../WishlistButton";

const FeaturedProductCard = memo(({ item, priority = false, featured = true }) => {
  const [imageError, setImageError] = useState(false);
  const { quickAdd, adding, openModal } = useQuickAdd(item);

  const { productId, discount, outOfStock, mainPrice, strokedPrice, rating } =
    useMemo(() => {
      let disc = null;
      if (item.discount && item.discount !== "-0%") disc = item.discount;
      else if (item.has_discount && item.discount_percent)
        disc = `-${item.discount_percent}%`;

      const stock = item.recent_stock ?? item.stock ?? item.quantity ?? 0;

      let main = "৳ 0";
      if (item.main_price) main = tkText(item.main_price);
      else if (item.price) main = toTk(item.price);
      else if (item.unit_price) main = toTk(item.unit_price);

      let stroked = null;
      if (item.stroked_price) stroked = tkText(item.stroked_price);
      else if (item.oldPrice) stroked = toTk(item.oldPrice);

      return {
        productId: item.id || item.product_id,
        discount: disc,
        outOfStock: Number(stock) <= 0,
        mainPrice: main,
        // The API sends the same string for both prices when nothing is
        // discounted; a struck-through copy of the price is only noise.
        strokedPrice: stroked && stroked !== main ? stroked : null,
        rating: Number(item.rating) || 0,
      };
    }, [item]);

  if (!item || (!item.id && !item.product_id)) return null;

  const productName = item.name || item.title || "Product";
  const category = item.category_name || item.category || "";
  const productHref = `/product/${item.slug || item.id}`;

  return (
    <article className="group relative flex h-full flex-col justify-between overflow-hidden rounded-xl border border-stone-200/90 bg-white shadow-xs transition-all duration-300 hover:shadow-xl sm:rounded-2xl">
      {/* ── Image ──────────────────────────────────────────────────────── */}
      <div className="relative aspect-square overflow-hidden bg-stone-100">
        <Link
          href={productHref}
          prefetch={false}
          className="block h-full w-full"
          tabIndex={-1}
          aria-hidden="true"
        >
          {!imageError && item.thumbnail_image ? (
            <Image
              src={item.thumbnail_image}
              alt={productName}
              fill
              className="object-cover object-center transition-transform duration-500 group-hover:scale-[1.08]"
              sizes="(max-width: 1024px) 50vw, 25vw"
              onError={() => setImageError(true)}
              loading={priority ? "eager" : "lazy"}
              priority={priority}
              quality={78}
            />
          ) : (
            <div className="flex h-full w-full items-center justify-center">
              <ImageOff size={28} className="text-stone-300" strokeWidth={1.5} />
            </div>
          )}
        </Link>

        {outOfStock && (
          <div className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-white/55">
            <span className="type-eyebrow bg-white px-3 py-1.5 text-stone-900 shadow-e1">
              Sold out
            </span>
          </div>
        )}

        <div className="pointer-events-none absolute left-2 top-2 z-20 flex flex-col gap-1 sm:left-3 sm:top-3">
          {featured && (
            <span className="flex items-center gap-1 rounded-sm bg-linear-to-r from-amber-600 to-amber-700 px-1.5 py-0.5 text-[9px] font-bold uppercase leading-tight tracking-wide text-white shadow-xs sm:px-2.5 sm:py-1 sm:text-[10px]">
              <Sparkles
                className="h-2.5 w-2.5 text-amber-200 sm:h-3 sm:w-3"
                aria-hidden="true"
              />
              Featured
            </span>
          )}
          {!outOfStock && discount && (
            <span className="w-fit rounded-sm bg-rose-600 px-1.5 py-0.5 text-[9px] font-bold leading-tight text-white shadow-xs sm:px-2 sm:text-[10px]">
              {discount} Off
            </span>
          )}
        </div>

        <div
          className="absolute right-2 top-2 z-20 sm:right-3 sm:top-3"
          onClick={(e) => e.preventDefault()}
        >
          <WishlistButton
            productId={productId}
            className="h-7! w-7! bg-white/90 shadow-md hover:bg-white sm:h-8! sm:w-8!"
            iconSize={16}
          />
        </div>

        {/* Hover shelf: Quick View (opens the cart modal) and a bag button
            (adds the default variant straight to the cart). Hidden on touch, where there is no hover;
            the footer's Cart and Order buttons cover those devices. */}
        {!outOfStock && (
          <div className="pointer-events-none absolute inset-x-0 bottom-0 z-20 flex translate-y-2 gap-2 p-2 opacity-0 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100 focus-within:translate-y-0 focus-within:opacity-100 sm:p-3 [@media(hover:none)]:hidden">
            <button
              type="button"
              onClick={openModal}
              className={`pointer-events-auto flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg py-2.5 text-xs font-semibold shadow-md transition-colors sm:text-sm ${
                featured
                  ? "bg-white/95 text-stone-900 backdrop-blur-sm hover:bg-white"
                  : "bg-black text-white hover:bg-stone-800"
              }`}
            >
              <Eye className="h-4 w-4" aria-hidden="true" />
              <span>Quick View</span>
            </button>
            {featured && (
            <button
              type="button"
              onClick={quickAdd}
              disabled={adding}
              aria-label="Add to cart"
              className="pointer-events-auto flex cursor-pointer items-center justify-center rounded-lg bg-stone-900 px-3.5 text-white shadow-md transition-colors hover:bg-black disabled:cursor-wait disabled:opacity-60"
            >
              <ShoppingBag className="h-4 w-4" aria-hidden="true" />
            </button>
            )}
          </div>
        )}
      </div>

      {/* ── Detail ─────────────────────────────────────────────────────── */}
      <div className="flex flex-1 flex-col p-3 sm:p-4">
        <div>
          <div className="mb-1 flex items-center justify-between gap-2 text-[11px] text-stone-400 sm:text-xs">
            <span className="truncate">{category}</span>
            {!featured ? (
              <span
                className={`shrink-0 font-medium ${outOfStock ? "text-rose-600" : "text-teal-700"}`}
              >
                {outOfStock ? "Out of Stock" : "In Stock"}
              </span>
            ) : rating > 0 && (
              <span className="flex shrink-0 items-center gap-0.5 text-amber-500 sm:gap-1">
                <Star
                  className="h-3 w-3 fill-current sm:h-3.5 sm:w-3.5"
                  aria-hidden="true"
                />
                <strong className="font-semibold text-stone-700">
                  {rating.toFixed(1)}
                </strong>
              </span>
            )}
          </div>

          <h3
            title={productName}
            className="type-card-serif line-clamp-1 text-base text-stone-900 sm:text-lg"
          >
            {/* The stretched link makes the whole card clickable while the
                buttons below stay independently clickable (they sit at z-20). */}
            <Link
              href={productHref}
              prefetch={false}
              className="transition-colors after:absolute after:inset-0 after:z-10 after:content-[''] hover:text-amber-800"
            >
              {productName}
            </Link>
          </h3>
        </div>

        <div className="mt-1.5 space-y-2 sm:mt-2">

          <div className="flex items-baseline gap-1 sm:gap-2">
            <span className="tabular text-sm font-bold text-stone-900 sm:text-lg">
              {mainPrice}
            </span>
            {strokedPrice && (
              <span className="tabular text-[10px] text-stone-400 line-through sm:text-xs">
                {strokedPrice}
              </span>
            )}
          </div>

          <div className="relative z-20 grid grid-cols-2 gap-1.5 sm:gap-2">
            <button
              type="button"
              onClick={quickAdd}
              disabled={outOfStock || adding}
              className="flex cursor-pointer items-center justify-center gap-1 rounded-lg border border-stone-300 px-1 py-1.5 text-[11px] font-semibold text-stone-800 transition-colors hover:border-stone-800 hover:text-black disabled:cursor-not-allowed disabled:opacity-40 sm:px-2.5 sm:py-2 sm:text-xs"
            >
              <ShoppingBag className="h-3 w-3" aria-hidden="true" />
              {/* Size sits on the span: the unlayered `button { font-size: inherit }`
                  in globals.css beats a text-* utility on the button itself. */}
              <span className="text-[11px] sm:text-xs">Cart</span>
            </button>

            {outOfStock ? (
              <span
                aria-disabled="true"
                className="flex cursor-not-allowed items-center justify-center gap-1 rounded-lg bg-[#A27339] px-1 py-1.5 text-[11px] font-semibold text-white opacity-40 sm:px-2.5 sm:py-2 sm:text-xs"
              >
                <Zap className="h-3 w-3 fill-current text-amber-200" aria-hidden="true" />
                <span>Order</span>
              </span>
            ) : (
              <Link
                href={productHref}
                prefetch={false}
                className="flex items-center justify-center gap-1 rounded-lg bg-[#A27339] px-1 py-1.5 text-[11px] font-semibold text-white shadow-xs transition-colors hover:bg-[#8B5F2A] sm:px-2.5 sm:py-2 sm:text-xs"
              >
                <Zap className="h-3 w-3 fill-current text-amber-200" aria-hidden="true" />
                <span>Order</span>
              </Link>
            )}
          </div>
        </div>
      </div>
    </article>
  );
});

FeaturedProductCard.displayName = "FeaturedProductCard";

export default FeaturedProductCard;
