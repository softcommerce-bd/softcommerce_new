# T-5 Deadlock retry – verification report (second edition: real-process HTTP verification)


---

## 0. Second edition – final targeted verification (this is the part to review)

**Status: T-5 is IMPLEMENTED, NOT ACCEPTED.** Production NO-GO; staging NOT activated; no flag enabled outside isolated test processes; staging fingerprint identical; nothing merged or deployed; no historical record touched. R1–R4 still UNRESOLVED. The checks ran on the isolated MariaDB 10.11 test database `inv_v2_test` only.

### 0.1 What you asked for and where it is proven

| # | Request | Evidence (all pass) |
|---|---|---|
| 1 | Authenticated real-process parallel tests, admin create-order | `AdminOrderParallelTest` (2): a double submit by one admin ×4; five admins racing for the last units |
| 2 | Courier settlement | `CourierSettlementParallelTest` (2): same order ×4 admins; four orders into one account |
| 3 | Legacy purchase and goods receipt | `PurchasingParallelTest` (4): same purchase ×4; four different purchases; purchases racing with orders; three goods receipts + two repeats. `SupplierPaymentParallelTest` (2) for the payment screen |
| 4 | Duplicates cannot create duplicate orders, stock movements, supplier payments, journal entries | assertions in the tests above: one order / one settlement / one purchase / one layer / one payment / one journal entry per key; `assertJournalSettles` checks no entry is journalled twice |
| 5 | FIFO costing and supplier balances preserved | orders racing for 6 units cost exactly 2x400 + 4x410 (oldest layer first); purchases racing with orders leave layers = shelf and cost 4x100; supplier due = exact sum of 4 racing purchases; cash = exact sum of 4 payments |
| 6 | Retries never repeat external side effects | `DeadlockSideEffectTest` (7): admin order, legacy purchase (upload), supplier payment (attachment), chat order, courier settlement produce identical Http/Mail/Notification/Queue/Bus/event/file effects with and without a deadlock; a positive control proves the harness would count a repeated call; a static audit of every retried controller method in `config/deadlock_retry.php` finds no outgoing call, mail, notification, SMS, event or dispatch of their own |
| 7 | PO conversion and customer-payment auto-import | documented as excluded, section 0.4 |
| 8 | Full suite and concurrency suite, not overlapping | section 0.5 |
| 9 | Staging fingerprints unchanged | section 0.5 |

**Real deadlocks, not injected** (`RealDeadlockHttpTest`, 3): a helper process takes a row and then wants one the request already holds, while the request is paused on purpose after its first lock, so the database itself raises 1213 against the request. In each of the three runs (and in 3 of 3 repetitions) the middleware retried exactly once (`X-Deadlock-Retries: 1`) and: courier settlement settled once (cash, COGS, statement log, journal once); the legacy purchase exists once (one purchase, one layer, stock +5, supplier due once, cash once, one double-submit key); the admin order took stock and FIFO layers once. The helper completed in every case.

### 0.2 DEFECTS the parallel tests found in the existing screens, and the fixes

The fault-injection tests of the first edition could not see these. Each was reproduced failing before it was fixed.

| # | Defect (real parallel requests) | Fix | Files |
|---|---|---|---|
| D1 | Concurrent courier settlements of different orders: all but one failed with a duplicate `settlement_no` (3 of 3 runs before the fix); the number was read, not reserved | `CourierSettlement::createNumbered` (own savepoint, locking read, retry on the unique-index clash, max 5) | `app/Models/CourierSettlement.php` |
| D2 | `settle()` read the order without a lock, so a double settlement was prevented only by accident (the colliding number in D1) | order row and every account the settlement can touch are locked by primary key, in id order, as the transaction's first statements | `CourierSettlementController.php` |
| D3 | **Account balance lost updates**: legacy screens read `current_balance`, add in PHP and save the absolute number; 4 parallel purchases paid 40 from the cash account instead of 10 x 4 (990 instead of 960) | `ChartOfAccount::performUpdate` now writes the CHANGE (`current_balance = current_balance + delta`) through the ledger-bridge increment, so concurrent postings all count and the outbox records the exact delta. **This is the widest change in this edition: it affects every legacy screen that saves a balance. A single request ends with the same value as before.** Absolute "set the balance to X" writes become "apply the difference". | `app/Models/ChartOfAccount.php` |
| D4 | **Supplier balance lost updates**: 4 parallel purchases left `due_amount` at 20 instead of 460 | supplier rows read with `lockForUpdate` in purchase store/update/delete/return, supplier payment store/update/delete, PO store/update/convert paths and `SupplierController@processPayment`; the payment account is locked before the funds check | `PurchaseController`, `SupplierPaymentController`, `SupplierController`, `PurchaseOrderController` |
| D5 | Payments to different suppliers at once collided on `payment_no` (`PAY-000001` twice) | `SupplierPayment::createNumbered` (same pattern as D1) | `app/Models/SupplierPayment.php`, `SupplierPaymentController.php` |
| D6 | **Admin create-order double submit created 4 orders** for one cart (the screen does not empty the cart) | cart rows locked first; an identical cart (same products, variants, quantities) placed by the same user within 15 seconds is answered with the earlier order instead of a new one. **Behaviour change: a deliberate repeat of an identical order must wait 15 seconds.** | `OrderController.php` |

D3 and D6 change behaviour and should be reviewed by someone who knows how the accountants and order desk use these screens.

### 0.3 Files changed in this edition (under `misron_backend/`)

Application: `app/Models/ChartOfAccount.php`, `app/Models/CourierSettlement.php`, `app/Models/SupplierPayment.php`, `app/Http/Controllers/{CourierSettlementController,OrderController,PurchaseController,PurchaseOrderController,SupplierController,SupplierPaymentController}.php`.

Tests (new): `tests/Concurrency/{HttpWorkflowCase,AdminOrderParallelTest,CourierSettlementParallelTest,PurchasingParallelTest,SupplierPaymentParallelTest,RealDeadlockHttpTest}.php`, `tests/Feature/Inventory/DeadlockSideEffectTest.php`; changed: `tests/Concurrency/worker.php` (`http_as` with pause window and header capture, `lock_then_lock`).

Evidence: `docs/evidence/verification/{phpunit_full_t5b,phpunit_concurrency_t5b,fingerprint_t5b_test_db,fingerprint_t5b_staging}.txt`.

### 0.4 Paths deliberately excluded, and why they need a separate transaction redesign

**`PurchaseOrderController@convertToPurchase`.** It calls `PurchaseController::store` (which opens, commits and journals its own transaction) and then, outside any transaction, marks the PO `purchased` and writes the received quantities line by line. Failure modes: (a) a deadlock or error after the purchase committed leaves a purchase with the PO still open, so the user converting again creates a **second purchase** (stock, FIFO layer, supplier due and journal doubled); (b) wrapping the whole request in a retry would repeat the committed purchase; (c) wrapping it in one outer transaction would turn the inner commit into a savepoint and change when the outbox journals. A safe design needs the purchase logic extracted to a service that can run inside the caller's transaction, one transaction covering purchase + PO status + received quantities, and an idempotency key derived from the PO id so a repeat returns the existing purchase. Until then a deadlock here shows the user an error and must be checked by hand for a purchase that already exists.

**`CustomerPaymentController@autoImport`.** It loops over customers and calls `store` for each one, each its own commit, and its `catch (\Exception)` swallows every error into a list while the response always says `success: true`. Failure modes: (a) a deadlock on one customer is never seen by the retry middleware (it is caught and hidden), so that customer is silently skipped; (b) retrying the whole request is unsafe while it is half-done unless every customer's payment is individually idempotent; (c) the response cannot tell the operator which customers failed except by reading the `errors` list. A safe design is one idempotency key per customer (customer + the set of order ids paid), a per-customer bounded retry that rethrows deadlocks instead of swallowing them, and a result that distinguishes imported / already-paid / failed.

Also not retried (unchanged from the first edition): order status/payment updates (they send notifications and SMS after saving), anything that calls a courier or payment API, and `PurchasingController` (already retries inside `Idempotency::once`).

### 0.5 Execution record (run one after the other; nothing overlapped, checked with `pgrep` before each)

| Run | Result |
|---|---|
| Full PHPUnit | 556 tests, 3 failures, the same pre-existing three (`ExampleTest`, `FunnelEditorMediaAndCatalogueTest::test_orders_carry_nullable_funnel_attribution`, `UploadSafetyTest::test_svg_and_xml_are_accepted_from_staff_only`); no new failure. Output: `phpunit_full_t5b.txt` |
| Concurrency suite | **61 tests, 1695 assertions, all pass** (48 before + 13 in this edition). Output: `phpunit_concurrency_t5b.txt` |
| Each new parallel test file | repeated 2-5 times; stable |
| Test-database fingerprint (`inv_v2_test`) | financial/stock section identical to the previous verification; all new tables at 0 rows (outbox, journal, receipts, settlements, supplier payments, adjustments...) |
| `sc_staging` fingerprint | **identical** to `/tmp/claude-0/staging/fp_final.txt` |

### 0.6 Limitations added by this edition

1. **MariaDB 10.11 only.** The production engine/version is still blocked; victim selection, lock wait and error text may differ on the real engine.
2. The three real-deadlock tests stage one specific lock cycle each with a deliberate pause; they prove the retry path on a real 1213, not that every possible cycle is covered.
3. Parallel tests cover the creation paths. **Purchase update/delete, purchase returns, vouchers, expenses, income, bank transactions, transfers and customer payments were not run as parallel HTTP requests.** D3 (balance writes) and D4 (supplier rows) were applied where the controllers read them, but those screens have only the first-edition injection tests plus the full-suite regression.
4. Non-account read-modify-write rows other than suppliers (for example customer or wallet balances in other screens) were not audited one by one.
5. After a burst of parallel postings the journalling of some groups may still be pending for seconds; the scheduled `ledger:process-outbox` completes it (the tests run it and then require zero waiting, balanced trial balance and no duplicated entry). That is the designed R4 recovery path and is unchanged.
6. The static audit of external calls looks one level deep (the method body, not the helpers it calls); helpers are covered by the behavioural side-effect tests, which count only effects that go through the framework (Http, Mail, Notification, Queue, Bus, events, Storage). A raw curl call elsewhere would not be seen.

### 0.7 Status

T-5: **implemented, not accepted** (awaiting your review, real-engine confirmation and accountant review of D3/D4). R1–R4: **UNRESOLVED**. Blocked and not complete: production engine/version output, fresh production backup, real admin theme, accountant review. **Stopped; waiting for your explicit approval. No staging activation.**

---

## First edition (previous report, kept for the record)

**Decision unchanged: PRODUCTION NO-GO. Staging activation NOT authorized.** All four technical risks (R1 stale-statistics deadlocks, R2 wallet-only refunds, R3 historical uncosted lines, R4 journal posting after legacy commit) remain **UNRESOLVED** until real-engine and accountant verification. T-5 is *implemented and tested on the isolated MariaDB 10.11 test database only*; it is not accepted by anyone but the author.

Nothing here touched staging or production, no feature flag was enabled outside isolated test processes (`INVENTORY_CONFIRMED_ONLY` stays false everywhere), no historical record was corrected, nothing was merged or deployed.

## 1. Design (what "safe retry" means here)

| Requirement | Implementation |
|---|---|
| Max three attempts | `DeadlockRetry::MAX = 3`. One retry layer per path (no layers stacked), so the total never exceeds 3. |
| Only after a safe rollback | `DeadlockRetry::canRetry`: attempt < 3, transaction level back at the level where the work started, and the error (walking the previous chain) is SQLSTATE 40001 / 1213. Inside a caller's open transaction nothing is retried (Laravel itself refuses; the middleware rolls back to its entry level and does not retry). |
| No repeated side effects | Uploads are made **once per request** (`RequestSideEffects::once`) and undone if the request finally fails; deletion of old files moved to `DB::afterCommit`; session writes in admin create-order moved after the transaction; no retried path calls a courier/payment API, SMS or notification. Routes that do are not retried. |
| Idempotency preserved | Idempotency keys are written inside the same transaction, so a rolled-back attempt also rolls back its key; chat orders return the existing order for a key that already committed. |
| No partial remains | Proven by "always deadlock" tests: exactly 3 attempts, then a fingerprint of rows, balances, outbox, journal, statement logs and files is identical to before. |

## 2. Files changed (all under `misron_backend/`)

New: `app/Support/DeadlockRetry.php`, `app/Support/RequestSideEffects.php`, `app/Support/DeadlockRetryRoutes.php`, `config/deadlock_retry.php`, `tests/Feature/Inventory/DeadlockInjector.php`, `tests/Feature/Inventory/DeadlockWorkflowRetryTest.php`, `tests/Feature/Inventory/DeadlockRouteCoverageTest.php`, `tests/Concurrency/ChatOrderConcurrencyTest.php`, `tests/Concurrency/CommittedDeadlockRetryTest.php`.

Changed: `app/Http/Middleware/RetryDeadlockedCheckout.php` (3 attempts, detects deadlock from exception *or* converted response, rolls back to entry level, undoes side effects on final failure), `app/Providers/RouteServiceProvider.php` (attach middleware after routes exist), `app/Http/Controllers/{PurchaseController,SupplierPaymentController,PurchaseOrderController,OrderController}.php`, `app/Services/Chat/ChatOrderService.php` (bounded loop, take released before each retry), `app/Services/Inventory/{DemandService,OrderStockTransitions,Idempotency}.php` and `app/Services/Purchasing/PurchaseOrderService.php` (`DB::transaction(..., 3)`), `tests/Funnel/FunnelTestCase.php` (base-level hook), `tests/Concurrency/worker.php` (`http_as`, `chat_place`).

## 3. Coverage

| Workflow | Retry | Notes |
|---|---|---|
| Admin create-order (`OrderController@store`) and line/address edits | middleware | session writes after the transaction |
| Chat orders (`ChatOrderService::place`) | in service | stock take released before each retry |
| Courier settle / unsettle | middleware | |
| Legacy purchases, supplier payments, PO store/update/destroy/payment, customer payments store/destroy, stock adjustments, vouchers, expense payments, income, bank transactions, balance transfers | middleware (`config/deadlock_retry.php`) | uploads once + undo; old files deleted after commit |
| v2 demand / order stock transitions / PO service / `Idempotency::once` (goods receipt, supplier ledger, journal) | `DB::transaction(..., 3)` | `PurchasingController` deliberately **not** wrapped again |
| Storefront API, POS, funnel checkout | middleware (earlier phase) | |

**Deliberately not retried** (documented, not hidden): `PurchaseOrderController@convert` and `CustomerPaymentController@autoImport` (several commits per request); order status/payment updates (they send notifications/SMS); anything calling courier or payment gateway APIs; `PurchasingController` (already retries inside `Idempotency::once`).

### Audit of other transaction paths
All 75 `DB::transaction`/`beginTransaction` sites were classified: covered above, nested inside a covered caller, or excluded for one of the reasons above. Findings that remain exposures rather than fixes: the excluded multi-commit paths and notification-sending status updates can still fail on a deadlock (the user sees an error and repeats the action; nothing partial is committed by a single failed statement, but a multi-commit path can stop between its commits). Funnel publish/draft services, `InboundProcessor::message`, `ChartOfAccountController` and `StockLedger` internals run inside their callers' transactions or are not inventory/accounting writers.

## 4. Tests executed and results

| Suite | Result |
|---|---|
| Fault injection, all workflows (`DeadlockWorkflowRetryTest`, 12) – admin create-order, chat order, courier settle, legacy purchase (+ upload), purchase-update keeps old document, supplier payment with attachments, stock adjustment; each *once* (succeeds, one of everything) and *always* (exactly 3 attempts, world unchanged) | **12/12 pass** |
| Committed-data service retry (`CommittedDeadlockRetryTest`, 2) – goods receipt: once → one receipt, one layer, one journal entry, replay changes nothing; always → 3 attempts, nothing left, idempotency key also rolled back | **2/2 pass** |
| Real parallel processes, chat (`ChatOrderConcurrencyTest`, 2) – same key ×4 → one order, stock/FIFO taken once; 5 different keys on 6 units of 2 → exactly 3 win, no oversell, layers match shelf, no deadlock leaked | **2/2 pass** |
| Route coverage (`DeadlockRouteCoverageTest`, 2) – every configured write route has the middleware (about 70 route assertions), excluded ones do not | **2/2 pass** |
| Full PHPUnit | 549 tests, **3 failures, all pre-existing and unrelated**: `ExampleTest`, `FunnelEditorMediaAndCatalogueTest::test_orders_carry_nullable_funnel_attribution`, `UploadSafetyTest::test_svg_and_xml_are_accepted_from_staff_only` |
| Concurrency suite (`phpunit.concurrency.xml`) | **48/48 pass**, 1475 assertions (includes earlier deadlock, outbox, refund, mixed-activity and stale-statistics suites). One earlier run showed 24 failures because I accidentally ran two copies at once; the clean re-run passed. |
| Fingerprint, `inv_v2_test` | financial/stock counts identical to the previous verification; all new tables back to 0 rows |
| Fingerprint, `sc_staging` | **identical** to `/tmp/claude-0/staging/fp_final.txt` (untouched) |

## 5. Remaining limitations (honest)

1. **Only MariaDB 10.11 was tested.** The production engine/version output is still **blocked**; InnoDB lock/deadlock behaviour and the deadlock error text may differ on the real engine.
2. Deadlocks are produced by **injection** (statement runs, then a 40001/1213 error is raised, with a savepoint rollback emulating the server). Real deadlocks were verified only for the earlier service-level paths (`lock_pair`, storefront, outbox). Real parallel-process tests exist for chat placement only in this phase; **admin create-order, courier settlement and legacy purchase screens were not run as parallel real HTTP processes** (the `http_as` worker action exists but needs authenticated form scaffolding I did not build). Those three are covered by fault injection and the route test.
3. The middleware recognises a deadlock from the exception or from the response text; a handler that swallows the error into a custom message would not be retried.
4. No retry is possible inside a caller's already open transaction; such paths fail with the deadlock error and roll back.
5. Excluded paths in section 3 remain unprotected, by design.
6. Wrapped-transaction tests cannot show Laravel's attempts loop for nested transactions; that is why the committed-data tests exist.

## 6. Status of the four risks

R1, R2, R3, R4: **UNRESOLVED**. Blocked inputs (production engine/version, fresh production backup, real admin theme, accountant review) remain blocked. `docs/STAGING_ACTIVATION_PREREQUISITES.md` T-5 row updated to "implemented in test, awaiting your acceptance".

**Stopped. Awaiting explicit approval; no staging activation will be performed.**
