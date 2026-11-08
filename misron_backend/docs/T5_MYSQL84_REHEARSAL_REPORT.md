# MySQL 8.4 rehearsal - evidence report (2026-11-08)

**Owner decision MYSQL84.1 = Option A (approved): run the pulled `mysql:8.4` container against disposable test databases only.** Executed per `T5_MYSQL84_TEST_PLAN.md`. **Result: the T-5 locking and concurrency work behaves correctly on MySQL 8.4.13; the rehearsal found one real code incompatibility (a write to a generated column) and several environment/setup differences. T-5 and staging acceptance remain OPEN. This is not a statement about production compatibility (production engine/version is still unknown, P1).**
Raw evidence: `docs/evidence/mysql84/` (every number below comes from a file there).

## 1. Environment (`00_environment.txt`)
| Item | Value |
|---|---|
| Server | MySQL Community Server **8.4.13**, image `mysql:8.4`, digest `sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902` (created 2026-11-03) |
| Container | Docker 29.9.2 on a private socket, host network, bound to **127.0.0.1:3307 only**, `--skip-name-resolve`; account `t5` limited to `t5_%` plus two named test databases; root only inside the container |
| Defaults seen | sql_mode `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`; REPEATABLE-READ; `innodb_lock_wait_timeout` 50; `innodb_autoinc_lock_mode` 2; `innodb_deadlock_detect` on; `collation_server` utf8mb4_0900_ai_ci; auth `caching_sha2_password` (PHP 8.3.6 mysqlnd connected without server changes) |
| Application side | PHP 8.3.6, same code at commit `f8998af` (no repository file changed; two temporary local edits were reverted, see 5) |
| Comparison engine | MariaDB 10.11.14 (original `inv_v2_test` and a control database built exactly like the 8.4 one) |
| Databases touched | `t5_mysql84_test`, `misron_test` (a disposable test database that two tests expect to exist), `t5_scratch_test` on the container; `t5_ctl_test` on the local MariaDB. **Never** production, staging, `sc_staging`, or any main `misron` database. |
| Data | schema and seed from the repository dump `empty_misron.sql` (it contains some staff/address/supplier rows, so `tests/staging/mask-pii.sql` was applied right after loading), then migrations; synthetic test fixtures. Nothing was copied from a real system. |

## 2. Results per phase
| # | Phase | Result | File |
|---|---|---|---|
| 0 | Identity | 8.4.13 confirmed | 00 |
| 1 | Connect | OK (PDO and client) | 00 |
| 2 | Schema + migrations | dump loaded in 6 s with no error; `inventory_v2` (10) and `unified_chat` (2) migrations all DONE; `migrations` table identical to the MariaDB test DB. **The plan's first step `php artisan migrate` fails on this dump** (`personal_access_tokens` already exists): a flaw in `mysql84-rehearsal.sh`, not in 8.4 - the dump already contains those tables, only the path migrations apply | 02 |
| 3 | Schema diff vs MariaDB test DB | columns and indexes identical after normalising integer display widths; differences only: `failed_jobs` absent from the dump, `chat_settings` primary key present in the 8.4 copy. **Engines:** the dump keeps `purchases`, `suppliers`, `purchase_returns`, `purchase_return_products` on **MyISAM**; converted to InnoDB as in the earlier rehearsals (12 small tables stay MyISAM in both) | 03 |
| 4 | T-5 index migration | `idx_t5_*` = 13; `down()` -> 0, `up()` -> 13, twice, plus a repeat `up()`; the other 435 index rows never changed | 04 |
| 5 | Default suite | 635 tests: **31 fail on 8.4; 21 fail on a MariaDB control built the same way** (the control's extra failures vs the 3 known on `inv_v2_test` come from seed data that database was given and this one was not). **10 fail only on 8.4** - classified in section 3 | 05, 05c |
| 6 | Concurrency suite | **96 tests / 2329 assertions OK, three consecutive runs**, zero skipped; the stale-statistics experiment shows 0 deadlocks in every round | 06 |
| 7a | Empty-index-range deadlock | reproduced with two plain SQL sessions on 8.4 (`SHOW ENGINE INNODB STATUS`: both hold X on the supremum and wait for insert-intention). Optimizer difference: 8.4 locks the PRIMARY index (backward scan) instead of the `voucher_no` index; the mechanism is the same. **Without `NumberSerializer`: 12 of 40 and 20 of 40 requests deadlocked and the three income tests fail; with it: 0 and 6 income tests OK in 3 consecutive runs (258 assertions each)** | 07a |
| 7b | `GET_LOCK` / self-check | `accounts:number-lock-selfcheck` PASS: one connection id across the transaction, lock visible to a second connection and released at the end; commit/rollback/nested/reentrancy covered by the passing tests | 07b |
| 7c | Deadlock-handling tests | concurrency: 11 of 11 pass; default suite: 84 of 85 pass (the failure also fails on the control) | 07c |
| 7d | EXPLAIN of the number read | 8.4 and MariaDB both choose PRIMARY with a backward scan on the (empty) `incomes` table | 07d |
| 7g | AUTO_INCREMENT | `payment_methods` counter did not move during the 8.4 default suite (59 -> 59); the MariaDB tinyint exhaustion did not recur | 07g |
| 8 | Operational scripts | `n1_readonly_audit.sh`: read-only probe refuses a write, Q1-Q10 all execute under `ONLY_FULL_GROUP_BY`, refuses without confirmation; `inc1_db_facts.sql` runs (one wording fix, section 4). Result files were not kept (they derive from the supplied backup's data) | 08 |
| 9 | Strict mode | with `strict=true`: 416 failures (387 more), almost all error 1364 "Field has no default value": the legacy schema relies on non-strict mode, which the app ships. Informational, not a blocker | 09 |
| 10 | Teardown | see section 6 | - |

## 3. Findings
| ID | Finding | Type | Evidence | Needs |
|---|---|---|---|---|
| **F1** | **The application writes to a generated column.** `purchase_order_details.pending_quantity` is `GENERATED ALWAYS AS (purchase_quantity - recieved_quantity) VIRTUAL`. `PurchaseOrderService.php:55` (insert), `:83` (update) and `GoodsReceiptService.php:144` (update) set it explicitly. **MySQL 8.4 rejects this with error 3105** whatever the sql_mode; MariaDB accepts it only because the app runs non-strict (it ignores the value with a warning; in strict mode it errors 1906). Effect on 8.4: creating a purchase order, receiving against one, bulk commit and the requirements screen fail with HTTP 500 - **8 tests fail only on 8.4**. It is the only truly generated column in the schema (checked in `information_schema`). On MariaDB the write was always a silent no-op, so line `:83` (cancel: "pending = 0") has never had its intended effect there either; that needs a look when it is fixed. | **real code incompatibility** | 05_generated_column_probe, 05_failure_comparison | owner go-ahead to remove `pending_quantity` from those three writes (no schema change, no financial rule change; MariaDB behaviour unchanged) and review `:83`; **not changed in this rehearsal** |
| F2 | `InventoryToolsTest` (2 tests) connect to a database literally named `misron_test` on the same server; it did not exist on the container, so they failed. With a disposable `misron_test` created they pass. | environment | 05, rerun 6/6 OK | none |
| F3 | `mysql84-rehearsal.sh` step 1 (`php artisan migrate`) cannot work on the dump, and lacks the MyISAM->InnoDB conversion, the `unified_chat` path, the mask step and the `misron_test` database | tooling | 02, 03 | script update (not done) |
| F4 | `inc1_db_facts.sql`: on 8.4 `LIKE 'group_replication%'` returns `group_replication_consistency` even when Group Replication is not used; it is not evidence of multi-primary | wording | 08b | fixed in this commit (comment + note in the DBA handoff) |
| F5 | The default suite on a database built from the dump has 21 failures on MariaDB too: the passing baseline (3 known) depends on the seed state of `inv_v2_test`, which is not reproducible from the repository alone | tooling | 05c | document how `inv_v2_test` is seeded |

## 4. What this does and does not show
* **Shown on 8.4.13:** the income-number deadlock fix works (0 deadlocks in 40 requests, 3 runs; 12-20 of 40 without it); the session lock behaves as designed; all 96 concurrency tests pass three times; the T-5 index migration is reversible and repeat-safe; the N-1 script and queries run read-only.
* **Not shown:** production compatibility (version, volume, storage engines, proxy, collations unknown); behaviour of any MySQL 8.0/8.1-8.3 or Percona/managed variant; the 21 seed-dependent default-suite failures were not individually triaged beyond "same on the MariaDB control"; the `purchases`/`suppliers` conversion to InnoDB was assumed from the earlier rehearsal, not re-decided; UI not exercised.

## 5. Temporary edits during the run (all reverted)
`app/Models/Income.php` (hold line commented to prove the failure mode; restored, `git diff` empty) and `config/database.php` (`strict` true for phase 9; restored). No other repository file was modified except the documentation and evidence in this commit.

## 6. Teardown
The container, its anonymous volume, the databases on the container, the control database on MariaDB, the pulled image and the private Docker daemon were removed after the evidence was saved; `docs/evidence/mysql84/teardown.txt` records the commands and the checks that nothing remained.
