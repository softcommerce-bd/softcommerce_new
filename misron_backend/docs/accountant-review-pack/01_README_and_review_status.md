# Accountant review pack

**Generated:** 2026-10-31 00:03 from database `sc_staging` (masked staging copy of the supplied backup `misron.sql`, generated 23 Oct 2026; I cannot verify it is the current production database).
**Feature flag:** OFF (`INVENTORY_CONFIRMED_ONLY=false`). The new tables exist but are empty. Every figure below is legacy data; nothing has been corrected, repaired, re-costed or invented.
**Review status: NOT REVIEWED.** No accountant has seen or approved these figures. The sign-off table at the end is blank on purpose.

Files in this folder: `02_chart_of_accounts_balances.csv`, `03_trial_balance_from_account_balances.csv`, `04_profit_and_loss_balance_vs_statement_rows.csv`, `05_inventory_valuation_fifo_by_variant.csv`, `06_supplier_payables_reconciliation.csv`, `07_difference_register.md`.

## 1. Suspense and opening-adjustment balances
| Account | Code | Balance |
|---|---|---|
| Supplier Advances | SYS-ADV | 0.00 |
| Goods Received Not Invoiced | SYS-GRNI | 0.00 |
| Inventory Opening Adjustment | SYS-OPEN | 0.00 |
| Purchase Price Variance | SYS-PPV | 0.00 |
| Legacy Posting Suspense | SYS-SUSP | 0.00 |

All five new system accounts are 0.00 because the flag has never been on: *Legacy Posting Suspense* only receives one-sided amounts the old screens post once the bridge is active, and *Inventory Opening Adjustment* only receives approved `inventory:opening-cost` files. **Decision for the accountant:** whether a suspense account is acceptable, who reviews it and how often, and whether the one-sided legacy postings (e.g. old purchase screen: debit Inventory and Supplier Advances, credit Payable and Cash) should instead be corrected at source.

## 2. Trial balance (from account balances, as of 2026-10-31)
Total debit 147,089,633.95 / total credit 147,089,332.20 / **difference 301.75**. The legacy trial balance does **not** balance (see D-01). The Balance Sheet hides this with an automatic *Retained Earnings* balancing figure of 301.75 (and a matching *Current Year Earnings* of -301.75). Detail: file 03.

## 3. Profit & Loss
The P&L screen reads account-statement rows dated in the period; the Trial Balance reads account balances. For this data they disagree: income account balance 52.20, expense account balance 353.95, but the P&L screen shows 0.00 for all time because those balances have no matching statement rows (D-03). Detail: file 04. There is no sales revenue or COGS booked at all although 32 orders exist (25 cancelled, 2 confirmed, 5 pending), so no profit figure can be finalized from this data.

## 4. Balance Sheet (as at 2026-10-31)
Assets 147,089,280.00 (Inventory only), liabilities 147,089,280.00 (Accounts Payable only), equity 0.00 after the balancing figure. Inventory and Accounts Payable are equal to the cent, which is unusual for a trading business: ask whether this is test/seed data (103 purchases dated 13–16 Sep 2026, all unpaid).

## 5. Inventory valuation and FIFO costing
| Measure | Value |
|---|---|
| Inventory account balance | 147,089,280.00 |
| FIFO cost-layer value, ALL layers (remaining qty x unit cost) | 147,078,025.00 |
| of which on layers that match a live stock row (file 05) | 143,878,025.00 |
| of which on layers of deleted products (orphans) | 3,200,000.00 |
| **Difference (account - all layers)** | **11,255.00** |
| Stock quantity, all variants (`product_stocks`) | 747,360 |
| FIFO layer quantity matching those rows | 670,700 |
| **Units on the shelf with no cost layer (value unknown, not in the Inventory account's layer value)** | **94,260** |
| `products.current_stock` total (cached) vs stock rows | 952,457 vs 747,360 |
| Cost layers pointing at deleted products | 80 layers, qty 16,000, value 3,200,000.00 |

Per-variant detail with a MATCH / UNCOSTED UNITS / LAYERS EXCEED STOCK status: file 05. Nothing was assigned a cost. To cost the uncosted units, a person fills in a unit cost with evidence in the `inventory:opening-cost` template; that is the only supported path and it is not done.

## 6. Supplier payables and invoice reconciliation
Accounts Payable account 147,089,280.00; sum of `suppliers.due_amount` 147,089,280.00; sum of purchase dues 147,089,280.00. These three agree. There are no new-style supplier invoices (the new tables are empty). Detail: file 06.
Supplier payment rows whose supplier does not exist: 1 (PAY-000001 supplier 3 amount 5150.00 paid 2026-06-27) (D-07).

## 7. Known historical figures and differences
See `07_difference_register.md` (every difference with its evidence, what is NOT being done about it, and the question for the accountant).

## 8. Sign-off (to be completed by a real accountant; leave blank until reviewed)
| Item | Reviewer | Date | Approved / Rejected / Needs change | Comment |
|---|---|---|---|---|
| Suspense and opening-adjustment treatment | | | | |
| Trial balance difference (D-01) | | | | |
| P&L basis (D-03) | | | | |
| Balance Sheet basis, balancing figure (D-02) | | | | |
| Inventory valuation and uncosted units (D-04, D-05, D-06) | | | | |
| Supplier payables (D-07, D-08) | | | | |
| Permission to enable `INVENTORY_CONFIRMED_ONLY` on staging | | | | |
