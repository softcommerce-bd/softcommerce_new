# T-5 deadlock retry coverage report

Status vocabulary: VERIFIED, IMPLEMENTED, TESTED, BLOCKED, REQUIRES BUSINESS APPROVAL, NOT APPLICABLE. Nothing is called covered without an executed test.

## 1. Mechanism (IMPLEMENTED, TESTED)
* `App\Http\Middleware\RetryDeadlockedCheckout` (alias `retry.deadlock`) is attached by `App\Support\DeadlockRetryRoutes` to every non-GET route of the controllers/methods in `config/deadlock_retry.php`; `PurchasingController` and the multi-commit paths are deliberately excluded.
* Retry only when: error is SQLSTATE 40001 / 1213 (`DeadlockRetry::isDeadlock`), the connection is back at the level the request started at, fewer than 3 attempts. Validation errors, business refusals and any other exception are never retried.
* **Changed in this phase:** a deadlock is now also recognised when the controller caught the exception and answered with a **redirect + flashed message** (the redirect has no body text). The middleware reads the flash keys written during the attempt, removes them before retrying, and leaves them in place when it gives up, so today's final-failure message is unchanged. Evidence: `DeadlockRetryFlashCoverageTest` (6 tests).
* **Changed in this phase:** a request that ends in an ordinary failure (non-deadlock exception, or a 4xx/5xx answer) now removes the files it uploaded (`RequestSideEffects::undoAll`). ASSUMPTION: no screen answers 4xx/5xx after its transaction committed.
* Files: uploads once per request; replaced/deleted files only `afterCommit` (see `DeadlockFileEffectsTest`).
* Maximum attempts: 3 at the request level. Service-level `Idempotency::once` retries (3) are on separate routes (no stacking). `CourierSettlement::createNumbered` / `SupplierPayment::createNumbered` retry only a unique-number clash (max 5) inside their own savepoint.

## 2. Methods (the 40 transactional methods currently listed; generated from the controllers)
| # | Method (transactional, in `config/deadlock_retry.php`) | Error answer | Executed deadlock test |
|---|---|---|---|
| 1 | `PurchaseController::purchaseUpdatePayment` | redirect + flash (detected via flash since T-5) | NOT EXECUTED |
| 2 | `PurchaseController::store` | JSON error with message (detected by text) | DeadlockWorkflowRetryTest (once, always), RealDeadlockHttpTest (real) |
| 3 | `PurchaseController::update` | JSON error with message (detected by text) | DeadlockWorkflowRetryTest (always, document kept) |
| 4 | `PurchaseController::storePurchaseReturn` | JSON error with message (detected by text) | DeadlockFileEffectsTest (once, always) |
| 5 | `PurchaseController::updatePurchaseReturn` | JSON error with message (detected by text) | DeadlockFileEffectsTest (always) |
| 6 | `PurchaseController::purchaseDelete` | redirect + flash (detected via flash since T-5) | DeadlockFileEffectsTest (once, always) |
| 7 | `PurchaseOrderController::purchaseUpdatePayment` | redirect + flash (detected via flash since T-5) | NOT EXECUTED |
| 8 | `PurchaseOrderController::store` | JSON error with message (detected by text) | NOT EXECUTED |
| 9 | `PurchaseOrderController::update` | exception propagates (detected) | NOT EXECUTED |
| 10 | `SupplierPaymentController::store` | JSON error with message (detected by text) | DeadlockWorkflowRetryTest (once) |
| 11 | `SupplierPaymentController::update` | JSON error with message (detected by text) | NOT EXECUTED |
| 12 | `SupplierPaymentController::destroy` | JSON error with message (detected by text) | NOT EXECUTED |
| 13 | `CustomerPaymentController::store` | JSON error with message (detected by text) | NOT EXECUTED |
| 14 | `CustomerPaymentController::destroy` | JSON error with message (detected by text) | NOT EXECUTED |
| 15 | `StockAdjustmentController::store` | JSON error with message (detected by text) | DeadlockWorkflowRetryTest (once, always) |
| 16 | `StockAdjustmentController::destroy` | JSON error with message (detected by text) | NOT EXECUTED |
| 17 | `CourierSettlementController::settle` | JSON error with message (detected by text) | DeadlockWorkflowRetryTest (once, always), RealDeadlockHttpTest (real) |
| 18 | `CourierSettlementController::unsettle` | other | NOT EXECUTED |
| 19 | `OrderController::updateItemsStatus` | JSON error with message (detected by text) | NOT EXECUTED |
| 20 | `OrderController::store` | other | DeadlockWorkflowRetryTest (once, always), RealDeadlockHttpTest (real), DeadlockSideEffectTest |
| 21 | `OrderController::orderEditUpdate` | redirect + flash (detected via flash since T-5) | NOT EXECUTED |
| 22 | `OrderController::orderEditDelete` | JSON error with message (detected by text) | NOT EXECUTED |
| 23 | `OrderController::processUpdateItemsStatus` | JSON error with message (detected by text) | NOT EXECUTED |
| 24 | `SupplierController::processPayment` | JSON error with message (detected by text) | NOT EXECUTED |
| 25 | `VoucherController::store` | redirect + flash (detected via flash since T-5) | DeadlockRetryFlashCoverageTest (once, always) |
| 26 | `VoucherController::update` | redirect + flash (detected via flash since T-5) | NOT EXECUTED |
| 27 | `VoucherController::destroy` | JSON error with message (detected by text) | NOT EXECUTED |
| 28 | `ExpensePaymentController::store` | JSON error with message (detected by text) | NOT EXECUTED |
| 29 | `ExpensePaymentController::update` | redirect + flash (detected via flash since T-5) | NOT EXECUTED |
| 30 | `ExpensePaymentController::destroy` | JSON error with message (detected by text) | NOT EXECUTED |
| 31 | `IncomeController::store` | redirect + flash (detected via flash since T-5) | DeadlockRetryFlashCoverageTest (once, always) |
| 32 | `IncomeController::update` | redirect + flash (detected via flash since T-5) | NOT EXECUTED |
| 33 | `IncomeController::destroy` | JSON error with message (detected by text) | NOT EXECUTED |
| 34 | `BankTransactionController::deposit` | JSON error with message (detected by text) | NOT EXECUTED |
| 35 | `BankTransactionController::withdraw` | JSON error with message (detected by text) | NOT EXECUTED |
| 36 | `BankTransactionController::transfer` | JSON error with message (detected by text) | NOT EXECUTED |
| 37 | `BankTransactionController::chequeClearing` | JSON error with message (detected by text) | NOT EXECUTED |
| 38 | `BalanceTransferController::store` | redirect + flash (detected via flash since T-5) | DeadlockRetryFlashCoverageTest (once, always) |
| 39 | `BalanceTransferController::update` | redirect + flash (detected via flash since T-5) | NOT EXECUTED |
| 40 | `BalanceTransferController::destroy` | JSON error with message (detected by text) | NOT EXECUTED |
**SUPERSEDED by `T5_FINAL_VERIFICATION_REPORT.md` section 1 (34 of 34 reachable methods tested; 4 BLOCKED; 2 NOT APPLICABLE).** Earlier counts: 40 transactional methods in the retry set; **12 had an executed deadlock test** (once and/or always; 3 also with a real, non-injected deadlock). **28 are IMPLEMENTED by the shared mechanism but NOT EXECUTED** individually: the mechanism is tested on three answer styles (JSON message, redirect+flash, exception), so a method that uses one of them is expected to behave the same, but that expectation is not evidence. Non-transactional listed methods and GET routes are NOT APPLICABLE.

## 3. Findings from Phase 0 that this report resolves
| Finding | Status |
|---|---|
| P0-2 flash+redirect answers were not retried (BalanceTransferController::store) | IMPLEMENTED, TESTED (6 tests; the same scenario now returns `X-Deadlock-Retries: 1`, one transfer, success flash) |
| The 11 flash+redirect methods | mechanism IMPLEMENTED for all 11; TESTED for BalanceTransfer::store, Income::store, Voucher::store, PurchaseController::purchaseDelete; the other seven (PurchaseController::purchaseUpdatePayment, PurchaseOrderController::purchaseUpdatePayment, OrderController::orderEditUpdate, VoucherController::update, ExpensePaymentController::update, IncomeController::update, BalanceTransferController::update) NOT EXECUTED |
| Methods listed but outside the retry because of design | `PurchaseOrderController::convertToPurchase`, `CustomerPaymentController::autoImport` (multi-commit, REQUIRES BUSINESS APPROVAL decisions 9/10); order status/payment updates that notify (decision 15); `OrderController::update_delivery_status`, `bulkAssignCourier`, `destroy` (no transaction, NOT TRANSACTIONAL, decision 15) |
| `BankTransactionController::{deposit,withdraw,transfer,chequeClearing}` | listed, but every call fails before posting: `BankTransaction::generateTransactionNo()` is not defined anywhere in the repository (never in history). BLOCKED: the number format is a business decision; no deadlock test is meaningful until it exists |

## 4. Real-process evidence
`RealDeadlockHttpTest` (3 tests, real 1213 raised by the database, request is the victim, retried once, effects once): courier settlement, legacy purchase store, admin order. All pass (concurrency suite, 72/72).
