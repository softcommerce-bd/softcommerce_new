-- Sales Funnel module: order attribution columns.
--
-- Requires sales_funnel_v1.sql (the funnels and funnel_versions tables).
-- Shell alternative (runs this and v1 together):
--   php artisan migrate --path=database/migrations/sales_funnel --force
--
-- Adds two nullable, indexed columns to `orders`. Existing orders keep NULL;
-- no existing row or column is changed. Undo with
-- sales_funnel_v2_orders_rollback.sql.
--
-- Take a database backup first. On a large orders table the ALTER can take a
-- while; MySQL 8 and MariaDB 10.3+ keep the table readable and writable
-- during it.

-- Safe to import twice: the ALTER runs only while the column is missing.
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'funnel_id') = 0,
  'ALTER TABLE `orders` ADD COLUMN `funnel_id` bigint unsigned NULL AFTER `order_from`, ADD COLUMN `funnel_version_id` bigint unsigned NULL AFTER `funnel_id`, ADD INDEX `orders_funnel_id_index` (`funnel_id`), ADD INDEX `orders_funnel_version_id_index` (`funnel_version_id`)',
  'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Foreign keys: OPTIONAL, and only when all three tables use InnoDB.
-- Check first:
--
--   SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
--   WHERE TABLE_SCHEMA = DATABASE()
--     AND TABLE_NAME IN ('orders', 'funnels', 'funnel_versions');
--
-- If every row says InnoDB, run the statement below as well. If `orders` uses
-- another engine (for example MyISAM), skip it: the columns and indexes above
-- are enough for the module, and the application never hard-deletes funnels.
--
-- ALTER TABLE `orders`
--   ADD CONSTRAINT `orders_funnel_id_foreign` FOREIGN KEY (`funnel_id`)
--     REFERENCES `funnels` (`id`) ON DELETE SET NULL,
--   ADD CONSTRAINT `orders_funnel_version_id_foreign` FOREIGN KEY (`funnel_version_id`)
--     REFERENCES `funnel_versions` (`id`) ON DELETE SET NULL;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_23_100000_add_funnel_columns_to_orders_table',
       (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`)
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_23_100000_add_funnel_columns_to_orders_table'
);
