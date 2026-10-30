-- Removes the Sales Funnel module schema (phase 1).
--
-- WARNING: deletes every funnel, version, template, saved section and funnel
-- audit entry. Orders, products, customers and stock are not touched.
-- Take a database backup first.
--
-- Equivalent artisan command:
--   php artisan migrate:rollback --path=database/migrations/sales_funnel --force

DELETE rhp FROM `role_has_permissions` rhp
  JOIN `permissions` p ON p.`id` = rhp.`permission_id`
  WHERE p.`section` = 'sales_funnel';
DELETE mhp FROM `model_has_permissions` mhp
  JOIN `permissions` p ON p.`id` = mhp.`permission_id`
  WHERE p.`section` = 'sales_funnel';
DELETE FROM `permissions` WHERE `section` = 'sales_funnel';

DROP TABLE IF EXISTS `funnel_audit_logs`;
DROP TABLE IF EXISTS `funnel_saved_sections`;
DROP TABLE IF EXISTS `funnel_templates`;
DROP TABLE IF EXISTS `funnel_versions`;
DROP TABLE IF EXISTS `funnels`;

DELETE FROM `migrations` WHERE `migration` IN (
  '2026_10_22_100000_create_funnels_table',
  '2026_10_22_100100_create_funnel_versions_table',
  '2026_10_22_100200_create_funnel_templates_and_sections_tables',
  '2026_10_22_100300_create_funnel_audit_logs_table',
  '2026_10_22_100400_add_funnel_permissions'
);
