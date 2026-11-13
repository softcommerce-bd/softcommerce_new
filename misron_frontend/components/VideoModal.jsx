"use client";

/**
 * Shoppable reels.
 *
 * Opens full screen on the video that was tapped; scrolling (swipe, wheel, arrow keys or the
 * up/down buttons) moves to the next and previous product videos, one at a time, like Facebook
 * or Instagram reels. Only the video on screen plays; the neighbours load just enough to start
 * instantly. Every reel carries its product, with Cart and Order buttons so a shopper can buy
 * without leaving the video.
 *
 * The open/close state is still the `videoModal` slice (`openVideoModal(item)` /
 * `closeVideoModal()`), so the home rail, the floating player and the product page all keep
 * working. The list of reels is the shoppable-video list from `/buy-watch-products`; the tapped
 * product is placed first if it is not in that list.
 */

import { memo, useCallback, useEffect, useRef, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import Link from "next/link";
import { toast } from "react-hot-toast";
import {
  ChevronDown,
  ChevronUp,
  Heart,
  ImageOff,
  Play,
  Share2,
  ShoppingBag,
  Volume2,
  VolumeX,
  X,
  Zap,
} from "lucide-react";

import { closeVideoModal } from "@/store/slices/videoModalSlice";
import { useQuickAdd } from "@/store/hooks/useQuickAdd";
import { useCheckoutModal } from "@/app/components/checkout/CheckoutModalProvider";
import { tkText } from "@/utils/currency";
import WishlistButton from "@/app/components/WishlistButton";

/** Uploaded video URLs may carry a stray "/public" segment. */
const cleanVideoUrl = (url) => {
  if (!url) return null;
  return url.replace("/public/uploads/videos/", "/uploads/videos/").replace("/public/", "/");
};

const isUnorderable = (item) => {
  const stock = Number(item.recent_stock ?? item.stock ?? 0);
  const onOrder =
    item.can_order === true &&
    (item.stock_state === "available_on_order" || item.stock_state === "preorder");
  // Older list payloads carry no selling state: fall back to the stock number alone.
  return stock <= 0 && !onOrder && item.stock_state !== undefined
    ? true
    : stock <= 0 && !onOrder && item.stock_state === undefined && item.recent_stock !== undefined;
};

/** Liked reels live in this browser only. */
const LIKES_KEY = "reel_likes_v1";
const readLikes = () => {
  try {
    return JSON.parse(localStorage.getItem(LIKES_KEY) || "[]");
  } catch {
    return [];
  }
};

/** A random key this browser keeps, so guests can like once per video. */
const visitorKey = () => {
  try {
    let key = localStorage.getItem("reel_visitor_v1");
    if (!key) {
      key = (crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}${Math.random().toString(36).slice(2)}`).replace(/[^A-Za-z0-9_-]/g, "");
      key = key.padEnd(16, "0").slice(0, 64);
      localStorage.setItem("reel_visitor_v1", key);
    }
    return key;
  } catch {
    return null;
  }
};

/** 1,200 -> 1.2K, like Facebook. */
const formatCount = (n) => {
  if (n >= 1e6) return `${(n / 1e6).toFixed(n >= 1e7 ? 0 : 1).replace(/\.0$/, "")}M`;
  if (n >= 1e3) return `${(n / 1e3).toFixed(n >= 1e4 ? 0 : 1).replace(/\.0$/, "")}K`;
  return String(n);
};

const Reel = memo(function Reel({ item, active, near, muted, onToggleMute, onClose, onNext, hasMore }) {
  const videoRef = useRef(null);
  const tapTimer = useRef(null);
  const lastTap = useRef(0);
  const [playing, setPlaying] = useState(true);
  const [progress, setProgress] = useState(0);
  const [failed, setFailed] = useState(false);
  // The server's answer wins when it gave one; otherwise this browser's own memory.
  const [liked, setLiked] = useState(() => item.liked ?? readLikes().includes(item.id));
  const [likes, setLikes] = useState(typeof item.likes_count === "number" ? item.likes_count : null);
  const likedRef = useRef(liked);
  const [bursts, setBursts] = useState([]);
  const { quickAdd, adding } = useQuickAdd(item);
  const { openCheckout } = useCheckoutModal();

  const src = cleanVideoUrl(item.custom_video);
  const unorderable = isUnorderable(item);

  const saveLike = useCallback(
    async (value) => {
      if (value === likedRef.current) return; // a double tap on an already liked reel keeps the like
      likedRef.current = value;
      setLiked(value);
      setLikes((n) => (n === null ? n : Math.max(0, n + (value ? 1 : -1))));
      try {
        const set = new Set(readLikes());
        if (value) set.add(item.id);
        else set.delete(item.id);
        localStorage.setItem(LIKES_KEY, JSON.stringify([...set]));
      } catch {
        // Private mode: the heart still reacts for this view.
      }
      // Ask the server to record it; it answers with the real count.
      try {
        const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/video-likes/toggle`, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({ product_id: item.id, visitor_key: visitorKey() }),
        });
        const json = await res.json();
        if (json?.success) {
          likedRef.current = !!json.liked;
          setLiked(!!json.liked);
          setLikes(Number(json.count) || 0);
        }
      } catch {
        // Offline: the like stays on this device.
      }
    },
    [item.id],
  );

  // Play only while this reel is the one on screen.
  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;
    if (active && playing) {
      const attempt = video.play();
      if (attempt?.catch) attempt.catch(() => setPlaying(false));
    } else {
      video.pause();
      if (!active) video.currentTime = 0;
    }
  }, [active, playing, near]);

  // One tap pauses or plays; a quick second tap likes, with a heart where the finger landed.
  const handleTap = (e) => {
    const now = Date.now();
    const rect = e.currentTarget.getBoundingClientRect();
    if (now - lastTap.current < 300) {
      clearTimeout(tapTimer.current);
      lastTap.current = 0;
      saveLike(true);
      const id = `${now}`;
      setBursts((list) => [...list, { id, x: e.clientX - rect.left, y: e.clientY - rect.top }]);
      setTimeout(() => setBursts((list) => list.filter((b) => b.id !== id)), 800);
      return;
    }
    lastTap.current = now;
    tapTimer.current = setTimeout(() => setPlaying((p) => !p), 300);
  };

  const handleOrder = async () => {
    const added = await quickAdd();
    if (added) {
      onClose();
      openCheckout();
    }
  };

  const handleShare = async () => {
    const url = `${window.location.origin}/product/${item.slug}`;
    try {
      if (navigator.share) await navigator.share({ title: item.name, text: `Watch ${item.name}`, url });
      else {
        await navigator.clipboard.writeText(url);
        toast.success("Link copied");
      }
    } catch {
      // Share sheet dismissed.
    }
  };

  const seek = (e) => {
    const video = videoRef.current;
    const value = parseFloat(e.target.value);
    if (video?.duration && !Number.isNaN(value)) {
      video.currentTime = (value / 100) * video.duration;
      setProgress(value);
    }
  };

  const label =
    item.stock_state === "preorder"
      ? "Preorder"
      : item.stock_state === "available_on_order"
        ? "Available on order"
        : unorderable
          ? "Out of stock"
          : null;

  const railButton =
    "flex h-11 w-11 items-center justify-center rounded-full bg-black/35 text-white backdrop-blur-sm transition-transform active:scale-90";

  return (
    <section
      data-reel
      className="relative h-full w-full snap-start snap-always overflow-hidden bg-black"
      aria-label={item.name}
    >
      {near && src && !failed ? (
        <video
          ref={videoRef}
          src={src}
          poster={item.thumbnail_image}
          className="absolute inset-0 h-full w-full object-cover"
          muted={muted}
          loop={!hasMore}
          playsInline
          preload={active ? "auto" : "metadata"}
          onTimeUpdate={(e) => {
            const v = e.currentTarget;
            if (v.duration) setProgress((v.currentTime / v.duration) * 100);
          }}
          onEnded={onNext}
          onError={() => setFailed(true)}
        />
      ) : (
        <div className="absolute inset-0 flex items-center justify-center">
          {item.thumbnail_image ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={item.thumbnail_image} alt="" className="h-full w-full object-cover opacity-70" />
          ) : (
            <ImageOff className="text-white/40" size={32} aria-hidden="true" />
          )}
        </div>
      )}

      {/* Tap layer: pause / play, double-tap to like */}
      <button
        type="button"
        onClick={handleTap}
        aria-label={playing ? "Pause" : "Play"}
        className="absolute inset-0 z-[1] cursor-default"
      />

      {failed && (
        <p className="absolute inset-x-0 top-1/2 z-[2] -translate-y-1/2 px-6 text-center text-sm text-white/80">
          This video could not be played.
        </p>
      )}

      {active && !playing && !failed && (
        <span className="pointer-events-none absolute left-1/2 top-1/2 z-[2] flex h-16 w-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm">
          <Play size={28} fill="currentColor" className="ml-1" />
        </span>
      )}

      {bursts.map((b) => (
        <Heart
          key={b.id}
          size={84}
          fill="currentColor"
          className="reel-heart pointer-events-none absolute z-[3] -ml-10 -mt-10 text-red-500 drop-shadow-lg"
          style={{ left: b.x, top: b.y }}
          aria-hidden="true"
        />
      ))}

      {/* Top and bottom shading, so the white controls read on any video */}
      <div className="pointer-events-none absolute inset-x-0 top-0 z-[2] h-28 bg-linear-to-b from-black/55 to-transparent" />
      <div className="pointer-events-none absolute inset-x-0 bottom-0 z-[2] h-72 bg-linear-to-t from-black/85 via-black/45 to-transparent" />

      {/* Action rail, like Facebook reels */}
      <div className="absolute bottom-44 right-3 z-10 flex flex-col items-center gap-4">
        <div className="flex flex-col items-center gap-1">
          <button
            type="button"
            onClick={() => saveLike(!likedRef.current)}
            aria-label={liked ? "Unlike" : "Like"}
            aria-pressed={liked}
            className={`${railButton} ${liked ? "text-red-500" : ""}`}
          >
            <Heart size={22} fill={liked ? "currentColor" : "none"} />
          </button>
          <span className="text-[11px] font-medium text-white drop-shadow">{likes ? formatCount(likes) : liked ? "Liked" : "Like"}</span>
        </div>

        <div className="flex flex-col items-center gap-1">
          <WishlistButton
            productId={item.id}
            iconSize={22}
            className={`${railButton} !border-0 !bg-black/35 !text-white hover:!bg-black/50`}
          />
          <span className="text-[11px] font-medium text-white drop-shadow">Save</span>
        </div>

        <div className="flex flex-col items-center gap-1">
          <button type="button" onClick={handleShare} aria-label="Share" className={railButton}>
            <Share2 size={21} />
          </button>
          <span className="text-[11px] font-medium text-white drop-shadow">Share</span>
        </div>

        <div className="flex flex-col items-center gap-1">
          <button
            type="button"
            onClick={onToggleMute}
            aria-label={muted ? "Turn sound on" : "Mute"}
            className={railButton}
          >
            {muted ? <VolumeX size={21} /> : <Volume2 size={21} />}
          </button>
          <span className="text-[11px] font-medium text-white drop-shadow">{muted ? "Sound" : "Mute"}</span>
        </div>
      </div>

      {/* Tagged product and buy buttons */}
      <div className="absolute inset-x-0 bottom-0 z-10 px-3 pb-[max(1.25rem,env(safe-area-inset-bottom))] pr-[4.5rem]">
        <Link
          href={`/product/${item.slug}`}
          onClick={onClose}
          className="mb-2.5 flex items-center gap-2.5 rounded-2xl bg-white/12 p-2 pr-3 backdrop-blur-md transition-colors hover:bg-white/20"
        >
          <span className="relative h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-white/10">
            {item.thumbnail_image && (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={item.thumbnail_image} alt="" className="h-full w-full object-cover" />
            )}
          </span>
          <span className="min-w-0 flex-1">
            <span className="line-clamp-1 block text-[13px] font-semibold leading-tight text-white">{item.name}</span>
            <span className="mt-0.5 flex items-baseline gap-2">
              <span className="text-sm font-bold text-white">{tkText(item.main_price)}</span>
              {item.stroked_price && item.stroked_price !== item.main_price && (
                <span className="text-[11px] text-white/60 line-through">{tkText(item.stroked_price)}</span>
              )}
              {label && <span className="text-[10px] font-medium text-amber-300">{label}</span>}
            </span>
          </span>
        </Link>

        <div className="flex gap-2">
          <button
            type="button"
            onClick={quickAdd}
            disabled={unorderable || adding}
            className="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-full border border-white/70 text-[13px] font-semibold text-white transition-colors hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-40"
          >
            <ShoppingBag size={15} aria-hidden="true" />
            <span>{adding ? "Adding…" : "Cart"}</span>
          </button>
          <button
            type="button"
            onClick={handleOrder}
            disabled={unorderable || adding}
            className="flex h-10 flex-[1.4] items-center justify-center gap-1.5 rounded-full bg-brand text-[13px] font-semibold text-white shadow-lg transition-colors hover:bg-brand-hover disabled:cursor-not-allowed disabled:opacity-40"
          >
            <Zap size={15} fill="currentColor" aria-hidden="true" />
            <span>Order now</span>
          </button>
        </div>
      </div>

      {/* Seek bar */}
      {active && (
        <input
          type="range"
          min="0"
          max="100"
          step="0.1"
          value={progress}
          onChange={seek}
          aria-label="Seek"
          className="reel-seek absolute inset-x-0 bottom-0 z-20 h-3 w-full cursor-pointer"
        />
      )}
    </section>
  );
});

export default function VideoModal() {
  const { isOpen, videoItem } = useSelector((state) => state.videoModal);
  const dispatch = useDispatch();
  const [list, setList] = useState([]);
  const [activeIndex, setActiveIndex] = useState(0);
  const [muted, setMuted] = useState(true);
  const [showHint, setShowHint] = useState(false);
  const scrollerRef = useRef(null);

  // First time only: a short "swipe up" cue.
  useEffect(() => {
    if (!isOpen) return;
    let seen = false;
    try {
      seen = localStorage.getItem("reel_hint_seen") === "1";
      if (!seen) localStorage.setItem("reel_hint_seen", "1");
    } catch {
      seen = true;
    }
    if (seen) return;
    const show = setTimeout(() => setShowHint(true), 0);
    const hide = setTimeout(() => setShowHint(false), 3500);
    return () => {
      clearTimeout(show);
      clearTimeout(hide);
    };
  }, [isOpen]);

  const close = useCallback(() => dispatch(closeVideoModal()), [dispatch]);

  // Reels list: the shoppable videos, with the tapped product first when it is not among them.
  useEffect(() => {
    if (!isOpen || !videoItem) return;
    let alive = true;
    (async () => {
      let videos = [];
      try {
        const key = visitorKey();
        const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/buy-watch-products${key ? `?visitor_key=${encodeURIComponent(key)}` : ""}`);
        const json = await res.json();
        if (json?.success && Array.isArray(json.data)) videos = json.data.filter((v) => v.custom_video);
      } catch {
        // Fall back to the one video that was tapped.
      }
      if (!alive) return;
      const tappedId = videoItem.id ?? videoItem.product_id;
      if (!videos.some((v) => v.id === tappedId) && videoItem.custom_video) {
        videos = [{ ...videoItem, id: tappedId }, ...videos];
      }
      const start = Math.max(0, videos.findIndex((v) => v.id === tappedId));
      setList(videos);
      setActiveIndex(start);
      // Jump to the tapped reel once the slides exist.
      requestAnimationFrame(() => {
        const scroller = scrollerRef.current;
        if (scroller) scroller.scrollTo({ top: start * scroller.clientHeight, behavior: "instant" });
      });
    })();
    return () => {
      alive = false;
    };
  }, [isOpen, videoItem]);

  // Lock the page behind the reels.
  useEffect(() => {
    if (!isOpen) return;
    const { overflow } = document.body.style;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = overflow;
    };
  }, [isOpen]);

  const goTo = useCallback(
    (index) => {
      const scroller = scrollerRef.current;
      if (!scroller || index < 0 || index >= list.length) return;
      scroller.scrollTo({ top: index * scroller.clientHeight, behavior: "smooth" });
    },
    [list.length],
  );

  // Which reel fills the screen.
  const onScroll = useCallback(() => {
    const scroller = scrollerRef.current;
    if (!scroller || !scroller.clientHeight) return;
    const index = Math.round(scroller.scrollTop / scroller.clientHeight);
    setActiveIndex((current) => (current === index ? current : index));
  }, []);

  // Keyboard: Esc closes, arrows move.
  useEffect(() => {
    if (!isOpen) return;
    const onKey = (e) => {
      if (e.key === "Escape") close();
      else if (e.key === "ArrowDown" || e.key === "PageDown") {
        e.preventDefault();
        goTo(activeIndex + 1);
      } else if (e.key === "ArrowUp" || e.key === "PageUp") {
        e.preventDefault();
        goTo(activeIndex - 1);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [isOpen, close, goTo, activeIndex]);

  if (!isOpen || !videoItem) return null;

  return (
    <div
      className="fixed inset-0 z-[1100] flex items-center justify-center bg-black/90 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      aria-label="Product videos"
    >
      {/* Click outside the phone-shaped column to close (desktop) */}
      <button type="button" onClick={close} aria-label="Close videos" className="absolute inset-0 cursor-default" />

      <div className="relative h-dvh w-full overflow-hidden bg-black sm:h-[min(92dvh,860px)] sm:w-[min(100%,calc(min(92dvh,860px)*9/16))] sm:rounded-2xl">
        <div
          ref={scrollerRef}
          onScroll={onScroll}
          className="scrollbar-hide h-full snap-y snap-mandatory overflow-y-scroll overscroll-contain"
        >
          {list.map((item, index) => (
            <div key={item.id} className="h-full w-full snap-start">
              <Reel
                item={item}
                active={index === activeIndex}
                near={Math.abs(index - activeIndex) <= 1}
                muted={muted}
                onToggleMute={() => setMuted((m) => !m)}
                onClose={close}
                onNext={() => goTo(index + 1)}
                hasMore={index < list.length - 1}
              />
            </div>
          ))}
        </div>

        {showHint && list.length > 1 && activeIndex === 0 && (
          <div className="pointer-events-none absolute inset-x-0 bottom-52 z-30 flex flex-col items-center gap-1 text-white drop-shadow">
            <ChevronUp size={26} className="reel-hint" aria-hidden="true" />
            <span className="rounded-full bg-black/45 px-3 py-1 text-xs font-medium backdrop-blur-sm">Swipe up for more</span>
          </div>
        )}

        <button
          type="button"
          onClick={close}
          aria-label="Close videos"
          className="absolute right-3 top-3 z-30 flex h-10 w-10 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm transition-colors hover:bg-black/65"
        >
          <X size={20} />
        </button>

        {list.length > 1 && (
          <span className="absolute left-3 top-4 z-30 rounded-full bg-black/45 px-2.5 py-1 text-xs font-medium text-white backdrop-blur-sm">
            {activeIndex + 1} / {list.length}
          </span>
        )}
      </div>

      {/* Up / down for mouse users */}
      {list.length > 1 && (
        <div className="absolute right-6 top-1/2 z-30 hidden -translate-y-1/2 flex-col gap-3 sm:flex">
          <button
            type="button"
            onClick={() => goTo(activeIndex - 1)}
            disabled={activeIndex === 0}
            aria-label="Previous video"
            className="flex h-11 w-11 items-center justify-center rounded-full bg-white/15 text-white transition-colors hover:bg-white/25 disabled:opacity-30"
          >
            <ChevronUp size={22} />
          </button>
          <button
            type="button"
            onClick={() => goTo(activeIndex + 1)}
            disabled={activeIndex === list.length - 1}
            aria-label="Next video"
            className="flex h-11 w-11 items-center justify-center rounded-full bg-white/15 text-white transition-colors hover:bg-white/25 disabled:opacity-30"
          >
            <ChevronDown size={22} />
          </button>
        </div>
      )}
    </div>
  );
}
