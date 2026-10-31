# Staging activation: prerequisites and unresolved issues

**Status: WAITING. `INVENTORY_CONFIRMED_ONLY=false` everywhere. Staging activation needs the owner's explicit approval after this table is shown and every row is satisfied.** Nothing here is complete unless it says DONE with evidence. Last updated after the owner's review of the investigation report.

## Prerequisites
| # | Prerequisite | Status | Evidence / what is missing |
|---|---|---|---|
| P1 | Production engine and exact version confirmed | **BLOCKED** | Needs the output of `sqlupdates/inventory_v2_environment_check.sql` from production. Sandbox is MariaDB 10.11; dump header says 8.4.3 (not proof). |
| P2 | Fresh production backup received, checksum recorded | **BLOCKED** | Runbook: `docs/PRODUCTION_BACKUP_REHEARSAL_RUNBOOK.md`. Only the repository dump of 23 Oct 2026 exists. |
| P3 | Real-engine rehearsal (InnoDB conversion, artisan + SQL, parity, rollback on a separate disposable copy) on the masked backup | **BLOCKED by P1, P2** | Tooling ready: `tests/staging/prepare-staging.sh`, `tests/staging/rehearse.sh`. Last run (MariaDB, repo dump): parity identical, all differences explained, rollback verified. |
| P4 | Real admin theme deployed; CSS/JS errors resolved; screens re-verified on desktop and mobile | **BLOCKED** | Theme package not supplied. The 7 sweep failures (DataTable, select2, script order, All Orders mobile) cannot be judged without it. |
| P5 | Accountant review of `docs/accountant-review-pack/` with the sign-off table completed | **BLOCKED** | No accountant has reviewed. Rulings needed on D-01 to D-09. |
| P6 | Owner's explicit approval to enable the flag on staging | **NOT GIVEN** | Required after this table is shown. |
| P7 | Permissions granted on staging (`view_financial_reports`, `manage_accounting`, order-status permissions) and tested with real staff roles | NOT STARTED | Needs the real roles from the restored backup. |
| P8 | `accounting:baseline` taken immediately before activation; `ANALYZE TABLE` run after conversion | NOT STARTED | Part of activation; only after P6. |

## Unresolved issues (nothing corrected; see `docs/accountant-review-pack/08_source_and_impact_D01_D09.md`)
| ID | Issue | Amount | Needs |
|---|---|---|---|
| D-01 | Trial balance out of balance | 301.75 (courier expense 353.95 vs income 52.20, no counter-entries) | Accountant ruling |
| D-02 | Balance sheet used a Retained Earnings plug | 301.75 (now shown as UNRECONCILED DIFFERENCE; books unchanged) | Accountant ruling |
| D-03 | P&L (statement rows) disagrees with account balances; no sales/COGS booked | 52.20 / 353.95 | Accountant ruling on the authoritative basis |
| D-04 | Inventory account vs FIFO layers | 11,255.00 (48 consumed units, never relieved) | Explain or adjust |
| D-05 | Units on the shelf with no cost | 94,260 units (83,200 in 416 variants with no layer, 11,060 above layers) | Cost evidence per product or a stock count |
| D-06 | Cost layers of deleted products | 16,000 units, BDT 3,200,000 (purchases Ref-1040, Ref-1041; also inside Payables) | Do the goods exist? Reverse or restore? |
| D-07 | Supplier payment PAY-000001 for non-existent supplier 3, allocated to purchase 11 | 5,150.00 | Was cash paid? |
| D-08 | One supplier carries 99.99% of payables; looks like opening stock typed in as purchases | 147,081,280.00 | Is the debt real? |
| D-09 | Statement rows with no transaction type | 105 rows | Accept? |
| T-1 | Deadlocks / stale statistics | operational | **UNRESOLVED.** Dev work done (lookup-index migration, preflight refusal, `inventory:db-health`, retry in service transactions; see `docs/TECHNICAL_RISKS_BEFORE_ACTIVATION.md`). The original failure was not reproduced; real-engine proof missing. |
| T-2 | Wallet-only refunds | design | **UNRESOLVED.** Treatment defined and implemented (`RefundService`, duplicate-proof `refund_pay`); needs accountant acceptance; wallet top-ups/spending still not journalled; no cash/gateway refund screens. |
| T-3 | Historical uncosted order lines | design | **UNRESOLVED.** Reporting policy implemented (`docs/HISTORICAL_ORDER_COSTS.md`); accountant must accept; no historical line is finalized or rewritten. |
| T-4 | Journal posting after the legacy commit | design | **UNRESOLVED.** Transactional outbox + `ledger:process-outbox` (scheduled) implemented with crash-recovery tests; needs real-engine rehearsal, scheduler running, load test. |

## Rules in force
No automatic inventory or accounting correction. No staging flag. No deploy, merge or production change. Existing branch `claude/peaceful-planck-elqcki` only.
| T-5 | Deadlock retry gaps (admin create-order, chat orders, courier settlement, purchase screens) | operational | **Implemented in the isolated test environment, NOT accepted.** Bounded 3-attempt retry added to admin create-order, chat orders, courier settlement and purchase/payment/adjustment screens; limitations (MariaDB only, injected deadlocks for admin/courier/purchase, excluded multi-commit and notifying paths) in `docs/T5_DEADLOCK_RETRY_VERIFICATION_REPORT.md`. Awaiting owner acceptance. |
