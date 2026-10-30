import { Check, Star, X } from "lucide-react";
import { cx, imageSrc, safeHref, videoSource, whatsappHref } from "../core/values.js";
import { FunnelImage, Text, inCanvas, rootWith } from "../render/parts.jsx";
import styles from "./blocks.module.css";
import { BeforeAfter, CountdownClock, StickyBarShell, VideoPlayer } from "./client.jsx";
import { ButtonLink } from "./content.jsx";
import { Icon, WhatsAppMark } from "./icons.jsx";

/**
 * Marketing blocks: heroes, trust and benefit strips, social proof, FAQs and
 * the floating bars.
 */

/** Where a call-to-action goes. "checkout" scrolls to the checkout form. */
function ctaHref(action, link) {
  if (action === "checkout") return "#checkout";
  return safeHref(link);
}

function SectionHeading({ node, ctx, path = ["heading"], value, placeholder = "Section heading", className }) {
  return (
    <Text
      ctx={ctx}
      node={node}
      path={path}
      as="h2"
      value={value}
      optional
      placeholder={placeholder}
      className={cx("m-0 text-center text-[24px] font-bold leading-tight @3xl:text-[30px]", className)}
    />
  );
}

export function PromoHero({ node, props, ctx, rootProps }) {
  const layout = props.layout || "image_right";
  const background = layout === "background" ? imageSrc(props.backgroundImage) : null;
  const centered = layout === "background" || layout === "image_top";
  const editing = ctx.mode === "editor";

  const media =
    layout !== "background" && (imageSrc(props.image) || editing) ? (
      <FunnelImage image={props.image} ctx={ctx} eager className="fn-hero-media block h-auto w-full rounded-[14px] object-cover" />
    ) : null;

  const content = (
    <div className={cx("flex min-w-0 flex-col gap-3", centered ? "items-center text-center" : "items-start text-left")}>
      <Text
        ctx={ctx}
        node={node}
        path={["eyebrow"]}
        as="span"
        value={props.eyebrow}
        optional
        placeholder="Small line above the headline"
        className="inline-flex rounded-full bg-[color-mix(in_srgb,var(--fn-accent)_14%,transparent)] px-3 py-1 text-[13px] font-semibold text-[var(--fn-accent)]"
      />
      <Text
        ctx={ctx}
        node={node}
        path={["headline"]}
        as="h1"
        value={props.headline}
        placeholder="Your main offer headline"
        className="m-0 text-balance text-[30px] font-extrabold leading-[1.12] @3xl:text-[40px] @5xl:text-[48px]"
      />
      <Text
        ctx={ctx}
        node={node}
        path={["subheadline"]}
        as="p"
        multiline
        value={props.subheadline}
        optional
        placeholder="One or two lines on why customers should buy now"
        className="m-0 text-[16px] opacity-85 @3xl:text-[18px]"
      />
      {props.ctaText || editing ? (
        <ButtonLink href={ctaHref(props.ctaAction, props.ctaLink)} disabled={editing} className="mt-2 px-8 py-3.5 text-[17px] @3xl:text-[18px]">
          <Text ctx={ctx} node={node} path={["ctaText"]} as="span" value={props.ctaText} placeholder="Order now" />
        </ButtonLink>
      ) : null}
      <Text
        ctx={ctx}
        node={node}
        path={["secondaryText"]}
        as="p"
        value={props.secondaryText}
        optional
        placeholder="e.g. Cash on delivery all over Bangladesh"
        className="m-0 text-[13px] opacity-75"
      />
    </div>
  );

  return (
    <section
      {...rootWith(rootProps, cx("fn-hero relative overflow-hidden px-4 py-10 @3xl:px-6 @3xl:py-16", background && "text-white"))}
      style={background ? { backgroundImage: `url("${background}")`, backgroundSize: "cover", backgroundPosition: "center" } : undefined}
    >
      {background ? <div className="pointer-events-none absolute inset-0 bg-black/45" aria-hidden="true" /> : null}
      <div
        className={cx(
          "relative mx-auto grid max-w-[var(--fn-max,1140px)] items-center gap-6 @3xl:gap-10",
          (layout === "image_right" || layout === "image_left") && media && "@3xl:grid-cols-2",
        )}
      >
        {layout === "image_left" || layout === "image_top" ? media : null}
        {layout === "image_right" && media ? <div className="@3xl:order-last">{media}</div> : null}
        {content}
      </div>
    </section>
  );
}

export function ImageBanner({ props, ctx, rootProps }) {
  const href = safeHref(props.link);
  const hasMobile = Boolean(imageSrc(props.mobileImage));
  const pictures = (
    <>
      <FunnelImage image={props.image} ctx={ctx} eager className={cx("fn-img block h-auto w-full object-cover", hasMobile && "hidden @3xl:block")} />
      {hasMobile ? <FunnelImage image={props.mobileImage} ctx={ctx} eager className="fn-img block h-auto w-full object-cover @3xl:hidden" /> : null}
    </>
  );
  return (
    <div {...rootWith(rootProps, "fn-banner")}>
      {href && ctx.mode !== "editor" ? <a href={href} className="block">{pictures}</a> : pictures}
    </div>
  );
}

export function VideoHero({ node, props, ctx, rootProps }) {
  const editing = ctx.mode === "editor";
  return (
    <section {...rootWith(rootProps, "fn-video-hero px-4 py-10 @3xl:px-6 @3xl:py-14")}>
      <div className="mx-auto flex max-w-[860px] flex-col items-center gap-4 text-center">
        <Text ctx={ctx} node={node} path={["headline"]} as="h2" value={props.headline} placeholder="Watch how it works"
          className="m-0 text-balance text-[26px] font-extrabold leading-tight @3xl:text-[36px]" />
        <Text ctx={ctx} node={node} path={["subheadline"]} as="p" multiline optional value={props.subheadline}
          placeholder="A short line under the headline" className="m-0 text-[16px] opacity-85" />
        <div className="w-full">
          <VideoPlayer source={videoSource(props.provider, props.url)} poster={props.poster} title={props.headline} editing={editing} />
        </div>
        {props.ctaText || editing ? (
          <ButtonLink href={ctaHref(props.ctaAction, props.ctaLink)} disabled={editing} className="px-8 py-3.5 text-[17px]">
            <Text ctx={ctx} node={node} path={["ctaText"]} as="span" value={props.ctaText} placeholder="Order now" />
          </ButtonLink>
        ) : null}
      </div>
    </section>
  );
}

export function AnnouncementRibbon({ node, props, ctx, rootProps }) {
  const href = safeHref(props.link);
  const scrolling = props.scrolling && ctx.mode !== "editor";
  const message = (
    <span className="inline-flex items-center gap-2 whitespace-nowrap">
      {props.icon ? <Icon name={props.icon} size={16} /> : null}
      <Text ctx={ctx} node={node} path={["text"]} as="span" value={props.text} placeholder="Free delivery on every order today" />
    </span>
  );

  return (
    <div {...rootWith(rootProps, "fn-ribbon overflow-hidden bg-[var(--fn-accent)] px-4 py-2 text-center text-[14px] font-semibold text-white")}>
      {scrolling ? (
        <div className={cx("flex w-max gap-16", styles.marquee)} aria-label={props.text}>
          {[0, 1, 2, 3].map((copy) => (
            <span key={copy} aria-hidden={copy > 0 || undefined}>{message}</span>
          ))}
        </div>
      ) : href && ctx.mode !== "editor" ? (
        <a href={href} className="text-inherit no-underline">{message}</a>
      ) : (
        message
      )}
    </div>
  );
}

export function CountdownTimer({ node, props, ctx, rootProps }) {
  return (
    <div {...rootWith(rootProps, "fn-countdown flex flex-col items-center gap-3")}>
      <Text ctx={ctx} node={node} path={["heading"]} as="p" optional value={props.heading} placeholder="Offer ends in"
        className="m-0 text-center text-[18px] font-bold" />
      <CountdownClock
        endsAt={props.endsAt}
        expiredAction={props.expiredAction}
        expiredMessage={props.expiredMessage}
        editing={ctx.mode === "editor"}
        labels={{ days: props.labelDays, hours: props.labelHours, minutes: props.labelMinutes, seconds: props.labelSeconds }}
      />
    </div>
  );
}

function IconItemGrid({ node, ctx, items, compact, path = "items" }) {
  return (
    <div className={cx("fn-grid grid gap-4", compact ? "grid-cols-2 @3xl:grid-cols-4" : "grid-cols-2 @3xl:grid-cols-4")}>
      {items.map((item, index) => (
        <div key={index} className={cx("flex flex-col items-center gap-2 text-center", compact ? "p-2" : "rounded-[12px] bg-[color-mix(in_srgb,var(--fn-accent)_6%,transparent)] p-4")}>
          {imageSrc(item.image) ? (
            <FunnelImage image={item.image} ctx={ctx} className="size-12 rounded-full object-cover" />
          ) : (
            <span className="inline-flex size-12 items-center justify-center rounded-full bg-[color-mix(in_srgb,var(--fn-accent)_14%,transparent)] text-[var(--fn-accent)]">
              <Icon name={item.icon || "check-circle"} size={24} />
            </span>
          )}
          <Text ctx={ctx} node={node} path={[path, index, "title"]} as="p" value={item.title} placeholder="Title" className="m-0 text-[15px] font-semibold leading-snug" />
          <Text ctx={ctx} node={node} path={[path, index, "text"]} as="p" optional value={item.text} placeholder="Short detail" className="m-0 text-[13px] opacity-75" />
        </div>
      ))}
    </div>
  );
}

export function UspHighlights({ node, props, ctx, rootProps }) {
  const items = Array.isArray(props.items) ? props.items : [];
  return (
    <div {...rootWith(rootProps, "fn-usp")}>
      <IconItemGrid node={node} ctx={ctx} items={items} compact={props.layout !== "grid"} />
    </div>
  );
}

export function TrustIcons({ node, props, ctx, rootProps }) {
  const items = Array.isArray(props.items) ? props.items : [];
  return (
    <div {...rootWith(rootProps, "fn-trust rounded-[12px] border border-[color-mix(in_srgb,currentColor_12%,transparent)] px-3 py-4")}>
      <IconItemGrid node={node} ctx={ctx} items={items} compact />
    </div>
  );
}

export function BenefitsGrid({ node, props, ctx, rootProps }) {
  const items = Array.isArray(props.items) ? props.items : [];
  return (
    <div {...rootWith(rootProps, "fn-benefits flex flex-col gap-6")}>
      <div className="flex flex-col gap-2">
        <SectionHeading node={node} ctx={ctx} value={props.heading} placeholder="Why customers love it" />
        <Text ctx={ctx} node={node} path={["subheading"]} as="p" multiline optional value={props.subheading}
          placeholder="A short introduction" className="m-0 text-center text-[16px] opacity-80" />
      </div>
      <div className="fn-grid grid grid-cols-1 gap-4 @xl:grid-cols-2 @5xl:grid-cols-3">
        {items.map((item, index) => (
          <div key={index} className="flex gap-3 rounded-[12px] border border-[color-mix(in_srgb,currentColor_10%,transparent)] bg-white/60 p-4">
            {imageSrc(item.image) ? (
              <FunnelImage image={item.image} ctx={ctx} className="size-14 shrink-0 rounded-[10px] object-cover" />
            ) : (
              <span className="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-[var(--fn-accent)] text-white">
                <Icon name={item.icon || "check"} size={22} />
              </span>
            )}
            <div className="flex min-w-0 flex-col gap-1">
              <Text ctx={ctx} node={node} path={["items", index, "title"]} as="h3" value={item.title} placeholder="Benefit"
                className="m-0 text-[17px] font-semibold leading-snug" />
              <Text ctx={ctx} node={node} path={["items", index, "text"]} as="p" multiline optional value={item.text}
                placeholder="Explain the benefit in a sentence" className="m-0 text-[14px] opacity-80" />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function Stars({ rating }) {
  const value = Math.max(0, Math.min(5, Number(rating) || 0));
  if (!value) return null;
  return (
    <span className="inline-flex gap-0.5 text-[#f59e0b]" aria-label={`${value} out of 5`}>
      {[1, 2, 3, 4, 5].map((step) => (
        <Star key={step} size={15} fill={step <= value ? "currentColor" : "none"} aria-hidden="true" />
      ))}
    </span>
  );
}

export function TestimonialCards({ node, props, ctx, rootProps }) {
  const items = Array.isArray(props.items) ? props.items : [];
  const carousel = props.layout === "carousel";
  return (
    <div {...rootWith(rootProps, "fn-testimonials flex flex-col gap-6")}>
      <SectionHeading node={node} ctx={ctx} value={props.heading} placeholder="What our customers say" />
      <div className={cx("fn-grid", carousel ? "flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2" : "grid grid-cols-1 gap-4 @3xl:grid-cols-2 @5xl:grid-cols-3")}>
        {items.map((item, index) => (
          <figure key={index} className={cx("m-0 flex flex-col gap-3 rounded-[12px] bg-white p-4 shadow-[0_2px_10px_rgba(19,19,21,.07)]", carousel && "w-[85%] shrink-0 snap-center @3xl:w-[45%]")}>
            <Stars rating={item.rating} />
            <Text ctx={ctx} node={node} path={["items", index, "text"]} as="blockquote" multiline value={item.text}
              placeholder="What the customer said" className="m-0 text-[15px] leading-relaxed" />
            <figcaption className="mt-auto flex items-center gap-3">
              {imageSrc(item.avatar) ? <FunnelImage image={item.avatar} ctx={ctx} className="size-10 rounded-full object-cover" /> : null}
              <span className="flex flex-col">
                <Text ctx={ctx} node={node} path={["items", index, "name"]} as="span" value={item.name} placeholder="Customer name" className="text-[14px] font-semibold" />
                <Text ctx={ctx} node={node} path={["items", index, "role"]} as="span" optional value={item.role} placeholder="City" className="text-[12px] opacity-70" />
              </span>
            </figcaption>
          </figure>
        ))}
      </div>
    </div>
  );
}

export function FaqAccordion({ node, props, ctx, rootProps }) {
  const items = Array.isArray(props.items) ? props.items : [];
  const editing = ctx.mode === "editor";
  return (
    <div {...rootWith(rootProps, "fn-faq mx-auto flex w-full max-w-[860px] flex-col gap-5")}>
      <SectionHeading node={node} ctx={ctx} value={props.heading} placeholder="Frequently asked questions" />
      <div className="flex flex-col gap-2.5">
        {items.map((item, index) => (
          <details
            key={index}
            open={editing || (props.openFirst && index === 0) || undefined}
            className="group rounded-[10px] border border-[color-mix(in_srgb,currentColor_12%,transparent)] bg-white px-4 py-3"
          >
            <summary
              className="flex cursor-pointer list-none items-center justify-between gap-3 text-[16px] font-semibold [&::-webkit-details-marker]:hidden"
              onClick={editing ? (event) => event.preventDefault() : undefined}
            >
              <Text ctx={ctx} node={node} path={["items", index, "question"]} as="span" value={item.question} placeholder="Question" />
              <span className="text-[20px] leading-none text-[var(--fn-accent)] transition-transform group-open:rotate-45" aria-hidden="true">+</span>
            </summary>
            <Text ctx={ctx} node={node} path={["items", index, "answer"]} as="p" multiline value={item.answer}
              placeholder="Answer" className="mb-0 mt-2 text-[15px] leading-relaxed opacity-85" />
          </details>
        ))}
      </div>
    </div>
  );
}

export function BeforeAfterSlider({ props, ctx, rootProps }) {
  return (
    <div {...rootWith(rootProps, "fn-before-after mx-auto w-full max-w-[720px]")}>
      <BeforeAfter
        before={props.before}
        after={props.after}
        beforeLabel={props.beforeLabel}
        afterLabel={props.afterLabel}
        initial={props.initial}
        editing={ctx.mode === "editor"}
      />
    </div>
  );
}

const YES = new Set(["✓", "✔", "yes", "হ্যাঁ", "আছে"]);
const NO = new Set(["✗", "✘", "x", "no", "না", "নেই"]);

function Cell({ value }) {
  const text = typeof value === "string" ? value.trim() : "";
  if (YES.has(text.toLowerCase())) return <Check size={18} className="mx-auto text-[var(--fn-accent)]" aria-label={text} />;
  if (NO.has(text.toLowerCase())) return <X size={18} className="mx-auto opacity-50" aria-label={text} />;
  return text;
}

export function ComparisonTable({ node, props, ctx, rootProps }) {
  const headers = Array.isArray(props.headers) ? props.headers : [];
  const rows = Array.isArray(props.rows) ? props.rows : [];
  const highlight = Number(props.highlightColumn) || 0;

  return (
    <div {...rootWith(rootProps, "fn-compare flex flex-col gap-5")}>
      <SectionHeading node={node} ctx={ctx} value={props.heading} placeholder="Compare and decide" />
      <div className="overflow-x-auto">
        <table className="w-full min-w-[420px] border-collapse text-[14px] @3xl:text-[15px]">
          <thead>
            <tr>
              <th className="p-3 text-left" />
              {headers.map((header, index) => (
                <th key={index} className={cx("p-3 text-center font-bold", highlight === index + 1 && "rounded-t-[10px] bg-[var(--fn-accent)] text-white")}>
                  {header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row, rowIndex) => (
              <tr key={rowIndex} className="border-t border-[color-mix(in_srgb,currentColor_10%,transparent)]">
                <th scope="row" className="p-3 text-left font-medium">
                  <Text ctx={ctx} node={node} path={["rows", rowIndex, "label"]} as="span" value={row.label} placeholder="Feature" />
                </th>
                {headers.map((_, index) => (
                  <td key={index} className={cx("p-3 text-center", highlight === index + 1 && "bg-[color-mix(in_srgb,var(--fn-accent)_8%,transparent)] font-semibold")}>
                    <Cell value={row.values?.[index]} />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export function GuaranteeSection({ node, props, ctx, rootProps }) {
  const items = Array.isArray(props.items) ? props.items : [];
  return (
    <div {...rootWith(rootProps, "fn-guarantee flex flex-col items-center gap-4 rounded-[14px] border-2 border-dashed border-[color-mix(in_srgb,var(--fn-accent)_45%,transparent)] px-5 py-7 text-center")}>
      <span className="inline-flex size-14 items-center justify-center rounded-full bg-[color-mix(in_srgb,var(--fn-accent)_14%,transparent)] text-[var(--fn-accent)]">
        <Icon name={props.icon || "shield-check"} size={30} />
      </span>
      <Text ctx={ctx} node={node} path={["heading"]} as="h2" value={props.heading} placeholder="Our promise"
        className="m-0 text-[22px] font-bold @3xl:text-[26px]" />
      <Text ctx={ctx} node={node} path={["text"]} as="p" multiline optional value={props.text}
        placeholder="Explain your return or quality policy" className="m-0 max-w-[640px] text-[15px] opacity-85" />
      {items.length ? (
        <div className="w-full">
          <IconItemGrid node={node} ctx={ctx} items={items} compact />
        </div>
      ) : null}
    </div>
  );
}

const SHOW_ON = { all: "", mobile: "@3xl:hidden", desktop: "hidden @3xl:block" };

export function StickyCta({ node, props, ctx, rootProps }) {
  const editing = ctx.mode === "editor";
  const bar = (
    <div className="mx-auto flex max-w-[var(--fn-max,1140px)] items-center justify-between gap-3 px-4 py-3">
      <Text ctx={ctx} node={node} path={["subtext"]} as="span" optional value={props.subtext}
        placeholder="e.g. Cash on delivery · Free delivery" className="min-w-0 truncate text-[14px] font-semibold" />
      <ButtonLink
        href={props.action === "checkout" ? "#checkout" : safeHref(props.link)}
        disabled={editing}
        className="ml-auto shrink-0 px-5 py-2.5 text-[15px]"
      >
        <Text ctx={ctx} node={node} path={["text"]} as="span" value={props.text} placeholder="Order now" />
      </ButtonLink>
    </div>
  );
  const surface = "border-t border-[color-mix(in_srgb,currentColor_10%,transparent)] bg-white/95 backdrop-blur shadow-[0_-4px_16px_rgba(19,19,21,.08)]";

  if (editing) {
    return (
      <div {...rootWith(rootProps, "fn-sticky-preview")}>
        <p className="m-0 bg-canvas-sunken px-4 py-1 text-center text-[11px] text-ink-tertiary">Sticky bar — stays on screen while customers scroll</p>
        <div className={surface}>{bar}</div>
      </div>
    );
  }

  const position = props.position === "top" ? "top-0" : "bottom-0";
  return (
    <div {...rootWith(rootProps, cx("fn-sticky-anchor", SHOW_ON[props.showOn] ?? ""))}>
      <StickyBarShell
        hideNearCheckout={props.hideNearCheckout !== false}
        className={cx("fn-sticky inset-x-0 z-40", inCanvas(ctx) ? "sticky" : "fixed", position, surface)}
      >
        {bar}
      </StickyBarShell>
    </div>
  );
}

export function FloatingWhatsApp({ props, ctx, rootProps }) {
  const href = whatsappHref(props.phone, props.message);
  const editing = ctx.mode === "editor";
  const button = (
    <span className="inline-flex items-center gap-2 rounded-full bg-[#25D366] px-3 py-3 text-white shadow-[0_6px_20px_rgba(37,211,102,.45)]">
      <WhatsAppMark size={26} />
      {props.label ? <span className="pr-1 text-[14px] font-semibold">{props.label}</span> : null}
    </span>
  );

  if (editing) {
    return (
      <div {...rootWith(rootProps, cx("fn-whatsapp-preview flex px-4 py-3", props.position === "left" ? "justify-start" : "justify-end"))}>
        {href ? button : <span className="text-[13px] text-ink-tertiary">Add the WhatsApp number in the settings panel</span>}
      </div>
    );
  }
  if (!href) return null;

  return (
    <a
      {...rootWith(
        rootProps,
        cx(
          "fn-whatsapp z-40 no-underline",
          inCanvas(ctx) ? "sticky ml-auto block w-fit" : "fixed",
          props.position === "left" ? "left-4" : "right-4",
          "bottom-4 [.fn-root:has(.fn-sticky)_&]:bottom-24",
        ),
      )}
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      aria-label={props.label || "Chat on WhatsApp"}
    >
      {button}
    </a>
  );
}
