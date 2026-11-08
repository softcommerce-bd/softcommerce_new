# T-5 final closure-readiness audit

**Date:** 2026-11-08 · **Commit audited:** `a967de0` on `claude/peaceful-planck-elqcki` (identical to origin; working tree clean; no stashes) · **Auditor:** Claude (read-only audit; no code changed, no database written, no production/staging access, nothing deployed, merged or enabled).

## 1. Verdict
**T-5 cannot be closed and staging acceptance cannot be granted today. The technical remediation that can be done without a person's decision is complete and verified on MariaDB 10.11.14 and MySQL 8.4.13 (disposable copies). What is left is not engineering: it is 22 open decisions (18 business/owner decisions DEC-xx and 4 accountant rulings ACC-03..06), 6 prepared items waiting for a named person to run or answer them, 4 built-and-tested items waiting for written acceptance, and 10 blocked items (production facts, activation steps and the real theme) that nobody has supplied.** No critical code defect was found in this audit. One critical **dependency** was found that no code currently enforces (R-01, MyISAM money tables in the supplied backup).

| Status of the 65 audited items | Count |
|---|---|
| DONE - VERIFIED (technical work, evidence on file) | 18 |
| DONE - AWAITING HUMAN ACCEPTANCE (built and tested, a person must accept) | 4 |
| PREPARED - WAITING FOR A PERSON (tooling/material ready, nobody has run or answered) | 6 |
| OPEN - DECISION REQUIRED | 22 |
| BLOCKED (needs information or access I do not have) | 10 |
| PARTIAL | 2 |
| MOOT / CLOSED BY DECISION | 2 |
| KNOWN ISSUE (3 unrelated failing tests, kept visible) | 1 |

## 2. Method (what I actually checked)
1. Read the complete decision register (`T5_CONSOLIDATED_DECISION_REGISTER.md`, decisions 1-15, B-1, N-1, P-1, AD-1 and UPDATE 4-10) and `T5_REMAINING_REQUIREMENTS.md`, plus `STAGING_ACTIVATION_PREREQUISITES.md`, `T5_FINAL_REVIEW.md` (F-01..F-16), `T5_DEADLOCK_RETRY_VERIFICATION_REPORT.md` (D1-D6), the coverage matrix and every later report.
2. Compared each requirement with code anchors (greps with line numbers), the test inventory and the evidence files (`docs/evidence/audit/`).
3. Repository state: `git status` clean apart from this audit's own files; `HEAD == origin`; 0 stashes; every approved change is committed (OUTBOX.1 `a967de0`, MYSQL84.2 `9677c7a`, decisions B-1.1/AD-1/N-1/INC.1 `25aec82`, ...). Commits after the last change to `app/`, `config/`, `routes/`, `resources/views` (`9677c7a`): only `a967de0`, which touched concurrency test files and docs.
4. Test inventory at HEAD (listing only, no database): **641** default tests, **97** concurrency tests - exactly the numbers in the latest evidence.
5. Evidence links: of 33 distinct `docs/evidence/...` paths cited by the T-5 documents, all exist in git (one cited prefix is a name stem). No email-like personal data in tracked evidence. All cited `T5_*.md` documents and `tests/` files exist.
6. Not done on purpose (restrictions): no test suite or database command was executed in this audit; the evidence of earlier runs was audited, not repeated.

## 3. Compatibility evidence (both engines)
| Evidence | MariaDB 10.11.14 (`inv_v2_test`) | MySQL 8.4.13 (disposable container) | Commit / files |
|---|---|---|---|
| Default suite | 641 tests, 4187 assertions, only the 3 known failures | 641 tests, 4187 assertions, only the same 3 (disposable copy aligned with the clean test state: 61 dump account rows with ~294M balances replaced by 13 zero-balance rows, 5 funnel rows removed) | 9677c7a - `evidence/mysql84_fix/` |
| Same build without alignment | 21 failures on a MariaDB control built like the 8.4 copy | 21 failures, identical set (seed-state dependence, R-05) | `evidence/mysql84/05_failure_comparison.txt`, `mysql84_fix/comparison.txt` |
| Purchase-order / receiving / requirements / bulk / screens subset | 69/69 | 69/69 | 9677c7a |
| Generated-column regression (MYSQL84.2) | 6/6 | 6/6 | `GeneratedQuantityTest` |
| Concurrency suite | 97 tests OK (two runs); earlier 96 OK x many | 97 tests OK (two runs); earlier 96 OK x3 | a967de0 - `evidence/outbox_hardening/04_*` |
| Income-number fix, repeated | 6 income tests x3 OK; 19/40 deadlocks without fix | 6 income tests x3 OK; 12-20/40 deadlocks without fix | `evidence/mysql84/07a_*`, `final*` |
| Hardened outbox test, 30 consecutive runs x2 tests | 30/30, 30/30 | 30/30, 30/30 | `outbox_hardening/03_after_30_runs.txt` |
| T-5 index migration up/down/up | OK | 13 -> 0 -> 13, twice, other 435 indexes unchanged | `evidence/mysql84/04_*` |
| Fingerprint + all-table row counts | unchanged | unchanged (+1 `translations` row = `translate()` side effect) | `final6/7`, `mysql84*`, `outbox_hardening` |
| N-1 script, INC.1 self-check and facts SQL | run, read-only proven | run, read-only proven (error 1792 probe) | `final7`, `mysql84/08*` |
**Limits:** disposable copies only; production engine/version/volume/storage engines/proxy unknown (DBA-02, DBA-05); the 8.4 default-suite result needed the seed alignment above; the 8.4 container was destroyed after each rehearsal (`teardown.txt` files). Neither engine was tested against real data.

## 4. Completed technical work vs decisions needing a human (separated)
### 4a. Technical work completed (no further engineering required)
Checkout cart-row locking · 13 lookup indexes (not applied anywhere real) · deadlock retry on every reachable state-changing method · single application of repeated reversals · file effects after commit · no response inside an open transaction · overdraft rules re-checked under lock · document-number and settle locking (D1/D2/D5) · balance-delta writes (D3) · supplier row locks (D4, except supplier edit/delete) · supplier-payment edit defect · POS cart isolation and duplicate-submission guard · auto-deposit race fix and pause guard · income first-number deadlock fix · bank-screen retirement with read-only history · generated-column fix · outbox test hardening · MySQL 8.4 rehearsal · N-1 and INC.1 tooling · scheduler cleanup · fingerprint/leak checks. (Rows T-01..T-26 below.)
### 4b. Needs a human (cannot be completed by engineering)
* **Decisions:** DEC-01..DEC-11, DEC-13..DEC-16, DEC-18..DEC-20 (18) and the accountant rulings ACC-03..ACC-06 (4) = 22.
* **Prepared items waiting for a person (6):** ACC-01 (N-1 on a restore), ACC-02 (accountant review pack differences D-01..D-09 - the pack's own numbering, not register decisions), DBA-01 (INC.1 sheet), DEC-12 (data-access approval for the full audit), and the tooling rows T-20/T-21 that serve ACC-01/DBA-01.
* **Blocked (10):** ACC-07, DBA-02..DBA-05, VER-01..VER-04, VER-06.
* **Acceptances:** T-07, T-09, T-10, T-11 (built and tested; need written acceptance).
The consolidated checklist is `T5_OUTSTANDING_APPROVALS_CHECKLIST.md`.

## 5. Final acceptance matrix
Responsible = role (no person names are recorded anywhere in the project). Status words are defined in section 1.

### Group T - technical remediation (completed work and its acceptance)
| ID | Requirement | Status | Evidence | Responsible | Outstanding action |
|---|---|---|---|---|---|
| T-01 | Checkout locks only the exact cart rows (no table-wide lock); identical resubmission returns the first order | DONE - VERIFIED | CartLockingParallelTest, AdminOrderParallelTest, StockConcurrencyTest; commit fdce744; final3/final7 runs; mysql84 concurrency x3 | Tech lead | None technical. Duplicate-identity/token policy is decision 5 (see D-05). |
| T-02 | 13 additive lookup indexes `idx_t5_*` (repeat-safe, rollback drops only them) | DONE - VERIFIED | `T5_MIGRATION_COMPATIBILITY_REPORT.md`; `docs/evidence/mysql84/04_index_migration.txt` (13 -> 0 -> 13 twice on 8.4.13); MariaDB suites | DBA | Schema change: approval + rehearsal on the masked production backup (P3) before any production run. Not applied anywhere real. |
| T-03 | Deadlock retry on every reachable state-changing method (34 of 34 reachable tested; 2 have no route; the 5 bank methods are now retired) | DONE - VERIFIED | `T5_DEADLOCK_RETRY_COVERAGE_REPORT.md` (+UPDATE 6), DeadlockRetryMethodCoverageTest (45), DeadlockRetryFlashCoverageTest, `docs/evidence/final*` | Tech lead | None technical. |
| T-04 | F-01 a repeated reversal/delete applies once (unsettle, supplier-payment delete, vouchers, expenses, income, transfers, purchases) | DONE - VERIFIED | ReversalParallelTest, WorkflowParallelCoverageTest; commit 1eb98f1/bd8eb33 | Tech lead | None technical. Wording of the repeat answer is decision 1 (DEC-01). |
| T-05 | F-02 file effects happen once and deletes occur after commit | DONE - VERIFIED | DeadlockFileEffectsTest; commit dbf106f | Tech lead | None. |
| T-06 | F-03 no response inside an open transaction; refusals roll back first | DONE - VERIFIED | TransactionExitTest, NoResponseInsideOpenTransactionTest (static); commit dbf106f | Tech lead | None. |
| T-07 | F-04 existing overdraft rules re-evaluated under the account row lock | DONE - AWAITING HUMAN ACCEPTANCE | OverdraftParallelTest; commit 9c0eda8 | Accountant + Management | Technical part done. The policy itself (which screens block/warn, update evaluation point) is decision 3 (DEC-03). |
| T-08 | D1/D2/D5 document numbers and settle locks (courier settlement, supplier payment numbers) | DONE - VERIFIED | CourierSettlementParallelTest, SupplierPaymentParallelTest; `T5_DEADLOCK_RETRY_VERIFICATION_REPORT.md` | Tech lead | None. |
| T-09 | D3 account-balance writes as deltas (`ChartOfAccount::performUpdate`) - no lost updates | DONE - AWAITING HUMAN ACCEPTANCE | `T5_DEADLOCK_RETRY_VERIFICATION_REPORT.md` D3; parallel purchase/payment/transfer/voucher tests; F-12 PASS for tested screens | Accountant | Review that the delta write is acceptable for every screen that saves a balance (the report asks for this review). Screens never run in parallel remain 'not verified' (see T-23). |
| T-10 | D4 supplier rows locked in purchase/payment/PO paths | DONE - AWAITING HUMAN ACCEPTANCE | WorkflowParallelCoverageTest, SupplierPaymentParallelTest | Accountant | Review requested in the report. **Supplier edit/delete remain unlocked reads (decision 4, D-04).** |
| T-11 | D6 / checkout duplicate identity (cart-row identity through `idempotency_keys` scope `checkout_cart`; the original 15-second guard no longer exists in code) | DONE - AWAITING HUMAN ACCEPTANCE | CartLockingParallelTest; OrderController `checkout_cart` | Order desk + Tech lead | Behaviour change (a deliberate identical resubmission returns the first order) needs approval - decision 5 (DEC-05). No token exists yet. |
| T-12 | Supplier-payment edit defect fixed (edit = revert + apply once on the same supplier row); delete detaches allocations | DONE - VERIFIED | SupplierPaymentEditAccountingTest (8), parallel edit tests; `T5_SUPPLIER_PAYMENT_ROOT_CAUSE_REPORT.md`; owner APPROVED treatment | Owner (approved) | None for the fix. Historical drift: see ACC-01. |
| T-13 | P-1 POS sale deletes only its own cart rows; duplicate submission makes one sale | DONE - VERIFIED | PosCartIsolationTest, PosAndConsoleParallelTest; owner APPROVED | Owner (approved) | None. |
| T-14 | Auto-deposit: balance read under the account lock (no double deposit) and guard G1 (paused, exits before any DB access, tag `AUTO_DEPOSIT_PAUSED_AD1`, exit 0) | DONE - VERIFIED | PosAndConsoleParallelTest (3), AutoDepositPausedGuardTest (3); `final8/disabled_state_check.txt`; owner approved option C + G1 | Owner (approved) | Deployment note R-03: G1 also pauses the job if production is currently running it. Confirm with DBA facts (DBA-04) before deploy. |
| T-15 | Income first-of-year number deadlock fixed (NumberSerializer named lock); no format/schema change | DONE - VERIFIED | `T5_INCOME_NUMBER_DEADLOCK_REPORT.md`; income tests 3x on both engines; without the fix 19/40 (MariaDB) and 12-20/40 (8.4) requests deadlock | Tech lead | Effective in production only if the connection assumptions hold: INC.1 (DBA-01). Falls back to the previous behaviour (retry) otherwise. |
| T-16 | B-1.1 the five bank POST actions retired (HTTP 410 before any write); model read-only; history readable; balance-transfer workflow unchanged; cheque clearing not built | DONE - VERIFIED | BankActionsRetiredTest (4 tests), BankReferenceExampleTest; `final8/disabled_state_check.txt`; owner APPROVED | Owner (approved) | None. |
| T-17 | MYSQL84.2 no application write to generated column `purchase_order_details.pending_quantity` (create, cancel, receive) | DONE - VERIFIED | GeneratedQuantityTest (6); `T5_PENDING_QUANTITY_FIX.md`; `docs/evidence/mysql84_fix/` both engines | Owner (approved) | None. |
| T-18 | OUTBOX.1 deterministic killed-worker outbox test (second start barrier + kill-point signal) | DONE - VERIFIED | `T5_OUTBOX_TEST_HARDENING.md`; 30/30 x2 per engine; concurrency 97 OK on both | Owner (approved) | None. |
| T-19 | MySQL 8.4.13 rehearsal on disposable databases; MariaDB 10.11.14 verification | DONE - VERIFIED | `T5_MYSQL84_REHEARSAL_REPORT.md`, `docs/evidence/mysql84*`, `outbox_hardening` | Tech lead | Only disposable copies tested. Production engine/version unknown: DBA-02. |
| T-20 | N-1 read-only investigation tooling (Q1-Q10, runbook, guarded script) proven on synthetic discrepancies | PREPARED - WAITING FOR A PERSON | SupplierDriftQueriesTest (28 assertions); `tests/staging/n1_readonly_audit.sh` run on both engines; `T5_N1_READONLY_AUDIT_RUNBOOK.md` | Accountant + DBA | Execute on an isolated restored copy and return the report (ACC-01). Nobody has run it on real data. |
| T-21 | INC.1 DBA tooling (`accounts:number-lock-selfcheck`, `inc1_db_facts.sql`, answer sheet) | PREPARED - WAITING FOR A PERSON | NumberLockSelfCheckTest; run on both engines; `T5_INC1_DBA_HANDOFF.md` | DBA | Return the answer sheet (DBA-01). No answer received. |
| T-22 | First-document-of-period race for the other numbered series (expense, transfer, voucher, stock adjustment, customer payment) | PARTIAL | `T5_INCOME_NUMBER_DEADLOCK_REPORT.md` section 5 - analysed from code + SQL reproduction, not reproduced per series; the retry still covers it | Owner (decision 7) | SEQ.1 (DEC-07): approve a `document_sequences` table, or accept the retry-only protection. |
| T-23 | Five test categories (sequential, injected deadlock, real parallel, duplicate submission, accounting reconciliation) for every operation | PARTIAL | `T5_REMAINING_WORKFLOW_COVERAGE_MATRIX.md`: no workflow has all five for all operations; closest 1, 3, 13, 19 | Tech lead | Either accept the documented gaps or fund further tests (purchase update parallel, supplier edit/delete, order edit/delete, chart-of-accounts destroy, console repair jobs, etc.). |
| T-24 | Scheduler references only defined commands (`accounts:import-customer-payments` removed) | DONE - VERIFIED | SchedulerCommandsExistTest; Kernel comment | Owner (decision 10) | Whether an hourly import is wanted at all: D-10. |
| T-25 | Legacy-ledger outbox: 4 workers, kill at every boundary, live postings racing the processor | DONE - VERIFIED | OutboxVerificationTest (+2 hardened), RefundVerificationTest; both engines | Tech lead | T-4 load test and scheduler run in a production-like environment: VER-04. |
| T-26 | Financial/stock fingerprints and all-table row counts unchanged by the suites on the disposable databases | DONE - VERIFIED | fingerprint pairs in final6/final7/mysql84*/outbox_hardening (only +1 `translations` row from `translate()`) | Tech lead | None. |

### Group D - business decisions
| ID | Requirement | Status | Evidence | Responsible | Outstanding action |
|---|---|---|---|---|---|
| DEC-01 | Decision 1 - repeat-request answers and client tokens ('already done' wording, 200 vs info, which forms carry tokens, tombstones) | OPEN - DECISION REQUIRED | Register decision 1 | Order desk, Accountant, Tech lead | Choose wording/status code, forms and cut-off date; approve tombstone storage. Recommended: server-issued token + 'already done' 200. |
| DEC-02 | Decision 2 - purchase delete vs `suppliers.due_amount` (A keep, B reduce due, C forbid delete with payments) | OPEN - DECISION REQUIRED | Register decision 2; F-06 | Accountant | Choose A/B/C and whether historical mismatches are report-only. |
| DEC-03 | Decision 3 - overdraft policy per screen (block/warn/none; update evaluation point) | OPEN - DECISION REQUIRED | Register decision 3; F-04 | Accountant + Management | Per-screen table. |
| DEC-04 | Decision 4 - supplier edit/delete semantics (A lock+refuse stale, B difference, C overwrite+audit; who may edit balances) | OPEN - DECISION REQUIRED | Register decision 4; F-05; coverage matrix row 7 | Accountant | Choose A/B/C and the permission. Until then supplier edit/delete stay unlocked writes. |
| DEC-05 | Decision 5 - checkout duplicate identity (token mandatory/optional, retention, payment-view owner, replay message) | OPEN - DECISION REQUIRED | Register decision 5; T-11 | Order desk + Tech lead | Approve token design or accept the cart-identity fallback only. |
| DEC-06 | Decision 6 - numbering formats, period resets, gap-free rule, order code generation | OPEN - DECISION REQUIRED | Register decision 6 | Accountant | Recommended: keep existing formats. Not needed for the income fix. |
| DEC-07 | Decision 7 / SEQ.1 - `document_sequences` table (schema change, seeded from maxima) | OPEN - DECISION REQUIRED | Register decision 7; T-22 | Owner + DBA | Approve after a rehearsal, or accept retry-only protection for the other series. |
| DEC-08 | Decision 8 - maintenance commands while trading (which may run, maintenance-mode rule, who authorises `--apply`) | OPEN - DECISION REQUIRED | Register decision 8 | Accountant + Tech lead | Their transactional/parallel behaviour is NOT VERIFIED (coverage matrix row 16). |
| DEC-09 | Decision 9 - purchase-order conversion (transaction, repeat-click rule, `converted_purchase_id` column, partial conversion) | OPEN - DECISION REQUIRED | Register decision 9 | Purchasing + Accountant | Schema change; most financial screen. |
| DEC-10 | Decision 10 - customer-payment auto-import (hourly job wanted? account/method rule, response format) | OPEN - DECISION REQUIRED | Register decision 10 | Accountant | Until decided the job stays removed. |
| DEC-11 | Decision 11 - retention of `idempotency_keys`, alert thresholds and owner | OPEN - DECISION REQUIRED | Register decision 11 | Tech lead | Retention periods and alert owner. |
| DEC-12 | Decision 12 - audits on an authorised isolated restore (supplier part prepared as N-1.1) | PREPARED - WAITING FOR A PERSON | Register decision 12; N-1.1 | Owner | Data-access approval and environment for the full Phase 0 audit SQL (the supplier Q1-Q10 part was approved as N-1.1). |
| DEC-13 | Decision 13 - money columns varchar/double -> decimal (timing) | OPEN - DECISION REQUIRED | Register decision 13 | Accountant | Timing after the accountant baseline. |
| DEC-14 | Decision 14 - unique constraints (`orders.code`, per-order settlements, returns/PO references) after the audit shows no duplicates | OPEN - DECISION REQUIRED | Register decision 14 | DBA + Accountant | Needs audit results (DEC-12) first. |
| DEC-15 | Decision 15 - order cancel/delete/status without transaction; notification outbox | OPEN - DECISION REQUIRED | Register decision 15 | Order desk + Tech lead | Approve pattern; whether `OrderController::destroy` stays. |
| DEC-16 | AD-1 future - run nightly auto-deposit at all? (a system user / b nullable / stay on c) | OPEN - DECISION REQUIRED | `T5_AD1_AUTO_DEPOSIT_OPTIONS.md`; C selected for now | Owner | Only if the business wants nightly deposits; recommended: stay on C. |
| DEC-17 | B-1.2..B-1.6 bank-screen decisions (bank-charge account, counter-accounts, cheque clearing, cancellation, audit table) | MOOT / CLOSED BY DECISION | Register UPDATE 6 | Owner | Moot while the bank screens are retired; only the bank-charge question survives as ACC-03. |
| DEC-18 | N-1.2 correction procedure and approver for any distorted supplier balance | OPEN - DECISION REQUIRED | Register N-1 | Accountant + Owner | Needs ACC-01 results first. Recommended: supplier by supplier, adjustment document, never a script. |
| DEC-19 | N-1.3 supplier-payment edit audit table (schema change) | OPEN - DECISION REQUIRED | Register N-1 | Owner | Recommended: later. |
| DEC-20 | P6 explicit approval to enable `INVENTORY_CONFIRMED_ONLY` on staging | OPEN - DECISION REQUIRED | STAGING_ACTIVATION_PREREQUISITES.md | Owner | NOT GIVEN; not before every row of this audit that blocks activation is satisfied. |
| DEC-21 | Closed owner rulings (for the record): P-1, supplier-edit treatment, B-1.1, AD-1.1 (C), AD-1.2 (G1), N-1.1, INC.1 (checklist), MYSQL84.1 (A), MYSQL84.2, OUTBOX.1 | MOOT / CLOSED BY DECISION | Register UPDATE 6-10 | Owner | None - recorded. |

### Group ACC - accountant
| ID | Requirement | Status | Evidence | Responsible | Outstanding action |
|---|---|---|---|---|---|
| ACC-01 | N-1.1 run Q1-Q10 on an isolated restored copy; classify every flagged supplier; sign the report | PREPARED - WAITING FOR A PERSON | `T5_N1_ACCOUNTANT_REVIEW_PACKAGE.md`, runbook, script | Accountant + DBA | DBA restores a backup to a separate database; run `tests/staging/n1_readonly_audit.sh`; return the template. No correction. |
| ACC-02 | D-01..D-09 rulings on the accountant review pack (trial balance 301.75 difference, retained-earnings plug, P&L basis, inventory vs FIFO 11,255.00, uncosted stock, orphan layers, PAY-000001 5,150.00, payables concentration, 105 untyped rows) and P5 sign-off | PREPARED - WAITING FOR A PERSON | `docs/accountant-review-pack/` (from the masked local copy of the supplied backup - not proven to be production) | Accountant | Rule on each; sign the blank table. Prerequisite P5 of staging activation. |
| ACC-03 | Where bank/withdrawal/transfer **charges** are expensed: the working balance-transfer screen credits the source with amount + charge but debits no expense account | OPEN - DECISION REQUIRED | `T5_B1_ACCOUNTING_EXAMPLE.md` section 1; BankReferenceExampleTest | Accountant | Name the charge account (or confirm one-sided posting is intended). **Existing behaviour, not changed.** |
| ACC-04 | T-2 acceptance of wallet-only refund treatment; wallet top-ups/spending still not journalled | OPEN - DECISION REQUIRED | STAGING_ACTIVATION_PREREQUISITES T-2 | Accountant | Accept or specify. |
| ACC-05 | T-3 acceptance of the historical-uncosted-order reporting policy (`docs/HISTORICAL_ORDER_COSTS.md`) | OPEN - DECISION REQUIRED | STAGING_ACTIVATION_PREREQUISITES T-3 | Accountant | Accept or specify. |
| ACC-06 | Review of D3 (balance deltas) and D4 (supplier row locks) (T-09, T-10) | OPEN - DECISION REQUIRED | `T5_DEADLOCK_RETRY_VERIFICATION_REPORT.md` | Accountant | Written acceptance. |
| ACC-07 | Accountant baseline (`accounting:baseline`) before any activation | BLOCKED | P8 | Accountant + Tech lead | Only at activation; after owner approval P6. |

### Group DBA - database / infrastructure
| ID | Requirement | Status | Evidence | Responsible | Outstanding action |
|---|---|---|---|---|---|
| DBA-01 | INC.1 ten infrastructure answers + `accounts:number-lock-selfcheck` output from the production application host | PREPARED - WAITING FOR A PERSON | `T5_INC1_DBA_HANDOFF.md`, `T5_INC1_DBA_CHECKLIST.md` | DBA | Return the answer sheet (all ten UNANSWERED today). |
| DBA-02 | P1 production engine/exact version, storage engines, isolation level, FKs/indexes (`inc1_db_facts.sql`, `sqlupdates/inventory_v2_environment_check.sql`, Phase 0 audit section 0) | BLOCKED | nothing received | DBA | Run on production (read-only) or a restored backup; return output. |
| DBA-03 | P2 fresh backup with checksum; P3 real-engine rehearsal (InnoDB conversion, artisan + SQL, parity, rollback) on the masked backup | BLOCKED | `PRODUCTION_BACKUP_REHEARSAL_RUNBOOK.md`; tooling `tests/staging/prepare-staging.sh`, `rehearse.sh` | DBA | Supply backup + checksum; run the rehearsal; return logs. |
| DBA-04 | Is `balance_transfers.created_by` nullable in production, and are any `deposit_type='auto'` methods active? (decides whether G1 changes a working job) | BLOCKED | `inc1_db_facts.sql` prints both | DBA | Return the two values. See R-03. |
| DBA-05 | **MyISAM tables:** in the supplied backup `misron.sql` (and `empty_misron.sql`) 16 tables are MyISAM, including `purchases`, `suppliers`, `purchase_returns`, `purchase_return_products` and (in `misron.sql`) `purchase_products` = the FIFO cost layers. MyISAM has no transactions, no row locks and no rollback, so every T-5 locking/retry guarantee is void on those tables | BLOCKED | dump inspection (`docs/evidence/audit/03_static_checks.txt`, `04_myisam_tables.txt`); `InventoryReconciliation` flags non-InnoDB; `T5_DEPLOYMENT_ROLLBACK_RUNBOOK.md` requires the conversion | DBA + Owner | Confirm production engines; if MyISAM the conversion (`sqlupdates/inventory_v2_innodb_conversion.sql`) is a prerequisite - see R-01. Maintenance window + rehearsal (DBA-03). |

### Group VER - verification and acceptance
| ID | Requirement | Status | Evidence | Responsible | Outstanding action |
|---|---|---|---|---|---|
| VER-01 | P4 real admin theme deployed; CSS/JS errors resolved; screens re-verified (the 7 sweep failures; bank list now a notice) | BLOCKED | theme package not supplied | Owner/Front-end | Provide the theme package; re-run `tests/ui` sweeps. |
| VER-02 | P7 permissions (`view_financial_reports`, `manage_accounting`, order-status permissions) tested with real staff roles | BLOCKED | needs roles from the restored backup | Tech lead | After DBA-03. |
| VER-03 | T-1 real-engine proof that the original deadlock/stale-statistics failure is gone | BLOCKED | original failure never reproduced; synthetic experiments show 0 deadlocks on both engines | DBA + Tech lead | Production-like load on the rehearsal copy. |
| VER-04 | T-4 outbox load test with the scheduler running | BLOCKED | processor tests only | Tech lead | Production-like environment. |
| VER-05 | The 3 known unrelated failing tests (UploadSafetyTest svg, ExampleTest GET `/`, FunnelEditorMedia attribution) | KNOWN ISSUE (kept visible) | every suite run since Phase 0; root-caused as unrelated | Tech lead | Fix or accept; they are kept visible and unchanged. |
| VER-06 | Staging acceptance and T-5 acceptance as a whole | BLOCKED | this audit | Owner | Cannot be granted until every BLOCKED/OPEN item above that applies to activation is resolved. |

## 6. Defects, risks and findings of this audit (reported separately; nothing changed)
**Critical code defects discovered: none.** Findings:

| ID | Finding | Action / who |
|---|---|---|
| R-01 | CRITICAL DEPENDENCY (not a code defect): no runtime gate stops the legacy T-5 retry/locking paths running on MyISAM tables. On MyISAM a 'rollback' does not undo writes, so a retried or failed financial request can double-apply or leave partial writes. The v2 preflight and reconciliation report detect non-InnoDB tables, but the legacy screens do not check. | Decide whether to add an engine check to the T-5 activation checklist/preflight (recommended, small) or rely on the conversion step alone; DBA-05 supplies the facts. No code changed in this audit. |
| R-02 | The supplied backup is not proven to be production (header 8.4.3 is the dump tool's server). All production statements remain unknown. | DBA-02/03. |
| R-03 | Deployment-time behaviour change: guard G1 pauses `accounts:auto-deposit`. If production's `created_by` is nullable and auto methods exist, the job may be moving money nightly today and would stop on deploy (option C was chosen on the assumption that it fails). | DBA-04 before any deploy; owner confirms C is still wanted. |
| R-04 | `AUTO_DEPOSIT_APPROVED=true` in an environment re-enables the old body, which writes `created_by = NULL`: harmless failure on a NOT NULL schema, but would post money on a nullable one. The setting must not be used until an AD-1 option is implemented. | Operational rule: do not set it; consider removing the env switch once a real approval exists. |
| R-05 | The test baseline (3 known failures) depends on the seed state of `inv_v2_test`; a database built only from the dump fails 21 tests on both engines (account balances ~294M and funnel rows are in the dump). The 8.4 default-suite evidence used a deliberately aligned disposable copy. | Document/commit a reproducible seed or accept. |
| R-06 | The income-number lock is a session lock: ineffective behind a multiplexing proxy, multi-primary, or persistent connections (falls back to retry; fails open after 10 s). | DBA-01. |
| R-07 | Latest full default-suite run on both engines is at commit 9677c7a; later commits changed tests/docs only (a967de0 touched concurrency harness files). The concurrency suite was re-run on both engines at a967de0 (97 tests). | None; stated for transparency. |
| R-08 | Four rehearsal logs exist only locally because `.gitignore` excludes them (docs/evidence/db-rehearsal.log, staging/rehearse.log, staging/sweep2.log, verification/migration-sql-parity.log); no document references them as tracked evidence. | None needed; do not cite them as repository evidence. |
| R-09 | Documentation drift found and corrected in this audit: register header said 'no decision taken or implemented' and its status table pre-dated UPDATE 6-10; `T5_REMAINING_REQUIREMENTS.md` header/MySQL row were stale; `STAGING_ACTIVATION_PREREQUISITES.md` T-5 row cites 570 tests. | Corrected (register, requirements); prerequisites row annotated with a pointer. |

## 7. What would have to be true to close T-5 (checklist of conditions, not a recommendation to proceed)
1. DBA-02/03/05 answered: production engine known, money/stock tables InnoDB (or converted and rehearsed on a masked backup), isolation level REPEATABLE-READ confirmed.
2. DBA-01 answered (or SEQ.1 chosen instead) so that the income-number protection is known to be effective.
3. DBA-04 answered and the owner re-confirms option C (R-03).
4. ACC-01 executed and N-1.2 decided; ACC-02 rulings and sign-off (P5); ACC-04/05/06 accepted.
5. Decisions DEC-01..DEC-15 either decided or explicitly deferred in writing with the consequence accepted (several change screens and some need schema changes).
6. T-23 gaps accepted or closed; VER-01/02 completed with the real theme and roles.
7. The owner gives P6 in writing. Only then P8 (`accounting:baseline`, `ANALYZE TABLE`) and the staged activation.
Until 1-7 hold: **T-5 and staging acceptance remain OPEN; production NO-GO.**

## 8. Corrections after the R-01 / R-03 / R-04 investigation (2026-11-08, documentation only)
* **R-01 widened:** besides voiding locks and transactions, MyISAM makes the deadlock **retry** unsafe (MyISAM writes are not rolled back but are repeated), and every T-5 test ran on converted copies, never on the backup's real engine configuration. See `T5_R01_MYISAM_INVESTIGATION.md` (table-by-table proposal) and decisions R01-A..D in `T5_DECISION_TRACKER.md`.
* **R-03 corrected:** a nullable `balance_transfers.created_by` would *not* by itself let the old auto-deposit body complete. It also writes `transaction_type='auto_deposit'` and `status='completed'` (illegal ENUM values in the dump/test schema, stored blank in non-strict mode), `expense_payments.created_by=NULL` for charged methods, and no statement lines. See `T5_R03_R04_AUTO_DEPOSIT_TRACE.md`.
* **R-04 extended:** nine activation mechanisms (E1-E9), the most realistic being a rollback to a release before `25aec82`; safeguards S1-S9 proposed, none implemented.
* New findings NB-1..NB-7 are listed in `T5_APPROVAL_REMEDIATION_ROADMAP.md` section 2. Status counts in section 1 are unchanged (the new items are additions to the tracker, not new audit rows).

## 9. Update after the autonomous completion mandate
The acceptance matrix with the honest statuses DONE / TESTED / BLOCKED / DEFERRED / NOT APPLICABLE is in `T5_FINAL_COMPLETION_REPORT.md` section 3 and supersedes the status columns above for items it covers. R-01 is gated and tooled (not "resolved in production": production facts unknown), R-03/R-04 are gated and tested, the three known failures are fixed, T-22 and the technical part of decision 4 are implemented. Nothing in this section is a human approval.
