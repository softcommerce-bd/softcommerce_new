import { Loader2 } from "lucide-react";

/**
 * The app's only spinner.
 *
 * Replaces three competing implementations: a `border-b-2 border-indigo-600`
 * ring, a `border-main` ring, and an inline animated SVG duplicated across
 * several buttons.
 *
 * A spinner is for waits with no known shape. Where the shape is known — a
 * product grid, a table, a card — use Skeleton instead.
 */

const SIZES = { xs: 14, sm: 16, md: 20, lg: 28, xl: 36 };

export default function Spinner({ size = "md", className = "", label = "Loading" }) {
  return (
    <Loader2
      size={SIZES[size] ?? SIZES.md}
      className={["animate-spin text-ink-tertiary", className].join(" ")}
      role="status"
      aria-label={label}
    />
  );
}

/** Centred spinner for a whole route or panel. */
export function PageSpinner({ label = "Loading", className = "" }) {
  return (
    <div
      className={["flex min-h-[50vh] flex-col items-center justify-center gap-3", className].join(" ")}
    >
      <Spinner size="lg" label={label} />
      <p className="type-footnote text-ink-tertiary">{label}…</p>
    </div>
  );
}
