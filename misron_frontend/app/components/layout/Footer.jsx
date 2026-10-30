"use client";

/**
 * Site footer.
 *
 * Follows the LORÉVA reference design (see reference-design.md §2.16): a dark
 * `#181614` ground, a brand column, two link columns, a "Get in Touch" column
 * and a bottom bar with the copyright and payment options. One responsive
 * layout serves every breakpoint — one column on a phone, two from `md`, the
 * 4 / 2 / 2 / 4 split from `lg`.
 *
 * The ground and ink are fixed to the reference palette on purpose: the admin's
 * footer colour no longer reaches this component.
 *
 * Still admin-driven: logo, blurb, social links, the about and useful-links
 * menus, contact details, copyright and the payment-methods image. Categories
 * come from the same tree the menu reads.
 */

import { useEffect, useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { Clock, Mail, MapPin, Phone } from "lucide-react";

import imageLoader from "@/lib/imageLoader";
import SocialIcons from "../SocialIcons";
import useCachedFetch from "../../utils/useCachedFetch";
import { Skeleton } from "../ui";

/** Quick Links shows Home, All Collections, this many categories, then Track Order. */
const QUICK_LINK_CATEGORY_LIMIT = 4;

export default function Footer() {
  // Shared with the header strip, the floating social bar and the mobile nav,
  // which all read the same payload.
  const { data: footerData, loading } = useCachedFetch(
    `${process.env.NEXT_PUBLIC_API_URL}/footer-data`,
    "footer-data",
    30 * 60 * 1000,
  );

  // Same tree the menu reads, so the footer lists categories in the same order.
  const { data: categories } = useCachedFetch(
    `${process.env.NEXT_PUBLIC_API_URL}/categories/tree`,
    "category-tree",
    10 * 60 * 1000,
  );

  if (loading) {
    return (
      <footer className="border-t border-stone-800 bg-[#181614]">
        <div className="container-page py-14 sm:py-16">
          <div className="grid gap-10 md:grid-cols-2 lg:grid-cols-12">
            {[0, 1, 2, 3].map((i) => (
              <div key={i} className="space-y-3 lg:col-span-3">
                <Skeleton className="h-3 w-24 bg-stone-800" />
                <Skeleton className="h-3 w-full bg-stone-800" />
                <Skeleton className="h-3 w-4/5 bg-stone-800" />
                <Skeleton className="h-3 w-3/5 bg-stone-800" />
              </div>
            ))}
          </div>
        </div>
      </footer>
    );
  }

  if (!footerData) return null;

  const quickLinks = [
    { label: "Home", href: "/" },
    { label: "All Collections", href: "/shop_page" },
    ...(categories || []).slice(0, QUICK_LINK_CATEGORY_LIMIT).map((category) => ({
      label: category.name,
      href: `/shop_page?categories=${category.id}`,
    })),
    { label: "Track Order", href: "/track-order" },
  ];

  // The admin keeps "about" and "useful links" as two menus; the reference has
  // one "About & Policies" column, so the two are listed together.
  const aboutItems = (
    footerData.about_menu?.length > 0 ? footerData.about_menu : STATIC_ABOUT_MENU
  ).map((item) => ({
    label: item.label,
    href: item.href ?? resolveMenuLink(item.link),
  })).filter((item) => item.href);
  const policyItems = (footerData.useful_links_menu || []).map((item) => ({
    label: item.label,
    href: resolveMenuLink(item.link),
  })).filter((item) => item.href);

  const phone = footerData.contact_phone;
  const email = footerData.contact_email;
  const address = footerData.contact_address;
  const worktime = footerData.contact_worktime;

  return (
    <footer className="border-t border-stone-800 bg-[#181614] pb-12 pt-14 text-stone-300 sm:pt-16">
      <div className="container-page">
        <div className="grid grid-cols-1 gap-10 border-b border-stone-800 pb-12 sm:gap-12 md:grid-cols-2 lg:grid-cols-12">
          {/* ── Brand ─────────────────────────────────────────────────── */}
          <div className="space-y-4 lg:col-span-4">
            {footerData.logo && <FooterLogo src={footerData.logo} />}

            {/* `.footer-richtext` pins every descendant, itself included, to
                its parent's type, so the size and colour sit on this wrapper. */}
            <div className="max-w-sm text-xs leading-relaxed text-stone-400">
              <div
                className="footer-richtext"
                dangerouslySetInnerHTML={{
                  __html: footerData.footer_description?.trim() || STATIC_FOOTER_DESCRIPTION,
                }}
              />
            </div>

            {footerData.show_social_links && (
              <div className="pt-2 text-stone-400">
                <SocialIcons
                  socialLinks={footerData.social_links}
                  className="h-4 w-4"
                  gap="gap-3"
                  // `SocialIcons` fades on hover and only transitions opacity;
                  // the reference tints the icon and washes the disc instead.
                  linkClassName="h-8 w-8 rounded-full bg-stone-900 transition-colors! hover:bg-stone-800 hover:text-amber-400 hover:opacity-100!"
                />
              </div>
            )}
          </div>

          {/* ── Quick Links ───────────────────────────────────────────── */}
          <FooterColumn title="Quick Links" items={quickLinks} className="lg:col-span-2" />

          {/* ── About & Policies ──────────────────────────────────────── */}
          <FooterColumn
            title="About & Policies"
            items={[...aboutItems, ...policyItems]}
            className="lg:col-span-2"
          />

          {/* ── Get in Touch ──────────────────────────────────────────── */}
          <div className="space-y-3 lg:col-span-4">
            <h3 className="type-footer-heading text-white">Get in Touch</h3>
            <div className="space-y-2.5 text-xs text-stone-400">
              {phone && (
                <ContactRow icon={Phone}>
                  Customer Care:{" "}
                  <a
                    href={`tel:${String(phone).replace(/[^\d+]/g, "")}`}
                    className="font-mono font-bold text-stone-200 transition-colors hover:text-amber-400"
                  >
                    {phone}
                  </a>
                </ContactRow>
              )}
              {email && (
                <ContactRow icon={Mail}>
                  Email:{" "}
                  <a
                    href={`mailto:${email}`}
                    className="break-all font-bold text-stone-200 transition-colors hover:text-amber-400"
                  >
                    {email}
                  </a>
                </ContactRow>
              )}
              {address && <ContactRow icon={MapPin}>{address}</ContactRow>}
              {worktime && <ContactRow icon={Clock}>{worktime}</ContactRow>}
            </div>
          </div>
        </div>

        {/* ── Bottom bar ───────────────────────────────────────────────── */}
        <div className="flex flex-col items-center justify-between gap-4 pt-6 text-xs text-stone-500 sm:flex-row">
          <div className="text-center sm:text-left">
            <div
              className="footer-richtext"
              dangerouslySetInnerHTML={{ __html: footerData.copyright || "" }}
            />
          </div>

          <div className="flex items-center gap-3">
            <span className="text-[11px] text-stone-400">Secure Payment Options:</span>
            <div className="flex items-center gap-2 rounded-lg border border-stone-800 bg-stone-900 px-3 py-1.5">
              {footerData.payment_methods_image ? (
                <div className="relative h-5 w-48">
                  <Image
                    src={footerData.payment_methods_image}
                    alt="Accepted payment methods"
                    fill
                    className="object-contain object-left"
                    sizes="192px"
                  />
                </div>
              ) : (
                PAYMENT_BADGES.map(({ label, className }) => (
                  <span key={label} className={`text-xs font-bold tracking-wider ${className}`}>
                    {label}
                  </span>
                ))
              )}
            </div>
          </div>
        </div>
      </div>
    </footer>
  );
}

/** One link column. Quick Links and About & Policies share this shape. */
function FooterColumn({ title, items, className = "" }) {
  if (!items || items.length === 0) return null;

  return (
    <div className={`space-y-3 ${className}`}>
      <h3 className="type-footer-heading text-white">{title}</h3>
      <ul className="space-y-2 text-xs text-stone-400">
        {items.map((item, idx) => (
          <li key={`${item.label}-${idx}`}>
            <Link href={item.href} className="transition-colors hover:text-amber-400">
              {item.label}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}

/**
 * Footer logo, cropped to its visible pixels.
 *
 * Logos are often uploaded on a large transparent canvas: `loreva.png` is an
 * 800×800 square with the mark in a 457×98 strip. `object-contain` scales the
 * whole canvas into the box, so the mark itself comes out a few pixels tall.
 * The file is read once in the browser, its transparent margin cut off, and
 * the crop shown instead. If the file cannot be read, it is shown as-is.
 */
function FooterLogo({ src }) {
  // `{ src, url }` once `src` has been measured. `url` is null when there was
  // nothing to cut or the file could not be read.
  const [trim, setTrim] = useState(null);

  useEffect(() => {
    let cancelled = false;
    let url = null;

    trimTransparentMargin(src).then((result) => {
      url = result;
      if (cancelled) {
        if (url) URL.revokeObjectURL(url);
        return;
      }
      setTrim({ src, url });
    });

    return () => {
      cancelled = true;
      if (url) URL.revokeObjectURL(url);
    };
  }, [src]);

  // The box has a fixed size, so holding the image back until it is measured
  // shifts nothing and avoids a flash of the tiny uncropped logo.
  const ready = trim?.src === src;

  return (
    <Link href="/" className="block h-14 w-60">
      <div className="relative h-full w-full">
        {ready && (
          <Image
            src={trim.url || src}
            alt="Logo"
            fill
            className="object-contain object-left"
            sizes="240px"
          />
        )}
      </div>
    </Link>
  );
}

/** Icon plus text row in the "Get in Touch" column. */
function ContactRow({ icon: Icon, children }) {
  return (
    <p className="flex items-start gap-2">
      <Icon className="mt-px h-3.5 w-3.5 shrink-0 text-amber-400" aria-hidden="true" />
      <span>{children}</span>
    </p>
  );
}

/**
 * Payment options shown when the admin has not uploaded a payment-methods
 * image. These are the methods the checkout offers.
 */
const PAYMENT_BADGES = [
  { label: "bKash", className: "text-rose-400" },
  { label: "Nagad", className: "text-amber-500" },
  { label: "VISA", className: "text-blue-400" },
  { label: "Mastercard", className: "text-orange-400" },
  { label: "COD", className: "text-emerald-400" },
];

/**
 * Fallback "About" links, used only when the admin has not configured the
 * about widget in Website Settings > Footer.
 */
const STATIC_ABOUT_MENU = [
  { label: "Our Story", href: "/pages/our-story" },
  { label: "Our Mission", href: "/pages/our-mission" },
  { label: "Our Vision", href: "/pages/our-vision" },
  { label: "Our Values", href: "/pages/our-values" },
  { label: "Craftsmanship & Quality", href: "/pages/craftsmanship-quality" },
];

/**
 * Brand blurb under the footer logo, used only when `footer_description` is
 * blank in Website Settings > Footer — otherwise the logo sits above empty space.
 */
const STATIC_FOOTER_DESCRIPTION =
  "Misron crafts footwear for people who walk their own path — leather chosen by hand, soles built to last, and a fit that stays comfortable from the first step.";

/**
 * Width requested from the image optimizer when measuring the logo. It must be
 * one of Next's `deviceSizes`; the optimizer never upscales, so a smaller
 * upload comes back at its own size.
 */
const TRIM_SOURCE_WIDTH = 1080;

/** Pixels at or below this alpha count as transparent margin. */
const TRIM_ALPHA_THRESHOLD = 8;

/**
 * Crop an image to the bounding box of its non-transparent pixels.
 *
 * The file is loaded through the optimizer, which is same-origin, so the
 * canvas is not tainted and its pixels can be read. Resolves to a blob URL of
 * the crop, or null when there is no margin to cut or the file cannot be read.
 */
function trimTransparentMargin(src) {
  return new Promise((resolve) => {
    // `window.Image`, not the `next/image` component imported above.
    const img = new window.Image();

    img.onload = () => {
      try {
        const { naturalWidth: width, naturalHeight: height } = img;
        const canvas = document.createElement("canvas");
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext("2d", { willReadFrequently: true });
        context.drawImage(img, 0, 0);
        const { data } = context.getImageData(0, 0, width, height);

        let left = width;
        let top = height;
        let right = -1;
        let bottom = -1;
        for (let y = 0; y < height; y++) {
          for (let x = 0; x < width; x++) {
            if (data[(y * width + x) * 4 + 3] > TRIM_ALPHA_THRESHOLD) {
              if (x < left) left = x;
              if (x > right) right = x;
              if (y < top) top = y;
              bottom = y;
            }
          }
        }

        const fullyTransparent = right < 0;
        const noMargin = left === 0 && top === 0 && right === width - 1 && bottom === height - 1;
        if (fullyTransparent || noMargin) {
          resolve(null);
          return;
        }

        const cropWidth = right - left + 1;
        const cropHeight = bottom - top + 1;
        const crop = document.createElement("canvas");
        crop.width = cropWidth;
        crop.height = cropHeight;
        crop
          .getContext("2d")
          .drawImage(canvas, left, top, cropWidth, cropHeight, 0, 0, cropWidth, cropHeight);
        crop.toBlob((blob) => resolve(blob ? URL.createObjectURL(blob) : null), "image/png");
      } catch {
        // A tainted canvas makes `getImageData` throw.
        resolve(null);
      }
    };
    img.onerror = () => resolve(null);
    img.src = imageLoader({ src, width: TRIM_SOURCE_WIDTH });
  });
}

/**
 * Routes the admin menu may link to that the storefront has no page for yet.
 * Links to them are dropped from the footer, so Next.js never prefetches a 404.
 * Remove an entry once its page exists.
 */
const UNAVAILABLE_ROUTES = ["/flash-deal"];

function isUnavailableRoute(href) {
  const path = href.replace(/^(https?:)?\/\/[^/]+/i, "").replace(/^\/?/, "/");
  return UNAVAILABLE_ROUTES.some(
    (route) => path === route || path.startsWith(`${route}/`) || path.startsWith(`${route}?`),
  );
}

/**
 * Resolve an admin-configured footer menu link.
 *
 * Links are entered in the admin as full URLs and reduced to a path by the API.
 * A bare path such as "/terms" is a CMS page, so it keeps the "/pages" prefix
 * the footer has always applied; anything already absolute, already under
 * "/pages", or pointing at a known frontend route is used as-is.
 */
function resolveMenuLink(link) {
  const href = link?.trim();
  if (!href || href === "#") return "#";
  if (isUnavailableRoute(href)) return null;

  if (/^(https?:)?\/\//i.test(href) || href.startsWith("mailto:") || href.startsWith("tel:")) {
    return href;
  }

  const path = href.startsWith("/") ? href : `/${href}`;
  const nonPageRoutes = [
    "/pages",
    "/blogs",
    "/shop_page",
    // "/flash-deal", // No flash-deal page yet; see UNAVAILABLE_ROUTES.
    "/track-order",
    "/wishlist",
  ];

  return nonPageRoutes.some(
    (route) => path === route || path.startsWith(`${route}/`) || path.startsWith(`${route}?`),
  )
    ? path
    : `/pages${path}`;
}
