"use client";

import { useId, useState } from "react";
import { ChevronDown, Loader2 } from "lucide-react";
import { useCommerce } from "../commerce/CommerceProvider.jsx";
import { useCheckout } from "../commerce/CheckoutProvider.jsx";
import { FIELD_TEXT, messageKey } from "../commerce/checkoutRules.js";
import { fill } from "../commerce/i18n.js";
import { cx, safeHref, safeImageSrc } from "../core/values.js";
import { Children, Text, rootWith } from "../render/parts.jsx";
import { Icon } from "./icons.jsx";

/**
 * Order form blocks: the form itself, its summary, delivery charge, payment
 * choice, order button and the confirmation shown after an order.
 *
 * Like the product blocks they know no price and no rule: amounts come from
 * the server's quote, the fields come from the page's settings, and what is
 * allowed (payment methods, delivery areas) comes from the store. In the
 * editor they are drawn but inert; Preview makes them work without placing
 * an order; the live page places real orders.
 */

const INPUT =
  "fn-input block w-full rounded-[10px] border border-[#c9c9d1] bg-white px-3.5 py-3 text-[16px] leading-snug text-[#131315] placeholder:text-[#8d8d96] " +
  "focus:border-[var(--fn-accent)] focus:outline-none focus:ring-2 focus:ring-[color-mix(in_srgb,var(--fn-accent)_28%,transparent)] disabled:bg-[#f4f4f6] aria-[invalid=true]:border-[#c0392b]";

const money = (value) => value?.text ?? "";

/** Wording for a field: the page's own label, else the standard one in the page's language. */
function wording(field, t) {
  const [labelKey, placeholderKey] = FIELD_TEXT[field.key] ?? [];
  return {
    label: field.label || (labelKey ? t[labelKey] : field.key),
    placeholder: field.placeholder || (placeholderKey ? t[placeholderKey] : ""),
  };
}

// ── The form ───────────────────────────────────────────────────────────────

function FieldInput({ field, checkout, id, describedBy }) {
  const { t, values, errors, interactive, districts } = checkout;
  const { label, placeholder } = wording(field, t);
  const invalid = Boolean(errors[field.key]);
  const common = {
    id,
    name: field.key,
    "aria-invalid": invalid || undefined,
    "aria-describedby": describedBy,
    "aria-required": field.required || undefined,
    disabled: !interactive,
    autoComplete: field.autoComplete,
  };
  const set = (value) => checkout.setValue(field.key, value);

  if (field.type === "checkbox") {
    return (
      <label className="flex items-start gap-2.5 text-[15px]">
        <input type="checkbox" {...common} checked={Boolean(values[field.key])} onChange={(event) => set(event.target.checked)} className="mt-1 size-5 shrink-0 accent-[var(--fn-accent)]" />
        <span>{label}</span>
      </label>
    );
  }
  if (field.key === "district" || field.type === "select") {
    const options = field.key === "district" ? districts.map((d) => ({ value: String(d.id), label: d.charge ? `${d.name} — ${d.charge.text}` : d.name })) : field.options.map((o) => ({ value: o, label: o }));
    return (
      <select {...common} value={values[field.key] ?? ""} onChange={(event) => set(event.target.value)} className={cx(INPUT, "appearance-auto")} data-testid={`input-${field.key}`}>
        <option value="">{placeholder || label}</option>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    );
  }
  if (field.type === "textarea") {
    return <textarea {...common} rows={3} value={values[field.key] ?? ""} placeholder={placeholder} onChange={(event) => set(event.target.value)} className={cx(INPUT, "min-h-[88px] resize-y")} data-testid={`input-${field.key}`} />;
  }
  return (
    <input
      {...common}
      type={field.inputType}
      inputMode={field.key === "phone" ? "tel" : undefined}
      value={values[field.key] ?? ""}
      placeholder={placeholder}
      onChange={(event) => set(event.target.value)}
      className={INPUT}
      data-testid={`input-${field.key}`}
    />
  );
}

function Field({ field, checkout, wide }) {
  const id = useId();
  const { t, errors } = checkout;
  const code = errors[field.key];
  const errorId = `${id}-error`;
  const { label } = wording(field, t);
  const checkbox = field.type === "checkbox";

  return (
    <div className={cx("fn-field flex flex-col gap-1.5", wide && field.width === "half" ? "@md:col-span-1" : "@md:col-span-2")} data-fn-field={field.key}>
      {checkbox ? null : (
        <label htmlFor={id} className="text-[14px] font-semibold">
          {label}
          {field.required ? <span aria-hidden="true" className="text-[#c0392b]"> *</span> : null}
        </label>
      )}
      <FieldInput field={field} checkout={checkout} id={id} describedBy={code ? errorId : undefined} />
      {field.key === "phone" && checkout.otpRequired ? <Otp checkout={checkout} /> : null}
      {code ? (
        <p id={errorId} role="alert" className="m-0 text-[13px] font-medium text-[#c0392b]" data-testid={`error-${field.key}`}>
          {t[messageKey(field.key, code)]}
        </p>
      ) : null}
    </div>
  );
}

/** Phone verification, when the store asks for it before ordering. */
function Otp({ checkout }) {
  const { t, otp, errors } = checkout;
  const [code, setCode] = useState("");
  if (otp.state === "verified") {
    return (
      <p className="m-0 flex items-center gap-1.5 text-[13px] font-semibold text-[#12805c]" data-testid="otp-verified">
        <Icon name="check-circle" size={16} /> {t.otpVerified}
      </p>
    );
  }
  return (
    <div className="flex flex-col gap-2 rounded-[10px] bg-[#f4f4f6] p-3" data-fn-field="otp">
      {otp.state === "idle" || otp.state === "sending" ? (
        <button type="button" onClick={checkout.sendOtp} disabled={!checkout.interactive || otp.state === "sending"} className="self-start rounded-[8px] border border-[#c9c9d1] bg-white px-3 py-2 text-[14px] font-semibold" data-testid="otp-send">
          {t.otpSend}
        </button>
      ) : (
        <div className="flex gap-2">
          <input value={code} onChange={(event) => setCode(event.target.value)} inputMode="numeric" autoComplete="one-time-code" placeholder={t.otpCode} aria-label={t.otpCode} className={cx(INPUT, "min-w-0 flex-1")} data-testid="otp-code" />
          <button type="button" onClick={() => checkout.verifyOtp(code)} disabled={otp.state === "verifying" || code.trim() === ""} className="rounded-[10px] bg-[var(--fn-accent)] px-4 text-[14px] font-semibold text-white disabled:opacity-60" data-testid="otp-verify">
            {t.otpVerify}
          </button>
        </div>
      )}
      {otp.state === "sent" || otp.state === "verifying" ? <p className="m-0 text-[13px] opacity-70">{t.otpSent}</p> : null}
      {otp.error ? <p role="alert" className="m-0 text-[13px] font-medium text-[#c0392b]">{t[otp.error]}</p> : null}
      {errors.otp ? <p role="alert" className="m-0 text-[13px] font-medium text-[#c0392b]" data-testid="error-otp">{t.errOtp}</p> : null}
    </div>
  );
}

function Notice({ checkout }) {
  const { notice, t } = checkout;
  if (!notice) return null;
  const text = fill(t[notice.key] ?? t.tryAgain, notice.vars ?? {});
  return (
    <p
      role={notice.kind === "error" ? "alert" : "status"}
      className={cx("m-0 rounded-[10px] px-3.5 py-3 text-[14px] font-medium", notice.kind === "error" ? "bg-[#fdecea] text-[#9c2b1f]" : "bg-[#eef3ff] text-[#1f4fb3]")}
      data-testid="checkout-notice"
    >
      {text}
    </p>
  );
}

export function CheckoutForm({ node, props, ctx, rootProps }) {
  const checkout = useCheckout();
  const t = checkout?.t;
  const editing = ctx.mode === "editor";

  const body = !checkout ? null : !checkout.loaded ? (
    <p className="m-0 text-[14px] opacity-70">{t?.checkoutLoading}</p>
  ) : checkout.loadFailed ? (
    <p className="m-0 text-[14px] text-[#c0392b]" role="alert">{t.checkoutLoadFailed}</p>
  ) : !checkout.open && !editing ? (
    <Closed checkout={checkout} />
  ) : checkout.status === "placed" && !editing && !checkout.hasConfirmationBlock ? (
    <Confirmation checkout={checkout} props={{}} ctx={ctx} node={node} />
  ) : checkout.status === "placed" && !editing ? null : (
    <>
      <div className={cx("grid grid-cols-1 gap-x-4 gap-y-3.5", props.layout === "two_column" && "@md:grid-cols-2")}>
        {checkout.fields.map((field) => (
          <Field key={field.key} field={field} checkout={checkout} wide={props.layout === "two_column"} />
        ))}
      </div>
      <Children node={node} ctx={ctx} />
      {checkout.requireConsent ? (
        <div className="flex flex-col gap-1" data-fn-field="consent">
          <label className="flex items-start gap-2.5 text-[14px]">
            <input type="checkbox" checked={checkout.consent} onChange={(event) => checkout.setConsent(event.target.checked)} disabled={!checkout.interactive} className="mt-0.5 size-5 shrink-0 accent-[var(--fn-accent)]" data-testid="input-consent" />
            <span>{props.consentText || t.consentDefault}</span>
          </label>
          {checkout.errors.consent ? <p role="alert" className="m-0 text-[13px] font-medium text-[#c0392b]" data-testid="error-consent">{t.errConsent}</p> : null}
        </div>
      ) : null}
      <Notice checkout={checkout} />
      {checkout.errors.selections ? <p role="alert" className="m-0 text-[13px] font-medium text-[#c0392b]" data-testid="error-selections">{t.errSelection}</p> : null}
    </>
  );

  return (
    <form
      {...rootWith(rootProps, "fn-checkout flex flex-col gap-4 rounded-[14px] border border-[#e2e2e6] bg-white p-4 text-[#131315] @md:p-6")}
      onSubmit={(event) => {
        event.preventDefault();
        if (!editing) checkout?.submit();
      }}
      noValidate
      aria-busy={checkout?.status === "submitting" || undefined}
      data-testid="checkout-form"
      data-fn-checkout=""
    >
      {checkout?.mode === "preview" ? (
        <p className="m-0 rounded-[8px] bg-[#fff6dd] px-3 py-2 text-center text-[13px] font-semibold text-[#8a6100]" data-testid="preview-banner">{t.previewBanner}</p>
      ) : null}
      <Text ctx={ctx} node={node} path={["title"]} as="h3" optional value={props.title ?? t?.formTitle} placeholder="Form title" className="m-0 text-[19px] font-bold" />
      {body}
    </form>
  );
}

function Closed({ checkout }) {
  const { t } = checkout;
  return (
    <div className="rounded-[10px] bg-[#f4f4f6] p-4 text-center" role="status" data-testid="checkout-closed" data-reason={checkout.closedReason ?? undefined}>
      <strong className="block text-[16px]">{t.closedTitle}</strong>
      <span className="text-[14px] opacity-75">{t.closedText}</span>
    </div>
  );
}

// ── Summary, delivery, payment, button ─────────────────────────────────────

function Row({ label, value, muted, strong }) {
  return (
    <div className={cx("flex items-baseline justify-between gap-3 text-[14px]", muted && "opacity-70", strong && "text-[17px] font-bold")}>
      <span>{label}</span>
      <span className="tabular-nums">{value}</span>
    </div>
  );
}

function imageFor(commerce, line, item) {
  const offer = commerce?.offers?.[line.key];
  const match = offer?.items?.find((candidate) => candidate.product_id === item.product_id);
  return safeImageSrc(match?.image ?? offer?.items?.[0]?.image);
}

export function OrderSummary({ node, props, ctx, rootProps }) {
  const commerce = useCommerce();
  const [open, setOpen] = useState(true);
  const t = commerce?.t;
  const quote = commerce?.quote;
  const totals = quote?.valid ? quote.totals : null;
  const delivery = totals?.delivery;

  return (
    <section {...rootWith(rootProps, "fn-order-summary flex flex-col gap-2.5 rounded-[12px] border border-[#e2e2e6] bg-[#fafafb] p-4")} aria-live="polite" data-testid="order-summary" data-updating={commerce?.quoting || undefined}>
      <div className="flex items-center justify-between gap-2">
        <Text ctx={ctx} node={node} path={["title"]} as="h4" optional value={props.title ?? t?.summary} placeholder="Order summary" className="m-0 text-[16px] font-bold" />
        {props.collapsibleOnMobile ? (
          <button type="button" onClick={() => setOpen((v) => !v)} aria-expanded={open} aria-label={t?.summary} className="@3xl:hidden rounded-full p-1">
            <ChevronDown size={18} className={cx("transition-transform", open && "rotate-180")} aria-hidden="true" />
          </button>
        ) : null}
      </div>
      <div className={cx("flex flex-col gap-2.5", props.collapsibleOnMobile && !open && "hidden @3xl:flex")}>
        {!commerce ? null : !quote ? (
          <p className="m-0 text-[14px] opacity-70">{commerce.ready ? t.emptySummary : t.calculating}</p>
        ) : !totals ? (
          <ul className="m-0 flex list-none flex-col gap-1 p-0 text-[13px] text-[#c0392b]" role="alert">
            {quote.errors.map((error, index) => <li key={index}>{error.message || t.priceError}</li>)}
          </ul>
        ) : (
          <div className={cx("flex flex-col gap-2.5 transition-opacity", commerce.quoting && "opacity-60")}>
            {quote.lines.map((line) =>
              line.items.map((item, index) => {
                const src = props.showImages === false ? null : imageFor(commerce, line, item);
                return (
                  <div key={`${line.key}-${index}`} className="flex items-center gap-3 border-b border-[#ececf0] pb-2.5 text-[14px]">
                    {src ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={src} alt="" className="size-12 shrink-0 rounded-[8px] object-cover" loading="lazy" draggable={false} />
                    ) : null}
                    <span className="min-w-0 flex-1">
                      <span className="block truncate font-medium">{item.name}</span>
                      <span className="block text-[12px] opacity-65">
                        {item.variant ? `${item.variant} · ` : ""}× {item.pieces}
                      </span>
                    </span>
                    <span className="tabular-nums">{money(item.total)}</span>
                  </div>
                );
              }),
            )}
            {quote.lines.map((line) =>
              line.gift ? (
                <div key={`${line.key}-gift`} className="flex justify-between gap-3 text-[13px] text-[#12805c]">
                  <span className="min-w-0 truncate">{t.freeGift}: {line.gift.name} × {line.gift.pieces}</span>
                  <span>0</span>
                </div>
              ) : null,
            )}
            {totals.funnel_discount.amount > 0 ? <Row label={t.discount} value={`− ${money(totals.funnel_discount)}`} /> : null}
            <Row label={t.subtotal} value={money(totals.product_subtotal)} />
            {totals.tax.amount > 0 ? <Row label={t.tax} value={money(totals.tax)} /> : null}
            <Row
              label={t.delivery}
              value={delivery?.status === "calculated" ? (delivery.free ? t.freeDelivery : money(delivery.amount)) : delivery?.free ? t.freeDelivery : t.chooseDistrictForDelivery}
              muted={delivery?.status !== "calculated" && !delivery?.free}
            />
            <div className="border-t border-[#d4d4d8] pt-2.5">
              <Row label={totals.payable ? t.payable : t.total} value={money(totals.payable ?? totals.total_before_delivery)} strong />
            </div>
            {totals.total_savings.amount > 0 ? (
              <p className="m-0 flex items-center gap-1 text-[13px] font-semibold text-[#12805c]">
                <Icon name="badge-percent" size={15} /> {t.save} {money(totals.total_savings)}
              </p>
            ) : null}
          </div>
        )}
      </div>
    </section>
  );
}

export function DeliveryCharges({ node, props, ctx, rootProps }) {
  const commerce = useCommerce();
  const t = commerce?.t;
  const delivery = commerce?.quote?.valid ? commerce.quote.totals.delivery : null;
  const calculated = delivery?.status === "calculated";

  return (
    <div {...rootWith(rootProps, "fn-delivery flex flex-col gap-1 rounded-[12px] border border-dashed border-[#c9c9d1] bg-white px-4 py-3")} data-testid="delivery-charges">
      <div className="flex items-baseline justify-between gap-3 text-[15px]">
        <Text ctx={ctx} node={node} path={["label"]} as="span" optional value={props.label ?? t?.delivery} placeholder="Delivery charge" className="font-semibold" />
        <span className="tabular-nums font-bold" data-testid="delivery-amount">
          {calculated ? (delivery.free ? t.freeDelivery : money(delivery.amount)) : delivery?.free ? t.freeDelivery : "—"}
        </span>
      </div>
      {!calculated && !delivery?.free ? <span className="text-[13px] opacity-70">{t?.chooseDistrictForDelivery}</span> : null}
      <Text ctx={ctx} node={node} path={["note"]} as="p" optional multiline value={props.note} placeholder="A note about delivery (optional)" className="m-0 text-[13px] opacity-75" />
    </div>
  );
}

export function PaymentMethodSelector({ node, props, ctx, rootProps }) {
  const checkout = useCheckout();
  const t = checkout?.t;
  const methods = checkout?.methods ?? [];
  if (!checkout || (methods.length === 0 && ctx.mode !== "editor")) return null;

  const names = { cash_on_delivery: [t.cashOnDelivery, t.cashOnDeliveryHint], paystation: [t.payOnline, t.payOnlineHint] };
  return (
    <fieldset {...rootWith(rootProps, "fn-payment m-0 flex flex-col gap-2 border-0 p-0")} data-fn-field="payment_type" data-testid="payment-methods">
      <legend className="mb-1 p-0 text-[14px] font-semibold">
        <Text ctx={ctx} node={node} path={["label"]} as="span" optional value={props.label ?? t.payment} placeholder="Payment method" />
      </legend>
      {methods.map((method) => {
        const [label, hint] = names[method.key] ?? [method.label, ""];
        const selected = checkout.payment === method.key;
        return (
          <label key={method.key} className={cx("flex cursor-pointer items-start gap-3 rounded-[12px] border-2 bg-white px-4 py-3", selected ? "border-[var(--fn-accent)]" : "border-[#e2e2e6]")}>
            <input type="radio" name="payment_type" value={method.key} checked={selected} onChange={() => checkout.setPayment(method.key)} disabled={!checkout.interactive} className="mt-1 size-5 accent-[var(--fn-accent)]" data-testid={`payment-${method.key}`} />
            <span className="flex flex-col">
              <span className="text-[15px] font-semibold">{label}</span>
              {hint ? <span className="text-[13px] opacity-70">{hint}</span> : null}
            </span>
          </label>
        );
      })}
      {checkout.errors.payment_type ? <p role="alert" className="m-0 text-[13px] font-medium text-[#c0392b]" data-testid="error-payment_type">{t.errPayment}</p> : null}
    </fieldset>
  );
}

export function PurchaseButton({ node, props, ctx, rootProps }) {
  const checkout = useCheckout();
  const commerce = useCommerce();
  const t = commerce?.t;
  const busy = checkout?.status === "submitting" || checkout?.status === "redirecting";
  const totals = commerce?.quote?.valid ? commerce.quote.totals : null;
  const total = totals ? money(totals.payable ?? totals.total_before_delivery) : "";
  const editing = ctx.mode === "editor";

  return (
    <div className="fn-purchase flex flex-col items-stretch gap-1.5">
      <button
        {...rootWith(rootProps, "fn-btn inline-flex min-h-[54px] w-full items-center justify-center gap-2 rounded-[12px] border-2 border-transparent bg-[var(--fn-accent)] px-6 py-3.5 text-[18px] font-bold leading-tight text-white transition-[filter] hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60")}
        type={editing ? "button" : "submit"}
        disabled={busy || (!editing && (checkout?.status === "placed"))}
        aria-busy={busy || undefined}
        data-testid="order-button"
      >
        {busy ? <Loader2 size={20} className="animate-spin" aria-hidden="true" /> : props.icon ? <Icon name={props.icon} size={20} /> : null}
        {busy ? (
          <span>{t?.placing}</span>
        ) : (
          <>
            <Text ctx={ctx} node={node} path={["text"]} as="span" value={props.text ?? t?.orderNow} placeholder="Order button" />
            {props.showTotal !== false && total ? <span className="tabular-nums" data-testid="button-total">· {total}</span> : null}
          </>
        )}
      </button>
      <Text ctx={ctx} node={node} path={["subtext"]} as="p" optional value={props.subtext} placeholder="A reassurance under the button" className="m-0 text-center text-[13px] opacity-75" />
    </div>
  );
}

// ── After the order ────────────────────────────────────────────────────────

function samplePlaced() {
  const money = (amount) => ({ amount, text: `৳${amount.toLocaleString("en-US")}` });
  return {
    code: "100245",
    payment: { method: "cash_on_delivery", status: "unpaid", action: "none" },
    items: [{ name: "Sample product", variant: null, quantity: 1, total: money(2500) }],
    totals: { products: money(2500), tax: money(0), delivery: money(80), total: money(2580) },
  };
}

/** The confirmation panel; used by the confirmation block and, when a page has none, by the form. */
function Confirmation({ checkout, props, ctx, node }) {
  const { t } = checkout;
  const editing = ctx.mode === "editor";
  const order = editing ? samplePlaced() : checkout.order;
  if (!order) return null;

  const failed = checkout.returned === "failed" || order.payment?.status === "cancelled";
  const paid = order.payment?.status === "paid";
  // Back from the gateway without a clear answer: the payment may have gone through, so no second payment is offered.
  const checking = checkout.returned === "pending" && !paid && !failed;
  const needsPayment = order.payment?.action === "pay" && !failed && !checking;
  const href = safeHref(props.ctaLink);

  return (
    <div className="fn-confirmation flex flex-col gap-3 rounded-[14px] border border-[#cfe8dc] bg-[#f3fbf6] p-5 text-[#131315]" role="status" data-fn-confirmation="" data-testid="order-confirmation" data-preview={checkout.preview || undefined}>
      {checkout.preview ? <p className="m-0 rounded-[8px] bg-[#fff6dd] px-3 py-1.5 text-center text-[13px] font-semibold text-[#8a6100]">{t.previewBanner}</p> : null}
      {failed ? (
        <p className="m-0 rounded-[10px] bg-[#fdecea] px-3.5 py-3 text-[15px] font-semibold text-[#9c2b1f]" role="alert" data-testid="payment-failed">{t.paymentFailed}</p>
      ) : (
        <>
          <h3 className="m-0 flex items-center gap-2 text-[20px] font-bold text-[#12805c]">
            <Icon name="check-circle" size={26} />
            <Text ctx={ctx} node={node} path={["title"]} as="span" value={props.title ?? t.thankYou} placeholder="Thank you message" />
          </h3>
          <Text ctx={ctx} node={node} path={["message"]} as="p" multiline value={props.message ?? t.weWillCall} placeholder="What happens next" className="m-0 text-[15px]" />
        </>
      )}
      {props.showOrderCode !== false && !failed ? (
        <p className="m-0 text-[15px]">
          {t.orderCode}: <strong className="tabular-nums" data-testid="order-code">{order.code}</strong>
        </p>
      ) : null}
      {props.showSummary !== false && !failed ? (
        <div className="flex flex-col gap-1.5 border-t border-[#cfe8dc] pt-3 text-[14px]" data-testid="confirmation-summary">
          {order.items.map((item, index) => (
            <div key={index} className="flex justify-between gap-3">
              <span className="min-w-0 truncate">{item.name}{item.variant ? ` (${item.variant})` : ""} × {item.quantity}</span>
              <span className="tabular-nums">{money(item.total)}</span>
            </div>
          ))}
          <Row label={t.delivery} value={order.totals.delivery.amount === 0 ? t.freeDelivery : money(order.totals.delivery)} muted />
          <Row label={t.payable} value={money(order.totals.total)} strong />
        </div>
      ) : null}
      {!failed ? (
        <p className="m-0 text-[14px] font-semibold" data-testid="payment-state">
          {paid ? t.paymentDone : checking ? t.paymentChecking : order.payment?.method === "cash_on_delivery" ? t.cashOnDelivery : t.paymentPending}
        </p>
      ) : null}
      {needsPayment ? (
        <button type="button" onClick={() => checkout.pay()} disabled={checkout.status === "redirecting"} className="min-h-[50px] rounded-[12px] bg-[var(--fn-accent)] px-5 text-[17px] font-bold text-white disabled:opacity-60" data-testid="pay-now">
          {checkout.status === "redirecting" ? t.paying : t.payNow}
        </button>
      ) : null}
      {checkout.notice ? <Notice checkout={checkout} /> : null}
      {props.ctaText && href ? (
        <a href={href} className="inline-flex min-h-[48px] items-center justify-center rounded-[12px] border-2 border-[var(--fn-accent)] px-5 text-[16px] font-semibold text-[var(--fn-accent)] no-underline">
          {props.ctaText}
        </a>
      ) : null}
    </div>
  );
}

export function OrderConfirmation({ node, props, ctx, rootProps }) {
  const checkout = useCheckout();
  if (!checkout) return null;
  const editing = ctx.mode === "editor";
  if (!editing && !checkout.order) return null;

  return (
    <div {...rootWith(rootProps, "fn-confirmation-wrap")}>
      <Confirmation checkout={checkout} props={props} ctx={ctx} node={node} />
    </div>
  );
}

export { Confirmation };
