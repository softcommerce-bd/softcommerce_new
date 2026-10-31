# T-5 Phase 0 – Read-only audit report

**Authorisation:** Phase 0 only (read-only audit and test preparation). **No remediation was implemented.** No application code, existing test, fixture, migration, configuration, environment file, feature flag, staging or production data was changed. T-5 **NOT ACCEPTED**, staging **NO-GO**, production **NO-GO**, R1–R4 **UNRESOLVED**.

Companion documents: `T5_PHASE0_SCHEMA_COMPATIBILITY.md` (Task 1), `T5_PHASE0_TEST_BASELINE.md` (Task 3), `T5_PHASE0_BUSINESS_DECISIONS.md` (Task 6), `T5_PHASE0_AUDIT_QUERIES.sql` (Task 4 queries).

Status words: **VERIFIED** (read from code/schema or executed here), **NOT VERIFIED**, **BLOCKED**, **FEASIBLE**, **FEASIBLE WITH CHANGES**, **NEEDS BUSINESS APPROVAL**, **FAILED**. In tables, **[V]** = verified by reading the cited code or by measurement, **[A]** = assumption, **[NT]** = not traced line by line.

---

## 1. Summary

1. **Database:** only a local MariaDB 10.11.14 exists. Production engine/version/table engines are **BLOCKED**. Financial tables are InnoDB in the local schemas; the original schema has four purchasing tables as MyISAM, which makes every transactional design impossible there; whether production is converted is **BLOCKED**.
2. **Two new defects were found and measured in the already-built code (not in the plan):**
   * **P0-1 FAILED (High):** the D6 cart lock `Cart::where('user_id', …)->lockForUpdate()` (`OrderController::store`, ~line 1286) runs on `carts`, which has **no index at all**; InnoDB therefore locks every row it scans. Measured on the disposable database: a second session locking a *different* user's cart row **timed out (error 1205)**. In production this serialises every checkout and can lock-wait up to 50 s.
   * **P0-2 FAILED (Medium):** the request-level deadlock retry does not cover controllers that catch the exception and answer with a flash+redirect. Measured: `BalanceTransferController::store` (listed in `config/deadlock_retry.php`) with one injected deadlock was **not retried** (no `X-Deadlock-Retries`, HTTP 302, flash error, nothing committed). Eleven configured methods answer this way by static scan.
3. **Other verified facts that correct earlier documents:** the scheduler runs `accounts:import-customer-payments` hourly but **no such command exists** in the repository; `combined_orders` has no `code` column; `PurchaseOrderController` has no `destroy`; `BankTransactionController` has no `store`/`update`; `orders.code`, `purchases.reference_no` (test schema), `purchase_orders.reference_no`, `purchase_returns.reference_no` have no unique index; `drafts` is MyISAM.
4. **Business rules** of 12 workflow groups are documented in section 4 with exact methods, tables, transaction boundaries, effects and protections. Eleven of the twelve have **no duplicate protection** except legacy purchase, chat, funnel, and the v2 services.
5. **Test baseline:** all 21 workflows are specified in `T5_PHASE0_TEST_BASELINE.md`, every one **NOT VERIFIED**. Existing suites were re-run on the disposable database: full PHPUnit 556 tests with the same 3 unrelated failures; concurrency suite 61/61.
6. **Data audit:** the queries exist and were syntax/column-validated on the disposable database (zero errors, read-only session proven to refuse a write); **execution against a production copy is BLOCKED** (no copy provided).
7. **Feasibility:** of the 11 designs, only one is FEASIBLE as written; 9 FEASIBLE WITH CHANGES, 1 BLOCKED, and 5 also NEEDS BUSINESS APPROVAL (section 6).
8. **Technical readiness for implementation: NOT READY** (section 8): production facts are blocked and two shipped changes (D6 lock, retry coverage) must first be re-decided.

---

## 2. Environment inspected and tests executed

| Item | Detail |
|---|---|
| Database server | Local MariaDB 10.11.14 (`127.0.0.1`), disposable `inv_v2_test` (synthetic data) for tests; metadata only on `misron_test`, `sc_staging`, `sc_stg_pre` and engine counts of the other local schemas |
| Production / real staging | not accessible; BLOCKED |
| Executed: full PHPUnit (`vendor/bin/phpunit`) | 556 tests, 3598 assertions, **3 failures/errors, all pre-existing and unrelated**: `ExampleTest::testBasicTest`, `FunnelEditorMediaAndCatalogueTest::test_orders_carry_nullable_funnel_attribution`, `UploadSafetyTest::test_svg_and_xml_are_accepted_from_staff_only`; 2 skipped |
| Executed: concurrency suite (`phpunit.concurrency.xml`), run after the full suite, not overlapping | **61 tests, 1695 assertions, OK** |
| Executed: audit-query validation | `T5_PHASE0_AUDIT_QUERIES.sql` run with `mysql --force` on `inv_v2_test`: **0 errors**, output in scratch `p0/audit_run_output.txt` (all "expected zero" queries returned no rows on the empty synthetic data, which proves syntax only); a `READ ONLY` session refused an `INSERT` |
| Executed: schema/lock probes | engine, index and FK listings; two temporary-row lock experiments (section 3 of the schema report); one retry-coverage probe (below) |
| Executed: one retry-coverage probe | throw-away test in the scratchpad (not in the repository): `BalanceTransferController::store` + `DeadlockInjector::once` → no retry |
| Fingerprints after the runs | `inv_v2_test` financial section identical to the previous verification; `sc_staging` fingerprint **identical** to `/tmp/claude-0/staging/fp_final.txt` (read-only aggregate query, disclosed in the schema report) |

Files inspected (read-only): the five plan/review documents and `docs/evidence/review/*`; `config/deadlock_retry.php`, `config/filesystems.php`, `app/Console/Kernel.php`; controllers `PurchaseController`, `SupplierPaymentController`, `CourierSettlementController`, `BalanceTransferController`, `BankTransactionController`, `ExpensePaymentController`, `IncomeController`, `CustomerPaymentController`, `VoucherController`, `StockAdjustmentController`, `PurchaseOrderController`, `SupplierController`, `ChartOfAccountController`, `OrderController`, `CheckoutController`, `PosController`, `Api/V2/OrderController`, `RefundRequestController`; `app/Utility/PosUtility.php`; services `Idempotency`, `Funnel/Checkout/FunnelOrderService`, `Chat/ChatOrderService`, `Accounting/*`; models `ChartOfAccount`, `Builders/ChartOfAccountBuilder`, `CourierSettlement`, `SupplierPayment`, `Income`, `CustomerPayment`, `Order`, numbering models; middleware `RetryDeadlockedCheckout`; `sqlupdates/inventory_v2_innodb_conversion.sql`; docs runbooks; the `tests/` tree (to map existing coverage); `information_schema` of the local schemas.

---

## 3. Findings from Phase 0 (new or confirmed)

| ID | Status | Finding | Exact source / evidence |
|---|---|---|---|
| P0-1 | **FAILED** (High) | D6 cart lock locks the whole `carts` table | `app/Http/Controllers/OrderController.php::store` line ~1286 `Cart::where('user_id', $user_id)->lockForUpdate()`; `carts` has only a PK (`information_schema.statistics`); lock experiment: session B, other user's row, `ERROR 1205` after 2 s (schema report §5.3); `EXPLAIN SELECT id FROM carts WHERE user_id = 1 FOR UPDATE` → `type=ALL`. The same missing index makes the web checkout (`CheckoutController::checkout` line ~203 reads `Cart::where('user_id')`) and the API (`Api\V2\OrderController::store` lines ~285–299) full scans. **Recommended next step:** do not enable the D6 guard anywhere real; when remediation is approved, replace the lock or add `carts(user_id)` first. |
| P0-2 | **FAILED** (Medium) | Deadlock retry is invisible for flash+redirect answers | `app/Http/Middleware/RetryDeadlockedCheckout.php::answerSaysDeadlock` inspects `$response->exception` and the response body text only; a redirect has no text. Measured on `BalanceTransferController::store` (probe above). Static scan of the 40+ configured methods: error answer = JSON containing `getMessage()` (detected by text) for most; **flash/redirect (not detected)** for `PurchaseController::purchaseUpdatePayment`, `::purchaseDelete`, `PurchaseOrderController::purchaseUpdatePayment`, `OrderController::orderEditUpdate`, `VoucherController::store`, `::update`, `ExpensePaymentController::update`, `IncomeController::store`, `::update`, `BalanceTransferController::store`, `::update` (11; heuristic, only `BalanceTransferController::store` verified by execution). Rolled-back work leaves nothing behind, so this is a coverage gap, not a corruption risk. |
| P0-3 | **VERIFIED** | Scheduler references a command that does not exist | `app/Console/Kernel.php` line 31 `accounts:import-customer-payments` hourly; `grep -rn "import-customer-payments"` over the repository (excluding vendor) finds only that line; commands with signature `accounts:*` are `accounts:auto-deposit` and `accounts:cleanup-statement-logs`. **Needs production log check (BLOCKED).** |
| P0-4 | **VERIFIED** | Plan corrections | `combined_orders` columns are `id,user_id,temp_user_id,shipping_address,grand_total,created_at,updated_at` (no `code`): plan M-6 for `combined_orders.code` is void. `PurchaseOrderController` public methods: no `destroy`. `BankTransactionController` methods: `deposit,withdraw,transfer,cashOut,chequeClearing`; overdraft checks are in `withdraw` (line 109) and `transfer` (line 169). A table `account_transfers` (unique `transfer_no`) exists and is not covered by any document (usage NOT VERIFIED). |
| P0-5 | **VERIFIED** | Schema drift between local schemas | `sc_staging.purchases` has `uq_purchases_reference_no`; `inv_v2_test.purchases` does not. `inv_v2_test` has `uq_product_stocks_product_variant` and `idx_purchase_products_product_variant`; `sc_staging` has neither. `ledger_outbox`, `refund_postings` do not exist in `sc_staging`. Source: index diff in schema report. |
| P0-6 | **VERIFIED** | Missing unique constraint on order codes | `orders` has no index on `code`; `Order::generateUniqueCode()` (`app/Models/Order.php` line 140) does `mt_rand(100000, 999999)` + `where code` exists-check = full scan per order, no constraint to catch a race. |
| P0-7 | **VERIFIED** | MyISAM `drafts` on the checkout path | `drafts` MyISAM in every local schema; deleted in `CheckoutController` ~212 and `Api\V2\OrderController` ~747; the delete is not covered by any rollback. |
| P0-8 | **NOT VERIFIED** | Duplicate order via web checkout and API under double submit | `CheckoutController::checkout` (no transaction, reads cart, calls `OrderController::store`, deletes cart afterwards), `Api\V2\OrderController::store` (reads the cart inside its transaction at lines 285–299 without a lock; deletes it at 684–686 before commit). No test exists for simultaneous identical submissions on those two entries. |
| P0-9 | **NOT VERIFIED** | Customer payment over-application | `CustomerPaymentController::store` (lines ~109–245) computes each order's remaining amount from `order_payment_mappings` sums read without locks; no unique `(customer_payment_id, order_id)`. No test. |

---

## 4. Business-rule verification (Task 2)

Conventions: line numbers are current as of this audit. "TX" = transaction boundary. "Dup" = existing duplicate protection. "Fail/retry" = behaviour on failure and on user retry. Effects on balances use `ChartOfAccount` saves (D3 delta writes since the second edition) unless noted; with the flag off, the legacy-ledger bridge is inactive (no outbox, no journal) [V `LegacyLedgerBridge::active`].

### 4.1 Purchases (legacy screens) – `app/Http/Controllers/PurchaseController.php`
| Operation | Method (lines) | Tables | TX | Balance / ledger | Stock | Files / external | Dup | Fail / retry |
|---|---|---|---|---|---|---|---|---|
| Create | `store` (278–492) | `purchases`, `purchase_products`, `product_stocks`, `suppliers`, `account_statement_logs`, `chart_of_accounts`, `idempotency_keys` | begin 280, commit 478, rollBack 315 + catch | supplier `due_amount += due`, `opening_balance` used; Accounts Payable, Inventory, Opening-Supplier-Balance and payment account balances; statement logs [V] | `updateProductStockVariant` adds shelf quantity + one FIFO layer per line [V] | document upload via `RequestSideEffects::once` (347) [V] | `op_key` → `Idempotency` scope `legacy_purchase` [V] (the only legacy screen with one); reference number `Ref-N` from a locking read (351–355) | validation/funds errors thrown and returned as JSON with message; deadlock detected by text → retried by middleware [V, tests] |
| Edit | `update` (493–819) | same + reversal of old values | begin 495, commit 800, rollBack 807; catch rethrows | reverts the old due/opening/payment effects then applies new (revert-and-reapply) [V] | stock/layer difference | new document via `once`, old deleted `afterCommit` (577–580) [V] | none | not idempotent: a repeated edit re-reverts using the values read at its start [A: depends on lock timing] |
| Delete | `purchaseDelete` (2003–2089) | `purchases` (hard delete), `purchase_products`, `product_stocks`, accounts, logs | begin 2005, commit 2077, rollBack 2081 | reverses payable/opening/payment accounts and writes reversal logs; **does not change `suppliers.due_amount`** [V, probe P3] | removes shelf quantity; refuses if any unit of the layer was sold (2025–2032) | **deletes the document before commit (2070)** [V] | none (second delete refused only by the stock guard) | redirect + flash on error (P0-2: not retried) |
| Pay due | `purchaseUpdatePayment` (196–277) | `purchases`, `suppliers` | begin 198, commit 268; early `return` 226 | advance/opening balance use; no account movement [NT] | – | – | none | redirect + flash |
| Returns | `storePurchaseReturn` (1327–1544), `updatePurchaseReturn` (1820–2002); no delete | `purchase_returns`, `purchase_return_products`, `product_stocks`, layers, accounts, supplier | begin 1329/1822, commit 1535/1992 | AP/inventory/opening reversals, statement logs, supplier due/opening [NT] | removes returned units from shelf/layers [NT] | upload at 1354 inside tx; old deleted + new stored inside tx at 1896–1899 [V] | none; reference `$request->reference_no ?? 'PR-'.time()` (1365) | JSON error with message |

### 4.2 Supplier payments – `SupplierPaymentController.php`
| Operation | Method (lines) | Tables | TX | Effects | Files | Dup | Fail/retry |
|---|---|---|---|---|---|---|---|
| Create | `store` (199–401) | `supplier_payments`, `supplier_payment_purchases`, `purchases`, `suppliers`, `chart_of_accounts`, `account_statement_logs` | begin 251, commit 382, rollBack 391; early `return` 261 | supplier `due_amount = due − amount`, `paid_amount += amount`; each purchase `total_payment_due −= alloc`; payment account −; Accounts Payable −; logs [V]. Overdraft check line 353 [V] | attachments via `once` (282) | none (number `PAY-NNNNNN` via `SupplierPayment::createNumbered`) | JSON error; overpay refused by `alloc > total_payment_due` check (under supplier lock) |
| Edit | `update` (532–709) | same | begin 546, commit 701 | reverse old amounts, apply new; overdraft check 673 [V] | new via `once` (620), old deleted `afterCommit` (634–636) | none | JSON error |
| Delete | `destroy` (710–787) | same, **hard delete** | begin 712, commit 774 | `due_amount += amount`, `paid_amount −= amount`, purchases' due +, account credit [V] | attachments deleted `afterCommit` (767–769) | none | **repeated simultaneous delete applies several times** [V, probe P2] |

### 4.3 Courier settlement – `CourierSettlementController.php`
| Operation | Method (lines) | Tables | TX | Effects | Dup | Fail/retry |
|---|---|---|---|---|---|---|
| Settle | `settle` (195–1127) | `courier_settlements`, `courier_settlement_details`, `orders`, `chart_of_accounts`, `account_statement_logs` | begin 214, commit 1028, rollBack 1115; **six early returns inside the transaction** (233, 241, 253, 264, 281, 391) | locks order + system/selected accounts first (D2); AR −, Inventory −COGS, COGS +, selected account + net, Sales/Courier Income/Bad Debt/expense legs by variant (full, partial delivered, zero-shipping customer-paid/admin-paid, POS: no sales/COGS/inventory) [V] | order `settlement_status` check under lock + unique `settlement_no` | JSON with message; deadlock retried [V tests] |
| Unsettle | `unsettle` (1128–1207) | same + deletes | begin 1135, commit 1189; early return 1141 | reverses every statement log of the settlement by balance, deletes logs, details, settlement, clears order fields [V] | **none**; validation `exists:courier_settlements,id` | **three simultaneous requests reversed three times** [V, probe P1]; after the first commit a retry gets a validation failure (hard delete) |
| Number | `CourierSettlement::createNumbered/generateSettlementNo` | | savepoint | `STL-YYYYMMDD-NNNN` | unique index | retried on 1062 up to 5 [V] |

### 4.4 Cash/bank balance updates, transfers, expenses, income
| Operation | Method (lines) | TX | Effects | Overdraft rule [V] | Dup | Notes |
|---|---|---|---|---|---|---|
| Balance write primitive | `Models/ChartOfAccount::performUpdate` (delta write), `Builders/ChartOfAccountBuilder::increment/decrement/update`, `JournalService` raw `UPDATE … current_balance + ?`, `Income::updateAccountBalance` (increments) | – | atomic per statement | – | – | PosUtility, vouchers, expenses, customer payments, settle, purchases mutate via model saves |
| Balance transfer / withdraw / deposit / cashout | `BalanceTransferController::store` (57–134), `update` (135–195), `destroy` (196–220) | begin 96/159/200 | from − (amount + charge), to + amount; logs [V] | withdraw/transfer/cashout: `current_balance < amount + charge` → refuse, **before the transaction, unlocked** (line 91) | none | number `WD/DP/TR/CO/AD-YYMMNNNN` (read-then-insert) |
| Bank transactions | `BankTransactionController::deposit` (41), `withdraw` (91), `transfer` (150), `chequeClearing` (216) | begin 57/116/176/234 | account movements, status `cleared`; [NT] ledger detail | withdraw line 109, transfer line 169 (before the transaction); none in deposit/cheque clearing | none | no update/destroy exist |
| Expense | `ExpensePaymentController::store` (90–201), `update` (239–370), `destroy` (371–431) | begin 122/271/374 | `updateAccountBalance()` + logs [NT detail] | store 114, update 267 (pre-transaction) | none | number `EXP-YYNNNNNN` |
| Income | `IncomeController::store` (85–176), `update`, `destroy` | begin 108/231/327 | `Income::updateAccountBalance`: received account +total, income accounts +amount (atomic increments) [V] | none | none | number `INC-YYNNNNNN` |
| Voucher | `VoucherController::store` (143–325), `update` (460–672), `destroy` (673–711) | begin 161/480/676; **validation returns after begin** (173, 180, 184, 207, 213; 501, 508, 512, 535, 541) | per-line debit/credit on chosen accounts via `Voucher` model | none | none | numbers `CP/BP/CR/BR/JV/CV-YYMMNNNN`; two implementations of the generator |
| Stock adjustment | `StockAdjustmentController::store` (158–289), `update`, `destroy` (353–416) | begin 184/356 | stock rows + layers + adjustment/loss account [NT] | store ~383 `current_balance < 0` on the adjustment account | none | number `SA<yyyy>/NNNN` (`generateReferenceNo`) |
| Chart of accounts | `ChartOfAccountController::store` (48), `update` (141), `updateBalance` (299), `destroy` (251) | begin 65/159/316/271 | opening balance changes adjust `current_balance` by the difference and Owner's Equity [V 181–226]; `destroy` requires balance 0 and deletes statements | – | none | |

### 4.5 Customer payments and auto-import – `CustomerPaymentController.php`
`store` (90–262, begin 109, commit 243): applies money to **orders already marked paid** (`where payment_status = 'paid'`, 118–121), writing `order_payment_mappings.amount_applied`, a `Voucher`, `customer_payments` and account/AR legs; marks orders `completed` [V 109–200 excerpt]. No lock on the orders; remaining amount computed from mapping sums (P0-9). Numbers `CUS000001`, voucher numbers. `destroy` (307–372, begin 311) removes mappings/voucher. `autoImport` (373–442): **no transaction**, loops customers calling `store`, takes the first `cash`/`bank` account and first active `TransactionMethod` (lines ~405–420), swallows `\Exception` into `$errors`, answers `success:true` always [V]. Dup: none. (The scheduler's hourly `accounts:import-customer-payments` does not exist, P0-3.)

### 4.6 Purchase-order conversion – `PurchaseOrderController::convertToPurchase` (628–710)
No transaction [V]. Builds a request, calls `app(PurchaseController::class)->store($newRequest)` (its own commit/journal), then if successful updates `purchase_orders.status`, `details.recieved_quantity` outside any transaction (661–681) [V]. Dup: none (`op_key` is not passed [A: not seen in the built data]). Failure between the two steps leaves purchase and open PO.

### 4.7 Order channels – creation and stock
| Channel | Entry | TX | Stock | Accounts | Dup | Failure |
|---|---|---|---|---|---|---|
| Web checkout | `CheckoutController::checkout` (152–247) → `OrderController::store` (1231–1508); route `payment.checkout` | `checkout` has **no** transaction; `store` has one closure (1282); cart delete (`$carts->toQuery()->delete()`, ~205) and draft delete (~212, MyISAM) **after** it | `StockLedger::take` at creation (legacy mode); v2 mode reserves nothing until confirmation (flag off today) [V] | order due → AR legs via `OrderStockObserver`/`insertOrderStatementLog` [NT] | cart consumed after the transaction; D6 guard (15 s, product/qty) added in this branch | `store` returns null on success; session writes after commit |
| Admin route | `POST /admin/orders` → `OrderController::store` directly (`Route::resource('orders')` in `routes/admin.php` 675) | as above; **does not delete the cart** | as above | as above | D6 guard only | |
| POS | `PosController::order_store` (route `pos-order`, `retry.deadlock`) → `PosUtility::orderStore` (697–1160) | begin 787, commit 1132, release on failure 1137/1151 | `StockLedger::take(…, ['immediate' => true])` (803) | payment accounts +, AR, Sales, COGS, Inventory legs (~903–1106) [V] | none | |
| API v2 | `Api\V2\OrderController::store` (139–) routes `order/store`, `guest/order/store` (`retry.deadlock`) | begin 242, commit 690 | `StockLedger::take` (461) | AR/sales legs [NT] | none; cart read without lock (285–299), deleted at 684–686 inside the transaction | `InventoryException` → refusal; `\Throwable` → rollback + release |
| Funnel | `FunnelCheckoutController::place` → `FunnelOrderService` (route `{slug}/checkout`, `retry.deadlock`) | inside service | `StockLedger` | via order observers [NT] | **`funnel_checkout_attempts.key_hash` unique** (sha256 of funnel id + client key) [V] | attempt states rejected/errored/settled |
| Chat | `ChatOrderService::place` | `DB::transaction` loop (≤3 on deadlock) | `StockLedger` | via order observers [NT] | `chat_conversation_orders.idempotency_key` unique + early return of the existing order [V] | |

### 4.8 Stock deduction, cancellation and returns
Deduction: `StockLedger::take` (conditional single-statement updates on `product_stocks` and FIFO layers `purchase_products`, ordered by id) [V earlier phases]. Cancellation: `set_order_line_status($detail,'cancelled')` (helper), `OrderController::update_delivery_status` (1663–1963, **no transaction**, notifications/SMS, restock + accounting reversal [NT]), `updateItemsStatus` (108–158, begin 117), `processUpdateItemsStatus` (3235–3444, begin 3262), `orderEditDelete` (2984–3012), `orderEditUpdate` (2483–2727, begin 2516, catch swallows into flash + manual `StockLedger::release`), `OrderController::destroy` (1609–1639, **no transaction**). Returns: `DemandService` return receipts (scope `return_receipt`, v2), legacy purchase returns (4.1). Refund: `RefundRequestController::refund_pay` → `RefundService` (`refund_postings` unique key/request/external ref) [V, parallel-tested earlier].

### 4.9 Document-number generation (all read-then-insert unless stated)
| Generator | Source | Format | Unique index | Notes |
|---|---|---|---|---|
| `CourierSettlement::generateSettlementNo` + `createNumbered` | `app/Models/CourierSettlement.php` | `STL-YYYYMMDD-NNNN` | yes | locking read, retry on 1062 |
| `SupplierPayment::createNumbered` | `app/Models/SupplierPayment.php` | `PAY-NNNNNN` | yes | same |
| `StockAdjustment::generateReferenceNo` | model | `SA<yyyy>/NNNN` | yes | also previewed at controller line 53 |
| `ExpensePayment::generateVoucherNo` | model | `EXP-YYNNNNNN` | yes | also `AutoDepositFunds` |
| `BalanceTransfer::generateVoucherNo` | model | `WD/DP/TR/CO/AD-YYMMNNNN` | yes | also `AutoDepositFunds` |
| `Voucher::generateVoucherNo` and `VoucherController::generateVoucherNo` (duplicate) | model/controller | `CP…CV-YYMMNNNN` | yes | controller copy is used at line 264 |
| `CustomerPayment::generatePaymentNo` | model | `CUS000001` | yes | |
| `Income::generateVoucherNo` | model | `INC-YYNNNNNN` | yes | |
| `CostCenter::generateCode` | model | `CC-NNNN` | yes | |
| Legacy purchase reference | `PurchaseController::store` 351–355 | `Ref-N` from 1000 | **no** (test) / yes (staging) | `Purchase::lockForUpdate()->where('reference_no','like','Ref-%')->orderBy('id','desc')` |
| Purchase return reference | `PurchaseController::storePurchaseReturn` 1365 | request value or `PR-<unix time>` | **no** | |
| PO reference | `PurchaseOrderController::store` 324/349 | client text; validated `unique:purchases,reference_no` (wrong table) | **no** | |
| Order code | `Order::generateUniqueCode` | 6 random digits | **no** | |
| Account code | `ChartOfAccountController::generateAccountCode` | by sub type | yes | |
Rollback behaviour of all read-then-insert generators: nothing is consumed (the number is derived from existing rows), so a rolled-back attempt leaves no gap; two simultaneous creations can pick the same number and the second then fails on the unique index (except the four without an index).

### 4.10 Overdraft validation (every check found by `grep -n nsufficient`)
| Where | Rule [V] | When evaluated | Locked? |
|---|---|---|---|
| `BalanceTransferController::store` 91 | `current_balance < amount + charge` for withdraw/transfer/cashout | before the transaction | no |
| `BankTransactionController::withdraw` 109, `::transfer` 169 | `current_balance < amount` | before the transaction | no |
| `ExpensePaymentController::store` 114, `::update` 267 | `current_balance < total` | before the transaction (begins at 122/271) | no |
| `SupplierPaymentController::store` 353, `::update` 673 | `current_balance < amount` | inside | no (account read plain) |
| `PurchaseController::store` 430, `::update` 710 | `current_balance < paidAmount` | inside | **yes** (`lockForUpdate` since the second edition) |
| `PurchaseController::purchaseUpdatePayment` 226, `PurchaseOrderController::purchaseUpdatePayment` 210 | insufficient *advance* balance | inside | supplier locked |
| `StockAdjustmentController` ~383 | adjustment account `< 0` after change | inside | no |
No check exists in income, vouchers, customer payments, settle, unsettle, `ChartOfAccountController::updateBalance`; `chequeClearing` [NT].

### 4.11 Console jobs touching balances
`accounts:auto-deposit` (`AutoDepositFunds`, daily 00:01 Asia/Dhaka, creates `BalanceTransfer` rows with `auto_deposit` vouchers), `accounts:cleanup-statement-logs` (`CleanupStatementLogs`, dry-run unless `--apply`; assigns an absolute balance at line 345), `ReverseSettlement`, `FixPartialSettlements`, `FixPosCourierIncome` (manual; not scheduled), `ledger:process-outbox --alert` (every minute), `courier:sync-status`, `orders:expire-unpaid`, `funnel:reconcile`, `chat:maintain`. Their transactional behaviour is **NOT VERIFIED**.

---

## 5. Data-risk audit (Task 4)
* **Deliverable:** `docs/T5_PHASE0_AUDIT_QUERIES.sql` – about 100 read-only SELECT statements in 10 sections (environment evidence; duplicate document numbers; duplicate financial operations; duplicate payment references; duplicate/unbalanced statement and ledger entries; supplier-due inconsistencies; account-balance inconsistencies; stock/FIFO; orphans; idempotency keys). The file opens a `READ ONLY` session; it returns ids, counts, document numbers and amounts only (no names, phones, addresses or notes).
* **Validation executed:** all statements ran without error on `inv_v2_test`; a write attempted in the read-only session was refused. Results on the empty synthetic data prove nothing about production.
* **Execution on a production copy: BLOCKED.** Required: an authorised, isolated restore of a recent production backup on a server with controlled access; confirmation that PII-bearing columns are masked or the environment is access-controlled (`tests/staging/mask-pii.sql` exists for the rehearsal copies); the engine/version of that server. Never copy production customer data into an uncontrolled environment. No repair, cleanup or delete query exists in the file or is authorised.
* **Assumptions flagged inside the SQL:** the intended relation between `suppliers.due_amount` and `purchases.total_payment_due` (E2), purchase arithmetic (E6), normal side of account types (F3), balanced legacy outbox groups (D4), and that document paths live in `purchases.document`/`purchase_returns.document`.

---

## 6. Feasibility of the 11 designs (Task 5)
Classification applies to the design as written in `T5_REMEDIATION_PLAN.md`, given Phase 0 facts. "Prod" = production facts that are BLOCKED.

| # | Design | Classification | Reason, required changes, dependencies, risks |
|---|---|---|---|
| 1 | Idempotency via existing `idempotency_keys` | **FEASIBLE WITH CHANGES** | Table, unique `(scope,key)`, InnoDB, replay and rollback semantics are VERIFIED locally by the earlier executed suites. Changes: the legacy controllers use manual `beginTransaction`; each reversal must run inside `Idempotency::once` (own `DB::transaction`, 3 attempts) so manual begin/commit are removed; remove the request-level retry for those routes to keep 3 attempts total; scope ≤ 40 chars and key ≤ 120 are sufficient; add M-1 columns if an audit trail is wanted. Dependency: the table must exist in production (**BLOCKED**) and the purchasing tables must be InnoDB. Risk: no key clean-up exists; keys grow unbounded. |
| 2 | Hard-deleted document retry handling | **FEASIBLE WITH CHANGES** | The key row acts as tombstone (hard delete confirmed: `CourierSettlement`, `SupplierPayment` models lack `SoftDeletes`; `Purchase` hard-deleted). Required: remove `exists:` validation on the unsettle route (`unsettle` validates `exists:courier_settlements,id`), tolerate a missing document when a key exists, carry the original response in `result`. Risk: a key written before a later partial failure cannot exist (same transaction), so only commit produces a tombstone – consistent. |
| 3 | Checkout token lifecycle and atomic consumption | **FEASIBLE WITH CHANGES** and **NEEDS BUSINESS APPROVAL** | Atomic consumption needs the cart delete inside the order transaction, which today is outside (`CheckoutController` ~205). Blocking dependency: `carts` has **no index** – any lock/consume by `user_id` locks the entire table (P0-1) → `carts(user_id)` must exist first (plan M-4 omitted it). `drafts` is MyISAM (not atomic). The payment-select view that would carry the token was not located (**NOT VERIFIED**). Session driver/guest flow (`guest_created`) must be preserved. Business: token mandatory vs fallback, retention (decision 5). |
| 4 | Durable after-commit file cleanup | **FEASIBLE** | New InnoDB table + scheduled reconciler follows the existing `ledger:process-outbox` pattern (scheduler present in `Kernel.php`). Dependencies: the production scheduler must run (**BLOCKED**) and the storage driver (local vs S3, **BLOCKED**) must support delete/exists. Risk: orphan files if the scheduler is down; reconciler must be idempotent on missing files. |
| 5 | Consistent financial lock ordering | **FEASIBLE WITH CHANGES** | Row locks by PK on accounts/suppliers/documents work on InnoDB (VERIFIED earlier). Changes: indexes so that locking reads are row-level – `account_statement_logs(source_type, source_id)` for `unsettle`'s log read, `purchases(supplier_id)`, `supplier_payment_purchases(supplier_payment_id)`, `carts(user_id)`; resolve system accounts before the transaction; implement one helper. Risk: REPEATABLE READ gap locks on non-unique ranges; MyISAM purchasing tables make it impossible. |
| 6 | Supplier due and account balance protection | **FEASIBLE WITH CHANGES** | PK locks fine; D3 delta writes and supplier locks exist. Changes: lock `SupplierController::supplierUpdate`/`supplierDelete`; money columns are varchar/double (needs a `Money` helper or M-9); the due relation is undefined (E2 is a report). Prod: BLOCKED. Needs accountant input (decisions 2, 4, 13). |
| 7 | Overdraft validation under concurrent requests | **FEASIBLE WITH CHANGES** and **NEEDS BUSINESS APPROVAL** | Technically: lock the account row, re-read, evaluate the same comparison – feasible with PK locks. The rules differ per screen (4.10), checks occur before transactions in four controllers, and updates revert before re-checking in an undocumented way → characterization tests first. Business: which screens block, warn or have no rule (decision 3). |
| 8 | Sequence-table document numbering | **FEASIBLE WITH CHANGES** and **NEEDS BUSINESS APPROVAL** | A new InnoDB table with `INSERT … ON DUPLICATE KEY UPDATE next=LAST_INSERT_ID(next+1)` works on MariaDB 10.11 and MySQL; rolls back with the transaction. Changes: seed from existing maxima (data insert into a new table), route all 14 generators and the two console callers through it, unify the two voucher generators, handle the four columns without unique indexes. Risk: any path that bypasses the sequence reintroduces duplicates; formats/period resets are accounting decisions (decision 6, 7). |
| 9 | Purchase-order conversion redesign | **FEASIBLE WITH CHANGES** and **NEEDS BUSINESS APPROVAL** | Requires extracting the database part of `PurchaseController::store` into a service callable inside a caller's transaction, a new nullable unique `purchase_orders.converted_purchase_id` (`purchase_orders.status` and line `recieved_quantity` already exist), and the key `convert:purchase_order:{id}`. Business: second click returns the existing purchase; partial conversions (decision 9). |
| 10 | Customer-payment auto-import redesign | **FEASIBLE WITH CHANGES** and **NEEDS BUSINESS APPROVAL** | Per-customer service + per-customer key is feasible; the loop must stop swallowing deadlocks. Prerequisites: locks on the customer's orders by PK ids, an index/unique on `order_payment_mappings (customer_payment_id, order_id)` after an audit. Business: account/method selection (currently "first match"), response shape, queueing (decision 10). Note the non-existent scheduled command (P0-3). |
| 11 | MyISAM-to-InnoDB migration dependencies | **BLOCKED** (production) / **FEASIBLE** (local rehearsal) | Production engines unknown. Locally: no FK depends on the five tables; the script exists and requires a maintenance window, a verified backup, free disk ≈ table size, duplicate checks and adds `uq_purchases_reference_no` (fails if duplicates exist). `drafts` and the non-financial MyISAM tables are outside the plan but `drafts` stays non-atomic. The v2 flag already refuses to enable while `purchase_products` is MyISAM (per `docs/INVENTORY_PURCHASING_UPGRADE.md`). |

---

## 7. What is verified versus assumed (summary)
* **Verified here by execution:** local engine/version; engines, indexes, unique keys, FKs of the local schemas; carts table-wide lock (error 1205); settlement-number query does not lock old rows; balance-transfer deadlock not retried; audit SQL runs error-free in a read-only session; the existing suites (556 / 61) and the fingerprints.
* **Verified by reading code:** every method, line number, transaction boundary, overdraft rule, number format, scheduler entry quoted in this report.
* **Not traced / assumed:** line-level ledger effects of bank transactions, stock adjustments, vouchers and customer-payment AR legs ([NT]); the view that renders the checkout payment form; production anything.

## 8. Is the remediation design technically ready for implementation?
**No.** Reasons: (1) production engine/version/table engines/`idempotency_keys` existence/scheduler/storage driver are BLOCKED – compatibility cannot be claimed; (2) two shipped changes (D6 cart lock, request-level retry coverage) are defective in ways the plan did not anticipate, and the plan's index list (M-4) omits `carts(user_id)`; (3) characterization tests of today's business rules do not exist yet (Phase 0 specified them, did not run them); (4) all 15 business decisions are open; (5) the data audit on a production copy is BLOCKED. What *is* ready: the design direction, the test specifications, the audit SQL and the decision register.

## 9. Blocked items
Production engine/version/engines/indexes (exact query in the schema report §2); fresh production backup and an authorised isolated restore for the data audit; production scheduler/cron and storage driver; production log evidence for the missing `accounts:import-customer-payments` command; the checkout payment-view template (not located – needs confirmation from the front-end owner); real admin theme; accountant review. Staging activation: NOT authorised, not performed.
