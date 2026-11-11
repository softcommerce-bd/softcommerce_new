# MYSQL84.2 - generated column `purchase_order_details.pending_quantity`: fix and verification

**Owner decision MYSQL84.2 (approved, limited scope):** remove the invalid writes to the generated column in `PurchaseOrderService` and `GoodsReceiptService`; review the cancellation write and remove it if equally invalid; no schema change, no financial-rule change; regression tests; run on MariaDB and MySQL 8.4 (disposable databases only). **T-5 and staging acceptance remain OPEN.** Nothing was merged, deployed or enabled; production and staging were not accessed; no financial history was touched.

## 1. The defect (from `T5_MYSQL84_REHEARSAL_REPORT.md`, finding F1)
`pending_quantity` is `GENERATED ALWAYS AS (purchase_quantity - recieved_quantity) VIRTUAL`. MySQL 8.4 rejects any explicit value (error 3105) whatever the sql_mode; MariaDB ignores it silently only because the app runs non-strict (in strict mode it errors 1906). On 8.4 the writes broke purchase-order creation, receiving, bulk commit and the requirements screen. It is the only truly generated column in the schema.

## 2. Exact changes (`docs/evidence/mysql84_fix/code_diff.patch`; 2 files, 4 lines of logic)
| File | Before | After |
|---|---|---|
| `PurchaseOrderService::create` (insert of each order line) | `'recieved_quantity' => 0, 'pending_quantity' => $r['qty'], ...` | `pending_quantity` removed; the database computes it (= ordered quantity at creation) |
| `PurchaseOrderService::cancel` (line 83) | `update(['pending_quantity' => 0, 'remaining_quantity' => 0, ...])` | `update(['remaining_quantity' => 0, ...])` |
| `GoodsReceiptService::receive` (line 144) | `update(['recieved_quantity' => ..., 'pending_quantity' => $pending, 'remaining_quantity' => $pending, ...])` | `pending_quantity` removed; `recieved_quantity` and `remaining_quantity` still written; the generated value follows |
Comments at each site say why. The local variable `$pending` (pending before this receipt minus this receipt) is kept: it is still the value of the real column `remaining_quantity`.

### Review of the cancellation write (item 2)
It was **equally invalid** and has been removed. Evidence that removing it changes nothing in behaviour:
* On MariaDB the old write was a silent no-op, so `pending_quantity` after a cancel has always been `ordered - received`, never 0. The new test pins exactly that and passes with both the old and the new code on MariaDB (only the static "no code writes it" check fails on the old code - `docs/evidence/mysql84_fix/old_code_on_mariadb.txt`).
* Every consumer of the quantity filters by order status and so already ignores a cancelled order: `StockBalance::incoming` and the dashboard card `awaiting_receipt` (`status IN ('ordered','partially_received')`), and `GoodsReceiptService::receive` (refuses any order not in those statuses). A cancelled order has status `cancelled`; a part-received one that is cancelled becomes `received`. Neither is counted anywhere.
* `remaining_quantity = 0` on cancel is a real column and is kept.
* Not changed (observed, for the record): a cancelled order's lines still show `ordered - received` as "pending" in the raw column, as they always did; no screen displays it for a closed order.

## 3. Regression tests (`tests/Feature/Inventory/GeneratedQuantityTest.php`, 6 tests, 38 assertions)
1. the column is generated in the schema, and **no `'pending_quantity' =>` write exists anywhere in `app/`** (static guard, fails on the old code);
2. create: pending = ordered, remaining = ordered, received 0, for two products; incoming correct;
3. partial then full receipt: (10,4,6,6) -> over-receipt refused with nothing changed -> (10,10,0,0), order `received`, incoming 0;
4. cancel an unreceived order: status `cancelled`, (8,0,8,0), incoming 0, no journal entry, dashboard and receiving list render and do not list it, receiving against it refused, a second cancel refused;
5. cancel after a partial receipt: status `received`, (10,4,6,0), stock 4 kept, incoming 0, cost layers and goods receipts untouched;
6. bulk commit and the requirements screen -> purchase order create correct generated values; the receiving form allows at most the pending quantity.
(Figures are purchase, received, pending, remaining.)

## 4. Results
| Check | MariaDB 10.11.14 (`inv_v2_test`) | MySQL 8.4.13 (disposable, built from the dump + migrations) |
|---|---|---|
| `GeneratedQuantityTest` | 6/6 OK | 6/6 OK |
| Purchase-order / goods-receipt / requirements / bulk / screens tests (`--filter 'Purchas|GoodsReceipt|GeneratedQuantity|OrderDetailsScreen|Requirement'`) | 69 tests, 490 assertions OK | 69 tests, 490 assertions OK (after the test-data alignment in section 5; 9 failed before it, all accounting tests that expect zero starting balances) |
| Default suite | 641 tests, 4187 assertions, only the 3 known unrelated failures | 641 tests, 4187 assertions, **only the same 3 known failures** (before the fix on 8.4: 31 failures, 8 of them this defect; after the fix and before alignment: 21 = exactly the MariaDB control built the same way) |
| Concurrency suite | 96 tests OK | 95 of 96 on the last run; see section 6 |
| Fingerprint / row counts | identical before/after | identical except the intentional alignment edits and 1 `translations` row |
Evidence: `docs/evidence/mysql84_fix/` (`comparison.txt` lists the 10 tests fixed on 8.4 and shows 0 newly failing).

## 5. Test-data alignment on the disposable 8.4 copy (stated so nobody mistakes it for a code change)
The database built from the repository dump carries 61 chart-of-accounts rows with real-looking balances (about 294 million in total) and 5 funnel rows, whereas `inv_v2_test` has 13 zero-balance accounts and no funnels; the accounting and funnel tests assert absolute values from that clean state, so they fail on **any** freshly built database, MariaDB included (finding F5). On the disposable 8.4 copy only, `chart_of_accounts` was replaced by the 13 rows of `inv_v2_test` and the 5 funnel rows (and children) deleted (`my84_seed_alignment.txt`). No production, staging or historical data was touched; the whole copy was destroyed afterwards (`teardown.txt`).

## 6. Open item found while running (not caused by this change) - CLOSED by OUTBOX.1, see `T5_OUTBOX_TEST_HARDENING.md`
`OutboxVerificationTest::test_a_killed_worker_does_not_block_the_other_workers` is timing-dependent on MySQL 8.4: it failed in 3 of 12 isolated runs on 8.4 and 0 of 12 on MariaDB (`outbox_kill_flakiness.txt`), and once in the last concurrency run; it had passed in the earlier three 8.4 concurrency runs. Failure mode: "the first worker was killed" is false - the worker meant to be killed finished without reaching the kill point, most likely because the two other workers drained the 12-entry backlog first. It tests the outbox processor, not purchasing, and is unrelated to `pending_quantity`. It needs hardening (a larger backlog or a start barrier) - not done here, out of scope.

## 7. Not shown
Production compatibility (version/data unknown, P1 BLOCKED); other MySQL versions; the UI beyond the rendered pages in the tests. Nothing else writes a generated column (checked in `information_schema`; the timestamp columns only carry defaults).
