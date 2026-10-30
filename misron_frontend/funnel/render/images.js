import imageLoader from "../../lib/imageLoader.js";

/**
 * Responsive product photos: the catalogue serves one full-size file per photo,
 * so cards and galleries ask the store's own image optimizer (lib/imageLoader.js,
 * the same one the storefront's next/image uses) for the width the screen needs.
 *
 * Widths are ones the optimizer accepts by default (Next's image and device sizes).
 */
const WIDTHS = [128, 256, 384, 640, 828, 1080];

/**
 * @param {string | null | undefined} src
 * @param {number} largest  the widest the photo is ever drawn, in CSS pixels × 2 for sharp screens
 * @returns {{ src?: string, srcSet?: string }}
 */
export function responsiveImage(src, largest = 1080) {
  if (!src) return {};
  // Inline images and vector files gain nothing from the optimizer.
  if (src.startsWith("data:") || src.startsWith("blob:") || /\.svg(\?|$)/i.test(src)) return { src };
  const widths = WIDTHS.filter((width) => width <= largest);
  if (widths.length === 0) widths.push(WIDTHS[0]);
  return {
    src: imageLoader({ src, width: widths[widths.length - 1] }),
    srcSet: widths.map((width) => `${imageLoader({ src, width })} ${width}w`).join(", "),
  };
}
