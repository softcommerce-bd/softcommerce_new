// Global next/image loader (wired in next.config.mjs).
//
// Upload URLs are rewritten to the same-origin /media route, which tries the
// production file host first and the local backend second (see
// app/media/[...path]/route.js). Every other source is passed through
// unchanged. Either way the result goes through the Next image optimizer.

const UPLOADS_PREFIX = "/uploads/";

function toMediaPath(src) {
  if (src.startsWith(UPLOADS_PREFIX)) return `/media${src}`;

  if (/^https?:\/\//i.test(src)) {
    try {
      const { pathname, search } = new URL(src);
      if (pathname.startsWith(UPLOADS_PREFIX)) return `/media${pathname}${search}`;
    } catch {
      // Malformed URL: leave it to the optimizer to reject.
    }
  }

  return src;
}

export default function imageLoader({ src, width, quality }) {
  const url = toMediaPath(src);
  return `/_next/image?url=${encodeURIComponent(url)}&w=${width}&q=${quality || 75}`;
}
