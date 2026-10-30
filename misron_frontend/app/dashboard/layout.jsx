'use client';

/**
 * Dashboard shell — sidebar plus content column.
 *
 * The sidebar starts closed on a phone, where it is a drawer, and open on
 * desktop, where it is permanent. The previous version defaulted it open at
 * every width, so a mobile visitor landed with the drawer covering the page.
 *
 * Collapsing on desktop now hides the sidebar entirely rather than shrinking
 * it to a 20-unit rail — nothing was rendered at that width, so the rail was
 * an empty white strip.
 */

import { useState, useEffect } from 'react';

import DashboardSidebar from '@/components/dashboard/DashboardSidebar';
import DashboardHeader from '@/components/dashboard/DashboardHeader';

export default function DashboardLayout({ children }) {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);

  useEffect(() => {
    setIsSidebarOpen(window.innerWidth >= 1024);
  }, []);

  return (
    <div className="min-h-screen bg-canvas">
      <DashboardSidebar isOpen={isSidebarOpen} setIsOpen={setIsSidebarOpen} />

      <div
        className={`transition-[padding] duration-base ease-standard ${
          isSidebarOpen ? 'lg:pl-64' : 'lg:pl-0'
        }`}
      >
        <DashboardHeader
          isSidebarOpen={isSidebarOpen}
          setIsSidebarOpen={setIsSidebarOpen}
        />

        <main className="px-4 py-8 sm:px-6 lg:px-8">
          <div className="mx-auto max-w-5xl">{children}</div>
        </main>
      </div>
    </div>
  );
}
