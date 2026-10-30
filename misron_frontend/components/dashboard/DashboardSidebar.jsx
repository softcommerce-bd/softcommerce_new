"use client";

/**
 * Account dashboard navigation.
 *
 * Rebuilt on the design system. The dashboard was styled in indigo
 * (`bg-indigo-50`, `text-indigo-600`) which matched nothing else in the app;
 * the active state is now the brand tint with ink text.
 *
 * Heroicons — the only place in the app still using that library — are
 * replaced by lucide, so the icon set is uniform. The separate solid-variant
 * icons for active items go with them: weight now carries the active state.
 *
 * The scrim also used `bg-opacity-50`, removed in Tailwind v4, so it rendered
 * fully opaque black over the page; it is now a translucent ink scrim.
 *
 * Navigation structure, the referral-code check and logout are unchanged.
 */

import { useState, useEffect } from "react";
import { usePathname } from "next/navigation";
import Link from "next/link";
import {
  LayoutDashboard,
  ShoppingBag,
  User,
  Users,
  BarChart3,
  Settings,
  Wallet,
  CreditCard,
  ChevronDown,
  LogOut,
  X,
} from "lucide-react";

const baseNavigation = [
  { name: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
  { name: "My Purchases", href: "/dashboard/purchases", icon: ShoppingBag },
  { name: "My Profile", href: "/dashboard/profile", icon: User },
];

const affiliateNavigation = {
  name: "Affiliate",
  href: "#",
  icon: Users,
  isDropdown: true,
  children: [
    { name: "Overview", href: "/dashboard/affiliate", icon: BarChart3 },
    {
      name: "Payment Settings",
      href: "/dashboard/affiliate/payment-settings",
      icon: Settings,
    },
    {
      name: "Payment History",
      href: "/dashboard/affiliate/payment-history",
      icon: Wallet,
    },
    {
      name: "Withdraw History",
      href: "/dashboard/affiliate/withdraw-history",
      icon: CreditCard,
    },
  ],
};

export default function DashboardSidebar({ isOpen, setIsOpen }) {
  const pathname = usePathname();
  const [expandedMenus, setExpandedMenus] = useState({});
  const [navigation, setNavigation] = useState(baseNavigation);
  const [isLoading, setIsLoading] = useState(true);

  // The affiliate section only exists for users who have a referral code.
  useEffect(() => {
    try {
      const userData = localStorage.getItem("user");
      if (userData) {
        const user = JSON.parse(userData);
        const hasReferral = user.referral_code && user.referral_code !== null;
        setNavigation(
          hasReferral ? [...baseNavigation, affiliateNavigation] : baseNavigation,
        );
      } else {
        setNavigation(baseNavigation);
      }
    } catch (error) {
      console.error("Error loading user data:", error);
      setNavigation(baseNavigation);
    } finally {
      setIsLoading(false);
    }
  }, []);

  const toggleSubmenu = (name) => {
    setExpandedMenus((prev) => ({ ...prev, [name]: !prev[name] }));
  };

  const handleLogout = () => {
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    window.location.href = "/";
  };

  const isMenuActive = (item) => {
    if (item.children) {
      return item.children.some((child) => pathname === child.href);
    }
    return pathname === item.href;
  };

  // Tapping a link on mobile closes the drawer behind it.
  const closeOnMobile = () => {
    if (window.innerWidth < 1024) setIsOpen(false);
  };

  const rowBase =
    "group flex items-center gap-3 rounded-md px-3 py-2.5 text-[0.875rem] font-medium transition-colors duration-fast";

  return (
    <>
      {isOpen && (
        <div
          className="fixed inset-0 z-20 bg-ink/40 backdrop-blur-[2px] lg:hidden"
          onClick={closeOnMobile}
          aria-hidden="true"
        />
      )}

      <aside
        className={`fixed left-0 top-[var(--header-height,80px)] z-30 flex h-[calc(100vh-var(--header-height,80px))] w-64 transform flex-col border-r border-hairline bg-surface transition-transform duration-base ease-standard lg:translate-x-0 ${
          isOpen ? "translate-x-0" : "-translate-x-full"
        }`}
        aria-label="Dashboard"
      >
        <div className="flex h-14 items-center justify-between px-4 lg:hidden">
          <span className="type-eyebrow text-ink-tertiary">Account</span>
          <button
            type="button"
            onClick={closeOnMobile}
            className="btn-icon btn-icon-sm btn-icon-round"
            aria-label="Close menu"
          >
            <X size={18} aria-hidden="true" />
          </button>
        </div>

        <nav className="scrollbar-subtle flex-1 space-y-1 overflow-y-auto px-3 py-4">
          {isLoading
            ? [0, 1, 2, 3].map((i) => (
                <div key={i} className="skeleton h-10 w-full rounded-md" />
              ))
            : navigation.map((item) => {
                if (item.isDropdown) {
                  const isActive = isMenuActive(item);
                  const isExpanded = expandedMenus[item.name] || isActive;

                  return (
                    <div key={item.name}>
                      <button
                        type="button"
                        onClick={() => toggleSubmenu(item.name)}
                        aria-expanded={isExpanded}
                        className={`${rowBase} w-full justify-between ${
                          isActive
                            ? "bg-brand-tint text-ink"
                            : "text-ink-secondary hover:bg-surface-hover hover:text-ink"
                        }`}
                      >
                        <span className="flex items-center gap-3">
                          <item.icon
                            size={17}
                            strokeWidth={isActive ? 2 : 1.75}
                            aria-hidden="true"
                          />
                          {item.name}
                        </span>
                        <ChevronDown
                          size={15}
                          className={`transition-transform duration-base ease-standard ${
                            isExpanded ? "rotate-180" : ""
                          }`}
                          aria-hidden="true"
                        />
                      </button>

                      {isExpanded && item.children && (
                        // Indented and hung off a rule, so the nesting reads
                        // without a second level of filled backgrounds.
                        <div className="ml-[22px] mt-1 space-y-1 border-l border-hairline pl-3">
                          {item.children.map((child) => {
                            const isChildActive = pathname === child.href;
                            return (
                              <Link
                                key={child.name}
                                href={child.href}
                                onClick={closeOnMobile}
                                aria-current={isChildActive ? "page" : undefined}
                                className={`${rowBase} py-2 text-[0.8125rem] ${
                                  isChildActive
                                    ? "bg-brand-tint text-ink"
                                    : "text-ink-tertiary hover:bg-surface-hover hover:text-ink"
                                }`}
                              >
                                <child.icon
                                  size={15}
                                  strokeWidth={1.75}
                                  aria-hidden="true"
                                />
                                {child.name}
                              </Link>
                            );
                          })}
                        </div>
                      )}
                    </div>
                  );
                }

                const isActive = pathname === item.href;
                return (
                  <Link
                    key={item.name}
                    href={item.href}
                    onClick={closeOnMobile}
                    aria-current={isActive ? "page" : undefined}
                    className={`${rowBase} ${
                      isActive
                        ? "bg-brand-tint text-ink"
                        : "text-ink-secondary hover:bg-surface-hover hover:text-ink"
                    }`}
                  >
                    <item.icon
                      size={17}
                      strokeWidth={isActive ? 2 : 1.75}
                      aria-hidden="true"
                    />
                    {item.name}
                  </Link>
                );
              })}
        </nav>

        <div className="border-t border-hairline p-3">
          <button
            type="button"
            onClick={handleLogout}
            className={`${rowBase} w-full text-danger hover:bg-danger-tint`}
          >
            <LogOut size={17} strokeWidth={1.75} aria-hidden="true" />
            Sign out
          </button>
        </div>
      </aside>
    </>
  );
}
