/**
 * Stock resolution shared by the product page and the quick-add dialog.
 *
 * The two used to disagree. The dialog treated "no size picked yet" as
 * in-stock and fell back to `current_stock` — the sum across every variant —
 * so a sold-out product opened with a live buy button. The page read
 * `variant_stocks[size]` directly, which the API keyed by size alone, so a
 * sold-out colour zeroed a size other colours still stocked. One product could
 * therefore read "5 available" in the dialog and "Currently unavailable" on its
 * own page.
 *
 * `variant_stocks` now carries both shapes: a per-size rollup summed across
 * colours ("XL"), and an exact entry per colour+size pair ("LightPink-XL").
 * Resolution prefers the exact pair and falls back to the rollup.
 */

/** Name of the selected colour, from its hex code. */
export function colorNameByCode(colorsName, code) {
  if (!code) return null;
  const found = (colorsName || []).find((c) => c.code === code);
  return found ? found.name : null;
}

/**
 * Stock entry for a variant, narrowed by colour when the product has colours.
 * Matching is case-insensitive because stored variants mix casing
 * ("LightPink-L" and "lightpurple-L" both occur).
 */
export function resolveVariantStock(productData, selectedVariant, selectedColor) {
  const stocks = productData?.variant_stocks;
  if (!stocks || !selectedVariant) return null;

  const colorName = colorNameByCode(productData?.colors_name, selectedColor);

  if (colorName) {
    const wanted = `${colorName}-${selectedVariant}`.toLowerCase();
    const exactKey = Object.keys(stocks).find(
      (key) => key.toLowerCase() === wanted,
    );
    if (exactKey) return stocks[exactKey];
  }

  return stocks[selectedVariant] || null;
}

/** Units available for the current selection. */
export function resolveStockQuantity(productData, selectedVariant, selectedColor) {
  if (!selectedVariant) return productData?.current_stock || 0;
  return (
    resolveVariantStock(productData, selectedVariant, selectedColor)?.quantity || 0
  );
}

/**
 * Whether any variant of the product can be bought. Used to decide the
 * sold-out state before a shopper has chosen anything, where per-variant
 * stock says nothing yet.
 */
export function hasAnyStock(productData) {
  const options = productData?.choice_options?.[0]?.options || [];
  if (options.length === 0) return (productData?.current_stock || 0) > 0;

  return options.some((option) =>
    typeof option === "string"
      ? productData?.variant_stocks?.[option]?.quantity > 0
      : option.in_stock,
  );
}

/** First variant a shopper can actually buy, or null when none can. */
export function firstAvailableVariant(productData) {
  const options = productData?.choice_options?.[0]?.options || [];
  const found = options.find((option) =>
    typeof option === "string"
      ? productData?.variant_stocks?.[option]?.quantity > 0
      : option.in_stock,
  );
  if (!found) return null;
  return typeof found === "string" ? found : found.value;
}
