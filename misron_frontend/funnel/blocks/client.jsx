"use client";

import { useEffect, useRef, useState } from "react";
import { Play } from "lucide-react";
import { cx, imageSrc } from "../core/values.js";

/**
 * The few blocks that need the browser: timers, sliders, video players and
 * the sticky bar. Everything they receive is plain data so a server-rendered
 * page can hand it over.
 */

const UNITS = [
  ["days", 86400],
  ["hours", 3600],
  ["minutes", 60],
  ["seconds", 1],
];

function remainingParts(endsAt, now) {
  const end = Date.parse(endsAt);
  if (!Number.isFinite(end)) return null;
  let left = Math.max(0, Math.floor((end - now) / 1000));
  const parts = {};
  for (const [unit, size] of UNITS) {
    parts[unit] = Math.floor(left / size);
    left -= parts[unit] * size;
  }
  parts.done = end <= now;
  return parts;
}

/**
 * Counts down to a fixed moment the merchant set. It renders dashes on the
 * server and starts on the client, so the first paint never shows a time
 * that is already wrong.
 */
export function CountdownClock({ endsAt, labels, expiredAction, expiredMessage, editing }) {
  const [now, setNow] = useState(null);

  useEffect(() => {
    const tick = () => setNow(Date.now());
    const first = setTimeout(tick, 0);
    const timer = setInterval(tick, 1000);
    return () => {
      clearTimeout(first);
      clearInterval(timer);
    };
  }, []);

  const parts = now === null ? null : remainingParts(endsAt, now);

  if (!endsAt) {
    return editing ? <p className="m-0 text-center text-[14px] text-ink-tertiary">Set the end date and time in the settings panel.</p> : null;
  }

  if (parts?.done) {
    if (expiredAction === "message" && expiredMessage) {
      return <p className="m-0 text-center text-[16px] font-semibold">{expiredMessage}</p>;
    }
    return editing ? <p className="m-0 text-center text-[14px] text-ink-tertiary">This countdown has ended and is hidden on the page.</p> : null;
  }

  return (
    <div className="flex justify-center gap-2 @3xl:gap-3" role="timer" aria-live="off">
      {UNITS.map(([unit]) => (
        <div key={unit} className="flex min-w-[64px] flex-col items-center rounded-[10px] bg-[var(--fn-accent)] px-2 py-2 text-white @3xl:min-w-[80px] @3xl:py-3">
          <span className="text-[26px] font-bold leading-none tabular-nums @3xl:text-[34px]">
            {parts ? String(parts[unit]).padStart(2, "0") : "--"}
          </span>
          <span className="mt-1 text-[12px] opacity-90 @3xl:text-[13px]">{labels[unit]}</span>
        </div>
      ))}
    </div>
  );
}

export function BeforeAfter({ before, after, beforeLabel, afterLabel, initial, editing }) {
  const [position, setPosition] = useState(Number.isFinite(initial) ? initial : 50);
  const beforeSrc = imageSrc(before);
  const afterSrc = imageSrc(after);

  if (!beforeSrc || !afterSrc) {
    return editing ? (
      <div className="flex aspect-[4/3] w-full items-center justify-center rounded-[10px] bg-canvas-sunken text-[13px] text-ink-tertiary">
        Choose a before and an after image
      </div>
    ) : null;
  }

  return (
    <div className="relative w-full select-none overflow-hidden rounded-[10px]">
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src={afterSrc} alt={after?.alt ?? afterLabel ?? ""} className="block h-auto w-full" draggable={false} loading="lazy" />
      <div className="absolute inset-0 overflow-hidden" style={{ width: `${position}%` }}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={beforeSrc}
          alt={before?.alt ?? beforeLabel ?? ""}
          className="block h-full max-w-none object-cover"
          style={{ width: `${10000 / Math.max(position, 1)}%` }}
          draggable={false}
          loading="lazy"
        />
      </div>
      <div className="pointer-events-none absolute inset-y-0 w-0.5 bg-white shadow-[0_0_0_1px_rgba(0,0,0,.15)]" style={{ left: `${position}%` }} />
      {beforeLabel ? <span className="absolute left-2 top-2 rounded bg-black/60 px-2 py-0.5 text-[12px] text-white">{beforeLabel}</span> : null}
      {afterLabel ? <span className="absolute right-2 top-2 rounded bg-black/60 px-2 py-0.5 text-[12px] text-white">{afterLabel}</span> : null}
      <input
        type="range"
        min={0}
        max={100}
        value={position}
        onChange={(event) => setPosition(Number(event.target.value))}
        aria-label={`${beforeLabel || "Before"} / ${afterLabel || "After"}`}
        className="absolute inset-0 h-full w-full cursor-ew-resize opacity-0"
        disabled={editing}
      />
    </div>
  );
}

/**
 * Shows a light preview first and loads the real player only when tapped,
 * so a page with a video stays fast on mobile data.
 */
export function VideoPlayer({ source, poster, title, editing }) {
  const [playing, setPlaying] = useState(false);
  const posterSrc = imageSrc(poster) ?? source?.thumbnail ?? null;

  if (!source) {
    return editing ? (
      <div className="flex aspect-video w-full items-center justify-center rounded-[10px] bg-canvas-sunken text-[13px] text-ink-tertiary">
        Paste a YouTube, Facebook or Vimeo link
      </div>
    ) : null;
  }

  if (source.kind === "file") {
    return (
      <video className="block aspect-video w-full rounded-[10px] bg-black" src={source.src} poster={posterSrc ?? undefined} controls preload="none" playsInline />
    );
  }

  if (playing && !editing) {
    return (
      <iframe
        className="block aspect-video w-full rounded-[10px] border-0 bg-black"
        src={`${source.src}${source.src.includes("?") ? "&" : "?"}autoplay=1`}
        title={title || "Video"}
        allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
        allowFullScreen
        loading="lazy"
      />
    );
  }

  return (
    <button
      type="button"
      onClick={() => !editing && setPlaying(true)}
      className="group relative block aspect-video w-full overflow-hidden rounded-[10px] bg-[#111] p-0"
      aria-label={title ? `Play: ${title}` : "Play video"}
    >
      {posterSrc ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={posterSrc} alt="" className="absolute inset-0 h-full w-full object-cover opacity-90" loading="lazy" />
      ) : null}
      <span className="absolute left-1/2 top-1/2 flex size-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-[var(--fn-accent)] text-white shadow-lg transition-transform group-hover:scale-105">
        <Play size={28} fill="currentColor" aria-hidden="true" />
      </span>
    </button>
  );
}

/**
 * Hides itself while the checkout form is on screen, so it never covers the
 * fields a customer is filling in.
 */
export function StickyBarShell({ hideNearCheckout, className, children }) {
  const [hidden, setHidden] = useState(false);
  const observed = useRef([]);

  useEffect(() => {
    if (!hideNearCheckout || typeof IntersectionObserver === "undefined") return undefined;
    const targets = Array.from(document.querySelectorAll("[data-fn-checkout]"));
    if (targets.length === 0) return undefined;
    const visible = new Set();
    const observer = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) visible.add(entry.target);
        else visible.delete(entry.target);
      }
      setHidden(visible.size > 0);
    });
    targets.forEach((target) => observer.observe(target));
    observed.current = targets;
    return () => observer.disconnect();
  }, [hideNearCheckout]);

  return (
    <div className={cx(className, "transition-transform duration-200", hidden && "translate-y-[150%]")} aria-hidden={hidden || undefined}>
      {children}
    </div>
  );
}
