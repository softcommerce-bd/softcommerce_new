-- INC.1: read-only facts for the production DBA (decision INC.1). SELECT / SHOW only; writes nothing; safe to run on the live server by an authorised DBA
-- with a read-only account. Paste the whole output into docs/T5_INC1_DBA_HANDOFF.md section 4 (answer sheet).
-- On MySQL 8.4 use SHOW REPLICA STATUS; on MariaDB / MySQL < 8.0.22 use SHOW SLAVE STATUS (both are listed; the one that does not exist will error - ignore it).
SELECT 'engine/version' AS fact, VERSION() AS value UNION ALL
SELECT 'hostname', @@hostname UNION ALL
SELECT 'port', @@port UNION ALL
SELECT 'read_only', @@read_only UNION ALL
SELECT 'innodb_lock_wait_timeout', @@innodb_lock_wait_timeout UNION ALL
SELECT 'wait_timeout', @@wait_timeout UNION ALL
SELECT 'max_connections', @@max_connections UNION ALL
SELECT 'sql_mode', @@sql_mode;
SHOW VARIABLES LIKE '%isolation%';                       -- transaction_isolation (MySQL 8.x) / tx_isolation (MariaDB < 11.1); expected REPEATABLE-READ
SHOW VARIABLES LIKE 'wsrep_on';                          -- ON = Galera / multi-primary: GET_LOCK is node-local, NOT safe
SHOW VARIABLES LIKE 'super_read_only';
SHOW VARIABLES LIKE 'group_replication%';                -- NOTE: MySQL 8.4 lists group_replication_consistency even when Group Replication is NOT used; that line alone is not evidence of multi-primary.
                                                          -- Real check: SELECT * FROM performance_schema.replication_group_members;  (empty = not a group member)
SHOW GLOBAL STATUS LIKE 'Threads_connected';
SHOW GLOBAL STATUS LIKE 'Connections';
-- Is the application connecting directly or through a proxy? Count client addresses (no user data):
SELECT SUBSTRING_INDEX(host, ':', 1) AS client_address, user, COUNT(*) AS connections
FROM information_schema.PROCESSLIST GROUP BY SUBSTRING_INDEX(host, ':', 1), user ORDER BY connections DESC;
-- Storage engines of the tables that carry the locks (must be InnoDB; MyISAM has no row locks or transactions):
SELECT table_name, engine FROM information_schema.TABLES
WHERE table_schema = DATABASE() AND table_name IN ('incomes', 'purchases', 'suppliers', 'supplier_payments', 'chart_of_accounts', 'balance_transfers', 'orders', 'carts', 'idempotency_keys')
ORDER BY table_name;
SELECT engine, COUNT(*) AS tables FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' GROUP BY engine;
-- Replication role (run whichever exists):
-- SHOW REPLICA STATUS;
-- SHOW SLAVE STATUS;
-- Is balance_transfers.created_by nullable? (decision AD-1 needs the answer)
SELECT is_nullable AS balance_transfers_created_by_nullable FROM information_schema.COLUMNS
WHERE table_schema = DATABASE() AND table_name = 'balance_transfers' AND column_name = 'created_by';
-- Are any auto-deposit methods configured? (count only)
SELECT COUNT(*) AS active_auto_deposit_methods FROM transaction_methods WHERE deposit_type = 'auto' AND status = 'active';
