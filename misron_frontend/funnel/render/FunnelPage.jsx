import { buildCss, pageVariables } from "../core/style.js";
import { countByType, sectionsOf } from "../core/tree.js";
import { CommerceProvider } from "../commerce/CommerceProvider.jsx";
import { cx } from "../core/values.js";
import { NodeView, PUBLIC_CONTEXT } from "./parts.jsx";

/**
 * A complete funnel page from its document. Used by the public page and, with
 * an editor context, by the editor canvas — one renderer for both.
 *
 * The root is a CSS container named "funnel": blocks and per-device styles
 * respond to the page's own width, not the browser window's, which is what
 * lets the editor preview tablet and mobile layouts faithfully.
 */
export default function FunnelPage({ document, ctx = PUBLIC_CONTEXT, className, commerce }) {
  const settings = document?.page?.settings ?? {};
  const sections = sectionsOf(document);
  const counts = countByType(document);
  const css = buildCss(document, { editor: ctx.mode === "editor" });

  // Floating bars must not cover the end of the page on the live site.
  const hasStickyBar = (counts.sticky_cta ?? 0) > 0 && ctx.mode !== "editor";

  const page = (
    <div
      className={cx(
        "fn-root @container/funnel relative min-h-full w-full bg-[var(--fn-bg,#ffffff)] text-[color:var(--fn-text,var(--ink))] [font-family:var(--fn-font,var(--font-sans))]",
        hasStickyBar && "pb-24",
        className,
      )}
      style={{ "--fn-accent": "var(--brand)", ...pageVariables(settings) }}
      lang={settings.language === "en" ? "en" : "bn"}
    >
      {css ? <style dangerouslySetInnerHTML={{ __html: css }} /> : null}
      {sections.map((node) => (
        <NodeView key={node.id} node={node} ctx={ctx} />
      ))}
    </div>
  );

  // The editor wraps its whole workspace in a CommerceProvider (the settings
  // panel reads the same live offers); the public page passes an adapter here.
  return commerce ? (
    <CommerceProvider document={document} adapter={commerce} interactive={ctx.mode !== "editor"}>
      {page}
    </CommerceProvider>
  ) : (
    page
  );
}
