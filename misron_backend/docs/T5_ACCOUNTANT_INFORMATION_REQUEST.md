# Information request to the accountant (T-5)

From the T-5 engineering work, commit `98339a1`. We ask for **rulings and signatures**, not for corrections: nothing here changes a historical balance, and no script will ever correct supplier or ledger figures. Please answer in writing; "no ruling yet" is an acceptable answer and keeps today's behaviour. Technical words are explained in brackets. Tracker rows: E5, E6 (accounting items).

## 1. Review that needs the DBA (items 1-2)
1. **Supplier-payment edit drift (N-1.1).** Before the fix, every edit of a supplier payment left the supplier's *due* too low and *paid* too high by the replaced amount. Ask the DBA to restore a backup to a separate database and run `tests/staging/n1_readonly_audit.sh` (read-only, refuses to write). Read the ten result files with `T5_N1_ACCOUNTANT_REVIEW_PACKAGE.md` section 3, classify each flagged supplier (explained by the edit defect / opening balance / other / unknown) and return the template in section 4 with your signature. **Question N-1.2:** how should any distorted supplier balance be corrected (recommended: you, supplier by supplier, with an adjustment document, never an automatic script)? **N-1.3:** do you want an audit table for future supplier-payment edits (a schema change; recommended: later)?
2. **Accountant review pack** (`docs/accountant-review-pack/`, built from a masked copy of the supplied backup, not proven to be production): rule on the nine differences: D-01 trial balance out by 301.75; D-02 retained-earnings plug; D-03 P&L vs balances; D-04 inventory vs FIFO 11,255.00; D-05 94,260 units without cost; D-06 cost layers of deleted products (BDT 3,200,000); D-07 payment PAY-000001 5,150.00 to a missing supplier; D-08 one supplier carries 99.99% of payables (147,081,280.00); D-09 105 statement rows without a type. Complete its sign-off table.

## 2. Rulings requested
| ID | Question | Options | Recommendation | If you do not decide |
|---|---|---|---|---|
| ACC-03 | Which expense account receives bank charges? Today a withdrawal/transfer with a charge credits the source with amount + charge and **debits no expense account** | name the account, or confirm one-sided posting is intended | name the account | charges stay un-expensed (existing behaviour) |
| ACC-04 / T-2 | Wallet-only refund treatment (wallet top-ups and spending are not journalled): accept or specify | accept / specify | accept after reading the report | refund accounting stays unaccepted |
| ACC-05 / T-3 | Historical orders without cost: reporting policy in `docs/HISTORICAL_ORDER_COSTS.md` | accept / specify | accept | profit reports keep the estimated-cost labelling |
| DEC-02 | Deleting a purchase leaves the supplier's *due* unchanged (payable falls, supplier due stays) | A keep / B reduce due by the open amount / C forbid deleting purchases with payments | decide; report-only for history | ledger and sub-ledger keep diverging on delete |
| DEC-03 | Overdraft rules: today only some screens refuse a balance below zero (balance transfer, expense, supplier payment, purchase, stock adjustment); income, vouchers, customer payments, settlements have none | per screen: block / warn / none; and whether updates check before or after reverting | keep today's rules until you decide | only the existing rules apply |
| DEC-04 | Supplier edit/delete: today an edit overwrites balances with the submitted figures (now re-read under lock) | A refuse if the figures changed since the form was shown / B apply as a difference / C overwrite + audit entry; who may edit balances | A | edit stays an overwrite |
| DEC-06 | Number formats, resets, gap-free rule | keep / change | keep | formats stay |
| DEC-08 | Repair commands (`cleanup-statement-logs --apply`, `ReverseSettlement`, `FixPartialSettlements`, `FixPosCourierIncome`): may they run while trading? who authorises `--apply`? | maintenance-mode only / other | maintenance only | not verified; do not run |
| DEC-10 | Customer-payment auto-import: is an hourly import wanted; which account and method; which orders | decide | decide | the job stays removed |
| DEC-13 | Money columns stored as text/double: when to convert to decimal | after the accounting baseline | after the baseline | text money columns stay |
| DEC-14 | Unique constraints (order codes, settlements, reference numbers) after an audit shows no duplicates (with the DBA) | add / not | add after the audit | no database-level uniqueness |

## 3. Acceptances requested (written, one line each)
T-07 existing overdraft rules are re-checked under the account lock (tested) · T-09 account balances are written as changes, not overwritten (D3, tested on the screens listed in the coverage matrix) · T-10 supplier rows are locked in purchase/payment/return paths and re-read under lock on edit/delete (D4) · T-11 duplicate checkout detection by the exact cart (returns the first order). Evidence: `T5_FINAL_COMPLETION_REPORT.md` section 3 and `T5_FINAL_TEST_EVIDENCE.md`.

## 4. For the auto-deposit job (decision DEC-16, owner)
It is **paused** and refuses to run unless prepared. If the business wants nightly deposits we need from you: the charge-booking rule (ACC-03), which accounts, and whether charged methods are allowed. Until then no money moves.

## 5. Answer sheet
| ID | Ruling / acceptance (text) | Signed by / date |
|---|---|---|
| N-1.1 results | | |
| N-1.2 / N-1.3 | | |
| Pack D-01..D-09 | | |
| ACC-03, ACC-04, ACC-05 | | |
| DEC-02, 03, 04, 06, 08, 10, 13, 14 | | |
| T-07, T-09, T-10, T-11 | | |
