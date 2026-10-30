'use client';

/**
 * Account overview — order stats and the five most recent orders.
 *
 * Rebuilt on the design system. The three stat cards previously carried
 * saturated icon tiles (blue, green, yellow) and lifted 5% on hover; they are
 * now quiet figures on a card, since a dashboard's job is to be read rather
 * than to react.
 *
 * The recent-orders table becomes a list of rows, which works at phone width
 * where the six-column table only scrolled sideways. Status pills come from
 * the shared OrderStatusBadge, so the dashboard's own duplicate colour maps
 * are gone.
 *
 * "Start Shopping" pointed at `/shop`, which is not a route in this app; it
 * now points at `/shop_page`.
 *
 * A commented-out earlier copy of `fetchDashboardData` has been removed. The
 * live fetch, endpoint and auth header are unchanged.
 */

import { useEffect, useState } from 'react';
import Link from 'next/link';
import {
  ShoppingBag,
  Wallet,
  Truck,
  RotateCw,
  AlertCircle,
  ChevronRight,
} from 'lucide-react';

import OrderStatusBadge from '@/app/components/dashboard/OrderStatusBadge';
import { Button, EmptyState, Skeleton } from '@/app/components/ui';
import { tkText } from '@/utils/currency';

export default function DashboardOverview() {
  const [stats, setStats] = useState({
    totalOrders: 0,
    totalSpent: 0,
    pendingOrders: 0,
  });
  const [recentOrders, setRecentOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    setLoading(true);
    setError(null);

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL;

      if (!apiUrl) throw new Error('API URL is not configured');

      const response = await fetch(`${apiUrl}/auth/dashboard/stats`, {
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${localStorage.getItem('token')}`,
        },
      });

      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

      const result = await response.json();

      if (result.success) {
        setStats({
          totalOrders: result.data.stats.total_orders,
          totalSpent: result.data.stats.total_spent,
          pendingOrders: result.data.stats.pending_orders,
        });
        setRecentOrders(result.data.recent_orders);
      } else {
        console.error('API returned unsuccessful:', result);
        setError(result.message || 'Failed to load dashboard data');
      }
    } catch (error) {
      console.error('Error fetching dashboard data:', error);
      setError('Failed to load dashboard data. Please try again later.');
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount) => `৳ ${(amount ?? 0).toLocaleString()}`;

  const statCards = [
    { name: 'Total orders', value: stats.totalOrders, icon: ShoppingBag },
    { name: 'Total spent', value: formatCurrency(stats.totalSpent), icon: Wallet },
    { name: 'In progress', value: stats.pendingOrders, icon: Truck },
  ];

  if (loading) {
    return (
      <div>
        <Skeleton className="mb-8 h-7 w-52" />
        <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-28 w-full" rounded="lg" />
          ))}
        </div>
        <Skeleton className="h-64 w-full" rounded="lg" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-col items-center rounded-xl bg-canvas-sunken px-6 py-16 text-center">
        <AlertCircle size={26} className="mb-4 text-ink-tertiary" strokeWidth={1.5} />
        <h2 className="type-title-3 text-ink">Couldn&apos;t load your dashboard</h2>
        <p className="type-footnote mt-2 text-ink-tertiary">{error}</p>
        <Button
          variant="secondary"
          className="mt-6"
          iconLeft={RotateCw}
          onClick={fetchDashboardData}
        >
          Try again
        </Button>
      </div>
    );
  }

  return (
    <div>
      <header className="mb-8">
        <h1 className="type-title-2 text-ink">Overview</h1>
        <p className="type-callout mt-1.5 text-ink-tertiary">
          Your orders at a glance.
        </p>
      </header>

      {/* ── Stats ──────────────────────────────────────────────────────────*/}
      <div className="mb-10 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {statCards.map((stat) => (
          <div key={stat.name} className="card p-5">
            <div className="mb-3 flex items-center gap-2 text-ink-tertiary">
              <stat.icon size={15} strokeWidth={1.75} aria-hidden="true" />
              <span className="type-caption font-medium">{stat.name}</span>
            </div>
            <p className="type-title-2 tabular text-ink">{stat.value}</p>
          </div>
        ))}
      </div>

      {/* ── Recent orders ──────────────────────────────────────────────────*/}
      <section className="card overflow-hidden">
        <header className="flex items-center justify-between gap-4 border-b border-hairline px-5 py-4">
          <h2 className="type-headline text-ink">Recent orders</h2>
          {recentOrders.length > 0 && (
            <Link
              href="/dashboard/purchases"
              className="type-footnote font-semibold text-ink-secondary transition-colors hover:text-ink"
            >
              View all
            </Link>
          )}
        </header>

        {recentOrders.length === 0 ? (
          <EmptyState
            icon={ShoppingBag}
            title="No orders yet"
            description="Your orders will appear here once you place one."
            action={{ label: 'Start shopping', href: '/shop_page' }}
            className="py-14"
          />
        ) : (
          <ul className="divide-y divide-hairline">
            {recentOrders.map((order) => (
              <li key={order.id}>
                {/* The whole row is the link, so the target is generous on
                    touch rather than a small "View details" button. */}
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
                    </p>
                  </div>

                  <div className="hidden shrink-0 items-center gap-2 sm:flex">
                    <OrderStatusBadge
                      status={order.payment_status_string || order.payment_status}
                      type="payment"
                    />
                    <OrderStatusBadge
                      status={order.delivery_status_string || order.delivery_status}
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
        )}
      </section>
    </div>
  );
}
