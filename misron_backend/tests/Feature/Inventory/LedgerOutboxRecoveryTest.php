<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\LegacyLedgerBridge;
use App\Services\Inventory\AccountingReconciliation;
use Illuminate\Support\Facades\DB;

/** Failure recovery for the transactional outbox: a committed business change can never stay without its journal entry, nor get two. */
class LedgerOutboxRecoveryTest extends InventoryV2TestCase
{
    private function acct(string $type, string $sub, float $bal = 1000): ChartOfAccount
    {
        return ChartOfAccount::create(['code' => 'OB-' . uniqid(), 'name' => 'OB ' . uniqid(), 'type' => $type, 'sub_type' => $sub, 'opening_balance' => 0, 'current_balance' => $bal, 'status' => 'active', 'is_deleted' => 0]);
    }

    /** a legacy-style operation: cash out, expense in, one transaction */
    private function legacyPayment(ChartOfAccount $cash, ChartOfAccount $exp, float $amount = 40.0): void
    {
        DB::transaction(function () use ($cash, $exp, $amount) {
            $c = ChartOfAccount::find($cash->id); $c->current_balance -= $amount; $c->save();
            $e = ChartOfAccount::find($exp->id); $e->current_balance += $amount; $e->save();
        });
    }

    private function failingJournal(bool $afterPosting): void
    {
        $real = new JournalService();
        $this->app->instance(JournalService::class, new class($real, $afterPosting) extends JournalService {
            public function __construct(private JournalService $real, private bool $after) {}
            public function postMirrored(string $key, string $date, array $lines, array $mirrored, ?string $sourceType, ?int $sourceId, ?string $description, ?int $by, ?int $reversalOf = null): array
            {
                if (!$this->after) {
                    throw new \RuntimeException('journal unavailable');
                }
                $r = $this->real->postMirrored($key, $date, $lines, $mirrored, $sourceType, $sourceId, $description, $by, $reversalOf);
                throw new \RuntimeException('process died after writing the journal entry');
            }
        });
    }

    private function restoreJournal(): void
    {
        $this->app->forgetInstance(JournalService::class);
    }

    public function test_the_normal_path_writes_outbox_rows_and_one_entry_and_marks_them_processed()
    {
        $cash = $this->acct('asset', 'cash'); $exp = $this->acct('expense', 'expense', 0);
        $this->legacyPayment($cash, $exp);
        $rows = DB::table('ledger_outbox')->whereIn('account_id', [$cash->id, $exp->id])->get();
        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows->pluck('journal_entry_id')->unique()->count());
        $this->assertNotNull($rows->first()->processed_at);
    }

    public function test_a_rolled_back_operation_leaves_no_outbox_row_and_no_entry()
    {
        $cash = $this->acct('asset', 'cash');
        $n = DB::table('journal_entries')->count();
        $o = DB::table('ledger_outbox')->count();
        try {
            DB::transaction(function () use ($cash) {
                $c = ChartOfAccount::find($cash->id); $c->current_balance -= 5; $c->save();
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
        }
        $this->assertSame([$n, $o], [DB::table('journal_entries')->count(), DB::table('ledger_outbox')->count()]);
    }

    public function test_when_the_journal_step_fails_after_the_business_commit_the_change_stays_queued_and_the_command_completes_it_once()
    {
        $cash = $this->acct('asset', 'cash'); $exp = $this->acct('expense', 'expense', 0);
        $entries = DB::table('journal_entries')->count();
        $this->failingJournal(false);
        $this->legacyPayment($cash, $exp); // the business transaction commits; the journal step throws and is swallowed
        $this->assertEquals(960.0, (float) $cash->fresh()->current_balance, 'the business change committed');
        $pending = DB::table('ledger_outbox')->whereIn('account_id', [$cash->id, $exp->id])->whereNull('processed_at')->get();
        $this->assertCount(2, $pending, 'the committed change is queued, not lost');
        $this->assertSame($entries, DB::table('journal_entries')->count(), 'no entry yet');
        $this->assertStringContainsString('journal unavailable', (string) $pending->first()->last_error);
        $this->assertSame('medium', collect((new AccountingReconciliation())->checks())->firstWhere('key', 'outbox_pending')['severity'], 'reconciliation shows what is waiting');

        $this->restoreJournal(); LegacyLedgerBridge::reset();
        $this->artisan('ledger:process-outbox')->assertExitCode(0);
        $this->assertSame($entries + 1, DB::table('journal_entries')->count(), 'exactly one entry after recovery');
        $this->assertSame(0, DB::table('ledger_outbox')->whereIn('account_id', [$cash->id, $exp->id])->whereNull('processed_at')->count());
        $this->artisan('ledger:process-outbox')->assertExitCode(0);
        $this->artisan('ledger:process-outbox')->assertExitCode(0);
        $this->assertSame($entries + 1, DB::table('journal_entries')->count(), 'running the recovery again posts nothing twice');
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
    }

    public function test_a_process_that_dies_after_writing_the_entry_but_before_marking_the_rows_leaves_nothing_half_done()
    {
        $cash = $this->acct('asset', 'cash'); $exp = $this->acct('expense', 'expense', 0);
        $entries = DB::table('journal_entries')->count();
        $this->failingJournal(true);
        $this->legacyPayment($cash, $exp);
        $this->assertSame($entries, DB::table('journal_entries')->count(), 'the entry and the processed-marks share a transaction: the entry was rolled back too');
        $this->assertSame(2, DB::table('ledger_outbox')->whereIn('account_id', [$cash->id, $exp->id])->whereNull('processed_at')->count());
        $this->restoreJournal(); LegacyLedgerBridge::reset();
        $this->artisan('ledger:process-outbox')->assertExitCode(0);
        $this->assertSame($entries + 1, DB::table('journal_entries')->count());
    }

    public function test_processing_the_same_group_twice_or_from_two_workers_cannot_double_post()
    {
        $cash = $this->acct('asset', 'cash'); $exp = $this->acct('expense', 'expense', 0);
        $this->legacyPayment($cash, $exp);
        $token = DB::table('ledger_outbox')->where('account_id', $cash->id)->value('token');
        $proc = app(\App\Services\Accounting\LedgerOutboxProcessor::class);
        $this->assertNull($proc->processToken($token), 'already processed: a second worker finds nothing to do');
        // even if the marks were lost, the journal key is derived from the token, so the entry is not duplicated
        DB::table('ledger_outbox')->where('token', $token)->update(['processed_at' => null, 'journal_entry_id' => null]);
        $before = DB::table('journal_entries')->count();
        $proc->processToken($token);
        $this->assertSame($before, DB::table('journal_entries')->count(), 'same key replayed: no second entry');
    }

    public function test_old_unprocessed_rows_make_the_reconciliation_high()
    {
        $cash = $this->acct('asset', 'cash'); $exp = $this->acct('expense', 'expense', 0);
        $this->failingJournal(false);
        $this->legacyPayment($cash, $exp);
        DB::table('ledger_outbox')->whereNull('processed_at')->update(['created_at' => now()->subMinutes(30)]);
        $this->assertSame('high', collect((new AccountingReconciliation())->checks())->firstWhere('key', 'outbox_pending')['severity']);
        $this->restoreJournal();
    }
}
