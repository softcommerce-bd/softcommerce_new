# INC.1: production DBA checklist - income-number lock (`NumberSerializer`)

**Why:** the first income number of a year is serialised with a database **session** lock (`GET_LOCK`). It is correct only if each web request keeps ONE database connection for its whole transaction, and the lock is visible to all other writers. If any answer below is "no" the code silently falls back to its previous behaviour (the deadlock retry), which is safe but not fixed. Nothing here needs a change to production; it is a set of **questions and read-only checks**. Record each answer with date, who, and evidence.

## A. Run the application's own self-check (writes nothing)
On the production application host, as the user the web app runs as, from the project root:
```bash
php artisan accounts:number-lock-selfcheck
```
Expected: `PASS` on all three lines and `RESULT: PASS`. It prints the connection ids sampled inside one transaction (must be identical), whether a second independent connection is refused while the lock is held, and that the lock is released when the transaction ends. (It takes one throw-away lock named `misron:number:selfcheck:<random>`, an empty transaction, and rolls it back. No table is read or written.) Paste the full output into the answer. A PASS here does not replace section B.

## B. Infrastructure answers (read-only)
| # | Question | How to find out | Required answer | Your answer / evidence |
|---|---|---|---|---|
| 1 | Engine and exact version (MySQL / MariaDB / managed service) | `SELECT VERSION();` | recorded (also needed for the MySQL 8.4 question, still untested) | |
| 2 | Is there a **proxy or pooler** between the application and the database (ProxySQL, MaxScale, RDS Proxy, PgBouncer-like, HAProxy in SQL-aware mode, cloud "connection pooling")? | architecture / `.env` DB_HOST points to a proxy? `SHOW PROCESSLIST` shows the proxy's address instead of the web server? | **None**, or one configured to pin a session for the whole transaction **and** to keep session state (`GET_LOCK`) - multiplexing/transaction-level pooling must be OFF for this application | |
| 3 | If a proxy exists: is **multiplexing / connection reuse between transactions** enabled (ProxySQL `mysql-multiplexing`, `mysql_users.transaction_persistent`; RDS Proxy "pinning"; other)? | proxy configuration | disabled, or `GET_LOCK` is known to pin the connection | |
| 4 | **Persistent connections** (`PDO::ATTR_PERSISTENT`, `DB_PERSISTENT`, php-fpm connection pooling extensions)? | `config/database.php` `options`, `.env`, php.ini | **not used** (the self-check prints the option) | |
| 5 | **How many database servers accept writes?** Single primary / replicas only for reads? Any multi-primary (Galera, Group Replication multi-primary, NDB)? | `SHOW VARIABLES LIKE 'wsrep_on';` `SELECT @@read_only, @@super_read_only;` replication topology | exactly **one writable primary**; all application writes (and these locks) go to it; `wsrep_on` = OFF/absent | |
| 6 | **Read/write splitting** in the application (`read`/`write` host arrays in `config/database.php`) or in a proxy? | config / proxy rules | if present: transactions and `GET_LOCK` must run on the **write** connection (Laravel uses the write PDO inside a transaction - confirm with the self-check output host = primary) | |
| 7 | **Request-to-connection behaviour**: does one web request open one connection and close it at the end (PHP-FPM, no long-lived workers)? Any Octane/Swoole/RoadRunner or queue workers that run the income screen? | runtime setup | one connection per request, closed at request end; for long-lived workers, confirm connections are not shared between concurrent requests | |
| 8 | Is the **lock wait of 10 s** acceptable with the database's `wait_timeout`, load-balancer idle timeout and PHP `max_execution_time`? | settings | all comfortably above 10 s | |
| 9 | Failover: after a primary failover, are locks lost (a new primary has none)? | failover design | accepted: a failover during a request ends that request's transaction as well; no stale lock survives | |
| 10 | Are there **other applications or scripts** that insert incomes (`INC-yy...`) directly into the database? | team knowledge / binlog / grants | none (they would bypass the lock; the unique index still prevents duplicates) | |

## C. Decision rule
* All of 2/3/4/5/6/7 as required and A = PASS -> INC.1 satisfied; the income-number lock is effective in production.
* Any "no" -> record it; the application keeps working exactly as before the fix (retry still active). Then decide SEQ.1 (a `document_sequences` table would remove the dependence on session locks).
* I do not have, and was not given, any of these answers: **status stays BLOCKED until the DBA returns this sheet.**
