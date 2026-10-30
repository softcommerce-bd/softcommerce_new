/**
 * Loading placeholders.
 *
 * Skeletons mirror the shape of the content they stand in for, so the layout
 * does not jump when real data lands. The previous code used a mix of pulsing
 * grey boxes and centred spinners for the same states.
 */

export function Skeleton({ className = "", style, rounded = "md" }) {
  const radius = {
    sm: "rounded-sm",
    md: "rounded-md",
    lg: "rounded-lg",
    xl: "rounded-xl",
    full: "rounded-full",
  }[rounded];

  return (
    <div
      className={["skeleton", radius, className].join(" ")}
      style={style}
      aria-hidden="true"
    />
  );
}

/** A run of text lines; the last is short, the way a paragraph ends. */
export function SkeletonText({ lines = 3, className = "" }) {
  return (
    <div className={["space-y-2", className].join(" ")} aria-hidden="true">
      {Array.from({ length: lines }).map((_, i) => (
        <Skeleton
          key={i}
          className="h-3"
          style={{ width: i === lines - 1 ? "62%" : "100%" }}
        />
      ))}
    </div>
  );
}

/** Matches ProductCard1's proportions: image, title, price, action row. */
export function SkeletonProductCard() {
  return (
    <div className="card overflow-hidden" aria-hidden="true">
      <Skeleton className="aspect-square w-full" rounded="sm" />
      <div className="space-y-3 p-4">
        <Skeleton className="h-3.5 w-4/5" />
        <Skeleton className="h-3.5 w-3/5" />
        <Skeleton className="h-5 w-24" />
        <div className="flex gap-2 pt-1">
          <Skeleton className="h-10 flex-1" />
          <Skeleton className="h-10 w-12" />
        </div>
      </div>
    </div>
  );
}

/** A responsive grid of product skeletons, matched to the shop grid. */
export function SkeletonProductGrid({ count = 8 }) {
  return (
    <div className="grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 xl:grid-cols-4">
      {Array.from({ length: count }).map((_, i) => (
        <SkeletonProductCard key={i} />
      ))}
    </div>
  );
}

export default Skeleton;
