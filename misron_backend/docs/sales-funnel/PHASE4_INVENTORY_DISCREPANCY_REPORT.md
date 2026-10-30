# Phase 4 — Inventory discrepancy report

Status: **read-only investigation. No stock quantity, batch or product row was changed.**
Date: 2026-10-23. Database inspected: the local copy of `misron` (MySQL 8.4.3), through a
session set to `READ ONLY`. Re-run on production with the same command:

```
php artisan inventory:audit --database=<name> --preflight --hold --out
```

The command writes CSV and JSON files to `storage/app/inventory-audit/<database>-<time>/`
(`variants.csv`, `products.csv`, `hold_list.csv`, `integrity.json`, `preflight.json` and others).
An SQL-only version of the schema checks is `sqlupdates/inventory_v1_preflight.sql`.

**The numbers below describe the local copy** (121 products, 103 purchases, 32 orders). They show
which mechanisms are at work and how large their effect is there; production will have its own
counts. Section 6 says how to get them.

---

## 1. Headline

| | |
|---|---|
| Products | 121 (116 published, none digital) |
| Stock rows (`product_stocks`) | 3,734, one per product and variation, 3 to 48 variations each |
| Purchase batches (`purchase_products`) | 3,462 (3,461 with units left) |
| Variations where stock = batches | 3,128 of 3,814 compared rows |
| Products where all three records agree | 49 of 121 |
| Products where `products.current_stock` ≠ stock rows | **68 of 121** |
| Products where stock rows ≠ batches | 35 |
| Units on the shelf with no cost batch | 94,260 of 747,360 |
| Units in batches of products that no longer exist | 16,000 |

Products by hold level (section 4): **19 blocked, 14 limited, 4 review, 86 clear.**

The 68 is not one problem. It is the visible edge of **six separate mechanisms** (section 3), two of
which also make the other two records drift.

---

## 2. The three records and which one decides

| Record | Meaning | Who reads it |
|---|---|---|
| `product_stocks.qty` | Units that can be sold, per product and variation. **Authoritative for selling.** | Storefront product page (`stocks->sum('qty')`), cart, `store()` stock check, funnel catalogue, reports (`$product->stocks->sum('qty')`) |
| `purchase_products.remaining_qty` | FIFO cost layers: how many units of each purchase are left and what they cost. **Authoritative for cost.** | `store()` (buying price and profit), stock-adjustment screen, stock valuation reports |
| `products.current_stock` | A cached total. **Not used to decide anything about a sale.** | The two seller API product-detail resources and the product export |

**Is variation-level stock authoritative?** Yes. Every product in the data is a variation product
(`variant_product = 1`) and every order line names an exact variation that matches one stock row. The code
also supports plain products (stock row with `variant = ''`, batches with `NULL` or `''`), but no such
product exists in this copy, so that path is covered by tests rather than by data.

**How FIFO batches are consumed.** A sale takes units from the oldest batch (lowest `id`) of the same
product and variation, then the next, and stores the average cost on the order line. The order line keeps
only the *first* batch's `purchase_id`; which batches supplied the line was never recorded (the
`fifo_batch_info` column exists and nothing wrote to it). The shared stock service now records it.

---

## 3. Why the records differ

Each mechanism lists the evidence found in the data and the code that causes it.

### 3.1 The product form seeds stock without a purchase batch
`ProductService::store` creates a `product_stocks` row per variation with the quantity typed in the form,
and sets `current_stock` to their sum. No purchase batch is created, so those units have no cost.

*Evidence:* **416 stock rows (every one holding exactly 200 units) have no batch at all**, across 19
products. Another **90 rows hold 100 units more than their batch** (typically 200 on the shelf against a
100-unit purchase recorded for a variation the form had already seeded with 200).

### 3.2 Saving a product again deletes and recreates its stock rows
`ProductService::update` (line 613) runs `$product->stocks()->delete()` and creates new rows from the
quantities in the form, whenever a variation product is saved. Everything that happened to those rows
since the product was created is overwritten: purchases received, orders placed, cancellations, adjustments.

*Evidence:* **748 stock rows were created after the first purchase batch of the same variation**, which can
only happen if the row was recreated. In **88** of them the new quantity is lower than the batches, and
those are exactly the 88 variations reported as `batch_excess` (all 200 units short; products 2396 and
2403 are the largest). For product 2403 the stock rows were recreated on 2026-09-17 05:44, two days after
the 400-unit purchase of 2026-09-15, and now say 200 per variation against 400 in the batches.

This is the strongest single cause of drift. The form total is also written to `current_stock`, so
right after an edit `current_stock` and `product_stocks` agree with each other while both have moved away
from the batches.

### 3.3 `current_stock` is not maintained by sales or cancellations
Purchases (`PurchaseController::updateProductStockVariant`), returns and adjustments change
`current_stock`; checkout, customer cancel, admin cancel and POS do not. The total therefore moves
with the stock rows only some of the time.

*Evidence (68 products that differ):* 13 hold `0` (never written), 5 hold exactly
twice the stock rows, 6 hold exactly half, 43 differ by other amounts (for example 8000 against 7991:
nine units sold since the last product save). Because nothing reads `current_stock` to
decide a sale, this mismatch is cosmetic for ordering but misleading in the seller API and exports.

### 3.4 Admin bulk cancel changes the order header only
`OrderController::bulkDeliveryStatusChange` sets `orders.delivery_status = 'cancelled'` and stops. It
does not touch the order lines, the stock rows or the batches.

*Evidence:* **21 cancelled orders still have 22 live (`pending`) lines holding 27 units.** Whether those
units were physically returned cannot be known from the database. The single-order admin status update
(`update_delivery_status`) does restock; it also has its own defects (3.5).

### 3.5 Cancel paths restock differently and some twice
| Path | Stock rows | Batches | Idempotent |
|---|---|---|---|
| Customer cancel, `Api\V2\OrderController::order_cancel` (before this work) | `product_restock` | **not returned** | no |
| Payment-failed cancel, `PaystationController` (before) | `product_restock` | returned to latest batches | no |
| Admin single status → cancelled | `product_restock` per line | `restockPurchaseProductsByPurchaseId`: adds the **full quantity to every batch of the first purchase, with no cap** | no: pressing cancelled twice restocks twice |
| Admin delete order | `product_restock` per line, even if already cancelled | not returned | no |
| Admin **bulk** cancel | nothing | nothing | n/a |

*Evidence:* product 2464, variation S: one batch of 200 shows 181 left (19 units consumed) while live
orders for it hold 4. The other 15 units went to cancelled or deleted orders and were never returned to the batch. The stock
row does not reconcile either (184 against an expected 196), which fits cancelled orders whose lines
were never restocked (3.4).

### 3.6 Deleting a product leaves its batches
`ProductService::destroy` deletes the stock rows but not `purchase_products`.

*Evidence:* **80 batches (16,000 units) belong to products 2391 and 2392, which no longer exist.** They
inflate any valuation that sums batches.

### Is any of this intentional?
Two things are deliberate and documented in the code: costs are tracked in a separate FIFO ledger from
sellable stock, and purchases are meant to update both stock rows and `current_stock`
("Always update the main product's total current stock"). The rest reads as omission rather than design:
sales and cancels skipping `current_stock`, the unbounded batch restock, and the delete-and-recreate on
product save. No comment or setting in the code treats a mismatch as acceptable.

### Can POS, purchases, returns, cancellations and adjustments create inconsistencies?
Yes, each of them. The per-writer analysis, including conflicts with the new locking strategy, is in
[PHASE4_STOCK_WRITERS_AUDIT.md](PHASE4_STOCK_WRITERS_AUDIT.md).

---

## 4. Products that cannot safely be sold until verified

Hold levels are computed per variation and a product takes its worst one.

| Level | Meaning | Effect on the new checkout | Products |
|---|---|---|---|
| **blocked** | Stock rows hold units but **no cost batch exists** (or stock is negative) | Refused with `inventory_unverified`. *Today's checkout refuses them too*, with "Insufficient purchase stock" and an HTTP 500 | 19 |
| **limited** | A cost batch covers only part of the stock | Sells up to the batch quantity, then refuses | 14 |
| **review** | Batches hold *more* than the stock row, or the product is gone | Sells up to the stock row; cost basis needs a decision | 4 |

**19 products are blocked** (15 of them published):

- 2346 Solid Drop Shoulder Oversized Hoodie (27 variations, 5,400 units)
- 2347–2357 the sweatshirt range (11 products, 2,400 to 3,600 units each)
- 2358 Old Money Premium Polo Tshirts (600)
- 2408 Essential Solid Drop Shoulder Oversized Tee (6,400)
- 2445 Luffy Wanted Poster Unisex Dropshoulder Tshirt (8,800)
- 2446, 2449, 2450, 2451 (unpublished, 8,000 to 8,800 each)

**14 are limited:** 2335, 2337–2345 (stock is double the batches, or 800 against 58), 2422, 2436, 2461, 2464.

**4 need review:** 2396 (stock 9,600, batches 19,200), 2403 (7,991 against 15,991), and the orphan batches of
deleted products 2391 and 2392.

The full list with variation counts is `hold_list.csv`. Of the 19 blocked products, **15 are published
today and shown as in stock on the storefront, yet cannot be ordered**.

---

## 5. Reconciliation strategy

**Principle: the audit finds, people decide, every correction is a recorded stock movement.** Nothing is
corrected automatically, merged or overwritten by any tool in this work.

1. **Freeze the cause first.** Stop product-form saves from resetting stock (change
   `ProductService::update` to update rows in place and leave quantities to purchases and
   adjustments), and move purchase, return and adjustment writers to `StockLedger::adjust()`. Until
   then every product edit re-creates the problem.
2. **Count the blocked and limited products physically.** For each, compare the count with the stock
   row.
   - Count matches the stock row: the units are real but uncosted. Record an *opening purchase* through the normal
     Purchase screen at the correct cost, so the batch exists and the books carry the value. Do not edit
     `purchase_products` by hand.
   - Count is lower: post a stock adjustment for the difference.
3. **`batch_excess` (88 variations).** Decide per variation: was the purchase over-recorded (return or
   edit the purchase), or were units lost when the product was re-saved (add them back with an
   adjustment)? The creation timestamps in `variants.csv` show which products were re-saved.
4. **Orphan batches (2391, 2392).** An accounting decision: write the 16,000 units off through a
   stock adjustment or delete them with the purchase record. Not automated.
5. **Cancelled orders with live lines (21 orders).** For each order decide whether the goods came
   back. If so, cancel the lines through the order screen once the admin cancel paths use the stock
   service; if not, it is a loss to record.
6. **Recompute `current_stock` last**, after stock rows are verified, with a reviewed one-off statement
   `current_stock = SUM(product_stocks.qty)`. It is a cache; it carries no information the stock rows lack.
7. **Re-run `inventory:audit`** after each step and keep the before and after CSVs as the sign-off record.
8. **Keep it from coming back:** schedule `inventory:audit --json` nightly and alert on any change in
   `variants_by_status`.

Until step 2 is done, the funnel builder warns at publish time for any product on the hold list (see
the catalogue integration in the Phase 4 report) and checkout refuses the units that have no cost.

---

## 6. Getting production numbers

```
php artisan inventory:audit --preflight --hold --out        # on the production server, read-only
```

or, without artisan, run `sqlupdates/inventory_v1_preflight.sql` in phpMyAdmin and read the comments
beside each query. Both only read. Send `summary.json` and `hold_list.csv`; they contain product ids,
names and quantities and no customer data.

## 7. What this investigation did not establish

- Whether the 21 cancelled orders' goods were physically returned (needs a person).
- Which of the 748 re-created stock rows reflect a deliberate recount rather than an accident.
- Production volumes, and whether production's `purchase_products` is also MyISAM (the local copy came
  from a production dump, so very likely yes; `engine_*` in the preflight confirms it).
