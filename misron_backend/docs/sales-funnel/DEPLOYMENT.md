# Sales Funnel Builder — Production Deployment Guide

Last updated: 2026-10-24 (production-readiness pass: PHP requirement, config cache warning, security updates). Applies to branch `feature/sales-funnel-builder` (backend and storefront).

This guide is for whoever deploys the module to the live shop: the Laravel 9 backend on the existing
host, the Next.js 16 storefront on its existing host, and the existing MySQL/MariaDB database.
**Nothing in it has been run against the live database.** Every database step was rehearsed on the
isolated `*_test` databases only.

Read it to the end once before starting. Each step says how to check it worked and what to do if it
did not.

---

## 0. Overview

| Part | What changes |
|---|---|
| Database | New tables (funnels, versions, templates, saved sections, audit log, checkout attempts, preview links, analytics events, order attribution, payment-gateway sessions), two nullable columns on `orders`, six permission rows. **Optional but recommended:** `purchase_products` converted to InnoDB plus two indexes (the stock-safety change). |
| Backend | New code only, plus fixes in shared code: stock writers (POS, purchases, returns, adjustments, order edit/cancel), Paystation payment confirmation, unpaid-order expiry. Routes, menu entries and two scheduled commands are added. |
| Storefront | New routes `/funnel/[slug]`, `/funnel/[slug]/preview/[token]`, `/builder/funnels/[id]`, `/api/funnel/revalidate`, `/api/funnel/purchase`. The store's loading screens moved from `app/loading.jsx` into each store section (no visible change); the Meta Pixel and GTM loaders skip draft previews and respect tracking consent. |
| Live checkout | **Off** until you switch it on (section 12). Pages can be built and published before that; their order form says ordering is not open. |

Order of work: **back up → check compatibility → database → backend → storefront → configure → smoke test →
switch checkout on.**

---

## 1. Compatibility checks

Run on the production host (or ask the host).

| Check | Command | Needed |
|---|---|---|
| PHP version | `php -v` | **8.4.1 or newer.** The committed `composer.lock` (unchanged by the funnel work apart from two security patches) contains `symfony/css-selector` and `symfony/filesystem` 8.1, which need PHP 8.4.1+, and `symfony/string`/`event-dispatcher` 7.4 (8.2+). All tests ran on PHP 8.5. If the host runs an older PHP, `composer install` refuses the lock file: either upgrade the host's PHP, or re-resolve the dependencies on a staging copy with `composer config platform.php <host version> && composer update` and run the whole test suite again before deploying. |
| Locked packages vs. host | `composer check-platform-reqs --lock --no-dev` | Every line "success" on PHP 8.4.x. On PHP 8.5 the only expected failure is `phpoffice/phpspreadsheet` (declares < 8.5); install there with `--ignore-platform-req=php+` (DEPLOYMENT_RUNBOOK.md section B.1). |
| PHP extensions | `php -m` | `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `gd` or `imagick` (already needed by the shop) |
| Database | `SELECT VERSION();` | MySQL 5.7+ or MariaDB 10.3+. No JSON column type, window functions or `SKIP LOCKED` are used. |
| Storage engines | `SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('orders','products','product_stocks','purchase_products');` | Any. The module works on MyISAM; InnoDB on `purchase_products` adds the stock safety described in section 3.3. |
| Cron | host control panel | A cron job that can run `php artisan schedule:run` every minute (section 7). |
| Node (storefront) | host settings | The Node version the storefront already builds with (Next 16 needs Node 20.9+). |
| Disk | `df -h` | Free space of at least twice the size of `purchase_products` if you run the stock-safety change. |

---

## 2. Backup and recovery

**Before anything else:**

1. Put the shop in maintenance mode: `php artisan down` (or the host's equivalent). Orders must not be placed
   while tables are being changed.
2. Full database dump, kept off the server as well:
   ```bash
   mysqldump -u USER -p --single-transaction --routines --triggers --set-gtid-purged=OFF DBNAME > misron-before-funnels.sql
   ```
   On MyISAM tables `--single-transaction` does not give a consistent snapshot; maintenance mode is what makes the
   dump consistent.
3. **Restore it once** into an empty scratch database and compare row counts of `orders`, `order_details`,
   `products`, `product_stocks`, `purchase_products`, `users`. A backup that has never been restored is not a backup.
4. Copy the code folders as they are now (backend and storefront), or note the commit each host runs.

Recovery is described per step below and summarised in section 13. **Not every schema change is reversible by a
transaction:** MySQL commits every `CREATE TABLE`/`ALTER TABLE` immediately. The rollback scripts undo the structure;
data written into new tables after the change is lost when they are dropped.

---

## 3. Database changes, in order

Two equivalent ways for each step: the SQL file in `sqlupdates/` (phpMyAdmin, no shell needed) or the artisan
command. Use one or the other. Every SQL file is safe to import twice (rehearsed: full rollback v5 → v1, then two
imports in a row, on 2026-10-24) and records itself in the `migrations` table,
so a later `migrate --path` skips it.

> **Never run a bare `php artisan migrate`.** Several old migrations of the shop are recorded as pending although
> their tables exist. The module's migrations live in their own folder and must be run with `--path`.

### 3.1 Funnel module tables (required)

| Order | File | What it does | Undo |
|---|---|---|---|
| 1 | `sales_funnel_v1.sql` | 5 tables (`funnels`, `funnel_versions`, `funnel_templates`, `funnel_saved_sections`, `funnel_audit_logs`) and 5 permissions | `sales_funnel_v1_rollback.sql` |
| 2 | `sales_funnel_v2_orders.sql` | `orders.funnel_id`, `orders.funnel_version_id` (nullable, indexed). Existing orders keep NULL. | `sales_funnel_v2_orders_rollback.sql` |
| 3 | `sales_funnel_v3_checkout.sql` | `funnel_checkout_attempts` (idempotency) | `..._v3_checkout_rollback.sql` |
| 4 | `sales_funnel_v4_publishing.sql` | `funnels.unpublish_at`, template library columns, `funnel_preview_links` | `..._v4_publishing_rollback.sql` |
| 5 | `sales_funnel_v5_analytics.sql` | `funnel_events`, `funnel_order_attributions`, `payment_gateway_attempts`, permission `view_funnel_analytics` | `..._v5_analytics_rollback.sql` |

Shell alternative for all five at once:

```bash
php artisan migrate --path=database/migrations/sales_funnel --force
php artisan permission:cache-reset
```

**Check:** `SELECT migration FROM migrations WHERE migration LIKE '2026_10_2%';` lists 14 rows;
`SHOW TABLES LIKE 'funnel%';` lists 10 tables; `SHOW COLUMNS FROM orders LIKE 'funnel%';` lists 2 columns.

The `ALTER` on `orders` (step 2) can take a while on a very large table; MySQL 8 and MariaDB 10.3+ keep the table
usable during it, but do it inside the maintenance window.

### 3.2 Template library (required for the template gallery)

```bash
php artisan funnel:templates
```

Writes the eight designs (Bengali and English, 16 rows) into `funnel_templates`. Safe to run again after an update;
it replaces the system templates and never touches funnels made from them. Without shell access, run it once from a
machine that can reach the database with the production `.env`, or ask the host. **Run it only after 3.1.** It
targets the database in `.env`; double-check `DB_DATABASE` first.

### 3.3 Stock safety (recommended; the same change the stock tests ran on)

Converts `purchase_products` to InnoDB and adds one unique key and one index. Follow
`PHASE4_INVENTORY_MIGRATION_PLAN.md` section 6 exactly. Summary:

1. `inventory_v1_preflight.sql` — reads only. **Stop** on any blocker (duplicate stock rows, NULL variations).
2. Backup + restore check (section 2 above covers it).
3. `inventory_v1_stock_safety.sql` — keeps in-database copies (`*_pre_inv1`), converts, adds keys.
4. Verify with the queries at the end of the script.
5. Undo if needed: `inventory_v1_stock_safety_rollback.sql`.

Without this step the stock code still works (it detects MyISAM and gives batch units back by hand), but a failed
order cannot roll back its batch consumption atomically.

### 3.4 Optional: clearing the dummy data (needs your separate written approval)

Your current orders, purchases and stock are test data. If you want the live shop to start clean, see section 14.
**Do not run it as part of the deployment.**

---

## 4. Environment variables

### Backend (`.env` of Laravel)

| Variable | Example | Required | Meaning |
|---|---|---|---|
| `FRONTEND_URL` | `https://shop.example` | yes (exists) | Storefront origin; payment returns go here. |
| `FUNNEL_PUBLIC_URL` | `https://shop.example` | no | Origin used for `/funnel/{slug}` links. Defaults to `FRONTEND_URL`. |
| `FUNNEL_EDITOR_URL` | `https://shop.example` | yes | Origin of the storefront that hosts the editor. Empty hides the Edit button. |
| `FUNNEL_REVALIDATE_URL` | `https://shop.example/api/funnel/revalidate` | recommended | Lets the backend refresh a page in the storefront cache the moment it changes. |
| `FUNNEL_REVALIDATE_SECRET` | 40+ random characters | with the above | Shared with the storefront. Generate with `php -r "echo bin2hex(random_bytes(24));"`. |
| `FUNNEL_CHECKOUT_ENABLED` | `false` | yes | **Keep `false` until section 12.** |
| `FUNNEL_REQUIRE_CHECKOUT` | `true` | recommended | Pages with products must have an order form to publish. |
| `FUNNEL_TEST_PHONES` | `01711111111,01822222222` | no | Orders from these numbers count as staff test orders. |
| `FUNNEL_TRACKING_SHARE_CUSTOMER_DATA` | `false` | no | `true` sends the buyer's **hashed** phone/email with Meta Purchase events. Only if your privacy notice covers it. |
| `FUNNEL_EVENT_RETENTION_DAYS` | `400` | no | Visitor events older than this are deleted nightly. |
| `ONLINE_ORDER_EXPIRY_ENABLED` | `false` → `true` | recommended | Turns on the unpaid-order expiry (section 7). |
| `ONLINE_ORDER_EXPIRE_MINUTES` | `60` | no | Age after which an unpaid online order is checked (min 15). |
| `TRUSTED_PROXIES` | storefront server IPs | see note | Makes per-customer rate limits and IP blocking work behind the storefront's `/api/v2` proxy. |
| `PAYSTATION_MERCHANT_ID`, `PAYSTATION_PASSWORD`, `PAYSTATION_CALLBACK_URL` | | for online payment (exist) | Unchanged. |
| `FUNNEL_MEDIA_MAX_KB` | `5120` | no | Largest image the editor accepts. |
| `FUNNEL_EDITOR_EXCHANGES_PER_MINUTE` | `10` | no | How many times per minute one address may open the editor. Raise it if many staff share one office address. |
| `APP_ENV`, `APP_DEBUG` | `production`, `false` | yes (exist) | Debug pages show environment values and stack traces to visitors. |

**`TRUSTED_PROXIES` note.** The storefront forwards shoppers' API calls to Laravel. Without this setting Laravel sees
every shopper as the storefront server's IP address: the per-address order limit (20 per minute) is shared by all
shoppers, and blocking one IP in "Blocked IPs" blocks everyone. Set it to the storefront's outgoing IP addresses
(comma separated, CIDR allowed). A serverless storefront host has no fixed addresses; then leave it empty and rely on
the per-phone limit (6 orders per 10 minutes per number), or put both behind one proxy. Do **not** set `*` while the
Laravel API is reachable directly from the internet: anyone could then fake their address.

After editing: `php artisan config:clear` (or "Clear Cache" in the admin).

### Storefront (host environment for Next.js)

| Variable | Required | Meaning |
|---|---|---|
| `BACKEND_API_URL` | yes (exists) | Laravel API base, e.g. `https://admin.example/api/v2`. |
| `NEXT_PUBLIC_SITE_URL` | yes (exists) | Storefront origin. |
| `FUNNEL_REVALIDATE_SECRET` | recommended | Same value as the backend's. Without it the refresh endpoint refuses everything and pages refresh within 60 s on their own. |
| `NEXT_PUBLIC_FACEBOOK_PIXEL_ID`, `META_CONVERSIONS_API_TOKEN`, `META_TEST_EVENT_CODE` | exist | Unchanged; the funnel pages reuse them. The token must never get a `NEXT_PUBLIC_` prefix. |
| `NEXT_PUBLIC_GTM_ID` | exists | Unchanged; GA4 events reach GA4 through your GTM container. |
| `NEXT_PUBLIC_TRACKING_CONSENT` | no | `implied` (default): tags run unless the visitor refused (`tracking_consent=denied` cookie). `required`: tags run only after `tracking_consent=granted`. Set `required` if you add a consent banner or sell where the law needs opt-in. |

`NEXT_PUBLIC_*` values are built into the JavaScript: **rebuild** the storefront after changing them.

---

## 5. Deployment order

1. Maintenance mode on, backup done and restore-checked (section 2).
2. Database: section 3.1, then 3.3 if you do it.
3. **Backend code**: deploy the branch. Then
   ```bash
   composer install --no-dev --optimize-autoloader   # only if vendor/ is not deployed as files
   php artisan config:clear && php artisan route:clear && php artisan view:clear
   # Do NOT run `php artisan config:cache` or `php artisan optimize`: the shop reads ~290 settings with env()
   # outside the config files (payment credentials, FRONTEND_URL, ...). With a cached config every one of them
   # becomes empty and online payment and payment returns break.
   php artisan permission:cache-reset
   php artisan funnel:templates
   ```
   (Without shell: "Clear Cache" in the admin; ask the host to run `funnel:templates` once.)
   `composer install` ends with `artisan package:discover`, which starts the application and therefore needs the
   database to be reachable; a "connection refused" there means the database settings in `.env`, not the code.
4. Backend `.env` (section 4), `php artisan config:clear` again.
5. **Storefront**: set its environment, build and deploy as you always do (`next build`). The storefront must be
   deployed after the backend: its new pages call new API routes.
6. Grant permissions: **Staff → Roles → Sales Funnel** (`view_funnels`, `add_funnel`, `edit_funnel`,
   `publish_funnel`, `delete_funnel`, `view_funnel_analytics`). Super Admin has all of them.
7. Maintenance mode off.
8. Smoke tests (section 11), with checkout still off.
9. Later, when ready: section 12.

Deploying the backend code before the database is safe for the shop (the module checks for its tables), but a Super
Admin opening "All Funnels" before the tables exist gets a database error. Do the database first.

---

## 6. Hosting notes for the storefront

* **Real HTTP 404 for closed pages.** Missing, unpublished, archived and expired funnel pages answer HTTP 404 and live
  pages HTTP 200. This needs no proxy/middleware (the earlier `proxy.js` could not be loaded by the host). It works
  because no loading screen sits above `/funnel/*`: the page checks the funnel before sending anything. If someone
  adds `app/loading.jsx` back at the top level, closed pages return to "200 + noindex". Keep loading screens inside
  the store's own folders.
* **Page cache.** Each visit renders the page on the storefront server from cached data: the page document and its
  offers are cached for 60 seconds and dropped immediately when the backend calls `/api/funnel/revalidate`. (A fully
  static cached page was measured slower on phones: the store's root layout makes the browser draw it twice.) Each
  view therefore runs the storefront's server function once, without calling the backend while the data is cached. Product prices and stock on a cached page can be up to 60 s old in the first paint; the
  browser re-reads them right after loading, and every order is priced on the server.
* **Image optimizer.** Product photos on funnel pages go through the same `/_next/image` optimizer and `/media` route
  as the storefront. The backend host must be in `IMAGE_URLS` / `BACKEND_API_URL` as it is today.

---

## 7. Cron and queue

One cron entry on the backend host (it probably exists already):

```
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

What the scheduler runs for this module:

| Command | When | What |
|---|---|---|
| `funnel:reconcile` | every minute | Applies scheduled publish/unpublish times to the stored status and refreshes the storefront cache. Customers do not depend on it (the page checks the clock itself). |
| `orders:expire-unpaid` | every 5 minutes | Unpaid online-payment orders older than `ONLINE_ORDER_EXPIRE_MINUTES`: asks Paystation about each payment session; marks paid ones paid; cancels and returns stock **only** when the gateway confirms the payment failed, or no payment was ever started; leaves unclear ones alone and, after 48 h, adds a note for staff. Does nothing while `ONLINE_ORDER_EXPIRY_ENABLED=false`. Try `php artisan orders:expire-unpaid --dry-run --force` first. |
| `funnel:prune-events` | daily 03:30 | Deletes visitor events older than the retention period. |
| `queue:work --once` | every minute (existing) | Unchanged; the module needs no queue worker. |

No Redis, no Node process and no extra service on the backend host.

---

## 8. Cache invalidation setup

1. Same random secret in both `.env`s (`FUNNEL_REVALIDATE_SECRET`).
2. Backend `FUNNEL_REVALIDATE_URL=https://<storefront>/api/funnel/revalidate`.
3. Test: publish a page, unpublish it, open its address within a few seconds: it must answer 404.
   If it still shows for up to a minute, the call is failing: check `storage/logs/laravel.log` for
   "Funnel page cache refresh failed" and that the backend host can reach the storefront over HTTPS.

---

## 9. Payment configuration checks

* Paystation: `PAYSTATION_MERCHANT_ID`, `PAYSTATION_PASSWORD` set; the **Paystation sandbox** switch in Business
  Settings is **off** for live payments; `PAYSTATION_CALLBACK_URL` (if set) is `https://<backend>/api/v2/paystation/callback`;
  the IPN URL in the Paystation dashboard is `https://<backend>/api/v2/paystation/ipn`.
* Payment methods: **Cash on delivery** and **Paystation** active under Payment Methods. Funnel pages offer only
  methods that are both active in the store and allowed by the page.
* What changed in Paystation handling (website and funnels alike):
  - a paid or cancelled order is never sent to the gateway again;
  - every payment session is recorded (`payment_gateway_attempts`) so its outcome can be asked for later;
  - the return page cancels an order only when the gateway **confirms** the payment failed. When the gateway cannot be
    reached or answers "pending", the order stays as it is, the customer is told the payment is being confirmed and is
    **not** offered a second payment;
  - "paid" is applied once even when the return page and the notification arrive together (lock);
  - the return address is public and invoice numbers are guessable, so a "failed" answer cancels an order only for
    an invoice the store itself started (recorded in `payment_gateway_attempts`);
  - a payment that arrives after an order was cancelled is recorded as paid and the order gets an "ATTENTION" note;
    the analytics dashboard lists it under "Needs attention". Refund or restore it by hand.
* Shipping: funnel orders use the store's own delivery charge setting (flat rate, seller-wise, product-wise or
  area-wise). Carrier-wise shipping is not supported on funnel pages; with it the page says ordering is not available.

---

## 10. Tracking setup (Meta, GA4)

Nothing new to install: funnel pages use the Pixel and GTM container the store already loads, and the existing
Conversions API token.

| Event | Browser | Server (Conversions API) | Fires when |
|---|---|---|---|
| PageView | Pixel base code (existing) | — | page opens |
| ViewContent / `view_item` | yes | relay, same event id | an offer card is at least half on screen, once per offer |
| AddToCart / `add_to_cart` | yes | relay, same event id | the visitor chooses an offer (not the one pre-selected for them) |
| InitiateCheckout / `begin_checkout` | yes | relay, same event id | first touch of the order form, once |
| Purchase / `purchase` | yes | `/api/funnel/purchase`, value read from the order | cash on delivery: order placed; online: payment confirmed. Never for cancelled or test orders. Event id derived from the order, so Meta keeps one event. |

**Verify in Meta:** Events Manager → your Pixel → Test events. Put the code in `META_TEST_EVENT_CODE` (storefront),
rebuild, open a funnel page in the browser shown there, choose an offer, place a cash-on-delivery test order (use the
editor's **Place a test order** link only if you want to see that nothing is sent). Each event should appear twice
(Browser and Server) and be marked **Deduplicated**. Remove `META_TEST_EVENT_CODE` afterwards.

**Verify in GA4:** in GTM, Preview mode on the funnel page: the data layer shows `view_item`, `add_to_cart`,
`begin_checkout`, `purchase` with an `ecommerce` object. Your container needs GA4 Event tags for these names (most
GA4 e-commerce setups already have them for the store). Then GA4 → Admin → DebugView.

**Consent:** with `NEXT_PUBLIC_TRACKING_CONSENT=required`, nothing is sent to Meta or Google until the visitor has the
`tracking_consent=granted` cookie; Google consent mode starts "denied". The store has no banner yet; any banner only
needs to set that cookie.

**Campaign links:** add `?utm_source=facebook&utm_medium=paid_social&utm_campaign=<name>` to ad links. Meta and Google
add `fbclid`/`gclid` themselves. Orders keep the campaign, the click id (with consent), the device and the source.

---

## 11. Final smoke-test checklist (checkout still off)

Backend admin:
- [ ] Sidebar shows **Sales Funnels → All Funnels / Create Funnel / Analytics** for an admin; a staff role without the
      permissions does not see them.
- [ ] Create Funnel shows nine cards (eight designs and blank) with pictures; Bengali/English switch works.
- [ ] Create a funnel from "Classic" with a real product; the editor opens with the product filled in.
- [ ] Editor: drag a block, undo, see "Saved"; save a section under **Saved**; Preview at phone width.
- [ ] Publish. The live address opens (HTTP 200) and shows the product with its real price.
- [ ] The order form says ordering is not open yet.
- [ ] Unpublish: the address answers **404** within seconds.
- [ ] Share a draft link; it opens in a private window; revoke it; it answers 404.
- [ ] **Analytics** opens and shows the visit.

Existing shop (regression):
- [ ] Home page, a category, a product page, cart and checkout open normally.
- [ ] Place a normal cash-on-delivery website order; it appears in Orders; cancel it; stock returns once.
- [ ] POS sale and a purchase entry still work; stock moves as before.

If PHP on the host is not the version tested here, also run `php -l` over the changed files and click through the
admin funnel pages once with `APP_DEBUG=false` while watching `storage/logs/laravel.log`.

---

## 12. Switching live checkout on

Only after sections 3–11 pass.

1. `FUNNEL_CHECKOUT_ENABLED=true`, `php artisan config:clear`.
2. Open the editor's **Publish** dialog on a live page: the checks must not say "Ordering is switched off".
3. **Place a test order** (Publish dialog, live page): fill the form, choose cash on delivery. It appears in Orders
   with the **Funnel** and **Test** badges, stock goes down. Cancel it: stock comes back once.
4. If Paystation is on: place a low-value online order through a test link, pay in the gateway, return: the page says
   paid, the order is Paid. Cancel the payment on a second try: the page says failed, the order is cancelled, stock is back.
5. `ONLINE_ORDER_EXPIRY_ENABLED=true` once you have watched one dry run:
   `php artisan orders:expire-unpaid --dry-run --force`.
6. Watch `storage/logs/laravel.log` and the Orders list for the first day.

To stop taking orders at any moment: `FUNNEL_CHECKOUT_ENABLED=false` and `php artisan config:clear`. Pages stay
online and say ordering is closed; nothing else changes.

---

## 13. Rollback and recovery

| Situation | Action | Data lost |
|---|---|---|
| Something is wrong with ordering | `FUNNEL_CHECKOUT_ENABLED=false` | nothing |
| A funnel page misbehaves | Unpublish it in the admin | nothing |
| Unpaid-order expiry misbehaves | `ONLINE_ORDER_EXPIRY_ENABLED=false` | nothing; orders it cancelled keep their note ("Cancelled automatically: …") and can be reviewed |
| Backend code problem | Deploy the previous release. The new tables stay and are ignored. | nothing |
| Storefront problem | Redeploy the previous storefront build. Funnel pages then answer the old 404 screen. | nothing |
| Remove the module's database changes | Maintenance mode, then the rollback files **in reverse order**: v5, v4, v3, v2, v1 (and `inventory_v1_stock_safety_rollback.sql` if 3.3 was done) | All funnels, versions, templates, saved sections, analytics and attribution. **Orders are kept**; `orders.funnel_id` disappears with v2's rollback, so funnel orders become ordinary orders. Back up first. |
| Data damaged | Restore the section 2 dump into a scratch database and copy back only the damaged rows | prefer row repair: a full restore loses every order since the backup |

`CREATE`/`ALTER`/`DROP` statements commit immediately in MySQL; there is no "undo" except the rollback scripts and
the backup.

---

## 14. Optional: starting the live shop without the dummy data (needs separate approval)

Your existing orders, purchases and stock movements are test data. `inventory:reset-dummy-data` can clear them so
stock starts from clean, consistent records. **It has not been run on the main database and must not be run without
your written go-ahead for that specific run.**

What it removes (scope `transactions`, the default): orders, order lines, combined orders, carts, coupon usage,
commission history, refund requests, checkout OTPs, purchases and purchase lines, purchase returns, stock adjustments,
account statement logs, funnel checkout attempts, funnel order attribution and payment-gateway session rows. Every
stock quantity becomes 0 and accounts return to their opening balances.

What it never touches: products (unless you ask for scope `catalogue`), **product images and the uploads table,
categories, brands, attributes**, customers and **all user accounts including admins and staff**, roles and
permissions, business settings, languages, currencies, add-ons, the chart of accounts, and the funnel pages,
templates, saved sections and visitor events.

Procedure:

1. Backup and restore-check (section 2). Maintenance mode on.
2. Dry run — reports counts, changes nothing:
   ```bash
   php artisan inventory:reset-dummy-data --database=DBNAME --allow-non-test
   ```
3. Read the report. If it says online-paid orders exist, stop: those may be real payments.
4. Only with written approval:
   ```bash
   php artisan inventory:reset-dummy-data --database=DBNAME --allow-non-test --execute --confirm=DBNAME
   ```
5. Enter opening stock through **Purchases** (so every unit has a cost batch), not by typing quantities on products.
6. Recovery: restore the dump from step 1.

Rehearse it first on a copy: restore the dump into `shopcopy_test` and run steps 2 and 4 there.
