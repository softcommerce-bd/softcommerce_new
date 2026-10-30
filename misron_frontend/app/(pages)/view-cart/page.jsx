"use client";

/**
 * Cart page.
 *
 * Rebuilt on the design system. Three substantive changes beyond styling:
 *
 *   · An empty cart now renders an empty state. Previously it rendered a
 *     table with a header row and no body, plus "There are 00 products".
 *   · The layout is a stack of rows rather than a `<table>`, which had no
 *     mobile treatment at all — the five columns simply scrolled sideways.
 *   · The row's delete control gained an accessible name; it was an unlabelled
 *     button wrapping an inline SVG.
 *
 * Playfair Display, loaded for this page alone, is gone, as is an unused
 * `samsung.png` placeholder import.
 *
 * Cart mutation, selection and clearing behaviour are unchanged.
 */

import Image from "next/image";
import Link from "next/link";
import { useState } from "react";
import { Trash2, Minus, Plus, Check, ShoppingBag } from "lucide-react";

import Breadcrumb from "@/app/components/layout/Breadcrumb";
import OrderSummary from "@/app/components/layout/OrderSummary";
import { useAppSelector } from "@/store/hooks";
import { useCart } from "@/store/hooks/useCart";
import { Button, EmptyState } from "@/app/components/ui";
import { toTk, tkText, parseAmount } from "@/utils/currency";

const parsePrice = parseAmount;

const calculateUnitPrice = (totalPrice, quantity) =>
  parsePrice(totalPrice) / quantity;

/** Square checkbox matching the one in the shop filters. */
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

export default function ViewCart() {
  const { cartData } = useAppSelector((state) => state.cart);
  const { removeItem, updateQuantity } = useCart();
  const [updatingItems, setUpdatingItems] = useState({});
  const [selectedItems, setSelectedItems] = useState([]);

  const handleRemoveItem = async (cartId) => {
    setUpdatingItems((prev) => ({ ...prev, [cartId]: true }));
    await removeItem(cartId);
    setUpdatingItems((prev) => ({ ...prev, [cartId]: false }));
  };

  const handleQuantityChange = async (cartId, currentQty, newQty, upperLimit) => {
    if (newQty === currentQty || newQty < 1 || newQty > upperLimit) return;

    setUpdatingItems((prev) => ({ ...prev, [cartId]: true }));
    await updateQuantity(cartId, newQty, upperLimit);
    setUpdatingItems((prev) => ({ ...prev, [cartId]: false }));
  };

  const allCartItems = cartData?.data?.flatMap((shop) => shop.cart_items) || [];
  const itemCount = allCartItems.length;

  const handleSelectAll = (e) => {
    setSelectedItems(e.target.checked ? allCartItems.map((item) => item.id) : []);
  };

  const handleSelectItem = (itemId) => {
    setSelectedItems((prev) =>
      prev.includes(itemId) ? prev.filter((id) => id !== itemId) : [...prev, itemId],
    );
  };

  const handleClearSelected = async () => {
    if (selectedItems.length === 0) return;

    for (const itemId of selectedItems) {
      await handleRemoveItem(itemId);
    }
    setSelectedItems([]);
  };

  if (itemCount === 0) {
    return (
      <div className="container-page py-8 md:py-12">
        <Breadcrumb items={[{ name: "Home", href: "/" }, { name: "Cart", href: "#" }]} />
        <EmptyState
          icon={ShoppingBag}
          title="Your cart is empty"
          description="Once you add something, it will show up here."
          action={{ label: "Start shopping", href: "/shop_page" }}
          className="py-24"
        />
      </div>
    );
  }

  const allSelected = selectedItems.length === itemCount;

  return (
    <div className="container-page py-8 md:py-12">
      <Breadcrumb items={[{ name: "Home", href: "/" }, { name: "Cart", href: "#" }]} />

      <header className="mb-8 mt-8">
        <h1 className="type-title-1 text-ink">Your cart</h1>
        <p className="type-callout tabular mt-2 text-ink-tertiary">
          {itemCount} {itemCount === 1 ? "item" : "items"}
        </p>
      </header>

      <div className="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:gap-10">
        <div className="lg:col-span-8">
          {/* ── Bulk actions ────────────────────────────────────────────*/}
          <div className="flex items-center justify-between border-b border-hairline pb-4">
            <label className="type-subhead flex cursor-pointer items-center gap-2.5 text-ink-secondary">
              <SelectBox
                checked={allSelected}
                onChange={handleSelectAll}
                label="Select all items"
              />
              Select all
            </label>

            <button
              type="button"
              onClick={handleClearSelected}
              disabled={selectedItems.length === 0}
              className="type-footnote flex items-center gap-1.5 font-semibold text-danger transition-opacity hover:opacity-70 disabled:pointer-events-none disabled:opacity-30"
            >
              <Trash2 size={14} aria-hidden="true" />
              Remove selected
              {selectedItems.length > 0 && ` (${selectedItems.length})`}
            </button>
          </div>

          {/* ── Rows ────────────────────────────────────────────────────
              A stacked row rather than a table cell, so the same markup
              works at every width. */}
          <ul className="divide-y divide-hairline">
            {cartData?.data?.map((shop) =>
              shop.cart_items.map((item) => {
                const unitPrice = calculateUnitPrice(item.price, item.quantity);
                const isUpdating = updatingItems[item.id];

                return (
                  <li
                    key={item.id}
                    className={`flex gap-4 py-5 transition-opacity ${
                      isUpdating ? "pointer-events-none opacity-50" : ""
                    }`}
                  >
                    <div className="pt-1">
                      <SelectBox
                        checked={selectedItems.includes(item.id)}
                        onChange={() => handleSelectItem(item.id)}
                        label={`Select ${item.product_name}`}
                      />
                    </div>

                    <div className="relative h-24 w-20 shrink-0 overflow-hidden rounded-md bg-canvas-sunken sm:h-28 sm:w-24">
                      {item.product_thumbnail_image && (
                        <Image
                          src={item.product_thumbnail_image}
                          alt=""
                          fill
                          className="object-cover"
                          sizes="96px"
                        />
                      )}
                    </div>

                    <div className="flex min-w-0 flex-1 flex-col gap-2">
                      <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                          <p className="type-subhead line-clamp-2 font-medium text-ink">
                            {item.product_name}
                          </p>

                          <p className="type-caption mt-1 text-ink-tertiary">
                            Sold by{" "}
                            <Link
                              href={`/shop/${shop.owner_id}`}
                              className="text-ink-secondary hover:text-ink hover:underline"
                            >
                              {shop.name}
                            </Link>
                          </p>

                          {item.variation && (
                            <p className="type-caption mt-0.5 text-ink-tertiary">
                              {item.variation}
                            </p>
                          )}
                        </div>

                        <button
                          type="button"
                          onClick={() => handleRemoveItem(item.id)}
                          disabled={isUpdating}
                          className="btn-icon btn-icon-sm shrink-0 hover:text-danger"
                          aria-label={`Remove ${item.product_name} from cart`}
                        >
                          <Trash2 size={15} aria-hidden="true" />
                        </button>
                      </div>

                      <div className="mt-auto flex flex-wrap items-end justify-between gap-3 pt-1">
                        <div className="inline-flex items-center rounded-md border border-hairline">
                          <button
                            type="button"
                            onClick={() =>
                              handleQuantityChange(
                                item.id,
                                item.quantity,
                                item.quantity - 1,
                                item.upper_limit,
                              )
                            }
                            disabled={item.quantity <= item.lower_limit || isUpdating}
                            className="flex h-9 w-9 items-center justify-center rounded-l-md text-ink-secondary transition-colors hover:bg-surface-hover disabled:opacity-30"
                            aria-label="Decrease quantity"
                          >
                            <Minus size={14} aria-hidden="true" />
                          </button>

                          <input
                            type="number"
                            min={item.lower_limit || 1}
                            max={item.upper_limit}
                            value={item.quantity}
                            onChange={(e) => {
                              const newQty = parseInt(e.target.value);
                              if (!isNaN(newQty)) {
                                handleQuantityChange(
                                  item.id,
                                  item.quantity,
                                  newQty,
                                  item.upper_limit,
                                );
                              }
                            }}
                            aria-label={`Quantity for ${item.product_name}`}
                            className="tabular w-11 border-x border-hairline bg-transparent py-2 text-center text-[0.8125rem] font-semibold text-ink outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                          />

                          <button
                            type="button"
                            onClick={() =>
                              handleQuantityChange(
                                item.id,
                                item.quantity,
                                item.quantity + 1,
                                item.upper_limit,
                              )
                            }
                            disabled={item.quantity >= item.upper_limit || isUpdating}
                            className="flex h-9 w-9 items-center justify-center rounded-r-md text-ink-secondary transition-colors hover:bg-surface-hover disabled:opacity-30"
                            aria-label="Increase quantity"
                          >
                            <Plus size={14} aria-hidden="true" />
                          </button>
                        </div>

                        <div className="text-right">
                          <p className="type-caption tabular text-ink-tertiary">
                            {toTk(unitPrice)} each
                          </p>
                          <p className="type-headline tabular text-ink">
                            {tkText(item.price)}
                          </p>
                        </div>
                      </div>
                    </div>
                  </li>
                );
              }),
            )}
          </ul>

          <div className="mt-8">
            <Button href="/shop_page" variant="secondary">
              Continue shopping
            </Button>
          </div>
        </div>

        {/* Summary sticks alongside the list on desktop. */}
        <div className="lg:col-span-4">
          <div className="lg:sticky lg:top-[calc(var(--header-height,80px)+24px)]">
            <OrderSummary />
          </div>
        </div>
      </div>
    </div>
  );
}
