"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useReducer, useRef } from "react";
import { useCommerce } from "./CommerceProvider.jsx";
import { configFromDocument } from "./checkoutAdapters.js";
import { sectionsOf, walk } from "../core/tree.js";
import {
  buildOrderBody, FINAL_REFUSALS, firstProblem, newSubmissionKey, normalizePhone, refusalMessage, resolveFields, validateForm,
} from "./checkoutRules.js";

/**
 * The order form's state, shared by the form, its summary, its payment choice,
 * its button and the confirmation area: what the customer has typed, what is
 * wrong with it, whether an order is being placed, and the order once placed.
 *
 * Safety rules kept here:
 *  - one submission id per attempt, kept while the customer is only retrying
 *    after a dropped connection (so an order is never placed twice) and replaced
 *    after any final answer;
 *  - no amount is ever sent, only choices and details;
 *  - the button cannot be pressed twice while a request is in flight.
 *
 * mode "editor": the form is drawn but cannot be used. "preview": it works, but
 * no order is created (the adapter only simulates the answer). "public": real.
 */

const CheckoutContext = createContext(null);

export function useCheckout() {
  return useContext(CheckoutContext);
}

const INITIAL = {
  loaded: false,
  loadFailed: false,
  config: null,
  values: {},
  consent: false,
  payment: null,
  errors: {},
  status: "idle", // idle | submitting | placed | redirecting
  notice: null,
  order: null,
  preview: false,
  returned: null, // success | failed | pending, when sent back by the payment gateway
  otp: { state: "idle", phone: null, error: null },
};

function normaliseConfig(raw) {
  const checkout = raw.checkout ?? {};
  return {
    enabled: Boolean(raw.enabled),
    reason: raw.reason ?? null,
    fields: resolveFields(checkout.fields),
    requireConsent: Boolean(checkout.require_consent),
    consentText: checkout.consent_text ?? "",
    methods: raw.payment_methods ?? [],
    districts: raw.districts ?? [],
    otpRequired: Boolean(raw.otp_required),
  };
}

function reducer(state, action) {
  switch (action.type) {
    case "loaded": {
      const config = action.config;
      const keep = config.methods.some((m) => m.key === state.payment);
      return { ...state, loaded: true, loadFailed: false, config, payment: keep ? state.payment : (config.methods[0]?.key ?? null) };
    }
    case "load_failed":
      return { ...state, loaded: true, loadFailed: true };
    case "value": {
      const errors = { ...state.errors };
      delete errors[action.field];
      const otp = action.field === "phone" && state.otp.state !== "idle" && normalizePhone(action.value) !== state.otp.phone ? { state: "idle", phone: null, error: null } : state.otp;
      return { ...state, values: { ...state.values, [action.field]: action.value }, errors, otp };
    }
    case "consent": {
      const errors = { ...state.errors };
      delete errors.consent;
      return { ...state, consent: action.value, errors };
    }
    case "payment": {
      const errors = { ...state.errors };
      delete errors.payment_type;
      return { ...state, payment: action.value, errors };
    }
    case "errors":
      return { ...state, errors: action.errors };
    case "notice":
      return { ...state, notice: action.notice };
    case "submitting":
      return { ...state, status: "submitting", notice: null };
    case "idle":
      return { ...state, status: "idle" };
    case "placed":
      return { ...state, status: "placed", order: action.order, preview: action.preview, notice: null, errors: {} };
    case "redirecting":
      return { ...state, status: "redirecting", notice: null };
    case "returned":
      return { ...state, status: "placed", order: action.order, returned: action.returned, notice: null };
    case "otp":
      return { ...state, otp: { ...state.otp, ...action.otp } };
    default:
      return state;
  }
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * @param {object} props
 * @param {object} props.document        the page document
 * @param {object} props.adapter         publicCheckout() or previewCheckout()
 * @param {"editor" | "preview" | "public"} props.mode
 */
export function CheckoutProvider({ document, adapter, mode, children }) {
  const commerce = useCommerce();
  const [state, dispatch] = useReducer(reducer, INITIAL);
  const stateRef = useRef(state);
  const commerceRef = useRef(commerce);
  const adapterRef = useRef(adapter);
  const documentRef = useRef(document);
  const keyRef = useRef(null);
  const busyRef = useRef(false);
  useEffect(() => {
    stateRef.current = state;
    commerceRef.current = commerce;
    adapterRef.current = adapter;
    documentRef.current = document;
  });

  const interactive = mode !== "editor";
  // A page without its own confirmation block shows the confirmation inside the form.
  const hasConfirmationBlock = useMemo(() => {
    let found = false;
    walk(sectionsOf(document), (node) => {
      if (node.type === "order_confirmation") found = true;
    });
    return found;
  }, [document]);

  // What the page's checkout settings say; in the editor this follows every edit.
  const configKey = useMemo(() => (mode === "public" ? "public" : JSON.stringify(configFromDocument(document))), [document, mode]);

  // ── Load what the form asks for ─────────────────────────────────────────
  useEffect(() => {
    let cancelled = false;
    (async () => {
      const result = await adapterRef.current.config({ document: documentRef.current });
      if (cancelled) return;
      if (result.status === 200 && result.body?.result) dispatch({ type: "loaded", config: normaliseConfig(result.body) });
      else dispatch({ type: "load_failed" });
    })();
    return () => {
      cancelled = true;
    };
  }, [adapter, configKey]);

  // ── Back from the payment gateway ───────────────────────────────────────
  useEffect(() => {
    if (mode !== "public") return undefined;
    let cancelled = false;
    (async () => {
      await Promise.resolve();
      const back = adapterRef.current.takeReturn?.();
      if (!back?.receipt) return;
      const result = await adapterRef.current.order(back.receipt);
      if (cancelled) return;
      if (result.status === 200 && result.body?.order) {
        // "pending": the gateway gave no clear answer yet. The order is kept and must not be paid again.
        const returned = back.payment === "failed" ? "failed" : back.payment === "pending" ? "pending" : "success";
        dispatch({ type: "returned", order: result.body.order, returned });
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [mode, adapter]);

  // ── Editing the form ────────────────────────────────────────────────────
  const setValue = useCallback((field, value) => {
    dispatch({ type: "value", field, value });
    if (field === "district") commerceRef.current?.setDistrict(value ? Number(value) : null);
  }, []);
  const setConsent = useCallback((value) => dispatch({ type: "consent", value }), []);
  const setPayment = useCallback((value) => dispatch({ type: "payment", value }), []);
  const dismissNotice = useCallback(() => dispatch({ type: "notice", notice: null }), []);

  // ── Phone verification ──────────────────────────────────────────────────
  const sendOtp = useCallback(async () => {
    const phone = normalizePhone(stateRef.current.values.phone);
    const errors = validateForm({ values: { phone }, fields: [], consent: true, consentRequired: false, payment: "x", paymentChoices: ["x"] });
    if (errors.phone) {
      dispatch({ type: "errors", errors: { ...stateRef.current.errors, phone: errors.phone } });
      return;
    }
    dispatch({ type: "otp", otp: { state: "sending", phone, error: null } });
    const result = await adapterRef.current.sendOtp(phone);
    dispatch({ type: "otp", otp: result.body?.result ? { state: "sent" } : { state: "idle", error: "tryAgain" } });
  }, []);

  const verifyOtp = useCallback(async (code) => {
    const phone = stateRef.current.otp.phone ?? normalizePhone(stateRef.current.values.phone);
    dispatch({ type: "otp", otp: { state: "verifying", error: null } });
    const result = await adapterRef.current.verifyOtp(phone, String(code ?? "").trim());
    dispatch({ type: "otp", otp: result.body?.result ? { state: "verified", phone } : { state: "sent", error: "otpWrong" } });
  }, []);

  // ── Payment of an online order ──────────────────────────────────────────
  const pay = useCallback(async (receiptOverride) => {
    const receipt = receiptOverride ?? stateRef.current.order?.receipt;
    if (!receipt || mode === "preview") return;
    dispatch({ type: "redirecting" });
    const result = await adapterRef.current.pay(receipt);
    if (result.body?.result && result.body.url) {
      window.location.assign(result.body.url);
      return;
    }
    dispatch({ type: "placed", order: stateRef.current.order, preview: false });
    dispatch({ type: "notice", notice: { kind: "error", key: result.body?.code === "order_cancelled" ? "paymentFailed" : "tryAgain" } });
  }, [mode]);

  // ── Sending the order ───────────────────────────────────────────────────
  const submit = useCallback(async (attempt = 0) => {
    if (!interactive || (busyRef.current && attempt === 0)) return;
    const s = stateRef.current;
    const live = commerceRef.current;
    if (!s.config || !live) return;

    const errors = validateForm({
      values: s.values,
      fields: s.config.fields,
      consent: s.consent,
      consentRequired: s.config.requireConsent,
      payment: s.payment,
      paymentChoices: s.config.methods.map((m) => m.key),
      otpRequired: s.config.otpRequired,
      otpVerifiedFor: s.otp.state === "verified" ? s.otp.phone : null,
    });
    const selections = live.selections ?? [];
    if (selections.length === 0) errors.selections = "empty_selection";
    if (Object.keys(errors).length > 0) {
      dispatch({ type: "errors", errors });
      dispatch({ type: "notice", notice: errors.selections ? { kind: "error", key: "errSelection" } : null });
      const first = firstProblem(errors, s.config.fields);
      window.document.querySelector(`[data-fn-field="${first}"]`)?.scrollIntoView({ block: "center", behavior: "smooth" });
      return;
    }
    // A price that is still being worked out settles within a moment: wait for it
    // rather than making the customer press the button a second time.
    for (let waited = 0; commerceRef.current?.quoting && waited < 5000; waited += 100) await sleep(100);
    const settled = commerceRef.current;
    if (settled.quoting || !settled.quote?.valid) {
      dispatch({ type: "notice", notice: { kind: "info", key: "calculating" } });
      return;
    }

    busyRef.current = true;
    dispatch({ type: "submitting" });
    keyRef.current = keyRef.current ?? newSubmissionKey();
    const body = buildOrderBody({
      values: s.values, fields: s.config.fields, selections: settled.selections ?? selections, payment: s.payment, consent: s.consent, key: keyRef.current, fingerprint: settled.quote.fingerprint,
    });

    const result = await adapterRef.current.place(body, { quote: settled.quote });
    const data = result.body;

    try {
      if (result.status === 0) {
        // Dropped connection: the same submission id is reused, so a retry cannot double-order.
        dispatch({ type: "idle" });
        dispatch({ type: "notice", notice: { kind: "error", key: "networkProblem" } });
        return;
      }

      if ((result.status === 200 || result.status === 201) && data?.result) {
        keyRef.current = null;
        dispatch({ type: "placed", order: data.order, preview: Boolean(data.preview) });
        window.document.querySelector("[data-fn-confirmation]")?.scrollIntoView({ block: "start", behavior: "smooth" });
        if (data.order?.payment?.action === "pay" && !data.preview) {
          busyRef.current = false;
          await pay(data.order.receipt);
        }
        return;
      }

      // The web server's own rate limit answers without a code.
      const code = result.status === 429 ? "too_many_attempts" : data?.code;
      if (code === "in_progress" && attempt < 6) {
        dispatch({ type: "notice", notice: { kind: "info", key: "inProgress" } });
        await sleep((result.retryAfter || 2) * 1000);
        busyRef.current = false;
        await submit(attempt + 1);
        return;
      }

      dispatch({ type: "idle" });
      if (code === "validation_failed" && data?.errors) {
        keyRef.current = null;
        const mapped = {};
        for (const [field, problem] of Object.entries(data.errors)) mapped[field] = problem.code;
        dispatch({ type: "errors", errors: mapped });
        window.document.querySelector(`[data-fn-field="${firstProblem(mapped, s.config.fields)}"]`)?.scrollIntoView({ block: "center", behavior: "smooth" });
        return;
      }
      if (FINAL_REFUSALS.has(code)) {
        keyRef.current = null;
        // Prices and stock may have moved: show the page as it is now.
        if (["price_changed", "out_of_stock", "offer_unavailable", "item_unavailable"].includes(code)) live.refresh();
        const payable = data?.quote?.totals?.payable ?? data?.quote?.totals?.total_before_delivery;
        dispatch({ type: "notice", notice: { kind: "error", key: refusalMessage(code), vars: { t: payable?.text ?? "", p: data?.product ?? "" } } });
        return;
      }
      dispatch({ type: "notice", notice: { kind: "error", key: refusalMessage(code) } });
    } finally {
      busyRef.current = false;
    }
  }, [interactive, pay]);

  const quote = commerce?.quote ?? null;
  const value = useMemo(
    () => ({
      mode,
      interactive,
      t: commerce?.t,
      language: commerce?.language,
      ...state,
      hasConfirmationBlock,
      fields: state.config?.fields ?? [],
      districts: state.config?.districts ?? [],
      methods: state.config?.methods ?? [],
      requireConsent: state.config?.requireConsent ?? false,
      consentText: state.config?.consentText ?? "",
      otpRequired: state.config?.otpRequired ?? false,
      open: state.config ? state.config.enabled : false,
      closedReason: state.config?.reason ?? null,
      quote,
      quoting: commerce?.quoting ?? false,
      setValue,
      setConsent,
      setPayment,
      dismissNotice,
      submit,
      pay,
      sendOtp,
      verifyOtp,
    }),
    [mode, interactive, hasConfirmationBlock, commerce?.t, commerce?.language, commerce?.quoting, state, quote, setValue, setConsent, setPayment, dismissNotice, submit, pay, sendOtp, verifyOtp],
  );

  return <CheckoutContext.Provider value={value}>{children}</CheckoutContext.Provider>;
}
