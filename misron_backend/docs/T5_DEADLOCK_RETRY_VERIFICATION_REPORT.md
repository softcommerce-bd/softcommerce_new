# T-5 Deadlock retry – verification report

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
