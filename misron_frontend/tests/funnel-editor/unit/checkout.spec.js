import { expect, test } from "@playwright/test";
import {
  buildOrderBody, DEFAULT_FIELDS, firstProblem, isValidPhone, messageKey, newSubmissionKey, normalizePhone, refusalMessage, resolveFields, validateForm,
} from "../../../funnel/commerce/checkoutRules.js";
import { fill, strings } from "../../../funnel/commerce/i18n.js";

const fields = resolveFields(undefined);
const valid = { name: "Rahim Uddin", phone: "01712345678", address: "House 5, Road 7", district: "12", area: "", notes: "" };
const check = (overrides = {}, extra = {}) =>
  validateForm({ values: { ...valid, ...overrides }, fields, consent: false, consentRequired: false, payment: "cash_on_delivery", paymentChoices: ["cash_on_delivery"], otpRequired: false, otpVerifiedFor: null, ...extra });

test.describe("phone numbers", () => {
  test("Bangladeshi numbers are accepted in every common spelling and stored one way", () => {
    for (const typed of ["01712345678", "017 1234 5678", "017-1234-5678", "+8801712345678", "8801712345678", "০১৭১২৩৪৫৬৭৮", "+৮৮০১৭১২৩৪৫৬৭৮", "1712345678"]) {
      expect(normalizePhone(typed), typed).toBe("01712345678");
    }
  });

  test("anything that is not an 11 digit mobile number is refused", () => {
    for (const typed of ["", "12345", "0171234567", "017123456789", "02123456789", "01212345678", "abcdefghijk", null, undefined]) {
      expect(isValidPhone(normalizePhone(typed)), String(typed)).toBe(false);
    }
    expect(isValidPhone("01312345678")).toBe(true);
    expect(isValidPhone("01912345678")).toBe(true);
  });
});

test.describe("which fields the form shows", () => {
  test("no settings gives the default Bangladesh form", () => {
    expect(fields.map((f) => f.key)).toEqual(DEFAULT_FIELDS);
    expect(fields.filter((f) => f.required).map((f) => f.key)).toEqual(["name", "phone", "address", "district"]);
    expect(fields.find((f) => f.key === "phone")).toMatchObject({ inputType: "tel", autoComplete: "tel" });
    expect(fields.find((f) => f.key === "district").type).toBe("select");
    expect(fields.find((f) => f.key === "notes").type).toBe("textarea");
  });

  test("the page can relabel, reorder, hide and require optional fields", () => {
    const resolved = resolveFields([
      { key: "phone", enabled: true, required: true, label: "ফোন" },
      { key: "name", enabled: true, required: true },
      { key: "email", enabled: true, required: true, placeholder: "you@mail.com" },
      { key: "notes", enabled: false },
      { key: "custom_1", enabled: true, required: false, label: "Gift message", type: "text", width: "half" },
    ]);
    // Reordered as listed, notes hidden; address and district the page forgot are put back.
    expect(resolved.map((f) => f.key)).toEqual(["phone", "name", "email", "custom_1", "address", "district"]);
    expect(resolved[0].label).toBe("ফোন");
    expect(resolved.find((f) => f.key === "email")).toMatchObject({ required: true, placeholder: "you@mail.com" });
    expect(resolved.find((f) => f.key === "custom_1")).toMatchObject({ width: "half", required: false });
  });

  test("required operational fields cannot be switched off or made optional", () => {
    const resolved = resolveFields([
      { key: "name", enabled: false, required: false },
      { key: "phone", enabled: false },
      { key: "address", enabled: false, required: false },
      { key: "district", enabled: false, required: false },
    ]);
    expect(resolved.map((f) => [f.key, f.required])).toEqual([["name", true], ["phone", true], ["address", true], ["district", true]]);
  });
});

test.describe("checking the form", () => {
  test("a complete form passes", () => {
    expect(check()).toEqual({});
  });

  test("each missing or wrong detail is reported on its own field", () => {
    const errors = check({ name: " ", phone: "12345", address: "x", district: "" });
    expect(errors).toEqual({ name: "required", phone: "invalid_phone", address: "required", district: "required" });
    expect(messageKey("phone", errors.phone)).toBe("errPhone");
    expect(messageKey("name", errors.name)).toBe("errName");
    expect(messageKey("district", errors.district)).toBe("errDistrict");
  });

  test("optional fields only matter when the page asks for them", () => {
    const withEmail = resolveFields([{ key: "name" }, { key: "phone" }, { key: "address" }, { key: "district" }, { key: "email", enabled: true, required: true }]);
    const values = { ...valid, email: "" };
    expect(validateForm({ values, fields: withEmail, consent: false, consentRequired: false, payment: "cash_on_delivery", paymentChoices: ["cash_on_delivery"] }).email).toBe("required");
    expect(validateForm({ values: { ...values, email: "nope" }, fields: withEmail, consent: false, consentRequired: false, payment: "cash_on_delivery", paymentChoices: ["cash_on_delivery"] }).email).toBe("invalid_email");
    expect(validateForm({ values: { ...values, email: "a@b.co" }, fields: withEmail, consent: false, consentRequired: false, payment: "cash_on_delivery", paymentChoices: ["cash_on_delivery"] })).toEqual({});
  });

  test("consent, payment choice and phone verification", () => {
    expect(check({}, { consentRequired: true, consent: false })).toEqual({ consent: "consent_required" });
    expect(check({}, { consentRequired: true, consent: true })).toEqual({});
    expect(check({}, { payment: "bitcoin" })).toEqual({ payment_type: "invalid_payment" });
    expect(check({}, { paymentChoices: [] })).toEqual({ payment_type: "invalid_payment" });
    expect(check({}, { otpRequired: true, otpVerifiedFor: null })).toEqual({ otp: "otp_required" });
    expect(check({}, { otpRequired: true, otpVerifiedFor: "01712345678" })).toEqual({});
    // A number verified earlier does not cover a different number typed afterwards.
    expect(check({ phone: "01898765432" }, { otpRequired: true, otpVerifiedFor: "01712345678" })).toEqual({ otp: "otp_required" });
  });

  test("the first problem on screen is focused", () => {
    expect(firstProblem({ address: "required", name: "required" }, fields)).toBe("name");
    expect(firstProblem({ payment_type: "invalid_payment", consent: "consent_required" }, fields)).toBe("consent");
    expect(firstProblem({}, fields)).toBeNull();
  });
});

test.describe("the request", () => {
  const selections = [{ key: "offer_one", variant: "M", quantity: 2 }];

  test("carries choices and details, never an amount", () => {
    const body = buildOrderBody({
      values: { ...valid, phone: "+৮৮০১৭১২৩৪৫৬৭৮", notes: "  call first  " }, fields, selections, payment: "cash_on_delivery", consent: true, key: "fn-abc", fingerprint: "a".repeat(40),
    });

    expect(body).toEqual({
      idempotency_key: "fn-abc",
      selections: [{ key: "offer_one", variant: "M", quantity: 2 }],
      customer: { name: "Rahim Uddin", phone: "01712345678", address: "House 5, Road 7", district_id: 12, area: "", notes: "call first" },
      payment_type: "cash_on_delivery",
      delivery_method: "home_delivery",
      consent: true,
      website: "",
      quote_fingerprint: "a".repeat(40),
    });
    expect(JSON.stringify(body)).not.toMatch(/price|total|amount|discount/i);
  });

  test("the submission id is stable text the server accepts, and different each time", () => {
    const a = newSubmissionKey();
    const b = newSubmissionKey();
    expect(a).not.toBe(b);
    for (const key of [a, b]) expect(key).toMatch(/^[A-Za-z0-9_\-:.]{16,100}$/);
  });
});

test.describe("what the page says when the server refuses", () => {
  test("every refusal has wording in both languages", () => {
    const codes = ["price_changed", "out_of_stock", "offer_unavailable", "item_unavailable", "in_progress", "too_many_attempts", "blocked", "otp_required", "checkout_disabled", "something_else"];
    for (const language of ["bn", "en"]) {
      const t = strings(language);
      for (const code of codes) expect(t[refusalMessage(code)], `${language}/${code}`).toBeTruthy();
    }
    expect(refusalMessage("out_of_stock")).toBe("gone");
    expect(refusalMessage("unknown")).toBe("tryAgain");
  });

  test("Bengali and English wording stay in step", () => {
    const bn = Object.keys(strings("bn"));
    const en = Object.keys(strings("en"));
    expect(bn.filter((key) => !en.includes(key))).toEqual([]);
    expect(en.filter((key) => !bn.includes(key))).toEqual([]);
    expect(fill(strings("bn").priceChanged, { t: "৳১,২০০" })).toContain("৳১,২০০");
    expect(fill(strings("en").gone, { p: "Blender" })).toContain("Blender");
  });
});
