// "use client";

// import { useEffect, useRef } from "react";
// import { useSearchParams, useRouter } from "next/navigation";

// export default function ReferralTracker() {
//   const searchParams = useSearchParams();
//   const router = useRouter();
//   const trackedRef = useRef(false);

//   useEffect(() => {
//     const ref = searchParams.get("ref") || searchParams.get("referral_code");
//     const shouldRegister = searchParams.get("register") === "true";
    
//     if (ref && !trackedRef.current) {
//       trackedRef.current = true;
      
//       const maxAge = 30 * 24 * 60 * 60;
//       // Store in multiple places for reliability
//       document.cookie = `referral_code=${ref}; max-age=${maxAge}; path=/; SameSite=Lax`;
//       sessionStorage.setItem("referral_code", ref);
//       localStorage.setItem("referral_code", ref);
//       console.log("✅ Referral saved to cookie, sessionStorage & localStorage:", ref);
      
//       // Track the click
//       trackAffiliateClick(ref);
      
//       // Don't clean URL if register modal is about to open
//       if (!shouldRegister) {
//         setTimeout(() => {
//           const params = new URLSearchParams(searchParams.toString());
//           params.delete("ref");
//           params.delete("referral_code");
//           const newUrl = params.toString() ? `/?${params.toString()}` : '/';
//           router.replace(newUrl, { scroll: false });
//         }, 1500);
//       }
//     }
//   }, [searchParams]);

//   const trackAffiliateClick = async (referralCode) => {
//     try {
//       const apiUrl = process.env.NEXT_PUBLIC_API_URL || '/api/v2';
//       await fetch(`${apiUrl}/affiliate/track-click`, {
//         method: 'POST',
//         headers: {
//           'Content-Type': 'application/json',
//           'Accept': 'application/json',
//         },
//         body: JSON.stringify({ referral_code: referralCode }),
//       });
//       console.log("✅ Affiliate click tracked for:", referralCode);
//     } catch (error) {
//       console.error("Failed to track affiliate click:", error);
//     }
//   };

//   return null;
// }

"use client";

import { useEffect, useRef } from "react";
import { useSearchParams, useRouter } from "next/navigation";

export default function ReferralTracker() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const trackedRef = useRef(false);

  useEffect(() => {
    const ref = searchParams.get("ref") || searchParams.get("referral_code");
    const shouldRegister = searchParams.get("register") === "true";
    
    if (ref && !trackedRef.current) {
      trackedRef.current = true;
      
      const maxAge = 30 * 24 * 60 * 60;
      document.cookie = `referral_code=${ref}; max-age=${maxAge}; path=/; SameSite=Lax`;
      sessionStorage.setItem("referral_code", ref);
      localStorage.setItem("referral_code", ref);
      // console.log(" Referral saved:", ref);
      
      trackAffiliateClick(ref);
      
      if (!shouldRegister) {
        setTimeout(() => {
          const params = new URLSearchParams(searchParams.toString());
          params.delete("ref"); params.delete("referral_code");
          const newUrl = params.toString() ? `/?${params.toString()}` : '/';
          router.replace(newUrl, { scroll: false });
        }, 1500);
      }
    }
  }, [searchParams]);

  const trackAffiliateClick = async (referralCode) => {
    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || '/api/v2';
      await fetch(`${apiUrl}/affiliate/track-click`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ referral_code: referralCode }),
      });
      // console.log("Affiliate click tracked for:", referralCode);
    } catch (error) {
      console.error("Failed to track affiliate click:", error);
    }
  };

  return null;
}