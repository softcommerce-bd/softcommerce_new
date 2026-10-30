# Phase 5 — Inventory: every stock writer on one set of rules

Status: implemented and tested on the test databases (original MyISAM schema and converted InnoDB schema).
Nothing is applied to the main `misron` database. The existing inventory records are dummy data and were not
reconciled; production-readiness here is judged on clean data built in the test databases.

Phase 4 moved the storefront and funnel checkout onto `StockLedger` and audited the other writers
(`PHASE4_STOCK_WRITERS_AUDIT.md`). Phase 5 moves the rest, as agreed, without rebuilding the inventory module.

## 1. The rules every writer now follows

1. A change to a quantity is **one SQL statement** (`qty = qty + n`, `remaining_qty = remaining_qty - n WHERE remaining_qty >= n`),
   never "read the number, change it in PHP, save it". A sale, receipt or adjustment made in between is never erased.
2. Nothing goes below zero; a refusal changes nothing and says which product and variation.
3. The **shelf (`product_stocks`) moves first, the batches (`purchase_products`) last.** The shelf is InnoDB and
   rolls back; the batches are MyISAM until converted. Writing them last means a refusal never leaves a half-done change.
   Batch units a failed request already consumed are given back by hand (`StockLedger::release`) while the table is MyISAM.
4. A cancel returns units **once**: the line is flipped to `cancelled` and the units go back only if that flip changed a row.
5. FIFO batches are read with locking reads and always in batch id order; rows are locked in one global order (product,
   variation), so two writers cannot deadlock each other.

## 2. What changed, writer by writer

| Writer (audit id) | Before | Now |
|---|---|---|
| Storefront checkout, funnel checkout, customer cancel, Paystation cancel (S1, R6) | — | already on `StockLedger` (Phase 4) |
| **Admin/web order create** `OrderController::store` (S2) | read-modify-write; its own FIFO copy; an out-of-stock refusal crashed with an unhandled exception | whole cart taken through `StockLedger::take()` in the order's transaction; refusal names the product and redirects to the cart; failure gives batches back; line stores `fifo_batch_info` |
| **Order edit** `orderEditUpdate` (S3) | `qty -= n; save()`, batch `+=/-=` through models, batch shortage silently costed at 0 | the five primitives (`updateStock`, `consumePurchaseProductQtyFIFO`, `returnPurchaseProductQtyFIFO`, `handleRemainingReturnQty`, `updatePreviousStock`) call `adjust` / `consumeBatches` / `returnBatchUnits`; a shortage is an error shown to the admin and the edit changes nothing |
| **Delete an order line** `orderEditDelete` (S4) | returned units to batches matched by a **name pattern** (could be another variation) and restocked even a cancelled line | `StockLedger::give()`: the batches the line came from, once, same variation |
| **POS** `PosUtility::orderStore` (S5) | no transaction; an error on line 3 left lines 1 and 2 deducted | one transaction; whole sale taken through `take()`; refusal names the product; failure puts everything back; batches recorded for later cancel |
| **Stock adjustment** (S6) | batches of *every* variation drained when no variation was posted; `update(['remaining_qty' => 0])`; deleting an adjustment never restored the shelf | one variation (by row id or name, ambiguity refused); shelf and batches in single statements; whole-number quantities; refuses beyond the shelf; **delete restores the shelf and the right batches** |
| **Purchase return** create / edit (S7) | `qty -= n; save()`; edit adjusted only `purchase_quantity`, leaving `remaining_qty` wrong | batch and shelf lose / regain units in single statements; edit undoes the old return then applies the new one (no stacking) |
| **Purchase receive** (R3) | `qty += n; save()` | `adjustRow` (atomic); a stock row is created with `''` not NULL (the column is NOT NULL: the old create crashed) |
| **Purchase edit** (R4) | batch overwritten from numbers read earlier | `resizeBatch`: `purchase_quantity` and `remaining_qty` move by the same difference in one statement; "below what was sold" is checked by the database |
| **Purchase delete** | restocked/removed regardless of sales | refused when any of its units was already sold |
| **All admin / seller / customer cancel screens** (R1, R5) | `product_restock()` added units to the shelf only, again on every press; admin cancel added the *full quantity to every batch* of the first purchase; bulk cancel restocked nothing | `set_order_line_status()` / `product_restock()` → `StockLedger::give()`: shelf and original batches, once. Bulk cancel now restocks. Admin delete of an order restocks only lines not already cancelled. Delivered → cancelled by an admin restocks (the goods return); delivered lines are kept out when an order is merely deleted |
| **Product form** `ProductService::update` (K7) | deleted and recreated every stock row with the quantities typed in the form on every save | `syncStockRows()`: rows keep id and quantity; price, SKU, picture update; new variations get their typed opening quantity; a removed variation goes only if its shelf is empty; `current_stock` = real total. The admin is told when a typed quantity was not applied |

Behaviour changes an admin will notice (all deliberate, all refusals with a clear message):

* A purchase from which units were sold cannot be deleted, and cannot be reduced below what was sold.
* Writing off or returning more than is on the shelf is refused.
* A stock adjustment on a product with several variations must say which one.
* Quantities typed in the product form no longer overwrite the stock of existing variations; use a purchase or an adjustment.
* Bulk-cancelling orders now returns their stock.

Left as they were, on purpose: `ProductsImport`, `DemoController`, `WholesaleService`, `ProductStockService`
(bulk setup tools that set absolute opening values), and `ProductService::store` (opening quantities typed when a product
is created have no cost batch; the funnel and storefront checkouts refuse such stock with a clear reason until a purchase
records its cost). Deleting a product still leaves its purchase batches (orphans); the inventory audit reports them.

## 3. New code

* `StockLedger`: `give($line, $includeDelivered)`, `consumeBatches()`, `returnBatchUnits()`, `adjustRow()`, `resizeBatch()`;
  `returnBatches` now also places units the recorded batches cannot take (an edited line) in the newest batches with room.
* `set_order_line_status()` helper; `product_restock()` delegates to `give()`.
* `ProductService::syncStockRows()`.

## 4. Tests

| Suite | Covers | Result |
|---|---|---|
| `CancelPathsTest` (11) | admin screen (twice), delivered → cancelled, bulk cancel (twice), delete open / cancelled / delivered order, a failing restock never blocks deletion, customer web cancel, old `product_restock` | pass |
| `PosSaleTest` (7) | sale moves stock and batches, refusal names product and leaves cart, uncosted stock, failure after take restores everything, last unit, cancel of a POS sale, digital | pass |
| `AdminCreateOrderTest` (4) | takes stock, refusal, failure restores, uncosted | pass |
| `OrderEditTest` (9) | add / reduce units, impossible edit changes nothing, batch shortage, removed line, variation change, cancel after edit, delete line (other variation untouched, once) | pass |
| `PurchaseStockTest` (8) | receive, no stock row, sale during an edit is kept, cannot reduce below sold, delete rules, return to supplier, oversized return refused, edit a return | pass |
| `StockAdjustmentTest` (7) | one variation, by name, beyond shelf refused, uncosted refused, ambiguity, whole numbers, delete restores | pass |
| `ProductFormStockTest` (6) | rows keep id and quantity, new variation, removed variation, plain product | pass |
| `StockConcurrencyTest` (22 total, 6 new) | receipt vs sale, six receipts, order-edit vs sale, batch resize vs sale, resize never below zero, six admin screens cancelling one line — real processes, released together | pass on the converted schema |
| Full PHPUnit | 275 tests on the original schema and on the converted schema | only the documented `ExampleTest` failure |

## 5. Data tools (test databases)

* `php artisan inventory:sample-data --database=<name>_test` — a clean, consistent catalogue: plain products, two
  products with four variations, a discount, a product with two batches at different costs (FIFO), a last unit, a sold-out
  product, delivery areas and the accounts the screens post to. Stock rows, batches and totals agree exactly
  (`inventory:audit` reports every product `ok`). Refuses a database not ending in `_test`.
* `php artisan inventory:reset-dummy-data --database=<name>` — clears orders, purchases, returns, adjustments, carts and ledger
  logs (scope `transactions`) or also the products (scope `catalogue`); resets every shelf to 0, accounts to their opening
  balances. **Dry run unless `--execute --confirm=<database name>`**; refuses a name not ending `_test` unless
  `--allow-non-test`; refuses when orders paid online exist; prints what the database holds first so the operator can
  see it is disposable. Never touches table structures, users, roles, permissions, settings, languages, currencies,
  categories, brands, uploads, the chart of accounts itself, or any funnel table.
  **Not run against `misron`. Doing so needs the owner's separate written go-ahead.**

## 6. Applying to the main database (not done)

Order, each after a backup, only when the owner says so (final integration):

1. `sqlupdates/inventory_v1_preflight.sql` (read-only check) → `inventory_v1_stock_safety.sql` (InnoDB for the purchase tables, stock indexes; rehearsed with identical checksums, rollback script included).
2. `sqlupdates/sales_funnel_v2_orders.sql`, `sales_funnel_v3_checkout.sql`, `sales_funnel_v4_publishing.sql`.
3. Optionally `inventory:reset-dummy-data` then enter real opening stock through purchases.
4. Set `FUNNEL_CHECKOUT_ENABLED=true` last.

The code is safe to deploy before the SQL (the new admin features check for the columns); checkout stays off until step 4.
