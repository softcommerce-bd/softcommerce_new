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

    public function test_the_switch_alone_is_not_enough_the_job_refuses_without_a_prepared_database_and_the_schedule_and_schema_are_untouched()
    {
        $this->fundedAutoMethod();
        config(['accounts.auto_deposit_approved' => true, 'accounts.auto_deposit_user_id' => null]);
        $began = 0;
        $transfers = DB::table('balance_transfers')->count();
        Event::listen(TransactionBeginning::class, function () use (&$began) {
            $began++;
        });
        $code = Artisan::call('accounts:auto-deposit');
        $out = Artisan::output();
        $this->assertSame(1, $code, 'switched on but not ready is a failure the scheduler reports');
        $this->assertStringContainsString(AutoDepositFunds::BLOCKED_TAG, $out);
        $this->assertStringNotContainsString('PAUSED', $out);
        $this->assertSame(0, $began, 'no transaction was opened');
        $this->assertSame($transfers, DB::table('balance_transfers')->count());

        // the schedule line is still registered, unchanged
        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'accounts:auto-deposit'));
        $this->assertCount(1, $events);
        $this->assertTrue($events->first()->withoutOverlapping, 'withoutOverlapping kept');
        // schema untouched: created_by is still NOT NULL
        $this->assertSame('NO', DB::selectOne("SELECT IS_NULLABLE AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'balance_transfers' AND COLUMN_NAME = 'created_by'")->n);
    }

    /** S8: nothing in the repository (deploy files, config, env templates) turns the switch on. */
    public function test_no_repository_file_sets_the_switch_or_the_system_user()
    {
        $hits = [];
        $root = base_path();
        $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), function ($f) {
            return !in_array($f->getFilename(), ['vendor', 'node_modules', 'storage', '.git', 'docs', 'tests'], true);
        }));
        foreach ($it as $f) {
            if (!$f->isFile() || $f->getSize() > 2_000_000 || !preg_match('/(\.env.*|\.ya?ml|\.xml|\.json|\.sh|\.ini|Dockerfile|\.conf)$/', $f->getFilename())) {
                continue;
            }
            if (preg_match('/AUTO_DEPOSIT_APPROVED\s*[=:]\s*["\']?(true|1|yes|on)/i', (string) file_get_contents($f->getPathname()))) {
                $hits[] = str_replace($root . '/', '', $f->getPathname());
            }
        }
        $this->assertSame([], $hits, 'no deploy/config file enables the auto-deposit switch');
        $this->assertFalse((bool) (include base_path('config/accounts.php'))['auto_deposit_approved'] && getenv('AUTO_DEPOSIT_APPROVED') === false, 'default false');
    }
}
