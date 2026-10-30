"use client";

/**
 * Product detail panel — title, price, options, quantity, purchase actions.
 *
 * Laid out after the reference design: a category tag and star rating over a
 * serif title, a bold price with its struck-through original and a "Save"
 * badge, a boxed trust grid, the quantity stepper with a live subtotal, then
 * Add to Cart (outlined), Express Order (gold) and a wishlist / share pair.
 *
 * Colour and size selectors sit between the price and the trust grid, in the
 * same label-then-value rhythm as the quantity row. Out-of-stock variants stay
 * visible and struck through rather than being hidden, so a shopper can see the
 * size exists.
 *
 * Affiliate click tracking, variant stock resolution, and the cart and
 * buy-now payloads are unchanged.
 */

import { useEffect, useMemo, useState } from "react";
import { useParams, useSearchParams } from "next/navigation";
import toast from "react-hot-toast";
import {
  Check,
  CreditCard,
  Minus,
  Plus,
  RotateCcw,
  Share2,
  ShieldCheck,
  Star,
  Truck,
  Zap,
} from "lucide-react";

import AddToCartButton from "@/components/cart/AddToCartButton";
import BuyNowButton from "@/components/cart/BuyNowButton";
import WishlistButton from "../components/WishlistButton";
import WhatsappBtn from "@/components/WhatsappBtn";
import ProductShareButton from "@/components/ProductShareButton";
import SizeGuide from "./SizeGuide";
import { parseAmount, toTk, tkText } from "@/utils/currency";
import {
  firstAvailableVariant,
  hasAnyStock,
  resolveVariantStock,
} from "@/utils/stock";

// Reassurances under the price. The same four points appear on the delivery
// tab, so a change to the policy means changing both.
const TRUST_POINTS = [
  { icon: Truck, text: "Free Nationwide Express Delivery" },
  { icon: ShieldCheck, text: "100% Premium Quality Guaranteed" },
  { icon: RotateCcw, text: "7-Day Hassle-Free Returns" },
  { icon: CreditCard, text: "Cash on Delivery (Inspect First)" },
];

// The shared Button hands its icon a size, so the bolt is a component rather
// than an element: lucide's Zap with the gold-on-gold fill the reference uses.
const ExpressIcon = (props) => (
  <Zap {...props} className="fill-current text-amber-200" />
);

export default function Details({
  productData,
  selectedColor,
  selectedVariant,
  onColorChange,
  onVariantChange,
}) {
  const [quantity, setQuantity] = useState(1);
  const [user, setUser] = useState(null);
  const [variantStock, setVariantStock] = useState(null);
  const [linkCopied, setLinkCopied] = useState(false);
  const params = useParams();
  const searchParams = useSearchParams();

  // A shared product link carries the sharer's referral code; it is stored for
  // a day so the credit survives the shopper browsing before ordering.
  useEffect(() => {
    const productReferralCode = searchParams.get("product_referral_code");
    if (productReferralCode) {
      document.cookie = `product_referral_code=${productReferralCode}; max-age=86400; path=/; SameSite=Lax`;
      trackAffiliateClick(productReferralCode);
    }
  }, [searchParams]);

  const trackAffiliateClick = async (referralCode) => {
    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || "/api/v2";
      await fetch(`${apiUrl}/affiliate/track-click`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ referral_code: referralCode }),
      });
    } catch (error) {
      console.error("Failed to track affiliate click:", error);
    }
  };

  useEffect(() => {
    if (typeof window !== "undefined") {
      const userData = localStorage.getItem("user");
      if (userData) {
        try {
          setUser(JSON.parse(userData));
        } catch (e) {
          console.error("Error parsing user data:", e);
        }
      }
    }
  }, []);

  // Memoised so the effects below get a stable dependency: rebuilding these
  // arrays on every render re-ran the auto-select effects in a loop.
  const colors = useMemo(() => productData?.colors_name || [], [productData]);
  const variantOptions = useMemo(
    () =>
      productData?.choice_options?.length > 0
        ? productData.choice_options[0]?.options || []
        : [],
    [productData],
  );

  // Switching variant or colour can drop the ceiling below the chosen
  // quantity.
  //
  // `quantity` is deliberately not a dependency: the effect writes it, so
  // listing it made every clamp re-run the effect.
  useEffect(() => {
    if (!productData) return;
    if (selectedVariant && productData.variant_stocks) {
      const available =
        resolveVariantStock(productData, selectedVariant, selectedColor)
          ?.quantity || 0;
      setVariantStock(available);
      setQuantity((current) => (current > available ? 1 : current));
    } else {
      setVariantStock(productData.current_stock);
    }
  }, [selectedVariant, selectedColor, productData]);

  useEffect(() => {
    if (colors.length > 0 && !selectedColor) onColorChange(colors[0].code);
  }, [colors, selectedColor, onColorChange]);

  // Land on the first variant a shopper can actually buy.
  useEffect(() => {
    if (variantOptions.length > 0 && !selectedVariant) {
      const first = firstAvailableVariant(productData);
      if (first) onVariantChange(first);
    }
  }, [variantOptions, selectedVariant, productData, onVariantChange]);

  // Every hook runs above this guard, so hook order stays stable across the
  // render where `productData` is absent.
  if (!productData) return null;

  const extractPrice = (priceString) => {
    const match = priceString?.match(/[\d,]+/);
    return match ? match[0].replace(/,/g, "") : "0";
  };

  const currentPrice = extractPrice(productData.main_price);

  // The API repeats the price in `stroked_price` for an undiscounted product,
  // so a struck-through figure only appears when it is genuinely higher.
  const strokedAmount = parseAmount(productData.stroked_price);
  const hasDiscount = strokedAmount > parseFloat(currentPrice);
  const savedAmount = hasDiscount ? strokedAmount - parseFloat(currentPrice) : 0;

  const currentStock = variantStock ?? productData.current_stock;

  // Sold out is a property of the product, not of the resolution order of the
  // effects above. Before a variant resolves, `currentStock` is still the
  // product total, so asking whether *any* variant is buyable avoids a
  // first-paint flash of "Currently unavailable" on a stocked product.
  const productHasStock = hasAnyStock(productData);
  const isOutOfStock = !productHasStock || currentStock <= 0;
  const isLowStock = !isOutOfStock && currentStock <= 5;

  // Resolved through the colour+size pair, so `full_variant` on the cart
  // payload names the exact stock row rather than the size rollup.
  const selectedVariantStock = resolveVariantStock(
    productData,
    selectedVariant,
    selectedColor,
  );

  // Both purchase buttons send the same payload.
  const cartProduct = {
    id: productData.id,
    user_id: user?.id || null,
    quantity: quantity,
    variant: selectedVariant || "",
    color: selectedColor || "",
    current_stock: currentStock,
    name: productData.name,
    price: parseFloat(currentPrice),
    calculable_price: parseFloat(currentPrice),
    main_price: productData.main_price,
    image: productData.thumbnail_image,
    full_variant: selectedVariantStock?.full_variant || selectedVariant || "",
    category_name: productData.category_name || productData.category?.name,
    brand_name: productData.brand?.name,
    shop_name: productData.shop_name,
    // Product-level SKU, kept for callers that already read it.
    sku: productData.sku,
    // Id of the exact stock row the shopper selected. This is the id the
    // catalogue feed publishes as <g:id>, so tracking must send this rather
    // than the product id or dynamic ads have nothing to match against. The
    // SKU column cannot serve this purpose: it is null on most rows and
    // repeats across unrelated products where it is set.
    variant_stock_id: selectedVariantStock?.stock_id || null,
  };

  const activeColorName = colors.find((c) => c.code === selectedColor)?.name;
  const variantTitle = productData.choice_options?.[0]?.title || "Variant";
  const skuLabel = selectedVariantStock?.sku || productData.sku;

  const categoryName = productData.category_name || productData.category?.name;
  const rating = Number(productData.rating) || 0;
  const ratingCount = Number(productData.rating_count) || 0;
  const tags = Array.isArray(productData.tags) ? productData.tags.filter(Boolean) : [];

  // The page address without the visitor's own query string, so a shared link
  // never carries somebody else's referral code.
  const copyProductLink = async () => {
    try {
      await navigator.clipboard.writeText(
        `${window.location.origin}${window.location.pathname}`,
      );
      setLinkCopied(true);
      setTimeout(() => setLinkCopied(false), 2000);
    } catch {
      toast.error("Could not copy the link");
    }
  };

  const optionLabel = "text-xs font-medium text-stone-700";

  return (
    <div className="lg:col-span-6">
      <div className="space-y-6 lg:sticky lg:top-[calc(var(--header-height,80px)+24px)]">
        {/* ── Title and price ────────────────────────────────────────────── */}
        <div className="space-y-2 border-b border-stone-200/60 pb-5">
          <div className="flex flex-wrap items-center gap-2">
            {categoryName && (
              <span className="rounded-sm bg-amber-50 px-2 py-0.5 text-xs font-semibold uppercase tracking-wider text-amber-800">
                {categoryName}
              </span>
            )}

            {rating > 0 && (
              <div className="flex items-center text-xs text-amber-500">
                <Star className="h-3.5 w-3.5 fill-current" aria-hidden="true" />
                <span className="ml-1 font-semibold text-stone-800">
                  {rating.toFixed(1)}
                </span>
                <span className="ml-1 text-stone-400">
                  ({ratingCount} {ratingCount === 1 ? "review" : "reviews"})
                </span>
              </div>
            )}
          </div>

          <h1 className="type-card-serif text-pretty text-2xl text-stone-900 sm:text-3xl md:text-4xl">
            {productData.name}
          </h1>

          <div className="flex flex-wrap items-baseline gap-3 pt-1">
            <span className="tabular text-2xl font-bold text-stone-950 sm:text-3xl">
              {toTk(parseFloat(currentPrice))}
            </span>

            {hasDiscount && (
              <>
                <span className="tabular text-base text-stone-400 line-through sm:text-lg">
                  {tkText(productData.stroked_price)}
                </span>
                <span className="tabular rounded-sm border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                  Save {toTk(savedAmount)}
                </span>
              </>
            )}
          </div>
        </div>

        {/* ── Colour ─────────────────────────────────────────────────────────
            Label and the selected value share one row, so the current choice
            reads without scanning the swatches. */}
        {colors.length > 0 && (
          <div className="space-y-2.5">
            <p className={optionLabel}>
              Colour:
              {activeColorName && (
                <span className="ml-1.5 font-semibold text-stone-900">
                  {activeColorName}
                </span>
              )}
            </p>

            <div className="flex flex-wrap gap-3 p-0.5">
              {colors.map((color) => {
                const isSelected = selectedColor === color.code;
                return (
                  <button
                    key={color.code}
                    type="button"
                    onClick={() => onColorChange(color.code)}
                    title={color.name}
                    aria-label={`Colour: ${color.name}`}
                    aria-pressed={isSelected}
                    className={`h-8 w-8 cursor-pointer rounded-full transition-transform hover:scale-110 ${
                      isSelected
                        ? "ring-2 ring-amber-700 ring-offset-2 ring-offset-cream"
                        : "ring-1 ring-inset ring-black/15"
                    }`}
                    style={{ backgroundColor: color.code }}
                  />
                );
              })}
            </div>
          </div>
        )}

        {/* ── Size guide ─────────────────────────────────────────────────────
            Sits directly above the size chips, where the question is asked.
            Renders nothing when the product's category tree has no chart. */}
        {/* <SizeGuide
          sizeChart={productData.size_chart}
          selectedVariant={selectedVariant}
        /> */}

        {/* ── Variant ────────────────────────────────────────────────────────
            Out-of-stock values stay visible and struck through, so a shopper
            can see the size exists rather than assuming it was never made. */}
        {variantOptions.length > 0 && (
          <div className="space-y-2.5">
            <p className={optionLabel}>
              {variantTitle}:
              {selectedVariant && (
                <span className="ml-1.5 font-semibold text-stone-900">
                  {selectedVariant}
                </span>
              )}
            </p>

            <div className="flex flex-wrap gap-2">
              {variantOptions.map((option) => {
                const variantValue =
                  typeof option === "string" ? option : option.value;
                const isInStock =
                  typeof option === "string"
                    ? productData.variant_stocks?.[variantValue]?.quantity > 0
                    : option.in_stock;
                const isSelected = selectedVariant === variantValue;

                return (
                  <button
                    key={variantValue}
                    type="button"
                    onClick={() => isInStock && onVariantChange(variantValue)}
                    disabled={!isInStock}
                    aria-pressed={isSelected}
                    title={!isInStock ? "Out of stock" : variantValue}
                    className={`h-10 min-w-12 cursor-pointer rounded-xl border px-4 text-sm! font-medium transition-colors ${
                      isSelected
                        ? "border-amber-700 bg-amber-50 font-semibold text-amber-900"
                        : "border-stone-300 bg-white text-stone-700 hover:border-stone-500"
                    } ${
                      !isInStock
                        ? "cursor-not-allowed line-through opacity-40 hover:border-stone-300"
                        : ""
                    }`}
                  >
                    {variantValue}
                  </button>
                );
              })}
            </div>
          </div>
        )}

        {/* ── Trust grid ─────────────────────────────────────────────────── */}
        {/* <div className="rounded-xl border border-stone-200/80 bg-white/80 p-4 text-xs text-stone-700 shadow-xs">
          <ul className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
            {TRUST_POINTS.map(({ icon: Icon, text }) => (
              <li key={text} className="flex items-center gap-2">
                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-800">
                  <Icon className="h-3.5 w-3.5" aria-hidden="true" />
                </span>
                <span>{text}</span>
              </li>
            ))}
          </ul>
        </div> */}

        {/* ── Quantity ───────────────────────────────────────────────────────
            The stock note sits on the label row; it only turns amber when the
            number is low enough to change a decision. */}
        <div className="space-y-2">
          <div className="flex items-center justify-between text-xs">
            {isOutOfStock ? (
              <span className="font-medium text-stone-500">Currently unavailable</span>
            ) : (
              <>
                <span className={optionLabel}>Quantity:</span>
                <span
                  className={`font-medium ${
                    isLowStock ? "text-amber-700" : "text-emerald-700"
                  }`}
                >
                  {isLowStock
                    ? `Only ${currentStock} left`
                    : `In Stock (${Number(currentStock).toLocaleString("en-US")} available)`}
                </span>
              </>
            )}
          </div>

          {!isOutOfStock && (
            <div className="flex items-center gap-4">
              <div className="inline-flex items-center rounded-xl border border-stone-300 bg-white p-1 shadow-xs">
                <button
                  type="button"
                  onClick={() => setQuantity(Math.max(1, quantity - 1))}
                  disabled={quantity <= 1}
                  className="flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-stone-600 transition-colors hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-30"
                  aria-label="Decrease quantity"
                >
                  <Minus className="h-3.5 w-3.5" aria-hidden="true" />
                </button>

                <span className="tabular w-12 text-center text-sm font-semibold text-stone-900">
                  {quantity}
                </span>

                <button
                  type="button"
                  onClick={() => setQuantity(Math.min(currentStock, quantity + 1))}
                  disabled={quantity >= currentStock}
                  className="flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-stone-600 transition-colors hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-30"
                  aria-label="Increase quantity"
                >
                  <Plus className="h-3.5 w-3.5" aria-hidden="true" />
                </button>
              </div>

              <div className="text-xs text-stone-500">
                Subtotal:{" "}
                <strong className="tabular text-sm font-bold text-stone-900">
                  {toTk(parseFloat(currentPrice) * quantity)}
                </strong>
              </div>
            </div>
          )}
        </div>

        {/* ── Purchase ───────────────────────────────────────────────────────
            Add to Cart is outlined and Express Order is the gold, filled
            action, so the shopper reads which one skips the cart. */}
        <div className="space-y-3 pt-2">
          {!isOutOfStock ? (
            <>
              <AddToCartButton
                product={cartProduct}
                hasVariants={variantOptions.length > 0}
                hasColors={colors.length > 0}
                selectedVariant={selectedVariant}
                selectedColor={selectedColor}
                onError={(msg) => toast.error(msg)}
                label="Add to Cart"
                confirmAdded
                className="h-auto rounded-xl border-stone-800 px-6 py-3.5 text-sm! text-stone-900 shadow-xs hover:border-stone-800 hover:bg-stone-50"
                addedClassName="h-auto rounded-xl border-emerald-500 bg-emerald-50 px-6 py-3.5 text-sm! text-emerald-700 shadow-xs hover:border-emerald-500 hover:bg-emerald-50"
              />

              <BuyNowButton
                product={cartProduct}
                hasVariants={variantOptions.length > 0}
                hasColors={colors.length > 0}
                selectedVariant={selectedVariant}
                selectedColor={selectedColor}
                onError={(msg) => toast.error(msg)}
                label="Express Order (Cash on Delivery)"
                icon={ExpressIcon}
                className="h-auto rounded-xl bg-gold px-6 py-3.5 text-sm! shadow-md shadow-amber-950/20 hover:bg-gold-hover"
              />
            </>
          ) : (
            <button
              type="button"
              disabled
              className="btn btn-primary btn-lg w-full rounded-xl"
            >
              Out of stock
            </button>
          )}
        </div>

        {/* ── Secondary actions ──────────────────────────────────────────────
            The reference pairs the wishlist with a spec-comparison button. This
            store has no comparison feature, so the slot holds a copy-link
            share instead. */}
        <div className="grid grid-cols-2 gap-3 pt-1">
          <WishlistButton
            productId={productData.id}
            labelled
            iconSize={14}
            className="rounded-xl px-3 py-2.5"
          />

          <button
            type="button"
            onClick={copyProductLink}
            className="flex cursor-pointer items-center justify-center gap-1.5 rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-xs! font-medium text-stone-700 transition-colors hover:bg-stone-50"
          >
            {linkCopied ? (
              <Check className="h-3.5 w-3.5 text-emerald-600" aria-hidden="true" />
            ) : (
              <Share2 className="h-3.5 w-3.5" aria-hidden="true" />
            )}
            {linkCopied ? "Link Copied!" : "Share Product"}
          </button>
        </div>

        <WhatsappBtn
          whatsapp_number={productData?.whatsapp_number}
          product_name={productData?.name}
          className="rounded-xl! text-xs!"
        />
        <ProductShareButton
          productSlug={params.slug}
          className="rounded-xl! text-xs!"
        />

        {/* ── Meta ───────────────────────────────────────────────────────── */}
        <div className="space-y-1 border-t border-stone-200/60 pt-3 text-xs text-stone-500">
          {categoryName && (
            <p>
              Category:{" "}
              <span className="font-medium text-stone-800">{categoryName}</span>
            </p>
          )}
          {productData.brand?.name && (
            <p>
              Brand:{" "}
              <span className="font-medium text-stone-800">
                {productData.brand.name}
              </span>
            </p>
          )}
          {tags.length > 0 && (
            <p>
              Tags:{" "}
              <span className="font-medium text-stone-800">{tags.join(", ")}</span>
            </p>
          )}
          {skuLabel && (
            <p>
              SKU: <span className="font-medium text-stone-800">{skuLabel}</span>
            </p>
          )}
        </div>
      </div>
    </div>
  );
}
