<?php

namespace Tests\Feature\Inventory;

use App\Console\Commands\AutoDepositFunds;
use App\Models\ChartOfAccount;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Guard G1 (owner decisions AD-1.1 = option C, AD-1.2): while approval is pending the scheduled auto-deposit command reports that it is
 * paused and returns BEFORE any database access: no transaction, no lock, no query, no row, no balance movement.
 */
class AutoDepositPausedGuardTest extends InventoryV2TestCase
{
    private function fundedAutoMethod(): array
    {
        $mk = fn (string $sub, float $bal) => ChartOfAccount::create(['code' => 'AD-' . uniqid(), 'name' => 'AD ' . uniqid(), 'type' => 'asset', 'sub_type' => $sub, 'opening_balance' => 0, 'current_balance' => $bal, 'status' => 'active', 'is_deleted' => 0]);
        $source = $mk('cash', 300);
        $bank = $mk('bank', 0);
        DB::table('transaction_methods')->insert(['name' => 'G1 ' . uniqid(), 'type' => 'mobile', 'deposit_type' => 'auto', 'charge' => 0, 'bank_account_id' => $bank->id, 'source_account_id' => $source->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return [$source, $bank];
    }

    public function test_the_paused_command_opens_no_transaction_runs_no_query_and_moves_nothing()
    {
        [$source, $bank] = $this->fundedAutoMethod(); // an active, funded auto method exists: without the guard money would be attempted
        config(['accounts.auto_deposit_approved' => false]);
        $transfers = DB::table('balance_transfers')->count();
        $logs = DB::table('account_statement_logs')->count();

        $began = 0;
        $queries = [];
        Event::listen(TransactionBeginning::class, function () use (&$began) {
            $began++;
        });
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });
        Log::shouldReceive('warning')->once()->withArgs(fn ($m) => str_contains($m, 'AUTO_DEPOSIT_PAUSED_AD1') && str_contains($m, 'AD-1'));

        $code = Artisan::call('accounts:auto-deposit');
        $out = Artisan::output();

        $this->assertSame(0, $code, 'paused by decision is the intended state, not a failure');
        $this->assertStringContainsString('AUTO_DEPOSIT_PAUSED_AD1', $out);
        $this->assertStringContainsString('PAUSED pending owner approval', $out);
        $this->assertStringContainsString('No transaction was opened', $out);
        $this->assertStringContainsString('Balance Transfers', $out, 'tells the operator what to do instead');
        $this->assertStringContainsString('T5_AD1_AUTO_DEPOSIT_OPTIONS.md', $out, 'and where the decision is');
        $this->assertSame(0, $began, 'no transaction (or savepoint) began');
        $this->assertSame([], $queries, 'not a single query: the guard returns before any database access');
        $this->assertSame($transfers, DB::table('balance_transfers')->count());
        $this->assertSame($logs, DB::table('account_statement_logs')->count());
        $this->assertEquals([300.0, 0.0], [(float) $source->fresh()->current_balance, (float) $bank->fresh()->current_balance]);
    }

    public function test_repeated_runs_stay_paused_and_the_default_configuration_is_paused()
    {
        $this->fundedAutoMethod();
        $this->assertFalse(config('accounts.auto_deposit_approved'), 'the shipped default is paused');
        $before = DB::table('balance_transfers')->count();
        foreach (range(1, 5) as $i) {
            $this->assertSame(0, Artisan::call('accounts:auto-deposit'));
            $this->assertStringContainsString(AutoDepositFunds::PAUSED_TAG, Artisan::output());
        }
        $this->assertSame($before, DB::table('balance_transfers')->count());
    }

    public function test_the_guard_is_the_only_thing_holding_it_back_and_the_schedule_and_schema_are_untouched()
    {
        [$source] = $this->fundedAutoMethod();
        // approved (a disposable-database test setting): the command now reaches the database and tries (and fails on NOT NULL created_by, option C leaves the schema alone)
        config(['accounts.auto_deposit_approved' => true]);
        $began = 0;
        Event::listen(TransactionBeginning::class, function () use (&$began) {
            $began++;
        });
        Artisan::call('accounts:auto-deposit');
        $this->assertGreaterThan(0, $began, 'without the guard a transaction is opened');
        $this->assertStringNotContainsString('PAUSED', Artisan::output());

        // the schedule line is still registered, unchanged (G1 is inside the command, as approved)
        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'accounts:auto-deposit'));
        $this->assertCount(1, $events);
        $this->assertTrue($events->first()->withoutOverlapping, 'withoutOverlapping kept');

        // schema untouched: created_by is still NOT NULL
        $this->assertSame('NO', DB::selectOne("SELECT IS_NULLABLE AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'balance_transfers' AND COLUMN_NAME = 'created_by'")->n);
    }
}
