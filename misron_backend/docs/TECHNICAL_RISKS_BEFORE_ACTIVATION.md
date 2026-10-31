# The four technical risks before staging activation

**All four remain UNRESOLVED.** Work was done only in the isolated development/test database and on disposable copies; staging and production were not touched, no flag was enabled, no historical record was repaired. "Resolved" requires evidence from a real-engine rehearsal and the accountant, which do not exist yet.

---
## R1. Deadlocks / stale table statistics
**Before:** one concurrency test failed 4 of 4 times right after the test tables were emptied and passed after `ANALYZE TABLE`. The staging copy of the dump has **no (product_id, variant) index on `product_stocks` or `purchase_products`** (only PRIMARY).
**Investigated (evidence):**
* PROVEN: without that index MySQL/MariaDB answers the "lock this stock row / oldest cost layer" queries (`... WHERE product_id=? AND variant=? ORDER BY id LIMIT 1 FOR UPDATE`) with a scan of the primary key; with the index it uses the index, whether the statistics are fresh or stale (EXPLAIN comparison in the investigation). A primary-key scan locks every row it passes.
* NOT reproduced: a 24-shopper storm with 3,000 neighbouring rows, with and without the indexes and with frozen statistics, produced 0 deadlocks in 3 runs per condition (`tests/Concurrency/StaleStatisticsConcurrencyTest.php`, results printed as "STALE-STATISTICS EXPERIMENT"). The original failure mechanism is therefore **unproven**; it may depend on engine background statistics recalculation.
* PROVEN: a deadlock victim is lost unless the code retries. A deterministic crossing-lock test (`DeadlockRecoveryTest`) shows a plain transaction loses one of two; `Idempotency::once` now runs with 3 attempts and both complete exactly once.
**After (implemented):**
1. Migration `2026_11_03_100200_add_stock_lookup_indexes` (+ identical SQL route): adds the non-unique lookup index only when no equivalent exists (artisan and SQL routes verified identical on two disposable copies).
2. `InventoryMode::preflight()` refuses the flag (and `inventory:preflight` says why) when either index is missing. Test: `StockLookupIndexTest`.
3. `php artisan inventory:db-health [--analyze]`: read-only report of indexes and statistics (estimated vs actual rows); `--analyze` only refreshes statistics.
4. Deadlock retry (3 attempts) in the transaction owned by `Idempotency::once` (goods receipts, supplier invoices/payments/returns, opening cost, refunds, journal posting).
**Operational procedure:** after the InnoDB conversion/restore and again after any bulk delete/import run `inventory:db-health`; fix index problems before enabling; run `inventory:db-health --analyze`; after activation watch the database deadlock counter (`SHOW ENGINE INNODB STATUS`, error log) and the application log for `Deadlock found`.
**Remaining limitations:** the legacy order-placement code (storefront, POS, funnel) opens its own transactions and does not retry, so a deadlock victim there is an error shown to the customer, who retries. The cause of the one observed failure is unproven. Real-engine behaviour (MySQL 8.4) untested.

---
## R2. Wallet-only refunds
**Before (by code reading, now guarded by a test):** `refund_pay` credited the customer's wallet and balance with no check of the refund's state: a second click or a second admin credited it again. No chart-of-accounts entry was ever made for any refund.
**Accounting treatment defined (implemented in `RefundService`):**
| Channel | Debit | Credit | Notes |
|---|---|---|---|
| Wallet credit | Sales Returns and Refunds (expense) | Customer Wallet Balances (liability) | we now owe the customer; the liability falls when the customer spends the wallet |
| Cash / bank refund | Sales Returns and Refunds | the cash or bank account that paid | needs the account |
| Payment-gateway refund | Sales Returns and Refunds | Payment Gateway Clearing (asset) | needs the gateway's refund reference; cleared when the gateway settles |
Stock and cost of goods are not touched by a refund; units come back only through the return-receipt workflow.
**Duplicate prevention (all tested):** the refund row is locked in one transaction and a paid or rejected refund is refused; a refund request has one posting (unique); a gateway reference has one posting (unique); an idempotency key replays; the total refunded per order line cannot exceed what the line was sold for; if the posting fails the wallet credit is rolled back. With the flag off the duplicate guard still applies and nothing is journalled.
**Remaining limitations:** wallet top-ups and wallet spending at checkout are not journalled, so the wallet liability account shows refunds only (reconciliation prints the difference to `users.balance` as informational). There is no cash-refund screen and no gateway refund API call: those channels are recorded by the service only. Refunds paid before the flag was on are not posted (history is not rewritten; reconciliation counts them).

---
## R3. Historical uncosted order lines
Documented in `docs/HISTORICAL_ORDER_COSTS.md` and implemented as reporting only: FINAL profit contains only confirmed-order lines with real FIFO cost; uncosted lines carry a labelled planning ESTIMATE never booked; historical lines are shown as recorded and unverified in a separate subtotal; cancelled orders never count; no order column is ever written (regression test compares the rows before and after). **Open:** the accountant must accept this policy and decide whether any historical line should ever be finalized.

---
## R4. Journal posting after the legacy commit
**Before:** the balance change committed first, the journal entry was written afterwards in memory-driven code; a crash in between left the change permanently without an entry (only detected as drift).
**After (implemented, transactional outbox):** every legacy balance change also writes a row to `ledger_outbox` in the SAME transaction, so they commit or roll back together. After the outermost commit the group becomes one balanced journal entry in a second transaction that also marks the rows processed (journal key derived from the group token: exactly once). Anything not processed is completed by `ledger:process-outbox`, scheduled every minute and runnable by hand; reconciliation shows rows waiting (high after 5 minutes).
**Failure-recovery tests (`LedgerOutboxRecoveryTest`):** journal step fails after the business commit -> change stays queued, command writes exactly one entry, running it again posts nothing more; process dies after writing the entry but before marking -> entry rolled back too, retried once; same group processed twice/two workers -> no duplicate; rolled-back operation -> no outbox row, no entry.
**Remaining limitations:** the entry is not in the very same transaction as the business change (it follows within milliseconds, or within a minute if recovery is needed), so for a short time a balance can be ahead of its journal entry; the scheduler must be running (cron `schedule:run`) — if it is not, reconciliation turns high. Not load-tested at production scale. Real-engine behaviour untested.

---
## Incidental finding fixed
`chart_of_accounts.sub_type` is an ENUM and Laravel runs MySQL non-strict: an invalid value (the first draft used 'wallet') is silently stored as '' by the artisan route while the SQL route errors. The wallet account now uses a valid value, and `SystemAccountDefinitionsTest` checks every system account against the live ENUMs.
