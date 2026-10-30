"use client";

import Link from "next/link";

/**
 * Call-to-action that sits on top of photography — hero slides, category
 * banners, promo panels.
 *
 * The previous code repeated a "wipe" button in five sections, each spelling
 * out three absolutely-positioned spans and a `duration-600` overlay by hand.
 * Here the fill is a single background transition: a translucent frosted pill
 * at rest, going solid white with the label inverting on hover. It reads as
 * one material rather than two layers sliding past each other.
 *
 * Renders an <a> when `href` is passed and a <button> otherwise.
 */
export default function OverlayButton({
  children,
  href,
  onClick,
  size = "md",
  className = "",
  ...props
}) {
  // Each step is responsive rather than one fixed height. The button sits on
  // photography, so it has to stay small enough on a phone not to dominate the
  // frame, and callers cannot fix that from outside — a `h-9` passed through
  // `className` collides with the size class at the same specificity, so which
  // one wins depends on Tailwind's output order, not on the call site.
  const sizeClasses = {
    sm: "h-8 px-4 text-[0.625rem] sm:h-9 sm:px-5 sm:text-[0.75rem]",
    md: "h-9 px-5 text-[0.6875rem] sm:h-10 sm:px-6 sm:text-[0.75rem] md:h-11 md:px-7 md:text-[0.8125rem]",
    lg: "h-9 px-5 text-[0.6875rem] sm:h-10 sm:px-7 sm:text-[0.75rem] md:h-12 md:px-9 md:text-[0.875rem]",
  }[size];

  const classes = [
    "group inline-flex items-center justify-center gap-2 rounded-full",
    "border border-white/40 bg-white/12 backdrop-blur-md",
    "font-semibold uppercase tracking-[0.1em] text-white",
    "transition-all duration-base ease-standard",
    "hover:border-white hover:bg-white hover:text-ink",
    "active:scale-[0.98]",
    sizeClasses,
    className,
  ].join(" ");

  if (href) {
    return (
      <Link href={href} className={classes} {...props}>
        {children}
      </Link>
    );
  }

  return (
    <button type="button" onClick={onClick} className={classes} {...props}>
      {children}
    </button>
  );
}
