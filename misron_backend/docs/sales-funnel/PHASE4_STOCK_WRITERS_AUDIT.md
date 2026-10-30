# Phase 4 — Audit of every stock writer against the shared locking strategy

Status: written in Phase 4 as an audit. **Phase 5 then moved every writer in sections 2 to 4 except the bulk setup tools
(import, demo data, wholesale and stock-service helpers) onto the shared rules; see `PHASE5_INVENTORY.md` for what changed
and how it was tested.** The table below describes the code as audited, before those changes.

Evidence: a search of `app/` for every write to `product_stocks.qty`, `purchase_products.remaining_qty` and
`products.current_stock`; the code of each writer read in full; and concurrency tests that run a writer's pattern
against the service (`tests/Concurrency`).

---

## 1. The locking strategy the service uses

1. **Every decrease is one conditional statement**: `UPDATE … SET qty = qty - n WHERE … AND qty >= n`. The database
   checks and subtracts in a single step; zero rows changed means "not enough". Batches use the same shape.
2. **Rows are touched in one global order**: lines sorted by product id, then variation. Within a line, stock row
   first, then batches oldest first.
3. **Batch lookups are locking reads** (`SELECT … FOR UPDATE`): a plain read in a REPEATABLE READ transaction keeps
   showing a batch another order already emptied.
4. **Cancels flip the line first**: `UPDATE order_details SET delivery_status='cancelled' WHERE id=? AND status NOT IN
   ('cancelled','delivered')`, and units go back only if that statement changed a row.
5. **Cart owner lock** (`GET_LOCK`) in the storefront checkout stops one cart being ordered twice.

A writer is **compatible** if it changes `product_stocks.qty` or `remaining_qty` in a single atomic statement, never
overwrites a number it read earlier, and does not lock in the opposite order. It **conflicts** if it does any of:

| Code | Conflict | What goes wrong |
|---|---|---|
| **K1** | Read-modify-write: load row, change `qty` in PHP, `save()` | Writes back an absolute number computed from an earlier read, **erasing** any sale or receipt in between. Demonstrated by `test_a_read_modify_write_writer_erases_a_concurrent_sale` |
| **K2** | Walks a list of batches read earlier and writes `remaining_qty` absolutely | Two writers take the same batch units; a batch goes wrong or negative |
| **K3** | No transaction | A failure part-way leaves earlier lines taken |
| **K4** | Restock is not tied to the status change | Cancel twice, restock twice |
| **K5** | Locks batches before stock, or rows in cart order | Can deadlock with the service (InnoDB aborts one; the loser sees an error) |
| **K6** | Transaction over a MyISAM table | `beginTransaction` looks protective; rollback does nothing for that table |
| **K7** | Overwrites with absolute values | Destroys the history the other records agree on |

---

## 2. Writers that reduce stock (sales)

| # | Writer | Stock rows | Batches | Transaction | Verdict |
|---|---|---|---|---|---|
| S1 | `Api\V2\OrderController` storefront checkout | atomic, via ledger | via ledger | yes | **Migrated.** Compatible |
| S2 | Admin `OrderController` create order (~L1239–1410) | `qty -= n; save()` | own FIFO copy: `remaining_qty -= n; save()` | closure transaction | **K1, K2.** Two shoppers (admin and storefront) can both sell the last unit; its batch writes can erase the service's |
| S3 | Admin `OrderController` edit order (L2458–2651, `updateStock` L2874, `updatePreviousStock` L2853) | `qty = qty - n`, `qty += n` via model | `remaining_qty +=/-=` via model | yes | **K1, K2.** Edits erase concurrent sales |
| S4 | Admin delete order line (`OrderController` ~L2925–2976) | `qty += n; save()` | adds back by **name pattern** `product_name LIKE "%(variant)%"`, any variant that matches | yes | **K1, K2**; can return units to a different variation's batch |
| S5 | `PosUtility::orderStore` (POS, three controllers) | `qty -= n; save()` | own FIFO copy | **none** | **K1, K2, K3.** If the order fails on line 3, lines 1 and 2 stay deducted and only the order row is deleted |
| S6 | `StockAdjustmentController::store` | `decrement('qty', n)` **atomic** | per batch from a list read earlier; `decrement` or `update(['remaining_qty' => 0])` | yes | Stock row **compatible** (atomic), but no zero guard (can go negative). **K2** on batches; with no variation posted it takes from batches of **every** variation; `update(0)` is an absolute write |
| S7 | `PurchaseController::storePurchaseReturn` / `updatePurchaseReturn` (L1254, L1769) | `qty -= n; save()` | `remaining_qty -= n; purchase_quantity -= n` via model | yes | **K1.** Guard compares with the batch only, not with the stock row |

## 3. Writers that increase stock

| # | Writer | How | Verdict |
|---|---|---|---|
| R1 | `product_restock()` (`Helpers.php`) and its callers: admin delete order, admin `update_delivery_status`, `PurchaseHistoryController` (web customer cancel), `Seller\OrderController`, `OrderService` | `qty += n; save()`; restocks anything not `delivered`, including an already-cancelled line | **K1, K4.** Admin delete after cancel restocks twice; pressing "cancelled" twice restocks twice |
| R2 | Admin `restockPurchaseProductsByPurchaseId` (L1912) | adds the **full quantity to every** batch of the order's first purchase; no cap at `purchase_quantity` | **K2, K4.** Inflates batches beyond what was bought |
| R3 | `PurchaseController::updateProductStockVariant` (L780) from purchase create, update and delete | `qty += diff; save()` then `current_stock += diff` | **K1.** A receipt erases a concurrent sale. Shown in the concurrency test |
| R4 | `PurchaseController::update` (L468–770) | edits batches and stock under `DB::beginTransaction()` | **K1, K6.** `purchases` and `purchase_products` are MyISAM: a failure leaves them half-edited |
| R5 | `OrderController::bulkDeliveryStatusChange` (admin bulk cancel) | sets the header to cancelled and **stops** | Not a conflict but a **leak**: no restock of stock or batches at all (21 orders in the local data) |
| R6 | Paystation payment-failed cancel; customer API cancel | **Migrated** to `StockLedger::give()` | Compatible |

## 4. Writers that set absolute values

| Writer | Effect | Verdict |
|---|---|---|
| `ProductService::update` (L613) | `$product->stocks()->delete()` then recreate from the form quantities, on every save of a variation product | **K7.** The largest source of drift (748 stock rows in the local data are newer than their batches). Also bypasses any lock: waits for the service's row locks, then overwrites its work |
| `ProductService::store` | seeds stock rows from the form, no batch | Creates uncosted stock (416 rows locally) |
| `ProductStockService`, `WholesaleService` (L113, L253), `ProductsImport`, `DemoController` | absolute `qty = value` | **K7** |
| `ProductService::destroy` | deletes stock rows, leaves batches | Orphan batches (80 locally) |

---

## 5. What the tests show

| Test (`tests/Concurrency`) | Result |
|---|---|
| Last unit among 8 buyers | exactly 1 sale, stock 0, batches 0, 7 plain `out_of_stock` |
| Same products in opposite order (10 orders) | no deadlock, 3 sales, stock 0 |
| 12 buyers over three batches | costs add up to the batches' cost; no batch below zero |
| Failure after taking stock (6 workers) | stock and batches of failed orders restored |
| Cancel one line from 6 processes | stock increased once |
| 5 taps on "place order" | one order, one deduction |
| Storefront vs a funnel-style checkout for the last unit | exactly one wins |
| 24 shoppers × 4 products, random mixes | stock and batches fell by exactly the units sold |
| **A read-modify-write purchase receipt racing a sale** | **the sale vanishes from `product_stocks` (15 instead of 14) while the batch lost it (9)** |
| `StockLedger::adjust()` racing a sale | both effects kept (14) |
| Old storefront logic, last unit | both buyers told yes (the defect the service replaces) |

---

## 6. Recommended changes, in order of value

None of these is done; each is a mechanical swap onto code that already exists and is tested.

1. **Receipts, returns and edits (R3, R4, S7)** → `StockLedger::adjust($productId, $variant, ±n)` for the stock row, and a
   conditional `UPDATE` for the batch. Removes K1 from the most frequent non-sale writer.
2. **Admin create order (S2) and POS (S5)** → `StockLedger::take()` inside one transaction, exactly as the storefront now
   does. POS gains the transaction it lacks.
3. **Every cancel path (R1, R2, R5, S4)** → `StockLedger::give()`: one idempotent step that returns the *recorded* batches,
   capped at what was bought. Bulk cancel gets a restock it never had.
4. **Product form (`ProductService::update`)** → update stock rows in place and leave quantities to purchases and adjustments.
   This stops new drift at its source.
5. **Stock adjustments (S6)** → require a variation, refuse to go below zero, use conditional updates.
6. **Retry on deadlock:** the checkout currently reports a deadlock as a failed order. Writers that lock in a different
   order (purchase edit: batches then stock) make this possible but rare; a single retry in `store()` removes it.

Until 1 to 3 are done, **the service protects every order placed through the storefront and funnel checkout, but not
against a purchase, POS sale or admin edit that lands at the same instant**. The window is small (milliseconds per
write), the effect is the lost update shown above, and `inventory:audit` run nightly will show it as a changed status.
