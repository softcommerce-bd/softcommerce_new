-- Sales Funnel module: scheduled unpublishing, secret preview links, template library columns.
--
-- Requires sales_funnel_v1.sql. For hosts without shell access (phpMyAdmin).
-- Shell alternative (runs this and the earlier funnel migrations together):
--   php artisan migrate --path=database/migrations/sales_funnel --force
--
-- Additive only: one nullable column on `funnels`, three nullable/defaulted
-- columns on `funnel_templates`, one new table. No existing row changes meaning.
-- Safe to import twice (each ALTER checks information_schema first).
-- Undo with sales_funnel_v4_publishing_rollback.sql.

-- funnels.unpublish_at ------------------------------------------------------
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'funnels' AND COLUMN_NAME = 'unpublish_at') = 0,
  'ALTER TABLE `funnels` ADD COLUMN `unpublish_at` timestamp NULL DEFAULT NULL AFTER `scheduled_at`, ADD INDEX `funnels_status_scheduled_at_index` (`status`, `scheduled_at`), ADD INDEX `funnels_unpublish_at_index` (`unpublish_at`)',
  'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- funnel_templates library columns -----------------------------------------
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'funnel_templates' AND COLUMN_NAME = 'template_key') = 0,
  'ALTER TABLE `funnel_templates` ADD COLUMN `template_key` varchar(60) NULL AFTER `slug`, ADD COLUMN `language` varchar(5) NOT NULL DEFAULT ''bn'' AFTER `template_key`, ADD COLUMN `thumbnail_path` varchar(255) NULL AFTER `thumbnail_upload_id`, ADD UNIQUE KEY `funnel_templates_template_key_language_unique` (`template_key`, `language`)',
  'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- funnel_preview_links ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `funnel_preview_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `funnel_id` bigint unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `label` varchar(120) NULL,
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `use_count` int unsigned NOT NULL DEFAULT 0,
  `created_by` int unsigned NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `funnel_preview_links_token_hash_unique` (`token_hash`),
  KEY `funnel_preview_links_funnel_id_index` (`funnel_id`),
  KEY `funnel_preview_links_funnel_id_revoked_at_expires_at_index` (`funnel_id`, `revoked_at`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.`name`, (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`)
FROM (
  SELECT '2026_10_25_100000_add_publishing_columns_to_funnels_table' AS `name`
  UNION ALL SELECT '2026_10_25_100100_create_funnel_preview_links_table'
  UNION ALL SELECT '2026_10_25_100200_add_library_columns_to_funnel_templates_table'
) m
WHERE NOT EXISTS (SELECT 1 FROM `migrations` x WHERE x.`migration` = m.`name`);
