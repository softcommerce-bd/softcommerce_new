"use client";

import { forwardRef } from "react";
import Link from "next/link";
import { Loader2 } from "lucide-react";

/**
 * The single button in the app.
 *
 * Variant sets colour, size sets the box, and the base `.btn` class in
 * globals.css owns motion, focus and the disabled contract — so a button
 * cannot drift from the system by being styled locally.
 *
 * Renders an <a> when `href` is passed and a <button> otherwise, so a link
 * that looks like a button is still a link for middle-click and keyboard.
 */

const VARIANTS = {
  primary: "btn-primary",
  secondary: "btn-secondary",
  /** Frosted, matching the carousel arrows and the sticky bars. */
  glass: "btn-glass",
  ghost: "btn-ghost",
  danger: "btn-danger",
};

const SIZES = {
  sm: "btn-sm",
  md: "btn-md",
  lg: "btn-lg",
};

const Button = forwardRef(function Button(
  {
    children,
    variant = "primary",
    size = "md",
    loading = false,
    disabled = false,
    fullWidth = false,
    iconLeft: IconLeft,
    iconRight: IconRight,
    href,
    className = "",
    type = "button",
    ...props
  },
  ref,
) {
  const isDisabled = disabled || loading;

  const classes = [
    "btn",
    VARIANTS[variant] ?? VARIANTS.primary,
    SIZES[size] ?? SIZES.md,
    fullWidth ? "w-full" : "",
    className,
  ]
    .filter(Boolean)
    .join(" ");

  // Icons scale with the button rather than being hard-coded per call site.
  const iconSize = size === "sm" ? 15 : size === "lg" ? 19 : 17;

  const content = (
    <>
      {loading ? (
        <Loader2 size={iconSize} className="animate-spin" aria-hidden="true" />
      ) : (
        IconLeft && <IconLeft size={iconSize} aria-hidden="true" />
      )}
      {children}
      {!loading && IconRight && <IconRight size={iconSize} aria-hidden="true" />}
    </>
  );

  if (href && !isDisabled) {
    return (
      <Link ref={ref} href={href} className={classes} {...props}>
        {content}
      </Link>
    );
  }

  return (
    <button
      ref={ref}
      type={type}
      className={classes}
      disabled={isDisabled}
      aria-busy={loading || undefined}
      {...props}
    >
      {content}
    </button>
  );
});

export default Button;
