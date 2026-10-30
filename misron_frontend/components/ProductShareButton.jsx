"use client";

/**
 * Affiliate share link. Only rendered for a signed-in user who has a referral
 * code, so most shoppers never see it.
 *
 * Restyled as a secondary button with a lucide icon, and the label swaps to a
 * check for two seconds after copying rather than only changing text.
 *
 * A full commented-out earlier copy of this component has been removed from
 * the top of the file. The copy behaviour and URL shape are unchanged.
 */

import { useState, useEffect } from "react";
import { Share2, Check } from "lucide-react";
import { useAppSelector } from "@/store/hooks";
import toast from "react-hot-toast";

export default function ProductShareButton({ productSlug, productId, className = "" }) {
  const { user: referraluser, isAuthenticated } = useAppSelector((state) => state.auth);
  const [copySuccess, setCopySuccess] = useState(false);
  const [mounted, setMounted] = useState(false);

  // Auth state is client-only, so nothing renders until after mount to keep
  // the server and client markup identical.
  useEffect(() => {
    setMounted(true);
  }, []);

  if (!mounted) return null;
  if (!isAuthenticated || !referraluser?.referral_code) return null;

  const copyProductReferralUrl = () => {
    const slug = productSlug || productId;
    if (!slug) {
      toast.error("Product link not available");
      return;
    }

    const productUrl = `${window.location.origin}/product/${slug}?product_referral_code=${referraluser.referral_code}`;

    navigator.clipboard.writeText(productUrl).then(() => {
      setCopySuccess(true);
      toast.success("Referral link copied");
      setTimeout(() => setCopySuccess(false), 2000);
    });
  };

  return (
    <button
      type="button"
      onClick={copyProductReferralUrl}
      title="Share this product and earn commission"
      className={`btn btn-secondary btn-md w-full ${className}`}
    >
      {copySuccess ? (
        <Check size={16} strokeWidth={2} className="text-success" aria-hidden="true" />
      ) : (
        <Share2 size={16} strokeWidth={1.75} aria-hidden="true" />
      )}
      {copySuccess ? "Link copied" : "Copy referral link"}
    </button>
  );
}
