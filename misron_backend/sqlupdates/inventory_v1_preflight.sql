-- Stock safety, step 1 of 3: PREFLIGHT. Reads only; changes nothing.
--
-- Run this in phpMyAdmin (or `mysql < file`) on the database you plan to change,
-- BEFORE inventory_v1_stock_safety.sql. Every check says what a clean result is.
-- Anything else: stop and read docs/sales-funnel/PHASE4_INVENTORY_MIGRATION_PLAN.md.
--
-- Shell alternative with a fuller report and CSV files:
--   php artisan inventory:audit --preflight --out

-- 1. Server and storage engines.
-- Expected: purchase_products = MyISAM (the change converts it), product_stocks = InnoDB.
SELECT VERSION() AS server_version, @@version_comment AS flavour, @@transaction_isolation AS isolation,
       @@innodb_lock_wait_timeout AS lock_wait_seconds, @@sql_mode AS sql_mode;

SELECT TABLE_NAME, ENGINE, ROW_FORMAT, TABLE_ROWS AS approx_rows,
       ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024) AS size_kb, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('product_stocks', 'purchase_products', 'purchases', 'purchase_returns', 'purchase_return_products', 'order_details');

-- 2. Indexes that exist today.
-- Expected: only PRIMARY on product_stocks and purchase_products. If a unique key on
-- (product_id, variant) already exists, the step-2 script will report "duplicate key name".
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('product_stocks', 'purchase_products')
GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE;

-- 3. Foreign keys, triggers, views and routines that touch the stock tables.
-- Expected: no rows from any of the four queries.
SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND (TABLE_NAME IN ('product_stocks', 'purchase_products', 'purchases') OR REFERENCED_TABLE_NAME IN ('product_stocks', 'purchase_products', 'purchases'));
SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE();
SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE();
SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE();

-- 4. Duplicate (product, variation) pairs in product_stocks, compared with the column's
-- collation, which is what the unique index will use (so 'Black-M' and 'black-m' count as equal).
-- Expected: no rows. Any row blocks the unique index. Nothing is merged for you: decide
-- per pair which quantity is right, then fix it in the admin panel or with a reviewed UPDATE.
SELECT product_id, variant, COUNT(*) AS copies, GROUP_CONCAT(id ORDER BY id) AS stock_ids, GROUP_CONCAT(qty ORDER BY id) AS quantities
FROM product_stocks
GROUP BY product_id, variant
HAVING COUNT(*) > 1;

-- 5. NULL, empty and space-padded variation values.
-- product_stocks.variant is NOT NULL, so 'null' should not appear. 'empty' is normal for
-- plain products (one per product). 'padded' rows are looked up wrongly by exact match.
SELECT 'product_stocks' AS table_name,
       CASE WHEN variant IS NULL THEN 'null' WHEN variant = '' THEN 'empty'
            WHEN CHAR_LENGTH(variant) <> CHAR_LENGTH(TRIM(variant)) THEN 'padded' ELSE 'text' END AS shape,
       COUNT(*) AS row_count
FROM product_stocks GROUP BY 2
UNION ALL
SELECT 'purchase_products',
       CASE WHEN variant IS NULL THEN 'null' WHEN variant = '' THEN 'empty'
            WHEN CHAR_LENGTH(variant) <> CHAR_LENGTH(TRIM(variant)) THEN 'padded' ELSE 'text' END,
       COUNT(*)
FROM purchase_products GROUP BY 2;

-- 6. Impossible numbers. Expected: no rows from any query.
SELECT 'negative stock' AS problem, id, product_id, variant, qty AS value_found FROM product_stocks WHERE qty < 0
UNION ALL SELECT 'negative batch', id, product_id, variant, remaining_qty FROM purchase_products WHERE remaining_qty < 0
UNION ALL SELECT 'batch holds more than bought', id, product_id, variant, remaining_qty FROM purchase_products
          WHERE remaining_qty > CAST(purchase_quantity AS SIGNED);

SELECT id, product_id, purchase_quantity, remaining_qty, buying_unit_cost AS non_numeric_row
FROM purchase_products
WHERE purchase_quantity IS NULL OR purchase_quantity NOT REGEXP '^[0-9]+$' OR remaining_qty IS NULL
   OR buying_unit_cost IS NULL OR buying_unit_cost NOT REGEXP '^[0-9]+(\\.[0-9]+)?$';

-- 7. Zero dates stop a MyISAM -> InnoDB conversion under strict SQL mode.
-- Expected: 0 and 0.
SELECT (SELECT COUNT(*) FROM purchase_products WHERE CAST(created_at AS CHAR) LIKE '0000-00-00%' OR CAST(updated_at AS CHAR) LIKE '0000-00-00%') AS purchase_products_zero_dates,
       (SELECT COUNT(*) FROM purchases          WHERE CAST(created_at AS CHAR) LIKE '0000-00-00%' OR CAST(updated_at AS CHAR) LIKE '0000-00-00%') AS purchases_zero_dates;

-- 8. Row counts to compare after the change. Write these numbers down.
SELECT (SELECT COUNT(*) FROM purchase_products) AS purchase_products_rows,
       (SELECT COALESCE(SUM(remaining_qty), 0) FROM purchase_products) AS purchase_products_remaining_sum,
       (SELECT COUNT(*) FROM product_stocks) AS product_stocks_rows,
       (SELECT COALESCE(SUM(qty), 0) FROM product_stocks) AS product_stocks_qty_sum,
       (SELECT SUM(CRC32(CONCAT_WS('|', id, product_id, purchase_id, variant, purchase_quantity, remaining_qty, buying_unit_cost, total_cost)))
          FROM purchase_products) AS purchase_products_checksum,
       (SELECT SUM(CRC32(CONCAT_WS('|', id, product_id, variant, qty, price))) FROM product_stocks) AS product_stocks_checksum;

-- 9. Free disk space. The conversion rebuilds purchase_products: you need roughly
-- 2x its size_kb (query 1) free in the MySQL data directory. Check the server, not SQL.
