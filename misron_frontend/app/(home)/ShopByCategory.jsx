"use client";

/**
 * Category-tabbed product grid.
 *
 * Copied from the reference storefront's "Explore by Category (Direct Tabs)"
 * block: a funnel eyebrow over a serif headline with a sort control on the
 * right, a white rounded tab bar (icon, name and a count pill per tab, the
 * selected tab filled dark), and a plain status line naming the open tab and
 * how many products show.
 *
 * While a tab's products load, the grid shows skeleton cards in place rather
 * than emptying and dropping a spinner in the middle, so the page height
 * holds steady as tabs are switched.
 *
 * Category and product fetching, the in-memory cache and the load-more paging
 * are unchanged. Sorting runs on the products already fetched for the open tab.
 * The reference's "All Jewelry" tab and its "Pendants" category are not drawn:
 * there is no all-products endpoint behind this strip, and the tabs are
 * whatever categories the admin picked for the home page.
 */

import { useEffect, useMemo, useState, useRef } from "react";
import {
  AlertCircle,
  Award,
  ChevronDown,
  Circle,
  CircleDot,
  Crown,
  Filter,
  Gem,
  ArrowUpDown,
  Star,
} from "lucide-react";

import ExpandableProductGrid from "../components/layout/ExpandableProductGrid";
import FeaturedProductCard from "../components/layout/FeaturedProductCard";
import { Button, Skeleton } from "@/app/components/ui";
import { parseAmount } from "@/utils/currency";

const cache = new Map();
const CACHE_DURATION = 5 * 60 * 1000; // 5 minutes

// The tab icon is decoration, not data: the tabs take these in turn.
const TAB_ICONS = [Gem, Star, CircleDot, Circle, Crown, Award];

const SORT_OPTIONS = [
  { value: "featured", label: "Featured / Best Match" },
  { value: "price-asc", label: "Price: Low to High" },
  { value: "price-desc", label: "Price: High to Low" },
  { value: "rating", label: "Highest Customer Rating" },
];

// A tab's shape and its selected fill.
const TAB_SHAPE =
  "inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl px-3 py-2 sm:gap-2 sm:px-3.5 sm:py-2.5";
const TAB_SELECTED = "bg-stone-900 font-semibold text-white shadow-md";

// Absent unless the API counted products for this list.
const countOf = (category) =>
  typeof category?.products_count === "number" ? category.products_count : null;

/** Icon, name and count pill: the inside of a tab, selected or not. */
function TabContent({ Icon, name, count, selected }) {
  return (
    <>
      <Icon
        className={`size-3.5 ${selected ? "text-amber-300" : "text-stone-400"}`}
        strokeWidth={1.8}
        aria-hidden="true"
      />
      {/* The size sits on the name, not the tab: globals.css sets
          `button { font-size: inherit }` unlayered, which beats a utility on the
          button itself. */}
      <span className="text-xs">{name}</span>
      {count !== null && (
        <span
          className={`rounded-full px-1.5 text-[10px] font-semibold leading-[1.15rem] tabular ${
            selected ? "bg-amber-400/20 text-amber-300" : "bg-stone-100 text-stone-500"
          }`}
        >
          {count}
        </span>
      )}
    </>
  );
}

const priceOf = (item) =>
  parseAmount(item.main_price ?? item.price ?? item.unit_price ?? 0);

/** Returns a sorted copy; "featured" keeps the order the API sent. */
const sortProducts = (products, sort) => {
  if (sort === "price-asc") return [...products].sort((a, b) => priceOf(a) - priceOf(b));
  if (sort === "price-desc") return [...products].sort((a, b) => priceOf(b) - priceOf(a));
  if (sort === "rating")
    return [...products].sort((a, b) => (Number(b.rating) || 0) - (Number(a.rating) || 0));
  return products;
};

export default function ShopByCategory() {
  const [categories, setCategories] = useState([]);
  const [selectedCategory, setSelectedCategory] = useState(null);
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(false);
  const [categoriesLoading, setCategoriesLoading] = useState(false);
  const [error, setError] = useState(null);
  const [sort, setSort] = useState("featured");
  const isMounted = useRef(true);

  const getCacheKey = (type, slug = null) =>
    type === "categories" ? "shop-categories" : `category-products-${slug}`;

  const isCacheValid = (cacheKey) => {
    const cached = cache.get(cacheKey);
    if (!cached) return false;
    return Date.now() - cached.timestamp < CACHE_DURATION;
  };

  const getFromCache = (cacheKey) => cache.get(cacheKey)?.data ?? null;

  const setToCache = (cacheKey, data) => {
    cache.set(cacheKey, { data, timestamp: Date.now() });
  };

  // ── Categories ──────────────────────────────────────────────────────────
  useEffect(() => {
    isMounted.current = true;

    const fetchCategories = async () => {
      const cacheKey = getCacheKey("categories");

      if (isCacheValid(cacheKey)) {
        const cachedData = getFromCache(cacheKey);
        if (isMounted.current) {
          setCategories(cachedData);
          if (cachedData.length > 0 && !selectedCategory) {
            setSelectedCategory(cachedData[0]);
          }
        }
        return;
      }

      setCategoriesLoading(true);
      setError(null);

      try {
        const res = await fetch(
          `${process.env.NEXT_PUBLIC_API_URL}/selected-categories`,
          {
            method: "GET",
            headers: {
              Accept: "application/json",
              "Content-Type": "application/json",
            },
          },
        );

        if (!res.ok) {
          if (res.status === 500) {
            throw new Error("Server error. Please try again later.");
          }
          throw new Error(`Failed to fetch categories (Status: ${res.status})`);
        }

        const response = await res.json();

        if (isMounted.current) {
          if (response.success && Array.isArray(response.data)) {
            setToCache(cacheKey, response.data);
            setCategories(response.data);
            if (response.data.length > 0) setSelectedCategory(response.data[0]);
          } else {
            console.error("Unexpected API response structure:", response);
            setCategories([]);
            if (response.message) setError(response.message);
          }
        }
      } catch (err) {
        if (isMounted.current) {
          console.error("Fetch error:", err);
          setError(err.message || "An error occurred");
          setCategories([]);
        }
      } finally {
        if (isMounted.current) setCategoriesLoading(false);
      }
    };

    fetchCategories();

    return () => {
      isMounted.current = false;
    };
  }, []);

  // ── Products for the selected tab ───────────────────────────────────────
  useEffect(() => {
    if (!selectedCategory) return;

    let isProductFetchMounted = true;

    const fetchProducts = async () => {
      const cacheKey = getCacheKey("products", selectedCategory.slug);

      if (isCacheValid(cacheKey)) {
        const cachedData = getFromCache(cacheKey);
        if (isProductFetchMounted) setProducts(cachedData);
        return;
      }

      setLoading(true);
      setError(null);

      try {
        const res = await fetch(
          `${process.env.NEXT_PUBLIC_API_URL}/products/category/${selectedCategory.slug}`,
          {
            method: "GET",
            headers: {
              Accept: "application/json",
              "Content-Type": "application/json",
            },
          },
        );

        if (!res.ok) {
          throw new Error(
            `Failed to fetch products for ${selectedCategory.name} (Status: ${res.status})`,
          );
        }

        const response = await res.json();

        if (isProductFetchMounted) {
          if (response.success && Array.isArray(response.data)) {
            setToCache(cacheKey, response.data);
            setProducts(response.data);
          } else {
            console.error("Unexpected API response structure:", response);
            setProducts([]);
            if (response.message) setError(response.message);
          }
        }
      } catch (err) {
        if (isProductFetchMounted) {
          console.error("Fetch error:", err);
          setError(err.message || "An error occurred");
          setProducts([]);
        }
      } finally {
        if (isProductFetchMounted) setLoading(false);
      }
    };

    fetchProducts();

    return () => {
      isProductFetchMounted = false;
    };
  }, [selectedCategory]);

  const sortedProducts = useMemo(() => sortProducts(products, sort), [products, sort]);

  const isBusy = loading || categoriesLoading;

  // The heading, sort control, tab bar and status line are passed to the grid
  // whole rather than letting the grid grow a prop for each.
  const header = (
    <div className="mb-6 md:mb-8">
      <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div className="min-w-0">
          <div className="mb-1 inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-amber-800">
            <Filter className="size-3.5 text-amber-600" aria-hidden="true" />
            <span>Full Catalog &amp; Direct Categories</span>
          </div>
          <h2 className="type-title-serif text-stone-900">Shop by Category</h2>
          <p className="mt-0.5 text-xs font-light text-stone-500 sm:text-sm">
            Select a tab to view matching products
          </p>
        </div>

        {/* A native select keeps the platform picker on a phone; the
            label wraps it so the whole pill is the tap target. */}
        <label className="relative inline-flex shrink-0 items-center gap-2 rounded-xl border border-stone-200 bg-white py-2 pl-3 pr-9 text-xs shadow-xs transition-colors focus-within:border-amber-500 hover:border-stone-300">
          <ArrowUpDown className="size-3.5 text-stone-400" aria-hidden="true" />
          <span className="text-stone-500">Sort:</span>
          <select
            value={sort}
            onChange={(e) => setSort(e.target.value)}
            aria-label="Sort products"
            className="cursor-pointer appearance-none bg-transparent font-medium text-stone-900 outline-none"
          >
            {SORT_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
          <ChevronDown
            className="pointer-events-none absolute right-3 size-3.5 text-stone-900"
            aria-hidden="true"
          />
        </label>
      </div>

      {/* Tab bar. Scrolls sideways on a phone rather than wrapping into an
          uneven block of tabs. */}
      <div className="scrollbar-hide -mx-4 overflow-x-auto px-4 py-1 md:mx-0 md:px-0">
        <div
          role="tablist"
          aria-label="Product categories"
          className="inline-flex min-w-full gap-1 rounded-2xl border border-stone-200 bg-white p-1.5 shadow-xs"
        >
          {categoriesLoading ? (
            <div className="flex gap-1">
              {[0, 1, 2, 3].map((i) => (
                <Skeleton key={i} className="h-10 w-28" rounded="xl" />
              ))}
            </div>
          ) : (
            categories.map((category, index) => {
              const isSelected = selectedCategory?.id === category.id;
              return (
                <button
                  key={category.id}
                  type="button"
                  role="tab"
                  aria-selected={isSelected}
                  onClick={() => setSelectedCategory(category)}
                  disabled={loading}
                  className={`${TAB_SHAPE} transition-all duration-200 disabled:cursor-not-allowed ${
                    isSelected
                      ? TAB_SELECTED
                      : "font-medium text-stone-600 hover:bg-stone-100 hover:text-stone-900"
                  }`}
                >
                  <TabContent
                    Icon={TAB_ICONS[index % TAB_ICONS.length]}
                    name={category.name}
                    count={countOf(category)}
                    selected={isSelected}
                  />
                </button>
              );
            })
          )}
        </div>
      </div>

      {/* Plain status line: the open tab's name, then how many products show. */}
      {selectedCategory && !isBusy && products.length > 0 && (
        <p className="mt-4 text-sm text-stone-500" aria-live="polite">
          <span className="font-medium text-stone-900">{selectedCategory.name}</span>
          <span className="mx-2">·</span>
          Showing <span className="font-semibold text-stone-900">{products.length}</span>{" "}
          {products.length === 1 ? "product" : "products"}
        </p>
      )}
    </div>
  );

  const errorPanel =
    error && products.length === 0 && !isBusy ? (
      <div className="flex flex-col items-center rounded-xl bg-canvas-sunken px-6 py-14 text-center">
        <AlertCircle size={26} className="mb-4 text-ink-tertiary" strokeWidth={1.5} />
        <h3 className="type-title-3 text-ink">Couldn&apos;t load products</h3>
        <p className="type-footnote mt-2 text-ink-tertiary">{error}</p>
        <Button
          variant="secondary"
          className="mt-6"
          onClick={() => window.location.reload()}
        >
          Try again
        </Button>
      </div>
    ) : null;

  return (
    <ExpandableProductGrid
      header={header}
      // Framed card with Cart / Order actions, as in the reference storefront.
      Card={FeaturedProductCard}
      cardProps={{ featured: false }}
      // Two rows on a phone, then "View all".
      compactOnPhone
      products={sortedProducts}
      loading={isBusy}
      dimmed={loading && products.length > 0}
      fallback={errorPanel}
      // "View all" opens the shop page already filtered to the open tab, so a
      // visitor past the second block lands where they were looking.
      href={
        selectedCategory
          ? `/shop_page?categories=${selectedCategory.id}`
          : "/shop_page"
      }
      emptyTitle="Nothing in this category yet"
      emptyDescription={
        selectedCategory
          ? `We haven't added products to ${selectedCategory.name} so far.`
          : undefined
      }
    />
  );
}
