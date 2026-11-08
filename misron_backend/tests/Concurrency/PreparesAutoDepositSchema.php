<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;

/**
 * The auto-deposit job needs a database prepared as in sqlupdates/t5_auto_deposit_schema.sql (ENUM values) and a non-login system user.
 * For tests on the DISPOSABLE *_test database only: widen the three ENUM columns, make the user, and put the exact original column definitions back.
 */
trait PreparesAutoDepositSchema
{
    /** @var array<int,array{0:string,1:string,2:string,3:?string}> table, column, original type, original default */
    private array $adOriginal = [];

    protected function adWiden(): void
    {
        if (!str_ends_with(DB::getDatabaseName(), '_test')) {
            $this->fail('schema preparation is for *_test databases only');
        }
        foreach ([['balance_transfers', 'transaction_type', 'auto_deposit'], ['balance_transfers', 'status', 'completed'], ['expense_payments', 'status', 'completed']] as [$t, $c, $add]) {
            $col = DB::selectOne('SELECT COLUMN_TYPE AS t, COLUMN_DEFAULT AS d FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$t, $c]);
            $this->adOriginal[] = [$t, $c, $col->t, $col->d];
            if (stripos($col->t, "'$add'") === false) {
                $new = substr($col->t, 0, -1) . ",'$add')";
                $default = $col->d !== null ? ' DEFAULT ' . DB::getPdo()->quote(trim((string) $col->d, "'")) : '';
                DB::statement("ALTER TABLE `$t` MODIFY `$c` $new NOT NULL$default");
            }
        }
    }

    protected function adRestore(): void
    {
        foreach (array_reverse($this->adOriginal) as [$t, $c, $type, $d]) {
            $default = $d !== null ? ' DEFAULT ' . DB::getPdo()->quote(trim((string) $d, "'")) : '';
            DB::statement("ALTER TABLE `$t` MODIFY `$c` $type NOT NULL$default");
        }
        $this->adOriginal = [];
    }

    protected function adUser(): int
    {
        return (int) DB::table('users')->insertGetId(['name' => 'Auto deposit (system)', 'email' => 'autodeposit-' . uniqid() . '@system.invalid', 'password' => '!', 'user_type' => 'system', 'banned' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }
}
