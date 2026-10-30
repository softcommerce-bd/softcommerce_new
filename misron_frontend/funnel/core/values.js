// @ts-check

/**
 * Small helpers shared by the page renderer and the editor.
 */

/** @param {...(string | false | null | undefined)} parts */
export function cx(...parts) {
  return parts.filter(Boolean).join(" ");
}

/**
 * Link targets a page may use. Mirrors the server's UrlRule so that a link the
 * editor shows is a link the server accepts; anything else renders as no link.
 * @param {unknown} url
 * @returns {string | null}
 */
export function safeHref(url) {
  if (typeof url !== "string" || url === "" || url.length > 2000) return null;
  // Whitespace and control characters are how scheme filters get bypassed.
  if (/[\x00-\x20\x7f]/.test(url)) return null;
  if (/^https?:\/\//i.test(url)) {
    try {
      const parsed = new URL(url);
      // user:password@host hides the real destination.
      return parsed.hostname && !parsed.username && !parsed.password ? url : null;
    } catch {
      return null;
    }
  }
  if (/^\/(?![/\\])/.test(url) && !url.includes("\\")) return url;
  if (/^#[A-Za-z][A-Za-z0-9_-]{0,63}$/.test(url)) return url;
  if (/^tel:\+?[0-9()-]{3,20}$/.test(url)) return url;
  if (/^mailto:[^@\s]+@[^@\s]+\.[^@\s]+$/.test(url)) return url;
  return null;
}

/**
 * Image sources: absolute http(s) or site-relative, and nothing that could
 * close a CSS url() or an attribute.
 * @param {unknown} url
 * @returns {string | null}
 */
export function safeImageSrc(url) {
  const href = safeHref(url);
  if (!href || !/^(https?:\/\/|\/(?![/\\]))/i.test(href)) return null;
  return /["'()<>\\]/.test(href) ? null : href;
}

/**
 * @param {Record<string, any> | undefined} image  { uploadId, url, alt }
 * @returns {string | null}
 */
export function imageSrc(image) {
  return image && typeof image === "object" ? safeImageSrc(image.url) : null;
}

/**
 * Value at a path such as ["items", 2, "question"].
 * @param {any} value
 * @param {Array<string | number>} path
 */
export function getIn(value, path) {
  let current = value;
  for (const key of path) {
    if (current === null || current === undefined) return undefined;
    current = current[key];
  }
  return current;
}

/**
 * Sets a value at a path on a mutable object (an Immer draft), creating
 * objects and arrays along the way.
 * @param {any} target
 * @param {Array<string | number>} path
 * @param {unknown} value
 */
export function setIn(target, path, value) {
  let current = target;
  for (let i = 0; i < path.length - 1; i++) {
    const key = path[i];
    if (current[key] === null || current[key] === undefined || typeof current[key] !== "object") {
      current[key] = typeof path[i + 1] === "number" ? [] : {};
    }
    current = current[key];
  }
  const last = path[path.length - 1];
  if (value === undefined) {
    if (Array.isArray(current) && typeof last === "number") current.splice(last, 1);
    else delete current[last];
  } else {
    current[last] = value;
  }
}

/**
 * Embed address for a video link from an allowed provider, or null.
 * @param {string | undefined} provider  youtube | facebook | vimeo | file
 * @param {unknown} url
 * @returns {{ kind: 'iframe' | 'file', src: string, thumbnail?: string } | null}
 */
export function videoSource(provider, url) {
  const href = safeHref(url);
  if (!href || !/^https?:\/\//i.test(href)) return null;

  let parsed;
  try {
    parsed = new URL(href);
  } catch {
    return null;
  }
  const host = parsed.hostname.replace(/^www\.|^m\./, "");

  // The host decides; the stored provider is only a hint for the editor.
  if (host === "youtube.com" || host === "youtu.be" || host === "youtube-nocookie.com") {
    let id = null;
    if (host === "youtu.be") id = parsed.pathname.slice(1);
    else if (parsed.pathname.startsWith("/watch")) id = parsed.searchParams.get("v");
    else {
      const match = /^\/(?:embed|shorts|live)\/([^/?#]+)/.exec(parsed.pathname);
      id = match ? match[1] : null;
    }
    if (!id || !/^[A-Za-z0-9_-]{6,20}$/.test(id)) return null;
    return {
      kind: "iframe",
      src: `https://www.youtube-nocookie.com/embed/${id}?rel=0&playsinline=1`,
      thumbnail: `https://i.ytimg.com/vi/${id}/hqdefault.jpg`,
    };
  }

  if (host === "vimeo.com" || host === "player.vimeo.com") {
    const match = /(\d{6,12})/.exec(parsed.pathname);
    return match ? { kind: "iframe", src: `https://player.vimeo.com/video/${match[1]}` } : null;
  }

  if (host === "facebook.com" || host === "fb.watch") {
    return {
      kind: "iframe",
      src: `https://www.facebook.com/plugins/video.php?href=${encodeURIComponent(href)}&show_text=false`,
    };
  }

  if (/\.(mp4|webm|ogg)(\?|$)/i.test(parsed.pathname)) return { kind: "file", src: href };
  return null;
}

/**
 * wa.me link for a phone number, with Bangladesh's country code added to
 * local numbers ("01712…" becomes "8801712…").
 * @param {unknown} phone
 * @param {unknown} message
 */
export function whatsappHref(phone, message) {
  if (typeof phone !== "string") return null;
  let digits = phone.replace(/\D/g, "");
  if (digits.startsWith("0") && digits.length === 11) digits = `88${digits}`;
  if (digits.length < 8 || digits.length > 15) return null;
  const text = typeof message === "string" && message.trim() ? `?text=${encodeURIComponent(message.trim())}` : "";
  return `https://wa.me/${digits}${text}`;
}
