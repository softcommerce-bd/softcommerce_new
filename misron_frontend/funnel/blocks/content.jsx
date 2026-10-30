import { cx, safeHref } from "../core/values.js";
import { FunnelImage, RichHtml, Text, rootWith } from "../render/parts.jsx";
import { Icon } from "./icons.jsx";

/**
 * Basic content blocks: text, images, buttons and small decorations.
 */

const HEADING_SIZES = {
  h1: "text-[28px] leading-[1.15] font-bold @3xl:text-[36px] @5xl:text-[44px]",
  h2: "text-[24px] leading-[1.2] font-bold @3xl:text-[30px] @5xl:text-[34px]",
  h3: "text-[20px] leading-[1.25] font-semibold @3xl:text-[24px]",
  h4: "text-[18px] leading-[1.3] font-semibold @3xl:text-[20px]",
  h5: "text-[16px] leading-[1.35] font-semibold",
  h6: "text-[14px] leading-[1.4] font-semibold uppercase tracking-wide",
};

export function Heading({ node, props, ctx, rootProps }) {
  const level = HEADING_SIZES[props.level] ? props.level : "h2";
  const href = safeHref(props.link);
  const text = (
    <Text ctx={ctx} node={node} path={["text"]} as="span" value={props.text} placeholder="Write a heading" />
  );
  const Tag = level;
  return (
    <Tag {...rootWith(rootProps, cx("fn-heading m-0 text-balance", HEADING_SIZES[level]))}>
      {href && ctx.mode !== "editor" ? <a href={href} className="hover:underline">{text}</a> : text}
    </Tag>
  );
}

export function Paragraph({ node, props, ctx, rootProps }) {
  return (
    <div {...rootWith(rootProps, "fn-text text-[16px] leading-relaxed @3xl:text-[17px]")}>
      <Text ctx={ctx} node={node} path={["text"]} as="p" className="m-0" value={props.text} multiline placeholder="Write something" />
    </div>
  );
}

export function RichText({ props, ctx, rootProps }) {
  const empty = !props.html;
  return (
    <div
      {...rootWith(
        rootProps,
        cx(
          "fn-rich text-[16px] leading-relaxed @3xl:text-[17px]",
          "[&_p]:mb-3 [&_p:last-child]:mb-0 [&_ul]:mb-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:mb-3 [&_ol]:list-decimal [&_ol]:pl-5",
          "[&_li]:mb-1 [&_h2]:mb-2 [&_h2]:text-[24px] [&_h2]:font-bold [&_h3]:mb-2 [&_h3]:text-[20px] [&_h3]:font-semibold",
          "[&_h4]:mb-2 [&_h4]:font-semibold [&_a]:text-[var(--fn-accent)] [&_a]:underline [&_blockquote]:border-l-4",
          "[&_blockquote]:border-[var(--fn-accent)] [&_blockquote]:pl-4 [&_blockquote]:italic",
        ),
      )}
    >
      {empty && ctx.mode === "editor" ? (
        <p className="m-0 text-ink-tertiary">Formatted text — edit it in the panel on the right.</p>
      ) : (
        <RichHtml html={props.html} />
      )}
    </div>
  );
}

export function ImageBlock({ props, ctx, rootProps }) {
  const href = safeHref(props.link);
  const image = <FunnelImage image={props.image} ctx={ctx} className="fn-img inline-block h-auto max-w-full rounded-[8px] align-middle" />;
  return (
    <figure {...rootWith(rootProps, "fn-image m-0 text-center")}>
      {href && ctx.mode !== "editor" ? <a href={href}>{image}</a> : image}
      {props.caption ? <figcaption className="mt-2 text-[13px] opacity-70">{props.caption}</figcaption> : null}
    </figure>
  );
}

const BUTTON_VARIANTS = {
  solid: "bg-[var(--fn-accent)] text-white border-2 border-transparent hover:brightness-95",
  outline: "bg-transparent text-[var(--fn-accent)] border-2 border-[var(--fn-accent)] hover:bg-[color-mix(in_srgb,var(--fn-accent)_8%,transparent)]",
  ghost: "bg-transparent text-[var(--fn-accent)] border-2 border-transparent underline-offset-4 hover:underline",
};

/** Shared by the button block and the call-to-action inside larger blocks. */
export function ButtonLink({ href, target, className, children, variant = "solid", disabled }) {
  const classes = cx(
    "fn-btn inline-flex items-center justify-center gap-2 rounded-[10px] px-6 py-3 text-[16px] font-semibold leading-tight no-underline transition-[filter,background-color] duration-150",
    BUTTON_VARIANTS[variant] ?? BUTTON_VARIANTS.solid,
    className,
  );
  if (!href || disabled) {
    return <span className={classes}>{children}</span>;
  }
  const external = target === "_blank";
  return (
    <a href={href} className={classes} target={external ? "_blank" : undefined} rel={external ? "noopener noreferrer" : undefined}>
      {children}
    </a>
  );
}

export function ButtonBlock({ node, props, ctx, rootProps }) {
  return (
    <div {...rootWith(rootProps, "fn-button text-center")}>
      <ButtonLink href={safeHref(props.link)} target={props.target} variant={props.variant} disabled={ctx.mode === "editor"}>
        {props.icon ? <Icon name={props.icon} size={18} /> : null}
        <Text ctx={ctx} node={node} path={["text"]} as="span" value={props.text} placeholder="Button text" />
      </ButtonLink>
    </div>
  );
}

export function IconBlock({ props, ctx, rootProps }) {
  const href = safeHref(props.link);
  const icon = (
    <span className="fn-icon inline-flex items-center justify-center text-[32px] text-[var(--fn-accent)]">
      <Icon name={props.icon || "star"} size="1em" />
    </span>
  );
  return (
    <div {...rootWith(rootProps, "fn-icon-block text-center leading-none")}>
      {href && ctx.mode !== "editor" ? <a href={href} aria-label={props.icon}>{icon}</a> : icon}
    </div>
  );
}

const LINE_STYLES = { solid: "border-solid", dashed: "border-dashed", dotted: "border-dotted" };

export function Divider({ props, rootProps }) {
  return (
    <div {...rootWith(rootProps, "fn-divider py-2")} role="separator">
      <div
        className={cx(
          "fn-divider-line mx-auto w-full border-0 border-t border-current text-[color:color-mix(in_srgb,currentColor_20%,transparent)]",
          LINE_STYLES[props.lineStyle] ?? LINE_STYLES.solid,
        )}
      />
    </div>
  );
}

export function Spacer({ ctx, rootProps }) {
  return (
    <div
      {...rootWith(rootProps, cx("fn-spacer h-8", ctx.mode === "editor" && "bg-[repeating-linear-gradient(45deg,transparent,transparent_6px,rgba(19,19,21,.04)_6px,rgba(19,19,21,.04)_12px)]"))}
      aria-hidden="true"
    />
  );
}

const BADGE_VARIANTS = {
  solid: "bg-[var(--fn-accent)] text-white border border-transparent",
  soft: "bg-[color-mix(in_srgb,var(--fn-accent)_12%,transparent)] text-[var(--fn-accent)] border border-transparent",
  outline: "bg-transparent text-[var(--fn-accent)] border border-[var(--fn-accent)]",
};

export function Badge({ node, props, ctx, rootProps }) {
  return (
    <div {...rootWith(rootProps, "fn-badge-block text-center")}>
      <span className={cx("fn-badge inline-flex items-center rounded-full px-3 py-1 text-[13px] font-semibold", BADGE_VARIANTS[props.variant] ?? BADGE_VARIANTS.soft)}>
        <Text ctx={ctx} node={node} path={["text"]} as="span" value={props.text} placeholder="Badge" />
      </span>
    </div>
  );
}

export function ListBlock({ node, props, ctx, rootProps }) {
  const items = Array.isArray(props.items) ? props.items : [];
  const Tag = props.ordered ? "ol" : "ul";
  return (
    <Tag {...rootWith(rootProps, cx("fn-list m-0 flex list-none flex-col gap-2 p-0 text-[16px]"))}>
      {items.map((item, index) => (
        <li key={index} className="flex items-start gap-2.5">
          <span className="mt-[3px] inline-flex size-5 shrink-0 items-center justify-center text-[var(--fn-accent)]">
            {props.ordered ? (
              <span className="text-[14px] font-bold">{index + 1}.</span>
            ) : (
              <Icon name={item.icon || props.icon || "check-circle"} size={20} />
            )}
          </span>
          <Text ctx={ctx} node={node} path={["items", index, "text"]} as="span" value={item.text} placeholder="List item" />
        </li>
      ))}
    </Tag>
  );
}
