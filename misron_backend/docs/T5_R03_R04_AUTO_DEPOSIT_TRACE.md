# R-03 / R-04: `accounts:auto-deposit` - complete execution path, production schema dependency, activation paths and safeguards

> **STATUS AT `98339a1`:** safeguards S4 (readiness check inside the command) and S8 (no repository file enables the switch) are IMPLEMENTED and tested; the old body was also corrected (statement lines, system-user creator, charged methods unsupported). S1-S3, S6, S7 are operational rules in `T5_FINAL_DEPLOYMENT_RUNBOOK.md`. The job stays paused by default. Text below that says 'no application code changed' describes the moment of writing.

**Status: ANALYSIS AND PROPOSAL ONLY. No flag, schedule, financial logic or application code was changed; no database was written; no `.env` content was read or printed (no env file exists in this checkout). Nothing here is an approval.** Evidence: `docs/evidence/r01_r03/02_dump_facts.txt`, `03_autodeposit_env_checks.txt`. Production schema and configuration are UNKNOWN (DBA-04).

## 1. Execution path (as the code stands at `775a236`)
| Step | Where | What happens |
|---|---|---|
| 0 Trigger | `app/Console/Kernel.php:67` | `schedule:run` (cron every minute) starts `accounts:auto-deposit` daily at 00:01 Asia/Dhaka, `withoutOverlapping()` (cache lock). Also runnable by hand. No controller, route, job, event or other command calls it. |
| 1 Guard G1 | `AutoDepositFunds.php:27-35` | if `config('accounts.auto_deposit_approved')` is false (the shipped default; `config/accounts.php` reads env `AUTO_DEPOSIT_APPROVED`, default false) the command logs `AUTO_DEPOSIT_PAUSED_AD1`, prints the message and returns `SUCCESS` (0) **before any database access**. |
| 2 Selection (only past G1) | `:38-45` | `TransactionMethod` rows with `deposit_type='auto'`, `status='active'`, with `sourceAccount` and `bankAccount`; none -> "No active auto-deposit methods", exit 0. |
| 3 Per method | `:61-103` | `DB::beginTransaction()`; source account re-read with `lockForUpdate()`; balance <= 0 -> rollback, skip; charge = balance x method charge %; transfer = balance - charge. |
| 4 Document | `:80-92` | `BalanceTransfer::create([...'voucher_no' => generateVoucherNo('auto_deposit') (AD-YYMMNNNN, row-locked read), 'transaction_type' => 'auto_deposit', 'status' => 'completed', 'created_by' => null ...])`. |
| 5 Balances | `:95-96` | `$source->decrement('current_balance', $balance)`, `$destination->increment('current_balance', $transferAmount)` (through `ChartOfAccountBuilder`, so the legacy-ledger bridge sees the delta when the inventory flag is on). **No `account_statement_logs` lines are written** (the manual screen writes them in `insertTransactionLogs`). |
| 6 Charge | `:99-101`, `recordChargeExpense` | if charge > 0: finds the "Card/Mobile Banking Charge" expense account (name match, code `5-51002`, fallback account **id 67**), creates an `ExpensePayment` with `status='completed'`, `created_by => null`, a numbered voucher. No expense account balance change is written here. |
| 7 Commit / failure | `:103`, `:108-117` | commit; or rollback, log "Auto-deposit failed for ...", count failure and carry on; the run always ends with exit code 0 (so the scheduler never reports a failed run). |

## 2. The production schema dependency (what must be true for the old body to work)
From the supplied dump and the disposable test database (both identical on these points; **production is unknown**):
| Column | Dump / test DB | Written by the job | Consequence |
|---|---|---|---|
| `balance_transfers.created_by` | `bigint unsigned NOT NULL` | `NULL` | explicit NULL into NOT NULL is an error even in non-strict mode: every method fails and rolls back (what the tests show) |
| `balance_transfers.transaction_type` | `enum('withdraw','deposit','transfer','cashout')` | `'auto_deposit'` | **not a legal value**. Strict mode: error 1265. The application connection is non-strict (`config/database.php` `strict => false`), where an illegal ENUM value is stored as the empty string with a warning (standard MySQL/MariaDB behaviour; not executed in this phase) |
| `balance_transfers.status` | `enum('pending','approved','rejected','cancelled')` | `'completed'` | same: not legal, stored blank in non-strict mode. (The manual balance-transfer screen writes `'completed'` too - an existing quirk worth a separate look, see NB-6.) |
| `expense_payments.created_by` | `bigint unsigned NOT NULL` | `NULL` (only if charge > 0) | the charge expense fails -> the whole method rolls back |
| `expense_payments.status` | same enum without `'completed'` | `'completed'` | blank in non-strict mode |
| statement lines | n/a | none written | balances change with no statement trail |
So "nullable `created_by` in production" (the question asked as DBA-04) is **necessary but not sufficient**. The old body can only complete if production *also* accepts NULL in `expense_payments.created_by` (when a charge applies) and the ENUMs differ from the dump; otherwise it either fails or writes blank type/status rows and moves money with no statement lines. The earlier race tests (which made `balance_transfers.created_by` nullable on a disposable database, charge 0) demonstrate the "moves money" case: the balance is read under the lock and moved exactly once - they do not show that the records are well-formed.
Case matrix (old body running):
| Production state | Result per method |
|---|---|
| as the dump (`created_by` NOT NULL) | insert fails, rollback, error logged, nothing moves, exit 0 - the safe failure the owner assumed |
| `balance_transfers.created_by` nullable only, charge 0 | **money moves**; transfer row with blank type/status (non-strict), no statement lines |
| same, charge > 0 | expense insert fails (NOT NULL) -> whole method rolls back, nothing moves |
| both `created_by` columns nullable | money moves and the charge expense row is written, both with blank status, no statement lines |
| enums widened and creators nullable | works end to end but still no statement lines |
**R-03 re-assessed:** G1 changes behaviour in production only in the rows where the job currently moves money. Given the dependency chain, a currently *working* job requires an unusual schema; DBA-04 must still be answered (nullable? enums? any `deposit_type='auto'` active methods? any `AD-%` vouchers or blank-type transfers already present?).

## 3. Every mechanism that could enable the old job body (R-04)
| # | Mechanism | Who / where | Effect | Detectable by |
|---|---|---|---|---|
| E1 | `AUTO_DEPOSIT_APPROVED=true` in the process environment | server env, php-fpm pool `env[...]`, systemd unit, container/orchestrator env, CI | runs at the next 00:01 | env review; `config('accounts.auto_deposit_approved')` on the host |
| E2 | The same value in an env file (`.env`, or `.env.<APP_ENV>` which Laravel loads for that environment) | deploy tooling / person editing the server | same | file review (none exists in this checkout) |
| E3 | Cached configuration containing `true` (`php artisan config:cache` run while E1/E2 held) | deploy step | stays `true` even after the env is corrected, until `config:clear` | `bootstrap/cache/config.php` |
| E4 | Editing `config/accounts.php` default to `true`, or removing/altering the guard | a code change + deploy | permanent | code review / diff / tests (`AutoDepositPausedGuardTest` fails) |
| E5 | `config(['accounts.auto_deposit_approved' => true])` at run time (tinker, a script, a test process) | developer with shell access; tests do this on disposable databases only | one process | shell audit |
| E6 | Another host or a second scheduler with a different environment | multi-host deployments (`withoutOverlapping` is per cache store) | that host runs it | per-host env review |
| E7 | Manual `php artisan accounts:auto-deposit` after E1-E5 | operator | one run | command history |
| E8 | Reverting to an older release/image that predates G1 (the old body had no switch and ran nightly) | rollback/redeploy | runs nightly | version check on every host |
| E9 | A future developer adding a second caller (route, job, event) | code | bypasses nothing (the guard is inside the command) but widens exposure | code review |
E8 is the most realistic: **any rollback to a commit before `25aec82` re-enables the old body with no switch.**

## 4. Proposed safeguards (none implemented; each needs your approval and the code ones a separate go-ahead)
| # | Safeguard | Type | Closes | Notes |
|---|---|---|---|---|
| S1 | Deployment checklist step: print and record `config('accounts.auto_deposit_approved')` on every host after deploy; must be `false` | ops | E1-E3, E6 | needs no code |
| S2 | Forbid the variable in all env sources: infrastructure review of env files, pool config, unit files, orchestrator manifests; add a CI grep that fails if `AUTO_DEPOSIT_APPROVED` appears in any deploy file | ops/CI | E1, E2 | CI step is a repository change |
| S3 | Release rule: after `config:cache`, run `config:clear` on change; never cache with the variable present | ops | E3 | |
| S4 | **Schema self-check inside the command** (future code): even when the switch is on, refuse unless `balance_transfers.created_by`/`expense_payments.created_by` accept the creator being written, the ENUM values `auto_deposit`/`completed` exist, and a system user is configured; log and exit instead | code | E1-E5 | turns "someone set a variable" into "someone set a variable and the schema was prepared for it" |
| S5 | Remove the env switch once an AD-1 option is really implemented; replace by the option's own approval record (e.g. a configured system-user id) | code | E1-E5 | part of DEC-16 |
| S6 | Monitoring: alert if the log tag `AUTO_DEPOSIT_PAUSED_AD1` is absent on a night, or any of "Auto-deposit completed", `Auto-deposit failed for` appears; weekly read-only query (below) | ops | all | detection, not prevention |
| S7 | Rollback rule: the release process must treat commits before `25aec82` as unsafe for the scheduler (pause the scheduler or set the switch false and keep a code-level kill) when rolling back | ops | E8 | an older build has no switch at all |
| S8 | Test pins (future): a test that fails if any file in the repository sets the variable to true; the existing `AutoDepositPausedGuardTest` already pins the default and the pause | code | E4, E9 | |
| S9 | Restrict who can change server environment and who can run artisan commands in production; log artisan runs | ops | E5, E7 | |
Read-only detection queries for S6 (run by the DBA; read-only):
```sql
SELECT COUNT(*) AS auto_vouchers FROM balance_transfers WHERE voucher_no LIKE 'AD-%';
SELECT COUNT(*) AS blank_type_transfers FROM balance_transfers WHERE transaction_type = '' OR status = '';
SELECT COUNT(*) AS auto_charge_expenses FROM expense_payments WHERE description LIKE 'Auto-deposit charge%';
SELECT COUNT(*) AS active_auto_methods FROM transaction_methods WHERE deposit_type = 'auto' AND status = 'active';
```

## 5. Corrections to earlier documents this trace requires
* `T5_AD1_AUTO_DEPOSIT_OPTIONS.md`: options (a) and (b) as written address only `balance_transfers.created_by`. Enabling the job later also needs the ENUM values, `expense_payments.created_by` for charged methods, statement lines, and a decision about the account used for charges (fallback id 67). An addendum was added to that file.
* Audit finding R-03 (nullable `created_by` is sufficient to make the job move money) is incomplete; see section 2.
