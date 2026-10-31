-- =====================================================================================================================
-- T-5 PHASE 0: READ-ONLY DATA AUDIT QUERIES
-- =====================================================================================================================
-- Purpose  : detect duplicates, inconsistencies and orphans BEFORE any T-5 remediation (and before unique indexes M-6/M-7/M-8
--            of docs/T5_REMEDIATION_PLAN.md are even considered).
-- Safety   : every statement is a SELECT. The session is opened READ ONLY, so the server refuses any write. No query returns
--            names, phones, e-mail addresses, street addresses or free-text notes: only ids, counts, document numbers and
--            money amounts. Do not export results outside the authorised environment. NEVER run this against production;
--            run it only on an authorised, isolated copy of a production backup (status today: EXECUTION BLOCKED, no copy
--            has been provided). It has been run only for syntax/column validation on the disposable synthetic database
--            `inv_v2_test` (empty tables), see docs/T5_PHASE0_AUDIT_REPORT.md section 5.
-- Use      : mysql --force -t <isolated_copy_database> < docs/T5_PHASE0_AUDIT_QUERIES.sql > audit_result.txt
--            Every query is expected to return ZERO rows unless its comment says "report" (a listing or a histogram).
--            A non-empty "expected zero" result is a FINDING to be reviewed by accounting; nothing is to be repaired by script.
-- Assumptions are marked ASSUMPTION; sections marked [v2] need the inventory_v2 tables and are skipped (errors ignored with
--            --force) on a database that has not had those migrations.
-- =====================================================================================================================
SET SESSION TRANSACTION READ ONLY;
START TRANSACTION READ ONLY;

-- ---------------------------------------------------------------------------------------------------------------------
-- 0. ENVIRONMENT EVIDENCE (report) -- the information that is BLOCKED for production today
-- ---------------------------------------------------------------------------------------------------------------------
SELECT VERSION() AS version, @@version_comment AS comment, @@default_storage_engine AS default_engine, @@tx_isolation AS isolation_mariadb, @@innodb_lock_wait_timeout AS lock_wait_timeout, @@lower_case_table_names AS lctn, @@character_set_server AS charset, @@collation_server AS collation;
SELECT @@sql_mode AS sql_mode;
SELECT table_name, engine, row_format, table_rows, ROUND((data_length + index_length) / 1048576, 1) AS size_mb
FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY engine, table_name;                                -- report
SELECT table_name, engine FROM information_schema.tables WHERE table_schema = DATABASE() AND engine <> 'InnoDB' ORDER BY table_name;                    -- report: every non-InnoDB table
SELECT table_name, engine FROM information_schema.tables WHERE table_schema = DATABASE() AND engine <> 'InnoDB'
  AND table_name IN ('purchases','purchase_products','purchase_returns','purchase_return_products','suppliers','supplier_payments','supplier_payment_purchases','product_stocks',
  'orders','order_details','combined_orders','carts','addresses','chart_of_accounts','account_statement_logs','vouchers','voucher_details','expense_payments','incomes','balance_transfers',
  'bank_transactions','customer_payments','order_payment_mappings','courier_settlements','courier_settlement_details','stock_adjustments','stock_adjustment_lines','purchase_orders',
  'purchase_order_details','idempotency_keys','journal_entries','journal_lines','ledger_outbox','users');                                                  -- expected zero rows
SELECT table_name, constraint_name, referenced_table_name FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() ORDER BY 1;     -- report: foreign keys
SELECT table_name, index_name, non_unique, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS cols FROM information_schema.statistics
WHERE table_schema = DATABASE() AND table_name IN ('carts','combined_orders','orders','order_details','purchases','suppliers','account_statement_logs','supplier_payment_purchases','addresses','purchase_returns','purchase_orders','order_payment_mappings')
GROUP BY table_name, index_name, non_unique ORDER BY table_name, index_name;                                                                              -- report: indexes on the lock/lookup tables
SELECT COUNT(*) AS has_idempotency_keys FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'idempotency_keys';              -- 1 expected after the v2 migrations

-- ---------------------------------------------------------------------------------------------------------------------
-- A. DUPLICATE DOCUMENT NUMBERS (expected zero rows each)
-- ---------------------------------------------------------------------------------------------------------------------
SELECT 'supplier_payments.payment_no' AS what, payment_no AS doc_no, COUNT(*) AS n FROM supplier_payments GROUP BY payment_no HAVING n > 1;
SELECT 'vouchers.voucher_no', voucher_no, COUNT(*) n FROM vouchers GROUP BY voucher_no HAVING n > 1;
SELECT 'incomes.voucher_no', voucher_no, COUNT(*) n FROM incomes GROUP BY voucher_no HAVING n > 1;
SELECT 'expense_payments.voucher_no', voucher_no, COUNT(*) n FROM expense_payments GROUP BY voucher_no HAVING n > 1;
SELECT 'balance_transfers.voucher_no', voucher_no, COUNT(*) n FROM balance_transfers GROUP BY voucher_no HAVING n > 1;
SELECT 'bank_transactions.transaction_no', transaction_no, COUNT(*) n FROM bank_transactions GROUP BY transaction_no HAVING n > 1;
SELECT 'customer_payments.payment_no', payment_no, COUNT(*) n FROM customer_payments GROUP BY payment_no HAVING n > 1;
SELECT 'stock_adjustments.reference_no', reference_no, COUNT(*) n FROM stock_adjustments GROUP BY reference_no HAVING n > 1;
SELECT 'courier_settlements.settlement_no', settlement_no, COUNT(*) n FROM courier_settlements GROUP BY settlement_no HAVING n > 1;
SELECT 'purchases.reference_no (candidate unique index M-8)', reference_no, COUNT(*) n FROM purchases GROUP BY reference_no HAVING n > 1;
SELECT 'purchase_orders.reference_no (M-8)', reference_no, COUNT(*) n FROM purchase_orders GROUP BY reference_no HAVING n > 1;
SELECT 'purchase_returns.reference_no (M-8)', reference_no, COUNT(*) n FROM purchase_returns GROUP BY reference_no HAVING n > 1;
SELECT 'orders.code (candidate unique index M-6)', code, COUNT(*) n FROM orders GROUP BY code HAVING n > 1;
-- NOTE: combined_orders has NO `code` column in the audited schema (columns: id,user_id,temp_user_id,shipping_address,grand_total,timestamps), so the
-- plan's M-6 mention of combined_orders.code does not apply (correction recorded in docs/T5_PHASE0_AUDIT_REPORT.md).
SELECT 'journal_entries.entry_no', entry_no, COUNT(*) n FROM journal_entries GROUP BY entry_no HAVING n > 1;                                               -- [v2]
SELECT 'goods_receipts.receipt_no', receipt_no, COUNT(*) n FROM goods_receipts GROUP BY receipt_no HAVING n > 1;                                          -- [v2]
SELECT 'chart_of_accounts.code', code, COUNT(*) n FROM chart_of_accounts GROUP BY code HAVING n > 1;
SELECT 'cost_centers.code', code, COUNT(*) n FROM cost_centers GROUP BY code HAVING n > 1;
-- Numbers that do not match the documented formats (report; format drift would break the "next number" generators):
SELECT 'supplier_payments bad format' AS what, id, payment_no FROM supplier_payments WHERE payment_no NOT REGEXP '^PAY-[0-9]{6,}$';
SELECT 'courier_settlements bad format', id, settlement_no FROM courier_settlements WHERE settlement_no NOT REGEXP '^STL-[0-9]{8}-[0-9]{4}$';
SELECT 'customer_payments bad format', id, payment_no FROM customer_payments WHERE payment_no NOT REGEXP '^CUS[0-9]{6,}$';
SELECT 'stock_adjustments bad format', id, reference_no FROM stock_adjustments WHERE reference_no NOT REGEXP '^SA[0-9]{4}/[0-9]{4}$';
SELECT 'incomes bad format', id, voucher_no FROM incomes WHERE voucher_no NOT REGEXP '^INC-[0-9]{2}[0-9]{6}$';
SELECT 'expense_payments bad format', id, voucher_no FROM expense_payments WHERE voucher_no NOT REGEXP '^EXP-[0-9]{2}[0-9]{6}$';
SELECT 'vouchers bad format', id, voucher_no FROM vouchers WHERE voucher_no NOT REGEXP '^(CP|BP|CR|BR|JV|CV)-[0-9]{4}[0-9]{4}$';
SELECT 'purchases not Ref-N (report: legacy references)', COUNT(*) AS n FROM purchases WHERE reference_no NOT REGEXP '^Ref-[0-9]+$';

-- ---------------------------------------------------------------------------------------------------------------------
-- B. DUPLICATE FINANCIAL OPERATIONS (candidates; each hit is a question for accounting, not proof)
-- ---------------------------------------------------------------------------------------------------------------------
-- B1 more than one live courier settlement per order (candidate unique index M-7). Expected zero.
SELECT order_id, COUNT(*) AS settlements, GROUP_CONCAT(id ORDER BY id) AS settlement_ids FROM courier_settlements GROUP BY order_id HAVING settlements > 1;
-- B2 order flagged settled but no settlement row / settlement row for an order not flagged settled. Expected zero.
SELECT o.id AS order_id, 'flag settled, no settlement' AS problem FROM orders o WHERE o.settlement_status IN ('true','1') AND NOT EXISTS (SELECT 1 FROM courier_settlements s WHERE s.order_id = o.id);
SELECT s.order_id, 'settlement exists, order not flagged' FROM courier_settlements s JOIN orders o ON o.id = s.order_id WHERE o.settlement_status NOT IN ('true','1') OR o.settlement_status IS NULL;
-- B3 same supplier + amount + account + date + reference paid twice within 2 minutes (double submit candidates).
SELECT a.id AS payment_a, b.id AS payment_b, a.supplier_id, a.amount, a.payment_date, a.created_at AS a_at, b.created_at AS b_at
FROM supplier_payments a JOIN supplier_payments b ON b.id > a.id AND b.supplier_id = a.supplier_id AND b.amount = a.amount AND b.payment_date = a.payment_date
 AND COALESCE(b.payment_account_id, 0) = COALESCE(a.payment_account_id, 0) AND ABS(TIMESTAMPDIFF(SECOND, a.created_at, b.created_at)) <= 120;
-- B4 same customer + amount + date within 2 minutes.
SELECT a.id AS payment_a, b.id AS payment_b, a.customer_id, a.received_amount, a.payment_date
FROM customer_payments a JOIN customer_payments b ON b.id > a.id AND b.customer_id = a.customer_id AND b.received_amount = a.received_amount AND b.payment_date = a.payment_date AND ABS(TIMESTAMPDIFF(SECOND, a.created_at, b.created_at)) <= 120;
-- B5 expense / income / transfer / bank rows identical in account, amount, date, creator within 2 minutes.
SELECT 'expense' AS kind, a.id, b.id AS dup_id, a.total_amount AS amount FROM expense_payments a JOIN expense_payments b ON b.id > a.id AND b.payment_account_id = a.payment_account_id AND b.total_amount = a.total_amount AND b.date = a.date AND b.created_by = a.created_by AND ABS(TIMESTAMPDIFF(SECOND, a.created_at, b.created_at)) <= 120;
SELECT 'income', a.id, b.id, a.total_amount FROM incomes a JOIN incomes b ON b.id > a.id AND b.received_account_id = a.received_account_id AND b.total_amount = a.total_amount AND b.date = a.date AND b.created_by = a.created_by AND ABS(TIMESTAMPDIFF(SECOND, a.created_at, b.created_at)) <= 120;
SELECT 'transfer', a.id, b.id, a.amount FROM balance_transfers a JOIN balance_transfers b ON b.id > a.id AND b.from_account_id = a.from_account_id AND b.to_account_id = a.to_account_id AND b.amount = a.amount AND b.date = a.date AND b.created_by = a.created_by AND ABS(TIMESTAMPDIFF(SECOND, a.created_at, b.created_at)) <= 120;
SELECT 'bank', a.id, b.id, a.amount FROM bank_transactions a JOIN bank_transactions b ON b.id > a.id AND b.type = a.type AND COALESCE(b.from_account_id,0) = COALESCE(a.from_account_id,0) AND COALESCE(b.to_account_id,0) = COALESCE(a.to_account_id,0) AND b.amount = a.amount AND b.transaction_date = a.transaction_date AND ABS(TIMESTAMPDIFF(SECOND, a.created_at, b.created_at)) <= 120;
-- B6 purchases with same supplier, date, total, item count within 5 minutes.
SELECT a.id AS purchase_a, b.id AS purchase_b, a.supplier_id, a.net_total_amount, a.purchase_date FROM purchases a JOIN purchases b ON b.id > a.id AND b.supplier_id = a.supplier_id AND b.purchase_date = a.purchase_date AND b.net_total_amount = a.net_total_amount AND b.total_item = a.total_item AND ABS(TIMESTAMPDIFF(SECOND, a.created_at, b.created_at)) <= 300;
-- B7 orders: same user, same total, created within 15 seconds (the double-submit pattern seen in the D6 test). Counts only.
SELECT a.id AS order_a, b.id AS order_b, a.user_id, a.grand_total FROM orders a JOIN orders b ON b.id > a.id AND b.user_id = a.user_id AND b.grand_total = a.grand_total AND ABS(TIMESTAMPDIFF(SECOND, a.created_at, b.created_at)) <= 15;
-- B8 duplicate allocation rows.
SELECT supplier_payment_id, purchase_id, COUNT(*) n FROM supplier_payment_purchases GROUP BY supplier_payment_id, purchase_id HAVING n > 1;
SELECT customer_payment_id, order_id, COUNT(*) n FROM order_payment_mappings GROUP BY customer_payment_id, order_id HAVING n > 1;

-- ---------------------------------------------------------------------------------------------------------------------
-- C. DUPLICATE PAYMENT REFERENCES (non-empty external references used more than once)
-- ---------------------------------------------------------------------------------------------------------------------
SELECT 'supplier_payments.reference_no' AS what, reference_no AS ref, COUNT(*) n FROM supplier_payments WHERE reference_no IS NOT NULL AND reference_no <> '' GROUP BY reference_no HAVING n > 1;
SELECT 'supplier_payments.transaction_no', transaction_no, COUNT(*) FROM supplier_payments WHERE transaction_no IS NOT NULL AND transaction_no <> '' GROUP BY transaction_no HAVING COUNT(*) > 1;
SELECT 'customer_payments.reference_no', reference_no, COUNT(*) FROM customer_payments WHERE reference_no IS NOT NULL AND reference_no <> '' GROUP BY reference_no HAVING COUNT(*) > 1;
SELECT 'bank_transactions.reference', reference, COUNT(*) FROM bank_transactions WHERE reference IS NOT NULL AND reference <> '' GROUP BY reference HAVING COUNT(*) > 1;
SELECT 'vouchers.reference_no', reference_no, COUNT(*) FROM vouchers WHERE reference_no IS NOT NULL AND reference_no <> '' GROUP BY reference_no HAVING COUNT(*) > 1;
SELECT 'balance_transfers.reference_no', reference_no, COUNT(*) FROM balance_transfers WHERE reference_no IS NOT NULL AND reference_no <> '' GROUP BY reference_no HAVING COUNT(*) > 1;
SELECT 'refund_postings.external_ref', external_ref, COUNT(*) FROM refund_postings WHERE external_ref IS NOT NULL GROUP BY external_ref HAVING COUNT(*) > 1;   -- [v2] unique index exists: expected zero

-- ---------------------------------------------------------------------------------------------------------------------
-- D. DUPLICATE / UNBALANCED STATEMENT AND LEDGER ENTRIES
-- ---------------------------------------------------------------------------------------------------------------------
-- D1 identical statement lines (same account, reference, type, source, amounts).
SELECT account_id, reference_no, transaction_type, source_type, source_id, debit, credit, COUNT(*) n, GROUP_CONCAT(id ORDER BY id) AS ids
FROM account_statement_logs GROUP BY account_id, reference_no, transaction_type, source_type, source_id, debit, credit HAVING n > 1;
-- D2 statement lines whose account no longer exists / whose order no longer exists.
SELECT l.id, l.account_id, 'missing account' AS problem FROM account_statement_logs l LEFT JOIN chart_of_accounts a ON a.id = l.account_id WHERE a.id IS NULL;
SELECT l.id, l.order_id, 'missing order' FROM account_statement_logs l LEFT JOIN orders o ON o.id = l.order_id WHERE l.order_id IS NOT NULL AND o.id IS NULL;
-- D3 journal entries that do not balance, journal lines without entry, entries without lines. [v2]  Expected zero.
SELECT journal_entry_id, SUM(debit) AS debit, SUM(credit) AS credit FROM journal_lines GROUP BY journal_entry_id HAVING ROUND(SUM(debit) - SUM(credit), 2) <> 0;
SELECT jl.id, jl.journal_entry_id FROM journal_lines jl LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id WHERE je.id IS NULL;
SELECT je.id, je.entry_no FROM journal_entries je LEFT JOIN journal_lines jl ON jl.journal_entry_id = je.id WHERE jl.id IS NULL;
SELECT idempotency_key, COUNT(*) n FROM journal_entries GROUP BY idempotency_key HAVING n > 1;   -- unique index exists: expected zero
-- D4 ledger outbox: unprocessed rows older than 10 minutes; groups whose deltas do not sum to zero; processed rows without journal entry. [v2]
SELECT token, MIN(created_at) AS oldest, COUNT(*) AS rows_in_group, SUM(attempts) AS attempts FROM ledger_outbox WHERE processed_at IS NULL AND created_at < NOW() - INTERVAL 10 MINUTE GROUP BY token;
SELECT token, ROUND(SUM(delta), 2) AS net FROM ledger_outbox GROUP BY token HAVING ROUND(SUM(delta), 2) <> 0;          -- ASSUMPTION: a legacy posting group is balanced by construction; the processor adds the suspense leg
SELECT o.token, o.journal_entry_id FROM ledger_outbox o LEFT JOIN journal_entries je ON je.id = o.journal_entry_id WHERE o.processed_at IS NOT NULL AND je.id IS NULL;
SELECT last_error, COUNT(*) n FROM ledger_outbox WHERE last_error IS NOT NULL GROUP BY last_error;                       -- report
-- D5 settlements: every settlement should have statement lines (and unsettled ones none).
SELECT s.id, s.settlement_no, 'settlement without statement lines' AS problem FROM courier_settlements s
WHERE NOT EXISTS (SELECT 1 FROM account_statement_logs l WHERE l.source_id = s.id AND l.source_type LIKE '%CourierSettlement');
SELECT l.source_id, 'statement lines of a settlement that no longer exists' AS problem, COUNT(*) AS n FROM account_statement_logs l
LEFT JOIN courier_settlements s ON s.id = l.source_id WHERE l.source_type LIKE '%CourierSettlement' AND s.id IS NULL GROUP BY l.source_id;

-- ---------------------------------------------------------------------------------------------------------------------
-- E. SUPPLIER DUE INCONSISTENCIES (money columns are varchar/double: the casts are part of the finding)
-- ---------------------------------------------------------------------------------------------------------------------
-- E1 non-numeric values in varchar money columns (blocks M-9 and any decimal arithmetic).
SELECT 'suppliers.opening_balance' AS col, id, opening_balance AS value FROM suppliers WHERE opening_balance IS NOT NULL AND opening_balance <> '' AND opening_balance NOT REGEXP '^-?[0-9]+(\\.[0-9]+)?$';
SELECT 'purchases.total_payment_due', id, total_payment_due FROM purchases WHERE total_payment_due IS NOT NULL AND total_payment_due <> '' AND total_payment_due NOT REGEXP '^-?[0-9]+(\\.[0-9]+)?$';
SELECT 'purchases.net_total_amount', id, net_total_amount FROM purchases WHERE net_total_amount IS NOT NULL AND net_total_amount <> '' AND net_total_amount NOT REGEXP '^-?[0-9]+(\\.[0-9]+)?$';
SELECT 'purchases.total_paid', id, total_paid FROM purchases WHERE total_paid IS NOT NULL AND total_paid <> '' AND total_paid NOT REGEXP '^-?[0-9]+(\\.[0-9]+)?$';
-- E2 supplier due versus the sum of its purchases' open amounts (ASSUMPTION: this relation is the intended one; the plan marks it NEEDS MORE INVESTIGATION, so treat as REPORT).
SELECT s.id AS supplier_id, s.due_amount, ROUND(COALESCE(SUM(CAST(NULLIF(p.total_payment_due, '') AS DECIMAL(15,2))), 0), 2) AS purchases_open, ROUND(s.due_amount - COALESCE(SUM(CAST(NULLIF(p.total_payment_due, '') AS DECIMAL(15,2))), 0), 2) AS difference
FROM suppliers s LEFT JOIN purchases p ON p.supplier_id = s.id GROUP BY s.id, s.due_amount HAVING ABS(difference) > 0.009;
-- E3 negative or impossible values.
SELECT id, due_amount, paid_amount, opening_balance FROM suppliers WHERE due_amount < 0 OR paid_amount < 0 OR CAST(NULLIF(opening_balance, '') AS DECIMAL(15,2)) < 0;
SELECT id, supplier_id, total_payment_due FROM purchases WHERE CAST(NULLIF(total_payment_due, '') AS DECIMAL(15,2)) < 0;
-- E4 supplier payment total versus its allocations; allocations above the purchase's remaining due.
SELECT sp.id, sp.payment_no, sp.amount, COALESCE(SUM(spp.paid_amount), 0) AS allocated FROM supplier_payments sp LEFT JOIN supplier_payment_purchases spp ON spp.supplier_payment_id = sp.id
WHERE sp.deleted_at IS NULL GROUP BY sp.id, sp.payment_no, sp.amount HAVING ABS(sp.amount - COALESCE(SUM(spp.paid_amount), 0)) > 0.009;
SELECT spp.id, spp.purchase_id, spp.paid_amount, p.total_payment_due FROM supplier_payment_purchases spp JOIN purchases p ON p.id = spp.purchase_id WHERE spp.paid_amount > CAST(NULLIF(p.net_total_amount, '') AS DECIMAL(15,2)) + 0.009;
-- E5 supplier.paid_amount versus the sum of its live payments.
SELECT s.id, s.paid_amount, ROUND(COALESCE(SUM(sp.amount), 0), 2) AS payments FROM suppliers s LEFT JOIN supplier_payments sp ON sp.supplier_id = s.id AND sp.deleted_at IS NULL GROUP BY s.id, s.paid_amount HAVING ABS(s.paid_amount - COALESCE(SUM(sp.amount), 0)) > 0.009;
-- E6 purchases whose arithmetic does not reproduce their own due (ASSUMPTION: due = net_total - total_paid - opening_balance_used - advance; report only).
SELECT id, supplier_id, net_total_amount, total_paid, opening_balance_used, total_advance_amount, total_payment_due FROM purchases
WHERE ABS(CAST(NULLIF(net_total_amount, '') AS DECIMAL(15,2)) - COALESCE(CAST(NULLIF(total_paid, '') AS DECIMAL(15,2)), 0) - COALESCE(CAST(NULLIF(opening_balance_used, '') AS DECIMAL(15,2)), 0) - COALESCE(CAST(NULLIF(total_advance_amount, '') AS DECIMAL(15,2)), 0)
  - CAST(NULLIF(total_payment_due, '') AS DECIMAL(15,2))) > 0.009 LIMIT 500;
-- E7 deleted purchases leave no trace (hard delete): purchases referenced by payments or layers that do not exist.
SELECT spp.id, spp.purchase_id FROM supplier_payment_purchases spp LEFT JOIN purchases p ON p.id = spp.purchase_id WHERE p.id IS NULL;

-- ---------------------------------------------------------------------------------------------------------------------
-- F. ACCOUNT BALANCE INCONSISTENCIES
-- ---------------------------------------------------------------------------------------------------------------------
-- F1 cash/bank/mobile-bank accounts below zero (overdraft evidence, report).
SELECT id, code, sub_type, current_balance FROM chart_of_accounts WHERE sub_type IN ('cash','bank','mobile_bank') AND current_balance < 0;
-- F2 balance versus the last statement line's running balance (ASSUMPTION: every legacy posting writes a statement line; report).
SELECT a.id, a.code, a.current_balance, l.balance AS last_statement_balance, ROUND(a.current_balance - l.balance, 2) AS difference
FROM chart_of_accounts a JOIN account_statement_logs l ON l.id = (SELECT MAX(id) FROM account_statement_logs x WHERE x.account_id = a.id)
WHERE ABS(a.current_balance - l.balance) > 0.009;
-- F3 balance versus opening + movements (ASSUMPTION: debit-normal for asset/expense, credit-normal for liability/equity/income; report).
SELECT a.id, a.code, a.type, a.opening_balance, a.current_balance,
  ROUND(a.opening_balance + SUM(CASE WHEN a.type IN ('asset','expense') THEN l.debit - l.credit ELSE l.credit - l.debit END), 2) AS expected,
  ROUND(a.current_balance - (a.opening_balance + SUM(CASE WHEN a.type IN ('asset','expense') THEN l.debit - l.credit ELSE l.credit - l.debit END)), 2) AS difference
FROM chart_of_accounts a JOIN account_statement_logs l ON l.account_id = a.id GROUP BY a.id, a.code, a.type, a.opening_balance, a.current_balance
HAVING ABS(difference) > 0.009 LIMIT 500;
-- F4 balance versus the journal (v2 accounts only). [v2]
SELECT a.id, a.code, a.current_balance, ROUND(SUM(jl.debit - jl.credit), 2) AS journal_net FROM chart_of_accounts a JOIN journal_lines jl ON jl.account_id = a.id GROUP BY a.id, a.code, a.current_balance LIMIT 500;   -- report (needs the accountant's baseline to interpret)
-- F5 deleted accounts still carrying a balance; accounts with an unknown parent.
SELECT id, code, current_balance FROM chart_of_accounts WHERE (is_deleted IN ('1', 1) OR deleted_at IS NOT NULL) AND current_balance <> 0;
SELECT c.id, c.parent_id FROM chart_of_accounts c LEFT JOIN chart_of_accounts p ON p.id = c.parent_id WHERE c.parent_id IS NOT NULL AND p.id IS NULL;
-- F6 the system accounts the settlement needs exist exactly once.
SELECT name, type, sub_type, COUNT(*) n FROM chart_of_accounts WHERE name IN ('Sales','Accounts Receivable','Inventory','Cost of Goods Sold','Accounts Payable','Courier Income','Bad Debt Expense') AND (is_deleted = '0' OR is_deleted = 0) GROUP BY name, type, sub_type HAVING n > 1;

-- ---------------------------------------------------------------------------------------------------------------------
-- G. STOCK AND FIFO
-- ---------------------------------------------------------------------------------------------------------------------
SELECT product_id, variant, COUNT(*) n FROM product_stocks GROUP BY product_id, variant HAVING n > 1;        -- unique index missing on staging-style schemas (see schema report)
SELECT ps.product_id, ps.variant, ps.qty AS shelf, COALESCE(SUM(pp.remaining_qty), 0) AS layers FROM product_stocks ps
LEFT JOIN purchase_products pp ON pp.product_id = ps.product_id AND COALESCE(pp.variant, '') = COALESCE(ps.variant, '')
GROUP BY ps.product_id, ps.variant, ps.qty HAVING ps.qty <> COALESCE(SUM(pp.remaining_qty), 0) LIMIT 1000;     -- report: legacy products without layers will appear (opening stock)
SELECT id, product_id, purchase_id, purchase_quantity, remaining_qty FROM purchase_products WHERE remaining_qty < 0 OR remaining_qty > CAST(NULLIF(purchase_quantity, '') AS SIGNED);
SELECT id, product_id, variant, qty FROM product_stocks WHERE qty < 0;
SELECT pp.id, pp.purchase_id FROM purchase_products pp LEFT JOIN purchases p ON p.id = pp.purchase_id WHERE p.id IS NULL;
SELECT od.id, od.order_id, od.purchase_id FROM order_details od LEFT JOIN purchases p ON p.id = od.purchase_id WHERE od.purchase_id IS NOT NULL AND p.id IS NULL;
SELECT od.id, od.product_id, od.variation FROM order_details od LEFT JOIN product_stocks ps ON ps.product_id = od.product_id AND COALESCE(ps.variant, '') = COALESCE(od.variation, '') WHERE ps.id IS NULL LIMIT 500;   -- report
SELECT product_id, variant, demand, allocated FROM stock_balances WHERE demand < 0 OR allocated < 0 OR allocated > demand;                                   -- [v2]
SELECT id, order_detail_id, qty_confirmed, qty_allocated, qty_dispatched, qty_cancelled FROM order_demands WHERE qty_allocated + qty_dispatched + qty_cancelled > qty_confirmed;   -- [v2]
SELECT order_detail_id, COUNT(*) n FROM order_demands GROUP BY order_detail_id HAVING n > 1;                                                               -- [v2] unique index exists

-- ---------------------------------------------------------------------------------------------------------------------
-- H. ORPHANED RECORDS
-- ---------------------------------------------------------------------------------------------------------------------
SELECT od.id, od.order_id FROM order_details od LEFT JOIN orders o ON o.id = od.order_id WHERE o.id IS NULL;
SELECT o.id, o.combined_order_id FROM orders o LEFT JOIN combined_orders c ON c.id = o.combined_order_id WHERE o.combined_order_id IS NOT NULL AND c.id IS NULL;
SELECT o.id FROM orders o WHERE o.combined_order_id IS NULL AND (o.order_from IS NULL OR o.order_from NOT IN ('pos'));                                  -- report
SELECT d.id, d.courier_settlement_id FROM courier_settlement_details d LEFT JOIN courier_settlements s ON s.id = d.courier_settlement_id WHERE s.id IS NULL;
SELECT s.id, s.order_id FROM courier_settlements s LEFT JOIN orders o ON o.id = s.order_id WHERE o.id IS NULL;
SELECT spp.id FROM supplier_payment_purchases spp LEFT JOIN supplier_payments sp ON sp.id = spp.supplier_payment_id WHERE sp.id IS NULL;
SELECT m.id FROM order_payment_mappings m LEFT JOIN customer_payments cp ON cp.id = m.customer_payment_id WHERE cp.id IS NULL;
SELECT m.id FROM order_payment_mappings m LEFT JOIN orders o ON o.id = m.order_id WHERE o.id IS NULL;
SELECT vd.id, vd.voucher_id FROM voucher_details vd LEFT JOIN vouchers v ON v.id = vd.voucher_id WHERE v.id IS NULL;
SELECT l.id, l.stock_adjustment_id FROM stock_adjustment_lines l LEFT JOIN stock_adjustments a ON a.id = l.stock_adjustment_id WHERE a.id IS NULL;
SELECT r.id FROM purchase_returns r LEFT JOIN purchases p ON p.id = r.purchase_id WHERE r.purchase_id IS NOT NULL AND p.id IS NULL;                      -- report (a return may outlive a deleted purchase)
SELECT rp.id FROM purchase_return_products rp LEFT JOIN purchase_returns r ON r.id = rp.purchase_return_id WHERE r.id IS NULL;
SELECT p.id, p.supplier_id FROM purchases p LEFT JOIN suppliers s ON s.id = p.supplier_id WHERE s.id IS NULL;
SELECT sp.id, sp.supplier_id FROM supplier_payments sp LEFT JOIN suppliers s ON s.id = sp.supplier_id WHERE s.id IS NULL;
SELECT c.id, c.user_id FROM carts c LEFT JOIN users u ON u.id = c.user_id WHERE c.user_id IS NOT NULL AND u.id IS NULL;                                 -- report
SELECT user_id, COUNT(*) AS cart_rows FROM carts WHERE user_id IS NOT NULL GROUP BY user_id HAVING cart_rows > 50;                                         -- report (carts that make a table-wide lock heavier)
SELECT COUNT(*) AS carts_total FROM carts;                                                                                                                -- report: size of the table the D6 cart lock scans
-- payment files referenced by more than one row (matters for the file-delete queue design, ASSUMPTION: paths are stored in these columns)
SELECT document, COUNT(*) n FROM purchases WHERE document IS NOT NULL AND document <> '' GROUP BY document HAVING n > 1;
SELECT document, COUNT(*) n FROM purchase_returns WHERE document IS NOT NULL AND document <> '' GROUP BY document HAVING n > 1;

-- ---------------------------------------------------------------------------------------------------------------------
-- I. IDEMPOTENCY KEYS (existing table; [v2])
-- ---------------------------------------------------------------------------------------------------------------------
SELECT scope, COUNT(*) AS keys_total, SUM(result IS NULL) AS without_result, MIN(created_at) AS first_key, MAX(created_at) AS last_key FROM idempotency_keys GROUP BY scope ORDER BY keys_total DESC;   -- report: current usage
SELECT id, scope, `key`, created_at FROM idempotency_keys WHERE result IS NULL AND created_at < NOW() - INTERVAL 5 MINUTE;      -- incomplete records: expected zero (the key is inserted inside the operation's transaction)
SELECT scope, `key`, COUNT(*) n FROM idempotency_keys GROUP BY scope, `key` HAVING n > 1;                                     -- unique index: expected zero
SELECT id, scope, CHAR_LENGTH(`key`) AS key_length FROM idempotency_keys WHERE CHAR_LENGTH(`key`) > 120 OR CHAR_LENGTH(scope) > 40;   -- expected zero
-- collisions: the same key text used under several scopes (a hint of copy/paste keys; report)
SELECT `key`, COUNT(DISTINCT scope) AS scopes FROM idempotency_keys GROUP BY `key` HAVING scopes > 1 LIMIT 200;
-- the table must agree with the documents it protects
SELECT g.id, g.idempotency_key FROM goods_receipts g LEFT JOIN idempotency_keys k ON k.scope = 'goods_receipt' AND k.`key` = g.idempotency_key WHERE k.id IS NULL;   -- report
SELECT k.id, k.`key` FROM idempotency_keys k LEFT JOIN goods_receipts g ON g.idempotency_key = k.`key` WHERE k.scope = 'goods_receipt' AND g.id IS NULL;
SELECT k.id, k.`key` FROM idempotency_keys k WHERE k.scope = 'legacy_purchase';                                               -- report: legacy purchase double-submit keys (the only legacy screen with one today)
SELECT COUNT(*) AS legacy_purchases_without_key FROM purchases;                                                              -- report: compare with the previous line; legacy purchases created before the guard have no key
SELECT chat.idempotency_key, COUNT(*) n FROM chat_conversation_orders chat GROUP BY chat.idempotency_key HAVING n > 1;        -- unique index exists: expected zero
SELECT key_hash, COUNT(*) n FROM funnel_checkout_attempts GROUP BY key_hash HAVING n > 1;                                      -- unique index exists: expected zero
SELECT status, COUNT(*) n FROM funnel_checkout_attempts GROUP BY status;                                                     -- report (attempts left "started")

ROLLBACK;
