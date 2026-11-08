-- T-5 financial reconciliation checks. READ ONLY (SELECT only). Run before and after any change window on staging / a restored copy, and keep both outputs.
-- Every row is: check_name, problem_count (0 = clean, "info" rows are figures for comparison before/after), detail. A non-zero count is a finding for the
-- accountant; it does not by itself prove a defect (see docs/T5_N1_ACCOUNTANT_REVIEW_PACKAGE.md for how to read supplier figures).
-- Usage: mysql --batch <database> < tests/staging/reconciliation_checks.sql > reconciliation_<stage>.tsv
SET SESSION TRANSACTION READ ONLY;
SELECT check_name, problem_count, detail FROM (
 SELECT 1 AS o, 'engine: money/stock tables not InnoDB' AS check_name, COUNT(*) AS problem_count, COALESCE(GROUP_CONCAT(TABLE_NAME ORDER BY TABLE_NAME SEPARATOR ','), '') AS detail
   FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND ENGINE <> 'InnoDB'
    AND TABLE_NAME IN ('purchases','purchase_products','suppliers','purchase_returns','purchase_return_products','purchase_orders','purchase_order_details','supplier_payments','supplier_payment_purchases','chart_of_accounts','account_statement_logs','balance_transfers','expense_payments','incomes','vouchers','voucher_details','customer_payments','stock_adjustments','courier_settlements','product_stocks','orders','order_details','idempotency_keys')
 UNION ALL
 SELECT 2, 'journal: entries whose debits <> credits', COUNT(*), COALESCE(GROUP_CONCAT(journal_entry_id ORDER BY journal_entry_id SEPARATOR ',' ), '')
   FROM (SELECT journal_entry_id FROM journal_lines GROUP BY journal_entry_id HAVING ROUND(SUM(debit) - SUM(credit), 2) <> 0 LIMIT 20) x
 UNION ALL
 SELECT 3, 'suppliers: due_amount <> sum of open purchase amounts (opening-due suppliers legitimately differ)', COUNT(*), ''
   FROM suppliers s LEFT JOIN (SELECT supplier_id, SUM(CAST(NULLIF(total_payment_due, '') AS DECIMAL(15,2))) AS open_due FROM purchases GROUP BY supplier_id) p ON p.supplier_id = s.id
  WHERE ABS(s.due_amount - COALESCE(p.open_due, 0)) > 0.009
 UNION ALL
 SELECT 4, 'purchases: net - paid - due <> 0', COUNT(*), ''
   FROM purchases WHERE ABS(net_total_amount - total_paid - CAST(NULLIF(total_payment_due, '') AS DECIMAL(15,2))) > 0.009
 UNION ALL
 SELECT 5, 'supplier payments: live payment not fully allocated (advances are legitimate)', COUNT(*), ''
   FROM supplier_payments sp LEFT JOIN (SELECT supplier_payment_id, SUM(paid_amount) a FROM supplier_payment_purchases GROUP BY supplier_payment_id) al ON al.supplier_payment_id = sp.id
  WHERE sp.deleted_at IS NULL AND ABS(sp.amount - COALESCE(al.a, 0)) > 0.009
 UNION ALL
 SELECT 6, 'supplier payments: allocation rows of deleted payments (orphans)', COUNT(*), ''
   FROM supplier_payment_purchases spp JOIN supplier_payments sp ON sp.id = spp.supplier_payment_id WHERE sp.deleted_at IS NOT NULL
 UNION ALL
 SELECT 7, 'stock: FIFO layer units <> shelf quantity per product/variant', COUNT(*), ''
   FROM (SELECT s.product_id, s.variant FROM (SELECT product_id, COALESCE(variant,'') v, variant, SUM(qty) q FROM product_stocks GROUP BY product_id, variant) s
         LEFT JOIN (SELECT product_id, COALESCE(variant,'') v, SUM(remaining_qty) r FROM purchase_products GROUP BY product_id, COALESCE(variant,'')) l ON l.product_id = s.product_id AND l.v = s.v
         WHERE s.q <> COALESCE(l.r, 0)) m
 UNION ALL
 SELECT 8, 'stock: negative shelf quantities', COUNT(*), '' FROM product_stocks WHERE qty < 0
 UNION ALL
 SELECT 9, 'numbers: duplicated income / expense / transfer / voucher / customer-payment / adjustment / purchase numbers',
   (SELECT COUNT(*) FROM (SELECT voucher_no FROM incomes GROUP BY voucher_no HAVING COUNT(*) > 1) a)
 + (SELECT COUNT(*) FROM (SELECT voucher_no FROM expense_payments GROUP BY voucher_no HAVING COUNT(*) > 1) b)
 + (SELECT COUNT(*) FROM (SELECT voucher_no FROM balance_transfers GROUP BY voucher_no HAVING COUNT(*) > 1) c)
 + (SELECT COUNT(*) FROM (SELECT voucher_no FROM vouchers GROUP BY voucher_no HAVING COUNT(*) > 1) d)
 + (SELECT COUNT(*) FROM (SELECT payment_no FROM customer_payments GROUP BY payment_no HAVING COUNT(*) > 1) e)
 + (SELECT COUNT(*) FROM (SELECT reference_no FROM stock_adjustments GROUP BY reference_no HAVING COUNT(*) > 1) f)
 + (SELECT COUNT(*) FROM (SELECT reference_no FROM purchases GROUP BY reference_no HAVING COUNT(*) > 1) g), ''
 UNION ALL
 SELECT 10, 'enum: blank type/status written by a screen (illegal ENUM value stored as blank)',
   (SELECT COUNT(*) FROM balance_transfers WHERE transaction_type = '' OR status = '') + (SELECT COUNT(*) FROM expense_payments WHERE status = ''), 'see docs/T5_R03_R04_AUTO_DEPOSIT_TRACE.md NB-6'
 UNION ALL
 SELECT 11, 'auto-deposit traces (AD- vouchers, active auto methods) - expected 0 while the job is paused',
   (SELECT COUNT(*) FROM balance_transfers WHERE voucher_no LIKE 'AD-%') + 0 + (SELECT COUNT(*) FROM transaction_methods WHERE deposit_type = 'auto' AND status = 'active'), ''
 UNION ALL
 SELECT 12, 'outbox: committed legacy postings still waiting', COUNT(*), '' FROM ledger_outbox WHERE processed_at IS NULL
 UNION ALL
 SELECT 13, 'info: sum of supplier due / paid', ROUND(SUM(due_amount), 2), CONCAT('paid=', ROUND(SUM(paid_amount), 2)) FROM suppliers
 UNION ALL
 SELECT 14, 'info: sum of chart_of_accounts.current_balance', ROUND(SUM(current_balance), 2), CONCAT(COUNT(*), ' accounts') FROM chart_of_accounts
 UNION ALL
 SELECT 15, 'info: FIFO layer units / valued at remaining cost', COALESCE(SUM(remaining_qty), 0), CONCAT('value=', ROUND(COALESCE(SUM(remaining_qty * CAST(buying_unit_cost AS DECIMAL(20,4))), 0), 2)) FROM purchase_products
) t ORDER BY o;
