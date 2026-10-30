// @ts-check

/**
 * Screen sizes a funnel page adapts to.
 *
 * Desktop is the base. Tablet and mobile only store what they change, and a
 * value set on a larger screen carries down to the smaller ones until it is
 * overridden. The same rules run in the editor canvas and on the public page:
 * both size the page inside a CSS container named "funnel", so a mobile
 * preview in the editor behaves exactly like a phone.
 */

/** @typedef {'desktop' | 'tablet' | 'mobile'} Device */

/** @type {Device[]} */
export const DEVICES = ["desktop", "tablet", "mobile"];

/** Canvas width the editor uses to preview each device. */
export const PREVIEW_WIDTH = { desktop: 1200, tablet: 768, mobile: 390 };

/** Smallest canvas the desktop preview may shrink to before it scales down. */
export const DESKTOP_MIN_WIDTH = 1024;

/**
 * Container query ranges. `cascade` ranges are used for style overrides
 * (desktop-first); `only` ranges target exactly one device, which is what
 * per-device visibility needs.
 */
export const QUERIES = {
  cascade: {
    tablet: "(max-width: 1023.98px)",
    mobile: "(max-width: 767.98px)",
  },
  only: {
    desktop: "(min-width: 1024px)",
    tablet: "(min-width: 768px) and (max-width: 1023.98px)",
    mobile: "(max-width: 767.98px)",
  },
};

/**
 * The devices whose stored values apply on `device`, largest first.
 * @param {Device} device
 * @returns {Device[]}
 */
export function cascadeFor(device) {
  const index = DEVICES.indexOf(device);
  return DEVICES.slice(0, index < 0 ? 1 : index + 1);
}

/**
 * Effective style values on one device: desktop values, overridden by
 * tablet, overridden by mobile, as far down as `device`.
 * @param {Record<string, Record<string, unknown>> | undefined} style
 * @param {Device} device
 * @returns {Record<string, unknown>}
 */
export function resolveStyle(style, device) {
  /** @type {Record<string, unknown>} */
  const out = {};
  if (!style) return out;
  for (const step of cascadeFor(device)) {
    Object.assign(out, style[step] || {});
  }
  return out;
}

/**
 * Where an effective value comes from: the device itself, a larger device it
 * inherits from, or nowhere (block default).
 * @param {Record<string, Record<string, unknown>> | undefined} style
 * @param {Device} device
 * @param {string} key
 * @returns {Device | null}
 */
export function valueSource(style, device, key) {
  if (!style) return null;
  const steps = cascadeFor(device).reverse();
  for (const step of steps) {
    if (style[step] && style[step][key] !== undefined) return step;
  }
  return null;
}
