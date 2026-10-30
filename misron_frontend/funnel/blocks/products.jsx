"use client";

import { useState } from "react";
import { Check, Minus, Plus } from "lucide-react";
import { useCommerce } from "../commerce/CommerceProvider.jsx";
import { fill, reasonText } from "../commerce/i18n.js";
import { cx, safeImageSrc } from "../core/values.js";
import { Text, rootWith } from "../render/parts.jsx";
import { responsiveImage } from "../render/images.js";
import { Icon } from "./icons.jsx";

/**
 * Product blocks: cards, selectors, bundles, gallery, price, variations,
 * quantity, discount badge and the order summary.
 *
 * None of them knows a price. Names, images, prices, stock and offer status
 * come from the server through the commerce context, and what a customer has
 * chosen lives there too. In the editor these blocks draw the same data but
 * ignore clicks, so a click selects the block for editing; Preview and the
 * live page make them interactive.
 */

// ── Shared pieces ────────────────────────────────────────────────────────

/** Placeholder shown where a block has no products yet. */
function NoProducts({ node, ctx, commerce }) {
  if (ctx.mode !== "editor") return null;
  return (
    <button
      type="button"
      className="flex min-h-[120px] w-full flex-col items-center justify-center gap-1 rounded-[8px] border-2 border-dashed border-[#c6c6cd] bg-white/70 px-4 py-6 text-center text-[14px] text-ink-secondary hover:border-[#1f6feb]"
      onClick={(event) => {
        event.stopPropagation();
        ctx.chooseProducts?.(node);
      }}
      data-testid="choose-products"
    >
      <strong className="text-[15px] text-ink">{commerce?.t.chooseProducts ?? "Choose products"}</strong>
      <span>Pick products from your store catalogue</span>
    </button>
  );
}

function Skeleton() {
  return <div className="h-[220px] animate-pulse rounded-[10px] bg-[#ebebed]" aria-hidden="true" />;
}

/** Offer price block for a card: current price, crossed-out price and savings. */
function Prices({ offer, variant, props, t, compact = false }) {
  const price = (variant && offer.variant_prices?.[variant]) || offer.price;
  if (!price) return null;
  const showOriginal = props.showOriginalPrice !== false;
  const showSavings = props.showSavings !== false;

  return (
    <div className={cx("flex flex-wrap items-baseline gap-x-2 gap-y-0.5", compact && "gap-x-1.5")}>
      <span className="fn-pprice text-[19px] font-extrabold leading-tight text-[var(--fn-accent)] @3xl:text-[21px]" data-testid="offer-price">
        {price.offer.text}
      </span>
      {price.has_discount && showOriginal ? (
        <del className="text-[13px] opacity-60" data-testid="regular-price">
          {price.regular.text}
        </del>
      ) : null}
      {price.has_discount && showSavings ? (
        <span className="fn-psave w-full text-[12px] font-semibold text-[#12805c]" data-testid="savings">
          {t.save} {price.savings.text}
          {price.savings_percent ? ` (${Math.round(price.savings_percent)}%)` : ""}
        </span>
      ) : null}
    </div>
  );
}

function StockLine({ offer, variant, t }) {
  const item = offer.items[0];
  if (!item || offer.items.length > 1) return null;
  const row = variant ? item.variants.find((v) => v.variant === variant) : null;
  const quantity = row ? row.quantity : item.stock_quantity;
  const text = quantity <= 0 ? t.outOfStock : quantity <= 5 ? fill(t.left, { n: quantity }) : t.inStock;
  return <span className={cx("text-[12px]", quantity <= 5 ? "text-[#c0392b]" : "text-[#12805c]")}>{text}</span>;
}

function VariantChips({ offer, selected, onPick, interactive }) {
  const item = offer.items[0];
  if (!item || offer.items.length > 1 || item.lock_variant || !item.has_variants) return null;
  return (
    <div className="flex flex-wrap gap-1.5" role="group" aria-label="Variation">
      {item.variants.map((row) => {
        const active = row.variant === selected;
        return (
          <button
            key={row.variant}
            type="button"
            disabled={row.quantity <= 0}
            aria-pressed={active}
            onClick={(event) => {
              event.stopPropagation();
              if (interactive) onPick(row.variant);
            }}
            className={cx(
              "rounded-full border px-2.5 py-1 text-[12px] font-medium leading-none transition-colors",
              active ? "border-[var(--fn-sel,var(--fn-accent))] bg-[var(--fn-sel,var(--fn-accent))] text-white" : "border-[#c6c6cd] bg-white text-inherit",
              row.quantity <= 0 && "line-through opacity-40",
            )}
            data-testid={`variant-${row.variant}`}
          >
            {row.variant || "—"}
          </button>
        );
      })}
    </div>
  );
}

function Stepper({ value, max, onChange, label }) {
  return (
    <div className="inline-flex items-center overflow-hidden rounded-[8px] border border-[#c6c6cd] bg-white" onClick={(event) => event.stopPropagation()}>
      <button type="button" className="inline-flex size-8 items-center justify-center disabled:opacity-30" onClick={() => onChange(value - 1)} disabled={value <= 1} aria-label={`${label} −`}>
        <Minus size={14} aria-hidden="true" />
      </button>
      <span className="min-w-8 text-center text-[14px] font-semibold tabular-nums" aria-live="polite" data-testid="quantity-value">
        {value}
      </span>
      <button type="button" className="inline-flex size-8 items-center justify-center disabled:opacity-30" onClick={() => onChange(value + 1)} disabled={value >= max} aria-label={`${label} +`}>
        <Plus size={14} aria-hidden="true" />
      </button>
    </div>
  );
}

function maxQuantity(offer) {
  const stock = Math.max(1, ...offer.items.map((item) => Math.floor((item.stock_quantity || 0) / Math.max(1, item.quantity))));
  return Math.max(1, Math.min(99, offer.max_per_order ?? 99, offer.items.length === 1 ? stock : 99));
}

/** Round (single choice) or square (multiple choice) mark of a card. */
function ChoiceMark({ kind, selected }) {
  return (
    <span
      className={cx(
        "inline-flex size-5 shrink-0 items-center justify-center border-2",
        kind === "radio" ? "rounded-full" : "rounded-[4px]",
        selected ? "border-[var(--fn-sel,var(--fn-accent))] bg-[var(--fn-sel,var(--fn-accent))] text-white" : "border-[#a1a1aa] bg-white",
      )}
      aria-hidden="true"
      data-testid="choice-mark"
      data-kind={kind}
    >
      {selected ? (kind === "radio" ? <span className="size-1.5 rounded-full bg-white" /> : <Check size={13} strokeWidth={3} />) : null}
    </span>
  );
}

/**
 * One offer as a card: used by the single product card, the grid and the
 * bundle selector.
 */
function OfferCard({ binding, offer, props, commerce, layout, kind, onChoose, children, showDescription }) {
  const { t, selection, interactive } = commerce;
  const key = binding.key;
  const chosen = selection[key];
  const selected = Boolean(chosen);
  const disabled = !offer.available;
  const display = binding.display ?? {};
  const item = offer.items[0];
  const variant = chosen?.variant ?? (item?.lock_variant ? item.variant : item?.variant);
  const imageSrc = safeImageSrc(display.image?.url) ?? (offer.items.length > 1 ? null : item?.image);
  const bundleImages = offer.items.map((i) => i.image).filter(Boolean).slice(0, 3);
  const horizontal = layout === "horizontal";
  const compact = layout === "compact";
  const description = display.description;

  const choose = () => {
    if (interactive && !disabled) onChoose(key);
  };

  return (
    <div
      className={cx(
        "fn-pcard group relative flex min-w-0 rounded-[12px] border-2 bg-white p-3 text-left text-[#131315] shadow-[0_1px_3px_rgba(19,19,21,.08)] transition-[border-color,box-shadow]",
        horizontal ? "flex-row gap-3" : "flex-col gap-2",
        compact && "p-2",
        selected ? "border-[var(--fn-sel,var(--fn-accent))] shadow-[0_0_0_1px_var(--fn-sel,var(--fn-accent))]" : "border-[#e2e2e6]",
        interactive && !disabled && "cursor-pointer",
        disabled && "opacity-70",
      )}
      data-fn-offer={key}
      data-selected={selected || undefined}
      data-available={!disabled}
      role={kind === "radio" ? "radio" : kind === "checkbox" ? "checkbox" : undefined}
      aria-checked={kind ? selected : undefined}
      aria-disabled={disabled || undefined}
      tabIndex={interactive && kind && !disabled ? 0 : undefined}
      onClick={choose}
      onKeyDown={(event) => {
        if (event.key === " " || event.key === "Enter") {
          event.preventDefault();
          choose();
        }
      }}
    >
      {display.badge ? (
        <span className="absolute left-2 top-2 z-[1] rounded-full bg-[var(--fn-accent)] px-2.5 py-0.5 text-[11px] font-bold leading-tight text-white shadow" data-testid="card-badge">
          {display.badge}
        </span>
      ) : null}
      {kind ? (
        <span className="absolute right-2 top-2 z-[1]">
          <ChoiceMark kind={kind} selected={selected} />
        </span>
      ) : null}

      <div className={cx("relative shrink-0 overflow-hidden rounded-[8px] bg-[#f4f4f5]", horizontal ? "w-[34%] max-w-[150px] self-start" : "w-full")} style={{ aspectRatio: horizontal ? "1 / 1" : "1 / 1" }}>
        {imageSrc ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img {...responsiveImage(imageSrc, 828)} sizes={horizontal ? "150px" : "(max-width: 640px) 50vw, 360px"} alt={item?.name ?? ""} className="size-full object-cover" loading="lazy" decoding="async" draggable={false} />
        ) : bundleImages.length ? (
          <div className="grid size-full grid-cols-2 gap-px">
            {bundleImages.map((src, index) => (
              // eslint-disable-next-line @next/next/no-img-element
              <img key={index} {...responsiveImage(src, 640)} sizes="(max-width: 640px) 25vw, 180px" alt="" className={cx("size-full object-cover", bundleImages.length === 3 && index === 0 && "row-span-2")} loading="lazy" decoding="async" draggable={false} />
            ))}
          </div>
        ) : null}
        {disabled ? (
          <span className="absolute inset-x-0 bottom-0 bg-black/65 py-1 text-center text-[12px] font-semibold text-white" data-testid="card-unavailable">
            {props.unavailableText || reasonText(t, offer.unavailable_reason)}
          </span>
        ) : null}
      </div>

      <div className="flex min-w-0 flex-1 flex-col gap-1.5">
        <h3 className={cx("fn-ptitle m-0 text-[14px] font-semibold leading-snug @3xl:text-[15px]", compact && "line-clamp-2 text-[13px]")} data-testid="card-title">
          {offer.title}
        </h3>
        {offer.items.length > 1 ? (
          <ul className="m-0 flex list-none flex-col gap-0.5 p-0 text-[12px] opacity-75">
            {offer.items.map((i, index) => (
              <li key={index}>
                {i.quantity} × {i.name}
                {i.variant ? ` (${i.variant})` : ""}
              </li>
            ))}
          </ul>
        ) : null}
        {showDescription && description && !compact ? <p className="m-0 text-[12px] leading-snug opacity-75">{description}</p> : null}

        <Prices offer={offer} variant={variant} props={props} t={t} compact={compact} />

        {offer.free_delivery || offer.gift ? (
          <div className="flex flex-wrap gap-1">
            {offer.free_delivery ? <Chip>{t.freeDelivery}</Chip> : null}
            {offer.gift ? <Chip>{t.freeGift}: {offer.gift.name}</Chip> : null}
            {offer.type === "bxgy" ? <Chip>{fill(t.getFree, { b: offer.buy_qty, g: offer.get_qty })}</Chip> : null}
          </div>
        ) : offer.type === "bxgy" ? (
          <div className="flex flex-wrap gap-1"><Chip>{fill(t.getFree, { b: offer.buy_qty, g: offer.get_qty })}</Chip></div>
        ) : null}

        {props.showStock && !disabled ? <StockLine offer={offer} variant={variant} t={t} /> : null}

        <VariantChips offer={offer} selected={variant} interactive={interactive} onPick={(v) => commerce.setVariant(key, v)} />

        {props.allowQuantity && selected && !disabled ? (
          <Stepper value={chosen.quantity} max={maxQuantity(offer)} onChange={(q) => commerce.setQuantity(key, q)} label={t.quantity} />
        ) : null}

        {children}
      </div>
    </div>
  );
}

function Chip({ children }) {
  return <span className="inline-flex items-center rounded-full bg-[#e6f4ef] px-2 py-0.5 text-[11px] font-semibold text-[#12805c]">{children}</span>;
}

function gridClasses(layout) {
  if (layout === "horizontal") return "grid-cols-1 @3xl:grid-cols-2";
  return "grid-cols-2 @3xl:grid-cols-3 @5xl:grid-cols-4";
}

// ── Blocks ───────────────────────────────────────────────────────────────

export function ProductCard({ node, props, ctx, rootProps }) {
  const commerce = useCommerce();
  const binding = node.productBindings?.[0];
  const offer = binding && commerce?.offers[binding.key];

  return (
    <div {...rootWith(rootProps, "fn-product-card mx-auto w-full max-w-[460px]")}>
      {!binding ? (
        <NoProducts node={node} ctx={ctx} commerce={commerce} />
      ) : !offer ? (
        commerce?.ready === false ? <Skeleton /> : null
      ) : (
        <OfferCard
          binding={binding}
          offer={offer}
          props={props}
          commerce={commerce}
          layout={props.layout === "horizontal" ? "horizontal" : "vertical"}
          kind={null}
          showDescription={props.showDescription}
          onChoose={commerce.select}
        >
          <button
            type="button"
            disabled={!offer.available}
            className="fn-btn mt-1 inline-flex w-full items-center justify-center rounded-[10px] bg-[var(--fn-accent)] px-4 py-2.5 text-[15px] font-semibold text-white disabled:opacity-50"
            onClick={(event) => {
              event.stopPropagation();
              if (!commerce.interactive) return;
              commerce.select(binding.key);
              if (props.ctaAction === "select_and_scroll") window.document.getElementById("checkout")?.scrollIntoView({ behavior: "smooth", block: "start" });
            }}
            data-testid="card-cta"
          >
            {commerce.selection[binding.key] ? (
              <>
                <Check size={16} className="mr-1.5" aria-hidden="true" />
                {commerce.t.selected}
              </>
            ) : (
              binding.display?.ctaText || props.ctaText || commerce.t.select
            )}
          </button>
        </OfferCard>
      )}
    </div>
  );
}

export function ProductOfferSelector({ node, props, ctx, rootProps }) {
  const commerce = useCommerce();
  const bindings = node.productBindings ?? [];
  const multiple = props.selectionMode === "multiple";
  const layout = props.cardLayout ?? "vertical";

  return (
    <div {...rootWith(rootProps, "fn-offer-selector flex flex-col gap-4")}>
      <Text ctx={ctx} node={node} path={["heading"]} as="h2" optional value={props.heading} placeholder="Choose your product"
        className="m-0 text-center text-[22px] font-bold leading-tight @3xl:text-[28px]" />
      {bindings.length === 0 ? (
        <NoProducts node={node} ctx={ctx} commerce={commerce} />
      ) : (
        <div className={cx("fn-grid grid gap-3 @3xl:gap-4", gridClasses(layout))} role={multiple ? "group" : "radiogroup"} data-testid="offer-grid">
          {bindings.map((binding) => {
            const offer = commerce?.offers[binding.key];
            return offer ? (
              <OfferCard
                key={binding.key}
                binding={binding}
                offer={offer}
                props={props}
                commerce={commerce}
                layout={layout}
                kind={multiple ? "checkbox" : "radio"}
                showDescription={props.showDescription}
                onChoose={commerce.select}
              />
            ) : commerce?.ready === false ? (
              <Skeleton key={binding.key} />
            ) : null;
          })}
        </div>
      )}
    </div>
  );
}

export function BundleOfferSelector({ node, props, ctx, rootProps }) {
  const commerce = useCommerce();
  const bindings = node.productBindings ?? [];

  return (
    <div {...rootWith(rootProps, "fn-bundle-selector flex flex-col gap-4")}>
      <Text ctx={ctx} node={node} path={["heading"]} as="h2" optional value={props.heading} placeholder="Choose your bundle"
        className="m-0 text-center text-[22px] font-bold leading-tight @3xl:text-[28px]" />
      {bindings.length === 0 ? (
        <NoProducts node={node} ctx={ctx} commerce={commerce} />
      ) : (
        <div className={cx("fn-grid grid gap-3 @3xl:gap-4", props.layout === "grid" ? "grid-cols-1 @3xl:grid-cols-2" : "grid-cols-1")} role="radiogroup">
          {bindings.map((binding) => {
            const offer = commerce?.offers[binding.key];
            return offer ? (
              <OfferCard
                key={binding.key}
                binding={binding}
                offer={offer}
                props={{ ...props, showDescription: true }}
                commerce={commerce}
                layout="horizontal"
                kind="radio"
                showDescription
                onChoose={commerce.select}
              />
            ) : commerce?.ready === false ? (
              <Skeleton key={binding.key} />
            ) : null;
          })}
        </div>
      )}
    </div>
  );
}

/** The offer a free-standing block follows, with a note for the editor when there is none. */
function useFollowed(props) {
  const commerce = useCommerce();
  if (!commerce) return { commerce: null, key: null, offer: null };
  const key = commerce.activeKey(props.offerKey);
  return { commerce, key, offer: key ? commerce.offers[key] : null };
}

function EditorHint({ ctx, children }) {
  return ctx.mode === "editor" ? <p className="m-0 rounded-[6px] bg-[#f4f4f5] px-3 py-2 text-center text-[13px] text-ink-tertiary">{children}</p> : null;
}

export function ProductGallery({ props, ctx, rootProps }) {
  const { offer } = useFollowed(props);
  const [index, setIndex] = useState(0);
  const images = offer?.items[0]?.images ?? [];
  const current = images[Math.min(index, Math.max(0, images.length - 1))];
  const side = props.layout === "thumbs_left";

  return (
    <div {...rootWith(rootProps, "fn-product-gallery")}>
      {!images.length ? (
        <EditorHint ctx={ctx}>Add a product block first. Its photos appear here.</EditorHint>
      ) : props.layout === "carousel" ? (
        <div className="flex snap-x snap-mandatory gap-2 overflow-x-auto pb-1">
          {images.map((src) => (
            // eslint-disable-next-line @next/next/no-img-element
            <img key={src} {...responsiveImage(src)} sizes="(max-width: 768px) 85vw, 480px" alt="" className="aspect-square w-[85%] shrink-0 snap-center rounded-[10px] object-cover @3xl:w-[48%]" loading="lazy" decoding="async" draggable={false} />
          ))}
        </div>
      ) : (
        <div className={cx("flex gap-2", side ? "flex-row" : "flex-col")}>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img {...responsiveImage(current)} sizes="(max-width: 768px) 92vw, 560px" fetchPriority="high" alt={offer.items[0].name ?? ""} className={cx("fn-img aspect-square rounded-[10px] bg-[#f4f4f5] object-cover", side ? "w-[80%]" : "w-full")} draggable={false} data-testid="gallery-main" />
          {images.length > 1 ? (
            <div className={cx("flex gap-2", side ? "w-[20%] flex-col" : "flex-row overflow-x-auto")}>
              {images.map((src, i) => (
                <button
                  key={src}
                  type="button"
                  className={cx("shrink-0 overflow-hidden rounded-[6px] border-2 p-0", i === index ? "border-[var(--fn-accent)]" : "border-transparent", side ? "w-full" : "size-16")}
                  onClick={(event) => {
                    event.stopPropagation();
                    setIndex(i);
                  }}
                  aria-label={`Photo ${i + 1}`}
                  aria-pressed={i === index}
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img {...responsiveImage(src, 256)} sizes="128px" alt="" className="aspect-square size-full object-cover" loading="lazy" decoding="async" draggable={false} />
                </button>
              ))}
            </div>
          ) : null}
        </div>
      )}
    </div>
  );
}

export function ProductPrice({ props, ctx, rootProps }) {
  const { commerce, key, offer } = useFollowed(props);
  const variant = key ? commerce.selection[key]?.variant ?? offer?.items[0]?.variant : null;
  return (
    <div {...rootWith(rootProps, "fn-product-price")}>
      {offer?.price ? (
        <Prices offer={offer} variant={variant} props={{ showOriginalPrice: props.showOriginal, showSavings: props.showSavings }} t={commerce.t} />
      ) : (
        <EditorHint ctx={ctx}>The price appears here once a product block is on the page.</EditorHint>
      )}
    </div>
  );
}

export function ProductVariations({ props, ctx, rootProps }) {
  const { commerce, key, offer } = useFollowed(props);
  const item = offer?.items[0];
  const usable = item && offer.items.length === 1 && item.has_variants && !item.lock_variant;
  const selected = key ? commerce.selection[key]?.variant ?? item?.variant : null;

  return (
    <div {...rootWith(rootProps, "fn-product-variations flex flex-col gap-1.5")}>
      {usable ? (
        <>
          <span className="text-[13px] font-semibold">{props.label || commerce.t.chooseVariation}</span>
          {props.display === "dropdown" ? (
            <select
              className="h-10 rounded-[8px] border border-[#c6c6cd] bg-white px-3 text-[14px]"
              value={selected ?? ""}
              disabled={!commerce.interactive}
              onChange={(event) => commerce.setVariant(key, event.target.value)}
              aria-label={props.label || commerce.t.chooseVariation}
            >
              {item.variants.map((row) => (
                <option key={row.variant} value={row.variant} disabled={row.quantity <= 0}>
                  {row.variant}
                  {row.quantity <= 0 ? ` — ${commerce.t.outOfStock}` : ""}
                </option>
              ))}
            </select>
          ) : (
            <VariantChips offer={offer} selected={selected} interactive={commerce.interactive} onPick={(v) => commerce.setVariant(key, v)} />
          )}
        </>
      ) : (
        <EditorHint ctx={ctx}>Shows variations (size, colour…) of the offer it follows, when that product has them.</EditorHint>
      )}
    </div>
  );
}

export function QuantitySelector({ props, ctx, rootProps }) {
  const { commerce, key, offer } = useFollowed(props);
  const value = key ? commerce.selection[key]?.quantity ?? 1 : 1;
  return (
    <div {...rootWith(rootProps, "fn-quantity flex items-center gap-3")}>
      {offer ? (
        <>
          <span className="text-[14px] font-semibold">{props.label || commerce.t.quantity}</span>
          <Stepper value={value} max={Math.min(props.max ?? 99, maxQuantity(offer))} onChange={(q) => commerce.interactive && commerce.setQuantity(key, q)} label={commerce.t.quantity} />
        </>
      ) : (
        <EditorHint ctx={ctx}>A quantity stepper for the offer it follows.</EditorHint>
      )}
    </div>
  );
}

export function DiscountBadge({ props, ctx, rootProps }) {
  const { commerce, offer } = useFollowed(props);
  const price = offer?.price;
  let text = props.text;
  if (!text && price?.has_discount) {
    text = price.savings_percent >= 1 ? `-${Math.round(price.savings_percent)}%` : `${price.savings.text} ${commerce.t.off}`;
  }
  if (!text) return ctx.mode === "editor" ? <EditorHint ctx={ctx}>Shows the discount of the offer it follows (hidden when there is none). Type text to override.</EditorHint> : null;

  const shape = props.shape === "ribbon" ? "rounded-[4px] px-4" : props.shape === "circle" ? "size-16 justify-center rounded-full text-center" : "rounded-full px-3.5";
  return (
    <div {...rootWith(rootProps, "fn-discount-badge")}>
      <span className={cx("fn-dbadge inline-flex items-center bg-[#c0392b] py-1.5 text-[14px] font-extrabold leading-none text-white", shape)} data-testid="discount-badge">
        {text}
      </span>
    </div>
  );
}

function SummaryRow({ label, value, strong, muted }) {
  return (
    <div className={cx("flex items-baseline justify-between gap-3", strong ? "text-[16px] font-extrabold" : "text-[14px]", muted && "opacity-70")}>
      <span>{label}</span>
      <span className="tabular-nums" data-testid={`summary-${String(label).length}`}>
        {value}
      </span>
    </div>
  );
}

export function DiscountSummary({ node, props, ctx, rootProps }) {
  const commerce = useCommerce();
  const t = commerce?.t;
  const quote = commerce?.quote;
  const valid = quote?.valid;
  const totals = valid ? quote.totals : null;

  return (
    <div {...rootWith(rootProps, "fn-summary flex flex-col gap-2 rounded-[12px] border border-[#e2e2e6] bg-white p-4 text-[#131315]")} aria-live="polite" data-testid="offer-summary" data-updating={commerce?.quoting || undefined}>
      <Text ctx={ctx} node={node} path={["title"]} as="h3" optional value={props.title ?? t?.summary} placeholder="Order summary" className="m-0 text-[16px] font-bold" />
      {!commerce ? null : !quote ? (
        <p className="m-0 text-[14px] opacity-70">{commerce.ready ? props.emptyText || t.emptySummary : t.calculating}</p>
      ) : !valid ? (
        <ul className="m-0 flex list-none flex-col gap-1 p-0 text-[13px] text-[#c0392b]" data-testid="summary-errors">
          {quote.errors.map((error, index) => (
            <li key={index}>{error.message || t.priceError}</li>
          ))}
        </ul>
      ) : (
        <div className={cx("flex flex-col gap-1.5 transition-opacity", commerce.quoting && "opacity-60")}>
          {props.showItems !== false
            ? quote.lines.map((line) => (
                <div key={line.key} className="flex flex-col gap-0.5 border-b border-[#ececf0] pb-1.5 text-[13px]">
                  {line.items.map((item, index) => (
                    <div key={index} className="flex justify-between gap-3">
                      <span className="min-w-0 truncate">
                        {item.name}
                        {item.variant ? ` (${item.variant})` : ""} × {item.pieces}
                      </span>
                      <span className="tabular-nums">{item.total.text}</span>
                    </div>
                  ))}
                  {line.gift ? (
                    <div className="flex justify-between gap-3 text-[#12805c]">
                      <span className="min-w-0 truncate">
                        {t.freeGift}: {line.gift.name} × {line.gift.pieces}
                      </span>
                      <span>0</span>
                    </div>
                  ) : null}
                </div>
              ))
            : null}
          {totals.funnel_discount.amount > 0 ? (
            <>
              <SummaryRow label={t.websitePrice} value={totals.website_subtotal.text} muted />
              <SummaryRow label={t.discount} value={`− ${totals.funnel_discount.text}`} />
            </>
          ) : null}
          <SummaryRow label={t.subtotal} value={totals.product_subtotal.text} />
          {props.showTax !== false && totals.tax.amount > 0 ? <SummaryRow label={t.tax} value={totals.tax.text} /> : null}
          <SummaryRow label={t.delivery} value={totals.delivery.free ? t.freeDelivery : t.deliveryAtCheckout} muted={!totals.delivery.free} />
          <div className="mt-1 border-t border-[#d4d4d8] pt-2">
            <SummaryRow label={t.total} value={totals.total_before_delivery.text} strong />
          </div>
          {totals.total_savings.amount > 0 ? (
            <p className="m-0 flex items-center gap-1 text-[12px] font-semibold text-[#12805c]">
              <Icon name="badge-percent" size={14} /> {t.save} {totals.total_savings.text}
            </p>
          ) : null}
        </div>
      )}
    </div>
  );
}
