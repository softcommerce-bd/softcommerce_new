<?php

namespace Tests\Concurrency;

use App\Services\Accounting\JournalService;
use Illuminate\Support\Facades\DB;

/** A. Transactional outbox: concurrent workers, termination at every boundary, recovery. */
class OutboxVerificationTest extends VerificationCase
{
    public function test_four_workers_clearing_the_same_backlog_create_exactly_one_entry_per_group()
    {
        $a = $this->account(); $b = $this->account('expense', 'expense', 0);
        $tokens = $this->backlog(40, $a, $b);
        $entriesBefore = DB::table('journal_entries')->count();
        $results = $this->race(array_map(fn () => ['action' => 'outbox_process'], range(1, 4)), 90);
        foreach ($results as $r) {
            $this->assertTrue(!empty($r['ok']), 'a worker failed: ' . json_encode($r));
            $this->assertSame(0, $r['outbox']['failed'], 'no group failed: ' . json_encode($r));
        }
        $this->assertSame(40, DB::table('journal_entries')->count() - $entriesBefore, 'exactly one entry per group');
        $this->assertSame(40, DB::table('journal_entries')->whereIn('idempotency_key', array_map(fn ($t) => 'legacy:' . $t, $tokens))->count());
        $this->assertSame(0, DB::table('ledger_outbox')->whereIn('token', $tokens)->whereNull('processed_at')->count());
        $this->assertSame(80, DB::table('ledger_outbox')->whereIn('token', $tokens)->whereNotNull('journal_entry_id')->count());
        $dupes = DB::table('journal_entries')->whereIn('idempotency_key', array_map(fn ($t) => 'legacy:' . $t, $tokens))->groupBy('idempotency_key')->havingRaw('COUNT(*) > 1')->count();
        $this->assertSame(0, $dupes);
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
    }

    public function test_a_worker_killed_at_every_boundary_leaves_a_state_a_second_worker_completes_exactly_once()
    {
        $a = $this->account(); $b = $this->account('expense', 'expense', 0);
        foreach (['after_lock', 'after_lines', 'after_journal', 'after_marks', 'after_commit'] as $boundary) {
            $tokens = $this->backlog(1, $a, $b);
            $key = 'legacy:' . $tokens[0];
            $entries = fn () => DB::table('journal_entries')->where('idempotency_key', $key)->count();
            $waiting = fn () => DB::table('ledger_outbox')->where('token', $tokens[0])->whereNull('processed_at')->count();

            $killed = $this->runOne(['action' => 'outbox_kill_at', 'at' => $boundary]);
            $this->assertNull($killed, "the worker really died at {$boundary} (it wrote no result)");
            if ($boundary === 'after_commit') {
                $this->assertSame([1, 0], [$entries(), $waiting()], "{$boundary}: the work had committed before the kill");
            } else {
                $this->assertSame([0, 2], [$entries(), $waiting()], "{$boundary}: nothing half-done: the entry and the marks share one transaction, so the killed connection rolled both back");
            }
            $r = $this->runOne(['action' => 'outbox_process']);
            $this->assertTrue(!empty($r['ok']), "recovery after {$boundary}: " . json_encode($r));
            $this->assertSame([1, 0], [$entries(), $waiting()], "{$boundary}: exactly one entry after recovery");
            $this->runOne(['action' => 'outbox_process']);
            $this->assertSame(1, $entries(), "{$boundary}: a repeat run posts nothing more");
        }
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
    }

    public function test_a_killed_worker_does_not_block_the_other_workers()
    {
        $a = $this->account(); $b = $this->account('expense', 'expense', 0);
        $tokens = $this->backlog(12, $a, $b);
        $results = $this->race([['action' => 'outbox_kill_at', 'at' => 'after_journal', 'may_die' => true], ['action' => 'outbox_process'], ['action' => 'outbox_process']], 90);
        $this->assertTrue($results[0]['died'] ?? false, 'the first worker was killed');
        $this->assertTrue(!empty($results[1]['ok']) && !empty($results[2]['ok']), 'the surviving workers finished without error: ' . json_encode($results));
        $this->runOne(['action' => 'outbox_process']);
        $this->assertSame(12, DB::table('journal_entries')->whereIn('idempotency_key', array_map(fn ($t) => 'legacy:' . $t, $tokens))->count());
        $this->assertSame(0, DB::table('ledger_outbox')->whereIn('token', $tokens)->whereNull('processed_at')->count());
    }

    public function test_live_old_screen_postings_racing_outbox_workers_never_deadlock_or_lose_a_posting()
    {
        // regression: processing used to lock the outbox by token / processed_at, taking gap locks that deadlocked against live transactions
        $a = $this->account('asset', 'cash', 10000); $b = $this->account('expense', 'expense', 0);
        for ($round = 1; $round <= 3; $round++) {
            $jobs = [];
            foreach (range(1, 16) as $n) {
                $jobs[] = ['action' => 'legacy_post', 'from' => $a, 'to' => $b, 'amount' => 5];
            }
            foreach (range(1, 4) as $n) {
                $jobs[] = ['action' => 'outbox_process'];
            }
            shuffle($jobs);
            $results = $this->race($jobs, 120);
            foreach ($results as $i => $r) {
                $this->assertTrue(!empty($r['ok']), "round {$round} {$jobs[$i]['action']}: " . json_encode($r));
            }
        }
        $this->runOne(['action' => 'outbox_process']);
        $this->assertEquals(10000 - 48 * 5.0, (float) DB::table('chart_of_accounts')->where('id', $a)->value('current_balance'));
        $this->assertSame(48, DB::table('ledger_outbox')->where('account_id', $a)->count());
        $this->assertSame(48, DB::table('journal_lines')->where('account_id', $a)->count(), 'one journal line per posting, none lost, none doubled');
        $this->assertSame(0, DB::table('ledger_outbox')->whereNull('processed_at')->count());
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
    }
}
