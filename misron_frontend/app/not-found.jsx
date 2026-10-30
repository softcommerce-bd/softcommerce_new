"use client";

/**
 * 404.
 *
 * Restrained: a single oversized numeral in the tertiary ink, a clear
 * sentence, and two exits. The previous version used a purple gradient
 * wordmark with a blurred blue glow behind it, which matched nothing else in
 * the app.
 */

import { useRouter } from "next/navigation";
import { ArrowLeft, Home, Search } from "lucide-react";
import { Button } from "./components/ui";

export default function NotFound() {
  const router = useRouter();

  return (
    <div className="flex min-h-[70vh] flex-col items-center justify-center px-6 py-20 text-center">
      <p
        className="type-display font-semibold tabular text-ink-quaternary"
        aria-hidden="true"
      >
        404
      </p>

      <h1 className="type-title-1 mt-4 text-ink">This page doesn&apos;t exist</h1>

      <p className="type-callout mt-3 max-w-sm text-ink-tertiary">
        The link may be out of date, or the page may have moved. Try searching
        for what you need.
      </p>

      <div className="mt-9 flex flex-wrap items-center justify-center gap-3">
        <Button href="/" iconLeft={Home}>
          Go home
        </Button>
        <Button href="/shop_page" variant="secondary" iconLeft={Search}>
          Browse products
        </Button>
        <Button onClick={() => router.back()} variant="ghost" iconLeft={ArrowLeft}>
          Go back
        </Button>
      </div>
    </div>
  );
}
