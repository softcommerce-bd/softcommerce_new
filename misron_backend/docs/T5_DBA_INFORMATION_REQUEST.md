# Information request to the DBA (T-5)

From the T-5 engineering work, commit `98339a1`. **Everything in this request is read-only unless it says "restore copy".** Please do **not** run anything on production that writes; the conversion and migrations are done later, in a window, only after the owner's written plan. Nothing here asks for secrets: do not send passwords or `.env` contents. Reply by filling the answer sheet at the end (or send the raw outputs with the labels below). Tracker rows: E1-E4, E2, E10.

## 1. Why we ask
T-5 added locking, retry and number-series safeguards that only work on transactional (InnoDB) tables; the supplied backup keeps the five purchasing tables on MyISAM. We do not know what production runs. The answers decide whether a conversion window is needed, how long it must be, and whether the nightly auto-deposit pause changes behaviour.

## 2. Run on the production application host (as the web-app user; writes nothing)
```bash
php artisan accounts:number-lock-selfcheck
```
Expected: PASS on the connection checks, or a clear FAIL. Paste the complete output (label **A**). If the command does not exist the release is not deployed there: say so, do not deploy for this.

## 3. Run read-only on production **or** on a restore (label **B**)
```bash
mysql <database> < tests/staging/inc1_db_facts.sql
```
(version, hostname, port, read_only, isolation, lock-wait, sql_mode, Galera/group-replication hints, client addresses, engines of the key tables, `balance_transfers.created_by` nullability, active auto-deposit method count).

## 4. Additional read-only queries (label **C**)
```sql
-- C1 the five purchasing tables: engine, rows, size, next id
SELECT table_name, engine, table_rows, ROUND(data_length/1048576,2) AS data_mb, ROUND(index_length/1048576,2) AS index_mb, row_format, auto_increment
FROM information_schema.tables WHERE table_schema = DATABASE()
  AND table_name IN ('purchases','purchase_products','suppliers','purchase_returns','purchase_return_products');
-- C2 every non-InnoDB table in the schema
SELECT table_name, engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' AND engine <> 'InnoDB';
-- C3 definitions the auto-deposit job depends on
SHOW CREATE TABLE balance_transfers\G
SHOW CREATE TABLE expense_payments\G
-- C4 traces of the auto-deposit job (counts only)
SELECT COUNT(*) AS auto_vouchers FROM balance_transfers WHERE voucher_no LIKE 'AD-%';
SELECT COUNT(*) AS blank_type_or_status FROM balance_transfers WHERE transaction_type = '' OR status = '';
SELECT status, COUNT(*) FROM balance_transfers GROUP BY status;
SELECT COUNT(*) AS active_auto_methods FROM transaction_methods WHERE deposit_type = 'auto' AND status = 'active';
-- C5 strict-mode blockers for a conversion (should be 0)
SELECT COUNT(*) FROM purchases WHERE created_at = '0000-00-00 00:00:00' OR updated_at = '0000-00-00 00:00:00';
SELECT COUNT(*) FROM suppliers WHERE created_at = '0000-00-00 00:00:00' OR updated_at = '0000-00-00 00:00:00';
SELECT COUNT(*) FROM purchase_products WHERE created_at = '0000-00-00 00:00:00' OR updated_at = '0000-00-00 00:00:00';
-- C6 does the scheduler run the job today? (look in the application log for the nightly line)
--    grep -c "Auto-deposit" storage/logs/*.log     (count only, do not send log content)
```

## 5. Infrastructure questions (label **D**; answer in `T5_INC1_DBA_HANDOFF.md` section 4C)
Proxy/pooler in front of the database? multiplexing between transactions? persistent connections? how many servers accept writes (Galera/Group Replication)? read/write splitting? one connection per request, closed at the end (PHP-FPM) or long-lived workers? 10 s lock wait vs `wait_timeout`/load-balancer/`max_execution_time`? failover behaviour? any other writer inserting `INC-yy...` incomes?

## 6. Backup and restore proof (label **E**) - needed before any schema step
1. Take a logical backup while the purchasing tables are quiet: `mysqldump --single-transaction --routines --triggers --lock-tables` (single-transaction alone is **not** consistent for MyISAM). Say which options you used.
2. Compute its SHA-256 and keep it.
3. Restore it on a **separate** server/database (never on production) and run `tests/staging/fingerprint.sql` on source and copy; send both outputs.
4. Mask personal data on the copy with `tests/staging/mask-pii.sql` before anyone else uses it.

## 7. On the restore copy only (label **F**; owner approval needed first)
`php artisan db:engine-preflight` (expected FAIL if tables are MyISAM), then the rehearsal in `T5_STAGING_EXECUTION_SEQUENCE.md` steps 4-5, recording the wall time of each `ALTER`. Reference timing from our rehearsal (3.5 k FIFO rows): ~0.1 s in total; yours will differ with volume.

## 8. Load evidence (label **G**, later, on the restore copy)
Production-like concurrent checkout/purchase load with the scheduler running, to answer T-1 (the original deadlock) and T-4 (outbox). Plan: `T5_STAGING_EXECUTION_SEQUENCE.md` step 9.

## 9. Answer sheet
| Label | Item | Done? | Attach / answer |
|---|---|---|---|
| A | selfcheck output | | |
| B | `inc1_db_facts.sql` output | | |
| C | queries C1-C6 | | |
| D | infrastructure questions | | |
| E | backup file name, SHA-256, restore log, two fingerprints | | |
| F | preflight + per-ALTER timing on the restore | | |
| G | load logs | | |
Answered by / date: __________
