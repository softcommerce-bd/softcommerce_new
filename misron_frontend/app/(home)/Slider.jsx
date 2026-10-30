"use client";

/**
 * Home hero: a rounded carousel with two image cards beside it.
 *
 * Desktop puts the carousel in the left 8 columns and the two cards stacked in
 * the right 4. Below `lg` the cards drop under the carousel as a two-column
 * row, phones included.
 *
 * The carousel slides and the two card images are all admin-managed (Website
 * Setup → Home Page → Home Slider) and arrive in one `/sliders` response. A card
 * with no upload, or an empty slider, shows "No image found".
 *
 * The first slide renders as plain markup so it is the LCP element; Swiper
 * (~136KB) is loaded afterwards and only when there is more than one slide,
 * so the carousel never blocks the first paint.
 *
 * Caching, fetch fallbacks and navigation behaviour are unchanged.
 */

import { useEffect, useState, useRef, useCallback } from "react";
import dynamic from "next/dynamic";
import { useRouter } from "next/navigation";
import { ImageOff, RotateCw } from "lucide-react";

import HeroSlide from "./HeroSlide";
import HeroSideCard from "./HeroSideCard";
import { Button, Skeleton } from "@/app/components/ui";

// Loaded after the first paint. Until it arrives the static first slide below
// stands in, so there is nothing to fall back to and no placeholder needed.
const HeroCarousel = dynamic(() => import("./HeroCarousel"), { ssr: false });


const SLIDER_CACHE_KEY = "slider-data";
const SLIDER_CACHE_TIME_KEY = "slider-data_time";
const SIDE_CACHE_KEY = "slider-side-data";
const CACHE_DURATION = 30 * 60 * 1000; // 30 minutes

/** Every state renders at the same height so the page below never jumps.
 *
 *  The carousel frame has a fixed height at each breakpoint rather than an
 *  aspect ratio, because it sits beside two stacked cards on desktop and the
 *  column of cards stretches to match it. `object-cover` fills the frame, so
 *  the art is cropped rather than letterboxed. Phones are the exception: there
 *  the frame is full width with nothing beside it, so it keeps the banner's
 *  wide shape through an aspect ratio instead. */
const HERO_HEIGHT = "aspect-9/5 sm:aspect-auto sm:h-[420px] lg:h-[460px] xl:h-[500px]";

export default function Slider() {
  const [sliders, setSliders] = useState([]);
  // Two entries — `{ photo }` or null for an empty slot. `null` while still
  // unknown, so the cards show a skeleton instead of flashing "No image found".
  const [sideImages, setSideImages] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [carouselReady, setCarouselReady] = useState(false);
  const router = useRouter();
  const fetchAttempted = useRef(false);
  const isMounted = useRef(true);

  const handleSelect = useCallback(
    (url) => {
      if (url && url !== "#" && url !== null) router.push(url);
    },
    [router],
  );

  const loadFromCache = useCallback(() => {
    try {
      const cachedSide = localStorage.getItem(SIDE_CACHE_KEY);
      if (cachedSide) setSideImages(JSON.parse(cachedSide));

      const cachedData = localStorage.getItem(SLIDER_CACHE_KEY);
      const cachedTime = localStorage.getItem(SLIDER_CACHE_TIME_KEY);

      if (cachedData && cachedTime) {
        const timeDiff = Date.now() - parseInt(cachedTime);

        if (timeDiff < CACHE_DURATION) {
          const parsedData = JSON.parse(cachedData);
          if (parsedData && parsedData.length > 0) {
            setSliders(parsedData);
            setLoading(false);
            return true;
          }
        }
      }
    } catch (err) {
      console.error("Cache read error:", err);
    }
    return false;
  }, []);

  const fetchSliders = useCallback(async () => {
    if (fetchAttempted.current) return;
    fetchAttempted.current = true;

    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 10000);

      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/sliders`, {
        signal: controller.signal,
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
      });

      clearTimeout(timeoutId);

      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const json = await response.json();

      if (isMounted.current) {
        // The endpoint has returned several shapes over time; each is accepted.
        let sliderData = null;

        if (json.success && Array.isArray(json.data)) sliderData = json.data;
        else if (Array.isArray(json)) sliderData = json;
        else if (json.data && Array.isArray(json.data)) sliderData = json.data;
        else if (json.sliders && Array.isArray(json.sliders)) sliderData = json.sliders;

        const side = Array.isArray(json.side_images) ? json.side_images : [null, null];

        // An empty list is a valid answer — the admin has not uploaded any
        // slides — so it clears the cache and shows "No image found" rather
        // than the error state.
        setSliders(sliderData || []);
        setSideImages(side);
        setError(null);

        if (sliderData && sliderData.length > 0) {
          localStorage.setItem(SLIDER_CACHE_KEY, JSON.stringify(sliderData));
          localStorage.setItem(SLIDER_CACHE_TIME_KEY, Date.now().toString());
        } else {
          localStorage.removeItem(SLIDER_CACHE_KEY);
          localStorage.removeItem(SLIDER_CACHE_TIME_KEY);
        }
        localStorage.setItem(SIDE_CACHE_KEY, JSON.stringify(side));
      }
    } catch (err) {
      // The only abort is the 10 s timeout above, which is a failure like any
      // other — left silent it fell through to the "No image found" state.
      if (isMounted.current) {
        if (err.name !== "AbortError") console.error("Fetch error:", err);
        setError(err.name === "AbortError" ? "Request timed out" : err.message);
        setSideImages((current) => current || [null, null]);
      }
    } finally {
      if (isMounted.current) {
        setLoading(false);
        fetchAttempted.current = false;
      }
    }
  }, []);

  useEffect(() => {
    isMounted.current = true;

    loadFromCache();
    fetchSliders();

    return () => {
      isMounted.current = false;
    };
  }, [loadFromCache, fetchSliders]);

  // Bring the carousel in once the browser is idle, so downloading and parsing
  // Swiper never competes with the hero image for the first paint.
  useEffect(() => {
    if (sliders.length <= 1) return;

    const schedule =
      typeof requestIdleCallback === "function"
        ? requestIdleCallback
        : (cb) => setTimeout(cb, 200);
    const cancel =
      typeof cancelIdleCallback === "function" ? cancelIdleCallback : clearTimeout;

    const handle = schedule(() => setCarouselReady(true), { timeout: 2000 });
    return () => cancel(handle);
  }, [sliders.length]);

  const hasSlides = sliders.length > 0;

  let frameContent;
  if (loading && !hasSlides) {
    frameContent = <Skeleton className="h-full w-full" rounded="sm" />;
  } else if (error && !hasSlides) {
    frameContent = (
      <div className="flex h-full w-full flex-col items-center justify-center gap-4">
        <ImageOff size={30} className="text-ink-quaternary" strokeWidth={1.5} />
        <p className="type-callout text-ink-tertiary">Couldn&apos;t load the gallery</p>
        <Button
          variant="secondary"
          size="sm"
          iconLeft={RotateCw}
          onClick={() => {
            setLoading(true);
            setError(null);
            fetchAttempted.current = false;
            fetchSliders();
          }}
        >
          Try again
        </Button>
      </div>
    );
  } else if (!hasSlides) {
    frameContent = (
      <div className="flex h-full w-full items-center justify-center">
        <p className="type-callout text-ink-tertiary">No image found</p>
      </div>
    );
  } else if (sliders.length > 1 && carouselReady) {
    // Mount the carousel only after the first paint, and only when there is
    // more than one slide — a single-slide hero has nothing to swipe, so it
    // never pays for Swiper at all.
    frameContent = <HeroCarousel sliders={sliders} onSelect={handleSelect} />;
  } else {
    frameContent = <HeroSlide item={sliders[0]} index={0} onSelect={handleSelect} />;
  }

  return (
    <section className="container-page pb-2 pt-3 md:pt-4">
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <div
          className={`hero-swiper relative overflow-hidden rounded-2xl shadow-e3 lg:col-span-8 ${HERO_HEIGHT} ${
            hasSlides ? "bg-black" : "bg-canvas-sunken"
          }`}
        >
          {frameContent}
        </div>

        <div className="flex flex-row gap-3 sm:gap-4 lg:col-span-4 lg:flex-col">
          {[0, 1].map((slot) => (
            <HeroSideCard
              // Keyed on the file so a new upload clears an earlier load failure.
              key={`${slot}-${sideImages?.[slot]?.photo || ""}`}
              loading={sideImages === null}
              src={sideImages?.[slot]?.photo}
              alt={`Featured ${slot + 1}`}
            />
          ))}
        </div>
      </div>
    </section>
  );
}
