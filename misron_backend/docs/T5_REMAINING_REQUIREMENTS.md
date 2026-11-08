# T-5 remaining requirements (state at commit `25aec82`, reviewed 2026-11-08)

Source: `T5_CONSOLIDATED_DECISION_REGISTER.md` (UPDATE 4-6), `STAGING_ACTIVATION_PREREQUISITES.md`, `T5_OWNER_DECISION_REPORT.md`, `docs/evidence/final7/` (PHPUnit 635 tests, only the 3 known unrelated failures; concurrency 96 tests OK; fingerprints and all 197 table row counts identical before/after) and `docs/evidence/final8/` (this review). **No approval below has been given unless the register says so. T-5 and staging acceptance are OPEN.**

Status words: OPEN (nothing done), PREPARED (material ready, waiting for the named person), BLOCKED (cannot proceed without information I do not have), DONE (with evidence).

## A. Owner approvals still outstanding
| ID | Question | Recommended | Status |
|---|---|---|---|
| AD-1 (future) | Run nightly auto-deposit at all? If yes: option a (non-login system user) or b (nullable column); today option C + guard G1 | stay on C | OPEN, only if the business wants it |
| B-1.2 - B-1.6 | bank-charge account, counter-accounts, cheque clearing, cancellation rule, audit table | none needed: the bank actions are retired (B-1.1) | OPEN but moot unless the bank screens are revived; bank charges on balance transfers are still booked one-sided (see `T5_B1_ACCOUNTING_EXAMPLE.md`) - **this is a live accounting question for the accountant, not a bank-screen one** |
| N-1.2 | correction procedure and approver for any distorted supplier balance found | accountant-led, supplier by supplier, adjustment document, never a script | OPEN (needs the audit results first) |
| N-1.3 | edit audit table for supplier payments (schema change) | later | OPEN |
| SEQ.1 (decision 7) | `document_sequences` table for the other numbered series | yes, after a rehearsal | OPEN |
| 1, 5 | repeat-request wording and client tokens; checkout duplicate identity | - | OPEN |
| 2, 3, 4 | purchase delete vs supplier due; overdraft policy; supplier edit/delete semantics (supplier edit/delete are still unlocked reads) | accountant | OPEN |
| 6 | numbering formats / period resets / gap-free rule | keep formats | OPEN |
| 8, 9, 10 | maintenance commands while trading; purchase-order conversion; customer-payment auto-import | - | OPEN |
| 11 - 15 | retention/alerts; audits on a restore (= N-1.1/12 for suppliers); money columns; unique constraints after audit; order cancel/delete/notification outbox | - | OPEN |
| P6 | explicit approval to switch `INVENTORY_CONFIRMED_ONLY` on staging | not before every row of section D | NOT GIVEN |

## B. Accountant review
| Item | What is needed | Material | Status |
|---|---|---|---|
| N-1.1 supplier-payment edit drift | run Q1-Q10 on an isolated restore, classify findings, sign the report | `T5_N1_ACCOUNTANT_REVIEW_PACKAGE.md`, `T5_N1_READONLY_AUDIT_RUNBOOK.md`, `tests/staging/n1_readonly_audit.sh` | PREPARED (nobody has run it on real data) |
| D-01 - D-09 and P5 | rulings on trial balance difference 301.75, retained-earnings plug, P&L basis, inventory vs FIFO 11,255.00, uncosted stock, orphan layers, PAY-000001 5,150.00, payables concentration 147,081,280.00, 105 untyped rows | `docs/accountant-review-pack/` (generated from the masked local copy of the supplied backup, not proven to be production) | PREPARED, NOT REVIEWED |
| T-2, T-3 | accept wallet-refund treatment and historical-uncosted-order policy | `T5_FINAL_VERIFICATION_REPORT.md` | OPEN |
| Bank charges | where a charge on a balance transfer/withdrawal is expensed | `T5_B1_ACCOUNTING_EXAMPLE.md` section 1 | OPEN |

## C. DBA / infrastructure
| Item | Evidence required | Material | Status |
|---|---|---|---|
| INC.1 income-number lock | the ten answers + output of `php artisan accounts:number-lock-selfcheck` from the production application host | `T5_INC1_DBA_HANDOFF.md` (answer sheet) and `T5_INC1_DBA_CHECKLIST.md` | PREPARED, **no answer received** |
| P1 engine and exact version, storage engines (MyISAM?), isolation level, FKs/indexes | output of `docs/T5_PHASE0_AUDIT_QUERIES.sql` section 0 / `sqlupdates/inventory_v2_environment_check.sql` run on production or a restored backup | - | BLOCKED |
| P2 fresh backup with checksum | backup file + checksum | `PRODUCTION_BACKUP_REHEARSAL_RUNBOOK.md` | BLOCKED |
| Whether `balance_transfers.created_by` is nullable in production and whether any `deposit_type='auto'` methods exist | two read-only queries (`T5_AD1_AUTO_DEPOSIT_OPTIONS.md` section 4) | - | BLOCKED |

## D. Verification still to do
| Item | Needs | Status |
|---|---|---|
| MySQL 8.4 run of migrations, all suites, concurrency | a MySQL 8.4 server for disposable databases | EXECUTED 2026-11-08 (report `T5_MYSQL84_REHEARSAL_REPORT.md`; finding F1 fixed by MYSQL84.2, see `T5_PENDING_QUANTITY_FIX.md`) - earlier: PLAN WRITTEN (`T5_MYSQL84_TEST_PLAN.md`); **cannot run here** (no 8.4 binary, Docker daemon down, `dev.mysql.com`/`repo.mysql.com` unreachable); needs your decision on how to obtain one |
| P3 real-engine rehearsal on the masked production backup | P1 + P2 | BLOCKED |
| P4 real admin theme and screen re-verification (7 sweep failures, bank list now a notice) | theme package | BLOCKED |
| P7 permissions with real staff roles | roles from the restored backup | NOT STARTED |
| P8 `accounting:baseline` and `ANALYZE TABLE` | only at activation | NOT STARTED |
| First-document-of-period race for series other than incomes (expense, transfer, voucher, stock adjustment, customer payment) | SEQ.1 | analysed, not reproduced per series; retry still covers it |
| Supplier edit/delete concurrency (decision 4) | decision 4 | OPEN |
| Five test categories (sequential, injected deadlock, real parallel, duplicate submission, accounting reconciliation) for every workflow | - | not complete for every operation (`T5_REMAINING_WORKFLOW_COVERAGE_MATRIX.md`) |
| T-1 real-engine deadlock proof, T-4 outbox load test and scheduler | production-like engine | OPEN |
| The 3 known failing tests (UploadSafety svg, ExampleTest, FunnelEditorMedia attribution) | unrelated to T-5; kept visible | KNOWN |

## E. Verified this review (evidence `docs/evidence/final8/disabled_state_check.txt`)
* Bank actions stay retired: five POST routes answer 410 before any write; no other write route; history readable (4 tests, 138 assertions OK).
* Automatic deposits stay off: shipped default `accounts.auto_deposit_approved=false`; the default-configuration command run printed `AUTO_DEPOSIT_PAUSED_AD1`, exit 0, **0 queries**; schedule line unchanged; `created_by` still NOT NULL.
* Flags: `inventory.confirmed_only=false` by default; nothing in the repository sets it or `AUTO_DEPOSIT_APPROVED` to true outside tests.
