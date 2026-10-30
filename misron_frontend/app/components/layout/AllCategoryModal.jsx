"use client";

/**
 * Mobile category and menu drawer.
 *
 * Rebuilt on the design system with lucide icons throughout, replacing three
 * icon sources (react-icons/fa, react-icons/hi, a local ButtonMenubar) and an
 * inline four-square SVG.
 *
 * The category rows keep their split behaviour: tapping the name navigates,
 * tapping the chevron expands the subcategories.
 *
 * Note this drawer manages its own open state and click-outside handling
 * rather than using the shared Sheet, because it is also used as an
 * uncontrolled component with its own trigger button.
 *
 * Category fetching, subcategory lazy-loading and the controlled/uncontrolled
 * prop contract are unchanged.
 */

import { useState, useEffect, useRef } from "react";
import Link from "next/link";
import { Menu, X, ChevronDown, LayoutGrid, Phone } from "lucide-react";

import useCachedFetch from "@/app/utils/useCachedFetch";
import { Skeleton } from "../ui";
import BrandLogo from "./BrandLogo";

export default function AllCategoryModal({
  open: controlledOpen,
  onOpenChange,
  hideTrigger = false,
}) {
  const isControlled = controlledOpen !== undefined;
  const [internalOpen, setInternalOpen] = useState(false);
  const isMenuOpen = isControlled ? controlledOpen : internalOpen;
  const setIsMenuOpen = isControlled ? onOpenChange : setInternalOpen;

  const [activeTab, setActiveTab] = useState("categories");
  const [expandedCategories, setExpandedCategories] = useState({});
  const [logoUrl, setLogoUrl] = useState(null);
  const [announcement, setAnnouncement] = useState("");

  // The whole tree in one call, in the admin's display order, children nested
  // under their parent. Expanding a row used to fire its own request for that
  // category's children, so the drawer made a request per row the user opened.
  const { data: categories, loading } = useCachedFetch(
    `${process.env.NEXT_PUBLIC_API_URL}/categories/tree`,
    "category-tree",
    30 * 60 * 1000,
  );

  const { data: footerData } = useCachedFetch(
    `${process.env.NEXT_PUBLIC_API_URL}/footer-data`,
    "footer-data",
    30 * 60 * 1000,
  );

  const usefulLinks = footerData?.useful_links_menu || [];
  const helpline = footerData?.contact_phone;

  // The logo is read from the cache the header already populated, so the
  // drawer does not fetch it a second time.
  useEffect(() => {
    const cachedData = localStorage.getItem("header_logo_data");
    if (cachedData) {
      try {
        const parsed = JSON.parse(cachedData);
        if (parsed.header_logo) setLogoUrl(parsed.header_logo);
        if (parsed.top_header) setAnnouncement(parsed.top_header);
      } catch (e) {}
    }
  }, []);

  const toggleCategory = (categoryId) => {
    setExpandedCategories((prev) => ({ ...prev, [categoryId]: !prev[categoryId] }));
  };

  const closeMenu = () => {
    setIsMenuOpen(false);
    setActiveTab("categories");
  };

  const openMenu = () => setIsMenuOpen(true);

  const triggerRef = useRef(null);
  const containerRef = useRef(null);

  useEffect(() => {
    const handleClickOutside = (e) => {
      if (
        isMenuOpen &&
        !containerRef.current?.contains(e.target) &&
        !triggerRef.current?.contains(e.target)
      ) {
        closeMenu();
      }
    };

    // Escape closes the drawer — it previously only closed on a click.
    const handleEscape = (e) => {
      if (e.key === "Escape" && isMenuOpen) closeMenu();
    };

    document.addEventListener("mousedown", handleClickOutside);
    document.addEventListener("keydown", handleEscape);
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
      document.removeEventListener("keydown", handleEscape);
    };
  }, [isMenuOpen]);

  const tabClass = (tab) =>
    `flex flex-1 items-center justify-center gap-2 py-3.5 text-[0.6875rem] font-semibold uppercase tracking-[0.08em] transition-colors duration-fast ${
      activeTab === tab
        ? "border-b-2 border-amber-700 text-amber-800"
        : "border-b-2 border-transparent text-stone-400 hover:text-stone-700"
    }`;

  return (
    <div className="relative">
      {!hideTrigger && (
        <button
          ref={triggerRef}
          type="button"
          onClick={openMenu}
          className="inline-flex size-9 cursor-pointer items-center justify-center rounded-lg text-stone-700 transition-colors duration-150 hover:bg-stone-100/70 hover:text-stone-950 sm:size-10"
          aria-label="Open menu"
          aria-expanded={isMenuOpen}
        >
          <Menu size={24} strokeWidth={1.75} aria-hidden="true" />
        </button>
      )}

      {isMenuOpen && (
        <div
          className="animate-fade-in fixed inset-0 z-40 bg-black/50 backdrop-blur-xs"
          onClick={closeMenu}
          aria-hidden="true"
        />
      )}

      <div
        ref={containerRef}
        role="dialog"
        aria-modal="true"
        aria-label="Categories and menu"
        // The shadow belongs to the open state only: a shadow on the parked
        // panel bleeds past the screen edge as a grey band down the page's left
        // side. `inert` keeps the parked links out of the tab order.
        inert={!isMenuOpen}
        className={`fixed left-0 top-0 z-50 flex h-dvh w-[85vw] max-w-sm transform flex-col bg-cream transition-[transform,box-shadow] duration-slow ease-standard ${
          isMenuOpen ? "translate-x-0 shadow-2xl" : "-translate-x-full"
        }`}
      >
        <header className="flex shrink-0 items-center justify-between gap-3 border-b border-stone-200 px-5 py-4">
          <Link href="/" onClick={closeMenu} className="group/logo min-w-0">
            <BrandLogo
              src={logoUrl}
              imageClassName="h-9 max-w-[160px]"
              wordmarkClassName="text-2xl"
            />
          </Link>

          <button
            type="button"
            onClick={closeMenu}
            className="-mr-1 inline-flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-stone-500 transition-colors duration-150 hover:bg-stone-100 hover:text-stone-900"
            aria-label="Close menu"
          >
            <X size={20} aria-hidden="true" />
          </button>
        </header>

        <div role="tablist" className="flex shrink-0 border-b border-stone-200">
          <button
            type="button"
            role="tab"
            aria-selected={activeTab === "categories"}
            onClick={() => setActiveTab("categories")}
            className={tabClass("categories")}
          >
            <LayoutGrid size={14} strokeWidth={2} aria-hidden="true" />
            Categories
          </button>

          <button
            type="button"
            role="tab"
            aria-selected={activeTab === "menu"}
            onClick={() => setActiveTab("menu")}
            className={tabClass("menu")}
          >
            <Menu size={14} strokeWidth={2} aria-hidden="true" />
            Menu
          </button>
        </div>

        <div className="scrollbar-subtle flex-1 overflow-y-auto">
          {activeTab === "categories" && (
            <ul className="divide-y divide-stone-200/70">
              {loading &&
                [0, 1, 2, 3, 4].map((i) => (
                  <li key={i} className="px-5 py-4">
                    <Skeleton className="h-4 w-2/3" />
                  </li>
                ))}

              {!loading && (!categories || categories.length === 0) && (
                <li className="type-footnote px-5 py-4 text-ink-tertiary">
                  No categories available
                </li>
              )}

              {categories?.map((cat) => {
                const isOpen = !!expandedCategories[cat.id];
                const subs = Array.isArray(cat.children) ? cat.children : [];
                const hasSubmenu = subs.length > 0;

                return (
                  <li key={cat.id}>
                    <div className="flex items-center">
                      <Link
                        href={`/shop_page?categories=${cat.id}`}
                        onClick={closeMenu}
                        className="type-subhead flex-1 px-5 py-3.5 font-medium text-stone-700 transition-colors hover:text-amber-900"
                      >
                        {cat.name}
                      </Link>

                      {hasSubmenu && (
                        <button
                          type="button"
                          onClick={() => toggleCategory(cat.id)}
                          className="btn-icon btn-icon-md mr-2 shrink-0"
                          aria-expanded={isOpen}
                          aria-label={`Show ${cat.name} subcategories`}
                        >
                          <ChevronDown
                            size={16}
                            className={`transition-transform duration-base ease-standard ${
                              isOpen ? "rotate-180" : ""
                            }`}
                            aria-hidden="true"
                          />
                        </button>
                      )}
                    </div>

                    {/* The children are already in hand, so there is no per-row
                        loading or empty state left to render: the chevron only
                        exists when this list has rows. */}
                    {hasSubmenu && isOpen && (
                      <ul className="animate-slide-down bg-stone-100/70 pb-2">
                        {subs.map((sub) => (
                          <li key={sub.id}>
                            <Link
                              href={`/shop_page?categories=${sub.id}`}
                              onClick={closeMenu}
                              className="type-footnote flex items-center gap-2.5 px-8 py-2.5 text-ink-secondary transition-colors hover:text-ink"
                            >
                              {sub.icon && (
                                <img
                                  src={sub.icon}
                                  alt=""
                                  className="h-4 w-4 shrink-0 object-contain"
                                />
                              )}
                              {sub.name}
                            </Link>
                          </li>
                        ))}
                      </ul>
                    )}
                  </li>
                );
              })}
            </ul>
          )}

          {activeTab === "menu" && (
            <ul className="divide-y divide-stone-200/70">
              {usefulLinks.length === 0 && (
                <li className="type-footnote px-5 py-4 text-ink-tertiary">
                  No links available
                </li>
              )}

              {usefulLinks.map((item, idx) => (
                <li key={idx}>
                  <Link
                    href={`/pages${item.link}`}
                    onClick={closeMenu}
                    className="type-subhead block px-5 py-3.5 font-medium text-stone-700 transition-colors hover:text-amber-900"
                  >
                    {item.label}
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </div>

        {(helpline || announcement) && (
          <div className="shrink-0 space-y-2 border-t border-stone-200 px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] text-xs text-stone-500">
            {helpline && (
              <a
                href={`tel:${String(helpline).replace(/[^d+]/g, "")}`}
                className="flex items-center gap-2 transition-colors hover:text-amber-800"
              >
                <Phone size={13} strokeWidth={2} aria-hidden="true" />
                <span>
                  Helpline: <span className="tabular font-medium text-stone-700">{helpline}</span>
                </span>
              </a>
            )}
            {announcement && <p>{announcement}</p>}
          </div>
        )}
      </div>
    </div>
  );
}
