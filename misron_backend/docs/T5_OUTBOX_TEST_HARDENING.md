# OUTBOX.1 - hardening `OutboxVerificationTest::test_a_killed_worker_does_not_block_the_other_workers`

**Owner decision OUTBOX.1 (approved).** Scope: make the test deterministic without weakening it; no change to production outbox behaviour (none was needed - no defect found). Disposable databases only (MariaDB 10.11.14 `inv_v2_test`; MySQL 8.4.13 in a throw-away container, destroyed afterwards - `docs/evidence/outbox_hardening/05_teardown.txt`). No schema or historical-data change, no production/staging access, no deployment or merge. **T-5 and staging acceptance remain OPEN.**

## 1. Why it failed (investigation)
The test started three workers together behind a start barrier and expected the one meant to be killed (`outbox_kill_at`, kill point `after_journal`) to reach the first group it processed. But the barrier only makes the processes start together; it does not decide who reaches a group first. `LedgerOutboxProcessor::processPending` reads the 12 waiting tokens and walks them in order; for each it locks the group's rows by primary key and, if another worker already completed it, returns null. If the two normal workers get through all twelve before the third has locked its first group (twelve tiny groups take milliseconds), the third finds nothing to do, **never reaches the kill point, finishes normally** and the test fails at "the first worker was killed".
Evidence (`01_before_flakiness.txt`, diagnostic copy of the test): in every failing run the "killer" returned `{"outbox":{"processed":12,"failed":0},"ok":true}` after about 70-80 ms - it completed without dying - while both survivors also reported 12 processed. In every passing run it returned `died`.
**The premise "fails on 8.4 but passes on MariaDB" was only partly true:** original test, 20 consecutive runs: MySQL 8.4.13 **3 failures (17/20)**, MariaDB **1 failure (19/20)**; the earlier 12-run sample (0/12 on MariaDB) was luck. 8.4 loses the race more often because worker start-up and the first statements are quicker/less uniform there; the cause is the same on both engines - a race in the test, not an engine difference and not a processor defect. The product claim itself (a killed worker cannot block the others) is not in doubt.

## 2. The change (tests only; `docs/evidence/outbox_hardening/test_changes.patch`)
| File | Change |
|---|---|
| `tests/Concurrency/worker.php` | new optional job keys: `wait_for` (+ `wait_for_timeout`, default 30 s) = a **second barrier**: after the common start barrier the worker waits until a given file exists; a timeout is recorded as `wait_for_timed_out`, never silent; `signal_file`: the kill hook of `outbox_kill_at` touches this file immediately before the SIGKILL |
| `tests/Concurrency/ConcurrencyTestCase.php` | `workDirectory()` accessor (the per-test scratch directory) |
| `tests/Concurrency/OutboxVerificationTest.php` | the test now forces the order of events: the doomed worker starts alone, locks the first waiting group (at `after_journal` it has also written that group's journal entry), announces it through the signal file and is SIGKILLed; the two survivors start only on that signal, i.e. always while the dead worker's transaction is being cleaned up by the server. A second test covers the earlier kill point `after_lock` (row locks held, nothing journaled) |
| Production code | **none** (`app/` untouched) |

## 3. Nothing weakened - assertions
All original assertions remain (killer died; survivors `ok`; 12 entries; no group left waiting) and new ones prove the order and the outcome: a precondition (12 groups waiting, 0 entries); the signal file exists; the survivors started because of the signal, not a timeout; no group failed in either survivor; each survivor finished within 30 s of starting (the server's own lock wait is 50 s, so a worker truly blocked behind the dead one would fail this); exactly one entry per group, **no duplicate**; all 24 outbox rows marked with their entry; trial balance balanced.
**Proof that the assertions are live (`02_mutation_checks.txt`, defects injected temporarily and reverted; `git status app` clean):** (1) doomed worker stops signalling -> fails ("it announced the kill point before dying"); (2) a production defect where a worker gives up after its first group -> both tests fail with "2 is identical to 12"; (3) a surviving worker stalls 35 s holding a group (what being blocked would look like) -> fails (a worker did not finish in the window).

## 4. Results (`03_after_30_runs.txt`, `04_concurrency_*`)
| | MySQL 8.4.13 | MariaDB 10.11.14 |
|---|---|---|
| Original test, 20 consecutive runs (before) | 17 pass / 3 fail | 19 pass / 1 fail |
| Hardened `test_a_killed_worker_does_not_block_the_other_workers`, 30 consecutive runs | **30 / 30** | **30 / 30** |
| New `test_a_worker_killed_while_holding_only_the_group_row_locks_does_not_block_the_others`, 30 runs | **30 / 30** | **30 / 30** |
| Complete concurrency suite (97 tests = 96 + 1 new), 2 runs each | OK, OK (2365 assertions) | OK, OK (2365 and 2363 assertions) |
| Fingerprint and row counts before/after | identical (+1 `translations` row, a pre-existing side effect of `translate()`) | identical |
(The assertion count of the suite varies by 1-2 between runs of the same code, as seen before; some tests loop until a condition and count assertions per iteration. No failure is hidden by it.)
The original default PHPUnit suite was not re-run: this change touches only the concurrency harness and test.

## 5. Not shown / left open
Production outbox behaviour is unchanged and was not found defective. The processor's reaction to a killed connection depends on the server noticing the dead socket; on both engines it did so well inside the 30 s bound here, which says nothing about a production proxy in between (INC.1 remains BLOCKED). Production engine/version still unknown (P1).
