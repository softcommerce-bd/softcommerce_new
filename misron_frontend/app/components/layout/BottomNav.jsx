"use client";

/**
 * Mobile tab bar.
 *
 * Frosted light bar rather than a solid dark one, so it layers over content
 * the way an iOS tab bar does. The active tab is marked by ink weight and a
 * filled icon, not a colour swap — the previous version dimmed inactive icons
 * to 50% white, which put every label below contrast minimums.
 *
 * `pb-[env(safe-area-inset-bottom)]` keeps the row clear of the iPhone home
 * indicator.
 */

import Link from "next/link";
import { useState } from "react";
import { usePathname } from "next/navigation";
import { Home, LayoutGrid, ShoppingBag, MessageCircle, User } from "lucide-react";

import { useAppSelector } from "@/store/hooks";
import useCachedFetch from "@/app/utils/useCachedFetch";
import AllCategoryModal from "./AllCategoryModal";

const formatWhatsAppNumber = (number) => {
  let cleanNumber = number.toString().replace(/[\s\-\(\)]/g, "");
  if (!cleanNumber.startsWith("+")) {
    cleanNumber = cleanNumber.startsWith("00")
      ? "+" + cleanNumber.substring(2)
      : "+" + cleanNumber;
  }
  return cleanNumber;
};

/** One tab. Renders as a link, an external link or a button as needed. */
function Tab({ icon: Icon, label, active = false, href, external, onClick, badge }) {
  const content = (
    <>
      <span className="relative">
        <Icon
          size={21}
          strokeWidth={active ? 2.2 : 1.75}
          className={active ? "text-ink" : "text-ink-tertiary"}
          aria-hidden="true"
        />
        {badge > 0 && (
          <span className="badge-count absolute -right-2 -top-1.5">
            {badge > 99 ? "99+" : badge}
          </span>
        )}
      </span>
      <span
        className={`text-[0.625rem] font-semibold tracking-[0.02em] ${
          active ? "text-ink" : "text-ink-tertiary"
        }`}
      >
        {label}
      </span>
    </>
  );

  const className =
    "flex flex-1 flex-col items-center justify-center gap-1 py-2.5 transition-colors duration-150 active:opacity-60";

  if (href) {
    return (
      <Link
        href={href}
        className={className}
        aria-current={active ? "page" : undefined}
        {...(external ? { target: "_blank", rel: "noopener noreferrer" } : {})}
      >
        {content}
      </Link>
    );
  }

  return (
    <button type="button" onClick={onClick} className={className}>
      {content}
    </button>
  );
}

export default function BottomNav({ onAccountClick }) {
  const pathname = usePathname();
  const [isCategoryOpen, setIsCategoryOpen] = useState(false);
  const { isAuthenticated } = useAppSelector((state) => state.auth);
  const { totalQuantity } = useAppSelector((state) => state.cart);

  const { data: footerData } = useCachedFetch(
    `${process.env.NEXT_PUBLIC_API_URL}/footer-data`,
    "footer-data",
    30 * 60 * 1000,
  );

  const whatsappNumber = footerData?.contact_phone;
  const whatsappUrl = whatsappNumber
    ? `https://wa.me/${formatWhatsAppNumber(whatsappNumber)}`
    : null;

  const isHome = pathname === "/";
  const isCart = pathname === "/view-cart";
  const isAccount = pathname?.startsWith("/dashboard");

  return (
    <>
      <nav
        /* Solid surface, not `surface-blur`. At 0.72 alpha the product imagery
           the bar scrolls over showed straight through it, and the icons and
           labels lost their contrast against whatever photo happened to be
           underneath. The bar is permanent chrome, so it gets a real ground. */
        /* `px-2` holds the outer tabs off the screen edge; without it the first
           and last icons sit flush against it while the inner ones look inset. */
        className="fixed inset-x-0 bottom-0 z-40 flex items-stretch border-t border-hairline bg-surface px-2 pb-[env(safe-area-inset-bottom)] shadow-[0_-1px_3px_rgba(0,0,0,0.04)] lg:hidden"
        aria-label="Primary mobile"
      >
        <Tab icon={Home} label="Home" href="/" active={isHome} />

        <Tab
          icon={LayoutGrid}
          label="Browse"
          active={isCategoryOpen}
          onClick={() => setIsCategoryOpen(true)}
        />

        <Tab
          icon={ShoppingBag}
          label="Cart"
          href="/view-cart"
          active={isCart}
          badge={totalQuantity}
        />

        {whatsappUrl && (
          <Tab icon={MessageCircle} label="Message" href={whatsappUrl} external />
        )}

        {isAuthenticated ? (
          <Tab icon={User} label="Account" href="/dashboard" active={isAccount} />
        ) : (
          <Tab icon={User} label="Account" onClick={onAccountClick} />
        )}
      </nav>

      <AllCategoryModal
        hideTrigger
        open={isCategoryOpen}
        onOpenChange={setIsCategoryOpen}
      />
    </>
  );
}
