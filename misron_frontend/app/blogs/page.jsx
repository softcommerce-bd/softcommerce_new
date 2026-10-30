'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import BlogAPI from '@/lib/api/blog';
import Link from 'next/link';
import { useSearchParams, useRouter } from 'next/navigation';
import Breadcrumb from '../components/layout/Breadcrumb';
import { Search, Check, FileText } from 'lucide-react';
import { EmptyState, Pagination, Skeleton } from '@/app/components/ui';

export default function BlogListing() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const isInitialMount = useRef(true);
  const isUpdatingFromURL = useRef(false);
  
  const [blogs, setBlogs] = useState([]);
  const [categories, setCategories] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [loading, setLoading] = useState(true);
  const [selectedCategories, setSelectedCategories] = useState([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);

  // Get initial values from URL params (only once on mount)
  useEffect(() => {
    const page = searchParams.get('page');
    const search = searchParams.get('search');
    const categoriesParam = searchParams.get('categories');
    
    if (page) setCurrentPage(parseInt(page));
    if (search) setSearchTerm(search);
    if (categoriesParam) setSelectedCategories(categoriesParam.split(','));
    
    isInitialMount.current = false;
  }, []); // Empty dependency array - only run once

  // Fetch blogs when filters change
  useEffect(() => {
    if (isInitialMount.current) return;
    
    fetchBlogs();
  }, [currentPage, selectedCategories, searchTerm]);

  // Fetch categories only once
  useEffect(() => {
    fetchCategories();
  }, []);

  const fetchBlogs = async () => {
    setLoading(true);
    try {
      const response = await BlogAPI.getAllBlogs({
        page: currentPage,
        perPage: 12,
        search: searchTerm,
        selectedCategories: selectedCategories,
      });
      
      // console.log('API Response:', response);
      
      // Set blogs data
      if (response.data && Array.isArray(response.data)) {
        setBlogs(response.data);
      } else if (response.data && response.data.data && Array.isArray(response.data.data)) {
        setBlogs(response.data.data);
      } else {
        setBlogs([]);
      }
      
      // Set pagination data
      if (response.pagination) {
        setPagination(response.pagination);
      }
      
      // Update URL with current filters (only if not already syncing from URL)
      if (!isUpdatingFromURL.current) {
        const params = new URLSearchParams();
        if (currentPage > 1) params.set('page', currentPage);
        if (searchTerm) params.set('search', searchTerm);
        if (selectedCategories.length) params.set('categories', selectedCategories.join(','));
        
        const newUrl = `${window.location.pathname}${params.toString() ? `?${params}` : ''}`;
        const currentUrl = window.location.pathname + window.location.search;
        
        if (newUrl !== currentUrl) {
          router.replace(newUrl, { scroll: false });
        }
      }
    } catch (error) {
      console.error('Error fetching blogs:', error);
      setBlogs([]);
    } finally {
      setLoading(false);
      isUpdatingFromURL.current = false;
    }
  };

  const fetchCategories = async () => {
    try {
      const response = await BlogAPI.getAllCategories();
      // console.log('Categories response:', response);
      
      // Handle different response formats
      let categoriesData = [];
      if (Array.isArray(response)) {
        categoriesData = response;
      } else if (response.data && Array.isArray(response.data)) {
        categoriesData = response.data;
      } else if (response.data && response.data.data && Array.isArray(response.data.data)) {
        categoriesData = response.data.data;
      }
      
      setCategories(categoriesData);
    } catch (error) {
      console.error('Error fetching categories:', error);
      setCategories([]);
    }
  };

  const handleCategoryToggle = (categorySlug) => {
    setSelectedCategories(prev => {
      const newSelection = prev.includes(categorySlug)
        ? prev.filter(slug => slug !== categorySlug)
        : [...prev, categorySlug];
      
      // console.log('Category toggled:', categorySlug, 'New selection:', newSelection);
      return newSelection;
    });
    setCurrentPage(1);
  };

  const handleSearch = (e) => {
    e.preventDefault();
    setCurrentPage(1);
  };

  const handlePageChange = (newPage) => {
    setCurrentPage(newPage);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const clearAllFilters = () => {
    setSelectedCategories([]);
    setSearchTerm('');
    setCurrentPage(1);
  };

  const hasFilters = selectedCategories.length > 0 || searchTerm;

  return (
    <div className="container-page py-8 md:py-12">
      <Breadcrumb items={[{ name: "Home", href: "/" }, { name: "Blog", href: "#" }]} />

      <header className="mb-10 mt-8">
        <h1 className="type-title-1 text-ink">Journal</h1>
        <p className="type-callout mt-2 text-ink-tertiary">
          Stories, guides and news from the studio.
        </p>
      </header>

      <div className="flex flex-col gap-10 lg:flex-row">
        {/* ── Filters ──────────────────────────────────────────────────────*/}
        <aside className="lg:w-56 lg:shrink-0">
          <div className="lg:sticky lg:top-[calc(var(--header-height,80px)+24px)]">
            <form onSubmit={handleSearch} className="mb-8">
              <label htmlFor="blog-search" className="field-label">
                Search
              </label>
              <div className="relative">
                <Search
                  size={16}
                  className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-tertiary"
                  aria-hidden="true"
                />
                <input
                  id="blog-search"
                  type="text"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  placeholder="Search posts"
                  className="field h-10 pl-10 text-[0.8125rem]"
                />
              </div>
              <button type="submit" className="sr-only-focusable">
                Search
              </button>
            </form>

            {categories.length > 0 && (
              <div>
                <h2 className="type-eyebrow mb-3 text-ink-tertiary">Categories</h2>

                <ul className="space-y-0.5">
                  {categories.map((category) => {
                    const slug = category.slug ?? category.category_slug;
                    const name = category.category_name ?? category.name;
                    const checked = selectedCategories.includes(slug);

                    return (
                      <li key={category.id}>
                        <label className="group flex cursor-pointer items-center gap-2.5 py-1.5">
                          <span className="relative flex h-4 w-4 shrink-0 items-center justify-center">
                            <input
                              type="checkbox"
                              checked={checked}
                              onChange={() => handleCategoryToggle(slug)}
                              className="peer absolute inset-0 cursor-pointer opacity-0"
                            />
                            <span
                              className="flex h-4 w-4 items-center justify-center rounded-sm border border-hairline-strong bg-surface transition-all duration-fast peer-checked:border-ink peer-checked:bg-ink peer-focus-visible:ring-2 peer-focus-visible:ring-brand peer-focus-visible:ring-offset-2"
                              aria-hidden="true"
                            >
                              {checked && (
                                <Check size={11} strokeWidth={3} className="text-ink-inverse" />
                              )}
                            </span>
                          </span>

                          <span className="type-subhead text-ink-secondary transition-colors group-hover:text-ink">
                            {name}
                          </span>
                        </label>
                      </li>
                    );
                  })}
                </ul>

                {hasFilters && (
                  <button
                    type="button"
                    onClick={clearAllFilters}
                    className="type-caption mt-4 font-semibold text-ink-tertiary transition-colors hover:text-ink"
                  >
                    Clear filters
                  </button>
                )}
              </div>
            )}
          </div>
        </aside>

        {/* ── Posts ────────────────────────────────────────────────────────*/}
        <div className="min-w-0 flex-1">
          {loading ? (
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
              {Array.from({ length: 6 }).map((_, i) => (
                <div key={i}>
                  <Skeleton className="aspect-4/3 w-full" rounded="lg" />
                  <Skeleton className="mt-4 h-4 w-4/5" />
                  <Skeleton className="mt-2 h-3 w-full" />
                  <Skeleton className="mt-2 h-3 w-2/3" />
                </div>
              ))}
            </div>
          ) : blogs.length === 0 ? (
            <EmptyState
              icon={FileText}
              title="No posts found"
              description={
                hasFilters
                  ? "Nothing matches these filters. Try clearing them."
                  : "Posts will appear here once they're published."
              }
              action={
                hasFilters
                  ? { label: "Clear filters", onClick: clearAllFilters }
                  : undefined
              }
            />
          ) : (
            <>
              <div className="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
                {blogs.map((blog) => (
                  <article key={blog.id}>
                    <Link href={`/blogs/${blog.slug}`} className="group block">
                      {blog.banner && (
                        <div className="relative aspect-4/3 w-full overflow-hidden rounded-lg bg-canvas-sunken">
                          <img
                            src={blog.banner}
                            alt=""
                            className="h-full w-full object-cover transition-transform duration-slower ease-standard group-hover:scale-[1.03]"
                            onError={(e) => {
                              e.currentTarget.style.display = "none";
                            }}
                          />
                        </div>
                      )}

                      <div className="mt-4">
                        <div className="type-caption flex flex-wrap items-center gap-2 text-ink-tertiary">
                          <time dateTime={blog.created_at}>
                            {new Date(blog.created_at).toLocaleDateString()}
                          </time>
                          {blog.category && (
                            <>
                              <span aria-hidden="true">·</span>
                              <span>
                                {blog.category.category_name || blog.category.name}
                              </span>
                            </>
                          )}
                        </div>

                        <h2 className="type-title-3 mt-2 line-clamp-2 text-ink transition-colors group-hover:text-ink-secondary">
                          {blog.title}
                        </h2>

                        <p className="type-footnote mt-2 line-clamp-2 text-ink-tertiary">
                          {blog.short_description}
                        </p>
                      </div>
                    </Link>
                  </article>
                ))}
              </div>

              {pagination && (
                <Pagination
                  currentPage={currentPage}
                  lastPage={pagination.last_page}
                  onPageChange={handlePageChange}
                  className="mt-14"
                />
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
