# Phase 4 — Stock tables: migration and recovery plan

Status: **rehearsed on isolated test databases only. The main `misron` database has not been altered.**
The SQL files are ready in `sqlupdates/`; running them against the real database waits for your go-ahead
(final integration testing).

| File | Purpose |
|---|---|
| `inventory_v1_preflight.sql` | Reads only. Go / no-go checks with the clean result written beside each query |
| `inventory_v1_stock_safety.sql` | The change: safety copies, `purchase_products` MyISAM → InnoDB, two indexes |
| `inventory_v1_stock_safety_rollback.sql` | Puts the structure back; leaves row data alone |

Shell equivalent of the preflight, with CSV output: `php artisan inventory:audit --preflight --out`.

---

## 1. What changes, and what deliberately does not

| Change | Why |
|---|---|
| `purchase_products` MyISAM → **InnoDB** | MyISAM ignores transactions. A failed order cannot give its batch units back, and there are no row locks. InnoDB gives both. |
| `UNIQUE (product_id, variant)` on `product_stocks` | One stock row per product and variation, and a precise lock target. Without an index every conditional stock update would scan (and lock) the whole table. |
| `INDEX (product_id, variant(100))` on `purchase_products` | The first-in-first-out lookup. Same locking reason. The 100-character prefix keeps the key under the 767-byte limit of older row formats. |

**Not changed:** `purchases`, `suppliers`, `purchase_returns`, `purchase_return_products` stay MyISAM, and
no existing row, column or value is altered. The stock service does not need them to be transactional: it
only reads `purchases.supplier_id`. A purchase screen that fails halfway still leaves its `purchases`
row behind exactly as it does today; only its `purchase_products` rows now roll back, which is no worse.

The code works on **both** schemas. `StockLedger` reads the engine of `purchase_products` and, while it is
still MyISAM, gives consumed batch units back by hand when an order fails. So deployment order does not matter
for safety; the recommended order is in section 6.

---

## 2. Inspection before conversion (done, read-only)

Server: MySQL 8.4.3 (Laragon), REPEATABLE READ, lock wait 50 s, `innodb_file_per_table=1`, default row format DYNAMIC.
Production's flavour and version are not known from here; the preflight prints them.

| Table | Engine | Rows | Size | Collation |
|---|---|---|---|---|
| `product_stocks` | InnoDB | 3,734 | 272 KB | utf8mb3_unicode_ci |
| `purchase_products` | **MyISAM** | 3,462 | 477 KB | utf8mb4_general_ci |
| `purchases` | MyISAM | 103 | 15 KB | utf8mb4_general_ci |
| `purchase_returns`, `purchase_return_products` | MyISAM | 0 | 1 KB | utf8mb4_general_ci |

- **Keys and indexes:** only `PRIMARY` on both tables. No other index exists on the stock tables.
- **Dependents:** no foreign keys, triggers, views or stored routines reference the stock tables.
- **Row counts and representative records** (kept for comparison after the change):
  `purchase_products` 3,462 rows, `Σ remaining_qty` 686,700; `product_stocks` 3,734 rows, `Σ qty` 747,360.
  A typical batch: `product 2403, variant "Beige-M", purchase_quantity "400", remaining_qty 392, buying_unit_cost "190"`;
  its stock row: `qty 192`.
- **Duplicate (product, variation) stock rows:** none, compared with the column's collation (so
  `Black-M` and `black-m` would have been caught).
- **NULL and empty variations:** `product_stocks.variant` is `NOT NULL`; no empty strings; no values with leading or
  trailing spaces. In `purchase_products` every variation is text; no `NULL` either. (A unique key
  does not stop duplicate `NULL`s, which is why the preflight reports them as a blocker for `product_stocks` and a
  warning for `purchase_products`. Plain, non-variant products would use `''` in stock rows and `NULL` or `''` in batches;
  the stock service matches both.)
- **Negative or impossible numbers:** none. No batch holds more than was bought; no non-numeric quantity or cost; no zero dates.
- **Existing inconsistencies between the records:** large, and unchanged by this migration. See
  [PHASE4_INVENTORY_DISCREPANCY_REPORT.md](PHASE4_INVENTORY_DISCREPANCY_REPORT.md). The unique key does not require
  them to be fixed first, because the key is on the *identity* of a row, not its quantity.
- **Application dependencies on `purchase_products`:** `Api\V2\OrderController`, admin `OrderController`,
  `PosUtility`, `PurchaseController`, `StockAdjustmentController`, `Api\V2\PaystationController`,
  `ReportController`, `SupplierController`, `CourierSettlementController`, `AccountController`,
  `PurchaseOrderController`, `Models\Product`, `Models\Purchase`, `FixPartialSettlements`. All use Eloquent or the
  query builder with plain `SELECT`/`UPDATE`/`INSERT`/`DELETE`. Nothing uses `LOCK TABLES`, `FULLTEXT`,
  `INSERT DELAYED` or `REPAIR`, which behave differently on InnoDB. Existing `DB::beginTransaction()` calls
  in purchase code start covering these rows, which is a fix, with one consequence: a transaction that
  holds batch rows for a long time (a very slow purchase edit) can now make a checkout wait, up to the 50 s lock wait.

## 3. FIFO purchase-batch compatibility

- `StockLedger::take()` reproduces the old routine's results: oldest batch first, the same cost arithmetic, the
  same batch list (`purchase_product_id`, `purchase_id`, `supplier_id`, `qty`, `unit_price`, `total_price`).
  `StockLedgerTest::test_result_matches_the_routine_it_replaces` runs the old routine and the service on identical
  data (four scenarios) and compares cost, batches and remaining quantities.
- The order line now also stores which batches supplied it in the previously unused `order_details.fifo_batch_info`.
- Batches are read with `SELECT … FOR UPDATE` and updated with `… WHERE remaining_qty >= n`. This matters only on
  InnoDB (on MyISAM the clause is accepted and ignored): without the locking read, REPEATABLE READ would keep
  showing a batch that another order had already emptied. The concurrency suite found this before it shipped.
- Other writers (purchases, returns, adjustments, POS, admin orders) keep working unchanged on InnoDB. Where they
  conflict with the service's locking is in [PHASE4_STOCK_WRITERS_AUDIT.md](PHASE4_STOCK_WRITERS_AUDIT.md).

### 3.1 A side effect of the new indexes, found by the test suite, and its fix

A query with no `ORDER BY` returns rows in whatever order the chosen index yields. Before the change, a
`WHERE product_id = ?` on `product_stocks` or `purchase_products` scanned the table in primary-key order,
so rows came back in the order they were created. After adding a key on `(product_id, variant)` the same
query returns them **alphabetically by variation** (`L, M, S, XL`, not `S, M, L, XL`). Two existing funnel tests
failed on the converted schema because of exactly this, and it would have reached the shop:

- the size picker and the "default variation" (`$product->stocks->first()`) on product pages and in the cart;
- the **stock valuation report**, which walks `$product->purchaseProducts` expecting oldest batch first and takes
  `first()` / `last()` as the earliest / latest purchase.

**Fix (in this work, three lines in `app/Models/Product.php`):** `stocks()`, `purchaseProducts()` and
`purchases()` now declare `orderBy('<table>.id')`, which is the order the shop has always had, so results are
identical before and after the migration. Covered by `RowOrderTest`, which passes on both schemas.

**Not changed, listed for review:** direct `ProductStock::where('product_id', …)->get()` and
`PurchaseProduct::where('product_id', …)` calls elsewhere (the purchase-screen variation search, a few admin reports)
will list variations alphabetically after the migration. None of them uses the order for a calculation; the
FIFO walks in the admin order screens, POS and stock-adjustment all already say `orderBy('id')`.

## 4. Backup and restore procedure (verified)

Verified on 2026-10-23 with the real tables, source → file → scratch database:

```
mysqldump -u root --single-transaction --skip-lock-tables --set-gtid-purged=OFF misron \
    product_stocks purchase_products purchases order_details > stock_tables.sql        # 944 KB, 0.5 s
mysql -u root misron_restore_test < stock_tables.sql                                   # 0.6 s
```

Row counts and a content checksum (`SUM(CRC32(CONCAT_WS('|', all stock columns)))`) were then compared across
the source database, the restored copy and the rehearsal database for all four tables. **All three matched exactly**
(`purchase_products` 3,462 rows, checksum 7561716148753; `product_stocks` 3,734 rows, 8017890047913; `purchases` 103,
234929076606; `order_details` 34, 68668550376). So the dump is complete and restorable, and the rehearsal data is a
true copy.

**On production:** put the storefront in maintenance mode first (`php artisan down`). With MyISAM tables
`--single-transaction` does not give a consistent snapshot of them, which is another reason to stop orders for the
few minutes this takes. Keep the dump file off the server as well as on it. Then restore it once into a scratch
database and compare the checksums above before you proceed. A backup that has not been restored is not a backup.

## 5. Rehearsal results (isolated database `misron_inventory_test`)

Built from scratch by `TEST_DB_DATABASE=misron_inventory_test php tests/create_test_database.php --fresh --inventory-data`,
which copies the rows of `products`, `product_stocks`, `purchase_products` and `purchases` from `misron` (read-only on
the source; no orders, users or addresses are copied), then the real SQL files were applied with the `mysql` client.

| Step | Result |
|---|---|
| Preflight SQL | Runs clean; all expected-empty queries empty |
| Change script | **1.2 s**. Safety copies equal (3,462 / 3,734 rows). Engine InnoDB. Both keys present |
| Data after change | Row counts and checksums **identical** to before |
| Query plans | FIFO lookup uses `idx_purchase_products_product_variant` (`ref`, 1 row). Stock update uses `uq_product_stocks_product_variant` (`range`, 1 row) |
| Rollback script | Structure back (MyISAM, keys gone); checksums **identical** again |
| Re-apply after dropping the copies | Succeeds |
| Run the change script twice | Second run stops at `CREATE TABLE … _pre_inv1` ("already exists") before touching anything |
| Unique key with a duplicate present (injected in the scratch copy) | `ADD UNIQUE` fails with *Duplicate entry*; table unchanged |
| Case-only duplicate (`Beige-M`/`beige-m`, `X`/`x `) | Detected by the collation-aware check and by the key |
| NULL variation | Rejected by the column (`NOT NULL`) |

The whole stock-service test suite then ran on this schema (section 7).

## 6. Production runbook

Do these in order. Each gate has a stop condition.

1. **Quiet window.** Choose a period with few orders. Expect the change itself to take seconds for a table this size
   (1.2 s for 3,462 rows; time scales with rows, so a table 100× larger needs minutes).
2. **Preflight.** Run `inventory_v1_preflight.sql` (or `inventory:audit --preflight`). **Stop** on any `blocker`:
   duplicate stock rows, NULL variations in `product_stocks`, zero dates, non-numeric batches. Duplicates are fixed by a
   person (decide which quantity is right), never merged by script.
3. **Free disk.** About twice the `purchase_products` size must be free in the MySQL data directory.
4. **Maintenance mode** (`php artisan down`) and confirm no order is in flight (`SHOW PROCESSLIST`).
5. **Backup** (section 4) and **restore-check it** in a scratch database. Write down the counts and checksums from preflight query 8.
6. **Run `inventory_v1_stock_safety.sql`.** Check that the two copy counts it prints are equal. Statements are
   independent: if the unique key fails, the engine change has already succeeded and is safe to keep; fix the duplicates and
   run the remaining `ALTER`s by hand.
7. **Verify:** engines/keys query at the end of the script; rerun preflight query 8 and compare rows, sums and checksums with step 5
   (they must be identical); `EXPLAIN` the two statements in section 5.
8. **Deploy the code** that includes `StockLedger`. (Deploying it before step 6 is also safe; it detects the old engine.)
9. **Smoke test** with one real order of a low-value, in-stock, non-hold product, then cancel it: stock and batch both return.
10. **Leave maintenance mode.** Watch `storage/logs` and `SHOW ENGINE INNODB STATUS` for lock waits during the first day.
11. **After about two weeks** with no problems: `DROP TABLE purchase_products_pre_inv1, product_stocks_pre_inv1;`.

## 7. Test evidence on the converted schema

| Suite | MyISAM schema (`misron_test`) | Converted schema (`misron_inventory_test`) |
|---|---|---|
| Stock service, audit, row order and storefront checkout (47 tests) | pass | pass (2 skipped: they need duplicate rows, which the unique key now forbids) |
| Concurrency (13 tests, separate PHP processes) | pass | pass, repeated four times |
| Full application suite (122 tests) | pass except the old `ExampleTest` | pass except the old `ExampleTest` (run on `misron_target_test`: empty data, schema changed by the real SQL file) |

## 8. Recovery plan

| Situation | Action | What is lost |
|---|---|---|
| `ALTER` fails | Nothing: MySQL leaves the original table. Read the error, fix, retry | nothing |
| Change applied, behaviour wrong | Maintenance mode, `inventory_v1_stock_safety_rollback.sql` | nothing: row data is untouched, orders placed since keep their effect |
| Code problem only | Deploy the previous release. The old code runs on InnoDB; it simply loses the new protection | nothing |
| Data damaged (should not happen) | Restore `stock_tables.sql` into a scratch database, compare, then copy only the damaged rows back; the `*_pre_inv1` tables are a second, in-database copy | every order placed after the backup, if the whole table is replaced; prefer a row-level repair |
| Lock waits slow checkout | Find the long transaction in `SHOW ENGINE INNODB STATUS`; for an emergency set `innodb_lock_wait_timeout` lower for the session. Rolling back the engine is the last resort | nothing |

Setting `INVENTORY_FIFO_TRANSACTIONAL=false` in `.env` forces the manual give-back behaviour even on InnoDB. It is for
tests, not a recovery tool.

## 9. MariaDB or older MySQL

- `UPDATE … ORDER BY … LIMIT`, `SELECT … FOR UPDATE`, `LOCK IN SHARE MODE` and `GET_LOCK` are all supported on MariaDB 10.x
  and MySQL 5.7+. Nothing in the service uses JSON columns, window functions, `SKIP LOCKED` or `NOWAIT`.
- On MySQL before 5.7.9 or MariaDB before 10.2 with COMPACT/REDUNDANT row format, an index column may not exceed 767 bytes.
  `product_stocks.variant` (255 × 3 = 765 bytes with utf8mb3) fits; the batch index uses a 100-character prefix for that reason.
- The preflight prints the server flavour and row format and warns when a limit applies.
