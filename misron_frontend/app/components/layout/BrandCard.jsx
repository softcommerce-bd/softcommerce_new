import Image from "next/image";
import Link from "next/link";

/**
 * Brand tile.
 *
 * The name now sits below the logo rather than in a floating white pill over
 * it, so a logo is never partly covered by its own label. Logos sit on the
 * sunken canvas and are contained rather than cropped — `object-cover` was
 * cutting the edges off wide wordmarks.
 *
 * A commented-out earlier version of the component has been removed, and a
 * missing logo now falls back to the brand's initial instead of a broken
 * image.
 */
export default function BrandCard({ item }) {
  return (
    <Link
      href={`/shop_page?brands=${item.id}`}
      className="group block text-center"
    >
      <div className="relative aspect-square w-full overflow-hidden rounded-xl bg-canvas-sunken transition-shadow duration-base ease-standard group-hover:shadow-e2">
        {item?.logo ? (
          <Image
            src={item.logo}
            alt={item?.name || "Brand"}
            fill
            className="object-contain p-6 transition-transform duration-slower ease-standard group-hover:scale-[1.04]"
            sizes="(max-width: 768px) 33vw, 20vw"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center">
            <span className="type-title-1 text-ink-quaternary" aria-hidden="true">
              {item?.name?.[0] ?? "?"}
            </span>
          </div>
        )}
      </div>

      <h3 className="type-footnote mt-3 truncate font-medium text-ink-secondary transition-colors group-hover:text-ink">
        {item?.name}
      </h3>
    </Link>
  );
}
