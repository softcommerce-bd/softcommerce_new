# T-5 handover package - start here

Commit of record for the engineering work: `98339a1`; this handover commit adds documentation, scripts and evidence only (application code untouched). **T-5 is technically complete. Staging acceptance, production acceptance and T-5 closure are NOT claimed.** All financial flags stay disabled (`INVENTORY_CONFIRMED_ONLY=false`, `AUTO_DEPOSIT_APPROVED` unset, `FINANCIAL_ENGINE_POLICY=enforce`); no production or staging system was touched.

| You are | Read first | Then |
|---|---|---|
| **Owner** | `T5_OWNER_DECISION_REQUEST.md` | `T5_FINAL_COMPLETION_REPORT.md` (matrix), `T5_HANDOVER_DEPENDENCY_TRACKER.md` |
| **DBA** | `T5_DBA_INFORMATION_REQUEST.md` | `T5_INC1_DBA_HANDOFF.md`, `T5_STAGING_EXECUTION_SEQUENCE.md`, `T5_DATABASE_MIGRATION_REPORT.md`, `T5_R01_MYISAM_INVESTIGATION.md` |
| **Accountant** | `T5_ACCOUNTANT_INFORMATION_REQUEST.md` | `T5_N1_ACCOUNTANT_REVIEW_PACKAGE.md`, `T5_N1_READONLY_AUDIT_RUNBOOK.md`, `docs/accountant-review-pack/`, `T5_B1_ACCOUNTING_EXAMPLE.md` |
| **Staging engineer** | `T5_STAGING_EXECUTION_SEQUENCE.md` | `T5_FINAL_DEPLOYMENT_RUNBOOK.md`, `docs/staging/env.staging.example`, `tests/staging/reconciliation_checks.sql`, `T5_FINAL_TEST_EVIDENCE.md`, `T5_E2E_DEFERRED_FAILURES.md` |
| **Anyone tracking progress** | `T5_HANDOVER_DEPENDENCY_TRACKER.md` (E1-E11) | `T5_CONSOLIDATED_DECISION_REGISTER.md` (UPDATE 13-14), `T5_FINAL_ACCEPTANCE_AUDIT.md` |

Verification of this package against the repository (2026-11-08): 211 path references in the T-5 documents checked (1 intentionally historical: the removed bank modal view); 12 artisan commands referenced all exist; test inventory at HEAD = 656 default + 100 concurrency = the numbers in `T5_FINAL_TEST_EVIDENCE.md` (MariaDB 10.11.14 and MySQL 8.4.13 both: 0 failures); stale earlier documents carry a status banner pointing here. Raw checks: `docs/evidence/final10/docs_check.txt`.
