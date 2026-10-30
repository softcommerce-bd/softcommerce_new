"use client";

/**
 * Shop filter panel — categories, price, colours, brands.
 *
 * Restyled on the design system. Two things that were broken are fixed while
 * passing through:
 *   · The selected-range highlight in the price slider had no background
 *     colour, so the track between the two thumbs was invisible.
 *   · The selected colour swatch drew a hard-coded purple ring (`#7c3aed`),
 *     unrelated to any brand colour.
 *
 * Checkboxes are now custom-drawn so they match the controls around them; the
 * native input stays in place behind them and keeps all its keyboard and
 * label behaviour.
 *
 * Every filter callback, URL parameter and limit rule is unchanged.
 */

import { useState, useEffect } from "react";
import { Check } from "lucide-react";

import FilterCard from "./FilterCard";
import { Button, Skeleton } from "../ui";

/** Checkbox row used by the category and brand lists. */
function CheckRow({ id, checked, onChange, label, count }) {
  return (
    <li>
      <label
        htmlFor={id}
        className="group flex cursor-pointer items-center gap-2.5 py-1.5"
      >
        <span className="relative flex h-4 w-4 shrink-0 items-center justify-center">
          <input
            type="checkbox"
            id={id}
            checked={checked}
            onChange={onChange}
            className="peer absolute inset-0 cursor-pointer opacity-0"
          />
          <span
            className="flex h-4 w-4 items-center justify-center rounded-sm border border-hairline-strong bg-surface transition-all duration-fast peer-checked:border-ink peer-checked:bg-ink peer-focus-visible:ring-2 peer-focus-visible:ring-brand peer-focus-visible:ring-offset-2"
            aria-hidden="true"
          >
            {checked && <Check size={11} strokeWidth={3} className="text-ink-inverse" />}
          </span>
        </span>

        <span className="type-subhead flex-1 text-ink-secondary transition-colors group-hover:text-ink">
          {label}
        </span>

        {count != null && (
          <span className="type-caption tabular text-ink-quaternary">{count}</span>
        )}
      </label>
    </li>
  );
}

export default function SidebarContent({
  categories = [],
  brands = [],
  colors = [],
  selectedCategory = "",
  minPrice = "",
  maxPrice = "",
  priceRangeMin = 0,
  priceRangeMax = 100000,
  onFilterChange,
  onClose,
  loading = false,
  currentProducts = [],
}) {
  const [priceMin, setPriceMin] = useState(
    minPrice ? parseInt(minPrice) : priceRangeMin,
  );
  const [priceMax, setPriceMax] = useState(
    maxPrice ? parseInt(maxPrice) : priceRangeMax,
  );
  const [selectedCategories, setSelectedCategories] = useState([]);
  const [selectedBrands, setSelectedBrands] = useState([]);
  const [selectedColors, setSelectedColors] = useState([]);
  const [selectedRating, setSelectedRating] = useState("");
  const [showAllColors, setShowAllColors] = useState(false);

  useEffect(() => {
    setPriceMin(minPrice ? parseInt(minPrice) : priceRangeMin);
  }, [minPrice, priceRangeMin]);

  useEffect(() => {
    setPriceMax(maxPrice ? parseInt(maxPrice) : priceRangeMax);
  }, [maxPrice, priceRangeMax]);

  useEffect(() => {
    setSelectedCategories(selectedCategory ? selectedCategory.split(",") : []);
  }, [selectedCategory]);

  // Brands, colours and rating are read straight from the URL on mount so a
  // shared or reloaded filter link restores its own checkbox state.
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);

    const brandsParam = params.get("brands");
    if (brandsParam) setSelectedBrands(brandsParam.split(","));

    const colorsParam = params.get("colors");
    if (colorsParam) setSelectedColors(colorsParam.split(","));

    const ratingParam = params.get("rating");
    if (ratingParam) setSelectedRating(ratingParam);
  }, []);

  const getMinPercent = () => {
    const range = priceRangeMax - priceRangeMin;
    if (range === 0) return 0;
    return ((priceMin - priceRangeMin) / range) * 100;
  };

  const getMaxPercent = () => {
    const range = priceRangeMax - priceRangeMin;
    if (range === 0) return 100;
    return ((priceMax - priceRangeMin) / range) * 100;
  };

  // The two thumbs are kept 100 apart so they cannot cross over each other.
  const handleMinChange = (e) => {
    let value = parseInt(e.target.value);
    const maxLimit = priceMax - 100;
    if (value > maxLimit) value = maxLimit;
    if (value < priceRangeMin) value = priceRangeMin;
    setPriceMin(value);
  };

  const handleMaxChange = (e) => {
    let value = parseInt(e.target.value);
    const minLimit = priceMin + 100;
    if (value < minLimit) value = minLimit;
    if (value > priceRangeMax) value = priceRangeMax;
    setPriceMax(value);
  };

  const handleMinInput = (e) => {
    let value = parseInt(e.target.value);
    if (isNaN(value)) value = priceRangeMin;
    if (value >= priceMax - 100) value = priceMax - 100;
    if (value < priceRangeMin) value = priceRangeMin;
    setPriceMin(value);
  };

  const handleMaxInput = (e) => {
    let value = parseInt(e.target.value);
    if (isNaN(value)) value = priceRangeMax;
    if (value <= priceMin + 100) value = priceMin + 100;
    if (value > priceRangeMax) value = priceRangeMax;
    setPriceMax(value);
  };

  const applyPriceFilter = () => {
    const min = priceMin >= priceRangeMin ? priceMin : priceRangeMin;
    const max = priceMax <= priceRangeMax ? priceMax : priceRangeMax;

    onFilterChange("price", { min, max });
    if (onClose) onClose();
  };

  const handleCategoryChange = (categoryId) => {
    const id = categoryId.toString();
    const updatedCategories = selectedCategories.includes(id)
      ? selectedCategories.filter((c) => c !== id)
      : [...selectedCategories, id];

    setSelectedCategories(updatedCategories);
    onFilterChange("category", updatedCategories.join(","));
  };

  const handleBrandChange = (brandId) => {
    const id = brandId.toString();
    const updatedBrands = selectedBrands.includes(id)
      ? selectedBrands.filter((b) => b !== id)
      : [...selectedBrands, id];

    setSelectedBrands(updatedBrands);
    onFilterChange("brand", updatedBrands.join(","));
  };

  const handleColorChange = (colorCode) => {
    if (!colorCode) return;

    const updatedColors = selectedColors.includes(colorCode)
      ? selectedColors.filter((code) => code !== colorCode)
      : [...selectedColors, colorCode];

    setSelectedColors(updatedColors);
    // The raw code is passed through; the caller handles URL encoding.
    onFilterChange("color", updatedColors.join(","));
  };

  const handleRatingChange = (rating) => {
    const newRating = selectedRating === rating ? "" : rating;
    setSelectedRating(newRating);
    onFilterChange("rating", newRating);
    if (onClose && newRating) onClose();
  };

  // Most-stocked colours first, so the visible 20 are the useful ones.
  const sortedColors = [...colors].sort(
    (a, b) => (b.product_count || 0) - (a.product_count || 0),
  );
  const displayedColors = showAllColors ? sortedColors : sortedColors.slice(0, 20);

  if (loading) {
    return (
      <div className="space-y-8 py-2">
        {[0, 1, 2].map((group) => (
          <div key={group} className="space-y-3">
            <Skeleton className="h-4 w-28" />
            {[0, 1, 2, 3].map((row) => (
              <Skeleton key={row} className="h-3.5 w-full" />
            ))}
          </div>
        ))}
      </div>
    );
  }

  return (
    <div>
      <h2 className="type-headline hidden pb-1 text-ink lg:block">Filters</h2>

      {categories.length > 0 && (
        <FilterCard title="Category">
          <ul className="scrollbar-subtle max-h-64 overflow-y-auto pr-1">
            {categories.map((cat) => (
              <CheckRow
                key={cat.id}
                id={`cat-${cat.id}`}
                checked={selectedCategories.includes(cat.id?.toString())}
                onChange={() => handleCategoryChange(cat.id)}
                label={cat.name}
                count={cat.products_count}
              />
            ))}
          </ul>

          {selectedCategories.length > 0 && (
            <button
              type="button"
              onClick={() => {
                setSelectedCategories([]);
                onFilterChange("category", "");
              }}
              className="type-caption mt-3 font-semibold text-ink-tertiary transition-colors hover:text-ink"
            >
              Clear categories
            </button>
          )}
        </FilterCard>
      )}

      <FilterCard title="Price">
        {/* Dual-thumb range. Both inputs are stacked over one track; each has
            `pointer-events-none` on its body and re-enables them on the thumb
            alone, so whichever thumb is nearer the pointer receives the drag. */}
        <div className="relative pb-3 pt-1">
          <div className="relative h-1 rounded-full bg-hairline">
            <div
              className="absolute h-full rounded-full bg-ink"
              style={{
                left: `${getMinPercent()}%`,
                right: `${100 - getMaxPercent()}%`,
              }}
            />
          </div>

          <input
            type="range"
            min={priceRangeMin}
            max={priceRangeMax}
            step="100"
            value={priceMin}
            onChange={handleMinChange}
            aria-label="Minimum price"
            className="range-input absolute left-0 top-0 w-full"
            style={{ zIndex: priceMin > priceRangeMax / 2 ? 5 : 3 }}
          />

          <input
            type="range"
            min={priceRangeMin}
            max={priceRangeMax}
            step="100"
            value={priceMax}
            onChange={handleMaxChange}
            aria-label="Maximum price"
            className="range-input absolute left-0 top-0 w-full"
            style={{ zIndex: priceMax < priceRangeMax / 2 ? 5 : 4 }}
          />
        </div>

        <div className="mt-4 flex items-center gap-2.5">
          <div className="relative flex-1">
            <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[0.8125rem] text-ink-tertiary">
              ৳
            </span>
            <input
              type="number"
              value={priceMin}
              onChange={handleMinInput}
              aria-label="Minimum price"
              className="field tabular h-10 pl-10 pr-2.5 text-[0.8125rem]"
              min={priceRangeMin}
              max={priceMax - 100}
              step="100"
            />
          </div>

          <span className="type-caption text-ink-tertiary">to</span>

          <div className="relative flex-1">
            <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[0.8125rem] text-ink-tertiary">
              ৳
            </span>
            <input
              type="number"
              value={priceMax}
              onChange={handleMaxInput}
              aria-label="Maximum price"
              className="field tabular h-10 pl-10 pr-2.5 text-[0.8125rem]"
              min={priceMin + 100}
              max={priceRangeMax}
              step="100"
            />
          </div>
        </div>

        <Button
          variant="secondary"
          size="sm"
          fullWidth
          className="mt-3"
          onClick={applyPriceFilter}
        >
          Apply
        </Button>
      </FilterCard>

      {sortedColors.length > 0 && (
        <FilterCard title="Colour">
          <div className="scrollbar-subtle flex max-h-44 flex-wrap gap-2.5 overflow-y-auto pr-1">
            {displayedColors.map((color, index) => {
              if (!color || !color.code) return null;
              const isSelected = selectedColors.includes(color.code);

              return (
                <button
                  key={`${color.code}-${index}`}
                  type="button"
                  onClick={() => handleColorChange(color.code)}
                  aria-pressed={isSelected}
                  aria-label={`${color.name} (${color.product_count} products)`}
                  title={`${color.name} · ${color.product_count}`}
                  className="h-6 w-6 rounded-full transition-transform duration-fast hover:scale-110"
                  style={{
                    backgroundColor: color.code,
                    boxShadow: isSelected
                      ? "0 0 0 2px var(--surface), 0 0 0 3.5px var(--ink)"
                      : "inset 0 0 0 1px rgba(0,0,0,0.12)",
                  }}
                />
              );
            })}
          </div>

          <div className="mt-3 flex flex-wrap gap-4">
            {sortedColors.length > 20 && (
              <button
                type="button"
                onClick={() => setShowAllColors(!showAllColors)}
                className="type-caption font-semibold text-ink-tertiary transition-colors hover:text-ink"
              >
                {showAllColors
                  ? "Show fewer"
                  : `Show ${sortedColors.length - 20} more`}
              </button>
            )}

            {selectedColors.length > 0 && (
              <button
                type="button"
                onClick={() => {
                  setSelectedColors([]);
                  onFilterChange("color", "");
                }}
                className="type-caption font-semibold text-ink-tertiary transition-colors hover:text-ink"
              >
                Clear colours ({selectedColors.length})
              </button>
            )}
          </div>
        </FilterCard>
      )}

      {brands.length > 0 && (
        <FilterCard title="Brand">
          <ul className="scrollbar-subtle max-h-48 overflow-y-auto pr-1">
            {brands.map((brand) => (
              <CheckRow
                key={brand.id}
                id={`brand-${brand.id}`}
                checked={selectedBrands.includes(brand.id?.toString())}
                onChange={() => handleBrandChange(brand.id)}
                label={brand.name}
                count={brand.products_count}
              />
            ))}
          </ul>

          {selectedBrands.length > 0 && (
            <button
              type="button"
              onClick={() => {
                setSelectedBrands([]);
                onFilterChange("brand", "");
              }}
              className="type-caption mt-3 font-semibold text-ink-tertiary transition-colors hover:text-ink"
            >
              Clear brands
            </button>
          )}
        </FilterCard>
      )}
    </div>
  );
}
