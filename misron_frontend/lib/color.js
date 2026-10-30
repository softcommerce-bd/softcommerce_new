/**
 * Colour helpers for admin-configurable grounds.
 *
 * Any colour the admin can set has to carry readable type on top of it, and the
 * admin picker imposes no contrast rule — the footer colour, for one, is
 * currently `#ffffff`. These helpers turn an arbitrary admin value into the
 * channels a foreground ramp needs.
 */

/** Parse `#rgb`, `#rrggbb`, `rgb()` or `rgba()` into `[r, g, b]` (0-255). */
export function parseColor(value) {
  const input = typeof value === "string" ? value.trim() : "";
  if (!input) return null;

  const hex = input.replace(/^#/, "");

  if (/^[0-9a-f]{3}$/i.test(hex)) {
    return [0, 1, 2].map((i) => parseInt(hex[i] + hex[i], 16));
  }

  if (/^[0-9a-f]{6}$/i.test(hex)) {
    return [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16));
  }

  const fn = input.match(/^rgba?\(([^)]+)\)$/i);
  if (fn) {
    const parts = fn[1].split(/[\s,/]+/).filter(Boolean).slice(0, 3);
    if (parts.length === 3) {
      const channels = parts.map((part) =>
        part.endsWith("%")
          ? Math.round((parseFloat(part) / 100) * 255)
          : parseInt(part, 10),
      );
      if (channels.every((c) => Number.isFinite(c) && c >= 0 && c <= 255)) {
        return channels;
      }
    }
  }

  return null;
}

/** Relative luminance per WCAG 2.1, 0 (black) to 1 (white). */
export function relativeLuminance([r, g, b]) {
  const [rl, gl, bl] = [r, g, b].map((channel) => {
    const c = channel / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });

  return 0.2126 * rl + 0.7152 * gl + 0.0722 * bl;
}

/** WCAG 2.1 contrast ratio between two luminances, 1 to 21. */
export function contrastRatio(a, b) {
  const [hi, lo] = a > b ? [a, b] : [b, a];
  return (hi + 0.05) / (lo + 0.05);
}

/**
 * Pick the foreground that contrasts better with `background`, as the bare
 * `"r g b"` channel string a `rgb(var(--token) / a)` ramp expects.
 *
 * The two candidates are scored by actual contrast ratio rather than split on a
 * luminance threshold: the crossover between light and dark ink sits near 0.18
 * luminance, not 0.5, so a mid grey ground reads better with dark ink than the
 * midpoint suggests. The dark candidate is `#111723`, the app's own ink token,
 * so a light footer reads like the rest of the site rather than like a terminal.
 */
export function contrastInkChannels(background, { dark = "17 23 35", light = "255 255 255" } = {}) {
  const rgb = parseColor(background);
  if (!rgb) return null;

  const groundLuminance = relativeLuminance(rgb);
  const darkRgb = parseColor(`rgb(${dark})`);
  const lightRgb = parseColor(`rgb(${light})`);
  if (!darkRgb || !lightRgb) return null;

  const darkRatio = contrastRatio(groundLuminance, relativeLuminance(darkRgb));
  const lightRatio = contrastRatio(groundLuminance, relativeLuminance(lightRgb));

  return darkRatio > lightRatio ? dark : light;
}
