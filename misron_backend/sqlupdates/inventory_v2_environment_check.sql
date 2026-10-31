-- READ-ONLY environment check for the production database engine (run with a SELECT-only user; changes nothing).
--   mysql -h <host> -u <readonly> -p <database> < sqlupdates/inventory_v2_environment_check.sql > environment_check_output.txt
-- Send the output back BEFORE the next rehearsal so the rehearsal uses the same engine and version.
SELECT 'server_version' AS item, VERSION() AS value;
SELECT 'version_comment' AS item, @@version_comment AS value;
SELECT 'sql_mode' AS item, @@sql_mode AS value;
SELECT 'default_storage_engine' AS item, @@default_storage_engine AS value;
SELECT 'character_set_server / collation_server' AS item, CONCAT(@@character_set_server, ' / ', @@collation_server) AS value;
SELECT 'innodb_lock_wait_timeout' AS item, @@innodb_lock_wait_timeout AS value;
SELECT 'innodb_file_per_table' AS item, @@innodb_file_per_table AS value;
SELECT 'log_bin' AS item, @@log_bin AS value;
SELECT 'binlog_format' AS item, @@binlog_format AS value;
SELECT 'max_allowed_packet' AS item, @@max_allowed_packet AS value;
SELECT 'time_zone' AS item, @@time_zone AS value;
SELECT 'database' AS item, DATABASE() AS value;
-- isolation level: MySQL 8 calls it transaction_isolation, MariaDB / MySQL 5.7 tx_isolation (one of the next two statements will fail; ignore that one)
SELECT 'transaction_isolation' AS item, @@transaction_isolation AS value;
SELECT 'tx_isolation' AS item, @@tx_isolation AS value;
-- storage engines in use (MyISAM money/stock tables must be converted first)
SELECT ENGINE AS engine, COUNT(*) AS tables, ROUND(SUM(DATA_LENGTH + INDEX_LENGTH) / 1048576, 1) AS size_mb FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' GROUP BY ENGINE;
SELECT TABLE_NAME, ENGINE, TABLE_ROWS, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('purchases','purchase_products','purchase_returns','purchase_return_products','suppliers','product_stocks','products','orders','order_details','account_statement_logs','chart_of_accounts','supplier_payments') ORDER BY TABLE_NAME;
-- objects the dump/restore must carry
SELECT 'triggers' AS item, COUNT(*) AS value FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE();
SELECT 'views' AS item, COUNT(*) AS value FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE();
SELECT 'routines' AS item, COUNT(*) AS value FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE();
SELECT 'foreign_keys' AS item, COUNT(*) AS value FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY';
-- the migration table: which migrations production has already run
SELECT 'migrations_applied' AS item, COUNT(*) AS value FROM migrations;
SELECT 'latest_migration' AS item, migration AS value FROM migrations ORDER BY id DESC LIMIT 1;
-- the same fingerprint the rehearsal uses (compare with the restored copy to prove the restore is complete)
SELECT 'orders.grand_total' AS item, ROUND(SUM(grand_total),2) AS value, COUNT(*) AS n FROM orders;
SELECT 'product_stocks.qty' AS item, SUM(qty) AS value, COUNT(*) AS n FROM product_stocks;
SELECT 'purchase_products.remaining' AS item, SUM(remaining_qty) AS value, COUNT(*) AS n FROM purchase_products;
SELECT 'suppliers.due' AS item, ROUND(SUM(due_amount),2) AS value, COUNT(*) AS n FROM suppliers;
SELECT 'chart.current_balance' AS item, ROUND(SUM(current_balance),2) AS value, COUNT(*) AS n FROM chart_of_accounts;
SELECT 'account_statement_logs' AS item, ROUND(SUM(debit),2) AS debit, ROUND(SUM(credit),2) AS credit, COUNT(*) AS n FROM account_statement_logs;
