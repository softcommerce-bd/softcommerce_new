# T-5 Phase 0 – Schema compatibility

**Scope:** Task 1 (schema verification) and the schema-dependent parts of Task 5. Read-only. Nothing was migrated, altered or written to staging or production. T-5 remains **NOT ACCEPTED**; staging **NO-GO**; production **NO-GO**.

Status words: **VERIFIED**, **NOT VERIFIED**, **BLOCKED**, **FEASIBLE**, **FEASIBLE WITH CHANGES**, **NEEDS BUSINESS APPROVAL**, **FAILED**.

## 0. Evidence rules for this document
* Everything below was read from `information_schema`/`SHOW` of the **local MariaDB server** (see section 1) and from the repository. Source of each table: the query text is given or obvious from the section heading.
* The local databases are **not** the production database. The production engine, version and table engines are **BLOCKED** (section 2). No local result is used as a statement about production.
* Local databases inspected (metadata only, `information_schema`): `inv_v2_test` (disposable, synthetic), `misron_test` (disposable; the *original* MyISAM-era schema), `sc_staging`, `sc_stg_pre` (staging-rehearsal copies; **not** modified; only metadata read), and engine counts of the other rehearsal schemas. Data queries were run only on `inv_v2_test`, with one exception disclosed in section 1.3.

## 1. Local database environment

### 1.1 Engine and version – VERIFIED (local only)
| Item | Value | Source |
|---|---|---|
| Server | MariaDB `10.11.14-MariaDB-0ubuntu0.24.04.1` (Ubuntu 24.04) | `SELECT VERSION(), @@version_comment` |
| Default engine | InnoDB | `@@default_storage_engine` |
| Isolation | REPEATABLE-READ | `@@tx_isolation` (MariaDB name; `@@transaction_isolation` is not a MariaDB 10.11 variable) |
| Lock wait timeout | 50 s | `@@innodb_lock_wait_timeout` |
| `sql_mode` | `STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION` | `@@sql_mode` (note: the earlier "silently blanked ENUM" observation applied to a non-strict session; this server's global mode is strict) |
| Charset/collation | `utf8mb4` / `utf8mb4_general_ci` | `@@character_set_server`, `@@collation_server` |
| `lower_case_table_names` | 0 | `@@lower_case_table_names` |

### 1.2 Schemas on this server
Disposable/test: `inv_v2_test`, `misron_test`, `parity_artisan_test`, `parity_sql_test`, `dumpcopy_test`, `dumpcopy2_test`, `rehearsal_a_test`, `rehearsal_b_test`, `rehearsal_c_test`, `dev_idx_a`, `dev_idx_b`. Staging-rehearsal copies: `sc_staging`, `sc_stg_pre`, `sc_stg_rb`, `sc_stg_sql`. Table counts: every schema has 147–185 InnoDB tables and 11–16 MyISAM tables (query: `select table_schema, engine, count(*) from information_schema.tables ... group by 1,2`).

### 1.3 Disclosure
* One read-only aggregate fingerprint query (`tests/staging/fingerprint.sql`, SELECT sums/counts) was run against `sc_staging` to confirm it is unchanged: **identical** to `/tmp/claude-0/staging/fp_final.txt`. Nothing else in staging was queried (row-count estimates came from `information_schema.tables.table_rows`).
* Two throw-away experiments wrote and removed temporary rows in the disposable `inv_v2_test` (3 `courier_settlements` rows, 2 `carts` rows) to measure lock behaviour; their auto-increment counters moved; the financial fingerprint is unchanged (`fingerprint_after` first 13 lines identical).

## 2. Production environment – BLOCKED
| Item | Status | Exact information required |
|---|---|---|
| Engine and exact version (MySQL vs MariaDB, e.g. `8.0.x` / `10.x.y`) | **BLOCKED** | Output of `SELECT VERSION(), @@version_comment, @@innodb_version, @@default_storage_engine, @@transaction_isolation OR @@tx_isolation, @@sql_mode, @@innodb_lock_wait_timeout, @@lower_case_table_names, @@character_set_server, @@collation_server;` run by the hosting owner on the production server |
| Engine of every table | **BLOCKED** | Output of section 0 of `docs/T5_PHASE0_AUDIT_QUERIES.sql` (the `information_schema.tables` listing), run on production or on a restored backup of it |
| Whether `idempotency_keys`, `ledger_outbox`, `journal_*` exist in production | **BLOCKED** | `SHOW TABLES` / the same listing; whether the inventory_v2 migrations have ever been applied there |
| Index definitions on production | **BLOCKED** | The `information_schema.statistics` query of section 0 of the audit file |
| Whether the hourly/daily scheduler runs and how | **BLOCKED** | crontab / supervisor config of the production host |
| Storage driver for uploads (`FILESYSTEM_DRIVER`, which disk holds `purchases/`, `supplier-payments/`, `documents/`) | **BLOCKED** | `.env` value (not the secret values) and the disk definition used in production |
| PHP and Laravel runtime (FPM vs persistent workers) | **BLOCKED** | process manager configuration; matters for open-transaction behaviour (audit report F-03) |

Until the first row is supplied, **no claim of production compatibility is made** for any design (section 6).

## 3. Storage engines of the tables involved (local `inv_v2_test`; same table set checked in `sc_staging`) – VERIFIED (local)
Query: `select table_name, engine from information_schema.tables where table_schema = '<db>'`.

| Domain | Tables | `inv_v2_test` | `sc_staging` |
|---|---|---|---|
| Purchases | `purchases`, `purchase_products`, `purchase_returns`, `purchase_return_products`, `purchase_orders`, `purchase_order_details` | InnoDB | InnoDB |
| Suppliers/payments | `suppliers`, `supplier_payments`, `supplier_payment_purchases`, `supplier_invoices`, `supplier_invoice_payments` | InnoDB | InnoDB |
| Stock | `product_stocks`, `stock_balances`, `stock_movements`, `stock_allocations`, `order_demands`, `stock_adjustments`, `stock_adjustment_lines` | InnoDB | InnoDB |
| Orders | `orders`, `order_details`, `combined_orders`, `carts`, `addresses`, `order_payment_mappings` | InnoDB | InnoDB |
| Accounts/accounting | `chart_of_accounts`, `account_statement_logs`, `journal_entries`, `journal_lines`, `ledger_outbox`, `refund_postings`, `refund_requests`, `wallets` | InnoDB | InnoDB (`ledger_outbox` and `refund_postings` do not exist in `sc_staging`: the T-5-era outbox and refund migrations are not applied there) |
| Vouchers/expenses/income/transfers | `vouchers`, `voucher_details`, `expense_payments`, `incomes`, `balance_transfers`, `bank_transactions`, `customer_payments`, `transaction_methods`, `cost_centers` | InnoDB | InnoDB |
| Courier | `courier_settlements`, `courier_settlement_details` | InnoDB | InnoDB |
| Idempotency | `idempotency_keys` | InnoDB | InnoDB (0 rows by `table_rows` estimate) |
| Checkout draft | `drafts` | **MyISAM** | **MyISAM** |

Full engine list: scratch file `p0/all_tables_inv_v2_test.txt` (197 tables). Row-count estimates in `sc_staging` (information_schema): `orders` 32, `purchases` 103, `courier_settlements` 24, `supplier_payments` 1, `account_statement_logs` 105, `idempotency_keys` 0.

## 4. MyISAM tables and their dependencies – VERIFIED (local)

### 4.1 MyISAM tables per local schema
| Schema | MyISAM tables |
|---|---|
| `inv_v2_test` (12) | `blocked_ips`, `career_banners`, `career_departments`, `career_job_posts`, `checkout_otps`, `countries`, `drafts`, `featured_collections`, `job_applications`, `pathao_courier_settings`, `purchase_products_pre_inv1` (backup copy), `social_credentials` |
| `sc_staging`, `sc_stg_pre` (11) | the same minus the `_pre_inv1` backup |
| `misron_test` (16, the **original** schema) | the 11 above plus **`purchases`, `purchase_returns`, `purchase_return_products`, `suppliers`** and `purchase_products_pre_inv1` |

### 4.2 What this means for the financial tables
* [VERIFIED, repository] In the original schema four purchasing tables are MyISAM (`misron_test`). `sqlupdates/inventory_v2_innodb_conversion.sql` converts `purchases`, `purchase_products`, `purchase_returns`, `purchase_return_products`, `suppliers` and states: *"MyISAM ignores transactions and row locks … `lockForUpdate()` does nothing"*. `docs/PRODUCTION_BACKUP_REHEARSAL_RUNBOOK.md` repeats that these tables are MyISAM "in the old schema".
* [VERIFIED, local] `sc_staging` and `inv_v2_test` have those tables as InnoDB because the conversion was applied in the rehearsal. **Whether production has been converted is BLOCKED.**
* **Every design in `T5_REMEDIATION_PLAN.md` that relies on atomic commit/rollback or row locks on these five tables is impossible on MyISAM**: transactions, savepoints, deadlock retry, `lockForUpdate`, the identity-row-with-effects atomicity and the suppliers' lock level (3 in the plan). The T-5 code that exists today has the same dependency.
* [VERIFIED, local] Foreign keys: `inv_v2_test` and `misron_test` have 8, `sc_staging` 7; **none** involves the five purchasing tables (`information_schema.referential_constraints`). The FKs present are permissions/roles, `chart_of_accounts.parent_id`, `transaction_methods.source_account_id`, `funnel_versions.funnel_id`, `payku_payments`. So the conversion has no FK-ordering dependency locally. **Production FKs: BLOCKED.**
* [VERIFIED, repository] The conversion script also adds `UNIQUE uq_purchases_reference_no (reference_no(100))` and tells the operator to run duplicate checks first. [VERIFIED, local] `sc_staging.purchases` has that unique index; `inv_v2_test.purchases` does **not** (it was built from Laravel migrations, not from the script) – the two local schemas differ here.
* [VERIFIED, local] `drafts` (the abandoned-checkout/draft table deleted during checkout: `CheckoutController` line ~212, `Api\V2\OrderController` line ~747) is **MyISAM**. A draft delete inside an order transaction is **not rolled back**; it must stay outside any atomicity claim.
* Other MyISAM tables (`blocked_ips`, `countries`, `featured_collections`, career tables, `checkout_otps`, `pathao_courier_settings`, `social_credentials`) are not financial. `countries` is read during address handling (no write on the order path).
* `purchase_products_pre_inv1` (MyISAM backup copy of FIFO layers) must not be reused by any code path; [NOT VERIFIED] whether anything references it.
* **ALTER cost/risk (NOT VERIFIED, needs production size):** `ALTER TABLE … ENGINE=InnoDB` rebuilds each table and needs free disk of about the table size (script comment); MyISAM→InnoDB changes index-length limits (`reference_no(100)` prefix is used for that reason), the AUTO_INCREMENT behaviour on restart, and `COUNT(*)` timing.

## 5. Indexes and constraints

### 5.1 Missing or insufficient indexes (VERIFIED: `information_schema.statistics` on `inv_v2_test`; same on `sc_staging` except where noted)
Method: for each lookup the code performs, the table's index list was checked for an index whose **leading column** is the predicate; `EXPLAIN` on the empty disposable tables confirmed `type=ALL` for `carts.user_id` and `orders.code`. Empty tables are not representative of cost; the conclusion "must scan" comes from the index definitions.

| Table | Indexes present (besides PK) | Lookups the code performs without an index | Consequence |
|---|---|---|---|
| `carts` | **none** | `where user_id`, `temp_user_id`, `product_id` (checkout, API, POS, cart pages) | Any `FOR UPDATE` filtered by `user_id` scans and **locks every row of the table** (measured, section 5.3). The D6 cart lock in `OrderController::store` (line ~1286) therefore serialises **all** customers. |
| `combined_orders` | none | `where user_id … created_at` (D6 guard) | full scan on every checkout |
| `orders` | `funnel_id`, `funnel_version_id` only | `where code` (`Order::generateUniqueCode`, every order), `where combined_order_id`, `where user_id`, `settlement_status`, `delivery_status` | full scans; the `code` race has no unique constraint |
| `addresses` | none | `where user_id` | scan |
| `account_statement_logs` | `(account_id, transaction_date)` | `where source_type = … and source_id = …` (`unsettle`), `where order_id = … and account_id` (settle AR check), `transaction_type` | scans; `unsettle`'s log read is unindexed |
| `purchases` | none (staging: unique `reference_no`) | `where supplier_id` (supplier due sum), `reference_no like 'Ref-%' order by id desc … FOR UPDATE` (number generation) | scans; locking scan on number generation |
| `suppliers` | none | `slug`, `mobile` | n/a to this plan (PK used for locks) |
| `supplier_payment_purchases` | none | `where supplier_payment_id`, `purchase_id` | scan |
| `purchase_products` | `(product_id, variant)` (absent on `sc_staging`) | `where purchase_id` | scan |
| `purchase_returns`, `purchase_orders` | none | `reference_no` | no uniqueness at all |
| `product_stocks` | unique `(product_id, variant)` (**absent on `sc_staging`**) | – | duplicate rows possible on staging-style schema |
| `courier_settlements` | `order_id`, `settlement_no` (+unique), `status` | – | OK; no uniqueness per order |

### 5.2 Existing unique constraints relevant to idempotency and numbering – VERIFIED (`inv_v2_test`)
Document numbers: `supplier_payments.payment_no`, `vouchers.voucher_no`, `incomes.voucher_no`, `expense_payments.voucher_no`, `balance_transfers.voucher_no`, `bank_transactions.transaction_no`, `customer_payments.payment_no`, `stock_adjustments.reference_no`, `courier_settlements.settlement_no`, `journal_entries.entry_no`, `goods_receipts.receipt_no`, `cost_centers.code`, `chart_of_accounts.code`, `account_transfers.transfer_no` (a table not covered by earlier audits; usage not traced).
**No unique constraint:** `orders.code`, `purchases.reference_no` (except staging), `purchase_orders.reference_no`, `purchase_returns.reference_no`. (`combined_orders` has **no `code` column**, so the plan's M-6 mention of `combined_orders.code` is void.)
Idempotency: `idempotency_keys (scope,key)`, `journal_entries.idempotency_key`, `journal_entries.reversal_of_id`, `goods_receipts.idempotency_key`, `supplier_invoices.idempotency_key` and `(supplier_id, invoice_no)`, `supplier_invoice_payments.idempotency_key`, `stock_return_receipts.idempotency_key`, `refund_postings.idempotency_key/refund_request_id/external_ref`, `chat_conversation_orders.idempotency_key`, `funnel_checkout_attempts.key_hash`, `order_demands.order_detail_id`, `stock_balances (product_id,variant)`.
Not protected: legacy supplier payment, voucher, expense, income, transfer, bank transaction, customer payment, stock adjustment, courier settlement/unsettle, purchase return, PO (legacy) creation – no idempotency key column or table use (code scan, audit report section 3). The legacy purchase uses `Idempotency` scope `legacy_purchase` (`PurchaseController::store`, op_key).

### 5.3 Lock-footprint measurements on the disposable database – VERIFIED
| Experiment | Result |
|---|---|
| Session A: `begin; select id from carts where user_id=900001 for update;` Session B: same for `user_id=900002` with `innodb_lock_wait_timeout=2` | **B failed with `ERROR 1205 Lock wait timeout exceeded`** – a different customer's cart row was locked by A (no index on `carts.user_id`). |
| Session A: `select … from courier_settlements where settlement_no like 'STL-<today>%' order by id desc limit 1 for update` (no row matching) with 3 older rows; B updates the oldest row | B succeeded – no table-wide lock here (D1's query is safe in this respect). |
Two temporary `carts` rows and three `courier_settlements` rows were inserted for these tests and deleted.

### 5.4 `idempotency_keys` – VERIFIED
```
id bigint unsigned PK auto_increment; scope varchar(40) NOT NULL; key varchar(120) NOT NULL; result longtext NULL;
created_at, updated_at timestamp NULL; UNIQUE idempotency_keys_scope_key_unique (scope, key); InnoDB; utf8mb4_unicode_ci
```
* Current usage in code (`Idempotency::once` call sites): scopes `supplier_return`, `supplier_invoice`, `supplier_payment`, `supplier_payment_batch`, `supplier_advance`, `supplier_advance_apply`, `purchase_order`, `goods_receipt`, `journal`, `refund`, `return_receipt`, `opening_balance`, plus `legacy_purchase` (op_key path in `PurchaseController::store`). The longest scope string is 22 characters (limit 40).
* Rows: `inv_v2_test` 0; `sc_staging` ≈0 by estimate; production **BLOCKED**.
* Behaviour [VERIFIED by earlier executed tests, `InventoryV2ConcurrencyTest`, `DeadlockLimitsTest`, `DeadlockRecoveryTest`]: the key row is inserted inside the operation's transaction; two simultaneous attempts serialise on the unique index; a rolled-back attempt leaves no key; a deadlock victim is retried up to 3 times.
* Limits relevant to the plan: `key` ≤ 120 chars (a sha1 fingerprint is 40; a composite `courier_settlement:17` is short); no `actor/target` columns (plan M-1); no expiry or clean-up job exists [VERIFIED: no command prunes it]; `result` is JSON text, replay adds `replayed:true`.

## 6. Is the proposed transaction and locking design possible with the current schema?
| Question | Answer |
|---|---|
| On **InnoDB** (local schemas) – can a unique identity row, row locks by primary key, savepoints and `FOR UPDATE` on accounts/suppliers/documents be used as designed? | **FEASIBLE WITH CHANGES** (VERIFIED locally by the earlier parallel and real-deadlock tests for several paths). Required changes: indexes in 5.1, otherwise locking reads on unindexed predicates lock whole tables. |
| On **MyISAM** purchasing tables (original schema) | **FAILED / impossible** – no transactions or locks; conversion is a hard prerequisite. |
| On **production** | **BLOCKED** – engine, version and table engines unknown. |
| Does the schema already contain what the plan's M-1…M-9 add? | None of M-1 … M-9 exists (VERIFIED); `idempotency_keys` exists locally (production unknown). |
| Anything in the current schema that contradicts a plan assumption? | Yes: (1) `combined_orders.code` does not exist; (2) `PurchaseOrderController` has no `destroy`; (3) `carts`, `orders`, `account_statement_logs`, `purchases`, `supplier_payment_purchases` lack the indexes the lock design silently assumes; (4) `drafts` is MyISAM; (5) `sc_staging` lacks `uq_product_stocks_product_variant` and `idx_purchase_products_product_variant`, so the unique-key assumption of `StockLedger` is not met on that schema; (6) money columns are `varchar(255)` (`suppliers.opening_balance`, `purchases.total_payment_due`, other purchase totals) or `double` (`suppliers.paid_amount`). |

## 7. Indexes the plan would need (listed, not created)
`carts(user_id)`, `carts(temp_user_id)`; `combined_orders(user_id, created_at)`; `orders(code)` unique after audit, `orders(combined_order_id)`, `orders(user_id)`; `account_statement_logs(source_type, source_id)`, `account_statement_logs(order_id, account_id)`; `purchases(supplier_id)`; `supplier_payment_purchases(supplier_payment_id)`, `(purchase_id)`; `purchase_products(purchase_id)`; `addresses(user_id)`; `courier_settlements(order_id)` unique after audit. Index builds on large tables need the production size (BLOCKED). None is proposed to run before the data audit (`T5_PHASE0_AUDIT_QUERIES.sql`, execution BLOCKED) is complete.

## 8. Summary of schema statuses
| Item | Status |
|---|---|
| Local engine/version | VERIFIED (MariaDB 10.11.14) |
| Production engine/version | BLOCKED |
| Engines of financial tables, local | VERIFIED (InnoDB) |
| Engines of financial tables, production | BLOCKED |
| MyISAM list and dependencies, local | VERIFIED; FK dependencies none |
| Missing/insufficient indexes | VERIFIED (section 5.1) |
| Unique constraints for numbering/idempotency | VERIFIED (section 5.2) |
| `idempotency_keys` structure and usage | VERIFIED locally; production usage BLOCKED |
| Design possible on current schema | FEASIBLE WITH CHANGES locally; BLOCKED for production; impossible on MyISAM |
