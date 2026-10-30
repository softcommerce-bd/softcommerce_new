# Phase 6 — Analytics, conversion tracking, performance, security, production readiness

Last updated: 2026-10-24. Branch `feature/sales-funnel-builder` in both repositories.
Deployment steps are in [DEPLOYMENT.md](DEPLOYMENT.md). Nothing in this phase was applied to the main
`misron` database; every database change ran on the `*_test` databases only.

---

## 1. Funnel analytics dashboard

**Admin → Sales Funnels → Analytics** (`/admin/funnels/analytics`), permission `view_funnel_analytics`
(new, section "Sales Funnel"; revenue is visible only to roles that have it). Each funnel's action menu links to
its own report.

Filters: funnel (or all), date range (quick 7/30/90 days, 12 months; at most 400 days), trend by day/week/month.
The same report is available as JSON (`Accept: application/json`).

| Figure | Source | Definition |
|---|---|---|
| Page views, product views, product selections | `funnel_events` | Events sent by the live page (section 2). |
| Unique visitors | `funnel_events` | Distinct anonymous visitor ids with a page view. |
| Checkout started | `funnel_events` | Once per visit. |
| Orders placed | `orders` | Every funnel checkout (one combined order) in the period, cancelled ones included. **Test orders left out.** |
| Paid | `orders` | Every order of the checkout has `payment_status = paid` (gateway-confirmed online payment, or cash recorded by staff). |
| Delivered | `orders` | Every order of the checkout is `delivered`. |
| Cancelled | `orders` | Any order of the checkout is `cancelled`. |
| Total order value | `orders` | `grand_total` of checkouts not cancelled. |
| Confirmed revenue | `orders` | Not cancelled **and** (paid **or** delivered). Cash on delivery counts once delivered. |
| Average order value | `orders` | Order value ÷ checkouts not cancelled. |
| Conversion rate | both | Visitors who placed an order ÷ visitors. An order counts when its visitor id was seen on the page, so orders from browsers that sent no events cannot push it over 100 %; the dashboard shows how many orders had no seen visit. |
| COD vs online | `orders` | By payment method; count and value. |
| Devices, traffic sources | events + `funnel_order_attributions` | Visitors from events, orders and confirmed revenue from the attributed orders. |
| Campaigns | `funnel_order_attributions` | By `utm_campaign`. |
| Trend | both | Visitors (events), orders, order value and confirmed revenue (orders) per day/week/month. |
| By funnel | both | When "All funnels" is selected; top 15 with "Show all". |
| Needs attention | `orders` | Checkouts paid online after they were cancelled (refund or restore by hand). |

**Browser events never decide** whether an order exists, is paid, delivered or cancelled, or how much it is worth.
There is no event type for those; a test proves a fake "purchase" event is dropped.

Charts use the admin's own Chart.js (no new library), one measure per chart (no dual axis), with a table view.

## 2. Events from the live page

`funnel/tracking/` (storefront), mounted only on the live page (never the editor or a preview):

| Visitor action | First-party event (dashboard) | Meta | GA4 (GTM data layer) |
|---|---|---|---|
| Page opens | `page_view` | PageView (the store's existing Pixel base code) | page view (existing GTM) |
| An offer card is at least half on screen | `product_view`, once per offer | ViewContent | `view_item` |
| The visitor chooses an offer (not the pre-selected one) | `product_select` | AddToCart | `add_to_cart` |
| First touch of the order form | `checkout_start`, once | InitiateCheckout | `begin_checkout` |
| The store says the order counts as a purchase | — (orders come from the order tables) | Purchase | `purchase` |

* Events are queued and sent in batches to `POST /api/v2/funnels/{slug}/events` (or with `sendBeacon` when the page is
  left). Each event has its own random id; the table has a unique key on it, so a retried batch counts once.
  Unknown types, bots and malformed values are dropped. Rate limit 120 requests/minute per address.
* Stored: funnel, version, type, anonymous visitor and session ids (random, made in the browser), offer key, device
  class, traffic source class, utm medium/campaign. **Not stored:** IP address, user agent, full URL, referrer path,
  anything typed into the form. Old events are pruned after 400 days (`funnel:prune-events`).
* A staff test visit (section 5) sends nothing.

## 3. Meta Pixel, Conversions API and GA4

No new Pixel or GTM injection: funnel pages use the tags the root layout already loads, `lib/pixel/meta.js` (Pixel +
the existing `/api/meta/conversion` relay with a shared event id) and `lib/gtm.js`.

**Purchase** is built from the order, never from the page:

* The order summary returned by the store carries a `tracking` block: `eligible` (cash on delivery once placed; online
  payment only once the gateway confirmed it; never cancelled or test orders), `event_id`
  (`purchase_` + HMAC of the order id), value, currency, contents (catalogue ids = `product_stocks` ids, as the
  storefront and the catalogue feed use), and hashed customer data only when
  `FUNNEL_TRACKING_SHARE_CUSTOMER_DATA=true`.
* The browser fires `fbq('track', 'Purchase', …, { eventID })` and pushes `purchase` to the data layer, once per order
  per tab.
* The server copy goes through a new route, `/api/funnel/purchase`, which receives only the page and the receipt,
  reads the order from the store itself, and sends the Conversions API event with the same event id. The access token
  stays on the storefront server (`lib/pixel/capi.js`, shared with the existing relay).
* Meta deduplicates browser and server copies, and repeats, by event id.

**Hardening of the existing relay** (`/api/meta/conversion`): only standard Meta event names are accepted, the event
id is bounded, and the body is limited to 16 KB, so the route cannot be used to push arbitrary events into the ad
account.

**Consent** (`lib/consent.js`): `NEXT_PUBLIC_TRACKING_CONSENT=implied` (default, the store's behaviour so far: tags run
unless the visitor's `tracking_consent` cookie says `denied`) or `required` (tags run only with
`tracking_consent=granted`). Without consent: no Meta or Google event from the funnel, `fbq('consent','revoke')`
before the Pixel initialises, Google consent mode defaults to "denied", the no-script Pixel is left out, ad click ids
are not kept on the order, and the visitor id lives only for the tab's session. The store has no consent banner; one
only needs to set the cookie.

**Verification** with Meta Test Events and GTM Preview/GA4 DebugView: DEPLOYMENT.md section 10.

## 4. Campaign attribution

On arrival the page records, in the tab's own storage: `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`,
`utm_term`, `fbclid`, `gclid` and the referring site's host name (not its path). A later arrival with new campaign
parameters replaces it; a reload keeps it. The checkout request carries it to the store, which saves one
`funnel_order_attributions` row per order, in the order's transaction: the values above, the source class
(facebook, google, tiktok, whatsapp, email, sms, referral, direct, other), device class, consent state and the
test flag. Click ids are dropped when consent was refused.

Nothing personal goes into any URL: the payment gateway returns the customer with an encrypted receipt, and an inline
script moves the receipt, the payment state and a test token from the address into the tab's storage **before** any
tag loads, then removes them from the address bar. `fbclid`/`gclid`/utm stay in the address (the Pixel needs
`fbclid` to set its `_fbc` cookie).

## 5. Test orders

The Publish dialog of a live page has **Place a test order**: it opens the live page with a signed token (HMAC with
the app key, bound to the funnel, valid 24 h). An order placed with it is a real order — it reserves stock and shows
in Orders with **Funnel** and **Test** badges and a note — but it is excluded from the analytics and never reported
to Meta or Google, and the visit sends no events. Phone numbers listed in `FUNNEL_TEST_PHONES` count the same way.
Staff cancel test orders afterwards as usual (stock returns once).

## 6. Real HTTP 404 (no proxy needed)

| Page | Status |
|---|---|
| Missing, unpublished, archived, expired, scheduled-for-later | **404** (store's not-found screen, `noindex`) |
| Published | 200 |
| Valid preview link | 200 (`noindex`, `no-store`) |
| Invalid, revoked or expired preview link | 404 |

Cause of the old "200 + noindex": the store's `app/loading.jsx` wrapped every route in a loading boundary, so
responses started streaming before the funnel page could decide its status. The loading screens now live in each
store segment (`(pages)`, `(shop)`, `blogs`, `builder`, `dashboard`, `pages`, `signup`); nothing changes for shoppers
except that the home page (`/`) has no route-level spinner (its sections already load on their own). `/funnel/*`
has no loading boundary, so the page checks the funnel before sending anything. Nothing depends on `proxy.js`, which
the current host cannot load. Covered by the browser tests (missing, draft, unpublished, archived, expired, bad
preview, bad slug).

## 7. Performance

Measured with Lighthouse 13.5 against the production build (`next build && next start`) on this machine, with the
PHP built-in server as backend, page `/funnel/classic-single-product-bn-2` (the Bengali "Classic" template with one
product). Mobile = Lighthouse default (simulated slow 4G, 4× CPU slowdown); desktop = `--preset=desktop`.
These are lab numbers on a developer laptop, not field data.

| Run set | Mobile performance | Mobile LCP | Mobile CLS | Mobile TBT | Desktop performance | Desktop LCP | Desktop CLS |
|---|---|---|---|---|---|---|---|
| Before (Phase 5 renderer) | 40, 55 | 7.4 s, 4.8 s | 0.31, 0.35 | 544, 357 ms | 92, 90 | 0.87, 0.82 s | 0.166 |
| After (Phase 6) | 70, 72, 61 | 4.8, 4.8, 5.4 s | 0.000 | 449, 414, 648 ms | 98, 99 | 1.0, 1.0 s | 0.000, 0.006 |

Observed (unsimulated) LCP on the mobile runs went from ~2.4 s to 1.1–1.6 s (one outlier 2.7 s). Accessibility 95,
best practices 100, SEO 92 in every run.

What changed:

* **Offers in the first HTML.** Product names, photos and prices of a page are read on the storefront server
  (cached with the page data, refreshed by tag on publish) and drawn into the initial HTML, instead of after a
  browser request. This removed the layout shift (CLS 0.31–0.35 → 0) and made the main photo discoverable at once.
  The browser still refreshes offers a moment after loading, and checkout always prices on the server.
* **Responsive product photos.** Product cards and galleries request the width they need from the store's own image
  optimizer (`lib/imageLoader.js`, `/_next/image`, same as the storefront) with `srcset`/`sizes`; the main gallery
  photo gets `fetchpriority="high"`; other photos are lazy.
* **Rendered per visit from cached data** (`connection()`), not as a fully static page: the store's root layout reads
  the address (`useSearchParams`), and in a static page that makes the browser draw the content twice, delaying the
  first photo. The page and offer reads stay cached for 60 s and are dropped by tag on publish.
* **No editor code on public pages.** Checked in the build manifest: the public page's client chunks contain none of
  the editor, drag-and-drop or admin panels (≈ 220 KB of compressed JavaScript in total, most of it React and the
  store's shared root-layout providers).

Remaining mobile cost, not changed because it belongs to the store's root layout and affects the whole site:
eight preloaded web-font files (Hind Siliguri 4 weights incl. Bengali, Cormorant 2 weights, ≈ 190 KB), the root
layout's client providers (Redux, theme, layout detector) and 27 KB of render-blocking CSS. Giving funnel pages their
own root layout (route groups) would remove them from funnel pages; it means moving the store's routes into a route
group, so it is left as a recommendation for your decision.

## 8. Payment and order reliability

Reviewed for cash on delivery and Paystation:

| Requirement | Status |
|---|---|
| Final prices on the server | Yes: the browser sends offer key, variation and quantity only (Phase 3/4, tested). |
| Shipping per the store's rules | Yes: the store's `getShippingCost` rule per district (carrier-wise shipping unsupported → ordering refused). |
| Tax and discounts once | Yes: tax on the charged price, funnel offer on top of the website price, line totals must add up to the quote or the order is rolled back. |
| No duplicate orders | Idempotency key per submission (unique), same key replays the first result. |
| Failed payments | **Fixed:** the gateway return now cancels an order only on a confirmed failure; unclear answers leave it untouched and the page says the payment is being confirmed (no second payment offered). |
| Repeated callbacks | **Fixed:** "paid" applied once under a lock even when the return page and the notification arrive together; repeats change nothing. |
| No overselling | Atomic conditional stock updates (StockLedger, Phase 4/5; 22 concurrency tests in separate processes). |
| Cancel returns stock once | Status flip + ledger (`give`), tested for every cancel path. |
| Funnel orders in Orders | Yes, with Funnel (and Test) badges and a per-funnel filter. |
| Storefront checkout | Unchanged flow; covered by the PHPUnit storefront checkout tests and a browser regression test. |

**Paystation changes (website and funnel orders alike)**, in `App\Services\Payments\PaystationGateway`:

* `begin` refuses orders that are paid or cancelled (previously a cancelled order could be paid).
* Every payment session is recorded in `payment_gateway_attempts` (invoice number, amount, status).
* Gateway answers are classified PAID / FAILED / UNKNOWN; an amount mismatch is UNKNOWN, never paid.
* A payment confirmed after cancellation is recorded as paid, the order gets an "ATTENTION" note and appears under
  "Needs attention" in the analytics; it is not silently revived (its stock was given back).
* A funnel whose page went offline sends the returning customer to the website's own confirmation page instead of a
  404.

**Stale unpaid online orders** (`orders:expire-unpaid`, every 5 minutes, off until `ONLINE_ORDER_EXPIRY_ENABLED=true`):
after `ONLINE_ORDER_EXPIRE_MINUTES` (default 60) an unpaid online order is settled with the gateway: paid sessions
mark it paid; it is cancelled with its stock returned only when every session confirmed failure, or when no payment
was ever started; otherwise it waits, and after 48 h gets a note for staff and is no longer retried. `--dry-run`
reports without changing anything.

## 9. Security review

| Area | Finding | Action |
|---|---|---|
| Draft preview links | The store-wide Pixel and GTM loaded on `/funnel/*/preview/<secret>` and would send the secret URL to Meta/Google. | **Fixed:** both skip preview addresses. |
| Order receipt in URLs | The gateway return URL carries the encrypted receipt (a bearer token for the order summary); tags would report it. | **Fixed:** inline script removes it from the address before any tag loads. |
| Conversions API relay | Accepted any event name and payload from anyone. | **Hardened:** standard event names only, bounded id and body. Funnel purchases use a server-verified route. |
| Paystation | Cancelled orders could be paid; unclear answers cancelled orders that may have been paid; concurrent callback + notification ran the paid steps twice. | **Fixed** (section 8). |
| Client IP behind the storefront proxy | Laravel trusts no proxy, so all shoppers coming through the storefront's `/api/v2` rewrite share the storefront server's address: per-IP limits are shared and "Blocked IPs" can block everyone. | **Configurable:** `TRUSTED_PROXIES` (config/trustedproxy.php). Default unchanged. See DEPLOYMENT.md section 4. |
| Admin auth, staff permissions | Every admin route and editor API action checks its own permission; analytics has its own permission. | Tested (403 without permission, storefront login tokens refused by the editor API). |
| Editor tokens | Sanctum token named/abilitied `funnel:edit`, 8 h, one-time 60 s handoff code. | No change. |
| XSS | Page content typed and sanitised (Phase 1); saved sections pass the same validator (tested with `<script>` and `javascript:`); analytics values (campaign names come from URLs) escaped by Blade; chart data via `@json` (HTML-escaped). | No issue found. |
| CSRF | Admin state changes are POST/DELETE with the token; the editor API uses bearer tokens; public APIs hold no session. | No issue found. |
| SQL injection | All new queries use the query builder with bound values; raw fragments use fixed internal names only. | No issue found. |
| File uploads | Editor images: extension and MIME allow-list, `getimagesize`, size limit (Phase 2). | No change. |
| Product binding / promotional prices | Server-side offer engine, compiled from the published version only; tampered prices ignored (Phase 3, tested). | No change. |
| Test-order token | HMAC, funnel-bound, expiring; it can only mark one's own order as a test. | New. |
| Analytics privacy | No IP, user agent, URL or form data stored; ids random; retention 400 days; hashed customer data to Meta only when enabled. | New. |
| Rate limits | Separate limits per public funnel route (page, offers, quote, config, checkout 20/min, order, pay, preview, events) plus 6 orders per phone per 10 minutes. | Events limiter added. |

Not changed and worth knowing: the storefront's `/payment-failed?order=<id>` page receives the raw combined order id
(pre-existing website behaviour).

## 10. Saved sections

Left panel → **Saved**: search, preview (drawn by the real renderer with live product data), **Add to page**,
rename, duplicate, delete (with confirmation; pages already using a section keep their copy). Save from the block
settings of any top-level section (**Save as reusable section**, bookmark button). Sections are validated like a
page; inserting gives every block a fresh id and offer key. API: `GET/POST /funnel-editor/saved-sections`,
`PUT/DELETE /saved-sections/{id}`, `POST /saved-sections/{id}/duplicate` (permission `edit_funnel`; at most 500
sections, 256 KB each).

## 11. Builder UX polish

* Narrow screens: below 1280 px only one side panel is open at a time (opening one closes the other), so the page
  stays at least ~600 px wide; below 1024 px panels float over the page with a backdrop that closes them; the toolbar
  status line no longer wraps.
* Saved sections with loading skeleton, empty state with instructions, error messages, confirmation for delete.
* Publish dialog: **Place a test order** for live pages.
* Payment return: "We are confirming your payment" state (Bengali and English) instead of offering a second payment.
* Opening the editor too often in a minute now explains itself ("Too many sign-ins just now… wait a minute") instead of
  showing "Too Many Attempts."; the limit (10 a minute per address) is configurable with
  `FUNNEL_EDITOR_EXCHANGES_PER_MINUTE`.
* Removed an outdated note in the block library ("order form blocks arrive in the next update").
* Analytics dashboard in the admin's own theme (cards, Bootstrap grid, admin Chart.js), usable at phone width.

## 12. Tests added in this phase

Backend (PHPUnit): `FunnelAnalyticsTest` (events, dedupe, privacy, bots, attribution and consent, test orders, the
editor's test link, Purchase tracking data, report definitions, dashboard permission), `PaystationReliabilityTest`
(paid once, confirmed failure cancels once, unknown never cancels, amount mismatch, paid-after-cancel, begin refuses
paid/cancelled, expiry job incl. dry run and give-up), `SavedSectionsTest`.

Frontend: unit `tracking.spec.js` (receipt stash, attribution, consent, catalogue ids); browser `tracking.spec.js`
(events, ViewContent on screen only, AddToCart not for pre-selection, InitiateCheckout once, Purchase once from the
order, receipt never in the address, test visit reports nothing, refused consent sends nothing),
`saved-sections.spec.js`, `ui-review.spec.js` (editor at 1024/1280/768 px, dashboard at laptop and phone width),
`storefront-checkout.spec.js` (website guest checkout regression), 404 cases in `public-page.spec.js`, dashboard
step in `journey.spec.js`.

## 13. Database changes (unapplied to `misron`)

`sqlupdates/sales_funnel_v5_analytics.sql` (+ rollback): `funnel_events`, `funnel_order_attributions`,
`payment_gateway_attempts`, permission `view_funnel_analytics`. Migrations
`database/migrations/sales_funnel/2026_10_26_*`. All additive; the code works without them (analytics and
attribution are skipped, payments are not recorded for expiry).

## 14. Test results (2026-10-24)

| Suite | Result |
|---|---|
| Backend PHPUnit, original MyISAM schema (`misron_test`) | all pass except the old `ExampleTest` (unchanged, documented) |
| Backend PHPUnit, converted InnoDB schema (`misron_target_test`) | all pass except the old `ExampleTest` (2 skipped: need duplicate stock rows the unique key forbids) |
| Concurrency (separate PHP processes, `misron_inventory_test`) | 22 / 22 |
| Frontend unit (`npm run test:funnel-core`) | 60 / 60 |
| Type check (`npm run typecheck:funnel`) | clean |
| Lint, funnel and new code | clean (full project: 24 errors, all in store files this work did not touch) |
| Next.js production build | passes |
| Full Playwright suite, one run on a fresh production build (81 tests incl. journey, storefront checkout regression, tracking, 404s, saved sections, templates, narrow screens) | **81 / 81** |

The run before it had 80 / 81: the editor sign-in limit (10 per minute per address) refused the suite, which opens the
editor far more often. That was a real refusal, not a flake; the limit is now a setting, raised only for the test
servers, and a backend test checks it still applies.

## 15. Known limitations

* A Purchase for an online payment is reported when the customer comes back to the page. If they close the gateway
  tab after paying, the order is still marked paid (gateway notification or expiry job) and counted in the dashboard,
  but no Purchase event reaches Meta/Google.
* Visitor numbers are estimates (ad blockers, cleared storage); order numbers are exact.
* Product prices in the first paint can be up to 60 s old; the browser refreshes them at once and checkout prices on
  the server.
* Mobile Lighthouse ~61–72 on this machine; the remaining cost is the store's shared root layout (section 7).
* The expiry job only settles Paystation orders (the only gateway whose outcome the store can query); other gateways'
  unpaid orders are left as before.
* GA4 receives events through your GTM container only; there is no server-side GA4 (Measurement Protocol) copy.
* No consent banner exists in the store; the consent rules work once something sets the cookie.
