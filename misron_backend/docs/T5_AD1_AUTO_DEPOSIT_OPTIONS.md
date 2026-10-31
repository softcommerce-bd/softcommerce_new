# AD-1: nightly auto-deposit and the NOT NULL `created_by` - option comparison

**Status: NOT YET APPROVED. Nothing here is implemented; no production or staging change is proposed to run.** This document compares the three options and recommends the safest.

## 1. Verified facts
* `app/Console/Commands/AutoDepositFunds.php` (`accounts:auto-deposit`, scheduled daily 00:01 Asia/Dhaka, `withoutOverlapping`) creates one `balance_transfers` row per active method with `deposit_type = 'auto'`, with `created_by => null`.
* `balance_transfers.created_by` is **NOT NULL** in every local schema (the original-schema clone, `sc_staging`, `sc_stg_pre`, the test database), so the insert fails for every method; the failure is caught per method (logged, reported "Failed to transfer for '<method>'", exit code 0), the transaction rolls back and **nothing moves**. Production schema: **BLOCKED** (unknown).
* In the staging copy there are **0 transfers and 0 auto-deposit methods** (metadata/aggregate read only), so in that copy the job has never had anything to do; whether production has configured auto methods is unknown (BLOCKED).
* Every human-entered document table has `created_by NOT NULL` (balance transfers, vouchers, incomes, expenses, customer/supplier payments, stock adjustments, bank transactions); only the system-produced ledger tables (`journal_entries`, `ledger_outbox`) allow NULL. Views already tolerate a missing creator (`$transaction->creator->name ?? '-'`).
* System origin is already recorded independently of the creator: `transaction_type = 'auto_deposit'`, voucher prefix `AD-`, reference `AUTO-<date>-<method id>`.
* A race that moved the balance twice was fixed in this phase (the balance is read under the account lock); it is only observable on a schema that accepts the insert (tested with the column made nullable on the disposable database, then restored).
* Login: `IsAdmin` admits only `user_type` `admin` or `staff`; `users.user_type` is `varchar(20)`.

## 2. Options
| | **(a) Named system user as creator** | **(b) Make `created_by` nullable (migration)** | **(c) Keep the job disabled / do nothing** |
|---|---|---|---|
| What changes | one new `users` row (the "System - auto deposit" account); the command writes its id | `ALTER TABLE balance_transfers MODIFY created_by ... NULL` | nothing (job keeps failing harmlessly, or its schedule is removed) |
| Auditability | Every row has a real, named, queryable creator; reports and print views show "System"; no loss of the NOT NULL guarantee for human entries | Weakest: NULL means "system" **or** "missing" (a bug elsewhere that forgets the user would now be accepted silently); the `AUTO-` reference still marks the source | No new rows, so nothing to audit; deposits stay manual transfers with a human creator |
| Accounting implications | None on figures: same debit/credit, same voucher numbers (`AD-`); the creator is attribution only | None on figures; reports grouping by creator gain a NULL group | Deposits continue to be made by hand: timing and charge expense depend on staff |
| Security | The account is a standing identity: must not be able to log in (unusable password, `user_type` outside `admin/staff`, banned flag, no roles/permissions), otherwise it is a backdoor; creating it is a data insert in production | No new identity; a schema change on a core financial table (size and engine of production unknown, `ALTER` may lock a large table; needs a maintenance window and rollback = restore) | Safest: no new identity, no schema change |
| Reversibility | Easy: stop using the user (its historical rows keep their creator) | Hard to reverse once NULL rows exist (re-adding NOT NULL requires backfilling) | Trivial |
| Production risk | Low (one insert), but it is a production data change needing approval | Medium (DDL on a financial table) | None |
| Consistency with the rest of the system | Matches the rule used by all other document tables | Breaks the rule for one table | Unchanged |
| Operational effect | Nightly deposits start working (money moves every night) | Same | Nothing starts moving |

## 3. Recommendation (the safest approach)
1. **Now: (c).** It changes nothing in production, nothing is lost today (the job cannot move money in any inspected schema, and the staging copy has no auto method), and money must not start moving every night as a side effect of a technical fix. If the failing nightly run is considered noise, the command can later be made to stop with one clear message when the schema rejects system rows - an optional change, not made.
2. **If the business wants nightly auto-deposit: (a).** It keeps the NOT NULL guarantee that protects every other document, keeps attribution explicit, needs no DDL on a core table and is reversible. Conditions for approval: the account is a **non-login system identity** (unusable password, `user_type` that `IsAdmin` rejects, banned, no roles), created by a reviewed migration/seeder, with its id read from configuration rather than hard-coded; the first production runs are supervised with the `AD-` vouchers reconciled by the accountant.
3. **(b) is not recommended**: it weakens the audit trail of the whole table and is the only option with a DDL risk on production.

## 4. Approval needed (nothing is done until it is given)
* Choose c (recommended now), a, or b. If a: approve creating the system user and its exact attributes and the configuration key; approve that the first runs are supervised.
* Provide the production answer to "is `balance_transfers.created_by` nullable there, and are any `deposit_type = 'auto'` methods configured?" (queries in `T5_PHASE0_AUDIT_QUERIES.sql` section 0 and `SELECT COUNT(*) FROM transaction_methods WHERE deposit_type='auto' AND status='active'` on a restored copy).
* Whether the schedule line stays.

Tests that exist: `PosAndConsoleParallelTest::test_the_auto_deposit_job_fails_on_the_unmodified_schema_because_created_by_is_not_nullable` and `...two_auto_deposit_runs_at_once_move_the_balance_exactly_once` (uses a temporarily nullable column on the disposable database only).

## 5. Addendum (final remediation): maintenance, compatibility, and exactly what option (c) leaves disabled
* **Compatibility with the existing user model.** `users.user_type` is `varchar(20)` and `IsAdmin` admits only `admin`/`staff`, so a system identity with a different `user_type` (for example `system`), an unusable password and the `banned` flag cannot sign in through the admin guard. Whether any other code path treats "a `users` row exists" as "a person" (notifications, dashboards counting users, role pickers) was not audited: this must be checked before (a) is approved. Views already print `creator->name ?? '-'`, so both (a) and (b) render.
* **Migration/rollback.** (a): one reviewed insert (rollback = ban the row; history keeps its creator). (b): DDL on `balance_transfers` of unknown production size/engine; rollback after NULL rows exist needs a backfill first. (c): none.
* **Operational maintenance.** (a): one configuration key (the id) to keep in sync across environments; a restored database without the row would make the job fail again (loudly, per method). (b): none, but "NULL means system" must be remembered by every future report. (c): none.
* **What option (c) does and does not disable.** (c) is "no change": the schedule line in `app/Console/Kernel.php` stays, the job keeps running at 00:01 and keeps failing harmlessly per method on a NOT NULL schema (nothing moves, exit code 0, one log line per method). It does **not** switch the job off. If a quiet system is wanted, removing the schedule line is a separate, tiny, reversible change that needs its own approval. Manual transfers (balance-transfer screen) are unaffected in every option.
* **Required to enable safely later (under (a)):** approved system-user attributes and configuration key; production answer on `created_by` nullability and on configured `deposit_type = 'auto'` methods; the account/charge rules reviewed by the accountant (the amount and charge computation is unchanged by this work); supervised first runs with the `AD-` vouchers reconciled; the existing race tests (below) re-run against the real schema.
* **Tests re-run for this addendum** (disposable database only): see `docs/evidence/final5/`. They prove the existing locking (balance read under the account lock, two simultaneous runs move the balance exactly once, a concurrent manual transfer keeps the books exact) and that the unmodified schema still refuses the insert.

## 6. Decision-support addendum: side-by-side summary and an interim guard (proposal only - nothing changed)

| | (a) system user | (b) nullable column | (c) no change |
|---|---|---|---|
| Operational impact | nightly deposits start moving money (and charges) every night | same as (a) | deposits stay manual; the job keeps failing quietly |
| Auditability | named actor on every row | creator may be NULL = "system" or "forgotten" | no new rows |
| Security | a standing identity that must not be able to log in | no identity; weaker integrity | none |
| Migration risk | one reviewed insert; roll back by banning the row | DDL on `balance_transfers`, size/engine unknown, hard to undo once NULLs exist | none |
| Reversible | yes | hard | yes |
| Needs from you | user attributes + config key + supervised first runs | approval of DDL window | nothing |

**Why a guard is worth having while approval is pending.** With option (c) the 00:01 job still runs. Per active auto method it opens a transaction, fails on the NOT NULL column, rolls back, writes one error log line and tells the console "Failed to transfer ...", exit code 0 (so the scheduler never reports a failure and nobody is alerted). Nothing moves, but the log fills with one identical error per method per night, and the day the schema is changed or a method is configured the behaviour would change silently.

**Three ways to stop the repeated failure (none implemented; each needs your authorization):**
| Guard | Change | Touches schedule / schema? | Effect | Risk |
|---|---|---|---|---|
| **G1 (recommended first)** command preflight | at the start of `accounts:auto-deposit`, if no creator can be recorded (the column is NOT NULL and no approved system-user setting exists), write ONE clear line "auto-deposit paused: decision AD-1 pending" and stop before opening any transaction | no / no | one log line per night instead of one per method; nothing moves; when (a) is approved and the setting exists, the guard steps aside by itself | code change in one command; trivial to revert; tested on the disposable database |
| G2 configuration switch | `AUTO_DEPOSIT_ENABLED=false` read by the schedule (`->when(...)`) | **yes (schedule)** | the job is not run at all | needs the schedule edit authorization; the switch must be turned on again explicitly |
| G3 remove the schedule line | delete the line in `Kernel.php` | **yes** | job never runs | easy to forget it was removed |
Recommendation: G1, because it needs neither a schedule edit nor a schema change, stays correct under (a), and keeps the evidence that the job was waiting on a decision. Authorize G1 (and I implement and test it) or choose G2/G3 or nothing.
