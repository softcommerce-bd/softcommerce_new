/**
 * First-paint placeholder for the header.
 *
 * Mirrors the real header's geometry at every breakpoint — the announcement
 * strip, the grid row (menu · logo · search · actions), the search row below
 * `lg`, and the desktop category nav — so the page does
 * not reflow when the header hydrates. Used by both Header's own pre-mount state and the dynamic-import
 * fallback in LayoutDetector.
 */
export default function HeaderSkeleton() {
  return (
    <div className="fixed inset-x-0 top-0 z-50">
      <div className="h-[51px] w-full bg-stone-900 sm:h-[55px] lg:h-8" />

      <header className="w-full border-b border-stone-200/80">
        <div className="bg-cream">
          <div className="container-page grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-2 gap-y-2 py-2.5 sm:gap-x-4 sm:py-3 lg:gap-x-8 lg:py-3.5">
            <div className="flex items-center gap-2 sm:gap-3">
              <div className="skeleton size-9 rounded-lg sm:size-10 lg:hidden" />
              <div className="skeleton h-6 w-[104px] rounded-md sm:h-7 sm:w-[128px]" />
            </div>

            <div className="skeleton col-span-3 row-start-2 h-9 rounded-full lg:col-span-1 lg:h-10 lg:col-start-2 lg:row-start-1 lg:mx-auto lg:w-full lg:max-w-xl xl:max-w-2xl" />

            <div className="col-start-3 row-start-1 flex items-center gap-1 sm:gap-2">
              <div className="skeleton size-9 rounded-lg sm:size-10" />
              <div className="skeleton size-9 rounded-lg sm:size-10" />
              <div className="skeleton h-9 w-11 rounded-full sm:h-10 md:w-[104px]" />
            </div>
          </div>
        </div>

        <div className="hidden h-11 border-t border-stone-200/60 bg-white/70 lg:block" />
      </header>
    </div>
  );
}
