# Difference register (nothing below has been corrected)

| ID | Difference | Figures | Evidence | What was NOT done | Question for the accountant |
|---|---|---|---|---|---|
| D-01 | Trial balance does not balance | debit 147,089,633.95 vs credit 147,089,332.20, difference 301.75 | file 03; equals expenses 353.95 minus income 52.20: Courier Settlement Expense and Courier Income were posted without a counter-account | No correcting entry | Which accounts should have received the other side? Write off, or correct at source? |
| D-02 | Balance Sheet balances only through an automatic *Retained Earnings* figure | 301.75 | report logic: retained earnings = assets - liabilities - equity - current-year earnings | Not changed | Accept as presentation, or replace with a real retained-earnings opening balance? |
| D-03 | P&L (statement rows) disagrees with account balances | income 52.20, expenses 353.95 vs P&L screen 0.00 | file 04: the balances have no statement rows | Not changed | Which basis is authoritative for the historical period? |
| D-04 | Inventory account differs from FIFO layer value | 11,255.00 (account 147,089,280.00 vs all layers 147,078,025.00) | file 05 | No revaluation | Explain or adjust? |
| D-05 | Units without any cost layer | 94,260 units in 518 variant rows | file 05 | No cost invented; no opening-cost file applied | Provide cost evidence per product, or decide a policy |
| D-06 | Stock quantity cached on products differs from stock rows; layers for deleted products | cached 952,457 vs rows 747,360; 80 orphan layers worth 3,200,000.00 | file 05 + reconcile output | Not repaired | Which is right? Orphan layers: write off? |
| D-07 | Supplier payment for a supplier that does not exist | PAY-000001: 5150.00 on 2026-06-27 | `supplier_payments` row not matched in `suppliers` | Not deleted, not re-pointed | Whose payment is it, and was cash actually paid? |
| D-08 | Implausible payable concentration | supplier 2 owes 147,081,280.00 of 147,089,280.00 | file 06 | none | Is this test data? |
| D-09 | 105 account-statement rows have a blank transaction type | 105 | the old ENUM discarded unlisted values; the original type cannot be reconstructed | not reconstructed | acceptable? |
