-- Sales Funnel module: checkout attempts (idempotency and price record).
--
-- Requires sales_funnel_v1.sql. For hosts without shell access (phpMyAdmin).
-- Shell alternative (runs this and the earlier funnel migrations together):
--   php artisan migrate --path=database/migrations/sales_funnel --force
--
-- One new table. Nothing existing is altered. The table holds no personal
-- data (names, phones and addresses live on the order). Safe to import twice.
-- Undo with sales_funnel_v3_checkout_rollback.sql.
--
-- Funnel checkout stays switched off until FUNNEL_CHECKOUT_ENABLED=true is set
-- in .env, and it needs sales_funnel_v2_orders.sql (the order attribution
-- columns) as well; with either missing it refuses to take orders.

CREATE TABLE IF NOT EXISTS `funnel_checkout_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `funnel_id` bigint unsigned NOT NULL,
  `funnel_version_id` bigint unsigned NOT NULL,
  `key_hash` char(64) NOT NULL,
  `request_hash` char(64) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'started',
  `error_code` varchar(60) NULL,
  `quote_fingerprint` char(40) NULL,
  `combined_order_id` bigint unsigned NULL,
  `payment_type` varchar(40) NULL,
  `quote_snapshot` longtext NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `funnel_checkout_attempts_key_hash_unique` (`key_hash`),
  KEY `funnel_checkout_attempts_funnel_id_index` (`funnel_id`),
  KEY `funnel_checkout_attempts_combined_order_id_index` (`combined_order_id`),
  KEY `funnel_checkout_attempts_status_created_at_index` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_24_100000_create_funnel_checkout_attempts_table',
       (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`)
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_24_100000_create_funnel_checkout_attempts_table'
);
