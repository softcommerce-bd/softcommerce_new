'use client';

/**
 * Chooses the chrome for the current route.
 *
 * Three layouts exist — dashboard, home and default — and the previous version
 * spelled each out in full, repeating the modals, toaster and bottom nav three
 * times. Here the shared chrome is written once in `Chrome` and each layout
 * only declares what actually differs: whether the footer and floating bar are
 * shown, and how far the main region is offset from the fixed header.
 *
 * All routing, auth bootstrapping and referral behaviour is unchanged.
 */

import { usePathname, useSearchParams, useRouter } from 'next/navigation';
import { useEffect, useState, useCallback, Suspense } from 'react';
import dynamic from 'next/dynamic';
import { Toaster } from 'react-hot-toast';
import { useAppDispatch } from '@/store/hooks';
import { setUser } from '@/store/slices/authSlice';
import { fetchCart } from '@/store/slices/cartSlice';
import { fetchWishlist } from '@/store/slices/wishlistSlice';
import { PageSpinner } from '@/app/components/ui';
import HeaderSkeleton from '@/app/components/layout/HeaderSkeleton';
import CheckoutModalProvider from '@/app/components/checkout/CheckoutModalProvider';

const Header = dynamic(() => import('@/app/components/layout/Header'), {
  ssr: false,
  loading: () => <HeaderSkeleton />
});

const CartModal = dynamic(() => import('@/app/components/CartModal'), {
  ssr: false,
  loading: () => null
});

const Footer = dynamic(() => import('@/app/components/layout/Footer'), {
  ssr: false,
  loading: () => null
});

// Mobile/tablet bottom tab bar (Home · Browse · Cart · Message · Account),
// switched off for now. To restore it, uncomment this import and the
// <BottomNav /> render below, and put back the bottom clearance it needs:
// `pb-16 lg:pb-0` on the dashboard <main>, `pb-16 lg:pb-0` on the loading
// footer and `pb-16 … lg:pb-12` on the main footer in Footer.jsx.
// const BottomNav = dynamic(() => import('@/app/components/layout/BottomNav'), {
//   ssr: false,
//   loading: () => null
// });

const ReferralTrackerWrapper = dynamic(() => import('@/components/ReferralTrackerWrapper'), {
  ssr: false,
  loading: () => null
});

// Right-edge social links rail, switched off for now in favour of the support
// chat. To restore it, uncomment this import and the <FloatingSocialBar />
// render below.
// const FloatingSocialBar = dynamic(() => import('@/app/components/FloatingSocialBar'), {
//   ssr: false,
//   loading: () => null
// });

// const SupportChat = dynamic(() => import('@/app/components/SupportChat'), {
//   ssr: false,
//   loading: () => null
// });

const LoginModal = dynamic(() => import('@/app/components/userAuth/LoginModal'), {
  ssr: false,
  loading: () => null
});

const RegisterModal = dynamic(() => import('@/app/components/userAuth/RegisterModal'), {
  ssr: false,
  loading: () => null
});

/**
 * Toasts are styled to match the design system rather than react-hot-toast's
 * default dark pill: a light raised surface with the app's radius and shadow.
 */
const TOAST_OPTIONS = {
  duration: 3000,
  style: {
    background: 'var(--surface-raised)',
    color: 'var(--ink)',
    border: '1px solid var(--hairline)',
    borderRadius: 'var(--radius-lg)',
    boxShadow: 'var(--shadow-e3)',
    fontSize: '0.875rem',
    fontWeight: 500,
    letterSpacing: '-0.006em',
    padding: '12px 16px',
    maxWidth: '380px',
  },
  success: { iconTheme: { primary: 'var(--success)', secondary: '#fff' } },
  error: { iconTheme: { primary: 'var(--danger)', secondary: '#fff' } },
};

export default function LayoutDetector({ children }) {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const router = useRouter();
  const dispatch = useAppDispatch();

  const [showLogin, setShowLogin] = useState(false);
  const [showRegister, setShowRegister] = useState(false);
  const [isInitialLoad, setIsInitialLoad] = useState(true);

  const isDashboardRoute = pathname?.startsWith('/dashboard');
  // The sales funnel editor is a full-screen admin tool: no storefront chrome,
  // no cart or wishlist requests, no sign-up prompts.
  // A published funnel page is a landing page of its own and gets the same bare frame.
  const isBuilderRoute = pathname?.startsWith('/builder') || pathname?.startsWith('/funnel/');

  useEffect(() => {
    setIsInitialLoad(false);
  }, []);

  // Referral links and ?register=true open the sign-up modal shortly after the
  // page settles, so it does not appear mid-paint.
  useEffect(() => {
    if (isDashboardRoute || isBuilderRoute) return;

    const ref = searchParams.get("ref");
    const shouldRegister = searchParams.get("register") === "true";

    if (ref || shouldRegister) {
      const timer = setTimeout(() => setShowRegister(true), 800);
      return () => clearTimeout(timer);
    }
  }, [searchParams, isDashboardRoute, isBuilderRoute]);

  useEffect(() => {
    if (isDashboardRoute || isBuilderRoute) return;

    const loadInitialData = async () => {
      try {
        const token = localStorage.getItem('token');
        const userData = localStorage.getItem('user');

        if (token && userData) {
          dispatch(setUser(JSON.parse(userData)));
          await Promise.allSettled([
            dispatch(fetchCart()),
            dispatch(fetchWishlist())
          ]);
        }
      } catch (error) {
        console.error('Error loading initial data:', error);
      }
    };

    if (isInitialLoad) loadInitialData();
  }, [isDashboardRoute, isBuilderRoute, dispatch, isInitialLoad]);

  const hydrateSession = useCallback((userData) => {
    if (!userData) return;
    dispatch(setUser(userData));
    if (!isDashboardRoute) {
      Promise.allSettled([dispatch(fetchCart()), dispatch(fetchWishlist())]);
    }
  }, [dispatch, isDashboardRoute]);

  const handleRegisterSuccess = useCallback((userData) => {
    hydrateSession(userData);
    setShowRegister(false);
    router.replace('/', { scroll: false });
  }, [router, hydrateSession]);

  const handleLoginSuccess = useCallback((userData) => {
    hydrateSession(userData);
    setShowLogin(false);
    router.replace('/', { scroll: false });
  }, [router, hydrateSession]);

  const handleCloseRegister = useCallback(() => {
    setShowRegister(false);
    router.replace('/', { scroll: false });
  }, [router]);

  const handleCloseLogin = useCallback(() => {
    setShowLogin(false);
    router.replace('/', { scroll: false });
  }, [router]);

  // The delay lets the outgoing modal finish its exit before the incoming one
  // starts, so the two do not overlap mid-transition.
  const switchToRegister = useCallback(() => {
    setShowLogin(false);
    setTimeout(() => setShowRegister(true), 100);
  }, []);

  const switchToLogin = useCallback(() => {
    setShowRegister(false);
    setTimeout(() => setShowLogin(true), 100);
  }, []);

  // Every route clears the header's measured height. The home hero used to run
  // under the header and took no offset; it is now a boxed carousel on the page
  // background, so it needs the same clearance as any other page.
  const mainStyle = { paddingTop: 'var(--header-height, 80px)' };

  if (isBuilderRoute) {
    return (
      <>
        <Suspense fallback={<PageSpinner />}>{children}</Suspense>
        <Toaster position="bottom-center" reverseOrder={false} toastOptions={TOAST_OPTIONS} />
      </>
    );
  }

  // The checkout popup wraps everything, so the header's cart and every page
  // can open it.
  return (
    <CheckoutModalProvider>
      <ReferralTrackerWrapper />
      <Header />

      <main style={mainStyle}>
        <Suspense fallback={<PageSpinner />}>{children}</Suspense>
      </main>

      {/* The cart drawer is irrelevant inside the dashboard. */}
      {!isDashboardRoute && <CartModal />}

      {!isDashboardRoute && <Footer />}
      {/* {!isDashboardRoute && <FloatingSocialBar />} */}
      {/* {!isDashboardRoute && <SupportChat />} */}

      {/* <BottomNav onAccountClick={() => setShowLogin(true)} /> */}

      <LoginModal
        open={showLogin}
        onClose={handleCloseLogin}
        onSwitch={switchToRegister}
        onLoginSuccess={handleLoginSuccess}
      />
      <RegisterModal
        open={showRegister}
        onClose={handleCloseRegister}
        onSwitch={switchToLogin}
        onRegisterSuccess={handleRegisterSuccess}
      />

      <Toaster position="top-right" reverseOrder={false} toastOptions={TOAST_OPTIONS} />
    </CheckoutModalProvider>
  );
}
