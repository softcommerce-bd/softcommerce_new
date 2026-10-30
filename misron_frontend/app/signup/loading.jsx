import { PageSpinner } from "@/app/components/ui";

/**
 * Route-level loading fallback for this part of the store. It lives here rather
 * than in app/loading.jsx so that the sales funnel pages (app/funnel) are not
 * inside a loading boundary: those pages must finish their existence check
 * before anything is sent, or a closed page could not answer HTTP 404.
 */
export default function Loading() {
  return <PageSpinner />;
}
