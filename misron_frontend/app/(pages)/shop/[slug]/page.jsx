"use client";

import React, { useState, useEffect } from "react";
import Breadcrumb from "@/app/components/layout/Breadcrumb";
import HeaderCard from "@/app/components/seller/HeaderCard"; // Make sure this path is correct
import SellerFeaturedProducts from "@/app/components/seller/SellerFeaturedProducts";
import SellerBanner from "@/app/components/seller/SellerBanner";
import CouponList from "@/app/components/seller/CouponList";
import SellerNewestProducts from "@/app/components/seller/SellerNewestProducts";
import SellerTopSellingProducts from "@/app/components/seller/SellerTopSellingProducts";
import SellerAllProducts from "@/app/components/seller/SellerAllProducts";
import { Store } from "lucide-react";
import { EmptyState, Skeleton } from "@/app/components/ui";

export default function SellerPage({ params }) {
  const unwrappedParams = React.use(params);
  const [shopId, setShopId] = useState(null);
  const [shopData, setShopData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState("STORE HOME");

  useEffect(() => {
    const fetchShopId = async () => {
      try {
        const res = await fetch(
          `${process.env.NEXT_PUBLIC_API_URL}/shops/details/${unwrappedParams.slug}`
        );

        if (!res.ok) throw new Error("Failed to fetch shop info");

        const response = await res.json();

        if (response.success && response.data) {
          setShopId(response.data.id);
          setShopData(response.data);
        } else {
          throw new Error("Shop not found or invalid response");
        }
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetchShopId();
  }, [unwrappedParams.slug]);

  const renderContent = () => {
    if (!shopId) {
      return (
        <EmptyState
          icon={Store}
          title="Shop not found"
          description="This shop may have closed, or the link may be wrong."
          action={{ label: "Browse products", href: "/shop_page" }}
          className="py-20"
        />
      );
    }

    switch (activeTab) {
      case "STORE HOME":
        return (
          <>
            <SellerBanner shopData={shopData} />
            <SellerFeaturedProducts shopId={shopId} />
            <CouponList shopId={shopId} />
            <SellerNewestProducts shopId={shopId} />
          </>
        );

      case "TOP SELLING":
        return <SellerTopSellingProducts shopId={shopId} />;

      case "COUPONS":
        return <CouponList shopId={shopId} />;

      case "ALL PRODUCTS":
        return <SellerAllProducts shopId={shopId} />;

      default:
        return null;
    }
  };

  if (loading) {
    return (
      <div className="container-page py-8 md:py-12">
        <Skeleton className="h-3 w-40" />
        <Skeleton className="mt-8 h-32 w-full" rounded="xl" />
        <Skeleton className="mt-8 h-10 w-80" rounded="full" />
        <Skeleton className="mt-8 h-64 w-full" rounded="xl" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="container-page py-8 md:py-12">
        <EmptyState
          icon={Store}
          title="Couldn't load this shop"
          description={error}
          action={{ label: "Browse products", href: "/shop_page" }}
          className="py-20"
        />
      </div>
    );
  }

  return (
    <div className="container-page py-8 md:py-12">
      <Breadcrumb
        items={[
          { name: "Home", href: "/" },
          { name: "Shops", href: "/shop_page" },
          { name: shopData?.name ?? "Shop", href: "#" },
        ]}
        className="mb-8"
      />

      <HeaderCard
        shopData={shopData}
        activeTab={activeTab}
        onTabChange={setActiveTab}
      />

      {renderContent()}
    </div>
  );
}
