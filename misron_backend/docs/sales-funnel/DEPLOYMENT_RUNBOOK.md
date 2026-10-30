# Sales Funnel — Production Deployment Runbook (for approval)

Prepared 2026-10-28. Companion to `DEPLOYMENT.md` (background and explanations) and `PRODUCTION_READINESS.md`
(findings). This file holds the **exact commands**, in order, with the expected result of each check.

**Nothing in this runbook has been run on production.** Funnel checkout stays off throughout
(`FUNNEL_CHECKOUT_ENABLED=false`). The dummy-data reset is **not** part of it.

Every step marked **[GATE]** needs your explicit go-ahead at the time it is run. Everything before Gate 1 only reads.

| Gate | What it allows |
|---|---|
| **Gate 1** | Push the reviewed branches and merge them into the branch the hosts deploy from |
| **Gate 2** | Maintenance mode and the database changes on the production database |
| **Gate 3** | Deploy backend and storefront code, change `.env` |
| **Gate 4** (later, separate) | Switch funnel checkout on — only after the post-deployment GO/NO-GO |

Placeholders: `BACKEND_DIR` (Laravel folder on the host), `DBNAME` / `DBUSER` (production database),
`https://ADMIN_HOST` (backend), `https://SHOP_HOST` (storefront), `SCRATCH_DB` (an empty scratch database on the
same server, e.g. `misron_restore_test`).

---

## A. Information needed before starting

1. Shell (SSH) access to the backend host, or only cPanel/phpMyAdmin? (Both are covered below.)
2. Output of the pre-flight commands in section B.
3. Where the storefront runs (the backend CORS list names `misron-frontend.vercel.app`) and whether it has fixed
   outgoing IP addresses (needed for `TRUSTED_PROXIES`, section F).
4. Which branch each host deploys from.

---

## B. Pre-flight on the host (read only)

```bash
cd BACKEND_DIR
php -v                                        # need 8.4.x (see B.1)
php -m | grep -iE 'pdo_mysql|mbstring|openssl|curl|fileinfo|gd|imagick|zip|xml'
composer --version
git log --oneline -1                          # note the commit running today (rollback point)
grep -E '^(APP_ENV|APP_DEBUG|FRONTEND_URL|QUEUE_CONNECTION|CACHE_DRIVER|FILESYSTEM_DRIVER)=' .env
ls bootstrap/cache/config.php 2>/dev/null && echo "CONFIG IS CACHED - see B.2"
df -h .
crontab -l | grep 'schedule:run'
mysql -u DBUSER -p DBNAME -e "SELECT VERSION();
  SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME IN ('orders','products','product_stocks','purchase_products','funnels');
  SELECT migration FROM migrations WHERE migration LIKE '2026_10_2%';"
```

### B.1 PHP version decides how dependencies are installed

The committed `composer.lock` needs **PHP 8.4.1 or newer** (Symfony 8.1 components). The patched PhpSpreadsheet
1.30.7 declares **PHP below 8.5**. So:

| Host PHP | Install command | Notes |
|---|---|---|
| **8.4.x** (expected) | `composer install --no-dev --optimize-autoloader` | Clean fit. `composer check-platform-reqs --lock --no-dev` must print only "success". |
| 8.5.x | `composer install --no-dev --optimize-autoloader --ignore-platform-req=php+` | `php+` ignores only the upper bound. The whole test suite ran on PHP 8.5 with these versions. |
| 8.3 or older | **Stop.** | Upgrade the host's PHP first (cPanel "MultiPHP Manager"), or the dependencies must be re-resolved for that version and retested. |

### B.2 Configuration cache

The shop reads about 290 settings with `env()` outside the config files. If `bootstrap/cache/config.php` exists, all
of them are empty at runtime. Run `php artisan config:clear` and never `config:cache` or `optimize`.

---

## C. Backup (before Gate 2; can be done now)

```bash
cd BACKEND_DIR
STAMP=$(date +%Y%m%d-%H%M)
mysqldump -u DBUSER -p --single-transaction --routines --triggers --set-gtid-purged=OFF DBNAME > ~/misron-$STAMP.sql
tar --exclude=vendor --exclude=node_modules -czf ~/misron-backend-$STAMP.tgz .   # includes .env, public/uploads, storage
ls -lh ~/misron-$STAMP.sql ~/misron-backend-$STAMP.tgz
```

Copy both files off the server. Without shell: phpMyAdmin → Export → Custom → "Add DROP TABLE" off, structure and
data, all tables; cPanel → Backup → Download a Full/Home backup.

**Restore check** (into the scratch database, never into `DBNAME`):

```bash
mysql -u DBUSER -p -e "CREATE DATABASE SCRATCH_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u DBUSER -p SCRATCH_DB < ~/misron-$STAMP.sql
for t in orders order_details products product_stocks purchase_products users uploads categories; do
  echo "$t $(mysql -u DBUSER -p -N -e "SELECT COUNT(*) FROM DBNAME.$t") $(mysql -u DBUSER -p -N -e "SELECT COUNT(*) FROM SCRATCH_DB.$t")"
done   # the two numbers must match on every line
```

Storefront: note the current production deployment (e.g. Vercel → Deployments → current one) for instant rollback.

---

## D. Rehearsal on the fresh backup (before Gate 2)

Run the complete database sequence of section E against `SCRATCH_DB` (the restored copy) first, using exactly the
same files. Only when every check passes there, ask for Gate 2.

Done on 2026-10-28 against a fresh dump of the local `misron` database (restored into `misron_restore_test`):
restore check equal on all 8 tables; preflight without blockers; v1–v5 applied (v1 was already present and was
skipped by its own checks); templates 16; permissions 6; stock-safety change applied with purchase and stock totals
unchanged (686,700 / 747,360); all 32 existing orders kept with `funnel_id` NULL; backend test suite run against
the migrated copy (result in PRODUCTION_READINESS.md). The production database may differ from the local one
(for example whether v1 is already applied), which is why the rehearsal must be repeated on the production dump.

---

## E. Final migration checklist [GATE 2]

Maintenance mode first: `php artisan down --retry=60` (cPanel without shell: rename nothing; announce a short
window instead and do it at the quietest hour).

| # | Run | Check | Expected |
|---|---|---|---|
| 1 | `mysql -u DBUSER -p --table DBNAME < sqlupdates/inventory_v1_preflight.sql` | read the output | sections 3, 4, 6 print **no rows**; zero dates `0 0`. Write down the totals of section 8. **Stop** otherwise. |
| 2 | `mysql -u DBUSER -p DBNAME < sqlupdates/sales_funnel_v1.sql` | — | no error (safe if already applied) |
| 3 | `… < sqlupdates/sales_funnel_v2_orders.sql` | `SHOW COLUMNS FROM orders LIKE 'funnel%';` | 2 rows |
| 4 | `… < sqlupdates/sales_funnel_v3_checkout.sql` | — | no error |
| 5 | `… < sqlupdates/sales_funnel_v4_publishing.sql` | — | no error |
| 6 | `… < sqlupdates/sales_funnel_v5_analytics.sql` | — | no error |
| 7 | `SELECT COUNT(*) FROM migrations WHERE migration LIKE '2026_10_2%';` | | **14** |
| 8 | `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND (table_name LIKE 'funnel%' OR table_name='payment_gateway_attempts');` | | **10** |
| 9 | `SELECT COUNT(*) FROM permissions WHERE name LIKE '%funnel%';` | | **6** |
| 10 | `mysql -u DBUSER -p DBNAME < sqlupdates/inventory_v1_stock_safety.sql` (recommended) | last result sets of the script | engines InnoDB; key `uq_product_stocks_product_variant` and index `idx_purchase_products_product_variant` present; row counts equal to step 1 |
| 11 | `SELECT SUM(remaining_qty) FROM purchase_products; SELECT SUM(qty) FROM product_stocks;` | | equal to the step 1 totals |
| 12 | `SELECT COUNT(*), SUM(funnel_id IS NULL) FROM orders;` | | both numbers equal (no existing order changed) |

Without shell: import the same files in the same order in phpMyAdmin (Import tab, one file at a time) and run the
check queries in the SQL tab.

Undo (only if a step fails and you decide to go back): in reverse order `inventory_v1_stock_safety_rollback.sql`,
`sales_funnel_v5_analytics_rollback.sql`, v4, v3, v2 (each `_rollback.sql`). Do **not** roll back v1 if it was
present before this deployment. Last resort: restore the section C dump.

---

## F. Backend code and environment [GATE 1 + GATE 3]

Gate 1 (branches): push `feature/sales-funnel-builder` of both repositories and merge it into the deploy branch,
the way you normally release. The backend branch also contains your own commits (`a20c479 fixed 404 issue`).

```bash
cd BACKEND_DIR
git fetch && git checkout <deploy-branch> && git pull           # or upload the files as you do today
composer install --no-dev --optimize-autoloader                 # + --ignore-platform-req=php+ only on PHP 8.5 (B.1)
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan permission:cache-reset
php artisan funnel:templates                                    # writes the 16 template rows; double-check DB_DATABASE first
```

`composer install` ends with `artisan package:discover`, which needs the database reachable.

**Do not run** `php artisan migrate` (bare), `config:cache`, `optimize`, or `inventory:reset-dummy-data`.

### `.env` additions and checks (backend)

```dotenv
APP_ENV=production
APP_DEBUG=false
FRONTEND_URL=https://SHOP_HOST
FUNNEL_PUBLIC_URL=https://SHOP_HOST
FUNNEL_EDITOR_URL=https://SHOP_HOST
FUNNEL_REVALIDATE_URL=https://SHOP_HOST/api/funnel/revalidate
FUNNEL_REVALIDATE_SECRET=<48 random hex characters, same value in the storefront>
FUNNEL_CHECKOUT_ENABLED=false
FUNNEL_REQUIRE_CHECKOUT=true
FUNNEL_TEST_PHONES=<staff test numbers, comma separated>
ONLINE_ORDER_EXPIRY_ENABLED=false
TRUSTED_PROXIES=<storefront outgoing IPs, or leave empty — see below>
PAYSTATION_CALLBACK_URL=https://ADMIN_HOST/api/v2/paystation/callback
```

Secret: `php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"`. After editing: `php artisan config:clear`.

`TRUSTED_PROXIES`: if the storefront is on Vercel (no fixed outgoing addresses), leave it **empty**. Per-IP limits
then count the storefront as one address, and the per-phone order limit (6 per 10 minutes) protects ordering.
Never set `*` while the backend is reachable directly.

### Cron and queue

One entry (check with `crontab -l`; add only if missing):

```
* * * * * cd BACKEND_DIR && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler already runs `queue:work --once` every minute (`QUEUE_CONNECTION=database`), `funnel:reconcile`
every minute, `orders:expire-unpaid` every 5 minutes (inactive while `ONLINE_ORDER_EXPIRY_ENABLED=false`) and
`funnel:prune-events` daily. No extra worker or service. Check after 2 minutes:
`grep "Laravel scheduler working" storage/logs/laravel-$(date +%F).log | tail -2`.

Business settings to confirm in the admin: **Paystation sandbox off** (for live payments), Cash on delivery and
Paystation active as today.

---

## G. Storefront [GATE 3]

Environment (production): existing `BACKEND_API_URL`, `NEXT_PUBLIC_SITE_URL`, Pixel/GTM values unchanged, plus
`FUNNEL_REVALIDATE_SECRET=<same value as backend>`. Build and deploy the merged branch as usual (`next build`,
Next 16.4.3; Node 20.9+). Deploy the storefront **after** the backend.

Maintenance mode off: `php artisan up`.

---

## H. Production smoke tests (checkout off) — run after Gate 3

Automated checks (any machine):

```bash
A=https://ADMIN_HOST; S=https://SHOP_HOST
curl -s -o /dev/null -w "%{http_code} storefront home (200)\n"             $S/
curl -s -o /dev/null -w "%{http_code} closed funnel page (404)\n"         $S/funnel/no-such-page-check
curl -s -o /dev/null -w "%{http_code} funnel API unknown slug (404)\n"    $A/api/v2/funnels/no-such-page-check
curl -s -o /dev/null -w "%{http_code} revalidate without secret (401)\n"  -X POST $S/api/funnel/revalidate
curl -s -o /dev/null -w "%{http_code} uploader without login (419 or 302: refused)\n"     -X POST $A/aiz-uploader/upload
curl -s -o /dev/null -w "%{http_code} image API without login (401)\n"    -H 'Accept: application/json' -X POST $A/api/v2/file/image-upload
curl -s -w " <- paystation ipn, unknown invoice ({\"status\":\"success\"})\n" -X POST -d invoice_number=AIZ-0-0 $A/api/v2/paystation/ipn
curl -sI $A/ | grep -i -E '^(x-debug|x-powered-by)' ; echo "(no x-debug header expected)"
```

Manual checks (admin + phone):

| Area | Check | Expected |
|---|---|---|
| Editor | Sales Funnels → Create Funnel → "Classic" → Edit; drag a block, undo, wait for "Saved" | editor opens, saves |
| Product selection | choose a real product in the offer block | name, photo, price appear |
| Publishing | Publish | address opens with HTTP 200, real price, order form says ordering is not open |
| Real 404 | Unpublish; reopen the address within seconds | **404** |
| Draft link | Share a draft link, open privately, revoke | opens, then 404 |
| Analytics | Sales Funnels → Analytics | the visit is counted |
| Storefront checkout | normal website order, cash on delivery, low-value product | order in Orders; stock down by 1 |
| Inventory | cancel that order | stock back by exactly 1 (product stock and batch) |
| Payments | website order with Paystation, open the payment page, cancel at the gateway | order cancelled, stock back, payment page not offered again; no money taken |
| Payments (optional, real money) | one small paid order through Paystation, then refund | order Paid once; IPN + return do not duplicate |
| POS / purchase | one POS sale and one purchase entry as usual | stock moves as before |
| Logs | `tail -50 storage/logs/laravel-$(date +%F).log` | no new errors |

Rollback (any failure that matters): `FUNNEL_*` changes need nothing (checkout is off); code → redeploy the previous
commit noted in B and the previous storefront deployment; database → undo files of section E or the section C dump.

---

## I. After the smoke tests

I report the live results and give a **separate GO/NO-GO for Gate 4** (funnel checkout on). Minimum for GO: every
check in H passes, a staff test order through a funnel page with checkout switched on briefly in a quiet hour (or on a
staging copy) passes the steps of DEPLOYMENT.md section 12, and the logs stay clean for a day.
