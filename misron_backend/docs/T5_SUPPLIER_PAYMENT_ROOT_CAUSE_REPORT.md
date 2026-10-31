# Supplier payment edit defect: root-cause report

Status: **ROOT CAUSE ESTABLISHED, CORRECTION IMPLEMENTED AND TESTED** on the disposable database (MariaDB 10.11.14). **Historical data is NOT corrected and is not touched by anything in this work**; whether and how to correct existing supplier balances is an accounting decision (register item N-1). Nothing was run against staging or production.

## 1. Symptom (reproduced)
`SupplierPaymentController::update` (route `PUT admin/accounts/supplier-payments/update/{id}`). Synthetic supplier, purchase of 100 on credit, payment 30, then edits (evidence `docs/evidence/supplier_payment/reproduction_before_fix.txt`, script `SpReproTest.php.txt`):

| Step | supplier due | supplier paid | purchase due | cash | Accounts Payable |
|---|---|---|---|---|---|
| start | 100 | 0 | 100 | 1000 | 100 |
| store payment 30 | 70 | 30 | 70 | 970 | 70 |
| edit -> 50 (before the fix) | **20** | **80** | 50 | 950 | 50 |
| edit -> 50 again | **-30** | **130** | 50 | 950 | 50 |
| edit -> 50 again | **-80** | **180** | 50 | 950 | 50 |
| edit -> 20 | **-100** | **200** | 80 | 980 | 80 |
| delete | **-80** | **180** | 100 | 1000 | 100 |

Only `suppliers.due_amount` and `suppliers.paid_amount` (and the `due_amount` snapshot stored on the payment) are wrong. Cash, Accounts Payable and the purchase's open amount were always right.

## 2. Flow of an edit and every field it changes
1. Locks the payment (since this phase) and loads **two** copies of the supplier row: `$oldSupplier = Supplier::findOrFail($payment->supplier_id)` and `$newSupplier = Supplier::findOrFail($request->supplier_id)` (same row when the supplier is not changed).
2. **Revert the old payment:** each previously allocated purchase `total_payment_due += pivot paid`; `$oldSupplier->due_amount += payment->amount`, `->paid_amount -= payment->amount`, `save()`; Accounts Payable `current_balance += amount`; payment account `current_balance += amount`; allocation rows detached; two reversal statement lines (`supplier_payment_reversal`, reference `<payment_no>-REV`).
3. **Apply the new payment:** check each new allocation against the purchase's open amount; `$newSupplierDue = $newSupplier->due_amount - $newAmount`; update the payment row (amount, paid_amount, `due_amount = $newSupplierDue`, account, method, status, date...); purchases `total_payment_due -= alloc` and attach; `$newSupplier->due_amount = $newSupplierDue; $newSupplier->paid_amount += $newAmount; save()`; Accounts Payable `-= newAmount`; payment account `-= newAmount` (balance check); two new statement lines (`supplier_payment`).
4. Journal (flag on): the legacy-ledger bridge records each balance change of the accounts as outbox rows and journals them after commit; suppliers are not ledger accounts, so the supplier fields have no journal side.

## 3. Root cause
Step 3 computes from `$newSupplier`, a copy loaded **before** step 2 saved the revert on `$oldSupplier`. When the supplier is unchanged the two copies are one database row: `$newSupplier->save()` writes `due = (value before the revert) - new` and `paid = (value before the revert) + new`, **overwriting the revert**. Every edit therefore applied the NEW amount in full on top of the OLD amount instead of applying the difference:
* intended result of an edit: `due = due_now + old - new`, `paid = paid_now - old + new`;
* actual result: `due = due_now - new`, `paid = paid_now + new`, i.e. **due is too low and paid too high by exactly the replaced amount `old` on every edit** (30->50: off by 30; 50->50: off by 50; 50->50: off by 50; 50->20: off by 50...). The drift accumulates and can make `due_amount` negative.
It does not occur when the supplier is changed in the edit (two different rows) and does not affect cash, payable or purchases (they are loaded fresh after their saves).

## 4. Intended accounting treatment (established, not guessed)
* The same screen's own revert step (`due += old; paid -= old`), the store screen (`due = due - amount; paid += amount; purchases due -= allocation; payable -= amount; cash -= amount`) and the delete screen (the exact inverse) define the treatment: **an edit = delete the old payment, then store the new one, on the current supplier row.**
* This is also checked by test: an edit 30 -> 70 -> 45 ends exactly like a direct payment of 45 (supplier due/paid, purchase, allocations, payment count and amounts), and deleting an edited payment returns every figure to the start.
* No ambiguity remained, so the correction was implemented. What stays undecided is only the treatment of figures already distorted in past edits (section 7).

## 5. Correction
`SupplierPaymentController::update`: after the old supplier is saved, `if ($newSupplier->is($oldSupplier)) { $newSupplier = $oldSupplier; }` (apply the new payment on the instance that carries the revert). Also `destroy` now detaches the allocation rows with the payment (they were left behind pointing at a payment that no longer exists; found when the delete test failed). No formula, account or response text was changed; the supplier-changed path is unchanged.

## 6. Verification (all executed)
`tests/Feature/Inventory/SupplierPaymentEditAccountingTest.php` (8 tests, 4 of them FAIL on the old code and pass now):
repeated identical edits do not move the supplier again; an increase (30->80) and a decrease (->10) apply only the difference; an edit equals storing the final amount directly; deleting an edited payment returns to the start; changing the supplier moves the payment between the two suppliers exactly; **a failed edit leaves everything unchanged** (account too small, amount above the invoice's open amount, allocations not adding up: supplier, purchase, cash, payable, allocations, statement-line count and the payment row are identical to before); journal stays balanced, no duplicate entry, outbox processed, across store/edit/edit/delete; an injected deadlock is retried without moving the supplier twice.
Real parallel processes (`WorkflowParallelCoverageTest`): the same edit x3 at once ends at supplier due 50 / paid 50 (before: drift); two different edits at once end as exactly one of them; an edit racing a delete ends deleted with every figure at the start and no orphan allocation.
After-fix reproduction: `docs/evidence/supplier_payment/reproduction_after_fix.txt` (due 70 -> 50 -> 50 -> 50 -> 80 -> delete 100; paid 30 -> 50 -> 50 -> 50 -> 20 -> 0; supplier due always equals the purchase's open amount).

## 7. What is NOT decided or done
* Existing supplier balances that were distorted by past edits are **not** corrected (register N-1). Section "Impact" in `T5_SUPPLIER_PAYMENT_DATA_IMPACT_REPORT.md` gives the accountant the read-only means to see the size.
* Payments that were edited and later deleted leave drift whose origin can no longer be attributed to a supplier from the data (the payment row is hard-deleted).
