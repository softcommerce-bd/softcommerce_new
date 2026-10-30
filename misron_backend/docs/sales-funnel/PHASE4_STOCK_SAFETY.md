# Phase 4 — Stock safety: audit and design

> **Update 2026-10-23 — implemented.** You approved the design with conditions (conversion to InnoDB
> in isolated test databases only; a read-only discrepancy report; a shared stock service for storefront
> and funnel checkout). What was built, and the evidence, is in:
> [PHASE4_INVENTORY_DISCREPANCY_REPORT.md](PHASE4_INVENTORY_DISCREPANCY_REPORT.md),
> [PHASE4_INVENTORY_MIGRATION_PLAN.md](PHASE4_INVENTORY_MIGRATION_PLAN.md) and
> [PHASE4_STOCK_WRITERS_AUDIT.md](PHASE4_STOCK_WRITERS_AUDIT.md). The text below is the original audit
> and design, kept as written; section 4.1 became `App\Services\Inventory\StockLedger`. Two details
> changed in implementation: batches are read with `SELECT … FOR UPDATE` (a plain read goes stale under
> REPEATABLE READ), and the storefront checkout also takes a per-cart `GET_LOCK` against double submits.
> The main `misron` database has not been altered.

Status of the original text: audit and design only. **No stock code had been changed at that point.**
`Api\V2\OrderController::store` and every other mutation path below were
untouched, as agreed. Implementation, concurrency tests and the funnel
checkout land together in Phase 4, after your approval of the points marked
**Needs approval**.

Audit date: 2026-10-23. Evidence: a code search of `app/` for every write to
`product_stocks.qty`, `products.current_stock` and `purchase_products.remaining_qty`,
plus read-only queries against the development database.

---

## 1. How stock is recorded today

Three records describe the same inventory and are written independently:

| Record | Meaning | Table engine |
|---|---|---|
| `product_stocks.qty` | What can be sold, per product and variation | InnoDB |
| `purchase_products.remaining_qty` | FIFO cost batches (what each unit cost) | **MyISAM** |
| `products.current_stock` | Cached total shown in some lists | InnoDB |

Findings from the development database (read-only):

- `purchase_products` is **MyISAM**: no transactions, no row locks, no rollback.
- `product_stocks` has **no index** besides the primary key: no unique
  `(product_id, variant)` and no index on `product_id`. Currently there are no
  duplicate rows and no negative quantities.
- `products.current_stock` differs from the sum of `product_stocks.qty` on
  **68 of 121 products**: order placement never updates it (only purchases and
  stock adjustments do). It is a cache nobody keeps correct, so Phase 4 must not
  rely on it.
- Isolation level is REPEATABLE READ, which does not stop two transactions from
  both reading the same quantity and both subtracting.

## 2. Every path that changes stock

**Sells (decrease)**

| # | Where | product_stocks | FIFO batches | Transaction | Guard |
|---|---|---|---|---|---|
| S1 | `Api\V2\OrderController::store` (storefront/app checkout) | `$stock->qty -= n; save()` from an earlier read | `consumePurchaseProductQtyFIFO` | `DB::beginTransaction` | `n > $stock->qty` check on the same stale read |
| S2 | `OrderController` (admin) create, ~L1285 | same read-modify-write | `consumePurchaseProductQtyFIFO` (own copy) | closure transaction | same stale check |
| S3 | `OrderController` (admin) edit order, L2515–2672, `updateStock` L2878 | `$stock->qty = $stock->qty - $q` | FIFO consume for changed lines | not verified | none seen |
| S4 | `PosUtility` POS sale, ~L797 | read-modify-write | own copy of FIFO consume | not verified | not verified |
| S5 | `StockAdjustmentController` L221–248 | `decrement('qty', n)` (atomic SQL) | `decrement('remaining_qty')` | yes | none (can go negative) |
| S6 | `PurchaseController` returns/edits L1321–1949 | `$stock->qty -= n` | `remaining_qty -= n` | not verified | none seen |

**Restocks (increase)**

| # | Where | Notes |
|---|---|---|
| R1 | `product_restock()` helper (`Helpers.php` L1598) | `qty += n`; called on cancel from `OrderService`, `Api\V2\OrderController::order_cancel`, `PaystationController`, `PurchaseHistoryController`, `Seller\OrderController`, admin `OrderController` (4 sites). Guarded only by "not already delivered/cancelled" read from the order row. |
| R2 | FIFO returns: `PaystationController::return_purchase_qty`, admin `OrderController` L1504, 1937, 1984, 2717, 2747, 2958 | `remaining_qty += n` by loading batches and saving |
| R3 | `PurchaseController` L798–835, 1801–1821 | purchase create/edit: `qty += diff` |
| R4 | `OrderController` L2853 `updatePreviousStock`, L2969 | order edit/delete |

Writers that set absolute values (product forms, bulk import, wholesale):
`ProductStockService`, `ProductService`, `ProductsImport`, `WholesaleService`,
`DemoController`. Cart add only reads stock (`CartController` L237).

The FIFO routine is copied into three files (`ApiV2OrderController`,
admin `OrderController`, `PosUtility`), so a fix in one does not reach the others.

## 3. Race conditions and consistency gaps

1. **Oversell on the last unit (S1, S2, S4).** Both requests read `qty = 1`, both
   pass the check, both write `0` (the second overwrites rather than failing).
   Two orders, one unit. No `lockForUpdate`, no conditional update.
2. **Lost update.** `save()` writes the absolute value computed from the earlier
   read, so a concurrent decrement or purchase receipt between read and write is
   silently erased. This also corrupts restocks (R1, R3).
3. **FIFO is unprotected and cannot roll back.** The consume routine reads
   batches, then writes `remaining_qty` from the stale read, on MyISAM. Two orders
   can take the same batch units (wrong costing, over-consumed batches). If an
   order fails *after* FIFO ran (exception, validation, stock row missing),
   `DB::rollBack()` restores `product_stocks` but **cannot** restore
   `purchase_products`: batches stay consumed. Over time `product_stocks` and the
   batches drift apart.
4. **Two ledgers, one failure mode.** FIFO throws "Insufficient purchase stock"
   when batches are empty even if `product_stocks.qty > 0`. Any drift (3 above,
   manual edits, imports) turns into refused orders.
5. **Double restock.** Cancel paths check order status and then restock without a
   lock; two cancels (admin click plus customer, or a gateway retry) can both pass
   the check and add the units twice.
6. **Duplicate placement.** No idempotency key anywhere: a double tap on "Place
   order" creates two orders and deducts twice.
7. **Negative stock is possible** through S3, S5, S6 (no guard).
8. **Variation key handling is inconsistent:** `variant = ''` for plain products
   in `product_stocks`, but FIFO queries use `whereNull('variant')`.
9. **Lock ordering:** an order with several lines locks rows in cart order; two
   orders with the same products in a different order would deadlock under any
   naive `lockForUpdate` fix.

## 4. Design

### 4.1 One stock ledger service, used by every seller

`App\Services\Inventory\StockLedger`, called from inside the caller's
transaction. Storefront orders and funnel orders call the **same** methods, so
there is one set of rules and one place to test.

```
take(array $lines): array   // [['product_id', 'variant', 'quantity'], …] → ['costs' => per line, 'batches' => …]
give(OrderDetail $line): void   // restock + batch return, idempotent
```

`take()`:

1. Merge identical (product, variant) lines, then **sort by product_id, variant**
   and process in that order: every transaction locks in the same order, so
   no deadlock between orders.
2. For each line, in one statement:
   `UPDATE product_stocks SET qty = qty - :n WHERE product_id = :p AND variant = :v AND qty >= :n`.
   Zero rows affected means not enough stock: throw `OutOfStock` carrying the
   available quantity (read afterwards). No read-then-write window, no negative
   stock, no lost update. InnoDB takes the row lock and holds it to commit.
3. FIFO, same pattern per batch:
   `UPDATE purchase_products SET remaining_qty = remaining_qty - :t WHERE id = :b AND remaining_qty >= :t`,
   walking batches oldest first. A failed conditional update re-reads and
   continues with the next batch. Returns the same batch list and total cost the
   current routine returns, so `order_details` columns are filled identically.
4. The result is exactly what `store()` computes today (selling price, cost,
   profit); order totals, tax, coupon, shipping and payment code do not change.

`give()` (cancel, return): in one transaction, flip the line to cancelled with
`UPDATE order_details SET delivery_status='cancelled' WHERE id=? AND delivery_status NOT IN ('cancelled','delivered')`
and restock **only if one row changed**. A second cancel finds zero rows and does
nothing. Restock uses `qty = qty + n` (atomic), and batch returns are capped by
`purchase_quantity`, as `PaystationController::return_purchase_qty` already does.

### 4.2 Transaction boundaries

- One database transaction covers order rows, order details, `StockLedger::take`,
  coupon usage and cart clearing. Failure anywhere rolls the whole thing back.
- **The MyISAM problem decides how safe this can be:**
  - **Needs approval — convert `purchase_products` to InnoDB**
    (`ALTER TABLE purchase_products ENGINE=InnoDB`). Then batches roll back with
    the order and row locks work. Cost: a table rebuild (size of the table is small
    relative to orders; run in a quiet minute), and a backup first. This is the
    clean fix.
  - *If conversion is refused:* guard FIFO with a MySQL named lock per
    (product, variant) (`GET_LOCK`) and keep a compensation list: on any failure
    after `take()`, put the consumed batch units back explicitly. Works, but
    leaves a window if PHP dies mid-request. Documented as the weaker option.
- **Needs approval — unique index** `product_stocks (product_id, variant)` (after
  a duplicate check, which is clean today) and a non-unique index on
  `purchase_products (product_id, variant)`. Both speed up and harden the
  updates above.

### 4.3 Idempotent funnel checkout

Table `funnel_checkout_attempts` (planned in Phase 1): unique `idempotency_key`
(client-generated at page load of the form), request hash, status
(`started|placed|failed`), `combined_order_id`. Flow: insert the key (unique
violation means a retry), place the order, store the result. A repeat returns the
stored result and never calls `take()` again.

### 4.4 What changes in existing code (Phase 4, with tests)

Only inside `Api\V2\OrderController::store` and the admin create path: replace
the stock check + decrement + `consumePurchaseProductQtyFIFO` call with
`StockLedger::take()` and keep everything else. Cancel paths call `give()`.
POS, purchases and adjustments are **not** touched in Phase 4 (listed in section
2 for a later, separate change); they keep working because they write the same
columns.

`products.current_stock` is out of scope: it is already wrong on 68 products and
nothing reads it for ordering. Phase 4 will not make it worse; a one-off
reconciliation command is proposed separately.

## 5. Concurrency tests prepared for Phase 4

PHPUnit runs one process, so true races need workers. Plan: a small harness
(`tests/Concurrency/`) that starts N PHP processes with `proc_open`, releases them
together (a file barrier), and each calls `StockLedger::take()` / the funnel
checkout service against `misron_test` (a real MySQL, InnoDB, same engines as
production incl. MyISAM until approved). Assertions are about the end state.

| Test | Setup | Expectation |
|---|---|---|
| Last unit | qty 1, 2 and then 8 workers buy 1 | exactly 1 order, qty 0, no negative stock, others get `OutOfStock` |
| Last unit, variations | variant L qty 1, workers buy L and M | one L sale; M unaffected |
| Multi-line orders, opposite order | two orders with products A and B in reverse order, qty 1 each | no deadlock; both succeed or one fails cleanly; totals consistent |
| FIFO consistency | three batches (2, 3, 5 left), parallel buys totalling 9 | Σ`remaining_qty` decreased by exactly the units sold; each batch ≥ 0; cost per order = sum of its batches |
| Failure rolls back everything | order fails after `take()` (forced exception) | qty and every batch unchanged (needs InnoDB or compensation) |
| Cancel twice | cancel the same order from 5 workers | stock increased once |
| Duplicate submission | 5 workers, same idempotency key | one order, one deduction, all callers get the same result |
| Mixed channels | storefront `store()` and funnel checkout racing for the last unit | one wins; same ledger code path |
| Characterization (today) | run the "last unit" scenario against the current `store()` logic | documents the oversell that exists now, then flips to pass after Phase 4 |

## 6. Decisions needed before Phase 4

1. Convert `purchase_products` to InnoDB (recommended) — or accept the
   named-lock/compensation fallback.
2. Add the two indexes above.
3. Confirm the Phase 4 change surface in `store()` (section 4.4) and that POS,
   purchases and adjustments stay as they are for now.
