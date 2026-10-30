"use client";

import { useEffect, useRef, useState } from "react";
import { Check, Package, Search, X } from "lucide-react";
import Modal from "@/app/components/ui/Modal";
import { cx } from "../../core/values.js";
import { useEditor } from "../useEditorState.js";

/**
 * Chooses products from the store's existing catalogue (GET
 * /funnel-editor/products). It returns references — product id and, for
 * products with variations, the variation or null for "customer chooses" —
 * never copies of product data.
 */
export default function ProductPicker({ open, onClose, onConfirm, max = 1, title, confirmLabel = "Use selected", existingIds = [] }) {
  const { api } = useEditor();
  const [query, setQuery] = useState("");
  const [inStock, setInStock] = useState(false);
  const [results, setResults] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [chosen, setChosen] = useState(() => new Map());
  const latest = useRef(0);

  const search = async (page) => {
    const ticket = ++latest.current;
    setLoading(true);
    setError(null);
    try {
      const result = await api.products({ q: query, in_stock: inStock ? 1 : undefined, page, per_page: 20 });
      if (ticket !== latest.current) return;
      setResults(result.data);
      setMeta(result.meta);
    } catch (e) {
      if (ticket === latest.current) setError(e.message);
    } finally {
      if (ticket === latest.current) setLoading(false);
    }
  };

  useEffect(() => {
    if (!open) return undefined;
    const timer = setTimeout(() => search(1), query ? 300 : 0);
    return () => clearTimeout(timer);
    // search() reads the latest query and filter when the timer fires.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, query, inStock]);

  const toggle = (product, variant = null) => {
    setChosen((current) => {
      const next = new Map(current);
      const existing = next.get(product.id);
      if (existing && existing.variant === variant) {
        next.delete(product.id);
        return next;
      }
      if (max === 1) next.clear();
      if (!existing && next.size >= max) return current;
      next.set(product.id, { productId: product.id, variant, quantity: 1, name: product.name, thumbnail: product.thumbnail_url });
      return next;
    });
  };

  const full = chosen.size >= max;

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title ?? (max === 1 ? "Choose a product" : `Choose products (up to ${max})`)}
      description="Products come from your store catalogue. Prices and stock shown are today's."
      size="full"
      footer={
        <div className="flex items-center justify-between gap-3">
          <span className="text-[13px] font-medium text-ink-secondary" data-testid="picker-count">
            {chosen.size} selected
          </span>
          <div className="flex gap-2">
            <button type="button" className="btn btn-md btn-secondary" onClick={onClose}>
              Cancel
            </button>
            <button type="button" className="btn btn-md btn-primary" disabled={chosen.size === 0} onClick={() => onConfirm(Array.from(chosen.values()))} data-testid="picker-confirm">
              {confirmLabel}
            </button>
          </div>
        </div>
      }
    >
      <div className="flex flex-col gap-3" data-testid="product-picker">
        <div className="flex flex-wrap items-center gap-3">
          <label className="relative min-w-[240px] flex-1">
            <span className="sr-only">Search products</span>
            <Search size={15} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-tertiary" aria-hidden="true" />
            <input
              type="search"
              className="field h-10 pl-9 text-[14px]"
              placeholder="Search by name, SKU, product ID, category or brand"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              data-testid="picker-search"
              autoFocus
            />
          </label>
          <label className="inline-flex items-center gap-2 text-[13px] text-ink-secondary">
            <input type="checkbox" checked={inStock} onChange={(e) => setInStock(e.target.checked)} /> In stock only
          </label>
        </div>

        {chosen.size > 0 ? (
          <ul className="m-0 flex list-none flex-wrap gap-2 p-0" aria-label="Selected products">
            {Array.from(chosen.values()).map((item) => (
              <li key={item.productId} className="inline-flex items-center gap-1.5 rounded-full bg-[#e8f0fe] py-1 pl-1 pr-2 text-[12px] text-[#1f6feb]">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={item.thumbnail} alt="" className="size-5 rounded-full object-cover" />
                <span className="max-w-[160px] truncate">{item.name}</span>
                <button type="button" aria-label={`Remove ${item.name}`} onClick={() => setChosen((c) => { const n = new Map(c); n.delete(item.productId); return n; })}>
                  <X size={12} />
                </button>
              </li>
            ))}
          </ul>
        ) : null}

        {error ? <p className="m-0 rounded-[6px] bg-danger-tint px-3 py-2 text-[13px] text-danger" role="alert">{error}</p> : null}

        <ul className="m-0 flex max-h-[48vh] list-none flex-col divide-y divide-hairline overflow-y-auto rounded-[6px] border border-hairline p-0" aria-busy={loading}>
          {results.map((product) => {
            const entry = chosen.get(product.id);
            const selected = Boolean(entry);
            const blocked = !selected && full && max > 1;
            return (
              <li key={product.id} className={cx("flex flex-col gap-2 px-3 py-2.5", selected && "bg-[#f4f8ff]")} data-testid={`pick-${product.id}`}>
                <button
                  type="button"
                  className="flex items-center gap-3 text-left disabled:opacity-50"
                  onClick={() => !product.has_variants && toggle(product)}
                  disabled={blocked}
                  aria-pressed={selected}
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={product.thumbnail_url} alt="" className="size-12 shrink-0 rounded-[4px] bg-canvas-sunken object-cover" loading="lazy" />
                  <span className="flex min-w-0 flex-1 flex-col">
                    <span className="truncate text-[14px] font-medium text-ink">{product.name}</span>
                    <span className="truncate text-[12px] text-ink-tertiary">
                      #{product.id}
                      {product.sku ? ` · ${product.sku}` : ""}
                      {product.category ? ` · ${product.category}` : ""}
                      {existingIds.includes(product.id) ? " · already on this page" : ""}
                    </span>
                  </span>
                  <span className="flex shrink-0 flex-col items-end text-[12px]">
                    <span className="font-semibold text-ink">{product.price.text}</span>
                    <span className={product.stock.in_stock ? "text-success" : "text-danger"}>
                      {product.stock.in_stock ? `${product.stock.quantity} in stock` : "Out of stock"}
                    </span>
                    {!product.published ? <span className="text-warning">Not published</span> : null}
                  </span>
                  {!product.has_variants ? (
                    <span className={cx("inline-flex size-6 shrink-0 items-center justify-center rounded-full border", selected ? "border-[#1f6feb] bg-[#1f6feb] text-white" : "border-hairline-strong")}>
                      {selected ? <Check size={14} aria-hidden="true" /> : null}
                    </span>
                  ) : null}
                </button>
                {product.has_variants ? (
                  <div className="flex flex-wrap gap-1.5 pl-[60px]" role="group" aria-label={`Variations of ${product.name}`}>
                    {[{ variant: null, label: "Customer chooses", quantity: null }, ...product.variants.map((v) => ({ variant: v.variant, label: v.variant || "Default", quantity: v.quantity }))].map((chip) => {
                      const active = selected && entry.variant === chip.variant;
                      return (
                        <button
                          key={chip.label}
                          type="button"
                          disabled={blocked}
                          aria-pressed={active}
                          className={cx("rounded-full border px-2.5 py-1 text-[12px]", active ? "border-[#1f6feb] bg-[#1f6feb] text-white" : "border-hairline", chip.quantity === 0 && "opacity-50")}
                          onClick={() => toggle(product, chip.variant)}
                          data-testid={`pick-${product.id}-${chip.variant ?? "any"}`}
                        >
                          {chip.label}
                          {chip.quantity !== null ? ` · ${chip.quantity}` : ""}
                        </button>
                      );
                    })}
                  </div>
                ) : null}
              </li>
            );
          })}
          {!loading && results.length === 0 ? (
            <li className="flex flex-col items-center gap-2 py-10 text-center text-ink-tertiary">
              <Package size={28} aria-hidden="true" />
              <span className="text-[14px]">{query ? "No product matches your search." : "No products in the catalogue yet."}</span>
            </li>
          ) : null}
        </ul>

        {meta.last_page > 1 ? (
          <div className="flex items-center justify-center gap-2 text-[13px]">
            <button type="button" className="btn btn-sm btn-secondary" disabled={meta.current_page <= 1 || loading} onClick={() => search(meta.current_page - 1)}>
              Previous
            </button>
            <span>
              Page {meta.current_page} of {meta.last_page} · {meta.total} products
            </span>
            <button type="button" className="btn btn-sm btn-secondary" disabled={meta.current_page >= meta.last_page || loading} onClick={() => search(meta.current_page + 1)}>
              Next
            </button>
          </div>
        ) : null}
      </div>
    </Modal>
  );
}
