-- Unified Chat, schema + permissions.
--
-- For hosts without shell access (phpMyAdmin import). Where `php artisan` is
-- available, run instead:
--   php artisan migrate --path=database/migrations/unified_chat --force
-- Use one or the other. This file records the same migrations as applied, so a
-- later artisan run skips them.
--
-- Safe to import twice: tables are IF NOT EXISTS and permission/migration rows
-- are inserted only when missing. Verified on MariaDB 10.11.
-- Additive only: ten new chat_* tables and twelve new permission rows. No
-- existing table or row is altered. Undo with unified_chat_v1_rollback.sql.
--
-- After importing, use "Clear Cache" in the admin panel so the new permissions
-- show up in Staff > Roles (section "Unified Chat Management").

CREATE TABLE IF NOT EXISTS `chat_accounts` (`id` bigint unsigned not null auto_increment primary key, `platform` varchar(20) not null, `external_id` varchar(100) not null, `name` varchar(190) not null, `handle` varchar(190) null, `status` varchar(20) not null default 'connected', `credentials` text null, `token_expires_at` timestamp null, `inbox_enabled` tinyint(1) not null default '1', `last_webhook_at` timestamp null, `last_message_at` timestamp null, `last_api_ok_at` timestamp null, `last_sync_at` timestamp null, `last_error` varchar(500) null, `meta` text null, `connected_by` int unsigned null, `created_at` timestamp null, `updated_at` timestamp null,
  UNIQUE KEY `chat_accounts_platform_external_id_unique` (`platform`, `external_id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `chat_account_user` (`id` bigint unsigned not null auto_increment primary key, `chat_account_id` bigint unsigned not null, `user_id` int unsigned not null, `created_at` timestamp null, `updated_at` timestamp null,
  UNIQUE KEY `chat_account_user_chat_account_id_user_id_unique` (`chat_account_id`, `user_id`),
  KEY `chat_account_user_user_id_index` (`user_id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `chat_conversations` (`id` bigint unsigned not null auto_increment primary key, `chat_account_id` bigint unsigned not null, `platform` varchar(20) not null, `external_id` varchar(150) not null, `customer_name` varchar(190) null, `customer_handle` varchar(190) null, `customer_avatar` varchar(500) null, `customer_phone` varchar(40) null, `user_id` int unsigned null, `status` varchar(20) not null default 'open', `priority` varchar(10) not null default 'normal', `assigned_to` int unsigned null, `unread_count` int unsigned not null default '0', `last_message_preview` varchar(200) null, `last_message_at` timestamp null, `last_inbound_at` timestamp null, `first_response_at` timestamp null, `resolved_at` timestamp null, `tags` text null, `customer_notes` text null, `created_at` timestamp null, `updated_at` timestamp null,
  UNIQUE KEY `chat_conversations_chat_account_id_external_id_unique` (`chat_account_id`, `external_id`),
  KEY `chat_conversations_status_last_message_at_index` (`status`, `last_message_at`),
  KEY `chat_conversations_assigned_to_status_index` (`assigned_to`, `status`),
  KEY `chat_conversations_chat_account_id_last_message_at_index` (`chat_account_id`, `last_message_at`),
  KEY `chat_conversations_user_id_index` (`user_id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `chat_messages` (`id` bigint unsigned not null auto_increment primary key, `chat_conversation_id` bigint unsigned not null, `chat_account_id` bigint unsigned not null, `external_id` varchar(190) null, `client_ref` varchar(64) null, `direction` varchar(3) not null, `type` varchar(20) not null default 'text', `body` text null, `attachments` text null, `status` varchar(20) not null default 'received', `error` varchar(500) null, `sent_by` int unsigned null, `retry_count` int unsigned not null default '0', `provider_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null,
  UNIQUE KEY `chat_messages_chat_account_id_external_id_unique` (`chat_account_id`, `external_id`),
  UNIQUE KEY `chat_messages_chat_conversation_id_client_ref_unique` (`chat_conversation_id`, `client_ref`),
  KEY `chat_messages_chat_conversation_id_id_index` (`chat_conversation_id`, `id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `chat_notes` (`id` bigint unsigned not null auto_increment primary key, `chat_conversation_id` bigint unsigned not null, `user_id` int unsigned not null, `body` text not null, `created_at` timestamp null, `updated_at` timestamp null,
  KEY `chat_notes_chat_conversation_id_index` (`chat_conversation_id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `chat_saved_replies` (`id` bigint unsigned not null auto_increment primary key, `title` varchar(120) not null, `body` text not null, `lang` varchar(5) not null default 'en', `created_by` int unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `chat_settings` (`key` varchar(100) not null, `value` longtext null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `chat_webhook_events` (`id` bigint unsigned not null auto_increment primary key, `source` varchar(12) not null, `platform` varchar(20) not null, `event_key` varchar(190) not null, `payload` longtext not null, `status` varchar(12) not null default 'pending', `attempts` smallint unsigned not null default '0', `error` varchar(500) null, `processed_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null,
  UNIQUE KEY `chat_webhook_events_platform_event_key_unique` (`platform`, `event_key`),
  KEY `chat_webhook_events_status_created_at_index` (`status`, `created_at`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `chat_conversation_orders` (`id` bigint unsigned not null auto_increment primary key, `chat_conversation_id` bigint unsigned not null, `combined_order_id` bigint unsigned not null, `idempotency_key` varchar(64) not null, `created_by` int unsigned null, `created_at` timestamp null, `updated_at` timestamp null,
  KEY `chat_conversation_orders_chat_conversation_id_index` (`chat_conversation_id`),
  KEY `chat_conversation_orders_combined_order_id_index` (`combined_order_id`),
  UNIQUE KEY `chat_conversation_orders_idempotency_key_unique` (`idempotency_key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `chat_audit_logs` (`id` bigint unsigned not null auto_increment primary key, `user_id` int unsigned null, `action` varchar(60) not null, `subject_type` varchar(40) null, `subject_id` bigint unsigned null, `context` text null, `ip` varchar(45) null, `created_at` timestamp null,
  KEY `chat_audit_logs_subject_type_subject_id_index` (`subject_type`, `subject_id`),
  KEY `chat_audit_logs_created_at_index` (`created_at`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

INSERT INTO `permissions` (`name`, `section`, `guard_name`, `created_at`, `updated_at`)
SELECT p.name, 'unified_chat_management', 'web', NOW(), NOW()
FROM (
  SELECT 'view_unified_chat' AS name UNION ALL
  SELECT 'view_all_chats' UNION ALL
  SELECT 'view_assigned_chats' UNION ALL
  SELECT 'reply_unified_chat' UNION ALL
  SELECT 'assign_unified_chat' UNION ALL
  SELECT 'manage_chat_accounts' UNION ALL
  SELECT 'manage_chat_settings' UNION ALL
  SELECT 'manage_chat_automation' UNION ALL
  SELECT 'view_chat_analytics' UNION ALL
  SELECT 'create_order_from_chat' UNION ALL
  SELECT 'manage_chat_notes' UNION ALL
  SELECT 'export_chat_data'
) p
WHERE NOT EXISTS (
  SELECT 1 FROM `permissions` e WHERE e.`name` = p.name AND e.`guard_name` = 'web'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.migration, (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`)
FROM (
  SELECT '2026_10_30_100000_create_unified_chat_tables' AS migration UNION ALL
  SELECT '2026_10_30_100100_add_unified_chat_permissions'
) m
WHERE NOT EXISTS (SELECT 1 FROM `migrations` x WHERE x.`migration` = m.migration);
