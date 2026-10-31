# Staging acceptance report (inventory / purchasing / accounting upgrade)

**Status: NOT ACCEPTED. Production = NO-GO.** Technical rehearsal done; accountant review NOT done; flag NOT enabled on staging; real admin theme NOT available.
Branch `claude/peaceful-planck-elqcki`. Nothing deployed, no production data touched, flag never enabled anywhere except isolated test databases.

## 1. Environment and database source
* Source data: `misron.sql` in the repository (phpMyAdmin dump of database `misron`, generated 23 Oct 2026, MySQL 8.4). **I have no way to confirm it is the current production database.** A fresh production backup was not available to me; if it differs, repeat with the real one (`tests/staging/rehearse.sh` is repeatable).
* Staging DB `sc_staging` (MariaDB 10.11 in a sandbox, not MySQL 8.4): imported, then PII-masked with `tests/staging/mask-pii.sql` (users, addresses, order/combined-order shipping data, suppliers, drafts, subscribers, tokens/OTPs removed, gateways switched off). Financial/stock fingerprint identical before and after masking (`fp_before_mask` vs `fp_after_mask`).
* Disposable copies: `sc_stg_sql` (SQL-script path), `sc_stg_pre` (pre-migration reference), `sc_stg_rb` (rollback). The staging DB itself was never rolled back.
* Engine differences (MariaDB vs MySQL 8) and sandbox-only: results must be re-confirmed on the target engine.

## 2. Baseline (before anything) -> after migration
All recorded in `docs/evidence/staging/baseline_before.json` / `after_*.json` (row counts, stock, FIFO layers, supplier balances, journal, trial balance, P&L, balance sheet).
* After InnoDB conversion: **0 differences**.
* After `artisan migrate` and after `sqlupdates/inventory_v2.sql`: **9 differences, all explained**: the new journal/ledger/goods-receipt/invoice tables now exist (empty) and `chart_of_accounts` 53 -> 58 (the five zero-balance system accounts SYS-GRNI, SYS-PPV, SYS-ADV, SYS-OPEN, SYS-SUSP). Every stock, FIFO, supplier, trial-balance, P&L and balance-sheet figure is identical.
* Staging fingerprint after the screen sweeps: identical to baseline except the +5 accounts (sums unchanged).
* Artisan path vs SQL path: columns, indexes, engines, system accounts, permissions (26 created, granted to 0 roles) and migration rows **identical**.
* Rollback (on `sc_stg_rb`): tables and indexes identical to the pre-migration copy; all legacy totals identical. Remaining by design: `account_statement_logs.transaction_type` and `purchase_orders.status` stay widened (VARCHAR), and the 5 zero-balance accounts remain.
* A migration success does **not** prove accounting correctness; see section 6.

## 3. Admin theme and browser evidence
* **The real admin theme assets cannot be deployed: they are not in the repository or on this machine** (`aiz-core.css/js`, `vendors.css/js`, DataTables, select2, ...). Please supply them (or the theme package); I did not fabricate them.
* Stand-in used (Bootstrap 4 + jQuery + Chart.js): theme styling, toasts, sidebar, DataTables and select2 are therefore NOT verified.
* Read-only sweep of 34 screens x desktop/mobile on the masked staging copy (flag off, no form submitted): **61/68 pass**. The 7 failures are all legacy theme-plugin or layout issues I cannot judge without the real theme: Legacy Purchase list (`DataTable` plugin missing), Legacy Supplier Payment create (`select2` missing), Customer Payments (script order, `$` undefined), All Orders on mobile (419 px overflow of a legacy page). One real defect was found and fixed by the sweep: wide pagination on Inventory Overview and Inventory Valuation overflowed on mobile.
* Interactive flows (forms, calculations, double-click, partial receipt, invoice/pay/advance, supplier return, policy save) were exercised earlier on an isolated synthetic test database with the flag on: admin suite 168/168, storefront suite 70/70 (in-stock, zero-stock backorder, negative-available, preorder, stock-only, variations, Add to Cart, Express Order, full checkout, confirmation, cancel). These are NOT staging-data results.
* **Not done on staging:** storefront checkout/backorder browser tests on staging data (they need the flag on, which is gated, section 5).

## 4. Automated tests (final commit)
| Run | Result |
|---|---|
| Full PHPUnit | 506 tests, 3265 assertions: **3 failures, all pre-existing**; 2 skipped (schema-conditional) |
| Concurrency | 30/30 (one run failed on stale optimizer statistics, see below) |
| Security folder | 22 tests; only the pre-existing UploadSafety failure |
* Pre-existing: `ExampleTest::testBasicTest` and `test_orders_carry_nullable_funnel_attribution` (fail on the pre-upgrade code too), `UploadSafetyTest::test_svg_and_xml…` (in the original baseline).
* New regressions: none.
* **Operational finding:** `test_many_shoppers_many_products…` failed 4/4 right after the test tables were emptied, passed 3/3 after `ANALYZE TABLE`. The legacy `UPDATE product_stocks ... ORDER BY id LIMIT 1` and `SELECT ... FOR UPDATE` batch queries deadlocked when table statistics were empty/stale. It reproduces on the original v2 commit too, so it is not caused by the bridge. **Run `ANALYZE TABLE product_stocks, purchase_products, stock_balances` after the InnoDB conversion/import**, and watch deadlocks after cut-over.

## 5. Not executed, and why
* Items 11-13 (accountant review, enabling the flag on staging, the cross-channel matrix on staging data) are **blocked by the acceptance gate**: no accountant has reviewed the figures. I did not enable the flag on the staging DB.
* The same lifecycle/cross-channel matrix (13 channels) already passes on the isolated test database with the flag on (`OrderChannelMatrixTest`, bridge and settlement tests); repeat it on staging after approval.
* Item 15: authorization/CSRF matrix passes in the test suite (`PurchasingPermissionsMatrixTest`, `FinancialPermissionsTest`: unauthenticated -> login, no-permission staff -> 403, CSRF enforced with the real middleware, profiles additive). Not re-run against the staging server with real roles (masking removed the real roles' users' passwords; role-to-permission mappings are intact).

## 6. Accountant review
**Status: NOT REVIEWED.** Pack: `docs/accountant-review-pack/` (suspense/opening balances, trial balance, P&L, balance sheet, inventory valuation + FIFO, supplier payable reconciliation, difference register D-01..D-09, blank sign-off table). Headlines the accountant must rule on (nothing corrected):
* D-01 legacy trial balance is out of balance by **301.75** (courier settlement expense 353.95 vs income 52.20 posted without counter-accounts); the Balance Sheet hides it with a Retained Earnings figure.
* D-03 P&L shows 0.00 while account balances hold income 52.20 / expenses 353.95 (no statement rows).
* D-04 Inventory account 147,089,280.00 vs FIFO layers 147,078,025.00 (11,255.00); D-05 94,260 units with no cost layer (416 variant rows); D-06 cached `current_stock` 952,457 vs 747,360 in stock rows, 80 orphan layers worth 3,200,000.00.
* D-07 a 5,150.00 supplier payment points to a supplier that does not exist; D-08 one supplier carries 147,081,280.00 of 147,089,280.00 payables; Inventory equals Accounts Payable to the cent (looks like seed/test data).

## 7. Known limitations
Real theme missing; dump may not be production; MariaDB not MySQL 8.4; interactive browser tests were on synthetic data; the two compatibility widenings stay after rollback; bridge journals after the legacy commit (crash between -> drift, reported by reconciliation); customer refunds are wallet-only; retroactive finalization of uncosted historical lines is not implemented; the permission grants must be done before cut-over (section 11 of the upgrade doc).

## 8. Recommendation
**Production: NO-GO.** Required before any GO: (1) real production backup rehearsed on MySQL 8.4; (2) real admin theme deployed and the 7 sweep failures re-checked; (3) an actual accountant completes the sign-off table in the review pack and rules on D-01..D-09; (4) only then enable `INVENTORY_CONFIRMED_ONLY=true` on staging and run the lifecycle/cross-channel matrix, security checks and the storefront backorder browser tests on staging data; (5) re-run the full suite; (6) explicit approval to deploy.

## Addendum after review (production remains NO-GO)
Status of the review priorities:
| # | Priority | Status |
|---|---|---|
| 1 | No historical correction | Kept. Nothing corrected; staging fingerprint unchanged apart from the 5 empty system accounts. |
| 2 | Prepare for a fresh production backup; confirm engine/version | **Prepared, NOT complete.** `docs/PRODUCTION_BACKUP_REHEARSAL_RUNBOOK.md`, `sqlupdates/inventory_v2_environment_check.sql`, `tests/staging/prepare-staging.sh` (refuses existing/production-looking/remote targets). The production engine/version is **still unconfirmed**: needs the environment-check output from you. |
| 3 | Real admin theme | **BLOCKED.** Stand-in results are not UI acceptance. |
| 4 | Improve accountant pack | Done: `docs/accountant-review-pack/08_source_and_impact_D01_D09.md` + evidence files 08-12 (each claim labelled PROVEN / INFERRED / UNKNOWN). **Still NOT reviewed by an accountant.** |
| 5 | P&L / Balance Sheet problems without concealment | Done in the reports (display only): the Balance Sheet no longer calls the balancing figure Retained Earnings (named UNRECONCILED DIFFERENCE with a banner), the Trial Balance shows an out-of-balance banner, the P&L lists income/expense balances its statement rows do not explain. No entry, no correction. |
| 6 | Flag false everywhere | Kept. Default `false`; set `true` only inside isolated test processes. Never on staging. |
| 7 | Enable on staging + cross-channel matrix on real data | **BLOCKED** (needs 2, 3 and accountant sign-off). |
| 8 | Final acceptance report | **BLOCKED**; this document is the interim report. |

Test-environment note: the full suite showed 9 extra errors once because the shared test DB's `payment_methods.id` ran out of values (tiny id column, each funnel test deletes and re-inserts a row). Resetting the auto-increment fixed it (7 + 1 tests pass again). Not an application defect; the reset is now in `tests/ui/reset-test-db.sql`. Pre-existing failures unchanged: ExampleTest, funnel attribution test, UploadSafetyTest.
