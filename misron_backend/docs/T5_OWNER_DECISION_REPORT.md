# T-5 owner decision report (decision-support package)

Nothing here was deployed, merged, flag-enabled, or applied to staging/production. No historical data or balance was touched. T-5 and staging acceptance remain OPEN. All evidence below comes from the disposable `inv_v2_test` database (MariaDB 10.11.14); MySQL 8.4 was never run.

## Verification summary (final, after all changes)
| Check | Result | Evidence |
|---|---|---|
| PHPUnit (default suites) | 629 tests, 4003 assertions; only the **3 known unrelated failures** (UploadSafety svg, ExampleTest, FunnelEditorMedia attribution) | `docs/evidence/final6/full_phpunit.txt` |
| Concurrency suite | **96 tests, 2330 assertions, OK** | `final6/concurrency.txt` |
| Fingerprints, disposable DB | financial/stock fingerprint **identical** before/after; row counts of all 197 tables **identical** (no leaked test data) | `final6/fp_*.txt`, `rowcounts_*.txt` |
| Income fix, repeated | 6 income tests x 3 consecutive runs all OK; without the fix the new tests fail (19/40 and 17/40 requests deadlocked) | `final6/income_repeat_runs.txt` |
| N-1 queries | 28 assertions on synthetic data, all planted discrepancies found, clean supplier silent | `tests/Feature/Inventory/SupplierDriftQueriesTest.php` |
| B-1 reference run | deposit / withdraw / transfer + reversal recorded on the working screen | `final6/b1_reference_run.json` |
Two test-environment corrections made along the way (disposable only, documented): (1) the concurrency base classes now also clean `chat_audit_logs`, `funnel_order_attributions` and `notifications` (they leaked a few rows per run); (2) `payment_methods.id` is `tinyint unsigned` and its auto-increment reached 256 after many rolled-back runs, which made 14 funnel tests fail with "Out of range"; reset with `ALTER TABLE payment_methods AUTO_INCREMENT=1` on the empty test table (note: each full run burns about 13 ids, so ~19 runs fit before it must be reset; `final6/payment_methods_autoincrement_note.txt`).

## 1. B-1 - bank transactions (`T5_B1_ACCOUNTING_EXAMPLE.md`, `T5_B1_BANK_TRANSACTION_SPECIFICATION.md`)
* Live example on the working balance-transfer screen (Cash 1,000 / Bank A 5,000 / Bank B 200): deposit 300 `DP-26100001` (Dr Bank A 300, Cr Cash 300); withdraw 100 + charge 5 `WD-26100001` (Dr Cash 100, Cr Bank A 105); transfer 400 + charge 10 `TR-26100001` (Dr Bank B 400, Cr Bank A 410); deleting all three restores 1,000 / 5,000 / 200 with `-DEL` mirror lines and the documents soft-deleted (kept).
* Findings you must know: **bank charges are booked one-sided** (credited to the source, no expense debit); **the four bank screens name only one account** so the counter-account is unspecified; **the BankTransaction model is an empty shell**, so a number format alone cannot enable them; cheque clearing has no working equivalent.
* Recommendation: **retire the four duplicate bank actions** and use the transfer screen (no new format, tested, concurrency-protected); build cheque clearing only after the accountant specifies it. Actions stay disabled and guarded by tests.
* Choices needed: B-1.1 .. B-1.6 in the register (UPDATE 5).

## 2. AD-1 - auto-deposit (`T5_AD1_AUTO_DEPOSIT_OPTIONS.md` sections 2, 5, 6)
Side-by-side table is in section 6. Recommendation: **(c) no change now; (a) if you want it running; (b) not recommended**. The job still runs nightly and fails harmlessly per method (exit code 0, no alert). Proposed interim guard **G1**: the command prints one "paused pending AD-1" line and stops before any transaction - no schedule edit, no schema change, steps aside by itself under (a). Choices: AD-1.1, AD-1.2.

## 3. N-1 - supplier drift (`T5_SUPPLIER_PAYMENT_IMPACT_QUERIES.sql` Q1-Q10)
Synthetic suppliers (clean, opening-only, edited-with-drift, orphan allocation, missing cash line, purchase-total mismatch/over-allocation, unallocated advance, negative balance) were planted; the real SQL file was executed against them: Q2 finds the edited supplier with residual 0.00 (the 200 defect explains the whole difference); Q6 the negative balance; Q7 the 250 opening (explained, not a defect) and the -200 drift; Q8 the 90 unallocated advance and the orphan allocation of a deleted payment; Q9 both planted purchase inconsistencies; Q10 exactly the live payment with no cash line (120). Clean, edited (net cash 300) and deleted-payment (net 0) records stay silent in Q10. Limit: proves the queries find what they should on constructed data; real data may need the assumptions A1-A5 revisited. Choices: N-1.1 .. N-1.3.

## 4. Income numbering
Fix preserved. Verified: repeated rounds, creators on different accounts, a previous year's row, rollback/commit release, nested transactions, reentrancy (`T5_INCOME_NUMBER_DEADLOCK_REPORT.md` sections 6-7). **Production assumption (BLOCKED, needs DBA):** one connection per request, no multiplexing proxy, single primary, no persistent connections; if it does not hold the code behaves exactly as before the fix. Choices: INC.1, SEQ.1.

## Exactly what I need from you
Reply with the register's UPDATE 5 table filled in (at minimum: B-1.1, AD-1.1, AD-1.2, N-1.1, INC.1). Until you do: bank actions stay disabled, the schedule and schema are unchanged, no real data is queried.
