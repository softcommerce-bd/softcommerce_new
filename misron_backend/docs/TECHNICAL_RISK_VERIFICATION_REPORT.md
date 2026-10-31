# Technical risk verification report

**Result: the four risks are better protected and much better evidenced, but ALL FOUR REMAIN UNRESOLVED** (reasons in section 7). Nothing here is a staging or production result. Staging was not touched (its financial and stock fingerprint is identical to the last report), no feature flag was enabled outside test processes, no historical record was repaired.

## 1. Environment (isolated)
| Item | Value |
|---|---|
| Database engine | MariaDB 10.11.14 (Ubuntu 24.04), REPEATABLE-READ, `innodb_lock_wait_timeout` 50. **Not MySQL 8.4; the production engine is still unconfirmed.** |
| PHP | 8.3.6 CLI |
| Database under test | `inv_v2_test` (isolated test schema). Parity and rollback runs used disposable copies `parity_artisan_test`, `parity_sql_test` of a pre-migration database. |
| Flag | `INVENTORY_CONFIRMED_ONLY` true only inside the test process and its worker processes; default `false` everywhere else |
| Schema evidence | `docs/evidence/verification/environment-and-schema.txt` (engine, `SHOW CREATE TABLE ledger_outbox`, `refund_postings`) |

## 2. Test totals (final run, all on the isolated database)
| Suite | Result | Evidence |
|---|---|---|
| Full PHPUnit | **535 tests, 3431 assertions: 3 failures, all pre-existing** (`ExampleTest::testBasicTest`, `FunnelEditorMediaAndCatalogueTest::test_orders_carry_nullable_funnel_attribution`, `UploadSafetyTest::test_svg_and_xml…`); 2 skipped (schema-conditional) | `phpunit_full.txt` |
| Concurrency (real parallel processes) | **44 tests, 1430 assertions, all pass** | `phpunit_concurrency.txt` |
| New regressions | none left (one existing test was changed on purpose, see D) | |
| Fingerprint of the test database before vs after everything | **identical** (financial, stock, supplier, chart and new-table counts) | `fingerprint_before.txt`, `fingerprint_after.txt` |
| Artisan vs SQL migration parity (all 9 inventory_v2 migrations) | **identical**: 1,798 column lines, 291 index lines, 174 table/engine lines, accounts/permissions/migration rows | `migration-sql-parity.log`, `tests/staging/parity-check.sh` |
| Rollback script | tables identical to pre-migration; remaining differences are the two deliberate column widenings and the lookup indexes (left on purpose) | this report |

## 3. A. Transactional outbox
| Requirement | Test | Result |
|---|---|---|
| Two or more concurrent workers, no duplicate entries | `OutboxVerificationTest::four_workers_clearing_the_same_backlog…` (4 processes, 40 groups) | exactly 40 entries, 80 rows linked, 0 duplicate keys, trial balance balanced |
| Termination at every processing boundary | `…a_worker_killed_at_every_boundary…` kills the process with SIGKILL after lock, after lines, after journal, after marks, after commit | before commit: nothing half-done (entry and marks roll back together); after commit: already complete; a second worker then finishes exactly once; a repeat run posts nothing |
| A killed worker does not block others | `…a_killed_worker_does_not_block_the_other_workers` | pass |
| Failed journal posting and recovery | `LedgerOutboxRecoveryTest` (6 tests) | change stays queued with `last_error`, command writes one entry, re-running posts nothing more |
| Scheduled execution | `OutboxOperationsTest::the_recovery_command_is_scheduled_every_minute…` | `ledger:process-outbox --alert`, `* * * * *`, without overlapping |
| Alert above five minutes | `…pending_over_five_minutes_raises_a_critical_log…`; reconciliation check `outbox_pending` | critical log + exit code 2 until recovered; fresh rows do not alert; reconciliation turns high |
| Traceability both ways | `…a_posting_can_be_traced_from_the_business_transaction_to_the_journal_and_back` | outbox group token <-> `journal_entries.idempotency_key = legacy:<token>`, `source_type/source_id` = the document, `ledger_outbox.journal_entry_id` -> entry |
**Defect found and fixed by this phase:** the processor locked outbox rows by token / `processed_at`, taking index gap locks that deadlocked against live old-screen transactions inserting their own rows (about half of the mixed-activity runs failed with "There is no active transaction", Laravel's masking of the deadlock). It now locks exactly the group's rows by primary key. 10 consecutive mixed runs and 3 rounds of 16 live postings against 4 workers pass. Regression: `test_live_old_screen_postings_racing_outbox_workers…`.

## 4. B. Refund accounting
| Requirement | Test | Result |
|---|---|---|
| Wallet, cash, bank, gateway | `RefundAccountingTest` (8 tests) | right accounts credited; bank accepted like cash; gateway needs and de-duplicates its reference |
| Two administrators at the same moment | `RefundVerificationTest::two_administrators_paying_the_same_refund_at_once…` (two processes through the real controller method) | one wallet row, balance credited once, one posting, liability +300 once |
| Same refund request / same gateway reference from two processes | `…the_same_refund_request_or_gateway_reference…` | exactly one succeeds |
| Partial and multiple refunds on one line | sequential 300+400+300 = 1000, then 0.01 refused; parallel 6 x 300 against a 1000 line | exactly three succeed, total 900, refused ones fail with "exceed"; all postings journalled |
| Cannot exceed the refundable amount | cap per line (price + tax) | enforced under parallel load |
| Rollback consistency | `…a_refused_refund_leaves_wallet_balance_postings_and_every_account_exactly_as_before` | wallet rows, user balance, postings, journal, every account balance and the request status unchanged |
**Defect found and fixed:** parallel refunds of one line deadlocked (existence checks used locking reads, taking gap locks). The order line is now locked first (serialising refunds of a line) and the unique keys decide duplicates; 3 stable runs.
**Missing integrations, stated plainly (not built):**
* Wallet **top-ups** (customer pays money in) are not journalled.
* Wallet **spending** at checkout is not journalled, so the Customer Wallet Balances liability shows refunds only; reconciliation prints the difference to `users.balance` as informational.
* **Gateway refund API:** nothing calls a gateway to return money. A gateway refund is recorded only when someone supplies the gateway's refund reference.
* **No cash/bank refund screen**, only the service.
* Refunds paid before the flag was on are not posted (history is not rewritten).

## 5. C. Historical cost reporting
`CostReportingVerificationTest`, `FinancialReportsLedgerTest::…historical…`. One report run over confirmed, cancelled, partially refunded and uncosted lines:
* confirmed shipped line -> FINAL (actual FIFO cost); uncosted shipped line -> UNCOSTED, excluded, with a labelled planning ESTIMATE (2000 - 2 x 300 = 1400) shown separately; historical line with a recorded cost -> HISTORICAL, unverified, own subtotal; historical line without cost -> "cost unknown"; cancelled order's line not listed or counted.
* Refunds are shown per line and in the note, **not netted** against any profit figure.
* **Estimates never enter accounting totals:** dispatching a line with an estimate posted no entry and moved no account balance (full ledger fingerprint identical); the P&L and trial balance do not contain it; reconciliation lists the line as excluded.
* Original order and cost rows are byte-identical after the report is read (JSON of the rows compared before/after); the ledger fingerprint is unchanged.
**Accountant approval requirement:** this is a reporting policy (`docs/HISTORICAL_ORDER_COSTS.md`). Using any historical or estimated figure in statutory or management reporting, and any decision to finalize a historical line, **requires an accountant's written approval**; none has been given.

## 6. D. Inventory and deadlock handling
| Requirement | Evidence |
|---|---|
| Index migration and SQL parity | parity-check above (all 9 migrations identical); `StockLookupIndexTest`: preflight refuses the flag when a lookup index is missing, `inventory:db-health` names it, indexes restored after |
| Retry limits and rollback | `DeadlockLimitsTest`: exactly 3 attempts then the deadlock surfaces; every attempt's rows rolled back; no key left; two deadlocks then success commits once; ordinary errors are not retried. `DeadlockRecoveryTest`: a real two-process deadlock kills one plain transaction, both complete through the service wrapper |
| Legacy storefront, POS and funnel paths | **Protected, bounded:** the new `retry.deadlock` middleware (max 3 attempts, short random pause) is on `api/v2/order/store`, `api/v2/guest/order/store`, funnel `{slug}/checkout` and both `pos-order` routes; it retries ONLY when the answer says deadlock (`Deadlock found`, `SQLSTATE[40001]`, the funnel's `inventory_busy`); refusals/validation/success are never retried (`DeadlockMiddlewareTest`, 5 tests; routes asserted). The existing test that expected exactly one stock attempt now expects three, then the same sanitized 503 |
| Not covered (explicitly blocked from being called safe) | other order-creation paths: admin "create order", chat orders, courier/settlement and the purchase screens still run their own transactions with no retry; a deadlock there is an error the user retries. Until the owner accepts this or the paths are wrapped, treat it as an **open activation condition** (T-5) |
| Realistic concurrent activity | `MixedActivityVerificationTest`: in one race, 4 confirmations, 3 goods receipts, 6 storefront guest orders over HTTP, 5 old-screen postings, 2 outbox workers; result: stock 9/demand 8/allocated 8 exactly, 6 orders created, pending orders took nothing, every posting journalled once, nothing left in the outbox, trial balance balanced, no critical reconciliation finding. Repeated 10 times without failure after the fix |
| Stale statistics | `StaleStatisticsConcurrencyTest` (24-shopper storm x 11 across: no indexes + frozen stats, indexes + frozen stats, after `inventory:db-health --analyze`): 0 deadlocks in every condition on this engine. **The original failure is still not reproduced.** |

## 7. Why all four stay UNRESOLVED
| Risk | Missing evidence |
|---|---|
| R1 deadlocks / statistics | cause of the one observed failure unproven; no run on the production engine (MySQL 8.4?) or production-sized data; legacy paths other than storefront/POS/funnel unprotected (T-5) |
| R2 refunds | accountant has not approved the account treatment; wallet top-up/spending and gateway API integrations missing; no cash-refund screen |
| R3 historical cost | accountant has not approved the reporting policy |
| R4 outbox | no real-engine run; scheduler (cron) must be proven to run on the target host; no production-scale load test; a short window remains in which a balance is ahead of its entry |

## 8. Other findings fixed on the way
* `refund_pay` duplicate credit (earlier phase) is covered by the parallel-administrator test.
* Test hygiene: two tests leaked rows (DDL implicit commit, committed accounts); fixed and proven by the identical before/after fingerprint.

## 9. Not done / still blocked
Real production engine output, fresh backup, real admin theme, accountant review, staging activation. Nothing was done on staging or production. **No approval to activate staging is implied by this report.**
