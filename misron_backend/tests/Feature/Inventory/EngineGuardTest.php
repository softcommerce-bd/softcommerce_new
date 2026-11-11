<?php

namespace Tests\Feature\Inventory;

use App\Http\Middleware\RequireTransactionalEngine;
use App\Support\DeadlockRetry;
use App\Support\TransactionalEngines;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R-01: money/stock tables on MyISAM. DDL is involved (implicit commits), so this test is NOT wrapped in a transaction; it only ever creates and
 * drops its own scratch tables (t5_eng_*) in the *_test database and points the guard at them.
 */
class EngineGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!str_ends_with(DB::getDatabaseName(), '_test')) {
            $this->fail('EngineGuardTest runs on *_test databases only.');
        }
        $this->dropScratch();
        TransactionalEngines::$tablesOverride = ['t5_eng_my', 't5_eng_in'];
        TransactionalEngines::$purchasingOverride = ['t5_eng_my'];
        TransactionalEngines::$forceSafe = null;
        TransactionalEngines::forget();
        config(['inventory.financial_engine_policy' => 'enforce']);
    }

    protected function tearDown(): void
    {
        $this->dropScratch();
        TransactionalEngines::$tablesOverride = null;
        TransactionalEngines::$purchasingOverride = null;
        TransactionalEngines::$forceSafe = null;
        TransactionalEngines::forget();
        DeadlockRetry::$baseLevel = 0;
        parent::tearDown();
    }

    private function dropScratch(): void
    {
        DB::statement('DROP TABLE IF EXISTS t5_eng_my');
        DB::statement('DROP TABLE IF EXISTS t5_eng_in');
    }

    private function scratch(string $myEngine = 'MyISAM'): void
    {
        $this->dropScratch();
        DB::statement("CREATE TABLE t5_eng_my (id INT AUTO_INCREMENT PRIMARY KEY, v VARCHAR(20)) ENGINE=$myEngine");
        DB::statement('CREATE TABLE t5_eng_in (id INT AUTO_INCREMENT PRIMARY KEY, v VARCHAR(20)) ENGINE=InnoDB');
        TransactionalEngines::forget();
    }

    private function deadlock(): QueryException
    {
        return new QueryException('update x', [], new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock'));
    }

    /** one request-like unit: writes the MyISAM table, then the InnoDB one, and dies as a deadlock victim on the first attempt */
    private function unit(): callable
    {
        return function (int $attempt) {
            DB::transaction(function () use ($attempt) {
                DB::table('t5_eng_my')->insert(['v' => "a$attempt"]);
                DB::table('t5_eng_in')->insert(['v' => "a$attempt"]);
                if ($attempt === 1) {
                    throw $this->deadlock();
                }
            });

            return 'done';
        };
    }

    public function test_the_guard_sees_myisam_and_reports_it()
    {
        $this->scratch();
        $this->assertSame(['t5_eng_my' => 'MyISAM'], TransactionalEngines::offenders());
        $this->assertFalse(TransactionalEngines::isSafe());
        $this->assertFalse(TransactionalEngines::retryAllowed());
        $this->assertSame(1, Artisan::call('db:engine-preflight', ['--json' => true]));
        $json = json_decode(Artisan::output(), true);
        $this->assertFalse($json['ok']);
        $this->assertSame(['t5_eng_my' => 'MyISAM'], $json['non_innodb']);
        $this->assertFalse($json['deadlock_retry_allowed']);
        DB::statement('ALTER TABLE t5_eng_my ENGINE=InnoDB');
        TransactionalEngines::forget();
        $this->assertSame(0, Artisan::call('db:engine-preflight', ['--json' => true]));
        $this->assertTrue(TransactionalEngines::isSafe());
    }

    /** The hazard of NB-1, shown and then prevented. */
    public function test_a_deadlock_retry_would_repeat_the_myisam_write_and_the_guard_prevents_it()
    {
        // (1) the hazard itself: guard bypassed in the test, MyISAM table -> the retry doubles the MyISAM write
        $this->scratch();
        TransactionalEngines::$forceSafe = true;
        $this->assertSame('done', DeadlockRetry::run($this->unit()));
        $this->assertSame(2, DB::table('t5_eng_my')->count(), 'MyISAM kept the rolled-back attempt AND got the retry: duplicated');
        $this->assertSame(1, DB::table('t5_eng_in')->count(), 'InnoDB rolled back attempt 1 and got attempt 2 once');

        // (2) the guard: no retry on MyISAM; the failure surfaces, nothing is repeated
        $this->scratch();
        TransactionalEngines::$forceSafe = null;
        try {
            DeadlockRetry::run($this->unit());
            $this->fail('with a non-transactional table the deadlock must surface, not be retried');
        } catch (QueryException $e) {
            $this->assertTrue(DeadlockRetry::isDeadlock($e));
        }
        $this->assertSame(1, DB::table('t5_eng_my')->count(), 'one (unrolled) write, never two');
        $this->assertSame(0, DB::table('t5_eng_in')->count());

        // (3) after conversion the retry is allowed and exact
        $this->scratch('InnoDB');
        $this->assertSame('done', DeadlockRetry::run($this->unit()));
        $this->assertSame(1, DB::table('t5_eng_my')->count());
        $this->assertSame(1, DB::table('t5_eng_in')->count());
    }

    public function test_the_purchasing_gate_refuses_warns_or_passes_by_policy()
    {
        $this->scratch();
        $mw = new RequireTransactionalEngine();
        $next = fn () => response('ran', 200);
        $post = fn () => Request::create('/admin/purchase/store', 'POST');

        $r = $mw->handle(Request::create('/x', 'GET'), $next);
        $this->assertSame('ran', $r->getContent(), 'reads always pass');

        config(['inventory.financial_engine_policy' => 'enforce']);
        $json = Request::create('/admin/purchase/store', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $r = $mw->handle($json, $next);
        $this->assertSame(503, $r->getStatusCode());
        $this->assertTrue(json_decode($r->getContent(), true)['engine_gate']);
        $this->assertStringContainsString('t5_eng_my=MyISAM', $r->getContent());

        config(['inventory.financial_engine_policy' => 'warn']);
        $r = $mw->handle($post(), $next);
        $this->assertSame('ran', $r->getContent());
        $this->assertSame('purchasing tables are not InnoDB', $r->headers->get('X-Engine-Warning'));

        config(['inventory.financial_engine_policy' => 'off']);
        $this->assertSame('ran', $mw->handle($post(), $next)->getContent());

        config(['inventory.financial_engine_policy' => 'enforce']);
        DB::statement('ALTER TABLE t5_eng_my ENGINE=InnoDB');
        TransactionalEngines::forget();
        $this->assertSame('ran', $mw->handle($json, $next)->getContent(), 'InnoDB: open');
    }

    public function test_the_gate_is_attached_to_the_real_purchasing_routes_and_not_to_selling_routes()
    {
        $routes = app('router')->getRoutes();
        foreach (['purchase.store', 'purchase.payment.update', 'purchase-return.store'] as $name) {
            $this->assertContains('engine.safe', $routes->getByName($name)->middleware(), "$name is gated");
            $this->assertContains('retry.deadlock', $routes->getByName($name)->middleware());
        }
        $this->assertNotContains('engine.safe', $routes->getByName('pos.order_place')->middleware(), 'selling paths are not refused (StockLedger compensates)');
        $gated = 0;
        foreach ($routes->getRoutes() as $r) {
            if (in_array('engine.safe', (array) $r->middleware(), true) && array_diff($r->methods(), ['GET', 'HEAD'])) {
                $gated++;
            }
        }
        $this->assertGreaterThan(10, $gated);
    }

    public function test_conversion_command_dry_run_refusals_execute_validation_and_revert()
    {
        $this->scratch();
        DB::table('t5_eng_my')->insert([['v' => 'x'], ['v' => 'y'], ['v' => 'z']]);
        DB::table('t5_eng_my')->where('v', 'z')->delete(); // AUTO_INCREMENT stays at 4
        $db = DB::getDatabaseName();

        $this->assertSame(0, Artisan::call('db:convert-engines'));
        $this->assertStringContainsString('DRY RUN', Artisan::output());
        $this->assertSame('MyISAM', DB::selectOne("SELECT ENGINE AS e FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't5_eng_my'")->e);

        $this->assertSame(1, Artisan::call('db:convert-engines', ['--execute' => true, '--confirm' => 'wrong']));
        $this->assertStringContainsString('--confirm must repeat the database name', Artisan::output());
        $this->assertSame(1, Artisan::call('db:convert-engines', ['--tables' => 'users']));
        $this->assertStringContainsString('not in the money/stock table list', Artisan::output());
        $this->assertSame(1, Artisan::call('db:convert-engines', ['--revert' => true]));
        $this->assertStringContainsString('--revert needs --tables', Artisan::output());

        $report = tempnam(sys_get_temp_dir(), 'conv');
        $code = Artisan::call('db:convert-engines', ['--execute' => true, '--confirm' => $db, '--report' => $report]);
        $out = Artisan::output();
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('DONE: 1 table(s) converted to InnoDB', $out);
        $j = json_decode(file_get_contents($report), true);
        $this->assertSame(2, $j['tables']['t5_eng_my']['rows_after']);
        $this->assertSame('InnoDB', $j['tables']['t5_eng_my']['engine_after']);
        $this->assertEquals($j['tables']['t5_eng_my']['auto_increment_before'], $j['tables']['t5_eng_my']['auto_increment_after']);
        $this->assertSame(2, DB::table('t5_eng_my')->count());
        $this->assertSame(['x', 'y'], DB::table('t5_eng_my')->orderBy('id')->pluck('v')->all(), 'data unchanged');
        $this->assertTrue(TransactionalEngines::isSafe());
        unlink($report);

        $this->assertSame(0, Artisan::call('db:convert-engines', ['--execute' => true, '--confirm' => $db]));
        $this->assertStringContainsString('Nothing to do', Artisan::output(), 'idempotent');

        // rollback rehearsal on a test database
        $code = Artisan::call('db:convert-engines', ['--revert' => true, '--tables' => 't5_eng_my', '--execute' => true, '--confirm' => $db]);
        $this->assertSame(0, $code, Artisan::output());
        $this->assertSame('MyISAM', DB::selectOne("SELECT ENGINE AS e FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't5_eng_my'")->e);
        $this->assertSame(2, DB::table('t5_eng_my')->count());
    }

    public function test_the_built_in_table_list_is_used_by_default_and_the_preflight_reads_the_real_schema()
    {
        TransactionalEngines::$tablesOverride = null;
        TransactionalEngines::$purchasingOverride = null;
        TransactionalEngines::forget();
        $this->assertSame(TransactionalEngines::TABLES, TransactionalEngines::tables());
        $this->assertNotNull(TransactionalEngines::offenders(), 'the engines of the real schema can be read');
        $code = Artisan::call('db:engine-preflight', ['--json' => true]);
        $json = json_decode(Artisan::output(), true);
        $this->assertGreaterThan(10, $json['checked_tables']);
        $this->assertSame($json['ok'] ? 0 : 1, $code);
        $this->assertSame([], TransactionalEngines::offenders(TransactionalEngines::PURCHASING), 'the five purchasing tables are InnoDB in the test database');
    }

    public function test_the_default_policy_is_enforce_and_an_unknown_value_falls_back_to_enforce()
    {
        config(['inventory.financial_engine_policy' => 'enforce']);
        $this->assertSame('enforce', TransactionalEngines::policy());
        config(['inventory.financial_engine_policy' => 'banana']);
        $this->assertSame('enforce', TransactionalEngines::policy());
        $this->assertSame('enforce', (include base_path('config/inventory.php'))['financial_engine_policy'] === env('FINANCIAL_ENGINE_POLICY', 'enforce') ? 'enforce' : 'x');
    }
}
