# N-1.1 accountant review package - supplier payments edited before the fix

**Status: PREPARED. Not run on any real data. No accountant has reviewed or approved anything in this package. The sign-off section is blank on purpose.**
Owner decision N-1.1 (approved): the read-only investigation may be run by an authorised accountant or DBA on an **isolated restored copy** of the database. I have not accessed production or staging data and will not.

## 1. What happened (one paragraph)
Before the fix, editing a supplier payment reverted the old payment on one copy of the supplier record and applied the new payment on another, older copy, so the revert was lost. **Each edit left the supplier's `due_amount` lower, and `paid_amount` higher, by exactly the amount that was being replaced.** Example: due 70 -> edit -> 20 -> edit -> -30. The ledger (statement lines, cash/bank balance) was posted correctly; the supplier record was not. Root cause and proof: `T5_SUPPLIER_PAYMENT_ROOT_CAUSE_REPORT.md`. Going forward an edit is "delete the old payment, store the new one" on the current supplier row (owner-approved treatment; 8 accounting tests + parallel tests). **Past drift in real data is untouched: no historical balance was changed or is authorised to change.**

## 2. What you are asked to do
1. Have the DBA restore a recent backup to a **separate** database (record backup date/ID and checksum).
2. Run `tests/staging/n1_readonly_audit.sh` as described in `T5_N1_READONLY_AUDIT_RUNBOOK.md` (read-only account; the script refuses to run unless read-only is proven). Output: `Q01.tsv` ... `Q10.tsv` + `00_context.tsv`.
3. Read the results with section 3, classify each flagged supplier (section 4), and return the report.
4. Do **not** correct anything. Corrections need decision N-1.2 (procedure and approver), which is not yet given.

## 3. How to read the ten queries
| File | Look for | Meaning |
|---|---|---|
| Q01 | `edits_recorded > 0` | payment edited; `original_amount_if_edited` is the first reversed amount |
| Q02 | residual columns | **0.00 = the edit defect alone explains the supplier difference**; any other value = something else is also wrong; `sum_reversed_upper_bound_of_drift` is the maximum drift the edits could have caused |
| Q03 / Q04 | allocation and statement trail of edited payments | what each edit reversed and re-posted |
| Q05 | journal entries of edited payments (v2 only; may be empty/fail on a database without those tables) | cross-check |
| Q06 | negative due/paid | a symptom, not proof |
| Q07 | due_amount not explained by open purchases | for suppliers created with a **"due" opening balance** the unexplained amount *is* the opening balance (not a defect) |
| Q08 | (a) payments not fully allocated to purchases (advances are legitimate); (b) allocation rows of deleted payments | (b) = leftovers of the old delete |
| Q09 | purchases whose net - paid - due is not 0, or allocated > paid | purchase-level inconsistency |
| Q10 | live payment whose cash/bank statement effect differs from its amount; deleted payment with a net cash effect | ledger vs document |
The queries were proven on constructed data only (`SupplierDriftQueriesTest`: planted edit drift, opening balance, orphan allocation, missing cash line, purchase mismatch, unallocated advance, negative balance - each found, clean supplier silent). **Real data may need the assumptions revisited:**
A1 an edit that changed the supplier did not drift (so "sum reversed" is an upper bound) · A2 `due_amount` should equal the sum of the supplier's open purchase amounts · A3 `paid_amount` counts supplier payments (other screens add to it too) · A4 one posting line per live payment on the payment account (credit = cash out) · A5 opening-balance storage (due-type -> `due_amount`; otherwise -> `opening_balance`). Tell us if production contradicts one; do not adjust the query.

## 4. Return this (template)
| Item | Answer |
|---|---|
| Backup used (date / ID / checksum) | |
| Restored by / run by / date | |
| Server and database name (from `00_context.tsv`) | |
| Query-file SHA-256 (from `00_context.tsv`) | |
| Row counts Q01 ... Q10 | |
| Suppliers where Q02 residual = 0.00 (fully explained by the defect): count and total drift | |
| Suppliers with another cause (opening balance / other defect / unknown): count | |
| Assumptions A1-A5 contradicted by data? | |
| Suppliers needing a decision (id, amount, proposed treatment) | |
| Confirmation: no write, no correction was made | |

## 5. Decisions this review feeds (all **NOT given**)
* **N-1.2** - how any distorted supplier balance is corrected, by whom, with which document. Recommended: accountant-led, supplier by supplier, with an adjustment document; never an automatic script.
* **N-1.3** - add an edit audit table for supplier payments so future edits are traceable (schema change). Recommended: later.
* Related but separate: the live question where bank/withdrawal **charges** are expensed (`T5_B1_ACCOUNTING_EXAMPLE.md` section 1), and rulings D-01 - D-09 in `docs/accountant-review-pack/`.

## 6. Sign-off (blank)
| Role | Name | Date | Findings accepted / follow-up required | Signature |
|---|---|---|---|---|
| Accountant | | | | |
| DBA (restore and run) | | | | |
| Owner (N-1.2 / N-1.3 decision) | | | | |
