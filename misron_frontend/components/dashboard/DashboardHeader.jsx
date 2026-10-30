'use client';

/**
 * Dashboard toolbar — sidebar toggle, page identity, quick actions.
 *
 * Restyled on the design system, with the indigo accents replaced by the
 * brand tint and lucide replacing heroicons.
 *
 * The three action buttons previously all used the same
 * `ArrowRightOnRectangleIcon`, so "View Store" and "Sign Out" were drawn with
 * the identical glyph. They now carry distinct icons.
 *
 * User loading, logout and navigation are unchanged.
 */

import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { Menu, Store, LogOut } from 'lucide-react';

export default function DashboardHeader({ isSidebarOpen, setIsSidebarOpen }) {
  const router = useRouter();
  const [user, setUser] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const loadUser = () => {
      try {
        const userData = localStorage.getItem('user');
        if (userData) setUser(JSON.parse(userData));
      } catch (error) {
        console.error('Error loading user:', error);
      } finally {
        setIsLoading(false);
      }
    };

    loadUser();
  }, []);

  const handleLogout = useCallback(() => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    router.push('/');
  }, [router]);

  const handleViewFrontend = useCallback(() => {
    router.push('/');
  }, [router]);

  const getUserInitials = (name) => {
    if (!name) return 'U';
    const names = name.split(' ');
    if (names.length >= 2) {
      return `${names[0][0]}${names[1][0]}`.toUpperCase();
    }
    return names[0][0].toUpperCase();
  };

  return (
    <header className="surface-blur sticky top-[var(--header-height,80px)] z-20 border-b border-hairline">
      <div className="flex h-14 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <div className="flex min-w-0 items-center gap-3">
          <button
            type="button"
            onClick={() => setIsSidebarOpen(!isSidebarOpen)}
            className="btn-icon btn-icon-sm btn-icon-round -ml-1"
            aria-label={isSidebarOpen ? 'Hide menu' : 'Show menu'}
            aria-expanded={isSidebarOpen}
          >
            <Menu size={18} aria-hidden="true" />
          </button>

          {!isLoading && user && (
            <div className="flex min-w-0 items-center gap-2.5">
              {/* Initials avatar — no photo is stored for customer accounts. */}
              <span
                className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-canvas-sunken text-[0.6875rem] font-semibold text-ink-secondary"
                aria-hidden="true"
              >
                {getUserInitials(user?.name)}
              </span>
              <span className="type-subhead truncate font-medium text-ink">
                {user?.name}
              </span>
            </div>
          )}
        </div>

        <div className="flex shrink-0 items-center gap-1.5">
          <button
            type="button"
            onClick={handleViewFrontend}
            className="btn btn-ghost btn-sm"
          >
            <Store size={15} strokeWidth={1.75} aria-hidden="true" />
            <span className="hidden sm:inline">View store</span>
          </button>

          <button
            type="button"
            onClick={handleLogout}
            className="btn btn-ghost btn-sm text-danger hover:bg-danger-tint"
          >
            <LogOut size={15} strokeWidth={1.75} aria-hidden="true" />
            <span className="hidden sm:inline">Sign out</span>
          </button>
        </div>
      </div>
    </header>
  );
}
