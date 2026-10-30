'use client';

/**
 * Order history.
 *
 * Rebuilt on the design system. The seven-column table becomes a list of
 * rows, readable on a phone without sideways scrolling, and the page's own
 * 60-line pagination — which duplicated the window logic and mixed brand and
 * indigo focus colours — is replaced by the shared Pagination.
 *
 * Status pills come from OrderStatusBadge rather than this page's private
 * colour map, so a status like "Shipped" no longer falls through to grey.
 *
 * Fetching, paging and the endpoint are unchanged.
 */

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { ShoppingBag, ChevronRight } from 'lucide-react';

import OrderStatusBadge from '@/app/components/dashboard/OrderStatusBadge';
import { EmptyState, Pagination, Skeleton } from '@/app/components/ui';
import { tkText } from '@/utils/currency';

export default function PurchasesPage() {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 5,
    nextPageUrl: null,
    prevPageUrl: null,
  });

  useEffect(() => {
    fetchOrders(1);
  }, []);

  const fetchOrders = async (page = 1) => {
    setLoading(true);
    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/purchase-history?page=${page}`,
        {
          headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${localStorage.getItem('token')}`,
          },
        },
      );

      const result = await response.json();

      if (result.success) {
        setOrders(result.data);
        setPagination({
          currentPage: result.meta.current_page,
          lastPage: result.meta.last_page,
          total: result.meta.total,
          perPage: result.meta.per_page,
          nextPageUrl: result.links.next,
          prevPageUrl: result.links.prev,
        });
      } else {
        console.error('API returned unsuccessful:', result);
      }
    } catch (error) {
      console.error('Error fetching orders:', error);
    } finally {
      setLoading(false);
    }
  };

  const handlePageChange = (page) => {
    if (page >= 1 && page <= pagination.lastPage) {
      fetchOrders(page);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  if (loading) {
    return (
      <div>
        <Skeleton className="mb-8 h-7 w-44" />
        <div className="card divide-y divide-hairline">
          {[0, 1, 2, 3].map((i) => (
            <div key={i} className="flex items-center gap-4 px-5 py-4">
              <div className="flex-1 space-y-2">
                <Skeleton className="h-3.5 w-28" />
                <Skeleton className="h-3 w-20" />
              </div>
              <Skeleton className="h-5 w-20" rounded="full" />
              <Skeleton className="h-3.5 w-16" />
            </div>
          ))}
        </div>
      </div>
    );
  }

  const rangeStart = (pagination.currentPage - 1) * pagination.perPage + 1;
  const rangeEnd = Math.min(
    pagination.currentPage * pagination.perPage,
    pagination.total,
  );

  return (
    <div>
      <header className="mb-8">
        <h1 className="type-title-2 text-ink">Purchases</h1>
        <p className="type-callout mt-1.5 text-ink-tertiary">
          Track and review everything you&apos;ve ordered.
        </p>
      </header>

      <section className="card overflow-hidden">
        {orders.length === 0 ? (
          <EmptyState
            icon={ShoppingBag}
            title="No orders yet"
            description="Your orders will appear here once you place one."
            action={{ label: 'Start shopping', href: '/shop_page' }}
            className="py-16"
          />
        ) : (
          <>
            <ul className="divide-y divide-hairline">
              {orders.map((order) => (
                <li key={order.id}>
                  <Link
                    href={`/dashboard/purchases/${order.id}`}
                    className="flex items-center gap-4 px-5 py-4 transition-colors duration-fast hover:bg-surface-hover"
                  >
                    <div className="min-w-0 flex-1">
                      <p className="type-subhead tabular font-semibold text-ink">
                        #{order.code}
                      </p>
                      <p className="type-caption mt-0.5 text-ink-tertiary">
                        {order.date}
                        {order.payment_type && ` · ${order.payment_type}`}
                      </p>

                      {/* Status pills drop below the order line on a phone,
                          where there is no room for them alongside it. */}
                      <div className="mt-2 flex flex-wrap gap-1.5 sm:hidden">
                        <OrderStatusBadge
                          status={order.payment_status_string}
                          type="payment"
                        />
                        <OrderStatusBadge
                          status={order.delivery_status_string}
                          type="delivery"
                        />
                      </div>
                    </div>

                    <div className="hidden shrink-0 items-center gap-2 sm:flex">
                      <OrderStatusBadge
                        status={order.payment_status_string}
                        type="payment"
                      />
                      <OrderStatusBadge
                        status={order.delivery_status_string}
                        type="delivery"
                      />
                    </div>

                    <p className="type-subhead tabular shrink-0 font-semibold text-ink">
                      {tkText(order.grand_total)}
                    </p>

                    <ChevronRight
                      size={16}
                      className="shrink-0 text-ink-quaternary"
                      aria-hidden="true"
                    />
                  </Link>
                </li>
              ))}
            </ul>

            {pagination.lastPage > 1 && (
              <div className="flex flex-col items-center gap-4 border-t border-hairline px-5 py-4 sm:flex-row sm:justify-between">
                <p className="type-caption tabular text-ink-tertiary">
                  {rangeStart}–{rangeEnd} of {pagination.total}
                </p>

                <Pagination
                  currentPage={pagination.currentPage}
                  lastPage={pagination.lastPage}
                  onPageChange={handlePageChange}
                />
              </div>
            )}
          </>
        )}
      </section>
    </div>
  );
}
