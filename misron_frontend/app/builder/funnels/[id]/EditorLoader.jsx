"use client";

import dynamic from "next/dynamic";
import { PageSpinner } from "@/app/components/ui";

// The editor runs only in the browser and ships in its own chunk, so none of
// its code (drag and drop, panels, field controls) reaches storefront pages.
const EditorApp = dynamic(() => import("@/funnel/editor/EditorApp.jsx"), {
  ssr: false,
  loading: () => (
    <div className="flex h-dvh items-center justify-center bg-canvas">
      <PageSpinner label="Opening the editor" />
    </div>
  ),
});

export default function EditorLoader({ funnelId }) {
  return <EditorApp funnelId={funnelId} />;
}
