# T-5 final deployment runbook (staging first; production is a separate decision)

**Status: PREPARED. Nothing in this runbook has been executed on staging or production. The steps were rehearsed only on disposable databases (MariaDB 10.11.14 and MySQL 8.4.13; evidence `docs/evidence/final9/`, `docs/T5_DATABASE_MIGRATION_REPORT.md`).** No authorised, isolated staging environment was available to the engineer, so no deployment was made. Do not deploy to production, enable `INVENTORY_CONFIRMED_ONLY`, set `AUTO_DEPOSIT_APPROVED`, or move real money as part of this runbook.

## 0. Gates (all must be true before step 1; owner/DBA/accountant items are listed in `T5_FINAL_COMPLETION_REPORT.md`)
1. The target is an **isolated staging** database restored from a masked backup, not production, and no live payment/courier/SMS credentials are configured there.
2. DBA facts returned (INC.1 sheet, `tests/staging/inc1_db_facts.sql` output, engines and sizes of the five purchasing tables).
3. A fresh backup with checksum **and a proven restore on another server**.
4. Written owner approval for this window.

## 1. Environment configuration
Use `docs/staging/env.staging.example`. Checks: `FINANCIAL_ENGINE_POLICY=enforce`; `INVENTORY_CONFIRMED_ONLY=false`; `AUTO_DEPOSIT_APPROVED` and `AUTO_DEPOSIT_USER_ID` absent in **every** environment source (env files, php-fpm pool, systemd/orchestrator, CI); `FILESYSTEM_DRIVER` explicit; one scheduler host. After deploying run `php artisan config:clear` (never cache config with the auto-deposit variable present), then record `php artisan tinker --execute="var_export([config('accounts.auto_deposit_approved'), config('inventory.confirmed_only'), config('inventory.financial_engine_policy')]);"` -> `[false, false, 'enforce']` on **every** host (safeguard S1). Rolling back to a release older than commit `25aec82` re-enables the old auto-deposit job: never roll the scheduler host back that far (safeguard S7).

## 2. Preflight (read-only; keep every output)
```bash
mysql <db> < tests/staging/inc1_db_facts.sql                 # engine, version, isolation, topology, proxy hints, engines of the key tables
php artisan db:engine-preflight                              # exit 1 lists the tables that are not InnoDB
php artisan accounts:number-lock-selfcheck                   # connection / session-lock behaviour of the real connection path
php artisan inventory:preflight                              # read-only readiness of the v2 stock model (stays informational: the flag stays off)
mysql --batch <db> < tests/staging/reconciliation_checks.sql > reconciliation_before.tsv
```
Stop if the engine or version differs from what the rehearsal covered (MariaDB 10.11 / MySQL 8.4): rehearse on that engine first.

## 3. Backup (inside the window, application stopped)
`php artisan down`; stop the scheduler and queue workers; confirm no writers (`SHOW PROCESSLIST`); `mysqldump --single-transaction --routines --triggers --lock-tables` is needed while MyISAM tables exist (single-transaction alone is not consistent for MyISAM); verify the file, checksum it, **restore it to a scratch server and compare the fingerprint** (`tests/staging/fingerprint.sql`) before continuing.

## 4. Engine conversion (R-01) - only if step 2 listed non-InnoDB tables
```bash
php artisan db:convert-engines                                                   # dry run: lists tables, rows, size, AUTO_INCREMENT
php artisan db:convert-engines --execute --confirm=<exact database name> --plan=<approved plan reference> --report=conversion_report.json
```
(or `sqlupdates/t5_engine_only_conversion.sql`; do **not** use `inventory_v2_innodb_conversion.sql`, which also adds a unique key that belongs to decision 14). The command refuses without the exact name, without maintenance mode and without a plan reference on a non-test database; it compares row counts and AUTO_INCREMENT per table and stops on any difference. Measured on the backup's data (3,462 FIFO rows): 0.05 s for the largest table, ~0.1 s in total; production size is unknown, so time it on the restore first and size the window at 3x. Then `ANALYZE TABLE` the five tables and re-run `php artisan db:engine-preflight` (must PASS).

## 5. Migrations and code
1. `php artisan migrate --path=database/migrations/inventory_v2 --force` (includes the repeat-safe 13-index migration `2026_11_04_100000...`; rollback drops only `idx_t5_*`), then `php artisan migrate --path=database/migrations/unified_chat --force`. Do **not** run a plain `php artisan migrate` on a database built from the supplied dump: its base tables already exist (first error `personal_access_tokens`).
2. Deploy the application release (from the approved commit). `php artisan config:clear && php artisan route:clear`; restart PHP-FPM so per-process engine caches are fresh.
3. `php artisan up` only after step 6 smoke tests on a maintenance-bypass URL pass.

## 6. Smoke tests (staging, with synthetic data)
- [ ] `php artisan db:engine-preflight` PASS; `php artisan accounts:number-lock-selfcheck` PASS.
- [ ] Create a purchase, edit its payment, delete it: supplier due/paid and Accounts Payable return to the start (compare `reconciliation_checks.sql`).
- [ ] Supplier payment: create, **edit twice**, delete: supplier due/paid correct, no orphan allocation.
- [ ] Balance transfer deposit/withdraw/transfer and delete: balances restored, statement lines mirrored.
- [ ] Income: two saved at once get two consecutive numbers.
- [ ] A POS sale for a registered customer leaves that customer's website cart intact.
- [ ] A bank-screen POST (`/admin/accounts/bank-transactions/deposit`) answers 410 and writes nothing; the bank list screen shows the pointer to Balance Transfers.
- [ ] `php artisan accounts:auto-deposit` prints `AUTO_DEPOSIT_PAUSED_AD1` and exits 0 with no database access.
- [ ] Upload an image and an SVG as staff: both files exist under the upload folder.
- [ ] Checkout twice with the same cart: one order. Stock and FIFO layers match (`reconciliation_checks.sql` check 7).
- [ ] `ledger:process-outbox --alert` runs and the outbox is empty.

## 7. Financial reconciliation
Run `tests/staging/reconciliation_checks.sql` before and after. Rows 1-12 must not get worse (a non-zero count that was already non-zero before is a finding for the accountant, not for this change); the three info rows (supplier due/paid sum, account balance sum, FIFO units/value) must be **identical** before and after the engine conversion and migrations. Keep both TSV files with the change record. No historical balance is ever corrected by a script.

## 8. Monitoring and recovery
| Signal (log text / header) | Meaning | Action |
|---|---|---|
| `DEADLOCK RETRY DISABLED: money/stock tables are not InnoDB` (critical) | an engine regressed or is unreadable | run `db:engine-preflight`; do not re-enable retries |
| `Engine gate: request REFUSED, purchasing tables are not on InnoDB` | purchasing is paused by design | run the conversion (section 4) |
| `X-Deadlock-Retries` response header | a request was retried after a deadlock | normal if rare; count per day, investigate a rise |
| `Deadlock persisted after 3 attempt(s); giving up` | contention beyond the retry | check long transactions, `SHOW ENGINE INNODB STATUS` |
| `Number lock not obtained ... continuing without it` | income-number lock timed out/unavailable | check proxy/pool (INC.1); retry still protects |
| `AUTO_DEPOSIT_PAUSED_AD1` nightly | expected | absence on a night = scheduler not running |
| `AUTO_DEPOSIT_BLOCKED_NOT_READY` / "Auto-deposit completed" | someone switched the job on | treat as an incident: unset the variable, `config:clear`, run the S6 queries |
| `ledger:process-outbox --alert` output | committed legacy postings waiting | check the processor, see `LedgerProcessOutbox` |
Recovery: the maintenance window is lossless to undo until `php artisan up`: restore the backup taken in section 3 (or `ALTER ... ENGINE=MyISAM` on a test copy only). After reopening there is no restore (business happened): fix forward; the index migration can be rolled back alone (`migrate:rollback` of that file or `down()`), the engine conversion cannot be undone without losing the guarantees.

## 9. Never
Set `INVENTORY_CONFIRMED_ONLY=true`; set `AUTO_DEPOSIT_APPROVED`; run any repair command with `--apply` while trading; correct historical supplier balances with a script; skip the restore proof; run this on production without a separate written plan.

## 10. Sign-off (blank)
| Step | Done by | Date | Evidence reference |
|---|---|---|---|
| Gates (section 0) | | | |
| Backup + restore proof | | | |
| Conversion + validation | | | |
| Migrations + deployment | | | |
| Smoke tests | | | |
| Reconciliation before/after | | | |
