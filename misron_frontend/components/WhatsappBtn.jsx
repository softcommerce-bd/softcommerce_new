"use client";

/**
 * "Chat on WhatsApp" link on the product page.
 *
 * Rendered as a secondary button rather than a green-bordered box, so it
 * reads as the tertiary action it is, below Add to cart and Order now. The
 * WhatsApp brand colour stays on the icon alone.
 *
 * The icon is the WhatsApp brand mark drawn inline. lucide's MessageCircle is
 * a generic empty bubble that does not read as WhatsApp at this size.
 *
 * The prefilled message names the product from the `product_name` prop. It used
 * to read the page's `<h1>` with `document.querySelector`, which cannot work
 * during server rendering: the server produced "this product" and the client
 * produced the real name, so the two `href` values disagreed and React reported
 * a hydration mismatch on every product page.
 */

import Link from "next/link";

const WhatsAppIcon = ({ size = 18, className = "" }) => (
  <svg
    width={size}
    height={size}
    viewBox="0 0 24 24"
    fill="currentColor"
    className={className}
    aria-hidden="true"
  >
    <path d="M12.04 2.25c-5.46 0-9.9 4.44-9.9 9.9 0 1.74.46 3.45 1.33 4.95L2.06 22l5.03-1.32a9.88 9.88 0 0 0 4.73 1.2h.01c5.46 0 9.9-4.44 9.9-9.9a9.84 9.84 0 0 0-2.9-7A9.83 9.83 0 0 0 12.05 2.25zm.01 18.02h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.1.82.83-3.03-.2-.31a8.19 8.19 0 0 1-1.26-4.37c0-4.53 3.7-8.22 8.23-8.22 2.2 0 4.26.86 5.81 2.42a8.16 8.16 0 0 1 2.41 5.81c0 4.53-3.69 8.21-8.23 8.21z" />
    <path d="M16.57 14.42c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.12-.56.13-.16.25-.64.81-.79.98-.14.16-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.38-1.72-.15-.25-.02-.39.11-.51.11-.11.25-.29.37-.44.13-.15.17-.25.25-.41.09-.17.04-.31-.02-.44-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.42h-.47c-.17 0-.44.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.13.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.52.6.19 1.14.16 1.57.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.08.15-1.18-.06-.11-.23-.17-.48-.29z" />
  </svg>
);

const WhatsappBtn = ({ whatsapp_number, product_name, className = "" }) => {
  if (!whatsapp_number) return null;

  const formatWhatsAppNumber = (number) => {
    let cleanNumber = number.toString().replace(/[\s\-\(\)]/g, "");

    if (!cleanNumber.startsWith("+")) {
      cleanNumber = cleanNumber.startsWith("00")
        ? "+" + cleanNumber.substring(2)
        : "+" + cleanNumber;
    }

    return cleanNumber;
  };

  // Falls back to the generic wording when no name is passed, so the button
  // still works on any page that renders it without product data.
  const productTitle = product_name || "this product";
  const message = `Hello! I'm interested in ${productTitle}. Could you please provide more information?`;

  const whatsappUrl = `https://wa.me/${formatWhatsAppNumber(
    whatsapp_number,
  )}?text=${encodeURIComponent(message)}`;

  return (
    <Link
      href={whatsappUrl}
      target="_blank"
      rel="noopener noreferrer"
      className={`btn btn-secondary btn-md w-full ${className}`}
    >
      <WhatsAppIcon size={18} className="shrink-0 text-[#25D366]" />
      Chat on WhatsApp
    </Link>
  );
};

export default WhatsappBtn;
