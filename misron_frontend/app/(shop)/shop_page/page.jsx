"use client";

/**
 * Shop / search results.
 *
 * Rebuilt on the design system. Notable changes:
 *   · The mobile filter drawer is the shared Sheet, so it traps focus, closes
 *     on Escape and locks page scroll — the previous hand-rolled overlay did
 *     none of these and used the removed `bg-opacity-40` utility.
 *   · Pagination and the sort dropdown come from the shared set.
 *   · Loading shows skeleton cards in the grid instead of a centred spinner,
 *     so the page keeps its height while a filter is applied.
 *   · Active filters render as dismissible chips rather than grey blocks, and
 *     the price chip now shows the taka symbol instead of a dollar sign.
 *   · Roughly 170 lines of commented-out earlier fetch implementations have
 *     been removed, along with an unused 35-entry colour-name lookup table.
 *
 * Every query parameter, endpoint and filter rule is unchanged.
 */

import { useState, useEffect } from "react";
import { useSearchParams, useRouter } from "next/navigation";
import { SlidersHorizontal, X, PackageSearch } from "lucide-react";

import ProductCard1 from "@/app/components/layout/ProductCard1";
import Breadcrumb from "@/app/components/layout/Breadcrumb";
import SidebarContent from "@/app/components/layout/SidebarContent";
import {
  Button,
  Select,
  Sheet,
  Pagination,
  EmptyState,
  SkeletonProductCard,
} from "@/app/components/ui";

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || "";

const SORT_OPTIONS = [
  { value: "featured", label: "Featured" },
  { value: "newest", label: "Newest arrivals" },
  // The backend's switch has no "popularity" branch, so that value silently
  // fell through to the default order. "best-selling" is the key it matches,
  // and it is also what the home page's best-seller link sends.
  { value: "best-selling", label: "Best selling" },
  { value: "top_rated", label: "Top rated" },
  { value: "price_low_to_high", label: "Price: low to high" },
  { value: "price_high_to_low", label: "Price: high to low" },
];

/** Dismissible active-filter chip. */
function FilterChip({ children, onRemove }) {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-full bg-canvas-sunken py-1.5 pl-3 pr-1.5 text-[0.75rem] font-medium text-ink">
      {children}
      <button
        type="button"
        onClick={onRemove}
        className="flex h-5 w-5 items-center justify-center rounded-full text-ink-tertiary transition-colors hover:bg-ink/10 hover:text-ink"
        aria-label="Remove filter"
      >
        <X size={12} aria-hidden="true" />
      </button>
    </span>
  );
}

export default function Shop() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const searchQuery = searchParams.get("q") || searchParams.get("name") || "";
  const categoryId = searchParams.get("categories") || "";
  const minPrice = searchParams.get("min_price") || searchParams.get("min") || "";
  const maxPrice = searchParams.get("max_price") || searchParams.get("max") || "";
  const sortBy = searchParams.get("sort") || searchParams.get("sort_key") || "newest";
  const page = parseInt(searchParams.get("page")) || 1;
  const filter = searchParams.get("filter") || "";

  const [showFilter, setShowFilter] = useState(false);
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [pagination, setPagination] = useState(null);
  const [totalProducts, setTotalProducts] = useState(0);
  const [priceRange, setPriceRange] = useState({ min: 0, max: 100000 });

  const [categories, setCategories] = useState([]);
  const [brands, setBrands] = useState([]);
  const [colors, setColors] = useState([]);
  const [loadingFilters, setLoadingFilters] = useState(true);

  // ── Filter facets ───────────────────────────────────────────────────────
  const fetchFilterData = async () => {
    try {
      const [categoriesRes, brandsRes, colorsRes, priceRes] = await Promise.all([
        fetch(`${API_BASE_URL}/categories`),
        fetch(`${API_BASE_URL}/brands`),
        fetch(`${API_BASE_URL}/available-colors`),
        fetch(`${API_BASE_URL}/products/price-range`),
      ]);

      const categoriesData = await categoriesRes.json();
      if (categoriesData.success) setCategories(categoriesData.data || []);

      const brandsData = await brandsRes.json();
      if (brandsData.success) setBrands(brandsData.data || []);

      const colorsData = await colorsRes.json();
      if (colorsData.success) setColors(colorsData.data);

      const priceData = await priceRes.json();
      if (priceData.success) {
        setPriceRange({
          min: priceData.min_price || 0,
          max: priceData.max_price || 100000,
        });
      }
    } catch (error) {
      console.error("Error fetching filter data:", error);
    } finally {
      setLoadingFilters(false);
    }
  };

  useEffect(() => {
    fetchFilterData();
  }, [searchParams.get("categories")]);

  // ── Products ────────────────────────────────────────────────────────────
  const fetchProducts = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();

      if (searchQuery) params.append("name", searchQuery);

      const categoriesParam = searchParams.get("categories");
      if (categoriesParam && categoriesParam !== "") {
        params.append("categories", categoriesParam);
      }

      const minPriceParam = searchParams.get("min");
      const maxPriceParam = searchParams.get("max");

      if (minPriceParam && minPriceParam !== "") params.append("min", minPriceParam);
      if (maxPriceParam && maxPriceParam !== "") params.append("max", maxPriceParam);

      if (sortBy) params.append("sort_key", sortBy);
      if (filter === "featured") params.append("featured", "1");

      const brandsParam = searchParams.get("brands");
      if (brandsParam && brandsParam !== "") params.append("brands", brandsParam);

      const colorsParam = searchParams.get("colors");
      if (colorsParam && colorsParam !== "") params.append("colors", colorsParam);

      params.append("page", page);
      params.append("per_page", 24);

      const response = await fetch(
        `${API_BASE_URL}/products/search?${params.toString()}`,
        { headers: { Accept: "application/json" } },
      );

      if (!response.ok) throw new Error("Failed to fetch products");

      const data = await response.json();

      if (data.success) {
        setProducts(data.data || []);
        setPagination(data.meta);
        setTotalProducts(data.meta?.total || 0);
      } else {
        setProducts([]);
        setTotalProducts(0);
      }
    } catch (error) {
      console.error("Error fetching products:", error);
      setProducts([]);
      setTotalProducts(0);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProducts();
  }, [searchQuery, categoryId, minPrice, maxPrice, sortBy, page, filter, searchParams]);

  // ── URL-driven filter changes ───────────────────────────────────────────
  const handleFilterChange = (filterType, value) => {
    const params = new URLSearchParams(searchParams.toString());

    switch (filterType) {
      case "category":
        if (value && value !== "all" && value !== "") {
          params.set("categories", value);
        } else {
          params.delete("categories");
        }
        break;

      case "brand":
        if (value && value !== "") params.set("brands", value);
        else params.delete("brands");
        break;

      case "color": {
        // Colour is filtered within the current category, so the category
        // parameter is carried across explicitly.
        const existingCategories = searchParams.get("categories");
        if (existingCategories) params.set("categories", existingCategories);

        if (value && value !== "") params.set("colors", value);
        else params.delete("colors");
        break;
      }

      case "price":
        // A bound equal to the range's own edge is not a filter, so it is
        // dropped from the URL rather than pinned.
        if (value.min !== undefined && value.min !== null && value.min > 0) {
          params.set("min", value.min.toString());
        } else {
          params.delete("min");
        }

        if (
          value.max !== undefined &&
          value.max !== null &&
          value.max < priceRange.max
        ) {
          params.set("max", value.max.toString());
        } else {
          params.delete("max");
        }
        break;

      case "rating":
        if (value && value !== "") params.set("rating", value);
        else params.delete("rating");
        break;
    }

    params.set("page", "1");
    router.push(`/shop_page?${params.toString()}`);
  };

  const handleSortChange = (e) => {
    const params = new URLSearchParams(searchParams.toString());
    params.set("sort", e.target.value);
    params.set("page", "1");
    router.push(`/shop_page?${params.toString()}`);
  };

  const handlePageChange = (newPage) => {
    const params = new URLSearchParams(searchParams.toString());
    params.set("page", newPage);
    router.push(`/shop_page?${params.toString()}`);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const clearAllFilters = () => router.push("/shop_page");

  const getActiveFiltersCount = () => {
    let count = 0;
    if (categoryId && categoryId !== "all") count++;
    if (minPrice || maxPrice) count++;
    if (searchParams.get("brands")) count++;
    if (searchParams.get("colors")) count++;
    if (searchParams.get("rating")) count++;
    return count;
  };

  const activeFilters = getActiveFiltersCount();

  const sidebarProps = {
    categories,
    brands,
    colors,
    selectedCategory: categoryId,
    minPrice: minPrice || priceRange.min,
    maxPrice: maxPrice || priceRange.max,
    priceRangeMin: priceRange.min,
    priceRangeMax: priceRange.max,
    onFilterChange: handleFilterChange,
    loading: loadingFilters,
    currentProducts: products,
  };

  return (
    <div className="container-page py-8 md:py-12">
      <Breadcrumb
        items={[
          { name: "Home", href: "/" },
          { name: "Shop", href: "/shop_page" },
          ...(searchQuery ? [{ name: `"${searchQuery}"`, href: "#" }] : []),
        ]}
      />

      <div className="relative mt-8 flex flex-col gap-8 lg:flex-row lg:gap-10">
        {/* ── Filter sidebar (desktop) ─────────────────────────────────────
            Sticks below the header and scrolls independently once it is
            taller than the viewport. */}
        <aside className="scrollbar-subtle sticky top-[calc(var(--header-height,80px)+24px)] hidden h-fit max-h-[calc(100vh-var(--header-height,80px)-48px)] w-72 shrink-0 overflow-y-auto lg:block">
          <SidebarContent {...sidebarProps} />
        </aside>

        <section className="min-w-0 flex-1">
          {/* ── Toolbar ───────────────────────────────────────────────────*/}
          <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h1 className="type-title-2 text-ink">
                {searchQuery ? `Results for “${searchQuery}”` : "All products"}
              </h1>
              <p className="type-footnote tabular mt-1.5 text-ink-tertiary">
                {totalProducts.toLocaleString()}{" "}
                {totalProducts === 1 ? "product" : "products"}
              </p>
            </div>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setShowFilter(true)}
                className="btn btn-secondary btn-md relative lg:hidden"
              >
                <SlidersHorizontal size={16} strokeWidth={1.75} aria-hidden="true" />
                Filters
                {activeFilters > 0 && (
                  <span className="badge-count absolute -right-1.5 -top-1.5">
                    {activeFilters}
                  </span>
                )}
              </button>

              <Select
                value={sortBy}
                onChange={handleSortChange}
                options={SORT_OPTIONS}
                aria-label="Sort products"
                className="h-10 w-full min-w-44 text-[0.8125rem] sm:w-auto"
              />
            </div>
          </div>

          {/* ── Active filters ────────────────────────────────────────────*/}
          {activeFilters > 0 && (
            <div className="mb-6 flex flex-wrap items-center gap-2">
              {categoryId && categoryId !== "all" && (
                <FilterChip onRemove={() => handleFilterChange("category", null)}>
                  {categories.find((c) => c.id == categoryId)?.name || categoryId}
                </FilterChip>
              )}

              {(minPrice || maxPrice) && (
                <FilterChip
                  onRemove={() =>
                    handleFilterChange("price", { min: null, max: null })
                  }
                >
                  ৳ {minPrice || priceRange.min} – ৳ {maxPrice || priceRange.max}
                </FilterChip>
              )}

              <button
                type="button"
                onClick={clearAllFilters}
                className="type-footnote ml-1 font-semibold text-ink-tertiary transition-colors hover:text-ink"
              >
                Clear all
              </button>
            </div>
          )}

          {/* ── Results ───────────────────────────────────────────────────*/}
          {loading ? (
            <div className="grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
              {Array.from({ length: 9 }).map((_, i) => (
                <SkeletonProductCard key={i} />
              ))}
            </div>
          ) : products.length === 0 ? (
            <EmptyState
              icon={PackageSearch}
              title="No products found"
              description={
                activeFilters > 0
                  ? "Nothing matches these filters. Try removing one or widening the price range."
                  : "We couldn't find anything here. Try a different search."
              }
              action={
                activeFilters > 0
                  ? { label: "Clear all filters", onClick: clearAllFilters }
                  : { label: "Browse all products", href: "/shop_page" }
              }
            />
          ) : (
            <>
              <div className="grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                {products.map((product, index) => (
                  <ProductCard1 key={product.id} item={product} priority={index < 6} />
                ))}
              </div>

              {pagination && (
                <Pagination
                  currentPage={pagination.current_page}
                  lastPage={pagination.last_page}
                  onPageChange={handlePageChange}
                  className="mt-14"
                />
              )}
            </>
          )}
        </section>
      </div>

      {/* ── Filter sheet (mobile) ────────────────────────────────────────*/}
      <Sheet
        open={showFilter}
        onClose={() => setShowFilter(false)}
        side="right"
        title="Filters"
        footer={
          <div className="flex gap-2.5">
            <Button
              variant="secondary"
              className="flex-1"
              onClick={() => {
                clearAllFilters();
                setShowFilter(false);
              }}
            >
              Clear all
            </Button>
            <Button className="flex-1" onClick={() => setShowFilter(false)}>
              Show {totalProducts.toLocaleString()}
            </Button>
          </div>
        }
      >
        <div className="px-5 pb-5">
          <SidebarContent {...sidebarProps} onClose={() => setShowFilter(false)} />
        </div>
      </Sheet>
    </div>
  );
}
