# T-5 final completion report

**Verdict: T-5 is TECHNICALLY COMPLETE for everything an engineer can do without external inputs; it is NOT ready for production acceptance.** The remaining items are decisions, rulings and facts that only the owner, the accountant and the DBA can supply (section 5). No production or staging system was accessed, no money was moved, no flag was enabled, nothing was deployed or merged. `INVENTORY_CONFIRMED_ONLY=false` and the auto-deposit job stays paused.

Acceptance items by status (41): TESTED 26 - DONE 4 - DEFERRED 3 - BLOCKED 7 - NOT APPLICABLE 1. Test numbers: `T5_FINAL_TEST_EVIDENCE.md`. Migration numbers: `T5_DATABASE_MIGRATION_REPORT.md`. Deployment: `T5_FINAL_DEPLOYMENT_RUNBOOK.md`.

## 1. What was done in this mandate (each with tests; commits on the branch)
1. **R-01 MyISAM/InnoDB.** Root cause analysed (`T5_R01_MYISAM_INVESTIGATION.md`): the supplied backup keeps `purchases`, `purchase_products`, `suppliers`, `purchase_returns`, `purchase_return_products` on MyISAM, where transactions, row locks and rollback do not exist and a deadlock **retry would repeat the MyISAM writes**. Implemented: `TransactionalEngines` (single source of truth), **deadlock retry never used when a money/stock table is not InnoDB** (also when the engines cannot be read), an `engine.safe` route gate on purchasing/supplier-balance routes (policy `FINANCIAL_ENGINE_POLICY`, default `enforce`; selling paths are not refused because StockLedger already compensates), `php artisan db:engine-preflight`, `php artisan db:convert-engines` (dry run by default; exact-name confirmation; maintenance mode + plan reference on real databases; row-count and AUTO_INCREMENT validation; test-only `--revert`), and an engine-only SQL script. The hazard was reproduced in a test (MyISAM probe table gets two rows after a retry) and prevented. Rehearsed end to end on MySQL 8.4.13 with the backup's own data: conversion ~0.1 s, fingerprints identical, rollback rehearsed.
2. **R-03/R-04 auto-deposit.** Traced end to end (`T5_R03_R04_AUTO_DEPOSIT_TRACE.md`); the old body was incomplete beyond `created_by`: illegal ENUM values (stored blank), a charge expense written into columns that do not exist (linked to no expense account), account-id-67 guess, no statement lines. Implemented: the switch alone no longer runs anything - `AutoDepositReadiness` requires a configured **non-login** system user, legal ENUM values, InnoDB tables and no charged method; otherwise `AUTO_DEPOSIT_BLOCKED_NOT_READY`, exit 1, no transaction. The body now writes statement lines like the manual screen and uses the system user; charged methods are unsupported until an accounting ruling. Default stays paused. The ENUM step is a separate SQL file (`sqlupdates/t5_auto_deposit_schema.sql`, rehearsed with rollback), not a migration.
3. **Numbering (T-22).** The first number of a period for expense, transfer and voucher series (and adjustments) could deadlock on an index gap; the series lock is now taken **before** account locks (one global lock order). Parallel test passes; fails 3/3 on the old code.
4. **Supplier operations (T-10).** Supplier edit/delete re-read the row under its lock. This found a real money bug: three simultaneous deletes reversed Accounts Payable three times (-240 instead of -80). Business semantics of edit (decision 4) unchanged.
5. **Known failures fixed (all three):** (a) `FILESYSTEM_DRIVER` was read with `env()` in 30 places: unset (or under `config:cache`) uploads were treated as cloud storage and **deleted after saving** - now `config('filesystems.default')`; (b) a stale framework stub asserted a 200 on `/`, which redirects to `/login` by design; (c) a hard-deleted funnel left orders pointing at it when the schema lacks the foreign key - the model now clears the reference.
6. **Outbox, checkout, balance updates, numbering, supplier operations** re-reviewed with tests (section 3 of the evidence). T-07/T-09/T-10/T-11 are technically reviewed and TESTED; their written acceptance is the accountant's/order desk's.
7. **Staging readiness:** env template, runbook with preflight / maintenance window / conversion / migrations / smoke tests / reconciliation / monitoring / recovery, read-only reconciliation SQL (run on both engines), migration scripts with rollback.
8. **MySQL 8.4.13 and MariaDB 10.11.14:** identical results on the whole backend suite; the 8.4 container was destroyed after use.

## 2. Findings during this mandate (all fixed unless stated)
| ID | Finding | State |
|---|---|---|
| F-A | retry on MyISAM duplicates writes | fixed (guard) |
| F-B | config key set to `null` made the engine list empty -> every retry disabled | fixed + test (found by running the preflight on the real schema) |
| F-C | triple supplier delete reverses Accounts Payable three times | fixed |
| F-D | uploads deleted when `FILESYSTEM_DRIVER` is unset / config cached | fixed |
| F-E | auto-deposit body: ENUM blanks, nonexistent columns, no statements, id-67 guess | gated + partly rebuilt; charged methods unsupported |
| F-F | `inventory_v2_innodb_conversion.sql` also adds an unapproved unique key | header warning; engine-only script provided |
| F-G | tests/feature code attempt outbound SMS/courier calls (blocked by the sandbox proxy) | NOT fixed: use sandbox credentials on staging |
| F-H | two funnel admin UI specs fail on the stand-in theme | DEFERRED (theme) |

## 3. Acceptance matrix (honest status: DONE, TESTED, BLOCKED, DEFERRED, NOT APPLICABLE)
| ID | Requirement | Status | Evidence | Outstanding / external |
|---|---|---|---|---|
| T-01 | Checkout locks only the exact cart rows; identical resubmission returns the first order | TESTED | CartLockingParallelTest, AdminOrderParallelTest, StockConcurrencyTest; both engines | Owner: duplicate-identity token policy (DEC-05) |
| T-02 | 13 additive lookup indexes `idx_t5_*`; rollback drops only them | TESTED | MySQL 8.4 up/down x2 (`evidence/mysql84/04_*`); MariaDB suites | DBA: rehearsal on the masked production backup, then approval to apply |
| T-03 | Deadlock retry on every reachable state-changing method; **never on non-InnoDB tables** | TESTED | DeadlockRetryMethodCoverageTest (45) + EngineGuardTest hazard test (retry on MyISAM duplicates the write; guard prevents it) | - |
| T-04 | Repeated reversal/delete applies once (unsettle, payment delete, vouchers, expenses, income, transfers, purchases, **supplier delete**) | TESTED | ReversalParallelTest, WorkflowParallelCoverageTest (supplier delete x3: Accounts Payable reduced once; before the fix 3 times) | - |
| T-05 | File effects once; deletes after commit | TESTED | DeadlockFileEffectsTest | - |
| T-06 | No response inside an open transaction; refusals roll back first | TESTED | TransactionExitTest, NoResponseInsideOpenTransactionTest | - |
| T-07 | Overdraft rules re-checked under the account row lock | TESTED | OverdraftParallelTest | Accountant: policy per screen (DEC-03). Technical review done by engineer; written acceptance external |
| T-08 | Document numbers and settle locks (D1/D2/D5) | TESTED | CourierSettlementParallelTest, SupplierPaymentParallelTest | - |
| T-09 | Balance-delta writes (D3) | TESTED | parallel purchase/payment/transfer/voucher/expense/income tests; engineer review: all tested screens write deltas | Accountant: written acceptance (ACC-06) |
| T-10 | Supplier row locks (D4) incl. **supplier edit/delete re-read under lock** | TESTED | WorkflowParallelCoverageTest (edit vs delete x4 rounds, delete x3); mutation run on old code fails with -240 vs -80 | Accountant: edit semantics (DEC-04: absolute overwrite vs stale-form refusal) and written acceptance |
| T-11 | Checkout duplicate identity (cart-row identity via idempotency_keys) | TESTED | CartLockingParallelTest | Order desk: token design / acceptance (DEC-05) |
| T-12 | Supplier-payment edit defect fixed | TESTED | SupplierPaymentEditAccountingTest (8) + parallel | Accountant: historical drift review (ACC-01, N-1.2) |
| T-13 | POS cart isolation + duplicate guard | TESTED | PosCartIsolationTest, PosAndConsoleParallelTest | - |
| T-14 | Auto-deposit: paused by default (G1); switch alone is not enough (readiness: system user, legal ENUMs, InnoDB, no charged method); statement lines; race-safe | TESTED | AutoDepositPausedGuardTest, AutoDepositReadinessTest, PosAndConsoleParallelTest (5); `sqlupdates/t5_auto_deposit_schema.sql` rehearsed on 8.4 | Owner: AD-1 (stay paused or approve option a + schema step); accountant: charge booking (ACC-03); DBA: DBA-04 facts |
| T-15 | Income first-number deadlock (NumberSerializer) | TESTED | income tests x3 on both engines; hold/release tests | DBA: INC.1 confirmation of one-connection-per-request |
| T-16 | Bank screens retired (410), history read-only | TESTED | BankActionsRetiredTest, BankReferenceExampleTest | - |
| T-17 | No application write to generated column pending_quantity | TESTED | GeneratedQuantityTest on both engines | - |
| T-18 | Outbox: concurrent workers, kill at every boundary, deterministic killed-worker test | TESTED | OutboxVerificationTest; 30/30 x2 per engine | VER-04: load test with scheduler in a production-like environment |
| T-19 | MySQL 8.4.13 and MariaDB 10.11.14 verification (default + concurrency) | TESTED | `docs/evidence/final9/` | Production engine/version unknown (DBA-02) |
| T-20 | N-1 read-only investigation tooling | DONE | SupplierDriftQueriesTest; script run on both engines | Accountant + DBA: run on a restore (ACC-01) |
| T-21 | INC.1 DBA tooling | DONE | NumberLockSelfCheckTest; run on both engines | DBA: return the sheet (DBA-01) |
| T-22 | Number-series locks for expense, transfer, voucher, adjustment series (first number of a period) | TESTED | first-of-period parallel test (6 rounds x 3 series; fails 3/3 on old code); lock-order rule: series first, then accounts | Owner: SEQ.1 (document_sequences) remains the long-term design; decision 6 formats unchanged |
| T-23 | Five test categories for every operation | DEFERRED | `T5_REMAINING_WORKFLOW_COVERAGE_MATRIX.md`: no workflow has all five for all operations; new this phase: supplier edit/delete, first-of-period for 3 more series, auto-deposit paths | Tech lead: accept the documented gaps (purchase update parallel, order edit/delete parallel, chart-of-accounts destroy, console repair jobs) |
| T-24 | Scheduler only references defined commands | TESTED | SchedulerCommandsExistTest | DEC-10 (import job) |
| T-25 | Fingerprints and row counts unchanged by suites | TESTED | `final9/*fp*`, rowcounts | - |
| T-26 | **R-01 engine guard**: `db:engine-preflight`, `db:convert-engines`, retry never on non-InnoDB, purchasing gate (policy enforce), SQL scripts | TESTED | EngineGuardTest (7), ProductionShapeEngineGateTest (3) on the backup-shaped 8.4 copy; conversion + rollback rehearsal | DBA: conversion on staging/production restore (DBA-03); owner: policy stays enforce |
| T-27 | Known failing tests (UploadSafety svg, ExampleTest, Funnel attribution) fixed | DONE | all three pass; root causes: uploads treated as cloud when FILESYSTEM_DRIVER unset (file deleted), stale root-URL test, missing FK-equivalent null on hard delete | - |
| T-28 | `env('FILESYSTEM_DRIVER')` read outside config (30 places) replaced by `config('filesystems.default')` (config:cache would have made every upload look like cloud storage and delete it) | TESTED | UploadSafetyTest; full suites | Deployers: set FILESYSTEM_DRIVER explicitly |
| T-29 | Staging readiness: env template, runbook, migration scripts, preflight, smoke checklist, reconciliation SQL, monitoring/recovery | DONE | `T5_FINAL_DEPLOYMENT_RUNBOOK.md`, `docs/staging/env.staging.example`, `tests/staging/reconciliation_checks.sql` | Deployment itself: BLOCKED (no isolated staging environment/credentials available) |
| T-30 | Frontend: funnel unit tests, TypeScript check, ESLint (funnel) | TESTED | 60 passed; tsc exit 0; eslint exit 0 | - |
| T-31 | Frontend end-to-end, funnel editor + storefront (Playwright, Chromium via a Chrome-channel symlink, local Laravel + built Next.js on a disposable DB) | TESTED | 79 passed / 3 failed (first run 31 passed: the disposable e2e database lacked sales_funnel v4/v5 SQL; applied); `final9/frontend_e2e_funnel_editor.txt`, re-run of the failing specs `frontend_e2e_rerun_failures.txt` (templates spec passed on re-run: flaky; 2 still fail) | see T-33 |
| T-33 | Two funnel-admin UI specs failed on the disposable run. **Handover update:** the analytics one was a stand-in artifact and passes with a test-only stand-in theme (RESOLVED); the 'save as template' spec still fails at the phone-width funnels list (theme-dependent `aiz-table`) - DEFERRED; one intermittent storefront guest-checkout spec failed once under load (4/4 pass alone) - see `T5_E2E_DEFERRED_FAILURES.md` | DEFERRED | `final9/frontend_e2e_funnel_editor.txt`, `frontend_e2e_rerun_failures.txt` | Owner: theme package (P4); then re-run. Not related to money/stock; not triaged to a code defect |
| T-32 | Admin theme / real-theme UI verification (P4) | BLOCKED | theme package not supplied | Owner: theme package |
| EXT-1 | Production engine, version, sizes, topology, proxy (INC.1, P1) | BLOCKED | - | DBA |
| EXT-2 | Fresh backup + proven restore + rehearsal on the masked production backup (P2/P3) | BLOCKED | - | DBA |
| EXT-3 | Accountant rulings: N-1 results, pack D-01..D-09 (P5), T-2, T-3, ACC-03, D3/D4 acceptance | BLOCKED | - | Accountant |
| EXT-4 | Owner decisions DEC-01..DEC-20 (see register) | DEFERRED | - | Owner and named approvers |
| EXT-5 | P6 written approval to enable INVENTORY_CONFIRMED_ONLY on staging; final acceptance | BLOCKED | - | Owner |
| EXT-6 | P7 permissions with real staff roles; P8 baseline at activation | BLOCKED | - | Tech lead after restore; accountant at activation |
| EXT-7 | T-1 real-engine deadlock proof, T-4 outbox load | BLOCKED | - | DBA + tech lead (production-like environment) |
| N/A-1 | Bank cheque clearing, B-1.2..B-1.6 | NOT APPLICABLE | bank actions retired (B-1.1) | - |

## 4. Definition of done - checked
| Condition | State |
|---|---|
| All technically achievable items implemented | yes (section 3); DEFERRED/BLOCKED rows need external input |
| Critical database dependencies resolved or safely gated | **gated**: retry off + purchasing refused on non-InnoDB; conversion tooling rehearsed; the real production state is unknown (EXT-1/2) |
| Financial transaction safety verified | yes on both engines (suites, mutation checks, race tests) |
| Regression tests | 656 default + 100 concurrency pass on both engines; frontend: 60 unit, tsc, eslint, 79/82 e2e |
| Migrations and rollback tested | yes on disposable copies (engine conversion, index migration, ENUM step) |
| Staging readiness demonstrated | **documents and rehearsals only**: no isolated staging environment or credentials were available, so nothing was deployed there |
| No unresolved critical code defect | none known; see F-G (outbound calls from tests) as a configuration risk |
| Documentation matches implementation | updated: runbook, migration report, evidence, register, audit; `T5_CONSOLIDATED_DECISION_REGISTER.md` UPDATE 13 |
**Production readiness is NOT claimed.**

## 5. Complete list of remaining external dependencies
| # | Dependency | Who | Why it blocks | Evidence required |
|---|---|---|---|---|
| E1 | Production engine, version, storage engines, table sizes, topology/proxy (`inc1_db_facts.sql`, INC.1 sheet) | DBA | decides the conversion plan and whether the income-number lock is effective | outputs in `T5_INC1_DBA_HANDOFF.md` |
| E2 | Fresh backup + checksum + proven restore + rehearsal on the masked production backup | DBA | every schema step needs it | logs, fingerprints |
| E3 | Are the five purchasing tables MyISAM in production? Sizes? | DBA | R-01 window sizing | `db:engine-preflight`, size query |
| E4 | `balance_transfers.created_by` / ENUMs / active auto methods in production | DBA | R-03 (G1 may stop a working job) | queries in the trace doc |
| E5 | N-1 run on a restore; accountant pack rulings D-01..D-09; T-2, T-3, ACC-03 (bank charges), D3/D4 acceptance | Accountant | P5 and historical drift | signed templates |
| E6 | Owner decisions DEC-01..DEC-20 (register) incl. AD-1, SEQ.1, supplier-edit semantics, overdraft policy, checkout token | Owner + approvers | behaviour changes and schema changes | written rulings |
| E7 | Real admin theme package | Owner | P4, the 2 deferred UI specs | theme + re-run |
| E8 | Real staff roles from the restore | Tech lead after E2 | P7 | permission test log |
| E9 | An isolated staging environment + credentials (sandbox gateways only) | Owner/DBA | staging deployment and smoke tests | environment details |
| E10 | Production-like load for T-1 / T-4 | DBA + tech lead | not provable on a sandbox | load logs |
| E11 | P6 written approval; final acceptance | Owner | gate to enabling anything | signed |
