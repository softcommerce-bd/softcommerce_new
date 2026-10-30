-- Sales Funnel module: analytics events, order attribution, payment gateway attempts.
--
-- Requires sales_funnel_v1.sql .. v4. For hosts without shell access (phpMyAdmin).
-- Shell alternative (runs this and the earlier funnel migrations together):
--   php artisan migrate --path=database/migrations/sales_funnel --force
--
-- Additive only: three new tables and one permission row. No existing row is changed.
-- After importing, use "Clear Cache" in the admin so the new permission is seen.
-- Safe to import twice. Undo with sales_funnel_v5_analytics_rollback.sql.

CREATE TABLE IF NOT EXISTS `funnel_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` varchar(64) NOT NULL,
  `funnel_id` bigint unsigned NOT NULL,
  `funnel_version_id` bigint unsigned NULL,
  `type` varchar(32) NOT NULL,
  `visitor_id` varchar(64) NULL,
  `session_id` varchar(64) NULL,
  `offer_key` varchar(64) NULL,
  `device` varchar(10) NOT NULL DEFAULT 'unknown',
  `source` varchar(40) NOT NULL DEFAULT 'direct',
  `medium` varchar(60) NULL,
  `campaign` varchar(120) NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `funnel_events_event_id_unique` (`event_id`),
  KEY `funnel_events_funnel_id_created_at_index` (`funnel_id`, `created_at`),
  KEY `funnel_events_funnel_id_type_created_at_index` (`funnel_id`, `type`, `created_at`),
  KEY `funnel_events_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `funnel_order_attributions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `combined_order_id` bigint unsigned NOT NULL,
  `funnel_id` bigint unsigned NOT NULL,
  `visitor_id` varchar(64) NULL,
  `session_id` varchar(64) NULL,
  `source` varchar(40) NOT NULL DEFAULT 'direct',
  `utm_source` varchar(150) NULL,
  `utm_medium` varchar(150) NULL,
  `utm_campaign` varchar(150) NULL,
  `utm_content` varchar(150) NULL,
  `utm_term` varchar(150) NULL,
  `fbclid` varchar(255) NULL,
  `gclid` varchar(255) NULL,
  `referrer_host` varchar(150) NULL,
  `device` varchar(10) NOT NULL DEFAULT 'unknown',
  `consent` varchar(10) NOT NULL DEFAULT 'unknown',
  `is_test` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `funnel_order_attributions_combined_order_id_unique` (`combined_order_id`),
  KEY `funnel_order_attributions_funnel_id_index` (`funnel_id`),
  KEY `funnel_order_attributions_funnel_id_is_test_index` (`funnel_id`, `is_test`),
  KEY `funnel_order_attributions_utm_campaign_index` (`utm_campaign`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_gateway_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `combined_order_id` bigint unsigned NOT NULL,
  `gateway` varchar(30) NOT NULL,
  `invoice_number` varchar(80) NOT NULL,
  `amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'started',
  `gateway_status` varchar(40) NULL,
  `checked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_gateway_attempts_invoice_number_unique` (`invoice_number`),
  KEY `payment_gateway_attempts_combined_order_id_index` (`combined_order_id`),
  KEY `payment_gateway_attempts_status_created_at_index` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- view_funnel_analytics permission (Staff > Roles > Sales Funnel) -------------
INSERT INTO `permissions` (`name`, `section`, `guard_name`, `created_at`, `updated_at`)
SELECT 'view_funnel_analytics', 'sales_funnel', 'web', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `permissions` e WHERE e.`name` = 'view_funnel_analytics' AND e.`guard_name` = 'web');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.`name`, (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`)
FROM (
  SELECT '2026_10_26_100000_create_funnel_events_table' AS `name`
  UNION ALL SELECT '2026_10_26_100100_create_funnel_order_attributions_table'
  UNION ALL SELECT '2026_10_26_100200_create_payment_gateway_attempts_table'
  UNION ALL SELECT '2026_10_26_100300_add_funnel_analytics_permission'
) m
WHERE NOT EXISTS (SELECT 1 FROM `migrations` x WHERE x.`migration` = m.`name`);
