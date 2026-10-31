-- Inventory v2 / Smart Purchase Manager: schema, permissions, system accounts.
--
-- For hosts without shell access. Where `php artisan` is available run instead:
--   php artisan migrate --path=database/migrations/inventory_v2 --force
-- Use one or the other. This file records the same migrations as applied.
--
-- PREREQUISITES (this file does NOT do them): full backup, and sqlupdates/inventory_v2_innodb_conversion.sql on a restored copy first.
-- Additive: new tables; new nullable/defaulted columns; account_statement_logs.transaction_type widened ENUM -> VARCHAR(50) (every stored
-- value, including blanks, is kept); purchase_orders.status widened to VARCHAR(20). The new purchasing permissions are granted to NO role (secure by default); assign them after importing. Nothing is switched on: INVENTORY_CONFIRMED_ONLY stays false.
-- IMPORT ONCE (the ALTERs are not re-runnable); undo with inventory_v2_rollback.sql.

CREATE TABLE IF NOT EXISTS `idempotency_keys` (`id` bigint unsigned not null auto_increment primary key, `scope` varchar(40) not null, `key` varchar(120) not null, `result` longtext null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `stock_balances` (`id` bigint unsigned not null auto_increment primary key, `product_id` bigint unsigned not null, `variant` varchar(255) not null default '', `demand` int not null default '0', `allocated` int not null default '0', `damaged` int not null default '0', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `order_demands` (`id` bigint unsigned not null auto_increment primary key, `order_id` bigint unsigned not null, `order_detail_id` bigint unsigned not null, `product_id` bigint unsigned not null, `variant` varchar(255) not null default '', `qty_confirmed` int not null, `qty_allocated` int not null default '0', `qty_dispatched` int not null default '0', `qty_cancelled` int not null default '0', `status` varchar(12) not null default 'open', `confirmed_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `stock_movements` (`id` bigint unsigned not null auto_increment primary key, `product_id` bigint unsigned not null, `variant` varchar(255) not null default '', `type` varchar(30) not null, `on_hand_delta` int not null default '0', `demand_delta` int not null default '0', `allocated_delta` int not null default '0', `source_type` varchar(40) null, `source_id` bigint unsigned null, `order_detail_id` bigint unsigned null, `note` varchar(255) null, `created_by` int unsigned null, `created_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `stock_allocations` (`id` bigint unsigned not null auto_increment primary key, `order_demand_id` bigint unsigned not null, `stock_movement_id` bigint unsigned null, `action` varchar(12) not null, `qty` int not null, `fifo_batch_info` varchar(1000) null, `created_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `goods_receipts` (`id` bigint unsigned not null auto_increment primary key, `receipt_no` varchar(40) not null, `purchase_order_id` bigint unsigned null, `supplier_id` bigint unsigned not null, `purchase_id` bigint unsigned null, `received_at` date not null, `goods_total` decimal(20, 4) not null default '0', `landed_cost` decimal(20, 4) not null default '0', `total` decimal(20, 4) not null default '0', `invoice_status` varchar(15) not null default 'not_invoiced', `journal_entry_id` bigint unsigned null, `status` varchar(12) not null default 'posted', `idempotency_key` varchar(120) not null, `created_by` int unsigned null, `note` varchar(500) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `goods_receipt_lines` (`id` bigint unsigned not null auto_increment primary key, `goods_receipt_id` bigint unsigned not null, `purchase_order_detail_id` bigint unsigned null, `product_id` bigint unsigned not null, `variant` varchar(255) not null default '', `qty` int not null, `unit_cost` decimal(20, 4) not null, `landed_unit_cost` decimal(20, 4) not null, `line_total` decimal(20, 4) not null, `purchase_product_id` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `supplier_invoices` (`id` bigint unsigned not null auto_increment primary key, `supplier_id` bigint unsigned not null, `goods_receipt_id` bigint unsigned null, `invoice_no` varchar(80) not null, `invoice_date` date not null, `subtotal` decimal(20, 4) not null, `discount` decimal(20, 4) not null default '0', `tax` decimal(20, 4) not null default '0', `freight` decimal(20, 4) not null default '0', `total` decimal(20, 4) not null, `paid` decimal(20, 4) not null default '0', `price_variance` decimal(20, 4) not null default '0', `journal_entry_id` bigint unsigned null, `status` varchar(12) not null default 'open', `idempotency_key` varchar(120) not null, `created_by` int unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `supplier_invoice_payments` (`id` bigint unsigned not null auto_increment primary key, `supplier_id` bigint unsigned not null, `supplier_invoice_id` bigint unsigned null, `account_id` bigint unsigned not null, `kind` varchar(12) not null, `amount` decimal(20, 4) not null, `paid_on` date not null, `journal_entry_id` bigint unsigned null, `idempotency_key` varchar(120) not null, `created_by` int unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `purchase_requirement_links` (`id` bigint unsigned not null auto_increment primary key, `order_demand_id` bigint unsigned not null, `purchase_order_detail_id` bigint unsigned not null, `qty_linked` int not null, `qty_received` int not null default '0', `status` varchar(12) not null default 'open', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `stock_return_receipts` (`id` bigint unsigned not null auto_increment primary key, `order_detail_id` bigint unsigned not null, `qty` int not null, `condition` varchar(12) not null, `stock_movement_id` bigint unsigned null, `note` varchar(255) null, `idempotency_key` varchar(120) not null, `received_by` int unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `journal_entries` (`id` bigint unsigned not null auto_increment primary key, `entry_no` varchar(40) not null, `entry_date` date not null, `source_type` varchar(40) null, `source_id` bigint unsigned null, `description` varchar(255) null, `reversal_of_id` bigint unsigned null, `idempotency_key` varchar(120) not null, `created_by` int unsigned null, `posted_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS `journal_lines` (`id` bigint unsigned not null auto_increment primary key, `journal_entry_id` bigint unsigned not null, `account_id` bigint unsigned not null, `debit` decimal(20, 2) not null default '0', `credit` decimal(20, 2) not null default '0', `supplier_id` bigint unsigned null, `memo` varchar(255) null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

alter table `idempotency_keys` add unique `idempotency_keys_scope_key_unique`(`scope`, `key`);

alter table `stock_balances` add unique `uq_stock_balances_product_variant`(`product_id`, `variant`);

alter table `order_demands` add unique `uq_order_demands_detail`(`order_detail_id`);

alter table `order_demands` add index `idx_order_demands_alloc`(`product_id`, `variant`, `status`, `confirmed_at`);

alter table `order_demands` add index `order_demands_order_id_index`(`order_id`);

alter table `stock_movements` add index `idx_stock_movements_item`(`product_id`, `variant`, `id`);

alter table `stock_movements` add index `stock_movements_source_type_source_id_index`(`source_type`, `source_id`);

alter table `stock_movements` add index `stock_movements_order_detail_id_index`(`order_detail_id`);

alter table `stock_allocations` add index `stock_allocations_order_demand_id_index`(`order_demand_id`);

alter table `goods_receipts` add index `goods_receipts_purchase_order_id_index`(`purchase_order_id`);

alter table `goods_receipts` add index `goods_receipts_supplier_id_index`(`supplier_id`);

alter table `goods_receipts` add unique `goods_receipts_receipt_no_unique`(`receipt_no`);

alter table `goods_receipts` add unique `goods_receipts_idempotency_key_unique`(`idempotency_key`);

alter table `goods_receipt_lines` add index `goods_receipt_lines_goods_receipt_id_index`(`goods_receipt_id`);

alter table `goods_receipt_lines` add index `goods_receipt_lines_product_id_variant_index`(`product_id`, `variant`);

alter table `supplier_invoices` add unique `uq_supplier_invoice_no`(`supplier_id`, `invoice_no`);

alter table `supplier_invoices` add index `supplier_invoices_goods_receipt_id_index`(`goods_receipt_id`);

alter table `supplier_invoices` add unique `supplier_invoices_idempotency_key_unique`(`idempotency_key`);

alter table `supplier_invoice_payments` add index `supplier_invoice_payments_supplier_id_kind_index`(`supplier_id`, `kind`);

alter table `supplier_invoice_payments` add index `supplier_invoice_payments_supplier_invoice_id_index`(`supplier_invoice_id`);

alter table `supplier_invoice_payments` add unique `supplier_invoice_payments_idempotency_key_unique`(`idempotency_key`);

alter table `purchase_requirement_links` add unique `uq_requirement_link`(`order_demand_id`, `purchase_order_detail_id`);

alter table `purchase_requirement_links` add index `purchase_requirement_links_purchase_order_detail_id_index`(`purchase_order_detail_id`);

alter table `stock_return_receipts` add index `stock_return_receipts_order_detail_id_index`(`order_detail_id`);

alter table `stock_return_receipts` add unique `stock_return_receipts_idempotency_key_unique`(`idempotency_key`);

alter table `journal_entries` add index `journal_entries_source_type_source_id_index`(`source_type`, `source_id`);

alter table `journal_entries` add index `journal_entries_entry_date_index`(`entry_date`);

alter table `journal_entries` add unique `journal_entries_entry_no_unique`(`entry_no`);

alter table `journal_entries` add unique `journal_entries_reversal_of_id_unique`(`reversal_of_id`);

alter table `journal_entries` add unique `journal_entries_idempotency_key_unique`(`idempotency_key`);

alter table `journal_lines` add index `journal_lines_account_id_journal_entry_id_index`(`account_id`, `journal_entry_id`);

alter table `journal_lines` add index `journal_lines_journal_entry_id_index`(`journal_entry_id`);

alter table `journal_lines` add index `journal_lines_supplier_id_index`(`supplier_id`);

alter table `products` add `stock_policy` varchar(12) not null default 'stock_only', add `max_backorder_qty` int unsigned null, add `max_preorder_qty` int unsigned null, add `preorder_available_at` date null, add `preferred_supplier_id` bigint unsigned null, add `estimated_purchase_cost` decimal(20, 4) null, add `supplier_lead_time_days` smallint unsigned null;

alter table `order_details` add `stock_mode` varchar(8) not null default 'legacy', add `cost_status` varchar(12) null, add `provisional_cost` decimal(20, 4) null;

alter table `order_details` add index `idx_order_details_mode`(`stock_mode`, `order_id`);

alter table `order_details` add index `idx_order_details_order`(`order_id`);

alter table `purchase_order_details` add `variant` varchar(255) null;

alter table `purchase_order_details` add index `idx_po_details_product_variant`(`product_id`, `variant`);

-- ENUM -> VARCHAR keeps every stored value, including blanks (the closed ENUM silently blanked values it did not list).
ALTER TABLE `account_statement_logs` MODIFY `transaction_type` VARCHAR(50) NULL;
-- The old purchase-order status only knew 'draft' and 'purchased'.
ALTER TABLE `purchase_orders` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'draft';

INSERT INTO `permissions` (`name`, `section`, `guard_name`, `created_at`, `updated_at`)
SELECT p.name, 'purchasing_inventory', 'web', NOW(), NOW() FROM (
  SELECT 'view_suppliers' AS name UNION ALL
  SELECT 'add_supplier' UNION ALL
  SELECT 'edit_supplier' UNION ALL
  SELECT 'delete_supplier' UNION ALL
  SELECT 'make_supplier_payment' UNION ALL
  SELECT 'view_supplier_payments' UNION ALL
  SELECT 'view_purchases' UNION ALL
  SELECT 'add_purchase' UNION ALL
  SELECT 'edit_purchase' UNION ALL
  SELECT 'delete_purchase' UNION ALL
  SELECT 'manage_purchase_returns' UNION ALL
  SELECT 'view_purchase_orders' UNION ALL
  SELECT 'manage_purchase_orders' UNION ALL
  SELECT 'receive_goods' UNION ALL
  SELECT 'view_purchase_requirements' UNION ALL
  SELECT 'view_drafts' UNION ALL
  SELECT 'manage_drafts' UNION ALL
  SELECT 'view_courier_settlements' UNION ALL
  SELECT 'settle_courier_orders' UNION ALL
  SELECT 'view_stock_adjustments' UNION ALL
  SELECT 'manage_stock_adjustments' UNION ALL
  SELECT 'view_inventory_reports' UNION ALL
  SELECT 'manage_stock_policy' UNION ALL
  SELECT 'manage_returns_receiving'
) p WHERE NOT EXISTS (SELECT 1 FROM `permissions` e WHERE e.`name` = p.name AND e.`guard_name` = 'web');

-- The permissions are granted to NO role here (secure by default). After importing, assign them per role in Staff > Roles, or with
--   php artisan inventory:grant-permissions "<role>" <profile>   (see docs/INVENTORY_PURCHASING_UPGRADE.md for the recommended profiles).

INSERT INTO `chart_of_accounts` (`code`,`name`,`type`,`sub_type`,`opening_balance`,`current_balance`,`status`,`is_deleted`,`description`,`created_at`,`updated_at`)
SELECT 'SYS-INV','Inventory','asset','inventory',0,0,'active',0,'Created by the inventory/purchasing upgrade.',NOW(),NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE (`name`='Inventory' AND `type`='asset') OR `code`='SYS-INV');
INSERT INTO `chart_of_accounts` (`code`,`name`,`type`,`sub_type`,`opening_balance`,`current_balance`,`status`,`is_deleted`,`description`,`created_at`,`updated_at`)
SELECT 'SYS-AP','Accounts Payable','liability','payable',0,0,'active',0,'Created by the inventory/purchasing upgrade.',NOW(),NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE (`name`='Accounts Payable' AND `type`='liability') OR `code`='SYS-AP');
INSERT INTO `chart_of_accounts` (`code`,`name`,`type`,`sub_type`,`opening_balance`,`current_balance`,`status`,`is_deleted`,`description`,`created_at`,`updated_at`)
SELECT 'SYS-GRNI','Goods Received Not Invoiced','liability','payable',0,0,'active',0,'Created by the inventory/purchasing upgrade.',NOW(),NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE (`name`='Goods Received Not Invoiced' AND `type`='liability') OR `code`='SYS-GRNI');
INSERT INTO `chart_of_accounts` (`code`,`name`,`type`,`sub_type`,`opening_balance`,`current_balance`,`status`,`is_deleted`,`description`,`created_at`,`updated_at`)
SELECT 'SYS-PPV','Purchase Price Variance','expense','expense',0,0,'active',0,'Created by the inventory/purchasing upgrade.',NOW(),NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE (`name`='Purchase Price Variance' AND `type`='expense') OR `code`='SYS-PPV');
INSERT INTO `chart_of_accounts` (`code`,`name`,`type`,`sub_type`,`opening_balance`,`current_balance`,`status`,`is_deleted`,`description`,`created_at`,`updated_at`)
SELECT 'SYS-ADV','Supplier Advances','asset','receivable',0,0,'active',0,'Created by the inventory/purchasing upgrade.',NOW(),NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE (`name`='Supplier Advances' AND `type`='asset') OR `code`='SYS-ADV');

INSERT INTO `chart_of_accounts` (`code`,`name`,`type`,`sub_type`,`opening_balance`,`current_balance`,`status`,`is_deleted`,`description`,`created_at`,`updated_at`)
SELECT 'SYS-SUSP','Legacy Posting Suspense','equity','equity',0,0,'active',0,'Created by the inventory/purchasing upgrade.',NOW(),NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE (`name`='Legacy Posting Suspense' AND `type`='equity') OR `code`='SYS-SUSP');
INSERT INTO `chart_of_accounts` (`code`,`name`,`type`,`sub_type`,`opening_balance`,`current_balance`,`status`,`is_deleted`,`description`,`created_at`,`updated_at`)
SELECT 'SYS-OPEN','Inventory Opening Adjustment','equity','equity',0,0,'active',0,'Created by the inventory/purchasing upgrade.',NOW(),NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE (`name`='Inventory Opening Adjustment' AND `type`='equity') OR `code`='SYS-OPEN');

-- Accounting permissions (2026_11_02_100200): granted to NO role.
INSERT INTO `permissions` (`name`, `section`, `guard_name`, `created_at`, `updated_at`)
SELECT p.name, 'purchasing_inventory', 'web', NOW(), NOW() FROM (SELECT 'view_financial_reports' AS name UNION ALL SELECT 'manage_accounting') p
WHERE NOT EXISTS (SELECT 1 FROM `permissions` e WHERE e.`name` = p.name AND e.`guard_name` = 'web');

-- Legacy-ledger bridge (2026_11_02_100000): mirrored journal lines, per-account baseline.
ALTER TABLE `journal_lines` ADD COLUMN `mirrored` tinyint(1) NOT NULL DEFAULT 0 AFTER `memo`;
CREATE TABLE IF NOT EXISTS `ledger_baselines` (`account_id` bigint unsigned not null primary key, `balance` decimal(20,2) not null, `taken_at` timestamp null, `taken_by` varchar(80) null) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Estimated (planning-only) unit cost on uncosted dispatched lines (2026_11_02_100100).
ALTER TABLE `order_details` ADD COLUMN `estimated_unit_cost` decimal(20,4) NULL;

-- Stock-lookup indexes (2026_11_03_100200): added only if no index starting (product_id, variant) exists.
SET @has := (SELECT COUNT(*) FROM information_schema.STATISTICS a JOIN information_schema.STATISTICS b ON a.TABLE_SCHEMA=b.TABLE_SCHEMA AND a.TABLE_NAME=b.TABLE_NAME AND a.INDEX_NAME=b.INDEX_NAME WHERE a.TABLE_SCHEMA=DATABASE() AND a.TABLE_NAME='product_stocks' AND a.SEQ_IN_INDEX=1 AND a.COLUMN_NAME='product_id' AND b.SEQ_IN_INDEX=2 AND b.COLUMN_NAME='variant');
SET @sql := IF(@has = 0, 'ALTER TABLE `product_stocks` ADD KEY `idx_stock_lookup_product_variant` (`product_id`, `variant`(100))', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @has := (SELECT COUNT(*) FROM information_schema.STATISTICS a JOIN information_schema.STATISTICS b ON a.TABLE_SCHEMA=b.TABLE_SCHEMA AND a.TABLE_NAME=b.TABLE_NAME AND a.INDEX_NAME=b.INDEX_NAME WHERE a.TABLE_SCHEMA=DATABASE() AND a.TABLE_NAME='purchase_products' AND a.SEQ_IN_INDEX=1 AND a.COLUMN_NAME='product_id' AND b.SEQ_IN_INDEX=2 AND b.COLUMN_NAME='variant');
SET @sql := IF(@has = 0, 'ALTER TABLE `purchase_products` ADD KEY `idx_stock_lookup_product_variant` (`product_id`, `variant`(100))', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Transactional outbox for legacy ledger postings (2026_11_03_100000).
CREATE TABLE IF NOT EXISTS `ledger_outbox` (`id` bigint unsigned not null auto_increment primary key, `token` varchar(40) not null, `account_id` bigint unsigned not null, `delta` decimal(20,2) not null, `source_type` varchar(40) null, `source_id` bigint unsigned null, `description` varchar(255) null, `created_by` int unsigned null, `created_at` timestamp not null default current_timestamp(), `processed_at` timestamp null, `journal_entry_id` bigint unsigned null, `last_error` varchar(255) null, `attempts` int unsigned not null default 0, KEY `idx_outbox_pending` (`processed_at`, `id`), KEY `idx_outbox_token` (`token`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Refund postings (2026_11_03_100100) and the three accounts refunds post to.
CREATE TABLE IF NOT EXISTS `refund_postings` (`id` bigint unsigned not null auto_increment primary key, `refund_request_id` bigint unsigned null, `order_id` bigint unsigned null, `order_detail_id` bigint unsigned null, `method` varchar(10) not null, `amount` decimal(20,2) not null, `account_id` bigint unsigned null, `external_ref` varchar(120) null, `idempotency_key` varchar(120) not null, `journal_entry_id` bigint unsigned null, `created_by` int unsigned null, `note` varchar(255) null, `created_at` timestamp not null default current_timestamp(), UNIQUE KEY `uq_refund_postings_request` (`refund_request_id`), UNIQUE KEY `uq_refund_postings_external` (`external_ref`), UNIQUE KEY `uq_refund_postings_key` (`idempotency_key`), KEY `refund_postings_order_detail_id_index` (`order_detail_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `chart_of_accounts` (`code`,`name`,`type`,`sub_type`,`opening_balance`,`current_balance`,`status`,`is_deleted`,`description`,`created_at`,`updated_at`)
SELECT 'SYS-SRET','Sales Returns and Refunds','expense','expense',0,0,'active',0,'Created by the inventory/purchasing upgrade.',NOW(),NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE (`name`='Sales Returns and Refunds' AND `type`='expense') OR `code`='SYS-SRET');
INSERT INTO `chart_of_accounts` (`code`,`name`,`type`,`sub_type`,`opening_balance`,`current_balance`,`status`,`is_deleted`,`description`,`created_at`,`updated_at`)
SELECT 'SYS-WAL','Customer Wallet Balances','liability','payable',0,0,'active',0,'Created by the inventory/purchasing upgrade.',NOW(),NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE (`name`='Customer Wallet Balances' AND `type`='liability') OR `code`='SYS-WAL');
INSERT INTO `chart_of_accounts` (`code`,`name`,`type`,`sub_type`,`opening_balance`,`current_balance`,`status`,`is_deleted`,`description`,`created_at`,`updated_at`)
SELECT 'SYS-GWC','Payment Gateway Clearing','asset','receivable',0,0,'active',0,'Created by the inventory/purchasing upgrade.',NOW(),NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE (`name`='Payment Gateway Clearing' AND `type`='asset') OR `code`='SYS-GWC');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.migration, (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`) FROM (
  SELECT '2026_11_01_100000_add_purchasing_permissions' AS migration UNION ALL
  SELECT '2026_11_01_100100_create_inventory_v2_tables' UNION ALL
  SELECT '2026_11_01_100200_add_inventory_v2_columns' UNION ALL
  SELECT '2026_11_02_100000_add_legacy_ledger_bridge' UNION ALL
  SELECT '2026_11_02_100100_add_estimated_unit_cost' UNION ALL
  SELECT '2026_11_02_100200_add_accounting_permissions' UNION ALL
  SELECT '2026_11_03_100000_create_ledger_outbox' UNION ALL
  SELECT '2026_11_03_100100_create_refund_postings' UNION ALL
  SELECT '2026_11_03_100200_add_stock_lookup_indexes'
) m WHERE NOT EXISTS (SELECT 1 FROM `migrations` x WHERE x.`migration` = m.migration);
