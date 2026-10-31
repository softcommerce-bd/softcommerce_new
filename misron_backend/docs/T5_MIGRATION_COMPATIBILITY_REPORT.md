# T-5 migration plan and compatibility report

Statuses: VERIFIED, IMPLEMENTED, TESTED, BLOCKED, REQUIRES BUSINESS APPROVAL, NOT APPLICABLE.

## 1. Migrations created in this work
| File | Content | Status |
|---|---|---|
| `database/migrations/inventory_v2/2026_11_04_100000_add_checkout_and_lock_lookup_indexes.php` | 13 additive NON-unique indexes named `idx_t5_*` on `carts` (user_id; temp_user_id), `combined_orders` (user_id, created_at), `orders` (code; combined_order_id; user_id), `addresses` (user_id), `account_statement_logs` (source_type, source_id; order_id, account_id), `purchases` (supplier_id), `supplier_payment_purchases` (supplier_payment_id; purchase_id), `purchase_products` (purchase_id) | IMPLEMENTED, TESTED on 3 local schema variants; NOT run on staging/production |

Built only from verified schema facts (Phase 0, `T5_PHASE0_SCHEMA_COMPATIBILITY.md` §5.1). Repeat-safe: skips missing tables/columns and any index whose leading columns already exist; text columns get a 100-character prefix; works on MySQL/MariaDB and InnoDB/MyISAM. **Rollback (`down`)** drops exactly the `idx_t5_*` indexes it created. It is placed in the `inventory_v2` folder, i.e. run explicitly: `php artisan migrate --path=database/migrations/inventory_v2/2026_11_04_100000_add_checkout_and_lock_lookup_indexes.php --force`.

**Not created (deliberately):** unique indexes (`orders.code`, `courier_settlements.order_id`, reference numbers: need the data audit, decision 14), `document_sequences` (decisions 6, 7), `file_operations`, `idempotency_keys` audit columns (decision 11), `purchase_orders.converted_purchase_id` (decision 9), money-column conversion (decision 13), any MyISAM→InnoDB conversion (production engines BLOCKED; the existing `sqlupdates/inventory_v2_innodb_conversion.sql` is unchanged and still a prerequisite).

## 2. Results (executed)
| Check | Database | Result |
|---|---|---|
| up, rollback, up, double `up()` | schema clone of `inv_v2_test` | 13 added → 0 → 13; second `up()` adds nothing |
| original schema (MyISAM `purchases`, `suppliers`, `purchase_returns`, `purchase_return_products`) | schema-only clone of `misron_test` | 13 added; rollback 0 left |
| staging-style schema (`uq_purchases_reference_no`, no `uq_product_stocks_product_variant`) | schema-only clone of `sc_staging` (no data) | 13 added; rollback 0 left |
| Effect | `EXPLAIN SELECT id FROM carts WHERE user_id = 1 FOR UPDATE` | `type=ref key=idx_t5_user` (was `ALL`) |
The migration was also applied to the disposable test database `inv_v2_test` so that the lock tests exercise the indexes.

## 3. Corrections to the earlier plan (verified)
`combined_orders` has no `code` column (M-6 void for it); `PurchaseOrderController` has no `destroy`; `BankTransactionController` has no `store/update`, and its four actions call an undefined `BankTransaction::generateTransactionNo()`; `orders.code`, `purchase_returns.reference_no`, `purchase_orders.reference_no` have no unique index; `drafts` is MyISAM in every local schema; `purchases`, `purchase_returns`, `purchase_return_products`, `suppliers` are MyISAM in the original schema (`misron_test`) and InnoDB in `sc_staging`/`inv_v2_test`; `sc_staging.purchases` has `uq_purchases_reference_no` and lacks `uq_product_stocks_product_variant`, `inv_v2_test` the reverse. None of the previous plan's migrations was applied blindly.

## 4. Engine compatibility
| Item | Status |
|---|---|
| MariaDB 10.11.14 (disposable) | VERIFIED: migrations, locking, savepoints, deadlock handling, all suites |
| MySQL 8.4 | **NOT EXECUTED**. `tests/staging/mysql84-rehearsal.sh` is prepared (refuses unless the server reports 8.4.x and the database name ends in `_test`). Static review: the migration uses `information_schema.STATISTICS/COLUMNS` (present in 8.x), `ALTER TABLE ... ADD KEY`, no MariaDB-only syntax; the code uses `FOR UPDATE` row locks, savepoints, `GET_LOCK` (API checkout) — all available in 8.4. This is a review, not a validation |
| Production engine/version/table engines/FKs/indexes | **BLOCKED** — required: output of section 0 of `docs/T5_PHASE0_AUDIT_QUERIES.sql` from production or a restored backup |
| MyISAM limitations | transactions, savepoints and row locks do not exist on MyISAM: every locking design here is void on a MyISAM `purchases`/`suppliers`; whether production still has them is BLOCKED |
| Isolation | all lock reasoning assumes REPEATABLE READ (verified locally); production isolation BLOCKED |

## 5. Deployment order (when production facts are available; not authorised now)
1. Verified backup; restore a copy; run the data audit (`T5_PHASE0_AUDIT_QUERIES.sql`) on the copy.
2. Rehearse the InnoDB conversion (if needed) and then the index migration on the copy, record durations.
3. Maintenance window: run the conversion (if needed), then the index migration, then deploy the code.
4. Rollback: code revert; `migrate:rollback --path=...` drops only `idx_t5_*`; an engine conversion is NOT reversible without the backup.
