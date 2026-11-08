<?php

return [
    /*
    | Nightly auto-deposit (accounts:auto-deposit). OWNER DECISION AD-1.1/AD-1.2: option C for now - the job stays PAUSED (guard G1).
    | It must not be switched on by flipping this value alone: today the job cannot record a creator (balance_transfers.created_by is NOT NULL)
    | and would fail for every method. Turning it on needs an approved AD-1 option (a system user, or a schema change) AND its implementation.
    | The value exists so the job's locking behaviour can still be tested on disposable databases.
    */
    'auto_deposit_approved' => (bool) env('AUTO_DEPOSIT_APPROVED', false),
];
