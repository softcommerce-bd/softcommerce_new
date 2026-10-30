// @ts-check

import { DEVICES, QUERIES } from "./breakpoints.js";
import { sectionsOf, walk } from "./tree.js";

/**
 * Turns the per-device style settings stored on each block into CSS.
 *
 * Every rule targets the block's own class (`fn-n-<id>`) inside the "funnel"
 * container, so the output is identical in the editor canvas and on the
 * public page. Only validated tokens are written: numbers are clamped,
 * colours and keywords are checked against allowlists, and anything else is
 * dropped, so nothing typed into a page can break out of the stylesheet.
 */

const COLOR = /^(#[0-9a-f]{3,8}|transparent)$/i;

const SHADOWS = {
  none: "none",
  sm: "0 1px 2px rgba(19,19,21,.06), 0 1px 3px rgba(19,19,21,.08)",
  md: "0 4px 8px rgba(19,19,21,.06), 0 10px 24px rgba(19,19,21,.10)",
  lg: "0 8px 16px rgba(19,19,21,.08), 0 24px 48px rgba(19,19,21,.16)",
};

const BUTTON_SIZES = {
  sm: "padding:8px 14px;font-size:14px",
  md: "padding:12px 22px;font-size:16px",
  lg: "padding:15px 30px;font-size:18px",
  xl: "padding:18px 38px;font-size:20px",
};

const FLEX = { start: "flex-start", center: "center", end: "flex-end", between: "space-between", stretch: "stretch" };
const ASPECT = new Set(["auto", "1/1", "4/3", "3/4", "16/9", "9/16", "3/2", "2/3"]);

/**
 * @param {unknown} value
 * @param {number} min
 * @param {number} max
 * @returns {number | null}
 */
function num(value, min, max) {
  const n = typeof value === "number" ? value : typeof value === "string" ? Number(value) : NaN;
  if (!Number.isFinite(n)) return null;
  return Math.min(max, Math.max(min, n));
}

/** @param {unknown} value */
function color(value) {
  return typeof value === "string" && COLOR.test(value) ? value : null;
}

/**
 * @param {unknown} value
 * @param {string[]} allowed
 */
function oneOf(value, allowed) {
  const v = typeof value === "number" ? String(value) : value;
  return typeof v === "string" && allowed.includes(v) ? v : null;
}

/** @type {Record<string, (value: unknown) => string | null>} */
const PROPERTY = {
  paddingTop: (v) => px("padding-top", num(v, 0, 400)),
  paddingRight: (v) => px("padding-right", num(v, 0, 400)),
  paddingBottom: (v) => px("padding-bottom", num(v, 0, 400)),
  paddingLeft: (v) => px("padding-left", num(v, 0, 400)),
  marginTop: (v) => px("margin-top", num(v, -200, 400)),
  marginBottom: (v) => px("margin-bottom", num(v, -200, 400)),
  textAlign: (v) => decl("text-align", oneOf(v, ["left", "center", "right", "justify"])),
  fontSize: (v) => px("font-size", num(v, 8, 120)),
  fontWeight: (v) => decl("font-weight", oneOf(v, ["300", "400", "500", "600", "700", "800", "900"])),
  lineHeight: (v) => decl("line-height", num(v, 0.8, 3)),
  letterSpacing: (v) => px("letter-spacing", num(v, -5, 20)),
  color: (v) => decl("color", color(v)),
  backgroundColor: (v) => decl("background-color", color(v)),
  borderRadius: (v) => px("border-radius", num(v, 0, 200)),
  borderWidth: (v) => px("border-width", num(v, 0, 20)),
  borderStyle: (v) => decl("border-style", oneOf(v, ["none", "solid", "dashed", "dotted"])),
  borderColor: (v) => decl("border-color", color(v)),
  shadow: (v) => {
    const key = oneOf(v, Object.keys(SHADOWS));
    return key ? `box-shadow:${SHADOWS[/** @type {keyof typeof SHADOWS} */ (key)]}` : null;
  },
  maxWidth: (v) => {
    const n = num(v, 0, 2000);
    return n === null ? null : n === 0 ? "max-width:none" : `max-width:${n}px`;
  },
  widthPercent: (v) => {
    const n = num(v, 5, 100);
    return n === null ? null : `width:${n}%`;
  },
  height: (v) => px("height", num(v, 0, 1200)),
  minHeight: (v) => px("min-height", num(v, 0, 1200)),
  columns: (v) => {
    const n = num(v, 1, 6);
    return n === null ? null : `grid-template-columns:repeat(${Math.round(n)},minmax(0,1fr))`;
  },
  gap: (v) => px("gap", num(v, 0, 120)),
  aspectRatio: (v) => {
    const r = typeof v === "string" && ASPECT.has(v) ? v : null;
    return r ? `aspect-ratio:${r === "auto" ? "auto" : r.replace("/", " / ")}` : null;
  },
  objectFit: (v) => decl("object-fit", oneOf(v, ["cover", "contain"])),
  justifyContent: (v) => {
    const key = oneOf(v, ["start", "center", "end", "between"]);
    return key ? `justify-content:${FLEX[/** @type {keyof typeof FLEX} */ (key)]}` : null;
  },
  alignItems: (v) => {
    const key = oneOf(v, ["start", "center", "end", "stretch"]);
    return key ? `align-items:${FLEX[/** @type {keyof typeof FLEX} */ (key)]}` : null;
  },
  buttonSize: (v) => {
    const key = oneOf(v, Object.keys(BUTTON_SIZES));
    return key ? BUTTON_SIZES[/** @type {keyof typeof BUTTON_SIZES} */ (key)] : null;
  },
  fullWidth: (v) => (v === true ? "display:flex;width:100%" : v === false ? "width:auto" : null),

  // Product cards.
  cardBackground: (v) => decl("background-color", color(v)),
  cardBorderColor: (v) => decl("border-color", color(v)),
  cardRadius: (v) => px("border-radius", num(v, 0, 60)),
  cardShadow: (v) => {
    const key = oneOf(v, Object.keys(SHADOWS));
    return key ? `box-shadow:${SHADOWS[/** @type {keyof typeof SHADOWS} */ (key)]}` : null;
  },
  cardPadding: (v) => px("padding", num(v, 0, 60)),
  titleColor: (v) => decl("color", color(v)),
  titleSize: (v) => px("font-size", num(v, 8, 60)),
  titleWeight: (v) => decl("font-weight", oneOf(v, ["400", "500", "600", "700", "800"])),
  priceColor: (v) => decl("color", color(v)),
  priceSize: (v) => px("font-size", num(v, 8, 80)),
  savingColor: (v) => decl("color", color(v)),
  selectedColor: (v) => decl("--fn-sel", color(v)),
};

/**
 * @param {string} property
 * @param {number | null} value
 */
function px(property, value) {
  return value === null ? null : `${property}:${value}px`;
}

/**
 * @param {string} property
 * @param {string | number | null} value
 */
function decl(property, value) {
  return value === null ? null : `${property}:${value}`;
}

/**
 * Settings that mean something different on one block type.
 * @type {Record<string, Record<string, (value: unknown) => string | null>>}
 */
const TYPE_PROPERTY = {
  // Columns sit in a flex row, where a plain width is ignored.
  column: {
    widthPercent: (v) => {
      const n = num(v, 5, 100);
      return n === null ? null : `flex:0 1 ${n}%`;
    },
  },
  // A divider is a single line: its height setting is the line thickness.
  divider: {
    height: (v) => px("border-top-width", num(v, 1, 20)),
  },
};

/** Where the shared card settings land inside a product block. */
function PRODUCT_CARD_TARGETS() {
  return {
    " .fn-grid": ["columns", "gap"],
    " .fn-pcard": ["cardBackground", "cardBorderColor", "cardRadius", "cardShadow", "cardPadding"],
    " .fn-ptitle": ["titleColor", "titleSize", "titleWeight"],
    " .fn-pprice": ["priceColor", "priceSize"],
    " .fn-psave": ["savingColor"],
  };
}

/**
 * Where a setting lands for each block type. Settings not listed go on the
 * block's root element. Selectors are relative to `.fn-n-<id>`.
 * @type {Record<string, Record<string, string[]>>}
 */
export const STYLE_TARGETS = {
  section: {
    "> .fn-inner": ["gap", "maxWidth", "justifyContent", "alignItems"],
  },
  button: {
    " .fn-btn": [
      "paddingTop", "paddingRight", "paddingBottom", "paddingLeft", "fontSize", "fontWeight", "lineHeight",
      "letterSpacing", "color", "backgroundColor", "borderRadius", "borderWidth", "borderStyle", "borderColor",
      "shadow", "buttonSize", "fullWidth", "widthPercent",
    ],
  },
  badge: {
    " .fn-badge": [
      "paddingTop", "paddingRight", "paddingBottom", "paddingLeft", "fontSize", "fontWeight", "letterSpacing",
      "color", "backgroundColor", "borderRadius", "borderWidth", "borderStyle", "borderColor", "shadow",
    ],
  },
  image: {
    " .fn-img": ["borderRadius", "borderWidth", "borderStyle", "borderColor", "shadow", "aspectRatio", "objectFit", "maxWidth", "widthPercent", "height"],
  },
  icon: {
    " .fn-icon": ["fontSize", "color", "backgroundColor", "borderRadius", "paddingTop", "paddingRight", "paddingBottom", "paddingLeft", "borderWidth", "borderStyle", "borderColor"],
  },
  divider: {
    " .fn-divider-line": ["color", "height", "widthPercent", "maxWidth"],
  },
  image_gallery: { " .fn-grid": ["columns", "gap", "aspectRatio"] },
  usp_highlights: { " .fn-grid": ["columns", "gap"] },
  trust_icons: { " .fn-grid": ["columns", "gap"] },
  benefits_grid: { " .fn-grid": ["columns", "gap"] },
  testimonial_cards: { " .fn-grid": ["columns", "gap"] },
  guarantee_section: { " .fn-grid": ["columns", "gap"] },
  promo_hero: { " .fn-hero-media": ["borderRadius", "aspectRatio", "objectFit", "shadow"] },
  product_card: PRODUCT_CARD_TARGETS(),
  product_offer_selector: PRODUCT_CARD_TARGETS(),
  bundle_offer_selector: PRODUCT_CARD_TARGETS(),
  product_gallery: { " .fn-img": ["borderRadius", "shadow", "borderWidth", "borderStyle", "borderColor"] },
  product_price: { " .fn-pprice": ["priceColor", "priceSize"], " .fn-psave": ["savingColor"] },
  discount_badge: { " .fn-dbadge": ["backgroundColor", "color", "fontSize", "borderRadius", "shadow"] },
  discount_summary: {},
  image_banner: { " .fn-img": ["borderRadius", "aspectRatio", "objectFit", "shadow", "height"] },
};

/**
 * Splits one device's settings into declarations per target selector.
 * @param {string} type
 * @param {Record<string, unknown>} values
 * @returns {Map<string, string[]>}
 */
function declarationsByTarget(type, values) {
  const targets = STYLE_TARGETS[type] ?? {};
  /** @type {Map<string, string[]>} */
  const out = new Map();

  for (const [key, value] of Object.entries(values)) {
    const write = TYPE_PROPERTY[type]?.[key] ?? PROPERTY[key];
    if (!write) continue;
    const css = write(value);
    if (!css) continue;

    let selector = "";
    for (const [suffix, keys] of Object.entries(targets)) {
      if (keys.includes(key)) {
        selector = suffix;
        break;
      }
    }

    // A border width without a style draws nothing; default it to solid.
    const extra = key === "borderWidth" && values.borderStyle === undefined ? ["border-style:solid"] : [];
    const list = out.get(selector) ?? [];
    list.push(css, ...extra);
    out.set(selector, list);
  }

  return out;
}

/** @param {string} id */
export function nodeClass(id) {
  return `fn-n-${id}`;
}

/**
 * Stylesheet for a whole page.
 * @param {import("./tree.js").FunnelDocument | null | undefined} doc
 * @param {{ editor?: boolean }} [options] editor: show hidden blocks faded instead of removing them
 */
export function buildCss(doc, options = {}) {
  /** @type {string[]} */
  const base = [];
  /** @type {Record<string, string[]>} */
  const cascade = { tablet: [], mobile: [] };
  /** @type {Record<string, string[]>} */
  const only = { desktop: [], tablet: [], mobile: [] };

  walk(sectionsOf(doc), (node) => {
    if (!/^[A-Za-z0-9_-]{3,64}$/.test(node.id)) return;
    const cls = `.${nodeClass(node.id)}`;

    for (const device of DEVICES) {
      const values = node.style?.[device];
      if (!values || typeof values !== "object") continue;
      for (const [suffix, declarations] of declarationsByTarget(node.type, values)) {
        const rule = `${cls}${suffix}{${declarations.join(";")}}`;
        if (device === "desktop") base.push(rule);
        else cascade[device].push(rule);
      }
    }

    const hidden = node.advanced?.hidden;
    if (hidden && typeof hidden === "object") {
      for (const device of DEVICES) {
        if (hidden[device] !== true) continue;
        only[device].push(
          options.editor
            ? `${cls}{opacity:.35;outline:1px dashed #85858e;outline-offset:-1px}`
            : `${cls}{display:none!important}`,
        );
      }
    }
  });

  const parts = [base.join("")];
  for (const device of /** @type {const} */ (["tablet", "mobile"])) {
    if (cascade[device].length) parts.push(`@container funnel ${QUERIES.cascade[device]}{${cascade[device].join("")}}`);
  }
  for (const device of DEVICES) {
    if (only[device].length) parts.push(`@container funnel ${QUERIES.only[device]}{${only[device].join("")}}`);
  }
  return parts.join("");
}

const FONTS = {
  hind_siliguri: 'var(--font-hind), "Hind Siliguri", system-ui, sans-serif',
  system: 'system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans Bengali", sans-serif',
  serif: 'var(--font-cormorant), Georgia, "Noto Serif Bengali", serif',
};

/**
 * CSS custom properties for page-wide settings, set on the page root.
 * @param {Record<string, unknown> | undefined} settings
 * @returns {Record<string, string>}
 */
export function pageVariables(settings = {}) {
  /** @type {Record<string, string>} */
  const vars = {};
  const accent = color(settings.accentColor);
  const background = color(settings.backgroundColor);
  const text = color(settings.textColor);
  const width = num(settings.maxWidth, 320, 1600);
  const font = oneOf(settings.fontFamily, Object.keys(FONTS));

  if (accent) vars["--fn-accent"] = accent;
  if (background) vars["--fn-bg"] = background;
  if (text) vars["--fn-text"] = text;
  if (width !== null) vars["--fn-max"] = `${width}px`;
  if (font) vars["--fn-font"] = FONTS[/** @type {keyof typeof FONTS} */ (font)];
  return vars;
}
