-- T-5 / R-03: schema step that the nightly auto-deposit job needs. NOT run by any migration. For a DBA, ONLY after the owner has chosen to run the job
-- (decision AD-1 option a) and AUTO_DEPOSIT_USER_ID / AUTO_DEPOSIT_CHARGE_ACCOUNT_ID are set. The job refuses to run until these values are legal
-- (App\Services\Accounting\AutoDepositReadiness). It only ADDS ENUM values; it removes nothing and changes no row.
--
-- CHECK FIRST (the statements below are written for the definitions of the supplied dump; if production differs, adapt them):
--   SHOW CREATE TABLE balance_transfers\G   SHOW CREATE TABLE expense_payments\G
--   expected: balance_transfers.transaction_type enum('withdraw','deposit','transfer','cashout'), status enum('pending','approved','rejected','cancelled'),
--             expense_payments.status enum('pending','approved','rejected','cancelled'), both created_by bigint unsigned NOT NULL (stays NOT NULL).
-- ROLLBACK (only while no row uses the new values): the same statements without the added value.

ALTER TABLE `balance_transfers` MODIFY `transaction_type` ENUM('withdraw','deposit','transfer','cashout','auto_deposit') NOT NULL;
ALTER TABLE `balance_transfers` MODIFY `status` ENUM('pending','approved','rejected','cancelled','completed') NOT NULL DEFAULT 'approved';
ALTER TABLE `expense_payments`  MODIFY `status` ENUM('pending','approved','rejected','cancelled','completed') NOT NULL DEFAULT 'approved';

-- rollback:
-- ALTER TABLE `balance_transfers` MODIFY `transaction_type` ENUM('withdraw','deposit','transfer','cashout') NOT NULL;
-- ALTER TABLE `balance_transfers` MODIFY `status` ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'approved';
-- ALTER TABLE `expense_payments`  MODIFY `status` ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'approved';
