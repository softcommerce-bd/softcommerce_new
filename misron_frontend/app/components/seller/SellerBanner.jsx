"use client";

/**
 * Shop cover image.
 *
 * The fallback was a blue-to-purple gradient, unrelated to the app's palette;
 * a shop without a banner now gets the sunken canvas with its name over it.
 */

import Image from "next/image";
import { useState } from "react";

export default function SellerBanner({ shopData }) {
  const [imageError, setImageError] = useState(false);

  if (imageError || !shopData?.top_banner) {
    return (
      <div className="flex h-48 w-full items-center justify-center rounded-xl bg-canvas-sunken md:h-64 lg:h-72">
        <h2 className="type-title-2 px-6 text-center text-ink-tertiary">
          {shopData?.name}
        </h2>
      </div>
    );
  }

  return (
    <div className="relative h-48 w-full overflow-hidden rounded-xl bg-canvas-sunken md:h-64 lg:h-72">
      <Image
        src={shopData.top_banner}
        alt=""
        fill
        className="object-cover"
        sizes="100vw"
        priority
        onError={() => setImageError(true)}
      />
    </div>
  );
}
