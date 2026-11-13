"use client";

/**
 * Mobile bottom bar, in the style of Facebook's: four icons, no labels.
 * Home, Reels, Shop, Flash Deal. The active tab is the filled icon in the brand colour with a
 * short bar above it. Shown below `lg` only; the safe-area padding keeps it off the iPhone home
 * indicator. Reels opens the full-screen video viewer instead of going to a page.
 */

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useDispatch } from "react-redux";
import { Clapperboard, House, Store, Zap } from "lucide-react";

import { openVideoModal } from "@/store/slices/videoModalSlice";

function Tab({ icon: Icon, label, href, active = false, onClick, solid = true }) {
  const inner = (
    <>
      <span
        className={`absolute inset-x-5 top-0 h-[3px] rounded-b-full transition-colors ${
          active ? "bg-brand" : "bg-transparent"
        }`}
        aria-hidden="true"
      />
      <Icon
        size={26}
        strokeWidth={active ? 2.4 : 1.8}
        fill={active && solid ? "currentColor" : "none"}
        className={active ? "text-brand" : "text-stone-500"}
        aria-hidden="true"
      />
    </>
  );
  const className =
    "relative flex h-12 flex-1 items-center justify-center transition-transform active:scale-90";

  return href ? (
    <Link href={href} aria-label={label} aria-current={active ? "page" : undefined} className={className}>
      {inner}
    </Link>
  ) : (
    <button type="button" aria-label={label} onClick={onClick} className={className}>
      {inner}
    </button>
  );
}

export default function BottomNav() {
  const pathname = usePathname() || "/";
  const dispatch = useDispatch();

  return (
    <nav
      aria-label="Primary mobile"
      className="fixed inset-x-0 bottom-0 z-40 flex items-stretch border-t border-stone-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden"
    >
      <Tab icon={House} label="Home" href="/" active={pathname === "/"} />
      <Tab icon={Clapperboard} solid={false} label="Reels" onClick={() => dispatch(openVideoModal({ reels: true }))} />
      <Tab icon={Store} solid={false} label="Shop" href="/shop_page" active={pathname.startsWith("/shop_page")} />
      <Tab icon={Zap} label="Flash Deal" href="/flash-deal" active={pathname.startsWith("/flash-deal")} />
    </nav>
  );
}
