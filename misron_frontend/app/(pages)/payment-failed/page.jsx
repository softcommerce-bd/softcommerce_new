"use client";

import { useSearchParams } from "next/navigation";
import { XCircle } from "lucide-react";
import Breadcrumb from "@/app/components/layout/Breadcrumb";
import { EmptyState } from "@/app/components/ui";

/**
 * Where the payment gateway sends the shopper back to when a transaction did
 * not go through.
 *
 * The order itself still exists and is simply unpaid, so this says what
 * happened rather than implying the basket was lost, and points at the orders
 * list where the attempt can be made again.
 */
export default function PaymentFailedPage() {
  const searchParams = useSearchParams();
  const orderId = searchParams.get("order");

  return (
    <>
      <Breadcrumb
        items={[
          { name: "Home", href: "/" },
          { name: "Payment failed", href: "#" },
        ]}
      />

      <div className="container-page">
        <EmptyState
          icon={XCircle}
          title="Payment was not completed"
          description={
            orderId
              ? `Your order was placed but has not been paid for yet. Nothing has been charged, so you can try the payment again from your orders.`
              : "Nothing has been charged. You can try the payment again from your orders."
          }
          action={{ href: "/orders", label: "Go to my orders" }}
          secondaryAction={{ href: "/", label: "Continue shopping" }}
        />
      </div>
    </>
  );
}
