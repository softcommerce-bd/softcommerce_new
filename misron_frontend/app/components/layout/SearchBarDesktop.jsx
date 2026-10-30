"use client";

import { Search as FaSearch, ChevronDown as IoMdArrowDropdown } from "lucide-react";
import { useState, useEffect, useRef } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import Image from "next/image";

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_URL ||
  "";

export default function SearchbarDesktop({
  open,
  setOpen,
  selectedCategory,
  setSelectedCategory,
  categories,
  isModal = false,
  onClose = null,
  // "bar" is the category-dropdown search used in the search modal; "pill" is
  // the rounded header search, which has no category picker.
  variant = "bar",
}) {
  const isPill = variant === "pill";
  const router = useRouter();
  const [searchTerm, setSearchTerm] = useState("");
  const [suggestions, setSuggestions] = useState([]);
  const [isSearching, setIsSearching] = useState(false);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [activeSuggestionIndex, setActiveSuggestionIndex] = useState(-1);
  const searchRef = useRef(null);
  const searchTimeout = useRef(null);
  const inputRef = useRef(null);

  // console.log('categories data', categories);
  // console.log('selected category', selectedCategory);

  // Handle keyboard navigation for suggestions
  const handleKeyDown = (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      if (activeSuggestionIndex >= 0 && suggestions[activeSuggestionIndex]) {
        // Navigate to selected suggestion
        handleSuggestionClick(suggestions[activeSuggestionIndex]);
      } else {
        // Perform search
        handleSearch(e);
      }
    } else if (e.key === "ArrowDown") {
      e.preventDefault();
      setActiveSuggestionIndex((prev) =>
        prev < suggestions.length - 1 ? prev + 1 : prev,
      );
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      setActiveSuggestionIndex((prev) => (prev > 0 ? prev - 1 : -1));
    } else if (e.key === "Escape") {
      setShowSuggestions(false);
      setActiveSuggestionIndex(-1);
    }
  };

  // Scroll active suggestion into view
  useEffect(() => {
    if (activeSuggestionIndex >= 0) {
      const activeElement = document.querySelector(
        `[data-suggestion-index="${activeSuggestionIndex}"]`,
      );
      if (activeElement) {
        activeElement.scrollIntoView({ block: "nearest" });
      }
    }
  }, [activeSuggestionIndex]);

  useEffect(() => {
    function handleClickOutside(event) {
      if (searchRef.current && !searchRef.current.contains(event.target)) {
        setShowSuggestions(false);
        setActiveSuggestionIndex(-1);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  useEffect(() => {
    if (searchTerm.trim().length < 2) {
      setSuggestions([]);
      setShowSuggestions(false);
      setActiveSuggestionIndex(-1);
      return;
    }

    if (searchTimeout.current) {
      clearTimeout(searchTimeout.current);
    }

    searchTimeout.current = setTimeout(() => {
      fetchSuggestions();
    }, 300);

    return () => {
      if (searchTimeout.current) {
        clearTimeout(searchTimeout.current);
      }
    };
  }, [searchTerm]);

  const fetchSuggestions = async () => {
    if (!searchTerm.trim()) return;

    setIsSearching(true);
    try {
      const params = new URLSearchParams({
        query_key: searchTerm,
        type: "product",
      });

      // Add category filter if not "All"
      if (selectedCategory.id !== "all") {
        params.append("category_id", selectedCategory.id);
      }

      const response = await fetch(
        `${API_BASE_URL}/get-search-suggestions?${params}`,
        {
          method: "GET",
          headers: {
            Accept: "application/json",
          },
        },
      );

      if (!response.ok) {
        throw new Error("Failed to fetch suggestions");
      }

      const data = await response.json();

      if (data.success && data.data) {
        // Transform the data to match what we need for display
        const transformedSuggestions = data.data.map((item) => ({
          id: item.id,
          name: item.query || item.name,
          slug: item.slug,
          type: item.type || "product",
          type_string: item.type_string || "Product",
          thumbnail_image: item.thumbnail_image || item.img,
          main_price: item.main_price,
          unit_price: item.unit_price,
          // For product items from search, they might have different structure
          ...(item.product && {
            name: item.product.name,
            slug: item.product.slug,
            thumbnail_image: item.product.thumbnail_image,
            main_price: item.product.main_price,
          }),
        }));

        setSuggestions(transformedSuggestions);
        setShowSuggestions(true);
        setActiveSuggestionIndex(-1);
      } else {
        setSuggestions([]);
      }
    } catch (error) {
      console.error("Suggestion fetch error:", error);
      setSuggestions([]);
    } finally {
      setIsSearching(false);
    }
  };

  // const handleSearch = (e) => {
  //   e.preventDefault();
  //   if (searchTerm.trim()) {
  //     const searchParams = new URLSearchParams({
  //       q: searchTerm,
  //       ...(selectedCategory.id !== "all" && { category: selectedCategory.id }),
  //     });

  //     router.push(`/shop_page?${searchParams.toString()}`);
  //     setShowSuggestions(false);
  //     setActiveSuggestionIndex(-1);
  //     setSearchTerm("");
  //   }
  // };

    const handleSearch = (e) => {
    e.preventDefault();
    if (searchTerm.trim()) {
      const searchParams = new URLSearchParams({
        q: searchTerm,
        ...(selectedCategory.id !== 'all' && { category: selectedCategory.id })
      });
      
      // Clear states
      setShowSuggestions(false);
      setActiveSuggestionIndex(-1);
      
      // Close modal if it's open
      if (isModal && onClose) {
        onClose();
      }
      
      router.push(`/shop_page?${searchParams.toString()}`);
      setSearchTerm('');
    }
  };


const handleSuggestionClick = (suggestion) => {
    // Clear all suggestion-related states first
    setShowSuggestions(false);
    setActiveSuggestionIndex(-1);
    setSearchTerm('');
    setSuggestions([]);
    
    // Remove focus from input
    if (inputRef.current) {
      inputRef.current.blur();
    }
    
    // Close modal if it's open (for mobile/tablet view)
    if (isModal && onClose) {
      onClose();
    }
    
    // Check if it's a single product and navigate to product details
    if (suggestion.type === 'product' && suggestion.slug) {
      // Navigate directly to product details page
      router.push(`/product/${suggestion.slug}`);
    } else {
      // For categories, brands, or multiple results - go to search results
      const searchParams = new URLSearchParams({
        q: suggestion.name,
        ...(selectedCategory.id !== 'all' && { category: selectedCategory.id })
      });
      
      // Add specific filters for category or brand
      if (suggestion.type === 'category') {
        searchParams.set('category', suggestion.id);
      } else if (suggestion.type === 'brand') {
        searchParams.set('brand', suggestion.id);
      }
      
      // Navigate to search results page
      router.push(`/shop_page?${searchParams.toString()}`);
    }
  };

  // Helper function to get image URL
  const getImageUrl = (suggestion) => {
    if (suggestion.thumbnail_image) {
      // Check if it's a full URL or just an ID
      if (suggestion.thumbnail_image.startsWith("http")) {
        return suggestion.thumbnail_image;
      }
      // If it's an ID, construct the URL (adjust based on your image URL pattern)
      return `${API_BASE_URL}/public/uploads/all/${suggestion.thumbnail_image}`;
    }
    return null;
  };

  const placeholderText = isPill
    ? "Search products by name or code..."
    : `Search ${selectedCategory.id !== "all" ? selectedCategory.name : "all products"}...`;

  return (
    <div
      className={
        isPill
          ? "relative w-full"
          : "hidden lg:flex flex-1 mx-2 lg:mx-10 relative"
      }
      ref={searchRef}
    >
      {/* Category Dropdown - Like Amazon's "All" dropdown */}
      {!isPill && (
      <div className="relative inline-block text-left">
        <button
          onClick={() => setOpen(!open)}
          className="rounded-l-md border border-hairline bg-canvas-sunken flex items-center justify-between text-black hover:bg-surface-hover py-2 lg:py-3 px-2 lg:px-3 lg:text-sm text-xs w-20 lg:w-auto min-w-[100px] h-full"
        >
          <span className="truncate">{selectedCategory.name}</span>
          <IoMdArrowDropdown size={15} className="ml-1 shrink-0 text-ink-tertiary" aria-hidden="true" />
        </button>

        {open && (
          <ul className="absolute left-0 mt-1 w-40 lg:w-56 rounded-lg bg-surface-raised shadow-e3 z-20 overflow-y-auto max-h-96">
            {categories.map((cat) => (
              <li
                key={cat.id}
                onClick={() => {
                  setSelectedCategory(cat);
                  setOpen(false);
                  // Refocus input after selection
                  inputRef.current?.focus();
                }}
                className={`px-4 py-2 cursor-pointer hover:bg-surface-hover type-subhead text-ink ${
                  selectedCategory.id === cat.id
                    ? "bg-gray-100 font-semibold"
                    : ""
                }`}
              >
                {cat.name}
              </li>
            ))}
          </ul>
        )}
      </div>
      )}

      {/* Search Input - Like Amazon's search bar */}
      <div className="flex-1 relative">
        <input
          ref={inputRef}
          type="text"
          aria-label="Search products"
          placeholder={isPill ? undefined : placeholderText}
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          onKeyDown={handleKeyDown}
          onFocus={() =>
            searchTerm.length >= 2 &&
            suggestions.length > 0 &&
            setShowSuggestions(true)
          }
          className={
            isPill
              ? "type-search-pill block h-9 w-full lg:h-10 truncate rounded-full border border-stone-300/80 bg-white pl-4 pr-12 text-stone-800 transition-all duration-fast ease-standard hover:border-stone-400 focus:border-amber-700 focus:ring-2 focus:ring-amber-100/80 focus-visible:outline-none! sm:pl-5 sm:pr-14"
              : "w-full border-y border-hairline bg-surface px-3.5 py-3 text-[0.875rem] text-ink focus:outline-none"
          }
          autoComplete="off"
        />

        {/* The pill draws its own placeholder instead of using the native one:
            a placeholder cannot be centred without dragging the caret and the
            typed text to the middle too, and the global `::placeholder` rule in
            globals.css is unlayered, so it beats any utility that tries to hide
            or restyle it. It starts where the typed text starts, and is a
            touch smaller below `lg` while the input itself stays 16px (a
            smaller input makes iPhones zoom the page on focus). */}
        {isPill && !searchTerm && (
          <span
            aria-hidden="true"
            className="pointer-events-none absolute inset-y-0 left-0 right-0 flex items-center pl-4 pr-12 sm:pl-5 sm:pr-14"
          >
            <span className="truncate text-xs leading-none text-ink-tertiary lg:text-sm">
              {placeholderText}
            </span>
          </span>
        )}

        {/* Search Suggestions Dropdown - Like Amazon's suggestion dropdown */}
        {showSuggestions && (
          <div className="absolute top-full left-0 right-0 mt-1 rounded-xl bg-surface-raised shadow-e3 z-30 max-h-96 overflow-y-auto">
            {/* Suggested searches */}
            {suggestions.length > 0 ? (
              <>
                {/* Products Section */}
                {suggestions.filter((s) => s.type === "product").length > 0 && (
                  <>
                    <div className="px-4 py-2 border-b border-hairline bg-canvas-sunken">
                      <p className="type-eyebrow text-ink-tertiary">
                        PRODUCTS
                      </p>
                    </div>
                    {suggestions
                      .filter((s) => s.type === "product")
                      .map((suggestion, idx) => {
                        const globalIndex = suggestions.indexOf(suggestion);
                        const imageUrl = getImageUrl(suggestion);

                        return (
                          <div
                            key={`${suggestion.type}-${suggestion.id}`}
                            data-suggestion-index={globalIndex}
                            onClick={() => handleSuggestionClick(suggestion)}
                            className={`px-4 py-3 cursor-pointer transition-colors hover:bg-surface-hover ${
                              activeSuggestionIndex === globalIndex
                                ? "bg-gray-100"
                                : ""
                            }`}
                          >
                            <div className="flex items-center gap-3">
                              {imageUrl && (
                                <div className="h-10 w-10 shrink-0 overflow-hidden rounded-md bg-canvas-sunken">
                                  <img
                                    src={imageUrl}
                                    alt={suggestion.name}
                                    className="w-full h-full object-cover"
                                    onError={(e) => {
                                      e.currentTarget.style.display = "none";
                                    }}
                                  />
                                </div>
                              )}

                              <div className="flex-1 min-w-0">
                                <h4 className="type-subhead font-medium text-ink">
                                  {suggestion.name}
                                </h4>

                                {suggestion.main_price && (
                                  <p className="type-subhead tabular mt-1 font-semibold text-ink">
                                    {suggestion.main_price}
                                  </p>
                                )}
                              </div>

                              <FaSearch size={13} className="text-ink-quaternary" aria-hidden="true" />
                            </div>
                          </div>
                        );
                      })}
                  </>
                )}

                {/* Categories Section */}
                {suggestions.filter((s) => s.type === "category").length >
                  0 && (
                  <>
                    <div className="px-4 py-2 border-y border-hairline bg-canvas-sunken">
                      <p className="type-eyebrow text-ink-tertiary">
                        CATEGORIES
                      </p>
                    </div>
                    {suggestions
                      .filter((s) => s.type === "category")
                      .map((suggestion, idx) => {
                        const globalIndex = suggestions.indexOf(suggestion);
                        return (
                          <div
                            key={`${suggestion.type}-${suggestion.id}`}
                            data-suggestion-index={globalIndex}
                            onClick={() => handleSuggestionClick(suggestion)}
                            className={`px-4 py-2 cursor-pointer transition-colors hover:bg-surface-hover ${
                              activeSuggestionIndex === globalIndex
                                ? "bg-gray-100"
                                : ""
                            }`}
                          >
                            <div className="flex items-center justify-between">
                              <span className="type-subhead text-ink-secondary">
                                {suggestion.name}
                              </span>
                              <span className="type-caption text-ink-tertiary">
                                Category
                              </span>
                            </div>
                          </div>
                        );
                      })}
                  </>
                )}

                {/* Brands Section */}
                {suggestions.filter((s) => s.type === "brand").length > 0 && (
                  <>
                    <div className="px-4 py-2 border-y border-hairline bg-canvas-sunken">
                      <p className="type-eyebrow text-ink-tertiary">
                        BRANDS
                      </p>
                    </div>
                    {suggestions
                      .filter((s) => s.type === "brand")
                      .map((suggestion, idx) => {
                        const globalIndex = suggestions.indexOf(suggestion);
                        return (
                          <div
                            key={`${suggestion.type}-${suggestion.id}`}
                            data-suggestion-index={globalIndex}
                            onClick={() => handleSuggestionClick(suggestion)}
                            className={`px-4 py-2 cursor-pointer transition-colors hover:bg-surface-hover ${
                              activeSuggestionIndex === globalIndex
                                ? "bg-gray-100"
                                : ""
                            }`}
                          >
                            <div className="flex items-center justify-between">
                              <span className="type-subhead text-ink-secondary">
                                {suggestion.name}
                              </span>
                              <span className="type-caption text-ink-tertiary">
                                Brand
                              </span>
                            </div>
                          </div>
                        );
                      })}
                  </>
                )}
              </>
            ) : (
              <div className="px-4 py-8 text-center">
                <p className="type-subhead text-ink-tertiary">No suggestions found</p>
              </div>
            )}

            {/* View all results link */}
            {suggestions.length > 0 && (
              <div className="px-4 py-3 bg-gray-50 border-t border-gray-200">
                <button
                  onClick={handleSearch}
                  className="type-subhead w-full text-center font-semibold text-ink hover:text-ink-secondary"
                >
                  Search for &quot;{searchTerm}&quot;{" "}
                  {selectedCategory.id !== "all" &&
                    `in ${selectedCategory.name}`}
                </button>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Search Button */}
      <button
        onClick={handleSearch}
        aria-label="Search"
        className={
          isPill
            ? "absolute right-1 top-1/2 flex size-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-gold text-white shadow-xs transition-colors duration-fast ease-standard hover:bg-gold-hover lg:right-1.5 lg:size-8"
            : "bg-main  px-4 lg:px-6 py-2 lg:py-3 rounded-r-md  flex items-center justify-center border border-l-0 border-hairline transition-colors"
        }
      >
        {isSearching ? (
          <div
            className={`animate-spin rounded-full h-4 w-4 border-2 border-t-transparent ${
              isPill ? "border-white" : "border-black"
            }`}
          ></div>
        ) : (
          <FaSearch size={isPill ? 15 : 17} className="text-white" aria-hidden="true" />
        )}
      </button>
    </div>
  );
}
