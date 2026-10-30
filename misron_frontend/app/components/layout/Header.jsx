// Header.jsx — global navigation.
//
// Visual layer follows the reference design (cream bar, dark announcement
// strip, pill search with a gold button, dark cart pill, centred category nav);
// all data loading, caching, auth and cart behaviour is unchanged.
//
// Structure:
//   TopHeader   announcement strip, collapses on scroll
//   Main bar    menu · logo · search · account, wishlist and cart
//   Nav         Home · All Collections · top categories (laptop and up)
//
// One grid carries the main bar at every width, so there is a single search
// field to keep in sync rather than a desktop one and a phone one:
//   phone, tablet (<1024)  menu · logo · actions, with the search on its own
//                          row; categories live behind the menu button
//   laptop  (1024+)        the search moves up into the middle column and the
//                          menu button gives way to the category nav row
//   large   (1280+)        a wider search, more categories in the nav
//
// The bar is solid at every scroll position; it gains a shadow only once the
// page has moved. "All Collections" opens a full-width panel listing every
// category with its children.

"use client";

import Link from "next/link";
import { useState, useEffect, useRef, useCallback } from "react";
import { usePathname, useSearchParams } from "next/navigation";
import { toast } from "react-hot-toast";
import {
  User,
  Heart,
  ShoppingBag,
  LayoutDashboard,
  Package,
  LogOut,
} from "lucide-react";

import AllCategoryModal from "./AllCategoryModal";
import BrandLogo from "./BrandLogo";
import HeaderSkeleton from "./HeaderSkeleton";
import LoginModal from "../userAuth/LoginModal";
import RegisterModal from "../userAuth/RegisterModal";
import CartDrawer from "./CartDrawer";
import SearchbarDesktop from "./SearchBarDesktop";
import TopHeader from "../TopHeader";

import { useAppSelector, useAppDispatch } from "@/store/hooks";
import {
  setTempUserId,
  fetchCart,
  getCartCount,
  clearCart,
} from "@/store/slices/cartSlice";
import { fetchWishlist } from "@/store/slices/wishlistSlice";
import { setUser, logout as logoutAction } from "@/store/slices/authSlice";
import useCachedFetch from "@/app/utils/useCachedFetch";
import { toTk } from "@/utils/currency";

export default function Header() {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const dispatch = useAppDispatch();

  // No stand-in logo: until the admin's logo is known (or if there is none) the
  // brand wordmark is shown, so a missing logo never reads as someone else's.
  const [logoUrl, setLogoUrl] = useState(null);
  const [topHeader, setTopHeader] = useState("");
  const [topPromo, setTopPromo] = useState({ text: "", code: "" });

  const { user, isAuthenticated } = useAppSelector((state) => state.auth);
  const { totalQuantity, totalPrice, cartLoading, cartData } = useAppSelector(
    (state) => state.cart,
  );
  const { totalItems: wishlistCount } = useAppSelector((state) => state.wishlist);

  const [open, setOpen] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState({
    id: "all",
    name: "All",
  });
  const [showLogin, setShowLogin] = useState(false);
  const [showRegister, setShowRegister] = useState(false);
  const [showUserDropdown, setShowUserDropdown] = useState(false);
  const [showCartDrawer, setShowCartDrawer] = useState(false);
  const [isMounted, setIsMounted] = useState(false);
  const [initialLoadDone, setInitialLoadDone] = useState(false);
  const [isLogoFetching, setIsLogoFetching] = useState(true);
  const [isScrolled, setIsScrolled] = useState(false);
  const [cachedCartData, setCachedCartData] = useState(null);
  const [showMenuPanel, setShowMenuPanel] = useState(false);

  const userDropdownRef = useRef(null);
  const headerRef = useRef(null);

  // ── Publish header height ───────────────────────────────────────────────
  // Pages offset their content by --header-height.
  //
  // A one-shot measurement keyed on `isScrolled` was wrong: the announcement
  // strip collapses over `duration-slow`, and the effect runs the moment the
  // state flips, so the value captured was a mid-transition height. The header
  // also grows after mount when the strip's data arrives. A ResizeObserver
  // tracks the real box through both, so content never sits under the header
  // or floats away from it.
  useEffect(() => {
    const el = headerRef.current;
    if (!el) return;

    const measure = () => {
      document.documentElement.style.setProperty(
        "--header-height",
        `${el.getBoundingClientRect().height}px`,
      );
    };

    measure();

    const observer = new ResizeObserver(measure);
    observer.observe(el);

    window.addEventListener("resize", measure);
    return () => {
      observer.disconnect();
      window.removeEventListener("resize", measure);
    };
  }, [isMounted]);

  useEffect(() => {
    const handleScroll = () => setIsScrolled(window.scrollY > 50);
    window.addEventListener("scroll", handleScroll, { passive: true });
    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  // ── Categories ──────────────────────────────────────────────────────────
  // One call returns the whole tree, already in the admin's display order,
  // with each category's children nested. This used to be `/categories`
  // followed by one `/sub-categories/{id}` per parent, fired when the panel
  // opened — a request per top-level category on every first open.
  const { data: categoryTree } = useCachedFetch(
    `${process.env.NEXT_PUBLIC_API_URL}/categories/tree`,
    "category-tree",
    5 * 60 * 1000,
  );

  const categories = Array.isArray(categoryTree)
    ? [
        { id: "all", name: "All", children: [] },
        ...categoryTree.map((cat) => ({
          id: cat.id,
          name: cat.name,
          slug: cat.slug,
          children: Array.isArray(cat.children) ? cat.children : [],
        })),
      ]
    : [{ id: "all", name: "All", children: [] }];

  // Navigating away closes the panel; without this it survives the route change.
  useEffect(() => {
    setShowMenuPanel(false);
  }, [pathname]);

  useEffect(() => {
    if (!showMenuPanel) return;
    const onKey = (e) => e.key === "Escape" && setShowMenuPanel(false);
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [showMenuPanel]);

  // ── Cart cache ──────────────────────────────────────────────────────────
  useEffect(() => {
    const cachedCart = localStorage.getItem("cached_cart_data");
    if (cachedCart) {
      try {
        setCachedCartData(JSON.parse(cachedCart));
      } catch (e) {}
    }
  }, []);

  useEffect(() => {
    if (cartData && !cartLoading) {
      localStorage.setItem("cached_cart_data", JSON.stringify(cartData));
      setCachedCartData(cartData);
    }
  }, [cartData, cartLoading]);

  useEffect(() => {
    setIsMounted(true);
  }, []);

  // ── Logo + announcement text ────────────────────────────────────────────
  useEffect(() => {
    const fetchFreshLogo = async () => {
      try {
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || "/api/v2";
        const response = await fetch(`${baseUrl}/header-details`, {
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
          },
        });

        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

        const data = await response.json();
        localStorage.setItem("header_logo_data", JSON.stringify(data));
        localStorage.setItem("header_logo_time", Date.now().toString());

        if (data) {
          setLogoUrl(data.header_logo || null);
          setTopHeader(data.top_header || "");
          setTopPromo({
            text: data.top_promo_text || "",
            code: data.top_promo_code || "",
          });
        }
      } catch (error) {
        console.error("Error fetching fresh logo:", error);
        setLogoUrl(null);
      } finally {
        setIsLogoFetching(false);
      }
    };

    const fetchLogoWithCache = async () => {
      try {
        const cachedData = localStorage.getItem("header_logo_data");
        const cachedTime = localStorage.getItem("header_logo_time");
        const now = Date.now();

        // Paint from cache immediately, refresh in the background if stale.
        if (cachedData) {
          const parsedCache = JSON.parse(cachedData);
          if (parsedCache.header_logo) setLogoUrl(parsedCache.header_logo);
          if (parsedCache.top_header) setTopHeader(parsedCache.top_header);
          if (parsedCache.top_promo_text) {
            setTopPromo({
              text: parsedCache.top_promo_text,
              code: parsedCache.top_promo_code || "",
            });
          }
          setIsLogoFetching(false);

          if (cachedTime && now - Number(cachedTime) >= 60 * 60 * 1000) {
            fetchFreshLogo();
          }
          return;
        }

        await fetchFreshLogo();
      } catch (error) {
        console.error("Error fetching logo:", error);
        setLogoUrl(null);
        setIsLogoFetching(false);
      }
    };

    fetchLogoWithCache();
  }, []);

  // ── Cart + auth bootstrapping ───────────────────────────────────────────
  const loadInitialCart = async () => {
    if (!isMounted) return;
    try {
      await dispatch(getCartCount()).unwrap();
      await dispatch(fetchCart()).unwrap();
    } catch (error) {
      console.error("Failed to load initial cart:", error);
    } finally {
      setInitialLoadDone(true);
    }
  };

  useEffect(() => {
    if (!isMounted) return;

    const checkAuth = () => {
      const token = localStorage.getItem("token");
      const userData = localStorage.getItem("user");

      if (token && userData) {
        try {
          dispatch(setUser(JSON.parse(userData)));
        } catch (error) {
          console.error("Error parsing user data:", error);
          localStorage.removeItem("token");
          localStorage.removeItem("user");
        }
      } else {
        let guestId = localStorage.getItem("guest_id");
        if (!guestId) {
          guestId =
            "guest_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9);
          localStorage.setItem("guest_id", guestId);
        }
        dispatch(setTempUserId(guestId));
      }
    };

    checkAuth();
  }, [dispatch, isMounted]);

  useEffect(() => {
    if (isMounted && !initialLoadDone) loadInitialCart();
  }, [isMounted, initialLoadDone]);

  useEffect(() => {
    if (isAuthenticated && initialLoadDone) dispatch(fetchWishlist());
  }, [isAuthenticated, dispatch, initialLoadDone]);

  useEffect(() => {
    if (isMounted && initialLoadDone) loadInitialCart();
  }, [isAuthenticated, isMounted]);

  // ── Dismiss dropdowns ───────────────────────────────────────────────────
  useEffect(() => {
    if (!isMounted) return;

    const handleClickOutside = (event) => {
      if (
        userDropdownRef.current &&
        !userDropdownRef.current.contains(event.target)
      ) {
        setShowUserDropdown(false);
      }
    };

    // Escape closes the user dropdown — the previous header had no keyboard
    // dismissal at all. The cart drawer is a Sheet and handles its own.
    const handleEscape = (event) => {
      if (event.key !== "Escape") return;
      setShowUserDropdown(false);
    };

    document.addEventListener("mousedown", handleClickOutside);
    document.addEventListener("keydown", handleEscape);
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
      document.removeEventListener("keydown", handleEscape);
    };
  }, [isMounted]);

  const handleLogout = () => {
    localStorage.removeItem("token");
    localStorage.removeItem("user");

    const newGuestId =
      "guest_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9);
    localStorage.setItem("guest_id", newGuestId);

    dispatch(logoutAction());
    dispatch(setTempUserId(newGuestId));
    dispatch(clearCart());

    setInitialLoadDone(false);
    setShowUserDropdown(false);
    toast.success("Logged out successfully");
  };

  const handleLoginSuccess = (userData) => {
    dispatch(setUser(userData));
    setShowLogin(false);
    toast.success(`Welcome back, ${userData.name}!`);
  };

  const handleRegisterSuccess = (userData) => {
    dispatch(setUser(userData));
    setShowRegister(false);
    toast.success(`Welcome ${userData.name}! Your account has been created.`);
  };

  const handleCartClick = useCallback(
    async (e) => {
      e.preventDefault();

      setShowCartDrawer(true);

      // Open instantly against the cache, then reconcile in the background.
      if (cachedCartData) {
        setTimeout(() => {
          dispatch(fetchCart()).unwrap().catch(console.error);
        }, 100);
      } else {
        await dispatch(fetchCart()).unwrap();
      }
    },
    [cachedCartData, dispatch],
  );

  if (!isMounted) return <HeaderSkeleton />;

  // Up to eight categories reach the nav bar; how many are visible depends on
  // the width (see the `hidden … xl:/2xl:` steps below), the rest stay one
  // click away behind "All Collections".
  const allCategories = categories.filter((cat) => cat.id !== "all");
  const navCategories = allCategories.slice(0, 8);

  // Which nav entry matches the route. Category links carry their id in
  // `?categories=`, so the shop page counts as "All Collections" only while no
  // category is selected.
  const onShopPage = pathname === "/shop_page";
  const activeCategoryId = onShopPage ? searchParams.get("categories") : null;

  // Account and wishlist share one shape: a soft rounded icon button that grows
  // a text label from `lg` up.
  const actionButton =
    "relative inline-flex h-9 min-w-9 items-center justify-center gap-1.5 rounded-lg px-1.5 text-stone-700 transition-colors duration-150 hover:bg-stone-100/70 hover:text-amber-800 sm:h-10 sm:min-w-10 sm:px-2";

  const navLink =
    "nav-link inline-flex items-center whitespace-nowrap hover:text-amber-800 data-[active=true]:font-semibold data-[active=true]:text-amber-800";

  // Below `md` the cart button is only an icon. Empty, it shrinks to a plain
  // circle with the icon dead centre; with items it keeps the wider pill, whose
  // extra right padding makes room for the count badge.
  const hasCartItems = totalQuantity > 0;

  const cartLabel =
    totalQuantity > 0 && totalPrice > 0
      ? toTk(totalPrice)
      : `${totalQuantity} ${totalQuantity === 1 ? "Item" : "Items"}`;

  // Nav categories beyond the fourth wait for a wider screen.
  const navStep = (index) =>
    index < 4 ? "" : index < 6 ? "hidden xl:block" : "hidden 2xl:block";

  return (
    <>
      <div ref={headerRef} className="fixed inset-x-0 top-0 z-50">
        {/* ── Announcement strip ──────────────────────────────────────────
            Collapses to zero height on scroll rather than unmounting, so the
            transition is a smooth height change instead of a jump. */}
        <div
          className={`overflow-hidden transition-all duration-slow ease-standard ${
            isScrolled ? "max-h-0 opacity-0" : "max-h-24 opacity-100"
          }`}
        >
          <TopHeader
            topHeader={topHeader}
            promoText={topPromo.text}
            promoCode={topPromo.code}
          />
        </div>

        <header
          className={`relative w-full border-b border-stone-200/80 transition-shadow duration-base ease-standard ${
            isScrolled ? "shadow-e1" : ""
          }`}
        >
          {/* ── Main bar ────────────────────────────────────────────────
              Three columns — menu and logo, search, actions. Below `lg` the
              search drops to a second row spanning all three. */}
          <div className="bg-cream">
            <div className="container-page grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-2 gap-y-2 py-2.5 sm:gap-x-4 sm:py-3 lg:gap-x-8 lg:py-3.5">
              {/* Menu + logo */}
              <div className="col-start-1 row-start-1 flex min-w-0 items-center gap-2 sm:gap-3">
                <div className="shrink-0 lg:hidden">
                  <AllCategoryModal />
                </div>

                <Link
                  href="/"
                  className="group/logo block min-w-0 select-none rounded-sm"
                  aria-label="Misron — home"
                >
                  <BrandLogo
                    src={logoUrl}
                    pending={isLogoFetching}
                    imageClassName="h-8 max-w-[150px] sm:h-9 sm:max-w-[170px] lg:h-11 lg:max-w-[200px]"
                    wordmarkClassName="text-[1.375rem] sm:text-2xl md:text-[1.75rem] lg:text-[1.875rem]"
                  />
                </Link>
              </div>

              {/* Search */}
              <div className="col-span-3 row-start-2 min-w-0 lg:col-span-1 lg:col-start-2 lg:row-start-1 lg:mx-auto lg:w-full lg:max-w-xl xl:max-w-2xl">
                <SearchbarDesktop
                  variant="pill"
                  open={open}
                  setOpen={setOpen}
                  selectedCategory={selectedCategory}
                  setSelectedCategory={setSelectedCategory}
                  categories={categories}
                />
              </div>

              {/* ── Account cluster ──────────────────────────────────────*/}
              <div className="col-start-3 row-start-1 flex shrink-0 items-center gap-0.5 sm:gap-2 lg:gap-3">
                {/* Account */}
                <div className="relative" ref={userDropdownRef}>
                  {isAuthenticated && user ? (
                    <>
                      <button
                        type="button"
                        onClick={() => setShowUserDropdown(!showUserDropdown)}
                        aria-expanded={showUserDropdown}
                        aria-haspopup="menu"
                        aria-label={`Account menu for ${user.name}`}
                        className={actionButton}
                      >
                        <User size={20} strokeWidth={1.7} aria-hidden="true" />
                        <span className="type-caption hidden max-w-24 truncate font-medium lg:inline">
                          {user.name}
                        </span>
                      </button>

                      {showUserDropdown && (
                        <div
                          role="menu"
                          className="animate-slide-down absolute right-0 z-50 mt-2 w-60 overflow-hidden rounded-xl bg-surface-raised p-1.5 shadow-e3"
                        >
                          <div className="px-3 pb-2.5 pt-2">
                            <p className="type-caption text-ink-tertiary">Signed in as</p>
                            <p className="type-subhead truncate font-semibold text-ink">
                              {user.name}
                            </p>
                          </div>

                          <div className="divider mx-1 mb-1.5" />

                          {[
                            { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
                            { href: "/profile", label: "My Profile", icon: User },
                            { href: "/orders", label: "My Orders", icon: Package },
                          ].map(({ href, label, icon: Icon }) => (
                            <Link
                              key={href}
                              href={href}
                              role="menuitem"
                              onClick={() => setShowUserDropdown(false)}
                              className="type-subhead flex items-center gap-2.5 rounded-md px-3 py-2.5 font-medium text-ink transition-colors duration-150 hover:bg-surface-hover"
                            >
                              <Icon size={16} strokeWidth={1.75} aria-hidden="true" />
                              {label}
                            </Link>
                          ))}

                          <Link
                            href="/wishlist"
                            role="menuitem"
                            onClick={() => setShowUserDropdown(false)}
                            className="type-subhead flex items-center gap-2.5 rounded-md px-3 py-2.5 font-medium text-ink transition-colors duration-150 hover:bg-surface-hover"
                          >
                            <Heart size={16} strokeWidth={1.75} aria-hidden="true" />
                            <span className="flex-1">Wishlist</span>
                            {wishlistCount > 0 && (
                              <span className="badge badge-neutral tabular">
                                {wishlistCount}
                              </span>
                            )}
                          </Link>

                          <div className="divider mx-1 my-1.5" />

                          <button
                            type="button"
                            role="menuitem"
                            onClick={handleLogout}
                            className="type-subhead flex w-full items-center gap-2.5 rounded-md px-3 py-2.5 font-medium text-danger transition-colors duration-150 hover:bg-danger-tint"
                          >
                            <LogOut size={16} strokeWidth={1.75} aria-hidden="true" />
                            Log out
                          </button>
                        </div>
                      )}
                    </>
                  ) : (
                    <button
                      type="button"
                      onClick={() => setShowLogin(true)}
                      className={actionButton}
                      aria-label="Log in"
                    >
                      <User size={20} strokeWidth={1.7} aria-hidden="true" />
                      <span className="type-caption hidden font-medium lg:inline">
                        Account
                      </span>
                    </button>
                  )}
                </div>

                {/* Wishlist */}
                <Link
                  href="/wishlist"
                  className={actionButton}
                  aria-label={
                    wishlistCount > 0
                      ? `Wishlist, ${wishlistCount} items`
                      : "Wishlist"
                  }
                >
                  <span className="relative">
                    <Heart
                      size={20}
                      strokeWidth={1.7}
                      className={wishlistCount > 0 ? "fill-rose-500 text-rose-500" : ""}
                      aria-hidden="true"
                    />
                    {wishlistCount > 0 && (
                      <span className="absolute -right-2 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-none text-white shadow-xs">
                        {wishlistCount > 99 ? "99+" : wishlistCount}
                      </span>
                    )}
                  </span>
                  <span className="type-caption hidden font-medium lg:inline">
                    Wishlist
                  </span>
                </Link>

                {/* Cart — a dark pill that carries the running total from `md` up. */}
                <div className="ml-2 sm:ml-1 lg:ml-2">
                  <button
                    type="button"
                    onClick={handleCartClick}
                    aria-haspopup="dialog"
                    aria-expanded={showCartDrawer}
                    aria-label={
                      totalQuantity > 0 ? `Cart, ${totalQuantity} items` : "Cart"
                    }
                    className={`relative inline-flex h-9 items-center gap-4 rounded-full bg-stone-900 py-0 text-white shadow-sm transition-all duration-150 ease-standard hover:bg-[#2D2825] hover:shadow active:scale-95 sm:h-10 ${
                      hasCartItems
                        ? "pl-3.5 pr-4 sm:pl-4"
                        : "w-9 justify-center sm:w-10 md:w-auto md:justify-start md:px-4"
                    }`}
                  >
                    <span className={`relative ${hasCartItems ? "mr-1" : "md:mr-1"}`}>
                      <ShoppingBag size={20} strokeWidth={1.75} aria-hidden="true" />
                      {!cartLoading && totalQuantity > 0 && (
                        <span className="absolute -right-3.5 -top-3 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-bold leading-none text-stone-950 ring-2 ring-stone-900">
                          {totalQuantity > 99 ? "99+" : totalQuantity}
                        </span>
                      )}
                    </span>

                    <span className="hidden min-w-12 flex-col items-start whitespace-nowrap text-left leading-none md:flex">
                      <span className="text-[10px] uppercase tracking-wider text-stone-400">
                        Cart
                      </span>
                      <span className="tabular mt-1 text-xs font-medium text-amber-200">
                        {cartLabel}
                      </span>
                    </span>
                  </button>

                  {showCartDrawer && (
                    <CartDrawer
                      loading={cartLoading}
                      onClose={() => setShowCartDrawer(false)}
                    />
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* ── Category nav ─────────────────────────────────────────────
              Laptop and up; below `lg` the drawer behind the menu button
              takes over. "All Collections" opens the full menu
              panel, which lists
              every category with its children. The list scrolls sideways
              rather than wrapping if the admin's category names run long. */}
          <nav
            aria-label="Primary"
            className="hidden border-t border-stone-200/60 bg-white lg:block"
          >
            <div className="container-page overflow-x-auto scrollbar-hide">
              <ul className="mx-auto flex w-max items-center justify-center gap-8 py-2.5 text-[13.5px] font-normal text-stone-600">
                <li>
                  <Link href="/" className={navLink} data-active={pathname === "/"}>
                    Home
                  </Link>
                </li>

                <li>
                  <button
                    type="button"
                    onClick={() => setShowMenuPanel((v) => !v)}
                    aria-expanded={showMenuPanel}
                    aria-controls="header-menu-panel"
                    className={navLink}
                    data-active={showMenuPanel || (onShopPage && !activeCategoryId)}
                  >
                    All Collections
                  </button>
                </li>

                {navCategories.map((cat, index) => (
                  <li key={cat.id} className={navStep(index)}>
                    <Link
                      href={`/shop_page?categories=${cat.id}`}
                      className={navLink}
                      data-active={String(cat.id) === activeCategoryId}
                    >
                      {cat.name}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          </nav>

          {/* ── Menu panel ───────────────────────────────────────────────
              Drops below the nav as a full-width sheet listing every category
              with its children inline. */}
          {showMenuPanel && (
            <div
              id="header-menu-panel"
              className="animate-slide-down absolute inset-x-0 top-full hidden max-h-[70vh] overflow-y-auto border-b border-stone-200/80 bg-white shadow-e3 lg:block"
            >
              <div className="container-page py-8">
                <div className="grid grid-cols-2 gap-x-8 gap-y-8 xl:grid-cols-4 2xl:grid-cols-5">
                  <div>
                    <Link
                      href="/shop_page"
                      onClick={() => setShowMenuPanel(false)}
                      className="type-subhead font-semibold uppercase tracking-[0.08em] text-ink transition-colors duration-150 hover:text-amber-800"
                    >
                      All Collections
                    </Link>
                  </div>

                  {/* The children arrive nested in the same payload as their
                      parent, so there is nothing left to wait on here — the
                      per-category loading skeletons this block used to show
                      are gone with the per-category requests. */}
                  {categories
                    .filter((cat) => cat.id !== "all")
                    .map((cat) => (
                      <div key={cat.id} className="min-w-0">
                        <Link
                          href={`/shop_page?categories=${cat.id}`}
                          onClick={() => setShowMenuPanel(false)}
                          className="type-subhead font-semibold uppercase tracking-[0.08em] text-ink transition-colors duration-150 hover:text-amber-800"
                        >
                          {cat.name}
                        </Link>

                        {cat.children.length > 0 && (
                          <div className="mt-3 space-y-1">
                            {cat.children.map((sub) => (
                              <Link
                                key={sub.id}
                                href={`/shop_page?categories=${sub.id}`}
                                onClick={() => setShowMenuPanel(false)}
                                className="type-footnote flex items-center gap-2 text-ink-secondary transition-colors duration-150 hover:text-amber-800"
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
                            ))}
                          </div>
                        )}
                      </div>
                    ))}
                </div>
              </div>
            </div>
          )}
        </header>

        {/* Click-away scrim. Sits below the header but above the page. */}
        {showMenuPanel && (
          <div
            className="fixed inset-0 -z-10 hidden lg:block"
            onClick={() => setShowMenuPanel(false)}
            aria-hidden="true"
          />
        )}
      </div>


      <LoginModal
        open={showLogin}
        onClose={() => setShowLogin(false)}
        onSwitch={() => {
          setShowLogin(false);
          setShowRegister(true);
        }}
        onLoginSuccess={handleLoginSuccess}
      />

      <RegisterModal
        open={showRegister}
        onClose={() => setShowRegister(false)}
        onSwitch={() => {
          setShowRegister(false);
          setShowLogin(true);
        }}
        onRegisterSuccess={handleRegisterSuccess}
      />
    </>
  );
}
