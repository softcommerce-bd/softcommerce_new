import Link from "next/link";
import { ArrowRight } from "lucide-react";

/**
 * The heading that opens every home-page and shop section.
 *
 * Previously each section built its own — different sizes, different
 * "View all" treatments, some centred and some not. One component keeps the
 * page's vertical rhythm consistent as sections are added or reordered.
 */
export default function SectionHeader({
  eyebrow,
  title,
  description,
  href,
  linkLabel = "View all",
  align = "left",
  className = "",
  children,
}) {
  const centered = align === "center";

  return (
    <div
      className={[
        "mb-6 gap-4 md:mb-8",
        centered
          ? "flex flex-col items-center text-center"
          : "flex flex-wrap items-end justify-between",
        className,
      ].join(" ")}
    >
      <div className={centered ? "max-w-2xl" : "min-w-0"}>
        {eyebrow && (
          <p className="type-eyebrow mb-2 text-ink-tertiary">{eyebrow}</p>
        )}
        <h2 className="type-title-2 text-ink">{title}</h2>
        {description && (
          <p className="type-callout mt-2 max-w-xl text-ink-tertiary">{description}</p>
        )}
      </div>

      {children}

      {href && (
        <Link
          href={href}
          className="group inline-flex shrink-0 items-center gap-1.5 text-[0.875rem] font-semibold text-ink transition-colors hover:text-ink-secondary"
        >
          {linkLabel}
          {/* The arrow nudges forward on hover — the only motion in the row. */}
          <ArrowRight
            size={15}
            className="transition-transform duration-base ease-standard group-hover:translate-x-0.5"
            aria-hidden="true"
          />
        </Link>
      )}
    </div>
  );
}
