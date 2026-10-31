# Runbook: preparing the next rehearsal from a fresh production backup

Nothing in this runbook touches production data except **reading** it. Nothing enables the feature flag. Production stays NO-GO.

## What I still need from you (blocked until supplied)
1. **The production engine and version.** Run `sqlupdates/inventory_v2_environment_check.sql` on production with a SELECT-only database user and send the output. The only evidence today is the header of `misron.sql` (it says "Server version: 8.4.3"), which is the version of the machine that made the dump, not proof of production. My sandbox is MariaDB 10.11, so every result so far is engine-unconfirmed.
   ```bash
   mysql -h <host> -u <readonly_user> -p <database> < sqlupdates/inventory_v2_environment_check.sql > environment_check_output.txt
   ```
2. **A fresh backup file**, taken as below, and the time it was taken.
3. **The real admin theme package** (`aiz-core.css/js`, `vendors.css/js` and the plugins it loads: DataTables, select2, ...). Until then Bootstrap stand-in results are not UI acceptance.
4. **An accountant** for the review pack (`docs/accountant-review-pack/`).

## Taking the backup (read-only on production, at a quiet time)
```bash
mysqldump --single-transaction --quick --routines --triggers --events --hex-blob \
  --set-gtid-purged=OFF --default-character-set=utf8mb4 -h <host> -u <backup_user> -p <database> > production_backup_YYYYMMDD_HHMM.sql
sha256sum production_backup_YYYYMMDD_HHMM.sql > production_backup_YYYYMMDD_HHMM.sql.sha256
```
* `--single-transaction` gives a consistent snapshot for InnoDB tables only. The purchasing tables are MyISAM in the old schema, so a quiet window (or `--lock-tables`) is needed for those; say which you used.
* Record `SELECT NOW(), VERSION()` at the time of the dump, and the `fingerprint` rows from the environment-check output; the restored copy must reproduce them.
* Send the file through a private channel. It contains customer data: it must never be committed to git.

## Restoring into staging (done by me once I have the file and the engine)
* Use the **same engine and major version** as production (MySQL 8.4 vs MariaDB differ in `ALTER`, isolation variable names, JSON handling and the optimizer; the deadlock finding in the acceptance report depends on the optimizer).
* `SOURCE_DUMP=<file> STAGING_DB=sc_stg_prodN tests/staging/prepare-staging.sh` imports into a NEW database, masks personal data and credentials, and proves the masking changed no number. It refuses an existing database, a non-staging-looking name and any remote host.
* Compare the restored fingerprint with the production environment-check fingerprint; any difference stops the rehearsal.
* `STAGING_DB=sc_stg_prodN tests/staging/rehearse.sh` then does the InnoDB conversion, `artisan migrate`, the SQL script on a disposable copy, parity checks, and a rollback on a separate disposable copy. After the InnoDB conversion run `ANALYZE TABLE product_stocks, purchase_products, stock_balances` (stale statistics caused deadlocks in a test).

## Rules that stay in force
* `INVENTORY_CONFIRMED_ONLY=false` everywhere (production and staging) until the accountant has signed the review pack. A guard in `inventory:preflight` is not a substitute for that decision.
* No historical inventory or accounting discrepancy is corrected. Orphan layers, uncosted units, the courier balances, the orphan supplier payment and the 301.75 difference stay exactly as found until the accountant decides.
* No deployment, merge or production write without your explicit approval.
