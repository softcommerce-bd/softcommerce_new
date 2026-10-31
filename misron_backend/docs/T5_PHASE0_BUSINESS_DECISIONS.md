# T-5 Phase 0 – Business decision register

**No decision in this register has been taken.** The "technical recommendation" column is engineering advice only; each decision belongs to the named approver. Until a decision is recorded in writing, the corresponding remediation step must not be implemented and today's behaviour stays exactly as it is.

Source list: the 15 decisions of `docs/T5_REMEDIATION_PLAN.md` §12. Current behaviour below was verified in Phase 0 (code reading and the probes in `docs/evidence/review/`); items not traced are marked **[NT]**. Companion: `T5_PHASE0_AUDIT_REPORT.md`.

Status for every decision: **NEEDS BUSINESS APPROVAL**.

Approver roles used: **Accounting** (accountant / finance lead), **Order desk** (head of order processing and customer service), **Purchasing**, **Tech lead**, **Management** (final sign-off owner).

| # | Decision (short) | Approver(s) | Gate |
|---|---|---|---|
| 1 | Repeat-request wording and client tokens | Order desk, Accounting, Tech lead | before P2 |
| 2 | Purchase delete and supplier due | Accounting | before P2 |
| 3 | Overdraft policy | Accounting, Management | before P4 |
| 4 | Supplier edit semantics | Accounting | before P4 |
| 5 | Checkout duplicate identity | Order desk, Tech lead | before P6 |
| 6 | Numbering rules | Accounting, Order desk | before P5 |
| 7 | Sequence table seeding | Accounting, Tech lead | before P5 |
| 8 | Maintenance commands | Accounting, Tech lead | before P4 |
| 9 | PO conversion | Purchasing, Accounting | before P8 |
| 10 | Customer-payment auto-import | Accounting | before P8 |
| 11 | Retention and alerts | Tech lead | before P1 |
| 12 | Read-only audits on a production copy | Management, Accounting, Tech lead | before P0 data audit |
| 13 | Money column conversion | Accounting, Tech lead | after the accountant baseline |
| 14 | Unique constraints if duplicates exist | Accounting, Management | after audit |
| 15 | Order cancel/delete screens and notifications | Order desk, Tech lead | before P7 |

---

## Decision 1 – Reversal replay behaviour, wording, and client tokens
* **Current behaviour [V]:** a repeated unsettle/delete is not recognised. Three simultaneous unsettles of one settlement all answered `success:true` and reversed the balances three times (cash −9880 instead of 0). A later retry after the first commit gets a validation failure (`exists:courier_settlements,id`) because the row is hard-deleted. Only legacy purchase creation has a duplicate guard (`op_key`); the other legacy forms send no token.
* **Proposed behaviour:** a repeat of the same reversal answers "already done on <time> by <user>" (HTTP 200, no second effect); a different operation on the same document gets a "state changed" message; forms carry a server-issued single-use token for create/update.
* **Impact:** financial — removes multiple application of reversals and duplicate documents; operational — users may see "already done" instead of an error; clients that cannot send a token (scripts, API consumers) need a transition rule.
* **Technical recommendation:** accept "already done" as success (it is the truthful state); require tokens on all browser forms; allow token-less posts for a defined transition period with logging.
* **Approver:** Order desk and Accounting (wording and who sees it); Tech lead (transition period).

## Decision 2 – Purchase delete and the supplier's due
* **Current behaviour [V, probe P3]:** `PurchaseController::purchaseDelete` reverses Accounts Payable (by the purchase's open amount), the payment account (by `total_paid`) and the opening-balance account, removes stock and the FIFO layer, deletes the row — and **leaves `suppliers.due_amount` unchanged** (after deleting a 100 purchase with 40 paid, supplier due stays 60.00 while Accounts Payable falls by 60). A purchase with a sold layer cannot be deleted. No guard against repeated delete other than the stock check.
* **Proposed behaviour:** options — (A) keep as is and document the mismatch; (B) reduce the supplier's due by the purchase's open amount on delete; (C) forbid deleting purchases that have payments or allocations.
* **Impact:** financial — supplier ledger versus Accounts Payable mismatch today; option B changes supplier statements and any report that sums supplier dues; option C removes a screen capability.
* **Technical recommendation:** B or C after the accountant confirms what the supplier statement should show; do not change before the audit queries E2/E6 are run on a production copy to see the historical size of the mismatch.
* **Approver:** Accounting.

## Decision 3 – Overdraft policy
* **Current behaviour [V]:** checks exist only in: balance transfer/withdraw/cashout (before the transaction, unlocked, `BalanceTransferController::store` 91), bank withdraw (109) and transfer (169), expense store (114) and update (267), supplier payment store (353) and update (673), legacy purchase store (430) and update (710, locked), advance balances in purchase and PO payment, stock adjustment (`< 0` after the change). **No check:** income, vouchers, customer payments, courier settle/unsettle, `ChartOfAccountController::updateBalance`, deposit, cheque clearing **[NT]**. Concurrent requests can both pass the check and leave an account negative.
* **Proposed behaviour:** keep every existing rule and message exactly, but evaluate it after locking the account row so that concurrent requests are serialised; optionally add rules to screens that have none.
* **Impact:** financial — prevents unintended negative cash/bank balances under concurrency; operational — a second simultaneous payment is refused with today's message; adding rules elsewhere would start refusing postings that succeed today.
* **Technical recommendation:** preserve existing rules only (technically required to make them true); treat new rules as a separate business change. First record today's exact semantics, including whether updates check before or after reverting the old amount, in characterization tests.
* **Approver:** Accounting, Management.

## Decision 4 – Supplier edit semantics
* **Current behaviour [V]:** `SupplierController::supplierUpdate` reads the supplier without a lock and writes the submitted `opening_balance` / `due_amount` as absolute values; a purchase or payment committed meanwhile is overwritten. The columns `suppliers.opening_balance` (varchar) and `paid_amount` (double) are not numeric types.
* **Proposed behaviour:** (A) lock and refuse with "changed by someone else, reload" when the supplier's financial columns changed since the form was shown; (B) apply the edit as a difference against the displayed value; (C) keep absolute overwrite but record an audit entry.
* **Impact:** financial — protects supplier balances from silent overwrite; operational — option A asks the user to reload.
* **Technical recommendation:** A (least change, no semantic surprise); consider restricting who may edit balances manually.
* **Approver:** Accounting.

## Decision 5 – Checkout duplicate identity
* **Current behaviour [V]:** the storefront checkout (`CheckoutController::checkout`) reads the cart, creates the order, and deletes the cart afterwards outside the transaction; the API (`Api\V2\OrderController::store`) reads the cart inside its transaction without a lock. This branch added a guard in `OrderController::store`: same user + identical product/variant/quantity within 15 seconds returns the earlier order; it ignores address, payment, shipping, coupon, and it locks `carts` by `user_id` where **no index exists, so it locks all carts** (measured, lock timeout between different users).
* **Proposed behaviour:** a server-issued checkout token (single use) and cart consumption inside the order transaction; a token-less fallback identity built from the cart row ids plus the order details; no time window; an explicit message when a repeat carries changed details.
* **Impact:** operational — eliminates both duplicate orders and silently wrong "same order" answers; a back-button resubmit shows the original order; token expiry may show "checkout expired, please review". Financial — one stock take per attempt.
* **Technical recommendation:** token mandatory for the website, fallback fingerprint for API/integrations; add the `carts(user_id)` index before any cart lock; retention of checkout keys 30 days (token) / 24 hours (fingerprint) as a proposal.
* **Approver:** Order desk (customer experience, retention); Tech lead (integrations).

## Decision 6 – Numbering rules
* **Current behaviour [V]:** formats are in the audit report §4.9: `STL-YYYYMMDD-NNNN`, `PAY-NNNNNN`, `SA<yyyy>/NNNN`, `EXP-YYNNNNNN`, `INC-YYNNNNNN`, `CP/BP/CR/BR/JV/CV-YYMMNNNN`, `WD/DP/TR/CO/AD-YYMMNNNN`, `CUS000001`, `CC-NNNN`, `Ref-N` (from 1000), `PR-<unix time>`, free-text PO reference, 6-digit random order code. Numbers are derived from the latest existing row, so a rolled-back document consumes nothing; simultaneous creations can collide (the second request fails on the unique index; four columns have no unique index at all).
* **Proposed behaviour:** unchanged formats and period resets, generated from a per-type/period sequence row inside the transaction (gap-free, no collisions); purchase returns, PO references and order codes optionally become system-generated.
* **Impact:** accounting — numbering continuity and audit trail; operational — fewer "duplicate entry" errors under load; changing order-code generation is customer-visible.
* **Technical recommendation:** keep every existing format; make numbers gap-free; leave order-code format unchanged but add a unique index after the audit.
* **Approver:** Accounting (documents), Order desk (order codes, purchase/PO references).

## Decision 7 – Approval to create and seed `document_sequences`
* **Current behaviour [V]:** the table does not exist; numbers come from the latest row of each document table.
* **Proposed behaviour:** a new table seeded, per type and period, from the current maximum numeric suffix (a data insert into a new table only; no existing row is touched).
* **Impact:** none on existing documents; a wrong seed would produce a duplicate or a skipped number — the seed is validated against the audit queries of section A.
* **Technical recommendation:** approve only together with a rehearsal on a production copy and a re-run of the duplicate-number queries before and after.
* **Approver:** Accounting, Tech lead.

## Decision 8 – Maintenance commands while trading
* **Current behaviour [V]:** `accounts:auto-deposit` is scheduled daily 00:01 Asia/Dhaka; `accounts:cleanup-statement-logs` is dry-run unless `--apply` and assigns an absolute balance (`CleanupStatementLogs.php:345`); `ReverseSettlement`, `FixPartialSettlements`, `FixPosCourierIncome` are manual. Since the balance-write change, an absolute assignment is applied as a difference from the value loaded. Transactional safety of these commands is **[NT]**.
* **Proposed behaviour:** commands that set absolute balances run only in maintenance mode and under the same lock order as the screens; scheduled commands use the same numbering and locking.
* **Impact:** operational — repairs need a short window; financial — prevents a repair command from overwriting live postings.
* **Technical recommendation:** approve the maintenance-mode rule; no repair command runs without explicit approval.
* **Approver:** Accounting, Tech lead.

## Decision 9 – Purchase-order conversion
* **Current behaviour [V]:** `PurchaseOrderController::convertToPurchase` calls the purchase screen's `store` (own commit) and then updates the PO status and received quantities outside any transaction; a failure between them leaves a purchase and an open PO; converting again creates a second purchase.
* **Proposed behaviour:** one transaction for purchase + PO status + received quantities, identity `convert:purchase_order:{id}`, new nullable unique `purchase_orders.converted_purchase_id`; a second click returns the existing purchase.
* **Impact:** financial — removes double purchases (stock, supplier due, payable); operational — behaviour change on repeat clicks; schema change.
* **Technical recommendation:** approve; decide separately whether partial receipt conversions are allowed.
* **Approver:** Purchasing, Accounting.

## Decision 10 – Customer-payment auto-import
* **Current behaviour [V]:** `CustomerPaymentController::autoImport` has no transaction, loops customers, uses the first `cash`/`bank` account and first active transaction method for every customer, swallows exceptions and always answers `success:true`. The scheduler calls a command `accounts:import-customer-payments` that does not exist in the code.
* **Proposed behaviour:** one transaction per customer with an identity, honest per-customer result (imported / already paid / failed with retry flag), HTTP 207 when partly done; account and method chosen explicitly or by a configured default; optional queue.
* **Impact:** financial — payments go to the intended account instead of an arbitrary "first" one; operational — new response format; the missing scheduled command is either implemented or removed (which one is a business call).
* **Technical recommendation:** configured default account/method per business rule; keep the screen synchronous until volumes require a queue.
* **Approver:** Accounting.

## Decision 11 – Retention and alerting
* **Current behaviour [V]:** `idempotency_keys` has no clean-up job; `ledger:process-outbox --alert` runs every minute; no file-cleanup queue exists.
* **Proposed behaviour:** retention for idempotency keys (by scope), the file-operation queue, and thresholds for alerts.
* **Impact:** table growth versus the ability to replay old requests; orphan files if the queue stalls.
* **Technical recommendation:** keep reversal keys indefinitely (they act as tombstones), checkout keys 30 days/24 hours, alert when a file operation fails 5 times.
* **Approver:** Tech lead.

## Decision 12 – Read-only audits on a production copy
* **Current behaviour:** no production copy has been provided; the audit SQL (`T5_PHASE0_AUDIT_QUERIES.sql`) has been validated only on synthetic data.
* **Proposed behaviour:** run the read-only queries on an authorised isolated restore of a recent backup; no repair of anything found without a separate decision; results contain no personal data.
* **Impact:** none on live data; reveals historical duplicates and mismatches that decide whether unique indexes can be added.
* **Technical recommendation:** approve and provide the restore environment and its engine/version output.
* **Approver:** Management (data access), Accounting, Tech lead.

## Decision 13 – Money column conversion
* **Current behaviour [V]:** `suppliers.opening_balance` and `purchases.total_payment_due` (and other purchase totals) are `varchar(255)`; `suppliers.paid_amount` is `double(13,2)`; `due_amount` and `chart_of_accounts.current_balance` are `decimal(15,2)`.
* **Proposed behaviour:** keep the columns and add a string-decimal helper under locks for now; convert to `decimal(15,2)` later, after an audit shows no non-numeric values.
* **Impact:** float/string rounding drift risk today; conversion changes how historical values are stored.
* **Technical recommendation:** helper now, conversion only after the accountant's baseline and the E1 query result.
* **Approver:** Accounting, Tech lead.

## Decision 14 – Unique constraints if duplicates exist
* **Current behaviour [V]:** no unique index on `orders.code`, `purchase_orders.reference_no`, `purchase_returns.reference_no`, per-order `courier_settlements`, and (test schema) `purchases.reference_no`.
* **Proposed behaviour:** add the indexes only after the audit shows no duplicates; if duplicates exist, decide what to do — no automatic correction is planned.
* **Impact:** a unique index fails to build (or later fails an insert) if duplicates exist.
* **Technical recommendation:** run audit section A/B first; handle each historical duplicate by an accountant-approved procedure.
* **Approver:** Accounting, Management.

## Decision 15 – Order cancel/delete screens and notifications
* **Current behaviour [V]:** `OrderController::update_delivery_status` (1663–1963), `bulkAssignCourier` (853–899) and `destroy` (1609–1639) write several tables with no transaction; status changes send notifications/SMS after saving; cancellation reverses stock and accounting [NT in detail].
* **Proposed behaviour:** wrap the database work in a transaction and send notifications/SMS after commit through an outbox; decide whether `OrderController::destroy` remains a supported operation.
* **Impact:** operational — notifications could be delayed by seconds and sent once; financial — no half-cancelled orders.
* **Technical recommendation:** approve the after-commit notification pattern; first complete the effect inventory of these methods (Phase 7 of the plan).
* **Approver:** Order desk, Tech lead.

---
## Cross-cutting
Open blockers that no decision can remove: production engine/version, fresh production backup, real admin theme, accountant review. T-5 is NOT ACCEPTED; staging and production remain NO-GO.
