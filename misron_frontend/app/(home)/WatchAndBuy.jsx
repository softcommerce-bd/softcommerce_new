"use client";

/**
 * Shoppable video rail.
 *
 * The heading and arrows come from the shared SectionHeader and CarouselNav,
 * so this rail matches the product rails above it. The previous version drew
 * two arrow buttons three separate times — once in the loading branch and
 * twice in the loaded one — each with its own inline chevron SVG.
 *
 * Fetching and the video modal handoff are unchanged.
 */

import { useEffect, useState } from "react";
import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation } from "swiper/modules";
import { Video } from "lucide-react";

import VideoCard from "../components/VideoCard";
import VideoModal from "@/components/VideoModal";
import {
  SectionHeader,
  CarouselNav,
  EmptyState,
  Skeleton,
} from "@/app/components/ui";


export default function WatchAndBuy() {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchProducts();
  }, []);

  const fetchProducts = async () => {
    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/buy-watch-products`,
      );
      const data = await response.json();
      if (data.success) setProducts(data.data);
    } catch (err) {
      console.error("Error fetching products:", err);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <section className="container-page section">
        <SectionHeader eyebrow="Shoppable" title="Watch & buy" />
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
          {[0, 1, 2, 3, 4].map((i) => (
            // Video cards are portrait, matching the reel format they hold.
            <Skeleton key={i} className="aspect-9/16 w-full" rounded="lg" />
          ))}
        </div>
      </section>
    );
  }

  return (
    <>
      <section className="container-page section">
        <SectionHeader eyebrow="Shoppable" title="Watch & buy" />

        {products.length > 0 ? (
          <div className="carousel-hover-nav relative">
            <Swiper
              modules={[Navigation]}
              spaceBetween={16}
              slidesPerView={2}
              breakpoints={{
                640: { slidesPerView: 3 },
                768: { slidesPerView: 4 },
                1024: { slidesPerView: 5 },
              }}
              navigation={{
                nextEl: ".watch-buy-next",
                prevEl: ".watch-buy-prev",
              }}
            >
              {products.map((item) => (
                <SwiperSlide key={item.id}>
                  <VideoCard item={item} />
                </SwiperSlide>
              ))}
            </Swiper>

            {products.length > 5 && <CarouselNav name="watch-buy" />}
          </div>
        ) : (
          <EmptyState
            icon={Video}
            title="No videos yet"
            description="Shoppable videos will show up here once they're published."
          />
        )}
      </section>

      <VideoModal />
    </>
  );
}
