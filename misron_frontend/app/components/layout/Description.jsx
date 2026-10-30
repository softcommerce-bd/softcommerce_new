"use client";

/**
 * Product information tabs — description, specification, Q&A, reviews, delivery.
 *
 * Laid out after the reference design: a row of text tabs on a hairline, the
 * active one in gold with a bar on the rule, and each panel's content in a
 * white bordered card.
 *
 * Admin-authored HTML in each panel runs through the shared `.prose-page`
 * reset, so the CMS's own markup inherits the app's typography rather than
 * whatever the editor stored.
 *
 * `aspect-w-16 aspect-h-9` on the video wrapper was a Tailwind v2 plugin class
 * that generates nothing in v4; the iframe is now in a real `aspect-video` box.
 *
 * Tab state, hash syncing and the reviews handoff are unchanged, except that
 * the page now opens on Description rather than Specification.
 */

import { useState, useEffect } from "react";
import Reviews from "../Reviews";

const TAB_IDS = ["description", "specification", "question-answer", "reviews", "delivery"];

const CARD = "rounded-xl border border-stone-200/80 bg-white";

export default function Description({ productData }) {
  const [activeTab, setActiveTab] = useState("description");

  // Always open on the description tab, whatever hash the page arrived with.
  useEffect(() => {
    setActiveTab("description");
    if (typeof window !== "undefined" && window.location.hash) {
      window.location.hash = "";
    }
  }, []);

  useEffect(() => {
    const handleHashChange = () => {
      const hash = window.location.hash.replace("#", "");
      setActiveTab(TAB_IDS.includes(hash) ? hash : "description");
    };

    window.addEventListener("hashchange", handleHashChange);
    return () => window.removeEventListener("hashchange", handleHashChange);
  }, []);

  if (!productData) return null;

  const tabs = [
    { id: "description", label: "Description" },
    { id: "specification", label: "Specification" },
    { id: "question-answer", label: "Q&A" },
    { id: "reviews", label: `Customer Reviews (${productData.rating_count ?? 0})` },
    { id: "delivery", label: "Delivery & Returns" },
  ];

  const handleTabClick = (tabId, e) => {
    e.preventDefault();
    setActiveTab(tabId);
    window.location.hash = tabId;
  };

  const renderContent = () => {
    switch (activeTab) {
      case "description":
        return (
          <div className="max-w-4xl">
            <div
              className={`${CARD} prose-page p-5 text-sm sm:p-8`}
              dangerouslySetInnerHTML={{
                __html: productData.description || "No description available.",
              }}
            />

            {productData.video_link && (
              <div className="mt-10">
                <h3 className="type-headline mb-4 text-stone-900">Product video</h3>
                <div className="aspect-video w-full overflow-hidden rounded-xl bg-stone-100">
                  <iframe
                    src={productData.video_link}
                    title="Product video"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowFullScreen
                    className="h-full w-full"
                  />
                </div>
              </div>
            )}
          </div>
        );

      case "specification":
        return (
          <div className="max-w-3xl">
            <div
              className={`${CARD} prose-page overflow-hidden p-5 text-sm sm:p-8`}
              dangerouslySetInnerHTML={{
                __html: productData.specification || "No specifications available.",
              }}
            />
          </div>
        );

      case "reviews":
        return <Reviews productId={productData.id} productData={productData} />;

      case "question-answer":
        return productData.question_answer ? (
          <div className="max-w-3xl">
            <div
              className={`${CARD} prose-page p-5 text-sm sm:p-8`}
              dangerouslySetInnerHTML={{ __html: productData.question_answer }}
            />
          </div>
        ) : (
          <p className="type-callout text-stone-500">
            No questions yet. Ask us anything about this product.
          </p>
        );

      case "delivery":
        return (
          <div className="max-w-3xl space-y-4 text-xs leading-relaxed text-stone-700 sm:text-sm">
            <div className={`${CARD} space-y-3 p-5`}>
              <h4 className="font-semibold text-stone-900">Shipping Information</h4>
              <ul className="list-disc space-y-1.5 pl-5">
                <li>
                  <strong>Inside Dhaka City:</strong> Doorstep delivery within 24 to
                  48 hours.
                </li>
                <li>
                  <strong>Outside Dhaka (Nationwide):</strong> Express courier home
                  delivery within 48 to 72 hours.
                </li>
                <li>
                  <strong>Delivery Charge:</strong> 100% Free Shipping on all orders
                  during this seasonal event!
                </li>
              </ul>
            </div>

            <div className={`${CARD} space-y-3 p-5`}>
              <h4 className="font-semibold text-stone-900">
                7-Day Hassle-Free Returns &amp; Exchange
              </h4>
              <p>
                You have the right to inspect your order before paying the delivery
                agent. If any size adjustment is required or if you wish to exchange,
                our customer support team facilitates a smooth 7-day exchange
                process.
              </p>
            </div>
          </div>
        );

      default:
        return null;
    }
  };

  return (
    <section className="mt-14 border-t border-stone-200 pt-6 sm:mt-18">
      {/* Tab strip scrolls sideways on a phone rather than wrapping. */}
      <div
        role="tablist"
        aria-label="Product information"
        className="scrollbar-hide flex items-center gap-4 overflow-x-auto border-b border-stone-200 pb-1 sm:gap-6"
      >
        {tabs.map((tab) => {
          const isActive = activeTab === tab.id;
          return (
            <a
              key={tab.id}
              href={`#${tab.id}`}
              role="tab"
              aria-selected={isActive}
              onClick={(e) => handleTabClick(tab.id, e)}
              className={`relative whitespace-nowrap pb-3 text-sm font-medium transition-all sm:text-base ${
                isActive
                  ? "font-semibold text-amber-800"
                  : "text-stone-500 hover:text-stone-800"
              }`}
            >
              {tab.label}
              {/* The active marker sits on the container's own border line. */}
              {isActive && (
                <span className="absolute inset-x-0 -bottom-1 h-0.5 bg-amber-700" />
              )}
            </a>
          );
        })}
      </div>

      <div className="py-6 sm:py-8">{renderContent()}</div>
    </section>
  );
}
