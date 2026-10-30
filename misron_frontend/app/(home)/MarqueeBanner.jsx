/**
 * Scrolling announcement band.
 *
 * The track holds four identical copies of the phrase list and slides exactly
 * one copy's width, so the loop has no visible seam. Hovering pauses it.
 *
 * Two copies were not enough: the shift is a percentage of the whole track, so
 * a track narrower than the viewport ran out of content before the animation
 * came back around and left a visible blank stretch. Four copies keep the
 * track wider than any realistic viewport even with a short phrase list.
 *
 * The phrases come from the `marquee_items` business setting, edited under
 * Website Setup → Marquee Banner in the admin panel, and `marquee_enabled`
 * hides the band entirely. Both are read on the server, so the band renders
 * with its real copy in the first paint and ships no client JavaScript. The
 * constant below is the fallback for a fresh install where neither setting
 * has been saved yet.
 *
 * This is a purely decorative band, so the whole thing is hidden from
 * assistive technology rather than read out twice.
 */

import { getBusinessSettings, getSetting, getSettingList } from "@/lib/api/settings";

const DEFAULT_MARQUEE_ITEMS = [
  "Welcome to Misron",
  "Premium cotton tees",
  "Original prints",
  "New drops every week",
  "Free delivery available",
];

/**
 * One pass of the phrase list.
 *
 * Spacing is a `gap` on the row rather than horizontal padding on each phrase.
 * Padding put its full value on both sides of every phrase and again on both
 * sides of every dot, so the visual gap was four times the number written; the
 * gap here is the actual distance between neighbours.
 *
 * The trailing `pr-6` matches that gap. It belongs to the copy rather than to
 * the track, because the track's translate is a percentage of its own width:
 * spacing added on the track would be measured into that percentage without
 * being part of a copy, and the seam would drift. Every item already renders a
 * dot after it, so this padding is the space following the copy's final dot.
 *
 * `aria-hidden` on the wrapper covers this, so the repeated copies are never
 * announced.
 */
function MarqueeContent({ items }) {
  return (
    <span className="flex shrink-0 items-center gap-6 pr-6">
      {items.map((item) => (
        <span key={item} className="flex items-center gap-6">
          <span className="type-eyebrow whitespace-nowrap text-ink-secondary">
            {item}
          </span>
          {/* Dot separators, drawn rather than typed as pipe characters. */}
          <span className="h-1 w-1 shrink-0 rounded-full bg-ink-quaternary" />
        </span>
      ))}
    </span>
  );
}

export default async function MarqueeBanner() {
  const settings = await getBusinessSettings();

  // Only an explicit "off" hides the band, so an install that has never opened
  // the settings page still shows it.
  if (getSetting(settings, "marquee_enabled") === "off") return null;

  const items = getSettingList(settings, "marquee_items", DEFAULT_MARQUEE_ITEMS);

  return (
    <div
      // No top margin: the band sits flush against the hero above it.
      className="w-full overflow-hidden border-y border-hairline bg-canvas-sunken py-3.5"
      aria-hidden="true"
    >
      <div className="marquee-track">
        <MarqueeContent items={items} />
        <MarqueeContent items={items} />
        <MarqueeContent items={items} />
        <MarqueeContent items={items} />
      </div>
    </div>
  );
}
