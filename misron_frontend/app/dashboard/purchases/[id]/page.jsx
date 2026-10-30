'use client';

import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, Download, PackageX } from 'lucide-react';
import { Button, EmptyState, Skeleton } from '@/app/components/ui';
import OrderStatusBadge from '@/app/components/dashboard/OrderStatusBadge';
import { tkText } from '@/utils/currency';

export default function PurchaseDetails() {
  const { id } = useParams();
  const router = useRouter();
  const [order, setOrder] = useState(null);
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
   const [downloading, setDownloading] = useState(false); // Added missing state

  useEffect(() => {
    fetchOrderDetails();
    fetchOrderItems();
  }, [id]);

  const fetchOrderDetails = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/purchase-history-details/${id}`,
        {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
        }
      );
      const data = await response.json();
      if (data.success && data.data.length > 0) {
        setOrder(data.data[0]);
      }
    } catch (error) {
      console.error('Error fetching order details:', error);
    }
  };

  const fetchOrderItems = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/purchase-history-items/${id}`,
        {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
        }
      );
      const data = await response.json();
      if (data.success) {
        setItems(data.data);
      }
    } catch (error) {
      console.error('Error fetching order items:', error);
    } finally {
      setLoading(false);
    }
  };

//   const downloadInvoice = async () => {
//     try {
//       const token = localStorage.getItem('token');
//       window.open(
//         `${process.env.NEXT_PUBLIC_API_URL}/invoice/download/${id}`,
//         '_blank'
//       );
//     } catch (error) {
//       console.error('Error downloading invoice:', error);
//     }
//   };

const downloadInvoice = async () => {
  if (downloading) return;
  
  setDownloading(true);
  try {
    const token = localStorage.getItem('token');
    
    const response = await fetch(
      `${process.env.NEXT_PUBLIC_API_URL}/invoice/download/${id}`,
      {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/pdf',
          'Currency-Code': 'BDT',
          'App-Language': 'en'
        },
      }
    );
    
    // Log response details for debugging
    // console.log('Response status:', response.status);
    // console.log('Response headers:', Object.fromEntries(response.headers.entries()));
    
    if (!response.ok) {
      const errorText = await response.text();
      console.error('Error response:', errorText);
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    // Check content type
    const contentType = response.headers.get('content-type');
    // console.log('Content-Type:', contentType);
    
    if (!contentType || !contentType.includes('application/pdf')) {
      const text = await response.text();
      console.error('Unexpected content type. Response:', text);
      throw new Error('Invalid response format. Expected PDF.');
    }
    
    const blob = await response.blob();
    // console.log('Blob size:', blob.size, 'bytes');
    
    if (blob.size === 0) {
      throw new Error('PDF file is empty');
    }
    
    // Create download link
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `invoice-${order?.code || id}.pdf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    
  } catch (error) {
    console.error('Error downloading invoice:', error);
    alert(`Failed to download invoice: ${error.message}`);
  } finally {
    setDownloading(false);
  }
};

  if (loading) {
    return (
      <div>
        <Skeleton className="mb-6 h-3 w-28" />
        <Skeleton className="mb-8 h-7 w-52" />
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <Skeleton className="h-72 lg:col-span-2" rounded="lg" />
          <Skeleton className="h-72" rounded="lg" />
        </div>
      </div>
    );
  }

  if (!order) {
    return (
      <EmptyState
        icon={PackageX}
        title="Order not found"
        description="This order may have been removed, or the link may be wrong."
        action={{ label: "Back to orders", href: "/dashboard/purchases" }}
        className="py-20"
      />
    );
  }

  return (
    <div>
      <Link
        href="/dashboard/purchases"
        className="type-footnote mb-6 inline-flex items-center gap-1.5 font-medium text-ink-tertiary transition-colors hover:text-ink"
      >
        <ArrowLeft size={14} aria-hidden="true" />
        Back to orders
      </Link>

      <header className="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="type-title-2 tabular text-ink">Order #{order.code}</h1>
          <p className="type-callout mt-1.5 text-ink-tertiary">
            Placed on {order.date}
          </p>
        </div>

        <Button
          variant="secondary"
          iconLeft={Download}
          onClick={downloadInvoice}
          loading={downloading}
        >
          {downloading ? "Preparing" : "Invoice"}
        </Button>
      </header>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* ── Items ────────────────────────────────────────────────────────*/}
        <section className="card overflow-hidden lg:col-span-2">
          <header className="border-b border-hairline px-5 py-4">
            <h2 className="type-headline text-ink">Items</h2>
          </header>

          <ul className="divide-y divide-hairline">
            {items.map((item) => (
              <li key={item.id} className="flex items-start justify-between gap-4 px-5 py-4">
                <div className="min-w-0">
                  <p className="type-subhead font-medium text-ink">
                    {item.product_name}
                  </p>
                  <p className="type-caption mt-1 text-ink-tertiary">
                    {[item.variation, `Qty ${item.quantity}`]
                      .filter(Boolean)
                      .join(" · ")}
                  </p>
                </div>

                <div className="shrink-0 text-right">
                  <p className="type-subhead tabular font-semibold text-ink">
                    {tkText(item.price)}
                  </p>
                  <div className="mt-1.5">
                    <OrderStatusBadge status={item.delivery_status} type="delivery" />
                  </div>
                </div>
              </li>
            ))}
          </ul>
        </section>

        {/* ── Summary and address ──────────────────────────────────────────*/}
        <div className="space-y-6">
          <section className="card p-5">
            <h2 className="type-headline mb-4 text-ink">Summary</h2>

            <dl className="space-y-2.5">
              <div className="type-subhead flex justify-between">
                <dt className="text-ink-secondary">Subtotal</dt>
                <dd className="tabular text-ink">{tkText(order.subtotal)}</dd>
              </div>

              <div className="type-subhead flex justify-between">
                <dt className="text-ink-secondary">Shipping</dt>
                <dd className="tabular text-ink">{tkText(order.shipping_cost)}</dd>
              </div>

              <div className="type-subhead flex justify-between">
                <dt className="text-ink-secondary">Coupon discount</dt>
                <dd className="tabular text-ink">{tkText(order.coupon_discount)}</dd>
              </div>
            </dl>

            <div className="divider my-4" />

            <div className="flex items-baseline justify-between">
              <span className="type-headline text-ink">Total</span>
              <span className="type-title-3 tabular text-ink">
                {tkText(order.grand_total)}
              </span>
            </div>
          </section>

          <section className="card p-5">
            <h2 className="type-headline mb-4 text-ink">Shipping address</h2>

            <address className="type-subhead not-italic text-ink-secondary">
              <span className="block font-medium text-ink">
                {order.shipping_address.name}
              </span>
              <span className="mt-1.5 block">{order.shipping_address.address}</span>
              <span className="block">
                {order.shipping_address.city}, {order.shipping_address.country}
              </span>
              <span className="mt-2 block">{order.shipping_address.phone}</span>
              <span className="block break-all">{order.shipping_address.email}</span>
            </address>
          </section>
        </div>
      </div>
    </div>
  );
}
