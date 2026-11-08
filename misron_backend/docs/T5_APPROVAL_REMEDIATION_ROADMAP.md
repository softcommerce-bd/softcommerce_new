# T-5 approval and remediation roadmap

Prepared 2026-11-08 on top of commit `775a236` (documentation only; nothing executed, nothing changed in code, databases, flags, schedules or environments; no production/staging access). **Nothing here is an approval or a decision; every gate needs the named person's written approval.** Companion documents: `T5_R01_MYISAM_INVESTIGATION.md`, `T5_R03_R04_AUTO_DEPOSIT_TRACE.md`, `T5_DECISION_TRACKER.md` (48 items, 0 approved), `T5_FINAL_ACCEPTANCE_AUDIT.md`, `T5_OUTSTANDING_APPROVALS_CHECKLIST.md`. **T-5 and staging acceptance remain OPEN; production NO-GO.**

## 1. Critical blockers (nothing can be deployed while any of these is open)
| ID | Blocker | Why it is critical | Who can clear it |
|---|---|---|---|
| CB-1 | **R-01 MyISAM money/stock tables** in the supplied backup (`purchases`, `purchase_products` = FIFO layers, `suppliers`, `purchase_returns`, `purchase_return_products`) | no transactions, no row locks, no rollback: all T-5 guarantees void, and the deadlock **retry can duplicate writes** (a repeated purchase / double supplier due) - static analysis, not yet reproduced. Every T-5 test ran on converted copies, never on this schema | DBA (facts, conversion), Owner (R01-A/C/D), Tech lead (R01-B) |
| CB-2 | **Production unknown**: engine/version, storage engines, isolation, topology, proxy (DBA-01/02/05) | every compatibility and sizing statement is about disposable copies | DBA |
| CB-3 | **No backup / restore proof / rehearsal on the masked production backup** (DBA-03, P2/P3) | the T-02 index migration and the conversion are schema changes that must be rehearsed first | DBA |
| CB-4 | **Auto-deposit old body can be re-enabled** by a variable, a cached config or a rollback to a pre-`25aec82` build, and it is incomplete (ENUM values, charge-expense creator, no statement lines) - R-03/R-04 | accidental activation would post money with blank type/status and no statements on any schema that allows NULL creators | Owner (R04-A, R03-A), DBA (DBA-04) |
| CB-5 | **Accountant rulings** on the accountant pack (P5), N-1 results, D3/D4, T-2/T-3 | staging prerequisites P5 and T-items unsatisfied | Accountant |

## 2. Newly discovered blockers and findings of this phase (no code changed)
| ID | Finding | Evidence | Where handled |
|---|---|---|---|
| NB-1 | Retry-on-MyISAM hazard (retry re-applies MyISAM writes whose transaction part rolled back; the idempotency key rolls back with the transaction) | `config/deadlock_retry.php`, `PurchaseController::store` (lines 281-479: key insert at 314 inside the transaction, MyISAM writes at 335+) | R-01 doc section 2, decision R01-D |
| NB-2 | Auto-deposit body needs more than a nullable `balance_transfers.created_by`: ENUM `transaction_type` lacks `auto_deposit`, `status` lacks `completed`, `expense_payments.created_by` is NOT NULL (charged methods), no statement lines are written | dump + test DB schema, `AutoDepositFunds.php:80-101,154-165` | R-03/R-04 doc section 2; AD-1 doc addendum |
| NB-3 | `sqlupdates/inventory_v2_innodb_conversion.sql` also adds a UNIQUE key on `purchases.reference_no` (decision 14 not approved; fails on duplicates) | the script | decision R01-B / DEC-14 |
| NB-4 | Test-evidence gap: no suite has run on the backup's real engine configuration (MyISAM `purchases`/`suppliers`/...) | all evidence directories | R01-D |
| NB-5 | Rollback to an older release re-enables the old auto-deposit body (no switch before `25aec82`) | git history | safeguard S7 |
| NB-6 | (to verify) the balance-transfer screen writes `status='completed'`, which is not a legal ENUM value in the dump/test schema; in non-strict mode it is stored blank | `BalanceTransferController::store`; schema | DBA-04 query `SELECT status, COUNT(*) FROM balance_transfers GROUP BY status`; accountant to judge |
| NB-7 | Minor: `config/deadlock_retry.php` still lists `BankTransactionController => '*'` (retired routes answer 410 before any write; harmless) | config | clean-up with the next approved code change |

## 3. Required human approvals (full list: `T5_DECISION_TRACKER.md`)
**Gate approvals needed before any rehearsal or deployment step:** R01-A, R01-B, R01-C, R01-D, R03-A, R04-A (owner side); DBA-02/04/05 answers; DEC-12 (data access for the audit).
**Needed before staging activation:** DEC-20 (P6), ACC-02 (P5), ACC-04, ACC-05, ACC-06 and T-07/T-09/T-10/T-11 acceptances, DEC-16 stays at option C (or its prerequisites are built), DEC-07 (or explicit acceptance of retry-only numbering).
**Needed before production (may follow staging):** DEC-01, DEC-02, DEC-03, DEC-04, DEC-05, DEC-06, DEC-08, DEC-13, DEC-14, DEC-18, DEC-19.
**May be deferred in writing with the consequence accepted:** DEC-09, DEC-10, DEC-11, DEC-15, T-23 gaps.

## 4. Required work by role
### DBA
1. Answer INC.1 (DBA-01) and the facts queries (DBA-02, DBA-04, DBA-05) - read-only.
2. Provide a fresh backup with checksum and a restore proof (DBA-03).
3. After R01-A: run the engine-only conversion rehearsal on the masked restore, then the two index migrations and `ANALYZE TABLE`; record per-ALTER times and the fingerprints.
4. Production-like deadlock/outbox load evidence (VER-03/04).
5. Later, in the approved window only: the conversion, then the index migrations.
### Accountant
1. With the DBA, run N-1.1 on the restore and return the signed template (ACC-01); then decide N-1.2/N-1.3.
2. Rule on the accountant pack D-01..D-09 and sign (ACC-02).
3. Decide ACC-03 (bank charge account), accept or specify ACC-04 (wallet refunds), ACC-05 (uncosted orders); review D3/D4 (ACC-06).
4. Rule on DEC-02, 03, 04, 06, 08, 10, 13 (and 14 with the DBA).
5. Take `accounting:baseline` only at activation (ACC-07).
### Owner / tech lead (decisions and tooling, not code unless approved)
1. Decide R01-A..D, R03-A, R04-A; keep DEC-16 at option C.
2. Adopt safeguards S1-S3, S6, S7 as deployment/operations rules (they need no code); decide S4/S5/S8 with DEC-16.
3. Approve the engine-only conversion script (R01-B) - the script edit itself is a later, separate change.
4. Supply the admin theme (VER-01); schedule VER-02 permission tests after the restore.
5. Decide the remaining DEC items per section 3.

## 5. Staging prerequisites (all must be satisfied; P-numbers from `STAGING_ACTIVATION_PREREQUISITES.md`)
| # | Prerequisite | State |
|---|---|---|
| P1 | production engine/version/table engines confirmed (DBA-02, DBA-05) | BLOCKED |
| P2 | fresh backup + checksum + restore proof | BLOCKED |
| P3 | rehearsal on the masked backup: conversion (engine-only), index migrations, artisan + SQL, parity, rollback, on the production target engine | BLOCKED by P1, P2 |
| P4 | real admin theme and screen re-verification | BLOCKED |
| P5 | accountant review of the pack with sign-off | BLOCKED |
| P6 | owner's explicit written approval to enable the flag on staging | NOT GIVEN |
| P7 | permissions with real staff roles | NOT STARTED |
| P8 | `accounting:baseline` and `ANALYZE TABLE` immediately before activation | NOT STARTED |
| new S-1 | all five money/stock tables InnoDB on staging **and** production, proven by query (CB-1) | OPEN |
| new S-2 | auto-deposit safeguards S1-S3, S6, S7 in place; `config('accounts.auto_deposit_approved')` recorded false on every host | OPEN |
| new S-3 | INC.1 answered or SEQ.1 decided | OPEN |
| new S-4 | N-1.1 executed and its findings classified | OPEN |

## 6. Final acceptance conditions (T-5 may be called closed only when ALL hold)
1. CB-1..CB-5 closed with evidence; P1-P8 and S-1..S-4 satisfied.
2. A suite (default + concurrency) has run green on the **production-shaped** schema (all five tables InnoDB after the rehearsal conversion) on the production target engine, with the same 3 known failures only.
3. The disposable MyISAM experiment (R01-D) has shown the hazard and its removal, or the owner has accepted CB-1 on the DBA's proof that production is already InnoDB.
4. The tracker shows every item either approved/accepted in writing or deferred in writing with the consequence accepted; no item is approved by recommendation or silence.
5. The accountant has signed N-1 results and the accountant pack and accepted D3/D4/T-2/T-3.
6. The owner gives P6 and, separately, final acceptance (VER-06). Production remains a separate decision.

## 7. Safe execution order
Each step stops for the named person; nothing runs on production or staging before step 7, and even then only after written approval.
| Step | Action | By | Output | Gate to continue |
|---|---|---|---|---|
| 1 | Decide R01-A, R01-D, R03-A, R04-A (and DEC-12) - or reject | Owner | tracker updated | recorded decisions |
| 2 | Adopt operational safeguards S1-S3, S6, S7 (no code) | Owner + DBA | checklist entries | recorded |
| 3 | Read-only facts: DBA-01, 02, 04, 05 | DBA | outputs in the doc templates | outputs received |
| 4 | Disposable experiment R01-D on a MyISAM-shaped `*_test` database (only if approved in step 1) | Tech lead | log: hazard reproduced / gone after conversion | owner reads it |
| 5 | Backup + restore proof (DBA-03) | DBA | checksum, restore log | restore proven |
| 6 | Rehearsal on the masked restore on the target engine: engine-only conversion (R01-B), index migrations, `ANALYZE`, suites, parity, rollback; N-1.1 on the restore (ACC-01) | DBA + Accountant | per-ALTER times, fingerprints, signed N-1 | all green / findings classified |
| 7 | Decisions batch (section 3 groups), accountant rulings (ACC-02..06) | Owner + Accountant | tracker updated | gates in section 6 items 4-5 |
| 8 | Staging: maintenance window, conversion, migrations, smoke, permissions (P7), baseline (P8) - **only with written approval P6** | DBA + Tech lead | staging acceptance report | owner decision |
| 9 | Final acceptance (VER-06); production planning as a new request | Owner | signed acceptance | - |
What is never done without the owner's written approval: deployments, merges, schema changes (T-02 indexes, conversion, DEC-07/09/14/19), flag changes, schedule changes, anything on production or staging.
