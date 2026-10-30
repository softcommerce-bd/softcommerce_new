// components/WishlistButton.jsx
'use client';

/**
 * Heart toggle used on product cards and the product detail page.
 *
 * The icon is lucide's Heart, filled when saved, and the whole control gives a
 * short scale-down on press. All wishlist state, auth gating and dispatch
 * behaviour is unchanged.
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { Heart, Loader2 } from 'lucide-react';
import {
  addToWishlist,
  removeFromWishlist,
  checkWishlistStatus
} from '@/store/slices/wishlistSlice';
import { toast } from 'react-hot-toast';

export default function WishlistButton({
  productId,
  className = "",
  style,
  iconSize = 16,
  // Text button for the product page: heart plus "Add to Wishlist" instead of a
  // bare round icon. Sizing and shape come from the caller's `className`.
  labelled = false,
}) {
  const dispatch = useDispatch();
  const { wishlistIds } = useSelector((state) => state.wishlist);
  const { isAuthenticated } = useSelector((state) => state.auth);
  const [isInWishlist, setIsInWishlist] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const hasCheckedRef = useRef(false);
  const prevProductIdRef = useRef(null);

  useEffect(() => {
    if (prevProductIdRef.current !== productId) {
      hasCheckedRef.current = false;
      prevProductIdRef.current = productId;
    }

    if (!productId) return;

    if (!isAuthenticated) {
      setIsInWishlist(false);
      return;
    }

    if (hasCheckedRef.current) return;

    if (wishlistIds[productId] !== undefined) {
      setIsInWishlist(wishlistIds[productId].isInWishlist);
      return;
    }

    if (!hasCheckedRef.current) {
      hasCheckedRef.current = true;
      dispatch(checkWishlistStatus(productId));
    }
  }, [productId, isAuthenticated]);

  useEffect(() => {
    if (productId && wishlistIds[productId] !== undefined) {
      setIsInWishlist(wishlistIds[productId].isInWishlist);
    }
  }, [wishlistIds, productId]);

  useEffect(() => {
    if (!isAuthenticated) {
      setIsInWishlist(false);
      hasCheckedRef.current = false;
    }
  }, [isAuthenticated]);

  const handleWishlistClick = useCallback(async (e) => {
    e.preventDefault();
    e.stopPropagation();

    if (isLoading) return;

    if (!isAuthenticated) {
      toast.error('Please login first to add items to wishlist');
      return;
    }

    setIsLoading(true);

    try {
      if (isInWishlist) {
        await dispatch(removeFromWishlist(productId)).unwrap();
      } else {
        await dispatch(addToWishlist(productId)).unwrap();
      }
    } catch (error) {
      if (process.env.NODE_ENV === 'development') {
        console.error('Wishlist action failed:', error);
      }
    } finally {
      setIsLoading(false);
    }
  }, [isLoading, isAuthenticated, isInWishlist, dispatch, productId]);

  if (labelled) {
    return (
      <button
        type="button"
        onClick={handleWishlistClick}
        disabled={isLoading}
        className={[
          'flex cursor-pointer items-center justify-center gap-1.5 border text-xs! font-medium',
          'transition-colors',
          isInWishlist
            ? 'border-rose-300 bg-rose-50 text-rose-600'
            : 'border-stone-200 bg-white text-stone-700 hover:bg-stone-50',
          isLoading ? 'cursor-wait opacity-60' : '',
          className,
        ].filter(Boolean).join(' ')}
        style={style}
        aria-pressed={isInWishlist}
      >
        {isLoading ? (
          <Loader2 size={iconSize} className="animate-spin" aria-hidden="true" />
        ) : (
          <Heart
            size={iconSize}
            strokeWidth={1.75}
            className={isInWishlist ? 'text-rose-500' : ''}
            fill={isInWishlist ? 'currentColor' : 'none'}
            aria-hidden="true"
          />
        )}
        {isInWishlist ? 'Saved in Wishlist' : 'Add to Wishlist'}
      </button>
    );
  }

  return (
    <button
      type="button"
      onClick={handleWishlistClick}
      disabled={isLoading}
      className={[
        'flex h-9 w-9 items-center justify-center rounded-full bg-surface/90',
        'transition-all duration-150 ease-standard',
        'hover:bg-surface active:scale-90',
        isLoading ? 'cursor-wait opacity-60' : '',
        className,
      ].filter(Boolean).join(' ')}
      style={style}
      aria-pressed={isInWishlist}
      aria-label={isInWishlist ? 'Remove from wishlist' : 'Add to wishlist'}
    >
      {isLoading ? (
        <Loader2 size={iconSize} className="animate-spin text-ink-tertiary" aria-hidden="true" />
      ) : (
        <Heart
          size={iconSize}
          strokeWidth={1.75}
          className={isInWishlist ? 'text-sale' : 'text-ink-secondary'}
          fill={isInWishlist ? 'currentColor' : 'none'}
          aria-hidden="true"
        />
      )}
    </button>
  );
}
