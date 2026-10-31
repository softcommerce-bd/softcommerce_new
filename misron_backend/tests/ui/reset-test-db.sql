-- Test-only: clears the v2 ledgers, orders, purchases and system-account balances of an isolated TEST database (never run on a real one).
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE journal_lines; TRUNCATE journal_entries; TRUNCATE goods_receipt_lines; TRUNCATE goods_receipts; TRUNCATE supplier_invoice_payments; TRUNCATE supplier_invoices; TRUNCATE idempotency_keys;
TRUNCATE stock_movements; TRUNCATE stock_allocations; TRUNCATE order_demands; TRUNCATE purchase_requirement_links; TRUNCATE purchase_order_details; TRUNCATE purchase_orders; TRUNCATE stock_balances; TRUNCATE ledger_baselines;
DELETE FROM order_details; DELETE FROM orders; DELETE FROM combined_orders; DELETE FROM carts; DELETE FROM purchase_products; DELETE FROM purchases; DELETE FROM account_statement_logs;
DELETE FROM suppliers WHERE business_name LIKE 'E2E Supplier%' OR business_name = 'Test Supplier';
DELETE FROM chart_of_accounts WHERE code LIKE 'E2E-%' OR code LIKE 'T-%' OR code LIKE 'R-%' OR code LIKE 'SP-%' OR code LIKE 'L-CASH-%' OR code LIKE 'P-CASH-%';
UPDATE chart_of_accounts SET current_balance=0 WHERE code LIKE 'SYS-%' OR code IN ('1-15001') OR name IN ('Sales','Cost of Goods Sold','Accounts Receivable');
UPDATE product_stocks SET qty = 0; UPDATE products SET current_stock = 0;
SET FOREIGN_KEY_CHECKS=1;
ALTER TABLE payment_methods AUTO_INCREMENT=1; -- the funnel tests delete+insert a row per test; the id column is tiny and runs out
