# T-5 Financial Integrity Remediation Plan (design only)

**Status: DESIGN AND AUDIT ONLY. Nothing in this plan has been implemented.** No application code, test, migration, configuration, database record, staging or production was changed to produce it. T-5 remains **NOT ACCEPTED**, production remains **NO-GO**, no feature flag is enabled, R1–R4 remain **UNRESOLVED**.

Tags used throughout: **[FACT]** read from the code or database schema in this repository (or measured earlier in this project); **[PROPOSAL]** a design that does not exist yet; **[ASSUMPTION]** something I could not verify and that must be confirmed before building. Classification of each finding: **TECHNICALLY REQUIRED**, **BUSINESS APPROVAL REQUIRED**, **NEEDS MORE INVESTIGATION** (a finding can carry more than one).

Sources: `docs/T5_FINAL_REVIEW.md`, `docs/evidence/review/*`, `docs/T5_DEADLOCK_RETRY_VERIFICATION_REPORT.md`, `docs/STAGING_ACTIVATION_PREREQUISITES.md`, and the code listed in appendix A.

---

## 1. Executive summary

The review showed that the second-edition changes made repeated *reversals* (unsettle, delete) apply their financial effect once per request instead of once per document (F-01), left three retried purchase paths with irreversible file effects (F-02), and left open transactions on refusals (F-03). It also found unfinished concurrency work in overdraft checks, supplier edits, document numbers and the repeated-order guard.

**One root cause sits under most findings, and it is design, not locking:** the legacy screens have no *operation identity*. A screen's financial effect is "whatever the request does when it runs", so correctness depended on accidental overwriting. Row locks (D2/D3/D4) make requests *sequential*; they do not make a repeated request *recognise itself*. The plan therefore rests on four small mechanisms used everywhere:

1. **Operation identity row** (reuse of the existing `idempotency_keys` table, which already has a unique `(scope,key)` index): inserted as the first write of the same transaction as the effects, so "effects applied" and "identity recorded" commit or roll back together. A repeat — double click, simultaneous duplicate, client retry after a timeout that happened after commit — finds the row and gets the stored result without touching any balance. A deadlock or error rolls back both, so a retry runs cleanly. (F-01, D6, PO conversion, auto-import)
2. **Document state machine under a primary-key lock**: the document row is locked first and its state checked (e.g. settled → unsettled), so a different operation on the same document (settle vs unsettle, update vs delete) is refused with a clear 409 instead of applied twice. (F-01)
3. **One global lock order** for every financial transaction: operation/document → parents (order, purchase) → suppliers → chart-of-accounts rows (all of them, ascending id, in one statement) → stock rows/layers → number sequence last. Overdraft and due checks are then evaluated *after* the locks, using locking reads, with the existing rule text unchanged. (F-04, F-05)
4. **Transaction-pure controllers + an after-commit file queue**: business refusals are raised as exceptions and turned into responses outside the transaction; file deletions are recorded in the transaction and executed after commit by a reconciler; uploads are tracked and swept if never attached. (F-02, F-03)

Document numbers move to a sequence table row locked last (F-10). The repeated-order guard is replaced by a checkout attempt identity: a server-issued token, and, as a fallback for callers without a token, the *set of cart rows being consumed*, which the customer cannot reproduce by buying the same items again (D6). Purchase-order conversion and customer-payment auto-import get single-transaction/per-customer designs with identities (section 8).

Nothing can be called verified until the matrix in section 7 passes. The plan has **15 business decisions** (section 9) that I have not made. Production blockers are in section 11.

Two facts discovered while preparing this plan and not in the review: **(a)** the storefront checkout is `CheckoutController::checkout`, which calls `OrderController::store` and then deletes the cart *outside* any transaction (`CheckoutController.php` lines ~203–207); my D6 tests posted straight to the admin resource route, which does not delete the cart. **(b)** `PurchaseOrderController` has no `destroy` method (the config entry `destroy` matches nothing) and `BankTransactionController` has no `store`/`update` (its overdraft checks are in `withdraw` line 109 and `transfer` line 169). The review's method names for those two should be read accordingly.

---

## 2. Finding-by-finding remediation

| ID | Finding (review verdict) | Classification | Section |
|---|---|---|---|
| F-01 | Reversal/delete paths apply more than once (FAIL, High) | **TECHNICALLY REQUIRED** (response wording: business) | 3, 4.1 |
| F-02 | File effects inside retried transactions (FAIL, High) | **TECHNICALLY REQUIRED** | 5 |
| F-03 | Early returns inside open transactions (FAIL, Medium) | **TECHNICALLY REQUIRED** | 5 |
| F-04 | Overdraft checks not atomic (FAIL / business) | **TECHNICALLY REQUIRED** for lock placement keeping today's rules; **BUSINESS APPROVAL REQUIRED** for any rule change or addition | 6.2 |
| F-05 | Supplier due: unlocked writers, varchar money columns (FAIL) | **TECHNICALLY REQUIRED** (locks); **BUSINESS APPROVAL REQUIRED** (supplier edit semantics); **NEEDS MORE INVESTIGATION** (column types, due invariant) | 6.3, 6.4 |
| F-06 | Purchase delete leaves supplier due (business) | **BUSINESS APPROVAL REQUIRED** | 4.3 |
| F-07 / D6 | Repeated-order guard (business + technical) | **BUSINESS APPROVAL REQUIRED** + **TECHNICALLY REQUIRED** | 7 |
| F-08 | D1 settlement numbers (PASS) | none for correctness; superseded by 6.5 if sequences are approved | 6.5 |
| F-09 | D5 payment numbers (PASS) | same | 6.5 |
| F-10 | Other number generators (FAIL, availability) | **TECHNICALLY REQUIRED**; numbering rules: **BUSINESS APPROVAL REQUIRED** | 6.5 |
| F-11 | D2 settle locking (PASS) | none; `unsettle` is in F-01 | 4.1 |
| F-12 | D3 coverage not verified; `CleanupStatementLogs` semantics | **NEEDS MORE INVESTIGATION** (tests); **BUSINESS APPROVAL REQUIRED** (maintenance commands) | 6.1, 8 |
| F-13 | Retry duplicates on untested controllers (NOT VERIFIED) | **NEEDS MORE INVESTIGATION** (tests) | 8 |
| F-14 | Partial commits: PO conversion, auto-import; unlisted no-transaction methods | **TECHNICALLY REQUIRED** (redesign, section 9 of this plan = "Excluded workflows"); **NEEDS MORE INVESTIGATION** (`update_delivery_status`, `bulkAssignCourier`, `OrderController::destroy`) | 9 |
| F-15 | Real engine not verified (blocked) | **NEEDS MORE INVESTIGATION** (blocked on production engine/version output) | 12 |
| F-16 | Report overclaims | **TECHNICALLY REQUIRED** (corrections, section 2.1) | 2.1 |

### 2.1 F-16 corrections to be made to the reports when the work is done (not made now)
1. "No repeated side effects" applies to outgoing calls only; file effects must be reported separately and the static audit extended (section 5.5).
2. D3/D4 described as fixes: add that they require operation identity for non-idempotent screens (F-01).
3. D6 described without the address/payment/coupon gap, and tested against a route (`/admin/orders`) that does not consume the cart as the storefront does (fact (a) above).
4. "PO destroy" and "Bank store/update" method names corrected (fact (b)).

---

## 3. Idempotency design for reversals and deletions (F-01)

### 3.1 Requirement and why locks alone are insufficient
[FACT] Probe P1/P2 (`docs/evidence/review/probe_output.txt`): three simultaneous unsettles ended with cash -9880 (one reversal gives 0); three simultaneous supplier-payment deletes added 90 to supplier due instead of 30. [FACT] `CourierSettlement` and `SupplierPayment` are hard-deleted (no `SoftDeletes` on either model; `supplier_payments.deleted_at` exists as a column but the model does not use it), and `Purchase` is hard-deleted (`purchases` has neither `status` nor `deleted_at`). After the first request deletes the row, a retry hits "record not found": the system has *no record that the reversal ever happened*. So any guard that reads the document state cannot recognise "this is the same operation again". A persistent identity is needed.

### 3.2 The identity mechanism [PROPOSAL]
Reuse `idempotency_keys` (columns: `scope varchar(40)`, `key varchar(120)`, `result longtext`, unique `(scope,key)`; [FACT] verified in `inv_v2_test`; `App\Services\Inventory\Idempotency::once` already inserts the key in the operation's own transaction, rolls it back with a failed operation, replays the stored result, and retries a deadlock victim up to 3 times).

* **[ASSUMPTION]** `idempotency_keys` exists in production once the inventory_v2 migrations are applied; the real-engine rehearsal must confirm it. If not, the same table is created by migration M-1.
* **Migration M-1 (optional, audit only):** add nullable `actor_id bigint unsigned`, `target_type varchar(60)`, `target_id bigint unsigned`, and index `(target_type,target_id)` so an operator can ask "who reversed settlement 17 and when" and a UI can show "already reversed by X at T". No data change to existing rows.
* **Identity** of a reversal = the *document*, not a client token: `scope = 'reverse'`, `key = '<type>:<id>'`, e.g. `courier_settlement:17`. A document can be reversed once in its life, so the same identity for every attempt is exactly right and needs no cooperation from the browser. For *revert-and-reapply updates* and *creates*, which can legitimately happen many times, identity = a server-issued form token (section 3.5).

### 3.3 Transaction boundary and sequence (the same for every reversal) [PROPOSAL]
```
validate input and permissions                           -- before any transaction
Idempotency::once('reverse', '<type>:<id>', function () { -- one DB::transaction(..., 3); key row inserted first
    1. lock the document row by primary key (withTrashed), FOR UPDATE
    2. state guard: if not in the expected state -> throw StateConflict (409), nothing written
    3. lock parents: order / purchase rows, ascending id
    4. lock suppliers, ascending id
    5. lock ALL chart_of_accounts rows the reversal can touch, ascending id, one statement
    6. read what to reverse FROM THE RECORDS UNDER THE LOCKS (statement logs / purchase totals), never from the request
    7. apply effects (balances via the model's delta write, stock via StockLedger, statement logs, legacy-ledger outbox rows)
    8. delete/mark the document; record file deletions in file_operations (section 5)
    9. return ['message' => ..., 'summary' => [...]]       -- stored in idempotency_keys.result
});
build the response from the result                       -- outside the transaction
```
The key insert is step 0 (inside `once`); steps 1–2 follow. Order of key vs document lock does not matter for correctness because both are rolled back together; the document lock first avoids a gap-lock wait on the unique index for hot documents.

### 3.4 How each required scenario is guaranteed

| Scenario | What happens | What guarantees it |
|---|---|---|
| Double click / two identical requests at the same moment | Request B blocks on the document row lock (or on the unique key) until A commits, then finds the key, returns A's stored result with `replayed:true`. No balance is touched. | Unique `(scope,key)`; key committed atomically with the effects; document lock |
| Timeout after the database committed | The client retries; the key exists; stored result returned (same message), HTTP 200. Because the document row may no longer exist (hard delete), the replay must not need it: the result is read from `idempotency_keys.result`. | Key row is the tombstone. The request's `exists:` validation rule on the document id **must be removed or relaxed** for reversal routes (today `unsettle` validates `exists:courier_settlements,id`, which would return 422 on the retry) |
| Client retries after an error response | If the error happened *before* commit, key and effects were rolled back: the retry runs normally. If the response failed *after* commit (render error, network), the retry is a replay. | Atomic key + effects. The controller returns an error body only for rolled-back work |
| Deadlock causes a retry | The victim's whole transaction, including the key row, is rolled back by the database; `Idempotency::once` re-runs it (max 3). Combined with the existing request-level middleware this must stay a *single* retry layer: reversal routes are removed from `config/deadlock_retry.php` (like `PurchasingController` today) and rely on `Idempotency::once`'s own 3 attempts, so the total stays 3. | `DeadlockRetry::MAX`; no stacked layers |
| A different operation on the same document at the same time (settle ∥ unsettle, update ∥ delete) | Serialised by the document lock; the second sees the new state and gets 409 with the current state. | State guard (step 2) |
| Process killed mid-transaction | Database rolls back; nothing applied; the client retry runs normally. Files: section 5. | InnoDB atomicity |

**Not relied on:** balance differences (the D3 delta writes remain an implementation detail; they are no longer what prevents a double effect), and row locks alone (they order requests but do not identify repeats). Backstop at the database level: reversal postings carry `idempotency_key` values (`reverse:<type>:<id>`) into the journal where journalling applies; `journal_entries.idempotency_key` is unique ([FACT] relied on by the earlier "no group journalled twice" assertions), so a second posting for the same reversal would fail loudly instead of silently double counting.

### 3.5 Updates and creates [PROPOSAL, same family]
* **Creates** (supplier payment, voucher, expense, income, transfer, bank transactions, customer payment, stock adjustment, PO, purchase return): [FACT] only legacy purchase has a duplicate guard (`op_key`, scope `legacy_purchase`). Each form gets a server-issued token (`op_token`, random 26 chars, stored in the session when the form is rendered, single use). Identity `scope = 'create:<type>'`, `key = op_token`. A double click or retry returns the first document; a new form render gets a new token. **[ASSUMPTION]** forms and any JSON clients can carry a hidden field; clients that send none (APIs, scripts) fall back to today's behaviour plus a log line, until they are migrated (**business decision 1**).
* **Updates that revert then re-apply** (all `update` methods listed in 3.6): add an `expected_state` value to the form: a fingerprint (sha1 of the document's financial columns and child ids/amounts) computed when the form is rendered; the update locks the document and recomputes the fingerprint; if different → 409 "changed by someone else, reload". Identity for replay = the form's `op_token` (`scope = 'update:<type>'`). This avoids adding `version` columns to ten tables; a `version` integer is the alternative (more robust against fingerprint blind spots, needs a migration per table) — **NEEDS MORE INVESTIGATION** (choose after the characterization tests show which columns matter).

### 3.6 Per-operation specification
All locations are in `app/Http/Controllers/` unless stated; line numbers are current ([FACT]) and will move.

| # | Operation (method, lines) | Identity | Terminal state / guard | Effects that must occur once | Notes specific to the operation |
|---|---|---|---|---|---|
| R1 | `CourierSettlementController::unsettle` (1128–1200) | `reverse` / `courier_settlement:{id}` | `courier_settlements.status = 'settled'` and order `settlement_status` true, under lock; hard-deleted afterwards, so the key is the tombstone | reversal of each statement log of the settlement (accounts locked first, ascending), delete of logs and details, order fields cleared | Reverse amounts read from the locked `account_statement_logs` of this settlement only. Remove `exists:` rule. Remove from `config/deadlock_retry.php` (single retry layer). Today it returns inside the open transaction at 1141 (F-03) |
| R2 | `SupplierPaymentController::destroy` (710–787) | `reverse` / `supplier_payment:{id}` | payment row lock; the payment's `purchases` pivot rows locked; hard-deleted | supplier `due_amount += amount`, `paid_amount -= amount`, each purchase `total_payment_due += pivot paid`, account credit, statement logs, attachments (after commit, section 5) | Supplier locked before the payment's purchases (global order: purchase rows, then supplier would invert it: use **parents first**: purchases ascending, then supplier) |
| R3 | `PurchaseController::purchaseDelete` (2003–2089) | `reverse` / `purchase:{id}` | purchase row lock; refuse if any layer was sold (existing rule, unchanged) | stock reduction, layers deleted, payable/opening/payment account reversals, statement logs, supplier advance restore | **Supplier due treatment is business decision 2 (F-06).** Document deleted after commit (section 5). Hard-deleted so the key is the tombstone |
| R4 | `CustomerPaymentController::destroy` (307–372) | `reverse` / `customer_payment:{id}` | row lock (soft-deleted model: lock `withTrashed`, guard `deleted_at is null`) | order payment status, voucher delete, mappings, account, logs | |
| R5 | `ExpensePaymentController::destroy` (371–431) | `reverse` / `expense_payment:{id}` | same | account credit, logs | |
| R6 | `IncomeController::destroy` (324–378) | `reverse` / `income:{id}` | same | account debit, logs | `Income` model uses `increment/decrement` (atomic) already |
| R7 | `VoucherController::destroy` (673–711) | `reverse` / `voucher:{id}` | same | multi-line debit/credit reversal on shared accounts | lock all accounts of all lines in one ascending statement |
| R8 | `BalanceTransferController::destroy` (196–220) | `reverse` / `balance_transfer:{id}` | same | both accounts, logs | accounts in ascending id regardless of from/to direction |
| R9 | `StockAdjustmentController::destroy` (353–416) | `reverse` / `stock_adjustment:{id}` | same | stock rows, FIFO layers, adjustment account | stock rows/layers locked ascending after accounts |
| R10 | `SupplierController::supplierDelete` (721–821) | `reverse` / `supplier:{id}` | refuse if purchases/payments exist (existing rule) | AP and opening-balance account adjustments | guard re-evaluated under the supplier lock, not before |
| R11 | `ChartOfAccountController::destroy` (251–298) | `reverse` / `account:{id}` | balance must be 0 under lock (existing rule) | statements deleted, account soft-deleted | |
| R12 | `OrderController::destroy` (1609–1639; **no transaction at all** [FACT]) and `::orderEditDelete` (2984–3219) | `reverse` / `order:{id}`, `order_line:{id}` | order row lock; refuse if settled | stock return, statement logs, commission | **NEEDS MORE INVESTIGATION:** `destroy` has no transaction and its effects have not been traced; `orderEditDelete` has one commit (2993→3002) |
| R13 | Cancellation reversal inside `OrderController::update_delivery_status`, `::updateItemsStatus`, `::processUpdateItemsStatus`, `set_order_line_status()` | `reverse` / `order_cancel:{id}` / `order_line_cancel:{id}` | order/line status state machine under lock | stock restock, accounts | **NEEDS MORE INVESTIGATION:** they send notifications/SMS (excluded from retry today); `update_delivery_status` and `bulkAssignCourier` have no transaction [FACT, static scan] |
| R14 | `RefundRequestController::refund_pay` | already `RefundService` + `refund_postings` | – | – | [FACT] duplicate-proof and parallel-tested in an earlier phase; the pattern to copy |
| R15 | `PurchasingController` v2 returns, receipts, payments | already `Idempotency::once` | – | – | [FACT] unchanged |

Revert-and-reapply updates covered by 3.5: `PurchaseController::update` (≈520–), `::purchaseUpdatePayment` (≈198), `::updatePurchaseReturn` (1820–2002), `SupplierPaymentController::update` (≈540–700), `PurchaseOrderController::update` (416) and `::purchaseUpdatePayment` (181), `VoucherController::update` (≈480), `ExpensePaymentController::update`, `IncomeController::update`, `BalanceTransferController::update` (135), `StockAdjustmentController::update`, `ChartOfAccountController::update` (141) and `::updateBalance` (299).

### 3.7 Response behaviour (all operations) [PROPOSAL; wording is business decision 1]
* First success: HTTP 200 `{success:true, replayed:false, message}`. Redirect-style screens: redirect with a success flash.
* Replay: HTTP 200 `{success:true, replayed:true, message:'Already done on <time> by <user>'}`; never an error, never a second effect.
* State conflict (document in another state): HTTP 409 `{success:false, code:'state_conflict', state:'<current>'}`.
* Validation / business refusal: 422, raised as an exception, **outside** the transaction (section 5.3).
* Deadlock survived 3 attempts: HTTP 503 with `Retry-After: 1` and `{retryable:true}`; nothing was committed, so the user may press again (identical outcome as a first attempt).
* Unknown failure after commit cannot be told apart from success by the client; its retry is a replay by construction.

---

## 4. Proposed database constraints and migrations (none created)

| ID | Change | Purpose | Risk / precondition |
|---|---|---|---|
| M-1 | `idempotency_keys`: add nullable `actor_id`, `target_type`, `target_id`, index `(target_type,target_id)` | audit and "already reversed by" display | additive; **[ASSUMPTION]** table exists in production after the v2 migrations |
| M-2 | New `file_operations` (`id`, `kind` enum(`delete`,`upload_guard`), `disk`, `path`, `owner_type`, `owner_id`, `state` enum(`pending`,`done`,`failed`), `attempts`, `last_error`, `created_at`, `run_after`); index `(state,run_after)` | after-commit file deletes and orphan-upload sweeping (section 5) | additive |
| M-3 | New `document_sequences` (`name varchar(40)`, `period varchar(8)`, `next_value bigint`, unique `(name,period)`) | concurrency-safe numbering (section 6.5) | seed rows computed from existing maximum suffix — a data insert into a *new* table only; **business decision 7** |
| M-4 | Index `combined_orders (user_id, created_at)` | [FACT] there is no index on `combined_orders.user_id` today; checkout lookups and reports scan the table | additive; index build time on a large table (rehearsal) |
| M-5 | `purchase_orders.converted_purchase_id` nullable, unique | PO conversion identity (section 9.1) | additive; **business decision 9** |
| M-6 | Unique index `orders.code` (and `combined_orders.code`) | [FACT] `Order::generateUniqueCode` uses `mt_rand` + exists-check and no unique index exists | **NEEDS MORE INVESTIGATION:** a read-only duplicate audit on a production copy must show no existing duplicates first |
| M-7 | Unique index on `courier_settlements (order_id)` | [FACT] settlements are hard-deleted on unsettle, so one live row per order is the intended invariant | **NEEDS MORE INVESTIGATION:** historical duplicates (audit query first) |
| M-8 | `purchases.reference_no`, `purchase_orders.reference_no`, `purchase_returns.reference_no`: unique indexes | [FACT] none is unique today (`PurchaseOrderController::store` validates `unique:purchases,reference_no` against the *wrong table* and then inserts into `purchase_orders`) | duplicates may exist; **NEEDS MORE INVESTIGATION** |
| M-9 | (optional, later) money columns to `decimal(15,2)`: `suppliers.opening_balance`, `purchases.total_payment_due` (varchar), `suppliers.paid_amount` (double) | [FACT] types verified in `inv_v2_test` | **NEEDS MORE INVESTIGATION:** non-numeric legacy values; do not do before the accountant's baseline; interim: a `Money` helper that does string-decimal arithmetic under locks |

No migration rewrites, backfills or repairs historical rows. All data audits are read-only queries run on a production copy.

---

## 5. File and transaction safety (F-02, F-03)

### 5.1 Inventory of file operations and transaction exits [FACT unless marked]

| Controller::method (lines) | File operations | Transaction structure | Defects |
|---|---|---|---|
| `PurchaseController::store` (≈250–460) | upload `purchases/` via `RequestSideEffects::once` (347) | `DB::transaction`-style begin/commit | OK (fixed in second edition) |
| `PurchaseController::update` (≈520–720) | upload via `once` (573); old document delete in `DB::afterCommit` (577–580) | one commit | OK |
| `PurchaseController::storePurchaseReturn` (1327–1571) | **upload `purchase_return_documents` at 1354, inside tx, not once, no undo** | begin 1329, commit 1535, rollBack 1538 | **F-02**: each retried or failed attempt leaves an orphan |
| `PurchaseController::updatePurchaseReturn` (1820–2002) | **deletes old document 1896–1897, stores new 1899, inside tx** | begin 1822, commit 1992, rollBack 1995 | **F-02**: old file lost on rollback; new file stored per attempt |
| `PurchaseController::purchaseDelete` (2003–2089) | **deletes document 2070–2071 before commit** | begin 2005, commit 2077, rollBack 2081 | **F-02**: document lost after a rollback |
| `PurchaseController::purchaseUpdatePayment` (≈190–290) | none | begin 198; **return at 226** inside tx | **F-03** |
| `SupplierPaymentController::store` (≈230–420) | upload via `once` (282–292) with undo | begin 251; **return at 261** (allocation mismatch 422) | F-03 (line 288 is a closure return, fine) |
| `SupplierPaymentController::update` (≈540–700) | upload via `once` (620–629); old deletes in `afterCommit` (634–636) | begin 546 (626 is a closure return) | OK for files |
| `SupplierPaymentController::destroy` (710–787) | attachment deletes in `afterCommit` (767–769) | begin 712, commit 774, rollBack 777 | OK for files |
| `PurchaseOrderController::store` (264–414) | upload via `once` (343) | begin 266; **return at 300** | F-03 |
| `PurchaseOrderController::update` (416–543) | old delete in `afterCommit` (≈424–428); upload via `once` (484) | `DB::transaction` closure | OK |
| `PurchaseOrderController::purchaseUpdatePayment` (181–262) | none | begin 183; **return at 210** | F-03 |
| `CourierSettlementController::settle` (≈176–1125) | none | begin 214; **returns at 233, 241, 253, 264, 281, 391** | F-03 |
| `CourierSettlementController::unsettle` (1128–1242) | none | begin 1135; **return at 1141** | F-03 |
| `VoucherController::store` (≈150–330) | none | begin 161; **returns at 173, 180, 184, 207, 213** (validation after `beginTransaction`) | F-03 |
| `VoucherController::update` (≈470–600) | none | begin 480; **returns at 501, 508, 512, 535, 541** | F-03 |
| `SupplierController::processPayment` (≈36–110) | none | begin 42; **return at 52** | F-03 |
| `OrderController::fixAllPayment` (≈158–200) | none | begin 162; **return at 170** | F-03 |

The F-03 list comes from a static scan of every controller and service for a `return` between `DB::beginTransaction()` and the next `commit`/`rollBack` ([FACT], re-run for this plan). Closure returns are excluded by hand. The scan cannot see conditional rollbacks in earlier branches, so it may under-report; the architecture test in 5.5 closes that gap.

### 5.2 After-commit file strategy [PROPOSAL]
Two table-backed lists, both written *inside* the business transaction or immediately around it, executed by a scheduled reconciler `files:reconcile` (every minute, same pattern as `ledger:process-outbox`, with an alert command):

1. **Deletes** (replaced documents, deleted documents' files, removed attachments): the transaction inserts `file_operations(kind='delete', path, owner_*)`. It commits or rolls back with the business rows, so a rolled-back or retried transaction never deletes a file and a committed one always eventually does. After the commit, an `afterCommit` callback tries the delete immediately and marks `done`; if the process dies or the disk call fails, the reconciler repeats it. A missing file counts as success, so the delete is idempotent. Failed deletes retry with back-off; after N attempts the row is `failed` and the alert lists it (an orphan file is a nuisance; a lost document is a loss, which is why delete is *never* done before commit).
2. **Uploads** (the new file must exist before the row that points to it): the request stores the file once (`RequestSideEffects::once`, as today) and immediately inserts an `upload_guard` row (autocommit, *outside* the business transaction). The business transaction flips it to `attached` as one of its writes. If the transaction finally fails, the request's undo deletes the file and the guard row; if the process crashes before either, the reconciler deletes guard rows still `pending` after one hour together with their files. This is the reconciliation path for crashes, final failures and rollbacks.

Why not "store in a temp path and move after commit": the DB row needs the final path inside the transaction, and the move could fail after commit leaving a row with a dead path; keeping the final path and sweeping unattached guards is simpler and fails safe.

Retry semantics: a deadlock retry re-runs the transaction body; `RequestSideEffects::once` returns the already-stored paths; the `delete` rows are re-inserted by the new attempt (the old attempt's rows were rolled back with it), so nothing is stored twice and nothing is deleted early.

### 5.3 Making every transaction exit safe [PROPOSAL]
* **Rule:** controllers contain no `return` between begin and commit. Validation and business refusals are performed **before** the transaction when they need no locked data, and otherwise raised as `App\Exceptions\FinancialRefusal` (carries message, HTTP status, redirect target) from inside the transaction closure and converted to the response by a `catch` *outside* it.
* Replace manual `DB::beginTransaction()/commit()/rollBack()` in the controllers listed in 5.1 with `DB::transaction(closure, 3)` or `Idempotency::once`, so commit/rollback is structural, not hand-written. Where the existing `try/catch (\Exception)` wraps both the transaction and response building, the catch must not swallow deadlocks (`DeadlockRetry::isDeadlock` is rethrown).
* **Safety net:** a terminating middleware on every route asserts `DB::transactionLevel() === entry level`; if not, it rolls back to the entry level and writes a critical log (today this happens only on the deadlock path in `RetryDeadlockedCheckout`). Test and alert on it; it is a net, not the fix.
* Specific moves: `VoucherController::store/update` — move the validator blocks (lines 168–213, 495–541) above `beginTransaction`; `SupplierController::processPayment` — the `payment_amount > totalDue` refusal needs the locked supplier, so raise `FinancialRefusal`; `CourierSettlementController::settle` — the six refusals need the locked order, so raise `FinancialRefusal` (releases the locks at once, which also removes the lock-hold side effect of D2/D4 noted in F-03); `OrderController::fixAllPayment` — the "no orders" check before the transaction.

### 5.4 Failed file operations: retry vs reconcile
| Failure | Handling |
|---|---|
| Delete fails after a committed transaction | row stays `pending`; reconciler retries with back-off; alert after N attempts; business rows are never rolled back for a file problem |
| Upload stored, transaction finally fails | request undo deletes file and guard row |
| Upload stored, process dies | guard row older than 1 hour → reconciler deletes file and row |
| Replace succeeded, old file delete fails | new document is live; old file is an orphan in the delete queue until it succeeds |
| Same file path referenced twice | not expected; the reconciler checks no live row references the path before deleting (**NEEDS MORE INVESTIGATION:** which tables store document paths) |

### 5.5 Tests that keep this true
`tests/Architecture/NoReturnInsideTransactionTest` (static: fail on any `return` between `beginTransaction` and `commit` outside a closure), `NoFileOperationInsideTransactionTest` (static: `Storage::`, `->store(`, `unlink`, `File::delete` only inside `RequestSideEffects`, `afterCommit` or the reconciler), extend `DeadlockSideEffectTest`'s audit to file operations, plus the behavioural tests of workflows 18 and 21 in section 7.

---

## 6. Financial consistency design (F-04, F-05, F-10, F-12)

### 6.1 Balance mutation rule (D3) [PROPOSAL]
Keep the D3 delta write in `ChartOfAccount::performUpdate` as the *only* way a model changes a balance, and add rules around it: (1) every code path that changes a balance first holds the account's row lock from the lock-order step 5, (2) no path may assign an absolute balance, (3) `CleanupStatementLogs` (the one absolute assigner, `app/Console/Commands/CleanupStatementLogs.php:345`) gets an explicit `setBalanceTo()` that refuses to run unless the application is in maintenance mode (**business decision 8**). A static test lists every writer of `current_balance` (today: `Models/ChartOfAccount.php`, `Models/Builders/ChartOfAccountBuilder.php`, `Services/Accounting/JournalService.php` raw SQL, the controllers and `Utility/PosUtility.php` listed in the review) and fails when a new one appears without an entry in an allow-list. [FACT] the writers found by grep are the files listed in the review (F-12).

### 6.2 Global lock order [PROPOSAL]
To minimise deadlocks every financial transaction takes locks in this order, using one helper `LedgerLocks::acquire(array $spec)` so the order is not re-implemented per controller:

| Level | What | How |
|---|---|---|
| 0 | operation identity row | `Idempotency::once` insert (unique key) |
| 1 | the document being changed | PK, `withTrashed`, `FOR UPDATE` |
| 2 | parent documents: order(s), purchase(s), customer(s), cart rows | PK ascending |
| 3 | supplier(s) | PK ascending |
| 4 | **all** chart_of_accounts rows the operation can touch | resolved *before* the transaction (as `ledgerAccountIds()` does for settle), then one `WHERE id IN (...) ORDER BY id FOR UPDATE` |
| 5 | stock rows (`product_stocks`), then FIFO layers (`purchase_products`), ascending id | existing `StockLedger` order |
| 6 | number sequence row | last, held for the shortest time |

[FACT] today's order is inconsistent: settle locks order → accounts; purchase store locks supplier → payment account → last-purchase row; supplier payment locks supplier → purchase rows → last-payment row; the real-deadlock tests (`RealDeadlockHttpTest`) rely on exactly these differences being retried. The unified order makes opposite-order deadlocks between these screens impossible by construction, leaving only the retry as a safety net. Accounts created lazily inside a method (e.g. Courier Income, Bad Debt in settle) must be created (idempotently, `firstOrCreate` on code) *before* the transaction so they join level 4.

### 6.3 Overdraft and funds checks (F-04) without changing business rules [PROPOSAL]
* **Characterization first (phase P0):** write tests that record today's rule for each screen *as it behaves now* — the condition, which balance it compares, its message, and whether the check happens before or after reverting an old amount on updates. [FACT] today's checks: `BalanceTransferController::store` line 91 (withdraw/transfer/cashout; before the transaction), `BankTransactionController::withdraw` line 109 and `::transfer` line 169, `ExpensePaymentController::store` line 114 and `::update` line 267, `SupplierPaymentController::store` line 353 and `::update` line 673, `PurchaseController::store` line 430 and `::update` line 710 (these two already read the account with `lockForUpdate` after D3/D4), `StockAdjustmentController` line 383 (`current_balance < 0` after the change), `PurchaseController::purchaseUpdatePayment` line 226 and `PurchaseOrderController::purchaseUpdatePayment` line 210 ("insufficient advance balance"). **No check exists** in income, vouchers, customer payments, settle, cheque clearing and `ChartOfAccountController::updateBalance` ([FACT] by grep; **NEEDS MORE INVESTIGATION** for `chequeClearing`).
* **Change:** only the *placement*: for each existing check, the account row is locked at level 4 and read with a locking read, and the **same comparison with the same message** is made on that fresh value, inside the transaction, via `FinancialRefusal` (so it exits cleanly). On updates that revert an old amount, the check uses the post-revert balance **only if the current code does** (decided by the characterization test, not by me).
* Concurrency result: two requests that each need more than half the balance are serialised by the account lock; the second sees the first's deduction and is refused with the existing message. Rules for screens without a check are **not** added (**business decision 3** lists them as questions).

### 6.4 Supplier due consistency (F-05) [PROPOSAL]
* Every site that modifies `suppliers.due_amount/opening_balance/paid_amount` takes the supplier at level 3 with `lockForUpdate` and re-reads it there. [FACT] the supplier write sites in the purchase, purchase-order and supplier-payment controllers and `SupplierController::processPayment` read the supplier with `lockForUpdate` since the second edition. Remaining: `SupplierController::supplierUpdate` (494–540, absolute write from an unlocked read) and `::supplierDelete` (721–821, unlocked read before its guards), plus the supplier create path (new row, no race).
* **Supplier edit semantics (business decision 4):** the form posts absolute `opening_balance`/due values. Options: (A) lock, then refuse with 409 if the supplier's financial columns changed since the form was rendered (fingerprint, section 3.5) — no semantics change, user reloads; (B) apply the edit as a delta against the displayed value; (C) keep absolute overwrite but write an audit entry. Option A is the least change; I do not choose.
* **Invariant & reconciliation [NEEDS MORE INVESTIGATION]:** define and test what "accurate supplier due" means: candidate `suppliers.due_amount = Σ purchases.total_payment_due` (minus opening-balance offsets). [FACT] `PurchaseController::purchaseDelete` does not reduce `due_amount` today (probe P3), so the candidate invariant is already violated by design; the characterization step must measure the real relation on the test database and on a production copy before any invariant is asserted. A read-only reconciliation report (like `inventory:reconcile`, no repair) is part of the plan.
* **Column types (M-9):** interim `Money` helper for string-decimal arithmetic on the varchar/double columns, executed only under the supplier lock; conversion to `decimal` is deferred until a data audit.

### 6.5 Document number generation (F-10)
**Chosen design [PROPOSAL, TECHNICALLY REQUIRED; numbering rules BUSINESS APPROVAL REQUIRED]:** the `document_sequences` table (M-3). `next(name, period)` runs inside the business transaction as the **last** lock: `INSERT ... ON DUPLICATE KEY UPDATE next_value = LAST_INSERT_ID(next_value + 1)` (row created on first use with the seed computed from the existing maximum suffix at migration time). Properties: gap-free (it rolls back with the transaction, so a failed or retried attempt consumes nothing); no unique-violation retry loop; no scan of the documents table; the held lock is one row, per type and period, for the tail of the transaction; formats unchanged. Unique indexes remain as the backstop. User-facing failure: none for numbering itself; a deadlock surviving three attempts shows the 503 "please press again" of 3.7.

Fallback if sequences are not approved: extend the already built `createNumbered` pattern (savepoint, locking read of the latest row, retry on error 1062 recognised by index name, max 5) to each generator; it works but keeps a scan, a last-row lock held to the end of the outer transaction and an engine-dependent error-text match ([FACT] D1/D5 as built; the text match is not verified on the production engine).

| # | Generator | Used by | Format | Unique index? [FACT] | Plan |
|---|---|---|---|---|---|
| N1 | `CourierSettlement::generateSettlementNo` / `createNumbered` | `CourierSettlementController::settle` | `STL-YYYYMMDD-NNNN` (period = day) | yes `courier_settlements_settlement_no_unique` | move to sequence `settlement/YYYYMMDD`; keep `createNumbered` as is until then |
| N2 | `SupplierPayment::createNumbered` (PAY-NNNNNN, inline in D5) | `SupplierPaymentController::store` | `PAY-000001` (no period) | yes | sequence `supplier_payment/-` |
| N3 | `StockAdjustment::generateReferenceNo` | `StockAdjustmentController` lines 53, 194 (line 53 only *previews* a number on the create form; line 194 assigns) | `SA<year>/NNNN` (period = year, taken from `created_at` year) | yes | sequence `stock_adjustment/YYYY`; the preview at line 53 must be labelled non-binding or read-only |
| N4 | `ExpensePayment::generateVoucherNo` | `ExpensePaymentController::store` 124, `AutoDepositFunds` command | `EXP-YYNNNNNN` (period = year) | yes | sequence `expense/YY` |
| N5 | `BalanceTransfer::generateVoucherNo($type)` | `BalanceTransferController::store` 98, `AutoDepositFunds` command | `WD/DP/TR/CO/AD-YYMMNNNN` (type + month) | yes | sequence `transfer_<type>/YYMM` |
| N6 | `Voucher::generateVoucherNo` (model) **and** `VoucherController::generateVoucherNo` (private duplicate, the one used at line 264) | `VoucherController::store`, `CustomerPaymentController::store` 189 (via model) | `CP/BP/CR/BR/JV/CV-YYMMNNNN` | yes | one implementation → sequence `voucher_<type>/YYMM`; remove the duplicate |
| N7 | `CustomerPayment::generatePaymentNo` | `CustomerPaymentController::store` 146 | `CUS000001` | yes | sequence `customer_payment/-` |
| N8 | `Income::generateVoucherNo` | `IncomeController::store` 110 | `INC-YYNNNNNN` | yes | sequence `income/YY` |
| N9 | `CostCenter::generateCode` | cost center creation | `CC-NNNN` | yes | sequence `cost_center/-` (low traffic; same pattern for uniformity) |
| N10 | `PurchaseController::store` reference: `Purchase::lockForUpdate()->where('reference_no','like','Ref-%')…` | legacy purchase | `Ref-NNNN` starting 1000 | **no** | serialised only by the last-row lock; sequence `purchase/-` seeded at max+1; **M-8** unique index after audit |
| N11 | `PurchaseController::storePurchaseReturn` line 1365: `$request->reference_no ?? 'PR-' . time()` | purchase returns | `PR-<unix time>` | **no** | two returns in one second get the same reference silently; sequence `purchase_return/-`, **M-8** |
| N12 | `PurchaseOrderController::store`: `reference_no` is client-supplied; validation `unique:purchases,reference_no` checks the **wrong table** [FACT] | purchase orders | free text | **no** | server-side sequence, or validate against `purchase_orders`; **NEEDS MORE INVESTIGATION** how users enter it |
| N13 | `Order::generateUniqueCode` | orders (`OrderController::store`, POS, API, chat, funnel) | 6-digit `mt_rand` | **no** | exists-check is a race and the space is 900 000 codes; sequence-based or random with a unique index (M-6) and retry on 1062; **NEEDS MORE INVESTIGATION** (customer-visible code format, existing duplicates) |
| N14 | Chart-of-accounts `generateAccountCode` (`ChartOfAccountController` 416) | account creation | by sub type | **NEEDS MORE INVESTIGATION** | low traffic; same pattern |

Rollback behaviour is identical for all: sequence row increments roll back with the transaction. Tests: workflow 17.

---

## 7. Duplicate order protection (D6)

### 7.1 Facts that drive the design
* [FACT] The storefront path is `CheckoutController::checkout` (route `payment.checkout`, `POST /checkout/payment`): it reads the cart, calls `(new OrderController)->store($request)`, then deletes the cart rows (`$carts->toQuery()->delete()`), deletes the session draft, then runs the payment decorator using `combined_order_id` from the session. `OrderController::store` has its own transaction; the cart delete and the session writes are outside it. The admin resource route `POST /admin/orders` calls `store` directly and **never deletes the cart**, which is why my parallel test saw four orders there; the review's D6 guard (same user + same product/variant/quantity within 15 s) was added to cover that, and it also runs on the storefront path.
* [FACT] The D6 guard compares only `product|variation|quantity` and a 15-second window, ignores address, recipient, payment option, shipping, coupon, notes; `combined_orders` has no `user_id` index.
* [FACT] Cart rows have auto-increment ids; a new purchase after a completed one creates *new* cart rows even for identical products.

### 7.2 Identity design [PROPOSAL]
**Primary identity: a checkout token.** When the payment step is rendered (the checkout payment-select view; **[ASSUMPTION]** its template can carry a hidden field, the view file was not located in this audit), the server issues `checkout_token` (random, 26 chars), stores it in the session (`checkout_token`), and puts it in a hidden field of the payment form (and returns it from the API checkout-info call for app clients). `CheckoutController::checkout` requires it to equal the session value, then runs the order creation under `Idempotency::once('checkout', token, …)`; the stored result is `{combined_order_id}`. Consequences:
* *Repeated submission of the same order* (double click, browser retry, back button + resubmit, a proxy replaying the POST): same token → the first order's result, replay screen says "this order was already placed (#…)"; stock taken once, cart consumed once.
* *Legitimate new order with identical products* (customer buys the same items again, even seconds later): the previous order consumed the cart and the session token is rotated on success; the next checkout page render issues a new token and the cart has new row ids → new order. No time window, nothing to wait for.
* *Changed address, shipping, payment, coupon or other details between two submits:* if the token is the same, the request is "the same submission" and the server must **not** create a second order; it answers with the first order and, because the payload differs, shows a clear message ("an order was already placed from this checkout; changes after submitting are not applied"). It never silently returns the earlier order as if the new details had been accepted. If the customer changed details they must start a new checkout (which issues a new token). If the token is *absent or unknown* the request is refused as "checkout expired, please review your order" (not turned into an order).
* *Deadlock retry:* the key row is rolled back with the failed attempt; the retried attempt inserts it again.

**Consume the cart in the same transaction.** Move the cart deletion (`CheckoutController` line ~205) and the session draft handling's DB part into `OrderController::store`'s transaction, after the order rows are written: the cart rows are the physical proof that "this basket has been turned into an order". Session writes stay after commit (as today).

**Fallback identity for callers without a token** (admin resource route, any API or integration that posts without the form): `fingerprint = sha1(user_id | sorted cart row ids | cart rows' quantity+price | address_id and the address row's updated_at | payment_option | shipping amount | coupon code | additional_info)` under `Idempotency::once('checkout_fp', fingerprint)`. Because the cart *row ids* are part of the identity, a second purchase of the same products is a different fingerprint (new cart rows), and a changed address or coupon is a different fingerprint. A repeat with the same fingerprint returns the first order with the same explicit message; there is no time window, but fingerprint keys expire after 24 hours (cleanup job) so a stale replay is impossible. This fallback exists only so existing callers keep working; it is **business decision 5** whether it is kept or those callers must send a token.

**What is removed:** the 15-second same-products window and its `recent` combined-order lookup. The cart-row lock (`Cart::where('user_id')->lockForUpdate()`) stays as level 2 of the lock order, and the carts are re-read after the lock (fixing the stale-snapshot point in the review).

### 7.3 Index and retention
M-4 index on `combined_orders(user_id, created_at)` (useful independent of this design); `idempotency_keys` retention policy for scopes `checkout` (30 days) and `checkout_fp` (24 hours) (**business decision 5**).

### 7.4 Tests (these are the D6 workflow 19 rows in section 8)
Parallel identical POST ×4 with token → one order; same token, changed address → one order, explicit message, address unchanged; two sequential checkouts with identical products and new tokens → two orders; no token → refused; cart rows consumed inside the transaction (forced failure after cart delete leaves cart intact); fallback fingerprint: identical rows → one order, changed coupon → separate fingerprint; deadlock once → one order; the storefront and the admin route are both covered.

---

## 8. Test matrix for the 21 workflows

**Verification status of every row today: NOT VERIFIED. A workflow becomes "verified" only when every listed test exists and passes in a recorded run; this document verifies nothing.**

### 8.1 Conventions (applies to all rows)
* **Parallel (P):** 3–4 real processes via `HttpWorkflowCase::race()`, each an authenticated Super-Admin request through the HTTP kernel (existing `http_as` worker action), released together.
* **Double-submit (D):** the *same request body and token* sent twice sequentially and 3× in parallel; expect one effect, `replayed:true` on the others.
* **Deadlock/retry (I/R):** `DeadlockInjector::once/always` (fault injection, in-process, `DeadlockWorkflowRetryTest` pattern) and, where the workflow shows a real lock cycle, the helper-process pattern of `RealDeadlockHttpTest` (assert `X-Deadlock-Retries`).
* **Rollback (B):** inject a deadlock/exception after the last write (`always`); assert the *world fingerprint* (rows, balances, statement logs, outbox, journal, files, stock, layers) equals the pre-request fingerprint, and `DB::transactionLevel()` is back to entry.
* **Ledger assertions (LA)** common set: expected account balances exactly; `JournalService::trialBalance` balanced; `ledger_outbox` all processed after `ledger:process-outbox`; no `journal_entries.idempotency_key` duplicated; `account_statement_logs` rows for the source = expected; supplier due equals expected; `Σ product_stocks.qty = Σ purchase_products.remaining_qty` per product; no negative layer.
* **Setup (fixtures, defined once):** run on an isolated `*_test` database reset with `tests/ui/reset-test-db.sql`, flag `INVENTORY_CONFIRMED_ONLY` set per test by `HttpWorkflowCase::flag()` inside the test processes only; committed rows removed by the base class by high-water id.
  * **FX-A** system accounts (Sales, Accounts Receivable, Inventory, Cost of Goods Sold, Accounts Payable, Opening Supplier Balance, Courier Income, Bad Debt, Courier Settlement Expense, Courier Fee Expense, Owner's Equity) + cash ×2 (balance 1000) + bank ×1.
  * **FX-S** suppliers S1 (opening 0) and S2 (opening 200).
  * **FX-P** products: P1 stock 10 with layers 2@400 + 8@410; P2 stock 0 (backorder); P3 stock 6 single layer.
  * **FX-D** delivered unsettled legacy order (5 units, cost 100, due 5000) and a settled copy; **FX-DV2** v2 equivalent.
  * **FX-U** admins A1..A5 (Super Admin).
  * **FX-F** `Storage::fake('public')` for in-process tests; for parallel tests a temp disk root asserted by file count.
  * **FX-X** helper worker action `lock_then_lock` for real deadlocks.

### 8.2 Matrix
For each row: P = parallel, D = double-submit, I/R = injected/real deadlock, B = rollback, LA = assertions beyond the common LA set. All rows also run the common LA set.

| # | Workflow | Parallel (P) | Double-submit (D) | Deadlock / retry (I/R) | Rollback (B) | Specific balance & ledger assertions (LA+) | Setup |
|---|---|---|---|---|---|---|---|
| 1 | Courier **unsettle** (R1) | P1: 3 admins unsettle the same settlement → exactly one success, others `replayed`. P2: settle ∥ unsettle of the same order → ends settled **or** unsettled, never half | same token twice; 3× parallel; retry after simulated lost response (re-send after commit) → replay, no 422 | I once/always; R: helper holds the order row vs unsettle's account locks | always-deadlock after logs deleted → settlement, logs, details, balances all restored | cash, AR, COGS, inventory, courier income return to pre-settlement exactly once; one reversing journal group; statement logs of the settlement gone once | FX-A, FX-D settled via real settle; A1–A4 |
| 2 | Courier **settle variants**: full, partial delivered, zero-shipping customer-paid, zero-shipping admin-paid, POS order | P: same order ×4 per variant (one wins); 4 different orders into one account | same body ×3 | I once/always per variant; R for full delivery (exists) | always → no settlement row, no log, no balance change | per variant: AR/COGS/inventory/courier income/bad debt/expense balances equal the single-run values; POS variant: no sales/COGS/inventory entries | FX-A, FX-D variants with `courier_status=partial_delivered`, item statuses, `order_from=pos` |
| 3 | Supplier payment **update / destroy** (R2) | P: destroy ×3; update ×3 with the same token; payments to the same account from 4 admins | same token ×3 for update; destroy ×3 | I once/always; R: helper holds the account vs destroy | always → payment, pivot rows, files, supplier, purchase due, account unchanged | supplier due/paid and each purchase due change once; cash +amount once; attachments removed once after commit | FX-A, S1 with 2 purchases (100 each), payment 30 across both; attachment file |
| 4 | Purchase **update**, **purchaseDelete** (R3), **purchaseUpdatePayment** | P: delete ×3; update ×3; delete ∥ sale of its layer; supplier change old→new ∥ payment | same token/identity ×3 | I once/always for each; R for delete (supplier vs account) | always-deadlock with document attached → document still exists, purchase intact | stock, layers, payable, opening-balance, payment account, supplier due per **decision 2**; FIFO: a sold layer blocks delete; no negative layer | FX-A, S1/S2, P2 with purchase 5@20 paid 40, document file |
| 5 | Purchase **returns** store/update | P: 4 returns on shared payable/inventory accounts; update ×3 | same token ×3 | I once/always with upload | always → no return row, no file (new or old changed), accounts unchanged; old document kept on failed update | payable, inventory, supplier due/opening exact; reference numbers distinct (N11) | FX-A, S1, purchase received, document files |
| 6 | **Purchase orders** store/update/purchaseUpdatePayment (+ no destroy exists) | P: 4 stores with different references; update ×3; advance payment from 4 admins | same token ×3 | I once/always with upload | always → no PO row, no file | advance balance never negative; supplier opening balance exact; PO totals | FX-A, S2 (opening 200) |
| 7 | **Supplier** edit / delete / processPayment | P: edit ∥ purchase ∥ payment on the same supplier; processPayment ×3; delete ∥ purchase | same token ×3 | I once/always for each; R: helper holds an account vs processPayment | always → supplier row unchanged | supplier due/opening/paid equal the sequential-run values; edit conflict → 409 (or per decision 4) | FX-A, S1 with purchases |
| 8 | **Customer payment** store / destroy (R4) | P: same orders ×3 → one payment; 4 payments, different customers, one account; destroy ×3 | same token ×3 | I once/always; R | always → no payment, no voucher, orders unpaid | orders marked paid once; account +amount once; one voucher; distinct numbers (N6, N7) | FX-A, customers C1..C3 with unpaid delivered orders |
| 9 | **Voucher** store / update / destroy (R7) | P: 4 vouchers over 2 shared accounts (multi-line); update ×3; destroy ×3 | same token ×3 | I once/always; R | always → no voucher/lines/logs | every line debits/credits once; trial balance balanced; distinct numbers (N6) | FX-A with 3 accounts; JV, CP, CR types |
| 10 | **Expense**, **Income**, **Bank transaction** (withdraw, transfer, deposit, cheque clearing), **Balance transfer** (R5, R6, R8) | P: 4 each on one account; destroy ×3; **overdraft race** per decision 3: N requests each over half the balance | same token ×3 | I once/always; R: transfer A→B ∥ B→A | always → nothing changed | balances exact; with the existing overdraft rule: exactly the number of requests the balance allows succeed, the rest get today's message; transfer pairs conserve the total | FX-A with account balances 100 and a bank |
| 11 | **Stock adjustment** update / destroy (R9) | P: 4 adjustments of one product; destroy ×3; adjustment ∥ sale | same token ×3 | I once/always; R: helper holds the stock row | always → stock, layers, adjustment, account unchanged | stock = layers; adjustment account once; reference numbers distinct (N3) | FX-A, P1 (two layers) |
| 12 | **Chart of accounts** opening-balance edit, `updateBalance` (R11 for destroy) | P: edit ∥ 4 postings; updateBalance ×3; destroy ∥ posting | same token ×3 | I once/always | always → balances, equity adjustment, logs unchanged | final = opening change + postings; Owner's Equity adjusted once; statement log once | FX-A with equity account |
| 13 | **POS order** (`PosUtility`) | P: 4 POS orders on one cash account and one product with limited stock | double submit with same token ×3 | I once/always; R | always → no order, stock/layers/accounts unchanged | cash, AR, sales, COGS, inventory exact; FIFO cost per layer; no oversell | FX-A, P3, POS-type admin; flag both states |
| 14 | **Order edit / cancel / status** (`orderEditUpdate`, `orderEditDelete`, `updateItemsStatus`, `update_delivery_status`, `bulkAssignCourier`, `OrderController::destroy`) | P: cancel ×3; cancel ∥ settle; two edits of the same order; bulk courier assign ∥ status change | cancel ×3 same token | I once/always for the transactional ones; for the no-transaction methods: crash-point test (exception after each write) | always → order, lines, stock, accounts unchanged; **for no-transaction methods the test documents the partial state first** (characterization) | stock restocked once; AR/COGS reversed once; notifications counted (must be once) | FX-D, P1, order in each status |
| 15 | **Refund** admin screen over HTTP (`refund_pay`) | P: 3 admins pay the same refund over HTTP | same token ×3 | I once; the service-level R exists | always → no posting | wallet/cash/gateway per method once; `refund_postings` once | reuse `RefundVerificationTest` fixtures through `http_as` |
| 16 | **Console commands** (`ReverseSettlement`, `FixPartialSettlements`, `CleanupStatementLogs`, `AutoDepositFunds`, `FixPosCourierIncome`) | each run while 4 HTTP postings hit the same account | run twice back to back | I once for those that transact | exception mid-run → no partial | asserts per command per decision 8 (maintenance-mode refusal for absolute setters); `AutoDepositFunds` number generation (N4, N5) distinct under load | FX-A, command-runner worker action (new) |
| 17 | **Number generators** N1–N14 | P: 4 simultaneous creations of each document type (and mixed types sharing a prefix period) | – | I once: sequence advanced once; always: sequence unchanged (no gap) | always → sequence value equals pre-request | all numbers distinct, format unchanged, gap-free after rollbacks | per type fixtures from workflows 3, 8–11 |
| 18 | **Early-return** methods (F-03: list in 5.1) | P: refused request ∥ valid request on the same rows → the valid one is not delayed beyond a bound | – | – | each refusal path of each method: `DB::transactionLevel()` unchanged at response and no lock held (a second connection's `lockForUpdate` on the rows returns immediately) | refusal leaves the world fingerprint unchanged | per method fixtures that trigger every refusal branch |
| 19 | **D6 checkout / repeated order** (section 7.4) | 4× same token; 4 users in parallel for the last units | same token twice; back-button replay | I once/always | forced failure after cart delete → cart intact, no order | stock/FIFO once; coupon usage once; explicit message when payload differs | FX-P, carts, addresses A/B, coupon, both storefront and admin routes |
| 20 | **Real deadlock** for purchase update/delete, supplier payment, voucher, customer payment | helper-process cycle per workflow (pause window as in `RealDeadlockHttpTest`) | – | R: retry header present, helper completes | – | once-only effects after the retry | FX-X plus the fixtures of rows 3, 4, 8, 9 |
| 21 | **File effects** (F-02): `storePurchaseReturn`, `updatePurchaseReturn`, `purchaseDelete` (plus the existing store/update paths) | parallel stores with uploads: one file per committed document | same token ×3 → one file | I once: exactly one file; always: zero new files, old files intact | always → `file_operations` and files equal the pre-request state | reconciler tests: killed process after upload → guard swept after 1 h (time-travelled); failed delete retried; delete of missing file succeeds | FX-F and a real temp disk for parallel tests |

### 8.3 Cross-cutting test infrastructure to build (proposal)
`HttpWorkflowCase` extensions: `worldFingerprint()`, `assertLedgerExactlyOnce()`, `assertNoOpenTransaction()`, `assertFilesEqual()`, a `command_as` worker action, reconciliation helpers, and the two architecture tests of 5.5. The full PHPUnit suite and the concurrency suite must be run one after the other (never overlapping), as before, with fingerprints of the test database and `sc_staging` recorded (staging never modified).

---

## 9. Excluded workflows: transaction redesign (F-14)

### 9.1 Purchase-order conversion — `PurchaseOrderController::convertToPurchase` (628–710)
[FACT] it validates, calls `PurchaseController::store` (which opens, commits and journals its own transaction), then outside any transaction sets the PO to `purchased` and writes `recieved_quantity` per line. A failure between the two leaves a purchase and an open PO; converting again creates a second purchase (stock, FIFO layer, supplier due, journal doubled).

**Design [PROPOSAL, TECHNICALLY REQUIRED; schema and behaviour BUSINESS APPROVAL REQUIRED]:**
1. Extract the database part of `PurchaseController::store` into `PurchaseService::create(array $data, string $opKey): Purchase` that **runs inside the caller's transaction** (no commit, no upload, no response; numbering via the sequence). `PurchaseController::store` becomes validation + upload (`RequestSideEffects::once`) + `Idempotency::once('create:purchase', op_token)` around the service + response.
2. `PurchaseOrderConversionService::convert(int $poId, array $data, User $by)` in **one** `Idempotency::once('convert', 'purchase_order:{poId}')` transaction:
   * lock the PO row (level 1); if `status = purchased`/`converted_purchase_id` set → return the existing purchase (replay);
   * lock supplier, accounts in the global order;
   * `PurchaseService::create(...)` with `op_key = 'po-convert:{poId}'`;
   * set PO `status`, line `recieved_quantity`, and `converted_purchase_id` in the same transaction.
3. The legacy-ledger outbox rows commit with it; journalling happens after the outermost commit (existing mechanism), so a conversion either exists with its PO update and outbox rows or not at all.
4. Files: the PO's document stays with the PO; the purchase's own upload follows 5.2.
5. **Partial success:** impossible by construction (single transaction). **Duplicate conversions:** the PO lock plus the identity row plus the unique `converted_purchase_id` (M-5). **Deadlock:** retried by `Idempotency::once` (max 3); route not in the request-level retry list. **Retry after timeout:** replay returns the existing purchase id.
6. Tests: workflow rows 4 and 6 plus: convert ×3 parallel → one purchase and PO purchased once; convert ∥ PO update; injected deadlock once/always after the purchase rows are written → nothing remains and PO still open; kill between the old two steps no longer exists; supplier due/layer/journal once.

### 9.2 Customer-payment auto-import — `CustomerPaymentController::autoImport` (≈370–445)
[FACT] it loops over customers calling `store` for each (one commit each), picks the **first** cash/bank account (`whereIn('sub_type',['cash','bank'])->first()`) and the first active transaction method for every payment, swallows every `\Exception` into an error list, and always answers `success:true`. A deadlock for one customer is therefore invisible to the retry layer and that customer is silently skipped.

**Design [PROPOSAL]:**
1. Extract `CustomerPaymentService::record(Customer, array $orderIds, int $accountId, int $methodId, string $date, string $key): array` — its own `Idempotency::once('auto_import', 'customer:{id}:{sha1(sorted order ids)}')` transaction: lock the customer's unpaid orders (level 2, ascending), **recompute the due inside the lock** and skip with `already_paid` if nothing is due, then create the payment (sequence numbers N7, N6), update orders, account (level 4), statement logs, voucher.
2. `autoImport` becomes a loop with **no outer transaction** (one transaction per customer keeps locks short; one failure must not roll back others), catching only business exceptions. A deadlock that survives the 3 attempts is recorded as `failed` with `retryable:true`, never swallowed.
3. Response: `{imported:n, already_paid:n, failed:[{customer, reason, retryable}]}`; HTTP 200 when all are imported/already paid, **207** with `success:false` when any failed (**decision 10**).
4. **Duplicate imports:** re-running is safe — finished customers return `already_paid` (identity row and recomputed due); only failed ones are processed again. Two admins pressing the button together: the per-customer identity serialises them and the second reports `already_paid`.
5. **Which account/method is used** is today an implicit "first match"; the plan keeps that behaviour only if the business confirms it, otherwise the screen asks (**decision 10**).
6. For large customer counts the per-customer call can move to a queued job with a unique job id (**decision 10**); not required for correctness.
7. Tests: workflow row 8 plus: autoImport ×3 parallel → every customer paid once; deadlock once on customer 2 → still imported once; always on customer 2 → listed under `failed`, others unaffected and committed; process killed between customers, then re-run → completes with no duplicates; response shape; shared account balance exact.

### 9.3 Other paths that must be decided before any redesign (NEEDS MORE INVESTIGATION)
`OrderController::update_delivery_status`, `::bulkAssignCourier` and `::destroy` write several tables with **no transaction** ([FACT], static scan; not traced further); `OrderController::updateItemsStatus` / `processUpdateItemsStatus` send notifications after saving. Each needs an effect inventory (which tables, which external messages) before a transaction/outbox design (notifications through an after-commit outbox, the same pattern as the ledger outbox).

---

## 10. Implementation sequence (after approval; one reviewable change per step)

| Phase | Content | Entry criteria | Exit criteria (all recorded runs, non-overlapping) |
|---|---|---|---|
| P0 | **Decisions and characterization.** Business decisions 1–15 answered. Write characterization tests that pin *today's* behaviour of every overdraft rule, supplier due rule, update-revert semantics, purchase delete, number formats (no production code change) | this plan approved | tests pass on current code; supplier-due relation measured on test DB; read-only duplicate audits (M-6/M-7/M-8) run on a production copy |
| P1 | **Foundations:** M-1, M-2, M-4 (+ M-3 if approved); `FinancialRefusal`; `LedgerLocks`; `FileOperations` + `files:reconcile` + alert; architecture tests (5.5); terminating transaction guard | P0 exit | migrations rehearsed on a production-engine copy; full + concurrency suites green; fingerprints unchanged |
| P2 | **F-01 reversals** one operation per change in this order: R1 unsettle, R2 supplier-payment destroy, R3 purchase delete, then R4–R11; rows 1, 3, 4 (and the matching others) of the matrix pass | P1 | probes P1/P2 of the review re-run: exactly one effect; replay and conflict responses as specified |
| P3 | **F-02/F-03:** convert the methods of 5.1 to pure transactions and the after-commit file strategy; rows 18, 21 pass | P1 | no `return` inside transactions (architecture test green); file reconciler tests pass |
| P4 | **Locking and funds:** unified lock order in the controllers; overdraft checks moved after locks with unchanged rules; supplier edit per decision 4; rows 10, 7, 12 | P0 decisions 3, 4 | characterization tests from P0 still pass unchanged; new parallel tests pass |
| P5 | **Numbers:** sequences (or `createNumbered` rollout); row 17 | P0 decision 6 | distinct, gap-free, format-identical |
| P6 | **D6 checkout identity:** token, cart consumed in the transaction, fallback fingerprint, M-4; row 19 | P0 decision 5 | storefront and admin routes pass; the 15-second window is gone |
| P7 | **Coverage of the remaining workflows** (rows 2, 5, 6, 8, 9, 11, 13, 14, 15, 16, 20) and the defects they find | P2–P6 | every row's tests pass |
| P8 | **Excluded workflows:** PO conversion (M-5) and auto-import | P2, P5 | rows 4, 6, 8 plus section 9 tests pass |
| P9 | **Real-engine rehearsal** on the production engine/version with a fresh production backup copy; accountant review of the reconciliation reports and the changed behaviours; update the verification report (corrections of 2.1) | production engine output, fresh backup, accountant availability (all currently **blocked**) | rehearsal report; accountant sign-off |
| P10 | **Staging activation only on your explicit approval** | P9 | – |

Rules for every phase: no feature flag enabled outside isolated test processes; no staging or production modification; no automatic correction of historical data; each phase leaves the branch with a green full suite and concurrency suite.

---

## 11. Acceptance criteria and production blockers

### 11.1 T-5 acceptance criteria (all must hold)
1. F-01: for every reversal/delete/update/create operation in 3.5–3.6, the double-click, simultaneous-duplicate, retry-after-commit, client-retry and deadlock scenarios each produce exactly one financial effect (matrix rows 1, 3, 4, 8–12 pass); probes P1/P2 repeated with the new code show cash 0 / due +30.
2. F-02/F-03: no file operation before commit in any retried method; every refusal path exits with `transactionLevel` unchanged and locks released; the two architecture tests are green and run in CI.
3. Lock order (6.2) is implemented through one helper and used by every controller in `config/deadlock_retry.php`; no new deadlock pattern appears in the real-deadlock tests.
4. Existing overdraft and due rules behave exactly as the P0 characterization tests record (or as the business has explicitly changed them in writing); concurrent requests cannot violate them.
5. Document numbers are distinct and gap-free under parallel load for N1–N14; formats unchanged.
6. D6: no legitimate new order is ever answered with an earlier order; a repeat is recognised only by token/fingerprint identity; message is explicit.
7. PO conversion and auto-import redesigned and tested as in section 9, or formally left excluded with the business's written acceptance of the residual risk.
8. All 21 matrix rows have passing recorded runs; the full PHPUnit suite and the concurrency suite pass (only the 3 pre-existing unrelated failures allowed) when run one after the other; test-database and `sc_staging` fingerprints identical.
9. The verification report is corrected (2.1) and the prerequisites document updated.
10. Real-engine rehearsal passed and accountant sign-off recorded.

### 11.2 Production blockers (current)
* Production engine/version output — **blocked** (all results are MariaDB 10.11 only).
* Fresh production backup — **blocked**.
* Real admin theme assets — **blocked** (UI acceptance not done).
* Accountant review of the balances, reversals' behaviour, supplier-due rule and reconciliation reports — **blocked**.
* F-01 and F-02 open (High); F-03, F-04, F-05, F-10 open; D6 not redesigned; 21 workflows unverified.
* R1–R4 UNRESOLVED; T-5 NOT ACCEPTED; `INVENTORY_CONFIRMED_ONLY` stays false everywhere outside isolated test processes.

---

## 12. Business decisions required (not taken here)

| # | Decision | Needed by | Who |
|---|---|---|---|
| 1 | Reversal/replay behaviour and wording: a repeated unsettle/delete answers "already done" (200) rather than an error; 409 text for state conflicts; whether existing forms and any API/script clients must send an `op_token` (and when un-tokened posts stop being accepted) | P0/P2 | order desk, accounting |
| 2 | **Purchase delete and supplier due (F-06):** should deleting a purchase reduce the supplier's `due_amount` (today it does not, while it reduces Accounts Payable)? Should a purchase with payments be deletable at all? | P0/P2 | accounting |
| 3 | **Overdraft policy (F-04):** confirm the existing rules are to be kept exactly (list in 6.3); decide whether to add a check to the screens that have none (income, vouchers, customer payments, settle, cheque clearing, `updateBalance`); on updates, whether the check is before or after reverting the old amount; whether overdraft is blocked or warned | P0/P4 | accounting |
| 4 | **Supplier edit semantics (F-05):** reject-on-conflict (A), apply as a difference (B), or absolute overwrite with an audit entry (C); who may edit opening balance/due manually | P0/P4 | accounting |
| 5 | **Checkout identity (D6):** token mandatory vs fallback fingerprint kept for token-less callers (admin route, integrations); retention of checkout keys (30 days / 24 hours); behaviour on back-button replay (show the earlier order page); whether admins may place an order twice for the same cart on purpose | P0/P6 | order desk |
| 6 | **Numbering rules:** gap-free requirement; confirmation that formats and period resets (day/month/year) stay as they are; whether Order codes (6-digit random) may change generation; whether purchase/PO/purchase-return references should become system-generated | P0/P5 | accounting, order desk |
| 7 | Approval to create `document_sequences` and seed it from existing maxima (a data insert into a new table) | P0/P5 | accounting, tech lead |
| 8 | **Maintenance commands:** may `CleanupStatementLogs`, `ReverseSettlement`, `FixPartialSettlements`, `AutoDepositFunds`, `FixPosCourierIncome` run while the shop is trading? Absolute balance setters allowed only in maintenance mode? | P0/P4 | accounting, tech lead |
| 9 | **PO conversion:** a second click returns the existing purchase instead of creating another; schema column M-5; whether a PO may be converted in parts | P0/P8 | purchasing, accounting |
| 10 | **Auto-import:** keep "first cash/bank account and first method" or ask the user; 207 response shape; queue large batches | P0/P8 | accounting |
| 11 | Retention and clean-up of `idempotency_keys`, `file_operations` rows and the alert thresholds | P1 | tech lead |
| 12 | Approval to run **read-only** duplicate and consistency audits (orders.code, courier_settlements per order, reference numbers, supplier due vs purchases, non-numeric money strings) on a production copy; no repair of anything found without a separate decision | P0 | accounting, tech lead |
| 13 | Money column conversion (M-9) timing: after the accountant's baseline, or never (keep the helper) | P4+ | accounting |
| 14 | Unique constraints M-6/M-7/M-8: what to do if existing duplicates are found (no automatic correction is planned) | P0 | accounting |
| 15 | Order cancellation/delete screens (R12, R13): whether notifications may move behind an after-commit outbox and whether `OrderController::destroy` is still a supported operation | P7 | order desk |

---

## Appendix A. Files examined for this plan (read-only)
`docs/T5_FINAL_REVIEW.md`, `docs/T5_DEADLOCK_RETRY_VERIFICATION_REPORT.md`, `docs/STAGING_ACTIVATION_PREREQUISITES.md`, `docs/evidence/review/*`; `config/deadlock_retry.php`; `app/Services/Inventory/Idempotency.php`; `app/Support/{DeadlockRetry,RequestSideEffects}.php`; controllers `CourierSettlementController`, `SupplierPaymentController`, `PurchaseController`, `PurchaseOrderController`, `SupplierController`, `CustomerPaymentController`, `VoucherController`, `ExpensePaymentController`, `IncomeController`, `BankTransactionController`, `BalanceTransferController`, `StockAdjustmentController`, `ChartOfAccountController`, `OrderController`, `CheckoutController`, `RefundRequestController`; models `CourierSettlement`, `SupplierPayment`, `StockAdjustment`, `ExpensePayment`, `BalanceTransfer`, `Voucher`, `CustomerPayment`, `Income`, `CostCenter`, `Order`, `ChartOfAccount`; `app/Models/Builders/ChartOfAccountBuilder.php`; `app/Services/Accounting/*`; `app/Console/Commands/CleanupStatementLogs.php`; the `inv_v2_test` schema (columns, indexes of the tables named in 4 and 6.5); static scans over all controllers/services for early returns inside transactions, file operations, multiple commits, and number generators.

## Appendix B. What is fact and what is not
* Facts are limited to what the code and the `inv_v2_test` schema show and what the review's probes measured on the isolated test database (MariaDB 10.11).
* Everything about production data (duplicates, non-numeric money strings, document paths shared across rows, existence of `idempotency_keys`), the production engine, UI forms able to carry a token, and callers of `OrderController::store` other than `CheckoutController` and the admin resource route is an **assumption or needs investigation**, as marked.
* No behaviour described in sections 3–9 exists yet.
