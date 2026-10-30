import { expect, test } from "@playwright/test";
import { cascadeFor, resolveStyle, valueSource } from "../../../funnel/core/breakpoints.js";
import { buildCss, pageVariables } from "../../../funnel/core/style.js";
import { safeHref, safeImageSrc, videoSource, whatsappHref } from "../../../funnel/core/values.js";

const page = (sections, settings) => ({ schemaVersion: 1, page: { sections, ...(settings ? { settings } : {}) } });

test("per-device styles cascade from desktop down", () => {
  const style = { desktop: { paddingTop: 40, color: "#111111" }, mobile: { paddingTop: 16 } };
  expect(cascadeFor("tablet")).toEqual(["desktop", "tablet"]);
  expect(resolveStyle(style, "tablet")).toEqual({ paddingTop: 40, color: "#111111" });
  expect(resolveStyle(style, "mobile")).toEqual({ paddingTop: 16, color: "#111111" });
  expect(valueSource(style, "mobile", "color")).toBe("desktop");
  expect(valueSource(style, "mobile", "paddingTop")).toBe("mobile");
  expect(valueSource(style, "tablet", "fontSize")).toBeNull();
});

test("css targets the right element and uses container queries", () => {
  const css = buildCss(page([
    {
      id: "sec_1",
      type: "section",
      style: { desktop: { paddingTop: 48, gap: 24 }, tablet: { paddingTop: 32 }, mobile: { paddingTop: 16 } },
      children: [{ id: "btn_1", type: "button", style: { desktop: { backgroundColor: "#ff6600", marginTop: 8, buttonSize: "lg" } } }],
    },
  ]));
  expect(css).toContain(".fn-n-sec_1{padding-top:48px}");
  expect(css).toContain(".fn-n-sec_1> .fn-inner{gap:24px}");
  expect(css).toContain(".fn-n-btn_1 .fn-btn{background-color:#ff6600;padding:15px 30px;font-size:18px}");
  expect(css).toContain(".fn-n-btn_1{margin-top:8px}");
  expect(css).toContain("@container funnel (max-width: 1023.98px){.fn-n-sec_1{padding-top:32px}}");
  expect(css).toContain("@container funnel (max-width: 767.98px){.fn-n-sec_1{padding-top:16px}}");
});

test("hidden blocks are removed on the page and faded in the editor", () => {
  const doc = page([{ id: "sec_1", type: "section", advanced: { hidden: { mobile: true } } }]);
  expect(buildCss(doc)).toBe("@container funnel (max-width: 767.98px){.fn-n-sec_1{display:none!important}}");
  expect(buildCss(doc, { editor: true })).toContain("opacity:.35");
});

test("nothing outside the allowlist reaches the stylesheet", () => {
  const css = buildCss(page([
    {
      id: "sec_1",
      type: "section",
      style: {
        desktop: {
          color: "red;}</style><script>alert(1)</script>",
          backgroundColor: "url(javascript:alert(1))",
          paddingTop: "10px;background:red",
          fontWeight: "bold",
          shadow: "0 0 0 red",
          unknownProp: 5,
          marginTop: 9999,
        },
      },
    },
    { id: "bad id;}*{display:none", type: "section", style: { desktop: { paddingTop: 10 } } },
  ]));
  expect(css).toBe(".fn-n-sec_1{margin-top:400px}");
});

test("page settings become variables only when valid", () => {
  expect(pageVariables({ accentColor: "#E8590C", maxWidth: 1200, fontFamily: "serif", textColor: "blue" })).toEqual({
    "--fn-accent": "#E8590C",
    "--fn-max": "1200px",
    "--fn-font": 'var(--font-cormorant), Georgia, "Noto Serif Bengali", serif',
  });
});

test("links and image sources are allowlisted", () => {
  for (const ok of ["https://misron.com/a?b=1", "/funnel/eid", "#order", "tel:+8801711000000", "mailto:hi@misron.com"]) {
    expect(safeHref(ok)).toBe(ok);
  }
  for (const bad of ["javascript:alert(1)", "JAVASCRIPT:x", "java\tscript:x", "data:text/html,x", "//evil.test", "/\\evil", "https://u@evil.test"]) {
    expect(safeHref(bad)).toBeNull();
  }
  expect(safeImageSrc("#anchor")).toBeNull();
  expect(safeImageSrc("https://cdn.test/a.jpg")).toBe("https://cdn.test/a.jpg");
  expect(safeImageSrc('https://cdn.test/a.jpg")')).toBeNull();
});

test("video links resolve to privacy-friendly embeds", () => {
  expect(videoSource("youtube", "https://www.youtube.com/watch?v=dQw4w9WgXcQ")).toEqual({
    kind: "iframe",
    src: "https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&playsinline=1",
    thumbnail: "https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg",
  });
  expect(videoSource(undefined, "https://youtu.be/dQw4w9WgXcQ")?.src).toContain("/embed/dQw4w9WgXcQ");
  expect(videoSource("youtube", "https://www.youtube.com/shorts/abcdefghijk")?.src).toContain("/embed/abcdefghijk");
  expect(videoSource("vimeo", "https://vimeo.com/123456789")?.src).toBe("https://player.vimeo.com/video/123456789");
  expect(videoSource("file", "https://cdn.test/demo.mp4")).toEqual({ kind: "file", src: "https://cdn.test/demo.mp4" });
  expect(videoSource("youtube", "javascript:alert(1)")).toBeNull();
  expect(videoSource("youtube", "https://evil.test/watch?v=dQw4w9WgXcQ")).toBeNull();
});

test("whatsapp links add the Bangladesh country code", () => {
  expect(whatsappHref("01711-000000", "অর্ডার করতে চাই")).toBe(`https://wa.me/8801711000000?text=${encodeURIComponent("অর্ডার করতে চাই")}`);
  expect(whatsappHref("+8801711000000", "")).toBe("https://wa.me/8801711000000");
  expect(whatsappHref("123", "")).toBeNull();
});
