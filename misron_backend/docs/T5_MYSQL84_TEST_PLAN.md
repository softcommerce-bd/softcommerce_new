# MySQL 8.4 compatibility test plan (disposable databases only)

**Status (updated): EXECUTED on 2026-11-08 under owner decision MYSQL84.1 = Option A. Results: `T5_MYSQL84_REHEARSAL_REPORT.md`. Original text follows: PLAN ONLY, MySQL 8.4 had NOT been run.** No production MySQL 8.4 compatibility is claimed. Until a run is attached, the only validated engine is MariaDB 10.11.14. Production engine and version are unknown (BLOCKED, P1); the 8.4.3 in the header of `empty_misron.sql` / `misron.sql` is the dump tool's server and is not proof of what production runs.

## 1. Can a server be had here? (checked 2026-11-08)
| Route | Result |
|---|---|
| Distro package | only `mysql-server-8.0` (not 8.4) |
| Vendor downloads (`dev.mysql.com`, `cdn.mysql.com`, `repo.mysql.com`) | unreachable through the session proxy |
| Docker | the daemon can be started on a private socket (new since the earlier attempt) and the image `mysql:8.4` (digest `sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902`) **was pulled** (Docker Hub reachable). **Starting a container from that image was refused by the session's permission classifier** (external code), so no server was started. I did not work around it. |
| A server provided by you / the DBA / CI | not available to me |
**Decision needed from the owner (pick one):** (A) allow me to run the pulled `mysql:8.4` container on loopback port 3307 for this session (disposable, destroyed afterwards; I execute this plan end to end and attach evidence) - fastest; (B) a MySQL 8.4 server you provide (host/port/account limited to `*_test` databases) - I run the plan against it; (C) the DBA or a CI runner executes `tests/staging/mysql84-rehearsal.sh` from section 4 and returns the output. The pulled image sits in `/tmp/claude-0/dockerdata` (about 800 MB, scratch, disappears with the container).

## 2. Safety rules for any run
Database names must end in `_test` (the script and test base classes refuse anything else); loopback or a dedicated host; **never** a connection to staging or production; no data copied from real systems (schema from `empty_misron.sql` after confirming it holds no personal data, plus migrations; synthetic fixtures only); no feature flag set except inside isolated test processes; the plan changes no repository file except evidence under `docs/evidence/mysql84/`; teardown drops the databases and removes the container.

## 3. Phases, checks and pass criteria
| # | Phase | What to run / check | Pass |
|---|---|---|---|
| 0 | Identity | `SELECT VERSION(), @@version_comment, @@sql_mode, @@transaction_isolation, @@innodb_lock_wait_timeout, @@innodb_autoinc_lock_mode, @@default_authentication_plugin/authentication_policy, @@collation_server`; script refuses unless `8.4.x` | version printed `8.4.*`; values recorded |
| 1 | Connect | Laravel/PDO connection with the account type 8.4 creates (`caching_sha2_password`; `mysql_native_password` is **disabled by default in 8.4**) | app connects without server changes |
| 2 | Schema | load `empty_misron.sql` into `t5_mysql84_test`; `php artisan migrate --force`; `--path=database/migrations/inventory_v2`; record the first error | no error, or each error classified (code vs dump) |
| 3 | Schema diff | dump `information_schema` columns/indexes/engines of 8.4 and of MariaDB `inv_v2_test`; diff | only explained differences (collation `utf8mb4_0900_ai_ci` default vs `utf8mb4_unicode_ci` used by Laravel config, integer display widths, defaults syntax); engines all InnoDB (12 tables are MyISAM in the MariaDB test DB - record whether 8.4 load converts or keeps them) |
| 4 | T-5 index migration | up, rollback, up (repeat-safe), count `idx_t5_*` (13 expected); `InventoryAudit` queries use aliased lower-case columns (MySQL 8 returns upper-case I_S names unless aliased) | 13 indexes, rollback drops only `idx_t5_*`, audit reads work |
| 5 | Default suite | `vendor/bin/phpunit --log-junit` sequentially | 635 tests; the same 3 known unrelated failures and no others; per-test result diff against the MariaDB junit |
| 6 | Concurrency suite | `vendor/bin/phpunit tests/Concurrency` three times, sequentially | 96 tests OK x3; zero rows left (compare table row counts before/after) |
| 7 | Engine-specific mechanisms (these are exactly what differs between engines) | (a) the empty-index-range deadlock: two sessions, `scratch` SQL from `T5_INCOME_NUMBER_DEADLOCK_REPORT.md` section 2 - must deadlock **without** `NumberSerializer` and never **with** it (`first_income...` tests: 0 retries / 40); (b) `GET_LOCK`: multiple locks per session, reentrancy counts, release by `RELEASE_LOCK`, visibility from a second connection (`accounts:number-lock-selfcheck`, `NumberLockSelfCheckTest`); (c) deadlock detection (`innodb_deadlock_detect`), SQLSTATE 40001 / 1213 mapping in `DeadlockRetry::isDeadlock`; lock wait timeout 1205 **not** retried; (d) `EXPLAIN` of the number-generator locking read (index range on `voucher_no`) and of the hot lookups; (e) savepoint nesting inside the wrapped tests; (f) `StaleStatisticsConcurrencyTest` (optimizer differences matter); (g) `AUTO_INCREMENT` after rollback (`innodb_autoinc_lock_mode=2` is the 8.x default: the tinyint `payment_methods` exhaustion seen on MariaDB may differ) | each item matches the MariaDB behaviour or the difference is recorded as a finding |
| 8 | Operational scripts | `tests/staging/n1_readonly_audit.sh` against the 8.4 test DB (read-only probe must report error 1792); `tests/staging/inc1_db_facts.sql`; Q1-Q10 and `SupplierDriftQueriesTest` under `ONLY_FULL_GROUP_BY` | probe refuses writes; queries run; no full-group-by error |
| 9 | Strictness matrix | repeat 5-6 once with `strict=true` in `config/database.php` equivalents (the app ships `strict=false`) | informational: differences listed, not blockers |
| 10 | Teardown and evidence | drop `*_test` databases, remove the container; save outputs to `docs/evidence/mysql84/` with the version line on top of each file | evidence committed |

## 4. Existing tooling
`tests/staging/mysql84-rehearsal.sh` already covers phases 0, 2, 4, 5, 6 and a syntax pass of the audit SQL (it refuses non-8.4 and non-`_test`). Needed additions before use (not yet made, no run depends on them): phase 1 (auth plugin note), 3, 7, 8, 9 and junit logging.

## 5. Static risks found without running anything (to be confirmed or cleared by the run)
* Raw `GET_LOCK` (3 app files), `ON DUPLICATE KEY` (StockBalance), `information_schema` reads (InventoryAudit - aliased), JSON functions (13 app files), `ALTER TABLE` in migrations (3), `sql_mode` handling (12 app files), `insertOrIgnore` (2): none MariaDB-only on reading, all need the run.
* `config/database.php` has `strict => false` and `utf8mb4_unicode_ci`: behaviour under 8.4's stricter defaults is a phase 9 question.
* The tests and the N-1/INC.1 scripts were only ever executed on MariaDB 10.11.14.

## 6. What a PASS would and would not mean
PASS = the code and tests behave the same on MySQL 8.4 **for synthetic data on a fresh server**. It does **not** prove production compatibility (unknown version, data volume, storage engines, proxy, isolation, collation of the real tables), does not replace P3 (rehearsal on the masked production backup) and does not accept T-5. A FAIL is a finding to fix before any staging step.
