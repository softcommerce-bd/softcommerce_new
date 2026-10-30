# Phase 4 — Checkout, orders and payment

Status: implemented and tested on the test databases. **Switched off by default**
(`FUNNEL_CHECKOUT_ENABLED=false`) and not applied to the main `misron` database.

A funnel page takes an order the same way the website does: the order is an ordinary row of
`combined_orders`, `orders` and `order_details`, so it appears in the existing Orders module and
moves through the normal fulfilment steps. There is no second orders list.

---

## 1. What has to be in place before it can take an order

| Requirement | Why | Where |
|---|---|---|
| `FUNNEL_CHECKOUT_ENABLED=true` in `.env` | The switch. While off, every checkout request is refused with `checkout_disabled` and the page says ordering is not open | `config/funnel.php` |
| `orders.funnel_id`, `orders.funnel_version_id` | Which funnel and which published version an order came from | `sqlupdates/sales_funnel_v2_orders.sql` (approved in principle, **not applied** to `misron`) |
| `funnel_checkout_attempts` table | Idempotency and the record of what the customer agreed to | `sqlupdates/sales_funnel_v3_checkout.sql` |
| Stock-table changes (recommended) | Row locks and rollback for purchase batches | `sqlupdates/inventory_v1_*.sql`, see the inventory migration plan |
| Published page with an order form | The form defines what is asked | The editor |

If either order column or the attempts table is missing, checkout refuses with `checkout_not_ready`
instead of failing half-way. The publish check lists the same conditions as warnings, so the merchant
sees them before going live. Nothing here changes how the existing website checkout behaves.

## 2. The customer's journey

1. They open `/funnel/<slug>`. The page loads the published version, live offers, and the order form
   settings (`GET {slug}/checkout/config`: fields, delivery areas with today's charge, the payment
   methods that work, whether a phone code is required).
2. They choose a product or bundle. The summary is priced by the server on every change
   (`POST {slug}/quote`). Choosing a delivery area adds the delivery charge and the total to pay.
3. They fill in the form and press the order button. The browser sends **choices and details only**
   (offer key, variation, quantity, name, phone, address, area, notes, payment method, consent) plus a
   submission id and the fingerprint of the total they saw. It never sends an amount.
4. The server re-prices everything, refuses a stale total, takes the stock, writes the order and
   returns a receipt. Cash on delivery is finished; online payment continues to the payment page.
5. The page shows the confirmation (a block the merchant can style, or a standard panel).

## 3. API

All under `/api/v2/funnels/{slug}`; only the **published** version is ever read.

| Call | Purpose | Limit (per IP, own counter each) |
|---|---|---|
| `GET /` | Published document and page details (no internal name) | 240/min |
| `GET /checkout/config` | Form fields, delivery areas, payment methods, `enabled` and `reason` | 120/min |
| `POST /quote` | Price a selection. With `district_id`: delivery, `totals.payable`, and a fingerprint that covers delivery | 240/min |
| `POST /checkout` | Place the order (idempotent) | 20/min, plus 6 new orders per phone number per 10 min |
| `GET /orders/{receipt}` | Summary of a placed order (no address, no phone) | 60/min |
| `POST /orders/{receipt}/pay` | Payment link of an unpaid online order | 20/min |

Each route has its own rate-limit counter (`funnel-*` named limiters). A first version shared one
counter per client address across all of them, which would have let a page that re-prices on every
change use up the allowance meant for the order button; a test now guards that.

### Errors

Every refusal is `{ result: false, code, message, errors? }`. The page words each `code` itself, in the
page's language; `message` is an English fallback.

| HTTP | `code` | Meaning | The customer sees |
|---|---|---|---|
| 422 | `validation_failed` | Field problems in `errors.{field}.code` | The message next to the field |
| 422 | `rejected` | Hidden spam field filled in | Generic "could not place the order" |
| 422 | `idempotency_key_reused` | Same submission id, different content | Reload and try again |
| 404 | `not_found`, `no_checkout` | Page not live, or it has no order form | Not-found page / form hidden |
| 409 | `price_changed` | The total moved since it was shown. Carries the new quote | "The price changed. New total ৳X. Press again to confirm." |
| 409 | `offer_unavailable`, `out_of_stock` | Stock or offer gone. Names the product | "Sorry, X is no longer available…" and the page re-reads stock |
| 409 | `item_unavailable` | Stock on the shelf but its cost records are missing (see the inventory report) | "X cannot be ordered right now" |
| 409 | `in_progress` | The same submission is still being placed. `Retry-After: 2` | "Your order is being placed…" and the page retries by itself |
| 403 | `blocked`, `otp_required` | Blocked address; phone code needed | Contact us / verify your number |
| 429 | `too_many_attempts` | Per-number or per-address limit | "Too many attempts, wait a few minutes" |
| 503 | `checkout_disabled`, `checkout_not_ready`, `delivery_unsupported`, `no_payment_method`, `inventory_busy` | Not set up, or briefly busy. `inventory_busy` has `Retry-After` | "Ordering is closed at the moment" / "Try again" |
| 500 | `checkout_failed` | Anything unexpected. Logged; nothing was charged or taken | "We could not place your order. You have not been charged." |

## 4. How it joins the existing order system

Written exactly as the website's `store()` does, with these deliberate choices:

| Field | Value |
|---|---|
| `orders.order_from` | `app`, the same as website orders. Admin screens branch on `pos` and `web`, so an unfamiliar value could change how an order is edited. The funnel is identified by `funnel_id`/`funnel_version_id` instead |
| `orders.code` | `Order::generateUniqueCode()` (six digits, unique), as POS and admin orders use. The website's own `store()` draws a two-digit random number, which collides |
| `orders.guest_id` | A random positive integer (the website uses the browser's temporary id); it only marks the order as placed without a login, as the payment return needs |
| `orders.shipping_address` | JSON with the website's keys (`name, email, phone, address, city, country, postal_code, order_note, delivery_type`); `city` is the delivery area's name |
| `orders.order_note` | The customer's note: the couriers read this first |
| `orders.additional_info` | Plain text for the admin screen: `Sales funnel: <title> (v<n>)`, then each extra field's answer and the consent given |
| `order_details` | One row per physical item: each item of a bundle, then free gifts at price 0. `price` is the line total after the funnel discount (exact, allocated across a bundle), `tax` is tax on that price, `shipping_cost` is the line's share of the delivery charge, `buying_price`/`profit` come from the FIFO batches, `fifo_batch_info` records which batches |
| Customer | Found by phone (stored with or without `+88`/`88`), then by email, else created. Unlike the website, a new customer gets an **unguessable password**, not their phone number |
| `blocked_ips` | Updated the same way, so the admin's address review covers funnel orders; a blocked address cannot order |
| Notifications | None at placement, the same as the website's API checkout. When an online payment succeeds the existing `checkout_done()` runs and sends the usual notification |
| Totals | `grand_total = Σ(price + tax + shipping)`, checked against the quote before the order is committed; a mismatch aborts the order |

Cancelling or failing payment goes through `StockLedger::give()`: stock and the original batches return
once, whoever cancels.

## 5. Prices

- Product prices, offers, tax and stock come from the Phase 3 offer engine on the published version.
- **Tax** is figured on the price the customer pays after the funnel discount (the website figures it on
  the price after the product's own discount). Order lines store that figure.
- **Delivery** uses the store's own rule, `getShippingCost()`, fed with in-memory rows for the order's
  lines, so a funnel order costs exactly what the same basket costs on the website (the store runs
  area-wise shipping: the area's `cost`, split across lines). An offer marked "free delivery" waives it.
  Carrier-wise shipping is not supported (it needs a customer choice a funnel does not offer): the form
  refuses to take orders and the publish check says why. The website's free-shipping banner
  (`free_shipping_amount`) is display text only; nothing server-side applies it, so neither does the funnel.
- **The fingerprint** covers products, tax, delivery and district. If any moves before the order is
  placed, the customer must confirm the new total with a fresh submission.
- **Immutable history:** the order lines keep the prices paid. `funnel_checkout_attempts.quote_snapshot`
  keeps the agreed quote (offer rules, lines, totals, delivery, version) unchanged.
- **Coupons:** funnel offers are not combined with coupons. The coupon block exists in the server
  registry but is not offered in the editor, and checkout reads no coupon.

## 6. Repeats, retries and failures

| Situation | What happens |
|---|---|
| Button pressed twice | The first request disables the button; a second request with the same submission id replays the first result |
| Reply lost on a bad connection | The page keeps the **same** submission id and shows "press again, your order will not be placed twice". The server returns the order it already has |
| A submission still running | `in_progress`; the page waits `Retry-After` and retries by itself |
| A submission that died mid-way | After two minutes a retry takes it over; stock and batches roll back with the transaction (or are given back by hand on the old MyISAM schema) |
| Refused for a final reason (price, stock, validation) | The submission id is retired; the next press is a new submission. A replay of a refused id gets the same refusal |
| Same id, different content | Refused (`idempotency_key_reused`) |
| Two customers, last unit | Exactly one order; the other is told. Covered by multi-process tests |
| A failure after stock was taken | One transaction: order rows, customer and stock roll back together |

## 7. Payment

- **Cash on delivery**: offered when the store's cash payment setting is on. The order is placed unpaid.
- **Online (Paystation)**: offered only when the store has it switched on and credentialed. The order is
  placed unpaid, then `POST …/orders/{receipt}/pay` returns the gateway link through the existing
  `PaystationController::begin`. That wrapper refuses a paid or cancelled order, so asking again after a
  failed attempt is safe and a double charge cannot be started from the funnel.
- The gateway's callback (`PaystationController::callback`) sends a funnel customer back to
  `/funnel/<slug>?receipt=…&payment=success|failed`. On success the page shows the paid confirmation; on
  failure the existing code cancels the order and returns the stock, and the page says so; the customer
  places a new order.
- bKash, Nagad, SSLCommerz and the others in the admin panel are **not** offered. On the website they are
  reachable only by a signed-in customer; wiring them for guests would be new payment behaviour. The merchant
  can narrow the list on a page (`payment_method_selector.allowed`) but never widen it.
- **Not built:** automatic expiry of an online order whose customer never returns from the gateway. Until the
  callback or a person cancels it, its stock stays held, exactly as on the website today.

## 8. The order form in the editor

Library group **Order form**: *Order form* (the container), *Order summary*, *Delivery charge*,
*Payment choice*, *Order button*, *Order confirmation*. Adding the form brings a complete one (fields,
summary, payment choice, button) with Bengali sample copy.

- The merchant can relabel, reorder, hide and require optional fields, add up to five extra fields
  (text, long text, choice list, tick box), choose one or two columns, and ask for a consent tick.
- **Name, mobile number, address and delivery area are always asked and required**, in the editor, in the
  validator and in the server, so no setting can produce an order that cannot be delivered.
- A form needs an **order button inside it** to publish (`checkout_no_button`).
- Colours: the container (background, border, text) and the order button are styleable per device; fields
  follow the page's accent colour.
- Editor: the form is drawn but inert. Preview: it works and only simulates the confirmation (clearly
  marked); **no order is created**. Live: real orders.
- Mobile: 16px field text (no zoom on focus), 44px+ fields, 54px button, no sideways scroll (tested at 390px).
- Language: Bengali or English per page; Bengali digits are accepted in the phone number.

## 9. Security

- Everything is validated on the server: fields, payment method, district, quantities, prices, offers.
  The browser's amounts, if sent, are ignored (tested).
- A hidden honeypot field, an address limit per route, six orders per phone number per ten minutes, a
  blocked-address check, and the store's own phone code when it is switched on.
- Receipt: an encrypted token naming the order and the funnel, valid 48 hours. It opens the summary and the
  payment link of that order only, on that funnel only; forged, expired or other-funnel tokens get 404.
- Responses never contain the customer's address or phone. The attempts table holds no personal data.
- Free text is stored as text and escaped on display; the checkout JSON body is capped at 64 KB.
- No internal SQL text reaches a customer: a deadlock answers "try again", anything else a generic message
  (the details go to the log).

## 10. Not done, on purpose or for later

- Public page caching, SEO and social metadata, templates, scheduling, preview links (Phase 5). The page
  asks search engines to stay away until then. Its HTTP status for a missing page is 200 with the store's
  not-found page, because the storefront's `loading` boundary starts streaming first; Phase 5 should
  answer 404 before that.
- Analytics events and server-side tracking (Phase 6).
- Pickup points and carrier choice; upsell steps; abandoned-order recovery.
- Automatic expiry of unpaid online orders.
- Moving the other stock writers onto the ledger (see the writers audit).

## 11. Tests

| Suite | What it covers |
|---|---|
| `tests/Feature/Funnel/FunnelCheckoutTest.php` (30) | Order as an ordinary order, forged amounts, delivery per area and free delivery, fingerprint with delivery, stock refusal and rollback, replay and key reuse, takeover of a dead attempt, validation and Bengali digits, configurable fields, honeypot and malformed bodies, blocked address and per-phone limit, phone code, payment options and the payment link rules, receipts, gateway return, gifts, rate-limit separation, public page endpoint, publish warnings |
| `tests/Unit/Funnel/PageValidatorTest.php` | Order button rule |
| `tests/Concurrency/StockConcurrencyTest.php` | 5 taps on the order button, 8 customers on the last unit, funnel vs website for the last unit |
| `tests/funnel-editor/unit/checkout.spec.js` (13) | Phone normalisation, field resolution, validation, request shape, wording in both languages |
| `tests/funnel-editor/e2e/checkout.spec.js` (10) | Live order with numbers checked in the database, Bengali validation, lost reply and repeat, price change, stock gone, closed form, not-found page, phone layout, building the form in the editor then preview then publish then order, publish refusal |
