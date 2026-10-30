-- Undo unified_chat_v1.sql. DESTROYS all stored chat conversations, messages and settings.
-- Take a backup first. Staff roles lose the chat permissions.

DELETE rp FROM `role_has_permissions` rp JOIN `permissions` p ON p.id = rp.permission_id WHERE p.`section` = 'unified_chat_management';
DELETE mp FROM `model_has_permissions` mp JOIN `permissions` p ON p.id = mp.permission_id WHERE p.`section` = 'unified_chat_management';
DELETE FROM `permissions` WHERE `section` = 'unified_chat_management';

DROP TABLE IF EXISTS `chat_audit_logs`;
DROP TABLE IF EXISTS `chat_conversation_orders`;
DROP TABLE IF EXISTS `chat_webhook_events`;
DROP TABLE IF EXISTS `chat_settings`;
DROP TABLE IF EXISTS `chat_saved_replies`;
DROP TABLE IF EXISTS `chat_notes`;
DROP TABLE IF EXISTS `chat_messages`;
DROP TABLE IF EXISTS `chat_conversations`;
DROP TABLE IF EXISTS `chat_account_user`;
DROP TABLE IF EXISTS `chat_accounts`;

DELETE FROM `migrations` WHERE `migration` IN ('2026_10_30_100000_create_unified_chat_tables', '2026_10_30_100100_add_unified_chat_permissions');
