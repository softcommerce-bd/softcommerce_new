"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from "react";
import { collectBindings } from "../core/tree.js";
import { strings } from "./i18n.js";

/**
 * Live offer data and the customer's selection, shared by every product
 * block on a page.
 *
 * Prices never come from the browser's own arithmetic. Cards draw from
 * `offers` (resolved by the server) and the order summary draws from `quote`
 * (priced by the server for the current selection, a moment after each
 * change). While a quote is in flight the last answer stays on screen,
 * marked as updating, so numbers never flash to zero.
 */

const CommerceContext = createContext(null);

export function useCommerce() {
  return useContext(CommerceContext);
}

const RESOLVE_DELAY = 250;
const QUOTE_DELAY = 150;

function isMultiple(entry) {
  return entry.blockType === "product_offer_selector" && entry.props.selectionMode === "multiple";
}

/**
 * Selection a page starts with: cards flagged "preselected", else the first
 * orderable card of every block that takes a single choice. Multi-choice
 * grids start empty.
 */
function defaultSelection(entries, offers) {
  const selection = {};
  const byBlock = new Map();
  for (const entry of entries) {
    if (!byBlock.has(entry.blockId)) byBlock.set(entry.blockId, []);
    byBlock.get(entry.blockId).push(entry);
  }

  for (const list of byBlock.values()) {
    const multiple = isMultiple(list[0]);
    const flagged = list.filter((entry) => entry.binding.display?.defaultSelected);
    let chosen = flagged.length ? (multiple ? flagged : [flagged[0]]) : [];
    if (chosen.length === 0 && !multiple) {
      const available = list.find((entry) => offers?.[entry.binding.key]?.available !== false);
      chosen = available ? [available] : [];
    }
    for (const entry of chosen) selection[entry.binding.key] = { selected: true, variant: null, quantity: 1 };
  }
  return selection;
}

/**
 * @param {object} props
 * @param {object} props.document         the page document
 * @param {{ resolve: Function, quote: Function }} props.adapter
 * @param {boolean} props.interactive     whether clicks change the selection
 * @param {object} [props.initialOffers]  offers already read on the server, so cards render with the page
 */
export function CommerceProvider({ document, adapter, interactive, initialOffers = null, children }) {
  const entries = useMemo(() => collectBindings(document), [document]);
  const bindings = useMemo(() => entries.map((entry) => entry.binding), [entries]);
  // Only offers matter here: edits elsewhere on the page must not refetch.
  const bindingsKey = useMemo(() => JSON.stringify(bindings), [bindings]);
  const language = document?.page?.settings?.language === "en" ? "en" : "bn";

  const [offers, setOffers] = useState(initialOffers);
  const [warnings, setWarnings] = useState([]);
  const [error, setError] = useState(null);
  const [selection, setSelection] = useState({});
  const [quote, setQuote] = useState(null);
  const [quoting, setQuoting] = useState(false);
  // The delivery area the customer chose: with it the quote also carries the delivery charge.
  const [district, setDistrict] = useState(null);
  const bindingsRef = useRef(bindings);
  const adapterRef = useRef(adapter);
  const entriesRef = useRef(entries);
  useEffect(() => {
    bindingsRef.current = bindings;
    adapterRef.current = adapter;
    entriesRef.current = entries;
  });

  // ── Offers ──────────────────────────────────────────────────────────────
  const refresh = useCallback(async () => {
    const list = bindingsRef.current;
    if (!adapterRef.current || list.length === 0) {
      setOffers({});
      setWarnings([]);
      setError(null);
      return;
    }
    try {
      const result = await adapterRef.current.resolve(list);
      setOffers(result.offers);
      setWarnings(result.warnings ?? []);
      setError(null);
    } catch (e) {
      setError(e.body?.errors?.[0]?.message ?? e.message ?? "offers");
    }
  }, []);

  useEffect(() => {
    const timer = setTimeout(refresh, RESOLVE_DELAY);
    return () => clearTimeout(timer);
  }, [bindingsKey, refresh]);

  // Stock and prices change on the website while a page stays open; refresh
  // when the tab is shown again.
  useEffect(() => {
    const onVisible = () => {
      if (window.document.visibilityState === "visible") refresh();
    };
    window.document.addEventListener("visibilitychange", onVisible);
    return () => window.document.removeEventListener("visibilitychange", onVisible);
  }, [refresh]);

  // ── Selection ───────────────────────────────────────────────────────────
  // Rebuilt from the page's defaults whenever the set of offers or their
  // defaults change; choices the customer made are kept for offers that remain.
  const seedKey = useMemo(
    () => `${offers !== null}#${JSON.stringify(entries.map((e) => [e.binding.key, e.binding.display?.defaultSelected ?? false, e.props.selectionMode ?? null]))}`,
    [offers, entries],
  );
  const [seededFor, setSeededFor] = useState(null);
  if (offers !== null && seededFor !== seedKey) {
    setSeededFor(seedKey);
    setSelection((current) => {
      const next = defaultSelection(entries, offers);
      if (interactive) {
        for (const [key, value] of Object.entries(current)) {
          if (bindings.some((binding) => binding.key === key)) next[key] = value;
        }
      }
      return next;
    });
  }

  const select = useCallback((key) => {
    const entry = entriesRef.current.find((item) => item.binding.key === key);
    if (!entry) return;
    setSelection((current) => {
      const next = { ...current };
      const keep = { selected: true, variant: current[key]?.variant ?? null, quantity: current[key]?.quantity ?? 1 };
      if (isMultiple(entry)) {
        if (next[key]) delete next[key];
        else next[key] = keep;
      } else {
        for (const other of entriesRef.current) if (other.blockId === entry.blockId) delete next[other.binding.key];
        next[key] = keep;
      }
      return next;
    });
  }, []);

  const patch = useCallback((key, values) => {
    const entry = entriesRef.current.find((item) => item.binding.key === key);
    setSelection((current) => {
      const next = { ...current };
      // Changing a detail of an offer chooses it, like tapping the card.
      if (!next[key] && entry && !isMultiple(entry)) {
        for (const other of entriesRef.current) if (other.blockId === entry.blockId) delete next[other.binding.key];
      }
      next[key] = { selected: true, variant: null, quantity: 1, ...(next[key] ?? {}), ...values };
      return next;
    });
  }, []);

  const setVariant = useCallback((key, variant) => patch(key, { variant }), [patch]);
  const setQuantity = useCallback((key, quantity) => patch(key, { quantity: Math.max(1, Math.min(99, Math.round(quantity) || 1)) }), [patch]);

  // ── Quote ───────────────────────────────────────────────────────────────
  const selections = useMemo(
    () =>
      Object.entries(selection)
        .filter(([key]) => bindings.some((binding) => binding.key === key))
        .map(([key, value]) => ({ key, variant: value.variant, quantity: value.quantity })),
    [selection, bindings],
  );
  const selectionsKey = JSON.stringify(selections);

  useEffect(() => {
    if (!offers) return undefined;
    if (selections.length === 0) {
      const clear = setTimeout(() => setQuote(null), 0);
      return () => clearTimeout(clear);
    }
    let cancelled = false;
    const timer = setTimeout(async () => {
      setQuoting(true);
      try {
        const result = await adapterRef.current.quote(bindingsRef.current, selections, { districtId: district });
        if (!cancelled) setQuote(result);
      } catch {
        if (!cancelled) setQuote({ valid: false, errors: [{ code: "network", message: "" }], totals: null, lines: [] });
      } finally {
        if (!cancelled) setQuoting(false);
      }
    }, QUOTE_DELAY);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
    // `selections` is derived from selectionsKey; a refresh re-prices it.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectionsKey, offers, bindingsKey, district]);

  /** The offer a free-standing block (price, gallery, quantity…) follows. */
  const activeKey = useCallback(
    (preferred) => {
      if (preferred && bindings.some((binding) => binding.key === preferred)) return preferred;
      return selections[0]?.key ?? bindings[0]?.key ?? null;
    },
    [bindings, selections],
  );

  const value = useMemo(
    () => ({
      t: strings(language),
      language,
      interactive,
      offers: offers ?? {},
      ready: offers !== null,
      warnings,
      error,
      selection,
      selections,
      quote,
      quoting,
      district,
      setDistrict,
      entries,
      select,
      setVariant,
      setQuantity,
      activeKey,
      refresh,
    }),
    [language, interactive, offers, warnings, error, selection, selections, quote, quoting, district, entries, select, setVariant, setQuantity, activeKey, refresh],
  );

  return <CommerceContext.Provider value={value}>{children}</CommerceContext.Provider>;
}
