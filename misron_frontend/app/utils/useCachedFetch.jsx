"use client";

import { useState, useEffect, useRef, useCallback } from "react";

/**
 * Cached GET with request deduplication.
 *
 * Two things this fixes over the previous version:
 *
 *   1. Deduplication. Three separate components ask for `/footer-data` on
 *      every page load (TopHeader, Footer, FloatingSocialBar) and two ask for
 *      `/categories` (Header, AllCategoryModal). Each hook instance kept its
 *      own `fetchInProgress` ref, so "in progress" only ever meant "in
 *      progress for me" — every caller fired its own request. In-flight
 *      promises are now shared in a module-level map keyed by URL, so
 *      concurrent callers await one request.
 *
 *   2. Respecting the cache. The old hook served the cache and then
 *      immediately refetched in the background anyway, so a valid cache saved
 *      no requests at all — it only made the first paint faster. The
 *      background refresh now runs only once the entry is past `staleAfter`
 *      (half its lifetime), so a fresh cache costs nothing.
 *
 * The returned shape — `{ data, loading, error }` — is unchanged, so no call
 * site needs to change.
 */

/** URL → in-flight promise, so concurrent callers share one request. */
const inFlight = new Map();

/** Cache reads and writes are wrapped: Safari private mode throws on both. */
const readCache = (cacheKey) => {
  try {
    const cachedData = localStorage.getItem(cacheKey);
    const cachedTime = Number(localStorage.getItem(`${cacheKey}_time`));
    if (!cachedData || !cachedTime) return null;

    return { data: JSON.parse(cachedData), time: cachedTime };
  } catch (err) {
    console.error("Cache read error:", err);
    return null;
  }
};

const writeCache = (cacheKey, data) => {
  try {
    localStorage.setItem(cacheKey, JSON.stringify(data));
    localStorage.setItem(`${cacheKey}_time`, Date.now().toString());
  } catch (storageError) {
    // Quota exceeded or storage disabled — the fetch still succeeded, so this
    // only costs the next visitor a request.
    console.error("Storage error:", storageError);
  }
};

/**
 * Fetch `url` once, sharing the promise with any concurrent caller.
 * Resolves to the response's `data`, or throws.
 */
const dedupedFetch = (url) => {
  const existing = inFlight.get(url);
  if (existing) return existing;

  const request = (async () => {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000);

    try {
      const response = await fetch(url, {
        method: "GET",
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });

      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

      const json = await response.json();

      if (json.success !== true) {
        throw new Error(json.message || "Failed to fetch data");
      }

      return json.data || [];
    } finally {
      clearTimeout(timeoutId);
      inFlight.delete(url);
    }
  })();

  inFlight.set(url, request);
  return request;
};

export default function useCachedFetch(url, cacheKey, cacheDuration = 6 * 60 * 1000) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const isMounted = useRef(true);

  useEffect(() => {
    isMounted.current = true;

    const load = async () => {
      const cached = readCache(cacheKey);
      const age = cached ? Date.now() - cached.time : Infinity;
      const isFresh = age < cacheDuration;

      // Serve the cache first whenever there is one, even a stale one — a
      // slightly old list beats an empty screen.
      if (cached) {
        if (isMounted.current) {
          setData(cached.data);
          setLoading(false);
          setError(null);
        }

        // Revalidate only once past the halfway mark of the cache's life, so
        // a page load inside that window makes no request at all.
        if (isFresh && age < cacheDuration / 2) return;
      } else if (isMounted.current) {
        setLoading(true);
      }

      try {
        const responseData = await dedupedFetch(url);

        if (isMounted.current) {
          setData(responseData);
          setError(null);
        }

        writeCache(cacheKey, responseData);
      } catch (err) {
        if (err.name === "AbortError") return;

        console.error("Fetch error:", err);

        // A background revalidation that fails leaves the cached data alone;
        // only a first load with nothing cached surfaces the error.
        if (isMounted.current && !cached) {
          setError(err.message);
        }
      } finally {
        if (isMounted.current) setLoading(false);
      }
    };

    load();

    return () => {
      isMounted.current = false;
    };
  }, [url, cacheKey, cacheDuration]);

  return { data, loading, error };
}
