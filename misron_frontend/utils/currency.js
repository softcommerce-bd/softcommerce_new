// utils/currency.js
//
// Prices reach the UI two ways: as numbers we format ourselves, and as strings
// the API has already formatted with the Bengali taka sign ("৳2,300"). The store
// displays "৳ 2,300" with a space, so every price passes through here — toTk() for
// numbers, tkText() to rewrite a string that already carries a symbol.

export const TK = "৳";

// Any currency marker the API or older code may have baked into a string.
const SYMBOL_PATTERN = /(?:৳|Tk\.?|TK\.?|BDT|Rs\.?)\s*/gi;

/** Strip whatever currency marker a pre-formatted string carries. */
export const stripCurrency = (value) =>
  String(value ?? "").replace(SYMBOL_PATTERN, "").trim();

/** Format a number as "৳ 2,300". Strings are passed to tkText(). */
export const toTk = (price) => {
  if (price === null || price === undefined || price === "") return `${TK} 0`;
  if (typeof price === "string") return tkText(price);

  const amount = Number(price);
  if (Number.isNaN(amount)) return `${TK} 0`;
  return `${TK} ${amount.toLocaleString()}`;
};

/** Rewrite an already-formatted price string to use the taka sign. */
export const tkText = (value) => {
  const bare = stripCurrency(value);
  return bare ? `${TK} ${bare}` : `${TK} 0`;
};

/** "৳1,299" / "Tk 1,299" -> 1299. Returns 0 when nothing numeric is present. */
export const parseAmount = (value) => {
  if (typeof value === "number") return value;
  const digits = stripCurrency(value).replace(/,/g, "");
  const amount = parseFloat(digits);
  return Number.isNaN(amount) ? 0 : amount;
};
