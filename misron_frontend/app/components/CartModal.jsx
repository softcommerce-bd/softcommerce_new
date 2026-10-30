"use client";

/**
 * Quick-add dialog opened from a product card.
 *
 * Rebuilt on the shared Modal so it inherits focus trapping, Escape and
 * scroll-lock. Playfair Display — pulled in only for this one dialog and
 * applied via six separate `playfair.className` props — is gone; the dialog
 * now uses the app's sans stack like everything else.
 *
 * Product fetching, variant/colour gating, stock limits and the cart and
 * buy-now handoffs are unchanged.
 */

import { useState, useEffect } from "react";
import Image from "next/image";
import toast from "react-hot-toast";
import { useDispatch, useSelector } from "react-redux";
import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation, Pagination, Thumbs } from "swiper/modules";
import { ImageOff, Minus, Plus, AlertCircle, X } from "lucide-react";

import AddToCartButton from "@/components/cart/AddToCartButton";
import BuyNowButton from "@/components/cart/BuyNowButton";
import WishlistButton from "./WishlistButton";
import SizeGuide from "@/app/(pages)/SizeGuide";
import { closeCartModal } from "@/store/slices/cartModalSlice";
import { Modal, Button, Skeleton, CarouselNav } from "./ui";
import { tkText } from "@/utils/currency";
import {
  firstAvailableVariant,
  hasAnyStock,
  resolveStockQuantity,
  resolveVariantStock,
} from "@/utils/stock";

import "../components/cart.css";

export default function CartModal() {
  const dispatch = useDispatch();
  const { isOpen, productId, productSlug } = useSelector((state) => state.cartModal);

  const [productData, setProductData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [selectedVariant, setSelectedVariant] = useState("");
  const [selectedColor, setSelectedColor] = useState("");
  const [quantity, setQuantity] = useState(1);
  const [error, setError] = useState("");
  const [thumbsSwiper, setThumbsSwiper] = useState(null);
  const [swiperReady, setSwiperReady] = useState(false);

  useEffect(() => {
    if (isOpen && productId) fetchProductDetails();
  }, [isOpen, productId]);

  useEffect(() => {
    if (!isOpen) setSwiperReady(false);
  }, [isOpen]);

  // Swiper is mounted a beat after the data lands so it measures the panel at
  // its final width rather than mid-entrance.
  useEffect(() => {
    if (productData) {
      const timer = setTimeout(() => setSwiperReady(true), 100);
      return () => clearTimeout(timer);
    }
  }, [productData]);

  const fetchProductDetails = async () => {
    setLoading(true);
    setError("");
    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/products/${productSlug}`,
      );
      const data = await response.json();
      if (data.success && data.data && data.data.length > 0) {
        setProductData(data.data[0]);
      } else {
        setError("Failed to load product details");
      }
    } catch (err) {
      setError("Error loading product details");
      console.error("Error fetching product:", err);
    } finally {
      setLoading(false);
    }
  };

  // Land on the first variant a shopper can actually buy, the same way the
  // product page does. Without this the dialog opened with no variant, so the
  // ceiling fell back to the sum across every variant and a sold-out product
  // still offered a live buy button.
  useEffect(() => {
    if (selectedVariant) return;
    const first = firstAvailableVariant(productData);
    if (first) setSelectedVariant(first);
  }, [productData, selectedVariant]);

  const handleVariantSelect = (variant) => {
    setSelectedVariant(variant);
    // Switching variant can drop the ceiling below the chosen quantity.
    const nextStock = resolveStockQuantity(productData, variant, selectedColor);
    setQuantity((current) => (current > nextStock ? 1 : current));
    setError("");
  };

  const handleColorSelect = (color) => {
    setSelectedColor(color.code);
    const nextStock = resolveStockQuantity(
      productData,
      selectedVariant,
      color.code,
    );
    setQuantity((current) => (current > nextStock ? 1 : current));
    setError("");
  };

  const getCurrentStock = () =>
    resolveStockQuantity(productData, selectedVariant, selectedColor);

  const getFullVariant = () => {
    if (!selectedVariant) return "";
    return (
      resolveVariantStock(productData, selectedVariant, selectedColor)
        ?.full_variant || selectedVariant
    );
  };

  const extractPrice = (priceString) => {
    const match = priceString?.match(/[\d,]+/);
    return match ? match[0].replace(/,/g, "") : "0";
  };

  // Add-to-cart stays disabled until every axis the product defines is chosen.
  const isAddToCartEnabled = () => {
    const hasVariantOptions =
      productData?.choice_options && productData.choice_options.length > 0;
    const hasColorOptions =
      productData?.colors_name && productData.colors_name.length > 0;

    if (hasVariantOptions && hasColorOptions) {
      return selectedVariant !== "" && selectedColor !== "";
    }
    if (hasVariantOptions) return selectedVariant !== "";
    if (hasColorOptions) return selectedColor !== "";
    return true;
  };

  const handleCloseModal = () => dispatch(closeCartModal());
  const handleAddToCartSuccess = () => handleCloseModal();
  const handleBuyNowSuccess = () => handleCloseModal();

  const handleAddToCartError = (msg) => {
    setError(msg);
    toast.error(msg);
  };

  const handleBuyNowError = (msg) => {
    setError(msg);
    toast.error(msg);
  };

  // Clearing state on close stops the previous product flashing behind the
  // next one the dialog opens for.
  useEffect(() => {
    if (!isOpen) {
      setProductData(null);
      setSelectedVariant("");
      setSelectedColor("");
      setQuantity(1);
      setError("");
      setThumbsSwiper(null);
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const getAllGalleryImages = () => {
    const images = [];
    if (productData?.thumbnail_image) {
      images.push({
        id: "thumbnail",
        src: productData.thumbnail_image,
        alt: productData.name || "Product Image",
      });
    }
    if (productData?.photos && Array.isArray(productData.photos)) {
      productData.photos.forEach((photo, index) => {
        const photoPath = typeof photo === "string" ? photo : photo.path || photo.url;
        if (photoPath) {
          images.push({
            id: `photo-${index}`,
            src: photoPath,
            alt: `${productData.name} - Image ${index + 2}`,
          });
        }
      });
    }
    return images;
  };

  const galleryImages = productData ? getAllGalleryImages() : [];
  const currentStock = productData ? getCurrentStock() : 0;
  const currentPrice = productData ? extractPrice(productData.main_price) : "0";
  const isSelectionEnabled = isAddToCartEnabled();
  const hasVariants =
    productData?.choice_options && productData.choice_options.length > 0;
  const hasColors = productData?.colors_name && productData.colors_name.length > 0;
  const showDiscount = productData?.discount && productData.discount !== "-0%";
  // Sold out is a property of the product, not of whether a size was picked
  // yet. Gating this on `selectedVariant` let a fully sold-out product open
  // with a live "Order now" button, contradicting its own product page. The
  // same expression runs on the page, so the two can no longer disagree.
  const isOutOfStock =
    !productData || !hasAnyStock(productData) || currentStock <= 0;

  const cartProduct = productData
    ? {
        id: productData.id,
        quantity: quantity,
        variant: selectedVariant || "",
        color: selectedColor || "",
        current_stock: currentStock,
        name: productData.name,
        price: parseFloat(currentPrice),
        image: productData.thumbnail_image,
        full_variant: getFullVariant(),
      }
    : null;

  return (
    <Modal
      open={isOpen}
      onClose={handleCloseModal}
      size="xl"
      showClose={false}
      bodyClassName="p-0"
      // Wider than the `xl` preset: at two columns a 672px panel leaves the
      // gallery about 330px, which is narrower than the card it opened from.
      className="sm:max-w-3xl lg:max-w-4xl"
    >
      {/* Two columns from `md` up: gallery left, detail right. Below that the
          two stack, so the dialog stays a single scrolling column on phones. */}
      <div className="grid grid-cols-1 md:max-h-[85dvh] md:grid-cols-2">
      {/* ── Gallery ────────────────────────────────────────────────────────
          The panel's own close button is rendered over the gallery instead of
          in the Modal header: a header strip would push the image down and
          crop the discount and wishlist controls pinned to its top corners. */}
      <div className="relative aspect-square w-full overflow-hidden rounded-t-2xl bg-canvas-sunken md:aspect-4/5 md:h-full md:rounded-l-2xl md:rounded-tr-none">
        {loading ? (
          <Skeleton className="h-full w-full" rounded="sm" />
        ) : galleryImages.length > 0 ? (
          swiperReady ? (
            <Swiper
              modules={[Navigation, Pagination, Thumbs]}
              pagination={{ clickable: true }}
              navigation={{
                nextEl: ".cart-modal-next",
                prevEl: ".cart-modal-prev",
              }}
              thumbs={{ swiper: thumbsSwiper }}
              spaceBetween={0}
              slidesPerView={1}
              className="h-full w-full"
            >
              {galleryImages.map((image) => (
                <SwiperSlide key={image.id}>
                  <div className="relative h-full w-full">
                    <Image
                      src={image.src}
                      alt={image.alt}
                      fill
                      className="object-cover"
                      sizes="(max-width: 768px) 100vw, 512px"
                      onError={(e) => {
                        e.currentTarget.style.display = "none";
                      }}
                    />
                  </div>
                </SwiperSlide>
              ))}
            </Swiper>
          ) : (
            // First frame rendered flat while Swiper initialises, so the panel
            // never shows an empty box.
            <div className="relative h-full w-full">
              <Image
                src={galleryImages[0].src}
                alt={galleryImages[0].alt}
                fill
                className="object-cover"
                sizes="(max-width: 768px) 100vw, 512px"
              />
            </div>
          )
        ) : (
          <div className="flex h-full w-full items-center justify-center">
            <ImageOff size={30} className="text-ink-quaternary" strokeWidth={1.5} />
          </div>
        )}

        {/* Prev / next. Only worth showing when there is more than one frame.
            The gallery clips its overflow, so the shared arrows are pulled back
            inside the well rather than sitting outside it as they do in the
            home-page rails. */}
        {!loading && galleryImages.length > 1 && (
          <CarouselNav
            name="cart-modal"
            className="mx-3 h-9! w-9! translate-x-0! md:translate-x-0!"
          />
        )}

        <div className="pointer-events-none absolute left-4 top-4 z-20">
          {isOutOfStock ? (
            <span className="badge badge-neutral shadow-e1">Out of stock</span>
          ) : (
            showDiscount && (
              <span className="badge badge-sale shadow-e1">{productData.discount}</span>
            )
          )}
        </div>

        <div className="absolute right-4 top-4 z-20 flex items-center gap-2">
          {productData && (
            <WishlistButton productId={productData.id} className="shadow-e2" iconSize={17} />
          )}
          <button
            type="button"
            onClick={handleCloseModal}
            aria-label="Close dialog"
            className="btn-icon btn-icon-sm btn-icon-round bg-surface/85 shadow-e2 backdrop-blur-sm"
          >
            <X size={18} aria-hidden="true" />
          </button>
        </div>
      </div>

      {/* ── Detail ─────────────────────────────────────────────────────────*/}
      <div className="px-6 pb-6 pt-5 md:overflow-y-auto md:px-7 md:pt-7">
      {loading ? (
        <div className="space-y-3">
          <Skeleton className="h-4 w-3/4" />
          <Skeleton className="h-5 w-28" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-11 w-full" />
        </div>
      ) : productData ? (
        <div className="space-y-5">
          <div>
            <h2 className="type-headline text-ink">{productData.name}</h2>
            <div className="mt-1.5 flex items-baseline gap-2">
              <span className="type-title-3 tabular text-ink-secondary">
                {tkText(productData.main_price)}
              </span>
              {productData.stroked_price && (
                <span className="type-subhead tabular text-ink-tertiary line-through">
                  {tkText(productData.stroked_price)}
                </span>
              )}
            </div>
          </div>

          {hasVariants && (
            <div>
              <p className="type-eyebrow mb-2 text-ink-tertiary">
                {productData.choice_options[0]?.title || "Size"}
              </p>
              <div className="flex flex-wrap gap-2">
                {productData.choice_options[0].options.map((option) => (
                  <button
                    key={option.value}
                    type="button"
                    onClick={() => handleVariantSelect(option.value)}
                    disabled={!option.in_stock}
                    aria-pressed={selectedVariant === option.value}
                    title={!option.in_stock ? "Out of stock" : option.value}
                    className={`chip h-10 min-w-10 ${
                      selectedVariant === option.value ? "chip-selected" : ""
                    }`}
                  >
                    {option.value}
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Sits under the size chips, where the sizing question is asked.
              Compact: the modal panel is narrower than the product page, so
              the chart drops its images and description here. */}
          {/* <SizeGuide
            sizeChart={productData.size_chart}
            selectedVariant={selectedVariant}
            compact
          /> */}

          {hasColors && (
            <div>
              <p className="type-eyebrow mb-2 text-ink-tertiary">Colour</p>
              <div className="flex flex-wrap gap-2.5">
                {productData.colors_name.map((color) => (
                  <button
                    key={color.code}
                    type="button"
                    onClick={() => handleColorSelect(color)}
                    title={color.name}
                    aria-label={`Colour: ${color.name}`}
                    aria-pressed={selectedColor === color.code}
                    className="h-7 w-7 rounded-full transition-transform duration-150 hover:scale-110"
                    style={{
                      backgroundColor: color.code,
                      boxShadow:
                        selectedColor === color.code
                          ? "0 0 0 2px var(--surface), 0 0 0 4px var(--ink)"
                          : "inset 0 0 0 1px rgba(0,0,0,0.12)",
                    }}
                  />
                ))}
              </div>
            </div>
          )}

          <div>
            <p className="type-eyebrow mb-2 text-ink-tertiary">Quantity</p>
            <div className="inline-flex items-center rounded-md border border-hairline">
              <button
                type="button"
                onClick={() => setQuantity(Math.max(1, quantity - 1))}
                disabled={!isSelectionEnabled || quantity <= 1}
                className="flex h-9 w-9 items-center justify-center rounded-l-md text-ink-secondary transition-colors hover:bg-surface-hover disabled:opacity-30"
                aria-label="Decrease quantity"
              >
                <Minus size={15} aria-hidden="true" />
              </button>

              <span className="tabular w-10 text-center text-[0.875rem] font-semibold text-ink">
                {quantity}
              </span>

              <button
                type="button"
                onClick={() => setQuantity(Math.min(currentStock, quantity + 1))}
                disabled={!isSelectionEnabled || quantity >= currentStock}
                className="flex h-9 w-9 items-center justify-center rounded-r-md text-ink-secondary transition-colors hover:bg-surface-hover disabled:opacity-30"
                aria-label="Increase quantity"
              >
                <Plus size={15} aria-hidden="true" />
              </button>
            </div>
          </div>

          {error && (
            <p role="alert" className="type-footnote flex items-center gap-1.5 text-danger">
              <AlertCircle size={14} aria-hidden="true" />
              {error}
            </p>
          )}

          {isOutOfStock ? (
            <button type="button" className="btn btn-primary btn-lg w-full" disabled>
              Out of stock
            </button>
          ) : (
            <div className="flex gap-2.5">
              <div className="flex-1">
                <BuyNowButton
                  product={cartProduct}
                  hasVariants={hasVariants}
                  hasColors={hasColors}
                  selectedVariant={selectedVariant}
                  selectedColor={selectedColor}
                  onError={handleBuyNowError}
                  onSuccess={handleBuyNowSuccess}
                  disabled={!isSelectionEnabled}
                />
              </div>

              <div className="w-12 shrink-0">
                <AddToCartButton
                  product={cartProduct}
                  hasVariants={hasVariants}
                  hasColors={hasColors}
                  selectedVariant={selectedVariant}
                  selectedColor={selectedColor}
                  onError={handleAddToCartError}
                  onSuccess={handleAddToCartSuccess}
                  disabled={!isSelectionEnabled}
                  iconOnly
                />
              </div>
            </div>
          )}
        </div>
      ) : error ? (
        <div className="py-8 text-center">
          <AlertCircle size={26} className="mx-auto mb-3 text-ink-tertiary" strokeWidth={1.5} />
          <p className="type-callout mb-5 text-ink-secondary">{error}</p>
          <Button onClick={handleCloseModal} variant="secondary">
            Close
          </Button>
        </div>
      ) : null}
      </div>
      </div>
    </Modal>
  );
}
