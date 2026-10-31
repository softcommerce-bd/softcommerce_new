<?php

namespace Tests\Concurrency;

use App\Models\ChartOfAccount;

/** A deadlock victim must not become a lost order / posting: service transactions retry; a plain transaction would just fail. */
class DeadlockRecoveryTest extends ConcurrencyTestCase
{
    private function accounts(): array
    {
        return array_map(fn ($n) => ChartOfAccount::create(['code' => 'DL-' . uniqid(), 'name' => 'DL ' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0])->id, [1, 2]);
    }

    private function pair(int $a, int $b, bool $retry, string $tag): array
    {
        $job = fn ($first, $second, $n) => ['action' => 'lock_pair', 'first' => $first, 'second' => $second, 'hold_ms' => 400, 'retry' => $retry, 'key' => "{$tag}-{$n}"];

        return $this->race([$job($a, $b, 1), $job($b, $a, 2)], 60);
    }

    public function test_without_retry_one_of_two_crossing_transactions_is_killed()
    {
        [$a, $b] = $this->accounts();
        $r = $this->pair($a, $b, false, 'plain');
        $failed = array_values(array_filter($r, fn ($x) => empty($x['ok'])));
        $this->assertCount(1, $failed, 'the database kills exactly one deadlock victim: ' . json_encode($r));
        $this->assertStringContainsString('Deadlock', json_encode($failed[0]['error'] ?? ''));
    }

    public function test_service_transactions_retry_the_victim_so_both_complete_exactly_once()
    {
        [$a, $b] = $this->accounts();
        $r = $this->pair($a, $b, true, 'retry-' . uniqid());
        $this->assertTrue(!empty($r[0]['ok']) && !empty($r[1]['ok']), 'both transactions completed: ' . json_encode($r));
        $this->assertGreaterThan(2, ($r[0]['attempts'] ?? 0) + ($r[1]['attempts'] ?? 0), 'a deadlock really happened and the victim ran again');
        $this->assertSame(2, \Illuminate\Support\Facades\DB::table('idempotency_keys')->where('scope', 'test_pair')->where('key', 'like', 'retry-%')->count(), 'each operation recorded once');
        \Illuminate\Support\Facades\DB::table('idempotency_keys')->where('scope', 'test_pair')->delete();
    }
}
