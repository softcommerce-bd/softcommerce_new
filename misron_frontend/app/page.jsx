import dynamic from "next/dynamic";

import Slider from "./(home)/Slider";
import FeaturedCategory from "./(home)/FeaturedCategory";
// import MarqueeBanner from "./(home)/MarqueeBanner";

/**
 * Home page.
 *
 * The first three sections are imported directly — the hero, the announcement
 * band and the category tiles are the first paint, so deferring them would only
 * delay it. The band sits between the hero and the tiles; the brand-promise
 * strip closes the page, directly above the footer.
 *
 * Everything from the first promo banner down is below the fold on every
 * viewport and is loaded on demand. Each carries its own data fetching and,
 * between them, all of Swiper; splitting them out keeps that off the initial
 * route chunk.
 *
 * The three admin banner slots are placed individually rather than merged into
 * one row, each closing the section above it: level 1 after the category
 * tiles, level 2 after the best sellers, level 3 after the shop-by-category
 * grid.
 *
 * Each placeholder reserves roughly the height of the section it stands in
 * for, so the page does not jump as they arrive.
 */

const SectionPlaceholder = ({ className = "h-[520px]" }) => (
  <div className={`container-page section ${className}`} aria-hidden="true">
    <div className="skeleton h-full w-full rounded-xl" />
  </div>
);

const BestSelling = dynamic(() => import("./(home)/BestSelling"), {
  loading: () => <SectionPlaceholder className="h-[460px] lg:h-[640px]" />,
});

const ShopByCategory = dynamic(() => import("./(home)/ShopByCategory"), {
  loading: () => <SectionPlaceholder className="h-[640px]" />,
});

const WatchAndBuy = dynamic(() => import("./(home)/WatchAndBuy"), {
  loading: () => <SectionPlaceholder className="h-[420px]" />,
});

const OurBrands = dynamic(() => import("./(home)/OurBrands"), {
  loading: () => <SectionPlaceholder className="h-[260px]" />,
});

// All three banner slots sit below the fold, so none of them is on the initial
// route chunk. One dynamic import serves all three: they are the same
// component, so the chunk is fetched once and the later levels reuse it.
const DeferredPromoBanner = dynamic(() => import("./(home)/PromoBanner"), {
  loading: () => <SectionPlaceholder className="h-[200px] md:h-[480px]" />,
});

export default async function Home() {
  return (
    <div className="min-h-screen">
      <Slider />
      {/* <MarqueeBanner /> */}
      <FeaturedCategory />
      <DeferredPromoBanner level={1} emptyMessage="No banner 1 found" />
      <BestSelling />
      <DeferredPromoBanner level={2} />
      <ShopByCategory />
      <DeferredPromoBanner level={3} />
      <WatchAndBuy />
      <OurBrands />
    </div>
  );
}
