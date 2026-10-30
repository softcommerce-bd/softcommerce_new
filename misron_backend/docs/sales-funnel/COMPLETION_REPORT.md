# Sales Funnel Builder — Completion Report

Date: 2026-10-29. Branch `feature/sales-funnel-builder` in both repositories (not merged, not pushed, not deployed).
The main `misron` database is unchanged, live checkout is off, and no dummy data was reset.

This report checks the built system against the original master requirements, lists what was finished in this
last round, the remaining limitations, every test result, and how to run the system locally.

---

## 1. Status

**Feature-complete for local use and review.** Every required use case (UC-01 to UC-18) is implemented and covered
by automated tests that were run on 2026-10-29 (section 5). The open items are limitations with a reason (section 4),
not unfinished features.

---

## 2. Requirements and where they are met

| Master requirement | Status | Where |
|---|---|---|
| §1 Audit, architecture, integration map | Done | `ARCHITECTURE.md` §1–3 |
| §2 Business journey (title → template → product → edit → preview → publish → URL → orders → metrics) | Done | `journey.spec.js` runs it end to end |
| §3 Admin menu: All Funnels, Create Funnel, Funnel Templates, Saved Sections, Analytics, Settings | Done (Templates, Saved Sections, Settings added in this round) | admin sidebar |
| §3 All Funnels: thumbnail, URL, status, products, views, orders, conversion, revenue, last modified; Edit, Preview, Duplicate, Publish, Unpublish, Archive, Analytics, Delete; search, filter, sort, pagination; Copy URL | Done (figures and Copy link added in this round). Preview is the editor's Preview and "Share draft link". | `funnels/index` |
| §4 Visual editor: toolbar, block library, live canvas with the public renderer, Content/Style/Advanced tabs, drag and drop, reorder, move between containers, duplicate, copy/paste, delete, hide, lock, columns (add, per-device width), responsive settings, undo/redo, shortcuts, layers, breadcrumbs, autosave, manual save | Done | `funnel/editor` (Phase 2) |
| §5 Component library (16 basic, 17 marketing, 15 e-commerce) | 49 blocks registered. The Coupon Input block is registered but not offered (section 4). | `config/funnel_blocks.php` |
| §6 Existing products: search by name, SKU, ID, category, brand; live name, images, prices, variants, stock; display overrides that never rename the product | Done | `FunnelCatalogue`, product picker |
| §7 Offers: percent, fixed amount, fixed bundle price, quantity tiers, Buy X Get Y, free gift, free delivery, start/end dates, per-order limits, featured/default selection; server-side pricing | Done | `Offers/OfferEngine` (Phase 3) |
| §8 Checkout form: Bangladesh defaults, labels, placeholders, field order, optional fields, consent, idempotency, server validation, existing order service, funnel ID on the order | Done | `Checkout/*` (Phase 4) |
| §9 Existing payments, delivery charges, fulfilment | COD and Paystation; store's delivery charge rules; orders enter normal fulfilment | section 4 for other gateways |
| §10 Blender reference template (single and multiple products) | Done ("Product showcase" and "Classic" designs) | `Templates/Definitions` |
| §11 Responsive builder, two-column mobile grid, one-hand checkout, no sideways scroll, Bengali typography | Done; phone/tablet/laptop checks in the browser suite | `responsive.spec.js`, `ui-review.spec.js` |
| §12 Eight templates; preview, apply, duplicate, rename, save funnel as template, saved sections, product slots instead of product IDs | Done (save-as-template and template management added in this round) | Funnel Templates page |
| §13 Publishing: slug, SEO, social image, versions, rollback, secret expiring preview links, published version stays live while editing, scheduling | Done | Phase 5 |
| §14 Analytics: views, visitors, selections, checkout starts, orders, purchases, conversion, revenue, AOV, sources, devices, trends; definitions shown; preview traffic excluded; Pixel/CAPI/GA4 with dedupe | Done | Phase 6 |
| §15 Optional features | Not built (allowed); the document and event design leave room for them | — |
| §16 Performance | Public pages without the editor bundle, responsive images, cached pages with refresh on publish. Mobile Lighthouse 61–72 (section 4). | `PHASE6.md` §7 |
| §17 Security | Done; reviewed again in the readiness rounds (uploads, payments, dependencies) | `PRODUCTION_READINESS.md` |
| §18 Versioned page JSON, registry, nesting rules, schema migrations, separated draft/published/templates/offers/attribution/events | Done | `ARCHITECTURE.md` §5 |
| §19 UI/UX for first-time merchants | Done: setup checklist, empty states, plain-language settings, confirmations | — |
| §21 Tests | Done (section 5) | — |
| §23 Deployment, rollback | Documented | `DEPLOYMENT.md`, `DEPLOYMENT_RUNBOOK.md` |

### Use cases

| UC | Covered by |
|---|---|
| 01 Create a funnel without coding | `journey.spec.js`, `templates.spec.js` |
| 02 Select eight existing products | `products-and-offers.spec.js` ("eight blenders become eight cards") |
| 03 Funnel discount, listing price unchanged | `OfferPricingTest` |
| 04 Customer selects products, totals recalculate | `checkout.spec.js`, `FunnelCheckoutTest` |
| 05 COD order in Orders with funnel ID | `FunnelCheckoutTest`, `journey.spec.js` |
| 06 Shared stock | `storefront-checkout.spec.js`, `checkout.spec.js`, `StockLedgerTest` |
| 07 Unavailable product | `checkout.spec.js` ("last unit goes to someone else"), `FunnelCheckoutTest` |
| 08 Mobile layout editing | `responsive.spec.js`, `ui-review.spec.js` |
| 09 Duplicate a funnel | `FunnelLifecycleTest::test_duplicate_is_independent` |
| 10 Edit a published funnel safely | `FunnelPublishingTest`, `publishing-ui.spec.js` |
| 11 Discount tampering | `FunnelCheckoutTest::test_the_browser_can_only_choose_never_price` |
| 12 Double submission | `FunnelCheckoutTest`, `checkout.spec.js` |
| 13 Historical price integrity | `FunnelCheckoutTest::test_an_order_keeps_its_prices_when_the_product_price_changes_later` (added in this round) |
| 14 Template reuse | `FunnelLibraryTest`, `ui-review.spec.js` (added in this round) |
| 15 Funnel analytics | `FunnelAnalyticsTest`, `journey.spec.js` |
| 16 Payment failure | `PaystationReliabilityTest` |
| 17 Permission protection | `FunnelEditorApiTest`, `FunnelAdminPagesTest`, `FunnelLibraryTest` |
| 18 Two customers, last unit | concurrency suite (separate PHP processes) |

---

## 3. Finished in this round

| Item | What changed |
|---|---|
| **Save as template (UC-14)** | All Funnels → ⋯ → *Save as template*. The draft's layout, wording, pictures and offer settings are kept; every product is removed. Offers that held one product become product slots, grouped by product, so a new campaign asks for "Product 1", "Product 2" like the built-in designs. Bundles of several products and free gifts are emptied and flagged. Service: `Templates/CustomTemplates`. |
| **Funnel Templates page** | Your templates (use, rename, duplicate, delete) and the built-in designs (use in Bengali or English). "Use" opens Create Funnel with the design chosen. Built-in designs cannot be changed. |
| **Saved Sections page** | List, search, rename, delete; sections are added to pages from the editor as before. |
| **Settings page** | Plain-language view of how the module is set up: ordering on/off, payment methods, delivery charge mode, test phones, unpaid-order expiry, editor and page-refresh configuration, tracking privacy, and which database steps are installed. Each row names the setting to change. |
| **All Funnels figures and Copy link** | Visitors, orders, conversion and revenue for the last 30 days per funnel (same definitions as the dashboard; only for staff with analytics permission), and a Copy link button for live pages. |
| **Bug: confirmations skipped (pre-existing since Phase 1)** | Publish, Unpublish, Archive and Delete in the All Funnels menu ran **without** their confirmation prompt: the admin table plugin rebuilds rows after page load, which dropped the handlers. All handlers now listen on the document; a browser test checks that saying "no" leaves the page live. |
| **Phone layout** | All Funnels, Templates and Settings scrolled sideways on a 390 px phone (35–400 px). Fixed; checked at 390, 768 and 1366 px. |
| **UC-13 test** | An order's lines and total stay the same after the product price changes, and the customer's receipt shows what was charged. |

Earlier readiness rounds (same branch): Next.js 16.4.3, Symfony/commonmark/PhpSpreadsheet security updates,
upload hardening (path traversal in the API image upload, web uploader without sign-in, SVG/XML staff-only),
Paystation forged-invoice guard, idempotent v2 migration and FK-aware rollback.

---

## 4. Remaining limitations

| Limitation | Reason |
|---|---|
| Coupon Input block not offered in the editor; funnel offers do not combine with store coupons | The store's coupons are limited per customer account; funnel checkout is a guest checkout, so per-customer use cannot be enforced. A coupon would let one code be used without limit. (Decision recorded in `PHASE4_CHECKOUT.md`.) |
| Online payment on funnel pages is Paystation only | It is the online gateway the storefront checkout uses. Other gateway code in the backend (bKash, Nagad, SSLCommerz, aamarPay) is not connected to the storefront and was not invented for funnels. |
| "By carrier" delivery charge mode not supported | A funnel page has no courier choice; with that mode ordering is refused rather than priced wrongly (the Settings page says so). Courier booking after the order works as for any order. |
| No SMS/e-mail at order placement | Same as the storefront's own checkout. Payment-success and status-change messages run as usual. |
| Your own templates | No picture unless the source funnel had a share image; to change a template's design, create a funnel from it, edit, and save it as a template again. |
| Editor interface is in English | Page content is Bengali or English per page; admin pages use the store's translation system (`translate()`), so they can be translated under Languages. The React editor's own labels are not yet translatable. |
| Purchase event reaches Meta/Google only when the customer returns from the payment page | Paid orders are still recorded and counted; only the ad event is missed if the tab is closed. |
| Visitor numbers are estimates; no consent banner in the store | Ad blockers and cleared storage; consent rules work once a banner sets the cookie. |
| Mobile Lighthouse 61–72 | The remaining cost is the store's shared root layout, outside the module. |
| Optional §15 features (A/B tests, upsells, exit popups, abandoned checkout) | Not built, as the requirements allow. |
| Laravel 9 is end-of-life; `svg-sanitize` has no fixed release | Framework upgrade is a separate project; SVG upload is staff-only. |
| Full browser suite needs an idle computer | On this 8 GB machine, other dev servers or sleep make the single-threaded PHP test server stall; an undisturbed run takes about 15 minutes. |

---

## 5. Test results (2026-10-29)

| Suite | Result |
|---|---|
| Backend PHPUnit, original schema (`misron_test`) | 314 tests: all pass except the old `ExampleTest` |
| Backend PHPUnit, converted schema (`misron_target_test`) | 314 tests: all pass except the old `ExampleTest` (2 skipped: need duplicate stock rows the unique key forbids) |
| Concurrency, separate processes (`misron_inventory_test`) | 22 / 22 |
| Frontend unit (`npm run test:funnel-core`) | 60 / 60 |
| Type check (`npm run typecheck:funnel`) | clean |
| Lint (funnel code and its tests) | clean |
| Browser suite, Playwright, fresh production build | **82 / 82** (26.7 min) |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `composer audit` | only Laravel 9 (no 9.x fix; the critical one is not reachable: no token guard) and `svg-sanitize` (mitigated) |

The old `ExampleTest` (the `/` route redirects to the login page) fails by design and was left unchanged.

What the tests exercise for the payment and stock questions: COD orders; Paystation paid, failed, unclear and
forged-invoice answers; percent, fixed, bundle, tier, Buy X Get Y and free-gift pricing; tax and delivery in the
totals; stock taken once and returned once on cancellation; double submission and retried submissions; the last
unit with two simultaneous buyers; price changes before and after ordering.

---

## 6. Commits

Backend (`misron_backend`): `3adcafb` Phase 6 · `23ae8e0` readiness · `002b678` deployment preparation ·
the commit that adds this report (this round). (`a20c479` is your own commit.)

Storefront (`misron_frontend`): `44ce36a` Phase 6 · `cb1c470` Next.js security update · the commit of this round (test updates for templates).
Your own commits and uncommitted edits are not included.

---

## 7. Running the complete system locally

Uses the rehearsal copy `misron_restore_test` (your local data from 2026-10-28 with every funnel database step
applied), so the main `misron` database is never touched. Product photos stored only on the live server show as
placeholders locally.

**Configured on 2026-10-29, ports changed on 2026-10-30** (the `.env` files are not in git). The backend runs on
port 8000 and the storefront on port 3000:

| File | Values |
|---|---|
| `misron_backend\.env` | `DB_DATABASE=misron_restore_test`, `APP_URL=http://127.0.0.1:8000`, `PAYSTATION_CALLBACK_URL=http://127.0.0.1:8000/api/v2/paystation/callback`, and a "Sales funnel" block: `FUNNEL_PUBLIC_URL` and `FUNNEL_EDITOR_URL=http://localhost:3000`, `FUNNEL_REVALIDATE_URL=http://localhost:3000/api/funnel/revalidate`, `FUNNEL_REVALIDATE_SECRET=local-dev-secret`, `FUNNEL_CHECKOUT_ENABLED=false`, `FUNNEL_REQUIRE_CHECKOUT=true`. Everything else unchanged. |
| `misron_frontend\.env.local` | `BACKEND_API_URL=http://127.0.0.1:8000/api/v2`, `NEXT_PUBLIC_FILE_BASE_URL=http://127.0.0.1:8000`, `IMAGE_URLS` with port 8000, `FUNNEL_REVALIDATE_SECRET=local-dev-secret`. |

With `DB_DATABASE` in `.env`, plain `php artisan …` commands and `php artisan serve` also use the copy, never the
main database. The test suites pin their own databases and funnel settings (`phpunit.xml`,
`phpunit.concurrency.xml`, `tests/e2e/router.php`), so these local values never change a test result. These are
local values only: production keeps its own `.env` with `APP_DEBUG=false` (DEPLOYMENT_RUNBOOK.md section F).

**Start everything** (start MySQL from Laragon first):

```powershell
cd D:\kik\misron\misron_backend;  php artisan serve --host=127.0.0.1 --port=8000
cd D:\kik\misron\misron_frontend; npm run dev
```

**The editor depends on the storefront reaching the backend.** The editor page on port 3000 calls `/api/v2/…`,
which Next.js forwards to `BACKEND_API_URL`. If the backend runs on a different port from the one in
`misron_frontend\.env.local`, the admin side still works but the editor cannot sign in: it shows "The editor
cannot reach the admin server". Change `BACKEND_API_URL`, `NEXT_PUBLIC_FILE_BASE_URL` and `IMAGE_URLS` to the
backend's port and restart `npm run dev`, because Next.js reads the forwarding address only when it starts.

To place test orders on the local copy only, set `FUNNEL_CHECKOUT_ENABLED=true` in `misron_backend\.env`; orders
then go into `misron_restore_test`. Two demo pages were published while checking the setup, "Local setup check"
(`/funnel/local-setup-check`) and "Visual builder check"; delete them from All Funnels when you no longer need them.

**4. Use it**

* Admin: <http://127.0.0.1:8000/login> with your usual local admin account → **Sales Funnels**.
* Create Funnel → choose a design → choose a product → **Create and open Visual Builder**. The editor opens on
  <http://localhost:3000/builder/funnels/ID>.
* All Funnels → **Open Visual Builder** on any funnel that is not archived. On phones the button sits under the
  funnel's name.
* Publish in the editor → the page is at `http://localhost:3000/funnel/<address>`.
* Settings page shows what is switched on.

**5. Tests** (all use the isolated `*_test` databases):

```powershell
cd D:\kik\misron\misron_backend
vendor\bin\phpunit
$env:DB_DATABASE = "misron_target_test"; vendor\bin\phpunit; Remove-Item Env:DB_DATABASE
$env:DB_DATABASE = "misron_inventory_test"; vendor\bin\phpunit -c phpunit.concurrency.xml; Remove-Item Env:DB_DATABASE

cd D:\kik\misron\misron_frontend
npm run typecheck:funnel
npm run test:funnel-core
npm run test:funnel-editor     # browser suite: builds, starts its own servers on 3100/8001; close other dev servers first
```

---

## 8. Readiness

* **Local use and review: ready.** Every feature in section 2 works with your existing products, stock, customers
  and orders, and all suites pass (section 5).
* **Production:** not started, as instructed. When you decide to deploy, `DEPLOYMENT_RUNBOOK.md` has the gated steps
  (host PHP check, backup, migration rehearsal on a production dump, deployment, smoke tests); live checkout stays a
  separate decision after the smoke tests.
