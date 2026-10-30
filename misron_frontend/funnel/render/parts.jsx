import { BLOCKS } from "../blocks/index.js";
import { nodeClass } from "../core/style.js";
import { cx, imageSrc } from "../core/values.js";

/**
 * Building blocks of the page renderer, shared by the public page and the
 * editor canvas. None of them uses hooks or browser APIs, so they render the
 * same on the server.
 *
 * The editor adds its behaviour through the render context (`ctx`) instead of
 * different components: `ctx.renderText` turns texts into inline editors,
 * `ctx.renderEmpty` draws drop zones in empty containers, and every block
 * root carries `data-fn-id` so the canvas can find it. The public page passes
 * a context without those hooks and gets plain markup.
 */

/**
 * @typedef {object} RenderContext
 * @property {'public' | 'editor' | 'preview'} mode
 * @property {(args: TextArgs) => import("react").ReactNode} [renderText]
 * @property {(node: object) => import("react").ReactNode} [renderEmpty]
 */

/**
 * @typedef {object} TextArgs
 * @property {object} node
 * @property {Array<string | number>} path   path inside node.props
 * @property {string} as
 * @property {string} [className]
 * @property {string} value
 * @property {boolean} [multiline]
 * @property {string} [placeholder]
 * @property {boolean} [optional]   may stay empty; the editor shows it only while the block is selected
 */

export const PUBLIC_CONTEXT = { mode: "public" };

/** True inside the editor (editing or previewing), where fixed elements stay in the canvas. */
export function inCanvas(ctx) {
  return ctx.mode === "editor" || ctx.mode === "preview";
}

export function NodeView({ node, ctx, parentId = null }) {
  const definition = BLOCKS[node.type];
  if (!definition) {
    return ctx.mode === "editor" ? (
      <div data-fn-id={node.id} data-fn-parent={parentId ?? "root"} className="rounded border border-dashed border-hairline-strong p-3 text-[13px] text-ink-tertiary">
        Unsupported block: {node.type}
      </div>
    ) : null;
  }

  const props = { ...definition.defaults, ...(node.props || {}) };
  const rootProps = {
    className: cx("fn-node", nodeClass(node.id)),
    id: node.advanced?.anchor || undefined,
  };
  if (ctx.mode === "editor") {
    rootProps["data-fn-id"] = node.id;
    rootProps["data-fn-parent"] = parentId ?? "root";
  }

  const Component = definition.Component;
  return <Component node={node} props={props} ctx={ctx} rootProps={rootProps} />;
}

/** Root element props with the block's own classes merged in. */
export function rootWith(rootProps, className) {
  return { ...rootProps, className: cx(rootProps.className, className) };
}

export function Children({ node, ctx }) {
  const children = Array.isArray(node.children) ? node.children : [];
  if (children.length === 0) {
    return ctx.mode === "editor" && ctx.renderEmpty ? ctx.renderEmpty(node) : null;
  }
  return children.map((child) => <NodeView key={child.id} node={child} ctx={ctx} parentId={node.id} />);
}

/**
 * A text prop. Plain text on the page; an inline editor in the canvas.
 */
export function Text({ ctx, node, path, as = "span", className, value, multiline = false, placeholder, optional = false }) {
  const text = typeof value === "string" ? value : "";
  if (ctx.mode === "editor" && ctx.renderText) {
    return ctx.renderText({ node, path, as, className, value: text, multiline, placeholder, optional });
  }
  if (text === "") return null;
  const Tag = as;
  return <Tag className={cx(className, multiline && "whitespace-pre-line")}>{text}</Tag>;
}

/**
 * Formatted text. The server sanitizes it on every save (RichTextSanitizer),
 * and published pages only ever contain saved content.
 */
export function RichHtml({ html, className }) {
  if (!html) return null;
  return <div className={className} dangerouslySetInnerHTML={{ __html: html }} />;
}

export function FunnelImage({ image, ctx, className, eager = false, fallbackAlt = "" }) {
  const src = imageSrc(image);
  if (!src) {
    return ctx.mode === "editor" ? (
      <div className={cx(className, "flex min-h-[140px] w-full items-center justify-center bg-canvas-sunken text-[13px] text-ink-tertiary")}>
        Choose an image
      </div>
    ) : null;
  }
  // A plain <img>: page images come from the shared media library on the
  // admin host and are already downscaled when uploaded.
  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      src={src}
      alt={image?.alt ?? fallbackAlt}
      className={className}
      loading={eager ? "eager" : "lazy"}
      decoding="async"
      draggable={false}
    />
  );
}
