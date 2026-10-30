-- Sales Funnel module, phase 1 schema.
--
-- For hosts without shell access (phpMyAdmin import). Where `php artisan` is
-- available, run instead:
--   php artisan migrate --path=database/migrations/sales_funnel --force
-- Use one or the other. This file records the same migrations as applied,
-- so a later artisan run skips them, and it is safe to import twice.
--
-- Additive only: five new tables and five new permission rows. No existing
-- table or row is altered. Undo with sales_funnel_v1_rollback.sql.
--
-- After importing, use "Clear Cache" in the admin panel so the new
-- permissions show up in Staff > Roles.

CREATE TABLE IF NOT EXISTS `funnels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `internal_name` varchar(150) NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `description` text NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `draft_content` longtext NOT NULL,
  `draft_revision` int unsigned NOT NULL DEFAULT 1,
  `draft_hash` char(40) NULL,
  `draft_updated_at` timestamp NULL,
  `published_version_id` bigint unsigned NULL,
  `published_at` timestamp NULL,
  `unpublished_at` timestamp NULL,
  `archived_at` timestamp NULL,
  `scheduled_at` timestamp NULL,
  `meta_title` varchar(255) NULL,
  `meta_description` varchar(500) NULL,
  `og_image_upload_id` int NULL,
  `thumbnail_upload_id` int NULL,
  `settings` longtext NULL,
  `source_template_id` bigint unsigned NULL,
  `product_ids` text NULL,
  `created_by` int unsigned NULL,
  `updated_by` int unsigned NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  `deleted_at` timestamp NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `funnels_slug_unique` (`slug`),
  KEY `funnels_status_deleted_at_index` (`status`, `deleted_at`),
  KEY `funnels_published_version_id_index` (`published_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `funnel_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `funnel_id` bigint unsigned NOT NULL,
  `version_number` int unsigned NOT NULL,
  `kind` varchar(20) NOT NULL DEFAULT 'published',
  `schema_version` smallint unsigned NOT NULL,
  `content` longtext NOT NULL,
  `content_hash` char(40) NOT NULL,
  `label` varchar(120) NULL,
  `created_by` int unsigned NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `funnel_versions_funnel_id_version_number_unique` (`funnel_id`, `version_number`),
  KEY `funnel_versions_funnel_id_kind_index` (`funnel_id`, `kind`),
  CONSTRAINT `funnel_versions_funnel_id_foreign` FOREIGN KEY (`funnel_id`) REFERENCES `funnels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `funnel_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `description` text NULL,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `thumbnail_upload_id` int NULL,
  `content` longtext NOT NULL,
  `product_slots` longtext NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int unsigned NOT NULL DEFAULT 0,
  `created_by` int unsigned NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  `deleted_at` timestamp NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `funnel_templates_slug_unique` (`slug`),
  KEY `funnel_templates_category_sort_order_index` (`category`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `funnel_saved_sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `thumbnail_upload_id` int NULL,
  `content` longtext NOT NULL,
  `created_by` int unsigned NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  `deleted_at` timestamp NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `funnel_audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `funnel_id` bigint unsigned NULL,
  `user_id` int unsigned NULL,
  `action` varchar(40) NOT NULL,
  `meta` longtext NULL,
  `ip` varchar(45) NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `funnel_audit_logs_funnel_id_created_at_index` (`funnel_id`, `created_at`),
  KEY `funnel_audit_logs_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`name`, `section`, `guard_name`, `created_at`, `updated_at`)
SELECT p.name, 'sales_funnel', 'web', NOW(), NOW()
FROM (
  SELECT 'view_funnels' AS name UNION ALL
  SELECT 'add_funnel' UNION ALL
  SELECT 'edit_funnel' UNION ALL
  SELECT 'publish_funnel' UNION ALL
  SELECT 'delete_funnel'
) p
WHERE NOT EXISTS (
  SELECT 1 FROM `permissions` e WHERE e.`name` = p.name AND e.`guard_name` = 'web'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.migration, (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`)
FROM (
  SELECT '2026_10_22_100000_create_funnels_table' AS migration UNION ALL
  SELECT '2026_10_22_100100_create_funnel_versions_table' UNION ALL
  SELECT '2026_10_22_100200_create_funnel_templates_and_sections_tables' UNION ALL
  SELECT '2026_10_22_100300_create_funnel_audit_logs_table' UNION ALL
  SELECT '2026_10_22_100400_add_funnel_permissions'
) m
WHERE NOT EXISTS (SELECT 1 FROM `migrations` x WHERE x.`migration` = m.migration);
