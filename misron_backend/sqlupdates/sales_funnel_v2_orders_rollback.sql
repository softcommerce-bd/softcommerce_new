-- Removes the funnel attribution columns from `orders`.
--
-- WARNING: orders lose the record of which funnel they came from. The orders
-- themselves are not deleted or changed otherwise. Take a backup first.
--
-- Equivalent artisan command (rolls back the last batch of the module):
--   php artisan migrate:rollback --path=database/migrations/sales_funnel --force
--
-- The foreign keys exist when the columns were added by the artisan migration on
-- InnoDB tables, or by hand from the optional block of sales_funnel_v2_orders.sql.
-- Each is dropped only if present, so this works in both cases.

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND CONSTRAINT_NAME = 'orders_funnel_id_foreign') > 0,
  'ALTER TABLE `orders` DROP FOREIGN KEY `orders_funnel_id_foreign`',
  'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND CONSTRAINT_NAME = 'orders_funnel_version_id_foreign') > 0,
  'ALTER TABLE `orders` DROP FOREIGN KEY `orders_funnel_version_id_foreign`',
  'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `orders`
  DROP INDEX `orders_funnel_version_id_index`,
  DROP INDEX `orders_funnel_id_index`,
  DROP COLUMN `funnel_version_id`,
  DROP COLUMN `funnel_id`;

DELETE FROM `migrations` WHERE `migration` = '2026_10_23_100000_add_funnel_columns_to_orders_table';
