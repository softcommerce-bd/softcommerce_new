/**
 * Client for the Laravel funnel editor API (/api/v2/funnel-editor/*).
 *
 * Requests go to this app's own /api/v2 path, which next.config.mjs rewrites
 * to the backend, so the editor never makes a cross-origin call. Every
 * request after sign-in carries the editor token from the admin handoff.
 */

const BASE = "/api/v2/funnel-editor";

export class ApiError extends Error {
  constructor(status, body) {
    super(body?.message || (status === 0 ? "Could not reach the server." : `Request failed (${status})`));
    this.name = "ApiError";
    this.status = status;
    this.code = body?.code || (status === 401 ? "unauthenticated" : status === 403 ? "forbidden" : status === 0 ? "network" : null);
    this.body = body ?? {};
  }
}

/**
 * @param {{ getToken: () => string | null, onUnauthorized?: (error: ApiError) => void }} options
 */
export function createApi({ getToken, onUnauthorized }) {
  async function request(method, path, body) {
    const headers = { Accept: "application/json" };
    const token = getToken();
    if (token) headers.Authorization = `Bearer ${token}`;

    let payload;
    if (body instanceof FormData) {
      payload = body;
    } else if (body !== undefined) {
      headers["Content-Type"] = "application/json";
      payload = JSON.stringify(body);
    }

    let response;
    try {
      response = await fetch(`${BASE}${path}`, { method, headers, body: payload, cache: "no-store", credentials: "omit" });
    } catch {
      throw new ApiError(0, null);
    }

    let data = null;
    try {
      data = await response.json();
    } catch {
      data = null;
    }

    if (!response.ok || data?.result === false) {
      const error = new ApiError(response.status, data);
      if (response.status === 401 && onUnauthorized) onUnauthorized(error);
      throw error;
    }
    return data;
  }

  return {
    exchange: (code) => request("POST", "/session", { code }),
    me: () => request("GET", "/session"),
    endSession: () => request("DELETE", "/session"),
    registry: () => request("GET", "/registry"),
    funnel: (id) => request("GET", `/funnels/${id}`),
    saveDraft: (id, content, revision) => request("PUT", `/funnels/${id}/draft`, { content, revision }),
    validate: (id, content) => request("POST", `/funnels/${id}/validate`, content ? { content } : {}),
    publish: (id, label) => request("POST", `/funnels/${id}/publish`, label ? { label } : {}),
    updateDetails: (id, details) => request("PUT", `/funnels/${id}`, details),
    versions: (id) => request("GET", `/funnels/${id}/versions`),
    version: (id, versionId) => request("GET", `/funnels/${id}/versions/${versionId}`),
    rollback: (id, versionId, publish = false) => request("POST", `/funnels/${id}/versions/${versionId}/rollback`, publish ? { publish: true } : {}),
    unpublish: (id) => request("POST", `/funnels/${id}/unpublish`),
    schedule: (id, at, until, label) => request("POST", `/funnels/${id}/schedule`, { at, until: until || null, ...(label ? { label } : {}) }),
    cancelSchedule: (id) => request("POST", `/funnels/${id}/schedule/cancel`),
    setEndTime: (id, until) => request("PUT", `/funnels/${id}/end-time`, { until: until || null }),
    previewLinks: (id) => request("GET", `/funnels/${id}/preview-links`),
    createPreviewLink: (id, hours, label) => request("POST", `/funnels/${id}/preview-links`, { hours, ...(label ? { label } : {}) }),
    revokePreviewLink: (id, linkId) => request("DELETE", `/funnels/${id}/preview-links/${linkId}`),
    templates: () => request("GET", "/templates"),
    testOrderLink: (id) => request("POST", `/funnels/${id}/test-order-link`),
    savedSections: (search = "") => request("GET", `/saved-sections?search=${encodeURIComponent(search)}`),
    createSavedSection: (name, node) => request("POST", "/saved-sections", { name, node }),
    renameSavedSection: (sectionId, name) => request("PUT", `/saved-sections/${sectionId}`, { name }),
    duplicateSavedSection: (sectionId) => request("POST", `/saved-sections/${sectionId}/duplicate`),
    deleteSavedSection: (sectionId) => request("DELETE", `/saved-sections/${sectionId}`),
    fillTemplateSlot: (id, slot, productId, variant, revision) => request("POST", `/funnels/${id}/template-slots`, { slot, product_id: productId, variant: variant || null, revision }),
    media: (query = "", page = 1) => request("GET", `/media?q=${encodeURIComponent(query)}&page=${page}`),
    uploadMedia: (file) => {
      const form = new FormData();
      form.append("file", file);
      return request("POST", "/media", form);
    },
    resolveOffers: (bindings) => request("POST", "/offers/resolve", { bindings }),
    quoteOffers: (bindings, selections, districtId) => request("POST", "/offers/quote", districtId ? { bindings, selections, district_id: Number(districtId) } : { bindings, selections }),
    products: (params = {}) => {
      const search = new URLSearchParams();
      for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== null && value !== "") search.set(key, String(value));
      }
      return request("GET", `/products?${search.toString()}`);
    },
  };
}
