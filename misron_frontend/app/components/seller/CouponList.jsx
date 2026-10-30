"use client";

/**
 * A shop's coupons.
 *
 * Restyled with the shared section header, skeletons and empty state; the
 * fetch and endpoint are unchanged.
 */

import { useEffect, useState } from "react";
import { TicketPercent } from "lucide-react";

import CouponCard from "./CouponCard";
import { SectionHeader, EmptyState, Skeleton } from "../ui";

export default function CouponList({ shopId }) {
  const [coupons, setCoupons] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchCoupons = async () => {
      try {
        const res = await fetch(
          `${process.env.NEXT_PUBLIC_API_URL}/seller/coupon-list/${shopId}`,
        );
        const data = await res.json();

        if (data.success) setCoupons(data.data);
      } catch (error) {
        console.error("Failed to load coupons:", error);
      }
      setLoading(false);
    };

    if (shopId) fetchCoupons();
  }, [shopId]);

  if (loading) {
    return (
      <div className="py-8">
        <SectionHeader title="Coupons" />
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-52 w-full" rounded="xl" />
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="py-8">
      <SectionHeader title="Coupons" />

      {coupons.length === 0 ? (
        <EmptyState
          icon={TicketPercent}
          title="No coupons right now"
          description="This shop hasn't published any offers yet."
        />
      ) : (
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
          {coupons.map((coupon) => (
            <CouponCard key={coupon.id} coupon={coupon} />
          ))}
        </div>
      )}
    </div>
  );
}
