-- Stock safety, step 2 of 3: CHANGE.
--
-- Do not run this until inventory_v1_preflight.sql came back clean and you hold a
-- backup you have tested restoring (docs/sales-funnel/PHASE4_INVENTORY_MIGRATION_PLAN.md).
-- Run in a quiet period with the storefront in maintenance mode (php artisan down):
-- orders must not be placed while the table is rebuilt.
--
-- What it does, in order:
--   A. Keeps a copy of purchase_products and product_stocks inside the database
--      (tables *_pre_inv1). The script stops here if they already exist, so it
--      cannot run twice by accident.
--   B. Converts purchase_products from MyISAM to InnoDB, so a failed order can give
--      its batch units back and row locks work.
--   C. Adds a unique key on product_stocks (product_id, variant): one stock row per
--      product and variation, and a fast row-level lock target.
--   D. Adds an index on purchase_products for the first-in-first-out lookup.
--
-- Nothing is merged, deleted or edited. Undo with inventory_v1_stock_safety_rollback.sql.

-- A. Safety copies.
CREATE TABLE `purchase_products_pre_inv1` LIKE `purchase_products`;
INSERT INTO `purchase_products_pre_inv1` SELECT * FROM `purchase_products`;
CREATE TABLE `product_stocks_pre_inv1` LIKE `product_stocks`;
INSERT INTO `product_stocks_pre_inv1` SELECT * FROM `product_stocks`;

-- Stop here if these two counts differ from each other.
SELECT (SELECT COUNT(*) FROM `purchase_products`) AS live_rows, (SELECT COUNT(*) FROM `purchase_products_pre_inv1`) AS copied_rows,
       (SELECT COUNT(*) FROM `product_stocks`) AS live_stock_rows, (SELECT COUNT(*) FROM `product_stocks_pre_inv1`) AS copied_stock_rows;

-- B. Engine change. Keeps every row, id and AUTO_INCREMENT value.
ALTER TABLE `purchase_products` ENGINE = InnoDB;

-- C. One stock row per product and variation. Fails with "Duplicate entry" if the
--    preflight duplicate check (query 4) was not clean: fix those rows first.
ALTER TABLE `product_stocks`
  ADD UNIQUE KEY `uq_product_stocks_product_variant` (`product_id`, `variant`);

-- D. Batch lookup. The 100-character prefix keeps the key inside the 767-byte limit
--    of older row formats; the full value is still compared.
ALTER TABLE `purchase_products`
  ADD KEY `idx_purchase_products_product_variant` (`product_id`, `variant`(100));

-- Verify: engine is InnoDB and the keys exist.
SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('purchase_products', 'product_stocks');
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('purchase_products', 'product_stocks')
GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE;
