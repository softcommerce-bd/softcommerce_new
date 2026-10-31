# Supplier payment edit defect: data-impact report and accountant reconciliation guide

**Read-only.** Queries: `docs/T5_SUPPLIER_PAYMENT_IMPACT_QUERIES.sql` (SELECT only, `READ ONLY` session, returns ids/document numbers/amounts only). **Execution on real data is BLOCKED**: no production copy was provided, and nothing was run on staging or production. The queries were executed only on synthetic rows created by the reproduction (`docs/evidence/supplier_payment/reproduction_before_fix.txt`), where they identified the edited payment, its original amount, the stored supplier figures, allocations, statement lines and journal entries. **No corrective UPDATE or DELETE exists or is proposed.** The defect is not assumed to explain every supplier discrepancy.

## 1. What evidence an edit leaves
* There is **no edit history table**. Each edit wrote one reversal pair of statement lines: `account_statement_logs.transaction_type = 'supplier_payment_reversal'`, reference `<payment_no>-REV`, `source_type` ending in `SupplierPayment`, `source_id` = the payment id; the amount of the line is the payment amount that was reverted at that moment. Counting those lines gives the number of edits; the first one is the **original amount**; the sum is the **upper bound of the drift** (each edit with an unchanged supplier left the supplier due too low and paid too high by exactly that amount).
* Deleting a payment writes `supplier_payment_deletion` lines (not counted as edits) and **hard-deletes** the payment row. Edits of a payment that was deleted afterwards still leave their `-REV` lines, but the supplier of that payment can no longer be found, so their drift cannot be attributed (query Q2 cannot count them; Q4 would list them only by payment id if the rows were joined without the payment).
* The payment row keeps `due_amount` = the supplier due after that payment, as computed at the last edit (also distorted by the defect).

## 2. The queries
| Query | Shows | Key columns |
|---|---|---|
| Q1 | every supplier payment with edit evidence | payment_id, payment_no, reference_no, supplier_id, current_amount, due_snapshot_stored_on_payment, edits_recorded, original_amount_if_edited, sum_of_reversed_amounts, status, deleted_at |
| Q2 | supplier reconciliation (suppliers with an edited payment, any difference, or negative due) | supplier_due, supplier_paid, purchases_open_due, live_payments_total, edits_recorded, sum_reversed_upper_bound_of_drift, due_minus_purchases_open, **due_residual_if_drift_explains**, paid_minus_live_payments, **paid_residual_if_drift_explains** |
| Q3 | allocations of edited payments | payment_id, purchase_id, allocated_now, purchase_open_due |
| Q4 | the statement-line trail per edited payment | log_id, reference_no, transaction_type, account_id, debit, credit |
| Q5 | journal entries linked to supplier-payment edits (v2 only) | entry_no, source_type, source_id, debit, credit |
| Q6 | negative supplier balances | supplier_id, due_amount, paid_amount |

## 3. How an accountant reads Q2 (assumptions A1-A3 are stated in the SQL)
* **A1** an edit that changed the supplier did not drift (the defect needs one supplier row), so `sum_reversed` is an **upper bound**.
* **A2** `suppliers.due_amount` is intended to equal the sum of its purchases' open amounts (`total_payment_due`); this relation is itself unconfirmed (register decision 2: purchase delete does not touch supplier due), so differences can have other causes.
* **A3** `suppliers.paid_amount` also grows through other screens (`SupplierController::processPayment`), so a paid difference is not proof either.
* `due_residual_if_drift_explains = supplier_due + sum_reversed - purchases_open_due`. **0.00 means the defect alone accounts for the difference.** A non-zero residual is something else (or edits of since-deleted payments, or supplier changes) and must be investigated separately; the same for the paid residual.
* Rows with `supplier_due < 0` are a visible symptom, never proof by themselves.

## 4. Worked example (synthetic, from the reproduction run, not real data)
Supplier with a 100 purchase, a payment of 40 edited twice to 60 (plus an earlier, since-deleted payment whose edits drifted the supplier): Q1 returned `edits_recorded 2, original_amount_if_edited 40, sum_of_reversed_amounts 100`; Q2 returned `supplier_due -240, purchases_open_due 40, due_minus_purchases_open -280, sum_reversed_upper_bound 100, due_residual_if_drift_explains -180`. The residual of -180 equals the drift of the **deleted** payment's four edits (30+50+50+50) that Q2 cannot attribute: exactly the limitation in section 1. After the correction the same sequence leaves due 40 / paid 60 and a zero difference (`reproduction_after_fix.txt`).

## 5. Reconciliation evidence for the accountant
* Before/after behaviour table: root-cause report section 1 and `reproduction_{before,after}_fix.txt`.
* Tests that pin the correct treatment: `SupplierPaymentEditAccountingTest` (8) and the parallel edit tests (section 6 of the root-cause report). Journal: after store/edit/edit/delete the trial balance is balanced, no entry is duplicated, the outbox is fully processed, and the cash account returns to its start.
* To do on a restored production copy (needs approval, decision 12): run Q1-Q6, review Q2 residuals with the accountant, decide per supplier (register N-1). Nothing is corrected automatically.
