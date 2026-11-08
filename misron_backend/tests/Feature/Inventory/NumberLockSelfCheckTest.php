<?php

namespace Tests\Feature\Inventory;

use App\Support\DeadlockRetry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** INC.1 diagnostic: passes on the local MariaDB, writes no data, and leaves no lock behind. */
class NumberLockSelfCheckTest extends InventoryV2TestCase
{
    public function test_selfcheck_passes_locally_writes_nothing_and_leaves_no_lock()
    {
        $counts = ['suppliers' => DB::table('suppliers')->count(), 'incomes' => DB::table('incomes')->count(), 'chart' => DB::table('chart_of_accounts')->count()];
        $writes = [];
        DB::listen(function ($q) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)/i', $q->sql)) {
                $writes[] = $q->sql;
            }
        });
        $this->assertSame(1, DeadlockRetry::$baseLevel, 'wrapped test level');
        $code = Artisan::call('accounts:number-lock-selfcheck');
        $out = Artisan::output();
        $this->assertSame(0, $code, $out);
        foreach (['PASS: one connection for the whole transaction', 'PASS: the lock is held and a second connection is refused', 'PASS: the lock was released when the transaction ended', 'RESULT: PASS'] as $needle) {
            $this->assertStringContainsString($needle, $out);
        }
        $this->assertSame([], $writes, 'no data write');
        $this->assertSame($counts, ['suppliers' => DB::table('suppliers')->count(), 'incomes' => DB::table('incomes')->count(), 'chart' => DB::table('chart_of_accounts')->count()]);
        $this->assertSame(0, \App\Support\NumberSerializer::heldCount());
    }
}
