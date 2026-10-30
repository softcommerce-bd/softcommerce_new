"use client";

/**
 * Buy-now action: adds the item, then opens the checkout popup.
 *
 * Now renders the shared primary Button. Playfair Display was loaded here for
 * this single button's label and has been dropped, along with the react-icons
 * glyph, in favour of the app's type stack and a lucide icon.
 *
 * This file previously carried a full commented-out earlier copy of the
 * component; it has been removed.
 *
 * Validation, referral handling and the add-then-redirect flow are unchanged.
 */

import { useCart } from "@/store/hooks/useCart";
import toast from "react-hot-toast";
import { useCheckoutModal } from "@/app/components/checkout/CheckoutModalProvider";
import { useReduxAuth } from "@/store/hooks/useReduxAuth";
import { useState, useEffect } from "react";
import { CreditCard } from "lucide-react";
import { Button } from "@/app/components/ui";

function getCookie(name) {
  if (typeof document === 'undefined') return null;
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop().split(';').shift();
  return null;
}

export default function BuyNowButton({
  product,
  hasVariants = false,
  hasColors = false,
  selectedVariant = null,
  selectedColor = null,
  onError,
  onSuccess,
  disabled = false,
  label = "Order now",
  icon = CreditCard,
  className = "",
}) {
  const { addToCart, loading } = useCart();
  const { getUserForGTM } = useReduxAuth();
  const { openCheckout } = useCheckoutModal();
  const [isClient, setIsClient] = useState(false);

  // The cart's loading flag differs between server and client on first paint,
  // so it is only honoured after mount.
  useEffect(() => {
    setIsClient(true);
  }, []);

  const isOutOfStock = product.current_stock <= 0;

  const getUserId = () => {
    if (typeof window === "undefined") return null;
    try {
      const user = JSON.parse(localStorage.getItem("user"));
      return user?.id || null;
    } catch (error) {
      return null;
    }
  };

  const handleBuyNow = async () => {
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

    const userId = getUserId();
    const quantity = product.quantity || 1;
    const productReferralCode = getCookie('product_referral_code');

    try {
      const cartData = {
        user_id: userId,
        id: product.id,
        variant: product.full_variant || selectedVariant || "",
        quantity: quantity,
        ...(selectedColor && { color: selectedColor }),
      };

      if (productReferralCode) {
        cartData.product_referral_code = productReferralCode;
      }

      const response = await addToCart(cartData, false);

      if (response?.result || response?.success) {
        if (onSuccess) onSuccess();
        // Short delay so the closing quick-add dialog has released focus and
        // the scroll lock before checkout takes them.
        setTimeout(openCheckout, 100);
      } else {
        const errorMsg = response?.message || "Failed to add to cart";
        if (onError) onError(errorMsg);
        else toast.error(errorMsg);
      }
    } catch (err) {
      console.error(err);
      const msg = err?.message || "Failed to add to cart";
      if (onError) onError(msg);
      else toast.error(msg);
    }
  };

  const isLoading = isClient && loading;
  const isButtonDisabled = disabled || isLoading || isOutOfStock;

  return (
    <Button
      size="lg"
      fullWidth
      onClick={handleBuyNow}
      disabled={isButtonDisabled}
      loading={isLoading}
      iconLeft={icon}
      className={className}
    >
      {isLoading ? "Processing" : isOutOfStock ? "Out of stock" : label}
    </Button>
  );
}
