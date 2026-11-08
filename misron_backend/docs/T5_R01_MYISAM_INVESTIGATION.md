# R-01: MyISAM money/stock tables - investigation and InnoDB migration proposal

**Status: ANALYSIS AND PROPOSAL ONLY. Nothing was executed, no database was touched (the analysis reads repository files and source code), no engine was changed, no application code was changed. Nothing in this document is an approval.** Evidence: `docs/evidence/r01_r03/01_static_method_trace.txt` (regex-assisted method map; indicative, not a proof of every call path) and `02_dump_facts.txt` (facts parsed from the dump files). Production sizes, engines and versions are UNKNOWN (DBA-02/DBA-05): everything about size and time below is for the supplied backup `misron.sql` only, which is not proven to be production.

## 1. The five tables as found in the supplied backup
| Table | Engine | Role | Columns / keys | Rows (backup) | Notes |
|---|---|---|---|---|---|
| `purchases` | MyISAM | purchase header, payment totals | 25 cols, PK only; money in `varchar(255)` / `double` / `decimal` mixed | ~103 | `supplier_id` is `int` (suppliers.id is `bigint`) |
| `purchase_products` | MyISAM | **FIFO cost layers** (one row per received batch; `remaining_qty` consumed by every sale) | 13 cols, PK only; `purchase_quantity`, `buying_unit_cost`, `total_cost` are `varchar(255)` | ~3,462 | the hottest table: written by sales, POS, cancels, settlements, returns, adjustments |
| `suppliers` | MyISAM | supplier master + `due_amount`, `paid_amount`, `opening_balance` | 15 cols, PK only | 2 (26 in `empty_misron.sql`) | balances mutated by read-modify-write |
| `purchase_returns` | MyISAM | return header | 8 cols, PK only | 0 | |
| `purchase_return_products` | MyISAM | return lines | 10 cols, PK only | 0 | |
In `empty_misron.sql` `purchase_products` is already InnoDB while a MyISAM copy `purchase_products_pre_inv1` exists; the other four are MyISAM in both dumps. 11 further small, non-financial tables are MyISAM too (blocked_ips, career_*, checkout_otps, countries, drafts, featured_collections, job_applications, pathao_courier_settings, social_credentials): out of scope.
The MariaDB test database and the 8.4 rehearsal copy had these four/five converted already, which is why every T-5 test passes: **the tests never ran on the schema the backup actually has.**

## 2. What the application does with them (static trace, 38 files, 85 call sites in `PurchaseController` alone)
| Workflow (entry points) | Tables written | Needs | On MyISAM today | After InnoDB |
|---|---|---|---|---|
| Purchase create / update / delete (`PurchaseController::store`, `update`, `purchaseDelete`, `purchaseUpdatePayment`, `updatePurchaseTotals`) | purchases, purchase_products, suppliers (+ chart_of_accounts, statement logs, idempotency_keys which are InnoDB) | one transaction (all-or-nothing), supplier row lock (due/paid lost updates, D4), purchase row lock, FIFO layer lock, idempotency key, deadlock retry | `BEGIN/COMMIT` cover only the InnoDB tables; the three MyISAM writes are immediate and never roll back; `lockForUpdate()` on `suppliers`/`purchases` does nothing | all four guarantees work as designed |
| Purchase return create / edit (`storePurchaseReturn`, `updatePurchaseReturn`, `purchaseReturnEdit`) | purchase_returns, purchase_return_products, purchase_products, purchases, suppliers | transaction; purchase row lock (payable drift fix); FIFO layer lock | same: partial commit possible, no locks | as designed |
| Supplier payment store / update / destroy (`SupplierPaymentController`) | purchases (`total_paid`, `total_payment_due`), suppliers (`due_amount`, `paid_amount`) | transaction, supplier + purchase locks, document-first re-read, retry; reverse-and-apply exactly once | `suppliers` and `purchases` writes survive a rollback; the lost-update fix (D4) and the edit fix do not hold | as designed |
| `SupplierController::processPayment` and supplier create/edit/delete | suppliers, purchases | transaction, lock | `processPayment` retried on deadlock: MyISAM part repeated; edit/delete are unlocked reads even on InnoDB (decision 4) | processPayment protected; edit/delete still need decision 4 |
| Legacy PO store / update / `convertToPurchase` | purchases, purchase_products (via the purchase screen), suppliers | transaction (convert has none: decision 9) | as above | as above |
| v2 goods receipt, supplier invoice/advance/return (`GoodsReceiptService`, `SupplierLedgerService`, `SupplierReturnService`, `OpeningBalanceService`) | purchases (one per receipt), purchase_products, suppliers | transaction (idempotency wrapper), layer + supplier locks | the v2 flag **refuses to switch on** while `purchase_products` is not InnoDB (`InventoryMode`/preflight) | fine; flag still OFF |
| Selling and reversing stock: `StockLedger::takeBatches/giveBackToBatch/release/resizeBatch`, `PosUtility::consumePurchaseProductQtyFIFO`, `OrderController::restock*`, `CourierSettlementController::settle`, `StockAdjustmentController`, `FixPartialSettlements`, `SupplierLedgerService::revalue` | purchase_products (`remaining_qty`) | atomic decrement under a row lock, all-or-nothing across lines | the only workflow with a MyISAM safeguard: `fifoIsTransactional()` (information_schema lookup) makes callers give batch units back by hand after a failure. It protects stock sales, not the other rows of the same request; the row lock is a no-op so two sales can consume the same layer unit | transactional; the hand compensation switches itself off |
| Reports, valuation, audits, dashboards (`ReportController`, `PurchasingController`, `InventoryAudit`, `InventoryReconciliation`, `AccountController`, supplier ledger screens) | read only | consistent reads | MyISAM reads see uncommitted work of other requests | snapshot reads |
| Maintenance (`InventoryResetCommand`, `InventorySampleDataCommand`) | all five | run only on `*_test` databases (guarded) | n/a | n/a |
**Retry is the sharp edge.** `config/deadlock_retry.php` attaches the bounded 3-attempt retry to every non-GET route of `PurchaseController`, `SupplierPaymentController`, part of `PurchaseOrderController`, `SupplierController::processPayment`, `CourierSettlementController`, `StockAdjustmentController` and `OrderController`. The retry assumes that a deadlock rolled the whole transaction back. A deadlock can only come from the InnoDB tables of the request (chart of accounts, orders, statement logs); the MyISAM writes made earlier in the same request are **not** rolled back, and the legacy-purchase idempotency key (an InnoDB insert inside the same transaction) **is** rolled back. The retry therefore re-inserts the purchase, its layers and the supplier balance change: a duplicated purchase and a double supplier due. This is static reasoning from the code; it has not been reproduced on a MyISAM copy (see proposal step R01-D below). It means that on the backup's schema **deploying T-5 can make a rare failure worse than the pre-T-5 behaviour**.

## 3. Which guarantees each table needs
| Guarantee | purchases | purchase_products | suppliers | purchase_returns | purchase_return_products |
|---|---|---|---|---|---|
| Transactions / rollback | required | required | required | required | required |
| Row locks (`FOR UPDATE`) | required (payment, return, delete) | required (FIFO claim) | required (D4) | required (with its purchase) | with its return |
| Atomic updates | totals read-modify-write under lock | `remaining_qty` decrements | `due_amount`/`paid_amount` | - | - |
| Safe deadlock retry | required | required | required | required | required |
| Index for the locking reads | `idx_t5_supplier` (T-5 migration) | `(product_id, variant)` (v2 stock-lookup migration) and `idx_t5_purchase` | PK only (lookups by id) | PK only (empty) | PK only (empty) |

## 4. Migration proposal (a proposal: nothing is approved, nothing runs)
**Existing material:** `sqlupdates/inventory_v2_innodb_conversion.sql`, `tests/staging/rehearse.sh` / `prepare-staging.sh` (a rehearsal on the masked copy already converted all five with "0 differences", MariaDB only, `docs/evidence/staging/engines_after.txt`), `PRODUCTION_BACKUP_REHEARSAL_RUNBOOK.md`, `T5_DEPLOYMENT_ROLLBACK_RUNBOOK.md`. On this session's MySQL 8.4.13 copy the same four `ALTER ... ENGINE=InnoDB` statements ran without error.
**Finding NB-3 in the existing script:** besides the five `ENGINE` changes the file also runs `ALTER TABLE purchases ADD UNIQUE KEY uq_purchases_reference_no (reference_no(100))`. That is a constraint change that belongs to decision 14 (unique constraints after an audit shows no duplicates) and is not covered by any approval; it fails if a duplicate exists. Proposal R01-B: run an **engine-only** version; add the key only after decision 14. (The file was not edited.)

### 4.1 Table-by-table
| # | Table | Statement (proposal) | Why this position | FK compatibility | Indexes before / after | Size (backup) | Validation specific to the table |
|---|---|---|---|---|---|---|---|
| 1 | `suppliers` | `ALTER TABLE suppliers ENGINE=InnoDB;` | smallest; row locks then exist for the balance writers | none in or out; adding FKs later would need `suppliers.id` type parity (child `supplier_id` columns are `int`) | PK only / unchanged | 2-26 rows | `COUNT(*)`, `SUM(due_amount)`, `SUM(paid_amount)`, AUTO_INCREMENT unchanged |
| 2 | `purchase_returns` | `ALTER TABLE purchase_returns ENGINE=InnoDB;` | empty/low risk | none; `purchase_id`/`supplier_id` are `int` | PK only | 0 rows | empty before and after |
| 3 | `purchase_return_products` | `ALTER TABLE purchase_return_products ENGINE=InnoDB;` | same | none; `purchase_return_id` is `int` vs `bigint` PK of parent | PK only | 0 rows | same |
| 4 | `purchases` | `ALTER TABLE purchases ENGINE=InnoDB;` | header table the payments lock | none | PK only / `idx_t5_supplier` added later by the T-5 migration | ~103 rows | `COUNT(*)`; sums of `net_total_amount`, `total_paid` (cast), `total_payment_due` (cast) |
| 5 | `purchase_products` | `ALTER TABLE purchase_products ENGINE=InnoDB;` (last: the hot table keeps selling until the window closes) | FIFO layers; every sale writes it | none | PK only / `idx_stock_lookup_product_variant` and `idx_t5_purchase` added later by migrations | ~3,462 rows (~0.6 MB of dump text) | `SUM(remaining_qty)` per product (must equal the fingerprint), valuation, `inventory:reconcile` |
All five in **one** maintenance window and one order (any order is valid: there are no foreign keys): a request that spans several of them is only atomic when all of them are InnoDB. Safer statement form for rehearsal: `ALTER TABLE t ENGINE=InnoDB, ALGORITHM=COPY, LOCK=SHARED;` (reads allowed, writes blocked, no surprises about the algorithm). Conversion keeps column order, AUTO_INCREMENT, charset (`utf8mb4_general_ci` here) and data; it does not add the missing indexes - those come from the two migrations (which are repeat-safe and run after).

### 4.2 Checks before (on a restored copy first, then in the window)
1. Facts: `SELECT table_name, engine, table_rows, ROUND(data_length/1048576,2) data_mb, ROUND(index_length/1048576,2) index_mb, row_format, auto_increment FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN (<the five>);` and `inc1_db_facts.sql` (engine, version, isolation, `sql_mode`).
2. Strict-mode blockers: zero dates / invalid values in the five tables (`created_at = '0000-00-00 00:00:00'` etc.). The backup has none (0 zero-date literals in their data); production unknown. `CHECK TABLE` on the copy.
3. Free disk >= 2 x (data + index) of the largest table plus redo/undo headroom (the backup's tables are all < 1 MB).
4. Fingerprint: `tests/staging/fingerprint.sql` (+ `baseline.php`) and per-table `COUNT(*)`; `php artisan inventory:reconcile` / `accounting:baseline` readings where the environment supports them.
5. Application quiet: maintenance mode, scheduler and queue workers stopped, no cron touching the tables, all admin users out, payment gateways' callbacks considered (an order callback during the window would fail).

### 4.3 Backup requirement
`mysqldump --single-transaction` is **not** consistent for MyISAM; during the window use `--lock-tables` (or `FLUSH TABLES WITH READ LOCK` snapshot) after the application is stopped, then **restore the dump on a separate server and compare the fingerprint** before the ALTER. Keep the file, checksum and the fingerprint with the change record. Also take a filesystem/volume snapshot if the host supports it (fast restore).

### 4.4 Downtime estimate
Evidence-based: for the supplied backup (largest table 3.5 k rows, < 1 MB) each `ALTER` finished within the same few-second step as the surrounding rehearsal on the MariaDB and MySQL 8.4 copies; no timing was recorded, so I give no measured number. Production: unknown size, so the estimate is a **method**: rehearse on the restored backup, record the wall time of each ALTER, add the backup, validation and reopening steps; plan the window as `3 x` the rehearsed total (minimum 30 minutes for safety). Writes to the five tables block during their copy; reads continue. `purchase_products` is written by every sale, so the storefront must not take orders in the window.

### 4.5 Validation after (before reopening)
Engines (`information_schema`): all five `InnoDB`; row counts and aggregates equal to the pre-window fingerprint; `AUTO_INCREMENT` unchanged; `SHOW CREATE TABLE` as expected; run the two index migrations (T-5, stock lookup) and `ANALYZE TABLE` on the five; `php artisan inventory:preflight` (read-only); `accounts:number-lock-selfcheck`; smoke on staging first: one purchase, one supplier payment edit, one sale, one cancel; confirm `StockLedger::fifoIsTransactional()` now returns true (it caches per process; restart PHP-FPM workers).

### 4.6 Rollback strategy
* **During the window (no new writes yet):** restore the five tables from the pre-window dump, or - preferred for `purchase_products` if the host allows - use a **shadow-table swap** instead of in-place conversion: `CREATE TABLE x_new LIKE x; ALTER TABLE x_new ENGINE=InnoDB; INSERT INTO x_new SELECT * FROM x; RENAME TABLE x TO x_old, x_new TO x;` which makes the rollback a single `RENAME` back and leaves the old table intact until the owner agrees to drop it. Both are lossless because nothing wrote in between.
* **After reopening:** a restore would discard business done since; the practical rollback is forward (fix, or `ALTER ... ENGINE=MyISAM` which is mechanically possible and returns to the unsafe state). The conversion script's own comment says an engine change is "not reversible without data risk once new rows exist"; treat it as one-way after reopening.
* Failure of the ALTER itself leaves the table unchanged (the copy is atomic).

### 4.7 Proposed sequence (each step needs the named person's approval)
1. **R01-A** (Owner + DBA): approve rehearsing the conversion on a masked restore of the production backup (P2/P3) with the target engine.
2. **R01-B** (Tech lead + DBA + Accountant): engine-only script; unique key deferred to decision 14.
3. **R01-D** (Owner approves a disposable-database experiment): reproduce the retry hazard on a MyISAM copy (inject a deadlock after a MyISAM write) and show it disappears after conversion; evidence for the owner. *Not done: it needs database writes on a disposable database.*
4. **R01-C** (Owner): add an explicit "all money/stock tables are InnoDB" gate to the activation checklist/preflight (the v2 preflight already refuses; the legacy T-5 paths do not). *Code change; not done.*
5. DBA converts in the approved window; validation 4.5; staging smoke; only then T-5 deployment is considered.

## 5. Residual risks even after conversion
Row locks on unindexed predicates lock many rows (hence the index migrations first); behaviour changes from table-level to row-level locking can expose latent races in unlocked paths (supplier edit/delete, decision 4); varchar money columns remain (decision 13); the 3.5 k-row backup says nothing about production volume.
