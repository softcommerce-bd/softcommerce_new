# T-5 consolidated decision register

**No decision here has been taken or implemented.** Until each is approved in writing, today's behaviour stays exactly as it is. "Existing" = verified in code/tests (evidence in `T5_PHASE0_AUDIT_REPORT.md`, `T5_FINAL_VERIFICATION_REPORT.md`). Approvers: **Accounting**, **Order desk**, **Purchasing**, **Tech lead**, **Management**. Every item is **REQUIRES BUSINESS APPROVAL**.

| ID | Subject | Blocks | Approver |
|---|---|---|---|
| 1 | Repeat-request answers and client tokens | reversal wording, token on forms | Order desk, Accounting, Tech lead |
| 2 | Purchase delete and supplier due | purchase delete rule | Accounting |
| 3 | Overdraft policy | any new/changed balance rule | Accounting, Management |
| 4 | Supplier edit semantics | supplier edit screen | Accounting |
| 5 | Checkout duplicate identity and token | token, retention | Order desk, Tech lead |
| 8 | Maintenance commands while trading | repair commands | Accounting, Tech lead |
| 9 | Purchase-order conversion | PO redesign | Purchasing, Accounting |
| 10 | Customer-payment auto-import (and the removed schedule) | import redesign, scheduled job | Accounting |
| B-1 | Bank transaction numbering (table to fill) | the four bank actions | Accounting |
| N-1 | Supplier payment edit: historical balances | handling of past drift | Accounting |
| P-1 | POS cart handling after a sale (fix, confirm) | confirmation | Order desk |
| AD-1 | Nightly auto-deposit fails on NOT NULL created_by | the scheduled job | Accounting, Tech lead |
| 6, 7, 11-15 | remaining Phase 0 decisions | sequences, retention, audits, columns, constraints, order screens | see below |

---
## Decision 1 - Repeat-request answers and client tokens
* **Existing:** a repeated unsettle/delete is refused by the document lock exactly as a sequential repeat is (unsettle: "record not found" style failure, supplier-payment/purchase/income/...: the same not-found refusal); the effect is applied once (tested). No create form carries a token; only legacy purchase has `op_key`. A client that retries after a timeout that happened after commit gets the not-found refusal, not a "done" answer.
* **Proposed:** a repeat of the same operation answers "already done at <time> by <user>" (HTTP 200) using the existing `idempotency_keys` table as tombstone; forms carry a server-issued token; un-tokened clients stay supported for a transition period.
* **Consequences:** users see success instead of an error on double clicks; scripts/APIs without a token keep today's behaviour; one new key row per reversal (kept indefinitely).
* **Risk:** a wrong message could hide a real failure; token expiry confuses users; table growth (decision 11).
* **Exact approval needed:** (a) exact wording for "already done" and for "state changed"; (b) whether it is a success (200) or an information message; (c) which forms must send tokens and the cut-off date for un-tokened posts; (d) approval to store tombstones.

## Decision 2 - Purchase delete and the supplier's due
* **Existing:** `PurchaseController::purchaseDelete` reverses Accounts Payable (open amount), the payment account (paid amount), the opening-balance account, stock and the cost layer, deletes the purchase - and leaves `suppliers.due_amount` unchanged (after deleting a 100 purchase with 40 paid, payable falls 60, supplier due stays 60). A purchase whose layer was partly sold cannot be deleted. Simultaneous deletes now apply once.
* **Proposed:** (A) keep and document; (B) reduce the supplier due by the purchase's open amount on delete; (C) forbid deleting purchases that have payments/allocations.
* **Consequences:** B changes supplier statements and any report summing supplier due; C removes a capability; A keeps a known ledger/sub-ledger mismatch.
* **Risk:** historical purchases already deleted under the old rule stay mismatched whatever is chosen (audit queries E2/E6 measure it on a production copy).
* **Exact approval needed:** A, B or C; and whether historical mismatches are to be reported only (no automatic repair).

## Decision 3 - Overdraft policy
* **Existing:** checks exist only in balance transfer (withdraw/transfer/cashout), bank withdraw/transfer (screens currently non-functional, B-1), expense store/update, supplier payment store/update, legacy purchase store/update, advance balances (purchase, PO), stock adjustment (`< 0`). They compare the balance read at the start; since this phase the same comparison is repeated under the account's row lock (proved: a stale first check no longer overdraws). No check in income, vouchers, customer payments, courier settle/unsettle, `updateBalance`, deposit, cheque clearing.
* **Proposed:** keep every existing rule/message (done); optionally add rules to the screens without one, decide whether updates check before or after reverting the old amount, block or warn.
* **Consequences:** new rules would start refusing postings that succeed today (e.g. a voucher that empties a cash account).
* **Risk:** wrong policy either blocks legitimate corrections or allows negative cash.
* **Exact approval needed:** per screen: block / warn / no rule; and for updates the evaluation point.

## Decision 4 - Supplier edit semantics
* **Existing:** `SupplierController::supplierUpdate` reads the supplier without a lock and writes the submitted opening balance / due as absolute values; a concurrent purchase or payment can be overwritten. `supplierDelete` reads before its guards. `suppliers.opening_balance` is varchar, `paid_amount` double.
* **Proposed:** (A) lock and refuse when the supplier's financial columns changed since the form was shown; (B) apply the edit as a difference against the displayed value; (C) absolute overwrite with an audit entry. Restrict who may edit balances.
* **Consequences:** A asks users to reload; B changes what "edit balance" means; C keeps the risk with an audit trail.
* **Risk:** B/C alter balances users enter today.
* **Exact approval needed:** A, B or C; the permission that may edit balances.

## Decision 5 - Checkout duplicate identity
* **Existing:** `OrderController::store` identifies a submission by the exact cart rows (ids, quantities, prices) plus address, payment option, shipping and coupon, using `idempotency_keys` scope `checkout_cart`, row-level cart locks (no table-wide lock; tested: unrelated customers do not wait); identical resubmission returns the first order; a new purchase (new cart rows) or any changed detail creates a new order; the storefront controller deletes the cart afterwards outside the transaction; the API uses its own per-user lock; no checkout token exists; keys are never pruned.
* **Proposed:** add a server-issued single-use checkout token on the payment form, consume the cart inside the order transaction, fall back to the cart-row identity for token-less callers, prune keys (30 days token / 24 h fallback).
* **Consequences:** back-button resubmits show the original order; expired tokens show "checkout expired"; API/POS callers keep the fallback.
* **Risk:** the payment-view template that would carry the token was not located; a bug there would block orders.
* **Exact approval needed:** token mandatory or optional for the website; retention periods; the location/owner of the payment view; the message shown on a replay.

## Decision 8 - Maintenance commands while trading
* **Existing:** `accounts:auto-deposit` runs daily 00:01 Asia/Dhaka; `accounts:cleanup-statement-logs` is dry-run unless `--apply` and assigns an absolute balance (applied as a difference since the balance-write change); `ReverseSettlement`, `FixPartialSettlements`, `FixPosCourierIncome` are manual; their transactional behaviour and parallel behaviour are NOT VERIFIED.
* **Proposed:** commands that set or repair balances run only in maintenance mode and under the same lock order as the screens.
* **Consequences:** repairs need a short window.
* **Risk:** a repair run during trading can overwrite live postings.
* **Exact approval needed:** which commands may run while trading; the maintenance-mode rule; who authorises each `--apply` run.

## Decision 9 - Purchase-order conversion
* **Existing:** `convertToPurchase` calls the purchase screen (its own commit), then updates the PO status and received quantities outside any transaction; a failure between leaves a purchase and an open PO; converting again creates a second purchase. Not in the retry set.
* **Proposed:** one transaction for purchase + PO update, identity per PO, new unique `purchase_orders.converted_purchase_id`; a second click returns the existing purchase.
* **Consequences:** behaviour change on repeat clicks; schema change; partial conversions need a rule.
* **Risk:** extracting the purchase logic touches the most financial screen.
* **Exact approval needed:** the repeat-click behaviour; the schema column; whether a PO may be converted in parts.

## Decision 10 - Customer-payment auto-import (and the removed schedule)
* **Existing:** `autoImport` has no transaction, loops customers, uses the first cash/bank account and first active method, swallows errors, always answers success. The scheduler's hourly `accounts:import-customer-payments` never existed; its line is disabled and a test forbids undefined scheduled commands.
* **Proposed:** one transaction per customer with an identity, honest per-customer result (HTTP 207 when partial), explicit/default account and method, optional queue; decide whether a scheduled import should exist.
* **Consequences:** different account selection and response format; a new job if wanted.
* **Risk:** payments posted to a wrong account if the default is wrong.
* **Exact approval needed:** whether an hourly import is wanted; the account and method rule; which orders qualify; the response format; the failure recipients.

## Decision B-1 - Bank transaction numbering (the four bank actions stay DISABLED until approved and tested)
* **Existing:** `BankTransactionController::{deposit, withdraw, transfer, chequeClearing}` (lines 61, 126, 192, 250) call `BankTransaction::generateTransactionNo($type)`, defined nowhere, so the four actions fail before any write. `bank_transactions.transaction_no` is UNIQUE; the table is empty in every local database; its CREATE statement is not in the repository. Nothing was implemented or invented; the actions were not touched.
* **Exact decision requested (to be filled in by Accounting):**

| Action | Type string passed | Prefix | Digit width / sequence | Period reset (none / monthly / yearly) | Example to be approved |
|---|---|---|---|---|---|
| Deposit | `deposit` | ? | ? | ? | ? |
| Withdrawal | `withdraw` | ? | ? | ? | ? |
| Transfer | `transfer` | ? | ? | ? | ? |
| Cheque clearing | `cheque_clearing` | ? | ? | ? | ? |
Also: (1) are these four screens in use, or should bank operations go through the balance-transfer screen (which already numbers `WD/DP/TR/CO/AD-YYMMNNNN`)? (2) must numbers be gap-free? (3) is a deleted transaction's number reusable (the table has soft delete; the other generators now never reuse)?
* **Technical requirements once decided (not decisions):** unique (index exists); generated inside the creating transaction; locked read of the latest row including soft-deleted ones (the pattern used by the sibling generators); simultaneous requests must receive consecutive distinct numbers; the retry test scenarios for the four actions are templated; a parallel test must be added; the actions stay disabled (not routed to a working implementation) until then.
* **Consequences:** the format becomes a permanent identifier on reports; retiring the screens removes capability.
* **Risk:** a guessed format cannot be changed later without renumbering.
* **Exact approval needed:** the completed table above and answers (1)-(3).

## Decision N-1 - Supplier payment edit: historical balances (updated)
* **Existing (after this phase):** the defect is corrected going forward: an edit now equals "delete the old payment, store the new one" on the current supplier row (root cause and proof: `T5_SUPPLIER_PAYMENT_ROOT_CAUSE_REPORT.md`, 8 + parallel tests). Before the correction every edit left `suppliers.due_amount` too low and `paid_amount` too high by the amount replaced (due 70 -> 20 -> -30 -> -80). Past drift in real data is **untouched**. Read-only means to size it: `T5_SUPPLIER_PAYMENT_IMPACT_QUERIES.sql` (execution on a restored copy BLOCKED).
* **Proposed:** (a) confirm that the corrected treatment is the intended one (edit = revert + apply); (b) decide how any distorted supplier balances found by the audit are handled: accountant-led, supplier by supplier, with an adjustment document, never an automatic script; (c) whether to add an edit audit trail so future edits are traceable without log forensics.
* **Consequences:** (a) none further; (b) changes supplier statements and any report summing supplier due/paid; (c) a new table.
* **Risk:** correcting historical figures changes numbers the accountants may have worked around; edits of since-deleted payments cannot be attributed to a supplier.
* **Exact approval needed:** written confirmation of (a); the procedure and approver for (b); yes/no for (c).

## Decision P-1 - POS cart handling after a sale (new, implemented as a defect fix; confirm)
* **Existing before:** completing a POS sale ran `Cart::where('user_id', $order->user_id)->orWhere('temp_user_id', ...)->delete()`; for walk-in customers `user_id` is NULL, so it deleted every walk-in cart of every counter. The same cart submitted twice created two sales (no guard).
* **Now:** the sale locks and deletes only its own cart rows; a repeat of the same cart is answered "Please select a product." (what a sequential repeat gets).
* **Consequences:** other counters' carts survive; a registered customer's website cart is no longer deleted by a POS sale to that customer (the old condition also matched `user_id = customer`).
* **Risk:** if the business relied on the website cart being cleared by a POS sale.
* **Exact approval needed:** confirm that deleting only the sold cart rows is the intended behaviour.

## Decision AD-1 - Nightly auto-deposit fails on a NOT NULL created_by (new)
* **Existing:** `accounts:auto-deposit` writes `balance_transfers.created_by = NULL`; in this schema the column is NOT NULL, so every run fails per method ("Column 'created_by' cannot be null") and moves nothing. Whether production allows NULL is BLOCKED. Separately, two runs at the same moment each moved the whole balance (double deposit); the balance is now read under the account lock (tested with the column made nullable on the disposable database only).
* **Proposed (options):** (a) record a named system user as creator; (b) make the column nullable by migration; (c) keep the job disabled.
* **Consequences:** (a) needs a user id decision; (b) schema change; (c) nightly deposits stay manual.
* **Risk:** enabling without a decision either fails every night or starts moving funds nightly.
* **Exact approval needed:** a, b or c; the system user id for (a).

---
## Decisions 6, 7, 11-15 (unchanged from the Phase 0 register; summarised)
| ID | Existing | Proposed | Exact approval needed |
|---|---|---|---|
| 6 Numbering rules | formats in the audit report; "latest row + 1" now under a row lock for income, expense, transfer, voucher, stock adjustment, customer payment; four columns without a unique index; `PR-<time>`, free-text PO reference, 6-digit random order code | keep formats; gap-free sequence; optionally system-generate returns/PO references/order codes | formats and period resets; gap-free requirement; whether order codes may change generation |
| 7 Sequence table | none | new `document_sequences` seeded from maxima | create + seed approval (rehearsal first) |
| 11 Retention/alerts | no prune of `idempotency_keys`; no file queue | retention by scope; alert thresholds | retention periods; alert owner |
| 12 Read-only audits | audit SQL validated on synthetic data only | run on an authorised isolated restore | data access approval and environment |
| 13 Money columns | varchar/double columns | helper now, decimal later | timing after the accountant baseline |
| 14 Unique constraints | none on `orders.code`, per-order settlements, returns/PO references | add after audit shows no duplicates | what to do with any duplicates found |
| 15 Order cancel/delete/notifications | `update_delivery_status`, `bulkAssignCourier`, `OrderController::destroy` have no transaction; status updates notify | transaction + after-commit notification outbox | pattern approval; whether `destroy` stays |
