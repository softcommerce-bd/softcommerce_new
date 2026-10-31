# T-5 deployment and rollback runbook (DRAFT — nothing here is authorised)

Production and staging remain NO-GO. Feature flags stay off (`INVENTORY_CONFIRMED_ONLY=false`). This runbook records what a later, explicitly approved rollout would need; it has not been executed anywhere.

## Preconditions (all currently open)
1. Production engine/version/table engines/indexes evidence (BLOCKED). 2. Fresh verified production backup (BLOCKED). 3. Data audit on an isolated restore (BLOCKED). 4. Business decisions 1–15 answered where they gate a step (`T5_PHASE0_BUSINESS_DECISIONS.md`). 5. MySQL 8.4 rehearsal if that is the target engine (NOT EXECUTED). 6. Accountant review (BLOCKED). 7. Explicit approval from the owner.

## Steps (staging first, never both at once)
1. Backup; confirm restore on a copy.
2. If any of `purchases, purchase_products, purchase_returns, purchase_return_products, suppliers` is MyISAM: `sqlupdates/inventory_v2_innodb_conversion.sql` on the copy, then in a maintenance window.
3. `php artisan migrate --path=database/migrations/inventory_v2 --force` (v2 tables, if not yet applied), then the T-5 index migration (path in the migration report).
4. Deploy code. Do not enable flags.
5. Smoke checks: scheduler lists no undefined command (`SchedulerCommandsExistTest`), checkout of two customers at once, one unsettle, one supplier payment delete.
6. Watch logs for `A deadlocked request left a database transaction open` (critical) and for `Deadlock persisted after 3 attempt(s)`.

## Rollback
* Code: revert to the previous release. * Indexes: `php artisan migrate:rollback --path=database/migrations/inventory_v2/2026_11_04_100000_add_checkout_and_lock_lookup_indexes.php --force` (drops only `idx_t5_*`). * Engine conversion: restore from the backup (not reversible once new rows exist). * `idempotency_keys` rows with scope `checkout_cart` are harmless to leave.
