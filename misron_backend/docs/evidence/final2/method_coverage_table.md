| # | Method | Executed deadlock test (once = retried and equals a clean run; always = 3 attempts, nothing left) | Real parallel-process test | Status |
|---|---|---|---|---|
| 1 | `PurchaseController::purchaseUpdatePayment` | DeadlockRetryMethodCoverageTest purchase_update_payment (once, always) | none | **TESTED** |
| 2 | `PurchaseController::store` | DeadlockWorkflowRetryTest (once, always); RealDeadlockHttpTest (real) | PurchasingParallelTest; WorkflowParallelCoverageTest | **TESTED** |
| 3 | `PurchaseController::update` | DeadlockWorkflowRetryTest (always, document kept) | none | **TESTED** |
| 4 | `PurchaseController::storePurchaseReturn` | DeadlockFileEffectsTest (once, always) | WorkflowParallelCoverageTest (4 at once vs 4 sequential) | **TESTED** |
| 5 | `PurchaseController::updatePurchaseReturn` | DeadlockFileEffectsTest (always) | none | **TESTED** |
| 6 | `PurchaseController::purchaseDelete` | DeadlockFileEffectsTest (once, always) | ReversalParallelTest (delete x3) | **TESTED** |
| 7 | `PurchaseOrderController::purchaseUpdatePayment` | none | none | **NOT APPLICABLE**: no route reaches this method (dead code) |
| 8 | `PurchaseOrderController::store` | DeadlockRetryMethodCoverageTest po_store (once, always) | WorkflowParallelCoverageTest (4 at once) | **TESTED** |
| 9 | `PurchaseOrderController::update` | DeadlockRetryMethodCoverageTest po_update (once, always) | none | **TESTED** |
| 10 | `SupplierPaymentController::store` | DeadlockWorkflowRetryTest (once); DeadlockRetryMethodCoverageTest (setup) | SupplierPaymentParallelTest | **TESTED** |
| 11 | `SupplierPaymentController::update` | DeadlockRetryMethodCoverageTest supplier_payment_update (once, always) | WorkflowParallelCoverageTest (update x3) | **TESTED** |
| 12 | `SupplierPaymentController::destroy` | DeadlockRetryMethodCoverageTest supplier_payment_destroy (once, always) | ReversalParallelTest (delete x3) | **TESTED** |
| 13 | `CustomerPaymentController::store` | DeadlockRetryMethodCoverageTest customer_payment_store (once, always) | WorkflowParallelCoverageTest (x3 same order) | **TESTED** |
| 14 | `CustomerPaymentController::destroy` | DeadlockRetryMethodCoverageTest customer_payment_destroy (once, always) | none | **TESTED** |
| 15 | `StockAdjustmentController::store` | DeadlockWorkflowRetryTest (once, always) | WorkflowParallelCoverageTest (4 at once) | **TESTED** |
| 16 | `StockAdjustmentController::destroy` | DeadlockRetryMethodCoverageTest stock_adjustment_destroy (once, always) | none | **TESTED** |
| 17 | `CourierSettlementController::settle` | DeadlockWorkflowRetryTest (once, always); RealDeadlockHttpTest (real) | CourierSettlementParallelTest | **TESTED** |
| 18 | `CourierSettlementController::unsettle` | DeadlockRetryMethodCoverageTest courier_unsettle (once, always) | ReversalParallelTest (unsettle x3) | **TESTED** |
| 19 | `OrderController::updateItemsStatus` | none | none | **NOT APPLICABLE**: no route reaches this method (dead code) |
| 20 | `OrderController::store` | DeadlockWorkflowRetryTest; RealDeadlockHttpTest (real); DeadlockSideEffectTest | AdminOrderParallelTest; CartLockingParallelTest | **TESTED** |
| 21 | `OrderController::orderEditUpdate` | DeadlockRetryMethodCoverageTest order_edit_update (once, always) | none | **TESTED** |
| 22 | `OrderController::orderEditDelete` | DeadlockRetryMethodCoverageTest order_edit_delete (once, always) | none | **TESTED** |
| 23 | `OrderController::processUpdateItemsStatus` | DeadlockRetryMethodCoverageTest order_items_status (once, always) | none | **TESTED** |
| 24 | `SupplierController::processPayment` | DeadlockRetryMethodCoverageTest supplier_process_payment (once, always) | WorkflowParallelCoverageTest (x3) | **TESTED** |
| 25 | `VoucherController::store` | DeadlockRetryFlashCoverageTest (once, always) | WorkflowParallelCoverageTest (4 at once) | **TESTED** |
| 26 | `VoucherController::update` | DeadlockRetryMethodCoverageTest voucher_update (once, always) | none | **TESTED** |
| 27 | `VoucherController::destroy` | DeadlockRetryMethodCoverageTest voucher_destroy (once, always) | WorkflowParallelCoverageTest (delete x3) | **TESTED** |
| 28 | `ExpensePaymentController::store` | DeadlockRetryMethodCoverageTest expense_store (once, always) | OverdraftParallelTest | **TESTED** |
| 29 | `ExpensePaymentController::update` | DeadlockRetryMethodCoverageTest expense_update (once, always) | none | **TESTED** |
| 30 | `ExpensePaymentController::destroy` | DeadlockRetryMethodCoverageTest expense_destroy (once, always) | WorkflowParallelCoverageTest (delete x3) | **TESTED** |
| 31 | `IncomeController::store` | DeadlockRetryFlashCoverageTest (once, always) | WorkflowParallelCoverageTest (4 at once) | **TESTED** |
| 32 | `IncomeController::update` | DeadlockRetryMethodCoverageTest income_update (once, always) | none | **TESTED** |
| 33 | `IncomeController::destroy` | DeadlockRetryMethodCoverageTest income_destroy (once, always) | ReversalParallelTest (delete x3) | **TESTED** |
| 34 | `BankTransactionController::deposit` | none: fails before any write (`BankTransaction::generateTransactionNo()` undefined) | none | **BLOCKED** (business decision: number format) |
| 35 | `BankTransactionController::withdraw` | none: fails before any write (`BankTransaction::generateTransactionNo()` undefined) | none | **BLOCKED** (business decision: number format) |
| 36 | `BankTransactionController::transfer` | none: fails before any write (`BankTransaction::generateTransactionNo()` undefined) | none | **BLOCKED** (business decision: number format) |
| 37 | `BankTransactionController::chequeClearing` | none: fails before any write (`BankTransaction::generateTransactionNo()` undefined) | none | **BLOCKED** (business decision: number format) |
| 38 | `BalanceTransferController::store` | DeadlockRetryFlashCoverageTest (once, always) | OverdraftParallelTest; WorkflowParallelCoverageTest (opposite directions) | **TESTED** |
| 39 | `BalanceTransferController::update` | DeadlockRetryMethodCoverageTest transfer_update (once, always) | none | **TESTED** |
| 40 | `BalanceTransferController::destroy` | DeadlockRetryMethodCoverageTest transfer_destroy (once, always) | ReversalParallelTest (delete x3) | **TESTED** |