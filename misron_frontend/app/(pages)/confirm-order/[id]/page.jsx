"use client";

import Breadcrumb2 from "@/app/components/layout/Breadcrumb2";
import Image from "next/image";
import samsung from "@/public/products/samsung.png";
import { useEffect, useState, useRef } from "react";
import { useParams, useSearchParams } from "next/navigation";
import Link from "next/link";
import { toast } from "react-hot-toast";
import { usePurchaseGTM } from "@/components/GTM/usePurchaseGTM";
import { CheckCircle2, PackageX } from "lucide-react";
import { Button, EmptyState, Skeleton } from "@/app/components/ui";
import OrderStatusBadge from "@/app/components/dashboard/OrderStatusBadge";

export default function OrderConfirmationPage() {
  const params = useParams();
  const searchParams = useSearchParams();
  const [orderDetails, setOrderDetails] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  
  const { trackPurchase } = usePurchaseGTM();
  
  // Add ref to track if event has been sent
  const hasSentPurchaseEvent = useRef(false);
  // Add ref to track if we're loading from localStorage
  const isLoadingFromStorage = useRef(false);
  
  const orderId = params.id;
  const isGuest = searchParams.get('guest') === 'true';

  // Check if we've already sent this event in this session
  useEffect(() => {
    // Check sessionStorage to prevent duplicate on reload
    const eventKey = `purchase_sent_${orderId}`;
    const hasSentInSession = sessionStorage.getItem(eventKey);
    
    if (hasSentInSession === 'true') {
      hasSentPurchaseEvent.current = true;
      // console.log('Purchase event already sent in this session');
    }
  }, [orderId]);

  useEffect(() => {
    if (orderId) {
      fetchOrderDetails();
    }
  }, [orderId]);

  // Track purchase when order details are loaded - ONLY ONCE
  useEffect(() => {
    // Check if we have order details AND event hasn't been sent yet
    if (orderDetails && orderDetails.order_details && orderDetails.order_details.length > 0) {
      if (!hasSentPurchaseEvent.current) {
        const eventKey = `purchase_sent_${orderId}`;
        
        // Mark as sent immediately to prevent any race conditions
        hasSentPurchaseEvent.current = true;
        
        // Store in sessionStorage to persist across reloads
        sessionStorage.setItem(eventKey, 'true');
        
        // Small delay to ensure all data is processed
        setTimeout(() => {
          // console.log('Sending purchase event for order:', orderDetails.code);
          trackPurchase(orderDetails);
        }, 500);
      } else {
        // console.log('Purchase event already sent, skipping duplicate');
      }
    }
  }, [orderDetails, orderId, trackPurchase]);

  const fetchOrderDetails = async () => {
    // Prevent fetching if we already have data and it's a reload
    if (orderDetails && !isLoadingFromStorage.current) {
      // console.log('Order details already loaded, skipping fetch');
      return;
    }
    
    try {
      setLoading(true);
      
      // Try to load from sessionStorage first (for page reload)
      const cachedOrder = sessionStorage.getItem(`order_${orderId}`);
      if (cachedOrder && !isLoadingFromStorage.current) {
        try {
          const parsedOrder = JSON.parse(cachedOrder);
          setOrderDetails(parsedOrder);
          isLoadingFromStorage.current = true;
          setLoading(false);
          return;
        } catch (e) {
          console.error('Error parsing cached order:', e);
        }
      }
      
      const token = localStorage.getItem('token');

      // Without a token the authenticated route only answers 401, which is what
      // a shopper returning from a payment gateway would otherwise see. The
      // guest route serves the same order and needs none, and the id in the
      // address is encrypted, so it cannot be guessed.
      const useGuestRoute = isGuest || !token;

      let url;
      if (useGuestRoute) {
        url = `${process.env.NEXT_PUBLIC_API_URL}/order/guest-details/${orderId}`;
        url += `?guest=true&temp_user_id=${localStorage.getItem('guest_id')}`;
      } else {
        url = `${process.env.NEXT_PUBLIC_API_URL}/order/details/${orderId}`;
      }

      const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };

      if (!useGuestRoute) {
        headers.Authorization = `Bearer ${token}`;
      }

      const response = await fetch(url, { headers });
      
      const responseText = await response.text();
      
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (e) {
        console.error('Failed to parse JSON:', responseText);
        throw new Error('Invalid response from server');
      }

      if (response.ok && data.result) {
        setOrderDetails(data.order);
        // Cache the order details
        sessionStorage.setItem(`order_${orderId}`, JSON.stringify(data.order));
      } else {
        setError(data.message || 'Failed to load order details');
        toast.error('Failed to load order details');
      }
    } catch (error) {
      console.error('Error fetching order:', error);
      setError(error.message || 'Failed to load order details');
      toast.error('Failed to load order details');
    } finally {
      setLoading(false);
    }
  };
  
  const formatDate = (timestamp) => {
    if (!timestamp) return 'N/A';
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    });
  };

  const getShippingAddress = () => {
    return orderDetails?.shipping_address || null;
  };

  // Clear sessionStorage when component unmounts (user leaves page)
  useEffect(() => {
    return () => {
      // Optional: Clear after 5 minutes to prevent stale data
      const eventKey = `purchase_sent_${orderId}`;
      setTimeout(() => {
        sessionStorage.removeItem(eventKey);
        sessionStorage.removeItem(`order_${orderId}`);
      }, 300000); // 5 minutes
    };
  }, [orderId]);

  if (loading) {
    return (
      <div className="container-page py-8 md:py-12">
        <Breadcrumb2 />
        <div className="mx-auto mt-12 max-w-lg text-center">
          <Skeleton className="mx-auto h-14 w-14" rounded="full" />
          <Skeleton className="mx-auto mt-5 h-7 w-48" />
          <Skeleton className="mx-auto mt-3 h-3 w-64" />
        </div>
        <div className="mt-12 grid gap-6 lg:grid-cols-3">
          <Skeleton className="h-80" rounded="lg" />
          <Skeleton className="h-80 lg:col-span-2" rounded="lg" />
        </div>
      </div>
    );
  }

  if (error || !orderDetails) {
    return (
      <div className="container-page py-8 md:py-12">
        <Breadcrumb2 />
        <EmptyState
          icon={PackageX}
          title="Couldn't load this order"
          description={error || "The link may be wrong, or the order may no longer exist."}
          action={{ label: "Go home", href: "/" }}
          secondaryAction={{ label: "Track an order", href: "/track-order" }}
          className="py-24"
        />
      </div>
    );
  }

  const shippingAddress = getShippingAddress();

  const items = orderDetails?.order_details ?? [];
  // Backed out of the grand total, so every charge the total carries — the gift
  // box included — has to come off here or it lands in the subtotal.
  const subtotal =
    (orderDetails?.grand_total ?? 0) -
    (orderDetails?.shipping_cost || 0) -
    (orderDetails?.tax || 0) -
    (orderDetails?.gift_box_charge || 0) +
    (orderDetails?.coupon_discount || 0);

  const formatTk = (value) => `৳ ${(value ?? 0).toLocaleString()}`;

  const summaryRows = [
    { label: "Order code", value: orderDetails?.code, mono: true },
    { label: "Name", value: shippingAddress?.name },
    { label: "Email", value: shippingAddress?.email },
    { label: "Phone", value: shippingAddress?.phone },
    {
      label: "Address",
      value: [
        shippingAddress?.address,
        shippingAddress?.city,
        shippingAddress?.country,
      ]
        .filter(Boolean)
        .join(", "),
    },
    { label: "Placed", value: formatDate(orderDetails?.date) },
    {
      label: "Delivery",
      value:
        orderDetails?.shipping_type === "home_delivery"
          ? "Home delivery"
          : "Pickup point",
    },
    {
      label: "Payment",
      value:
        orderDetails?.payment_type
          ?.replace(/_/g, " ")
          .replace(/\b\w/g, (l) => l.toUpperCase()) || "Cash on delivery",
    },
  ];

  return (
    <div className="container-page py-8 md:py-12">
      <Breadcrumb2 />

      {/* ── Confirmation ───────────────────────────────────────────────────
          The green tick is a lucide icon in a soft disc, rather than the
          58-point decorative starburst the page carried inline. */}
      <header className="mx-auto mb-12 mt-8 max-w-lg text-center">
        <span
          className="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-success-tint"
          aria-hidden="true"
        >
          <CheckCircle2 size={26} className="text-success" strokeWidth={1.75} />
        </span>

        <h1 className="type-title-1 text-ink">Order placed</h1>

        <p className="type-callout mt-3 text-ink-tertiary">
          {shippingAddress?.email
            ? `We've sent a confirmation to ${shippingAddress.email}.`
            : "We've sent a confirmation to your email."}
        </p>

        {orderDetails?.delivery_status && (
          <div className="mt-5 flex justify-center">
            <OrderStatusBadge status={orderDetails.delivery_status} type="delivery" />
          </div>
        )}
      </header>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* ── Order facts ──────────────────────────────────────────────────*/}
        <section className="card h-fit p-5">
          <h2 className="type-headline mb-4 text-ink">Order details</h2>

          <dl className="space-y-3">
            {summaryRows.map((row) => (
              <div key={row.label} className="flex justify-between gap-4">
                <dt className="type-caption shrink-0 text-ink-tertiary">{row.label}</dt>
                <dd
                  className={`type-footnote text-right text-ink ${
                    row.mono ? "tabular" : ""
                  }`}
                >
                  {row.value || "—"}
                </dd>
              </div>
            ))}
          </dl>
        </section>

        {/* ── Items and totals ─────────────────────────────────────────────*/}
        <div className="space-y-6 lg:col-span-2">
          <section className="card overflow-hidden">
            <header className="border-b border-hairline px-5 py-4">
              <h2 className="type-headline text-ink">Items</h2>
            </header>

            <ul className="divide-y divide-hairline">
              {items.map((item, index) => (
                <li key={item.id || index} className="flex gap-3 px-5 py-4">
                  <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-md bg-canvas-sunken">
                    <Image
                      src={item.product?.thumbnail_image || samsung}
                      alt=""
                      fill
                      className="object-cover"
                      sizes="64px"
                    />
                  </div>

                  <div className="flex min-w-0 flex-1 items-start justify-between gap-3">
                    <div className="min-w-0">
                      <p className="type-subhead line-clamp-2 font-medium text-ink">
                        {item.product?.name || "Product"}
                      </p>
                      <p className="type-caption mt-1 text-ink-tertiary">
                        {[
                          item.variation,
                          `Qty ${item.quantity}`,
                          item.shipping_type === "home_delivery"
                            ? "Home delivery"
                            : "Pickup",
                        ]
                          .filter(Boolean)
                          .join(" · ")}
                      </p>
                    </div>

                    <p className="type-subhead tabular shrink-0 font-semibold text-ink">
                      {formatTk(item.price)}
                    </p>
                  </div>
                </li>
              ))}
            </ul>

            <div className="border-t border-hairline px-5 py-5">
              <dl className="ml-auto max-w-xs space-y-2.5">
                <div className="type-subhead flex justify-between">
                  <dt className="text-ink-secondary">Subtotal</dt>
                  <dd className="tabular text-ink">{formatTk(subtotal)}</dd>
                </div>

                <div className="type-subhead flex justify-between">
                  <dt className="text-ink-secondary">Shipping</dt>
                  <dd className="tabular text-ink">
                    {formatTk(orderDetails?.shipping_cost)}
                  </dd>
                </div>

                {orderDetails?.tax > 0 && (
                  <div className="type-subhead flex justify-between">
                    <dt className="text-ink-secondary">Tax</dt>
                    <dd className="tabular text-ink">{formatTk(orderDetails.tax)}</dd>
                  </div>
                )}

                {orderDetails?.coupon_discount > 0 && (
                  <div className="type-subhead flex justify-between text-success">
                    <dt>Voucher</dt>
                    <dd className="tabular">
                      −{formatTk(orderDetails.coupon_discount)}
                    </dd>
                  </div>
                )}

                {orderDetails?.gift_box && (
                  <div className="type-subhead flex justify-between">
                    <dt className="text-ink-secondary">Gift box</dt>
                    <dd className="tabular text-ink">
                      {formatTk(orderDetails.gift_box_charge)}
                    </dd>
                  </div>
                )}

                <div className="flex items-baseline justify-between border-t border-hairline pt-3">
                  <span className="type-headline text-ink">Total</span>
                  <span className="type-title-3 tabular text-ink">
                    {formatTk(orderDetails?.grand_total)}
                  </span>
                </div>
              </dl>
            </div>
          </section>

          <div className="flex flex-col gap-2.5 sm:flex-row">
            <Button href="/track-order" variant="secondary" className="flex-1">
              Track this order
            </Button>
            <Button href="/shop_page" className="flex-1">
              Continue shopping
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
