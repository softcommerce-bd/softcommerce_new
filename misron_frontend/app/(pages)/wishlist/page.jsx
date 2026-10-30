// app/wishlist/page.jsx
"use client";

/**
 * Saved items.
 *
 * Rebuilt on the design system. The previous page rendered its items twice —
 * a five-column `<table>` for desktop and a separate stack of cards for
 * mobile, roughly 200 duplicated lines. One responsive row now serves both.
 *
 * Ratings use lucide stars rather than a hand-rolled loop of coloured "★"
 * characters, and the destructive actions keep their confirm dialogs.
 *
 * Fetching, selection, removal and the add-to-cart handoff are unchanged.
 */

import Image from "next/image";
import Link from "next/link";
import { useState, useEffect, useCallback, useMemo } from "react";
import { useRouter } from "next/navigation";
import { Heart, Star, Trash2, ShoppingBag, Check } from "lucide-react";
import toast from "react-hot-toast";

import Breadcrumb from "@/app/components/layout/Breadcrumb";
import { useAppSelector, useAppDispatch } from "@/store/hooks";
import { fetchWishlist, removeFromWishlist } from "@/store/slices/wishlistSlice";
import { openCartModal } from "@/store/slices/cartModalSlice";
import { Button, EmptyState, Skeleton } from "@/app/components/ui";
import { tkText } from "@/utils/currency";

/** Square checkbox, matching the cart and shop filters. */
function SelectBox({ checked, onChange, label }) {
  return (
    <span className="relative flex h-[18px] w-[18px] shrink-0 items-center justify-center">
      <input
        type="checkbox"
        checked={checked}
        onChange={onChange}
        aria-label={label}
        className="peer absolute inset-0 cursor-pointer opacity-0"
      />
      <span
        className="flex h-[18px] w-[18px] items-center justify-center rounded-sm border border-hairline-strong bg-surface transition-all duration-fast peer-checked:border-ink peer-checked:bg-ink peer-focus-visible:ring-2 peer-focus-visible:ring-brand peer-focus-visible:ring-offset-2"
        aria-hidden="true"
      >
        {checked && <Check size={12} strokeWidth={3} className="text-ink-inverse" />}
      </span>
    </span>
  );
}

/** Five stars, filled to the rounded rating. */
function Rating({ value = 0, count }) {
  const rounded = Math.round(value);

  return (
    <span
      className="flex items-center gap-1"
      aria-label={`Rated ${value} out of 5`}
    >
      <span className="flex gap-0.5" aria-hidden="true">
        {[0, 1, 2, 3, 4].map((i) => (
          <Star
            key={i}
            size={12}
            className={i < rounded ? "text-ink" : "text-ink-quaternary"}
            fill={i < rounded ? "currentColor" : "none"}
            strokeWidth={1.75}
          />
        ))}
      </span>
      {count != null && (
        <span className="type-caption tabular text-ink-tertiary">({count})</span>
      )}
    </span>
  );
}

export default function Wishlist() {
  const router = useRouter();
  const dispatch = useAppDispatch();
  const [selectedItems, setSelectedItems] = useState([]);
  const [selectAll, setSelectAll] = useState(false);

  const { items, loading } = useAppSelector((state) => state.wishlist);
  const { isAuthenticated } = useAppSelector((state) => state.auth);

  // The API occasionally returns entries whose product has been deleted.
  const validItems = useMemo(
    () => items.filter((item) => item && item.product && item.product.id),
    [items],
  );

  useEffect(() => {
    if (!isAuthenticated) {
      toast.error("Please login to view your wishlist");
      router.push("/");
      return;
    }
    dispatch(fetchWishlist());
  }, [dispatch, isAuthenticated, router]);

  // Drop selections whose product has since left the list.
  useEffect(() => {
    const validProductIds = validItems.map((item) => item.product.id);

    setSelectedItems((prev) => {
      const filtered = prev.filter((id) => validProductIds.includes(id));
      return JSON.stringify(filtered) !== JSON.stringify(prev) ? filtered : prev;
    });
  }, [validItems]);

  useEffect(() => {
    setSelectAll(validItems.length > 0 && selectedItems.length === validItems.length);
  }, [selectedItems, validItems.length]);

  const handleSelectAll = useCallback(() => {
    setSelectedItems(selectAll ? [] : validItems.map((item) => item.product.id));
  }, [selectAll, validItems]);

  const handleSelectItem = useCallback((productId) => {
    setSelectedItems((prev) =>
      prev.includes(productId)
        ? prev.filter((id) => id !== productId)
        : [...prev, productId],
    );
  }, []);

  const handleRemoveItem = useCallback(
    async (productId, e) => {
      e.preventDefault();
      e.stopPropagation();

      setSelectedItems((prev) => prev.filter((id) => id !== productId));
      await dispatch(removeFromWishlist(productId));
    },
    [dispatch],
  );

  const handleRemoveSelected = useCallback(async () => {
    if (selectedItems.length === 0) {
      toast.error("No items selected");
      return;
    }

    if (window.confirm(`Remove ${selectedItems.length} item(s) from wishlist?`)) {
      const itemsToRemove = [...selectedItems];

      setSelectedItems([]);
      setSelectAll(false);

      for (const productId of itemsToRemove) {
        await dispatch(removeFromWishlist(productId));
      }
    }
  }, [dispatch, selectedItems]);

  const handleAddToCart = useCallback(
    (item, e) => {
      e.preventDefault();
      e.stopPropagation();

      dispatch(
        openCartModal({
          productId: item.product.id,
          productSlug: item.product.slug,
          product: item.product,
        }),
      );
    },
    [dispatch],
  );

  if (loading) {
    return (
      <div className="container-page py-8 md:py-12">
        <Breadcrumb
          items={[{ name: "Home", href: "/" }, { name: "Wishlist", href: "#" }]}
        />
        <Skeleton className="mb-8 mt-8 h-8 w-48" />
        <div className="space-y-4">
          {[0, 1, 2].map((i) => (
            <div key={i} className="flex gap-4">
              <Skeleton className="h-24 w-20" />
              <div className="flex-1 space-y-2 py-1">
                <Skeleton className="h-3.5 w-3/5" />
                <Skeleton className="h-3 w-1/3" />
                <Skeleton className="h-8 w-32" />
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="container-page py-8 md:py-12">
      <Breadcrumb
        items={[{ name: "Home", href: "/" }, { name: "Wishlist", href: "#" }]}
      />

      <header className="mb-8 mt-8">
        <h1 className="type-title-1 text-ink">Wishlist</h1>
        <p className="type-callout tabular mt-2 text-ink-tertiary">
          {validItems.length} saved {validItems.length === 1 ? "item" : "items"}
        </p>
      </header>

      {validItems.length === 0 ? (
        <EmptyState
          icon={Heart}
          title="Nothing saved yet"
          description="Tap the heart on any product to keep it here for later."
          action={{ label: "Browse products", href: "/shop_page" }}
          className="py-24"
        />
      ) : (
        <>
          <div className="flex items-center justify-between border-b border-hairline pb-4">
            <label className="type-subhead flex cursor-pointer items-center gap-2.5 text-ink-secondary">
              <SelectBox
                checked={selectAll}
                onChange={handleSelectAll}
                label="Select all items"
              />
              Select all
            </label>

            <button
              type="button"
              onClick={handleRemoveSelected}
              disabled={selectedItems.length === 0}
              className="type-footnote flex items-center gap-1.5 font-semibold text-danger transition-opacity hover:opacity-70 disabled:pointer-events-none disabled:opacity-30"
            >
              <Trash2 size={14} aria-hidden="true" />
              Remove selected
              {selectedItems.length > 0 && ` (${selectedItems.length})`}
            </button>
          </div>

          <ul className="divide-y divide-hairline">
            {validItems.map((item) => {
              const product = item.product;
              const isSelected = selectedItems.includes(product.id);

              return (
                <li key={product.id} className="flex gap-4 py-5">
                  <div className="pt-1">
                    <SelectBox
                      checked={isSelected}
                      onChange={() => handleSelectItem(product.id)}
                      label={`Select ${product.name}`}
                    />
                  </div>

                  <Link
                    href={`/product/${product.slug || product.id}`}
                    className="relative h-24 w-20 shrink-0 overflow-hidden rounded-md bg-canvas-sunken sm:h-28 sm:w-24"
                  >
                    {product.thumbnail_image && (
                      <Image
                        src={product.thumbnail_image}
                        alt=""
                        fill
                        className="object-cover"
                        sizes="96px"
                      />
                    )}
                  </Link>

                  <div className="flex min-w-0 flex-1 flex-col gap-2">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <Link
                          href={`/product/${product.slug || product.id}`}
                          className="type-subhead line-clamp-2 font-medium text-ink hover:text-ink-secondary"
                        >
                          {product.name}
                        </Link>

                        {product.rating != null && (
                          <div className="mt-1.5">
                            <Rating
                              value={product.rating}
                              count={product.rating_count}
                            />
                          </div>
                        )}
                      </div>

                      <button
                        type="button"
                        onClick={(e) => handleRemoveItem(product.id, e)}
                        className="btn-icon btn-icon-sm shrink-0 hover:text-danger"
                        aria-label={`Remove ${product.name} from wishlist`}
                      >
                        <Trash2 size={15} aria-hidden="true" />
                      </button>
                    </div>

                    <div className="mt-auto flex flex-wrap items-end justify-between gap-3 pt-1">
                      <div>
                        <p className="type-headline tabular text-ink">
                          {tkText(product.main_price)}
                        </p>
                        {product.stroked_price && (
                          <p className="type-caption tabular text-ink-tertiary line-through">
                            {tkText(product.stroked_price)}
                          </p>
                        )}
                      </div>

                      <Button
                        size="sm"
                        variant="secondary"
                        iconLeft={ShoppingBag}
                        onClick={(e) => handleAddToCart(item, e)}
                      >
                        Add to cart
                      </Button>
                    </div>
                  </div>
                </li>
              );
            })}
          </ul>

          <div className="mt-8">
            <Button href="/shop_page" variant="secondary">
              Continue shopping
            </Button>
          </div>
        </>
      )}
    </div>
  );
}
