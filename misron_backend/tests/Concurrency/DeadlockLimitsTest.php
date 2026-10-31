<?php

namespace Tests\Concurrency;

use App\Services\Inventory\Idempotency;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** D. Retry limits and rollback, in-process and outside any wrapping test transaction (so the retry really applies). */
class DeadlockLimitsTest extends ConcurrencyTestCase
{
    private function deadlock(): QueryException
    {
        $pdo = new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction');
        $pdo->errorInfo = ['40001', 1213, 'Deadlock found'];

        return new QueryException('update x', [], $pdo);
    }

    protected function tearDown(): void
    {
        DB::table('idempotency_keys')->where('scope', 'limits')->delete();
        DB::table('chart_of_accounts')->where('code', 'like', 'LIM-%')->delete();
        parent::tearDown();
    }

    public function test_a_service_transaction_gives_up_after_three_attempts_and_everything_it_wrote_is_rolled_back()
    {
        $attempts = 0;
        try {
            Idempotency::once('limits', 'always-' . uniqid(), function () use (&$attempts) {
                $attempts++;
                DB::table('chart_of_accounts')->insert(['code' => 'LIM-' . $attempts . uniqid(), 'name' => 'lim', 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0]);
                throw $this->deadlock();
            });
            $this->fail('it must surface the deadlock after the last attempt');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Deadlock', $e->getMessage());
        }
        $this->assertSame(3, $attempts, 'exactly three attempts, no endless loop');
        $this->assertSame(0, DB::table('chart_of_accounts')->where('code', 'like', 'LIM-%')->count(), 'every attempt rolled back its rows');
        $this->assertSame(0, DB::table('idempotency_keys')->where('scope', 'limits')->count(), 'no key was left behind, so the operation can be tried again later');
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_two_deadlocks_then_success_commits_once()
    {
        $attempts = 0;
        $key = 'twice-' . uniqid();
        $r = Idempotency::once('limits', $key, function () use (&$attempts) {
            $attempts++;
            DB::table('chart_of_accounts')->insert(['code' => 'LIM-ok' . uniqid(), 'name' => 'lim', 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0]);
            if ($attempts < 3) {
                throw $this->deadlock();
            }

            return ['done' => true];
        });
        $this->assertSame([3, true, false], [$attempts, $r['done'], $r['replayed']]);
        $this->assertSame(1, DB::table('chart_of_accounts')->where('code', 'like', 'LIM-ok%')->count(), 'only the successful attempt\'s rows exist');
        $this->assertTrue(Idempotency::once('limits', $key, fn () => ['done' => false])['replayed'], 'and the key replays');
    }

    public function test_an_ordinary_error_is_not_retried()
    {
        $attempts = 0;
        try {
            Idempotency::once('limits', 'plain-' . uniqid(), function () use (&$attempts) {
                $attempts++;
                throw new \InvalidArgumentException('refused');
            });
        } catch (\InvalidArgumentException $e) {
        }
        $this->assertSame(1, $attempts);
    }
}
