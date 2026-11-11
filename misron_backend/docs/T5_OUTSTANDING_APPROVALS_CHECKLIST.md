# T-5 final outstanding approvals checklist

State at commit `a967de0` (2026-11-08). Every box is **unchecked on purpose: no approval, review, answer or sign-off listed here has been given.** Nothing is to be inferred from silence. IDs refer to `T5_FINAL_ACCEPTANCE_AUDIT.md` (matrix) and the decision register. "Recommended" is the assistant's technical recommendation, not a decision. Responsible parties are roles; no names are recorded in the project.

## A. Owner
- [ ] **MYSQL/OUTBOX/earlier rulings** - nothing outstanding (P-1, supplier-edit treatment, B-1.1, AD-1.1 option C, AD-1.2 guard G1, N-1.1, INC.1 checklist, MYSQL84.1, MYSQL84.2, OUTBOX.1 are recorded as approved).
- [ ] **R-03 / AD-1 confirmation** - after the DBA returns DBA-04, confirm that pausing the nightly auto-deposit (G1) is still wanted if production turns out to be running it. *Recommended: yes, option C, unless the business relies on nightly deposits.*
- [ ] **DEC-16** run nightly auto-deposit at all? (a non-login system user / b nullable column / stay on c). *Recommended: stay on c.*
- [ ] **DEC-07 (SEQ.1)** approve a `document_sequences` table (schema change, rehearsal first) for the other numbered series, or accept retry-only protection. *Recommended: yes, after a rehearsal.*
- [ ] **DEC-12** data-access approval and environment for the full Phase 0 audit SQL on an isolated restore (the supplier part is already approved as N-1.1).
- [ ] **DEC-18 / DEC-19 (N-1.2, N-1.3)** correction procedure and approver for any distorted supplier balance; whether to add a supplier-payment edit audit table. *Recommended: accountant-led, supplier by supplier, adjustment document, never a script; audit table later.*
- [ ] **R-01** decide whether T-5 activation must be gated by an explicit "all money/stock tables are InnoDB" check (preflight/checklist). *Recommended: yes.*
- [ ] **VER-01** supply the real admin theme package.
- [ ] **DEC-20 (P6)** explicit written approval to enable `INVENTORY_CONFIRMED_ONLY` on staging - **do not give before sections B-D that apply to activation are satisfied.**
- [ ] **VER-06** final acceptance of T-5 and of staging (cannot be given today).

## B. Accountant
- [ ] **ACC-01 (N-1.1)** with the DBA, run Q1-Q10 on an isolated restored copy via `tests/staging/n1_readonly_audit.sh`; classify every flagged supplier; return the template in `T5_N1_ACCOUNTANT_REVIEW_PACKAGE.md`; sign. No correction.
- [ ] **ACC-02 (P5)** rule on the nine differences D-01..D-09 of `docs/accountant-review-pack/` and complete its sign-off table.
- [ ] **ACC-03** name the expense account for bank/withdrawal/transfer charges (today the source is credited amount + charge and no expense is debited), or confirm one-sided posting is intended.
- [ ] **ACC-04 (T-2)** accept or specify the wallet-only refund treatment.
- [ ] **ACC-05 (T-3)** accept or specify the historical uncosted-order reporting policy.
- [ ] **ACC-06 (T-09/T-10)** written review of D3 (balance delta writes) and D4 (supplier row locks).
- [ ] **DEC-02** purchase delete vs supplier due: A keep / B reduce due / C forbid delete with payments; historical mismatches report-only? 
- [ ] **DEC-03 (T-07)** overdraft policy per screen (block / warn / no rule) and evaluation point for updates.
- [ ] **DEC-04** supplier edit/delete: A lock + refuse stale / B apply difference / C overwrite + audit; who may edit balances.
- [ ] **DEC-06** numbering formats / period resets / gap-free rule. *Recommended: keep existing formats.*
- [ ] **DEC-08** which maintenance commands may run while trading; who authorises each `--apply`.
- [ ] **DEC-10** is an hourly customer-payment import wanted; account/method rule; response format.
- [ ] **DEC-13** timing of money-column conversion (varchar/double -> decimal).
- [ ] **ACC-07 (P8)** `accounting:baseline` immediately before any activation (only after P6).

## C. DBA / infrastructure
- [ ] **DBA-01 (INC.1)** return `T5_INC1_DBA_HANDOFF.md` section 4: self-check output, `inc1_db_facts.sql` output, ten answers (all UNANSWERED today).
- [ ] **DBA-02 (P1)** production engine and exact version, storage engines, isolation level, FKs/indexes.
- [ ] **DBA-05** confirm whether `purchases`, `purchase_products`, `suppliers`, `purchase_returns`, `purchase_return_products` (MyISAM in the supplied backup) are MyISAM in production; if so the InnoDB conversion is a prerequisite.
- [ ] **DBA-04** `balance_transfers.created_by` nullable? number of active `deposit_type='auto'` methods?
- [ ] **DBA-03 (P2/P3)** fresh backup + checksum; rehearsal on the masked copy (conversion, artisan + SQL, parity, rollback). Needed for the T-02 index migration as well.
- [ ] **DEC-14** unique constraints after the audit shows no duplicates; handling of any duplicates (with the accountant).
- [ ] **VER-03 / VER-04** production-like proof for T-1 (deadlock/stale statistics) and the T-4 outbox load test with the scheduler.

## D. Order desk / Purchasing / Tech lead / Management
- [ ] **DEC-01** wording and status for "already done" repeat answers; which forms carry tokens; cut-off date; tombstone storage (Order desk, Accountant, Tech lead).
- [ ] **DEC-05 (T-11)** checkout duplicate identity: token mandatory or optional, retention, owner of the payment view, replay message; accept the behaviour change (Order desk, Tech lead).
- [ ] **DEC-09** purchase-order conversion design and the `converted_purchase_id` column (Purchasing, Accountant).
- [ ] **DEC-11** retention of `idempotency_keys` and alert owner/thresholds (Tech lead).
- [ ] **DEC-15** order cancel/delete/status transaction and notification outbox pattern; does `OrderController::destroy` stay (Order desk, Tech lead).
- [ ] **T-23** accept the documented test-coverage gaps (no workflow has all five categories for all operations) or fund more tests (Tech lead).
- [ ] **VER-02 (P7)** permissions tested with the real staff roles after the restore (Tech lead).
- [ ] **VER-05** the 3 known unrelated failing tests: fix or accept (Tech lead).

## E. Rule that applies to everything above
Nothing is deployed, merged, activated or applied to staging/production until the owner says so in writing; every schema change (T-02 indexes, DEC-07, DEC-09, DEC-19, DEC-14) needs its own approval and a rehearsal on the masked backup first.

## F. Added by the R-01 / R-03 / R-04 investigation (all unchecked; detail in `T5_DECISION_TRACKER.md`)
- [ ] **R01-A** approve the conversion rehearsal on a masked restore (Owner + DBA).
- [ ] **R01-B** approve an engine-only conversion script; the existing one also adds a unique key that belongs to DEC-14 (Tech lead + DBA + Accountant).
- [ ] **R01-C** approve the InnoDB gate in the activation checklist/preflight (Owner).
- [ ] **R01-D** approve the disposable MyISAM retry-hazard experiment (Owner).
- [ ] **R03-A** confirm option C after the DBA answers DBA-04 (Owner).
- [ ] **R04-A** choose the auto-deposit activation safeguards S1-S9; recommended minimum S1, S2, S3, S6, S7 (Owner + Tech lead).
- [ ] **DBA-04 (extended)** also return the ENUM definitions of `balance_transfers.transaction_type/status`, `expense_payments.status`, the nullability of `expense_payments.created_by`, and the four read-only counts of the trace document (DBA).
- [ ] **DBA-05 (extended)** engines **and sizes** of the five tables (DBA).

## G. After the autonomous completion mandate (2026-11-08)
Engineering is finished for what can be done without you; the unchecked boxes above are unchanged and still need the named person. Newly relevant, all unchecked: **E9** provide an isolated staging environment with sandbox-only credentials (nothing was deployed); **E7** the theme package (two deferred UI specs); confirm the `FINANCIAL_ENGINE_POLICY=enforce` default (or choose warn/off in writing); decide whether the auto-deposit job is wanted at all (DEC-16) - until then it stays paused and, if switched on, refuses to run.
