"use client";

import { Suspense } from "react";
import ReferralTracker from "./ReferralTracker";

export default function ReferralTrackerWrapper() {
  return (
    <Suspense fallback={null}>
      <ReferralTracker />
    </Suspense>
  );
}