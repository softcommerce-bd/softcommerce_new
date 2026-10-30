"use client";

import { useEffect } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { PageSpinner } from "@/app/components/ui";

export default function SignupPage() {
  const router = useRouter();
  const searchParams = useSearchParams();

  useEffect(() => {
    const ref = searchParams.get("ref");
    const params = new URLSearchParams();
    if (ref) params.set("ref", ref);
    params.set("register", "true");
    
    const redirectUrl = `/${params.toString() ? `?${params.toString()}` : ""}`;
    router.replace(redirectUrl);
  }, [router, searchParams]);

  // This route only forwards to the home page with the sign-up modal open, so
  // it shows a brief spinner rather than any page of its own.
  return <PageSpinner label="Opening sign up" />;
}