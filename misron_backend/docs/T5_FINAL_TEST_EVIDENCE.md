# T-5 final test evidence

Commit under test: the HEAD of `claude/peaceful-planck-elqcki` at the time of the run (see `git log`); all runs sequential, on **disposable databases only**. Raw outputs and junit files: `docs/evidence/final9/`.

## 1. Environments
| | MariaDB | MySQL |
|---|---|---|
| Server | 10.11.14-MariaDB (local), database `inv_v2_test` | MySQL Community Server **8.4.13** (Docker image `mysql:8.4`, digest `sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902`), loopback 127.0.0.1:3307, databases `t5_mysql84_test` (dump + migrations, aligned with the clean test state), `t5_prodshape_test` (the supplied backup restored and masked, purchasing tables still MyISAM), `misron_test` |
| App | PHP 8.3.6, PHPUnit 9.6.36; frontend Node (Next.js app + Playwright) | same |
| After use | kept (standing test database) | container, volume, image, daemon and databases destroyed (`final9/teardown.txt`); local `misron_e2e_test` dropped |

## 2. Backend results (final run)
| Suite | MariaDB 10.11.14 | MySQL 8.4.13 |
|---|---|---|
| Default (Unit + Feature) | **656 tests, 4270 assertions, 0 failures, 0 errors, 5 skipped** | **656 tests, 4270 assertions, 0 failures, 0 errors, 5 skipped** |
| Concurrency (real processes) | **100 tests, 2586 assertions OK** | **100 tests, 2585 assertions OK** |
| Fingerprint + all-table row counts before/after | identical | identical (+1 `translations` row = `translate()` side effect) |
Skipped (5): 3 are `ProductionShapeEngineGateTest` (they need the backup's MyISAM shape and ran **and passed** on the backup-shaped 8.4 copy, section 4), 2 are the pre-existing skips. **The three previously "known" failing tests now pass** (details in `T5_FINAL_COMPLETION_REPORT.md`). No failing test is hidden, downgraded or excluded. The assertion count varies by 1-2 between runs of the same code (some tests loop until a condition holds).
Evidence: `final_maria_default.txt`, `final_maria_concurrency.txt`, `final_my84_default.txt`, `final_my84_concurrency.txt` (+ `_junit.xml`), `m_fp_*`, `y_fp_*`, `*_rows_*`.

## 3. Targeted evidence for this phase
| Area | Test(s) | Result |
|---|---|---|
| Engine guard + R-01 hazard (retry on MyISAM duplicates the write; the guard prevents it) | `EngineGuardTest` (7 tests, 64 assertions) | OK |
| Production shape (HTTP kernel, real purchasing routes, backup-shaped 8.4 copy, MyISAM) | `ProductionShapeEngineGateTest` (3 tests, 20 assertions) | OK: 503 `engine_gate` on purchase store / payment update / return store / supplier payment, nothing written, reads work, retry off |
| Conversion + rollback rehearsal | `db:convert-engines` on `t5_prodshape_test` | 5 tables converted in ~0.1 s, fingerprint identical; `--revert` restored MyISAM; plain-SQL script also converts (`prodshape_*`) |
| Auto-deposit readiness / pause / race | `AutoDepositReadinessTest` (4), `AutoDepositPausedGuardTest` (4), `PosAndConsoleParallelTest` (5) | OK; switched on but unprepared: exit 1, nothing posted |
| Auto-deposit ENUM schema step | `sqlupdates/t5_auto_deposit_schema.sql` apply -> readiness -> rollback on the 8.4 backup-shaped copy | OK (`auto_deposit_schema_rehearsal_my84.txt`) |
| First number of a period for expense / transfer / voucher | `WorkflowParallelCoverageTest::test_first_number_of_a_period_...` (6 rounds x 3 series) | OK; **fails 3/3 on the old code** (`t22_without_fix.txt`) |
| Supplier edit / delete | `WorkflowParallelCoverageTest` (delete x3, edit vs delete x4) | OK; old code: Accounts Payable -240 instead of -80 (`t10_without_fix.txt`) |
| Known failures | `ExampleTest`, `FunnelEditorMediaAndCatalogueTest`, `UploadSafetyTest` | all pass |
| Reconciliation SQL | `tests/staging/reconciliation_checks.sql` on both engines | runs read-only; findings are the known data items (`reconciliation_checks_*.tsv`) |

## 4. Frontend / browser
| Check | Result |
|---|---|
| `tsc -p tsconfig.funnel.json` | exit 0 |
| `eslint funnel` | exit 0 |
| `npm run test:funnel-core` (unit) | **60 passed** |
| Playwright end-to-end (funnel editor + storefront + checkout; local Laravel on the disposable `misron_e2e_test` + built Next.js; Chromium via a Chrome-channel symlink) | **79 passed, 3 failed** (10.3 min). Re-run of the failing specs: templates spec passed (flaky), **2 fail**: admin analytics page overflows 169 px on a phone; "save as template" spec times out. Both are Blade admin pages under the stand-in theme (theme not in the repository) - DEFERRED, not a money/stock path. First run (31 passed) failed because the disposable e2e database lacked `sales_funnel_v4/v5` SQL; applying them fixed it (environment setup, not code). |
| Admin/storefront browser suites in `tests/ui/*.js` (stand-in theme) | not run: need the theme bundle and a running test server; superseded for money paths by the HTTP-level suites above - NOT RUN |

## 5. Not covered (stated plainly)
Production engine/version/volume (unknown); real admin theme; any real payment/courier/SMS integration (the sandbox proxy refused ~70 outbound calls made by tests/feature code to SMS and courier hosts - the tests do not need them, but a staging server with live credentials could send real messages: use sandbox credentials only); MySQL versions other than 8.4.13; the full five test categories for every operation (`T5_REMAINING_WORKFLOW_COVERAGE_MATRIX.md`).
