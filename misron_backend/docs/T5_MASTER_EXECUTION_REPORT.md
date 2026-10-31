# T-5 master execution report

Branch `claude/peaceful-planck-elqcki`. Disposable database: local MariaDB 10.11.14 only. **No deployment, merge, PR, flag change, or write to staging/production.** `INVENTORY_CONFIRMED_ONLY` stays `false` everywhere (it is switched on only inside isolated test processes). Classification words: VERIFIED, IMPLEMENTED, TESTED, BLOCKED, REQUIRES BUSINESS APPROVAL, NOT APPLICABLE.

## UPDATE - final verification assignment (supersedes the counts below where they differ)
Details: `docs/T5_FINAL_VERIFICATION_REPORT.md`; decisions: `docs/T5_CONSOLIDATED_DECISION_REGISTER.md`.
* **Retry coverage:** 34 of 34 reachable, working transactional methods now have an executed deadlock test (once and always); 4 bank actions BLOCKED (`BankTransaction::generateTransactionNo()` is defined nowhere; format not established, nothing invented); 2 methods NOT APPLICABLE (no route). Earlier figure "12 of 40" is superseded.
* **Parallel workflow evidence:** 17 of the 21 workflows have at least one passing real-process test; none is fully verified (see the table in the final report; sequential-only workflows 18 and 21; untested 13 and 16).
* **Defects found and fixed by the new tests:** flash notifier re-flash (extra retries), number reuse after delete (customer payment, stock adjustment), document-number races, ABBA transfer deadlocks, stale revert-and-reapply updates, customer-payment over-application, purchase-return payable drift, test-harness leakage. Open and not fixed (needs accounting): supplier payment edit corrupts supplier due/paid (F-17 / N-1).
* **Regression:** `vendor/bin/phpunit` 615 tests (only the 3 known unrelated failures); `phpunit.concurrency.xml` 85 tests OK (all real multi-process); fingerprints identical; migration up/rollback parity on 3 schema variants. MariaDB 10.11.14 only; MySQL 8.4 NOT EXECUTED; production BLOCKED.
* **Scheduler:** disabling `accounts:import-customer-payments` verified harmless; replacement requires decision 10.
* **Readiness:** code - partially ready; automated tests - not accepted as complete; staging - NO-GO; production - NO-GO.

## 1. Executive summary
This phase fixed the technical defects found in Phase 0 that need no business decision, and left every behaviour that does need one exactly as it was.
* **Cart locking (Phase 0 A):** the checkout locked the whole `carts` table (no index on `user_id`; a different customer's checkout timed out with error 1205). It now locks only the exact cart rows being ordered, by primary key in ascending order, and identifies a submission by its cart rows plus the chosen details, so a double click is one order while a new purchase, or a changed address/payment/coupon, is never answered with an earlier order. IMPLEMENTED, TESTED (4 real-process tests).
* **Deadlock retry coverage (B):** controllers that catch the exception and answer with a redirect + flash message were never retried. The middleware now recognises that answer, clears the stale flash before retrying and keeps today's message on final failure. IMPLEMENTED, TESTED for 4 of the 11 such methods, 12 of the 40 transactional methods at that time (now 34 of 34 reachable: see the update above).
* **Scheduler (C):** `accounts:import-customer-payments` never existed in the repository or its history; its behaviour cannot be established, so the schedule line is disabled (commented, with the reason) and a test now fails if the scheduler ever references an undefined command. IMPLEMENTED, TESTED; a replacement job REQUIRES BUSINESS APPROVAL (decision 10).
* **Schema (D):** migration `idx_t5_*` (13 additive non-unique indexes) built from verified schema facts, repeat-safe, with rollback; tested on three schema variants. No unique constraints, no engine conversion.
* **Reversals (review F-01):** unsettle, supplier-payment delete, purchase delete, income/expense/voucher/transfer/stock-adjustment/customer-payment delete now lock the document first, so a duplicate request waits and then gets the same refusal a sequential duplicate gets today; the books change once. TESTED in parallel for unsettle, supplier payment, purchase, income, transfer.
* **Files and transaction exits (F-02, F-03):** the three purchase paths that stored/deleted files inside the transaction now store once per request and delete only after commit; 23 refusal returns roll back before answering; failed requests remove their uploads. A static test keeps it that way.
* **Overdraft (F-04):** the existing "insufficient balance" rules are unchanged but also evaluated under the account's row lock (balance transfer, expense, bank, supplier payment); without this a request whose first check had gone stale overdrew the account (proved by a test that fails without the change).
* **Not done, and why:** sequence-table numbering, the new checkout token, file-operation queue, PO-conversion and auto-import redesign, supplier-edit semantics, purchase-delete supplier due, money-column conversion, MySQL 8.4 run, production facts, real-theme UI check — each needs a business decision, production information or infrastructure that does not exist here (section 11/12).

## 2. Files changed since the Phase 0 commit (`ab7931a`), application and test code
Application: `app/Console/Kernel.php`; controllers `BalanceTransfer`, `BankTransaction`, `CourierSettlement`, `CustomerPayment`, `ExpensePayment`, `Income`, `Order`, `Purchase`, `PurchaseOrder`, `StockAdjustment`, `Supplier`, `SupplierPayment`, `Voucher`; `app/Http/Middleware/RetryDeadlockedCheckout.php`. Migration: `database/migrations/inventory_v2/2026_11_04_100000_add_checkout_and_lock_lookup_indexes.php`. Tests (new): `tests/Architecture/NoResponseInsideOpenTransactionTest.php`, `tests/Concurrency/{CartLockingParallelTest,OverdraftParallelTest,ReversalParallelTest}.php`, `tests/Feature/Inventory/{DeadlockFileEffectsTest,DeadlockRetryFlashCoverageTest,TransactionExitTest}.php`, `tests/Feature/SchedulerCommandsExistTest.php`; changed: `tests/Concurrency/worker.php` (options `delay_ms`, `lock_wait`, action `delete_carts`). Script: `tests/staging/mysql84-rehearsal.sh`. Docs listed in section 14. 26 files, +982/−35 outside docs (git diff stat). Earlier-phase files are unchanged by this phase except as listed.

## 3. Migrations created
One (section 1 of `T5_MIGRATION_COMPATIBILITY_REPORT.md`). Database operations performed: that migration applied to the disposable `inv_v2_test`; three throw-away schema clones created and dropped (`t5_idx_test`, `t5_mig_misron_test_test`, `t5_mig_scstaging_test`; clones of `sc_staging` were schema-only); local root created these and granted the test user on them (local disposable server only); nothing else.

## 4. Defects corrected (each with evidence)
| ID | Defect | Status | Evidence |
|---|---|---|---|
| P0-1 | checkout cart lock locked every cart (error 1205 between customers) | IMPLEMENTED, TESTED | `CartLockingParallelTest::two_customers...` (B < 1.5 s while A held 2.5 s); before: mysql experiment (Phase 0 §5.3) |
| P0-2 | flash+redirect answers never retried | IMPLEMENTED, TESTED | `DeadlockRetryFlashCoverageTest` (6) |
| P0-3 | scheduler runs an undefined command | IMPLEMENTED, TESTED | `SchedulerCommandsExistTest` (fails on the old Kernel, passes now) |
| P0-4/5/6/7 | schema corrections | VERIFIED, documented | migration report §3 |
| P0-8 | "API order duplicates unverified" | **CORRECTED**: the API already takes a per-user named lock (`checkoutLockName`/`acquireCheckoutLock`) and an existing executed test (`StockConcurrencyTest`, 5 simultaneous guest orders → 1 order) covers it; the web checkout path (`CheckoutController`) is now protected by the new submission identity in `OrderController::store` (admin route tested; storefront controller path itself NOT EXECUTED) |
| P0-9 | customer payment over-application | NOT FIXED, NOT TESTED (no lock on the orders being paid; workflow 8) |
| F-01 | repeated reversals applied several times | IMPLEMENTED, TESTED | `ReversalParallelTest` (4 tests; before: probes cash −9880 / due +90) |
| F-02 | files inside retried transactions | IMPLEMENTED, TESTED | `DeadlockFileEffectsTest` (5) |
| F-03 | responses returned inside open transactions | IMPLEMENTED, TESTED | 23 sites; architecture test (fails on the old code) + `TransactionExitTest` (2) |
| F-04 | overdraft checks stale | IMPLEMENTED, TESTED | `OverdraftParallelTest` (3; the two stale-check tests fail without the change: 2 transfers / 2 expenses instead of 1) |
| New | `BankTransaction::generateTransactionNo()` is called but undefined: bank deposit/withdraw/transfer/cheque-clearing cannot work | VERIFIED; BLOCKED (number format = business decision; not invented) |

## 5. Features implemented
Row-scoped checkout lock and submission identity (`idempotency_keys` scope `checkout_cart`, used only where the table exists); flash-aware retry; document-first locking; after-commit file deletion; refusal rollbacks; locked overdraft re-check; index migration; scheduler guard test; rehearsal script. **No new accounting entry, opening balance, stock quantity or historical cost was created or computed.**

## 6. Test results (exact)
* `vendor/bin/phpunit`: **570 tests, 3676 assertions, 1 error + 2 failures (pre-existing), 2 skipped**.
* `vendor/bin/phpunit -c phpunit.concurrency.xml`: **72 tests, 1839 assertions, OK** (69 before the F-04 tests, 61 at the start of this phase).
* Details and the per-file list: `T5_TEST_MATRIX_AND_RESULTS.md`; logs: `docs/evidence/final/`.

## 7. Concurrency results
Real parallel processes: two customers concurrent (no blocking), identical submission ×4 → 1 order, changed address → 2nd order, vanished cart → no order and no stock change, lock-wait timeout → clean failure with one order, unsettle ×3 → one reversal, supplier payment delete ×3 → one, purchase delete ×3 → one, income and transfer delete ×3 → one, stale overdraft checks refused, plus the earlier phases' purchasing, chat, courier and three real-deadlock tests (retried once, effects once). Database fingerprints identical before/after.

## 8. Database engine used
MariaDB 10.11.14 (local, disposable). MySQL 8.4: **NOT EXECUTED**. Production: **BLOCKED**.

## 9. Schema compatibility
`T5_PHASE0_SCHEMA_COMPATIBILITY.md` (VERIFIED locally) and `T5_MIGRATION_COMPATIBILITY_REPORT.md`. Production compatibility BLOCKED; no table converted.

## 10. Existing failures (not caused by this work)
`ExampleTest::testBasicTest`, `FunnelEditorMediaAndCatalogueTest::test_orders_carry_nullable_funnel_attribution`, `UploadSafetyTest::test_svg_and_xml_are_accepted_from_staff_only`.

## 11. Business decisions still pending (all 15; none taken)
See `T5_PHASE0_BUSINESS_DECISIONS.md`. How each gates work: **1** reversal replay wording/tokens (the lock-first foundation is in, wording unchanged); **2** purchase delete vs supplier due (behaviour unchanged, observed mismatch); **3** overdraft policy (existing rules kept, only made race-free; no rule added); **4** supplier edit semantics (not touched); **5** checkout token / retention (the cart-row identity foundation is in; token and 15-second rule not used); **6/7** numbering and sequence table (not started); **8** maintenance commands; **9** PO conversion; **10** auto-import + the dead scheduled command; **11** retention/alerts; **12** read-only audits on a production copy; **13** money columns; **14** unique constraints; **15** order cancel/delete screens and notifications. Also needed: the bank transaction number format (new, above).

## 12. Production and staging blockers
Production engine/version/table engines/indexes/FKs/scheduler/storage driver; fresh production backup; isolated restore for the data audit; real admin theme; checkout payment-view template location; accountant review; MySQL 8.4 server; owner approval for any rollout. Staging: not touched, not activated.

## 13. Remaining technical risks
1. Of 40 transactional retry methods, 28 have no executed deadlock test. 2. Workflows 5–7, 9, 11–16 of the 21-workflow baseline have no parallel/double-submit tests. 3. Customer payment over-application (P0-9) open. 4. `OrderController::update_delivery_status`, `bulkAssignCourier`, `destroy` have no transaction. 5. Upload cleanup on 4xx/5xx assumes no screen fails after committing. 6. `checkout_cart` identity keys are never pruned (decision 11). 7. Legacy-ledger journalling still happens after commit (R4: the outbox recovers it; UNRESOLVED until real-engine rehearsal and accountant review). 8. Inventory V2, purchasing/FIFO, refund, outbox and historical-cost designs from earlier phases were re-run (suite above) but not changed; R1–R4 remain UNRESOLVED; wallet top-up/spending journalling is still missing (prerequisites T-2). 9. UI/mobile/theme verification not performed. 10. Idempotency table must exist where the checkout identity is used; where it does not exist the identity is skipped (documented fallback).

## 14. Documentation delivered / updated
This report; `T5_DEADLOCK_RETRY_COVERAGE_REPORT.md`; `T5_TEST_MATRIX_AND_RESULTS.md` (also serves as the concurrency report); `T5_MIGRATION_COMPATIBILITY_REPORT.md` (also the migration plan); `T5_DEPLOYMENT_ROLLBACK_RUNBOOK.md`; Phase 0 reports (corrections appended); `STAGING_ACTIVATION_PREREQUISITES.md` row T-5. The other requested reports (inventory reconciliation, accounting reconciliation, outbox recovery, refund verification, historical FIFO, staging acceptance, production readiness) already exist from earlier phases (`TECHNICAL_RISK_VERIFICATION_REPORT.md`, `HISTORICAL_ORDER_COSTS.md`, `STAGING_ACCEPTANCE_REPORT.md`, accountant pack); they were **not re-issued** in this phase and no new reconciliation figures were produced.

## 15. Git
Branch `claude/peaceful-planck-elqcki`; this phase's commits: `fdce744`, `dbf106f`, `9c0eda8`, `1fef815`, `052206d` (+ the commit containing this report). Not merged, no PR.

## 16. Readiness decisions
| Area | Decision |
|---|---|
| Code implementation readiness | **Partially ready**: everything that needs no business decision and no production fact is implemented; items in §11/§13 are open |
| Test acceptance readiness | **Not ready**: new work is tested, but 28/40 retry methods and most of the 21 workflows have no executed test, and MySQL 8.4/UI were not run |
| Staging readiness | **NO-GO**: needs the owner's approval, decisions 1–15 where they gate, the index migration rehearsal on a production-shaped copy, accountant review |
| Production readiness | **NO-GO**: production facts BLOCKED; R1–R4 UNRESOLVED |

## 17. Exact next action required from you
1. Provide the production database `SELECT VERSION()…` output and the section-0 listing of `docs/T5_PHASE0_AUDIT_QUERIES.sql` (or an authorised restore of a backup).
2. Answer decisions 1–5, 8–10 and the bank-transaction number format (these unlock the remaining implementation).
3. Say whether a MySQL 8.4 server can be provided for `tests/staging/mysql84-rehearsal.sh`.
