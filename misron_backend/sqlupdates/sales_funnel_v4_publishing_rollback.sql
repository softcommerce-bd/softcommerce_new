-- Undoes sales_funnel_v4_publishing.sql.
--
-- WARNING: deletes every funnel preview link, the scheduled-unpublish times and
-- the template library columns (library rows stay, as plain templates). Pages,
-- versions and orders are not touched. Take a backup first.

DROP TABLE IF EXISTS `funnel_preview_links`;

ALTER TABLE `funnel_templates`
  DROP INDEX `funnel_templates_template_key_language_unique`,
  DROP COLUMN `thumbnail_path`,
  DROP COLUMN `language`,
  DROP COLUMN `template_key`;

ALTER TABLE `funnels`
  DROP INDEX `funnels_unpublish_at_index`,
  DROP INDEX `funnels_status_scheduled_at_index`,
  DROP COLUMN `unpublish_at`;

DELETE FROM `migrations` WHERE `migration` IN (
  '2026_10_25_100000_add_publishing_columns_to_funnels_table',
  '2026_10_25_100100_create_funnel_preview_links_table',
  '2026_10_25_100200_add_library_columns_to_funnel_templates_table'
);
