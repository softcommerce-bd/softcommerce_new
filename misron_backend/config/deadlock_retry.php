<?php

/*
| Routes that get the bounded deadlock retry (App\Http\Middleware\RetryDeadlockedCheckout, max 3 attempts), attached to every non-GET route of
| these controllers (or of the listed methods only). A method belongs here only if it is a single database transaction with no external
| call, notification or one-off file effect that is not made once per request (RequestSideEffects).
|
| Deliberately NOT listed: PurchaseOrderController@convertToPurchase (calls PurchaseController::store, which commits on its own, then updates the order outside any transaction: a repeat after a failure creates a second purchase) and CustomerPaymentController@autoImport (one commit per customer in a loop, errors swallowed so a deadlock is never seen); both need a transaction redesign, see docs/T5_DEADLOCK_RETRY_VERIFICATION_REPORT.md section 0.4.
|
| Also NOT listed: PurchasingController (every write already retries inside Idempotency::once: a second layer would exceed three
| attempts), OrderController status/payment updates (they send notifications and SMS after saving), anything that calls a courier or payment API.
*/
return [
    'controllers' => [
        \App\Http\Controllers\PurchaseController::class => '*',
        \App\Http\Controllers\PurchaseOrderController::class => ['store', 'update', 'destroy', 'purchaseUpdatePayment'], // not 'convert': it commits a purchase and then updates the order in separate steps
        \App\Http\Controllers\SupplierPaymentController::class => '*',
        \App\Http\Controllers\CustomerPaymentController::class => ['store', 'destroy'], // not 'autoImport': one commit per customer in a loop
        \App\Http\Controllers\StockAdjustmentController::class => ['store', 'update', 'destroy'],
        \App\Http\Controllers\CourierSettlementController::class => ['settle', 'unsettle'],
        \App\Http\Controllers\OrderController::class => ['store', 'updateItemsStatus', 'processUpdateItemsStatus', 'orderEditUpdate', 'orderEditDelete', 'updateAddress'],
        \App\Http\Controllers\SupplierController::class => ['processPayment'],
        \App\Http\Controllers\VoucherController::class => '*',
        \App\Http\Controllers\ExpensePaymentController::class => '*',
        \App\Http\Controllers\IncomeController::class => '*',
        \App\Http\Controllers\BankTransactionController::class => '*',
        \App\Http\Controllers\BalanceTransferController::class => '*',
    ],
];
