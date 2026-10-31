# First income number of a period: root cause, fix, and what remains

## 1. Symptom
`test_four_incomes_into_one_account_add_up_and_get_distinct_numbers` failed about 3 runs in 25 (a request used all 3 deadlock attempts). Attempt-scaled backoff (previous phase) lowered the rate but could not remove it; it is not a fix.

## 2. Exact mechanism (reproduced)
* `IncomeController::store` begins a transaction and, as its **first statement that locks anything**, calls `Income::generateVoucherNo()`: `SELECT ... WHERE voucher_no LIKE 'INC-26%' ORDER BY id DESC LIMIT 1 FOR UPDATE`. The optimizer uses the `voucher_no` index (`EXPLAIN`: `range`, key `voucher_no`).
* When the year has **no row yet**, that locking read finds nothing to lock and locks the **gap** of the index range instead. Gap locks do not conflict with each other, so every concurrent creator gets one.
* Each creator then INSERTs its row, which needs an insert-intention lock inside that gap, which conflicts with the other creators' gap locks. Everyone waits for everyone: InnoDB kills one as the deadlock victim (error 1213).
* Conflicting resources: the `incomes.voucher_no` index gap (`INC-26...`), nothing else. Lock order is not the issue (there is only one resource); the lock *kind* is.
* Pure-SQL reproduction (scratch database, two sessions, same statements): one session failed with the deadlock, one committed. Real-process reproduction: with the fix removed, 10 rounds x 4 simultaneous incomes from an empty year gave **19 of 40 requests that met a deadlock** (each rescued by the retry middleware, visible by the `X-Deadlock-Retries` header); with the fix **0 of 40**, in 5 consecutive repetitions of the 10-round test.
* It only exists for the first document of a period (year for incomes); afterwards a real latest row exists and is row-locked.

## 3. Fix (technical, no business rule, no schema, no format change)
`App\Support\NumberSerializer::hold('income:INC-<yy>')` is called by `Income::generateVoucherNo()` before the locking read. It takes a named database lock (`GET_LOCK`, 10 s) and keeps it until the outermost transaction ends - released on both `TransactionCommitted` and `TransactionRolledBack` events (and not before the test-suite wrapper level). Creators of the series therefore run one after another; the number format, width, reset and the "latest row + 1" rule are unchanged.
Safety properties and limits (all stated in the class):
* A named lock is invisible to InnoDB's deadlock detector. It is deadlock-free here **only because incomes call it before taking any other lock** in their transaction (verified in `IncomeController::store`). It is deliberately **not** applied to the other series, which generate their number after locking account rows (expense payment, balance transfer, customer payment/voucher) - applying it there would create a lock-order inversion that no detector would see.
* Needs one session = one server connection (no statement-multiplexing proxy). Not verified for production's connection setup (BLOCKED).
* If the lock cannot be obtained within 10 s or the call fails, the code carries on exactly as before and the deadlock retry still protects the request (logged as a warning).
* Same lock is reentrant for the same connection (a deadlock retry in the same process does not block itself).

## 4. Tests (all against the disposable `inv_v2_test` database)
* Regression: `WorkflowParallelCoverageTest::test_first_income_number_of_a_year_never_deadlocks_over_repeated_rounds` - 10 rounds, each from an empty year, 4 real parallel HTTP processes, asserts 4 distinct consecutive numbers 000001..000004 and **zero** deadlock retries. Fails without the fix (19/40), passes with it.
* Failure recovery: `test_a_failed_income_releases_the_number_lock` - a request that fails after the lock is taken must not hold the next one up (< 5 s, one income stored).
* Existing: `four_incomes...` (3/3 and repeated), `DeadlockRetryMethodCoverageTest` (47 filtered tests incl. income store) pass. Evidence: `docs/evidence/final5/`.

## 5. Are decisions 6 and 7 necessary?
* **Decision 6 (formats): not needed** for this fix; nothing about formats was touched or invented.
* **Decision 7 (sequence table): not needed for incomes now.** It **is still the correct permanent design for the remaining series** (expense payment `EXP`, balance transfer `WD/DP/TR/CO/AD`, voucher, stock adjustment, customer payment, and the future bank numbers) because they take their number after other row locks. Their real exposure is narrow: only the first document of a period, only simultaneous creators on **different** accounts (same-account creators already serialise on the account row lock before the number is read). It was reasoned from the code and the SQL reproduction; **not** reproduced per series, so it is labelled analysis, not test.
* Decision 7 would give: a pre-existing row per series (so a row lock, never a gap lock), gap-free or not by choice, one place for reset rules, and bank numbering for free. Cost: new table, seeding from current maxima, rehearsal, approval.
* Residual risk if 7 is never approved: other series' first-of-period collisions end in the retry (bounded, observable by the header), as before.
