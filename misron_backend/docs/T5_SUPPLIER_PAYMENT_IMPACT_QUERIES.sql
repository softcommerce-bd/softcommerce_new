-- =====================================================================================================================
-- T-5: SUPPLIER PAYMENT EDIT DEFECT - READ-ONLY DATA IMPACT QUERIES
-- =====================================================================================================================
-- Defect: SupplierPaymentController::update (before the correction) reverted the old payment on one copy of the supplier row and applied the
-- new payment on another copy loaded before the revert, so the revert was lost: each edit left suppliers.due_amount LOWER and
-- suppliers.paid_amount HIGHER than intended by exactly the amount that was being replaced (the "reversed amount").
-- Evidence of an edit that survives in the data: every edit wrote ONE reversal statement line pair (transaction_type 'supplier_payment_reversal',
-- reference "<payment_no>-REV") whose amount is the payment amount that was reverted at that moment. Deleting a payment writes a different type
-- ('supplier_payment_deletion'), so the two are distinguishable. There is no other edit history (no audit table).
--
-- SAFETY: SELECT only, session opened READ ONLY. Returns ids, document numbers and money only (no names/phones/addresses/notes). Run ONLY on an
-- authorised, isolated copy of a production backup (execution on production data is BLOCKED: none provided). No corrective query exists or is authorised.
-- Do not assume that every supplier discrepancy comes from this defect: the residual columns show what is NOT explained by it.
-- ASSUMPTIONS (marked A): A1 an edit that changed the supplier did not drift (the defect needs old and new supplier to be the same row), so
-- "sum reversed" is an UPPER BOUND of the drift; A2 suppliers.due_amount is intended to equal the sum of its purchases' open amounts; A3
-- suppliers.paid_amount counts supplier payments (other screens, e.g. SupplierController::processPayment, also add to it).
-- =====================================================================================================================
SET SESSION TRANSACTION READ ONLY;
START TRANSACTION READ ONLY;

-- Q1. Every supplier payment, with the edit evidence (edits_recorded > 0 = edited at least once). original_amount_if_edited = the first reversed amount.
SELECT sp.id AS payment_id, sp.payment_no, sp.reference_no, sp.supplier_id, sp.payment_date, sp.amount AS current_amount, sp.due_amount AS due_snapshot_stored_on_payment,
       COALESCE(e.edits, 0) AS edits_recorded, e.first_reversed_amount AS original_amount_if_edited, ROUND(COALESCE(e.total_reversed, 0), 2) AS sum_of_reversed_amounts, sp.status, sp.deleted_at
FROM supplier_payments sp
LEFT JOIN (SELECT l.source_id, COUNT(*) AS edits, SUM(l.credit) AS total_reversed, SUBSTRING_INDEX(GROUP_CONCAT(l.credit ORDER BY l.id SEPARATOR ','), ',', 1) AS first_reversed_amount
           FROM account_statement_logs l
           WHERE l.transaction_type = 'supplier_payment_reversal' AND l.credit > 0 AND l.source_type LIKE '%SupplierPayment'
           GROUP BY l.source_id) e ON e.source_id = sp.id
ORDER BY edits_recorded DESC, sp.id;

-- Q2. Supplier reconciliation for the accountant. Only suppliers with an edited payment OR any unexplained difference are listed.
--     due_residual_if_drift_explains / paid_residual_if_drift_explains = the difference that would remain if the whole upper-bound drift were
--     added back / removed; a residual of 0.00 means the defect alone explains the difference, anything else is something else (A1-A3).
SELECT s.id AS supplier_id, s.due_amount AS supplier_due, s.paid_amount AS supplier_paid,
       ROUND(COALESCE(pu.open_due, 0), 2) AS purchases_open_due, COALESCE(pay.payments, 0) AS live_payments, ROUND(COALESCE(pay.total, 0), 2) AS live_payments_total,
       COALESCE(ed.edits, 0) AS edits_recorded, ROUND(COALESCE(ed.reversed, 0), 2) AS sum_reversed_upper_bound_of_drift,
       ROUND(s.due_amount - COALESCE(pu.open_due, 0), 2) AS due_minus_purchases_open,
       ROUND(s.due_amount + COALESCE(ed.reversed, 0) - COALESCE(pu.open_due, 0), 2) AS due_residual_if_drift_explains,
       ROUND(s.paid_amount - COALESCE(pay.total, 0), 2) AS paid_minus_live_payments,
       ROUND(s.paid_amount - COALESCE(ed.reversed, 0) - COALESCE(pay.total, 0), 2) AS paid_residual_if_drift_explains
FROM suppliers s
LEFT JOIN (SELECT supplier_id, SUM(CAST(NULLIF(total_payment_due, '') AS DECIMAL(15,2))) AS open_due FROM purchases GROUP BY supplier_id) pu ON pu.supplier_id = s.id
LEFT JOIN (SELECT supplier_id, COUNT(*) AS payments, SUM(amount) AS total FROM supplier_payments WHERE deleted_at IS NULL GROUP BY supplier_id) pay ON pay.supplier_id = s.id
LEFT JOIN (SELECT sp.supplier_id, COUNT(*) AS edits, SUM(l.credit) AS reversed
           FROM account_statement_logs l JOIN supplier_payments sp ON sp.id = l.source_id
           WHERE l.transaction_type = 'supplier_payment_reversal' AND l.credit > 0 AND l.source_type LIKE '%SupplierPayment' GROUP BY sp.supplier_id) ed ON ed.supplier_id = s.id
WHERE COALESCE(ed.edits, 0) > 0 OR ABS(s.due_amount - COALESCE(pu.open_due, 0)) > 0.009 OR s.due_amount < 0
ORDER BY edits_recorded DESC, s.id;

-- Q3. Purchase allocations of every edited payment (current pivot rows) with the purchase's open amount.
SELECT sp.id AS payment_id, sp.payment_no, spp.purchase_id, spp.paid_amount AS allocated_now, p.reference_no AS purchase_reference, p.net_total_amount, p.total_payment_due AS purchase_open_due
FROM supplier_payments sp
JOIN (SELECT DISTINCT source_id FROM account_statement_logs WHERE transaction_type = 'supplier_payment_reversal' AND source_type LIKE '%SupplierPayment') e ON e.source_id = sp.id
LEFT JOIN supplier_payment_purchases spp ON spp.supplier_payment_id = sp.id
LEFT JOIN purchases p ON p.id = spp.purchase_id
ORDER BY sp.id, spp.purchase_id;

-- Q4. The statement-line trail of every edited payment (what each edit reversed and re-posted), in order.
SELECT l.source_id AS payment_id, l.id AS log_id, l.transaction_date, l.reference_no, l.transaction_type, l.account_id, l.debit, l.credit
FROM account_statement_logs l
WHERE l.source_type LIKE '%SupplierPayment' AND l.source_id IN (SELECT DISTINCT source_id FROM account_statement_logs WHERE transaction_type = 'supplier_payment_reversal' AND source_type LIKE '%SupplierPayment')
ORDER BY l.source_id, l.id;

-- Q5. Journal entries linked to edited payments. [v2 only; ASSUMPTION: the legacy-ledger bridge names the source after the route and uses the payment id; verify the
--     naming in the copy with: SELECT DISTINCT source_type FROM journal_entries WHERE source_type LIKE 'legacy:%';]
SELECT je.id AS journal_entry_id, je.entry_no, je.entry_date, je.source_type, je.source_id, ROUND(SUM(jl.debit), 2) AS debit, ROUND(SUM(jl.credit), 2) AS credit
FROM journal_entries je JOIN journal_lines jl ON jl.journal_entry_id = je.id
WHERE je.source_type LIKE '%supplier-payment%' OR je.source_type LIKE '%supplier_payment%'
GROUP BY je.id, je.entry_no, je.entry_date, je.source_type, je.source_id
ORDER BY je.id;

-- Q6. Negative supplier balances (a visible symptom; not proof of this defect).
SELECT id AS supplier_id, due_amount, paid_amount FROM suppliers WHERE due_amount < 0 OR paid_amount < 0;

ROLLBACK;
