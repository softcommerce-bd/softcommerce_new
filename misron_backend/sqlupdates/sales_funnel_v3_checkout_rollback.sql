-- Removes the funnel checkout attempts table.
--
-- WARNING: this deletes the record of which submissions were placed and the
-- prices each customer agreed to. The orders themselves are not touched.
-- Take a backup first. Equivalent artisan command:
--   php artisan migrate:rollback --path=database/migrations/sales_funnel --force

DROP TABLE IF EXISTS `funnel_checkout_attempts`;

DELETE FROM `migrations` WHERE `migration` = '2026_10_24_100000_create_funnel_checkout_attempts_table';
