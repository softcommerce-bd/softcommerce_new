<?php

namespace App\Console\Commands;

use App\Support\TransactionalEngines;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Read-only: are the money/stock tables InnoDB? Exit 1 when any is not (or the engines cannot be read). See App\Support\TransactionalEngines. */
class EnginePreflight extends Command
{
    protected $signature = 'db:engine-preflight {--json : machine-readable output}';
    protected $description = 'Read-only check that the money/stock tables are InnoDB (T-5 locking, rollback and deadlock retry need it)';

    public function handle(): int
    {
        TransactionalEngines::forget();
        $tables = TransactionalEngines::tables();
        $offenders = TransactionalEngines::offenders();
        $all = [];
        if ($offenders !== null) {
            $rows = DB::select("SELECT TABLE_NAME AS t, ENGINE AS e FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME IN (" . implode(',', array_fill(0, count($tables), '?')) . ') ORDER BY TABLE_NAME', array_values($tables));
            foreach ($rows as $r) {
                $all[$r->t] = $r->e;
            }
        }
        $purchasing = TransactionalEngines::offenders(TransactionalEngines::purchasing());
        $report = [
            'database' => DB::getDatabaseName(),
            'server' => DB::selectOne('SELECT VERSION() AS v')->v ?? null,
            'policy' => TransactionalEngines::policy(),
            'checked_tables' => count($all),
            'non_innodb' => $offenders,
            'purchasing_gate_open' => $purchasing === [],
            'deadlock_retry_allowed' => $offenders === [],
            'ok' => $offenders === [],
        ];
        if ($this->option('json')) {
            $this->line(json_encode($report + ['engines' => $all], JSON_PRETTY_PRINT));

            return $offenders === [] ? self::SUCCESS : self::FAILURE;
        }
        $this->line("database={$report['database']} server={$report['server']} policy={$report['policy']}");
        foreach ($all as $t => $e) {
            $this->line(sprintf('  %-28s %s', $t, strtoupper($e) === 'INNODB' ? $e : "$e   <-- NOT InnoDB"));
        }
        if ($offenders === null) {
            $this->error('FAIL: the engines could not be read (information_schema). Deadlock retry stays disabled.');

            return self::FAILURE;
        }
        if ($offenders === []) {
            $this->info('PASS: every checked money/stock table is InnoDB. Deadlock retry and the purchasing gate are active.');

            return self::SUCCESS;
        }
        $this->error('FAIL: ' . count($offenders) . ' money/stock table(s) are not InnoDB: ' . implode(', ', array_keys($offenders)));
        $this->line('  - deadlock retry is DISABLED (a retry would repeat writes that did not roll back)');
        $this->line('  - purchasing / supplier-balance changes are ' . ($purchasing === [] ? 'allowed' : ($report['policy'] === 'enforce' ? 'REFUSED (policy enforce)' : "allowed with a warning (policy {$report['policy']})")));
        $this->line('  - plan: back up, put the application in maintenance mode, then `php artisan db:convert-engines` (dry run), then with --execute --confirm=' . $report['database'] . ' (see docs/T5_FINAL_DEPLOYMENT_RUNBOOK.md)');

        return self::FAILURE;
    }
}
