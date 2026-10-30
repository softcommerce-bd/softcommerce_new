// Serves uploaded files with a fallback chain: the production file host first,
// then the local backend. The first host that answers 200 with an image wins.
//
// Hosts come from IMAGE_URLS (comma-separated, tried in order). When it is
// unset, NEXT_PUBLIC_FILE_BASE_URL is used alone.

export const dynamic = "force-dynamic";

function sources() {
  const bases = (process.env.IMAGE_URLS || process.env.NEXT_PUBLIC_FILE_BASE_URL || "").split(",");
  const seen = new Set();
  const result = [];
  for (const base of bases) {
    const clean = base?.trim().replace(/\/+$/, "");
    if (clean && !seen.has(clean)) {
      seen.add(clean);
      result.push(clean);
    }
  }
  return result;
}

export async function GET(request, { params }) {
  const { path } = await params;
  const filePath = path.map(encodeURIComponent).join("/");

  for (const base of sources()) {
    let upstream;
    try {
      upstream = await fetch(`${base}/${filePath}`, { cache: "no-store" });
    } catch {
      continue;
    }

    const type = upstream.headers.get("content-type") || "";
    if (!upstream.ok || !type.startsWith("image/")) continue;

    return new Response(upstream.body, {
      status: 200,
      headers: {
        "content-type": type,
        "cache-control": "public, max-age=3600",
      },
    });
  }

  return new Response("Not found", { status: 404 });
}
