import { useCallback, useRef, useState } from 'react';
import { useDispatch } from 'react-redux';
import toast from 'react-hot-toast';

import { useCart } from '@/store/hooks/useCart';
import { openCartModal } from '@/store/slices/cartModalSlice';
import {
  firstAvailableVariant,
  hasAnyStock,
  resolveVariantStock,
} from '@/utils/stock';

/**
 * One-tap add to cart from a product card.
 *
 * The list payload carries no variants, so the product detail is fetched first
 * and the first size (and colour) that is actually in stock is added, the same
 * default the product page lands on. When no default can be resolved, or the
 * lookup fails, the cart modal opens instead so the shopper chooses.
 */
export function useQuickAdd(item) {
  const dispatch = useDispatch();
  const { addToCart } = useCart();
  const [adding, setAdding] = useState(false);
  const busyRef = useRef(false);

  const openModal = useCallback(() => {
    dispatch(
      openCartModal({
        productId: item.id || item.product_id,
        productSlug: item.slug,
        product: item,
      }),
    );
  }, [dispatch, item]);

  const quickAdd = useCallback(
    async (e) => {
      e?.preventDefault();
      e?.stopPropagation();
      if (busyRef.current) return;
      busyRef.current = true;
      setAdding(true);

      try {
        const response = await fetch(
          `${process.env.NEXT_PUBLIC_API_URL}/products/${item.slug || item.id}`,
        );
        const json = await response.json();
        const product = json?.data?.[0];
        if (!json?.success || !product) return openModal();

        if (!hasAnyStock(product)) {
          toast.error('Sorry! This product is out of stock.');
          return;
        }

        const hasSizes = (product.choice_options?.[0]?.options || []).length > 0;
        const colors = product.colors_name || [];

        let variant = '';
        let color = '';
        let fullVariant = '';

        if (hasSizes) {
          variant = firstAvailableVariant(product) || '';
          if (!variant) return openModal();

          if (colors.length > 0) {
            const inStock = colors.find(
              (c) => resolveVariantStock(product, variant, c.code)?.quantity > 0,
            );
            if (!inStock) return openModal();
            color = inStock.code;
          }
          fullVariant =
            resolveVariantStock(product, variant, color)?.full_variant || variant;
        } else if (colors.length > 0) {
          // Colour-only products have no size key to resolve a stock row from.
          return openModal();
        }

        const result = await addToCart(
          {
            id: product.id,
            name: product.name,
            image: product.thumbnail_image,
            variant: fullVariant,
            color,
            quantity: 1,
          },
          false,
        );

        if (result && result.result === true) toast.success('Added to cart');
        else toast.error(result?.message || 'Failed to add to cart');
      } catch (err) {
        console.error('Quick add error:', err);
        openModal();
      } finally {
        busyRef.current = false;
        setAdding(false);
      }
    },
    [item, addToCart, openModal],
  );

  return { quickAdd, adding, openModal };
}
