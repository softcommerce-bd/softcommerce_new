# T-5 Phase 0 – Test baseline for the 21 workflows

**These are specifications. No new test was written, no existing test or fixture was modified.** Every workflow is **NOT VERIFIED** until a recorded run of its tests passes. "Existing evidence" lists tests that already exist and what they do *not* cover; it does not verify the workflow.

Workflow **numbers 1–21 and scope are the exact 21 rows of `docs/T5_REMEDIATION_PLAN.md` §8.2**; titles are shortened, and W03/W11 add the `store` operation to the plan's "update / destroy" so that create-path duplicates are specified too. Companion: `T5_PHASE0_AUDIT_REPORT.md`.

## 0. Executed baseline (this phase, disposable `inv_v2_test`, MariaDB 10.11.14)
| Run | Result |
|---|---|
| `vendor/bin/phpunit` (full) | 556 tests, 3598 assertions; **3 pre-existing unrelated failures** (`ExampleTest`, `FunnelEditorMediaAndCatalogueTest::test_orders_carry_nullable_funnel_attribution`, `UploadSafetyTest::test_svg_and_xml_are_accepted_from_staff_only`); 2 skipped |
| `vendor/bin/phpunit -c phpunit.concurrency.xml` (run after, never overlapping) | **61 tests, 1695 assertions, OK** |
| Fingerprints | test DB financial section identical; `sc_staging` identical |
These runs show only that nothing regressed; none of them verifies a workflow below.

## 1. Conventions (apply to all 21)
* **Normal request:** success path with exact expected numbers (computed by hand in the test, not copied from output).
* **Repeated identical request:** same body (and, after remediation, same `op_token`/identity) sent again sequentially, and again after a simulated lost response (re-send after commit).
* **Simultaneous parallel requests:** real processes via `HttpWorkflowCase::race()` (`tests/Concurrency/worker.php` action `http_as`), authenticated Super-Admin users, released together.
* **Deadlock/retry:** (a) in-process fault injection `DeadlockInjector::once/always(regex)` — success on the retry / exactly 3 attempts then a clean failure; (b) real deadlock with the helper-process pattern of `RealDeadlockHttpTest` (action `lock_then_lock`, `pause_after`) asserting `X-Deadlock-Retries`.
* **Transaction rollback:** injected failure after the last write; the **world fingerprint** (rows of every touched table, balances, statement logs, outbox, journal, files, stock, layers) equals the pre-request fingerprint and `DB::transactionLevel()` is back to its entry value.
* **Balance reconciliation:** every touched account and supplier compared with hand-computed expected values; sum of changes across accounts = 0 for balanced postings.
* **Ledger reconciliation:** `JournalService::trialBalance` balanced; `ledger_outbox` fully processed after `ledger:process-outbox`; no `journal_entries.idempotency_key` duplicated; `account_statement_logs` rows for the source equal the expected legs.
* **Stock reconciliation:** per product/variant `Σ product_stocks.qty = Σ purchase_products.remaining_qty`; no layer below 0 or above its purchased quantity; FIFO order oldest first.
* **File and event side effects:** file count/paths on a faked or temp disk; `Http`, `Mail`, `Notification`, `Queue`, `Bus` fakes and an event counter equal between a clean run and a run with one injected deadlock (pattern of `DeadlockSideEffectTest`).
* **Database:** disposable `*_test` database only (the workers refuse any name not ending in `_test`), reset with `tests/ui/reset-test-db.sql`; rows committed by parallel tests are removed by `HttpWorkflowCase`/`VerificationCase` high-water cleanup. The flag `INVENTORY_CONFIRMED_ONLY` is set only inside test processes via `HttpWorkflowCase::flag()`.

## 2. Fixture catalogue
| Id | Content |
|---|---|
| **FX-ACC** | Accounts (code prefix `T5-`): cash C1=1000, C2=1000, bank B1=1000; system accounts that the code looks up by name/type: Sales, Accounts Receivable, Inventory, Cost of Goods Sold, Accounts Payable, Opening Supplier Balance, Courier Income, Bad Debt Expense, Courier Settlement Expense, Courier Fee Expense, Owner's Equity — all balance 0 unless a workflow says otherwise |
| **FX-USR** | Admins A1–A5 (`user_type=admin`, role `Super Admin`), customers U1–U4 (`user_type=customer`) |
| **FX-SUP** | S1 (opening 0, due 0), S2 (opening 200, due 0) |
| **FX-PRD** | P1 variant `M` stock 10 = layers 2@400 + 8@410; P2 variant `M` stock 0, backorder, no layers; P3 stock 6 = one layer @400; P4 variant `''` stock 5 |
| **FX-DLV** | delivered, unsettled legacy order O1: due 5000, grand total 5000, courier steadfast, one line qty 5, `buying_price` 100, `purchase_id` set, `stock_mode='legacy'`; clones O2–O4; variants OP (partial), OZC/OZA (zero-shipping), OPOS (POS) |
| **FX-PUR** | purchase PU1 on S1: 5×20=100, paid 40 from C1, with a document; PU2/PU3: 100 on credit |
| **FX-CART** | carts + addresses A/B, coupon, for checkout tests |
| **FX-FILES** | `Storage::fake('public')`, or a temp directory as the `public` disk for parallel tests |
| **FX-X** | helper worker for real deadlocks (`lock_then_lock`, `touch`, `pause_after`) |
| **Flag** | OFF for legacy order-stock tests; ON for ledger/outbox tests (set inside test processes only) |

## 3. Workflow specifications

### W01 – Courier unsettle (R1)

**Status: NOT VERIFIED.** Known defect: **FAILED (defect reproduced by probe)**

* **Entry points:** `CourierSettlementController::unsettle` (1128–1207), route `POST /courier-settlement/unsettle`
* **Tables:** `courier_settlements`, `courier_settlement_details`, `orders`, `account_statement_logs`, `chart_of_accounts`, `ledger_outbox`, `journal_entries/lines`, `idempotency_keys` (after remediation)
* **Fixtures:** FX-ACC, FX-USR(A1–A4), FX-DLV settled through the real `settle` (O1: due 5000, courier fee 60, COGS 500) so the logs are genuine; flag ON in the test processes only
* **Normal successful request:** A1 unsettles O1 → success; settlement, details and logs gone; order fields cleared (`settlement_status`, `settlement_date`, `settlement_account_id`…)
* **Repeated identical request:** same `settlement_id` sent again after the first commit → today: validation failure (hard delete); required: success with `replayed:true`, no second effect
* **Simultaneous parallel requests:** 3 admins unsettle O1 at once; plus settle ∥ unsettle on O1; plus unsettle of two different settlements into the same account
* **Deadlock / retry:** `DeadlockInjector::once('/^update `chart_of_accounts`/')` (success, one reversal) and `::always` (3 attempts, nothing changes); real deadlock: helper holds the cash account then wants the order row
* **Transaction rollback:** injected failure after the logs are deleted and before commit → settlement, details, logs, order fields, balances identical to the settled state
* **Balance reconciliation:** cash back to 0 (4940 → 0), AR +5000, COGS −500, Inventory +500, Courier Income/Bad Debt legs reversed — each exactly once
* **Ledger reconciliation:** statement logs of the settlement = 0 afterwards; exactly one reversing outbox group (flag ON) and one journal posting; no `idempotency_key` duplicated; trial balance balanced
* **Stock reconciliation:** n/a (legacy lines carry cost; no stock movement)
* **File / event side effects:** none
* **Existing evidence (does not verify):** none passing. Probe `docs/evidence/review/ReviewProbeTest.php.txt` (P1) reproduces the defect: cash −9880, COGS −1500 after 3 simultaneous requests

### W02 – Courier settle variants

**Status: NOT VERIFIED.**

* **Entry points:** `CourierSettlementController::settle` (195–1127), route `POST /courier-settlement/settle`
* **Tables:** `courier_settlements`, `courier_settlement_details`, `orders`, `order_details`, `account_statement_logs`, `chart_of_accounts`, `ledger_outbox`
* **Fixtures:** FX-ACC + system accounts (Sales, AR, Inventory, COGS, Courier Income, Bad Debt, Settlement/Courier-fee expense); one order per variant: full delivery (O1), partial delivered with delivered/cancelled/return lines (OP), zero-shipping customer-paid (OZC, given_customer, actual fee), zero-shipping admin-paid (OZA), POS order (OPOS, `order_from='pos'`); cost 100/unit
* **Normal successful request:** settle each variant once; assert the single-run reference numbers (recorded from the first run and frozen in the test as golden values after accountant review)
* **Repeated identical request:** same body again → `already been settled` (today) / replay (after remediation); no second settlement
* **Simultaneous parallel requests:** same order ×4 admins (one wins); 4 different orders into one account (sum exact); per variant
* **Deadlock / retry:** injected once/always on `chart_of_accounts` update and on `courier_settlement_details` insert; real deadlock (helper) for variant 1
* **Transaction rollback:** failure after COGS posted → no settlement row, no log, no balance change
* **Balance reconciliation:** selected account +net; AR −collected; Courier Income/Bad Debt/Settlement expense per variant; POS: no Sales/COGS/Inventory entries
* **Ledger reconciliation:** outbox group per settlement balanced; journal entry once; `account_statement_logs` per settlement matches the variant's expected legs
* **Stock reconciliation:** legacy line cost layer unchanged (no stock movement)
* **File / event side effects:** none
* **Existing evidence (does not verify):** `CourierSettlementParallelTest` (full delivery: same order ×4, 4 orders into one account), `DeadlockWorkflowRetryTest` (once/always), `RealDeadlockHttpTest` (real deadlock), `SettlementCostV2Test` (v2 cost). Partial, zero-shipping and POS variants: none

### W03 – Supplier payment store / update / destroy (R2)

**Status: NOT VERIFIED.** Known defect: **FAILED (destroy ×3 → supplier due +90, cash +90 instead of +30; probe P2)**

* **Entry points:** `SupplierPaymentController::store` (199–401), `::update` (532–709), `::destroy` (710–787); routes `accounts/supplier-payments/store|update/{id}|delete/{id}`
* **Tables:** `supplier_payments`, `supplier_payment_purchases`, `purchases`, `suppliers`, `chart_of_accounts`, `account_statement_logs`, files (attachments)
* **Fixtures:** FX-ACC, FX-SUP (S1), purchase PU2 (credit 100) and PU3 (credit 100) on S1, cash C1=1000, a payment SP1=30 allocated to PU2, attachment fixtures
* **Normal successful request:** store a payment of 70 across PU2 (40) and PU3 (30); update to 50; destroy
* **Repeated identical request:** same store body ×2 sequential (today a second payment; required replay); destroy ×2; update ×2 with the same token
* **Simultaneous parallel requests:** destroy ×3; update ×3; 4 payments by 4 admins into one account; payment ∥ purchase delete
* **Deadlock / retry:** inject on `supplier_payment_purchases` insert (once/always) with attachments; real deadlock helper holding the account vs `destroy`
* **Transaction rollback:** failure after the supplier is updated → payment, pivots, files, supplier, purchase dues, account unchanged
* **Balance reconciliation:** supplier `due_amount`, `paid_amount`, each purchase `total_payment_due`, cash balance — exact deltas
* **Ledger reconciliation:** statement logs and (flag ON) outbox/journal once; payment numbers distinct
* **Stock reconciliation:** n/a
* **File / event side effects:** attachments stored once per request; removed after commit on destroy; none left on final failure
* **Existing evidence (does not verify):** `SupplierPaymentParallelTest` (store: 4×70 vs 100 invoice; 3 suppliers distinct numbers), `DeadlockWorkflowRetryTest` (store with attachments), `DeadlockSideEffectTest` (store). Update/destroy: probe P2 only

### W04 – Purchase update / purchaseDelete (R3) / purchaseUpdatePayment

**Status: NOT VERIFIED.** Known defect: **NOT VERIFIED; observation: supplier due unchanged after delete (decision 2)**

* **Entry points:** `PurchaseController::update` (493–819), `::purchaseDelete` (2003–2089), `::purchaseUpdatePayment` (196–277); routes `PUT /purchase/update/{id}`, `DELETE /purchase/delete/{id}`, purchase payment route
* **Tables:** `purchases`, `purchase_products`, `product_stocks`, `suppliers`, `chart_of_accounts`, `account_statement_logs`, files (document)
* **Fixtures:** FX-ACC, FX-SUP, P2 (backorder, no layers), purchase PU1 = 5×20 on S1 paid 40 from C1 with a document; a second purchase with a sold layer for the refusal case
* **Normal successful request:** edit quantity/cost; edit supplier S1→S2; delete PU1; pay the remaining due
* **Repeated identical request:** delete ×2 sequential; edit with the same form token ×2
* **Simultaneous parallel requests:** delete ×3; edit ×3; delete ∥ sale of its layer; supplier change ∥ payment on S1
* **Deadlock / retry:** inject on `purchase_products` insert and on `update suppliers` (once/always) with a document; real deadlock (supplier vs cash account) for edit
* **Transaction rollback:** failure after stock/layer change → stock, layers, supplier, accounts, document file all as before (document must still exist after a failed delete)
* **Balance reconciliation:** supplier due/opening, Accounts Payable, Inventory, payment account, Opening-Supplier-Balance — exact deltas per decision 2 (supplier due on delete)
* **Ledger reconciliation:** statement logs; outbox/journal once; reversal references `-DEL`/`-REV`
* **Stock reconciliation:** shelf = Σ layers per product/variant; a sold layer blocks delete; no negative layer
* **File / event side effects:** document stored once; not deleted before commit
* **Existing evidence (does not verify):** `PurchasingParallelTest` (store ×4 same key, different keys, purchases ∥ orders, receipts), `DeadlockWorkflowRetryTest` (store once/always; update keeps old document), `RealDeadlockHttpTest` (store), `PurchaseStockTest`, `LegacyFlowsJournalTest`. Delete/update/payment parallel: none (probe P3 single/triple delete equal; supplier due unchanged by delete)

### W05 – Purchase returns store / update

**Status: NOT VERIFIED.**

* **Entry points:** `PurchaseController::storePurchaseReturn` (1327–1544), `::updatePurchaseReturn` (1820–2002); routes `POST /purchase-return/store`, `PUT /purchase-return/update/{id}`
* **Tables:** `purchase_returns`, `purchase_return_products`, `product_stocks`, `purchase_products`, `suppliers`, `chart_of_accounts`, `account_statement_logs`, files
* **Fixtures:** FX-ACC, FX-SUP, a received purchase PU1 (5 units, layer remaining 5), document files
* **Normal successful request:** return 2 units; update to 3 units
* **Repeated identical request:** same body ×2; update ×2
* **Simultaneous parallel requests:** 4 returns on shared AP/Inventory accounts; update ×3
* **Deadlock / retry:** inject on `purchase_return_products` insert with upload (once/always)
* **Transaction rollback:** failure after the shelf change → shelf, layer, return rows, supplier, accounts, and the old document unchanged; no new file
* **Balance reconciliation:** AP −, Inventory −, supplier due/opening — exact
* **Ledger reconciliation:** reversal logs; outbox/journal once; reference numbers distinct (`PR-<time>` collides within one second today)
* **Stock reconciliation:** shelf and layer reduced once; never below zero
* **File / event side effects:** new document stored once; old one kept on failure
* **Existing evidence (does not verify):** `PurchaseStockTest` (sequential returns); no parallel, no deadlock test

### W06 – Purchase orders store / update / purchaseUpdatePayment (no destroy exists)

**Status: NOT VERIFIED.**

* **Entry points:** `PurchaseOrderController::store` (264–415), `::update` (416–543), `::purchaseUpdatePayment` (181–263)
* **Tables:** `purchase_orders`, `purchase_order_details`, `suppliers`, `chart_of_accounts` (advance), files
* **Fixtures:** FX-SUP (S2 opening 200), FX-USR, advance payment case
* **Normal successful request:** create PO; update; pay advance from opening balance
* **Repeated identical request:** same store body ×2; update ×2
* **Simultaneous parallel requests:** 4 POs with distinct references; update ×3; advance payment from 4 admins on S2
* **Deadlock / retry:** inject on the PO detail insert with a document (once/always)
* **Transaction rollback:** failure after the supplier opening balance is reduced → supplier and PO rows unchanged, no file
* **Balance reconciliation:** S2 opening balance never below 0; exact deltas
* **Ledger reconciliation:** PO has no ledger legs except advance logs [NT]; outbox once if any
* **Stock reconciliation:** n/a
* **File / event side effects:** document once; old deleted after commit
* **Existing evidence (does not verify):** `DeadlockRouteCoverageTest` only (route attachment). No behaviour test for the legacy PO screens

### W07 – Supplier edit / delete / processPayment

**Status: NOT VERIFIED.**

* **Entry points:** `SupplierController::supplierUpdate` (494–680), `::supplierDelete` (721–821), `::processPayment` (34–122)
* **Tables:** `suppliers`, `purchases`, `supplier_payments`, `chart_of_accounts`, `account_statement_logs`
* **Fixtures:** FX-SUP (S1 with purchases PU2/PU3 credit), FX-ACC
* **Normal successful request:** edit contact data; edit opening balance; processPayment 50; delete a supplier without history
* **Repeated identical request:** processPayment ×2; edit ×2
* **Simultaneous parallel requests:** edit ∥ purchase ∥ payment on S1; processPayment ×3; delete ∥ purchase creation
* **Deadlock / retry:** inject on `update suppliers`; real deadlock helper vs processPayment
* **Transaction rollback:** failure after the supplier row changed → supplier unchanged
* **Balance reconciliation:** supplier due/opening/paid equal the sequential-run values; edit conflict handling per decision 4
* **Ledger reconciliation:** AP/opening-balance account legs [NT]
* **Stock reconciliation:** n/a
* **File / event side effects:** n/a
* **Existing evidence (does not verify):** none

### W08 – Customer payment store / destroy (R4)

**Status: NOT VERIFIED.**

* **Entry points:** `CustomerPaymentController::store` (90–262), `::destroy` (307–372); route `accounts/customer-payments/store|delete`
* **Tables:** `customer_payments`, `order_payment_mappings`, `orders`, `vouchers`, `voucher_details`, `chart_of_accounts`, `account_statement_logs`
* **Fixtures:** FX-ACC, customers U1–U3 with orders already `payment_status='paid'` (grand_total 1000 each), payment account C1
* **Normal successful request:** apply 1000 to one order; destroy
* **Repeated identical request:** same body ×2
* **Simultaneous parallel requests:** same orders ×3; 4 customers into one account; destroy ×3
* **Deadlock / retry:** inject on `order_payment_mappings` insert; real deadlock on the account
* **Transaction rollback:** failure after the voucher is created → no payment, mapping, voucher, balance change
* **Balance reconciliation:** account +amount once; order remaining amounts reduce once (no over-application)
* **Ledger reconciliation:** voucher once; logs; outbox/journal once; payment and voucher numbers distinct
* **Stock reconciliation:** n/a
* **File / event side effects:** none
* **Existing evidence (does not verify):** none

### W09 – Voucher store / update / destroy (R7)

**Status: NOT VERIFIED.**

* **Entry points:** `VoucherController::store` (143–325), `::update` (460–672), `::destroy` (673–711)
* **Tables:** `vouchers`, `voucher_details`, `chart_of_accounts`, `account_statement_logs`
* **Fixtures:** FX-ACC with three accounts; types CP, CR, JV with multi-line debits/credits
* **Normal successful request:** create each type; update; destroy
* **Repeated identical request:** same body ×2
* **Simultaneous parallel requests:** 4 vouchers over 2 shared accounts; update ×3; destroy ×3
* **Deadlock / retry:** inject on `voucher_details` insert
* **Transaction rollback:** failure after the first line is posted → no voucher, no line, no balance change; every refusal path (validation after `beginTransaction`) leaves `transactionLevel` unchanged
* **Balance reconciliation:** each line debits/credits once; trial balance balanced
* **Ledger reconciliation:** outbox/journal once; distinct numbers (two generators exist)
* **Stock reconciliation:** n/a
* **File / event side effects:** none
* **Existing evidence (does not verify):** none

### W10 – Expense, Income, Bank transaction, Balance transfer

**Status: NOT VERIFIED.**

* **Entry points:** `ExpensePaymentController::store|update|destroy`, `IncomeController::store|update|destroy`, `BankTransactionController::deposit|withdraw|transfer|chequeClearing`, `BalanceTransferController::store|update|destroy`
* **Tables:** `expense_payments`, `incomes`, `bank_transactions`, `balance_transfers`, `chart_of_accounts`, `account_statement_logs`
* **Fixtures:** FX-ACC with C1=100, C2=0, B1=1000; cost center
* **Normal successful request:** create/update/destroy each type; transfer 100
* **Repeated identical request:** same body ×2
* **Simultaneous parallel requests:** 4 each on one account; destroy ×3; **overdraft race**: N requests each larger than half the balance; transfer A→B ∥ B→A
* **Deadlock / retry:** inject on the statement-log insert (balance transfer probe shows NO retry: flash+redirect); real deadlock A↔B
* **Transaction rollback:** failure after the first account moved → both accounts unchanged
* **Balance reconciliation:** balances exact; with today's rule exactly as many requests succeed as the balance allows; total conserved for transfers
* **Ledger reconciliation:** logs; outbox/journal once
* **Stock reconciliation:** n/a
* **File / event side effects:** none
* **Existing evidence (does not verify):** none (a one-off probe for `BalanceTransferController::store` exists; see audit report P0-2)

### W11 – Stock adjustment store / update / destroy (R9)

**Status: NOT VERIFIED.**

* **Entry points:** `StockAdjustmentController::store` (158–289), `::update`, `::destroy` (353–416)
* **Tables:** `stock_adjustments`, `stock_adjustment_lines`, `product_stocks`, `purchase_products`, `chart_of_accounts`, `account_statement_logs`
* **Fixtures:** FX-PRD P1 (10 units, layers 2@400 + 8@410), FX-ACC
* **Normal successful request:** adjust 3 units; update; destroy
* **Repeated identical request:** same body ×2
* **Simultaneous parallel requests:** 4 adjustments of one product; destroy ×3; adjustment ∥ sale
* **Deadlock / retry:** inject on `product_stocks` update (once/always); real deadlock on the stock row
* **Transaction rollback:** failure after layers reduced → stock, layers, lines, account unchanged
* **Balance reconciliation:** adjustment/loss account once
* **Ledger reconciliation:** logs; outbox/journal once; reference numbers distinct
* **Stock reconciliation:** shelf = Σ layers; FIFO oldest first; no negative
* **File / event side effects:** none
* **Existing evidence (does not verify):** `StockAdjustmentTest`, `DeadlockWorkflowRetryTest` (store once/always). Parallel/destroy: none

### W12 – Chart of accounts: opening-balance edit, updateBalance, destroy

**Status: NOT VERIFIED.**

* **Entry points:** `ChartOfAccountController::update` (141–250), `::updateBalance` (299–362), `::destroy` (251–298), `::store` (48–140)
* **Tables:** `chart_of_accounts`, `account_statement_logs`, Owner's Equity account
* **Fixtures:** FX-ACC with Owner's Equity
* **Normal successful request:** edit opening balance by +100; updateBalance +50; destroy a zero-balance account
* **Repeated identical request:** same body ×2
* **Simultaneous parallel requests:** edit ∥ 4 postings; updateBalance ×3; destroy ∥ posting
* **Deadlock / retry:** inject on the statement-log insert
* **Transaction rollback:** failure after equity adjustment → account, equity, logs unchanged
* **Balance reconciliation:** final balance = old + difference + postings; Owner's Equity adjusted once
* **Ledger reconciliation:** opening-balance log and equity log once
* **Stock reconciliation:** n/a
* **File / event side effects:** none
* **Existing evidence (does not verify):** `InventoryV2ConcurrencyTest` touches accounts indirectly; no controller test

### W13 – POS order

**Status: NOT VERIFIED.**

* **Entry points:** `PosController::order_store` → `PosUtility::orderStore` (697–1160); route `pos-order` (`retry.deadlock`)
* **Tables:** `orders`, `order_details`, `combined_orders`, `product_stocks`, `purchase_products`, `chart_of_accounts`, `account_statement_logs`, `carts`
* **Fixtures:** FX-PRD P3 (6 units @400), FX-ACC cash C1, POS carts for A1–A4, flag OFF and ON
* **Normal successful request:** POS sale of 2 units paid in cash
* **Repeated identical request:** same submission ×2
* **Simultaneous parallel requests:** 4 POS orders of 2 units on 6 → 3 win; double submit ×3
* **Deadlock / retry:** inject on `product_stocks` update (exists for storefront); real deadlock on stock row
* **Transaction rollback:** failure after payment accounts credited → order, stock, layers, accounts unchanged
* **Balance reconciliation:** cash +total once; AR, Sales, COGS, Inventory legs exact
* **Ledger reconciliation:** logs; (flag ON) outbox/journal once
* **Stock reconciliation:** shelf = layers; cost per layer exact; no oversell
* **File / event side effects:** none (no SMS in POS path [A])
* **Existing evidence (does not verify):** `PosSaleTest`, `LegacyFlowsJournalTest`, `OrderChannelMatrixTest`, `DeadlockMiddlewareTest`: sequential; no parallel POS test

### W14 – Order edit / cancel / status

**Status: NOT VERIFIED.**

* **Entry points:** `OrderController::orderEditUpdate` (2483–2727), `::orderEditDelete` (2984–3012), `::updateItemsStatus` (108–158), `::processUpdateItemsStatus` (3235–3444), `::update_delivery_status` (1663–1963, no TX), `::bulkAssignCourier` (853–899, no TX), `::destroy` (1609–1639, no TX), `set_order_line_status()`
* **Tables:** `orders`, `order_details`, `product_stocks`, `purchase_products`, `account_statement_logs`, `chart_of_accounts`, v2: `order_demands`, `stock_movements`
* **Fixtures:** FX-DLV orders in each status, FX-PRD P1, FX-ACC
* **Normal successful request:** edit quantity; delete a line; change status; cancel; bulk assign courier (courier API faked)
* **Repeated identical request:** cancel ×2; status change ×2
* **Simultaneous parallel requests:** cancel ×3; cancel ∥ settle; two edits of the same order; bulk assign ∥ status change
* **Deadlock / retry:** inject on `product_stocks` update for transactional methods; for the no-transaction methods an exception after each write (documents the partial state)
* **Transaction rollback:** transactional: unchanged world; no-transaction methods: the test first records the partial state as a characterization
* **Balance reconciliation:** AR/COGS reversed once; stock restocked once
* **Ledger reconciliation:** reversal logs once; outbox once
* **Stock reconciliation:** restock once; layers restored to the right layer; never above purchased quantity
* **File / event side effects:** notifications/SMS/courier calls counted: once
* **Existing evidence (does not verify):** `OrderEditTest`, `CancelPathsTest`, `ConfirmedOrderStockTest`: sequential and single-request

### W15 – Refund admin screen over HTTP

**Status: NOT VERIFIED.**

* **Entry points:** `RefundRequestController::refund_pay` (193–260) → `RefundService::record`
* **Tables:** `refund_requests`, `refund_postings`, `wallets`, `chart_of_accounts`, `journal_entries/lines`
* **Fixtures:** FX-ACC, wallet customer, refund request approved, flag ON
* **Normal successful request:** pay a refund (wallet, cash, gateway)
* **Repeated identical request:** same refund ×2
* **Simultaneous parallel requests:** 3 admins pay the same refund over HTTP
* **Deadlock / retry:** inject on `journal_entries` insert; service-level real deadlock exists
* **Transaction rollback:** failure after wallet credit → no posting, wallet unchanged
* **Balance reconciliation:** wallet/cash/gateway once
* **Ledger reconciliation:** `refund_postings` unique key once; journal once
* **Stock reconciliation:** n/a
* **File / event side effects:** none
* **Existing evidence (does not verify):** `RefundVerificationTest` (parallel at service/controller-method level, executed in the concurrency suite), `RefundAccountingTest`; HTTP-kernel parallel test: none

### W16 – Console jobs

**Status: NOT VERIFIED.**

* **Entry points:** `AutoDepositFunds` (`accounts:auto-deposit`, scheduled 00:01), `CleanupStatementLogs` (`--apply`), `ReverseSettlement`, `FixPartialSettlements`, `FixPosCourierIncome`; scheduled-but-missing `accounts:import-customer-payments`
* **Tables:** `balance_transfers`, `chart_of_accounts`, `account_statement_logs`, `courier_settlements`, `orders`
* **Fixtures:** FX-ACC, settled orders, deposit rules; a command-runner worker (`command_as`, to be written)
* **Normal successful request:** run each command once (dry-run where available)
* **Repeated identical request:** run twice in a row
* **Simultaneous parallel requests:** run while 4 HTTP postings hit the same account
* **Deadlock / retry:** inject where the command opens a transaction
* **Transaction rollback:** exception mid-run → no partial effect
* **Balance reconciliation:** no lost update on shared accounts; absolute setters refuse outside maintenance mode (decision 8)
* **Ledger reconciliation:** numbers distinct (shared generators)
* **Stock reconciliation:** n/a
* **File / event side effects:** none
* **Existing evidence (does not verify):** none

### W17 – Document number generators

**Status: NOT VERIFIED.**

* **Entry points:** `CourierSettlement::createNumbered`, `SupplierPayment::createNumbered`, `StockAdjustment::generateReferenceNo`, `ExpensePayment::generateVoucherNo`, `BalanceTransfer::generateVoucherNo`, `Voucher::generateVoucherNo` + `VoucherController::generateVoucherNo`, `CustomerPayment::generatePaymentNo`, `Income::generateVoucherNo`, `CostCenter::generateCode`, legacy purchase `Ref-N`, `PR-<time>`, PO reference, `Order::generateUniqueCode`
* **Tables:** all document tables with number columns
* **Fixtures:** fixtures of workflows 3, 8–11, orders
* **Normal successful request:** one document of each type: format matches
* **Repeated identical request:** same token twice (after remediation): one number
* **Simultaneous parallel requests:** 4 simultaneous creations per type; mixed types sharing a prefix and period
* **Deadlock / retry:** inject: number consumed once on success, not consumed on `always`
* **Transaction rollback:** rolled-back attempt leaves the sequence/next number unchanged (no gap)
* **Balance reconciliation:** n/a
* **Ledger reconciliation:** distinct, formats unchanged, gap-free
* **Stock reconciliation:** n/a
* **File / event side effects:** n/a
* **Existing evidence (does not verify):** `CourierSettlementParallelTest` (4 orders), `SupplierPaymentParallelTest` (3 suppliers) cover two generators; the others: none

### W18 – Early-return methods (F-03)

**Status: NOT VERIFIED.**

* **Entry points:** `CourierSettlementController::settle` (returns at 233, 241, 253, 264, 281, 391), `::unsettle` (1141), `SupplierPaymentController::store` (261), `PurchaseController::purchaseUpdatePayment` (226), `PurchaseOrderController::store` (300), `::purchaseUpdatePayment` (210), `VoucherController::store` (173, 180, 184, 207, 213), `::update` (501, 508, 512, 535, 541), `SupplierController::processPayment` (52), `OrderController::fixAllPayment` (170)
* **Tables:** the rows each method locks
* **Fixtures:** fixtures that trigger every refusal branch
* **Normal successful request:** refusal returns the documented message
* **Repeated identical request:** same refused request ×2
* **Simultaneous parallel requests:** refused request ∥ valid request on the same rows: the valid one is not delayed beyond a bound
* **Deadlock / retry:** n/a
* **Transaction rollback:** every refusal path: `DB::transactionLevel()` equals its entry value at response time and a second connection's `lockForUpdate` on the same rows returns immediately
* **Balance reconciliation:** world unchanged after a refusal
* **Ledger reconciliation:** n/a
* **Stock reconciliation:** n/a
* **File / event side effects:** n/a
* **Existing evidence (does not verify):** none (static scan only)

### W19 – Repeated-order protection (D6) and checkout

**Status: NOT VERIFIED.** Known defect: **FAILED for the lock footprint (P0-1: measured timeout between different users)**

* **Entry points:** `CheckoutController::checkout` (152–247) → `OrderController::store` (1231–1508); `Api\V2\OrderController::store` (139–); admin route `POST /admin/orders`
* **Tables:** `carts`, `addresses`, `combined_orders`, `orders`, `order_details`, `product_stocks`, `purchase_products`, `coupon_usages`, `drafts` (MyISAM)
* **Fixtures:** FX-PRD P1/P3, FX-CART carts + addresses A/B + coupon, customers U1–U4, flag OFF
* **Normal successful request:** place an order from a cart; cart consumed; stock taken once
* **Repeated identical request:** same submission ×2 (same cart rows); identical products re-added after the first order (new cart rows) must create a **new** order; changed address/payment/coupon on a second submit must not be silently answered with the first order
* **Simultaneous parallel requests:** same cart ×4; 4 users for the last units; **two different users' carts at once must not block each other (P0-1)**
* **Deadlock / retry:** inject on `product_stocks` update; real deadlock on stock row
* **Transaction rollback:** failure after the cart delete → cart intact, no order
* **Balance reconciliation:** stock/FIFO once; coupon usage once
* **Ledger reconciliation:** order rows and totals
* **Stock reconciliation:** shelf = layers; no oversell
* **File / event side effects:** draft delete not transactional (MyISAM)
* **Existing evidence (does not verify):** `AdminOrderParallelTest` (admin route only: same cart ×4 → one order; five admins for the last units), `StorefrontCheckoutTest`, `OrderChannelMatrixTest`, `DeadlockMiddlewareTest`, `RealDeadlockHttpTest` (admin order). Not covered: the storefront `CheckoutController` path, API v2 duplicates, user-to-user lock blocking

### W20 – Real deadlock on purchase update/delete, supplier payment, voucher, customer payment

**Status: NOT VERIFIED.**

* **Entry points:** as workflows 3, 4, 8, 9
* **Tables:** as above
* **Fixtures:** FX-X helper action `lock_then_lock` (exists in `tests/Concurrency/worker.php`) with a heavy helper (many touched rows) so that the request is the victim
* **Normal successful request:** –
* **Repeated identical request:** –
* **Simultaneous parallel requests:** –
* **Deadlock / retry:** helper-process cycle per workflow with `pause_after` window; assert `X-Deadlock-Retries` present (for JSON-error controllers) and the helper completes
* **Transaction rollback:** –
* **Balance reconciliation:** once-only effects after the retry
* **Ledger reconciliation:** once-only effects
* **Stock reconciliation:** as the underlying workflow
* **File / event side effects:** as the underlying workflow
* **Existing evidence (does not verify):** `RealDeadlockHttpTest`: courier settlement, legacy purchase **store**, admin order – 3 tests passing. Flash+redirect controllers are expected to FAIL this test (P0-2)

### W21 – File effects (F-02)

**Status: NOT VERIFIED.** Known defect: **FAILED by code reading (files deleted/stored inside the transaction)**

* **Entry points:** `PurchaseController::storePurchaseReturn` (1354), `::updatePurchaseReturn` (1896–1899), `::purchaseDelete` (2070–2071); already protected: `::store` (347), `::update` (573–580), `SupplierPaymentController::store|update|destroy`, `PurchaseOrderController::store|update`
* **Tables:** files on the `public` disk (`purchases/`, `purchase_return_documents/`, `documents/`, `supplier-payments/`), the owning rows
* **Fixtures:** FX-FILES: `Storage::fake('public')` for in-process tests; a real temp disk for parallel tests
* **Normal successful request:** upload stored once, row points to it
* **Repeated identical request:** same request token ×2: one file
* **Simultaneous parallel requests:** parallel stores with uploads: one file per committed document
* **Deadlock / retry:** `DeadlockInjector::once`: exactly one file; `::always`: zero new files and old files intact
* **Transaction rollback:** final failure → files equal pre-request state
* **Balance reconciliation:** n/a
* **Ledger reconciliation:** n/a
* **Stock reconciliation:** n/a
* **File / event side effects:** file count and paths; after-commit deletes only; reconciler behaviour (killed process, failed delete)
* **Existing evidence (does not verify):** `DeadlockWorkflowRetryTest` (legacy purchase upload, update keeps old document, supplier payment attachments), `DeadlockSideEffectTest`. The three F-02 methods: none

## 4. New test code needed (not written)
`HttpWorkflowCase` helpers: `worldFingerprint()`, `assertLedgerExactlyOnce()`, `assertNoOpenTransaction()`, `assertFilesEqual()`; worker action `command_as` (W16); fixtures builders FX-DLV variants, FX-PUR, FX-CART; architecture tests (static scans for `return` inside transactions and file operations inside transactions) used by W18/W21; the characterization suite of today's overdraft, supplier-due and update-revert rules (plan phase P0) which must pass on unchanged code before any remediation.

## 5. Completion rule
A workflow is promoted from NOT VERIFIED only when (1) every applicable bullet above exists as an executable test, (2) all of them pass in a recorded run on a disposable database, (3) the full PHPUnit and concurrency suites pass one after the other, and (4) test-database and staging fingerprints are unchanged. Until then this document verifies nothing.
