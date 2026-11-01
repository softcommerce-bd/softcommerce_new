# N-1: read-only supplier-payment investigation - runbook for an authorised accountant / DBA

**Authority:** owner decision N-1.1 (APPROVED: prepare for execution by an authorised accountant or DBA against an isolated, restored database). I (the developer/assistant) have not run, and will not run, anything against production or any real data. The queries were proven only on synthetic data (`tests/Feature/Inventory/SupplierDriftQueriesTest.php`).

## 0. Rules (do not skip)
1. Run **only** on an isolated copy restored from a backup (separate server or at least a separate database no application points at). **Never on the live database, never on staging that the team uses.**
2. Use a database account that has **SELECT only**. The script additionally opens every query in `START TRANSACTION READ ONLY` and first proves that a write is refused; it refuses to run without `N1_CONFIRM_ISOLATED_RESTORE=yes`.
3. Nothing in this runbook changes data. **No correction is authorised**: findings go to the accountant; any correction needs decision N-1.2 (procedure and approver) first, supplier by supplier, with an adjustment document, never a script.
4. Results contain ids, document numbers and money only (no names, phones, addresses, notes). Still treat the TSV files as confidential financial data.

## 1. Run
```bash
export N1_CONFIRM_ISOLATED_RESTORE=yes
tests/staging/n1_readonly_audit.sh <restored_database_name> <output_dir> --defaults-extra-file=/path/to/readonly.cnf
```
It writes `00_context.tsv` (server, version, database, user, time, SHA-256 of the query file - so the report states exactly what ran) and `Q01.tsv` ... `Q10.tsv` (`Qnn.err` if a query failed; Q5 needs the v2 journal tables and may legitimately fail on a database that lacks them). Disposable-database sample run: `docs/evidence/final7/n1_script_run.txt`.

## 2. What each query answers, and how to read it
| Query | Question | Read it as |
|---|---|---|
| Q1 | every supplier payment with edit evidence | `edits_recorded > 0` = edited at least once; `original_amount_if_edited` = first reversed amount |
| Q2 | supplier reconciliation, only suppliers with an edit or an unexplained difference | residual columns **0.00** = the edit defect alone explains the difference; anything else is something else (assumptions A1-A3) |
| Q3 | purchase allocations of edited payments | open amount per purchase |
| Q4 | statement-line trail of edited payments | what each edit reversed and re-posted |
| Q5 | journal entries linked to edited payments (v2 only) | assumption on source naming - verify |
| Q6 | negative supplier balances | a symptom, not proof of the defect |
| Q7 | opening positions next to open purchases | for "due"-type opening suppliers the unexplained amount **is the opening balance** (A5), not a defect |
| Q8 | allocation integrity | (a) payment amount vs allocated (advances are legitimately unallocated); (b) allocation rows of deleted payments (orphans from the old delete) |
| Q9 | purchase totals | net - paid - due should be 0; allocated by live payments should not exceed paid |
| Q10 | cash/bank ledger effect per payment (A4) | live payment: net cash out should equal the amount; deleted: 0.00; a difference is for review |
**Do not assume every discrepancy comes from the edit defect.** The defect leaves a signature (Q2 residual 0.00 and reversal lines); everything else needs its own explanation.

## 3. Assumptions the accountant must confirm (stated in the SQL file)
A1 an edit that changed the supplier did not drift; "sum reversed" is an upper bound. A2 `suppliers.due_amount` should equal the sum of the supplier's purchases' open amounts. A3 `paid_amount` counts supplier payments (other screens add to it too). A4 one posting line per live payment on its payment account. A5 opening-balance storage as described. If production's data contradicts one, say so in the report rather than adjusting the query.

## 4. Report back (template)
Database restored from (backup date/ID) · who ran it · `00_context.tsv` · per query: row count and the accountant's classification (explained by the edit defect / opening balance / other / unknown) · suppliers needing follow-up · **no corrective action taken**. Then decide N-1.2 and N-1.3.
