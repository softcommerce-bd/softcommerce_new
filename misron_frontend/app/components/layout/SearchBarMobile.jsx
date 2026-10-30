"use client";

import { Search as FaSearch, ChevronDown as IoMdArrowDropdown } from "lucide-react";
import { useState, useEffect, useRef } from "react";
import { useRouter } from "next/navigation";

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || '';

export default function SearchbarMobile({ 
  open, 
  setOpen, 
  selectedCategory, 
  setSelectedCategory, 
  categories,
  isModal = false,  // Add this prop
  onClose = null    // Add this prop
}) {
  const router = useRouter();
  const [searchTerm, setSearchTerm] = useState('');
  const [suggestions, setSuggestions] = useState([]);
  const [isSearching, setIsSearching] = useState(false);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [activeSuggestionIndex, setActiveSuggestionIndex] = useState(-1);
  const searchRef = useRef(null);
  const searchTimeout = useRef(null);
  const inputRef = useRef(null);

  // Handle keyboard navigation for suggestions
  const handleKeyDown = (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      if (activeSuggestionIndex >= 0 && suggestions[activeSuggestionIndex]) {
        handleSuggestionClick(suggestions[activeSuggestionIndex]);
      } else {
        handleSearch(e);
      }
    } else if (e.key === 'ArrowDown') {
      e.preventDefault();
      setActiveSuggestionIndex(prev => 
        prev < suggestions.length - 1 ? prev + 1 : prev
      );
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActiveSuggestionIndex(prev => prev > 0 ? prev - 1 : -1);
    } else if (e.key === 'Escape') {
      setShowSuggestions(false);
      setActiveSuggestionIndex(-1);
    }
  };

  // Scroll active suggestion into view
  useEffect(() => {
    if (activeSuggestionIndex >= 0) {
      const activeElement = document.querySelector(`[data-mobile-suggestion-index="${activeSuggestionIndex}"]`);
      if (activeElement) {
        activeElement.scrollIntoView({ block: 'nearest' });
      }
    }
  }, [activeSuggestionIndex]);

  // Handle click outside
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

  // Debounce search suggestions
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
        type: 'product'
      });

      // Add category filter if not "All"
      if (selectedCategory.id !== 'all') {
        params.append('category_id', selectedCategory.id);
      }

      const response = await fetch(`${API_BASE_URL}/get-search-suggestions?${params}`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch suggestions');
      }

      const data = await response.json();
      
      if (data.success && data.data) {
        // Transform the data to match what we need for display
        const transformedSuggestions = data.data.map(item => ({
          id: item.id,
          name: item.query || item.name,
          slug: item.slug,
          type: item.type || 'product',
          type_string: item.type_string || 'Product',
          thumbnail_image: item.thumbnail_image || item.img,
          main_price: item.main_price,
          unit_price: item.unit_price,
          // For product items from search, they might have different structure
          ...(item.product && {
            name: item.product.name,
            slug: item.product.slug,
            thumbnail_image: item.product.thumbnail_image,
            main_price: item.product.main_price
          })
        }));
        
        setSuggestions(transformedSuggestions);
        setShowSuggestions(true);
        setActiveSuggestionIndex(-1);
      } else {
        setSuggestions([]);
      }
    } catch (error) {
      console.error('Suggestion fetch error:', error);
      setSuggestions([]);
    } finally {
      setIsSearching(false);
    }
  };

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
      setSearchTerm('');
      inputRef.current?.blur(); // Close keyboard on mobile
      
      // Close modal if it's open
      if (isModal && onClose) {
        onClose();
      }
      
      router.push(`/shop_page?${searchParams.toString()}`);
    }
  };

  const handleSuggestionClick = (suggestion) => {
    // Clear all suggestion-related states
    setShowSuggestions(false);
    setActiveSuggestionIndex(-1);
    setSearchTerm('');
    setSuggestions([]);
    
    // Close keyboard on mobile
    if (inputRef.current) {
      inputRef.current.blur();
    }
    
    // Close modal if it's open
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
      if (suggestion.thumbnail_image.startsWith('http')) {
        return suggestion.thumbnail_image;
      }
      return `${API_BASE_URL}/public/uploads/all/${suggestion.thumbnail_image}`;
    }
    return null;
  };

  return (
    <div className="lg:hidden flex items-center px-2 pb-2" ref={searchRef}>
      <div className="flex flex-1 w-full relative">
        {/* Category Dropdown */}
        <div className="relative inline-block text-left">
          <button
            onClick={() => setOpen(!open)}
            className="rounded-l-md border border-hairline bg-canvas-sunken flex items-center justify-between text-black hover:bg-surface-hover py-[10px] px-3 text-xs w-16"
          >
            <span className="truncate">{selectedCategory.name}</span>
            <IoMdArrowDropdown size={15} className="ml-1 shrink-0 text-ink-tertiary" aria-hidden="true" />
          </button>

          {open && (
            <ul className="absolute left-0 mt-1 w-40 rounded-lg bg-surface-raised shadow-e3 z-50 overflow-y-auto max-h-80">
              {categories.map((cat) => (
                <li
                  key={cat.id}
                  onClick={() => {
                    setSelectedCategory(cat);
                    setOpen(false);
                    inputRef.current?.focus();
                  }}
                  className={`px-4 py-2 cursor-pointer hover:bg-surface-hover text-black text-xs ${
                    selectedCategory.id === cat.id ? 'bg-gray-100 font-semibold' : ''
                  }`}
                >
                  {cat.name}
                </li>
              ))}
            </ul>
          )}
        </div>

        {/* Search Input Container */}
        <div className="flex-1 relative">
          <input
            ref={inputRef}
            type="text"
            placeholder={`Search ${selectedCategory.id !== 'all' ? selectedCategory.name : 'products'}...`}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            onKeyDown={handleKeyDown}
            onFocus={() => searchTerm.length >= 2 && suggestions.length > 0 && setShowSuggestions(true)}
            className="w-full border-y border-hairline bg-surface px-3.5 py-2.5 text-[0.875rem] text-ink focus:outline-none"
            autoComplete="off"
          />

          {/* Search Suggestions Dropdown - Mobile Optimized */}
          {showSuggestions && (
            <div className="absolute top-full left-0 right-0 mt-1 rounded-xl bg-surface-raised shadow-e3 z-50 max-h-80 overflow-y-auto">
              {suggestions.length > 0 ? (
                <>
                  {/* Products Section */}
                  {suggestions.filter(s => s.type === 'product').length > 0 && (
                    <>
                      <div className="px-3 py-1 border-b border-hairline bg-canvas-sunken sticky top-0">
                        <p className="type-eyebrow text-ink-tertiary">PRODUCTS</p>
                      </div>
                      {suggestions
                        .filter(s => s.type === 'product')
                        .map((suggestion, idx) => {
                          const globalIndex = suggestions.indexOf(suggestion);
                          const imageUrl = getImageUrl(suggestion);
                          
                          return (
                            <div
                              key={`mobile-${suggestion.type}-${suggestion.id}`}
                              data-mobile-suggestion-index={globalIndex}
                              onClick={() => handleSuggestionClick(suggestion)}
                              className={`px-3 py-2 cursor-pointer transition-colors hover:bg-surface-hover ${
                                activeSuggestionIndex === globalIndex ? 'bg-gray-100' : ''
                              }`}
                            >
                              <div className="flex items-center gap-2">
                                {imageUrl && (
                                  <div className="w-8 h-8 bg-gray-100 rounded-md overflow-hidden flex-shrink-0">
                                    <img
                                      src={imageUrl}
                                      alt={suggestion.name}
                                      className="w-full h-full object-cover"
                                      onError={(e) => {
                                        e.currentTarget.style.display = 'none';
                                      }}
                                    />
                                  </div>
                                )}
                                
                                <div className="flex-1 min-w-0">
                                  <h4 className="text-xs font-medium text-gray-800 line-clamp-1">
                                    {suggestion.name}
                                  </h4>
                                  
                                  {suggestion.main_price && (
                                    <p className="type-caption tabular font-semibold text-ink">
                                      {suggestion.main_price}
                                    </p>
                                  )}
                                </div>

                                <FaSearch size={13} className="shrink-0 text-ink-quaternary" aria-hidden="true" />
                              </div>
                            </div>
                          );
                        })}
                    </>
                  )}

                  {/* Categories Section */}
                  {suggestions.filter(s => s.type === 'category').length > 0 && (
                    <>
                      <div className="px-3 py-1 border-y border-hairline bg-canvas-sunken sticky top-0">
                        <p className="type-eyebrow text-ink-tertiary">CATEGORIES</p>
                      </div>
                      {suggestions
                        .filter(s => s.type === 'category')
                        .map((suggestion, idx) => {
                          const globalIndex = suggestions.indexOf(suggestion);
                          return (
                            <div
                              key={`mobile-${suggestion.type}-${suggestion.id}`}
                              data-mobile-suggestion-index={globalIndex}
                              onClick={() => handleSuggestionClick(suggestion)}
                              className={`px-3 py-2 cursor-pointer transition-colors hover:bg-surface-hover ${
                                activeSuggestionIndex === globalIndex ? 'bg-gray-100' : ''
                              }`}
                            >
                              <div className="flex items-center justify-between">
                                <span className="text-xs text-gray-700">{suggestion.name}</span>
                                <span className="type-caption text-ink-tertiary">Category</span>
                              </div>
                            </div>
                          );
                        })}
                    </>
                  )}

                  {/* Brands Section */}
                  {suggestions.filter(s => s.type === 'brand').length > 0 && (
                    <>
                      <div className="px-3 py-1 border-y border-hairline bg-canvas-sunken sticky top-0">
                        <p className="type-eyebrow text-ink-tertiary">BRANDS</p>
                      </div>
                      {suggestions
                        .filter(s => s.type === 'brand')
                        .map((suggestion, idx) => {
                          const globalIndex = suggestions.indexOf(suggestion);
                          return (
                            <div
                              key={`mobile-${suggestion.type}-${suggestion.id}`}
                              data-mobile-suggestion-index={globalIndex}
                              onClick={() => handleSuggestionClick(suggestion)}
                              className={`px-3 py-2 cursor-pointer transition-colors hover:bg-surface-hover ${
                                activeSuggestionIndex === globalIndex ? 'bg-gray-100' : ''
                              }`}
                            >
                              <div className="flex items-center justify-between">
                                <span className="text-xs text-gray-700">{suggestion.name}</span>
                                <span className="type-caption text-ink-tertiary">Brand</span>
                              </div>
                            </div>
                          );
                        })}
                    </>
                  )}
                </>
              ) : (
                <div className="px-3 py-6 text-center">
                  <p className="type-caption text-ink-tertiary">No suggestions found</p>
                </div>
              )}

              {/* View all results link */}
              {suggestions.length > 0 && (
                <div className="px-3 py-2 bg-gray-50 border-t border-gray-200 sticky bottom-0">
                  <button
                    onClick={handleSearch}
                    className="type-caption w-full text-center font-semibold text-ink hover:text-ink-secondary"
                  >
                    Search for "{searchTerm}"
                  </button>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Search Button */}
        <button 
          onClick={handleSearch}
          className="bg-main px-3 py-2 rounded-r-md hover:bg-main-hov flex items-center justify-center border border-l-0 border-hairline"
        >
          {isSearching ? (
            <div className="animate-spin rounded-full h-3 w-3 border-2 border-black border-t-transparent"></div>
          ) : (
            <FaSearch size={16} className="text-white" aria-hidden="true" />
          )}
        </button>
      </div>
    </div>
  );
}