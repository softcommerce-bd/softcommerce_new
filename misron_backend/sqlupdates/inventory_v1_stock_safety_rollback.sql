-- Stock safety, step 3 of 3 (only if needed): ROLLBACK of inventory_v1_stock_safety.sql.
--
-- This puts the structure back (indexes dropped, purchase_products back to MyISAM).
-- It does NOT touch row data, so orders placed since the change keep their effect
-- on stock and on purchase batches. That is what you want after a structural
-- rollback. Put the storefront in maintenance mode first.
--
-- The *_pre_inv1 copies are left in place; drop them yourself once you no longer need them:
--   DROP TABLE `purchase_products_pre_inv1`, `product_stocks_pre_inv1`;
--
-- Only if the DATA itself must go back to the moment of the change (rare, loses every
-- order placed since): see "Restore" in docs/sales-funnel/PHASE4_INVENTORY_MIGRATION_PLAN.md.

ALTER TABLE `purchase_products` DROP KEY `idx_purchase_products_product_variant`;
ALTER TABLE `product_stocks` DROP KEY `uq_product_stocks_product_variant`;
ALTER TABLE `purchase_products` ENGINE = MyISAM;

SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('purchase_products', 'product_stocks');
