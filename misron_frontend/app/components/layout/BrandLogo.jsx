"use client";

/**
 * The brand mark for the header and the menu drawer.
 *
 * Shows the admin's uploaded logo when it can actually be read at header size,
 * and the typeset wordmark otherwise. The upload is first trimmed of any empty
 * margin: logos are often exported on a square canvas with the mark floating in
 * the middle, which scaled to the bar's height leaves the mark a few pixels
 * tall. Once trimmed, a logo is only used when it is clearly wider than tall.
 *
 * Trimming draws the image to a canvas, which needs a same-origin file, so
 * uploads are read through the `/media` route. A logo from anywhere else is
 * shown untrimmed, under the same width-to-height test.
 *
 * While the logo is still being fetched the wordmark is rendered invisibly, so
 * the bar keeps its width and nothing shifts when the decision is made.
 */

import { useEffect, useState } from "react";

const BRAND_NAME = process.env.NEXT_PUBLIC_APP_NAME || "Misron";

/** Narrowest width-to-height ratio at which an uploaded logo is shown. */
const MIN_ASPECT = 1.8;

/** Longest side the trim scan works at; larger uploads are scanned scaled down. */
const SCAN_SIZE = 1200;

/** A pixel counts as ink when it is visible and not near-white. */
const isInk = (data, i) =>
  data[i + 3] > 12 && !(data[i] > 245 && data[i + 1] > 245 && data[i + 2] > 245);

/** Same-origin URL the canvas can read, or null when the file lives elsewhere. */
function readableUrl(url) {
  try {
    const parsed = new URL(url, window.location.href);
    if (parsed.pathname.startsWith("/uploads/")) return `/media${parsed.pathname}${parsed.search}`;
    return parsed.origin === window.location.origin ? parsed.href : null;
  } catch {
    return null;
  }
}

/** Data URL of `url` cropped to its visible content, or null when there is
 *  nothing to crop or the image cannot be read. */
async function trimLogo(url) {
  const source = readableUrl(url);
  if (!source) return null;

  try {
    const img = new Image();
    img.src = source;
    await img.decode();

    const { naturalWidth: width, naturalHeight: height } = img;
    const scale = Math.min(1, SCAN_SIZE / Math.max(width, height));
    const sw = Math.max(1, Math.round(width * scale));
    const sh = Math.max(1, Math.round(height * scale));

    const scan = document.createElement("canvas");
    scan.width = sw;
    scan.height = sh;
    const scanCtx = scan.getContext("2d", { willReadFrequently: true });
    scanCtx.drawImage(img, 0, 0, sw, sh);
    const { data } = scanCtx.getImageData(0, 0, sw, sh);

    let left = sw;
    let top = sh;
    let right = -1;
    let bottom = -1;
    for (let y = 0; y < sh; y++) {
      for (let x = 0; x < sw; x++) {
        if (!isInk(data, (y * sw + x) * 4)) continue;
        if (x < left) left = x;
        if (x > right) right = x;
        if (y < top) top = y;
        if (y > bottom) bottom = y;
      }
    }
    if (right < 0) return null;

    // Back to the original's pixels, with a small margin so antialiased edges
    // are not clipped.
    const pad = Math.max(2, Math.round(Math.max(width, height) * 0.0125));
    const x0 = Math.max(0, Math.floor(left / scale) - pad);
    const y0 = Math.max(0, Math.floor(top / scale) - pad);
    const x1 = Math.min(width, Math.ceil((right + 1) / scale) + pad);
    const y1 = Math.min(height, Math.ceil((bottom + 1) / scale) + pad);
    const cropWidth = x1 - x0;
    const cropHeight = y1 - y0;

    // Already tight: keep the original file and its caching.
    if (cropWidth >= width - 2 * pad && cropHeight >= height - 2 * pad) return null;

    const out = document.createElement("canvas");
    out.width = cropWidth;
    out.height = cropHeight;
    out.getContext("2d").drawImage(img, x0, y0, cropWidth, cropHeight, 0, 0, cropWidth, cropHeight);
    return out.toDataURL("image/png");
  } catch {
    return null;
  }
}

// One trim per logo URL for the page's lifetime: the header and the menu drawer
// both mount a BrandLogo and would otherwise scan the same file twice.
const trimmed = new Map();

function trimOnce(url) {
  if (!trimmed.has(url)) trimmed.set(url, trimLogo(url));
  return trimmed.get(url);
}

export default function BrandLogo({
  src,
  pending = false,
  imageClassName = "",
  wordmarkClassName = "",
}) {
  // `src` can be a static import object while nothing has been fetched; only a
  // URL string is worth probing.
  const url = typeof src === "string" && src ? src : null;

  // The trim result for `url`: `{ url, src }`, with `src` null when the file
  // was left as is. Null until the attempt for the current `url` has finished.
  const [trim, setTrim] = useState(null);
  useEffect(() => {
    if (!url) return undefined;
    let live = true;
    trimOnce(url).then((result) => {
      if (live) setTrim({ url, src: result });
    });
    return () => {
      live = false;
    };
  }, [url]);

  const shown = url && trim?.url === url ? trim.src || url : null;

  // "pending" until the image reports its size, then "image" or "wordmark".
  const [decision, setDecision] = useState({ url: null, mode: "pending" });
  const mode = !url ? "wordmark" : decision.url === shown && shown ? decision.mode : "pending";

  const decide = (nextMode) => setDecision({ url: shown, mode: nextMode });

  return (
    <span className="relative inline-flex items-center">
      {shown && mode !== "wordmark" && (
        // eslint-disable-next-line @next/next/no-img-element -- admin-hosted, arbitrary origin
        <img
          src={shown}
          alt={mode === "image" ? BRAND_NAME : ""}
          onLoad={(e) => {
            const { naturalWidth: w, naturalHeight: h } = e.currentTarget;
            decide(h > 0 && w / h >= MIN_ASPECT ? "image" : "wordmark");
          }}
          onError={() => decide("wordmark")}
          className={
            mode === "image"
              ? `block w-auto object-contain ${imageClassName}`
              : "pointer-events-none absolute size-0 opacity-0"
          }
        />
      )}

      {mode !== "image" && (
        <span
          className={`type-wordmark whitespace-nowrap text-stone-900 transition-colors duration-150 group-hover/logo:text-amber-800 ${wordmarkClassName} ${
            pending || mode === "pending" ? "invisible" : ""
          }`}
        >
          {BRAND_NAME}
        </span>
      )}
    </span>
  );
}
