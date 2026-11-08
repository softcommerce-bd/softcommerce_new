> **Owner decision B-1.1 (approved): the supported mechanism is the balance-transfer workflow shown in section 1 (LIVE). The PROPOSAL sections are history and not to be implemented.**

# B-1: complete accounting example for the four bank actions (decision support - nothing is enabled)

**The four bank actions remain disabled.** Two kinds of statement are separated below: **[LIVE]** = recorded by running the same movements through the working balance-transfer screen on synthetic accounts (`tests/Feature/Inventory/BankReferenceExampleTest.php`, output in `docs/evidence/final6/b1_reference_run.json`); **[PROPOSAL]** = what the bank screens would do if they followed that live behaviour; it is not implemented and **needs your/the accountant's approval**.

## 1. Worked figures [LIVE] (accounts: Cash in Hand 1,000; Bank A 5,000; Bank B 200)

| # | Action (live equivalent) | Number | Debit account | Credit account | Amount / charge | Balances after (Cash, Bank A, Bank B) |
|---|---|---|---|---|---|---|
| 0 | start | | | | | 1,000 / 5,000 / 200 |
| 1 | **Deposit** cash -> Bank A | `DP-26100001` | Bank A 300 | Cash in Hand 300 | 300 / 0 | 700 / 5,300 / 200 |
| 2 | **Withdrawal** Bank A -> cash | `WD-26100001` | Cash in Hand 100 | Bank A **105** | 100 / charge 5 | 800 / 5,195 / 200 |
| 3 | **Transfer** Bank A -> Bank B | `TR-26100001` | Bank B 400 | Bank A **410** | 400 / charge 10 | 800 / 4,785 / 600 |
| 4 | **Cheque clearing** | no live equivalent | see 3 | see 3 | | |
| R | **Reversal** = delete 3, 2, 1 | `TR-26100001-DEL`, `WD-26100001-DEL`, `DP-26100001-DEL` | mirror lines | mirror lines | | 1,000 / 5,000 / 200 (exactly the start) |

Audit history [LIVE]: each posting writes two statement lines (source credited with amount + charge, destination debited with amount); a delete writes two mirror lines with the original date and reference `<number>-DEL`, restores both balances, and **soft-deletes** the document (3 rows kept, 0 visible). The reversal carries no actor or reason column; who deleted it exists only in the application log. An edit would write `<number>-REV` lines (same pattern).

### Two live facts the owner should see before approving anything
1. **Bank charges are one-sided.** In steps 2 and 3 the source account is credited for amount **+ charge** (105, 410) but no account is debited for the 5 and 10: the statement lines do not net to zero and no expense account is posted. The balances are consistent with each other (Bank A fell by the full 105/410), the missing piece is where the charge is booked as an expense. This is how the working screen behaves today; I changed nothing. **[DECISION A]** which account receives the bank charge.
2. **Deposit/withdraw/transfer here always name both accounts.** The bank screens' deposit, withdrawal and cheque forms name **one** account only (`account_id`). The other side (cash in hand? a suspense account? a customer?) is **not specified anywhere**. **[DECISION B]** the counter-account of each action.

## 2. The four actions [PROPOSAL, all marked as needing approval]

| Action | Bank screen input | Proposed debit | Proposed credit | Balance impact | Reversal | Needs |
|---|---|---|---|---|---|---|
| Deposit | bank account, amount | the chosen bank account | **[B] counter account** (live equivalent: Cash in Hand) | bank + amount, counter - amount | mirror lines, `-DEL`, document kept | B |
| Withdrawal (and cash-out, which today simply calls withdrawal) | bank account, amount | **[B] counter account** (live: Cash in Hand) | the chosen bank account (amount + charge) | bank - (amount + charge) | mirror lines | A, B; are withdrawal and cash-out one thing or two? the table enum has both `withdraw` and `cash_out` |
| Transfer | from, to, amount | to-account | from-account (amount + charge) | from - (amount + charge), to + amount | mirror lines | A |
| Cheque clearing | bank account, cheque no/dates, amount | the bank account at the **clearing date** | **[C]** the account that held the cheque (cheques in hand? the customer's receivable?) - there is no such account in the form | bank + amount at clearing | **[D]** a cheque that bounces after clearing | C, D, and a decision on whether anything is posted when the cheque is received (before clearing) |

Overdraft rule (existing, unchanged): withdrawals/transfers are refused when the source balance is below amount (+ charge); it is re-checked under the account lock.

## 3. Numbering choices (the format is yours to choose; I invent none)
| Option | What it is | For | Against |
|---|---|---|---|
| **1 (recommended)** Do not number the bank screens: send deposit / withdrawal / transfer through the balance-transfer screen (`WD/DP/TR/CO-YYMMNNNN`, monthly reset, one sequence **per type**, never reuses a deleted number), and build only cheque clearing | zero new formats; the working, tested, concurrency-protected path; reversals and audit already exist | removes one duplicate entry path; cheque clearing still needs [C]/[D] | the four bank screens/modal would be retired or redirected |
| 2 Same pattern for the bank screens, with prefixes you choose | consistent with option 1's pattern | keeps both screens | two screens doing the same job; every prefix is a permanent identifier |
| 3 One shared sequence across all four types (e.g. one prefix, yearly) | simplest to read | single contention point; first-of-period race needs decision 7 (sequence table) | cannot tell the type from the number |

Gap-free? the working pattern never reuses a number but a rolled-back creation cannot leave a gap either; "gap-free across deletions" is not provided by any option here. Reset period (never / monthly / yearly): your choice, the existing screens use monthly (transfers) and yearly (incomes). Bank numbers generated after account locks must not use the new income lock (see `T5_INCOME_NUMBER_DEADLOCK_REPORT.md`); they need the sequence table (decision 7) for the first-of-period case.

## 4. Unresolved decisions (all of them)
A bank-charge account · B counter-account of deposit / withdrawal / cash-out · C account credited by cheque clearing and whether anything is posted at receipt · D bounced cheque after clearing · E withdrawal vs cash-out (one type or two; `cash_out` is in the table enum, the route stores `withdraw`) · F final status names (`pending` -> `completed`? `cleared`?) · G numbering option 1/2/3 and, for 2/3, prefix/width/reset · H cancellation: delete (as the transfer screen) or reversing document with reason and actor; audit table yes/no · I keep or retire the bank screens · J reactivation also needs the model, relations and posting implemented (empty shell) and the real tests of section 4 of the B-1 specification.
