<?php

namespace App\Console\Commands;

use App\Support\TransactionalEngines;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Converts the money/stock tables that are not InnoDB to InnoDB, with the checks a financial change needs. Dry run by default.
 *
 * Safety: --execute needs --confirm=<exact database name>. On a database whose name does not end in _test it also needs the application
 * to be in maintenance mode (php artisan down) and --plan=<reference of the approved operational plan>. Row counts and AUTO_INCREMENT are
 * compared before and after and a difference stops the run with restore instructions. Nothing is dropped, nothing is deleted, no data is changed:
 * only ALTER TABLE ... ENGINE=InnoDB (ALGORITHM=COPY, LOCK=SHARED: reads continue, writes wait). --revert (back to MyISAM, rollback rehearsal)
 * is allowed on *_test databases only; on a real database the rollback is the restore of the pre-window backup.
 */
class ConvertEngines extends Command
{
    protected $signature = 'db:convert-engines {--execute} {--revert} {--confirm=} {--plan=} {--tables=} {--report=}';
    protected $description = 'Convert non-InnoDB money/stock tables to InnoDB (dry run unless --execute --confirm=<database>)';

    public function handle(): int
    {
        $db = DB::getDatabaseName();
        $isTest = str_ends_with($db, '_test');
        $revert = (bool) $this->option('revert');
        $execute = (bool) $this->option('execute');
        $allowed = TransactionalEngines::tables();
        $wanted = $this->option('tables') ? array_filter(array_map('trim', explode(',', (string) $this->option('tables')))) : null;
        if ($wanted !== null && ($bad = array_diff($wanted, $allowed))) {
            $this->error('Refused: not in the money/stock table list: ' . implode(', ', $bad));

            return self::FAILURE;
        }
        $target = $revert ? 'MyISAM' : 'InnoDB';
        if ($revert && !$isTest) {
            $this->error("Refused: --revert is allowed on *_test databases only. On '$db' the rollback is the restore of the pre-window backup.");

            return self::FAILURE;
        }
        $engines = $this->engines($wanted ?? $allowed);
        $todo = array_keys(array_filter($engines, fn ($e) => $revert ? strtoupper($e) === 'INNODB' : strtoupper($e) !== 'INNODB'));
        if ($revert && $wanted === null) {
            $this->error('Refused: --revert needs --tables= (never reverts the whole list).');

            return self::FAILURE;
        }
        $this->line("database=$db  target engine=$target  tables to change: " . ($todo ? implode(', ', $todo) : '(none)'));
        if (!$todo) {
            $this->info('Nothing to do.');

            return self::SUCCESS;
        }
        $before = array_map(fn ($t) => $this->figures($t), array_combine($todo, $todo));
        foreach ($before as $t => $f) {
            $this->line(sprintf('  %-26s engine=%-7s rows=%-8d data+index=%.2f MB auto_increment=%s', $t, $engines[$t], $f['rows'], $f['mb'], $f['ai'] ?? '-'));
        }
        if (!$execute) {
            $this->warn('DRY RUN: nothing changed. To run: back up, (real database: php artisan down), then add --execute --confirm=' . $db . ($isTest ? '' : ' --plan=<approved plan reference>'));

            return self::SUCCESS;
        }
        if ((string) $this->option('confirm') !== $db) {
            $this->error("Refused: --confirm must repeat the database name exactly ('$db').");

            return self::FAILURE;
        }
        if (!$isTest) {
            if (!app()->isDownForMaintenance()) {
                $this->error('Refused: put the application in maintenance mode first (php artisan down) so nothing writes while tables are copied.');

                return self::FAILURE;
            }
            if (trim((string) $this->option('plan')) === '') {
                $this->error('Refused: a real database needs --plan=<reference of the approved operational plan>.');

                return self::FAILURE;
            }
            $this->line('plan reference: ' . $this->option('plan'));
        }
        $report = ['database' => $db, 'target' => $target, 'plan' => $this->option('plan'), 'started' => now()->toIso8601String(), 'tables' => []];
        foreach ($todo as $t) {
            $started = microtime(true);
            try {
                DB::statement("ALTER TABLE `$t` ENGINE=$target, ALGORITHM=COPY, LOCK=SHARED");
            } catch (\Throwable $e) {
                $this->error("FAILED on $t: " . $e->getMessage() . ' - the table is unchanged (the copy is atomic); the tables converted before it stay converted. Fix the cause and re-run; the command skips tables already done.');
                $report['failed'] = $t;
                $this->save($report);

                return self::FAILURE;
            }
            $secs = round(microtime(true) - $started, 3);
            $after = $this->figures($t);
            $same = $after['rows'] === $before[$t]['rows'] && ($after['ai'] ?? null) == ($before[$t]['ai'] ?? null);
            $engineNow = $this->engines([$t])[$t] ?? '?';
            $report['tables'][$t] = ['seconds' => $secs, 'rows_before' => $before[$t]['rows'], 'rows_after' => $after['rows'], 'auto_increment_before' => $before[$t]['ai'] ?? null, 'auto_increment_after' => $after['ai'] ?? null, 'engine_after' => $engineNow];
            $this->line(sprintf('  %-26s -> %s in %ss rows %d -> %d', $t, $engineNow, $secs, $before[$t]['rows'], $after['rows']));
            if (!$same || strtoupper($engineNow) !== strtoupper($target)) {
                $this->error("VALIDATION FAILED on $t (rows or AUTO_INCREMENT changed, or engine is $engineNow). Stop; restore the pre-window backup.");
                $this->save($report + ['validation_failed' => $t]);
                TransactionalEngines::forget();

                return self::FAILURE;
            }
        }
        TransactionalEngines::forget();
        \App\Services\Inventory\StockLedger::forgetEngineCache();
        $report['finished'] = now()->toIso8601String();
        $this->save($report);
        $this->info('DONE: ' . count($todo) . " table(s) converted to $target; row counts and AUTO_INCREMENT unchanged. Next: ANALYZE TABLE, db:engine-preflight, reconciliation, smoke tests.");

        return self::SUCCESS;
    }

    /** @return array<string,string> */
    private function engines(array $tables): array
    {
        $out = [];
        foreach (DB::select("SELECT TABLE_NAME AS t, ENGINE AS e FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME IN (" . implode(',', array_fill(0, count($tables), '?')) . ')', array_values($tables)) as $r) {
            $out[$r->t] = (string) $r->e;
        }
        ksort($out);

        return $out;
    }

    private function figures(string $t): array
    {
        try {
            DB::statement('SET SESSION information_schema_stats_expiry = 0'); // MySQL 8 caches table statistics for 24 h; MariaDB has no such variable
        } catch (\Throwable $e) {
        }
        $rows = (int) DB::selectOne("SELECT COUNT(*) AS c FROM `$t`")->c;
        $i = DB::selectOne('SELECT AUTO_INCREMENT AS ai, (DATA_LENGTH + INDEX_LENGTH) / 1048576 AS mb FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$t]);

        return ['rows' => $rows, 'ai' => $i->ai ?? null, 'mb' => (float) ($i->mb ?? 0)];
    }

    private function save(array $report): void
    {
        if ($file = $this->option('report')) {
            file_put_contents($file, json_encode($report, JSON_PRETTY_PRINT));
        }
    }
}
