"use client";

import SearchbarDesktop from "@/app/components/layout/SearchBarDesktop";
import SearchbarMobile from "@/app/components/layout/SearchBarMobile";
import { useEffect } from "react";
import { X } from "lucide-react";

export default function SearchModal({
  isOpen,
  onClose,
  open,
  setOpen,
  selectedCategory,
  setSelectedCategory,
  categories,
}) {
  useEffect(() => {
    if (isOpen) {
      // Calculate scrollbar width
      const scrollbarWidth =
        window.innerWidth - document.documentElement.clientWidth;

      // Get the header element
      const header = document.querySelector("header");

      // Store current scroll position
      const scrollY = window.scrollY;

      // Lock body scroll and prevent shift
      document.body.style.position = "fixed";
      document.body.style.top = `-${scrollY}px`;
      document.body.style.width = "100%";
      document.body.style.overflowY = "scroll"; // Keep scrollbar visible

      // Add padding to header to prevent shift
      if (header) {
        header.style.paddingRight = `${scrollbarWidth}px`;
      }

      // Store scroll position for restoration
      document.body.dataset.scrollPosition = scrollY;
    } else {
      // Restore scroll position
      const scrollY = document.body.dataset.scrollPosition;

      // Restore body styles
      document.body.style.position = "";
      document.body.style.top = "";
      document.body.style.width = "";
      document.body.style.overflowY = "";

      // Remove header padding
      const header = document.querySelector("header");
      if (header) {
        header.style.paddingRight = "";
      }

      // Restore scroll position
      if (scrollY) {
        window.scrollTo(0, parseInt(scrollY));
      }
      delete document.body.dataset.scrollPosition;
    }

    const handleEsc = (e) => {
      if (e.key === "Escape") onClose();
    };

    if (isOpen) {
      window.addEventListener("keydown", handleEsc);
    }

    return () => {
      document.body.style.position = "";
      document.body.style.top = "";
      document.body.style.width = "";
      document.body.style.overflowY = "";

      const header = document.querySelector("header");
      if (header) {
        header.style.paddingRight = "";
      }

      window.removeEventListener("keydown", handleEsc);

      // Restore scroll on cleanup
      const scrollY = document.body.dataset.scrollPosition;
      if (scrollY) {
        window.scrollTo(0, parseInt(scrollY));
        delete document.body.dataset.scrollPosition;
      }
    };
  }, [isOpen, onClose]);
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-[9999]">
      <div
        className="animate-fade-in absolute inset-0 bg-ink/40 backdrop-blur-[3px]"
        onClick={onClose}
        aria-hidden="true"
      />

      {/* The panel drops from the top edge, replacing the header, so the
          search field lands where the search button was. */}
      <div
        role="dialog"
        aria-modal="true"
        aria-label="Search products"
        className="animate-slide-down relative bg-surface shadow-e3"
      >
        <div className="container-page flex items-center justify-between gap-4 border-b border-hairline py-4">
          <h2 className="type-headline text-ink">Search</h2>

          <button
            type="button"
            onClick={onClose}
            className="btn-icon btn-icon-sm btn-icon-round -mr-1"
            aria-label="Close search"
          >
            <X size={18} aria-hidden="true" />
          </button>
        </div>

        <div className="container-page py-5">
          <SearchbarDesktop
            open={open}
            setOpen={setOpen}
            selectedCategory={selectedCategory}
            setSelectedCategory={setSelectedCategory}
            categories={categories}
            isModal={true}
            onClose={onClose}
          />

          <SearchbarMobile
            open={open}
            setOpen={setOpen}
            selectedCategory={selectedCategory}
            setSelectedCategory={setSelectedCategory}
            categories={categories}
            isModal={true}
            onClose={onClose}
          />
        </div>
      </div>
    </div>
  );
}
