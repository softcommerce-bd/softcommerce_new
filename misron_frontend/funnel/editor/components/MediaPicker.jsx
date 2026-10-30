"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { ImagePlus, Search, Upload } from "lucide-react";
import Modal from "@/app/components/ui/Modal";
import { useEditor } from "../useEditorState.js";

/**
 * Picks an image from the shared media library (the same files as
 * "Uploaded Files" in the admin), or uploads a new one into it.
 */
export default function MediaPicker({ open, onClose, onSelect }) {
  const { api } = useEditor();
  const [query, setQuery] = useState("");
  const [items, setItems] = useState([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState(null);
  const fileRef = useRef(null);

  const load = useCallback(
    async (term, pageNumber) => {
      setLoading(true);
      setError(null);
      try {
        const result = await api.media(term, pageNumber);
        setItems((current) => (pageNumber === 1 ? result.data : [...current, ...result.data]));
        setPage(result.meta.current_page);
        setLastPage(result.meta.last_page);
      } catch (e) {
        setError(e.message);
      } finally {
        setLoading(false);
      }
    },
    [api],
  );

  useEffect(() => {
    if (!open) return undefined;
    const timer = setTimeout(() => load(query, 1), query ? 300 : 0);
    return () => clearTimeout(timer);
  }, [open, query, load]);

  const upload = async (file) => {
    if (!file) return;
    setUploading(true);
    setError(null);
    try {
      const result = await api.uploadMedia(file);
      onSelect({ uploadId: result.data.id, url: result.data.url, alt: result.data.name || "" });
      onClose();
    } catch (e) {
      setError(e.message);
    } finally {
      setUploading(false);
      if (fileRef.current) fileRef.current.value = "";
    }
  };

  return (
    <Modal open={open} onClose={onClose} title="Choose an image" size="full" bodyClassName="px-6 pb-6">
      <div
        className="flex flex-col gap-4"
        onDragOver={(event) => event.preventDefault()}
        onDrop={(event) => {
          event.preventDefault();
          upload(event.dataTransfer.files?.[0]);
        }}
      >
        <div className="flex flex-wrap items-center gap-3">
          <label className="relative min-w-[220px] flex-1">
            <span className="sr-only">Search images</span>
            <Search size={15} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-tertiary" aria-hidden="true" />
            <input type="search" className="field h-10 pl-9 text-[14px]" placeholder="Search by file name" value={query} onChange={(e) => setQuery(e.target.value)} />
          </label>
          <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp,image/gif" className="hidden" onChange={(e) => upload(e.target.files?.[0])} data-testid="media-upload-input" />
          <button type="button" className="btn btn-md btn-primary" onClick={() => fileRef.current?.click()} disabled={uploading}>
            <Upload size={16} aria-hidden="true" /> {uploading ? "Uploading…" : "Upload image"}
          </button>
        </div>
        <p className="m-0 text-[12px] text-ink-tertiary">JPG, PNG, WebP or GIF. Large photos are resized automatically. You can also drop a file here.</p>

        {error ? <p className="m-0 rounded-[6px] bg-danger-tint px-3 py-2 text-[13px] text-danger" role="alert">{error}</p> : null}

        {items.length === 0 && !loading ? (
          <div className="flex flex-col items-center gap-2 py-12 text-center text-ink-tertiary">
            <ImagePlus size={32} aria-hidden="true" />
            <p className="m-0 text-[14px]">{query ? "No image matches your search." : "No images yet. Upload the first one."}</p>
          </div>
        ) : (
          <div className="grid max-h-[55vh] grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-4 lg:grid-cols-6">
            {items.map((item) => (
              <button
                key={item.id}
                type="button"
                className="group flex flex-col gap-1 rounded-[6px] border border-hairline p-1 text-left hover:border-[#1f6feb] focus-visible:border-[#1f6feb]"
                onClick={() => {
                  onSelect({ uploadId: item.id, url: item.url, alt: item.name || "" });
                  onClose();
                }}
                title={item.name}
              >
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={item.url} alt="" className="aspect-square w-full rounded-[4px] bg-canvas-sunken object-cover" loading="lazy" />
                <span className="truncate px-1 text-[11px] text-ink-secondary">{item.name}</span>
              </button>
            ))}
          </div>
        )}

        {page < lastPage ? (
          <button type="button" className="btn btn-sm btn-secondary self-center" onClick={() => load(query, page + 1)} disabled={loading}>
            {loading ? "Loading…" : "Load more"}
          </button>
        ) : null}
      </div>
    </Modal>
  );
}
