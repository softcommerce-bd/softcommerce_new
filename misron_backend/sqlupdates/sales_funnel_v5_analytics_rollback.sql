-- Undo sales_funnel_v5_analytics.sql.
--
-- DESTROYS funnel analytics events, order attribution and the record of
-- payment gateway sessions. Orders, payments and stock are not touched.
-- Back up these three tables first if their history matters.

DROP TABLE IF EXISTS `funnel_events`;
DROP TABLE IF EXISTS `funnel_order_attributions`;
DROP TABLE IF EXISTS `payment_gateway_attempts`;

DELETE rp FROM `role_has_permissions` rp JOIN `permissions` p ON p.`id` = rp.`permission_id` WHERE p.`name` = 'view_funnel_analytics';
DELETE mp FROM `model_has_permissions` mp JOIN `permissions` p ON p.`id` = mp.`permission_id` WHERE p.`name` = 'view_funnel_analytics';
DELETE FROM `permissions` WHERE `name` = 'view_funnel_analytics';

DELETE FROM `migrations` WHERE `migration` IN (
  '2026_10_26_100000_create_funnel_events_table',
  '2026_10_26_100100_create_funnel_order_attributions_table',
  '2026_10_26_100200_create_payment_gateway_attempts_table',
  '2026_10_26_100300_add_funnel_analytics_permission'
);
