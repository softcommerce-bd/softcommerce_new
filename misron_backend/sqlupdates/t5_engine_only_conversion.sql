-- T-5 / R-01: ENGINE-ONLY conversion of the purchasing tables from MyISAM to InnoDB. For a DBA; nothing runs this automatically.
-- Prefer `php artisan db:convert-engines` (dry run by default, row-count and AUTO_INCREMENT validation, maintenance-mode check, report);
-- this file is the same change as plain SQL. Unlike sqlupdates/inventory_v2_innodb_conversion.sql it adds NO unique key (decision 14 is open).
--
-- BEFORE: full backup restored and proven on a copy; application in maintenance mode (php artisan down); scheduler and queue workers stopped;
--         free disk >= 2 x (data + index) of the largest table; run the counts below and keep their output.
-- ROLLBACK: in the window (nothing has written since the backup) restore the five tables from the backup, or `ALTER TABLE x ENGINE=MyISAM`
--           (mechanically possible, returns to the unsafe state). After reopening, rollback is a forward fix, not a restore.
-- AFTER: ANALYZE TABLE on the five; php artisan db:engine-preflight; reconciliation; smoke tests; php artisan up.

SELECT 'before' AS stage, (SELECT COUNT(*) FROM suppliers) AS suppliers, (SELECT COUNT(*) FROM purchases) AS purchases,
       (SELECT COUNT(*) FROM purchase_products) AS purchase_products, (SELECT COUNT(*) FROM purchase_returns) AS purchase_returns,
       (SELECT COUNT(*) FROM purchase_return_products) AS purchase_return_products,
       (SELECT COALESCE(SUM(remaining_qty),0) FROM purchase_products) AS layer_units, (SELECT COALESCE(SUM(due_amount),0) FROM suppliers) AS supplier_due;

ALTER TABLE `suppliers`                ENGINE = InnoDB, ALGORITHM = COPY, LOCK = SHARED;
ALTER TABLE `purchase_returns`         ENGINE = InnoDB, ALGORITHM = COPY, LOCK = SHARED;
ALTER TABLE `purchase_return_products` ENGINE = InnoDB, ALGORITHM = COPY, LOCK = SHARED;
ALTER TABLE `purchases`                ENGINE = InnoDB, ALGORITHM = COPY, LOCK = SHARED;
ALTER TABLE `purchase_products`        ENGINE = InnoDB, ALGORITHM = COPY, LOCK = SHARED;

SELECT 'after' AS stage, (SELECT COUNT(*) FROM suppliers) AS suppliers, (SELECT COUNT(*) FROM purchases) AS purchases,
       (SELECT COUNT(*) FROM purchase_products) AS purchase_products, (SELECT COUNT(*) FROM purchase_returns) AS purchase_returns,
       (SELECT COUNT(*) FROM purchase_return_products) AS purchase_return_products,
       (SELECT COALESCE(SUM(remaining_qty),0) FROM purchase_products) AS layer_units, (SELECT COALESCE(SUM(due_amount),0) FROM suppliers) AS supplier_due;

SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME IN ('purchases','purchase_products','purchase_returns','purchase_return_products','suppliers');
