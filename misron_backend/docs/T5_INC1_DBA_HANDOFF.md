# INC.1 - production DBA handoff (income-number lock) and answer sheet

**Status: PREPARED. NO ANSWER HAS BEEN RECEIVED. Every answer below is UNANSWERED and must be filled in by the DBA; I have not guessed or assumed any.** Owner decision INC.1 asked for the checklist; this is the hand-off pack. Detail and rationale: `T5_INC1_DBA_CHECKLIST.md`, `T5_INCOME_NUMBER_DEADLOCK_REPORT.md` section 7.

## 1. Why the DBA is asked
The first income number of a year used to deadlock when several people saved incomes at the same moment (empty index range; reproduced: 19 of 40 requests). It is fixed by a database **session** lock (`GET_LOCK`) held for the transaction. That works only if one web request keeps one database connection for its whole transaction and all writers use one primary. If not, nothing breaks: the application behaves as before the fix (the deadlock is retried, up to 3 attempts). So the question is whether the fix is *effective* in production, not whether it is *safe*.

## 2. Nothing here changes anything
Everything below is read-only or writes nothing: SELECT/SHOW statements, and one diagnostic command that opens an empty transaction, takes and releases a throw-away lock named `misron:number:selfcheck:<random>`, then rolls back. No schema change, no migration, no deployment, no flag.

## 3. Steps
1. On the **production application host**, as the web-app user, in the project root: `php artisan accounts:number-lock-selfcheck` -> paste the complete output in 4A. (If the command does not exist there, the branch has not been deployed: say so; do not deploy for this.)
2. With a read-only database account: `mysql < tests/staging/inc1_db_facts.sql` -> paste the output in 4B. (It also answers two open AD-1 questions: is `balance_transfers.created_by` nullable, and are any auto-deposit methods configured; and shows storage engines, which production-engine item P1 needs.)
3. Answer questions 1-10 in 4C from the infrastructure (proxy/pool configuration, topology, runtime), with evidence.
4. Return this file; the owner records the result in the register.

## 4. Answer sheet
**4A. Self-check output:** UNANSWERED
**4B. `inc1_db_facts.sql` output:** UNANSWERED

**4C. Infrastructure questions** (required answer in brackets; "yes/no" is not enough - state the product, setting and where you checked)
| # | Question | Required | Answer | Evidence | Answered by / date |
|---|---|---|---|---|---|
| 1 | Engine and exact version (MySQL / MariaDB / managed service) | recorded | UNANSWERED | | |
| 2 | Proxy or pooler between the application and the database (ProxySQL, MaxScale, RDS Proxy, SQL-aware HAProxy, cloud pooling)? | none, or one that pins a session for the whole transaction | UNANSWERED | | |
| 3 | If a proxy exists: multiplexing / connection reuse between transactions enabled? | disabled, or `GET_LOCK` pins the connection | UNANSWERED | | |
| 4 | Persistent connections (`PDO::ATTR_PERSISTENT`, pooling extensions)? | not used | UNANSWERED | | |
| 5 | How many servers accept writes? Any Galera / Group Replication multi-primary / NDB (`wsrep_on`, `group_replication%`)? | one writable primary, no multi-primary | UNANSWERED | | |
| 6 | Read/write splitting (application `read`/`write` hosts or proxy rules)? | locks and transactions on the write connection | UNANSWERED | | |
| 7 | Request-to-connection: one connection per request, closed at the end (PHP-FPM)? Any Octane/Swoole/RoadRunner or queue workers on the income screen? | one connection per request, never shared between concurrent requests | UNANSWERED | | |
| 8 | Lock wait of 10 s vs `wait_timeout`, load-balancer idle timeout, PHP `max_execution_time` | all comfortably above 10 s | UNANSWERED | | |
| 9 | Behaviour on primary failover (a new primary has no locks) | accepted | UNANSWERED | | |
| 10 | Any other application/script inserting `INC-yy...` incomes directly? | none (a duplicate is still stopped by the unique index) | UNANSWERED | | |

## 5. How the owner reads the result
* Self-check PASS and questions 2-7 as required -> INC.1 satisfied; the lock is effective in production.
* Any "no" -> record it; behaviour stays as before the fix. Then decide **SEQ.1** (a `document_sequences` table removes the dependence on session locks) - not before.
* Self-check FAIL on "connection id changed inside one transaction" -> a multiplexing proxy is in front of the database; question 2/3 become the finding.
* A self-check PASS does **not** replace 4C: a multiplexer that happens to keep these few statements together would pass.

## 6. Still unknown to me (listed so nobody assumes)
Production engine, version, isolation level, storage engines (MyISAM would void all row-lock reasoning), topology, proxy, connection handling. Status of INC.1: **BLOCKED until this sheet is returned.**
