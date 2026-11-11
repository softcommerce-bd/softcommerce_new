# T-5 database migration report

All work below ran on **disposable databases only** (`inv_v2_test` on MariaDB 10.11.14; `t5_mysql84_test`, `t5_prodshape_test`, `misron_test` on a throw-away MySQL Community Server 8.4.13 container, destroyed afterwards). Nothing touched production, staging, `sc_staging`, or any main `misron` database. The supplied backup file was restored into `t5_prodshape_test` (a disposable database) and masked with `tests/staging/mask-pii.sql`. Evidence: `docs/evidence/final9/`.

## 1. What was migrated or scripted
| Item | Artifact | Nature | Reversible | Tested on |
|---|---|---|---|---|
| MyISAM -> InnoDB for `purchases`, `purchase_products`, `suppliers`, `purchase_returns`, `purchase_return_products` (+ any other listed money/stock table that is not InnoDB) | `php artisan db:convert-engines` (dry run default; `--execute --confirm=<db>`; non-test DB also needs maintenance mode and `--plan=`); plain SQL `sqlupdates/t5_engine_only_conversion.sql` | engine change only (`ALGORITHM=COPY, LOCK=SHARED`), no data change, no new constraint | in the window by restore; `--revert --tables=` back to MyISAM on `*_test` databases (rehearsal) | MySQL 8.4.13 backup-shaped copy; MariaDB scratch tables (unit tests) |
| 13 lookup indexes `idx_t5_*` | `database/migrations/inventory_v2/2026_11_04_100000_add_checkout_and_lock_lookup_indexes.php` (existing) | additive, repeat-safe | `down()` drops only `idx_t5_*` | MySQL 8.4.13 (13 -> 0 -> 13, twice), MariaDB |
| Auto-deposit schema step (ENUM values) | `sqlupdates/t5_auto_deposit_schema.sql` (NOT run by any migration; only after an approved AD-1 option) | adds `auto_deposit` / `completed` ENUM values | rollback statements in the file | MySQL 8.4.13 backup-shaped copy (apply, readiness re-check, rollback) |
| Number-series locks, supplier row lock, generated-column fix, upload disk default, funnel null-on-delete | application code only | no schema change | code revert | both engines (suites) |
No other schema change was made. `sqlupdates/inventory_v2_innodb_conversion.sql` was **not** modified except for a header warning: it also adds a unique key on `purchases.reference_no` (decision 14, not approved); the engine-only script replaces it.

## 2. Engine conversion rehearsal on the backup's own shape (MySQL 8.4.13)
Source: the supplied backup restored into `t5_prodshape_test` (the five tables are MyISAM there; `docs/evidence/final9/prodshape_01_preflight_before.txt` shows `db:engine-preflight` FAIL).
| Step | Result |
|---|---|
| Dry run | lists the five tables: 3,462 / 103 / 5 / 0 / 0 rows, 0.55 MB for the largest |
| Wrong `--confirm` | refused (exit 1) |
| Execute | `purchase_products` 0.053 s, `purchases` 0.013 s, `suppliers` 0.011 s, both returns tables ~0.01 s: **~0.1 s in total**; rows and AUTO_INCREMENT unchanged per table; fingerprint (counts, sum of supplier due 147,089,280.00, FIFO units 686,700, layer value) identical before/after |
| `db:engine-preflight` after | PASS |
| **Rollback rehearsal** (`--revert --tables=...`, test database only) | all five back to MyISAM in ~0.06 s, counts unchanged; preflight FAIL again; the HTTP gate test (below) passes again |
| Plain-SQL variant `t5_engine_only_conversion.sql` | converts the same five, before/after count rows identical; preflight PASS |
| Production-shape HTTP gate (`ProductionShapeEngineGateTest`, 3 tests) | on the MyISAM shape: `purchase.store`, `purchase.payment.update`, `purchase-return.store`, `supplier.payment.process` answer **503 `engine_gate`**, nothing written; reads work; deadlock retry disabled; `warn`/`off` policies behave as documented |
Incidental proof of the underlying problem: the test fixtures inserted 3 supplier rows inside a "rolled back" test transaction on the MyISAM shape and the rows stayed (5 -> 8 suppliers).
Limits: ~0.1 s is for **3.5 k rows**; production size is unknown (DBA-05). Size the window from a timed rehearsal on the production restore (method in `T5_R01_MYISAM_INVESTIGATION.md` section 4.4).

## 3. Index migration
`idx_t5_*` on MySQL 8.4.13: 13 before, `down()` 0, `up()` 13, `down()` 0, `up()` 13, repeat `up()` 13; the other 435 index rows never changed (`docs/evidence/mysql84/04_index_migration.txt`). On MariaDB the migration is part of the standing test database.

## 4. Auto-deposit schema step (R-03)
Backup-shaped 8.4 copy: before the step `AutoDepositReadiness` reports the user and all three ENUM problems; after the SQL step only the missing system user remains; the rollback statements restore the exact original definitions (`docs/evidence/final9/auto_deposit_schema_rehearsal_my84.txt`). The job stays paused by default and refuses to run until every readiness condition holds.

## 5. Rollback summary
| Change | Rollback |
|---|---|
| engine conversion | in the window: restore the pre-window backup (lossless, nothing wrote); rehearsal only: `--revert`; after reopening: fix forward |
| index migration | `down()` |
| auto-deposit ENUM step | the commented statements in the SQL file while no row uses the new values |
| application release | redeploy the previous release **but never one older than commit `25aec82` on the scheduler host** (re-enables the old auto-deposit job) |
