// components/ThemeProvider.jsx
'use client';
import { useEffect } from 'react';
import { contrastInkChannels } from '@/lib/color';

export function ThemeProvider({ children }) {
  useEffect(() => {
    async function fetchColors() {
      try {
        const response = await fetch( `${process.env.NEXT_PUBLIC_API_URL}/all-custom-color`);
        const data = await response.json();

        if (data.success) {
          const colors = data.data;
          const root = document.documentElement;

          // Set CSS variables dynamically
        //   root.style.setProperty('--background', colors.base_color);
          root.style.setProperty('--main', colors.base_color);
          root.style.setProperty('--main-hov', colors.base_hov_color);
          root.style.setProperty('--second-color', colors.secondary_base_color);
          root.style.setProperty('--second-hov-color', colors.secondary_base_hov_color);
          root.style.setProperty('--header-topbar-color', colors.header_topbar_color);
          root.style.setProperty('--header-bottom-color', colors.header_bottom_color);

          // The footer ground follows the admin too, but it carries type, so the
          // colour alone is not enough: the picker allows any value (it is
          // `#ffffff` today) and the footer's own ink ramp is derived from it
          // rather than fixed to white. An unparseable value is skipped so the
          // globals.css ground and ramp stay in agreement.
          const footerInk = contrastInkChannels(colors.footer_color);
          if (footerInk) {
            root.style.setProperty('--footer-color', colors.footer_color);
            root.style.setProperty('--footer-ink-rgb', footerInk);
          }
        }
      } catch (error) {
        console.error('Failed to fetch colors:', error);
      }
    }

    fetchColors();
  }, []);

  return children;
}