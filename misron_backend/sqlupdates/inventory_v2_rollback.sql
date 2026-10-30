-- Undo inventory_v2.sql. DESTROYS the v2 ledgers (movements, demand, receipts, invoices, journal) and the new columns.
-- Take a backup first. Set INVENTORY_CONFIRMED_ONLY=false BEFORE running this and make sure no order line is still stock_mode='v2'
-- (their stock lives only in these tables). Journal postings already mirrored into chart_of_accounts.current_balance are NOT reversed here.
-- The ENUM on account_statement_logs.transaction_type is deliberately not restored (narrowing it would blank values written since).

SELECT COUNT(*) AS v2_lines_still_open FROM `order_details` WHERE `stock_mode` = 'v2';  -- must be 0 before continuing
DROP TABLE IF EXISTS `ledger_baselines`;
DROP TABLE IF EXISTS `journal_lines`;
DROP TABLE IF EXISTS `journal_entries`;
DROP TABLE IF EXISTS `stock_return_receipts`;
DROP TABLE IF EXISTS `purchase_requirement_links`;
DROP TABLE IF EXISTS `supplier_invoice_payments`;
DROP TABLE IF EXISTS `supplier_invoices`;
DROP TABLE IF EXISTS `goods_receipt_lines`;
DROP TABLE IF EXISTS `goods_receipts`;
DROP TABLE IF EXISTS `stock_allocations`;
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `order_demands`;
DROP TABLE IF EXISTS `stock_balances`;
DROP TABLE IF EXISTS `idempotency_keys`;

ALTER TABLE `order_details` DROP INDEX `idx_order_details_mode`, DROP INDEX `idx_order_details_order`, DROP COLUMN `stock_mode`, DROP COLUMN `cost_status`, DROP COLUMN `provisional_cost`, DROP COLUMN `estimated_unit_cost`;
ALTER TABLE `purchase_order_details` DROP INDEX `idx_po_details_product_variant`, DROP COLUMN `variant`;
ALTER TABLE `products` DROP COLUMN `stock_policy`, DROP COLUMN `max_backorder_qty`, DROP COLUMN `max_preorder_qty`, DROP COLUMN `preorder_available_at`, DROP COLUMN `preferred_supplier_id`, DROP COLUMN `estimated_purchase_cost`, DROP COLUMN `supplier_lead_time_days`;

DELETE rp FROM `role_has_permissions` rp JOIN `permissions` p ON p.id = rp.permission_id WHERE p.`section` = 'purchasing_inventory';
DELETE mp FROM `model_has_permissions` mp JOIN `permissions` p ON p.id = mp.permission_id WHERE p.`section` = 'purchasing_inventory';
DELETE FROM `permissions` WHERE `section` = 'purchasing_inventory';
DELETE FROM `migrations` WHERE `migration` IN ('2026_11_01_100000_add_purchasing_permissions', '2026_11_01_100100_create_inventory_v2_tables', '2026_11_01_100200_add_inventory_v2_columns', '2026_11_02_100000_add_legacy_ledger_bridge', '2026_11_02_100100_add_estimated_unit_cost', '2026_11_02_100200_add_accounting_permissions');
-- System accounts (GRNI, Legacy Posting Suspense, Inventory Opening Adjustment, Purchase Price Variance, Supplier Advances) are left in place: remove them by hand only if they carry no balance.
