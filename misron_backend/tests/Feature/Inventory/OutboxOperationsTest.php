<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\LegacyLedgerBridge;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** A. Scheduling, alerting and two-way traceability of the outbox. */
class OutboxOperationsTest extends InventoryV2TestCase
{
    private function acct(string $type, string $sub, float $bal): ChartOfAccount
    {
        return ChartOfAccount::create(['code' => 'OP-' . uniqid(), 'name' => 'OP ' . uniqid(), 'type' => $type, 'sub_type' => $sub, 'opening_balance' => 0, 'current_balance' => $bal, 'status' => 'active', 'is_deleted' => 0]);
    }

    private function payment(ChartOfAccount $cash, ChartOfAccount $exp): void
    {
        LegacyLedgerBridge::$sourceOverride = ['legacy:payments.store', 4242, 'Legacy posting: payments.store #4242'];
        DB::transaction(function () use ($cash, $exp) {
            ChartOfAccount::where('id', $cash->id)->decrement('current_balance', 25);
            ChartOfAccount::where('id', $exp->id)->increment('current_balance', 25);
        });
    }

    private function breakJournal(): void
    {
        $this->app->instance(JournalService::class, new class extends JournalService {
            public function postMirrored(string $key, string $date, array $lines, array $mirrored, ?string $sourceType, ?int $sourceId, ?string $description, ?int $by, ?int $reversalOf = null): array
            {
                throw new \RuntimeException('journal down');
            }
        });
    }

    public function test_the_recovery_command_is_scheduled_every_minute_with_the_alert_option()
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'ledger:process-outbox'));
        $this->assertCount(1, $events);
        $e = $events->first();
        $this->assertStringContainsString('--alert', $e->command);
        $this->assertSame('* * * * *', $e->expression, 'every minute');
        $this->assertTrue($e->withoutOverlapping, 'one run at a time per server');
    }

    public function test_pending_over_five_minutes_raises_a_critical_log_and_a_failing_exit_code_until_it_is_recovered()
    {
        $cash = $this->acct('asset', 'cash', 500); $exp = $this->acct('expense', 'expense', 0);
        $this->breakJournal();
        $this->payment($cash, $exp);
        DB::table('ledger_outbox')->whereNull('processed_at')->update(['created_at' => now()->subMinutes(7)]);

        Log::spy();
        $this->artisan('ledger:process-outbox', ['--alert' => true])->expectsOutputToContain('ALERT')->assertExitCode(2);
        Log::shouldHaveReceived('critical')->atLeast()->once();

        $this->app->forgetInstance(JournalService::class); LegacyLedgerBridge::reset();
        $this->artisan('ledger:process-outbox', ['--alert' => true])->assertExitCode(0);
        $this->assertSame(0, DB::table('ledger_outbox')->whereNull('processed_at')->count());
    }

    public function test_fresh_pending_rows_do_not_alert()
    {
        $cash = $this->acct('asset', 'cash', 500); $exp = $this->acct('expense', 'expense', 0);
        $this->breakJournal();
        $this->payment($cash, $exp);
        $this->app->forgetInstance(JournalService::class);
        $this->artisan('ledger:process-outbox', ['--alert' => true, '--status' => true])->assertExitCode(0);
    }

    public function test_a_posting_can_be_traced_from_the_business_transaction_to_the_journal_and_back()
    {
        $cash = $this->acct('asset', 'cash', 500); $exp = $this->acct('expense', 'expense', 0);
        $this->payment($cash, $exp);

        // forward: outbox group (the business transaction) -> journal entry -> lines
        $rows = DB::table('ledger_outbox')->whereIn('account_id', [$cash->id, $exp->id])->get();
        $token = $rows->first()->token;
        $entryId = $rows->first()->journal_entry_id;
        $this->assertNotNull($entryId);
        $entry = DB::table('journal_entries')->find($entryId);
        $this->assertSame('legacy:' . $token, $entry->idempotency_key, 'the entry key names the business-transaction group');
        $this->assertSame(['legacy:payments.store', 4242], [$entry->source_type, (int) $entry->source_id], 'and the document that caused it');
        $lines = DB::table('journal_lines')->where('journal_entry_id', $entryId)->get();
        $this->assertEquals(0.0, (float) ($lines->sum('debit') - $lines->sum('credit')));
        $this->assertEquals(25.0, (float) $lines->where('account_id', $exp->id)->sum('debit'));

        // backward: entry -> group token -> every outbox row (account and amount) that produced it
        $back = DB::table('ledger_outbox')->where('token', substr($entry->idempotency_key, strlen('legacy:')))->orderBy('id')->get();
        $this->assertEqualsCanonicalizing([-25.0, 25.0], $back->map(fn ($r) => (float) $r->delta)->all());
        $this->assertSame(1, $back->pluck('journal_entry_id')->unique()->count());
        $this->assertSame((int) $entryId, (int) $back->first()->journal_entry_id);
        $this->assertSame([4242], $back->pluck('source_id')->unique()->map(fn ($v) => (int) $v)->all());
    }
}
