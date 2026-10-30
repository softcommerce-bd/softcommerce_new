"use client";

/**
 * Horizontal product rail.
 *
 * The home page rails, the product page's "You may also like" and the seller
 * shop's sections each carried their own copy of this Swiper setup, its
 * breakpoints, its arrows and its skeleton. They now share one.
 *
 * The rail handles every state itself — loading, empty, and loaded — so a
 * calling section only has to fetch data and hand it over.
 */

import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation, Autoplay } from "swiper/modules";
import { PackageSearch } from "lucide-react";

import ProductCard1 from "./ProductCard1";
import {
  SectionHeader,
  CarouselNav,
  EmptyState,
  SkeletonProductCard,
} from "../ui";


/**
 * Four across on a wide screen, two on a phone. Matching the shop grid means a
 * card is the same size wherever it appears.
 */
const BREAKPOINTS = {
  320: { slidesPerView: 2, spaceBetween: 12 },
  640: { slidesPerView: 3, spaceBetween: 16 },
  768: { slidesPerView: 3, spaceBetween: 20 },
  1024: { slidesPerView: 4, spaceBetween: 24 },
  1280: { slidesPerView: 4, spaceBetween: 24 },
};

export default function ProductCarousel({
  products = [],
  loading = false,
  title,
  eyebrow,
  description,
  href,
  linkLabel = "View all",
  /** Unique per rail — Swiper binds its arrows by this name. */
  name = "rail",
  autoplay = true,
  emptyTitle = "Nothing here yet",
  emptyDescription,
  skeletonCount = 4,
  className = "",
}) {
  // Loading with nothing cached: show the rail's shape, not a spinner.
  if (loading && products.length === 0) {
    return (
      <section className={`container-page section ${className}`}>
        {title && <SectionHeader eyebrow={eyebrow} title={title} />}
        <div className="grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 xl:grid-cols-4">
          {Array.from({ length: skeletonCount }).map((_, i) => (
            <SkeletonProductCard key={i} />
          ))}
        </div>
      </section>
    );
  }

  if (!loading && products.length === 0) {
    return (
      <section className={`container-page section ${className}`}>
        {title && <SectionHeader eyebrow={eyebrow} title={title} />}
        <EmptyState
          icon={PackageSearch}
          title={emptyTitle}
          description={emptyDescription}
        />
      </section>
    );
  }

  // Arrows and looping only make sense once there is more than a screenful.
  const hasOverflow = products.length > 4;

  return (
    <section className={`container-page section ${className}`}>
      {title && (
        <SectionHeader
          eyebrow={eyebrow}
          title={title}
          description={description}
          href={href}
          linkLabel={linkLabel}
        />
      )}

      <div className="carousel-hover-nav relative">
        <Swiper
          modules={[Navigation, Autoplay]}
          autoplay={
            autoplay && hasOverflow
              ? { delay: 6500, disableOnInteraction: false, pauseOnMouseEnter: true }
              : false
          }
          spaceBetween={24}
          slidesPerView={2}
          breakpoints={BREAKPOINTS}
          navigation={
            hasOverflow ? { nextEl: `.${name}-next`, prevEl: `.${name}-prev` } : false
          }
          // Cards vary in height with their option rows, so slides align to the
          // top instead of stretching to the tallest one.
          className="pb-1!"
        >
          {products.map((item, index) => (
            <SwiperSlide key={item.id ?? index} className="h-auto! self-start!">
              <ProductCard1 item={item} priority={index < 4} />
            </SwiperSlide>
          ))}
        </Swiper>

        {hasOverflow && <CarouselNav name={name} />}
      </div>
    </section>
  );
}
