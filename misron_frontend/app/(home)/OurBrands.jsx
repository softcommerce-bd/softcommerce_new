"use client";

/**
 * Brand carousel.
 *
 * Now uses the shared SectionHeader and CarouselNav, so its heading and arrows
 * match every other carousel on the page. The previous "See All" control was a
 * dark pill wrapping a white square wrapping a rotated inline arrow glyph.
 *
 * The progress bar under the track is kept — it tells you how far through a
 * long brand list you are — but drawn as a hairline rather than a 3px slab.
 *
 * Fetching, caching and slide behaviour are unchanged.
 */

import { useState } from "react";
import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation, Autoplay } from "swiper/modules";

import BrandCard from "../components/layout/BrandCard";
import useCachedFetch from "../utils/useCachedFetch";
import { SectionHeader, CarouselNav, Skeleton } from "@/app/components/ui";


export default function OurBrands() {
  const { data: brands, loading, error } = useCachedFetch(
    `${process.env.NEXT_PUBLIC_API_URL}/selected-brands`,
    "brands_cache",
    5 * 60 * 1000,
  );

  const [progress, setProgress] = useState(0);

  if (loading) {
    return (
      <section className="container-page section">
        <Skeleton className="mb-8 h-7 w-40" />
        <div className="grid grid-cols-2 gap-5 md:grid-cols-4">
          {[0, 1, 2, 3].map((i) => (
            <Skeleton key={i} className="aspect-4/3 w-full" rounded="lg" />
          ))}
        </div>
      </section>
    );
  }

  // A failed brand strip is not worth an error panel on the home page; the
  // section simply does not render.
  if (error) return null;
  if (!brands || brands.length === 0) return null;

  return (
    <section className="container-page section">
      <SectionHeader title="Our brands" href="/all-brands" linkLabel="See all" />

      <div className="carousel-hover-nav relative">
        <Swiper
          modules={[Navigation, Autoplay]}
          autoplay={{ delay: 6500, disableOnInteraction: false }}
          spaceBetween={20}
          slidesPerView={6}
          navigation={{
            nextEl: ".brands-next",
            prevEl: ".brands-prev",
          }}
          breakpoints={{
            320: { slidesPerView: 2 },
            640: { slidesPerView: 3 },
            768: { slidesPerView: 4 },
            1024: { slidesPerView: 4 },
            1280: { slidesPerView: 5 },
          }}
          onSlideChange={(swiper) => {
            const totalSlides = brands.length;
            const visibleSlides = swiper.params.slidesPerView;
            const currentIndex = swiper.activeIndex;

            const maxIndex = Math.max(totalSlides - visibleSlides, 1);
            setProgress(Math.min((currentIndex / maxIndex) * 100, 100));
          }}
        >
          {brands.map((item) => (
            <SwiperSlide key={item.id}>
              <BrandCard item={item} />
            </SwiperSlide>
          ))}
        </Swiper>

        <CarouselNav name="brands" />

        <div className="mt-7 h-0.5 w-full overflow-hidden rounded-full bg-hairline">
          <div
            className="h-full rounded-full bg-ink transition-[width] duration-slow ease-standard"
            style={{ width: `${progress}%` }}
          />
        </div>
      </div>
    </section>
  );
}
