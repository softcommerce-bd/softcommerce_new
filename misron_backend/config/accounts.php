<?php

return [
    /*
    | Nightly auto-deposit (accounts:auto-deposit). OWNER DECISION AD-1.1/AD-1.2: option C for now - the job stays PAUSED (guard G1).
    | It must not be switched on by flipping this value alone: today the job cannot record a creator (balance_transfers.created_by is NOT NULL)
    | and would fail for every method. Turning it on needs an approved AD-1 option (a system user, or a schema change) AND its implementation.
    | The value exists so the job's locking behaviour can still be tested on disposable databases.
    */
    'auto_deposit_approved' => (bool) env('AUTO_DEPOSIT_APPROVED', false),

    /*
    | Even with the switch above ON, accounts:auto-deposit refuses to run until AutoDepositReadiness reports no problem (R-03/R-04):
    | a configured NON-LOGIN system user as the creator, ENUM values the job writes, InnoDB tables, and - only when a method charges a fee -
    | an explicitly configured expense account (the accounting rule for charges is an open ruling; no account is guessed).
    */
    'auto_deposit_user_id' => env('AUTO_DEPOSIT_USER_ID') ? (int) env('AUTO_DEPOSIT_USER_ID') : null,
];
