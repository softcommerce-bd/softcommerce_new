# Remaining workflow coverage matrix (21 workflows)

Y = a passing test exists in that category; N = none; PART = only part of the workflow's operations; n/a = not meaningful. **Full workflow verification is claimed for none**: a workflow needs all applicable categories for all its operations. Columns: S sequential/functional, I injected-deadlock, P real parallel-process, D duplicate-submission, A accounting reconciliation (balances, journal, supplier/stock reconciliation).

| # | Workflow | S | I | P | D | A | Tests / what is missing |
|---|---|---|---|---|---|---|---|
| 1 | Courier unsettle | Y | Y | Y | Y | Y | `ReversalParallelTest` (x3, cash/COGS/journal), `DeadlockRetryMethodCoverageTest`; missing: real-deadlock helper |
| 2 | Courier settle variants | PART | PART | PART | PART | PART | full delivery only (`CourierSettlementParallelTest`, `DeadlockWorkflowRetryTest`, `RealDeadlockHttpTest`); partial, zero-shipping (2), POS variants N |
| 3 | Supplier payment store/update/destroy | Y | Y | Y | Y | Y | `SupplierPaymentEditAccountingTest` (8), `SupplierPaymentParallelTest`, `WorkflowParallelCoverageTest` (update x3, different edits, edit vs delete), `ReversalParallelTest` (delete x3), method coverage; **edit defect fixed** |
| 4 | Purchase update / delete / payment | PART | Y | PART | PART | PART | store+delete parallel and duplicate-tested; update and purchaseUpdatePayment parallel N |
| 5 | Purchase returns | Y | Y | PART | N | Y | store x4 parallel vs sequential reconciliation; update parallel N; no duplicate protection exists (repeats are separate returns) |
| 6 | Purchase orders | PART | Y | PART | N | N | store x4 parallel; update parallel N; no duplicate protection (reference check points at the wrong table); no accounting assertions |
| 7 | Supplier edit/delete/processPayment | PART | PART | PART | PART | PART | processPayment x3 complete; **supplier edit/delete N** (unlocked write, decision 4) |
| 8 | Customer payment | Y | Y | PART | Y | Y | store x3 same order reconciled; destroy parallel N |
| 9 | Voucher | Y | Y | PART | PART | Y | store x4 and destroy x3 parallel; update parallel N |
| 10 | Expense/income/bank/transfer | Y | PART | Y | PART | Y | incomes x4, expense/income/transfer destroy x3, opposite transfers, overdraft races; **bank actions N (BLOCKED)**; updates parallel N |
| 11 | Stock adjustment | Y | Y | PART | N | Y | store x4 parallel (shelf = layers, numbers); destroy parallel N |
| 12 | Chart of accounts | N | N | PART | N | Y | adjust-balance x4 parallel only; opening-balance edit, account destroy N |
| 13 | POS order | Y | Y | Y | Y | Y | `PosAndConsoleParallelTest` (4 sales for 6 units reconcile like sequential, same cart x3 = one sale), `PosSaleTest`, `DeadlockMiddlewareTest`; **two POS defects fixed (cart wipe, no duplicate guard)** |
| 14 | Order edit/cancel/status | Y | PART | PART | PART | PART | cancel x3 parallel; edit/delete parallel N; `update_delivery_status`, `bulkAssignCourier`, `OrderController::destroy` have no transaction (N) |
| 15 | Refund | Y | PART | PART | Y | Y | controller-method parallel (`RefundVerificationTest`); HTTP-kernel parallel N |
| 16 | Console jobs | N | N | PART | PART | PART | `accounts:auto-deposit` x2 race and alongside a transfer; **job fails on the unmodified schema (created_by NOT NULL)**; `accounts:cleanup-statement-logs`, `ReverseSettlement`, `FixPartialSettlements`, `FixPosCourierIncome` N |
| 17 | Document numbers | Y | PART | PART | n/a | n/a | incomes/vouchers/adjustments/transfers/settlements/supplier payments parallel; expense, customer payment, cost center, `Ref-N`, `PR-time`, PO reference, order code N |
| 18 | Early returns | Y | n/a | N | N | n/a | `TransactionExitTest`, static architecture test |
| 19 | Checkout / repeated order | Y | Y | Y | Y | Y | `CartLockingParallelTest`, `AdminOrderParallelTest`, `StockConcurrencyTest`; storefront `CheckoutController` path itself N |
| 20 | Real deadlock | n/a | n/a | PART | n/a | Y | courier settle, legacy purchase, admin order; voucher, customer payment, supplier payment N |
| 21 | File effects | Y | Y | N | N | n/a | `DeadlockFileEffectsTest`; parallel uploads N |

**Reading:** workflows with Y in all five categories for every operation: none. Closest: 1, 3, 13, 19.

## UPDATE 3
- POS: added `PosCartIsolationTest` (own cart rows only; website cart untouched) — P-1 approved.
- Bank actions: `BankActionsStayDisabledTest` enforces disabled state pending B-1.
