/**
 * Rules of the order form that do not depend on React: reading what the page
 * asks for, checking what a customer typed, and shaping the request. The same
 * rules run again on the server; checking here only saves a round trip.
 */

const BENGALI_DIGITS = "০১২৩৪৫৬৭৮৯";

/**
 * A Bangladeshi mobile number as 01XXXXXXXXX: Bengali digits, spaces, dashes,
 * +88 and 88 are accepted.
 */
export function normalizePhone(value) {
  let digits = String(value ?? "")
    .replace(/[০-৯]/g, (digit) => String(BENGALI_DIGITS.indexOf(digit)))
    .replace(/\D+/g, "");
  if (digits.length === 13 && digits.startsWith("880")) digits = digits.slice(2);
  if (digits.length === 10 && digits[0] === "1") digits = `0${digits}`;
  return digits;
}

export function isValidPhone(phone) {
  return /^01[3-9][0-9]{8}$/.test(phone);
}

/** Fields of the default Bangladesh form, in order. */
export const DEFAULT_FIELDS = ["name", "phone", "address", "district", "area", "notes"];
const ALWAYS = ["name", "phone", "address", "district"];

/** Label and placeholder keys in the page's own language file, per field. */
export const FIELD_TEXT = {
  name: ["fieldName", "fieldNamePlaceholder"],
  phone: ["fieldPhone", "fieldPhonePlaceholder"],
  email: ["fieldEmail", "fieldEmailPlaceholder"],
  address: ["fieldAddress", "fieldAddressPlaceholder"],
  district: ["fieldDistrict", "fieldDistrictPlaceholder"],
  area: ["fieldArea", "fieldAreaPlaceholder"],
  notes: ["fieldNotes", "fieldNotesPlaceholder"],
};

const INPUT_TYPES = { phone: "tel", email: "email" };
const AUTOCOMPLETE = { name: "name", phone: "tel", email: "email", address: "street-address", area: "address-level2" };

/**
 * The fields the form shows, from the checkout block's settings. Name, phone,
 * address and delivery area are always present and required, whatever the page
 * says; the rest follow the page. With no settings, the default form.
 *
 * @param {object[] | undefined} configured  props.fields of the checkout block
 */
export function resolveFields(configured) {
  const byKey = new Map((configured ?? []).filter((field) => field?.key).map((field) => [field.key, field]));
  const keys = byKey.size ? [...byKey.keys()] : [...DEFAULT_FIELDS];
  for (const key of ALWAYS) if (!keys.includes(key)) keys.push(key);

  return keys
    .map((key) => {
      const field = byKey.get(key) ?? {};
      const forced = ALWAYS.includes(key);
      const enabled = forced ? true : (field.enabled ?? DEFAULT_FIELDS.includes(key));
      return {
        key,
        enabled,
        required: forced ? true : Boolean(field.required),
        // The delivery area is always a choice from the store's own list.
        type: key === "district" ? "select" : (field.type ?? (key === "notes" || key === "address" ? "textarea" : "text")),
        label: field.label || null,
        placeholder: field.placeholder || null,
        width: field.width === "half" ? "half" : "full",
        options: Array.isArray(field.options) ? field.options : [],
        inputType: INPUT_TYPES[key] ?? "text",
        autoComplete: AUTOCOMPLETE[key],
      };
    })
    .filter((field) => field.enabled);
}

/**
 * What is wrong with the form, by field key: a code the page turns into words.
 * An empty object means the form can be sent.
 */
export function validateForm({ values, fields, consent, consentRequired, payment, paymentChoices, otpRequired, otpVerifiedFor }) {
  const errors = {};
  const text = (key) => String(values[key] ?? "").trim();

  for (const field of fields) {
    const value = field.type === "checkbox" ? Boolean(values[field.key]) : text(field.key);
    if (field.required && (field.type === "checkbox" ? !value : value === "")) errors[field.key] = "required";
  }
  if (!errors.name && fields.some((f) => f.key === "name") && text("name").length < 2) errors.name = "required";
  if (!errors.address && fields.some((f) => f.key === "address") && text("address").length < 5) errors.address = "required";
  if (!errors.phone && !isValidPhone(normalizePhone(values.phone))) errors.phone = "invalid_phone";
  if (!errors.email && text("email") !== "" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text("email"))) errors.email = "invalid_email";
  for (const field of fields) {
    if (field.type === "select" && field.key !== "district" && text(field.key) !== "" && !field.options.includes(text(field.key))) errors[field.key] = "invalid_choice";
  }
  if (consentRequired && !consent) errors.consent = "consent_required";
  if (paymentChoices.length === 0 || !paymentChoices.includes(payment)) errors.payment_type = "invalid_payment";
  if (otpRequired && !errors.phone && otpVerifiedFor !== normalizePhone(values.phone)) errors.otp = "otp_required";

  return errors;
}

/** Order of fields on screen, used to focus the first problem. */
export function firstProblem(errors, fields) {
  const order = [...fields.map((f) => f.key), "consent", "payment_type", "otp"];
  return order.find((key) => errors[key]) ?? Object.keys(errors)[0] ?? null;
}

/** Message key (in i18n.js) for an error code on a field. */
export function messageKey(field, code) {
  if (field === "phone" && (code === "required" || code === "invalid_phone")) return "errPhone";
  if (code === "invalid_phone") return "errPhone";
  if (code === "invalid_email") return "errEmail";
  if (code === "consent_required") return "errConsent";
  if (code === "invalid_payment") return "errPayment";
  if (code === "invalid_choice") return "errChoice";
  if (code === "otp_required") return "errOtp";
  if (code === "empty_selection") return "errSelection";
  if (code === "invalid_district") return "errDistrict";
  return { name: "errName", address: "errAddress", district: "errDistrict" }[field] ?? "required";
}

/** The request body the server expects. Choices and details only; never an amount. */
export function buildOrderBody({ values, fields, selections, payment, consent, key, fingerprint }) {
  const customer = {};
  for (const field of fields) {
    const value = values[field.key];
    if (field.key === "district") customer.district_id = value === "" || value == null ? null : Number(value);
    else if (field.key === "phone") customer.phone = normalizePhone(value);
    else if (field.type === "checkbox") customer[field.key] = Boolean(value);
    else customer[field.key] = String(value ?? "").trim();
  }

  const body = {
    idempotency_key: key,
    selections: selections.map(({ key: offerKey, variant, quantity }) => ({ key: offerKey, variant: variant ?? null, quantity })),
    customer,
    payment_type: payment,
    delivery_method: "home_delivery",
    consent: Boolean(consent),
    website: "",
  };
  if (fingerprint) body.quote_fingerprint = fingerprint;
  return body;
}

/**
 * A fresh submission id. Kept for as long as the customer is repeating the
 * same attempt (a retry after a dropped connection), so the server places the
 * order once; replaced after any final answer.
 */
export function newSubmissionKey() {
  const random =
    typeof crypto !== "undefined" && typeof crypto.randomUUID === "function"
      ? crypto.randomUUID()
      : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
  return `fn-${random}`;
}

/** Answers the server gives that no retry of the same submission can change. */
export const FINAL_REFUSALS = new Set([
  "price_changed", "offer_unavailable", "out_of_stock", "item_unavailable", "validation_failed", "idempotency_key_reused",
  "rejected", "blocked", "otp_required", "no_checkout", "checkout_disabled", "checkout_not_ready", "delivery_unsupported", "no_payment_method",
]);

/** Which message key an answer from the server should show. */
export function refusalMessage(code) {
  switch (code) {
    case "price_changed":
      return "priceChanged";
    case "out_of_stock":
    case "offer_unavailable":
      return "gone";
    case "item_unavailable":
      return "unavailableNow";
    case "in_progress":
      return "inProgress";
    case "too_many_attempts":
      return "tooMany";
    case "blocked":
      return "blocked";
    case "otp_required":
      return "errOtp";
    case "checkout_disabled":
    case "checkout_not_ready":
    case "no_checkout":
    case "delivery_unsupported":
    case "no_payment_method":
      return "closedTitle";
    default:
      return "tryAgain";
  }
}
