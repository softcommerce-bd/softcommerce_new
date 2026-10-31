-- Financial / stock fingerprint: must be IDENTICAL before and after masking (masking may not change a number).
SELECT 'orders.grand_total', ROUND(SUM(grand_total),2), COUNT(*) FROM orders UNION ALL
SELECT 'order_details.price', ROUND(SUM(price),2), COUNT(*) FROM order_details UNION ALL
SELECT 'order_details.buying_price', ROUND(SUM(CAST(buying_price AS DECIMAL(20,4))),4), COUNT(*) FROM order_details UNION ALL
SELECT 'product_stocks.qty', SUM(qty), COUNT(*) FROM product_stocks UNION ALL
SELECT 'purchase_products.remaining', SUM(remaining_qty), COUNT(*) FROM purchase_products UNION ALL
SELECT 'purchase_products.cost', ROUND(SUM(remaining_qty*CAST(buying_unit_cost AS DECIMAL(20,4))),2), COUNT(*) FROM purchase_products UNION ALL
SELECT 'purchases.net', ROUND(SUM(CAST(net_total_amount AS DECIMAL(20,2))),2), COUNT(*) FROM purchases UNION ALL
SELECT 'suppliers.due', ROUND(SUM(due_amount),2), COUNT(*) FROM suppliers UNION ALL
SELECT 'chart.current_balance', ROUND(SUM(current_balance),2), COUNT(*) FROM chart_of_accounts UNION ALL
SELECT 'statement.debit', ROUND(SUM(debit),2), COUNT(*) FROM account_statement_logs UNION ALL
SELECT 'statement.credit', ROUND(SUM(credit),2), COUNT(*) FROM account_statement_logs UNION ALL
SELECT 'users', COUNT(*), COUNT(DISTINCT user_type) FROM users UNION ALL
SELECT 'products', COUNT(*), SUM(current_stock) FROM products;
