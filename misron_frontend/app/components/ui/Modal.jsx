"use client";

import { useEffect, useRef, useCallback } from "react";
import { createPortal } from "react-dom";
import { X } from "lucide-react";

/**
 * Centred dialog with a blurred scrim.
 *
 * Handles the things the app's hand-rolled modals were each missing a
 * different subset of: Escape to close, click-outside to close, body scroll
 * lock, focus moved into the dialog on open and returned to the trigger on
 * close, and a focus trap so Tab cannot walk out into the page behind.
 *
 * Rendered through a portal so a modal opened from inside a transformed or
 * overflow-hidden ancestor is not clipped by it.
 */

const SIZES = {
  sm: "max-w-sm",
  md: "max-w-md",
  lg: "max-w-lg",
  xl: "max-w-2xl",
  full: "max-w-5xl",
};

const FOCUSABLE =
  'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

export default function Modal({
  open,
  onClose,
  title,
  description,
  children,
  footer,
  size = "md",
  showClose = true,
  closeOnBackdrop = true,
  className = "",
  bodyClassName = "px-6 pb-6",
  // Element to focus on open, as a ref or a CSS selector scoped to the panel.
  // Without it focus lands on the first focusable child, which is the close
  // button whenever the dialog has one.
  initialFocus,
  // Accessible name for a dialog that draws its own heading instead of taking
  // `title`.
  ariaLabel,
}) {
  const panelRef = useRef(null);
  const previouslyFocused = useRef(null);

  // Callers usually pass inline arrows for onClose and rebuild them on every
  // render. Reading them through refs keeps the open effect from re-running --
  // it moves focus, so re-running it would drag the caret back to the first
  // field while the user types.
  const onCloseRef = useRef(onClose);
  const initialFocusRef = useRef(initialFocus);
  useEffect(() => {
    onCloseRef.current = onClose;
    initialFocusRef.current = initialFocus;
  });

  const handleKeyDown = useCallback(
    (e) => {
      // A dialog can open over another (the OTP step over checkout). Both
      // listen on the document, so only the topmost may react — otherwise one
      // Escape closes the whole stack.
      const dialogs = document.querySelectorAll('[role="dialog"][aria-modal="true"]');
      if (dialogs[dialogs.length - 1] !== panelRef.current) return;

      if (e.key === "Escape") {
        e.stopPropagation();
        onCloseRef.current?.();
        return;
      }

      if (e.key !== "Tab" || !panelRef.current) return;

      // Keep Tab inside the dialog by wrapping at both ends.
      const items = Array.from(panelRef.current.querySelectorAll(FOCUSABLE)).filter(
        (el) => el.offsetParent !== null,
      );
      if (items.length === 0) return;

      const first = items[0];
      const last = items[items.length - 1];

      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    },
    [],
  );

  useEffect(() => {
    if (!open) return;

    previouslyFocused.current = document.activeElement;

    // Lock the page behind the dialog. Compensating for the scrollbar width
    // stops the page shifting sideways as it disappears.
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    const { overflow, paddingRight } = document.body.style;
    document.body.style.overflow = "hidden";
    if (scrollbarWidth > 0) document.body.style.paddingRight = `${scrollbarWidth}px`;

    document.addEventListener("keydown", handleKeyDown, true);

    // Move focus in after the entrance animation has begun, so the browser
    // does not scroll the panel into view mid-transform.
    const focusTimer = setTimeout(() => {
      const panel = panelRef.current;
      const wanted = initialFocusRef.current;
      const requested =
        typeof wanted === "string" ? panel?.querySelector(wanted) : wanted?.current;
      const target = requested ?? panel?.querySelector(FOCUSABLE);
      target?.focus();
    }, 40);

    return () => {
      document.removeEventListener("keydown", handleKeyDown, true);
      document.body.style.overflow = overflow;
      document.body.style.paddingRight = paddingRight;
      clearTimeout(focusTimer);
      previouslyFocused.current?.focus?.();
    };
  }, [open, handleKeyDown]);

  if (!open || typeof document === "undefined") return null;

  return createPortal(
    <div className="fixed inset-0 z-[1000] flex items-end justify-center sm:items-center">
      {/* Scrim — dims and blurs the page rather than blacking it out. */}
      <div
        className="animate-fade-in absolute inset-0 bg-ink/40 backdrop-blur-[3px]"
        onClick={closeOnBackdrop ? onClose : undefined}
        aria-hidden="true"
      />

      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-label={ariaLabel ?? (typeof title === "string" ? title : undefined)}
        tabIndex={-1}
        className={[
          "animate-scale-in relative z-10 flex max-h-[92dvh] w-full flex-col",
          "bg-surface shadow-e4 outline-none",
          "rounded-t-2xl sm:rounded-2xl",
          SIZES[size] ?? SIZES.md,
          className,
        ].join(" ")}
      >
        {(title || showClose) && (
          <header className="flex items-start gap-4 px-6 pt-6 pb-4">
            <div className="min-w-0 flex-1">
              {title && <h2 className="type-title-3 text-ink">{title}</h2>}
              {description && (
                <p className="type-subhead mt-1.5 text-ink-tertiary">{description}</p>
              )}
            </div>

            {showClose && (
              <button
                type="button"
                onClick={onClose}
                aria-label="Close dialog"
                className="btn-icon btn-icon-sm btn-icon-round -mr-1.5 -mt-1"
              >
                <X size={18} aria-hidden="true" />
              </button>
            )}
          </header>
        )}

        <div
          className={`scrollbar-subtle min-h-0 flex-1 overflow-y-auto ${bodyClassName}`}
        >
          {children}
        </div>

        {footer && (
          <footer className="border-t border-hairline px-6 py-4">{footer}</footer>
        )}
      </div>
    </div>,
    document.body,
  );
}
