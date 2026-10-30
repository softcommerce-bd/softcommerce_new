import { cx, imageSrc } from "../core/values.js";
import { Children, rootWith } from "../render/parts.jsx";

/**
 * Layout blocks. Responsive defaults use container-query variants (`@3xl:` =
 * page at least 768px wide, `@5xl:` = at least 1024px) so they follow the
 * page width in the editor preview as well as on phones.
 */

export function Section({ node, props, ctx, rootProps }) {
  const Tag = props.tag === "header" || props.tag === "footer" ? props.tag : "section";
  const background = imageSrc(props.backgroundImage);

  return (
    <Tag
      {...rootWith(rootProps, "fn-section relative px-4 py-10 @3xl:px-6 @3xl:py-14")}
      style={background ? { backgroundImage: `url("${background}")`, backgroundSize: "cover", backgroundPosition: "center" } : undefined}
    >
      {background && props.backgroundOverlay ? (
        <div className="pointer-events-none absolute inset-0" style={{ backgroundColor: props.backgroundOverlay }} aria-hidden="true" />
      ) : null}
      <div className={cx("fn-inner relative mx-auto flex w-full flex-col gap-4", props.contentWidth === "full" ? "max-w-none" : "max-w-[var(--fn-max,1140px)]")}>
        <Children node={node} ctx={ctx} />
      </div>
    </Tag>
  );
}

export function Container({ node, props, ctx, rootProps }) {
  const horizontal = props.direction === "horizontal";
  return (
    <div
      {...rootWith(
        rootProps,
        cx("fn-container flex min-w-0 gap-4", horizontal ? "flex-row items-center" : "flex-col", horizontal && props.wrap && "flex-wrap"),
      )}
    >
      <Children node={node} ctx={ctx} />
    </div>
  );
}

export function Grid({ node, ctx, rootProps }) {
  return (
    <div {...rootWith(rootProps, "fn-grid-block grid min-w-0 grid-cols-1 gap-4 @3xl:grid-cols-2 @5xl:grid-cols-3")}>
      <Children node={node} ctx={ctx} />
    </div>
  );
}

const STACK = {
  none: "flex-row",
  mobile: "flex-col @3xl:flex-row",
  tablet: "flex-col @5xl:flex-row",
};

const STACK_REVERSED = {
  none: "flex-row",
  mobile: "flex-col-reverse @3xl:flex-row",
  tablet: "flex-col-reverse @5xl:flex-row",
};

export function Columns({ node, props, ctx, rootProps }) {
  const stack = props.reverseOnStack ? STACK_REVERSED : STACK;
  return (
    <div {...rootWith(rootProps, cx("fn-columns flex min-w-0 gap-6", stack[props.stackOn] ?? stack.mobile))}>
      <Children node={node} ctx={ctx} />
    </div>
  );
}

export function Column({ node, ctx, rootProps }) {
  return (
    <div {...rootWith(rootProps, "fn-column flex min-w-0 flex-1 flex-col gap-4")}>
      <Children node={node} ctx={ctx} />
    </div>
  );
}
