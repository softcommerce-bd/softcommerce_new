<?php

namespace App\Services\Accounting;

use App\Support\TransactionalEngines;
use Illuminate\Support\Facades\DB;

/**
 * Is the database (and configuration) prepared for `accounts:auto-deposit` to post money correctly? Read-only.
 *
 * The job moves a whole account balance every night, so "someone switched it on" must never be enough. It runs only when ALL of these hold:
 *  1. a creator exists: AUTO_DEPOSIT_USER_ID names an existing user that cannot sign in (banned, or user_type 'system'; never admin/staff);
 *  2. the values the job writes are legal in the database: balance_transfers.transaction_type 'auto_deposit', balance_transfers.status and
 *     expense_payments.status 'completed' (otherwise a non-strict connection stores them as blank);
 *  3. the tables it writes are InnoDB (rollback, row locks);
 *  4. if any active auto method charges a fee, AUTO_DEPOSIT_CHARGE_ACCOUNT_ID names an existing active expense account (the accounting rule for charges is an open
 *     ruling: nothing is guessed, the old fallback to account id 67 is gone).
 */
final class AutoDepositReadiness
{
    public const TYPE = 'auto_deposit';
    public const STATUS = 'completed';

    /** @return string[] empty when ready */
    public static function problems(): array
    {
        $p = [];
        $uid = config('accounts.auto_deposit_user_id');
        if (!$uid) {
            $p[] = 'AUTO_DEPOSIT_USER_ID is not set: the job needs a named non-login system user as the creator of its records (balance_transfers.created_by and expense_payments.created_by are NOT NULL)';
        } else {
            $u = DB::table('users')->where('id', $uid)->first(['id', 'user_type', 'banned']);
            if (!$u) {
                $p[] = "AUTO_DEPOSIT_USER_ID=$uid does not exist in users";
            } elseif (in_array($u->user_type, ['admin', 'staff'], true) || !((int) $u->banned === 1 || $u->user_type === 'system')) {
                $p[] = "user $uid can sign in (user_type={$u->user_type}, banned={$u->banned}): the system creator must be banned or user_type 'system' and never admin/staff";
            }
        }
        foreach ([['balance_transfers', 'transaction_type', self::TYPE], ['balance_transfers', 'status', self::STATUS], ['expense_payments', 'status', self::STATUS]] as [$table, $col, $val]) {
            $type = self::columnType($table, $col);
            if ($type === null) {
                $p[] = "$table.$col could not be read";
            } elseif (stripos($type, 'enum(') === 0 && stripos($type, "'" . $val . "'") === false) {
                $p[] = "$table.$col is $type: the value '$val' is not allowed, a non-strict connection would store it blank (needs the schema step in sqlupdates/t5_auto_deposit_schema.sql after approval)";
            }
        }
        $bad = TransactionalEngines::offenders(['balance_transfers', 'expense_payments', 'chart_of_accounts', 'account_statement_logs', 'transaction_methods']);
        if ($bad === null || $bad) {
            $p[] = 'tables not on InnoDB or unreadable: ' . json_encode($bad);
        }
        $charged = DB::table('transaction_methods')->where('deposit_type', 'auto')->where('status', 'active')->where('charge', '>', 0)->count();
        if ($charged > 0) {
            $acc = config('accounts.auto_deposit_charge_account_id');
            if (!$acc || !DB::table('chart_of_accounts')->where('id', $acc)->where('type', 'expense')->where('status', 'active')->where('is_deleted', 0)->exists()) {
                $p[] = "$charged active auto method(s) charge a fee but AUTO_DEPOSIT_CHARGE_ACCOUNT_ID is not an existing active expense account (accounting ruling needed; no account is guessed)";
            }
        }

        return $p;
    }

    private static function columnType(string $table, string $column): ?string
    {
        try {
            $r = DB::selectOne('SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);

            return $r ? (string) $r->t : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
