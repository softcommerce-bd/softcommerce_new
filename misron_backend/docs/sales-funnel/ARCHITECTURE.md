# Sales Funnel Builder — Architecture and Plan

Status: Phases 1–6 implemented on the feature branch and tested on the `*_test` databases (see PHASE6.md). Deployment: DEPLOYMENT.md.
Last updated: 2026-10-24.

This module adds no-code sales funnel pages to the existing Misron platform.
It owns page documents only. Products, stock, customers, orders, payments and
couriers stay in the systems that already handle them.

---

## 1. Audit of the existing application

### Stack

| Part | What is there |
|---|---|
| Admin + API backend | Laravel 9.52 (`misron_backend`), PHP `^8.0.2` declared (8.5 locally). Active-eCommerce-style codebase. Blade admin on Bootstrap 4 + jQuery ("aiz" theme), JSON API under `/api/v2`. |
| Storefront | Next.js 16.3 App Router (`misron_frontend`), React 19.2, Tailwind 4, Redux Toolkit. Talks to Laravel through a `/api/v2/*` rewrite in `next.config.mjs`. **Next 16 conventions differ from older versions; read `node_modules/next/dist/docs/` before writing frontend code (see `AGENTS.md`).** |
| Database | MySQL 8.4 locally, 158 tables. Schema comes from SQL dumps in `sqlupdates/`, not from Laravel migrations: only a handful of migrations exist and several are recorded as "Pending" although their tables exist. |
| Auth | Admin: Laravel session (`web` guard), `IsAdmin` middleware (user_type `admin` or `staff`), Spatie permissions (`@can`, `permission:` middleware), `Super Admin` role passes every check via `Gate::before`. Customers: Sanctum 2.14 bearer tokens. |
| Cache / queue | File cache, database queue. The scheduler runs `queue:work --once` every minute, so a cron job calling `schedule:run` is expected in production. No Redis dependency. |
| Hosting signals | Backend: cPanel-style host (`cgi-bin/`, `error_log`, `.htaccess`, `web.config`); shell access cannot be assumed. Storefront: serverless host (comments in `next.config.mjs` mention `/var/task`, i.e. Vercel/Lambda). |
| Tests | PHPUnit 9 with one example test; no database isolation before this module. |

### Findings that shape the design

1. **`php artisan migrate` is not safe to run as-is.** Legacy migrations are
   pending although their tables exist. Module migrations live in their own
   folder and run with `--path`; a SQL file is provided for phpMyAdmin.
2. **Order creation is one 490-line controller method**
   (`Api\V2\OrderController::store`), driven by the `carts` table. It decrements
   `product_stocks.qty` with a read-modify-write (`$stock->qty -= n; save()`)
   and no row lock, consumes FIFO purchase batches, creates
   `combined_orders → orders → order_details`, links or creates the customer
   by phone, and clears the cart. There is no idempotency protection.
3. **Prices** come from helpers in `app/Http/Helpers.php`
   (`cart_product_price`, `home_discounted_base_price`, taxes from
   `product_taxes`), based on `product_stocks.price` plus the product-level
   discount window.
4. **Shipping** is computed by `getShippingCost($carts, …)` from settings
   (`shipping_type`: flat rate, seller-wise, product-wise, area/carrier-wise).
5. **Payments**: the storefront checkout offers Cash on Delivery and the
   methods returned by `GET /active-payment-methods`; online payment starts
   *after* the order exists, from its `combined_order_id` (Paystation is wired
   in the storefront; bKash, Nagad, SSLCommerz and aamarPay controllers exist in
   the API). Orders are created `unpaid`; gateway callbacks mark them paid.
6. **Couriers** (Steadfast, Pathao, BD Courier) are driven from the admin order
   screens after the order exists. Nothing in order creation depends on them.
7. **Tracking**: GTM data layer, Meta Pixel in the browser and a Conversions
   API relay route in Next (`app/api/meta/conversion/route.js`) that
   deduplicates with `event_id`.
8. **Uploads**: `uploads` table + `AizUploadController`; pages reference
   images by upload id and resolve them with `uploaded_asset()`.
9. **Global middleware** `TrimStrings` and `ConvertEmptyStringsToNull` rewrite
   every JSON string in a request. Page documents are therefore read from the
   raw request body.
10. **API exception handler** turns `ModelNotFoundException` into HTTP 500 for
    `api/*`. Funnel API code looks records up explicitly and answers 404 itself.
11. **Sanctum 2.x has no per-token expiry column.** Editor token lifetime is
    enforced in middleware.

---

## 2. Integration map

| Need | Existing source of truth | How funnels use it |
|---|---|---|
| Product catalogue | `products`, `product_stocks`, `product_taxes`, `uploads` | Page blocks store **references** (`productId`, optional `variant`). Nothing is copied. Names, images and prices are read live. |
| Price | `product_stocks.price`, product discount window, taxes | Funnel offers apply *on top of* the current price, computed server-side at checkout (Phase 3/4). The product's own price is never changed. |
| Stock | `product_stocks.qty`, `products.current_stock` | Read live for availability; decremented atomically when a funnel order is placed (Phase 4). |
| Orders | `combined_orders`, `orders`, `order_details` | Funnel orders are ordinary rows in these tables with `order_from = 'app'` (as website orders) and `orders.funnel_id` / `funnel_version_id` (Phase 4, see PHASE4_CHECKOUT.md). They show up in the existing Orders module. |
| FIFO costing | `purchase_products.remaining_qty` | Same FIFO consumption as storefront orders (Phase 4). |
| Customers | `users` (guest checkout links or creates by phone) | Same rule as the storefront checkout. |
| Payment | COD + active gateways, started from `combined_order_id` | Same flow: create unpaid order, then hand off to the gateway. |
| Delivery charge | Business settings via `getShippingCost` | Same calculation, fed with the funnel's selected items (Phase 4). |
| Couriers, invoices, SMS | Admin order screens, `OrderService`, SMS templates | Unchanged; they act on the order rows. |
| Tracking | GTM, Pixel, CAPI relay | Funnel pages reuse them; Purchase is read from the order on the server (Phase 6). |
| Media | `uploads` | Image props store an upload id (or a vetted URL). |
| Staff permissions | Spatie `permissions` (grouped by `section`) | New section `sales_funnel`; appears automatically in Staff → Roles. |

---

## 3. Architecture

```
 Laravel admin (Blade)                Next.js app
 ┌──────────────────────────┐        ┌─────────────────────────────────────┐
 │ Sales Funnels menu        │ Edit   │ /builder/funnels/[id]   (Phase 2)   │
 │  list / create / actions  │──────▶ │  editor bundle, client only, noindex│
 │  (session + permissions)  │ code   │                                     │
 └────────────┬──────────────┘        │ /funnel/[slug]          (Phase 5)   │
              │                       │  public renderer, no editor code    │
              ▼                       └───────────┬─────────────────────────┘
 ┌──────────────────────────────────────────────────▼──────────────────────┐
 │ Laravel API  /api/v2/funnel-editor/*  (Sanctum editor token)             │
 │              /api/v2/funnels/*        public read + checkout (Phase 4/5) │
 │ FunnelService · PageValidator · BlockRegistry · EditorSession            │
 │ OfferResolver / PricingEngine / FunnelOrderService        (Phase 3/4)    │
 └──────────────┬───────────────────────────────────────────────────────────┘
                ▼
   funnels · funnel_versions · funnel_templates · funnel_saved_sections ·
   funnel_audit_logs  (+ existing products / stock / orders / users)
```

### Why the editor runs in the Next.js app

The canvas must render with the same components customers see. Those
components live in the Next.js app, so the editor lives there too, on its own
route that the public pages never import. The Laravel admin keeps the list,
permissions and lifecycle actions in its own look and feel; the editor is a
full-screen workspace, like Elementor's.

### Editor sign-in (implemented)

1. The admin clicks **Edit**. `GET /admin/funnels/{id}/builder` (session auth,
   `edit_funnel`) stores a random one-time code in the cache for 60 seconds.
2. The browser is redirected to `FUNNEL_EDITOR_URL/builder/funnels/{id}?handoff=CODE`.
3. The editor posts the code to `POST /api/v2/funnel-editor/session` through
   the same-origin `/api/v2` rewrite, so no CORS is involved. The code is
   consumed on first use.
4. The response carries a Sanctum token named `funnel-editor` with ability
   `funnel:edit`. `FunnelEditorAccess` accepts only that token, only for
   admin/staff accounts, and only within `token_ttl_minutes` (8 hours).
   Storefront login tokens (ability `*`) are refused.
5. Each API action then checks the staff permission it needs.

### Publishing model (implemented)

- `funnels.draft_content` is the working copy. Autosave writes only there.
- Publishing validates the draft in *publish* mode, writes an immutable
  `funnel_versions` row and points `funnels.published_version_id` at it, in
  one transaction with the funnel row locked. Customers see either the old or
  the new version, never a half-saved draft.
- Publishing an unchanged draft is a no-op (no empty versions on double click).
- Rollback copies a stored version into the draft, after saving the current
  draft as a restore point. Publishing the result is a separate step that
  needs `publish_funnel`.
- Two editor tabs cannot overwrite each other: saves carry the draft revision
  and a stale revision gets HTTP 409.
- Deleting is a soft delete. The slug is released, versions are kept, and no
  order is touched.

### Lifecycle

```
draft ──publish──▶ published ──unpublish──▶ unpublished ──publish──▶ published
  │                    │                         │
  └──────archive───────┴─────────archive─────────┘──▶ archived ──restore──▶ draft / unpublished
published ──edit draft──▶ published (with unpublished changes) ──publish──▶ new version
scheduled (Phase 5): goes live by itself once scheduled_at passes; the public
read checks the time, so it does not depend on cron.
```

---

## 4. Database plan

All changes are additive. No existing table is altered in Phase 1.

| Table | Phase | Purpose |
|---|---|---|
| `funnels` | 1 ✅ | Metadata, slug, status, draft document, SEO, pointer to the published version, `product_ids` index of bound products. Soft deletes. |
| `funnel_versions` | 1 ✅ | Immutable snapshots: `published` and `restore_point`. |
| `funnel_templates` | 1 ✅ (table) / 5 (content) | Reusable documents with `product_slots` instead of product ids. |
| `funnel_saved_sections` | 1 ✅ (table) / 5 (UI) | One reusable top-level block each. |
| `funnel_audit_logs` | 1 ✅ | Who did what. No foreign key, so the trail outlives the funnel. |
| `permissions` rows | 1 ✅ | `view_funnels`, `add_funnel`, `edit_funnel`, `publish_funnel`, `delete_funnel` (section `sales_funnel`). |
| `funnel_offers` | 3 | Offers compiled from the published version (one row per binding per version) so checkout validates against an indexed table, never against browser input. |
| `funnel_checkout_attempts` | 4 | Idempotency keys (unique), request hash, resulting `combined_order_id`, status. |
| `orders.funnel_id`, `orders.funnel_version_id` (nullable, indexed) | 4 | **Alters an existing table — needs your approval.** Fallback without altering `orders`: a `funnel_order_links` table keyed by order id. |
| `funnel_events` | 6 ✅ | Page views, product views and selections, checkout starts (no personal data), deduplicated by event id. |
| `funnel_order_attributions`, `payment_gateway_attempts` | 6 ✅ | Campaign/device/test flag per order; Paystation sessions for the unpaid-order expiry. |

Migrations: `database/migrations/sales_funnel/` (run with `--path`).
SQL for phpMyAdmin: `sqlupdates/sales_funnel_v1.sql`, undo with
`sqlupdates/sales_funnel_v1_rollback.sql`.

---

## 5. Page document and component registry

### Document (schema version 1)

```json
{
  "schemaVersion": 1,
  "page": {
    "settings": { "accentColor": "#e8590c", "fontFamily": "hind_siliguri" },
    "sections": [
      {
        "id": "hero_001",
        "type": "section",
        "props": { "contentWidth": "boxed" },
        "style": { "desktop": { "paddingTop": 48 }, "mobile": { "paddingTop": 24 } },
        "advanced": { "hidden": { "mobile": false }, "anchor": "top" },
        "children": [
          { "id": "heading_001", "type": "heading", "props": { "text": "ব্লেন্ডার মেগা অফার", "level": "h1" } }
        ]
      },
      {
        "id": "offers_001",
        "type": "product_offer_selector",
        "props": { "selectionMode": "multiple", "showSavings": true },
        "productBindings": [
          {
            "key": "offer_blender_a",
            "items": [{ "productId": 123, "variant": null, "quantity": 1 }],
            "offer": { "type": "percent", "value": 15, "endsAt": "2026-12-31T23:59:00+06:00" },
            "display": { "title": "Blender 1000W", "badge": "Best seller" }
          }
        ]
      },
      { "id": "checkout_001", "type": "checkout_form", "children": [] }
    ]
  }
}
```

- `props` hold content, `style` holds per-breakpoint layout tokens (numbers
  and enumerations only, never raw CSS), `advanced` holds visibility, lock,
  anchor and layer label.
- `productBindings` exist only on offer-defining blocks (`product_card`,
  `product_offer_selector`, `bundle_offer_selector`). Other product blocks
  point at an offer with `offerKey`. An offer may contain several products
  (bundles) and one promotion: none, percent, fixed amount, fixed price,
  buy X get Y, free gift, plus free delivery, date window and per-order limit.
- Templates never contain product ids; they declare `product_slots` that the
  merchant fills when applying the template.

### Registry

`config/funnel_blocks.php` is the server-side allowlist: 49 block types in
three categories (17 basic, 17 marketing, 15 e-commerce — every component the
brief lists, plus `column` as the child of `columns`), where each may be placed
(`parents`), what it may contain (`children`), its props with typed rules,
per-page limits (one checkout form, one sticky CTA, …), list-item shapes and
the style vocabulary. `PageValidator` enforces it:

- unknown types and invalid nesting are errors; unknown props are removed
  with a warning; invalid values are errors with a JSON path the editor can
  point at;
- rich text goes through an allowlist sanitizer (no scripts, handlers,
  `javascript:` links, iframes or images);
- links must be `https://`, site-relative, `#anchor`, `tel:` or `mailto:`;
- name, phone and address are always enabled and required in the checkout;
- limits: 1 MB document, 600 blocks, depth 8, 40 offers;
- publish mode additionally requires: at least one section, products chosen
  for offer blocks, resolvable `offerKey` references, a checkout form wherever
  products can be selected, and a real end time for every countdown (there is
  no "evergreen" fake countdown).

The editor fetches the same registry from `GET /api/v2/funnel-editor/registry`
and adds the visual side (defaults, controls, renderer) under the same type
names. `SchemaMigrator` upgrades stored documents step by step when the schema
version is raised.

---

## 6. Editor UI structure (Phase 2)

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ ← Back │ Blender Mega Offer  • Saved 10:42 │ ↶ ↷ │ 🖥 📱 📱 │ Preview │ Save │ Publish │
├───────────────┬──────────────────────────────────────────────┬──────────────┤
│ Elements      │                                              │ Settings     │
│ [search]      │        live canvas (same renderer as         │ Content|Style│
│ Basic         │        the public page, device width)        │ |Advanced    │
│ Marketing     │   drop indicators · selection outline        │              │
│ E-commerce    │   breadcrumb: Page › Section › Heading       │              │
│ Saved sections│                                              │              │
│ Layers ▾      │                                              │              │
└───────────────┴──────────────────────────────────────────────┴──────────────┘
```

- Drag and drop with `@dnd-kit` (to be added; no page-builder framework).
- State: one document store with an undo/redo history of patches; autosave
  debounced to the draft endpoint with the revision number; offline/409
  recovery dialog.
- Product picker modal backed by a server-side catalogue search (name, SKU,
  id, category, brand), paginated.
- Desktop-first for precision editing; on small screens the editor offers
  text, image and offer edits in a single-column panel.
- Bengali and English UI strings kept in one dictionary so the admin language
  can be switched later.

---

## 7. Phases

| Phase | Scope | Status |
|---|---|---|
| 1 | Audit, schema, models, permissions, page validator, lifecycle service, editor sign-in, editor API, admin list/create, tests | **Done** (this change) |
| 2 | Next.js editor route `/builder/funnels/[id]`: shared renderer, editor shell, dnd-kit drag and drop, undo/redo, autosave with conflict handling, responsive preview, layers, 31 basic and marketing blocks, media library, catalogue search API, `orders` attribution columns (migration only) | **Done** |
| 3 | Product picker, 9 product blocks, offer builder, server-side pricing engine, publish-time catalogue audit (section 12; no `funnel_offers` table, see there) | **Done** |
| 4 | Shared `StockLedger` (storefront and funnel), inventory audit and migration plan, order form blocks, `FunnelOrderService` writing ordinary orders, idempotency, delivery charge, COD + Paystation, order attribution, minimal live page `/funnel/[slug]` | **Done** on test databases; checkout is off by default and nothing is applied to the main database (see PHASE4_CHECKOUT.md, PHASE4_INVENTORY_*.md) |
| 5 | Public page `/funnel/[slug]` with cache refresh on publish, secret expiring preview links, scheduled publish and unpublish, version history and restore, search and social settings, sitemap, eight Bengali/English templates, template setup checklist, orders dashboard integration; all stock writers (POS, purchases, returns, adjustments, order edit/cancel, product form) moved onto the shared stock rules | **Done** on test databases (see PHASE5_PUBLIC_PAGES.md, PHASE5_INVENTORY.md). Saved sections UI deferred |
| 6 | Analytics events and dashboard, Pixel/GTM/CAPI wiring with consent and server-verified Purchase, campaign attribution, test orders, real HTTP 404, performance pass, security review and fixes, Paystation reliability and unpaid-order expiry, saved sections, builder polish, deployment guide | **Done** on test databases (see PHASE6.md, DEPLOYMENT.md) |

---

## 8. Risks and safeguards

| Risk | Safeguard |
|---|---|
| Overselling the last unit when two orders race (UC-18). The storefront path writes stock with a read-modify-write and no lock, so it can also overwrite a concurrent decrement. | Funnel orders will decrement with a conditional atomic update (`qty = qty - n WHERE qty >= n`) inside the order transaction. Fully closing the gap also needs the storefront path to stop writing absolute values — a one-line change per call site, proposed separately for approval. |
| Client-side price tampering (UC-11) | The browser sends only offer keys, variants and quantities. Prices, discounts and delivery are recomputed on the server from the published version's compiled offers; totals below zero are impossible by construction. |
| Duplicate submissions (UC-12) | Idempotency key per checkout attempt, unique in the database; a repeated key returns the first result. |
| Live page changing under customers (UC-10) | Immutable versions; autosave never touches the published version. |
| Stored XSS through page content | Typed allowlist validation, rich-text sanitizer, no raw HTML/CSS/JS blocks, link scheme allowlist. React escapes all text on render. |
| Editor token leakage | One-time 60-second handoff code; token never in a URL; token limited by name, ability, user type and age. |
| Permission bypass via direct API calls (UC-17) | Every endpoint checks its own Spatie permission; covered by tests. |
| Breaking the existing site | No existing behaviour changed. Touched files: `routes/admin.php`, `routes/api.php` (routes appended), `app/Http/Kernel.php` (one middleware alias), the admin sidebar (one menu block), `phpunit.xml` (test database). |
| Running `php artisan migrate` on production by habit | Module migrations live outside the default path; documented `--path` command and SQL file. |
| MariaDB on the production host | No JSON column type is used (`longtext` + application-side JSON), no MySQL-8-only syntax. |
| Tests touching real data | Tests run only against `*_test` databases (guard in `FunnelTestCase`) inside rolled-back transactions. |
| Next.js 16 differences | Read the bundled docs before Phase 2; keep the editor on its own route so public pages never load it. |
| Spatie permission cache after an SQL import | "Clear Cache" in the admin (documented). |

---

## 9. Deployment

### Backend

Apply the schema together with the code (or just before it). If the code is
live without the tables, the menu is still hidden from staff without the new
permissions, but a Super Admin clicking **All Funnels** gets a database error.

Shell access:

```bash
php artisan migrate --path=database/migrations/sales_funnel --force
php artisan permission:cache-reset   # or "Clear Cache" in the admin
```

No shell: import `sqlupdates/sales_funnel_v1.sql` in phpMyAdmin, then use
**Clear Cache** in the admin. The file is safe to import twice.

Environment (all optional in Phase 1):

| Variable | Default | Meaning |
|---|---|---|
| `FUNNEL_PUBLIC_URL` | `FRONTEND_URL` | Storefront origin used to build `/funnel/{slug}` links. |
| `FUNNEL_EDITOR_URL` | empty | Origin of the Next.js app that hosts the editor. Empty hides the Edit action. Set it once Phase 2 is deployed. |
| `FUNNEL_REVALIDATE_URL`, `FUNNEL_REVALIDATE_SECRET` | empty | Storefront cache refresh endpoint and shared secret (Phase 5). Empty: pages refresh within 60 seconds on their own. |
| `FUNNEL_CHECKOUT_ENABLED` | `false` | Lets funnel pages take real orders. Needs the order columns, the attempts table and, recommended, the stock-table changes (PHASE4_CHECKOUT.md section 1). |
| `INVENTORY_FIFO_TRANSACTIONAL` | detected | Tests only: forces the stock service to treat purchase batches as transactional or not. |

After changing `.env`: `php artisan config:clear` (or Clear Cache).

Then grant the new permissions to staff roles in **Staff → Roles → Sales Funnel**.
Super Admin has them automatically.

No new services: no Redis, no queue worker, no Node on the backend host.

### Rollback

1. Code: revert the commit. The module's routes, menu entry and middleware
   alias go with it.
2. Database (destroys funnel data, orders untouched — back up first):
   `php artisan migrate:rollback --path=database/migrations/sales_funnel --force`
   or import `sqlupdates/sales_funnel_v1_rollback.sql`.
3. To hide the module without removing anything, remove the Sales Funnel
   permissions from all roles.

---

## 10. Testing

```bash
php tests/create_test_database.php          # once: copies the schema into misron_test (no personal data)
DB_DATABASE=misron_test php artisan migrate --path=database/migrations/sales_funnel --force
php vendor/bin/phpunit --filter Funnel
```

`phpunit.xml` points tests at `misron_test`. The test database receives the
table structure of the development database and only non-personal reference
rows (languages, settings, currencies, add-ons, roles, permissions).

| Suite | Covers |
|---|---|
| `tests/Unit/Funnel/PageValidatorTest` | normalization, unknown types, nesting, duplicate ids, depth/size limits, XSS sanitizing with Bengali text, link schemes, forced checkout fields, publish completeness, offer rules, countdown truthfulness, per-page limits, schema versions, error paths |
| `tests/Feature/Funnel/FunnelLifecycleTest` | slugs (incl. Bengali), revisions and conflicts, invalid drafts, version isolation (UC-10), publish checks, state machine and audit trail, duplication (UC-09), soft delete, rollback |
| `tests/Feature/Funnel/FunnelEditorApiTest` | one-time handoff, token restrictions and expiry, permission enforcement on direct API calls (UC-17), full editor flow, registry |
| `tests/Feature/Funnel/FunnelAdminPagesTest` | admin list and empty state, staff/customer access, create → editor redirect, slug errors, publish errors |

### Manual check for Phase 1

1. Apply the migration to the development database (command above).
2. Sign in as an admin: the sidebar shows **Sales Funnels → All Funnels /
   Create Funnel**; the list shows the empty state.
3. Create a funnel with a Bengali title and no address: it appears as Draft
   with a transliterated address such as `/funnel/blendar-mega-ofar`.
4. Duplicate it: a new Draft "… (Copy)" with `-copy` in the address.
5. Publish the empty draft from the list: the page explains that a section is
   needed (sections are added in the editor, Phase 2).
6. Archive, see it under the Archived tab, restore it.
7. Delete it, then create a new funnel with the same address: accepted.
8. Give a staff role only `view_funnels`: the list opens, Create/Publish/Delete
   are hidden, and posting to the publish URL directly answers 403.

---

## 11. Phase 2 — what was added

Frontend (misron_frontend): `funnel/core` (tree, rules, style, paths — pure logic),
`funnel/blocks` + `funnel/render` (shared renderer; 31 block types), `funnel/editor`
(store, operations, canvas, panels), route `app/builder/funnels/[id]`.

Backend: media API (`GET/POST /funnel-editor/media`, raster images only, stored in the shared
`uploads` library), catalogue search (`GET /funnel-editor/products`: name, SKU, id, category, brand;
read-only, no product data copied), migration `2026_10_23_100000_add_funnel_columns_to_orders_table`
(+ `sqlupdates/sales_funnel_v2_orders*.sql`).

**The orders migration has NOT been applied to the development or production `misron` database.**
It has only been run against the throw-away `misron_test` / `misron_e2e_test` databases.

Extra environment variable: `FUNNEL_MEDIA_MAX_KB` (default 5120).
Next.js side: nothing new in production; `FUNNEL_EDITOR_URL` on the backend must be the storefront origin.

Blocks not yet offered in the editor (no renderer/controls yet): all product, offer, bundle, price,
variation, quantity, coupon, summary, delivery, payment, purchase, checkout and confirmation blocks,
`customer_reviews`. The server registry already validates them; the library hides them.

### Running the editor tests
```
# one-time, from misron_backend
TEST_DB_DATABASE=misron_e2e_test php tests/create_test_database.php
DB_DATABASE=misron_e2e_test php artisan migrate --path=database/migrations/sales_funnel --force
# from misron_frontend
npm run typecheck:funnel
npm run test:funnel-core       # unit, no servers
npm run test:funnel-editor     # E2E: starts PHP :8001 (test DB) and next dev :3100, uses installed Chrome
```

---

## 12. Phase 3 — products and offers

### What a funnel stores, and what it never stores

A page holds **references and rules**, never product data:

| Stored in the funnel (immutable once published) | Read live from the store on every request |
|---|---|
| offer key; product id and variation per item; how many pieces make one offer unit | product name, photos, description |
| the rule: type, percent / amount / price, quantity price breaks, buy-X-get-Y, free-gift product, free-delivery flag | list price, the product's own discount, product tax |
| start and end date, per-order limit | stock quantity and whether a product can be sold (published, approved, not digital/auction/wholesale) |
| the funnel's own wording: card title, description, badge, button text, picture, preselected flag | which variations exist and their prices |

So a website price change moves the payable amount through the same rule and
never edits the rule; a draft edit never reaches a published version; stock
changes show on the page without anyone touching the funnel.

### Pricing (server only)

`AppServicesFunnelOffersOfferEngine` is the single calculation behind
both what a card shows (`resolve`) and what a selection costs (`quote`). The
browser sends offer key, variation and quantity and nothing else; any price,
total or discount in a request is ignored (tested).

- Money is whole poisha (`Money`), rounded half up; bundle discounts are split
  across items by largest remainder so lines always add up to the total.
- The website price comes from the storefront's own `cart_product_price()`, so
  the shop and funnels agree. Funnel offers apply **on top of** it.
- A discount can never make a product free or negative (offer is paused and
  publishing is blocked); `fixed_price` above the current website price gives
  no discount rather than overcharging.
- Stock is checked in total across all selected offers that draw on the same
  product/variation; a free gift needs gift stock or the offer pauses.
- A quote separates: list subtotal, website subtotal, funnel discount, product
  subtotal, tax (on the price charged), delivery (`calculated_at_checkout`, with
  the free-delivery flag) and total before delivery. It also returns a
  `fingerprint`; Phase 4 checkout will send back the one the customer saw, and a
  mismatch means a price moved and must be confirmed.

### Endpoints

| Endpoint | Auth | Purpose |
|---|---|---|
| `POST /funnel-editor/offers/resolve` | editor token, `edit_funnel` | live card data for draft offers |
| `POST /funnel-editor/offers/quote` | same | priced preview against draft offers |
| `GET /funnels/{slug}/offers` | public, throttled | resolved offers of the **published** version |
| `POST /funnels/{slug}/quote` | public, throttled | authoritative price of a selection (published version only) |

### Changes to earlier decisions

- **No `funnel_offers` table.** The plan compiled offers into a table at publish
  time. A published version is immutable JSON, so offers are read from it (and
  cached per version id); a second copy would only add a place to go stale.
- A page with product blocks but no checkout form now publishes with a warning
  (`FUNNEL_REQUIRE_CHECKOUT=false`). Phase 4 turns this into an error.
- Wholesale, digital and auction products are not offered in the picker.

### Not changed

Checkout, `ApiV2OrderController::store`, products, stock, prices and orders.
The `orders.funnel_id` migration remains **unapplied** to the `misron` database.
