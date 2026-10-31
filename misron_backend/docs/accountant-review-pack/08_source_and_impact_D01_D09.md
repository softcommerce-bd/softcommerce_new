# Source and impact of D-01 to D-09

How to read this: **PROVEN** = reproduced by a query on the data (files 08-12 are the evidence). **INFERRED** = a pattern consistent with the data, not provable from it. **UNKNOWN** = the data cannot say; the accountant or owner must. Nothing here changes any figure.

## Context that affects every item
* The statement log starts on **2026-09-13** and holds 105 rows up to 2026-09-16. Accounts last updated before that date but still carrying a balance: Courier Settlement Expense (353.95, last touched 2026-07-29), Courier Income (52.20, last touched 2026-07-14) (PROVEN). The courier-settlement table, by contrast, holds 24 records dated 2026-06-23 to 2026-08-20. So the accounting history before mid-September 2026 is not in the statement log, while some of its leftovers are still in the balances.
* 3,715 of 3,734 stock rows hold exactly 200 units (PROVEN). Real counted stock does not look like that; this is the product form's default, i.e. **opening quantities were typed in, not counted** (INFERRED).
* All 103 purchases are dated 13-16 Sep 2026, none has any payment, and every sale-side balance (sales, receivable, cash, COGS) is 0.00 (PROVEN).

## D-01 Trial balance out of balance by 301.75
* **Source (PROVEN for the amounts, INFERRED for the cause):** the whole difference is the two courier accounts. Expense *Courier Settlement Expense* 353.95 and income *Courier Income* 52.20: expense minus income = 301.75 = the difference. Their counter-entries (cash, receivable, sales, COGS) no longer exist because those accounts are 0.00. The settlement table shows 23 settled courier settlements (gross 37,354.96, settlement fees 358.10); 16 of them point at orders that no longer exist in `orders` (file 12). The balances look like leftovers of that earlier activity after the other accounts and the statement rows were reset. The exact derivation of 353.95 / 52.20 from the settlement fees cannot be reproduced (fees total 358.10).
* **Impact:** the ledger cannot balance until these two balances are explained. Amount is small (301.75) but it proves the books are not double-entry consistent. The Balance Sheet now shows it on its own line labelled UNRECONCILED DIFFERENCE.
* **Not done:** no entry, no write-off, no reset of these balances.

## D-02 Balance Sheet "Retained Earnings"
* **Source (PROVEN, from the code):** the old report computed Retained Earnings as assets - liabilities - equity - current-year earnings, i.e. whatever made the page balance. With no real retained-earnings account, it hid D-01 inside equity.
* **Impact:** a balanced-looking sheet that was not balanced. **Fixed in the report (display only):** the line is now named *UNRECONCILED DIFFERENCE (ledger out of balance, not retained earnings)* with a red banner; the trial balance has its own banner. The amount is unchanged (301.75) and still not corrected in the books.

## D-03 P&L shows 0.00 while income / expense accounts have balances
* **Source (PROVEN):** the P&L sums *account statement rows* in the period; the two courier balances have no rows (all 105 rows are Sept 2026 purchase/payable rows, none on these accounts).
* **Impact:** 52.20 income and 353.95 expense are missing from the P&L, and there is no sales revenue or COGS at all (no booked sales; 25 of the 32 orders are cancelled, 2 confirmed, 5 pending), so **no profit can be reported from this data**. The P&L screen now lists, per account, the balance that rows do not explain.

## D-04 Inventory account 147,089,280.00 vs FIFO layers 147,078,025.00 (difference 11,255.00)
* **Source (PROVEN):** the difference equals exactly the cost of units already consumed from layers: 48 units across 17 layers = 11,255.00 (file 11). The Inventory account equals the total cost of all purchases (147,089,280.00); it was never credited when those units left.
* **What the data shows (PROVEN):** for most of the consumed variants the stock row was lowered by the same amount (e.g. stock 199 / layer remaining 199), so the units left the shelf; a few rows disagree (file 11). **What is unknown:** which sales consumed them. No remaining order carries FIFO batch information. 24 lines (29 units) on CANCELLED orders are for the same product/variant as consumed layers, which suggests the units belonged to orders that were later cancelled without the layers being given back (INFERRED; the cancel path in the old code did not return units to cost layers before this project fixed it).
* **Impact:** if those 48 units were sold, Inventory is overstated by 11,255.00 and revenue/COGS are unrecorded; if they were returned to the shelf, the layers are understated. Either way the amount is small against the total.

## D-05 94,260 units on the shelf with no cost
* **Source (PROVEN):** two groups (file 09): **83,200 units in variant rows that have no cost layer at all** (file 10 lists them by product) and **11,060 units in rows where stock exceeds the layers**. All the no-layer rows hold exactly 200 units: they were created by the product form's default stock, never by a purchase (INFERRED).
* **Impact:** these units have no cost, so they are not part of the FIFO valuation and the Inventory account does not include them. If they are real stock their cost is unknown (nothing has been invented); if they are placeholders, stock is overstated by 94,260 units and cannot be sold from without producing "uncosted" sales that are excluded from profit. Another group (file 09 class 3) is the reverse: layers exceed stock by 17,600 units.
* **Decision needed:** per product, either supply cost evidence (opening-cost template) or declare the quantity a placeholder to be corrected by a stock count. Neither has been done.

## D-06 Orphan cost layers: 2 purchases, 16,000 units, BDT 3,200,000.00
* **Source (PROVEN):** purchase 41 (Ref-1040, 2026-09-15) supplier 2: 40 layers x 200 units = 8,000 units, BDT 1,520,000.00, product id 2391 no longer exists, still unpaid (1520000); purchase 42 (Ref-1041, 2026-09-15) supplier 2: 40 layers x 200 units = 8,000 units, BDT 1,680,000.00, product id 2392 no longer exists, still unpaid (1680000) (file 08). The two product ids are missing from the middle of the catalogue's id range (2335-2464), so the products were deleted after being purchased (INFERRED); the purchases, their layers and the payable stayed (PROVEN).
* **Impact:** BDT 3,200,000.00 of Inventory and the same BDT 3,200,000.00 of Accounts Payable (owed to supplier 2) relate to goods that cannot be sold because no product exists. They inflate both sides equally, so net equity is unaffected, but total assets and payables are overstated by that amount if the goods were never received/kept or the products were deleted by mistake. Whether the goods exist physically is UNKNOWN.
* **Not done:** layers not deleted, purchases not reversed, products not restored.

## D-07 Supplier payment for a supplier that does not exist
* **Source (PROVEN):** `PAY-000001`: 5,150.00, type *advance*, dated 2026-06-27, recorded 2026-06-27 19:42:42, for supplier id 3, money account id 23. The supplier table only has ids 1, 2 (created from 2026-09-13, i.e. after this payment). The payment is allocated by `supplier_payment_purchases` to purchase 11 (Ref-1010, supplier 2, dated 2026-09-14): paid_amount 5150.00, but that purchase still shows due 2160000 and paid 0.00 (file 06).
* **What this means (PROVEN facts, INFERRED reading):** a June payment is allocated to a September purchase of a different supplier, and that purchase was not reduced. This is what happens when purchase and supplier ids are reused after the tables were emptied: the payment row survived the reset and now points at unrelated records (INFERRED).
* **Impact (PROVEN):** the payment changed no current balance (supplier due, payable and purchase due are all unreduced). **UNKNOWN:** whether 5,150.00 of real cash was paid in June 2026; if so, purchase 11 / supplier 2 are overstated by 5,150.00 and a cash outflow is missing from the ledger.
* **Not done:** not deleted, not re-pointed, not applied.

## D-08 One supplier carries almost all payables
* **Source (PROVEN):** supplier 2 owes 147,081,280.00 = 99.99% of all payables, from 102 purchases dated 2026-09-14 to 2026-09-16, none paid. 3,296 of the 3,462 layers are exactly 200 units, the same default quantity as the stock rows.
* **Impact / inference (INFERRED):** this is the pattern of **opening stock typed in through the purchase screen**, which the legacy system can only record as a purchase on credit. If so, the payable is not a real debt to a real supplier and Inventory / Payables are both overstated by 147,081,280.00. If the debt is real, ignore this. Needs the owner's answer.

## D-09 Statement rows with a blank type
* **Source (PROVEN):** 105 rows, none with a transaction type; the old column was an ENUM that silently discarded values it did not list.
* **Impact:** the type cannot be reconstructed, the amounts and references remain. Reports that filter by type would miss them; the migration widens the column so new rows keep their type. Nothing rewritten.
