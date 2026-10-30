# Sales Funnel Builder — Production Readiness Report

Date: 2026-10-25. Branch `feature/sales-funnel-builder` (backend and storefront). Scope of this final stage: critical
security, checkout, payment and deployment issues only. No features were added or rebuilt.

**State of the live system: unchanged.** The main `misron` database has not been touched, live funnel checkout stays
off (`FUNNEL_CHECKOUT_ENABLED=false`), nothing was pushed, merged or deployed.

---

## 1. Verdict

**GO — for deployment with live funnel checkout off**, subject to the three owner actions below. No critical failure
is open: every test suite passes (section 6) and the remaining dependency advisories are either not reachable in this
shop or limited to staff/seller bulk upload (section 4).

**NO-GO for switching live checkout on** until the site has run with checkout off and the smoke tests in DEPLOYMENT.md
section 11 have passed on the live host. That is a separate, later decision (DEPLOYMENT.md section 12).

Before deploying:

1. Confirm the host's PHP version (needs 8.4.1+ for the committed `composer.lock`, see section 4).
2. Approve the database changes for the main database (DEPLOYMENT.md section 3).
3. Decide on the dummy-data reset (DEPLOYMENT.md section 14). It is optional and has not been run.

---

## 2. Fixed in this stage

| Area | Problem | Fix |
|---|---|---|
| Storefront dependency | Next.js 16.3.4 is in the range of four advisories (one critical): remote code execution in `next/og` (not used here), cache poisoning of cached pages on self-hosted servers, draft content leaking into cached pages, dev-server information disclosure. | Upgraded to **16.4.3** (same major; `sharp` and `source-map-js` updated with it). `npm audit --omit=dev`: 0 vulnerabilities. |
| Backend dependency | `symfony/http-foundation` 6.4.45: ReDoS through the `Cache-Control` request header (any visitor). `league/commonmark` 2.10.1: denial of service and a raw-HTML filter bypass. | Patch updates to 6.4.48 and 2.10.4. |
| Payment | The Paystation return address is public and invoice numbers follow a guessable pattern (`AIZ-<order>-<time>`). A made-up invoice number for someone else's order, answered "failed" by the gateway, would cancel that order and release its stock while the customer might be paying. | A "failed" answer now cancels an order only for an invoice the store itself sent to the gateway (`payment_gateway_attempts`). Test added; it fails without the fix. |
| Database migration | `sales_funnel_v2_orders.sql` was not safe to import twice (the guide said it was), and its rollback failed when the optional foreign keys exist (which the artisan migration adds on InnoDB), blocking the v1 rollback after it. | Import now checks for the columns first; rollback drops the foreign keys if present. Full chain rehearsed on a test database: rollback v5 → v1 to zero, then two imports in a row. |
| Deployment guide | Said PHP 8.0.2 – 8.2; the committed lock file needs 8.4.1+. Did not warn against `config:cache`, which would empty ~290 `env()` reads (payment credentials, return URLs). | Corrected and added: PHP requirement and `composer check-platform-reqs`, config-cache warning, `APP_ENV`/`APP_DEBUG`, database needed during `composer install`. |

### 2a. Deployment preparation round (2026-10-28)

| Area | Problem | Fix |
|---|---|---|
| **File uploads (critical, pre-existing)** | `POST api/v2/file/image-upload`, `profile/image-upload` and `profile/update-image` wrote the image to `uploads/all/<filename>` with the file name taken unchecked from the request. Any signed-in customer (registration is open) could overwrite and then delete files outside the uploads folder (`../../.env`, `index.php`), briefly place a `.php` file in the public folder, or store an unsanitised SVG. | One shared helper (`App\Services\Uploads\Base64ImageUpload`): the format is read from the image bytes (JPEG, PNG, GIF, WebP only), the stored name is generated, the given name is used as the display name only, 10 MB limit. The storefront's profile picture upload works as before. |
| **Web uploader without sign-in (pre-existing)** | `/aiz-uploader`, `/aiz-uploader/upload` and `/aiz-uploader/get-uploaded-files` had no login check; an upload was stored before the code failed on the missing user. | These routes require a signed-in user. Every page that uses the uploader is already behind a login. |
| SVG uploads | The SVG sanitiser has an unpatched bypass (stored XSS). | SVG and XML uploads are accepted from admin and staff accounts only; sellers and customers cannot upload them. Staff SVGs still go through the sanitiser. |
| PhpSpreadsheet | 1.30.0 with critical/high advisories, reachable through admin and seller bulk upload. | **1.30.7** with `maatwebsite/excel` **3.1.70** (only these two packages changed). Its PHP < 8.5 declaration is bypassed for the PHP 8.5 test machine with `--ignore-platform-req=php+` (upper bound only); on a PHP 8.4 host it installs cleanly. Spreadsheet read/write test added. |

Tests: `tests/Feature/Security/UploadSafetyTest.php` (5 tests, all fail on the old code) and
`SpreadsheetLibraryTest.php`.

## 3. Checked, no change needed

* Funnel checkout off: every checkout request is refused on the server while the setting is false (tested).
* Paystation: amounts are checked against the order; unclear answers never cancel; paid is applied once under a lock;
  paid or cancelled orders cannot be sent to the gateway again (tested).
* Storefront cache refresh endpoint: shared secret compared in constant time; refuses everything when unset.
* Editor API: same-origin through the storefront's `/api/v2` proxy, so the backend's CORS list does not apply to it.
* No `.env`, database dumps, logs, build output or test reports are tracked in either repository.
* The earlier Phase 6 security review (DEPLOYMENT.md / PHASE6.md section 9) still holds.

## 4. Remaining risks (not fixed here; owner decision)

| Risk | Why not fixed now | Recommendation |
|---|---|---|
| **Host PHP version (not yet known).** The lock file needs PHP 8.4.1+; PhpSpreadsheet 1.30.7 declares < 8.5. Laravel 9 officially supports up to 8.2; all tests pass on PHP 8.5. | Needs the host. | DEPLOYMENT_RUNBOOK.md section B.1: 8.4.x installs as is, 8.5.x with `--ignore-platform-req=php+`, 8.3 or older: stop. |
| **Laravel 9 is end-of-life.** `composer audit` lists advisories with no Laravel 9 fix: TokenGuard bypass (**not reachable**: no `token` guard is configured), CRLF in the `email` rule, file validation bypass, signed URL path confusion (used only for e-mail verification), debug-page XSS (off with `APP_DEBUG=false`). | A framework upgrade (9 → 12) is a project of its own. | Plan the upgrade after launch. Keep `APP_DEBUG=false`. |
| `enshrined/svg-sanitize` 0.22.0 (medium advisories) | No fixed version published. | **Mitigated:** SVG only from admin/staff accounts (section 2a). Update the package when a fix is released. |
| Purchase reported to Meta/Google only when the customer returns from the payment page | By design (PHASE6.md section 15). | None for launch. |
| No consent banner in the store | Out of scope. | Add one before advertising where opt-in is required. |

## 5. Database changes to apply (after approval)

All additive; rehearsed on the `*_test` databases only. In order, from `sqlupdates/` (phpMyAdmin) **or**
`php artisan migrate --path=database/migrations/sales_funnel --force` (never a bare `migrate`):

1. `sales_funnel_v1.sql` — funnel tables, permissions
2. `sales_funnel_v2_orders.sql` — `orders.funnel_id`, `orders.funnel_version_id`
3. `sales_funnel_v3_checkout.sql` — checkout idempotency
4. `sales_funnel_v4_publishing.sql` — scheduling, preview links, template library
5. `sales_funnel_v5_analytics.sql` — analytics, attribution, payment sessions
6. Then `php artisan funnel:templates` and `php artisan permission:cache-reset`.
7. Recommended: `inventory_v1_preflight.sql` → `inventory_v1_stock_safety.sql` (stock safety, PHASE4 plan).

Each has a `_rollback.sql`; undo in reverse order. Backup and restore check first (DEPLOYMENT.md section 2).

## 6. Test results

| Suite | Result |
|---|---|
| Backend PHPUnit, original schema (`misron_test`), 2026-10-28 | 306 tests: all pass except the old `ExampleTest` |
| Backend PHPUnit, converted schema (`misron_target_test`), 2026-10-28 | 306 tests: all pass except the old `ExampleTest` (2 skipped: need duplicate stock rows the unique key forbids) |
| Concurrency (`misron_inventory_test`), 2026-10-28 | 22 / 22 |
| Upload and spreadsheet safety tests (new) | 6 / 6 (the 5 upload tests fail on the old code) |
| Frontend unit / type check / funnel lint | 60 / 60, clean, clean |
| Playwright end-to-end, full suite, production build with Next 16.4.3, after all backend changes (2026-10-28) | **81 / 81** (14.7 min, undisturbed) |
| Migration rehearsal on a fresh dump of the local `misron` database (`misron_restore_test`) | restore check equal on 8 tables; preflight without blockers; v1–v5, templates, permissions and stock safety applied; stock totals unchanged; existing orders untouched (DEPLOYMENT_RUNBOOK.md section D). The backend suite on this real-data copy: 279 of 306 pass, 2 skipped; of the 25 others, one is the old `ExampleTest` and 24 assume an empty orders/uploads table or read "the latest order" and pick up the copy's real rows (test assumption, not a fault). |
| `composer audit` | only Laravel 9 (no fix in 9.x; see section 4) and `svg-sanitize` (mitigated) remain |
| `npm audit --omit=dev` | 0 vulnerabilities |

The old `ExampleTest` failure (the `/` route redirects to login) is unchanged on purpose.

### Browser-test failures seen during this stage, and their cause

* **Tracking test (one run):** the test pressed "Order" before the order total had refreshed after choosing an offer
  and district; the server correctly answered "the price changed, press again". The test now waits until the total
  has stopped changing, as a customer would. The price-change guard itself is unchanged and has its own test.
* **Four publishing tests (two runs):** pages stayed visible after being unpublished, and the editor's Publish button
  kept spinning. Timing logs added for the investigation (since removed) showed that a publish normally takes
  0.2–0.9 s and the storefront's cache refresh 0.01–0.1 s, but in the failing runs one publish request stalled for
  39 s and one refresh call for 9 s. The storefront's `/api/v2` proxy gives up after 30 s, so the editor never got
  its answer. Those runs took 23–24 minutes instead of 15–16, and the same code passed all 81 tests in the next
  undisturbed run. Cause: load and pauses on the test machine (the single-threaded PHP test server and other
  programs; the computer also went to sleep twice during runs), not the application. No test was weakened and no
  application code was changed for it.
* **What this means for production:** publishing is a sub-second request, far from the 30-second proxy limit. A
  cache refresh that does fail is logged ("Funnel page cache refresh failed") and the page still updates within its
  60-second cache lifetime.

## 7. Deployment

Follow `DEPLOYMENT.md` from the top: backup → compatibility (PHP!) → database → backend → storefront → environment →
smoke tests with checkout off. Live checkout, the unpaid-order expiry and the dummy-data reset each stay off until
separately approved.
