/** @type {import('next').NextConfig} */

// Every host comes from the environment (.env.local overrides .env).
// No backend hostname is hardcoded in this file.
const BACKEND_API_URL = process.env.BACKEND_API_URL;
const FILE_BASE_URL = process.env.NEXT_PUBLIC_FILE_BASE_URL;
const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL;


if (!BACKEND_API_URL) {
  throw new Error(
    "BACKEND_API_URL is not set. Define it in .env.local (or .env) before starting Next.js."
  );
}

// Build the allowed image hosts from whatever URLs the environment provides.
const remotePatterns = [];
const seen = new Set();

// Hosts serving uploaded images, tried in order; see lib/imageLoader.js and
// app/media.
const IMAGE_URLS = (process.env.IMAGE_URLS || "")
  .split(",")
  .map((v) => v.trim())
  .filter(Boolean);

for (const value of [BACKEND_API_URL, FILE_BASE_URL, SITE_URL, ...IMAGE_URLS]) {
  if (!value) continue;

  let url;
  try {
    url = new URL(value);
  } catch {
    throw new Error(`Invalid URL in environment configuration: ${value}`);
  }

  const key = `${url.protocol}//${url.host}`;
  if (seen.has(key)) continue;
  seen.add(key);

  remotePatterns.push({
    protocol: url.protocol.replace(":", ""),
    hostname: url.hostname,
    port: url.port,
    pathname: "/**",
  });
}

// True when one of the configured hosts is loopback or a private address.
// The image optimizer refuses those URLs outright unless it is told not to,
// so the flag below follows the environment rather than being hardcoded on.
const hasLocalHost = remotePatterns.some(({ hostname }) =>
  hostname === "localhost" ||
  hostname === "::1" ||
  /^127\./.test(hostname) ||
  /^10\./.test(hostname) ||
  /^192\.168\./.test(hostname) ||
  /^172\.(1[6-9]|2[0-9]|3[01])\./.test(hostname)
);

const nextConfig = {
  images: {
    remotePatterns,
    loaderFile: "./lib/imageLoader.js",
    dangerouslyAllowSVG: true,

    // Next 16 rejects any quality that is not listed here. The default list
    // is [75], so a component asking for quality={80} gets a 400.
    // Every value any <Image quality={...}> in the app asks for must appear
    // here, or that request answers 400 INVALID_IMAGE_OPTIMIZE_REQUEST in
    // production. 70/78/82 are used by ImageGallery, PromoBanner and
    // ProductCard1.
    qualities: [50, 70, 75, 78, 80, 82, 90, 100],

    // Next 16 blocks remote images served from loopback or private addresses
    // by default, which breaks the local backend on 127.0.0.1:8000.
    dangerouslyAllowLocalIP: hasLocalHost,
  },

  // Legacy and miscased product URLs.
  //
  // These were redirects in `proxy.js`, which had to be removed: Next 16 builds
  // that entry as a Node.js function, and the deployment host could not load
  // it ("No exports found in module /var/task/middleware.js"), so every matched
  // request answered 500. Expressed here they are handled by the router and
  // need no function at all.
  //
  // `source` matching is case-insensitive, so `/products` also catches
  // `/Products` and one entry covers both spellings.
  //
  // That insensitivity is why there is NO rule for a miscased singular
  // (`/Product/:slug`). Next compiles every `source` to a regex carrying the
  // `i` flag, so such a rule also matches the already-correct `/product/:slug`
  // and redirects it to itself — an infinite 308 loop that takes the whole
  // product section down with ERR_TOO_MANY_REDIRECTS. A character-class or
  // negative-lookahead pattern does not help either: the `i` flag is applied to
  // whatever is written here, so no `source` can tell the spellings apart.
  // Miscased singular URLs are left to 404.
  async redirects() {
    return [
      { source: "/products/:slug*", destination: "/product/:slug*", permanent: true },
      { source: "/products", destination: "/product", permanent: true },
    ];
    // `/admin` and `/login` are not carried over. `proxy.js` sent both to
    // `${NEXT_PUBLIC_SITE_URL}/login`, but there is no `/login` route in the
    // app — so `/admin` pointed at a 404 and `/login` redirected to itself.
    // Sign-in is a modal, not a page. Add an entry here if a real route lands.
  },

  // Proxy /api/v2/* to the backend defined by BACKEND_API_URL.
  async rewrites() {
    return [
      {
        source: "/api/v2/:path*",
        destination: `${BACKEND_API_URL.replace(/\/+$/, "")}/:path*`,
      },
    ];
  },

  // Disable strict mode if causing issues
  reactStrictMode: false,
  trailingSlash: false,
};

export default nextConfig;
