> **SUPERSEDED by owner decision B-1.1 (UPDATE 6 in the register): the four bank actions are RETIRED, not specified for revival; cheque clearing is not to be implemented. This document is kept as history and only applies if the bank screens are ever revived.**

# B-1: Bank transaction workflows - implementation-ready specification (NOT approved; the actions stay DISABLED)

**Status: PENDING OWNER/ACCOUNTANT APPROVAL.** Nothing in this document is implemented. No numbering format, accounting entry or policy has been invented: every cell that needs a business answer is marked **[DECISION]**. Facts below were read from the repository and the local disposable schema on this branch.

## 1. What exists today (verified)

| Item | Fact |
|---|---|
| Controller | `app/Http/Controllers/BankTransactionController.php`: `index`, `getAccounts`, `deposit` (l.41), `withdraw` (l.91), `transfer` (l.156), `cashOut` (l.223, simply calls `withdraw`), `chequeClearing` (l.228), `show`, `export` (stub "coming soon") |
| Routes | `routes/admin.php` l.318-328, prefix `accounts/bank-transactions`, names `accounts.bank-transactions.{index,accounts,deposit,withdraw,transfer,cash-out,cheque-clearing,show,export}` |
| Screens | `resources/views/backend/accounts/bank_operations.blade.php` (list + forms, posts by AJAX to deposit/withdraw/transfer/cheque-clearing) and `resources/views/backend/accounts/modals/bank_modal.blade.php` (the same four posts; included by `accounts/layout.blade.php` l.165, so the modal is reachable from every accounts page) |
| Model | `app/Models/BankTransaction.php` is an **empty shell**: no `$fillable`, no `SoftDeletes`, no `generateTransactionNo()`, no `complete()`, no `fromAccount()/toAccount()/creator()/voucher()` relations. All of these are called by the controller. |
| Table | `bank_transactions` exists in the local schemas only (its CREATE is not in the repository): `transaction_no varchar(191) NOT NULL UNIQUE`; `type enum('deposit','withdraw','transfer','cash_out','cheque_clearing')`; `status enum('pending','completed','cancelled','cleared')`; `from_account_id`, `to_account_id`, `amount decimal(15,2)`, cheque fields, `voucher_id`, `created_by NOT NULL`, `deleted_at`. Empty everywhere checked. |
| Mismatches inside the code itself | the cash-out route stores type `withdraw` (enum also has `cash_out`); deposit/withdraw/transfer create `status='pending'` then call `complete()`; cheque clearing creates `cleared`. Which statuses are final is **[DECISION]**. |
| Current behaviour | each action validates, then fails with HTTP 500 mentioning `generateTransactionNo`; nothing is written (pinned by `tests/Feature/Inventory/BankActionsStayDisabledTest`, 2 tests). The list screen only loads because the table is empty (eager-loading undefined relations is never reached with zero rows) - with the first row it would fail. |
| Production | whether the table, rows, or a different schema exist in production is **BLOCKED**. |

**Consequence:** approving a number format is necessary but **not sufficient**. Enabling needs the model, the posting logic and the relations as well. This was not previously visible because only the missing generator was reported.

## 2. Numbering specification to be approved **[DECISION - Accounting]**

| Action | `type` | Prefix | Width | Reset (never / monthly / yearly) | Shares a sequence with | Example |
|---|---|---|---|---|---|---|
| Deposit | `deposit` | ? | ? | ? | ? | ? |
| Withdrawal | `withdraw` | ? | ? | ? | ? | ? |
| Transfer | `transfer` | ? | ? | ? | ? | ? |
| Cash-out (today = withdrawal) | `cash_out` or `withdraw`? | ? | ? | ? | ? | ? |
| Cheque clearing | `cheque_clearing` | ? | ? | ? | ? | ? |

Questions that change the design: (1) one shared sequence for all types, or one per type (a shared one is a single contention point; per type spreads it); (2) must numbers be gap-free (a rolled-back creation leaves no gap with the "locked latest row + 1" pattern, a database sequence would); (3) may the number of a deleted/cancelled document be reused (sibling generators use `withTrashed()` and never reuse); (4) should these screens exist at all, or should bank operations go through the balance-transfer screen, which already numbers `WD/DP/TR/CO/AD-YYMMNNNN` (**[DECISION - Management]**).
Existing sibling patterns that the answers could simply reuse (no invention): `BalanceTransfer::generateVoucherNo` (`PREFIX-YYMM` + 4 digits, monthly reset), `Income` (`INC-YY` + 6 digits, yearly), `ExpensePayment`, `Voucher`.

## 3. Technical requirements (independent of the format; not decisions)

**Duplicate prevention.** `transaction_no` is already UNIQUE, so a duplicate number is rejected by the database, but a collision would surface as an error to the user, not as a retry. A double click / repeated submission is a different problem: it creates two real documents with two numbers. Needs a client token in `idempotency_keys` (scope `bank_transaction`), the pattern already used for checkout and legacy purchase; the token wording/retention is part of register decisions 1 and 5.

**Concurrency.** Order of locks inside the single transaction: (1) account rows in ascending id (transfer locks both; use `BalanceTransferController::lockAccountsAscending`), (2) number generation, (3) document insert, (4) posting. The overdraft rule is re-checked under the account lock (the controller already does this for withdraw/transfer). **First-document-of-period race:** the "locked latest row + 1" read deadlocks when the period is empty (see `T5_INCOME_NUMBER_DEADLOCK_REPORT.md`); `NumberSerializer` fixes it only for series reached before any other row lock. Bank numbers are generated after the account locks, so they must use either the sequence table (decision 7) or a redesigned order; the serializer must NOT be used as-is here (lock-order inversion with the account locks).

**Journal posting and reversal.** The debit/credit treatment of each type is an **accounting decision [DECISION - Accountant]** and is not inferred here. What is known: `complete()` must update `chart_of_accounts.current_balance` through the existing D3 delta path, write the account statement lines and the legacy-ledger/journal entry the way `BalanceTransferController` does, store the voucher id on the row, and be atomic with the document insert. For each type the approval must state: debit account, credit account, whether cheque clearing moves money at deposit time or only at `clearing_date`, bank charges, and foreign/other-currency cases (assumed none).

**Audit history and deletion restrictions.** The table has `deleted_at` and a `cancelled` status but no route deletes or cancels. Proposed (to be approved): no hard delete; no edit after `completed`; cancellation only by a reversing document with its own number, linked to the original, never by soft delete (a soft-deleted row would leave its postings in place). Who may reverse, and whether a reversal needs a reason: **[DECISION]**. Any audit table is a schema addition and needs approval (cf. register decision 11/N-1(c)).

## 4. Work to do after approval (with the tests that will be written)
1. `BankTransaction` model: `$fillable`, `SoftDeletes`, relations, `generateTransactionNo()` per the approved table, `complete()` per the approved posting.
2. Idempotency token on the four forms (both screens) and in the controller.
3. Tests, matching the other workflows: sequential (each type, balances, statement lines, voucher link, overdraft refusal); numbering (first of period, month/year rollover per the approved reset, deleted-number non-reuse, shared vs per-type sequence); injected deadlock (extend `DeadlockRetryMethodCoverageTest` - the four methods are currently marked BLOCKED); real parallel processes (4 at once same type, mixed types, same account, transfer A->B and B->A, repeated rounds from an empty period); duplicate submission; journal settles and supplier/bank reconciliation (`assertJournalSettles`); list screen with real rows.
4. Replace `BankActionsStayDisabledTest` with the real tests; flip nothing else.

## 5. Approval needed
The completed table in section 2 and answers (1)-(4); the posting table of section 3 signed by the accountant; yes/no on the cancellation rule; yes/no on an audit table. Until then the four actions (and cash-out) remain failing-closed and the test above guards that.
