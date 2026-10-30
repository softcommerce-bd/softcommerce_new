"use client";

/**
 * Product page skeleton. Mirrors the loaded layout: the full-bleed breadcrumb
 * band, a square framed photo with a thumbnail row, and an info column of
 * title, price, trust grid, quantity and the two purchase buttons.
 */
export default function loading() {
  return (
    <div className="bg-cream">
      <div className="border-b border-stone-200/80 bg-white/50">
        <div className="container-page py-3">
          <div className="skeleton h-4 w-56" />
        </div>
      </div>

      <section className="container-page min-h-screen pb-16 pt-6 sm:pt-10">
        <div className="grid grid-cols-1 items-start gap-8 sm:gap-12 lg:grid-cols-12">
          {/* Gallery */}
          <div className="space-y-4 lg:col-span-6">
            <div className="skeleton aspect-square w-full rounded-2xl" />
            <div className="flex gap-3 p-1">
              {[1, 2, 3, 4].map((i) => (
                <div key={i} className="skeleton h-20 w-20 shrink-0 rounded-xl" />
              ))}
            </div>
          </div>

          {/* Details */}
          <div className="space-y-6 lg:col-span-6">
            <div className="space-y-3 border-b border-stone-200/60 pb-5">
              <div className="skeleton h-5 w-40" />
              <div className="skeleton h-10 w-4/5" />
              <div className="skeleton h-9 w-1/2" />
            </div>

            <div className="skeleton h-24 w-full rounded-xl" />

            <div className="space-y-3">
              <div className="skeleton h-4 w-24" />
              <div className="skeleton h-10 w-40 rounded-lg" />
            </div>

            <div className="space-y-3 pt-2">
              <div className="skeleton h-13 w-full rounded-xl" />
              <div className="skeleton h-13 w-full rounded-xl" />
            </div>
          </div>
        </div>

        {/* Tabs */}
        <div className="mt-14 space-y-3 border-t border-stone-200 pt-6 sm:mt-18">
          <div className="skeleton h-6 w-72" />
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="skeleton h-4 w-full" />
          ))}
        </div>
      </section>
    </div>
  );
}
