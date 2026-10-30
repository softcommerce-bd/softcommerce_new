-- Inventory v2, PREREQUISITE (not run by any migration): convert the MyISAM purchasing tables to InnoDB.
--
-- WHY: MyISAM ignores transactions and row locks. Goods receipts, purchases and supplier balances cannot be made
-- all-or-nothing, and `lockForUpdate()` does nothing, until these tables are InnoDB.
-- The v2 stock model refuses to switch on (INVENTORY_CONFIRMED_ONLY=true) while purchase_products is MyISAM.
--
-- BEFORE RUNNING, in a maintenance window:
--   1. Full backup (mysqldump --single-transaction --routines --triggers) and verify it restores on a copy.
--   2. Run this whole file on the COPY first and re-run `php artisan inventory:reconcile` before and after.
--   3. Make sure the disk has free space of at least the size of the five tables.
--   4. Run the duplicate checks below; each must return no rows.
-- ROLLBACK: restore the backup (an ENGINE change is not reversible without data risk once new rows exist).

-- Duplicate checks (must be empty before adding unique keys):
SELECT product_id, variant, COUNT(*) c FROM product_stocks GROUP BY product_id, variant HAVING c > 1;
SELECT reference_no, COUNT(*) c FROM purchases GROUP BY reference_no HAVING c > 1;

ALTER TABLE `purchases`                ENGINE = InnoDB;
ALTER TABLE `purchase_products`        ENGINE = InnoDB;
ALTER TABLE `purchase_returns`         ENGINE = InnoDB;
ALTER TABLE `purchase_return_products` ENGINE = InnoDB;
ALTER TABLE `suppliers`                ENGINE = InnoDB;

-- Keys that make the stock rows and references unambiguous (the same as inventory_v1_stock_safety.sql; skip what it already added).
-- ALTER TABLE `product_stocks`   ADD UNIQUE KEY `uq_product_stocks_product_variant` (`product_id`, `variant`);
-- ALTER TABLE `purchase_products` ADD KEY `idx_purchase_products_product_variant` (`product_id`, `variant`(100));
ALTER TABLE `purchases` ADD UNIQUE KEY `uq_purchases_reference_no` (`reference_no`(100));

SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN
 ('purchases','purchase_products','purchase_returns','purchase_return_products','suppliers');
