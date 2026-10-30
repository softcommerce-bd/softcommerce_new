"use client";

/**
 * Product card — the site's most repeated component.
 *
 * Editorial layout. The card has no frame of its own: the image sits directly
 * on the page in a 4:5 well, and the text beneath it is a quiet left-aligned
 * block. Nothing but the photograph carries weight at rest.
 *
 * Interaction is deferred to hover. The wishlist control and the "Add to bag"
 * action fade in over the bottom of the image, so a grid of twenty products
 * reads as twenty photographs rather than twenty widgets. On touch devices,
 * where there is no hover, the action row is always visible.
 *
 * The card carries no variant controls. Size, colour and material belong to
 * the product page, where a shopper can read the fabric and the measurements
 * before committing; a chip rail on a 160px thumbnail invited mis-taps and
 * blind purchases. "Add to bag" therefore opens the cart modal with the
 * product's own defaults rather than a selection made from the grid.
 */

import Image from "next/image";
import Link from "next/link";
import { memo, useState, useCallback, useMemo } from "react";
import { useDispatch } from "react-redux";
import { ImageOff } from "lucide-react";

import { openCartModal } from "@/store/slices/cartModalSlice";
import { toTk, tkText } from "@/utils/currency";
import WishlistButton from "../WishlistButton";

const formatPrice = toTk;

const ProductCard1 = memo(({ item, priority = false }) => {
  const [imageError, setImageError] = useState(false);
  const [isHovered, setIsHovered] = useState(false);
  const [hoverImageError, setHoverImageError] = useState(false);

  const dispatch = useDispatch();

  // The card renders straight from the list payload. There is no detail fetch
  // any more: sizes, colours and materials were the only fields that needed
  // it, and those now live solely on the product page. A grid of 24 products
  // costs 24 images and no extra API calls.
  const mergedItem = item;

  const {
    productId,
    productSlug,
    currentImage,
    discount,
    isOutOfStockFlag,
    mainPrice,
    strokedPrice,
  } = useMemo(() => {
    const id = mergedItem.id || mergedItem.product_id;
    const slug = mergedItem.slug;

    let currentImg = mergedItem.thumbnail_image;
    if (isHovered && mergedItem.hover_image && !hoverImageError)
      currentImg = mergedItem.hover_image;

    let disc = null;
    if (mergedItem.discount && mergedItem.discount !== "-0%")
      disc = mergedItem.discount;
    else if (mergedItem.has_discount && mergedItem.discount_percent)
      disc = `-${mergedItem.discount_percent}%`;

    const stock =
      mergedItem.recent_stock ?? mergedItem.stock ?? mergedItem.quantity ?? 0;
    const outOfStock = Number(stock) <= 0;

    let mainPriceStr = "৳ 0";
    if (mergedItem.main_price) mainPriceStr = tkText(mergedItem.main_price);
    else if (mergedItem.price) mainPriceStr = formatPrice(mergedItem.price);
    else if (mergedItem.unit_price) mainPriceStr = formatPrice(mergedItem.unit_price);

    let strokedPriceStr = null;
    if (mergedItem.stroked_price) strokedPriceStr = tkText(mergedItem.stroked_price);
    else if (mergedItem.oldPrice) strokedPriceStr = formatPrice(mergedItem.oldPrice);

    return {
      productId: id,
      productSlug: slug,
      currentImage: currentImg,
      discount: disc,
      isOutOfStockFlag: outOfStock,
      mainPrice: mainPriceStr,
      strokedPrice: strokedPriceStr,
    };
  }, [mergedItem, isHovered, hoverImageError]);

  const handleMouseEnter = useCallback(() => {
    if (item.hover_image && !hoverImageError) setIsHovered(true);
  }, [item.hover_image, hoverImageError]);

  const handleMouseLeave = useCallback(() => setIsHovered(false), []);

  const handleAddToCartClick = useCallback(
    (e) => {
      e.preventDefault();
      e.stopPropagation();
      // No variant is pre-selected here any more — the modal opens with the
      // product's own defaults and the shopper picks size and colour there.
      if (productId) {
        dispatch(
          openCartModal({
            productId,
            productSlug,
            product: mergedItem,
          }),
        );
      }
    },
    [dispatch, productId, productSlug, mergedItem],
  );

  const handleImageError = useCallback(() => {
    if (isHovered && item.hover_image) {
      setHoverImageError(true);
      setIsHovered(false);
    } else {
      setImageError(true);
    }
  }, [isHovered, item.hover_image]);

  const imageSizes = useMemo(
    () => "(max-width: 768px) 50vw, (max-width: 1200px) 33vw, 25vw",
    [],
  );

  if (!item || (!item.id && !item.product_id)) return null;

  const productName = item.name || item.title || "Product";
  const brandName = item.brand?.name || item.brand_name || null;
  const productHref = `/product/${item.slug || item.id}`;

  return (
    <article
      className="group relative flex h-full flex-col"
      onMouseEnter={handleMouseEnter}
      onMouseLeave={handleMouseLeave}
    >
      {/* ── Image ──────────────────────────────────────────────────────────
          A square well with no frame or shadow. The sunken ground keeps a
          product shot on white from bleeding into the page. */}
      <div className="relative aspect-square w-full shrink-0 overflow-hidden rounded-md bg-canvas-sunken">
        <Link
          href={productHref}
          prefetch={false}
          className="block h-full w-full"
          tabIndex={-1}
          aria-hidden="true"
        >
          {!imageError && currentImage ? (
            <Image
              src={currentImage}
              alt={productName}
              fill
              // A slow, small zoom. Large scale jumps make a grid feel jittery
              // as the pointer crosses it.
              className="object-cover transition-transform duration-slower ease-standard group-hover:scale-[1.03]"
              sizes={imageSizes}
              onError={handleImageError}
              loading={priority ? "eager" : "lazy"}
              priority={priority}
              quality={78}
            />
          ) : (
            <div className="flex h-full w-full items-center justify-center">
              <ImageOff size={28} className="text-ink-quaternary" strokeWidth={1.5} />
            </div>
          )}
        </Link>

        {/* Sold out dims the photograph itself, so the state is legible from
            across the grid without a heavy badge. */}
        {isOutOfStockFlag && (
          <div className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-surface/55">
            <span className="type-eyebrow bg-surface px-3 py-1.5 text-ink shadow-e1">
              Sold out
            </span>
          </div>
        )}

        {/* Discount — a filled sale chip. Bare `text-sale` type sat straight on
            the photograph, and on a mid-tone or busy image the dark red read as
            part of the picture rather than as a price cut. The solid ground
            keeps the figure legible over any image. */}
        {!isOutOfStockFlag && discount && (
          <span className="type-eyebrow pointer-events-none absolute left-3 top-3 z-20 rounded-sm bg-sale px-2 py-1 leading-none text-white shadow-e1">
            {discount}
          </span>
        )}

        {/* Wishlist — resting at 0 opacity on pointer devices so the grid stays
            calm, always visible on touch where there is no hover. */}
        <div
          className="absolute right-3 top-3 z-20 opacity-0 transition-opacity duration-base ease-standard group-hover:opacity-100 focus-within:opacity-100 [@media(hover:none)]:opacity-100"
          onClick={(e) => e.preventDefault()}
        >
          <WishlistButton
            productId={productId}
            className="h-9! w-9! bg-surface/85 shadow-e1 backdrop-blur-sm"
            iconSize={16}
          />
        </div>

        {/* ── Hover shelf ──────────────────────────────────────────────────
            Only the buy action lives over the foot of the image. Size, colour
            and material are deliberately absent: choosing a variant belongs on
            the product page, where the shopper can see the fabric and the
            measurements first. The shelf slides up on hover and is pinned open
            on touch. */}
        {!isOutOfStockFlag && (
          <div className="pointer-events-none absolute inset-x-0 bottom-0 z-20 translate-y-full p-1.5 opacity-0 transition-all duration-base ease-standard group-hover:translate-y-0 group-hover:opacity-100 focus-within:translate-y-0 focus-within:opacity-100 md:p-2 lg:p-3 [@media(hover:none)]:translate-y-0 [@media(hover:none)]:opacity-100">
            <button
              type="button"
              onClick={handleAddToCartClick}
              /* `btn-card` carries the responsive sizing — see globals.css. */
              className="btn btn-primary btn-card pointer-events-auto w-full"
            >
              Add to bag
            </button>
          </div>
        )}
      </div>

      {/* ── Detail ─────────────────────────────────────────────────────────
          Left-aligned, tightly spaced, no card padding of its own. */}
      <div className="flex grow flex-col gap-1.5 pt-4">
        {brandName && (
          <p className="type-eyebrow text-ink-tertiary">{brandName}</p>
        )}

        <h3 className="type-subhead line-clamp-1 font-normal leading-relaxed text-ink">
          <Link
            href={productHref}
            prefetch={false}
            // The stretched link makes the whole card clickable while leaving
            // the buttons inside it independently clickable. The colour does
            // not shift on hover: the image and the "Add to bag" panel already
            // signal the hover state, and a second cue read as a mis-render.
            className="after:absolute after:inset-0 after:z-10 after:content-['']"
          >
            {productName}
          </Link>
        </h3>

        {/* The extra top margin separates the price from the name. The block's
            own gap stays tight so brand and name still read as one unit. Both
            prices sit in the muted ink at medium weight, so the name stays the
            loudest line in the block. */}
        <div className="mt-3 flex items-baseline gap-2">
          <span className="type-callout tabular font-medium text-ink-secondary">
            {mainPrice}
          </span>
          {strokedPrice && (
            <span className="type-footnote tabular font-medium text-ink-tertiary line-through">
              {strokedPrice}
            </span>
          )}
        </div>

      </div>
    </article>
  );
});

ProductCard1.displayName = "ProductCard1";

export default ProductCard1;
