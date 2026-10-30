"use client";

/**
 * Cart preview panel, anchored under the header's cart button.
 *
 * Rebuilt on the design system. The file previously carried a full
 * commented-out earlier copy of the component above the live one — roughly
 * 280 dead lines — which has been removed.
 *
 * Cart mutation behaviour (remove, quantity change, limits) is unchanged.
 */

import { useState, useCallback, useMemo, memo } from "react";
import { Minus, Plus, Trash2, ShoppingBag } from "lucide-react";

import { useAppSelector } from "@/store/hooks";
import { useCart } from "@/store/hooks/useCart";
import { toTk, tkText, parseAmount } from "@/utils/currency";
import { Button, EmptyState, Skeleton } from "../ui";
import { useCheckoutModal } from "@/app/components/checkout/CheckoutModalProvider";

/** "৳1,299" → 1299. Prices arrive from the API pre-formatted as strings. */
const parsePrice = parseAmount;

/** Stepper shared by every row. Limits come from the item itself. */
function QuantityStepper({ value, onDecrement, onIncrement, canDecrement, canIncrement, disabled }) {
  return (
    <div className="inline-flex items-center rounded-md border border-hairline">
      <button
        type="button"
        onClick={onDecrement}
        disabled={!canDecrement || disabled}
        className="flex h-7 w-7 items-center justify-center rounded-l-md text-ink-secondary transition-colors hover:bg-surface-hover disabled:opacity-30"
        aria-label="Decrease quantity"
      >
        <Minus size={13} aria-hidden="true" />
      </button>

      <span className="tabular w-8 text-center text-[0.8125rem] font-semibold text-ink">
        {value}
      </span>

      <button
        type="button"
        onClick={onIncrement}
        disabled={!canIncrement || disabled}
        className="flex h-7 w-7 items-center justify-center rounded-r-md text-ink-secondary transition-colors hover:bg-surface-hover disabled:opacity-30"
        aria-label="Increase quantity"
      >
        <Plus size={13} aria-hidden="true" />
      </button>
    </div>
  );
}

const CartItem = memo(({ item, onRemove, onQuantityChange, updatingItems }) => {
  const unitPrice = useMemo(
    () => parsePrice(item.price) / item.quantity,
    [item.price, item.quantity],
  );

  const isUpdating = updatingItems[item.id];

  return (
    <div
      className={`flex gap-3 px-5 py-4 transition-opacity ${
        isUpdating ? "pointer-events-none opacity-50" : ""
      }`}
    >
      <div className="h-[68px] w-[68px] shrink-0 overflow-hidden rounded-md bg-canvas-sunken">
        <img
          src={item.product_thumbnail_image}
          alt=""
          className="h-full w-full object-cover"
          loading="lazy"
        />
      </div>

      <div className="flex min-w-0 flex-1 flex-col gap-1.5">
        <div className="flex items-start justify-between gap-2">
          <p className="type-footnote line-clamp-2 font-medium text-ink">
            {item.product_name}
          </p>

          <button
            type="button"
            onClick={() => onRemove(item.id)}
            disabled={isUpdating}
            className="btn-icon btn-icon-sm -mr-1.5 -mt-1 shrink-0 hover:text-danger"
            aria-label={`Remove ${item.product_name} from cart`}
          >
            <Trash2 size={15} aria-hidden="true" />
          </button>
        </div>

        {item.variation && (
          <p className="type-caption text-ink-tertiary">{item.variation}</p>
        )}

        <div className="mt-auto flex items-end justify-between gap-2 pt-1">
          <QuantityStepper
            value={item.quantity}
            disabled={isUpdating}
            canDecrement={item.quantity > item.lower_limit}
            canIncrement={item.quantity < item.upper_limit}
            onDecrement={() =>
              onQuantityChange(item.id, item.quantity, item.quantity - 1, item.upper_limit)
            }
            onIncrement={() =>
              onQuantityChange(item.id, item.quantity, item.quantity + 1, item.upper_limit)
            }
          />

          <div className="text-right">
            <p className="type-caption tabular text-ink-tertiary">
              {toTk(unitPrice)} each
            </p>
            <p className="type-subhead tabular font-semibold text-ink">
              {tkText(item.price)}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
});

CartItem.displayName = "CartItem";

/** Shell shared by every state, so the panel's frame never shifts. */
function Panel({ children, className = "" }) {
  return (
    <div
      className={`animate-slide-down absolute right-0 z-50 mt-2 w-[380px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl bg-surface-raised shadow-e4 ${className}`}
    >
      {children}
    </div>
  );
}

export default function CartDropdown({ loading, onClose }) {
  const { cartData, totalQuantity } = useAppSelector((state) => state.cart);
  const { removeItem, updateQuantity } = useCart();
  const { openCheckout } = useCheckoutModal();
  const [updatingItems, setUpdatingItems] = useState({});

  const handleRemoveItem = useCallback(
    async (cartId) => {
      setUpdatingItems((prev) => ({ ...prev, [cartId]: true }));
      await removeItem(cartId);
      setUpdatingItems((prev) => ({ ...prev, [cartId]: false }));
    },
    [removeItem],
  );

  const handleQuantityChange = useCallback(
    async (cartId, currentQty, newQty, upperLimit) => {
      if (newQty === currentQty || newQty < 1 || newQty > upperLimit) return;

      setUpdatingItems((prev) => ({ ...prev, [cartId]: true }));
      await updateQuantity(cartId, newQty, upperLimit);
      setUpdatingItems((prev) => ({ ...prev, [cartId]: false }));
    },
    [updateQuantity],
  );

  const groupedCartItems = useMemo(() => cartData?.data ?? [], [cartData?.data]);

  const isEmpty = useMemo(
    () => totalQuantity === 0 || !cartData?.data || cartData.data.length === 0,
    [totalQuantity, cartData?.data],
  );

  // Loading — item-shaped skeletons rather than a spinner, so the panel opens
  // at roughly the height it will settle at.
  if (loading) {
    return (
      <Panel>
        <div className="space-y-4 p-5">
          {[0, 1, 2].map((i) => (
            <div key={i} className="flex gap-3">
              <Skeleton className="h-[68px] w-[68px]" />
              <div className="flex-1 space-y-2 py-1">
                <Skeleton className="h-3 w-4/5" />
                <Skeleton className="h-3 w-1/2" />
                <Skeleton className="h-6 w-24" />
              </div>
            </div>
          ))}
        </div>
      </Panel>
    );
  }

  if (isEmpty) {
    return (
      <Panel>
        <EmptyState
          icon={ShoppingBag}
          title="Your cart is empty"
          description="Items you add will appear here."
          action={{ label: "Start shopping", href: "/shop_page", onClick: onClose }}
          className="py-10"
        />
      </Panel>
    );
  }

  return (
    <Panel>
      <header className="flex items-center justify-between border-b border-hairline px-5 py-3.5">
        <h3 className="type-headline text-ink">Cart</h3>
        <span className="badge badge-neutral tabular">
          {totalQuantity} {totalQuantity === 1 ? "item" : "items"}
        </span>
      </header>

      <div className="scrollbar-subtle max-h-104 overflow-y-auto overscroll-contain">
        {groupedCartItems.map((shop) => (
          <section key={shop.owner_id}>
            {/* Seller name pins to the top while its own items scroll past. */}
            <div className="surface-blur sticky top-0 z-10 border-b border-hairline px-5 py-2">
              <p className="type-eyebrow text-ink-tertiary">{shop.name}</p>
            </div>

            <div className="divide-y divide-hairline">
              {shop.cart_items.map((item) => (
                <CartItem
                  key={item.id}
                  item={item}
                  onRemove={handleRemoveItem}
                  onQuantityChange={handleQuantityChange}
                  updatingItems={updatingItems}
                />
              ))}
            </div>
          </section>
        ))}
      </div>

      <footer className="border-t border-hairline p-5">
        <div className="mb-4 flex items-baseline justify-between">
          <span className="type-subhead text-ink-secondary">Subtotal</span>
          <span className="type-title-3 tabular text-ink">
            {tkText(cartData.grand_total)}
          </span>
        </div>

        <div className="flex gap-2.5">
          <Button
            onClick={() => {
              onClose();
              openCheckout();
            }}
            className="flex-1"
          >
            Checkout
          </Button>
          <Button href="/view-cart" onClick={onClose} variant="secondary" className="flex-1">
            View cart
          </Button>
        </div>

        <p className="type-caption mt-3.5 text-center text-ink-tertiary">
          Shipping calculated at checkout
        </p>
      </footer>
    </Panel>
  );
}
