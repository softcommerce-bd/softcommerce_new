'use client';

/**
 * Add-to-cart action, used on the product page and inside the quick-add
 * dialog.
 *
 * Now renders the shared Button in its two shapes — full-width with a label,
 * or icon-only when it sits next to Buy Now. The 130-line inline SVG cart
 * glyph (duplicated verbatim in three other files) is replaced by lucide's
 * ShoppingBag, and the `hover:scale-[1.04]` bounce is gone: the shared button
 * presses inward on click rather than growing on hover.
 *
 * This file previously carried a full commented-out earlier copy of the
 * component; it has been removed.
 *
 * Cart validation, referral handling and dispatch behaviour are unchanged.
 */

import { useCart } from '@/store/hooks/useCart';
import toast from 'react-hot-toast';
import { useReduxAuth } from '@/store/hooks/useReduxAuth';
import { useRef, useState, useEffect } from 'react';
import { Check, ShoppingBag } from 'lucide-react';
import { trackAddToCart, contentId } from '@/lib/pixel/meta';
import { Button } from '@/app/components/ui';

function getCookie(name) {
  if (typeof document === 'undefined') return null;
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop().split(';').shift();
  return null;
}

export default function AddToCartButton({
  product,
  hasVariants = false,
  hasColors = false,
  selectedVariant = null,
  selectedColor = null,
  disabled = false,
  onError,
  onSuccess,
  iconOnly = false,
  label = 'Add to cart',
  className = '',
  // The product page confirms in place; the quick-add dialog closes instead.
  confirmAdded = false,
  addedClassName = '',
}) {
  const { addToCart, loading } = useCart();
  const { getUserForGTM } = useReduxAuth();
  const isSendingRef = useRef(false);
  const [isClient, setIsClient] = useState(false);
  const [justAdded, setJustAdded] = useState(false);
  const addedTimerRef = useRef(null);

  useEffect(() => () => clearTimeout(addedTimerRef.current), []);

  // The cart's loading flag differs between server and client on first paint,
  // so it is only honoured after mount.
  useEffect(() => {
    setIsClient(true);
  }, []);

  // The server decides (selling policy). `can_order` is absent on older API responses: fall back to the old stock test.
  const isOutOfStock =
    typeof product.can_order === "boolean"
      ? !product.can_order
      : product.current_stock <= 0;

  const handleAddToCart = async () => {
    if (isOutOfStock) {
      const msg = "Sorry! This product is out of stock.";
      if (onError) onError(msg);
      else toast.error(msg);
      return;
    }

    if (hasVariants && !selectedVariant) {
      const msg = "Please select a variant";
      if (onError) onError(msg);
      else toast.error(msg);
      return;
    }

    if (hasColors && !selectedColor) {
      const msg = "Please select a color";
      if (onError) onError(msg);
      else toast.error(msg);
      return;
    }

    // Guards against a double-click firing two identical cart writes.
    if (isSendingRef.current) return;
    isSendingRef.current = true;

    let userId = null;
    if (typeof window !== 'undefined') {
      const userData = localStorage.getItem('user');
      if (userData) {
        try {
          userId = JSON.parse(userData).id;
        } catch (e) {}
      }
    }

    try {
      const variantToSend = product.full_variant || selectedVariant || '';
      const quantity = product.quantity || 1;
      const productReferralCode = getCookie('product_referral_code');

      const cartData = {
        user_id: userId,
        id: product.id,
        variant: variantToSend,
        quantity: quantity,
      };

      if (selectedColor) {
        cartData.color = selectedColor;
      }

      if (productReferralCode) {
        cartData.product_referral_code = productReferralCode;
      }

      const result = await addToCart(cartData, false);

      if (result && result.result === true) {
        // Tracked only after the cart write succeeds, so a failed add is never
        // reported to Meta as a conversion.
        trackAddToCart({
          id: contentId(product, { stock_id: product.variant_stock_id }),
          name: product.name,
          price: product.calculable_price ?? product.price,
          quantity,
        });

        toast.success('Added to cart');
        if (confirmAdded) {
          setJustAdded(true);
          clearTimeout(addedTimerRef.current);
          addedTimerRef.current = setTimeout(() => setJustAdded(false), 1500);
        }
        if (onSuccess) onSuccess();
      } else {
        const errorMsg = result?.message || "Failed to add to cart";
        if (onError) onError(errorMsg);
        else toast.error(errorMsg);
      }
    } catch (err) {
      console.error('Add to cart error:', err);
      const msg = err?.message || "Failed to add to cart";
      if (onError) onError(msg);
      else toast.error(msg);
    } finally {
      isSendingRef.current = false;
    }
  };

  const isLoading = isClient && loading;
  const isButtonDisabled = disabled || isLoading || isOutOfStock;

  // Icon-only: a square secondary button paired with Buy Now.
  if (iconOnly) {
    return (
      <Button
        variant="secondary"
        size="lg"
        onClick={handleAddToCart}
        disabled={isButtonDisabled}
        loading={isLoading}
        className="w-full px-0"
        aria-label={isOutOfStock ? 'Out of stock' : 'Add to cart'}
      >
        {!isLoading && <ShoppingBag size={18} strokeWidth={1.75} aria-hidden="true" />}
      </Button>
    );
  }

  return (
    <Button
      variant="secondary"
      size="lg"
      fullWidth
      onClick={handleAddToCart}
      disabled={isButtonDisabled}
      loading={isLoading}
      iconLeft={justAdded ? Check : ShoppingBag}
      className={justAdded && addedClassName ? addedClassName : className}
    >
      {isLoading
        ? 'Adding'
        : isOutOfStock
          ? 'Out of stock'
          : justAdded
            ? 'Added to Cart'
            : label}
    </Button>
  );
}
