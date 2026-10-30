/**
 * Where the product blocks get live data. Both adapters answer the same two
 * questions; only the endpoint differs:
 *
 *   resolve(bindings)             -> { offers: { [key]: resolvedOffer }, warnings }
 *   quote(bindings, selections)   -> { valid, errors, lines, totals, fingerprint }
 *
 * The editor asks about the draft's offers through the signed-in editor API.
 * The public page asks about the published version through the public API,
 * which ignores any offers the browser sends.
 */

export function editorCommerce(api) {
  return {
    resolve: async (bindings) => {
      const result = await api.resolveOffers(bindings);
      return { offers: result.offers, warnings: result.warnings ?? [] };
    },
    quote: async (bindings, selections, extra) => {
      try {
        return await api.quoteOffers(bindings, selections, extra?.districtId);
      } catch (error) {
        // A refused selection (out of stock, over a limit) is an answer, not a failure.
        if (error.status === 422 && Array.isArray(error.body?.errors)) return { valid: false, errors: error.body.errors, totals: null, lines: [] };
        throw error;
      }
    },
  };
}

/**
 * The draft behind a secret preview link: live offers and prices for the
 * draft's products, read through the link. There is no ordering call.
 */
export function previewLinkCommerce(slug, token) {
  const base = `/api/v2/funnels/${encodeURIComponent(slug)}/preview/${encodeURIComponent(token)}`;
  return {
    resolve: async () => {
      const response = await fetch(`${base}/offers`, { headers: { Accept: "application/json" }, cache: "no-store" });
      if (!response.ok) throw new Error(`offers ${response.status}`);
      const data = await response.json();
      return { offers: data.offers, warnings: [] };
    },
    quote: async (_bindings, selections, extra) => {
      const response = await fetch(`${base}/quote`, {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json" },
        body: JSON.stringify(extra?.districtId ? { selections, district_id: Number(extra.districtId) } : { selections }),
        cache: "no-store",
      });
      const data = await response.json().catch(() => null);
      if (response.status === 422 && data?.errors) return { valid: false, errors: data.errors, totals: null, lines: [] };
      if (!response.ok) throw new Error(`quote ${response.status}`);
      return data;
    },
  };
}

export function publicCommerce(slug) {
  const base = `/api/v2/funnels/${encodeURIComponent(slug)}`;
  return {
    resolve: async () => {
      const response = await fetch(`${base}/offers`, { headers: { Accept: "application/json" }, cache: "no-store" });
      if (!response.ok) throw new Error(`offers ${response.status}`);
      const data = await response.json();
      return { offers: data.offers, warnings: [] };
    },
    quote: async (_bindings, selections, extra) => {
      const response = await fetch(`${base}/quote`, {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json" },
        body: JSON.stringify(extra?.districtId ? { selections, district_id: Number(extra.districtId) } : { selections }),
        cache: "no-store",
      });
      const data = await response.json().catch(() => null);
      if (response.status === 422 && data?.errors) return { valid: false, errors: data.errors, totals: null, lines: [] };
      if (!response.ok) throw new Error(`quote ${response.status}`);
      return data;
    },
  };
}
