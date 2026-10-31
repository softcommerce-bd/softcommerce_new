# T-5 final independent review (code level, D1–D6)

**Reviewer verdict: T-5 is NOT ready for acceptance.** The retry mechanism and the parallel *creation* paths hold up. But the review found that D3 and D4 turn accidental "last writer wins" idempotence into **multiple application of reversals** when a delete/unsettle is submitted more than once, and that three retried purchase-return/purchase-delete paths still have irreversible file effects. Both were reproduced with real parallel requests. Production stays NO-GO, staging is not activated, R1–R4 stay UNRESOLVED.

Scope and method: I read `docs/T5_DEADLOCK_RETRY_VERIFICATION_REPORT.md`, `docs/evidence/verification/*` and `docs/STAGING_ACTIVATION_PREREQUISITES.md`, then read the code of D1–D6 and every place they touch (grep and static scans over `app/Http/Controllers`, `app/Models`, `app/Services`), and ran four throw-away probes (sources and output in `docs/evidence/review/`). **No application or test code was changed**, nothing deployed, merged, enabled or modified in staging. To compare against the pre-D3 behaviour I temporarily swapped `app/Models/ChartOfAccount.php` for its earlier version in the working tree and restored it (git status clean afterwards). The only contact with staging was a single read-only `SELECT count(*)` on `sc_staging.courier_settlements` (24 rows) while checking an execution plan; nothing in staging was written or changed. One look at the test database's lock behaviour (P4) inserted and removed three rows in `inv_v2_test` (its auto-increment counter for `courier_settlements` moved by 3; no fingerprint figure changed).

Labels: **PASS** (code and evidence agree), **FAIL** (defect or false claim, evidence given), **NEEDS BUSINESS APPROVAL** (a decision that is not technical), **NOT VERIFIED** (no test or evidence either way).

## 1. Result matrix

| ID | Area | Verdict | Severity |
|---|---|---|---|
| F-01 | D3/D4: reversal and delete paths apply more than once on a repeated request (unsettle, supplier-payment delete) | **FAIL** | High |
| F-02 | Retried purchase delete / purchase-return store / update still have unrepeatable file effects | **FAIL** | High |
| F-03 | Early `return` inside an open manual transaction; D2/D4 locks now held until the process exits | **FAIL** | Medium |
| F-04 | D3 does not make balance checks (overdraft) atomic | **FAIL**, and **NEEDS BUSINESS APPROVAL** on the policy | Medium |
| F-05 | D4 completeness: supplier edit and other supplier writes unlocked; string/float money columns | **FAIL** (gap) | Low–Medium |
| F-06 | Purchase delete never reduces `suppliers.due_amount` (pre-existing; seen in probe P3) | **NEEDS BUSINESS APPROVAL** | Medium |
| F-07 | D6: repeated-order guard | **NEEDS BUSINESS APPROVAL**, plus technical **FAIL** items | Medium |
| F-08 | D1 courier settlement numbers | **PASS** (with notes) | – |
| F-09 | D5 supplier payment numbers | **PASS** (with notes) | – |
| F-10 | Six other number generators have the same read-then-insert race | **FAIL** (availability only; unique indexes stop duplicates) | Low |
| F-11 | D2 order/account locking in `settle` | **PASS** (single order, shared account); `unsettle` not covered, see F-01 | – |
| F-12 | D3 mechanism itself (`ChartOfAccount::performUpdate`) | **PASS** for single-request equivalence and lost updates; **NOT VERIFIED** for POS, vouchers, expenses, income, bank, transfers, stock adjustment | – |
| F-13 | Retry middleware / `DeadlockRetry` duplicating database work | **PASS** on tested paths; **NOT VERIFIED** on the untested controllers | – |
| F-14 | Partial commits of financial records | **PASS** statically for every retried method; **FAIL** for the two excluded paths and **NOT VERIFIED** for two unlisted ones | – |
| F-15 | Real engine (production version) | **NOT VERIFIED** (blocked) | – |
| F-16 | Report accuracy | **FAIL** (three overclaims, see section 5) | – |

## 2. Findings

### F-01 FAIL (High) – a repeated reversal is now applied several times

* **Files / methods:** `app/Http/Controllers/CourierSettlementController.php::unsettle` (about lines 1128–1200); `app/Http/Controllers/SupplierPaymentController.php::destroy` (about lines 700–770). The same pattern is likely in every delete/reverse screen (see section 6).
* **Code:** both load the document (`CourierSettlement::findOrFail`, the payment) with a plain read and no state guard under a lock, read the account/supplier, add the reversal in PHP and save. Before D3/D4 the absolute write from a stale read made a second submit overwrite the first with the *same* number, so a double click looked idempotent by accident. D3 (delta writes) and D4 (supplier row locked and re-read) make every submit add its own change.
* **Evidence (probe P1, P2, `docs/evidence/review/probe_output.txt`):**
  * Settle once, then unsettle sent three times at once (all three answered success): cash account 4940 → **-9880** (one reversal gives 0), COGS **-1500** (one reversal -500). With the pre-D3 model file the same run ended at **0 / -500**.
  * Supplier payment of 30 deleted three times at once (all HTTP 200): supplier due 30 → **120** (one delete: 60), cash 930 → **1020** (one delete: 960; pre-D3 model: 960), purchase due 30 → 60.
  * The outbox/journal records the same deltas, so the journal is wrong in the same way.
* **Risk:** a double click, two tabs, or a retried request creates money (cash 60 too high in P2) and wrong supplier dues. The deadlock retry is not the cause (it retries only after a rollback), but `unsettle` and `destroy` are in the retry set and the failure is a direct consequence of the T-5 changes.
* **Recommended next step (needs your approval; not done):** in every reverse/delete method, lock the document row first (`lockForUpdate`) and re-check its state under the lock (settled? deleted? `settlement_status`/row exists), answering "already reversed" otherwise; then add the tests in section 6 for each method before D3 is accepted. Until then, D3 should be considered unsafe for any non-idempotent legacy screen.

### F-02 FAIL (High) – unrepeatable file effects inside retried transactions

* **Files / methods (all `app/Http/Controllers/PurchaseController.php`, class has `'*'` in `config/deadlock_retry.php`):**
  * `purchaseDelete` line ~2070: `Storage::disk('public')->delete($purchase->document)` runs **before** `DB::commit()`. A deadlock or later error rolls the purchase back but the document is gone for good; the retry then finds no file.
  * `updatePurchaseReturn` lines ~1896–1899: deletes the old document and stores a new one inside the transaction; each retried attempt stores another file and the old one is already deleted.
  * `storePurchaseReturn` line ~1354: stores the upload inside the transaction, not once per request, no undo; every retried or finally failed attempt leaves an orphan.
* **Evidence:** code lines above; my static grep for file operations in the retried controllers (all `'*'` classes) lists exactly these three beside the already-fixed store/update. The first edition's static audit (`DeadlockSideEffectTest`) checks only outgoing calls (Http, Mail, notification, queue, event) and never looked at file effects.
* **Risk:** loss of the supplier invoice document after a failed delete; orphan files; contradicts requirement "do not repeat non-transactional side effects".
* **Recommended next step:** wrap the stores in `RequestSideEffects::once` with undo, and move the deletes into `DB::afterCommit` (same pattern already used in `store`/`update`); extend the static audit to file operations so the gap cannot reappear; add the fault-injection tests listed in section 6.

### F-03 FAIL (Medium) – open transactions after an early return; locks now held longer

* **Files / methods:** `CourierSettlementController::settle` (six `return response()->json(...)` after `DB::beginTransaction()`: already settled, cancelled, no due, no deliverable item, pending items, bad input), `::unsettle`, `SupplierPaymentController::store` (422 on allocation mismatch), `PurchaseController::purchaseUpdatePayment`, `PurchaseOrderController::store` and `::purchaseUpdatePayment`, `VoucherController::store` and `::update` (many), `SupplierController::processPayment`, `OrderController::fixAllPayment` (found by a static scan of every manual `beginTransaction` for a `return` before any `commit`/`rollBack`).
* **Evidence:** code. Since D2 the settlement takes the order row and up to eight account rows as its first statements, and D4 takes the supplier row; a refused request ("already settled", "exceeds due") returns without releasing them. In PHP-FPM the connection closes at the end of the request and the transaction rolls back, but until then (response rendering, session save, terminating callbacks) other requests queue behind it. With persistent workers (Octane, queue workers) the transaction would stay open. The retry middleware rolls back and logs critical only on the *deadlock* path.
* **Risk:** lock convoys when an admin double-clicks; a persistent-worker deployment would hold row locks indefinitely.
* **Recommended next step:** `DB::rollBack()` before each of these returns (or use `DB::transaction` closures); add a test that a refused settle leaves `DB::transactionLevel()` unchanged and releases the locks (second request does not wait).

### F-04 FAIL (Medium) / NEEDS BUSINESS APPROVAL – overdraft checks are still check-then-act

* **Files / methods:** `BalanceTransferController::store` line 91 (check *before* the transaction, no lock), `BankTransactionController::store` line 109 and `::update` line 169, `ExpensePaymentController::store` line 114 and `::update` line 267, `SupplierPaymentController::store` line 353 and `::update` line 673 (account read without lock). Only `PurchaseController` lines 430/710 were changed to lock the account.
* **Evidence:** code. D3 makes the *balance* correct (all postings counted) but two requests can both pass "balance ≥ amount" and both pay, leaving the account negative.
* **Risk:** negative cash/bank balances under concurrency. Whether that matters, and whether a refusal is wanted, is a business rule.
* **Recommended next step:** decision from the accountant (block overdraft strictly, or allow with a warning); if blocked, lock the account row before the check in these seven places and test with N parallel payments larger than the balance.

### F-05 FAIL (Low–Medium) – D4 does not cover every supplier write; money columns are not numeric

* **Files / methods:** `SupplierController::supplierUpdate` (lines ~494–540: `Supplier::find` unlocked, then `$supplier->update(['opening_balance' => ..., 'due_amount' => ...])` as an absolute write; a concurrent purchase or payment is overwritten), `::supplierDelete` (~723), `::supplierStore` (new row, fine). Locked and consistent (PASS): `PurchaseController` store, update (old and new supplier), purchaseUpdatePayment, purchase return store/update, purchaseDelete (advance only); `PurchaseOrderController` store/update/destroy/purchaseUpdatePayment; `SupplierPaymentController` store/update/destroy; `SupplierController::processPayment`.
* **Evidence:** static review of all 20 `supplier->save()/update` sites in the controllers; dynamic proof only for purchase store ×4 (supplier due exact) and payment store.
* **Notes:** `suppliers.opening_balance` and `purchases.total_payment_due` are `varchar(255)` and `suppliers.paid_amount` is `double(13,2)` (checked in `inv_v2_test`); the legacy code does PHP float arithmetic on them. Locking makes the arithmetic sequential but does not remove rounding drift.
* **Recommended next step:** lock the supplier in `supplierUpdate` and `supplierDelete` and re-read before applying; test supplier edit ∥ purchase ∥ payment.

### F-06 NEEDS BUSINESS APPROVAL – purchase delete leaves the supplier's due unchanged (pre-existing)

* **File / method:** `PurchaseController::purchaseDelete` (lines 2003–2077). **Evidence (probe P3):** after deleting a 100 purchase with 40 paid, the payable account is reduced by 60 and cash restored, but `suppliers.due_amount` stays **60.00**; single and triple delete end identical (the 2nd/3rd are stopped by the "stock already gone" guard, not by a lock on the purchase, so this protection is accidental). Not introduced by T-5.
* **Risk:** supplier sub-ledger ≠ Accounts Payable after a delete.
* **Next step:** accountant to say whether the supplier due should be reduced on delete; no change made.

### F-07 D6 repeated-order guard – NEEDS BUSINESS APPROVAL, with technical FAIL items

* **File / method:** `app/Http/Controllers/OrderController.php::store` (lines ~1284–1306).
* **Can it block a legitimate repeat?** Yes. The match is: same `user_id` + a combined order created in the last 15 seconds + identical sorted `product|variation|quantity` lines. It ignores the delivery address, recipient name/phone, payment option, shipping cost, coupon, notes. A customer sending the same item to a second address within 15 seconds, or placing a deliberately identical second order, silently gets the *earlier* order back (same success screen, no message). The earlier order may even have been cancelled in between. For a logged-in buyer the cart user is the buyer; for guest checkout the user is the guest, so it only affects repeats by the same account. This route (`orders.store`, `/admin/orders`) is the checkout posting, not a staff-only screen.
* **Other technical points:** (a) `$carts` is read **before** the transaction, then the cart rows are locked; a quantity change made between the read and the lock is not noticed (rows ids are compared, quantities are not). (b) `combined_orders` has **no index on `user_id`** (`show index` is empty), so the guard scans that table on every order. (c) `AdminOrderParallelTest` proves 4 identical submits give one order, but there is no test that a *different* order with the same cart (other address/payment) is still accepted.
* **Next step:** business to decide: either an explicit per-submission token (hidden field, honoured server-side) with no time window, or keep a window but include address, payment option and shipping in the match and show "this order was already placed". Add the index, re-read the cart after locking, and add the two tests in section 6.

### F-08 D1 `CourierSettlement::createNumbered` – PASS (notes)

* **Safe after rollback and retry:** the number is derived from existing rows (`generateSettlementNo` with a locking read), nothing is consumed, so a rolled-back or retried attempt reuses the same number and leaves no gap; a clash is resolved in a savepoint (`DB::transaction` inside the caller's transaction) and retried at most 5 times. `CourierSettlementParallelTest` (4 orders at once) and the real-deadlock test pass; before the fix 3 of 3 runs failed.
* **Checked and rejected concern:** the locking query could lock every historical row on the first settlement of a day (EXPLAIN on the empty test table shows a primary-key scan). With rows present, probe P4 showed it did **not** block an update of the oldest row, so this is not confirmed.
* **Notes:** (1) the inner 5 tries sit inside the outer 3 deadlock attempts, so the worst case is 15 tries (bounded, but not "max 3"); (2) the clash is recognised by the text `settlement_no` in the error message (the index name `courier_settlements_settlement_no_unique`), engine-dependent: **NOT VERIFIED** on the production engine.

### F-09 D5 `SupplierPayment::createNumbered` – PASS (notes)

* Same property as F-08 (number derived from the last row, nothing consumed). The last row is locked (`order by id desc limit 1 for update`, primary-key scan, one row) until the **outer** transaction ends, so all supplier payments in the system are serialised for that time. Safe, but a throughput limit, and it adds a second lock after the supplier lock (ordering supplier → last payment is consistent inside `store`; `update`/`destroy` do not take it).

### F-10 FAIL (Low, availability) – other number generators unchanged

* `StockAdjustment::generateReferenceNo`, `ExpensePayment::generateVoucherNo`, `BalanceTransfer::generateVoucherNo`, `Voucher::generateVoucherNo` (+ `VoucherController::generateVoucherNo`), `CustomerPayment::generatePaymentNo`, `Income::generateVoucherNo`. All have the same read-last-then-insert race. All eight number columns have a unique index (checked in `inv_v2_test`), so the result is a failed request (as D1/D5 behaved before the fix), not duplicate numbers. **NOT VERIFIED** by test. Next step: apply the same `createNumbered` pattern and add the parallel test per document type, once approved.

### F-11 D2 locking in `settle` – PASS

* Order row and every account the settlement can touch are locked by primary key in ascending id order before any plain read (`ledgerAccountIds`). Evidence: `CourierSettlementParallelTest` (same order ×4, four orders into one account) and the real-deadlock test. Notes: accounts that are created lazily inside the method (`firstOrCreate` style) are not in the lock set; the other screens lock in other orders (deadlocks are possible and are what the retry is for); `unsettle` has none of this (F-01).

### F-12 D3 `ChartOfAccount::performUpdate` – mechanism PASS, coverage NOT VERIFIED

* **Create:** unaffected (insert path). **Update/save:** a changed `current_balance` is written as `current_balance = current_balance + delta` through `ChartOfAccountBuilder::increment`, so the ledger bridge records the exact delta; the other changed columns are written in the same statement; the stored value is read back under the row lock so statement logs written afterwards show the real balance. **Delete:** soft delete does not touch the balance. **Transfer:** `BalanceTransferController` locks both accounts and saves via the model: correct. **Refund:** `RefundService`/`JournalService` already use atomic SQL and are untouched (earlier phases tested parallel refunds). **Reversal:** correct arithmetically, but see F-01 for the missing guard. Raw writers bypass the model and are unaffected: `JournalService` (atomic), `InventoryResetCommand` (reset only).
* **Behaviour change to approve:** code that assigns an absolute balance now applies the difference from the model's loaded value. In the repository the only such caller is `Console/Commands/CleanupStatementLogs.php:345` (`$account->update(['current_balance' => $net])`); run while the shop is trading it would add a difference instead of setting the value. **NEEDS BUSINESS APPROVAL** whether that maintenance command may run only offline.
* **Cost:** one extra locking read per balance save.
* **NOT VERIFIED (no parallel or injected test):** POS orders (`Utility/PosUtility.php`, many balance saves), `VoucherController`/`Voucher` model, `ExpensePaymentController`, `Income` model, `BankTransactionController`, `StockAdjustmentController`, `CustomerPaymentController`, `ChartOfAccountController::update/updateBalance` (opening-balance edit adds `$difference`, equity adjustment), `TransactionMethodController`, the console commands `ReverseSettlement`, `FixPartialSettlements`, `AutoDepositFunds`, `FixPosCourierIncome`.

### F-13 Retry mechanism and duplicate side effects – PASS on tested paths

* A retry happens only if the transaction level is back at the level where the work started, the error is SQLSTATE 40001/1213, and fewer than 3 attempts were made; the request re-runs from the start, so every database statement of a failed attempt has been rolled back (`DeadlockWorkflowRetryTest`, `CommittedDeadlockRetryTest`, `RealDeadlockHttpTest`). Files for store/update paths are stored once per request and undone on final failure. Outgoing calls: none in the retried methods (`DeadlockSideEffectTest`, with a positive control; static audit one level deep).
* **NOT VERIFIED / limits:** F-02 (three file paths); the detection of a deadlock from response *text* when a handler swallows the exception; effects through helpers that bypass the framework fakes (raw curl); untested controllers in the retry set; behaviour on the production engine.

### F-14 Partial commits – PASS (static) with two FAIL and two NOT VERIFIED

* Static scans of every public controller method that touches balances, dues, stock or statement logs (62 methods): none of the **retried** methods has more than one commit, a write outside a transaction, or a write after the commit.
* **FAIL (known, excluded from retry):** `PurchaseOrderController::convertToPurchase` (purchase committed, then PO status and received quantities written outside any transaction); `CustomerPaymentController::autoImport` (one commit per customer, errors swallowed, always `success: true`).
* **NOT VERIFIED (not in the retry set, no transaction at all while writing several tables):** `OrderController::update_delivery_status`, `::bulkAssignCourier` (flagged by the scan; pre-existing; not examined further).
* By design not an atomic commit: legacy-ledger journalling after commit (outbox, R4), which is why R4 stays UNRESOLVED.

### F-15 Real engine – NOT VERIFIED
* Every result is MariaDB 10.11. Victim selection, lock waits, the duplicate-key message text used by F-08/F-09, and the error text used by the middleware are engine-dependent. The production engine/version output is still missing.

### F-16 Accuracy of the second-edition report – FAIL
1. "Retries never repeat external side effects … static audit finds no …" is true for outgoing calls only; file effects (F-02) are not covered by the audit and three retried methods violate it.
2. D3 and D4 are described as fixes without stating that they change the outcome of repeated reversal requests (F-01).
3. D6 is described as "answered with the earlier order" without the address/payment/coupon gap (F-07).
4. The statement "all other paths covered by D3/D4" is not backed by tests (F-12).
The first-edition text in the same file is superseded where it says admin/courier/purchase were injected only.

## 3. D1–D6 at a glance

| D | What | Verdict | Open points |
|---|---|---|---|
| D1 | Settlement number | PASS | engine-dependent clash detection (not verified on prod engine); 5×3 worst case |
| D2 | Order/account locks in settle | PASS for `settle`; `unsettle` FAIL | F-01, F-03 |
| D3 | Delta balance writes | mechanism PASS; **FAIL** as a safe change for non-idempotent screens | F-01, F-04, F-12 |
| D4 | Supplier locks | PASS for tested/locked sites; **FAIL** gaps | F-01, F-05, F-06 |
| D5 | Payment number | PASS | global serialisation; other generators F-10 |
| D6 | Repeated-order guard | NEEDS BUSINESS APPROVAL + technical FAIL | F-07 |

## 4. Your six focus questions

1. **D3 and every mutation (create, update, delete, reversal, refund, transfer)?** Create/update/transfer/refund: yes mechanically (F-12). Reversal/delete: arithmetic correct, **repeat-safety broken** (F-01). Overdraft checks: not atomic (F-04).
2. **D4 supplier dues accurate across purchase and payment operations?** Concurrency-safe where the supplier is now locked (list in F-05); not safe for supplier edit; accuracy of the legacy rules themselves is questionable (F-06) and repeat-safety broken for payment delete (F-01).
3. **D6 blocking legitimate repeats?** Yes, see F-07.
4. **D1/D5 safe after rollback and retry?** Yes (F-08, F-09); bounded; other generators not fixed (F-10).
5. **Retry causing duplicated database or external effects?** Not for database rows on tested paths; **yes for files** in three retried methods (F-02).
6. **Controller paths that partially commit?** None of the retried ones statically; the two excluded paths do (F-14); open-transaction early returns do not commit but hold locks (F-03).

## 5. Untested financial workflows and the exact tests needed

Test names are proposals. Each test uses the existing `HttpWorkflowCase` (real processes, authenticated admins, committed rows removed afterwards). "P" = parallel duplicate (3 identical requests at once, expect exactly one effect). "I" = injected deadlock once (success, one of everything) and always (3 attempts, world unchanged). "L" = N parallel *different* documents on a shared account/supplier (no lost update). "X" = create ∥ delete of the same document. "R" = real deadlock (helper process, `RealDeadlockHttpTest` pattern).

| # | Workflow | Tests needed (assertions) |
|---|---|---|
| 1 | Courier **unsettle** | P: balances back to pre-settlement exactly once, one set of statement-log deletions, one reversing journal entry; X: settle ∥ unsettle ends settled or unsettled, never half; I; R |
| 2 | Courier settle variants: partial delivered, zero-shipping customer-paid, zero-shipping admin-paid, POS order | L and P for each variant; AR/COGS/inventory/courier-income/bad-debt balances equal the single-run values |
| 3 | Supplier payment **update / destroy** | P (destroy ×3, update ×3): supplier due, paid, purchase due, account all change once; L (payments to one account); X payment ∥ purchase delete |
| 4 | Purchase **update**, **purchaseDelete**, **purchaseUpdatePayment** | P each; supplier change (old→new) ∥ payment; delete ∥ sale of its layer (layer cannot go negative, cost stays FIFO); I with file present (document survives a failed delete) |
| 5 | Purchase **returns** store/update/delete | P, L (shared payable/inventory accounts), I with upload (no orphan file, old file kept on failure) |
| 6 | **Purchase orders** store/update/destroy/purchaseUpdatePayment | P, L, I; advance balance never negative |
| 7 | **Supplier** edit/delete/processPayment | supplier edit ∥ purchase ∥ payment keeps due exact; processPayment ×3 pays once |
| 8 | **Customer payment** store/destroy | P (same orders ×3 → one payment, orders marked paid once), L, X |
| 9 | **Voucher** store/update/destroy | multi-line vouchers over 2 shared accounts: L and P; trial balance balanced; I |
| 10 | **Expense**, **Income**, **Bank transaction**, **Balance transfer** | P, L, X; overdraft race (N payments each larger than half the balance, expected per decision F-04); I |
| 11 | **Stock adjustment** update/destroy | P, X; stock and FIFO layers match; account balance once |
| 12 | **Chart of accounts** opening-balance edit and `updateBalance` | edit ∥ postings: final = opening change + postings; equity adjustment once |
| 13 | **POS order** (`PosUtility`) | L (parallel POS orders, one cash account, one product), P (double submit), stock/FIFO/COGS/AR; I |
| 14 | **Order edit/cancel/status** (`orderEditUpdate`, `orderEditDelete`, `updateItemsStatus`, `update_delivery_status`, `bulkAssignCourier`) | cancel ∥ courier settle on the same order; P cancel ×3 (stock restocked once, accounts reversed once); partial-commit check for the two no-transaction methods |
| 15 | **Refund** admin screen over HTTP | the service-level parallel tests exist; add P over HTTP (`refund_pay` ×3) |
| 16 | **Console commands** (`ReverseSettlement`, `FixPartialSettlements`, `CleanupStatementLogs`, `AutoDepositFunds`, `FixPosCourierIncome`) | run concurrently with HTTP postings on the same account; behaviour decided under F-12 |
| 17 | **Number generators** (six types, F-10) | L: 4 parallel creations of each document type, all succeed with distinct numbers |
| 18 | **Early-return** methods (F-03) | refused request leaves `transactionLevel` unchanged and releases locks (second request is not delayed) |
| 19 | **D6** | different address/payment with the same cart within 15 s is accepted (after fix); identical resubmit is answered once; cart quantity changed between read and lock |
| 20 | **Real deadlock** for purchase update/delete, supplier payment, voucher, customer payment | R, asserting retry header and once-only effects |
| 21 | **File effects** (F-02) | I-once with upload on `storePurchaseReturn`, `updatePurchaseReturn`, `purchaseDelete`: exactly one file, old file kept on final failure |

## 6. Transaction redesign proposals (not implemented)

### 6.1 Purchase-order conversion (`PurchaseOrderController::convertToPurchase`)
Problem: it calls `PurchaseController::store` (its own commit, journalling and idempotency), then updates the PO status and received quantities in separate non-transactional statements; a failure in between leaves a purchase with an open PO and the next conversion creates a second purchase.

Proposal:
1. **Extract** the database part of `PurchaseController::store` into `PurchaseService::create(array $data, string $opKey): Purchase` that runs *inside the caller's transaction* (no commit, no upload, no response). The controller keeps validation, the upload (`RequestSideEffects::once` + undo) and the redirect.
2. **New `PurchaseOrderConversionService::convert(int $poId, array $data, User $by)`**, one `DB::transaction(..., 3)`:
   * `PurchaseOrder::lockForUpdate()` the PO; if `status = purchased`, return the existing purchase (found through a new nullable unique column `purchase_orders.converted_purchase_id`; schema change **needs approval**) without creating anything;
   * lock the supplier and accounts in a fixed order (supplier, then accounts by id);
   * call `PurchaseService::create` with `op_key = 'po-convert:{po_id}'` (the existing double-submit key table makes a repeat return the same purchase);
   * set PO status, received quantities and `converted_purchase_id` in the same transaction.
3. Journalling stays the existing outbox, which only fires after the outermost commit, so a conversion either commits purchase + PO + outbox rows together or nothing.
4. Add the route to `config/deadlock_retry.php` only after the above; the upload is made once per request.
5. **Tests:** P (convert ×3 → one purchase, PO purchased once); I once/always (nothing remains, PO still open on failure); R; X convert ∥ PO update/destroy; stock, FIFO layer, supplier due, cash, journal once.
6. **Rollout:** migration for the column (backfill: none); no flag; behaviour change for users: a second click returns the existing purchase instead of creating another (needs approval).

### 6.2 Customer-payment auto-import (`CustomerPaymentController::autoImport`)
Problem: loops over customers calling `store` (one commit each); `catch (\Exception)` hides every error including deadlocks; the response always says success; the retry middleware cannot see failures; re-running after a partial run is unsafe unless each customer is idempotent.

Proposal:
1. **Extract** `CustomerPaymentService::record(Customer, array $orderIds, int $accountId, int $methodId, string $date, string $key)` which does the database work in its own `DB::transaction(..., 3)`: lock the customer's unpaid orders (`lockForUpdate`, ordered by id), **recompute the due inside the lock**, skip (return `already_paid`) if nothing is due, create the payment, update the orders and account via the existing code, key = `cust-auto:{customer_id}:{sha1(sorted order ids)}` recorded in the idempotency table in the same transaction.
2. `autoImport` becomes a thin loop: no outer transaction (one per customer keeps locks short), catches only business exceptions; a deadlock that survives the 3 attempts is collected as `failed (retryable)` instead of being swallowed silently; the response carries `imported`, `already_paid` and `failed[]` (customer, reason, retryable) and `success = false` when anything failed, HTTP 207 if partly done.
3. Re-running is safe: finished customers return `already_paid`, only failed ones are processed again. For large batches move the per-customer call into a queued job with a unique job id (needs approval, since it changes how the screen reports).
4. **Not** a single transaction around the loop (long locks on the shared cash account, one failure rolls back everyone).
5. **Tests:** P (autoImport ×3 → every customer paid once), I (deadlock once on customer 2 → still imported once; always → listed under `failed`, others unaffected), L (shared account balance exact), crash between customers (kill the process) then re-run completes without duplicates; response shape test.

## 7. What needs your decision before any fix

1. F-01: approval to add lock-and-recheck guards to all reverse/delete screens (this is a behaviour change: a repeated request is answered "already reversed").
2. F-04: overdraft policy.
3. F-06: should purchase delete reduce the supplier's due.
4. F-07: the repeated-order rule (token vs time window, what must match).
5. F-12: may `CleanupStatementLogs` and similar maintenance commands run while the shop is trading.
6. 6.1 and 6.2: approval of the schema change and the new response shape.

## 8. Status

T-5 remains **implemented, not accepted**; this review recommends **not accepting** until F-01 and F-02 are fixed and re-tested. R1–R4 UNRESOLVED. Blocked, not complete: production engine/version output, fresh production backup, real admin theme, accountant review. **Stopped; waiting for your approval. No code was changed.**
