import { safeHref } from "../core/values.js";

/**
 * Client-side counterpart of the server's RichTextSanitizer, applied as the
 * merchant types so the canvas never renders markup the server would strip
 * (pasted scripts, inline handlers). The server check stays authoritative.
 */

const ALLOWED = new Set(["P", "BR", "STRONG", "B", "EM", "I", "U", "S", "UL", "OL", "LI", "H2", "H3", "H4", "BLOCKQUOTE", "SPAN", "A"]);
const DROP = new Set([
  "SCRIPT", "STYLE", "IFRAME", "OBJECT", "EMBED", "NOSCRIPT", "TEMPLATE", "SVG", "MATH", "TEXTAREA", "SELECT",
  "FORM", "INPUT", "BUTTON", "IMG", "VIDEO", "AUDIO", "CANVAS", "PICTURE", "SOURCE", "LINK", "META",
]);

function clean(node, doc) {
  for (const child of Array.from(node.childNodes)) {
    if (child.nodeType === Node.TEXT_NODE) continue;
    if (child.nodeType !== Node.ELEMENT_NODE) {
      child.remove();
      continue;
    }
    const tag = child.tagName;
    if (DROP.has(tag)) {
      child.remove();
      continue;
    }
    clean(child, doc);

    if (tag === "DIV") {
      // Browsers wrap new lines in <div>; keep them as paragraphs.
      const p = doc.createElement("p");
      while (child.firstChild) p.appendChild(child.firstChild);
      child.replaceWith(p);
      continue;
    }
    if (!ALLOWED.has(tag)) {
      child.replaceWith(...Array.from(child.childNodes));
      continue;
    }

    for (const attribute of Array.from(child.attributes)) {
      const keep = tag === "A" && (attribute.name === "href" || attribute.name === "target");
      if (!keep) child.removeAttribute(attribute.name);
    }
    if (tag === "A") {
      const href = safeHref(child.getAttribute("href")?.trim());
      if (href) child.setAttribute("href", href);
      else child.removeAttribute("href");
      if (child.getAttribute("target") === "_blank") child.setAttribute("rel", "noopener noreferrer nofollow");
      else child.removeAttribute("target");
    }
  }
}

export function sanitizeRichHtml(html) {
  if (!html) return "";
  const doc = new DOMParser().parseFromString(`<body>${html}</body>`, "text/html");
  clean(doc.body, doc);
  const out = doc.body.innerHTML.trim();
  return out === "<p></p>" || out === "<br>" ? "" : out;
}
