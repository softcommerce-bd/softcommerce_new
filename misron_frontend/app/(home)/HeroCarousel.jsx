"use client";

import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay, Navigation, Pagination } from "swiper/modules";
import { ChevronLeft, ChevronRight } from "lucide-react";

import HeroSlide from "./HeroSlide";

/**
 * The hero's Swiper carousel, split into its own module so it can be loaded
 * on demand.
 *
 * Swiper is ~136KB of the home page's JS. The hero is the first thing painted
 * but the *carousel* is not needed for that paint — one static slide is. Slider
 * renders that slide directly and pulls this in afterwards, so Swiper no longer
 * blocks the largest contentful paint.
 *
 * The arrows are plain buttons bound by selector, like CarouselNav, but sit
 * inside the frame: CarouselNav pushes its arrows outside the carousel, and the
 * hero frame clips anything that leaves it. On phones they are hidden; swipe
 * and the pagination dots cover navigation there.
 */
const ARROW =
  "absolute top-1/2 z-10 hidden size-9 -translate-y-1/2 items-center justify-center sm:flex " +
  "rounded-full bg-white/90 text-ink shadow-e2 transition-all duration-150 ease-standard " +
  "hover:scale-105 hover:bg-white active:scale-95 disabled:opacity-30 sm:size-10";

export default function HeroCarousel({ sliders, onSelect }) {
  return (
    <>
      <Swiper
        modules={[Autoplay, Navigation, Pagination]}
        autoplay={{
          delay: 5000,
          disableOnInteraction: false,
          pauseOnMouseEnter: true,
        }}
        navigation={{ prevEl: ".hero-prev", nextEl: ".hero-next" }}
        pagination={{ clickable: true }}
        loop={sliders.length > 1}
        speed={800}
        className="h-full w-full"
      >
        {sliders.map((item, index) => (
          <SwiperSlide key={item.id || index}>
            <HeroSlide item={item} index={index} onSelect={onSelect} />
          </SwiperSlide>
        ))}
      </Swiper>

      <button
        type="button"
        aria-label="Previous slide"
        className={`hero-prev ${ARROW} left-3 sm:left-4`}
      >
        <ChevronLeft size={18} strokeWidth={2} aria-hidden="true" />
      </button>
      <button
        type="button"
        aria-label="Next slide"
        className={`hero-next ${ARROW} right-3 sm:right-4`}
      >
        <ChevronRight size={18} strokeWidth={2} aria-hidden="true" />
      </button>
    </>
  );
}
