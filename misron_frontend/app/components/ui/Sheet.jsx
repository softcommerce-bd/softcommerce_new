"use client";

import { useEffect, useRef, useCallback } from "react";
import { createPortal } from "react-dom";
import { X } from "lucide-react";

/**
 * Edge-anchored panel — the cart drawer, filter sidebar and mobile category
 * menu all use this instead of each rolling their own fixed-position div.
 *
 * Shares Modal's dismissal and scroll-lock behaviour; differs only in that it
 * slides from an edge and fills that edge's full extent.
 */

const SIDES = {
  right: {
    position: "inset-y-0 right-0 h-full",
    size: "w-full max-w-md",
    radius: "sm:rounded-l-2xl",
    animation: "animate-slide-in-right",
  },
  left: {
    position: "inset-y-0 left-0 h-full",
    size: "w-full max-w-sm",
    radius: "sm:rounded-r-2xl",
    animation: "animate-slide-in-right",
  },
  bottom: {
    position: "inset-x-0 bottom-0 w-full",
    size: "max-h-[85dvh]",
    radius: "rounded-t-2xl",
    animation: "animate-slide-in-bottom",
  },
};

export default function Sheet({
  open,
  onClose,
  side = "right",
  title,
  header,
  label,
  children,
  footer,
  className = "",
  bodyClassName = "",
  footerClassName,
}) {
  const panelRef = useRef(null);
  const config = SIDES[side] ?? SIDES.right;

  const handleKeyDown = useCallback(
    (e) => {
      if (e.key === "Escape") onClose?.();
    },
    [onClose],
  );

  useEffect(() => {
    if (!open) return;

    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    const { overflow, paddingRight } = document.body.style;
    document.body.style.overflow = "hidden";
    if (scrollbarWidth > 0) document.body.style.paddingRight = `${scrollbarWidth}px`;

    document.addEventListener("keydown", handleKeyDown);

    return () => {
      document.removeEventListener("keydown", handleKeyDown);
      document.body.style.overflow = overflow;
      document.body.style.paddingRight = paddingRight;
    };
  }, [open, handleKeyDown]);

  if (!open || typeof document === "undefined") return null;

  // A left-anchored sheet reuses the right-slide keyframe, mirrored.
  const mirror = side === "left" ? { transform: "none" } : undefined;

  return createPortal(
    <div className="fixed inset-0 z-[1000]">
      <div
        className="animate-fade-in absolute inset-0 bg-ink/40 backdrop-blur-[3px]"
        onClick={onClose}
        aria-hidden="true"
      />

      <aside
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-label={label ?? (typeof title === "string" ? title : undefined)}
        style={mirror}
        className={[
          "absolute flex flex-col bg-surface shadow-e4",
          config.position,
          config.size,
          config.radius,
          config.animation,
          className,
        ].join(" ")}
      >
        {/* Bottom sheets get a grab handle — the affordance people expect on
            a panel that can be dragged away on touch. */}
        {side === "bottom" && (
          <div className="flex justify-center pt-3" aria-hidden="true">
            <span className="h-1 w-9 rounded-full bg-hairline-strong" />
          </div>
        )}

        {/* `header` replaces the stock title row for panels that need their own
            masthead; it owns its close button. */}
        {header ??
          (title && (
            <header className="flex items-center justify-between gap-4 px-5 py-4">
              <h2 className="type-headline text-ink">{title}</h2>
              <button
                type="button"
                onClick={onClose}
                aria-label="Close panel"
                className="btn-icon btn-icon-sm btn-icon-round -mr-1"
              >
                <X size={18} aria-hidden="true" />
              </button>
            </header>
          ))}

        <div
          className={["scrollbar-subtle min-h-0 flex-1 overflow-y-auto", bodyClassName].join(" ")}
        >
          {children}
        </div>

        {footer && (
          <footer
            className={footerClassName ?? "border-t border-hairline bg-surface px-5 py-4"}
          >
            {footer}
          </footer>
        )}
      </aside>
    </div>,
    document.body,
  );
}
