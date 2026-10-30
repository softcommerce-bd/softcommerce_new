"use client";

/**
 * Breadcrumb trail.
 *
 * Callers pass an `items` array; when none is given the trail is derived from
 * the current path as before.
 *
 * NOTE: the previous version declared no props at all and always derived from
 * the pathname, so the `items` the shop page and others were passing were
 * silently ignored — a search page showed "Shop Page" rather than the search
 * term. Honouring the prop is a behaviour fix, not only a visual one.
 *
 * Separators are chevrons rather than typed slashes, and the current page is
 * marked with `aria-current` instead of only a heavier weight.
 */

import Link from "next/link";
import { usePathname } from "next/navigation";
import { ChevronRight } from "lucide-react";

export default function Breadcrumb({ items, className = "" }) {
  const pathname = usePathname();

  // Derived trail: "/shop_page/shoes" → Home / Shop Page / Shoes
  const derived = pathname
    .split("/")
    .filter(Boolean)
    .map((part, index, parts) => ({
      href: "/" + parts.slice(0, index + 1).join("/"),
      name: decodeURIComponent(part)
        .replace(/[-_]/g, " ")
        .replace(/\b\w/g, (l) => l.toUpperCase()),
    }));

  const crumbs = items?.length ? items : [{ name: "Home", href: "/" }, ...derived];

  return (
    <nav aria-label="Breadcrumb" className={className}>
      <ol className="flex flex-wrap items-center gap-1.5">
        {crumbs.map((crumb, index) => {
          const isLast = index === crumbs.length - 1;

          return (
            <li key={`${crumb.href}-${index}`} className="flex items-center gap-1.5">
              {index > 0 && (
                <ChevronRight
                  size={13}
                  className="shrink-0 text-ink-quaternary"
                  aria-hidden="true"
                />
              )}

              {isLast ? (
                <span
                  aria-current="page"
                  className="type-footnote max-w-[200px] truncate font-medium text-ink sm:max-w-none"
                >
                  {crumb.name}
                </span>
              ) : (
                <Link
                  href={crumb.href}
                  className="type-footnote text-ink-tertiary transition-colors duration-fast hover:text-amber-800"
                >
                  {crumb.name}
                </Link>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
