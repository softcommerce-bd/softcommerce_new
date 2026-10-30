"use client";

import { forwardRef } from "react";
import Link from "next/link";
import { Loader2 } from "lucide-react";

/**
 * Icon-only control — toolbar actions, close buttons, carousel arrows.
 *
 * `label` is required and becomes the accessible name. The old codebase had
 * icon buttons with no name at all, which read as "button" to a screen reader.
 */

const SIZES = {
  sm: { box: "btn-icon-sm", icon: 16 },
  md: { box: "btn-icon-md", icon: 18 },
  lg: { box: "btn-icon-lg", icon: 20 },
};

const IconButton = forwardRef(function IconButton(
  {
    icon: Icon,
    label,
    size = "md",
    round = false,
    loading = false,
    disabled = false,
    href,
    className = "",
    type = "button",
    ...props
  },
  ref,
) {
  const { box, icon: iconSize } = SIZES[size] ?? SIZES.md;
  const isDisabled = disabled || loading;

  const classes = ["btn-icon", box, round ? "btn-icon-round" : "", className]
    .filter(Boolean)
    .join(" ");

  const content = loading ? (
    <Loader2 size={iconSize} className="animate-spin" aria-hidden="true" />
  ) : (
    <Icon size={iconSize} aria-hidden="true" />
  );

  if (href && !isDisabled) {
    return (
      <Link ref={ref} href={href} className={classes} aria-label={label} {...props}>
        {content}
      </Link>
    );
  }

  return (
    <button
      ref={ref}
      type={type}
      className={classes}
      aria-label={label}
      title={label}
      disabled={isDisabled}
      {...props}
    >
      {content}
    </button>
  );
});

export default IconButton;
